package api

import (
	"context"
	"database/sql"
	"errors"
	"net/http"
	"strconv"
	"strings"
	"time"
)

type AccountOrderItem struct {
	ID             int64   `json:"id"`
	ProductID      *int64  `json:"product_id,omitempty"`
	Slug           *string `json:"slug,omitempty"`
	ImageURL       *string `json:"image_url,omitempty"`
	Name           string  `json:"name"`
	Quantity       int     `json:"quantity"`
	UnitPriceCents int64   `json:"unit_price_cents"`
	LineTotalCents int64   `json:"line_total_cents"`
}

type AccountShipmentResponse struct {
	ID             int64      `json:"id"`
	Carrier        string     `json:"carrier"`
	TrackingNumber *string    `json:"tracking_number,omitempty"`
	Status         string     `json:"status"`
	TrackingURL    *string    `json:"tracking_url,omitempty"`
	ShippedAt      *time.Time `json:"shipped_at,omitempty"`
	DeliveredAt    *time.Time `json:"delivered_at,omitempty"`
	UpdatedAt      time.Time  `json:"updated_at"`
}

type AccountOrderStatusEvent struct {
	ID        string    `json:"id"`
	Status    string    `json:"status"`
	Timestamp time.Time `json:"timestamp"`
	Note      *string   `json:"note,omitempty"`
	Source    string    `json:"source,omitempty"`
}

type AccountOrderResponse struct {
	ID               int64                     `json:"id"`
	MerchantOID      string                    `json:"merchant_oid"`
	CheckoutRef      string                    `json:"checkout_ref"`
	Status           string                    `json:"status"`
	StatusLabel      string                    `json:"status_label"`
	Currency         string                    `json:"currency"`
	SubtotalCents    int64                     `json:"subtotal_cents"`
	ShippingCents    int64                     `json:"shipping_cents"`
	DiscountCents    int64                     `json:"discount_cents"`
	TotalCents       int64                     `json:"total_cents"`
	CustomerName     string                    `json:"customer_name"`
	CustomerEmail    string                    `json:"customer_email"`
	CustomerPhone    string                    `json:"customer_phone"`
	ShippingCity     *string                   `json:"shipping_city,omitempty"`
	ShippingDistrict *string                   `json:"shipping_district,omitempty"`
	ShippingAddress  string                    `json:"shipping_address"`
	PaidAt           *time.Time                `json:"paid_at,omitempty"`
	CreatedAt        time.Time                 `json:"created_at"`
	Items            []AccountOrderItem        `json:"items"`
	Shipment         *AccountShipmentResponse  `json:"shipment,omitempty"`
	StatusHistory    []AccountOrderStatusEvent `json:"status_history,omitempty"`
}

type CustomerDashboardResponse struct {
	Summary      map[string]any         `json:"summary"`
	Identity     RequestIdentity        `json:"identity"`
	Sync         map[string]any         `json:"sync"`
	RecentOrders []AccountOrderResponse `json:"recent_orders"`
	QuickActions []map[string]string    `json:"quick_actions"`
	ServerAt     time.Time              `json:"server_at"`
}

type CustomerCouponResponse struct {
	ID                int64      `json:"id"`
	Code              string     `json:"code"`
	DiscountType      string     `json:"discount_type"`
	DiscountValue     int64      `json:"discount_value"`
	MinimumOrderCents int64      `json:"minimum_order_cents"`
	StartsAt          *time.Time `json:"starts_at,omitempty"`
	EndsAt            *time.Time `json:"ends_at,omitempty"`
	IsActive          bool       `json:"is_active"`
	UsageLimit        *int64     `json:"usage_limit,omitempty"`
	UsedCount         int64      `json:"used_count"`
}

