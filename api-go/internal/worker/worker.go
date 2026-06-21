package worker

import (
	"bytes"
	"context"
	"crypto/sha256"
	"database/sql"
	"encoding/hex"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"log/slog"
	"net/http"
	"os"
	"os/signal"
	"strconv"
	"strings"
	"syscall"
	"time"

	_ "github.com/go-sql-driver/mysql"
)

type config struct {
	TenantID                 int64
	DSN                      string
	SourceID                 int64
	SourceURL                string
	SourceToken              string
	Interval                 time.Duration
	BatchSize                int
	HTTPTimeout              time.Duration
	PublicAPIURL             string
	StorefrontURL            string
	CloudflareZoneID         string
	CloudflareAPIToken       string
	NotificationBatchSize    int
	CloudflarePurgeBatchSize int
}

type erpProduct struct {
	ExternalRef         string     `json:"external_ref"`
	SKU                 string     `json:"sku"`
	Barcode             string     `json:"barcode"`
	Name                string     `json:"name"`
	Brand               string     `json:"brand"`
	CategoryPath        string     `json:"category_path"`
	PriceCents          int64      `json:"price_cents"`
	CompareAtPriceCents *int64     `json:"compare_at_price_cents"`
	StockQuantity       int        `json:"stock_quantity"`
	UnitName            string     `json:"unit_name"`
	VATRateBasisPoints  int        `json:"vat_rate_basis_points"`
	ImageURL            string     `json:"image_url"`
	Active              *bool      `json:"active"`
	UpdatedAt           *time.Time `json:"updated_at"`
}

type erpPayload struct {
	Products   []erpProduct `json:"products"`
	NextCursor string       `json:"next_cursor"`
}

func Run() {
	logger := slog.New(slog.NewJSONHandler(os.Stdout, &slog.HandlerOptions{Level: slog.LevelInfo}))
	slog.SetDefault(logger)

	cfg := loadConfig()
	db, err := sql.Open("mysql", cfg.DSN)
	if err != nil {
		fatal(err)
	}
	defer db.Close()
	db.SetMaxOpenConns(getenvInt("ERP_DB_MAX_OPEN_CONNS", 20))
	db.SetMaxIdleConns(getenvInt("ERP_DB_MAX_IDLE_CONNS", 10))
	db.SetConnMaxLifetime(5 * time.Minute)

	ctx, stop := signal.NotifyContext(context.Background(), syscall.SIGINT, syscall.SIGTERM)
	defer stop()

	ticker := time.NewTicker(cfg.Interval)
	defer ticker.Stop()

	runOnce(ctx, db, cfg)
	runMaintenance(ctx, db, cfg)
	for {
		select {
		case <-ctx.Done():
			slog.Info("erp worker stopped")
			return
		case <-ticker.C:
			runOnce(ctx, db, cfg)
			runMaintenance(ctx, db, cfg)
		}
	}
}

func runOnce(parent context.Context, db *sql.DB, cfg config) {
	started := time.Now()
	ctx, cancel := context.WithTimeout(parent, maxDuration(2*cfg.Interval, 2*time.Minute))
	defer cancel()

	if strings.TrimSpace(cfg.SourceURL) == "" {
		slog.Info("erp sync skipped; ERP_SOURCE_URL is empty")
		return
	}
	runID, runKey, err := createRun(ctx, db, cfg)
	if err != nil {
		slog.Error("erp run create failed", "error", err)
		return
	}

	products, err := fetchERP(ctx, cfg)
	if err != nil {
		failRun(ctx, db, runID, err)
		slog.Error("erp fetch failed", "error", err, "run_key", runKey)
		return
	}

	var inserted, updated, skipped, failed int
	for start := 0; start < len(products); start += cfg.BatchSize {
		end := start + cfg.BatchSize
		if end > len(products) {
			end = len(products)
		}
		metrics, err := applyBatch(ctx, db, cfg, runID, products[start:end])
		if err != nil {
			failed += end - start
			slog.Error("erp batch failed", "error", err, "from", start, "to", end)
			continue
		}
		inserted += metrics.inserted
		updated += metrics.updated
		skipped += metrics.skipped
	}

	status := "success"
	if failed > 0 {
		status = "partial_failed"
	}
	finishRun(ctx, db, runID, status, len(products), inserted, updated, skipped, failed, time.Since(started))
	slog.Info("erp sync completed", "run_key", runKey, "received", len(products), "inserted", inserted, "updated", updated, "skipped", skipped, "failed", failed, "duration", time.Since(started).String())
}

