package api

import (
	"fmt"
	"log/slog"
	"os"
	"strconv"
	"strings"
	"time"
)

type Config struct {
	Env                   string
	HTTPAddr              string
	TenantID              int64
	MySQLDSN              string
	AllowedOrigins        map[string]struct{}
	PublicAPIURL          string
	StorefrontURL         string
	CookieDomain          string
	CookieSecure          bool
	MaxOpenConns          int
	MaxIdleConns          int
	ConnMaxLifetime       time.Duration
	ReadTimeout           time.Duration
	WriteTimeout          time.Duration
	IdleTimeout           time.Duration
	ShutdownTimeout       time.Duration
	CacheTTL              time.Duration
	RedisAddr             string
	RedisPassword         string
	RedisDB               int
	RedisTimeout          time.Duration
	MaxBodyBytes          int64
	RateLimitPerMinute    int
	PaymentLimitPerMin    int
	LoginIPLimit          int
	LoginAccountLimit     int
	LoginLimitWindow      time.Duration
	CDNURL                string
	CatalogCacheMaxAge    int
	CatalogStaleSeconds   int
	AppMinIOSVersion      string
	MaintenanceMode       bool
	InternalAPIToken      string
	SupportEmail          string
	SupportPhone          string
	MailServiceURL        string
	MailAdminToken        string
	PasswordResetTTL      time.Duration
	MobileSyncLimit       int
	ActionTokenSecret     string
	ActionTokenMode       string
	ActionTokenTTL        time.Duration
	MinOrderCents         int64
	FreeShippingCents     int64
	StandardShippingCents int64
	GeminiAPIKey          string
	GeminiModel           string
	PayTR                 PayTRConfig
}

type PayTRConfig struct {
	MerchantID    string
	MerchantKey   string
	MerchantSalt  string
	TestMode      bool
	Debug         bool
	OKURL         string
	FailURL       string
	CallbackURL   string
	Currency      string
	TimeoutLimit  int
	TokenURL      string
	IframeURL     string
	DirectURL     string
	LinkCreateURL string
}

