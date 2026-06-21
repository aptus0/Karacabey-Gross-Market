package mailer

import (
	"net/mail"
	"strings"
	"testing"
	"time"

	"kgm-mail-service/internal/store"
)

func TestBuildMessageIncludesRequiredHeaders(t *testing.T) {
	now := time.Date(2026, time.June, 12, 9, 30, 0, 0, time.UTC)
	from := mail.Address{Name: "Karacabey Gross Market", Address: "destek@karacabeygrossmarket.com"}
	raw := buildMessage(store.Message{
		To:       []string{"customer@example.com"},
		Subject:  "Hosgeldiniz",
		TextBody: "Merhaba",
	}, from, now)

	parsed, err := mail.ReadMessage(strings.NewReader(string(raw)))
	if err != nil {
		t.Fatalf("ReadMessage: %v", err)
	}

	if got, want := parsed.Header.Get("Date"), now.Format(time.RFC1123Z); got != want {
		t.Fatalf("Date = %q, want %q", got, want)
	}
	if got, want := parsed.Header.Get("Message-ID"), "<1781256600000000000@karacabeygrossmarket.com>"; got != want {
		t.Fatalf("Message-ID = %q, want %q", got, want)
	}
}
