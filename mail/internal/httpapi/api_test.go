package httpapi

import (
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"

	"kgm-mail-service/internal/config"
	"kgm-mail-service/internal/store"
)

func newTestAPI(t *testing.T) http.Handler {
	t.Helper()
	st, err := store.New(t.TempDir())
	if err != nil {
		t.Fatalf("store.New: %v", err)
	}
	cfg := config.Config{
		Addr:             ":0",
		AdminToken:       "test-admin-token",
		InboundToken:     "test-inbound-token",
		RateLimitPerMin:  1000,
		MaxJSONBodyBytes: 1 << 20,
		SMTPDisabled:     true,
	}
	return New(cfg, st, nil).Routes()
}

func TestAdminEndpointsRequireToken(t *testing.T) {
	handler := newTestAPI(t)
	req := httptest.NewRequest(http.MethodGet, "/api/v1/mail/queue/stats", nil)
	rec := httptest.NewRecorder()

	handler.ServeHTTP(rec, req)

	if rec.Code != http.StatusUnauthorized {
		t.Fatalf("status = %d, want %d", rec.Code, http.StatusUnauthorized)
	}
}

func TestAdminEndpointAcceptsInjectedHeader(t *testing.T) {
	handler := newTestAPI(t)
	req := httptest.NewRequest(http.MethodGet, "/api/v1/mail/queue/stats", nil)
	req.Header.Set("X-Mail-Admin-Token", "test-admin-token")
	rec := httptest.NewRecorder()

	handler.ServeHTTP(rec, req)

	if rec.Code != http.StatusOK {
		t.Fatalf("status = %d, want %d body=%s", rec.Code, http.StatusOK, rec.Body.String())
	}
}

func TestAdminEndpointAcceptsCookieFallback(t *testing.T) {
	handler := newTestAPI(t)
	req := httptest.NewRequest(http.MethodGet, "/api/v1/mailboxes", nil)
	req.AddCookie(&http.Cookie{Name: "mail_admin_token", Value: "test-admin-token"})
	rec := httptest.NewRecorder()

	handler.ServeHTTP(rec, req)

	if rec.Code != http.StatusOK {
		t.Fatalf("status = %d, want %d body=%s", rec.Code, http.StatusOK, rec.Body.String())
	}
}

func TestCSPAllowsGoogleMeasurementConnection(t *testing.T) {
	handler := newTestAPI(t)
	req := httptest.NewRequest(http.MethodGet, "/health", nil)
	rec := httptest.NewRecorder()

	handler.ServeHTTP(rec, req)

	csp := rec.Header().Get("Content-Security-Policy")
	if !strings.Contains(csp, "connect-src") || !strings.Contains(csp, "https://www.google.com") {
		t.Fatalf("CSP does not allow google measurement connection: %q", csp)
	}
}
