package api

import (
	"context"
	"database/sql"
	"encoding/json"
	"errors"
	"fmt"
	"strings"
	"time"
)

func (app *App) createCheckoutOrder(ctx context.Context, id CartIdentity, req CheckoutRequest) (OrderRecord, error) {
	customerName := strings.TrimSpace(req.Customer.Name)
	email := strings.TrimSpace(req.Customer.Email)
	phone := strings.TrimSpace(req.Customer.Phone)
	address := strings.TrimSpace(req.Shipping.Address)
	if customerName == "" || email == "" || phone == "" || address == "" {
		return OrderRecord{}, fmt.Errorf("%w: Müşteri ve teslimat bilgileri zorunludur.", ErrBadRequest)
	}
	cart, err := app.cart(ctx, id)
	if err != nil {
		return OrderRecord{}, err
	}
	if len(cart.Items) == 0 && len(req.Items) > 0 {
		cart, err = app.checkoutCartFromItems(ctx, id, req.Items, req.CouponCode)
		if err != nil {
			return OrderRecord{}, err
		}
	}
	if len(cart.Items) == 0 {
		return OrderRecord{}, fmt.Errorf("%w: Sepet boş.", ErrBadRequest)
	}

	tx, err := app.db.BeginTx(ctx, &sql.TxOptions{Isolation: sql.LevelReadCommitted})
	if err != nil {
		return OrderRecord{}, err
	}
	defer tx.Rollback()

	merchantOID := fmt.Sprintf("KGM%d%s", time.Now().UnixNano(), randomHex(4))
	identity := requestIdentity(ctx)
	customerUID := firstNonEmpty(derefString(id.CustomerUID), identity.CustomerUID)
	sessionUID := identity.SessionUID
	localDelivery := isKaracabeyDelivery(req.Shipping.City, req.Shipping.District)
	paymentFlow := checkoutPaymentFlow(req.PaymentFlow)
	cashOnDelivery := paymentFlow == "cash_on_delivery"
	if app.cfg.MinOrderCents > 0 && cart.SubtotalCents < app.cfg.MinOrderCents {
		return OrderRecord{}, fmt.Errorf("%w: Servis için minimum sepet tutarı %.2f TL.", ErrBadRequest, float64(app.cfg.MinOrderCents)/100)
	}
	if cashOnDelivery && !localDelivery {
		return OrderRecord{}, fmt.Errorf("%w: Kapıda ödeme yalnızca Bursa / Karacabey teslimat adreslerinde kullanılabilir.", ErrBadRequest)
	}
	discount := discountCents(cart)
	shippingCents := int64(0)
	if !localDelivery && app.cfg.FreeShippingCents > 0 && cart.SubtotalCents < app.cfg.FreeShippingCents {
		shippingCents = app.cfg.StandardShippingCents
	}
	totalCents := cart.SubtotalCents - discount + shippingCents
	if totalCents < 0 {
		totalCents = 0
	}
	invoiceType := firstNonEmpty(req.Invoice.Type, req.OrderType, "individual")
	metadata := map[string]any{
		"cart_token": id.CartToken, "customer_uid": customerUID, "session_uid": sessionUID,
		"checkout_key": req.CheckoutKey, "checkout_uid": req.CheckoutUID, "payment_uid": req.PaymentUID,
		"stock_reserved": true, "source": normalizeOrderSource(req.Source), "created_via": "go-api", "local_delivery": localDelivery,
		"payment_flow": paymentFlow, "cash_on_delivery": cashOnDelivery, "meal_card_accepted": cashOnDelivery,
		"shipping_cents": shippingCents, "carrier": req.ShippingQuote.Carrier,
		"invoice": map[string]any{
			"type":         invoiceType,
			"tc_identity":  firstNonEmpty(req.Invoice.TCIdentity, req.Customer.TCIdentity),
			"company_name": firstNonEmpty(req.Invoice.CompanyName, req.Customer.CompanyName),
			"tax_office":   firstNonEmpty(req.Invoice.TaxOffice, req.Customer.TaxOffice),
			"tax_number":   firstNonEmpty(req.Invoice.TaxNumber, req.Customer.TaxNumber),
		},
		"geo": map[string]any{"lat": req.Shipping.Lat, "lng": req.Shipping.Lng},
	}
	metadataJSON, _ := json.Marshal(metadata)
	shippingAddress := strings.TrimSpace(strings.Join([]string{req.Shipping.City, req.Shipping.District, address}, " "))

	orderStatus := "awaiting_payment"
	paymentProvider := "paytr"
	if cashOnDelivery {
		orderStatus = "reviewing"
		paymentProvider = "cash_on_delivery"
	}
	res, err := tx.ExecContext(ctx, `INSERT INTO orders (tenant_id,user_id,customer_uid,session_uid,checkout_uid,payment_uid,merchant_oid,status,currency,subtotal_cents,shipping_cents,discount_cents,total_cents,customer_name,customer_email,customer_phone,shipping_city,shipping_district,shipping_address,metadata,created_at,updated_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())`, app.cfg.TenantID, sqlNullInt64Ptr(id.UserID), nullableString(customerUID), nullableString(sessionUID), nullableString(req.CheckoutUID), nullableString(req.PaymentUID), merchantOID, orderStatus, "TL", cart.SubtotalCents, shippingCents, discount, totalCents, customerName, email, phone, nullableString(req.Shipping.City), nullableString(req.Shipping.District), shippingAddress, string(metadataJSON))
	if err != nil {
		return OrderRecord{}, err
	}
	orderID, err := res.LastInsertId()
	if err != nil {
		return OrderRecord{}, err
	}

	orderItems := make([]OrderItemRecord, 0, len(cart.Items))
	for _, item := range cart.Items {
		var stock int
		if err := tx.QueryRowContext(ctx, `SELECT stock_quantity FROM products WHERE id=? AND tenant_id=? AND is_active=1 AND price_cents>0 FOR UPDATE`, item.Product.ID, app.cfg.TenantID).Scan(&stock); err != nil {
			if errors.Is(err, sql.ErrNoRows) {
				return OrderRecord{}, ErrNotFound
			}
			return OrderRecord{}, err
		}
		if stock < item.Quantity {
			return OrderRecord{}, fmt.Errorf("%w: %s için yeterli stok yok.", ErrConflict, item.Product.Name)
		}
		_, err := tx.ExecContext(ctx, `UPDATE products SET stock_quantity=stock_quantity-?, updated_at=NOW() WHERE id=? AND tenant_id=?`, item.Quantity, item.Product.ID, app.cfg.TenantID)
		if err != nil {
			return OrderRecord{}, err
		}
		_, err = tx.ExecContext(ctx, `INSERT INTO order_items (order_id,product_id,name,unit_price_cents,quantity,line_total_cents,metadata,created_at,updated_at) VALUES (?,?,?,?,?,?,NULL,NOW(),NOW())`, orderID, item.Product.ID, item.Product.Name, item.Product.PriceCents, item.Quantity, item.LineTotalCents)
		if err != nil {
			return OrderRecord{}, err
		}
		pid := item.Product.ID
		orderItems = append(orderItems, OrderItemRecord{ProductID: &pid, Name: item.Product.Name, UnitPriceCents: item.Product.PriceCents, Quantity: item.Quantity, LineTotalCents: item.LineTotalCents})
	}

	paymentRes, err := tx.ExecContext(ctx, `INSERT INTO payments (order_id,provider,merchant_oid,payment_uid,customer_uid,checkout_uid,status,amount_cents,currency,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,NOW(),NOW())`, orderID, paymentProvider, merchantOID, nullableString(req.PaymentUID), nullableString(customerUID), nullableString(req.CheckoutUID), "pending", totalCents, "TL")
	if err != nil {
		return OrderRecord{}, err
	}
	paymentID, err := paymentRes.LastInsertId()
	if err != nil {
		return OrderRecord{}, err
	}

	if cashOnDelivery {
		if err := app.clearOrderCartTx(ctx, tx, orderID); err != nil {
			return OrderRecord{}, err
		}
	}

	if err := tx.Commit(); err != nil {
		return OrderRecord{}, err
	}
	app.touchCustomerSync(ctx, id, "checkout.order_created")
	return OrderRecord{ID: orderID, PaymentID: paymentID, MerchantOID: merchantOID, CustomerEmail: email, CustomerName: customerName, CustomerPhone: phone, ShippingAddress: shippingAddress, TotalCents: totalCents, SubtotalCents: cart.SubtotalCents, DiscountCents: discount, UserID: id.UserID, CartToken: id.CartToken, Items: orderItems}, nil
}