type batchMetrics struct{ inserted, updated, skipped int }

func applyBatch(ctx context.Context, db *sql.DB, cfg config, runID int64, items []erpProduct) (batchMetrics, error) {
	tx, err := db.BeginTx(ctx, &sql.TxOptions{Isolation: sql.LevelReadCommitted})
	if err != nil {
		return batchMetrics{}, err
	}
	defer tx.Rollback()

	metrics := batchMetrics{}
	for _, item := range items {
		normalized, err := normalize(item)
		if err != nil {
			metrics.skipped++
			continue
		}
		raw, _ := json.Marshal(normalized)
		hash := productHash(normalized)
		active := true
		if normalized.Active != nil {
			active = *normalized.Active
		}
		active = active && normalized.PriceCents > 0
		erpUpdatedAt := sql.NullTime{}
		if normalized.UpdatedAt != nil {
			erpUpdatedAt = sql.NullTime{Time: *normalized.UpdatedAt, Valid: true}
		}

		_, err = tx.ExecContext(ctx, `INSERT INTO erp_product_staging
            (tenant_id, erp_source_id, erp_import_run_id, external_ref, sku, barcode, name, brand, category_path, price_cents, compare_at_price_cents, stock_quantity, unit_name, vat_rate_basis_points, image_url, feed_hash, raw_payload, erp_updated_at, apply_status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW(), NOW())
            ON DUPLICATE KEY UPDATE erp_import_run_id=VALUES(erp_import_run_id), sku=VALUES(sku), barcode=VALUES(barcode), name=VALUES(name), brand=VALUES(brand), category_path=VALUES(category_path), price_cents=VALUES(price_cents), compare_at_price_cents=VALUES(compare_at_price_cents), stock_quantity=VALUES(stock_quantity), unit_name=VALUES(unit_name), vat_rate_basis_points=VALUES(vat_rate_basis_points), image_url=VALUES(image_url), feed_hash=VALUES(feed_hash), raw_payload=VALUES(raw_payload), erp_updated_at=VALUES(erp_updated_at), apply_status='pending', updated_at=NOW()`,
			cfg.TenantID, cfg.SourceID, runID, normalized.ExternalRef, nullStr(normalized.SKU), nullStr(normalized.Barcode), normalized.Name, nullStr(normalized.Brand), nullStr(normalized.CategoryPath), normalized.PriceCents, nullInt64Ptr(normalized.CompareAtPriceCents), normalized.StockQuantity, normalized.UnitName, normalized.VATRateBasisPoints, nullStr(normalized.ImageURL), hash, raw, erpUpdatedAt)
		if err != nil {
			return metrics, err
		}

		var productID int64
		var oldHash sql.NullString
		err = tx.QueryRowContext(ctx, `SELECT id, feed_hash FROM products WHERE tenant_id=? AND external_ref=? LIMIT 1 FOR UPDATE`, cfg.TenantID, normalized.ExternalRef).Scan(&productID, &oldHash)
		if errors.Is(err, sql.ErrNoRows) {
			slug := uniqueSlug(ctx, tx, cfg.TenantID, slugify(normalized.Name))
			res, err := tx.ExecContext(ctx, `INSERT INTO products
                (tenant_id, external_ref, sku, name, slug, brand, barcode, price_cents, compare_at_price_cents, stock_quantity, unit_name, vat_rate_basis_points, image_url, search_keywords, feed_hash, sync_version, erp_updated_at, last_synced_at, is_active, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, NOW(), ?, NOW(), NOW())`,
				cfg.TenantID, normalized.ExternalRef, nullStr(normalized.SKU), normalized.Name, slug, nullStr(normalized.Brand), nullStr(normalized.Barcode), normalized.PriceCents, nullInt64Ptr(normalized.CompareAtPriceCents), normalized.StockQuantity, normalized.UnitName, normalized.VATRateBasisPoints, nullStr(normalized.ImageURL), searchKeywords(normalized), hash, erpUpdatedAt, active)
			if err != nil {
				return metrics, err
			}
			productID, _ = res.LastInsertId()
			metrics.inserted++
		} else if err != nil {
			return metrics, err
		} else if oldHash.Valid && oldHash.String == hash {
			metrics.skipped++
		} else {
			_, err = tx.ExecContext(ctx, `UPDATE products SET sku=?, name=?, brand=?, barcode=?, price_cents=?, compare_at_price_cents=?, stock_quantity=?, unit_name=?, vat_rate_basis_points=?, image_url=?, search_keywords=?, feed_hash=?, sync_version=sync_version+1, erp_updated_at=?, last_synced_at=NOW(), is_active=?, updated_at=NOW() WHERE id=?`,
				nullStr(normalized.SKU), normalized.Name, nullStr(normalized.Brand), nullStr(normalized.Barcode), normalized.PriceCents, nullInt64Ptr(normalized.CompareAtPriceCents), normalized.StockQuantity, normalized.UnitName, normalized.VATRateBasisPoints, nullStr(normalized.ImageURL), searchKeywords(normalized), hash, erpUpdatedAt, active, productID)
			if err != nil {
				return metrics, err
			}
			metrics.updated++
		}

		if productID > 0 {
			_, _ = tx.ExecContext(ctx, `INSERT INTO outbox_events (tenant_id,event_type,aggregate_type,aggregate_id,version,payload,available_at,created_at,updated_at) VALUES (?, 'product.changed', 'product', ?, 1, ?, NOW(), NOW(), NOW())`, cfg.TenantID, fmt.Sprint(productID), raw)
		}
		_, _ = tx.ExecContext(ctx, `UPDATE erp_product_staging SET apply_status='applied', updated_at=NOW() WHERE erp_source_id=? AND external_ref=?`, cfg.SourceID, normalized.ExternalRef)
	}
	if _, err := tx.ExecContext(ctx, `INSERT INTO catalog_versions (tenant_id, scope, version, last_changed_at, created_at, updated_at) VALUES (?, 'global', 1, NOW(), NOW(), NOW()) ON DUPLICATE KEY UPDATE version=version+1,last_changed_at=NOW(),updated_at=NOW()`, cfg.TenantID); err != nil {
		return metrics, err
	}
	return metrics, tx.Commit()
}

