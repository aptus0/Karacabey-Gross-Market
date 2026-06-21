package api

import (
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"net/http"
)

type ErrorPayload struct {
	Message    string              `json:"message"`
	Code       int                 `json:"code"`
	Status     string              `json:"status"`
	Category   string              `json:"category"`
	ErrorUID   string              `json:"error_uid"`
	RequestUID string              `json:"request_uid,omitempty"`
	TraceID    string              `json:"trace_id,omitempty"`
	Errors     map[string][]string `json:"errors,omitempty"`
}

type httpStatusMeta struct {
	Text     string
	Category string
}

var (
	ErrNotFound     = errors.New("not found")
	ErrUnauthorized = errors.New("unauthorized")
	ErrForbidden    = errors.New("forbidden")
	ErrBadRequest   = errors.New("bad request")
	ErrConflict     = errors.New("conflict")
	ErrUnavailable  = errors.New("service unavailable")
	ErrPaymentDown  = errors.New("payment provider unavailable")
)

var httpStatusCatalog = map[int]httpStatusMeta{
	100: {"Continue", "1xx Bilgilendirme"},
	101: {"Switching Protocols", "1xx Bilgilendirme"},
	102: {"Processing", "1xx Bilgilendirme"},
	103: {"Early Hints", "1xx Bilgilendirme"},
	200: {"OK", "2xx Başarı"},
	201: {"Created", "2xx Başarı"},
	202: {"Accepted", "2xx Başarı"},
	203: {"Non-Authoritative Information", "2xx Başarı"},
	204: {"No Content", "2xx Başarı"},
	205: {"Reset Content", "2xx Başarı"},
	206: {"Partial Content", "2xx Başarı"},
	300: {"Multiple Choices", "3xx Yönlendirme"},
	301: {"Moved Permanently", "3xx Yönlendirme"},
	302: {"Found", "3xx Yönlendirme"},
	303: {"See Other", "3xx Yönlendirme"},
	304: {"Not Modified", "3xx Yönlendirme"},
	307: {"Temporary Redirect", "3xx Yönlendirme"},
	308: {"Permanent Redirect", "3xx Yönlendirme"},
	400: {"Bad Request", "4xx İstemci Hatası"},
	401: {"Unauthorized", "4xx İstemci Hatası"},
	402: {"Payment Required", "4xx İstemci Hatası"},
	403: {"Forbidden", "4xx İstemci Hatası"},
	404: {"Not Found", "4xx İstemci Hatası"},
	405: {"Method Not Allowed", "4xx İstemci Hatası"},
	406: {"Not Acceptable", "4xx İstemci Hatası"},
	407: {"Proxy Authentication Required", "4xx İstemci Hatası"},
	408: {"Request Timeout", "4xx İstemci Hatası"},
	409: {"Conflict", "4xx İstemci Hatası"},
	410: {"Gone", "4xx İstemci Hatası"},
	411: {"Length Required", "4xx İstemci Hatası"},
	412: {"Precondition Failed", "4xx İstemci Hatası"},
	413: {"Payload Too Large", "4xx İstemci Hatası"},
	414: {"URI Too Long", "4xx İstemci Hatası"},
	415: {"Unsupported Media Type", "4xx İstemci Hatası"},
	416: {"Range Not Satisfiable", "4xx İstemci Hatası"},
	417: {"Expectation Failed", "4xx İstemci Hatası"},
	418: {"I'm a teapot", "4xx İstemci Hatası"},
	421: {"Misdirected Request", "4xx İstemci Hatası"},
	422: {"Unprocessable Entity", "4xx İstemci Hatası"},
	423: {"Locked", "4xx İstemci Hatası"},
	424: {"Failed Dependency", "4xx İstemci Hatası"},
	425: {"Too Early", "4xx İstemci Hatası"},
	426: {"Upgrade Required", "4xx İstemci Hatası"},
	428: {"Precondition Required", "4xx İstemci Hatası"},
	429: {"Too Many Requests", "4xx İstemci Hatası"},
	431: {"Request Header Fields Too Large", "4xx İstemci Hatası"},
	451: {"Unavailable For Legal Reasons", "4xx İstemci Hatası"},
	500: {"Internal Server Error", "5xx Sunucu Hatası"},
	501: {"Not Implemented", "5xx Sunucu Hatası"},
	502: {"Bad Gateway", "5xx Sunucu Hatası"},
	503: {"Service Unavailable", "5xx Sunucu Hatası"},
	504: {"Gateway Timeout", "5xx Sunucu Hatası"},
	505: {"HTTP Version Not Supported", "5xx Sunucu Hatası"},
	506: {"Variant Also Negotiates", "5xx Sunucu Hatası"},
	507: {"Insufficient Storage", "5xx Sunucu Hatası"},
	508: {"Loop Detected", "5xx Sunucu Hatası"},
	511: {"Network Authentication Required", "5xx Sunucu Hatası"},
}

