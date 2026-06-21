package api

import "testing"

func TestParseGeminiVisualText(t *testing.T) {
	text := "```json\n{\"query\":\"domates 1 kg\",\"labels\":[\"domates\",\"sebze\"],\"barcodes\":[\"8690000000012\"]}\n```"

	intent := parseGeminiVisualText(text)
	if intent.Query != "domates 1 kg" {
		t.Fatalf("expected query, got %q", intent.Query)
	}
	if len(intent.Labels) != 2 || intent.Labels[0] != "domates" {
		t.Fatalf("expected labels, got %#v", intent.Labels)
	}
	if len(intent.Barcodes) != 1 || intent.Barcodes[0] != "8690000000012" {
		t.Fatalf("expected barcode, got %#v", intent.Barcodes)
	}
}

func TestUniqueStringsDedupesAndLimits(t *testing.T) {
	got := uniqueStrings([]string{" domates ", "Domates", "salatalık", "patates"}, 2)
	if len(got) != 2 {
		t.Fatalf("expected 2 values, got %#v", got)
	}
	if got[0] != "domates" || got[1] != "salatalık" {
		t.Fatalf("unexpected unique values: %#v", got)
	}
}