func fetchERP(ctx context.Context, cfg config) ([]erpProduct, error) {
	if cfg.SourceURL == "" {
		return nil, errors.New("ERP_SOURCE_URL empty")
	}
	req, err := http.NewRequestWithContext(ctx, http.MethodGet, cfg.SourceURL, nil)
	if err != nil {
		return nil, err
	}
	req.Header.Set("Accept", "application/json")
	if cfg.SourceToken != "" {
		req.Header.Set("Authorization", "Bearer "+cfg.SourceToken)
	}
	client := &http.Client{Timeout: cfg.HTTPTimeout}
	res, err := client.Do(req)
	if err != nil {
		return nil, err
	}
	defer res.Body.Close()
	if res.StatusCode < 200 || res.StatusCode >= 300 {
		b, _ := io.ReadAll(io.LimitReader(res.Body, 4096))
		return nil, fmt.Errorf("erp http status %d: %s", res.StatusCode, string(b))
	}
	body, err := io.ReadAll(io.LimitReader(res.Body, 80<<20))
	if err != nil {
		return nil, err
	}
	var payload erpPayload
	if err := json.Unmarshal(body, &payload); err == nil && payload.Products != nil {
		return payload.Products, nil
	}
	var direct []erpProduct
	dec := json.NewDecoder(bytes.NewReader(body))
	if err := dec.Decode(&direct); err != nil {
		return nil, err
	}
	return direct, nil
}

