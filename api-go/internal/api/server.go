package api

import (
	"context"
	"database/sql"
	"errors"
	"log/slog"
	"net/http"
	"os"
	"os/signal"
	"syscall"
	"time"

	_ "github.com/go-sql-driver/mysql"
)

type App struct {
	cfg                 Config
	db                  *sql.DB
	cache               *TTLCache
	redis               *RedisClient
	limiter             *RateLimiter
	payLimiter          *RateLimiter
	loginIPLimiter      *RateLimiter
	loginAccountLimiter *RateLimiter
	metrics             *RuntimeMetrics
	paytr               *PayTRClient
}

func Run() {
	logger := slog.New(slog.NewJSONHandler(os.Stdout, &slog.HandlerOptions{Level: slog.LevelInfo}))
	slog.SetDefault(logger)

	cfg := LoadConfig()
	if err := cfg.Validate(); err != nil {
		slog.Error("invalid production configuration", "error", err)
		os.Exit(1)
	}

	db, err := openDB(cfg)
	if err != nil {
		slog.Error("database connection failed", "error", err)
		os.Exit(1)
	}
	defer db.Close()

	app := &App{
		cfg:                 cfg,
		db:                  db,
		cache:               NewTTLCache(cfg.CacheTTL),
		redis:               NewRedisClient(cfg.RedisAddr, cfg.RedisPassword, cfg.RedisDB, cfg.RedisTimeout),
		limiter:             NewRateLimiter(cfg.RateLimitPerMinute, time.Minute),
		payLimiter:          NewRateLimiter(cfg.PaymentLimitPerMin, time.Minute),
		loginIPLimiter:      NewRateLimiter(cfg.LoginIPLimit, cfg.LoginLimitWindow),
		loginAccountLimiter: NewRateLimiter(cfg.LoginAccountLimit, cfg.LoginLimitWindow),
		metrics:             NewRuntimeMetrics(),
		paytr:               NewPayTRClient(cfg.PayTR),
	}

	srv := &http.Server{
		Addr:              cfg.HTTPAddr,
		Handler:           app.routes(),
		ReadHeaderTimeout: 4 * time.Second,
		ReadTimeout:       cfg.ReadTimeout,
		WriteTimeout:      cfg.WriteTimeout,
		IdleTimeout:       cfg.IdleTimeout,
	}

	go func() {
		slog.Info("kgm go api listening", "addr", cfg.HTTPAddr, "env", cfg.Env)
		if err := srv.ListenAndServe(); err != nil && !errors.Is(err, http.ErrServerClosed) {
			slog.Error("http server stopped", "error", err)
			os.Exit(1)
		}
	}()

	quit := make(chan os.Signal, 1)
	signal.Notify(quit, syscall.SIGINT, syscall.SIGTERM)
	<-quit

	ctx, cancel := context.WithTimeout(context.Background(), cfg.ShutdownTimeout)
	defer cancel()
	if err := srv.Shutdown(ctx); err != nil {
		slog.Error("graceful shutdown failed", "error", err)
	}
}

func openDB(cfg Config) (*sql.DB, error) {
	db, err := sql.Open("mysql", cfg.MySQLDSN)
	if err != nil {
		return nil, err
	}
	db.SetMaxOpenConns(cfg.MaxOpenConns)
	db.SetMaxIdleConns(cfg.MaxIdleConns)
	db.SetConnMaxLifetime(cfg.ConnMaxLifetime)
	db.SetConnMaxIdleTime(2 * time.Minute)

	ctx, cancel := context.WithTimeout(context.Background(), 6*time.Second)
	defer cancel()
	if err := db.PingContext(ctx); err != nil {
		return nil, err
	}
	return db, nil
}
