package api

import (
	"compress/gzip"
	"context"
	"crypto/rand"
	"encoding/hex"
	"fmt"
	"log/slog"
	"net"
	"net/http"
	"runtime/debug"
	"strings"
	"sync"
	"time"
)

type contextKey string

const contextKeyRequestID contextKey = "request_id"

const gzipMinSize = 768

var gzipWriterPool = sync.Pool{
	New: func() any { return gzip.NewWriter(nil) },
}

var gzipBufferableContentTypes = []string{
	"application/json",
	"application/javascript",
	"application/xml",
	"text/",
	"image/svg+xml",
}

type gzipResponseWriter struct {
	http.ResponseWriter
	writer       *gzip.Writer
	wroteHeader  bool
	useGzip      bool
	statusCode   int
	sentHeader   bool
	buffer       []byte
	flushedBytes int
}

func (w *gzipResponseWriter) Unwrap() http.ResponseWriter {
	return w.ResponseWriter
}

func shouldCompressContentType(value string) bool {
	value = strings.ToLower(value)
	if strings.HasPrefix(value, "text/event-stream") {
		return false
	}
	for _, prefix := range gzipBufferableContentTypes {
		if strings.HasPrefix(value, prefix) {
			return true
		}
	}
	return false
}

func (w *gzipResponseWriter) WriteHeader(status int) {
	if w.wroteHeader {
		return
	}
	w.wroteHeader = true
	w.statusCode = status
	headers := w.ResponseWriter.Header()
	if shouldCompressContentType(headers.Get("Content-Type")) {
		w.useGzip = true
		headers.Del("Content-Length")
		headers.Add("Vary", "Accept-Encoding")
		return
	}
	w.sentHeader = true
	w.ResponseWriter.WriteHeader(status)
}

func (w *gzipResponseWriter) sendGzipHeader() {
	if w.sentHeader {
		return
	}
	w.sentHeader = true
	w.Header().Set("Content-Encoding", "gzip")
	w.ResponseWriter.WriteHeader(w.statusCode)
}

func (w *gzipResponseWriter) Write(data []byte) (int, error) {
	if !w.wroteHeader {
		// http.ResponseWriter contract: ensure WriteHeader is called before Write,
		// so we can decide on gzip based on Content-Type (which handlers set first).
		if w.Header().Get("Content-Type") == "" {
			w.Header().Set("Content-Type", http.DetectContentType(data))
		}
		w.WriteHeader(http.StatusOK)
	}
	if !w.useGzip {
		return w.ResponseWriter.Write(data)
	}
	if w.flushedBytes == 0 && len(w.buffer)+len(data) < gzipMinSize {
		w.buffer = append(w.buffer, data...)
		return len(data), nil
	}
	if len(w.buffer) > 0 {
		w.sendGzipHeader()
		if _, err := w.writer.Write(w.buffer); err != nil {
			return 0, err
		}
		w.flushedBytes += len(w.buffer)
		w.buffer = w.buffer[:0]
	}
	w.sendGzipHeader()
	n, err := w.writer.Write(data)
	w.flushedBytes += n
	return n, err
}

func (w *gzipResponseWriter) finalize() error {
	if !w.wroteHeader {
		return nil
	}
	if !w.useGzip {
		return nil
	}
	if w.flushedBytes == 0 && len(w.buffer) > 0 {
		// Tiny response: skip gzip overhead and write raw before headers are sent.
		w.sentHeader = true
		w.ResponseWriter.WriteHeader(w.statusCode)
		_, err := w.ResponseWriter.Write(w.buffer)
		w.buffer = nil
		return err
	}
	if w.flushedBytes == 0 && len(w.buffer) == 0 {
		w.sentHeader = true
		w.ResponseWriter.WriteHeader(w.statusCode)
		return nil
	}
	if len(w.buffer) > 0 {
		w.sendGzipHeader()
		if _, err := w.writer.Write(w.buffer); err != nil {
			return err
		}
		w.buffer = nil
	}
	w.sendGzipHeader()
	return w.writer.Close()
}

type statusCaptureWriter struct {
	http.ResponseWriter
	status int
}

func (w *statusCaptureWriter) Unwrap() http.ResponseWriter {
	return w.ResponseWriter
}

func (w *statusCaptureWriter) WriteHeader(status int) {
	w.status = status
	w.ResponseWriter.WriteHeader(status)
}

func (w *statusCaptureWriter) Write(data []byte) (int, error) {
	if w.status == 0 {
		w.status = http.StatusOK
	}
	return w.ResponseWriter.Write(data)
}

func (app *App) monitor(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		started := time.Now()
		capture := &statusCaptureWriter{ResponseWriter: w}
		next.ServeHTTP(capture, r)
		status := capture.status
		if status == 0 {
			status = http.StatusOK
		}
		duration := time.Since(started)
		app.metrics.Observe(r.Method, r.URL.Path, status, duration, clientIP(r))
		if status >= 500 || duration >= 750*time.Millisecond {
			args := []any{
				"method", r.Method,
				"path", normalizeMetricPath(r.URL.Path),
				"status", status,
				"duration_ms", duration.Milliseconds(),
				"request_id", r.Context().Value(contextKeyRequestID),
			}
			if app.db != nil {
				stats := app.db.Stats()
				args = append(args,
					"db_in_use", stats.InUse,
					"db_idle", stats.Idle,
					"db_wait_count", stats.WaitCount,
					"db_wait_ms", stats.WaitDuration.Milliseconds(),
				)
			}
			slog.Warn("api request observed", args...)
		}
	})
}

