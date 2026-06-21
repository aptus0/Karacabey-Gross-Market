package api

import (
	"context"
	"encoding/json"
	"log/slog"
	"net/http"
	"strconv"
	"strings"
	"time"
)

type trackingConsentRequest struct {
	Source  string                 `json:"source"`
	Consent map[string]interface{} `json:"consent"`
}

type trackingEventRequest struct {
	EventID     string                 `json:"event_id"`
	EventName   string                 `json:"event_name"`
	Category    string                 `json:"category"`
	AnonymousID string                 `json:"anonymous_id"`
	SessionID   string                 `json:"session_id"`
	CartToken   string                 `json:"cart_token"`
	PageURL     string                 `json:"page_url"`
	Referrer    string                 `json:"referrer"`
	Source      string                 `json:"source"`
	Medium      string                 `json:"medium"`
	Campaign    string                 `json:"campaign"`
	ProductID   interface{}            `json:"product_id"`
	OrderID     interface{}            `json:"order_id"`
	ValueCents  *int64                 `json:"value_cents"`
	Currency    string                 `json:"currency"`
	EventData   map[string]interface{} `json:"event_data"`
	Consent     map[string]interface{} `json:"consent"`
	OccurredAt  string                 `json:"occurred_at"`
}

func (app *App) handleTrackingConsent(w http.ResponseWriter, r *http.Request) {
	var body trackingConsentRequest
	if err := parseJSON(r, &body); err != nil {
		writeError(w, r, http.StatusUnprocessableEntity, err.Error())
		return
	}

	ctx, cancel := withTimeout(r, 2*time.Second)
	defer cancel()

	stored := app.storeTrackingConsent(ctx, r, body) == nil
	if !stored {
		slog.Warn("tracking consent store failed")
	}

	writeData(w, http.StatusAccepted, map[string]any{"accepted": true, "stored": stored})
}

func (app *App) handleTrackingEvent(w http.ResponseWriter, r *http.Request) {
	var body trackingEventRequest
	if err := parseJSON(r, &body); err != nil {
		writeError(w, r, http.StatusUnprocessableEntity, err.Error())
		return
	}
	body.EventName = truncate(strings.TrimSpace(body.EventName), 100)
	if body.EventName == "" {
		writeError(w, r, http.StatusUnprocessableEntity, "event_name zorunludur.")
		return
	}

	ctx, cancel := withTimeout(r, 2*time.Second)
	defer cancel()

	stored := app.storeTrackingEvent(ctx, r, body) == nil
	if !stored {
		slog.Warn("tracking event store failed", "event", body.EventName)
	}

	writeData(w, http.StatusAccepted, map[string]any{"accepted": true, "stored": stored})
}

func (app *App) storeTrackingConsent(ctx context.Context, r *http.Request, body trackingConsentRequest) error {
	if app.db == nil {
		return nil
	}

	identity := requestIdentity(r.Context())
	requestID, _ := r.Context().Value(contextKeyRequestID).(string)
	_, err := app.db.ExecContext(ctx, `INSERT INTO cookie_consents
		(tenant_id,user_id,anonymous_id,session_id,cart_token,source,necessary,analytics,marketing,personalization,performance,consent_version,ip_address,user_agent,request_id,created_at,updated_at)
		VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())`,
		app.cfg.TenantID,
		nil,
		nullableString(firstNonEmpty(identity.CustomerUID, bodyString(body.Consent, "anonymous_id"))),
		nullableString(firstNonEmpty(identity.SessionUID, bodyString(body.Consent, "session_id"))),
		nullableString(truncate(r.Header.Get("X-Cart-Token"), 100)),
		nullableString(truncate(body.Source, 40)),
		true,
		bodyBool(body.Consent, "analytics"),
		bodyBool(body.Consent, "marketing"),
		bodyBool(body.Consent, "personalization"),
		bodyBool(body.Consent, "performance"),
		nullableString(truncate(bodyString(body.Consent, "version"), 40)),
		nullableString(clientIP(r)),
		nullableString(truncate(r.UserAgent(), 1000)),
		nullableString(truncate(requestID, 80)),
	)
	return err
}

