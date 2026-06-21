package api

import (
	"bytes"
	"context"
	"database/sql"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"log/slog"
	"net/http"
	"net/url"
	"strconv"
	"strings"
	"time"
)

func (app *App) handleCheckout(w http.ResponseWriter, r *http.Request) {
	ctx, cancel := withTimeout(r, 20*time.Second)
	defer cancel()
	id, err := app.identityFromRequest(ctx, r)
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	body, err := parseCheckoutBody(r)
	if err != nil {
		writeError(w, r, http.StatusUnprocessableEntity, err.Error())
		return
	}
	body.Source = normalizeOrderSource(firstNonEmpty(body.Source, r.Header.Get("X-Platform")))
	if err := app.hydrateCheckoutAddress(ctx, id, &body); err != nil {
		app.handleErr(w, r, err)
		return
	}
	if body.CheckoutUID == "" {
		body.CheckoutUID = sanitizeUID(firstNonEmpty(r.Header.Get("X-Checkout-Key"), body.CheckoutKey, newPublicUID("chk")))
	}
	if body.PaymentUID == "" {
		body.PaymentUID = sanitizeUID(firstNonEmpty(r.Header.Get("X-Payment-UID"), newPublicUID("pay")))
	}
	if body.CheckoutKey == "" {
		body.CheckoutKey = body.CheckoutUID
	}
	idemKey := checkoutIdempotencyKey(r, body)
	idemHash := checkoutRequestHash(body)
	idem, err := app.beginIdempotency(ctx, "checkout.start", idemKey, idemHash, 20*time.Minute)
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	if idem.Replay {
		w.Header().Set("Content-Type", "application/json; charset=utf-8")
		w.Header().Set("X-Idempotency-Key", idem.Key)
		w.WriteHeader(idem.StatusCode)
		_, _ = w.Write(idem.ResponseBody)
		return
	}
	w.Header().Set("X-Idempotency-Key", idem.Key)
	app.recordIdentityEvent(r.Context(), "checkout.started", id, map[string]any{"checkout_uid": body.CheckoutUID, "payment_uid": body.PaymentUID, "idempotency_key": idem.Key})
	order, err := app.createCheckoutOrder(ctx, id, body)
	if err != nil {
		app.failIdempotency(ctx, "checkout.start", idem.Key, err)
		app.handleErr(w, r, err)
		return
	}
	if checkoutPaymentFlow(body.PaymentFlow) == "cash_on_delivery" {
		response := CheckoutResponse{
			MerchantOID:    order.MerchantOID,
			OrderID:        order.ID,
			PaymentID:      order.PaymentID,
			Status:         "cash_on_delivery",
			TotalCents:     order.TotalCents,
			Currency:       "TL",
			PaymentFlow:    "cash_on_delivery",
			CashOnDelivery: true,
			Message:        "Siparişiniz kontrol ediliyor. Onaylandığında sipariş numaranızla bildirim göndereceğiz; nakit, banka/kredi kartı ve yemek kartı teslimatta geçerlidir.",
		}
		app.completeIdempotency(ctx, "checkout.start", idem.Key, http.StatusOK, response)
		writeData(w, http.StatusOK, response)
		return
	}
	if checkoutPaymentFlow(body.PaymentFlow) == "direct" {
		directPayment, reason, err := app.paytr.DirectPaymentParams(order, clientIP(r))
		if err != nil {
			traceID, _ := r.Context().Value(contextKeyRequestID).(string)
			response := CheckoutResponse{MerchantOID: order.MerchantOID, OrderID: order.ID, PaymentID: order.PaymentID, Status: "payment_unavailable", TotalCents: order.TotalCents, Currency: "TL", PaymentFlow: "direct", PaymentUnavailable: true, Message: "Online ödeme şu anda kullanılamıyor. Lütfen kısa süre sonra tekrar deneyin.", ProviderReason: reason, TraceID: traceID}
			app.completeIdempotency(ctx, "checkout.start", idem.Key, http.StatusBadGateway, response)
			writeJSON(w, http.StatusBadGateway, map[string]any{"data": response})
			return
		}
		response := CheckoutResponse{MerchantOID: order.MerchantOID, OrderID: order.ID, PaymentID: order.PaymentID, Status: "awaiting_payment", TotalCents: order.TotalCents, Currency: "TL", PaymentFlow: "direct", DirectPayment: directPayment}
		app.completeIdempotency(ctx, "checkout.start", idem.Key, http.StatusOK, response)
		writeData(w, http.StatusOK, response)
		return
	}
	token, iframeURL, reason, err := app.paytr.GetIframeToken(ctx, order, clientIP(r))
	if err != nil {
		traceID, _ := r.Context().Value(contextKeyRequestID).(string)
		slog.Warn("paytr token failed", "merchant_oid", order.MerchantOID, "reason", reason, "error", err, "trace_id", traceID)
		response := CheckoutResponse{MerchantOID: order.MerchantOID, OrderID: order.ID, PaymentID: order.PaymentID, Status: "payment_unavailable", TotalCents: order.TotalCents, Currency: "TL", PaymentFlow: "iframe", PaymentUnavailable: true, Message: "Online ödeme şu anda kullanılamıyor. Lütfen kısa süre sonra tekrar deneyin.", ProviderReason: reason, TraceID: traceID}
		app.completeIdempotency(ctx, "checkout.start", idem.Key, http.StatusBadGateway, response)
		writeJSON(w, http.StatusBadGateway, map[string]any{"data": response})
		return
	}
	app.storePayTRToken(ctx, order.MerchantOID, token, map[string]any{"token": token, "iframe_src": iframeURL, "checkout_uid": body.CheckoutUID, "payment_uid": body.PaymentUID, "idempotency_key": idem.Key})
	app.recordIdentityEvent(r.Context(), "payment.url_created", id, map[string]any{"checkout_uid": body.CheckoutUID, "payment_uid": body.PaymentUID, "merchant_oid": order.MerchantOID, "idempotency_key": idem.Key})
	response := CheckoutResponse{MerchantOID: order.MerchantOID, OrderID: order.ID, CheckoutURL: iframeURL, IframeSrc: iframeURL, PaymentID: order.PaymentID, Status: "awaiting_payment", TotalCents: order.TotalCents, Currency: "TL", PaymentFlow: "iframe"}
	app.completeIdempotency(ctx, "checkout.start", idem.Key, http.StatusOK, response)
	writeData(w, http.StatusOK, response)
}

