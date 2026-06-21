package api

import (
	"context"
	"database/sql"
	"encoding/json"
	"net/http"
	"time"
)

type OpsSummary struct {
	Service     map[string]any `json:"service"`
	Catalog     map[string]any `json:"catalog"`
	ERP         map[string]any `json:"erp"`
	Orders      map[string]any `json:"orders"`
	Payments    map[string]any `json:"payments"`
	Outbox      map[string]any `json:"outbox"`
	Mobile      map[string]any `json:"mobile"`
	Runtime     map[string]any `json:"runtime"`
	GeneratedAt time.Time      `json:"generated_at"`
}

func (app *App) handleRuntimeMetrics(w http.ResponseWriter, r *http.Request) {
	snapshot := app.metrics.Snapshot()
	if app.db != nil {
		stats := app.db.Stats()
		snapshot["db_pool"] = map[string]any{
			"max_open":             stats.MaxOpenConnections,
			"open":                 stats.OpenConnections,
			"in_use":               stats.InUse,
			"idle":                 stats.Idle,
			"wait_count":           stats.WaitCount,
			"wait_duration_ms":     stats.WaitDuration.Milliseconds(),
			"max_idle_closed":      stats.MaxIdleClosed,
			"max_idle_time_closed": stats.MaxIdleTimeClosed,
			"max_lifetime_closed":  stats.MaxLifetimeClosed,
		}
	}
	if app.redis != nil {
		snapshot["redis"] = map[string]any{
			"enabled": app.redis.Enabled(),
		}
	}
	writeData(w, http.StatusOK, snapshot)
}

func (app *App) handleOpsSummary(w http.ResponseWriter, r *http.Request) {
	ctx, cancel := withTimeout(r, 4*time.Second)
	defer cancel()

	catalogVersion, _ := app.catalogVersion(ctx)
	summary := OpsSummary{
		Service: map[string]any{
			"env":                  app.cfg.Env,
			"public_api_url":       app.cfg.PublicAPIURL,
			"storefront_url":       app.cfg.StorefrontURL,
			"cdn_url":              app.cfg.CDNURL,
			"maintenance_mode":     app.cfg.MaintenanceMode,
			"catalog_cache_maxage": app.cfg.CatalogCacheMaxAge,
			"redis_enabled":        app.redis != nil && app.redis.Enabled(),
		},
		Catalog: map[string]any{
			"version":         catalogVersion,
			"active_products": scalarInt64(ctx, app.db, `SELECT COUNT(*) FROM products WHERE tenant_id=? AND is_active=1`, app.cfg.TenantID),
			"out_of_stock":    scalarInt64(ctx, app.db, `SELECT COUNT(*) FROM products WHERE tenant_id=? AND is_active=1 AND stock_quantity=0`, app.cfg.TenantID),
			"categories":      scalarInt64(ctx, app.db, `SELECT COUNT(*) FROM categories WHERE tenant_id=? AND is_active=1`, app.cfg.TenantID),
		},
		ERP: map[string]any{
			"last_run":     app.lastERPRun(ctx),
			"pending_rows": scalarInt64(ctx, app.db, `SELECT COUNT(*) FROM erp_product_staging WHERE tenant_id=? AND apply_status='pending'`, app.cfg.TenantID),
			"failed_rows":  scalarInt64(ctx, app.db, `SELECT COUNT(*) FROM erp_product_staging WHERE tenant_id=? AND apply_status='failed'`, app.cfg.TenantID),
		},
		Orders: map[string]any{
			"today":            scalarInt64(ctx, app.db, `SELECT COUNT(*) FROM orders WHERE tenant_id=? AND created_at >= CURRENT_DATE`, app.cfg.TenantID),
			"awaiting_payment": scalarInt64(ctx, app.db, `SELECT COUNT(*) FROM orders WHERE tenant_id=? AND status='awaiting_payment'`, app.cfg.TenantID),
			"processing":       scalarInt64(ctx, app.db, `SELECT COUNT(*) FROM orders WHERE tenant_id=? AND status IN ('reviewing','paid','processing','preparing')`, app.cfg.TenantID),
		},
		Payments: map[string]any{
			"pending":      scalarInt64(ctx, app.db, `SELECT COUNT(*) FROM payments p JOIN orders o ON o.id=p.order_id WHERE o.tenant_id=? AND p.status='pending'`, app.cfg.TenantID),
			"failed_today": scalarInt64(ctx, app.db, `SELECT COUNT(*) FROM payments p JOIN orders o ON o.id=p.order_id WHERE o.tenant_id=? AND p.status='failed' AND p.updated_at >= CURRENT_DATE`, app.cfg.TenantID),
		},
		Outbox: map[string]any{
			"pending":                    scalarInt64(ctx, app.db, `SELECT COUNT(*) FROM outbox_events WHERE tenant_id=? AND processed_at IS NULL`, app.cfg.TenantID),
			"failed":                     scalarInt64(ctx, app.db, `SELECT COUNT(*) FROM outbox_events WHERE tenant_id=? AND processed_at IS NULL AND attempts >= 5`, app.cfg.TenantID),
			"cloudflare_purge_pending":   scalarInt64(ctx, app.db, `SELECT COUNT(*) FROM cloudflare_purge_jobs WHERE tenant_id=? AND status='pending'`, app.cfg.TenantID),
			"notification_jobs_pending":  scalarInt64(ctx, app.db, `SELECT COUNT(*) FROM notification_jobs WHERE tenant_id=? AND status='pending'`, app.cfg.TenantID),
			"idempotency_processing_now": scalarInt64(ctx, app.db, `SELECT COUNT(*) FROM idempotency_keys WHERE tenant_id=? AND status='processing'`, app.cfg.TenantID),
		},
		Mobile: map[string]any{
			"active_devices_24h": scalarInt64(ctx, app.db, `SELECT COUNT(*) FROM mobile_devices WHERE tenant_id=? AND last_seen_at >= NOW() - INTERVAL 1 DAY`, app.cfg.TenantID),
			"events_24h":         scalarInt64(ctx, app.db, `SELECT COUNT(*) FROM mobile_events WHERE tenant_id=? AND created_at >= NOW() - INTERVAL 1 DAY`, app.cfg.TenantID),
			"crashes_24h":        scalarInt64(ctx, app.db, `SELECT COUNT(*) FROM mobile_events WHERE tenant_id=? AND event_name IN ('crash','crash_reported') AND created_at >= NOW() - INTERVAL 1 DAY`, app.cfg.TenantID),
		},
		Runtime:     app.metrics.Snapshot(),
		GeneratedAt: time.Now().UTC(),
	}
	writeData(w, http.StatusOK, summary)
}

