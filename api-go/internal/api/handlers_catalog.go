package api

import (
	"log/slog"
	"net/http"
	"strings"
	"time"
)

func (app *App) handleHealth(w http.ResponseWriter, r *http.Request) {
	writeJSON(w, http.StatusOK, map[string]any{"status": "ok", "service": "kgm-go-api"})
}

func (app *App) handleReady(w http.ResponseWriter, r *http.Request) {
	ctx, cancel := withTimeout(r, 2*time.Second)
	defer cancel()
	if err := app.db.PingContext(ctx); err != nil {
		writeError(w, r, http.StatusServiceUnavailable, "Database bağlantısı hazır değil.")
		return
	}
	redisStatus := "disabled"
	if app.redis != nil && app.redis.Enabled() {
		redisStatus = "ok"
		if err := app.redis.Ping(ctx); err != nil {
			redisStatus = "degraded"
		}
	}
	writeJSON(w, http.StatusOK, map[string]any{"status": "ready", "redis": redisStatus})
}

func (app *App) handleProductsIndex(w http.ResponseWriter, r *http.Request) {
	ctx, cancel := withTimeout(r, 5*time.Second)
	defer cancel()
	sort := strings.TrimSpace(r.URL.Query().Get("sort"))
	if sort != "price_asc" && sort != "price_desc" && sort != "newest" {
		sort = "newest"
	}
	filter := ProductFilter{
		Query:    strings.TrimSpace(r.URL.Query().Get("q")),
		Category: strings.TrimSpace(r.URL.Query().Get("category")),
		Sort:     sort,
		InStock:  parseBoolQuery(r, "in_stock"),
		PriceMin: parseCentsQuery(r, "price_min"),
		PriceMax: parseCentsQuery(r, "price_max"),
		Page:     parseIntQuery(r, "page", 1, 1, 100000),
		PerPage:  parseIntQuery(r, "per_page", 12, 1, 96),
	}
	data, err := app.listProducts(ctx, filter)
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	writeJSON(w, http.StatusOK, data)
}

func (app *App) handleProductShow(w http.ResponseWriter, r *http.Request) {
	ctx, cancel := withTimeout(r, 4*time.Second)
	defer cancel()
	product, err := app.productBySlug(ctx, r.PathValue("slug"))
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	writeData(w, http.StatusOK, product)
}

func (app *App) handleProductsSuggest(w http.ResponseWriter, r *http.Request) {
	ctx, cancel := withTimeout(r, 3*time.Second)
	defer cancel()
	products, err := app.suggestProducts(ctx, r.URL.Query().Get("q"))
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	type suggestion struct {
		ID       int64   `json:"id"`
		Name     string  `json:"name"`
		Slug     string  `json:"slug"`
		Brand    *string `json:"brand,omitempty"`
		Price    string  `json:"price"`
		ImageURL *string `json:"image_url,omitempty"`
		Category *string `json:"category,omitempty"`
	}
	out := make([]suggestion, 0, len(products))
	for _, p := range products {
		var cat *string
		if len(p.Categories) > 0 {
			c := p.Categories[0].Name
			cat = &c
		}
		out = append(out, suggestion{ID: p.ID, Name: p.Name, Slug: p.Slug, Brand: p.Brand, Price: p.Price, ImageURL: p.ImageURL, Category: cat})
	}
	writeData(w, http.StatusOK, out)
}

func (app *App) handleCategoriesIndex(w http.ResponseWriter, r *http.Request) {
	ctx, cancel := withTimeout(r, 4*time.Second)
	defer cancel()
	categories, err := app.listCategories(ctx)
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	writeData(w, http.StatusOK, categories)
}

func (app *App) handleCategoryShow(w http.ResponseWriter, r *http.Request) {
	ctx, cancel := withTimeout(r, 4*time.Second)
	defer cancel()
	category, err := app.categoryBySlug(ctx, r.PathValue("slug"))
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	writeData(w, http.StatusOK, category)
}

func (app *App) handleErr(w http.ResponseWriter, r *http.Request, err error) {
	status, message := mapErrorStatus(err)
	if status >= http.StatusInternalServerError {
		requestID, _ := r.Context().Value(contextKeyRequestID).(string)
		slog.Error("api request failed",
			"request_id", requestID,
			"method", r.Method,
			"path", r.URL.Path,
			"status", status,
			"error", err,
		)
	}
	writeError(w, r, status, message)
}