func (app *App) storeTrackingEvent(ctx context.Context, r *http.Request, body trackingEventRequest) error {
	if app.db == nil {
		return nil
	}

	identity := requestIdentity(r.Context())
	eventData, _ := json.Marshal(body.EventData)
	consent, _ := json.Marshal(body.Consent)
	occurredAt := parseOptionalTime(body.OccurredAt)
	category := truncate(strings.TrimSpace(body.Category), 40)
	if category == "" {
		category = "analytics"
	}
	currency := truncate(strings.TrimSpace(body.Currency), 8)
	if currency == "" {
		currency = "TRY"
	}

	_, err := app.db.ExecContext(ctx, `INSERT IGNORE INTO tracking_events
		(tenant_id,user_id,event_id,event_name,category,anonymous_id,session_id,cart_token,page_url,referrer,source,medium,campaign,product_id,order_id,value_cents,currency,event_data,consent_snapshot,ip_address,user_agent,occurred_at,created_at)
		VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())`,
		app.cfg.TenantID,
		nil,
		nullableString(truncate(body.EventID, 100)),
		body.EventName,
		category,
		nullableString(firstNonEmpty(truncate(body.AnonymousID, 100), identity.CustomerUID)),
		nullableString(firstNonEmpty(truncate(body.SessionID, 100), identity.SessionUID)),
		nullableString(truncate(body.CartToken, 100)),
		nullableString(truncate(body.PageURL, 4000)),
		nullableString(truncate(body.Referrer, 4000)),
		nullableString(truncate(body.Source, 120)),
		nullableString(truncate(body.Medium, 120)),
		nullableString(truncate(body.Campaign, 160)),
		numericID(body.ProductID),
		numericID(body.OrderID),
		sqlNullInt64Ptr(body.ValueCents),
		currency,
		nullableJSON(eventData),
		nullableJSON(consent),
		nullableString(clientIP(r)),
		nullableString(truncate(r.UserAgent(), 1000)),
		occurredAt,
	)
	return err
}

func bodyBool(body map[string]interface{}, key string) bool {
	value, ok := body[key]
	if !ok {
		return false
	}
	switch typed := value.(type) {
	case bool:
		return typed
	case string:
		typed = strings.ToLower(strings.TrimSpace(typed))
		return typed == "1" || typed == "true" || typed == "yes" || typed == "on"
	default:
		return false
	}
}

func bodyString(body map[string]interface{}, key string) string {
	value, ok := body[key]
	if !ok || value == nil {
		return ""
	}
	return strings.TrimSpace(toString(value))
}

func toString(value interface{}) string {
	switch typed := value.(type) {
	case string:
		return typed
	case float64:
		if typed == float64(int64(typed)) {
			return strconv.FormatInt(int64(typed), 10)
		}
		return strconv.FormatFloat(typed, 'f', -1, 64)
	case bool:
		return strconv.FormatBool(typed)
	default:
		return ""
	}
}

func numericID(value interface{}) interface{} {
	switch typed := value.(type) {
	case float64:
		if typed > 0 {
			return int64(typed)
		}
	case string:
		if parsed, err := strconv.ParseInt(strings.TrimSpace(typed), 10, 64); err == nil && parsed > 0 {
			return parsed
		}
	}
	return nil
}

func parseOptionalTime(raw string) interface{} {
	raw = strings.TrimSpace(raw)
	if raw == "" {
		return time.Now().UTC()
	}
	if parsed, err := time.Parse(time.RFC3339Nano, raw); err == nil {
		return parsed.UTC()
	}
	return time.Now().UTC()
}

func nullableJSON(raw []byte) interface{} {
	if len(raw) == 0 || string(raw) == "null" {
		return nil
	}
	return string(raw)
}

func truncate(value string, max int) string {
	value = strings.TrimSpace(value)
	if max > 0 && len(value) > max {
		return value[:max]
	}
	return value
}
