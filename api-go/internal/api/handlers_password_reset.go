package api

import (
	"bytes"
	"context"
	"crypto/subtle"
	"database/sql"
	"encoding/json"
	"fmt"
	"html"
	"io"
	"net/http"
	"net/mail"
	"net/url"
	"strings"
	"time"
)

const passwordResetGenericMessage = "Hesap bulunursa şifre sıfırlama bağlantısı e-posta adresine gönderilecektir."

// handleForgotPassword deliberately returns the same success response for unknown
// accounts, while still surfacing a real mail delivery failure for known accounts.
func (app *App) handleForgotPassword(w http.ResponseWriter, r *http.Request) {
	var body struct {
		Email string `json:"email"`
		Phone string `json:"phone"`
	}
	if err := parseJSON(r, &body); err != nil {
		writeError(w, r, http.StatusUnprocessableEntity, "E-posta veya telefon bilgisi gereklidir.")
		return
	}

	email := strings.ToLower(strings.TrimSpace(body.Email))
	phone := normalizePhone(body.Phone)
	if email == "" && phone == "" {
		writeError(w, r, http.StatusUnprocessableEntity, "E-posta veya telefon bilgisi gereklidir.")
		return
	}
	if email != "" && !validResetEmail(email) {
		writeError(w, r, http.StatusUnprocessableEntity, "E-posta adresi geçersiz.")
		return
	}
	if phone != "" && (len(phone) != 10 || !strings.HasPrefix(phone, "5")) {
		writeError(w, r, http.StatusUnprocessableEntity, "Telefon numarası geçersiz.")
		return
	}

	lookupKey := firstNonEmpty(email, phone)
	if app.loginIPLimiter != nil && !app.loginIPLimiter.Allow("reset:ip:"+clientIP(r)) {
		writeError(w, r, http.StatusTooManyRequests, "Çok fazla şifre sıfırlama isteği. Lütfen daha sonra tekrar deneyin.")
		return
	}
	if app.loginAccountLimiter != nil && !app.loginAccountLimiter.Allow("reset:account:"+sha256Hex(lookupKey)) {
		writeError(w, r, http.StatusTooManyRequests, "Çok fazla şifre sıfırlama isteği. Lütfen daha sonra tekrar deneyin.")
		return
	}

	ctx, cancel := withTimeout(r, 10*time.Second)
	defer cancel()

	var accountEmail sql.NullString
	err := app.db.QueryRowContext(ctx, `
		SELECT email
		FROM users
		WHERE (? <> '' AND email = ?) OR (? <> '' AND phone = ?)
		LIMIT 1
	`, email, email, phone, phone).Scan(&accountEmail)
	if err != nil && err != sql.ErrNoRows {
		app.handleErr(w, r, err)
		return
	}
	if err == sql.ErrNoRows || !accountEmail.Valid || strings.TrimSpace(accountEmail.String) == "" {
		writeData(w, http.StatusOK, map[string]string{"status": "ok", "message": passwordResetGenericMessage})
		return
	}

	rawToken, err := newToken()
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	accountEmailValue := strings.ToLower(strings.TrimSpace(accountEmail.String))
	tokenHash := sha256Hex(rawToken)
	if _, err := app.db.ExecContext(ctx, `
		INSERT INTO password_reset_tokens (email, token, created_at)
		VALUES (?, ?, NOW())
		ON DUPLICATE KEY UPDATE token=VALUES(token), created_at=NOW()
	`, accountEmailValue, tokenHash); err != nil {
		app.handleErr(w, r, err)
		return
	}

	resetURL := app.cfg.StorefrontURL + "/auth/reset-password?email=" + url.QueryEscape(accountEmailValue) + "&token=" + url.QueryEscape(rawToken)
	if err := app.sendPasswordResetMail(ctx, accountEmailValue, resetURL); err != nil {
		_, _ = app.db.ExecContext(ctx, `DELETE FROM password_reset_tokens WHERE email=? AND token=?`, accountEmailValue, tokenHash)
		writeError(w, r, http.StatusServiceUnavailable, "Şifre sıfırlama e-postası şu anda gönderilemedi. Lütfen daha sonra tekrar deneyin.")
		return
	}

	writeData(w, http.StatusOK, map[string]string{"status": "ok", "message": passwordResetGenericMessage})
}