func parseCheckoutBody(r *http.Request) (CheckoutRequest, error) {
	defer r.Body.Close()
	raw, err := io.ReadAll(io.LimitReader(r.Body, 1<<20))
	if err != nil {
		return CheckoutRequest{}, fmt.Errorf("JSON gövdesi geçersiz: %w", err)
	}
	raw = bytes.TrimSpace(raw)
	if len(raw) == 0 {
		return CheckoutRequest{}, fmt.Errorf("JSON gövdesi geçersiz: boş istek")
	}

	var checkout CheckoutRequest
	checkoutErr := decodeStrictJSON(raw, &checkout)
	if checkoutErr == nil {
		return checkout, nil
	}

	var mobile PayTRMobilePaymentRequest
	if err := decodeStrictJSON(raw, &mobile); err != nil {
		return CheckoutRequest{}, fmt.Errorf("JSON gövdesi geçersiz: %w", checkoutErr)
	}

	return checkoutRequestFromMobilePayment(mobile)
}

func decodeStrictJSON(raw []byte, dst any) error {
	decoder := json.NewDecoder(bytes.NewReader(raw))
	decoder.DisallowUnknownFields()
	if err := decoder.Decode(dst); err != nil {
		return err
	}
	if err := decoder.Decode(&struct{}{}); err != io.EOF {
		return fmt.Errorf("birden fazla JSON değeri gönderildi")
	}
	return nil
}