func scalarInt64(ctx context.Context, db *sql.DB, query string, args ...any) int64 {
	var value int64
	_ = db.QueryRowContext(ctx, query, args...).Scan(&value)
	return value
}

func (app *App) lastERPRun(ctx context.Context) map[string]any {
	row := app.db.QueryRowContext(ctx, `SELECT run_key,status,received_count,inserted_count,updated_count,skipped_count,failed_count,started_at,finished_at,error_message FROM erp_import_runs WHERE tenant_id=? ORDER BY id DESC LIMIT 1`, app.cfg.TenantID)
	var runKey, status string
	var received, inserted, updated, skipped, failed int64
	var started, finished sql.NullTime
	var errMsg sql.NullString
	if err := row.Scan(&runKey, &status, &received, &inserted, &updated, &skipped, &failed, &started, &finished, &errMsg); err != nil {
		return nil
	}
	return map[string]any{
		"run_key":        runKey,
		"status":         status,
		"received_count": received,
		"inserted_count": inserted,
		"updated_count":  updated,
		"skipped_count":  skipped,
		"failed_count":   failed,
		"started_at":     nullableTime(started),
		"finished_at":    nullableTime(finished),
		"error_message":  ptrString(errMsg),
	}
}

func nullableTime(value sql.NullTime) any {
	if !value.Valid {
		return nil
	}
	return value.Time.UTC().Format(time.RFC3339)
}

type OpsQueueSummary struct {
	NotificationPending int64 `json:"notification_pending"`
	NotificationFailed  int64 `json:"notification_failed"`
	PurgePending        int64 `json:"cloudflare_purge_pending"`
	PurgeFailed         int64 `json:"cloudflare_purge_failed"`
	IdempotencyOpen     int64 `json:"idempotency_open"`
}

