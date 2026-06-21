package api

import (
	"bytes"
	"context"
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"strings"
	"time"
)

type VisualSearchRequest struct {
	ImageBase64 string `json:"image_base64"`
	MimeType    string `json:"mime_type"`
	Barcode     string `json:"barcode"`
}

type VisualSearchResponse struct {
	Query    string    `json:"query"`
	Labels   []string  `json:"labels"`
	Products []Product `json:"products"`
	Message  string    `json:"message,omitempty"`
}

type geminiVisualIntent struct {
	Query    string   `json:"query"`
	Labels   []string `json:"labels"`
	Barcodes []string `json:"barcodes"`
}

func (app *App) handleProductVisualSearch(w http.ResponseWriter, r *http.Request) {
	ctx, cancel := withTimeout(r, 20*time.Second)
	defer cancel()

	var req VisualSearchRequest
	if err := parseJSONLimit(r, &req, 28<<20); err != nil {
		writeError(w, r, http.StatusUnprocessableEntity, "Geçerli görsel arama verisi gönderin.")
		return
	}

	if barcode := strings.TrimSpace(req.Barcode); barcode != "" {
		products, err := app.productsForVisualTerms(ctx, []string{barcode}, 8)
		if err != nil {
			app.handleErr(w, r, err)
			return
		}
		writeData(w, http.StatusOK, VisualSearchResponse{Query: barcode, Labels: []string{"barkod"}, Products: products})
		return
	}

	if app.cfg.GeminiAPIKey == "" {
		writeError(w, r, http.StatusServiceUnavailable, "Gemini AI görsel arama yapılandırılmamış.")
		return
	}
	imageBase64 := strings.TrimSpace(req.ImageBase64)
	if imageBase64 == "" {
		writeError(w, r, http.StatusUnprocessableEntity, "Ürün bulmak için görsel veya barkod gönderin.")
		return
	}
	if len(imageBase64) > 26*1024*1024 {
		writeError(w, r, http.StatusRequestEntityTooLarge, "Görsel çok büyük. Lütfen daha küçük bir fotoğraf gönderin.")
		return
	}

	intent, err := app.geminiVisualIntent(ctx, imageBase64, req.MimeType)
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	terms := append([]string{}, intent.Barcodes...)
	if intent.Query != "" {
		terms = append(terms, intent.Query)
	}
	terms = append(terms, intent.Labels...)
	products, err := app.productsForVisualTerms(ctx, terms, 12)
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	writeData(w, http.StatusOK, VisualSearchResponse{
		Query:    intent.Query,
		Labels:   uniqueStrings(intent.Labels, 8),
		Products: products,
	})
}

func (app *App) geminiVisualIntent(ctx context.Context, imageBase64, mimeType string) (geminiVisualIntent, error) {
	mimeType = strings.TrimSpace(mimeType)
	if !strings.HasPrefix(mimeType, "image/") {
		mimeType = "image/jpeg"
	}
	model := strings.TrimSpace(app.cfg.GeminiModel)
	if model == "" {
		model = "gemini-2.5-flash"
	}
	prompt := `Karacabey Gross Market mobil uygulaması için bu görseldeki market ürününü tanı.
Sadece JSON döndür: {"query":"en olası ürün arama metni","labels":["kısa ürün etiketleri"],"barcodes":["okunabilen barkodlar"]}.
Türkçe ürün adları kullan. Emin değilsen geniş kategori ve marka ipuçlarını yaz.`

	payload := map[string]any{
		"contents": []map[string]any{{
			"parts": []map[string]any{
				{"inline_data": map[string]string{"mime_type": mimeType, "data": imageBase64}},
				{"text": prompt},
			},
		}},
		"generation_config": map[string]any{
			"temperature":        0.1,
			"response_mime_type": "application/json",
		},
	}
	raw, _ := json.Marshal(payload)
	url := fmt.Sprintf("https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent", model)
	httpReq, err := http.NewRequestWithContext(ctx, http.MethodPost, url, bytes.NewReader(raw))
	if err != nil {
		return geminiVisualIntent{}, err
	}
	httpReq.Header.Set("Content-Type", "application/json")
	httpReq.Header.Set("x-goog-api-key", app.cfg.GeminiAPIKey)

	resp, err := http.DefaultClient.Do(httpReq)
	if err != nil {
		return geminiVisualIntent{}, fmt.Errorf("%w: Gemini AI yanıt vermedi.", ErrUnavailable)
	}
	defer resp.Body.Close()
	body, _ := io.ReadAll(io.LimitReader(resp.Body, 1<<20))
	if resp.StatusCode < 200 || resp.StatusCode >= 300 {
		return geminiVisualIntent{}, fmt.Errorf("%w: Gemini AI görseli işleyemedi.", ErrUnavailable)
	}

	var out struct {
		Candidates []struct {
			Content struct {
				Parts []struct {
					Text string `json:"text"`
				} `json:"parts"`
			} `json:"content"`
		} `json:"candidates"`
	}
	if err := json.Unmarshal(body, &out); err != nil {
		return geminiVisualIntent{}, err
	}
	if len(out.Candidates) == 0 || len(out.Candidates[0].Content.Parts) == 0 {
		return geminiVisualIntent{}, fmt.Errorf("%w: Gemini AI ürün ipucu döndürmedi.", ErrUnavailable)
	}
	return parseGeminiVisualText(out.Candidates[0].Content.Parts[0].Text), nil
}

