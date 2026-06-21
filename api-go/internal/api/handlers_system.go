package api

import (
	"net/http"
	"time"
)

func (app *App) handleSystemStatus(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Cache-Control", "no-store, no-cache, max-age=0, must-revalidate")
	writeData(w, http.StatusOK, map[string]any{
		"maintenance": map[string]any{
			"enabled":    app.cfg.MaintenanceMode,
			"active":     app.cfg.MaintenanceMode,
			"title":      "Kısa bir bakım yapıyoruz",
			"message":    "Karacabey Gross Market deneyimini daha hızlı ve güvenli hale getirmek için kısa süreli bakımdayız.",
			"starts_at":  nil,
			"ends_at":    nil,
			"updated_at": time.Now().UTC().Format(time.RFC3339),
			"channels": map[string]bool{
				"storefront": app.cfg.MaintenanceMode,
				"checkout":   app.cfg.MaintenanceMode,
				"api_writes": app.cfg.MaintenanceMode,
				"mobile":     app.cfg.MaintenanceMode,
			},
			"support": map[string]string{
				"phone":    app.cfg.SupportPhone,
				"email":    app.cfg.SupportEmail,
				"whatsapp": "9065453458663",
			},
		},
	})
}