func checkoutRequestFromMobilePayment(payload PayTRMobilePaymentRequest) (CheckoutRequest, error) {
	orderID := sanitizeUID(payload.OrderID)
	userID := strings.TrimSpace(payload.UserID)
	email := strings.TrimSpace(payload.Email)
	phone := normalizePhone(payload.Phone)
	currency := strings.ToUpper(strings.TrimSpace(payload.Currency))
	addressID := strings.TrimSpace(payload.AddressID)

	if orderID == "" {
		return CheckoutRequest{}, fmt.Errorf("orderId zorunludur.")
	}
	if userID == "" {
		return CheckoutRequest{}, fmt.Errorf("userId zorunludur.")
	}
	if !strings.Contains(email, "@") {
		return CheckoutRequest{}, fmt.Errorf("Geçerli e-posta zorunludur.")
	}
	if len(phone) < 10 {
		return CheckoutRequest{}, fmt.Errorf("Geçerli telefon zorunludur.")
	}
	if payload.AmountKurus <= 0 {
		return CheckoutRequest{}, fmt.Errorf("amountKurus pozitif olmalıdır.")
	}
	if currency == "" {
		currency = "TL"
	}
	if currency != "TL" {
		return CheckoutRequest{}, fmt.Errorf("PayTR para birimi TL olmalıdır.")
	}
	if addressID == "" {
		return CheckoutRequest{}, fmt.Errorf("addressId zorunludur.")
	}
	if len(payload.Basket) == 0 {
		return CheckoutRequest{}, fmt.Errorf("basket boş olamaz.")
	}

	items := make([]CheckoutLineInput, 0, len(payload.Basket))
	for _, basketItem := range payload.Basket {
		productID, err := strconv.ParseInt(strings.TrimSpace(basketItem.ProductID), 10, 64)
		if err != nil || productID <= 0 {
			return CheckoutRequest{}, fmt.Errorf("basket içindeki productId geçersiz.")
		}
		if strings.TrimSpace(basketItem.Name) == "" {
			return CheckoutRequest{}, fmt.Errorf("basket içindeki ürün adı zorunludur.")
		}
		if basketItem.Quantity <= 0 {
			return CheckoutRequest{}, fmt.Errorf("basket içindeki quantity pozitif olmalıdır.")
		}
		if basketItem.UnitPriceKurus <= 0 {
			return CheckoutRequest{}, fmt.Errorf("basket içindeki unitPriceKurus pozitif olmalıdır.")
		}
		items = append(items, CheckoutLineInput{ProductID: productID, Quantity: basketItem.Quantity})
	}

	var checkout CheckoutRequest
	checkout.Customer.Name = "Karacabey Gross Market Müşterisi"
	checkout.Customer.Email = email
	checkout.Customer.Phone = phone
	checkout.Shipping.Address = "Adres #" + addressID
	checkout.AddressID = addressID
	checkout.CheckoutKey = orderID
	checkout.CheckoutUID = orderID
	checkout.PaymentUID = orderID
	checkout.PaymentFlow = "iframe"
	checkout.Source = "ios"
	checkout.Items = items
	return checkout, nil
}

func normalizeOrderSource(source string) string {
	switch strings.ToLower(strings.TrimSpace(source)) {
	case "ios", "iphone", "ipad":
		return "ios"
	case "android":
		return "android"
	case "mobile", "mobil", "app", "mobile_app":
		return "mobile"
	default:
		return "web"
	}
}

func (app *App) hydrateCheckoutAddress(ctx context.Context, id CartIdentity, req *CheckoutRequest) error {
	addressID := strings.TrimSpace(req.AddressID)
	if addressID == "" {
		return nil
	}
	if id.UserID == nil {
		return fmt.Errorf("%w: Kayıtlı adres ile ödeme için oturum gerekli.", ErrBadRequest)
	}
	parsedAddressID, err := strconv.ParseInt(addressID, 10, 64)
	if err != nil || parsedAddressID <= 0 {
		return fmt.Errorf("%w: Geçersiz adres kimliği.", ErrBadRequest)
	}

	var recipientName, phone, city, district, neighborhood, addressLine string
	err = app.db.QueryRowContext(
		ctx,
		`SELECT recipient_name,phone,city,district,COALESCE(neighborhood,''),address_line
		 FROM addresses WHERE id=? AND user_id=? LIMIT 1`,
		parsedAddressID,
		*id.UserID,
	).Scan(&recipientName, &phone, &city, &district, &neighborhood, &addressLine)
	if err != nil {
		if errors.Is(err, sql.ErrNoRows) {
			return fmt.Errorf("%w: Adres bulunamadı.", ErrBadRequest)
		}
		return err
	}

	req.Customer.Name = firstNonEmpty(req.Customer.Name, recipientName)
	req.Customer.Phone = firstNonEmpty(req.Customer.Phone, phone)
	req.Shipping.City = firstNonEmpty(req.Shipping.City, city)
	req.Shipping.District = firstNonEmpty(req.Shipping.District, district)
	req.Shipping.Address = strings.TrimSpace(strings.Join(nonEmptyStrings(neighborhood, addressLine), ", "))
	return nil
}

