package api

import (
	"context"
	"database/sql"
	"net/http"
	"strings"
	"time"
)

func (app *App) handleRegister(w http.ResponseWriter, r *http.Request) {
	ctx, cancel := withTimeout(r, 8*time.Second)
	defer cancel()
	var body struct {
		Name       string `json:"name"`
		Phone      string `json:"phone"`
		Password   string `json:"password"`
		Location   string `json:"location"`
		DeviceName string `json:"device_name"`
		CartToken  string `json:"cart_token"`
	}
	if err := parseJSON(r, &body); err != nil {
		writeError(w, r, http.StatusUnprocessableEntity, err.Error())
		return
	}
	phone := normalizePhone(body.Phone)
	if len([]rune(strings.TrimSpace(body.Name))) < 2 || len(phone) != 10 || !strings.HasPrefix(phone, "5") || len(body.Password) < 8 {
		writeError(w, r, http.StatusUnprocessableEntity, "Ad, telefon veya şifre bilgileri geçersiz.")
		return
	}
	hash, err := passwordHash(body.Password)
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	identity := requestIdentity(r.Context())
	publicUID := newPublicUID("usr")
	customerUID := firstNonEmpty(identity.CustomerUID, newPublicUID("cus"))
	version := syncVersionNow()
	res, err := app.db.ExecContext(ctx, `INSERT INTO users (public_uid,customer_uid,sync_version,name,phone,email,password,last_ip,last_location,last_login_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,NOW(),NOW(),NOW())`, publicUID, customerUID, version, strings.TrimSpace(body.Name), phone, nil, hash, clientIP(r), nullableString(body.Location))
	if err != nil {
		writeError(w, r, http.StatusUnprocessableEntity, "Bu telefon numarasıyla zaten bir hesap olabilir.")
		return
	}
	userID, _ := res.LastInsertId()
	if body.CartToken != "" {
		app.claimGuestCart(ctx, body.CartToken, userID)
	}
	app.linkIdentityToUser(ctx, customerUID, userID)
	if body.CartToken != "" {
		app.upsertActiveCart(ctx, customerUID, body.CartToken, &userID)
	}
	app.recordIdentityEvent(r.Context(), "auth.registered", CartIdentity{UserID: &userID, CartToken: stringPtr(body.CartToken), CustomerUID: &customerUID}, map[string]any{"phone_suffix": lastN(phone, 4)})
	token, expires, err := app.createAPIToken(ctx, userID, fallbackDeviceName(body.DeviceName))
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	user := User{ID: userID, PublicUID: &publicUID, CustomerUID: &customerUID, SyncVersion: version, Name: strings.TrimSpace(body.Name), Phone: &phone}
	app.writeAuthCookie(w, token, expires)
	writeJSON(w, http.StatusCreated, map[string]any{"user": user, "token": token, "refresh_token": token, "token_type": "Bearer", "expires_at": expires.Format(time.RFC3339)})
}

