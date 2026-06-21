package api

// Mobile (iOS/Android) ihtiyacına yönelik ek endpoint'ler.
// Bu dosyaya eklenen handler'lar middleware.go içinde mux'a kaydedilmelidir.

import (
	"context"
	"database/sql"
	"errors"
	"math"
	"net/http"
	"strconv"
	"strings"
	"time"
)

// POST /api/v1/auth/refresh
// Body: {"refresh_token": "<önceki access token>"}
// Backend tek-token modeli kullanır; "refresh" mekanizması fiilen "stateless
// rotate" şeklindedir: mevcut token doğrulanır, geçerli ise eski silinip
// yenisi oluşturulur ve geri döndürülür.
func (app *App) handleTokenRefresh(w http.ResponseWriter, r *http.Request) {
	ctx, cancel := withTimeout(r, 4*time.Second)
	defer cancel()

	var body struct {
		RefreshToken string `json:"refresh_token"`
	}
	if err := parseJSON(r, &body); err != nil {
		writeError(w, r, http.StatusUnprocessableEntity, "Refresh token gereklidir.")
		return
	}
	candidate := strings.TrimSpace(body.RefreshToken)
	if candidate == "" {
		// Bearer header'dan da kabul et.
		candidate = requestBearerToken(r)
	}
	if candidate == "" {
		writeError(w, r, http.StatusUnauthorized, "Refresh token bulunamadı.")
		return
	}

	user, err := app.resolveUser(ctx, candidate)
	if err != nil || user == nil {
		writeError(w, r, http.StatusUnauthorized, "Geçersiz veya süresi dolmuş token.")
		return
	}

	// Eski token'ı sil, yenisini oluştur.
	if _, err := app.db.ExecContext(ctx, `DELETE FROM api_tokens WHERE token_hash=?`, sha256Hex(candidate)); err != nil {
		app.handleErr(w, r, err)
		return
	}
	token, expires, err := app.createAPIToken(ctx, user.ID, "refresh")
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	app.writeAuthCookie(w, token, expires)
	writeJSON(w, http.StatusOK, map[string]any{
		"user":          user,
		"token":         token,
		"refresh_token": token,
		"token_type":    "Bearer",
		"expires_at":    expires.Format(time.RFC3339),
	})
}

// POST /api/v1/addresses — yeni adres oluşturur
func (app *App) handleAddressCreate(w http.ResponseWriter, r *http.Request) {
	user, ok := app.requireUser(w, r)
	if !ok {
		return
	}
	ctx, cancel := withTimeout(r, 6*time.Second)
	defer cancel()

	body, err := readAddressBody(r)
	if err != nil {
		writeError(w, r, http.StatusUnprocessableEntity, err.Error())
		return
	}

	if body.IsDefault {
		if _, err := app.db.ExecContext(ctx, `UPDATE addresses SET is_default=0, updated_at=NOW() WHERE user_id=?`, user.ID); err != nil {
			app.handleErr(w, r, err)
			return
		}
	}

	res, err := app.db.ExecContext(ctx, `INSERT INTO addresses
        (user_id,title,recipient_name,phone,city,district,neighborhood,address_line,postal_code,is_default,created_at,updated_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,NOW(),NOW())`,
		user.ID, body.Title, body.RecipientName, body.Phone, body.City, body.District,
		nullableString(body.Neighborhood), body.AddressLine, nullableString(body.PostalCode), body.IsDefault)
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	id, _ := res.LastInsertId()
	app.touchCustomerSync(ctx, CartIdentity{UserID: &user.ID, CustomerUID: user.CustomerUID}, "address.created")
	writeData(w, http.StatusCreated, addressRow(id, body))
}