func nonEmptyStrings(values ...string) []string {
	filtered := make([]string, 0, len(values))
	for _, value := range values {
		if trimmed := strings.TrimSpace(value); trimmed != "" {
			filtered = append(filtered, trimmed)
		}
	}
	return filtered
}

func (app *App) handlePayTRCallback(w http.ResponseWriter, r *http.Request) {
	ctx, cancel := withTimeout(r, 12*time.Second)
	defer cancel()
	if err := r.ParseForm(); err != nil {
		http.Error(w, "bad form", http.StatusBadRequest)
		return
	}
	payload := sanitizePayTR(r.PostForm)
	merchantOID := r.PostForm.Get("merchant_oid")
	paymentLookupOID := firstNonEmpty(r.PostForm.Get("callback_id"), merchantOID)
	hashStatus := "verified"
	if !app.paytr.VerifyCallback(r.PostForm) {
		hashStatus = "failed"
		app.persistPaymentEvent(ctx, nil, merchantOID, hashStatus, payload)
		http.Error(w, "PAYTR notification failed: bad hash", http.StatusBadRequest)
		return
	}
	paymentID, orderID, amountCents, status, err := app.findPayment(ctx, paymentLookupOID)
	if err != nil {
		if errors.Is(err, sql.ErrNoRows) {
			app.persistPaymentEvent(ctx, nil, merchantOID, hashStatus, payload)
			w.Header().Set("Content-Type", "text/plain")
			_, _ = w.Write([]byte("OK"))
			return
		}
		slog.Error("payment lookup failed", "error", err)
		http.Error(w, "temporary error", http.StatusInternalServerError)
		return
	}
	app.persistPaymentEvent(ctx, &paymentID, merchantOID, hashStatus, payload)
	if status == "paid" || status == "failed" || status == "cancelled" || status == "refunded" || status == "partially_refunded" {
		w.Header().Set("Content-Type", "text/plain")
		_, _ = w.Write([]byte("OK"))
		return
	}
	paidAmount, err := paytrAmount(r.PostForm)
	if err != nil || paidAmount != amountCents {
		slog.Error("paytr amount mismatch", "merchant_oid", merchantOID, "paytr_amount", paidAmount, "expected", amountCents)
		http.Error(w, "amount mismatch", http.StatusBadRequest)
		return
	}
	if r.PostForm.Get("status") == "success" {
		if err := app.markPaymentPaid(ctx, paymentID, orderID, r.PostForm); err != nil {
			slog.Error("mark paid failed", "error", err)
			http.Error(w, "temporary error", 500)
			return
		}
		app.recordIdentityEvent(r.Context(), "payment.paid", CartIdentity{}, map[string]any{"merchant_oid": merchantOID, "payment_id": paymentID, "order_id": orderID})
	} else {
		if err := app.markPaymentFailed(ctx, paymentID, orderID, r.PostForm); err != nil {
			slog.Error("mark failed failed", "error", err)
			http.Error(w, "temporary error", 500)
			return
		}
		app.recordIdentityEvent(r.Context(), "payment.failed", CartIdentity{}, map[string]any{"merchant_oid": merchantOID, "payment_id": paymentID, "order_id": orderID})
	}
	w.Header().Set("Content-Type", "text/plain")
	_, _ = w.Write([]byte("OK"))
}

func (app *App) findPayment(ctx context.Context, merchantOID string) (int64, int64, int64, string, error) {
	var paymentID, orderID, amount int64
	var status string
	err := app.db.QueryRowContext(ctx, `SELECT id,order_id,amount_cents,status FROM payments WHERE merchant_oid=? LIMIT 1`, merchantOID).Scan(&paymentID, &orderID, &amount, &status)
	return paymentID, orderID, amount, status, err
}

