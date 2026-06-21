package api

import (
	"context"
	"database/sql"
	"encoding/json"
	"net/http"
	"strings"
	"time"
)

const (
	customerUIDCookie = "kgm_uid"
	sessionUIDCookie  = "kgm_sid"
	authTokenCookie   = "kgm_auth"
)

const (
	contextKeyCustomerUID contextKey = "customer_uid"
	contextKeySessionUID  contextKey = "session_uid"
)

type RequestIdentity struct {
	CustomerUID string `json:"customer_uid"`
	SessionUID  string `json:"session_uid"`
	Source      string `json:"source"`
}

func (app *App) ensureRequestIdentity(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		customerUID := firstNonEmpty(
			sanitizeUID(r.Header.Get("X-Customer-UID")),
			sanitizeUID(cookieValue(r, customerUIDCookie)),
		)
		sessionUID := firstNonEmpty(
			sanitizeUID(r.Header.Get("X-Session-UID")),
			sanitizeUID(cookieValue(r, sessionUIDCookie)),
		)
		createdCustomer := false
		createdSession := false
		if customerUID == "" {
			customerUID = newPublicUID("cus")
			createdCustomer = true
		}
		if sessionUID == "" {
			sessionUID = newPublicUID("ses")
			createdSession = true
		}

		w.Header().Set("X-Customer-UID", customerUID)
		w.Header().Set("X-Session-UID", sessionUID)
		if createdCustomer || r.Header.Get("X-Customer-UID") != "" {
			app.writeIdentityCookie(w, customerUIDCookie, customerUID, 365*24*time.Hour)
		}
		if createdSession || r.Header.Get("X-Session-UID") != "" {
			app.writeIdentityCookie(w, sessionUIDCookie, sessionUID, 30*24*time.Hour)
		}

		ctx := context.WithValue(r.Context(), contextKeyCustomerUID, customerUID)
		ctx = context.WithValue(ctx, contextKeySessionUID, sessionUID)
		go app.upsertCustomerIdentity(customerUID, sessionUID, r)
		next.ServeHTTP(w, r.WithContext(ctx))
	})
}

func (app *App) writeIdentityCookie(w http.ResponseWriter, name, value string, maxAge time.Duration) {
	cookie := &http.Cookie{
		Name:     name,
		Value:    value,
		Path:     "/",
		MaxAge:   int(maxAge.Seconds()),
		HttpOnly: false,
		Secure:   app.cfg.CookieSecure,
		SameSite: http.SameSiteLaxMode,
	}
	if app.cfg.CookieDomain != "" {
		cookie.Domain = app.cfg.CookieDomain
	}
	http.SetCookie(w, cookie)
}

func (app *App) writeAuthCookie(w http.ResponseWriter, value string, expiresAt time.Time) {
	cookie := &http.Cookie{
		Name:     authTokenCookie,
		Value:    value,
		Path:     "/",
		MaxAge:   int(time.Until(expiresAt).Seconds()),
		Expires:  expiresAt,
		HttpOnly: true,
		Secure:   app.cfg.CookieSecure,
		SameSite: http.SameSiteLaxMode,
	}
	if app.cfg.CookieDomain != "" {
		cookie.Domain = app.cfg.CookieDomain
	}
	http.SetCookie(w, cookie)
}

func (app *App) clearAuthCookie(w http.ResponseWriter) {
	cookie := &http.Cookie{
		Name:     authTokenCookie,
		Value:    "",
		Path:     "/",
		MaxAge:   -1,
		Expires:  time.Unix(1, 0),
		HttpOnly: true,
		Secure:   app.cfg.CookieSecure,
		SameSite: http.SameSiteLaxMode,
	}
	if app.cfg.CookieDomain != "" {
		cookie.Domain = app.cfg.CookieDomain
	}
	http.SetCookie(w, cookie)
}