// PATCH /api/v1/addresses/{id} — adres günceller
func (app *App) handleAddressUpdate(w http.ResponseWriter, r *http.Request) {
	user, ok := app.requireUser(w, r)
	if !ok {
		return
	}
	id, err := strconv.ParseInt(r.PathValue("id"), 10, 64)
	if err != nil {
		writeError(w, r, http.StatusUnprocessableEntity, "Geçersiz adres kimliği.")
		return
	}
	ctx, cancel := withTimeout(r, 6*time.Second)
	defer cancel()

	if !app.userOwnsAddress(ctx, id, user.ID) {
		writeError(w, r, http.StatusNotFound, "Adres bulunamadı.")
		return
	}

	body, err := readAddressBody(r)
	if err != nil {
		writeError(w, r, http.StatusUnprocessableEntity, err.Error())
		return
	}

	if body.IsDefault {
		if _, err := app.db.ExecContext(ctx, `UPDATE addresses SET is_default=0, updated_at=NOW() WHERE user_id=?`, user.ID); err != nil {
			app.handleErr(w, r, err)
			return
		}
	}

	if _, err := app.db.ExecContext(ctx, `UPDATE addresses SET title=?,recipient_name=?,phone=?,city=?,district=?,neighborhood=?,address_line=?,postal_code=?,is_default=?, updated_at=NOW() WHERE id=? AND user_id=?`,
		body.Title, body.RecipientName, body.Phone, body.City, body.District,
		nullableString(body.Neighborhood), body.AddressLine, nullableString(body.PostalCode), body.IsDefault,
		id, user.ID); err != nil {
		app.handleErr(w, r, err)
		return
	}
	app.touchCustomerSync(ctx, CartIdentity{UserID: &user.ID, CustomerUID: user.CustomerUID}, "address.updated")
	writeData(w, http.StatusOK, addressRow(id, body))
}

// POST /api/v1/addresses/{id}/default — adresi varsayılan yapar
func (app *App) handleAddressSetDefault(w http.ResponseWriter, r *http.Request) {
	user, ok := app.requireUser(w, r)
	if !ok {
		return
	}
	id, err := strconv.ParseInt(r.PathValue("id"), 10, 64)
	if err != nil {
		writeError(w, r, http.StatusUnprocessableEntity, "Geçersiz adres kimliği.")
		return
	}
	ctx, cancel := withTimeout(r, 4*time.Second)
	defer cancel()

	if !app.userOwnsAddress(ctx, id, user.ID) {
		writeError(w, r, http.StatusNotFound, "Adres bulunamadı.")
		return
	}

	tx, err := app.db.BeginTx(ctx, nil)
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	defer tx.Rollback()
	if _, err := tx.ExecContext(ctx, `UPDATE addresses SET is_default=0, updated_at=NOW() WHERE user_id=?`, user.ID); err != nil {
		app.handleErr(w, r, err)
		return
	}
	if _, err := tx.ExecContext(ctx, `UPDATE addresses SET is_default=1, updated_at=NOW() WHERE id=? AND user_id=?`, id, user.ID); err != nil {
		app.handleErr(w, r, err)
		return
	}
	if err := tx.Commit(); err != nil {
		app.handleErr(w, r, err)
		return
	}
	app.touchCustomerSync(ctx, CartIdentity{UserID: &user.ID, CustomerUID: user.CustomerUID}, "address.default_changed")
	writeData(w, http.StatusOK, map[string]any{"status": "ok", "id": id})
}