func (app *App) persistPaymentEvent(ctx context.Context, paymentID *int64, merchantOID, hashStatus string, payload map[string]any) {
	raw, _ := json.Marshal(payload)
	_, _ = app.db.ExecContext(ctx, `INSERT INTO payment_events (payment_id,provider,event_type,merchant_oid,hash_status,payload,created_at,updated_at) VALUES (?,?,?,?,?,?,NOW(),NOW())`, sqlNullInt64Ptr(paymentID), "paytr", "callback", nullableString(merchantOID), hashStatus, string(raw))
}

type mapValues interface{ Get(string) string }

func (app *App) handlePaymentStatus(w http.ResponseWriter, r *http.Request) {
	user, ok := app.requireUser(w, r)
	if !ok {
		return
	}
	ctx, cancel := withTimeout(r, 4*time.Second)
	defer cancel()
	id, err := parsePathID(r, "id")
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	var status, merchantOID string
	var amount int64
	err = app.db.QueryRowContext(ctx, `SELECT p.status,p.merchant_oid,p.amount_cents
		FROM payments p
		INNER JOIN orders o ON o.id=p.order_id
		WHERE p.id=? AND o.user_id=? AND o.tenant_id=?`, id, user.ID, app.cfg.TenantID).Scan(&status, &merchantOID, &amount)
	if err != nil {
		if errors.Is(err, sql.ErrNoRows) {
			app.handleErr(w, r, ErrNotFound)
			return
		}
		app.handleErr(w, r, err)
		return
	}
	writeData(w, http.StatusOK, map[string]any{"id": id, "status": status, "merchant_oid": merchantOID, "amount_cents": amount})
}

func (app *App) releaseOrderStockTx(ctx context.Context, tx *sql.Tx, orderID int64) error {
	rows, err := tx.QueryContext(ctx, `SELECT product_id,quantity FROM order_items WHERE order_id=? AND product_id IS NOT NULL`, orderID)
	if err != nil {
		return err
	}
	type stockRelease struct {
		productID int64
		quantity  int
	}
	releases := make([]stockRelease, 0, 8)
	for rows.Next() {
		var release stockRelease
		if err := rows.Scan(&release.productID, &release.quantity); err != nil {
			_ = rows.Close()
			return err
		}
		releases = append(releases, release)
	}
	if err := rows.Err(); err != nil {
		_ = rows.Close()
		return err
	}
	if err := rows.Close(); err != nil {
		return err
	}
	for _, release := range releases {
		if _, err := tx.ExecContext(ctx, `UPDATE products SET stock_quantity=stock_quantity+?, updated_at=NOW() WHERE id=?`, release.quantity, release.productID); err != nil {
			return err
		}
	}
	return nil
}

func (app *App) clearOrderCartTx(ctx context.Context, tx *sql.Tx, orderID int64) error {
	var userID sql.NullInt64
	var metadata sql.NullString
	var tenantID int64
	if err := tx.QueryRowContext(ctx, `SELECT tenant_id,user_id,metadata FROM orders WHERE id=?`, orderID).Scan(&tenantID, &userID, &metadata); err != nil {
		return err
	}
	var meta map[string]any
	if metadata.Valid {
		_ = json.Unmarshal([]byte(metadata.String), &meta)
	}
	if userID.Valid {
		if _, err := tx.ExecContext(ctx, `DELETE FROM cart_items WHERE tenant_id=? AND user_id=?`, tenantID, userID.Int64); err != nil {
			return err
		}
		if _, err := tx.ExecContext(ctx, `DELETE FROM cart_coupons WHERE tenant_id=? AND user_id=?`, tenantID, userID.Int64); err != nil {
			return err
		}
	}
	if raw, ok := meta["cart_token"]; ok {
		if token, ok := raw.(string); ok && token != "" {
			if _, err := tx.ExecContext(ctx, `DELETE FROM cart_items WHERE tenant_id=? AND cart_token=?`, tenantID, token); err != nil {
				return err
			}
			if _, err := tx.ExecContext(ctx, `DELETE FROM cart_coupons WHERE tenant_id=? AND cart_token=?`, tenantID, token); err != nil {
				return err
			}
		}
	}
	return nil
}

