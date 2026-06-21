package api

import (
	"bytes"
	"context"
	"crypto/hmac"
	"crypto/sha256"
	"encoding/base64"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"net/http"
	"net/url"
	"strconv"
	"strings"
	"time"
)

type PayTRClient struct {
	cfg    PayTRConfig
	client *http.Client
}

type PayTRTokenResponse struct {
	Status string `json:"status"`
	Token  string `json:"token"`
	Reason string `json:"reason"`
}

type PayTRLinkResponse struct {
	Status string `json:"status"`
	ID     string `json:"id"`
	Link   string `json:"link"`
	Reason string `json:"reason"`
	ErrMsg string `json:"err_msg"`
}

func NewPayTRClient(cfg PayTRConfig) *PayTRClient {
	return &PayTRClient{cfg: cfg, client: &http.Client{Timeout: 20 * time.Second}}
}

func (p *PayTRClient) configured() bool {
	return p.cfg.MerchantID != "" && p.cfg.MerchantKey != "" && p.cfg.MerchantSalt != ""
}

func (p *PayTRClient) GetIframeToken(ctx context.Context, order OrderRecord, userIP string) (string, string, string, error) {
	if !p.configured() {
		return "", "", "PayTR ayarları eksik.", ErrPaymentDown
	}
	basketRaw, err := json.Marshal(orderPayTRBasket(order.Items))
	if err != nil {
		return "", "", "Sepet hazırlanamadı.", err
	}
	basket := base64.StdEncoding.EncodeToString(basketRaw)
	amount := strconv.FormatInt(order.TotalCents, 10)
	testMode := boolAs01(p.cfg.TestMode)
	debug := boolAs01(p.cfg.Debug)
	noInstallment := "0"
	maxInstallment := "0"
	hashStr := p.cfg.MerchantID + userIP + order.MerchantOID + order.CustomerEmail + amount + basket + noInstallment + maxInstallment + p.cfg.Currency + testMode
	form := url.Values{}
	form.Set("merchant_id", p.cfg.MerchantID)
	form.Set("user_ip", userIP)
	form.Set("merchant_oid", order.MerchantOID)
	form.Set("email", order.CustomerEmail)
	form.Set("payment_amount", amount)
	form.Set("paytr_token", p.hmac(hashStr+p.cfg.MerchantSalt))
	form.Set("user_basket", basket)
	form.Set("debug_on", debug)
	form.Set("no_installment", noInstallment)
	form.Set("max_installment", maxInstallment)
	form.Set("user_name", order.CustomerName)
	form.Set("user_address", order.ShippingAddress)
	form.Set("user_phone", order.CustomerPhone)
	form.Set("merchant_ok_url", p.cfg.OKURL)
	form.Set("merchant_fail_url", p.cfg.FailURL)
	form.Set("timeout_limit", strconv.Itoa(p.cfg.TimeoutLimit))
	form.Set("currency", p.cfg.Currency)
	form.Set("test_mode", testMode)

	req, err := http.NewRequestWithContext(ctx, http.MethodPost, p.cfg.TokenURL, bytes.NewBufferString(form.Encode()))
	if err != nil {
		return "", "", "Ödeme isteği hazırlanamadı.", err
	}
	req.Header.Set("Content-Type", "application/x-www-form-urlencoded")
	req.Header.Set("Accept", "application/json")
	res, err := p.client.Do(req)
	if err != nil {
		return "", "", "PayTR bağlantı hatası.", fmt.Errorf("%w: %v", ErrPaymentDown, err)
	}
	defer res.Body.Close()
	body, _ := io.ReadAll(io.LimitReader(res.Body, 1<<20))
	if res.StatusCode >= 500 {
		return "", "", string(body), ErrPaymentDown
	}
	var payload PayTRTokenResponse
	if err := json.Unmarshal(body, &payload); err != nil {
		return "", "", "PayTR geçersiz yanıt döndürdü.", err
	}
	if payload.Status != "success" || payload.Token == "" {
		return "", "", payload.Reason, ErrPaymentDown
	}
	iframeURL := strings.TrimRight(p.cfg.IframeURL, "/") + "/" + payload.Token
	return payload.Token, iframeURL, "", nil
}

func (p *PayTRClient) DirectPaymentParams(order OrderRecord, userIP string) (*PayTRDirectPayment, string, error) {
	if !p.configured() {
		return nil, "PayTR ayarları eksik.", ErrPaymentDown
	}
	basketRaw, err := json.Marshal(orderPayTRBasket(order.Items))
	if err != nil {
		return nil, "Sepet hazırlanamadı.", err
	}

	amount := fmt.Sprintf("%.2f", float64(order.TotalCents)/100)
	paymentType := "card"
	installmentCount := "0"
	testMode := boolAs01(p.cfg.TestMode)
	non3D := "0"
	currency := firstNonEmpty(p.cfg.Currency, "TL")
	hashStr := p.cfg.MerchantID + userIP + order.MerchantOID + order.CustomerEmail + amount + paymentType + installmentCount + currency + testMode + non3D

	return &PayTRDirectPayment{
		PostURL: p.cfg.DirectURL,
		Fields: map[string]string{
			"merchant_id":       p.cfg.MerchantID,
			"user_ip":           userIP,
			"merchant_oid":      order.MerchantOID,
			"email":             order.CustomerEmail,
			"payment_type":      paymentType,
			"payment_amount":    amount,
			"installment_count": installmentCount,
			"currency":          currency,
			"test_mode":         testMode,
			"non_3d":            non3D,
			"merchant_ok_url":   p.cfg.OKURL,
			"merchant_fail_url": p.cfg.FailURL,
			"user_name":         order.CustomerName,
			"user_address":      order.ShippingAddress,
			"user_phone":        order.CustomerPhone,
			"user_basket":       string(basketRaw),
			"debug_on":          boolAs01(p.cfg.Debug),
			"client_lang":       "tr",
			"paytr_token":       p.hmac(hashStr + p.cfg.MerchantSalt),
		},
	}, "", nil
}