func LoadConfig() Config {
	cfg := Config{
		Env:                   getenv("KGM_ENV", "production"),
		HTTPAddr:              getenv("KGM_HTTP_ADDR", "0.0.0.0:8080"),
		TenantID:              getenvInt64("KGM_TENANT_ID", 1),
		MySQLDSN:              getenv("KGM_MYSQL_DSN", "root:password@tcp(127.0.0.1:3306)/karacabey_gross_market?charset=utf8mb4&parseTime=true&loc=Local"),
		AllowedOrigins:        parseOrigins(getenv("KGM_ALLOWED_ORIGINS", "http://localhost:3000,http://127.0.0.1:3000")),
		PublicAPIURL:          trimURL(getenv("KGM_PUBLIC_API_URL", "http://127.0.0.1:8080")),
		StorefrontURL:         trimURL(getenv("KGM_STOREFRONT_URL", "http://localhost:3000")),
		CookieDomain:          getenv("KGM_COOKIE_DOMAIN", ""),
		CookieSecure:          getenvBool("KGM_COOKIE_SECURE", true),
		MaxOpenConns:          getenvInt("KGM_MAX_OPEN_CONNS", 120),
		MaxIdleConns:          getenvInt("KGM_MAX_IDLE_CONNS", 40),
		ConnMaxLifetime:       getenvDuration("KGM_CONN_MAX_LIFETIME", 5*time.Minute),
		ReadTimeout:           getenvDuration("KGM_READ_TIMEOUT", 8*time.Second),
		WriteTimeout:          getenvDuration("KGM_WRITE_TIMEOUT", 15*time.Second),
		IdleTimeout:           getenvDuration("KGM_IDLE_TIMEOUT", 90*time.Second),
		ShutdownTimeout:       getenvDuration("KGM_SHUTDOWN_TIMEOUT", 20*time.Second),
		CacheTTL:              getenvDuration("KGM_CACHE_TTL", 45*time.Second),
		RedisAddr:             getenv("KGM_REDIS_ADDR", ""),
		RedisPassword:         getenv("KGM_REDIS_PASSWORD", ""),
		RedisDB:               getenvInt("KGM_REDIS_DB", 0),
		RedisTimeout:          getenvDuration("KGM_REDIS_TIMEOUT", 900*time.Millisecond),
		MaxBodyBytes:          getenvInt64("KGM_MAX_BODY_BYTES", 32<<20),
		RateLimitPerMinute:    getenvInt("KGM_RATE_LIMIT_PER_MINUTE", 900),
		PaymentLimitPerMin:    getenvInt("KGM_PAYMENT_RATE_LIMIT_PER_MINUTE", 45),
		LoginIPLimit:          getenvInt("KGM_LOGIN_IP_LIMIT", 20),
		LoginAccountLimit:     getenvInt("KGM_LOGIN_ACCOUNT_LIMIT", 5),
		LoginLimitWindow:      getenvDuration("KGM_LOGIN_LIMIT_WINDOW", 15*time.Minute),
		CDNURL:                trimURL(getenv("KGM_CDN_URL", "")),
		CatalogCacheMaxAge:    getenvInt("KGM_CATALOG_CACHE_MAX_AGE", 60),
		CatalogStaleSeconds:   getenvInt("KGM_CATALOG_STALE_SECONDS", 300),
		AppMinIOSVersion:      getenv("KGM_IOS_MIN_VERSION", "1.0.0"),
		MaintenanceMode:       getenvBool("KGM_MAINTENANCE_MODE", false),
		InternalAPIToken:      getenv("KGM_INTERNAL_API_TOKEN", ""),
		SupportEmail:          getenv("KGM_SUPPORT_EMAIL", "destek@karacabeygrossmarket.com"),
		SupportPhone:          getenv("KGM_SUPPORT_PHONE", ""),
		MailServiceURL:        trimURL(getenv("KGM_MAIL_SERVICE_URL", "")),
		MailAdminToken:        getenv("KGM_MAIL_ADMIN_TOKEN", ""),
		PasswordResetTTL:      getenvDuration("KGM_PASSWORD_RESET_TTL", 30*time.Minute),
		MobileSyncLimit:       getenvInt("KGM_MOBILE_SYNC_LIMIT", 250),
		ActionTokenSecret:     getenv("KGM_ACTION_TOKEN_SECRET", getenv("KGM_INTERNAL_API_TOKEN", "")),
		ActionTokenMode:       getenv("KGM_ACTION_TOKEN_MODE", "report"),
		ActionTokenTTL:        getenvDuration("KGM_ACTION_TOKEN_TTL", 90*time.Second),
		MinOrderCents:         getenvInt64("KGM_MIN_ORDER_CENTS", 35000),
		FreeShippingCents:     getenvInt64("KGM_FREE_SHIPPING_CENTS", 150000),
		StandardShippingCents: getenvInt64("KGM_STANDARD_SHIPPING_CENTS", 9990),
		GeminiAPIKey:          getenv("GEMINI_API_KEY", ""),
		GeminiModel:           getenv("GEMINI_MODEL", "gemini-2.5-flash"),
		PayTR: PayTRConfig{
			MerchantID:    getenv("PAYTR_MERCHANT_ID", ""),
			MerchantKey:   getenv("PAYTR_MERCHANT_KEY", ""),
			MerchantSalt:  getenv("PAYTR_MERCHANT_SALT", ""),
			TestMode:      getenvBool("PAYTR_TEST_MODE", false),
			Debug:         getenvBool("PAYTR_DEBUG", false),
			OKURL:         getenv("PAYTR_OK_URL", "http://localhost:3000/checkout/success"),
			FailURL:       getenv("PAYTR_FAIL_URL", "http://localhost:3000/checkout/fail"),
			CallbackURL:   getenv("PAYTR_CALLBACK_URL", "http://127.0.0.1:8080/api/cb/p"),
			Currency:      getenv("PAYTR_CURRENCY", "TL"),
			TimeoutLimit:  getenvInt("PAYTR_TIMEOUT_LIMIT", 30),
			TokenURL:      getenv("PAYTR_IFRAME_TOKEN_URL", "https://www.paytr.com/odeme/api/get-token"),
			IframeURL:     getenv("PAYTR_IFRAME_URL", "https://www.paytr.com/odeme/guvenli"),
			DirectURL:     getenv("PAYTR_DIRECT_PAYMENT_URL", "https://www.paytr.com/odeme"),
			LinkCreateURL: getenv("PAYTR_LINK_CREATE_URL", "https://www.paytr.com/odeme/api/link/create"),
		},
	}

	if cfg.MySQLDSN == "" {
		slog.Warn("KGM_MYSQL_DSN is empty; API cannot start without database")
	}

	return cfg
}

