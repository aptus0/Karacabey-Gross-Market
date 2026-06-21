package config

import (
	"os"
	"strconv"
)

type Config struct {
	Env                 string
	Addr                string
	PublicURL           string
	DataDir             string
	AdminToken          string
	InboundToken        string
	DefaultFromName     string
	DefaultFromEmail    string
	SupportEmail        string
	OrderEmail          string
	NoReplyEmail        string
	SMTPHost            string
	SMTPPort            string
	SMTPTLSServerName   string
	SMTPTLSInsecure     bool
	SMTPUsername        string
	SMTPPassword        string
	SMTPAuth            bool
	SMTPDisabled        bool
	MaxJSONBodyBytes    int64
	RateLimitPerMin     int
	MaildirPollEnabled  bool
	MaildirRoot         string
	MaildirIntervalSecs int
}

func Load() Config {
	return Config{
		Env:                 getenv("APP_ENV", "development"),
		Addr:                getenv("APP_ADDR", ":8088"),
		PublicURL:           getenv("APP_PUBLIC_URL", "http://127.0.0.1:8088"),
		DataDir:             getenv("APP_DATA_DIR", "./data"),
		AdminToken:          getenv("MAIL_ADMIN_TOKEN", "dev-token-change-me"),
		InboundToken:        getenv("MAIL_INBOUND_TOKEN", "dev-inbound-token-change-me"),
		DefaultFromName:     getenv("MAIL_DEFAULT_FROM_NAME", "Karacabey Gross Market"),
		DefaultFromEmail:    getenv("MAIL_DEFAULT_FROM_EMAIL", "noreply@karacabeygrossmarket.com"),
		SupportEmail:        getenv("MAIL_SUPPORT_EMAIL", "destek@karacabeygrossmarket.com"),
		OrderEmail:          getenv("MAIL_ORDER_EMAIL", "siparis@karacabeygrossmarket.com"),
		NoReplyEmail:        getenv("MAIL_NOREPLY_EMAIL", "noreply@karacabeygrossmarket.com"),
		SMTPHost:            getenv("SMTP_HOST", "127.0.0.1"),
		SMTPPort:            getenv("SMTP_PORT", "25"),
		SMTPTLSServerName:   getenv("SMTP_TLS_SERVER_NAME", getenv("SMTP_HOST", "127.0.0.1")),
		SMTPTLSInsecure:     getenvBool("SMTP_TLS_INSECURE_SKIP_VERIFY", false),
		SMTPUsername:        getenv("SMTP_USERNAME", ""),
		SMTPPassword:        getenv("SMTP_PASSWORD", ""),
		SMTPAuth:            getenvBool("SMTP_AUTH", false),
		SMTPDisabled:        getenvBool("SMTP_DISABLED", false),
		MaxJSONBodyBytes:    getenvInt64("MAX_JSON_BODY_BYTES", 1048576),
		RateLimitPerMin:     getenvInt("RATE_LIMIT_PER_MINUTE", 120),
		MaildirPollEnabled:  getenvBool("MAILDIR_POLL_ENABLED", false),
		MaildirRoot:         getenv("MAILDIR_ROOT", "/maildata"),
		MaildirIntervalSecs: getenvInt("MAILDIR_INTERVAL_SECONDS", 20),
	}
}

func getenv(key, fallback string) string {
	if v := os.Getenv(key); v != "" {
		return v
	}
	return fallback
}
func getenvBool(key string, fallback bool) bool {
	v := os.Getenv(key)
	if v == "" {
		return fallback
	}
	b, err := strconv.ParseBool(v)
	if err != nil {
		return fallback
	}
	return b
}
func getenvInt(key string, fallback int) int {
	v := os.Getenv(key)
	if v == "" {
		return fallback
	}
	n, err := strconv.Atoi(v)
	if err != nil {
		return fallback
	}
	return n
}
func getenvInt64(key string, fallback int64) int64 {
	v := os.Getenv(key)
	if v == "" {
		return fallback
	}
	n, err := strconv.ParseInt(v, 10, 64)
	if err != nil {
		return fallback
	}
	return n
}
