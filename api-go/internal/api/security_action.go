package api

import (
	"context"
	"crypto/hmac"
	"crypto/sha256"
	"encoding/base64"
	"encoding/json"
	"errors"
	"fmt"
	"net/http"
	"strings"
	"time"
)

type ActionTokenClaims struct {
	Action      string `json:"action"`
	CustomerUID string `json:"customer_uid,omitempty"`
	SessionUID  string `json:"session_uid,omitempty"`
	Nonce       string `json:"nonce"`
	ExpiresAt   int64  `json:"exp"`
	IssuedAt    int64  `json:"iat"`
}

type actionTokenRequest struct {
	Action string `json:"action"`
}

var errActionTokenInvalid = errors.New("invalid action token")
var errActionTokenExpired = errors.New("expired action token")

func (app *App) handleActionToken(w http.ResponseWriter, r *http.Request) {
	ctx, cancel := withTimeout(r, 3*time.Second)
	defer cancel()
	identity := requestIdentity(r.Context())

	action := strings.TrimSpace(r.URL.Query().Get("action"))
	if action == "" && r.Method == http.MethodPost {
		var body actionTokenRequest
		_ = parseJSON(r, &body)
		action = strings.TrimSpace(body.Action)
	}
	if !isAllowedClientAction(action) {
		app.recordSecurityEvent(ctx, r, "action_token.rejected", action, "unknown_action")
		writeError(w, r, http.StatusUnprocessableEntity, "Geçerli bir işlem adı gerekli.")
		return
	}

	token, claims, err := app.signActionToken(action, identity.CustomerUID, identity.SessionUID, app.cfg.ActionTokenTTL)
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	writeData(w, http.StatusOK, map[string]any{
		"token":       token,
		"action":      claims.Action,
		"expires_at":  claims.ExpiresAt,
		"ttl_seconds": int(app.cfg.ActionTokenTTL.Seconds()),
	})
}

func (app *App) actionGuard(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		action := actionNameForRequest(r)
		if action == "" {
			next.ServeHTTP(w, r)
			return
		}

		mode := strings.ToLower(strings.TrimSpace(app.cfg.ActionTokenMode))
		if mode == "" || mode == "off" || app.cfg.ActionTokenSecret == "" {
			next.ServeHTTP(w, r)
			return
		}

		token := strings.TrimSpace(r.Header.Get("X-Action-Token"))
		if token == "" {
			app.recordSecurityEvent(r.Context(), r, "action_token.missing", action, "missing_header")
			w.Header().Set("X-Action-Token-Status", "missing")
			if mode == "enforce" {
				writeError(w, r, http.StatusForbidden, "Güvenli işlem tokenı gerekli.")
				return
			}
			next.ServeHTTP(w, r)
			return
		}

		claims, err := app.verifyActionToken(token, action, requestIdentity(r.Context()))
		if err != nil {
			app.recordSecurityEvent(r.Context(), r, "action_token.invalid", action, err.Error())
			w.Header().Set("X-Action-Token-Status", "invalid")
			if mode == "enforce" {
				writeError(w, r, http.StatusForbidden, "Güvenli işlem tokenı geçersiz veya süresi dolmuş.")
				return
			}
		} else {
			w.Header().Set("X-Action-Token-Status", "verified")
			_ = claims
		}

		next.ServeHTTP(w, r)
	})
}

func (app *App) signActionToken(action, customerUID, sessionUID string, ttl time.Duration) (string, ActionTokenClaims, error) {
	if ttl <= 0 {
		ttl = 90 * time.Second
	}
	now := time.Now().UTC()
	claims := ActionTokenClaims{
		Action:      action,
		CustomerUID: sanitizeUID(customerUID),
		SessionUID:  sanitizeUID(sessionUID),
		Nonce:       newPublicUID("act"),
		IssuedAt:    now.Unix(),
		ExpiresAt:   now.Add(ttl).Unix(),
	}
	payload, err := json.Marshal(claims)
	if err != nil {
		return "", claims, err
	}
	encodedPayload := base64.RawURLEncoding.EncodeToString(payload)
	sig := app.signActionPayload(encodedPayload)
	return encodedPayload + "." + sig, claims, nil
}