func statusMeta(status int) httpStatusMeta {
	if meta, ok := httpStatusCatalog[status]; ok {
		return meta
	}
	if text := http.StatusText(status); text != "" {
		category := "Sistem Durumu"
		switch {
		case status >= 100 && status < 200:
			category = "1xx Bilgilendirme"
		case status >= 200 && status < 300:
			category = "2xx Başarı"
		case status >= 300 && status < 400:
			category = "3xx Yönlendirme"
		case status >= 400 && status < 500:
			category = "4xx İstemci Hatası"
		case status >= 500:
			category = "5xx Sunucu Hatası"
		}
		return httpStatusMeta{Text: text, Category: category}
	}
	return httpStatusMeta{Text: "Internal Server Error", Category: "5xx Sunucu Hatası"}
}

func writeJSON(w http.ResponseWriter, status int, payload any) {
	w.Header().Set("Content-Type", "application/json; charset=utf-8")
	w.WriteHeader(status)
	_ = json.NewEncoder(w).Encode(payload)
}

func writeData(w http.ResponseWriter, status int, data any) {
	writeJSON(w, status, map[string]any{"data": data})
}

func writeError(w http.ResponseWriter, r *http.Request, status int, message string) {
	traceID, _ := r.Context().Value(contextKeyRequestID).(string)
	if traceID == "" {
		traceID = randomHex(12)
	}
	errorUID := "kgm_err_" + randomHex(10)
	meta := statusMeta(status)
	w.Header().Set("X-Request-ID", traceID)
	w.Header().Set("X-Error-UID", errorUID)
	writeJSON(w, status, ErrorPayload{Message: message, Code: status, Status: meta.Text, Category: meta.Category, ErrorUID: errorUID, RequestUID: traceID, TraceID: traceID})
}

func parseJSON(r *http.Request, dst any) error {
	return parseJSONLimit(r, dst, 1<<20)
}

func parseJSONLimit(r *http.Request, dst any, limit int64) error {
	defer r.Body.Close()
	if limit <= 0 {
		limit = 1 << 20
	}
	decoder := json.NewDecoder(io.LimitReader(r.Body, limit+1))
	decoder.DisallowUnknownFields()
	if err := decoder.Decode(dst); err != nil {
		return fmt.Errorf("JSON gövdesi geçersiz: %w", err)
	}
	if err := decoder.Decode(&struct{}{}); err != io.EOF {
		return fmt.Errorf("JSON gövdesi tek bir nesne olmalıdır")
	}
	return nil
}

func mapErrorStatus(err error) (int, string) {
	switch {
	case errors.Is(err, ErrNotFound):
		return http.StatusNotFound, "Kayıt bulunamadı."
	case errors.Is(err, ErrUnauthorized):
		return http.StatusUnauthorized, "Oturum gerekli."
	case errors.Is(err, ErrForbidden):
		return http.StatusForbidden, "Bu işlem için yetkiniz yok."
	case errors.Is(err, ErrBadRequest):
		return http.StatusUnprocessableEntity, err.Error()
	case errors.Is(err, ErrConflict):
		return http.StatusConflict, err.Error()
	case errors.Is(err, ErrUnavailable):
		return http.StatusServiceUnavailable, err.Error()
	case errors.Is(err, ErrPaymentDown):
		return http.StatusBadGateway, "Ödeme sağlayıcısına şu an ulaşılamıyor."
	default:
		return http.StatusInternalServerError, "Beklenmeyen bir hata oluştu."
	}
}
