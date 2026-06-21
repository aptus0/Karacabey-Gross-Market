package api

import (
	"context"
	"crypto/sha1"
	"database/sql"
	"encoding/hex"
	"fmt"
	"net/http"
	"regexp"
	"strings"
	"time"
)

type erkurProductPushRequest struct {
	SourceUID string                 `json:"source_uid"`
	SyncUID   string                 `json:"sync_uid"`
	Products  []erkurProductPushItem `json:"products"`
}

type erkurProductPushItem struct {
	ExternalRef   string  `json:"external_ref"`
	SKU           string  `json:"sku"`
	Barcode       *string `json:"barcode"`
	Name          string  `json:"name"`
	Brand         *string `json:"brand"`
	CategoryPath  *string `json:"category_path"`
	PriceCents    int64   `json:"price_cents"`
	StockQuantity float64 `json:"stock_quantity"`
	UnitName      *string `json:"unit_name"`
	ImageURL      *string `json:"image_url"`
	Active        bool    `json:"active"`
	UpdatedAt     *string `json:"updated_at"`
	RowHash       string  `json:"row_hash"`
}

func (app *App) handleErkurProductPush(w http.ResponseWriter, r *http.Request) {
	token := strings.TrimSpace(r.Header.Get("X-Internal-Token"))
	if token == "" {
		token = requestBearerToken(r)
	}
	if app.cfg.InternalAPIToken == "" || token == "" || token != app.cfg.InternalAPIToken {
		writeError(w, r, http.StatusForbidden, "Ürün aktarımı için geçerli API token gerekli.")
		return
	}

	var input erkurProductPushRequest
	if err := parseJSON(r, &input); err != nil {
		writeError(w, r, http.StatusBadRequest, err.Error())
		return
	}

	input.SourceUID = strings.TrimSpace(input.SourceUID)
	if input.SourceUID == "" {
		input.SourceUID = "erkur-desktop"
	}
	input.SyncUID = strings.TrimSpace(input.SyncUID)
	if input.SyncUID == "" {
		input.SyncUID = "kgm_sync_" + randomHex(8)
	}
	if len(input.Products) == 0 {
		writeError(w, r, http.StatusUnprocessableEntity, "Ürün listesi boş.")
		return
	}
	if len(input.Products) > 2000 {
		writeError(w, r, http.StatusRequestEntityTooLarge, "Tek istekte en fazla 2000 ürün gönderilebilir.")
		return
	}

	ctx := r.Context()
	tx, err := app.db.BeginTx(ctx, nil)
	if err != nil {
		writeError(w, r, http.StatusInternalServerError, "Import transaction başlatılamadı.")
		return
	}
	defer tx.Rollback()

	sourceID, err := app.ensureErkurSource(ctx, tx, input.SourceUID)
	if err != nil {
		writeError(w, r, http.StatusInternalServerError, "ERP kaynak kaydı oluşturulamadı.")
		return
	}

	runID, err := app.createErkurImportRun(ctx, tx, sourceID, input.SyncUID, len(input.Products))
	if err != nil {
		writeError(w, r, http.StatusConflict, "Bu sync UID zaten işlendi veya import run oluşturulamadı.")
		return
	}

	inserted, updated, skipped, failed := 0, 0, 0, 0
	for _, product := range input.Products {
		result, err := app.upsertErkurProduct(ctx, tx, product)
		if err != nil {
			failed++
			continue
		}
		switch result {
		case "inserted":
			inserted++
		case "updated":
			updated++
		default:
			skipped++
		}
	}

	status := "success"
	if failed > 0 && inserted+updated+skipped > 0 {
		status = "partial_failed"
	} else if failed > 0 {
		status = "failed"
	}

	_, _ = tx.ExecContext(ctx, `UPDATE erp_import_runs SET status=?,inserted_count=?,updated_count=?,skipped_count=?,failed_count=?,finished_at=NOW(),updated_at=NOW() WHERE id=?`, status, inserted, updated, skipped, failed, runID)
	_, _ = tx.ExecContext(ctx, `UPDATE erp_sources SET last_success_at=IF(?='success' OR ?='partial_failed', NOW(), last_success_at), last_failure_at=IF(?='failed', NOW(), last_failure_at), last_error=IF(?='failed', 'Ürün aktarımında tüm satırlar hata aldı', NULL), updated_at=NOW() WHERE id=?`, status, status, status, status, sourceID)

	if err := tx.Commit(); err != nil {
		writeError(w, r, http.StatusInternalServerError, "Import transaction tamamlanamadı.")
		return
	}

	app.cache.PurgePrefix("products:")
	app.cache.PurgePrefix("product:")
	app.cache.PurgePrefix("suggest:")
	app.cache.PurgePrefix("categories:")

	writeData(w, http.StatusAccepted, map[string]any{
		"source_uid": input.SourceUID,
		"sync_uid":   input.SyncUID,
		"received":   len(input.Products),
		"inserted":   inserted,
		"updated":    updated,
		"skipped":    skipped,
		"failed":     failed,
		"status":     status,
	})
}

func (app *App) ensureErkurSource(ctx context.Context, tx *sql.Tx, sourceUID string) (int64, error) {
	var id int64
	err := tx.QueryRowContext(ctx, `SELECT id FROM erp_sources WHERE tenant_id=? AND name=? LIMIT 1`, app.cfg.TenantID, sourceUID).Scan(&id)
	if err == nil {
		return id, nil
	}
	if err != sql.ErrNoRows {
		return 0, err
	}

	res, err := tx.ExecContext(ctx, `INSERT INTO erp_sources (tenant_id,name,driver,base_url,token_ref,is_active,batch_size,sync_interval_seconds,created_at,updated_at) VALUES (?,?,?,?,?,1,500,300,NOW(),NOW())`, app.cfg.TenantID, sourceUID, "desktop_push", "erkur-desktop", "KGM_INTERNAL_API_TOKEN")
	if err != nil {
		return 0, err
	}
	return res.LastInsertId()
}