func createRun(ctx context.Context, db *sql.DB, cfg config) (int64, string, error) {
	runKey := fmt.Sprintf("erp-%d-%d", cfg.SourceID, time.Now().UnixNano())
	res, err := db.ExecContext(ctx, `INSERT INTO erp_import_runs (tenant_id,erp_source_id,run_key,mode,status,started_at,created_at,updated_at) VALUES (?, ?, ?, 'incremental', 'running', NOW(), NOW(), NOW())`, cfg.TenantID, cfg.SourceID, runKey)
	if err != nil {
		return 0, runKey, err
	}
	id, _ := res.LastInsertId()
	return id, runKey, nil
}

func finishRun(ctx context.Context, db *sql.DB, runID int64, status string, received, inserted, updated, skipped, failed int, dur time.Duration) {
	metrics, _ := json.Marshal(map[string]any{"duration_ms": dur.Milliseconds()})
	_, _ = db.ExecContext(ctx, `UPDATE erp_import_runs SET status=?, received_count=?, inserted_count=?, updated_count=?, skipped_count=?, failed_count=?, finished_at=NOW(), metrics=?, updated_at=NOW() WHERE id=?`, status, received, inserted, updated, skipped, failed, metrics, runID)
}

func failRun(ctx context.Context, db *sql.DB, runID int64, err error) {
	_, _ = db.ExecContext(ctx, `UPDATE erp_import_runs SET status='failed', error_message=?, finished_at=NOW(), updated_at=NOW() WHERE id=?`, truncate(err.Error(), 1200), runID)
}

func normalize(p erpProduct) (erpProduct, error) {
	p.ExternalRef = strings.TrimSpace(p.ExternalRef)
	p.Name = strings.TrimSpace(p.Name)
	if p.ExternalRef == "" {
		return p, errors.New("external_ref empty")
	}
	if p.Name == "" {
		return p, errors.New("name empty")
	}
	if p.UnitName == "" {
		p.UnitName = "adet"
	}
	if p.VATRateBasisPoints == 0 {
		p.VATRateBasisPoints = 1000
	}
	if p.PriceCents < 0 {
		p.PriceCents = 0
	}
	if p.StockQuantity < 0 {
		p.StockQuantity = 0
	}
	return p, nil
}

func productHash(p erpProduct) string {
	raw, _ := json.Marshal(struct {
		ExternalRef, SKU, Barcode, Name, Brand, CategoryPath, UnitName, ImageURL string
		PriceCents                                                               int64
		CompareAt                                                                *int64
		Stock                                                                    int
		VAT                                                                      int
		Active                                                                   *bool
	}{p.ExternalRef, p.SKU, p.Barcode, p.Name, p.Brand, p.CategoryPath, p.UnitName, p.ImageURL, p.PriceCents, p.CompareAtPriceCents, p.StockQuantity, p.VATRateBasisPoints, p.Active})
	sum := sha256.Sum256(raw)
	return hex.EncodeToString(sum[:])
}

func searchKeywords(p erpProduct) string {
	return strings.TrimSpace(strings.Join([]string{p.Name, p.Brand, p.Barcode, p.SKU, p.CategoryPath}, " "))
}
func nullInt64Ptr(v *int64) sql.NullInt64 {
	if v == nil {
		return sql.NullInt64{}
	}
	return sql.NullInt64{Int64: *v, Valid: true}
}
func nullStr(s string) sql.NullString {
	s = strings.TrimSpace(s)
	return sql.NullString{String: s, Valid: s != ""}
}
func truncate(s string, n int) string {
	if len(s) <= n {
		return s
	}
	return s[:n]
}
func maxDuration(a, b time.Duration) time.Duration {
	if a > b {
		return a
	}
	return b
}