// POST /api/v1/orders/{id}/cancel — ödeme bekleyen veya hazırlanan siparişi iptal eder.
func (app *App) handleOrderCancel(w http.ResponseWriter, r *http.Request) {
	user, ok := app.requireUser(w, r)
	if !ok {
		return
	}
	id, err := strconv.ParseInt(r.PathValue("id"), 10, 64)
	if err != nil {
		writeError(w, r, http.StatusUnprocessableEntity, "Geçersiz sipariş kimliği.")
		return
	}
	ctx, cancel := withTimeout(r, 6*time.Second)
	defer cancel()

	tx, err := app.db.BeginTx(ctx, &sql.TxOptions{Isolation: sql.LevelReadCommitted})
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	defer tx.Rollback()

	var status, paymentProvider, paymentStatus string
	row := tx.QueryRowContext(ctx, `SELECT o.status,p.provider,p.status
		FROM orders o
		INNER JOIN payments p ON p.order_id=o.id
		WHERE o.id=? AND o.user_id=? AND o.tenant_id=?
		ORDER BY p.id DESC LIMIT 1 FOR UPDATE`, id, user.ID, app.cfg.TenantID)
	if err := row.Scan(&status, &paymentProvider, &paymentStatus); err != nil {
		if errors.Is(err, sql.ErrNoRows) {
			writeError(w, r, http.StatusNotFound, "Sipariş bulunamadı.")
			return
		}
		app.handleErr(w, r, err)
		return
	}
	if !canCancelOrder(status, paymentProvider, paymentStatus) {
		writeError(w, r, http.StatusUnprocessableEntity, "Bu siparişin durumu iptal edilmeye uygun değil.")
		return
	}
	if _, err := tx.ExecContext(ctx, `UPDATE orders SET status='cancelled', updated_at=NOW() WHERE id=? AND user_id=? AND tenant_id=?`, id, user.ID, app.cfg.TenantID); err != nil {
		app.handleErr(w, r, err)
		return
	}
	if _, err := tx.ExecContext(ctx, `UPDATE payments SET status='cancelled', updated_at=NOW() WHERE order_id=? AND status='pending'`, id); err != nil {
		app.handleErr(w, r, err)
		return
	}
	if err := app.releaseOrderStockTx(ctx, tx, id); err != nil {
		app.handleErr(w, r, err)
		return
	}
	if err := tx.Commit(); err != nil {
		app.handleErr(w, r, err)
		return
	}
	app.touchCustomerSync(ctx, CartIdentity{UserID: &user.ID, CustomerUID: user.CustomerUID}, "order.cancelled")
	writeData(w, http.StatusOK, map[string]any{"status": "ok", "order_id": id})
}

func canCancelOrder(orderStatus, paymentProvider, paymentStatus string) bool {
	if paymentStatus != "pending" {
		return false
	}
	return orderStatus == "awaiting_payment" ||
		(orderStatus == "reviewing" && paymentProvider == "cash_on_delivery")
}

// GET /api/v1/payment-methods — desteklenen ödeme yöntemleri (sabit liste)
func (app *App) handlePaymentMethods(w http.ResponseWriter, r *http.Request) {
	writeData(w, http.StatusOK, paymentMethods())
}

func paymentMethods() []map[string]any {
	return []map[string]any{
		{"id": "paytr-card", "type": "credit_card", "display_name": "Kredi / Banka Kartı", "provider": "paytr", "is_default": true},
		{"id": "cash-on-delivery", "type": "cash_on_delivery", "display_name": "Kapıda Nakit", "provider": "manual", "is_default": false},
		{"id": "pos-on-delivery", "type": "card_on_delivery", "display_name": "Kapıda Kart", "provider": "manual", "is_default": false},
	}
}

// GET /api/v1/branches/nearby?lat=&lng=
// Şu an branches tablosu yok; tek bir Karacabey merkez şubesi sabit olarak dönülür.
// Lat/lng geldiğinde Haversine ile mesafe hesaplanır.
func (app *App) handleBranchesNearby(w http.ResponseWriter, r *http.Request) {
	karacabeyLat := 40.2122
	karacabeyLng := 28.3617

	lat, _ := strconv.ParseFloat(r.URL.Query().Get("lat"), 64)
	lng, _ := strconv.ParseFloat(r.URL.Query().Get("lng"), 64)
	distance := haversineKm(lat, lng, karacabeyLat, karacabeyLng)

	writeData(w, http.StatusOK, []map[string]any{
		{
			"id":          1,
			"name":        "Karacabey Merkez",
			"address":     "Karacabey, Bursa",
			"latitude":    karacabeyLat,
			"longitude":   karacabeyLng,
			"phone":       app.cfg.SupportPhone,
			"distance_km": distance,
			"is_open":     true,
		},
	})
}