func (app *App) handleCustomerDashboard(w http.ResponseWriter, r *http.Request) {
	user, ok := app.requireUser(w, r)
	if !ok {
		return
	}
	ctx, cancel := withTimeout(r, 5*time.Second)
	defer cancel()

	ordersTotal := app.countForUser(ctx, "orders", user.ID, "")
	ordersActive := app.countForUser(ctx, "orders", user.ID, "AND status IN ('awaiting_payment','reviewing','paid','preparing','shipping','in_delivery')")
	addressesTotal := app.countForUser(ctx, "addresses", user.ID, "")
	favoritesTotal := app.countForUser(ctx, "favorites", user.ID, "")
	unreadNotifications := app.countForUser(ctx, "notifications", user.ID, "AND read_at IS NULL")

	recentOrders, _ := app.accountOrders(ctx, user.ID, 3)

	var cartItems int64
	var cartTotal int64
	identity := requestIdentity(r.Context())
	if id, err := app.identityFromRequest(ctx, r); err == nil {
		if cart, err := app.cart(ctx, id); err == nil {
			cartItems = int64(len(cart.Items))
			cartTotal = cart.TotalCents
			setCartIdentityHeaders(w, cart)
		}
	}

	syncID := CartIdentity{UserID: &user.ID, CustomerUID: user.CustomerUID}
	version, reason, updatedAt := app.customerSyncState(ctx, syncID)
	w.Header().Set("X-Customer-Sync-Version", strconvFormatInt(version))

	writeData(w, http.StatusOK, CustomerDashboardResponse{
		Summary: map[string]any{
			"orders_total":         ordersTotal,
			"orders_active":        ordersActive,
			"addresses_total":      addressesTotal,
			"favorites_total":      favoritesTotal,
			"unread_notifications": unreadNotifications,
			"cart_items_count":     cartItems,
			"cart_total_cents":     cartTotal,
		},
		Identity: identity,
		Sync: map[string]any{
			"version":    version,
			"reason":     reason,
			"updated_at": updatedAt,
		},
		RecentOrders: recentOrders,
		QuickActions: []map[string]string{
			{"label": "Tekrar alışveriş yap", "href": "/products", "kind": "shopping"},
			{"label": "Adresleri düzenle", "href": "/account/addresses", "kind": "address"},
			{"label": "Bildirimleri gör", "href": "/notifications", "kind": "notification"},
		},
		ServerAt: time.Now().UTC(),
	})
}

func (app *App) handleOrdersIndex(w http.ResponseWriter, r *http.Request) {
	user, ok := app.requireUser(w, r)
	if !ok {
		return
	}
	ctx, cancel := withTimeout(r, 5*time.Second)
	defer cancel()
	items, err := app.accountOrders(ctx, user.ID, 50)
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"data": items, "total": len(items), "per_page": 50, "current_page": 1, "last_page": 1})
}

func (app *App) handleOrderShow(w http.ResponseWriter, r *http.Request) {
	user, ok := app.requireUser(w, r)
	if !ok {
		return
	}
	ctx, cancel := withTimeout(r, 5*time.Second)
	defer cancel()
	id, err := parsePathID(r, "id")
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	order, err := app.accountOrderByID(ctx, user.ID, id)
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	writeData(w, http.StatusOK, order)
}

func (app *App) handleAddressesIndex(w http.ResponseWriter, r *http.Request) {
	user, ok := app.requireUser(w, r)
	if !ok {
		return
	}
	ctx, cancel := withTimeout(r, 4*time.Second)
	defer cancel()
	rows, err := app.db.QueryContext(ctx, `SELECT id,title,recipient_name,phone,city,district,neighborhood,address_line,postal_code,is_default FROM addresses WHERE user_id=? ORDER BY is_default DESC,id DESC`, user.ID)
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	defer rows.Close()
	items := []map[string]any{}
	for rows.Next() {
		var id int64
		var title, name, phone, city, district, address string
		var neigh, postal sql.NullString
		var def bool
		if err := rows.Scan(&id, &title, &name, &phone, &city, &district, &neigh, &address, &postal, &def); err != nil {
			app.handleErr(w, r, err)
			return
		}
		items = append(items, map[string]any{"id": id, "title": title, "recipient_name": name, "phone": phone, "city": city, "district": district, "neighborhood": ptrString(neigh), "address_line": address, "postal_code": ptrString(postal), "is_default": def})
	}
	writeData(w, http.StatusOK, items)
}