func (app *App) verifyActionToken(token, expectedAction string, identity RequestIdentity) (ActionTokenClaims, error) {
	var claims ActionTokenClaims
	parts := strings.Split(token, ".")
	if len(parts) != 2 || parts[0] == "" || parts[1] == "" {
		return claims, errActionTokenInvalid
	}
	expectedSig := app.signActionPayload(parts[0])
	if !hmac.Equal([]byte(expectedSig), []byte(parts[1])) {
		return claims, errActionTokenInvalid
	}
	payload, err := base64.RawURLEncoding.DecodeString(parts[0])
	if err != nil {
		return claims, errActionTokenInvalid
	}
	if err := json.Unmarshal(payload, &claims); err != nil {
		return claims, errActionTokenInvalid
	}
	if claims.ExpiresAt < time.Now().UTC().Unix() {
		return claims, errActionTokenExpired
	}
	if claims.Action != expectedAction {
		return claims, fmt.Errorf("action mismatch")
	}
	if claims.CustomerUID != "" && identity.CustomerUID != "" && claims.CustomerUID != identity.CustomerUID {
		return claims, fmt.Errorf("customer mismatch")
	}
	if claims.SessionUID != "" && identity.SessionUID != "" && claims.SessionUID != identity.SessionUID {
		return claims, fmt.Errorf("session mismatch")
	}
	return claims, nil
}

func (app *App) signActionPayload(encodedPayload string) string {
	mac := hmac.New(sha256.New, []byte(app.cfg.ActionTokenSecret))
	_, _ = mac.Write([]byte(encodedPayload))
	return base64.RawURLEncoding.EncodeToString(mac.Sum(nil))
}

func actionNameForRequest(r *http.Request) string {
	path := r.URL.Path
	switch {
	case r.Method == http.MethodPost && path == "/api/v1/cart/items":
		return "cart.add"
	case r.Method == http.MethodPatch && strings.HasPrefix(path, "/api/v1/cart/items/"):
		return "cart.update"
	case r.Method == http.MethodDelete && strings.HasPrefix(path, "/api/v1/cart/items/"):
		return "cart.delete"
	case r.Method == http.MethodDelete && path == "/api/v1/cart":
		return "cart.clear"
	case r.Method == http.MethodPost && path == "/api/v1/cart/coupon":
		return "coupon.apply"
	case r.Method == http.MethodDelete && path == "/api/v1/cart/coupon":
		return "coupon.remove"
	case r.Method == http.MethodPost && path == "/api/v1/c":
		return "checkout.start"
	case r.Method == http.MethodPut && path == "/api/v1/auth/profile":
		return "profile.update"
	case r.Method == http.MethodPatch && path == "/api/v1/auth/profile":
		return "profile.update"
	case r.Method == http.MethodDelete && strings.HasPrefix(path, "/api/v1/addresses/"):
		return "address.delete"
	case r.Method == http.MethodPost && strings.HasPrefix(path, "/api/v1/favorites/"):
		return "favorite.add"
	case r.Method == http.MethodDelete && strings.HasPrefix(path, "/api/v1/favorites/"):
		return "favorite.delete"
	case r.Method == http.MethodPost && path == "/api/v1/notifications/read-all":
		return "notification.read_all"
	case r.Method == http.MethodPost && strings.HasPrefix(path, "/api/v1/notifications/") && strings.HasSuffix(path, "/read"):
		return "notification.read"
	case r.Method == http.MethodDelete && strings.HasPrefix(path, "/api/v1/notifications/"):
		return "notification.delete"
	case r.Method == http.MethodPost && strings.Contains(path, "/reviews"):
		return "product.review"
	case r.Method == http.MethodPost && strings.Contains(path, "/stock-alert"):
		return "product.stock_alert"
	case r.Method == http.MethodPost && strings.HasPrefix(path, "/api/v1/orders/") && strings.HasSuffix(path, "/reorder"):
		return "order.reorder"
	default:
		return ""
	}
}

func isAllowedClientAction(action string) bool {
	switch action {
	case "cart.add", "cart.update", "cart.delete", "cart.clear", "coupon.apply", "coupon.remove", "checkout.start", "profile.update", "address.delete", "favorite.add", "favorite.delete", "notification.read", "notification.read_all", "notification.delete", "product.review", "product.stock_alert", "order.reorder":
		return true
	default:
		return false
	}
}

func (app *App) recordSecurityEvent(ctx context.Context, r *http.Request, eventType, action, reason string) {
	if app.db == nil {
		return
	}
	identity := requestIdentity(r.Context())
	requestID, _ := r.Context().Value(contextKeyRequestID).(string)
	metadata, _ := json.Marshal(map[string]any{
		"method":     r.Method,
		"path":       r.URL.Path,
		"action":     action,
		"reason":     reason,
		"user_agent": r.UserAgent(),
	})
	_, _ = app.db.ExecContext(ctx, `INSERT INTO api_security_events
		(tenant_id, event_type, severity, customer_uid, session_uid, ip, route, request_id, metadata, created_at)
		VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())`,
		app.cfg.TenantID,
		eventType,
		"medium",
		nullableString(identity.CustomerUID),
		nullableString(identity.SessionUID),
		clientIP(r),
		r.URL.Path,
		nullableString(requestID),
		string(metadata),
	)
}