func (app *App) createErkurImportRun(ctx context.Context, tx *sql.Tx, sourceID int64, syncUID string, received int) (int64, error) {
	res, err := tx.ExecContext(ctx, `INSERT INTO erp_import_runs (tenant_id,erp_source_id,run_key,mode,status,received_count,started_at,created_at,updated_at) VALUES (?,?,?,?,?, ?,NOW(),NOW(),NOW())`, app.cfg.TenantID, sourceID, syncUID, "incremental", "running", received)
	if err != nil {
		return 0, err
	}
	return res.LastInsertId()
}

func (app *App) upsertErkurProduct(ctx context.Context, tx *sql.Tx, product erkurProductPushItem) (string, error) {
	product.ExternalRef = strings.TrimSpace(product.ExternalRef)
	product.SKU = strings.TrimSpace(product.SKU)
	product.Name = strings.TrimSpace(product.Name)
	if product.ExternalRef == "" && product.SKU == "" {
		return "failed", fmt.Errorf("external_ref ve sku boş")
	}
	if product.ExternalRef == "" {
		product.ExternalRef = product.SKU
	}
	if product.Name == "" {
		product.Name = product.ExternalRef
	}
	if product.RowHash == "" {
		product.RowHash = shortHash(product.ExternalRef + product.Name + fmt.Sprint(product.PriceCents, product.StockQuantity))
	}

	var existingID int64
	var existingHash sql.NullString
	err := tx.QueryRowContext(ctx, `SELECT id,feed_hash FROM products WHERE tenant_id=? AND external_ref=? LIMIT 1`, app.cfg.TenantID, product.ExternalRef).Scan(&existingID, &existingHash)
	if err != nil && err != sql.ErrNoRows {
		return "failed", err
	}
	if err == nil && existingHash.Valid && existingHash.String == product.RowHash {
		_, _ = tx.ExecContext(ctx, `UPDATE products SET last_synced_at=NOW(), updated_at=NOW() WHERE id=?`, existingID)
		return "skipped", nil
	}

	stock := int(product.StockQuantity)
	if stock < 0 {
		stock = 0
	}
	unit := cleanStringPtr(product.UnitName, "adet")
	slug := productSlug(product.Name, product.ExternalRef)
	erpUpdatedAt := parseERPTime(product.UpdatedAt)
	active := 0
	if product.Active && product.PriceCents > 0 {
		active = 1
	}
	search := strings.Join([]string{product.Name, deref(product.Brand), deref(product.Barcode), product.SKU, product.ExternalRef}, " ")

	if err == sql.ErrNoRows {
		_, err = tx.ExecContext(ctx, `INSERT INTO products (tenant_id,external_ref,sku,barcode,name,slug,brand,price_cents,stock_quantity,unit_name,image_url,search_keywords,feed_hash,sync_version,erp_updated_at,last_synced_at,is_active,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,1,?,NOW(),?,NOW(),NOW())`, app.cfg.TenantID, product.ExternalRef, product.SKU, nullable(product.Barcode), product.Name, slug, nullable(product.Brand), maxInt64(product.PriceCents, 0), stock, unit, nullable(product.ImageURL), search, product.RowHash, erpUpdatedAt, active)
		return "inserted", err
	}

	_, err = tx.ExecContext(ctx, `UPDATE products SET sku=?,barcode=?,name=?,brand=?,price_cents=?,stock_quantity=?,unit_name=?,image_url=?,search_keywords=?,feed_hash=?,sync_version=sync_version+1,erp_updated_at=?,last_synced_at=NOW(),is_active=?,updated_at=NOW() WHERE id=?`, product.SKU, nullable(product.Barcode), product.Name, nullable(product.Brand), maxInt64(product.PriceCents, 0), stock, unit, nullable(product.ImageURL), search, product.RowHash, erpUpdatedAt, active, existingID)
	return "updated", err
}

var slugCleanup = regexp.MustCompile(`[^a-z0-9]+`)

func productSlug(name, externalRef string) string {
	base := strings.ToLower(strings.TrimSpace(name))
	replacer := strings.NewReplacer("ı", "i", "ğ", "g", "ü", "u", "ş", "s", "ö", "o", "ç", "c")
	base = replacer.Replace(base)
	base = slugCleanup.ReplaceAllString(base, "-")
	base = strings.Trim(base, "-")
	if base == "" {
		base = "urun"
	}
	return base + "-" + shortHash(externalRef)[:8]
}

func shortHash(value string) string {
	h := sha1.Sum([]byte(value))
	return hex.EncodeToString(h[:])
}

func cleanStringPtr(value *string, fallback string) string {
	if value == nil || strings.TrimSpace(*value) == "" {
		return fallback
	}
	return strings.TrimSpace(*value)
}

func nullable(value *string) any {
	if value == nil || strings.TrimSpace(*value) == "" {
		return nil
	}
	return strings.TrimSpace(*value)
}

func deref(value *string) string {
	if value == nil {
		return ""
	}
	return *value
}

func parseERPTime(value *string) any {
	if value == nil || strings.TrimSpace(*value) == "" {
		return nil
	}
	if t, err := time.Parse(time.RFC3339, strings.TrimSpace(*value)); err == nil {
		return t.UTC()
	}
	return nil
}

func maxInt64(v, min int64) int64 {
	if v < min {
		return min
	}
	return v
}