func (app *App) handleFavoritesIndex(w http.ResponseWriter, r *http.Request) {
	user, ok := app.requireUser(w, r)
	if !ok {
		return
	}
	ctx, cancel := withTimeout(r, 4*time.Second)
	defer cancel()
	rows, err := app.db.QueryContext(ctx, `SELECT p.id,p.name,p.slug,p.brand,p.price_cents,p.image_url
		FROM favorites f
		JOIN products p ON p.id=f.product_id
		WHERE f.user_id=? AND p.is_active=1
		ORDER BY f.id DESC LIMIT 100`, user.ID)
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	defer rows.Close()
	items := []map[string]any{}
	for rows.Next() {
		var id, price int64
		var name, slug string
		var brand, image sql.NullString
		if err := rows.Scan(&id, &name, &slug, &brand, &price, &image); err != nil {
			app.handleErr(w, r, err)
			return
		}
		items = append(items, map[string]any{"id": id, "name": name, "slug": slug, "brand": ptrString(brand), "price_cents": price, "price": moneyTRY(price), "image_url": ptrString(image)})
	}
	writeData(w, http.StatusOK, items)
}

func (app *App) handleFavoriteAdd(w http.ResponseWriter, r *http.Request) {
	user, ok := app.requireUser(w, r)
	if !ok {
		return
	}
	slug := strings.TrimSpace(r.PathValue("slug"))
	if slug == "" {
		app.handleErr(w, r, ErrBadRequest)
		return
	}
	ctx, cancel := withTimeout(r, 4*time.Second)
	defer cancel()
	var productID int64
	if err := app.db.QueryRowContext(ctx, `SELECT id FROM products WHERE slug=? AND is_active=1 LIMIT 1`, slug).Scan(&productID); err != nil {
		if errors.Is(err, sql.ErrNoRows) {
			app.handleErr(w, r, ErrNotFound)
			return
		}
		app.handleErr(w, r, err)
		return
	}
	_, err := app.db.ExecContext(ctx, `INSERT IGNORE INTO favorites (user_id,product_id,created_at,updated_at) VALUES (?,?,NOW(),NOW())`, user.ID, productID)
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	app.touchCustomerSync(ctx, CartIdentity{UserID: &user.ID, CustomerUID: user.CustomerUID}, "favorite.added")
	writeData(w, http.StatusOK, map[string]bool{"favorited": true})
}

func (app *App) handleFavoriteDelete(w http.ResponseWriter, r *http.Request) {
	user, ok := app.requireUser(w, r)
	if !ok {
		return
	}
	slug := strings.TrimSpace(r.PathValue("slug"))
	ctx, cancel := withTimeout(r, 4*time.Second)
	defer cancel()
	_, err := app.db.ExecContext(ctx, `DELETE f FROM favorites f JOIN products p ON p.id=f.product_id WHERE f.user_id=? AND p.slug=?`, user.ID, slug)
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	app.touchCustomerSync(ctx, CartIdentity{UserID: &user.ID, CustomerUID: user.CustomerUID}, "favorite.removed")
	writeData(w, http.StatusOK, map[string]bool{"favorited": false})
}

func (app *App) handleCustomerCoupons(w http.ResponseWriter, r *http.Request) {
	user, ok := app.requireUser(w, r)
	if !ok {
		return
	}
	_ = user
	ctx, cancel := withTimeout(r, 4*time.Second)
	defer cancel()
	rows, err := app.db.QueryContext(ctx, `SELECT id,code,discount_type,discount_value,minimum_order_cents,starts_at,ends_at,is_active,usage_limit,used_count
		FROM coupons
		WHERE tenant_id=? AND is_active=1 AND (starts_at IS NULL OR starts_at<=NOW()) AND (ends_at IS NULL OR ends_at>=NOW())
		ORDER BY id DESC LIMIT 25`, app.cfg.TenantID)
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	defer rows.Close()
	items := []CustomerCouponResponse{}
	for rows.Next() {
		var c CustomerCouponResponse
		var starts, ends sql.NullTime
		var limit sql.NullInt64
		if err := rows.Scan(&c.ID, &c.Code, &c.DiscountType, &c.DiscountValue, &c.MinimumOrderCents, &starts, &ends, &c.IsActive, &limit, &c.UsedCount); err != nil {
			app.handleErr(w, r, err)
			return
		}
		if starts.Valid {
			c.StartsAt = &starts.Time
		}
		if ends.Valid {
			c.EndsAt = &ends.Time
		}
		if limit.Valid {
			c.UsageLimit = &limit.Int64
		}
		items = append(items, c)
	}
	writeData(w, http.StatusOK, items)
}

