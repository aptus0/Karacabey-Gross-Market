package api

import (
	"context"
	"database/sql"
	"encoding/json"
	"errors"
	"fmt"
	"net/http"
	"strings"
	"time"
)

type MobileDeviceRegisterRequest struct {
	DeviceID    string `json:"device_id"`
	Platform    string `json:"platform"`
	AppVersion  string `json:"app_version"`
	OSVersion   string `json:"os_version"`
	DeviceModel string `json:"device_model"`
	PushToken   string `json:"push_token"`
	Locale      string `json:"locale"`
	Timezone    string `json:"timezone"`
}

type MobileEventRequest struct {
	DeviceID   string         `json:"device_id"`
	SessionID  string         `json:"session_id"`
	EventName  string         `json:"event_name"`
	Screen     string         `json:"screen"`
	AppVersion string         `json:"app_version"`
	Platform   string         `json:"platform"`
	Payload    map[string]any `json:"payload"`
	OccurredAt *time.Time     `json:"occurred_at"`
}

type LiveActivityTokenRequest struct {
	DeviceID string `json:"device_id"`
	FCMToken string `json:"fcm_token"`
	Token    string `json:"token"`
	Kind     string `json:"kind"`
	OrderID  *int64 `json:"order_id"`
	IsActive *bool  `json:"is_active"`
}

type MobileSyncResponse struct {
	ServerVersion   int64          `json:"server_version"`
	ClientVersion   int64          `json:"client_version"`
	HasChanges      bool           `json:"has_changes"`
	ChangedProducts []Product      `json:"changed_products,omitempty"`
	DeletedProducts []string       `json:"deleted_products,omitempty"`
	NextCursor      *int64         `json:"next_cursor,omitempty"`
	ServerAt        time.Time      `json:"server_at"`
	Policies        map[string]any `json:"policies"`
}

func (app *App) handleMobileDeviceRegister(w http.ResponseWriter, r *http.Request) {
	ctx, cancel := withTimeout(r, 5*time.Second)
	defer cancel()

	var req MobileDeviceRegisterRequest
	if err := parseJSON(r, &req); err != nil {
		app.handleErr(w, r, fmt.Errorf("%w: %s", ErrBadRequest, err.Error()))
		return
	}
	req.DeviceID = strings.TrimSpace(req.DeviceID)
	req.Platform = strings.ToLower(strings.TrimSpace(req.Platform))
	if req.DeviceID == "" || req.Platform == "" {
		app.handleErr(w, r, fmt.Errorf("%w: device_id ve platform zorunludur.", ErrBadRequest))
		return
	}
	if req.Platform != "ios" && req.Platform != "android" && req.Platform != "web" {
		app.handleErr(w, r, fmt.Errorf("%w: platform ios, android veya web olmalıdır.", ErrBadRequest))
		return
	}
	if len(req.PushToken) > 600 {
		req.PushToken = req.PushToken[:600]
	}

	identity := requestIdentity(r.Context())
	var userID *int64
	if user, err := app.resolveUser(ctx, requestBearerToken(r)); err == nil && user != nil {
		userID = &user.ID
	}
	_, err := app.db.ExecContext(ctx, `INSERT INTO mobile_devices
		(tenant_id, customer_uid, device_id, user_id, platform, app_version, os_version, device_model, push_token, locale, timezone, last_ip, last_seen_at, created_at, updated_at)
		VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), NOW())
		ON DUPLICATE KEY UPDATE customer_uid=VALUES(customer_uid), platform=VALUES(platform), app_version=VALUES(app_version), os_version=VALUES(os_version), device_model=VALUES(device_model), push_token=VALUES(push_token), locale=VALUES(locale), timezone=VALUES(timezone), last_ip=VALUES(last_ip), last_seen_at=NOW(), updated_at=NOW()`,
		app.cfg.TenantID, nullableString(identity.CustomerUID), req.DeviceID, sqlNullInt64Ptr(userID), req.Platform, nullableString(req.AppVersion), nullableString(req.OSVersion), nullableString(req.DeviceModel), nullableString(req.PushToken), nullableString(req.Locale), nullableString(req.Timezone), clientIP(r))
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	app.recordIdentityEvent(r.Context(), "mobile.device_registered", CartIdentity{UserID: userID, CustomerUID: stringPtr(identity.CustomerUID)}, map[string]any{"device_id": req.DeviceID, "platform": req.Platform})
	writeData(w, http.StatusOK, map[string]any{"registered": true, "device_id": req.DeviceID, "customer_uid": identity.CustomerUID})
}