func parseGeminiVisualText(text string) geminiVisualIntent {
	clean := strings.TrimSpace(text)
	clean = strings.TrimPrefix(clean, "```json")
	clean = strings.TrimPrefix(clean, "```")
	clean = strings.TrimSuffix(clean, "```")
	clean = strings.TrimSpace(clean)
	var intent geminiVisualIntent
	if err := json.Unmarshal([]byte(clean), &intent); err != nil {
		intent.Query = strings.TrimSpace(clean)
	}
	intent.Query = strings.TrimSpace(intent.Query)
	intent.Labels = uniqueStrings(intent.Labels, 8)
	intent.Barcodes = uniqueStrings(intent.Barcodes, 4)
	return intent
}

func (app *App) productsForVisualTerms(ctx context.Context, terms []string, limit int) ([]Product, error) {
	products := make([]Product, 0, limit)
	seen := map[int64]bool{}
	for _, term := range uniqueStrings(terms, 8) {
		term = strings.TrimSpace(term)
		if term == "" {
			continue
		}
		page, err := app.listProducts(ctx, ProductFilter{Query: term, Sort: "newest", Page: 1, PerPage: limit})
		if err != nil {
			return products, err
		}
		for _, product := range page.Data {
			if seen[product.ID] {
				continue
			}
			seen[product.ID] = true
			products = append(products, product)
			if len(products) >= limit {
				return products, nil
			}
		}
	}
	return products, nil
}

func uniqueStrings(values []string, limit int) []string {
	out := make([]string, 0, len(values))
	seen := map[string]bool{}
	for _, value := range values {
		clean := strings.TrimSpace(value)
		if clean == "" {
			continue
		}
		key := strings.ToLower(clean)
		if seen[key] {
			continue
		}
		seen[key] = true
		out = append(out, clean)
		if limit > 0 && len(out) >= limit {
			return out
		}
	}
	return out
}

type ExternalSearchRequest struct {
	Query      string `json:"query"`
	MaxResults int    `json:"max_results"`
}

type ExternalSearchResponse struct {
	Query      string                 `json:"query"`
	Disclaimer string                 `json:"disclaimer"`
	Results    []ExternalSearchResult `json:"results"`
}

type ExternalSearchResult struct {
	ID         string  `json:"id"`
	Title      string  `json:"title"`
	Provider   string  `json:"provider"`
	URL        string  `json:"url"`
	Snippet    *string `json:"snippet,omitempty"`
	ImageURL   *string `json:"image_url,omitempty"`
	PriceLabel *string `json:"price_label,omitempty"`
}

func (app *App) handleExternalSearch(w http.ResponseWriter, r *http.Request) {
	ctx, cancel := withTimeout(r, 12*time.Second)
	defer cancel()

	var req ExternalSearchRequest
	if err := parseJSON(r, &req); err != nil {
		writeError(w, r, http.StatusUnprocessableEntity, "Geçerli arama metni gönderin.")
		return
	}
	query := strings.TrimSpace(req.Query)
	if len(query) < 2 {
		writeError(w, r, http.StatusUnprocessableEntity, "Dış arama için en az 2 karakter yazın.")
		return
	}
	if len(query) > 120 {
		query = query[:120]
	}
	limit := req.MaxResults
	if limit <= 0 || limit > 10 {
		limit = 8
	}

	cacheKey := "external-search:" + strings.ToLower(query)
	if value, ok := app.cache.Get(cacheKey); ok {
		if cached, ok := value.(ExternalSearchResponse); ok {
			writeData(w, http.StatusOK, cached)
			return
		}
	}

	if app.cfg.GeminiAPIKey == "" {
		writeData(w, http.StatusOK, ExternalSearchResponse{
			Query:      query,
			Disclaimer: externalSearchDisclaimer(),
			Results:    []ExternalSearchResult{},
		})
		return
	}

	results, err := app.geminiExternalSearch(ctx, query, limit)
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	response := ExternalSearchResponse{
		Query:      query,
		Disclaimer: externalSearchDisclaimer(),
		Results:    sanitizeExternalResults(results, limit),
	}
	app.cache.Set(cacheKey, response)
	writeData(w, http.StatusOK, response)
}