func (app *App) handleNotifications(w http.ResponseWriter, r *http.Request) {
	user, ok := app.requireUser(w, r)
	if !ok {
		return
	}
	limit := parseIntQuery(r, "limit", 25, 1, 100)
	page := parseIntQuery(r, "page", 1, 1, 100000)
	offset := (page - 1) * limit
	status := strings.ToLower(strings.TrimSpace(r.URL.Query().Get("status")))
	if status == "" {
		status = "unread"
	}
	ctx, cancel := withTimeout(r, 4*time.Second)
	defer cancel()

	unread := app.countForUser(ctx, "notifications", user.ID, "AND read_at IS NULL")
	statusFilter := ""
	if status == "unread" {
		statusFilter = "AND read_at IS NULL"
	} else if status == "read" {
		statusFilter = "AND read_at IS NOT NULL"
	}
	rows, err := app.db.QueryContext(ctx, `SELECT id,type,title,body,data,read_at,created_at FROM notifications WHERE tenant_id=? AND user_id=? `+statusFilter+` ORDER BY id DESC LIMIT ? OFFSET ?`, app.cfg.TenantID, user.ID, limit, offset)
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	defer rows.Close()
	items := []map[string]any{}
	for rows.Next() {
		var id int64
		var typ, title, body string
		var data sql.NullString
		var readAt sql.NullTime
		var created time.Time
		if err := rows.Scan(&id, &typ, &title, &body, &data, &readAt, &created); err != nil {
			app.handleErr(w, r, err)
			return
		}
		payload := parseJSONMap(data)
		items = append(items, map[string]any{
			"id":         strconvFormatInt(id),
			"type":       typ,
			"title":      title,
			"body":       body,
			"payload":    payload,
			"action_url": payloadString(payload, "action_url"),
			"image_url":  payloadString(payload, "image_url"),
			"cta_title":  payloadString(payload, "cta_title"),
			"read_at":    nullTimeString(readAt),
			"created_at": created.Format(time.RFC3339),
		})
	}
	writeJSON(w, http.StatusOK, map[string]any{"data": items, "meta": map[string]any{"unread_count": unread, "page": page, "limit": limit}})
}

func (app *App) handleNotificationReadAll(w http.ResponseWriter, r *http.Request) {
	user, ok := app.requireUser(w, r)
	if !ok {
		return
	}
	ctx, cancel := withTimeout(r, 4*time.Second)
	defer cancel()
	_, err := app.db.ExecContext(ctx, `UPDATE notifications SET read_at=COALESCE(read_at,NOW()),updated_at=NOW() WHERE tenant_id=? AND user_id=? AND read_at IS NULL`, app.cfg.TenantID, user.ID)
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	app.touchCustomerSync(ctx, CartIdentity{UserID: &user.ID, CustomerUID: user.CustomerUID}, "notifications.read_all")
	writeData(w, http.StatusOK, map[string]string{"status": "ok"})
}

func (app *App) handleNotificationRead(w http.ResponseWriter, r *http.Request) {
	user, ok := app.requireUser(w, r)
	if !ok {
		return
	}
	id, err := parsePathID(r, "id")
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	ctx, cancel := withTimeout(r, 4*time.Second)
	defer cancel()
	_, err = app.db.ExecContext(ctx, `UPDATE notifications SET read_at=COALESCE(read_at,NOW()),updated_at=NOW() WHERE tenant_id=? AND user_id=? AND id=?`, app.cfg.TenantID, user.ID, id)
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	app.touchCustomerSync(ctx, CartIdentity{UserID: &user.ID, CustomerUID: user.CustomerUID}, "notification.read")
	item := app.notificationByID(ctx, user.ID, id)
	writeData(w, http.StatusOK, item)
}

