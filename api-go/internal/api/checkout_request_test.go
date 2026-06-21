package api

import (
	"encoding/json"
	"net/http/httptest"
	"strings"
	"testing"
)

func TestCheckoutRequestAcceptsShippingCarrier(t *testing.T) {
	body := `{
		"order_type":"individual",
		"customer":{"name":"Test Musteri","email":"test@example.com","phone":"5551234567"},
		"shipping":{"city":"Bursa","district":"Karacabey","address":"Test adres 1","carrier":"yurtici"},
		"shipping_quote":{"carrier":"yurtici","local_delivery":true,"shipping_cents":0},
		"checkout_uid":"chk_test",
		"payment_uid":"pay_test",
		"payment_flow":"cash_on_delivery",
		"items":[{"product_id":1,"quantity":1}]
	}`

	req := httptest.NewRequest("POST", "/api/v1/c", strings.NewReader(body))
	payload, err := parseCheckoutBody(req)
	if err != nil {
		t.Fatalf("parseCheckoutBody returned error: %v", err)
	}
	if payload.Shipping.Carrier != "yurtici" {
		t.Fatalf("expected shipping carrier yurtici, got %q", payload.Shipping.Carrier)
	}
	if payload.PaymentFlow != "cash_on_delivery" {
		t.Fatalf("expected payment_flow cash_on_delivery, got %q", payload.PaymentFlow)
	}
}

func TestCheckoutRequestAcceptsWebIframePayloadWithoutCardData(t *testing.T) {
	body := `{
		"order_type":"individual",
		"customer":{"name":"Test Musteri","email":"test@example.com","phone":"5551234567","tc_identity":""},
		"invoice":{"type":"individual","tc_identity":""},
		"shipping":{"city":"Bursa","district":"Karacabey","address":"Test adres 1","carrier":"yurtici","lat":40.2,"lng":28.3},
		"shipping_quote":{"carrier":"yurtici","local_delivery":true,"shipping_cents":0},
		"cart_token":"cart_test",
		"coupon_code":"KGM10",
		"checkout_key":"chk_test",
		"checkout_uid":"chk_test",
		"payment_uid":"pay_test",
		"payment_flow":"iframe",
		"items":[{"product_id":1,"quantity":1}]
	}`

	req := httptest.NewRequest("POST", "/api/v1/c", strings.NewReader(body))
	payload, err := parseCheckoutBody(req)
	if err != nil {
		t.Fatalf("parseCheckoutBody returned error: %v", err)
	}
	if payload.PaymentFlow != "iframe" {
		t.Fatalf("expected iframe payment flow, got %q", payload.PaymentFlow)
	}
	if payload.Shipping.Carrier != "yurtici" {
		t.Fatalf("expected shipping carrier yurtici, got %q", payload.Shipping.Carrier)
	}
}

func TestCheckoutRequestRejectsCardPaymentObject(t *testing.T) {
	body := `{
		"customer":{"name":"Test Musteri","email":"test@example.com","phone":"5551234567"},
		"shipping":{"address":"Test adres 1"},
		"payment":{"cc_number":"4508034508034509"},
		"items":[{"product_id":1,"quantity":1}]
	}`

	req := httptest.NewRequest("POST", "/api/v1/c", strings.NewReader(body))
	if _, err := parseCheckoutBody(req); err == nil || !strings.Contains(err.Error(), `unknown field "payment"`) {
		t.Fatalf("expected payment object to be rejected, got %v", err)
	}
}

func TestCheckoutRequestAcceptsPayTRMobileJSON(t *testing.T) {
	body := `{
		"orderId":"KGM-ORDER-ID",
		"userId":"5",
		"email":"customer@example.com",
		"phone":"05555555555",
		"amountKurus":129950,
		"currency":"TL",
		"addressId":"7",
		"basket":[{"productId":"42","name":"Product Name","quantity":1,"unitPriceKurus":129950}]
	}`

	req := httptest.NewRequest("POST", "/api/v1/c", strings.NewReader(body))
	payload, err := parseCheckoutBody(req)
	if err != nil {
		t.Fatalf("parseCheckoutBody returned error: %v", err)
	}
	if payload.CheckoutUID != "KGM-ORDER-ID" || payload.PaymentUID != "KGM-ORDER-ID" {
		t.Fatalf("expected mobile order id to become checkout/payment uid, got %q / %q", payload.CheckoutUID, payload.PaymentUID)
	}
	if payload.AddressID != "7" {
		t.Fatalf("expected address id 7, got %q", payload.AddressID)
	}
	if payload.Customer.Email != "customer@example.com" || payload.Customer.Phone != "5555555555" {
		t.Fatalf("expected normalized customer contact, got %q / %q", payload.Customer.Email, payload.Customer.Phone)
	}
	if len(payload.Items) != 1 || payload.Items[0].ProductID != 42 || payload.Items[0].Quantity != 1 {
		t.Fatalf("expected one checkout item from PayTR basket, got %#v", payload.Items)
	}
}

func TestCheckoutRequestRejectsInvalidPayTRMobileJSON(t *testing.T) {
	body := `{
		"orderId":"KGM-ORDER-ID",
		"userId":"5",
		"email":"",
		"phone":"05555555555",
		"amountKurus":129950,
		"currency":"TL",
		"addressId":"7",
		"basket":[{"productId":"42","name":"Product Name","quantity":1,"unitPriceKurus":129950}]
	}`

	req := httptest.NewRequest("POST", "/api/v1/c", strings.NewReader(body))
	if _, err := parseCheckoutBody(req); err == nil {
		t.Fatal("expected invalid PayTR mobile JSON to fail")
	}
}

func TestCheckoutResponseIncludesPaymentID(t *testing.T) {
	raw, err := json.Marshal(CheckoutResponse{
		CheckoutURL: "https://www.paytr.com/odeme/guvenli/token",
		IframeSrc:   "https://www.paytr.com/odeme/guvenli/token",
		PaymentID:   42,
	})
	if err != nil {
		t.Fatalf("marshal CheckoutResponse: %v", err)
	}
	if !strings.Contains(string(raw), `"payment_id":42`) {
		t.Fatalf("expected payment_id in response JSON, got %s", string(raw))
	}
}

func TestCheckoutPaymentFlowNormalization(t *testing.T) {
	tests := map[string]string{
		"":                 "iframe",
		"iframe":           "iframe",
		"direct":           "direct",
		"card":             "direct",
		"cod":              "cash_on_delivery",
		"cash":             "cash_on_delivery",
		"cash_on_delivery": "cash_on_delivery",
		"kapida_odeme":     "cash_on_delivery",
	}
	for input, expected := range tests {
		if got := checkoutPaymentFlow(input); got != expected {
			t.Fatalf("checkoutPaymentFlow(%q) = %q, want %q", input, got, expected)
		}
	}
}

func TestKaracabeyDeliveryNormalization(t *testing.T) {
	if !isKaracabeyDelivery("BURSA", "Karacabey") {
		t.Fatal("expected Bursa / Karacabey to be local delivery")
	}
	if isKaracabeyDelivery("Balıkesir", "Bandırma") {
		t.Fatal("expected non-Karacabey address to be non-local")
	}
}