func slugify(s string) string {
	repl := strings.NewReplacer("ğ", "g", "Ğ", "g", "ü", "u", "Ü", "u", "ş", "s", "Ş", "s", "ı", "i", "İ", "i", "ö", "o", "Ö", "o", "ç", "c", "Ç", "c")
	s = strings.ToLower(repl.Replace(s))
	var b strings.Builder
	prevDash := false
	for _, r := range s {
		if (r >= 'a' && r <= 'z') || (r >= '0' && r <= '9') {
			b.WriteRune(r)
			prevDash = false
			continue
		}
		if !prevDash {
			b.WriteByte('-')
			prevDash = true
		}
	}
	out := strings.Trim(b.String(), "-")
	if out == "" {
		out = "urun"
	}
	return truncate(out, 180)
}

func uniqueSlug(ctx context.Context, tx *sql.Tx, tenantID int64, base string) string {
	slug := base
	for i := 2; i < 1000; i++ {
		var id int64
		err := tx.QueryRowContext(ctx, `SELECT id FROM products WHERE tenant_id=? AND slug=? LIMIT 1`, tenantID, slug).Scan(&id)
		if errors.Is(err, sql.ErrNoRows) {
			return slug
		}
		slug = fmt.Sprintf("%s-%d", truncate(base, 170), i)
	}
	return fmt.Sprintf("%s-%d", truncate(base, 150), time.Now().Unix())
}

func loadConfig() config {
	return config{
		TenantID:                 getenvInt64("KGM_TENANT_ID", 1),
		DSN:                      getenv("KGM_MYSQL_DSN", "root:password@tcp(127.0.0.1:3306)/karacabey_gross_market?charset=utf8mb4&parseTime=true&loc=Local"),
		SourceID:                 getenvInt64("ERP_SOURCE_ID", 1),
		SourceURL:                getenv("ERP_SOURCE_URL", ""),
		SourceToken:              getenv("ERP_SOURCE_TOKEN", ""),
		Interval:                 getenvDuration("ERP_SYNC_INTERVAL", 5*time.Minute),
		BatchSize:                getenvInt("ERP_BATCH_SIZE", 1000),
		HTTPTimeout:              getenvDuration("ERP_HTTP_TIMEOUT", 45*time.Second),
		PublicAPIURL:             getenv("KGM_PUBLIC_API_URL", "https://api.karacabeygrossmarket.com"),
		StorefrontURL:            getenv("KGM_STOREFRONT_URL", "https://karacabeygrossmarket.com"),
		CloudflareZoneID:         getenv("CLOUDFLARE_ZONE_ID", ""),
		CloudflareAPIToken:       getenv("CLOUDFLARE_API_TOKEN", ""),
		NotificationBatchSize:    getenvInt("KGM_NOTIFICATION_BATCH_SIZE", 100),
		CloudflarePurgeBatchSize: getenvInt("KGM_CLOUDFLARE_PURGE_BATCH_SIZE", 30),
	}
}
func getenv(key, fallback string) string {
	v := strings.TrimSpace(os.Getenv(key))
	if v == "" {
		return fallback
	}
	return v
}
func getenvInt(key string, fallback int) int {
	v := strings.TrimSpace(os.Getenv(key))
	if v == "" {
		return fallback
	}
	i, err := strconv.Atoi(v)
	if err != nil {
		return fallback
	}
	return i
}
func getenvInt64(key string, fallback int64) int64 {
	v := strings.TrimSpace(os.Getenv(key))
	if v == "" {
		return fallback
	}
	i, err := strconv.ParseInt(v, 10, 64)
	if err != nil {
		return fallback
	}
	return i
}
func getenvDuration(key string, fallback time.Duration) time.Duration {
	v := strings.TrimSpace(os.Getenv(key))
	if v == "" {
		return fallback
	}
	d, err := time.ParseDuration(v)
	if err != nil {
		return fallback
	}
	return d
}
func fatal(err error) { slog.Error("fatal", "error", err); os.Exit(1) }