func (p *PayTRClient) CreatePaymentLink(ctx context.Context, order OrderRecord) (string, string, string, error) {
	if !p.configured() {
		return "", "", "PayTR ayarları eksik.", ErrPaymentDown
	}
	name := strings.TrimSpace("Karacabey Gross Market Siparis " + order.MerchantOID)
	if len(name) > 200 {
		name = name[:200]
	}
	price := strconv.FormatInt(order.TotalCents, 10)
	currency := firstNonEmpty(p.cfg.Currency, "TL")
	maxInstallment := "12"
	linkType := "product"
	lang := "tr"
	minCount := "1"
	required := name + price + currency + maxInstallment + linkType + lang + minCount

	form := url.Values{}
	form.Set("merchant_id", p.cfg.MerchantID)
	form.Set("name", name)
	form.Set("price", price)
	form.Set("currency", currency)
	form.Set("max_installment", maxInstallment)
	form.Set("link_type", linkType)
	form.Set("lang", lang)
	form.Set("min_count", minCount)
	form.Set("max_count", "1")
	form.Set("callback_link", p.cfg.CallbackURL)
	form.Set("callback_id", order.MerchantOID)
	form.Set("debug_on", boolAs01(p.cfg.Debug))
	form.Set("paytr_token", p.hmac(required+p.cfg.MerchantSalt))
	form.Set("user_name", order.CustomerName)

	req, err := http.NewRequestWithContext(ctx, http.MethodPost, p.cfg.LinkCreateURL, bytes.NewBufferString(form.Encode()))
	if err != nil {
		return "", "", "Ödeme linki isteği hazırlanamadı.", err
	}
	req.Header.Set("Content-Type", "application/x-www-form-urlencoded")
	req.Header.Set("Accept", "application/json")
	res, err := p.client.Do(req)
	if err != nil {
		return "", "", "PayTR Link API bağlantı hatası.", fmt.Errorf("%w: %v", ErrPaymentDown, err)
	}
	defer res.Body.Close()
	body, _ := io.ReadAll(io.LimitReader(res.Body, 1<<20))
	if res.StatusCode >= 500 {
		return "", "", string(body), ErrPaymentDown
	}
	var payload PayTRLinkResponse
	if err := json.Unmarshal(body, &payload); err != nil {
		return "", "", "PayTR Link API geçersiz yanıt döndürdü.", err
	}
	if payload.Status != "success" || payload.Link == "" {
		reason := firstNonEmpty(payload.Reason, payload.ErrMsg, "PayTR Link API ödeme linki oluşturamadı.")
		return "", "", reason, ErrPaymentDown
	}
	return payload.ID, payload.Link, "", nil
}

func (p *PayTRClient) VerifyCallback(values url.Values) bool {
	if !p.configured() {
		return false
	}
	if values.Get("id") != "" {
		return p.verifyLinkCallback(values)
	}
	merchantOID := values.Get("merchant_oid")
	status := values.Get("status")
	totalAmount := values.Get("total_amount")
	hash := values.Get("hash")
	if merchantOID == "" || status == "" || totalAmount == "" || hash == "" {
		return false
	}
	expected := p.hmac(merchantOID + p.cfg.MerchantSalt + status + totalAmount)
	return hmac.Equal([]byte(expected), []byte(hash))
}

func (p *PayTRClient) verifyLinkCallback(values url.Values) bool {
	id := values.Get("id")
	merchantOID := values.Get("merchant_oid")
	status := values.Get("status")
	totalAmount := values.Get("total_amount")
	hash := values.Get("hash")
	if id == "" || merchantOID == "" || status == "" || totalAmount == "" || hash == "" {
		return false
	}
	expected := p.hmac(id + merchantOID + p.cfg.MerchantSalt + status + totalAmount)
	return hmac.Equal([]byte(expected), []byte(hash))
}

func (p *PayTRClient) hmac(value string) string {
	mac := hmac.New(sha256.New, []byte(p.cfg.MerchantKey))
	mac.Write([]byte(value))
	return base64.StdEncoding.EncodeToString(mac.Sum(nil))
}

func boolAs01(value bool) string {
	if value {
		return "1"
	}
	return "0"
}

func orderPayTRBasket(items []OrderItemRecord) [][]any {
	basket := make([][]any, 0, len(items))
	for _, item := range items {
		basket = append(basket, []any{item.Name, fmt.Sprintf("%.2f", float64(item.UnitPriceCents)/100), item.Quantity})
	}
	return basket
}

func formToMap(values url.Values) map[string]any {
	out := map[string]any{}
	for k, v := range values {
		if len(v) > 0 {
			out[k] = v[0]
		}
	}
	return out
}

func sanitizePayTR(values url.Values) map[string]any {
	skip := map[string]struct{}{"card_number": {}, "cc_owner": {}, "cvv": {}, "expiry_month": {}, "expiry_year": {}}
	out := map[string]any{}
	for k, v := range values {
		if _, ok := skip[k]; ok {
			continue
		}
		if len(v) > 0 {
			out[k] = v[0]
		}
	}
	return out
}

func paytrAmount(values url.Values) (int64, error) {
	candidates := []string{values.Get("payment_amount"), values.Get("total_amount")}
	for _, raw := range candidates {
		raw = strings.TrimSpace(raw)
		if raw == "" {
			continue
		}
		parsed, err := strconv.ParseInt(raw, 10, 64)
		if err == nil {
			return parsed, nil
		}
	}
	return 0, errors.New("paytr amount missing")
}