func (app *App) handleOpsPaymentRisk(w http.ResponseWriter, r *http.Request) {
	ctx, cancel := withTimeout(r, 4*time.Second)
	defer cancel()
	writeData(w, http.StatusOK, map[string]any{
		"pending":              scalarInt64(ctx, app.db, `SELECT COUNT(*) FROM payments p JOIN orders o ON o.id=p.order_id WHERE o.tenant_id=? AND p.status='pending'`, app.cfg.TenantID),
		"failed_24h":           scalarInt64(ctx, app.db, `SELECT COUNT(*) FROM payments p JOIN orders o ON o.id=p.order_id WHERE o.tenant_id=? AND p.status='failed' AND p.updated_at >= NOW() - INTERVAL 1 DAY`, app.cfg.TenantID),
		"paid_24h":             scalarInt64(ctx, app.db, `SELECT COUNT(*) FROM payments p JOIN orders o ON o.id=p.order_id WHERE o.tenant_id=? AND p.status='paid' AND p.updated_at >= NOW() - INTERVAL 1 DAY`, app.cfg.TenantID),
		"callback_hash_failed": scalarInt64(ctx, app.db, `SELECT COUNT(*) FROM payment_events WHERE provider='paytr' AND hash_status='failed' AND created_at >= NOW() - INTERVAL 1 DAY`),
		"idempotency_open":     scalarInt64(ctx, app.db, `SELECT COUNT(*) FROM idempotency_keys WHERE tenant_id=? AND status='processing'`, app.cfg.TenantID),
	})
}

func (app *App) handleOpsMobileMonitor(w http.ResponseWriter, r *http.Request) {
	ctx, cancel := withTimeout(r, 4*time.Second)
	defer cancel()
	writeData(w, http.StatusOK, map[string]any{
		"active_devices_24h": scalarInt64(ctx, app.db, `SELECT COUNT(*) FROM mobile_devices WHERE tenant_id=? AND last_seen_at >= NOW() - INTERVAL 1 DAY`, app.cfg.TenantID),
		"events_24h":         scalarInt64(ctx, app.db, `SELECT COUNT(*) FROM mobile_events WHERE tenant_id=? AND created_at >= NOW() - INTERVAL 1 DAY`, app.cfg.TenantID),
		"crashes_24h":        scalarInt64(ctx, app.db, `SELECT COUNT(*) FROM mobile_events WHERE tenant_id=? AND event_name IN ('crash','crash_reported') AND created_at >= NOW() - INTERVAL 1 DAY`, app.cfg.TenantID),
		"checkout_started":   scalarInt64(ctx, app.db, `SELECT COUNT(*) FROM mobile_events WHERE tenant_id=? AND event_name='checkout_started' AND created_at >= NOW() - INTERVAL 1 DAY`, app.cfg.TenantID),
		"payment_failed":     scalarInt64(ctx, app.db, `SELECT COUNT(*) FROM mobile_events WHERE tenant_id=? AND event_name='payment_failed' AND created_at >= NOW() - INTERVAL 1 DAY`, app.cfg.TenantID),
	})
}

func (app *App) handleOpsMobileDevices(w http.ResponseWriter, r *http.Request) {
	ctx, cancel := withTimeout(r, 4*time.Second)
	defer cancel()

	rows, err := app.db.QueryContext(ctx, `SELECT device_id, platform, COALESCE(app_version,''), COALESCE(os_version,''), COALESCE(device_model,''), COALESCE(locale,''), COALESCE(last_ip,''), customer_uid, user_id, last_seen_at, created_at
		FROM mobile_devices WHERE tenant_id=? ORDER BY last_seen_at DESC LIMIT 25`, app.cfg.TenantID)
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	defer rows.Close()

	devices := make([]map[string]any, 0, 25)
	for rows.Next() {
		var deviceID, platform, appVersion, osVersion, model, locale, lastIP string
		var customerUID sql.NullString
		var userID sql.NullInt64
		var lastSeen, createdAt sql.NullTime
		if err := rows.Scan(&deviceID, &platform, &appVersion, &osVersion, &model, &locale, &lastIP, &customerUID, &userID, &lastSeen, &createdAt); err != nil {
			app.handleErr(w, r, err)
			return
		}
		devices = append(devices, map[string]any{
			"device_id":    deviceID,
			"platform":     platform,
			"app_version":  appVersion,
			"os_version":   osVersion,
			"device_model": model,
			"locale":       locale,
			"last_ip":      lastIP,
			"customer_uid": ptrString(customerUID),
			"user_id":      ptrInt64(userID),
			"last_seen_at": nullableTime(lastSeen),
			"created_at":   nullableTime(createdAt),
		})
	}
	totals := map[string]any{
		"total":   scalarInt64(ctx, app.db, `SELECT COUNT(*) FROM mobile_devices WHERE tenant_id=?`, app.cfg.TenantID),
		"ios":     scalarInt64(ctx, app.db, `SELECT COUNT(*) FROM mobile_devices WHERE tenant_id=? AND platform='ios'`, app.cfg.TenantID),
		"android": scalarInt64(ctx, app.db, `SELECT COUNT(*) FROM mobile_devices WHERE tenant_id=? AND platform='android'`, app.cfg.TenantID),
		"web":     scalarInt64(ctx, app.db, `SELECT COUNT(*) FROM mobile_devices WHERE tenant_id=? AND platform='web'`, app.cfg.TenantID),
	}
	writeData(w, http.StatusOK, map[string]any{"devices": devices, "totals": totals})
}