func (app *App) handleNotificationDelete(w http.ResponseWriter, r *http.Request) {
	user, ok := app.requireUser(w, r)
	if !ok {
		return
	}
	id, err := parsePathID(r, "id")
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	ctx, cancel := withTimeout(r, 4*time.Second)
	defer cancel()
	res, err := app.db.ExecContext(ctx, `DELETE FROM notifications WHERE tenant_id=? AND user_id=? AND id=?`, app.cfg.TenantID, user.ID, id)
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	if rows, _ := res.RowsAffected(); rows == 0 {
		app.handleErr(w, r, ErrNotFound)
		return
	}
	app.touchCustomerSync(ctx, CartIdentity{UserID: &user.ID, CustomerUID: user.CustomerUID}, "notification.delete")
	writeData(w, http.StatusOK, map[string]string{"status": "deleted"})
}

func (app *App) handleDeviceToken(w http.ResponseWriter, r *http.Request) {
	user, ok := app.requireUser(w, r)
	if !ok {
		return
	}
	var body struct {
		DeviceToken string `json:"device_token"`
		Token       string `json:"token"`
		Platform    string `json:"platform"`
		DeviceID    string `json:"device_id"`
		DeviceName  string `json:"device_name"`
		AppVersion  string `json:"app_version"`
		Locale      string `json:"locale"`
		Timezone    string `json:"timezone"`
	}
	if err := parseJSON(r, &body); err != nil {
		app.handleErr(w, r, ErrBadRequest)
		return
	}
	token := strings.TrimSpace(firstNonEmpty(body.Token, body.DeviceToken))
	if token == "" {
		app.handleErr(w, r, ErrBadRequest)
		return
	}
	ctx, cancel := withTimeout(r, 4*time.Second)
	defer cancel()
	_, err := app.db.ExecContext(ctx, `INSERT INTO device_tokens (user_id,token,device_type,device_name,is_active,created_at,updated_at) VALUES (?,?,?,?,1,NOW(),NOW()) ON DUPLICATE KEY UPDATE user_id=VALUES(user_id),device_type=VALUES(device_type),device_name=VALUES(device_name),is_active=1,updated_at=NOW()`, user.ID, token, firstNonEmpty(body.Platform, "ios"), firstNonEmpty(body.DeviceName, "customer-device"))
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	writeData(w, http.StatusOK, map[string]string{"status": "ok"})
}

func (app *App) accountOrders(ctx context.Context, userID int64, limit int) ([]AccountOrderResponse, error) {
	rows, err := app.db.QueryContext(ctx, `SELECT id,merchant_oid,COALESCE(checkout_ref,''),status,currency,COALESCE(subtotal_cents,0),COALESCE(shipping_cents,0),COALESCE(discount_cents,0),total_cents,COALESCE(customer_name,''),COALESCE(customer_email,''),COALESCE(customer_phone,''),shipping_city,shipping_district,COALESCE(shipping_address,''),paid_at,created_at FROM orders WHERE user_id=? ORDER BY id DESC LIMIT ?`, userID, limit)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	orders := []AccountOrderResponse{}
	for rows.Next() {
		var o AccountOrderResponse
		var city, district sql.NullString
		var paid sql.NullTime
		if err := rows.Scan(&o.ID, &o.MerchantOID, &o.CheckoutRef, &o.Status, &o.Currency, &o.SubtotalCents, &o.ShippingCents, &o.DiscountCents, &o.TotalCents, &o.CustomerName, &o.CustomerEmail, &o.CustomerPhone, &city, &district, &o.ShippingAddress, &paid, &o.CreatedAt); err != nil {
			return nil, err
		}
		o.StatusLabel = accountStatusLabel(o.Status)
		o.ShippingCity = ptrString(city)
		o.ShippingDistrict = ptrString(district)
		if paid.Valid {
			o.PaidAt = &paid.Time
		}
		o.Items, _ = app.accountOrderItems(ctx, o.ID)
		o.Shipment = app.accountShipment(ctx, o.ID)
		o.StatusHistory = app.accountOrderStatusEvents(ctx, o.ID, o.Status, o.CreatedAt)
		orders = append(orders, o)
	}
	return orders, rows.Err()
}

