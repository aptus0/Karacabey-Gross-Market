package api

import (
	"context"
	"crypto/sha256"
	"database/sql"
	"encoding/hex"
	"encoding/json"
	"errors"
	"fmt"
	"net/http"
	"strings"
	"time"
)

type IdempotencyDecision struct {
	Proceed      bool
	Replay       bool
	StatusCode   int
	ResponseBody []byte
	Key          string
	RequestHash  string
}

func checkoutIdempotencyKey(r *http.Request, body CheckoutRequest) string {
	return sanitizeUID(firstNonEmpty(r.Header.Get("X-Idempotency-Key"), body.PaymentUID, body.CheckoutUID, body.CheckoutKey))
}

func checkoutRequestHash(body CheckoutRequest) string {
	clone := body
	clone.CheckoutKey = ""
	raw, _ := json.Marshal(clone)
	sum := sha256.Sum256(raw)
	return hex.EncodeToString(sum[:])
}

func (app *App) beginIdempotency(ctx context.Context, scope, key, requestHash string, ttl time.Duration) (IdempotencyDecision, error) {
	key = sanitizeUID(key)
	if key == "" {
		return IdempotencyDecision{Proceed: true}, nil
	}
	if ttl <= 0 {
		ttl = 15 * time.Minute
	}
	lockUntil := time.Now().Add(ttl)
	res, err := app.db.ExecContext(ctx, `INSERT IGNORE INTO idempotency_keys
		(tenant_id, scope, idempotency_key, request_hash, status, locked_until, created_at, updated_at)
		VALUES (?, ?, ?, ?, 'processing', ?, NOW(), NOW())`, app.cfg.TenantID, scope, key, requestHash, lockUntil)
	if err != nil {
		return IdempotencyDecision{}, err
	}
	if affected, _ := res.RowsAffected(); affected == 1 {
		return IdempotencyDecision{Proceed: true, Key: key, RequestHash: requestHash}, nil
	}

	var existingHash, status string
	var responseBody sql.NullString
	var statusCode sql.NullInt64
	var lockedUntil sql.NullTime
	err = app.db.QueryRowContext(ctx, `SELECT request_hash,status,response_code,response_body,locked_until
		FROM idempotency_keys WHERE tenant_id=? AND scope=? AND idempotency_key=? LIMIT 1`, app.cfg.TenantID, scope, key).Scan(&existingHash, &status, &statusCode, &responseBody, &lockedUntil)
	if err != nil {
		return IdempotencyDecision{}, err
	}
	if existingHash != requestHash {
		return IdempotencyDecision{}, fmt.Errorf("%w: Aynı idempotency key farklı istek gövdesiyle kullanılamaz.", ErrConflict)
	}
	if status == "completed" && responseBody.Valid {
		code := http.StatusOK
		if statusCode.Valid && statusCode.Int64 > 0 {
			code = int(statusCode.Int64)
		}
		return IdempotencyDecision{Replay: true, StatusCode: code, ResponseBody: []byte(responseBody.String), Key: key, RequestHash: requestHash}, nil
	}
	if lockedUntil.Valid && lockedUntil.Time.After(time.Now()) {
		return IdempotencyDecision{}, fmt.Errorf("%w: Bu ödeme işlemi hâlâ işleniyor. Lütfen tekrar ödeme başlatmadan sonucu bekleyin.", ErrConflict)
	}
	_, err = app.db.ExecContext(ctx, `UPDATE idempotency_keys SET status='processing', locked_until=?, updated_at=NOW()
		WHERE tenant_id=? AND scope=? AND idempotency_key=?`, lockUntil, app.cfg.TenantID, scope, key)
	if err != nil {
		return IdempotencyDecision{}, err
	}
	return IdempotencyDecision{Proceed: true, Key: key, RequestHash: requestHash}, nil
}

func (app *App) completeIdempotency(ctx context.Context, scope, key string, statusCode int, payload any) {
	key = sanitizeUID(key)
	if key == "" {
		return
	}
	raw, _ := json.Marshal(map[string]any{"data": payload})
	_, _ = app.db.ExecContext(ctx, `UPDATE idempotency_keys SET status='completed', response_code=?, response_body=?, locked_until=NULL, updated_at=NOW(), completed_at=NOW()
		WHERE tenant_id=? AND scope=? AND idempotency_key=?`, statusCode, string(raw), app.cfg.TenantID, scope, key)
}

func (app *App) failIdempotency(ctx context.Context, scope, key string, err error) {
	key = sanitizeUID(key)
	if key == "" {
		return
	}
	msg := ""
	if err != nil {
		msg = err.Error()
	}
	if errors.Is(err, ErrConflict) || strings.TrimSpace(msg) != "" {
		_, _ = app.db.ExecContext(ctx, `UPDATE idempotency_keys SET status='failed', error_message=?, locked_until=NULL, updated_at=NOW()
			WHERE tenant_id=? AND scope=? AND idempotency_key=?`, msg, app.cfg.TenantID, scope, key)
	}
}
