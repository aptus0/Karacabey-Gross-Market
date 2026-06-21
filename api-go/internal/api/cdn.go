package api

import (
	"net/url"
	"strings"
)

func (app *App) publicImageURL(raw *string) *string {
	if raw == nil || strings.TrimSpace(*raw) == "" {
		return raw
	}
	value := strings.TrimSpace(*raw)
	if app.cfg.CDNURL == "" {
		return &value
	}
	parsed, err := url.Parse(value)
	if err == nil && parsed.IsAbs() {
		// Cloudflare/CDN domaini hazırsa sadece origin görsel hostunu CDN üstünden servis ederiz.
		// External tedarikçi görselleri ERP image worker ile image_assets tablosuna taşınmalı.
		if strings.Contains(parsed.Host, "karacabeygrossmarket.com") {
			out := app.cfg.CDNURL + parsed.EscapedPath()
			return &out
		}
		return &value
	}
	if strings.HasPrefix(value, "/") {
		out := app.cfg.CDNURL + value
		return &out
	}
	out := app.cfg.CDNURL + "/" + value
	return &out
}

func (app *App) applyProductCDN(products []Product) {
	for i := range products {
		if products[i].ImageURL != nil {
			products[i].ImageURL = app.publicImageURL(products[i].ImageURL)
		}
	}
}

func (app *App) applyCategoryCDN(categories []Category) {
	for i := range categories {
		if categories[i].ImageURL != nil {
			categories[i].ImageURL = app.publicImageURL(categories[i].ImageURL)
		}
		if len(categories[i].Children) > 0 {
			app.applyCategoryCDN(categories[i].Children)
		}
	}
}