func (app *App) accountOrderByID(ctx context.Context, userID, orderID int64) (AccountOrderResponse, error) {
	rows, err := app.accountOrders(ctx, userID, 100)
	if err != nil {
		return AccountOrderResponse{}, err
	}
	for _, order := range rows {
		if order.ID == orderID {
			return order, nil
		}
	}
	return AccountOrderResponse{}, ErrNotFound
}

func (app *App) accountOrderItems(ctx context.Context, orderID int64) ([]AccountOrderItem, error) {
	rows, err := app.db.QueryContext(ctx, `SELECT oi.id,oi.product_id,p.slug,COALESCE(NULLIF(p.cdn_image_url,''),p.image_url),oi.name,oi.quantity,oi.unit_price_cents,oi.line_total_cents
		FROM order_items oi LEFT JOIN products p ON p.id=oi.product_id WHERE oi.order_id=? ORDER BY oi.id ASC`, orderID)
	if err != nil {
		return []AccountOrderItem{}, nil
	}
	defer rows.Close()
	items := []AccountOrderItem{}
	for rows.Next() {
		var item AccountOrderItem
		var productID sql.NullInt64
		var slug, image sql.NullString
		if err := rows.Scan(&item.ID, &productID, &slug, &image, &item.Name, &item.Quantity, &item.UnitPriceCents, &item.LineTotalCents); err != nil {
			return items, err
		}
		item.ProductID = ptrInt64(productID)
		item.Slug = ptrString(slug)
		item.ImageURL = app.publicImageURL(ptrString(image))
		items = append(items, item)
	}
	return items, rows.Err()
}

func (app *App) accountOrderStatusEvents(ctx context.Context, orderID int64, currentStatus string, createdAt time.Time) []AccountOrderStatusEvent {
	events := []AccountOrderStatusEvent{{
		ID:        strconvFormatInt(orderID) + "-created",
		Status:    "pending",
		Timestamp: createdAt,
		Source:    "checkout",
	}}

	rows, err := app.db.QueryContext(ctx, `SELECT id,to_status,created_at,note,source FROM order_status_events WHERE tenant_id=? AND order_id=? ORDER BY created_at ASC, id ASC`, app.cfg.TenantID, orderID)
	if err == nil {
		defer rows.Close()
		for rows.Next() {
			var id int64
			var status, source string
			var note sql.NullString
			var at time.Time
			if err := rows.Scan(&id, &status, &at, &note, &source); err != nil {
				continue
			}
			events = append(events, AccountOrderStatusEvent{
				ID:        strconvFormatInt(id),
				Status:    status,
				Timestamp: at,
				Note:      ptrString(note),
				Source:    source,
			})
		}
	}

	if strings.TrimSpace(currentStatus) != "" {
		seenCurrent := false
		for _, event := range events {
			if strings.EqualFold(event.Status, currentStatus) {
				seenCurrent = true
				break
			}
		}
		if !seenCurrent {
			events = append(events, AccountOrderStatusEvent{
				ID:        strconvFormatInt(orderID) + "-current",
				Status:    currentStatus,
				Timestamp: time.Now().UTC(),
				Source:    "orders",
			})
		}
	}

	return events
}

func (app *App) accountShipment(ctx context.Context, orderID int64) *AccountShipmentResponse {
	var shipment AccountShipmentResponse
	var trackingNumber, trackingURL sql.NullString
	var shippedAt, deliveredAt sql.NullTime
	err := app.db.QueryRowContext(ctx, `SELECT id,carrier,tracking_number,status,tracking_url,shipped_at,delivered_at,updated_at
		FROM shipments WHERE tenant_id=? AND order_id=? ORDER BY id DESC LIMIT 1`, app.cfg.TenantID, orderID).
		Scan(&shipment.ID, &shipment.Carrier, &trackingNumber, &shipment.Status, &trackingURL, &shippedAt, &deliveredAt, &shipment.UpdatedAt)
	if err != nil {
		return nil
	}
	shipment.TrackingNumber = ptrString(trackingNumber)
	shipment.TrackingURL = ptrString(trackingURL)
	if shippedAt.Valid {
		shipment.ShippedAt = &shippedAt.Time
	}
	if deliveredAt.Valid {
		shipment.DeliveredAt = &deliveredAt.Time
	}
	return &shipment
}