func (app *App) handleLogin(w http.ResponseWriter, r *http.Request) {
	ctx, cancel := withTimeout(r, 8*time.Second)
	defer cancel()
	var body struct {
		Phone      string `json:"phone"`
		Password   string `json:"password"`
		Location   string `json:"location"`
		DeviceName string `json:"device_name"`
		CartToken  string `json:"cart_token"`
	}
	if err := parseJSON(r, &body); err != nil {
		writeError(w, r, http.StatusUnprocessableEntity, err.Error())
		return
	}
	phone := normalizePhone(body.Phone)
	ipLimitKey := "login:ip:" + clientIP(r)
	accountLimitKey := "login:account:" + sha256Hex(phone)
	if app.loginIPLimiter != nil && !app.loginIPLimiter.Allow(ipLimitKey) {
		writeError(w, r, http.StatusTooManyRequests, "Çok fazla giriş denemesi. Lütfen daha sonra tekrar deneyin.")
		return
	}
	if app.loginAccountLimiter != nil && !app.loginAccountLimiter.Allow(accountLimitKey) {
		writeError(w, r, http.StatusTooManyRequests, "Çok fazla giriş denemesi. Lütfen daha sonra tekrar deneyin.")
		return
	}
	row := app.db.QueryRowContext(ctx, `SELECT id,public_uid,customer_uid,sync_version,
		COALESCE(loyalty_points,0),COALESCE(loyalty_points_lifetime,0),COALESCE(is_vip,0),vip_started_at,vip_expires_at,
		name,phone,email,avatar_url,email_verified_at,password FROM users WHERE phone=? LIMIT 1`, phone)
	var u User
	var publicUID, customerUID, dbPhone, email, avatar, password sql.NullString
	var verified, vipStarted, vipExpires sql.NullTime
	var isVIP sql.NullBool
	if err := row.Scan(&u.ID, &publicUID, &customerUID, &u.SyncVersion, &u.LoyaltyPoints, &u.LoyaltyPointsLifetime, &isVIP, &vipStarted, &vipExpires, &u.Name, &dbPhone, &email, &avatar, &verified, &password); err != nil {
		writeError(w, r, http.StatusUnprocessableEntity, "Telefon numarası veya şifre hatalı.")
		return
	}
	if !password.Valid || !compareBcrypt(password.String, body.Password) {
		writeError(w, r, http.StatusUnprocessableEntity, "Telefon numarası veya şifre hatalı.")
		return
	}
	if app.loginIPLimiter != nil {
		app.loginIPLimiter.Reset(ipLimitKey)
	}
	if app.loginAccountLimiter != nil {
		app.loginAccountLimiter.Reset(accountLimitKey)
	}
	u.PublicUID = ptrString(publicUID)
	u.CustomerUID = ptrString(customerUID)
	u.Phone = ptrString(dbPhone)
	u.Email = ptrString(email)
	u.AvatarURL = ptrString(avatar)
	if verified.Valid {
		u.EmailVerifiedAt = &verified.Time
	}
	applyUserVIPFields(&u, isVIP, vipStarted, vipExpires)
	identity := requestIdentity(r.Context())
	loginCustomerUID := firstNonEmpty(identity.CustomerUID, derefString(u.CustomerUID), newPublicUID("cus"))
	_, _ = app.db.ExecContext(ctx, `UPDATE users SET customer_uid=COALESCE(NULLIF(customer_uid,''), ?), last_ip=?,last_location=?,last_login_at=NOW(),updated_at=NOW() WHERE id=?`, loginCustomerUID, clientIP(r), nullableString(body.Location), u.ID)
	u.CustomerUID = &loginCustomerUID
	app.linkIdentityToUser(ctx, loginCustomerUID, u.ID)
	if body.CartToken != "" {
		app.claimGuestCart(ctx, body.CartToken, u.ID)
		app.upsertActiveCart(ctx, loginCustomerUID, body.CartToken, &u.ID)
	}
	app.recordIdentityEvent(r.Context(), "auth.logged_in", CartIdentity{UserID: &u.ID, CartToken: stringPtr(body.CartToken), CustomerUID: &loginCustomerUID}, map[string]any{"phone_suffix": lastN(phone, 4)})
	token, expires, err := app.createAPIToken(ctx, u.ID, fallbackDeviceName(body.DeviceName))
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	app.writeAuthCookie(w, token, expires)
	writeJSON(w, http.StatusOK, map[string]any{"user": u, "token": token, "refresh_token": token, "token_type": "Bearer", "expires_at": expires.Format(time.RFC3339)})
}

func (app *App) handleMe(w http.ResponseWriter, r *http.Request) {
	ctx, cancel := withTimeout(r, 4*time.Second)
	defer cancel()
	user, err := app.resolveUser(ctx, requestBearerToken(r))
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	if user == nil {
		app.handleErr(w, r, ErrUnauthorized)
		return
	}
	writeData(w, http.StatusOK, user)
}