func discountCents(cart CartData) int64 {
	if cart.AppliedCoupon == nil {
		return 0
	}
	return cart.AppliedCoupon.DiscountCents
}

func (app *App) checkoutCartFromItems(ctx context.Context, id CartIdentity, inputs []CheckoutLineInput, couponCode string) (CartData, error) {
	quantities := map[int64]int{}
	for _, input := range inputs {
		if input.ProductID <= 0 {
			continue
		}
		quantities[input.ProductID] = clampQuantity(quantities[input.ProductID] + input.Quantity)
	}
	if len(quantities) == 0 {
		return CartData{CustomerUID: id.CustomerUID, CartToken: id.CartToken, Items: []CartLineItem{}}, nil
	}

	items := make([]CartLineItem, 0, len(quantities))
	var subtotal int64
	for productID, quantity := range quantities {
		var product CartProduct
		var brand, image sql.NullString
		err := app.db.QueryRowContext(ctx, `SELECT id,name,slug,brand,price_cents,stock_quantity,COALESCE(NULLIF(cdn_image_url,''), image_url)
			FROM products WHERE tenant_id=? AND id=? AND is_active=1 AND price_cents>0 LIMIT 1`, app.cfg.TenantID, productID).
			Scan(&product.ID, &product.Name, &product.Slug, &brand, &product.PriceCents, &product.StockQuantity, &image)
		if err != nil {
			if errors.Is(err, sql.ErrNoRows) {
				return CartData{}, ErrNotFound
			}
			return CartData{}, err
		}
		if product.StockQuantity < quantity {
			return CartData{}, fmt.Errorf("%w: %s için yeterli stok yok.", ErrConflict, product.Name)
		}
		product.Brand = ptrString(brand)
		product.ImageURL = app.publicImageURL(ptrString(image))
		product.Price = moneyTRY(product.PriceCents)
		lineTotal := product.PriceCents * int64(quantity)
		subtotal += lineTotal
		items = append(items, CartLineItem{
			Quantity:       quantity,
			LineTotalCents: lineTotal,
			Product:        product,
		})
	}

	coupon, total, err := app.checkoutCouponFromCode(ctx, couponCode, subtotal)
	if err != nil {
		return CartData{}, err
	}
	return CartData{CustomerUID: id.CustomerUID, CartToken: id.CartToken, Items: items, AppliedCoupon: coupon, SubtotalCents: subtotal, TotalCents: total}, nil
}

