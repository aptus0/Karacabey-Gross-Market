package api

import (
	"context"
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"
	"time"
)

func TestSendPasswordResetMailQueuesRealMessage(t *testing.T) {
	var received struct {
		To       []string `json:"to"`
		Subject  string   `json:"subject"`
		TextBody string   `json:"text_body"`
	}
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if got := r.Header.Get("X-Mail-Admin-Token"); got != "test-mail-token" {
			http.Error(w, "unauthorized", http.StatusUnauthorized)
			return
		}
		if err := json.NewDecoder(r.Body).Decode(&received); err != nil {
			http.Error(w, "bad body", http.StatusBadRequest)
			return
		}
		w.WriteHeader(http.StatusAccepted)
	}))
	defer server.Close()

	app := &App{cfg: Config{
		MailServiceURL:   server.URL,
		MailAdminToken:   "test-mail-token",
		PasswordResetTTL: 30 * time.Minute,
	}}
	resetURL := "https://example.com/auth/reset-password?token=secret"
	if err := app.sendPasswordResetMail(context.Background(), "customer@example.com", resetURL); err != nil {
		t.Fatalf("sendPasswordResetMail() error = %v", err)
	}
	if len(received.To) != 1 || received.To[0] != "customer@example.com" {
		t.Fatalf("unexpected recipients: %#v", received.To)
	}
	if !strings.Contains(received.TextBody, resetURL) || received.Subject == "" {
		t.Fatalf("reset message missing required content: %#v", received)
	}
}

func TestSendPasswordResetMailRejectsDeliveryFailure(t *testing.T) {
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		http.Error(w, "queue unavailable", http.StatusServiceUnavailable)
	}))
	defer server.Close()

	app := &App{cfg: Config{MailServiceURL: server.URL, MailAdminToken: "test-mail-token"}}
	if err := app.sendPasswordResetMail(context.Background(), "customer@example.com", "https://example.com/reset"); err == nil {
		t.Fatal("mail delivery failure must not be reported as success")
	}
}

func TestValidResetEmail(t *testing.T) {
	if !validResetEmail("customer@example.com") {
		t.Fatal("valid email rejected")
	}
	if validResetEmail("Customer <customer@example.com>") {
		t.Fatal("display-name email should be rejected")
	}
}
