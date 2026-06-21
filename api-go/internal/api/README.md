# internal/api

Bu paket KGM public/mobile API uygulamasının iç kodlarını içerir.

Dosya grupları:

- `server.go`: uygulama bootstrap, DB bağlantısı, graceful shutdown
- `routes.go`: HTTP route kayıtları
- `middleware.go`: güvenlik, CORS, gzip, rate limit, request id, recover
- `response.go`: JSON response ve hata formatı
- `config.go`: env/config/production validation
- `db_*.go`: MySQL veri erişim yardımcıları
- `handlers_*.go`: domain endpoint handlerları
- `security_action.go`: action token güvenliği
- `metrics.go`: runtime request/latency gözlemi
- `*_test.go`: API unit/handler testleri

Yeni domain eklendiğinde önerilen isimlendirme:

```text
handlers_<domain>.go
db_<domain>.go
<domain>_test.go
```
