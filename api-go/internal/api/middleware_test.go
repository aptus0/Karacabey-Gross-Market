package api

import (
	"compress/gzip"
	"io"
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"
	"time"
)

func TestGzipMiddlewareWritesTinyJSONRaw(t *testing.T) {
	app := &App{}
	handler := app.gzip(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		writeJSON(w, http.StatusOK, map[string]string{"ok": "yes"})
	}))

	req := httptest.NewRequest(http.MethodGet, "/tiny", nil)
	req.Header.Set("Accept-Encoding", "gzip")
	rec := httptest.NewRecorder()

	handler.ServeHTTP(rec, req)

	res := rec.Result()
	defer res.Body.Close()

	if got := res.Header.Get("Content-Encoding"); got != "" {
		t.Fatalf("Content-Encoding = %q, want empty", got)
	}
	body, err := io.ReadAll(res.Body)
	if err != nil {
		t.Fatalf("read body: %v", err)
	}
	if !strings.Contains(string(body), `"ok":"yes"`) {
		t.Fatalf("body = %q, want raw JSON", string(body))
	}
}

func TestRateLimiterResetAllowsNextAttempt(t *testing.T) {
	limiter := NewRateLimiter(1, time.Minute)
	if !limiter.Allow("login:test") {
		t.Fatal("first attempt should be allowed")
	}
	if limiter.Allow("login:test") {
		t.Fatal("second attempt should be blocked")
	}
	limiter.Reset("login:test")
	if !limiter.Allow("login:test") {
		t.Fatal("attempt after reset should be allowed")
	}
}

func TestGzipMiddlewareCompressesLargeJSON(t *testing.T) {
	app := &App{}
	payload := strings.Repeat("a", gzipMinSize)
	handler := app.gzip(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		writeJSON(w, http.StatusOK, map[string]string{"payload": payload})
	}))

	req := httptest.NewRequest(http.MethodGet, "/large", nil)
	req.Header.Set("Accept-Encoding", "gzip")
	rec := httptest.NewRecorder()

	handler.ServeHTTP(rec, req)

	res := rec.Result()
	defer res.Body.Close()

	if got := res.Header.Get("Content-Encoding"); got != "gzip" {
		t.Fatalf("Content-Encoding = %q, want gzip", got)
	}
	reader, err := gzip.NewReader(res.Body)
	if err != nil {
		t.Fatalf("open gzip body: %v", err)
	}
	defer reader.Close()

	body, err := io.ReadAll(reader)
	if err != nil {
		t.Fatalf("read gzip body: %v", err)
	}
	if !strings.Contains(string(body), payload) {
		t.Fatal("decompressed body does not contain payload")
	}
}

func TestGzipMiddlewareDoesNotCompressEventStream(t *testing.T) {
	app := &App{}
	handler := app.gzip(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "text/event-stream")
		_, _ = w.Write([]byte("event: heartbeat\ndata: {\"ok\":true}\n\n"))
	}))

	req := httptest.NewRequest(http.MethodGet, "/stream", nil)
	req.Header.Set("Accept-Encoding", "gzip")
	rec := httptest.NewRecorder()
	handler.ServeHTTP(rec, req)

	if got := rec.Header().Get("Content-Encoding"); got != "" {
		t.Fatalf("Content-Encoding = %q, want empty", got)
	}
	if !strings.Contains(rec.Body.String(), "event: heartbeat") {
		t.Fatalf("body = %q, want event stream", rec.Body.String())
	}
}