func (app *App) handleLiveActivityToken(w http.ResponseWriter, r *http.Request) {
	user, ok := app.requireUser(w, r)
	if !ok {
		return
	}

	var req LiveActivityTokenRequest
	if err := parseJSON(r, &req); err != nil {
		app.handleErr(w, r, fmt.Errorf("%w: %s", ErrBadRequest, err.Error()))
		return
	}
	req.DeviceID = strings.TrimSpace(req.DeviceID)
	req.FCMToken = strings.TrimSpace(req.FCMToken)
	req.Token = strings.TrimSpace(req.Token)
	req.Kind = strings.ToLower(strings.TrimSpace(req.Kind))
	if req.DeviceID == "" || req.FCMToken == "" || req.Token == "" {
		app.handleErr(w, r, fmt.Errorf("%w: device_id, fcm_token ve token zorunludur.", ErrBadRequest))
		return
	}
	if req.Kind != "push_to_start" && req.Kind != "activity" {
		app.handleErr(w, r, fmt.Errorf("%w: kind push_to_start veya activity olmalıdır.", ErrBadRequest))
		return
	}
	if len(req.DeviceID) > 160 || len(req.FCMToken) > 700 || len(req.Token) > 512 {
		app.handleErr(w, r, fmt.Errorf("%w: Canlı etkinlik token alanları çok uzun.", ErrBadRequest))
		return
	}

	ctx, cancel := withTimeout(r, 5*time.Second)
	defer cancel()
	if req.OrderID != nil {
		var exists int
		if err := app.db.QueryRowContext(ctx, `SELECT COUNT(*) FROM orders WHERE tenant_id=? AND user_id=? AND id=?`, app.cfg.TenantID, user.ID, *req.OrderID).Scan(&exists); err != nil {
			app.handleErr(w, r, err)
			return
		}
		if exists == 0 {
			app.handleErr(w, r, ErrNotFound)
			return
		}
	}

	active := true
	if req.IsActive != nil {
		active = *req.IsActive
	}
	_, err := app.db.ExecContext(ctx, `INSERT INTO live_activity_tokens
		(tenant_id,user_id,order_id,device_id,fcm_token,token,kind,is_active,created_at,updated_at)
		VALUES (?,?,?,?,?,?,?,?,NOW(),NOW())
		ON DUPLICATE KEY UPDATE user_id=VALUES(user_id),order_id=VALUES(order_id),device_id=VALUES(device_id),fcm_token=VALUES(fcm_token),kind=VALUES(kind),is_active=VALUES(is_active),updated_at=NOW()`,
		app.cfg.TenantID, user.ID, sqlNullInt64Ptr(req.OrderID), req.DeviceID, req.FCMToken, req.Token, req.Kind, active)
	if err != nil {
		app.handleErr(w, r, err)
		return
	}

	writeData(w, http.StatusOK, map[string]any{
		"registered": true,
		"kind":       req.Kind,
		"order_id":   req.OrderID,
		"is_active":  active,
	})
}

func (app *App) handleMobileEvent(w http.ResponseWriter, r *http.Request) {
	ctx, cancel := withTimeout(r, 4*time.Second)
	defer cancel()

	var req MobileEventRequest
	if err := parseJSON(r, &req); err != nil {
		app.handleErr(w, r, fmt.Errorf("%w: %s", ErrBadRequest, err.Error()))
		return
	}
	req.EventName = strings.TrimSpace(req.EventName)
	if req.EventName == "" {
		app.handleErr(w, r, fmt.Errorf("%w: event_name zorunludur.", ErrBadRequest))
		return
	}
	if len(req.EventName) > 120 || len(req.Screen) > 160 || len(req.SessionID) > 120 || len(req.DeviceID) > 160 {
		app.handleErr(w, r, fmt.Errorf("%w: Event alanları çok uzun.", ErrBadRequest))
		return
	}
	occurred := time.Now().UTC()
	if req.OccurredAt != nil {
		occurred = req.OccurredAt.UTC()
	}
	payload, _ := json.Marshal(req.Payload)
	if len(payload) > 16*1024 {
		app.handleErr(w, r, fmt.Errorf("%w: Event payload 16KB sınırını aşıyor.", ErrBadRequest))
		return
	}

	identity := requestIdentity(r.Context())
	var userID *int64
	if user, err := app.resolveUser(ctx, requestBearerToken(r)); err == nil && user != nil {
		userID = &user.ID
	}
	_, err := app.db.ExecContext(ctx, `INSERT INTO mobile_events
		(tenant_id, customer_uid, user_id, device_id, session_id, event_name, screen, platform, app_version, payload, ip_address, user_agent, occurred_at, created_at)
		VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())`,
		app.cfg.TenantID, nullableString(identity.CustomerUID), sqlNullInt64Ptr(userID), nullableString(req.DeviceID), nullableString(req.SessionID), req.EventName, nullableString(req.Screen), nullableString(strings.ToLower(req.Platform)), nullableString(req.AppVersion), string(payload), clientIP(r), r.UserAgent(), occurred)
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	app.recordIdentityEvent(r.Context(), "mobile.event", CartIdentity{UserID: userID, CustomerUID: stringPtr(identity.CustomerUID)}, map[string]any{"event_name": req.EventName, "screen": req.Screen})
	writeData(w, http.StatusAccepted, map[string]bool{"accepted": true})
}