func (app *App) handleProfileUpdate(w http.ResponseWriter, r *http.Request) {
	ctx, cancel := withTimeout(r, 6*time.Second)
	defer cancel()
	user, err := app.resolveUser(ctx, requestBearerToken(r))
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	if user == nil {
		app.handleErr(w, r, ErrUnauthorized)
		return
	}
	var body struct {
		Name  string `json:"name"`
		Email string `json:"email"`
		Phone string `json:"phone"`
	}
	if err := parseJSON(r, &body); err != nil {
		writeError(w, r, http.StatusUnprocessableEntity, err.Error())
		return
	}
	name := strings.TrimSpace(body.Name)
	email := strings.TrimSpace(body.Email)
	phone := normalizePhone(body.Phone)
	if len([]rune(name)) < 2 || len([]rune(name)) > 80 {
		writeError(w, r, http.StatusUnprocessableEntity, "Ad soyad 2-80 karakter arasında olmalıdır.")
		return
	}
	if email != "" && (!strings.Contains(email, "@") || len(email) > 120) {
		writeError(w, r, http.StatusUnprocessableEntity, "E-posta adresi geçersiz.")
		return
	}
	if phone != "" && (len(phone) != 10 || !strings.HasPrefix(phone, "5")) {
		writeError(w, r, http.StatusUnprocessableEntity, "Telefon numarası geçersiz.")
		return
	}
	version := syncVersionNow()
	_, err = app.db.ExecContext(ctx, `UPDATE users SET name=?, email=?, phone=?, sync_version=?, updated_at=NOW() WHERE id=?`, name, nullableString(email), nullableString(phone), version, user.ID)
	if err != nil {
		writeError(w, r, http.StatusUnprocessableEntity, "Profil güncellenemedi. Telefon veya e-posta başka hesapta olabilir.")
		return
	}
	uid := derefString(user.CustomerUID)
	if uid == "" {
		uid = requestIdentity(r.Context()).CustomerUID
	}
	app.touchCustomerSync(ctx, CartIdentity{UserID: &user.ID, CustomerUID: stringPtr(uid)}, "profile.updated")
	app.recordIdentityEvent(r.Context(), "profile.updated", CartIdentity{UserID: &user.ID, CustomerUID: stringPtr(uid)}, map[string]any{"fields": []string{"name", "email", "phone"}})
	updated := User{ID: user.ID, PublicUID: user.PublicUID, CustomerUID: stringPtr(uid), SyncVersion: version, LoyaltyPoints: user.LoyaltyPoints, LoyaltyPointsLifetime: user.LoyaltyPointsLifetime, IsVIP: user.IsVIP, VIPStartedAt: user.VIPStartedAt, VIPExpiresAt: user.VIPExpiresAt, AdFree: user.AdFree, Name: name, Phone: stringPtr(phone), Email: stringPtr(email), AvatarURL: user.AvatarURL, EmailVerifiedAt: user.EmailVerifiedAt}
	writeData(w, http.StatusOK, updated)
}

func (app *App) handleLogout(w http.ResponseWriter, r *http.Request) {
	ctx, cancel := withTimeout(r, 4*time.Second)
	defer cancel()
	token := requestBearerToken(r)
	if token != "" {
		_, _ = app.db.ExecContext(ctx, `DELETE FROM api_tokens WHERE token_hash=?`, sha256Hex(token))
	}
	app.clearAuthCookie(w)
	writeData(w, http.StatusOK, map[string]string{"status": "ok"})
}

func (app *App) handleProviders(w http.ResponseWriter, r *http.Request) {
	writeData(w, http.StatusOK, map[string]any{"google": false, "github": false, "facebook": false})
}

func (app *App) claimGuestCart(ctx context.Context, cartToken string, userID int64) {
	cartToken = strings.TrimSpace(cartToken)
	if cartToken == "" {
		return
	}
	rows, err := app.db.QueryContext(ctx, `SELECT product_id,SUM(quantity) FROM cart_items WHERE cart_token=? GROUP BY product_id`, cartToken)
	if err != nil {
		return
	}
	defer rows.Close()
	for rows.Next() {
		var productID int64
		var quantity int
		if err := rows.Scan(&productID, &quantity); err != nil {
			continue
		}
		_, _ = app.db.ExecContext(ctx, `INSERT INTO cart_items (tenant_id,user_id,cart_token,product_id,quantity,created_at,updated_at) VALUES (?,?,?,?,?,NOW(),NOW())
            ON DUPLICATE KEY UPDATE quantity=LEAST(cart_items.quantity+VALUES(quantity),99), updated_at=NOW()`, app.cfg.TenantID, userID, nil, productID, quantity)
	}
	_, _ = app.db.ExecContext(ctx, `DELETE FROM cart_items WHERE cart_token=?`, cartToken)
	_, _ = app.db.ExecContext(ctx, `UPDATE cart_coupons SET user_id=?, cart_token=NULL, updated_at=NOW() WHERE cart_token=? AND tenant_id=?`, userID, cartToken, app.cfg.TenantID)
}

func fallbackDeviceName(value string) string {
	if strings.TrimSpace(value) == "" {
		return "storefront"
	}
	return strings.TrimSpace(value)
}

func (app *App) requireUser(w http.ResponseWriter, r *http.Request) (*User, bool) {
	ctx, cancel := withTimeout(r, 4*time.Second)
	defer cancel()
	user, err := app.resolveUser(ctx, requestBearerToken(r))
	if err != nil || user == nil {
		app.handleErr(w, r, ErrUnauthorized)
		return nil, false
	}
	return user, true
}