func (app *App) internalOnly(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		token := strings.TrimSpace(r.Header.Get("X-Internal-Token"))
		if token == "" {
			token = requestBearerToken(r)
		}
		if app.cfg.InternalAPIToken == "" || token == "" || token != app.cfg.InternalAPIToken {
			writeError(w, r, http.StatusForbidden, "Internal endpoint için geçerli token gerekli.")
			return
		}
		next.ServeHTTP(w, r)
	})
}

func (app *App) recover(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		defer func() {
			if rec := recover(); rec != nil {
				slog.Error("panic recovered", "panic", rec, "stack", string(debug.Stack()))
				writeError(w, r, http.StatusInternalServerError, "Beklenmeyen bir hata oluştu.")
			}
		}()
		next.ServeHTTP(w, r)
	})
}

func (app *App) requestID(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		requestID := r.Header.Get("X-Request-ID")
		if requestID == "" {
			requestID = randomHex(12)
		}
		w.Header().Set("X-Request-ID", requestID)
		next.ServeHTTP(w, r.WithContext(context.WithValue(r.Context(), contextKeyRequestID, requestID)))
	})
}

func (app *App) security(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		h := w.Header()
		h.Set("X-Content-Type-Options", "nosniff")
		h.Set("X-Frame-Options", "DENY")
		h.Set("Referrer-Policy", "strict-origin-when-cross-origin")
		h.Set("Permissions-Policy", "camera=(), microphone=(), geolocation=(), payment=()")
		h.Set("Cross-Origin-Opener-Policy", "same-origin")
		h.Set("Cross-Origin-Resource-Policy", "same-site")
		h.Set("Origin-Agent-Cluster", "?1")
		h.Set("X-Permitted-Cross-Domain-Policies", "none")
		h.Set("X-DNS-Prefetch-Control", "off")
		if r.TLS != nil || strings.EqualFold(r.Header.Get("X-Forwarded-Proto"), "https") {
			h.Set("Strict-Transport-Security", "max-age=31536000; includeSubDomains; preload")
		}
		if isPrivateAPIPath(r.URL.Path) {
			h.Set("Cache-Control", "no-store, no-cache, max-age=0, must-revalidate")
			h.Set("Pragma", "no-cache")
		}
		next.ServeHTTP(w, r)
	})
}

func isPrivateAPIPath(path string) bool {
	privatePrefixes := []string{
		"/api/v1/cart",
		"/api/v1/checkout",
		"/api/v1/auth",
		"/api/v1/orders",
		"/api/v1/addresses",
		"/api/v1/favorites",
		"/api/v1/notifications",
		"/api/v1/customer",
	}
	for _, prefix := range privatePrefixes {
		if strings.HasPrefix(path, prefix) {
			return true
		}
	}
	return false
}

func (app *App) cors(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		origin := strings.TrimRight(r.Header.Get("Origin"), "/")
		if _, ok := app.cfg.AllowedOrigins[origin]; ok {
			h := w.Header()
			h.Set("Access-Control-Allow-Origin", origin)
			h.Add("Vary", "Origin")
			h.Set("Access-Control-Allow-Credentials", "true")
			h.Set("Access-Control-Allow-Headers", "Authorization, Content-Type, X-Cart-Token, X-Checkout-Key, X-Payment-UID, X-Customer-UID, X-Session-UID, X-Request-ID, X-Device-Id, X-App-Version, X-Platform, X-Idempotency-Key, X-Action-Token, X-Action-Token-Status")
			h.Set("Access-Control-Allow-Methods", "GET, POST, PATCH, PUT, DELETE, OPTIONS")
			h.Set("Access-Control-Expose-Headers", "X-Request-ID, X-Customer-UID, X-Session-UID, X-Cart-Token, X-Customer-Sync-Version, X-Idempotency-Key, X-Action-Token, X-Action-Token-Status")
			h.Set("Access-Control-Max-Age", "600")
		}
		if r.Method == http.MethodOptions {
			w.WriteHeader(http.StatusNoContent)
			return
		}
		next.ServeHTTP(w, r)
	})
}

func (app *App) gzip(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if !strings.Contains(r.Header.Get("Accept-Encoding"), "gzip") || r.Header.Get("Range") != "" {
			next.ServeHTTP(w, r)
			return
		}
		gz := gzipWriterPool.Get().(*gzip.Writer)
		gz.Reset(w)
		gzw := &gzipResponseWriter{ResponseWriter: w, writer: gz}
		defer func() {
			_ = gzw.finalize()
			gz.Reset(nil)
			gzipWriterPool.Put(gz)
		}()
		next.ServeHTTP(gzw, r)
	})
}