func (app *App) handleMobileSync(w http.ResponseWriter, r *http.Request) {
	ctx, cancel := withTimeout(r, 5*time.Second)
	defer cancel()

	clientVersion := int64(parseIntQuery(r, "since", 0, 0, 0))
	serverVersion, err := app.catalogVersion(ctx)
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	limit := app.cfg.MobileSyncLimit
	if limit <= 0 || limit > 500 {
		limit = 250
	}

	res := MobileSyncResponse{
		ServerVersion: serverVersion,
		ClientVersion: clientVersion,
		HasChanges:    serverVersion > clientVersion,
		ServerAt:      time.Now().UTC(),
		Policies: map[string]any{
			"sync_limit":        limit,
			"cdn_base_url":      app.cfg.CDNURL,
			"cache_max_age_sec": app.cfg.CatalogCacheMaxAge,
		},
	}
	if !res.HasChanges {
		writeData(w, http.StatusOK, res)
		return
	}
	products, nextCursor, err := app.changedProducts(ctx, clientVersion, limit)
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	res.ChangedProducts = products
	res.NextCursor = nextCursor
	writeData(w, http.StatusOK, res)
}

func (app *App) catalogVersion(ctx context.Context) (int64, error) {
	var version int64
	err := app.db.QueryRowContext(ctx, `SELECT version FROM catalog_versions WHERE tenant_id=? AND scope='global' LIMIT 1`, app.cfg.TenantID).Scan(&version)
	if errors.Is(err, sql.ErrNoRows) {
		return 1, nil
	}
	return version, err
}

func (app *App) changedProducts(ctx context.Context, since int64, limit int) ([]Product, *int64, error) {
	rows, err := app.db.QueryContext(ctx, `SELECT p.id,p.name,p.slug,p.description,p.brand,p.barcode,p.price_cents,p.compare_at_price_cents,p.stock_quantity,COALESCE(NULLIF(p.cdn_image_url,''), p.image_url),p.seo,p.sync_version
		FROM products p WHERE p.tenant_id=? AND p.is_active=1 AND p.price_cents>0 AND p.sync_version>? ORDER BY p.sync_version ASC, p.id ASC LIMIT ?`, app.cfg.TenantID, since, limit+1)
	if err != nil {
		return nil, nil, err
	}
	defer rows.Close()
	products := make([]Product, 0, limit)
	var lastVersion int64
	for rows.Next() {
		var p Product
		var desc, brand, barcode, img, seo sql.NullString
		var compare sql.NullInt64
		var syncVersion int64
		if err := rows.Scan(&p.ID, &p.Name, &p.Slug, &desc, &brand, &barcode, &p.PriceCents, &compare, &p.StockQuantity, &img, &seo, &syncVersion); err != nil {
			return nil, nil, err
		}
		lastVersion = syncVersion
		if len(products) < limit {
			p.Description = ptrString(desc)
			p.Brand = ptrString(brand)
			p.Barcode = ptrString(barcode)
			p.CompareAtPriceCents = ptrInt64(compare)
			p.ImageURL = ptrString(img)
			p.SEO = parseJSONMap(seo)
			p.Price = moneyTRY(p.PriceCents)
			products = append(products, p)
		}
	}
	app.applyProductCDN(products)
	var next *int64
	if len(products) == limit && lastVersion > since {
		n := lastVersion
		next = &n
	}
	return products, next, rows.Err()
}
