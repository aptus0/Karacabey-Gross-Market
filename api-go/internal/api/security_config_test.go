package api

import (
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"
	"time"
)

func TestProductionConfigRequiresStrongEnforcedActionToken(t *testing.T) {
	cfg := Config{Env: "production", ActionTokenSecret: "CHANGE_ME_LONG_RANDOM_ACTION_SECRET", ActionTokenMode: "report"}
	if err := cfg.Validate(); err == nil {
		t.Fatal("insecure production config should be rejected")
	}

	cfg.ActionTokenSecret = strings.Repeat("a", 32)
	if err := cfg.Validate(); err == nil {
		t.Fatal("report mode should be rejected in production")
	}

	cfg.ActionTokenMode = "enforce"
	cfg.MailServiceURL = "http://mail:8088"
	cfg.MailAdminToken = strings.Repeat("m", 24)
	cfg.MaxBodyBytes = 1 << 20
	if err := cfg.Validate(); err != nil {
		t.Fatalf("secure production config rejected: %v", err)
	}
}

func TestProductionConfigRequiresMailService(t *testing.T) {
	cfg := Config{
		Env:               "production",
		ActionTokenSecret: strings.Repeat("a", 32),
		ActionTokenMode:   "enforce",
		MaxBodyBytes:      1 << 20,
	}
	if err := cfg.Validate(); err == nil {
		t.Fatal("production config without mail service should be rejected")
	}

	cfg.MailServiceURL = "http://mail:8088"
	cfg.MailAdminToken = "CHANGE_ME"
	if err := cfg.Validate(); err == nil {
		t.Fatal("production config with insecure mail token should be rejected")
	}
}

func TestNonProductionConfigAllowsReportMode(t *testing.T) {
	cfg := Config{Env: "local", ActionTokenMode: "report"}
	if err := cfg.Validate(); err != nil {
		t.Fatalf("local config rejected: %v", err)
	}
}

func TestCanCancelOrder(t *testing.T) {
	tests := []struct {
		name            string
		orderStatus     string
		paymentProvider string
		paymentStatus   string
		want            bool
	}{
		{"awaiting card", "awaiting_payment", "paytr", "pending", true},
		{"cash reviewing", "reviewing", "cash_on_delivery", "pending", true},
		{"paid preparing", "preparing", "paytr", "paid", false},
		{"shipped cash", "shipped", "cash_on_delivery", "pending", false},
		{"already cancelled", "cancelled", "paytr", "cancelled", false},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			if got := canCancelOrder(tt.orderStatus, tt.paymentProvider, tt.paymentStatus); got != tt.want {
				t.Fatalf("canCancelOrder() = %v, want %v", got, tt.want)
			}
		})
	}
}

func TestAuthCookieIsHttpOnlyAndPreferredOverBearer(t *testing.T) {
	app := &App{cfg: Config{CookieSecure: true, CookieDomain: ".example.com"}}
	recorder := httptest.NewRecorder()
	expiresAt := time.Now().Add(time.Hour)
	app.writeAuthCookie(recorder, "cookie-token", expiresAt)

	cookies := recorder.Result().Cookies()
	if len(cookies) != 1 {
		t.Fatalf("expected one cookie, got %d", len(cookies))
	}
	cookie := cookies[0]
	if !cookie.HttpOnly || !cookie.Secure || cookie.SameSite != http.SameSiteLaxMode {
		t.Fatalf("auth cookie security flags missing: %#v", cookie)
	}

	request := httptest.NewRequest("GET", "/api/v1/auth/me", nil)
	request.AddCookie(cookie)
	request.Header.Set("Authorization", "Bearer stale-browser-token")
	if got := requestBearerToken(request); got != "cookie-token" {
		t.Fatalf("requestBearerToken() = %q, want cookie token", got)
	}
}