func (app *App) countForUser(ctx context.Context, table string, userID int64, suffix string) int64 {
	allowed := map[string]bool{"orders": true, "addresses": true, "favorites": true, "notifications": true}
	if !allowed[table] {
		return 0
	}
	var count int64
	query := "SELECT COUNT(*) FROM " + table + " WHERE user_id=? " + suffix
	if table == "notifications" {
		query = "SELECT COUNT(*) FROM notifications WHERE tenant_id=? AND user_id=? " + suffix
		_ = app.db.QueryRowContext(ctx, query, app.cfg.TenantID, userID).Scan(&count)
		return count
	}
	_ = app.db.QueryRowContext(ctx, query, userID).Scan(&count)
	return count
}

func (app *App) notificationByID(ctx context.Context, userID, id int64) map[string]any {
	var typ, title, body string
	var data sql.NullString
	var readAt sql.NullTime
	var created time.Time
	err := app.db.QueryRowContext(ctx, `SELECT type,title,body,data,read_at,created_at FROM notifications WHERE tenant_id=? AND user_id=? AND id=? LIMIT 1`, app.cfg.TenantID, userID, id).Scan(&typ, &title, &body, &data, &readAt, &created)
	if err != nil {
		return map[string]any{"id": strconvFormatInt(id), "read_at": time.Now().UTC().Format(time.RFC3339)}
	}
	payload := parseJSONMap(data)
	return map[string]any{"id": strconvFormatInt(id), "type": typ, "title": title, "body": body, "payload": payload, "action_url": payloadString(payload, "action_url"), "image_url": payloadString(payload, "image_url"), "cta_title": payloadString(payload, "cta_title"), "read_at": nullTimeString(readAt), "created_at": created.Format(time.RFC3339)}
}

func accountStatusLabel(status string) string {
	switch status {
	case "awaiting_payment":
		return "Ödeme bekleniyor"
	case "paid":
		return "Ödeme alındı"
	case "reviewing":
		return "Kontrol ediliyor"
	case "approved":
		return "Onaylandı"
	case "preparing":
		return "Hazırlanıyor"
	case "shipping", "in_delivery", "shipped":
		return "Yolda"
	case "completed", "delivered":
		return "Teslim edildi"
	case "failed":
		return "Ödeme başarısız"
	case "cancelled":
		return "İptal edildi"
	case "refunded":
		return "İade edildi"
	default:
		return "Sipariş alındı"
	}
}

func payloadString(payload map[string]any, key string) *string {
	if payload == nil {
		return nil
	}
	if value, ok := payload[key].(string); ok && strings.TrimSpace(value) != "" {
		return &value
	}
	return nil
}

func nullTimeString(value sql.NullTime) *string {
	if !value.Valid {
		return nil
	}
	s := value.Time.Format(time.RFC3339)
	return &s
}

func strconvFormatInt(value int64) string {
	return strconv.FormatInt(value, 10)
}

func (app *App) handleAddressDelete(w http.ResponseWriter, r *http.Request) {
	user, ok := app.requireUser(w, r)
	if !ok {
		return
	}
	id, err := parsePathID(r, "id")
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	ctx, cancel := withTimeout(r, 4*time.Second)
	defer cancel()
	res, err := app.db.ExecContext(ctx, `DELETE FROM addresses WHERE id=? AND user_id=?`, id, user.ID)
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	if affected, _ := res.RowsAffected(); affected == 0 {
		app.handleErr(w, r, ErrNotFound)
		return
	}
	app.touchCustomerSync(ctx, CartIdentity{UserID: &user.ID, CustomerUID: user.CustomerUID}, "address.deleted")
	writeData(w, http.StatusOK, map[string]bool{"deleted": true})
}
