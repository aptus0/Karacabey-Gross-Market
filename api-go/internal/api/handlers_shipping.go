package api

import (
	"context"
	"encoding/json"
	"fmt"
	"math"
	"net/http"
	"strconv"
	"strings"
	"time"
)

type ShippingQuoteRequest struct {
	Carrier       string  `json:"carrier"`
	City          string  `json:"city"`
	District      string  `json:"district"`
	SubtotalCents int64   `json:"subtotal_cents"`
	WeightKg      float64 `json:"weight_kg"`
}

type ShippingQuoteResponse struct {
	Carrier               string `json:"carrier"`
	LocalDelivery         bool   `json:"local_delivery"`
	MinimumOrderCents     int64  `json:"minimum_order_cents"`
	FreeShippingCents     int64  `json:"free_shipping_cents"`
	StandardShippingCents int64  `json:"standard_shipping_cents"`
	MinimumReached        bool   `json:"minimum_reached"`
	FreeShippingReached   bool   `json:"free_shipping_reached"`
	ShippingCents         int64  `json:"shipping_cents"`
	TotalCents            int64  `json:"total_cents"`
	Message               string `json:"message"`
}

type CargoOptionResponse struct {
	Code               string         `json:"code"`
	Name               string         `json:"name"`
	LogoURL            string         `json:"logo_url"`
	PriceCents         int64          `json:"price_cents"`
	OriginalPriceCents int64          `json:"original_price_cents"`
	IsFree             bool           `json:"is_free"`
	FreeThresholdCents int64          `json:"free_threshold_cents"`
	EstimatedDays      map[string]int `json:"estimated_days"`
}

func (app *App) handleShippingQuote(w http.ResponseWriter, r *http.Request) {
	var req ShippingQuoteRequest
	if r.Method == http.MethodPost {
		if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
			writeError(w, r, http.StatusUnprocessableEntity, "Geçerli kargo hesaplama verisi gönderin.")
			return
		}
	} else {
		q := r.URL.Query()
		req.Carrier = q.Get("carrier")
		req.City = q.Get("city")
		req.District = q.Get("district")
		req.SubtotalCents = parseInt64(q.Get("subtotal_cents"), 0)
		req.WeightKg = parseFloat64(q.Get("weight_kg"), 1)
	}
	carrier := normalizeCarrier(req.Carrier)
	local := isKaracabeyDelivery(req.City, req.District)
	minimumReached := app.cfg.MinOrderCents <= 0 || req.SubtotalCents >= app.cfg.MinOrderCents
	freeReached := true
	shippingCents := int64(0)
	message := "Bursa / Karacabey teslimatında kapıda ödeme ve yemek kartı geçerlidir."
	if !local {
		freeReached = req.SubtotalCents >= app.cfg.FreeShippingCents
		if !freeReached {
			shippingCents = app.cfg.StandardShippingCents + int64(math.Ceil(math.Max(req.WeightKg, 1)))*carrierPerKgCents(carrier)
		}
		if freeReached {
			message = "1500 TL ve üzeri standart kargo ücretsiz."
		} else {
			message = "Standart kargo tutarı adrese, desiye ve firmaya göre hesaplanır."
		}
	}
	if !minimumReached {
		message = fmt.Sprintf("Servis için minimum sepet tutarı %.2f TL.", float64(app.cfg.MinOrderCents)/100)
	}
	writeData(w, http.StatusOK, ShippingQuoteResponse{
		Carrier:               carrier,
		LocalDelivery:         local,
		MinimumOrderCents:     app.cfg.MinOrderCents,
		FreeShippingCents:     app.cfg.FreeShippingCents,
		StandardShippingCents: app.cfg.StandardShippingCents,
		MinimumReached:        minimumReached,
		FreeShippingReached:   freeReached,
		ShippingCents:         shippingCents,
		TotalCents:            req.SubtotalCents + shippingCents,
		Message:               message,
	})
}

func (app *App) handleCargoOptions(w http.ResponseWriter, r *http.Request) {
	orderCents := parseInt64(r.URL.Query().Get("order_cents"), 0)

	ctx, cancel := context.WithTimeout(r.Context(), 2*time.Second)
	defer cancel()

	rows, err := app.db.QueryContext(ctx, `
		SELECT code,name,price_cents,free_threshold_cents,estimated_days_min,estimated_days_max
		FROM cargo_provider_settings
		WHERE tenant_id=? AND is_active=1
		ORDER BY price_cents ASC,name ASC`, app.cfg.TenantID)
	if err != nil {
		writeError(w, r, http.StatusServiceUnavailable, "Kargo seçenekleri şu anda alınamıyor.")
		return
	}
	defer rows.Close()

	options := make([]CargoOptionResponse, 0, 4)
	for rows.Next() {
		var rawCode, name string
		var priceCents, freeThreshold int64
		var minDay, maxDay int
		if err := rows.Scan(&rawCode, &name, &priceCents, &freeThreshold, &minDay, &maxDay); err != nil {
			writeError(w, r, http.StatusServiceUnavailable, "Kargo seçenekleri şu anda alınamıyor.")
			return
		}
		code := normalizeCarrier(rawCode)
		if code == "" {
			continue
		}
		isFree := freeThreshold > 0 && orderCents >= freeThreshold
		effectivePrice := priceCents
		if isFree {
			effectivePrice = 0
		}
		options = append(options, CargoOptionResponse{
			Code:               code,
			Name:               name,
			LogoURL:            cargoLogoURL(code),
			PriceCents:         effectivePrice,
			OriginalPriceCents: priceCents,
			IsFree:             isFree,
			FreeThresholdCents: freeThreshold,
			EstimatedDays:      map[string]int{"min": maxInt(minDay, 1), "max": maxInt(maxDay, maxInt(minDay, 1))},
		})
	}

	if err := rows.Err(); err != nil {
		writeError(w, r, http.StatusServiceUnavailable, "Kargo seçenekleri şu anda alınamıyor.")
		return
	}

	writeData(w, http.StatusOK, options)
}

func normalizeCarrier(value string) string {
	value = strings.ToLower(strings.TrimSpace(value))
	switch value {
	case "aras", "yurtici", "ptt", "mng", "dhlcommerce":
		return value
	case "yurtiçi", "yurtici kargo", "yurtıçı", "yurtıcı":
		return "yurtici"
	case "mng kargo":
		return "mng"
	default:
		return "yurtici"
	}
}

func carrierPerKgCents(carrier string) int64 {
	switch carrier {
	case "aras":
		return 1250
	case "ptt":
		return 1050
	case "dhlcommerce":
		return 1650
	case "mng":
		return 1450
	default:
		return 1350
	}
}

func cargoLogoURL(code string) string {
	switch normalizeCarrier(code) {
	case "aras":
		return "/assets/cargo/aras.svg"
	case "ptt":
		return "/assets/cargo/ptt.svg"
	case "mng":
		return "/assets/cargo/mng.svg"
	default:
		return "/assets/cargo/yurtici.svg"
	}
}

func maxInt(a, b int) int {
	if a > b {
		return a
	}
	return b
}

func parseInt64(value string, fallback int64) int64 {
	parsed, err := strconv.ParseInt(strings.TrimSpace(value), 10, 64)
	if err != nil {
		return fallback
	}
	return parsed
}

func parseFloat64(value string, fallback float64) float64 {
	parsed, err := strconv.ParseFloat(strings.TrimSpace(value), 64)
	if err != nil {
		return fallback
	}
	return parsed
}
