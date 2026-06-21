package api

import (
	"net/http"
	"net/http/httptest"
	"testing"
)

func TestActionNameForProtectedMobileRequests(t *testing.T) {
	tests := []struct {
		method string
		path   string
		want   string
	}{
		{http.MethodPost, "/api/v1/cart/items", "cart.add"},
		{http.MethodPatch, "/api/v1/cart/items/12", "cart.update"},
		{http.MethodDelete, "/api/v1/cart/items/12", "cart.delete"},
		{http.MethodPost, "/api/v1/notifications/15/read", "notification.read"},
		{http.MethodPost, "/api/v1/notifications/read-all", "notification.read_all"},
	}

	for _, test := range tests {
		req := httptest.NewRequest(test.method, test.path, nil)
		if got := actionNameForRequest(req); got != test.want {
			t.Fatalf("%s %s action = %q, want %q", test.method, test.path, got, test.want)
		}
	}
}
