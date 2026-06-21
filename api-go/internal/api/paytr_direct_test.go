package api

import (
	"encoding/json"
	"testing"
)

func TestDirectPaymentParamsDoNotContainSensitiveCardData(t *testing.T) {
	client := NewPayTRClient(PayTRConfig{
		MerchantID:   "merchant-id",
		MerchantKey:  "merchant-key",
		MerchantSalt: "merchant-salt",
		OKURL:        "https://example.com/checkout/success",
		FailURL:      "https://example.com/checkout/fail",
		Currency:     "TL",
		DirectURL:    "https://www.paytr.com/odeme",
	})
	order := OrderRecord{
		MerchantOID:     "KGM-123",
		CustomerEmail:   "customer@example.com",
		CustomerName:    "Test Musteri",
		CustomerPhone:   "5551234567",
		ShippingAddress: "Test adresi",
		TotalCents:      12990,
		Items: []OrderItemRecord{{
			Name:           "Test urunu",
			UnitPriceCents: 12990,
			Quantity:       1,
			LineTotalCents: 12990,
		}},
	}

	direct, reason, err := client.DirectPaymentParams(order, "127.0.0.1")
	if err != nil {
		t.Fatalf("DirectPaymentParams returned error: %v (%s)", err, reason)
	}
	if direct.PostURL != "https://www.paytr.com/odeme" {
		t.Fatalf("unexpected direct payment URL: %q", direct.PostURL)
	}
	for _, key := range []string{"card_number", "cc_owner", "expiry_month", "expiry_year", "cvv"} {
		if _, exists := direct.Fields[key]; exists {
			t.Fatalf("sensitive card field %q must not be returned by the API", key)
		}
	}
	for _, key := range []string{"merchant_id", "merchant_oid", "payment_amount", "paytr_token"} {
		if direct.Fields[key] == "" {
			t.Fatalf("expected PayTR field %q", key)
		}
	}
	if direct.Fields["payment_amount"] != "129.90" {
		t.Fatalf("expected decimal Direct API amount, got %q", direct.Fields["payment_amount"])
	}
	var basket [][]any
	if err := json.Unmarshal([]byte(direct.Fields["user_basket"]), &basket); err != nil {
		t.Fatalf("expected Direct API basket to be JSON: %v", err)
	}
}