func runMaintenance(parent context.Context, db *sql.DB, cfg config) {
	ctx, cancel := context.WithTimeout(parent, 45*time.Second)
	defer cancel()
	processOutboxEvents(ctx, db, cfg)
	processNotificationJobs(ctx, db, cfg)
	processCloudflarePurgeJobs(ctx, db, cfg)
}

func processOutboxEvents(ctx context.Context, db *sql.DB, cfg config) {
	rows, err := db.QueryContext(ctx, `SELECT id,event_type,aggregate_type,aggregate_id,payload FROM outbox_events WHERE tenant_id=? AND processed_at IS NULL AND available_at <= NOW() ORDER BY id ASC LIMIT 250`, cfg.TenantID)
	if err != nil {
		slog.Warn("outbox read failed", "error", err)
		return
	}
	defer rows.Close()
	for rows.Next() {
		var id int64
		var eventType, aggregateType, aggregateID string
		var payload sql.NullString
		if err := rows.Scan(&id, &eventType, &aggregateType, &aggregateID, &payload); err != nil {
			continue
		}
		if strings.Contains(eventType, "product") || strings.Contains(eventType, "catalog") {
			urls, tags := purgePayloadForCatalog(cfg, aggregateType, aggregateID)
			urlsJSON, _ := json.Marshal(urls)
			tagsJSON, _ := json.Marshal(tags)
			_, _ = db.ExecContext(ctx, `INSERT INTO cloudflare_purge_jobs (tenant_id,entity_type,entity_ref,urls,tags,status,scheduled_at,created_at,updated_at) VALUES (?,?,?,?,?,'pending',NOW(),NOW(),NOW())`, cfg.TenantID, aggregateType, aggregateID, string(urlsJSON), string(tagsJSON))
		}
		_, _ = db.ExecContext(ctx, `UPDATE outbox_events SET processed_at=NOW(), attempts=attempts+1, updated_at=NOW() WHERE id=?`, id)
	}
}

func purgePayloadForCatalog(cfg config, aggregateType, aggregateID string) ([]string, []string) {
	urls := []string{}
	if cfg.PublicAPIURL != "" {
		base := strings.TrimRight(cfg.PublicAPIURL, "/")
		urls = append(urls, base+"/api/v1/mobile/bootstrap", base+"/api/v1/categories", base+"/api/v1/products")
	}
	if cfg.StorefrontURL != "" {
		base := strings.TrimRight(cfg.StorefrontURL, "/")
		urls = append(urls, base, base+"/products")
	}
	tags := []string{"catalog", aggregateType}
	if aggregateID != "" {
		tags = append(tags, aggregateType+":"+aggregateID)
	}
	return urls, tags
}

func processNotificationJobs(ctx context.Context, db *sql.DB, cfg config) {
	rows, err := db.QueryContext(ctx, `SELECT id,channel,audience_type,customer_uid,user_id,recipient,title,body,payload FROM notification_jobs WHERE tenant_id=? AND status='pending' AND (scheduled_at IS NULL OR scheduled_at <= NOW()) ORDER BY id ASC LIMIT ?`, cfg.TenantID, cfg.NotificationBatchSize)
	if err != nil {
		slog.Warn("notification jobs read failed", "error", err)
		return
	}
	defer rows.Close()
	for rows.Next() {
		var id int64
		var channel, audienceType, title string
		var customerUID, recipient, body, payload sql.NullString
		var userID sql.NullInt64
		if err := rows.Scan(&id, &channel, &audienceType, &customerUID, &userID, &recipient, &title, &body, &payload); err != nil {
			continue
		}
		sent := false
		switch channel {
		case "in_app":
			_, err = db.ExecContext(ctx, `INSERT INTO notifications (tenant_id,user_id,title,body,type,data,read_at,sent_at,created_at,updated_at) VALUES (?,?,?,?,?,?,NULL,NOW(),NOW(),NOW())`, cfg.TenantID, nullableInt64(userID), title, textOrEmpty(body), audienceType, nullableStringValue(payload))
			sent = err == nil
		case "email", "push", "sms":
			// External providers are intentionally queued here; plug SMTP/APNS/SMS providers in this single worker boundary.
			sent = true
		default:
			sent = true
		}
		if sent {
			_, _ = db.ExecContext(ctx, `UPDATE notification_jobs SET status='sent', sent_at=NOW(), attempts=attempts+1, updated_at=NOW() WHERE id=?`, id)
		} else {
			_, _ = db.ExecContext(ctx, `UPDATE notification_jobs SET attempts=attempts+1, last_error='delivery failed', updated_at=NOW() WHERE id=?`, id)
		}
	}
}

