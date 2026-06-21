package api

import (
	"encoding/json"
	"fmt"
	"log/slog"
	"net/http"
	"time"
)

type MobileBootstrap struct {
	App      MobileAppInfo        `json:"app"`
	Config   map[string]any       `json:"config"`
	Catalog  MobileCatalogPayload `json:"catalog"`
	Policies map[string]any       `json:"policies"`
	ServerAt time.Time            `json:"server_at"`
}

type MobileAppInfo struct {
	MinIOSVersion string `json:"min_ios_version"`
	ForceUpdate   bool   `json:"force_update"`
	Maintenance   bool   `json:"maintenance"`
}

type MobileCatalogPayload struct {
	Categories       []Category `json:"categories"`
	FeaturedProducts []Product  `json:"featured_products"`
}

func (app *App) handleMobileBootstrap(w http.ResponseWriter, r *http.Request) {
	ctx, cancel := withTimeout(r, 4*time.Second)
	defer cancel()

	version, err := app.catalogVersion(ctx)
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	etag := fmt.Sprintf("W/\"mobile-bootstrap-%d\"", version)
	w.Header().Set("ETag", etag)
	if r.Header.Get("If-None-Match") == etag {
		w.WriteHeader(http.StatusNotModified)
		return
	}

	cacheKey := fmt.Sprintf("kgm:mobile:bootstrap:%d:%d", app.cfg.TenantID, version)
	if app.redis != nil && app.redis.Enabled() {
		if raw, ok, err := app.redis.Get(ctx, cacheKey); err == nil && ok {
			var cachedPayload MobileBootstrap
			if err := json.Unmarshal(raw, &cachedPayload); err == nil {
				writeData(w, http.StatusOK, cachedPayload)
				return
			}
		} else if err != nil {
			slog.Warn("mobile bootstrap redis get failed", "error", err)
		}
	}

	categories, err := app.listCategories(ctx)
	if err != nil {
		app.handleErr(w, r, err)
		return
	}

	featured, err := app.listProducts(ctx, ProductFilter{Sort: "newest", InStock: true, Page: 1, PerPage: 24})
	if err != nil {
		app.handleErr(w, r, err)
		return
	}

	payload := MobileBootstrap{
		App: MobileAppInfo{
			MinIOSVersion: app.cfg.AppMinIOSVersion,
			ForceUpdate:   false,
			Maintenance:   app.cfg.MaintenanceMode,
		},
		Config: map[string]any{
			"currency":                "TRY",
			"api_base_url":            app.cfg.PublicAPIURL,
			"cdn_base_url":            app.cfg.CDNURL,
			"support_email":           app.cfg.SupportEmail,
			"support_phone":           app.cfg.SupportPhone,
			"catalog_version":         version,
			"minimum_order_cents":     app.cfg.MinOrderCents,
			"free_shipping_cents":     app.cfg.FreeShippingCents,
			"standard_shipping_cents": app.cfg.StandardShippingCents,
			"active_payment_methods":  paymentMethods(),
			"support_conversations":   true,
			"order_cancellation":      true,
		},
		Catalog: MobileCatalogPayload{
			Categories:       categories,
			FeaturedProducts: featured.Data,
		},
		Policies: map[string]any{
			"catalog_version":                version,
			"catalog_cache_seconds":          app.cfg.CatalogCacheMaxAge,
			"cart_requires_server_pricing":   true,
			"payment_card_data_never_stored": true,
			"server_driven_commerce_rules":   true,
		},
		ServerAt: time.Now().UTC(),
	}
	if app.redis != nil && app.redis.Enabled() {
		if raw, err := json.Marshal(payload); err == nil {
			_ = app.redis.SetEX(ctx, cacheKey, time.Duration(app.cfg.CatalogCacheMaxAge+app.cfg.CatalogStaleSeconds)*time.Second, raw)
		}
	}
	writeData(w, http.StatusOK, payload)
}
