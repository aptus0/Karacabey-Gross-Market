package api

import (
	"net/http/httptest"
	"strings"
	"testing"
)

func TestParseJSONRejectsTrailingValues(t *testing.T) {
	req := httptest.NewRequest("POST", "/", strings.NewReader(`{"name":"kgm"} {"name":"extra"}`))
	var body struct {
		Name string `json:"name"`
	}
	if err := parseJSON(req, &body); err == nil {
		t.Fatal("expected parseJSON to reject trailing JSON values")
	}
}

func TestParseJSONLimitAllowsLargeVisualPayload(t *testing.T) {
	payload := `{"image_base64":"` + strings.Repeat("a", 2<<20) + `","mime_type":"image/jpeg"}`
	req := httptest.NewRequest("POST", "/", strings.NewReader(payload))
	var body VisualSearchRequest
	if err := parseJSONLimit(req, &body, 3<<20); err != nil {
		t.Fatalf("parseJSONLimit returned error: %v", err)
	}
	if len(body.ImageBase64) != 2<<20 {
		t.Fatalf("image length = %d", len(body.ImageBase64))
	}
}