func (app *App) updatePaymentPayloadTx(ctx context.Context, tx *sql.Tx, paymentID int64, values mapValues, success bool) error {
	raw, _ := json.Marshal(formToMap(valuesToURLValues(values)))
	if success {
		total, _ := strconv.ParseInt(values.Get("total_amount"), 10, 64)
		_, err := tx.ExecContext(ctx, `UPDATE payments SET status='paid',captured_amount_cents=?,payment_type=?,provider_payload=?,confirmed_at=NOW(),updated_at=NOW() WHERE id=?`, total, nullableString(values.Get("payment_type")), string(raw), paymentID)
		return err
	}
	_, err := tx.ExecContext(ctx, `UPDATE payments SET status='failed',failed_reason_code=?,failed_reason_msg=?,provider_payload=?,updated_at=NOW() WHERE id=?`, nullableString(values.Get("failed_reason_code")), nullableString(values.Get("failed_reason_msg")), string(raw), paymentID)
	return err
}

func valuesToURLValues(v mapValues) url.Values {
	// Callback passes url.Values; this fallback keeps interface simple for tests.
	if vv, ok := v.(url.Values); ok {
		return vv
	}
	keys := []string{"merchant_oid", "status", "total_amount", "payment_amount", "payment_type", "failed_reason_code", "failed_reason_msg"}
	out := url.Values{}
	for _, k := range keys {
		if value := v.Get(k); value != "" {
			out[k] = []string{value}
		}
	}
	return out
}

func (app *App) markPaymentPaidReal(ctx context.Context, paymentID, orderID int64, values mapValues) error {
	tx, err := app.db.BeginTx(ctx, &sql.TxOptions{Isolation: sql.LevelReadCommitted})
	if err != nil {
		return err
	}
	defer tx.Rollback()
	var paymentStatus string
	if err := tx.QueryRowContext(ctx, `SELECT status FROM payments WHERE id=? AND order_id=? FOR UPDATE`, paymentID, orderID).Scan(&paymentStatus); err != nil {
		return err
	}
	if paymentStatus != "pending" {
		return nil
	}
	if err := app.updatePaymentPayloadTx(ctx, tx, paymentID, values, true); err != nil {
		return err
	}
	if _, err := tx.ExecContext(ctx, `UPDATE orders SET status='reviewing',paid_at=NOW(),updated_at=NOW() WHERE id=?`, orderID); err != nil {
		return err
	}
	if err := app.clearOrderCartTx(ctx, tx, orderID); err != nil {
		return err
	}
	if err := app.awardMobilePurchasePointTx(ctx, tx, orderID); err != nil {
		return err
	}
	return tx.Commit()
}

func (app *App) markPaymentFailedReal(ctx context.Context, paymentID, orderID int64, values mapValues) error {
	tx, err := app.db.BeginTx(ctx, &sql.TxOptions{Isolation: sql.LevelReadCommitted})
	if err != nil {
		return err
	}
	defer tx.Rollback()
	var paymentStatus string
	if err := tx.QueryRowContext(ctx, `SELECT status FROM payments WHERE id=? AND order_id=? FOR UPDATE`, paymentID, orderID).Scan(&paymentStatus); err != nil {
		return err
	}
	if paymentStatus != "pending" {
		return nil
	}
	if err := app.updatePaymentPayloadTx(ctx, tx, paymentID, values, false); err != nil {
		return err
	}
	if _, err := tx.ExecContext(ctx, `UPDATE orders SET status='failed',updated_at=NOW() WHERE id=?`, orderID); err != nil {
		return err
	}
	if err := app.releaseOrderStockTx(ctx, tx, orderID); err != nil {
		return err
	}
	return tx.Commit()
}

func (app *App) markPaymentPaid(ctx context.Context, paymentID, orderID int64, values mapValues) error {
	return app.markPaymentPaidReal(ctx, paymentID, orderID, values)
}
func (app *App) markPaymentFailed(ctx context.Context, paymentID, orderID int64, values mapValues) error {
	return app.markPaymentFailedReal(ctx, paymentID, orderID, values)
}