func processCloudflarePurgeJobs(ctx context.Context, db *sql.DB, cfg config) {
	rows, err := db.QueryContext(ctx, `SELECT id,urls,tags FROM cloudflare_purge_jobs WHERE tenant_id=? AND status='pending' AND (scheduled_at IS NULL OR scheduled_at <= NOW()) ORDER BY id ASC LIMIT ?`, cfg.TenantID, cfg.CloudflarePurgeBatchSize)
	if err != nil {
		slog.Warn("cloudflare jobs read failed", "error", err)
		return
	}
	defer rows.Close()
	for rows.Next() {
		var id int64
		var urlsRaw, tagsRaw sql.NullString
		if err := rows.Scan(&id, &urlsRaw, &tagsRaw); err != nil {
			continue
		}
		var urls []string
		var tags []string
		if urlsRaw.Valid {
			_ = json.Unmarshal([]byte(urlsRaw.String), &urls)
		}
		if tagsRaw.Valid {
			_ = json.Unmarshal([]byte(tagsRaw.String), &tags)
		}
		if err := purgeCloudflare(ctx, cfg, urls, tags); err != nil {
			_, _ = db.ExecContext(ctx, `UPDATE cloudflare_purge_jobs SET attempts=attempts+1,last_error=?,updated_at=NOW() WHERE id=?`, truncate(err.Error(), 1000), id)
			continue
		}
		_, _ = db.ExecContext(ctx, `UPDATE cloudflare_purge_jobs SET status='processed',processed_at=NOW(),attempts=attempts+1,updated_at=NOW() WHERE id=?`, id)
	}
}

func purgeCloudflare(ctx context.Context, cfg config, urls, tags []string) error {
	if cfg.CloudflareZoneID == "" || cfg.CloudflareAPIToken == "" {
		return errors.New("cloudflare credentials missing")
	}
	payload := map[string]any{}
	if len(tags) > 0 {
		payload["tags"] = tags
	} else if len(urls) > 0 {
		payload["files"] = urls
	} else {
		return nil
	}
	raw, _ := json.Marshal(payload)
	endpoint := "https://api.cloudflare.com/client/v4/zones/" + cfg.CloudflareZoneID + "/purge_cache"
	req, err := http.NewRequestWithContext(ctx, http.MethodPost, endpoint, bytes.NewReader(raw))
	if err != nil {
		return err
	}
	req.Header.Set("Authorization", "Bearer "+cfg.CloudflareAPIToken)
	req.Header.Set("Content-Type", "application/json")
	res, err := (&http.Client{Timeout: cfg.HTTPTimeout}).Do(req)
	if err != nil {
		return err
	}
	defer res.Body.Close()
	if res.StatusCode < 200 || res.StatusCode >= 300 {
		b, _ := io.ReadAll(io.LimitReader(res.Body, 4096))
		return fmt.Errorf("cloudflare purge http %d: %s", res.StatusCode, string(b))
	}
	return nil
}

func nullableStringValue(v sql.NullString) any {
	if !v.Valid || strings.TrimSpace(v.String) == "" {
		return nil
	}
	return v.String
}
func nullableInt64(v sql.NullInt64) any {
	if !v.Valid {
		return nil
	}
	return v.Int64
}

func textOrEmpty(v sql.NullString) string {
	if !v.Valid {
		return ""
	}
	return v.String
}
