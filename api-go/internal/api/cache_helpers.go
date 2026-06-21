package api

import (
	"context"
	"encoding/json"
	"log/slog"
	"time"
)

func (app *App) cachedJSON(ctx context.Context, key string, ttl time.Duration, dst any, loader func() (any, error)) (any, error) {
	if app.redis != nil && app.redis.Enabled() {
		if raw, ok, err := app.redis.Get(ctx, key); err == nil && ok {
			if err := json.Unmarshal(raw, dst); err == nil {
				return dst, nil
			}
		} else if err != nil {
			slog.Warn("redis get failed", "key", key, "error", err)
		}
	}
	value, err := loader()
	if err != nil {
		return nil, err
	}
	if app.redis != nil && app.redis.Enabled() {
		if raw, err := json.Marshal(value); err == nil {
			if err := app.redis.SetEX(ctx, key, ttl, raw); err != nil {
				slog.Warn("redis set failed", "key", key, "error", err)
			}
		}
	}
	return value, nil
}