func (app *App) handleOpsMobileEvents(w http.ResponseWriter, r *http.Request) {
	ctx, cancel := withTimeout(r, 4*time.Second)
	defer cancel()

	rows, err := app.db.QueryContext(ctx, `SELECT event_name, COALESCE(screen,''), COALESCE(device_id,''), COALESCE(platform,''), COALESCE(app_version,''), COALESCE(ip_address,''), customer_uid, occurred_at, created_at
		FROM mobile_events WHERE tenant_id=? ORDER BY id DESC LIMIT 30`, app.cfg.TenantID)
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	defer rows.Close()
	events := make([]map[string]any, 0, 30)
	for rows.Next() {
		var eventName, screen, deviceID, platform, appVersion, ip string
		var customerUID sql.NullString
		var occurredAt, createdAt sql.NullTime
		if err := rows.Scan(&eventName, &screen, &deviceID, &platform, &appVersion, &ip, &customerUID, &occurredAt, &createdAt); err != nil {
			app.handleErr(w, r, err)
			return
		}
		events = append(events, map[string]any{
			"event_name":   eventName,
			"screen":       screen,
			"device_id":    deviceID,
			"platform":     platform,
			"app_version":  appVersion,
			"ip_address":   ip,
			"customer_uid": ptrString(customerUID),
			"occurred_at":  nullableTime(occurredAt),
			"created_at":   nullableTime(createdAt),
		})
	}
	writeData(w, http.StatusOK, map[string]any{"events": events})
}

func (app *App) handleOpsQueues(w http.ResponseWriter, r *http.Request) {
	ctx, cancel := withTimeout(r, 4*time.Second)
	defer cancel()
	writeData(w, http.StatusOK, OpsQueueSummary{
		NotificationPending: scalarInt64(ctx, app.db, `SELECT COUNT(*) FROM notification_jobs WHERE tenant_id=? AND status='pending'`, app.cfg.TenantID),
		NotificationFailed:  scalarInt64(ctx, app.db, `SELECT COUNT(*) FROM notification_jobs WHERE tenant_id=? AND status NOT IN ('pending','sent')`, app.cfg.TenantID),
		PurgePending:        scalarInt64(ctx, app.db, `SELECT COUNT(*) FROM cloudflare_purge_jobs WHERE tenant_id=? AND status='pending'`, app.cfg.TenantID),
		PurgeFailed:         scalarInt64(ctx, app.db, `SELECT COUNT(*) FROM cloudflare_purge_jobs WHERE tenant_id=? AND status='pending' AND attempts >= 5`, app.cfg.TenantID),
		IdempotencyOpen:     scalarInt64(ctx, app.db, `SELECT COUNT(*) FROM idempotency_keys WHERE tenant_id=? AND status='processing'`, app.cfg.TenantID),
	})
}

func (app *App) handleOpsCreatePurge(w http.ResponseWriter, r *http.Request) {
	ctx, cancel := withTimeout(r, 3*time.Second)
	defer cancel()
	type purgeRequest struct {
		EntityType string   `json:"entity_type"`
		EntityRef  string   `json:"entity_ref"`
		URLs       []string `json:"urls"`
		Tags       []string `json:"tags"`
	}
	var body purgeRequest
	if err := parseJSON(r, &body); err != nil {
		writeError(w, r, http.StatusUnprocessableEntity, err.Error())
		writeJSON(w, http.StatusUnprocessableEntity, map[string]any{"error": err.Error()})
		writeData(w, http.StatusUnprocessableEntity, map[string]any{"error": err.Error()})
		return
	}
	urls, _ := json.Marshal(body.URLs)
	tags, _ := json.Marshal(body.Tags)
	res, err := app.db.ExecContext(ctx, `INSERT INTO cloudflare_purge_jobs (tenant_id,entity_type,entity_ref,urls,tags,status,scheduled_at,created_at,updated_at) VALUES (?,?,?,?,?,'pending',NOW(),NOW(),NOW())`, app.cfg.TenantID, nullableString(body.EntityType), nullableString(body.EntityRef), string(urls), string(tags))
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	id, _ := res.LastInsertId()
	writeData(w, http.StatusAccepted, map[string]any{"job_id": id, "status": "pending"})
}
