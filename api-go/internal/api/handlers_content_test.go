package api

import (
	"database/sql"
	"testing"
)

func TestContentChannelFilter(t *testing.T) {
	tests := map[string]string{
		"mobile": " AND show_on_mobile=1",
		"web":    " AND show_on_web=1",
		"WEB":    " AND show_on_web=1",
		"":       "",
		"other":  "",
	}

	for input, expected := range tests {
		if actual := contentChannelFilter(input); actual != expected {
			t.Fatalf("contentChannelFilter(%q) = %q, want %q", input, actual, expected)
		}
	}
}

func TestCampaignImageURLPrefersStoredImage(t *testing.T) {
	app := &App{cfg: Config{CDNURL: "https://cdn.example.com"}}
	stored := sql.NullString{String: "campaigns/welcome.webp", Valid: true}
	external := sql.NullString{String: "https://images.example.com/old.jpg", Valid: true}

	if actual := campaignImageURL(app, stored, external); actual != "https://cdn.example.com/storage/campaigns/welcome.webp" {
		t.Fatalf("campaignImageURL() = %#v", actual)
	}
}

func TestCampaignDiscountLabel(t *testing.T) {
	if actual := campaignDiscountLabel("percent", 15); actual != "%15 İndirim" {
		t.Fatalf("campaignDiscountLabel(percent) = %q", actual)
	}
	if actual := campaignDiscountLabel("fixed", 2500); actual != "₺25.00 İndirim" {
		t.Fatalf("campaignDiscountLabel(fixed) = %q", actual)
	}
}