func requestIdentity(ctx context.Context) RequestIdentity {
	customerUID, _ := ctx.Value(contextKeyCustomerUID).(string)
	sessionUID, _ := ctx.Value(contextKeySessionUID).(string)
	return RequestIdentity{CustomerUID: customerUID, SessionUID: sessionUID, Source: "go-api"}
}

func (app *App) upsertCustomerIdentity(customerUID, sessionUID string, r *http.Request) {
	if customerUID == "" || app.db == nil {
		return
	}
	ctx, cancel := context.WithTimeout(context.Background(), 3*time.Second)
	defer cancel()
	_, _ = app.db.ExecContext(ctx, `INSERT INTO customer_identities
		(tenant_id, customer_uid, session_uid, first_ip, last_ip, user_agent_hash, first_seen_at, last_seen_at, created_at, updated_at)
		VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW(), NOW(), NOW())
		ON DUPLICATE KEY UPDATE session_uid=VALUES(session_uid), last_ip=VALUES(last_ip), user_agent_hash=VALUES(user_agent_hash), last_seen_at=NOW(), updated_at=NOW()`,
		app.cfg.TenantID, customerUID, nullableString(sessionUID), clientIP(r), clientIP(r), sha256Hex(r.UserAgent()))
}

func (app *App) linkIdentityToUser(ctx context.Context, customerUID string, userID int64) {
	if customerUID == "" || userID <= 0 {
		return
	}
	_, _ = app.db.ExecContext(ctx, `UPDATE customer_identities SET user_id=?, updated_at=NOW() WHERE tenant_id=? AND customer_uid=?`, userID, app.cfg.TenantID, customerUID)
	_, _ = app.db.ExecContext(ctx, `UPDATE users SET customer_uid=COALESCE(NULLIF(customer_uid,''), ?), sync_version=?, updated_at=NOW() WHERE id=?`, customerUID, syncVersionNow(), userID)
	app.touchCustomerSync(ctx, CartIdentity{UserID: &userID, CustomerUID: &customerUID}, "identity.linked")
}

func (app *App) recordIdentityEvent(ctx context.Context, eventName string, id CartIdentity, extra map[string]any) {
	identity := requestIdentity(ctx)
	customerUID := firstNonEmpty(identity.CustomerUID, derefString(id.CustomerUID))
	if customerUID == "" && id.UserID == nil {
		return
	}
	payload := map[string]any{}
	for k, v := range extra {
		payload[k] = v
	}
	payloadJSON, _ := json.Marshal(payload)
	requestID, _ := ctx.Value(contextKeyRequestID).(string)
	_, _ = app.db.ExecContext(ctx, `INSERT INTO customer_identity_events
		(tenant_id, customer_uid, session_uid, user_id, event_name, cart_token, request_id, metadata, created_at)
		VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())`, app.cfg.TenantID, nullableString(customerUID), nullableString(identity.SessionUID), sqlNullInt64Ptr(id.UserID), eventName, sqlNullStringPtr(id.CartToken), nullableString(requestID), string(payloadJSON))
}

func (app *App) activeCartToken(ctx context.Context, customerUID string) (string, error) {
	if customerUID == "" {
		return "", nil
	}
	var token string
	err := app.db.QueryRowContext(ctx, `SELECT cart_token FROM customer_active_carts WHERE tenant_id=? AND customer_uid=? LIMIT 1`, app.cfg.TenantID, customerUID).Scan(&token)
	if err == nil {
		return token, nil
	}
	if err == sql.ErrNoRows {
		return "", nil
	}
	return "", err
}

func (app *App) upsertActiveCart(ctx context.Context, customerUID, cartToken string, userID *int64) {
	if customerUID == "" || cartToken == "" {
		return
	}
	_, _ = app.db.ExecContext(ctx, `INSERT INTO customer_active_carts
		(tenant_id, customer_uid, user_id, cart_token, last_seen_at, created_at, updated_at)
		VALUES (?, ?, ?, ?, NOW(), NOW(), NOW())
		ON DUPLICATE KEY UPDATE user_id=VALUES(user_id), cart_token=VALUES(cart_token), last_seen_at=NOW(), updated_at=NOW()`, app.cfg.TenantID, customerUID, sqlNullInt64Ptr(userID), cartToken)
}

