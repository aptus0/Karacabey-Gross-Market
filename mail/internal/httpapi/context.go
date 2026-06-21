package httpapi

import (
	"context"
	"net/http"
)

type ctxKey string

const requestUIDKey ctxKey = "request_uid"

func withRequestUID(ctx context.Context, uid string) context.Context {
	return context.WithValue(ctx, requestUIDKey, uid)
}
func requestUID(r *http.Request) string {
	if v, ok := r.Context().Value(requestUIDKey).(string); ok {
		return v
	}
	return r.Header.Get("X-Request-UID")
}
