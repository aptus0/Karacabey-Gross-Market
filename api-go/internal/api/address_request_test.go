package api

import (
	"net/http/httptest"
	"strings"
	"testing"
)

func TestReadAddressBodyAcceptsMobileCoordinates(t *testing.T) {
	body := `{
		"title":"Ev",
		"recipient_name":"Test Musteri",
		"phone":"5551234567",
		"city":"Bursa",
		"district":"Karacabey",
		"neighborhood":"Drama",
		"address_line":"Runguçpaşa Caddesi No:1",
		"postal_code":"16700",
		"latitude":40.213,
		"longitude":28.361,
		"is_default":true
	}`

	req := httptest.NewRequest("POST", "/api/v1/addresses", strings.NewReader(body))
	payload, err := readAddressBody(req)
	if err != nil {
		t.Fatalf("readAddressBody returned error: %v", err)
	}
	if payload.RecipientName != "Test Musteri" {
		t.Fatalf("expected recipient Test Musteri, got %q", payload.RecipientName)
	}
	if !payload.IsDefault {
		t.Fatal("expected address to be default")
	}
}