func (app *App) cdnCache(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		path := r.URL.Path
		if r.Method == http.MethodGet && (strings.HasPrefix(path, "/api/v1/products") || strings.HasPrefix(path, "/api/v1/categories") || strings.HasPrefix(path, "/api/v1/content") || strings.HasPrefix(path, "/api/v1/mobile/bootstrap")) {
			maxAge := app.cfg.CatalogCacheMaxAge
			stale := app.cfg.CatalogStaleSeconds
			if maxAge <= 0 {
				maxAge = 60
			}
			if stale <= 0 {
				stale = 300
			}
			w.Header().Set("Cache-Control", fmt.Sprintf("public, max-age=%d, stale-while-revalidate=%d", maxAge, stale))
			w.Header().Set("CDN-Cache-Control", fmt.Sprintf("public, max-age=%d, stale-while-revalidate=%d", maxAge, stale))
			w.Header().Set("Vary", "Accept-Encoding, Origin")
		}
		next.ServeHTTP(w, r)
	})
}

func (app *App) maxBody(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		limit := app.cfg.MaxBodyBytes
		if limit <= 0 {
			limit = 32 << 20
		}
		if r.ContentLength > limit {
			writeError(w, r, http.StatusRequestEntityTooLarge, "Gönderilen veri çok büyük. Lütfen daha küçük bir içerik gönderin.")
			return
		}
		r.Body = http.MaxBytesReader(w, r.Body, limit)
		next.ServeHTTP(w, r)
	})
}

func (app *App) maintenance(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if !app.cfg.MaintenanceMode || !strings.HasPrefix(r.URL.Path, "/api/v1/") {
			next.ServeHTTP(w, r)
			return
		}

		if r.URL.Path == "/api/v1/system/status" || r.Method == http.MethodGet || r.Method == http.MethodHead || r.Method == http.MethodOptions {
			next.ServeHTTP(w, r)
			return
		}

		w.Header().Set("Retry-After", "300")
		w.Header().Set("Cache-Control", "no-store, no-cache, max-age=0, must-revalidate")
		writeError(w, r, http.StatusServiceUnavailable, "Karacabey Gross Market kısa süreli bakımda. Lütfen biraz sonra tekrar deneyin.")
	})
}

func (app *App) rateLimit(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		ip := clientIP(r)
		if !app.limiter.Allow(ip) {
			writeError(w, r, http.StatusTooManyRequests, "Çok fazla istek gönderildi. Lütfen biraz sonra tekrar deneyin.")
			return
		}
		next.ServeHTTP(w, r)
	})
}

func (app *App) paymentGuard(next http.HandlerFunc) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		if !app.payLimiter.Allow(clientIP(r)) {
			writeError(w, r, http.StatusTooManyRequests, "Ödeme denemesi limiti aşıldı. Lütfen biraz sonra tekrar deneyin.")
			return
		}
		next(w, r)
	}
}

func clientIP(r *http.Request) string {
	if forwarded := strings.TrimSpace(r.Header.Get("X-Forwarded-For")); forwarded != "" {
		return strings.TrimSpace(strings.Split(forwarded, ",")[0])
	}
	if realIP := strings.TrimSpace(r.Header.Get("X-Real-IP")); realIP != "" {
		return realIP
	}
	host, _, err := net.SplitHostPort(r.RemoteAddr)
	if err == nil {
		return host
	}
	return r.RemoteAddr
}

func randomHex(bytesLen int) string {
	b := make([]byte, bytesLen)
	if _, err := rand.Read(b); err != nil {
		return time.Now().Format("20060102150405.000000000")
	}
	return hex.EncodeToString(b)
}

type RateLimiter struct {
	mu       sync.Mutex
	limit    int
	window   time.Duration
	counters map[string]rateBucket
}

type rateBucket struct {
	resetAt time.Time
	count   int
}

func NewRateLimiter(limit int, window time.Duration) *RateLimiter {
	if limit <= 0 {
		limit = 600
	}
	l := &RateLimiter{limit: limit, window: window, counters: map[string]rateBucket{}}
	go l.runCleanup()
	return l
}

// runCleanup evicts expired buckets every minute so the map size stays bounded
// regardless of unique-key cardinality (e.g. IP churn behind Cloudflare).
func (l *RateLimiter) runCleanup() {
	ticker := time.NewTicker(time.Minute)
	defer ticker.Stop()
	for range ticker.C {
		now := time.Now()
		l.mu.Lock()
		for k, v := range l.counters {
			if now.After(v.resetAt) {
				delete(l.counters, k)
			}
		}
		l.mu.Unlock()
	}
}

func (l *RateLimiter) Allow(key string) bool {
	now := time.Now()
	l.mu.Lock()
	defer l.mu.Unlock()
	bucket := l.counters[key]
	if bucket.resetAt.IsZero() || now.After(bucket.resetAt) {
		l.counters[key] = rateBucket{resetAt: now.Add(l.window), count: 1}
		return true
	}
	if bucket.count >= l.limit {
		return false
	}
	bucket.count++
	l.counters[key] = bucket
	return true
}

func (l *RateLimiter) Reset(key string) {
	l.mu.Lock()
	delete(l.counters, key)
	l.mu.Unlock()
}