func externalSearchDisclaimer() string {
	return "Dış market sonuçları yalnızca karşılaştırma amaçlıdır. KGM tarafından satılmaz; fiyat, stok ve görsellerin güncelliği garanti edilmez."
}

func (app *App) geminiExternalSearch(ctx context.Context, query string, limit int) ([]ExternalSearchResult, error) {
	model := strings.TrimSpace(app.cfg.GeminiModel)
	if model == "" {
		model = "gemini-2.5-flash"
	}
	prompt := fmt.Sprintf(`Karacabey Gross Market mobil hızlı sipariş için "%s" ürününü Türkiye'deki güvenilir market/e-ticaret kaynaklarında ara.
Sadece JSON döndür: {"results":[{"title":"ürün adı","provider":"site/market adı","url":"https://...","snippet":"kısa açıklama","image_url":"https://... veya boş","price_label":"fiyat varsa"}]}.
Sonuçlar dış karşılaştırmadır; KGM sepetine eklenemez. En fazla %d sonuç döndür.`, query, limit)

	payload := map[string]any{
		"contents": []map[string]any{{
			"parts": []map[string]any{{"text": prompt}},
		}},
		"tools": []map[string]any{{"google_search": map[string]any{}}},
		"generation_config": map[string]any{
			"temperature":        0.2,
			"response_mime_type": "application/json",
		},
	}
	raw, _ := json.Marshal(payload)
	apiURL := fmt.Sprintf("https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent", model)
	httpReq, err := http.NewRequestWithContext(ctx, http.MethodPost, apiURL, bytes.NewReader(raw))
	if err != nil {
		return nil, err
	}
	httpReq.Header.Set("Content-Type", "application/json")
	httpReq.Header.Set("x-goog-api-key", app.cfg.GeminiAPIKey)

	resp, err := http.DefaultClient.Do(httpReq)
	if err != nil {
		return nil, fmt.Errorf("%w: Dış market araması şu an yanıt vermedi.", ErrUnavailable)
	}
	defer resp.Body.Close()
	body, _ := io.ReadAll(io.LimitReader(resp.Body, 2<<20))
	if resp.StatusCode < 200 || resp.StatusCode >= 300 {
		return nil, fmt.Errorf("%w: Gemini dış arama tamamlanamadı.", ErrUnavailable)
	}

	var out struct {
		Candidates []struct {
			Content struct {
				Parts []struct {
					Text string `json:"text"`
				} `json:"parts"`
			} `json:"content"`
		} `json:"candidates"`
	}
	if err := json.Unmarshal(body, &out); err != nil {
		return nil, err
	}
	if len(out.Candidates) == 0 || len(out.Candidates[0].Content.Parts) == 0 {
		return []ExternalSearchResult{}, nil
	}
	return parseExternalSearchResults(out.Candidates[0].Content.Parts[0].Text), nil
}

func parseExternalSearchResults(text string) []ExternalSearchResult {
	clean := strings.TrimSpace(text)
	clean = strings.TrimPrefix(clean, "```json")
	clean = strings.TrimPrefix(clean, "```")
	clean = strings.TrimSuffix(clean, "```")
	clean = strings.TrimSpace(clean)
	var parsed struct {
		Results []ExternalSearchResult `json:"results"`
	}
	if err := json.Unmarshal([]byte(clean), &parsed); err != nil {
		return []ExternalSearchResult{}
	}
	return parsed.Results
}

func sanitizeExternalResults(results []ExternalSearchResult, limit int) []ExternalSearchResult {
	out := make([]ExternalSearchResult, 0, limit)
	seen := map[string]bool{}
	for i, result := range results {
		result.Title = strings.TrimSpace(result.Title)
		result.Provider = strings.TrimSpace(result.Provider)
		result.URL = strings.TrimSpace(result.URL)
		if result.Title == "" || !strings.HasPrefix(strings.ToLower(result.URL), "https://") {
			continue
		}
		if seen[result.URL] {
			continue
		}
		seen[result.URL] = true
		if result.Provider == "" {
			result.Provider = "Dış kaynak"
		}
		if strings.TrimSpace(result.ID) == "" {
			result.ID = fmt.Sprintf("external-%d", i+1)
		}
		out = append(out, result)
		if len(out) >= limit {
			return out
		}
	}
	return out
}