func (cfg Config) Validate() error {
	if !strings.EqualFold(cfg.Env, "production") {
		return nil
	}

	if cfg.MaxBodyBytes < 1<<20 {
		return fmt.Errorf("KGM_MAX_BODY_BYTES must be at least 1MB")
	}
	if _, hasWildcard := cfg.AllowedOrigins["*"]; hasWildcard {
		return fmt.Errorf("KGM_ALLOWED_ORIGINS cannot contain wildcard '*' in production")
	}
	secret := strings.TrimSpace(cfg.ActionTokenSecret)
	if secret == "" || strings.Contains(strings.ToUpper(secret), "CHANGE_ME") || len(secret) < 32 {
		return fmt.Errorf("KGM_ACTION_TOKEN_SECRET must be a production secret of at least 32 characters")
	}
	if !strings.EqualFold(cfg.ActionTokenMode, "enforce") {
		return fmt.Errorf("KGM_ACTION_TOKEN_MODE must be enforce in production")
	}
	if cfg.MailServiceURL == "" {
		return fmt.Errorf("KGM_MAIL_SERVICE_URL is required in production")
	}
	mailToken := strings.TrimSpace(cfg.MailAdminToken)
	if mailToken == "" || strings.Contains(strings.ToUpper(mailToken), "CHANGE_ME") || len(mailToken) < 24 {
		return fmt.Errorf("KGM_MAIL_ADMIN_TOKEN must be a production secret of at least 24 characters")
	}
	return nil
}

func getenv(key, fallback string) string {
	value := strings.TrimSpace(os.Getenv(key))
	if value == "" {
		return fallback
	}
	return value
}

func getenvInt(key string, fallback int) int {
	value := strings.TrimSpace(os.Getenv(key))
	if value == "" {
		return fallback
	}
	parsed, err := strconv.Atoi(value)
	if err != nil {
		return fallback
	}
	return parsed
}

func getenvInt64(key string, fallback int64) int64 {
	value := strings.TrimSpace(os.Getenv(key))
	if value == "" {
		return fallback
	}
	parsed, err := strconv.ParseInt(value, 10, 64)
	if err != nil {
		return fallback
	}
	return parsed
}

func getenvBool(key string, fallback bool) bool {
	value := strings.ToLower(strings.TrimSpace(os.Getenv(key)))
	if value == "" {
		return fallback
	}
	return value == "1" || value == "true" || value == "yes" || value == "on"
}

func getenvDuration(key string, fallback time.Duration) time.Duration {
	value := strings.TrimSpace(os.Getenv(key))
	if value == "" {
		return fallback
	}
	parsed, err := time.ParseDuration(value)
	if err != nil {
		return fallback
	}
	return parsed
}

func parseOrigins(raw string) map[string]struct{} {
	origins := make(map[string]struct{})
	for _, item := range strings.Split(raw, ",") {
		item = trimURL(item)
		if item != "" {
			origins[item] = struct{}{}
		}
	}
	return origins
}

func trimURL(value string) string {
	return strings.TrimRight(strings.TrimSpace(value), "/")
}
