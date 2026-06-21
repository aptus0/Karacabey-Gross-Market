package main

import (
	"context"
	"log"
	"net/http"
	"os"
	"time"

	"kgm-mail-service/internal/config"
	"kgm-mail-service/internal/httpapi"
	"kgm-mail-service/internal/maildir"
	"kgm-mail-service/internal/mailer"
	"kgm-mail-service/internal/store"
)

func main() {
	cfg := config.Load()
	if err := os.MkdirAll(cfg.DataDir, 0750); err != nil {
		log.Fatal(err)
	}
	st, err := store.New(cfg.DataDir)
	if err != nil {
		log.Fatal(err)
	}
	if cfg.MaildirPollEnabled {
		p := maildir.Poller{Root: cfg.MaildirRoot, Every: time.Duration(cfg.MaildirIntervalSecs) * time.Second, Store: st, MaxBytes: 10 << 20}
		go p.Run(context.Background())
		log.Printf("maildir poller enabled root=%s interval=%ds", cfg.MaildirRoot, cfg.MaildirIntervalSecs)
	}
	api := httpapi.New(cfg, st, mailer.New(cfg))
	srv := &http.Server{Addr: cfg.Addr, Handler: api.Routes(), ReadHeaderTimeout: 5 * time.Second}
	log.Printf("kgm mail service phase2 listening on %s env=%s", cfg.Addr, cfg.Env)
	if err := srv.ListenAndServe(); err != nil && err != http.ErrServerClosed {
		log.Fatal(err)
	}
}