func (app *App) checkoutCouponFromCode(ctx context.Context, code string, subtotal int64) (*AppliedCoupon, int64, error) {
	code = strings.ToUpper(strings.TrimSpace(code))
	if code == "" {
		return nil, subtotal, nil
	}

	row := app.db.QueryRowContext(ctx, `SELECT code,discount_type,discount_value,minimum_order_cents
		FROM coupons
		WHERE tenant_id=? AND code=? AND is_active=1 AND (starts_at IS NULL OR starts_at<=NOW()) AND (ends_at IS NULL OR ends_at>=NOW()) LIMIT 1`, app.cfg.TenantID, code)
	var storedCode, kind string
	var value, minimum int64
	if err := row.Scan(&storedCode, &kind, &value, &minimum); err != nil {
		if errors.Is(err, sql.ErrNoRows) {
			return nil, subtotal, nil
		}
		return nil, 0, err
	}
	if subtotal < minimum {
		return nil, subtotal, nil
	}

	discount := value
	if kind == "percent" {
		discount = subtotal * value / 100
	}
	if discount > subtotal {
		discount = subtotal
	}
	total := subtotal - discount
	return &AppliedCoupon{Code: storedCode, DiscountType: kind, DiscountValue: value, DiscountCents: discount, TotalCents: total}, total, nil
}

func (app *App) storePayTRToken(ctx context.Context, merchantOID, token string, raw map[string]any) {
	payload, _ := json.Marshal(raw)
	_, _ = app.db.ExecContext(ctx, `UPDATE payments SET provider_token=?, provider_payload=?, updated_at=NOW() WHERE merchant_oid=?`, token, string(payload), merchantOID)
}

func isKaracabeyDelivery(city, district string) bool {
	normalize := func(value string) string {
		value = strings.ToLower(strings.TrimSpace(value))
		value = strings.NewReplacer("ı", "i", "İ", "i", "ş", "s", "ğ", "g", "ü", "u", "ö", "o", "ç", "c").Replace(value)
		return value
	}
	return strings.Contains(normalize(city), "bursa") && strings.Contains(normalize(district), "karacabey")
}

func checkoutPaymentFlow(flow string) string {
	switch strings.ToLower(strings.TrimSpace(flow)) {
	case "cash", "cash_on_delivery", "cod", "kapida_odeme":
		return "cash_on_delivery"
	case "direct", "card":
		return "direct"
	default:
		return "iframe"
	}
}