func (app *App) handleResetPassword(w http.ResponseWriter, r *http.Request) {
	var body struct {
		Email                string `json:"email"`
		Token                string `json:"token"`
		Password             string `json:"password"`
		PasswordConfirmation string `json:"password_confirmation"`
	}
	if err := parseJSON(r, &body); err != nil {
		writeError(w, r, http.StatusUnprocessableEntity, "Şifre sıfırlama bilgileri geçersiz.")
		return
	}

	email := strings.ToLower(strings.TrimSpace(body.Email))
	token := strings.TrimSpace(body.Token)
	if !validResetEmail(email) || token == "" || len(body.Password) < 8 || body.Password != body.PasswordConfirmation {
		writeError(w, r, http.StatusUnprocessableEntity, "Bağlantı veya yeni şifre bilgileri geçersiz.")
		return
	}
	if app.loginIPLimiter != nil && !app.loginIPLimiter.Allow("reset-confirm:ip:"+clientIP(r)) {
		writeError(w, r, http.StatusTooManyRequests, "Çok fazla şifre sıfırlama denemesi. Lütfen daha sonra tekrar deneyin.")
		return
	}
	if app.loginAccountLimiter != nil && !app.loginAccountLimiter.Allow("reset-confirm:account:"+sha256Hex(email)) {
		writeError(w, r, http.StatusTooManyRequests, "Çok fazla şifre sıfırlama denemesi. Lütfen daha sonra tekrar deneyin.")
		return
	}

	ctx, cancel := withTimeout(r, 10*time.Second)
	defer cancel()
	tx, err := app.db.BeginTx(ctx, nil)
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	defer tx.Rollback()

	var userID int64
	var storedHash string
	var createdAt time.Time
	err = tx.QueryRowContext(ctx, `
		SELECT u.id, p.token, p.created_at
		FROM password_reset_tokens p
		INNER JOIN users u ON u.email = p.email
		WHERE p.email = ?
		FOR UPDATE
	`, email).Scan(&userID, &storedHash, &createdAt)
	if err != nil {
		writeError(w, r, http.StatusUnprocessableEntity, "Şifre sıfırlama bağlantısı geçersiz veya süresi dolmuş.")
		return
	}

	providedHash := sha256Hex(token)
	expired := time.Since(createdAt) > app.passwordResetTTL()
	if expired || subtle.ConstantTimeCompare([]byte(storedHash), []byte(providedHash)) != 1 {
		writeError(w, r, http.StatusUnprocessableEntity, "Şifre sıfırlama bağlantısı geçersiz veya süresi dolmuş.")
		return
	}

	password, err := passwordHash(body.Password)
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	if _, err := tx.ExecContext(ctx, `UPDATE users SET password=?, updated_at=NOW() WHERE id=?`, password, userID); err != nil {
		app.handleErr(w, r, err)
		return
	}
	if _, err := tx.ExecContext(ctx, `DELETE FROM api_tokens WHERE user_id=?`, userID); err != nil {
		app.handleErr(w, r, err)
		return
	}
	if _, err := tx.ExecContext(ctx, `DELETE FROM password_reset_tokens WHERE email=?`, email); err != nil {
		app.handleErr(w, r, err)
		return
	}
	if err := tx.Commit(); err != nil {
		app.handleErr(w, r, err)
		return
	}

	writeData(w, http.StatusOK, map[string]string{"status": "ok", "message": "Şifreniz güncellendi. Yeni şifrenizle giriş yapabilirsiniz."})
}

func (app *App) passwordResetTTL() time.Duration {
	if app.cfg.PasswordResetTTL <= 0 {
		return 30 * time.Minute
	}
	return app.cfg.PasswordResetTTL
}

func validResetEmail(value string) bool {
	address, err := mail.ParseAddress(value)
	return err == nil && strings.EqualFold(address.Address, value)
}

func (app *App) sendPasswordResetMail(ctx context.Context, recipient, resetURL string) error {
	if app.cfg.MailServiceURL == "" || app.cfg.MailAdminToken == "" {
		return fmt.Errorf("mail service is not configured")
	}

	safeURL := html.EscapeString(resetURL)
	payload := map[string]any{
		"to":        []string{recipient},
		"subject":   "Karacabey Gross Market şifre sıfırlama",
		"text_body": "Şifrenizi yenilemek için bağlantıyı açın: " + resetURL + "\n\nBu bağlantı " + app.passwordResetTTL().String() + " süreyle geçerlidir. Bu isteği siz yapmadıysanız e-postayı yok sayabilirsiniz.",
		"html_body": `<p>Şifrenizi yenilemek için aşağıdaki güvenli bağlantıyı açın:</p><p><a href="` + safeURL + `">Şifremi yenile</a></p><p>Bu bağlantı ` + html.EscapeString(app.passwordResetTTL().String()) + ` süreyle geçerlidir. Bu isteği siz yapmadıysanız e-postayı yok sayabilirsiniz.</p>`,
	}
	body, err := json.Marshal(payload)
	if err != nil {
		return err
	}

	req, err := http.NewRequestWithContext(ctx, http.MethodPost, app.cfg.MailServiceURL+"/api/v1/mail/send", bytes.NewReader(body))
	if err != nil {
		return err
	}
	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("Accept", "application/json")
	req.Header.Set("X-Mail-Admin-Token", app.cfg.MailAdminToken)

	resp, err := (&http.Client{Timeout: 8 * time.Second}).Do(req)
	if err != nil {
		return err
	}
	defer resp.Body.Close()
	_, _ = io.Copy(io.Discard, io.LimitReader(resp.Body, 32<<10))
	if resp.StatusCode < 200 || resp.StatusCode >= 300 {
		return fmt.Errorf("mail service returned status %d", resp.StatusCode)
	}
	return nil
}