func (app *App) touchCustomerSync(ctx context.Context, id CartIdentity, reason string) int64 {
	version := syncVersionNow()
	if id.UserID != nil {
		_, _ = app.db.ExecContext(ctx, `UPDATE users SET sync_version=?, updated_at=NOW() WHERE id=?`, version, *id.UserID)
		_, _ = app.db.ExecContext(ctx, `INSERT INTO customer_sync_versions
			(tenant_id, user_id, customer_uid, scope, version, reason, updated_at, created_at)
			VALUES (?, ?, ?, 'customer', ?, ?, NOW(), NOW())
			ON DUPLICATE KEY UPDATE customer_uid=VALUES(customer_uid), version=VALUES(version), reason=VALUES(reason), updated_at=NOW()`,
			app.cfg.TenantID, *id.UserID, sqlNullStringPtr(id.CustomerUID), version, reason)
	}
	if id.CustomerUID != nil && *id.CustomerUID != "" {
		_, _ = app.db.ExecContext(ctx, `INSERT INTO customer_sync_versions
			(tenant_id, user_id, customer_uid, scope, version, reason, updated_at, created_at)
			VALUES (?, ?, ?, 'customer', ?, ?, NOW(), NOW())
			ON DUPLICATE KEY UPDATE user_id=COALESCE(VALUES(user_id), user_id), version=VALUES(version), reason=VALUES(reason), updated_at=NOW()`,
			app.cfg.TenantID, sqlNullInt64Ptr(id.UserID), *id.CustomerUID, version, reason)
	}
	return version
}

func syncVersionNow() int64 { return time.Now().UTC().UnixMicro() }

func newPublicUID(prefix string) string {
	token, err := newToken()
	if err != nil {
		return prefix + "_" + randomHex(12)
	}
	if len(token) > 22 {
		token = token[:22]
	}
	return prefix + "_" + token
}

func sanitizeUID(value string) string {
	value = strings.TrimSpace(value)
	if len(value) > 64 {
		value = value[:64]
	}
	var b strings.Builder
	for _, ch := range value {
		if (ch >= 'a' && ch <= 'z') || (ch >= 'A' && ch <= 'Z') || (ch >= '0' && ch <= '9') || ch == '_' || ch == '-' {
			b.WriteRune(ch)
		}
	}
	return b.String()
}

func cookieValue(r *http.Request, name string) string {
	cookie, err := r.Cookie(name)
	if err != nil || cookie == nil {
		return ""
	}
	return cookie.Value
}

func firstNonEmpty(values ...string) string {
	for _, value := range values {
		if strings.TrimSpace(value) != "" {
			return strings.TrimSpace(value)
		}
	}
	return ""
}

func derefString(value *string) string {
	if value == nil {
		return ""
	}
	return *value
}

func (app *App) customerSyncVersion(ctx context.Context, id CartIdentity) int64 {
	if id.UserID != nil {
		var version int64
		if err := app.db.QueryRowContext(ctx, `SELECT sync_version FROM users WHERE id=? LIMIT 1`, *id.UserID).Scan(&version); err == nil && version > 0 {
			return version
		}
	}
	if id.CustomerUID != nil && *id.CustomerUID != "" {
		var version int64
		if err := app.db.QueryRowContext(ctx, `SELECT version FROM customer_sync_versions WHERE tenant_id=? AND customer_uid=? AND scope='customer' LIMIT 1`, app.cfg.TenantID, *id.CustomerUID).Scan(&version); err == nil && version > 0 {
			return version
		}
	}
	return 0
}