// DELETE /api/v1/notifications/device-tokens/{token} — push token kaydını siler
func (app *App) handleDeviceTokenDelete(w http.ResponseWriter, r *http.Request) {
	user, ok := app.requireUser(w, r)
	if !ok {
		return
	}
	token := strings.TrimSpace(r.PathValue("token"))
	if token == "" {
		writeError(w, r, http.StatusUnprocessableEntity, "Token gereklidir.")
		return
	}
	ctx, cancel := withTimeout(r, 4*time.Second)
	defer cancel()
	if _, err := app.db.ExecContext(ctx, `DELETE FROM device_tokens WHERE user_id=? AND token=?`, user.ID, token); err != nil {
		app.handleErr(w, r, err)
		return
	}
	writeData(w, http.StatusOK, map[string]string{"status": "ok"})
}

// MARK: helpers

type addressPayload struct {
	Title         string
	RecipientName string
	Phone         string
	City          string
	District      string
	Neighborhood  string
	AddressLine   string
	PostalCode    string
	IsDefault     bool
}

func readAddressBody(r *http.Request) (addressPayload, error) {
	var body struct {
		Title         string   `json:"title"`
		RecipientName string   `json:"recipient_name"`
		Phone         string   `json:"phone"`
		City          string   `json:"city"`
		District      string   `json:"district"`
		Neighborhood  string   `json:"neighborhood"`
		AddressLine   string   `json:"address_line"`
		PostalCode    string   `json:"postal_code"`
		Latitude      *float64 `json:"latitude"`
		Longitude     *float64 `json:"longitude"`
		Lat           *float64 `json:"lat"`
		Lng           *float64 `json:"lng"`
		IsDefault     bool     `json:"is_default"`
	}
	if err := parseJSON(r, &body); err != nil {
		return addressPayload{}, errors.New("Geçersiz adres verisi.")
	}
	out := addressPayload{
		Title:         strings.TrimSpace(firstNonEmpty(body.Title, "Adresim")),
		RecipientName: strings.TrimSpace(body.RecipientName),
		Phone:         normalizePhone(body.Phone),
		City:          strings.TrimSpace(body.City),
		District:      strings.TrimSpace(body.District),
		Neighborhood:  strings.TrimSpace(body.Neighborhood),
		AddressLine:   strings.TrimSpace(body.AddressLine),
		PostalCode:    strings.TrimSpace(body.PostalCode),
		IsDefault:     body.IsDefault,
	}
	if out.RecipientName == "" || out.Phone == "" || out.City == "" || out.District == "" || out.AddressLine == "" {
		return out, errors.New("Alıcı, telefon, il, ilçe ve adres satırı zorunludur.")
	}
	if len(out.Phone) < 10 {
		return out, errors.New("Telefon numarası geçersiz.")
	}
	return out, nil
}

func (app *App) userOwnsAddress(ctx context.Context, addressID, userID int64) bool {
	var owner int64
	if err := app.db.QueryRowContext(ctx, `SELECT user_id FROM addresses WHERE id=? LIMIT 1`, addressID).Scan(&owner); err != nil {
		return false
	}
	return owner == userID
}

func addressRow(id int64, body addressPayload) map[string]any {
	return map[string]any{
		"id":             id,
		"title":          body.Title,
		"recipient_name": body.RecipientName,
		"phone":          body.Phone,
		"city":           body.City,
		"district":       body.District,
		"neighborhood":   nullableStringValue(body.Neighborhood),
		"address_line":   body.AddressLine,
		"postal_code":    nullableStringValue(body.PostalCode),
		"is_default":     body.IsDefault,
	}
}

func nullableStringValue(value string) any {
	if value == "" {
		return nil
	}
	return value
}

func haversineKm(lat1, lon1, lat2, lon2 float64) float64 {
	if lat1 == 0 && lon1 == 0 {
		return 0
	}
	const earthRadiusKm = 6371.0
	dLat := degToRad(lat2 - lat1)
	dLon := degToRad(lon2 - lon1)
	a := math.Sin(dLat/2)*math.Sin(dLat/2) +
		math.Cos(degToRad(lat1))*math.Cos(degToRad(lat2))*math.Sin(dLon/2)*math.Sin(dLon/2)
	return earthRadiusKm * 2 * math.Asin(math.Sqrt(a))
}

func degToRad(d float64) float64 { return d * math.Pi / 180 }
