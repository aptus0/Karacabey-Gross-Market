package api

import (
	"database/sql"
	"fmt"
	"net/http"
	"strings"
	"time"
)

type ProductReviewSubmitRequest struct {
	Rating     int    `json:"rating"`
	Title      string `json:"title"`
	Body       string `json:"body"`
	AuthorName string `json:"author_name"`
}

func (app *App) handleProductReviewCreate(w http.ResponseWriter, r *http.Request) {
	user, ok := app.requireUser(w, r)
	if !ok {
		return
	}
	ctx, cancel := withTimeout(r, 5*time.Second)
	defer cancel()

	product, err := app.productBySlug(ctx, r.PathValue("slug"))
	if err != nil {
		app.handleErr(w, r, err)
		return
	}

	var body ProductReviewSubmitRequest
	if err := parseJSON(r, &body); err != nil {
		app.handleErr(w, r, fmt.Errorf("%w: Yorum bilgileri okunamadı.", ErrBadRequest))
		return
	}
	body.Title = strings.TrimSpace(body.Title)
	body.Body = strings.TrimSpace(body.Body)
	body.AuthorName = strings.TrimSpace(firstNonEmpty(body.AuthorName, user.Name, "Müşteri"))
	if body.Rating < 1 || body.Rating > 5 {
		app.handleErr(w, r, fmt.Errorf("%w: Puan 1 ile 5 arasında olmalıdır.", ErrBadRequest))
		return
	}
	if len([]rune(body.Body)) < 3 {
		app.handleErr(w, r, fmt.Errorf("%w: Yorumunuz en az 3 karakter olmalıdır.", ErrBadRequest))
		return
	}
	if len([]rune(body.Title)) > 120 || len([]rune(body.Body)) > 2000 {
		app.handleErr(w, r, fmt.Errorf("%w: Yorum alanları çok uzun.", ErrBadRequest))
		return
	}

	_, err = app.db.ExecContext(ctx, `INSERT INTO product_reviews
		(tenant_id,product_id,user_id,author_name,rating,title,body,is_approved,created_at,updated_at)
		VALUES (?,?,?,?,?,?,?,0,NOW(),NOW())`,
		app.cfg.TenantID, product.ID, user.ID, body.AuthorName, body.Rating, nullableTrimmed(body.Title), nullableTrimmed(body.Body),
	)
	if err != nil {
		if isMissingTableError(err) {
			app.handleErr(w, r, fmt.Errorf("%w: Yorum altyapısı henüz aktif değil.", ErrBadRequest))
			return
		}
		app.handleErr(w, r, err)
		return
	}

	writeData(w, http.StatusCreated, map[string]any{
		"status":  "pending",
		"message": "Yorumunuz alındı. Moderasyon sonrası yayınlanacaktır.",
	})
}

func nullableTrimmed(value string) any {
	value = strings.TrimSpace(value)
	if value == "" {
		return nil
	}
	return value
}

type ProductReviewResponse struct {
	ID         int64     `json:"id"`
	AuthorName string    `json:"author_name"`
	Rating     int       `json:"rating"`
	Title      *string   `json:"title,omitempty"`
	Body       *string   `json:"body,omitempty"`
	CreatedAt  time.Time `json:"created_at"`
}

func (app *App) handleProductRelated(w http.ResponseWriter, r *http.Request) {
	ctx, cancel := withTimeout(r, 5*time.Second)
	defer cancel()

	product, err := app.productBySlug(ctx, r.PathValue("slug"))
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	if len(product.Categories) == 0 {
		writeData(w, http.StatusOK, []Product{})
		return
	}

	page, err := app.listProducts(ctx, ProductFilter{Category: product.Categories[0].Slug, Page: 1, PerPage: 12})
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	related := make([]Product, 0, len(page.Data))
	for _, item := range page.Data {
		if item.ID != product.ID {
			related = append(related, item)
		}
	}
	writeData(w, http.StatusOK, related)
}

func (app *App) handleProductReviews(w http.ResponseWriter, r *http.Request) {
	ctx, cancel := withTimeout(r, 5*time.Second)
	defer cancel()

	product, err := app.productBySlug(ctx, r.PathValue("slug"))
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	rows, err := app.db.QueryContext(ctx, `SELECT id,author_name,rating,title,body,created_at
		FROM product_reviews
		WHERE tenant_id=? AND product_id=? AND is_approved=1
		ORDER BY created_at DESC,id DESC LIMIT 100`, app.cfg.TenantID, product.ID)
	if err != nil {
		if isMissingTableError(err) {
			writeData(w, http.StatusOK, map[string]any{"reviews": []ProductReviewResponse{}, "average_rating": 0, "review_count": 0})
			return
		}
		app.handleErr(w, r, err)
		return
	}
	defer rows.Close()

	reviews := []ProductReviewResponse{}
	var ratingTotal int
	for rows.Next() {
		var review ProductReviewResponse
		var title, body sql.NullString
		if err := rows.Scan(&review.ID, &review.AuthorName, &review.Rating, &title, &body, &review.CreatedAt); err != nil {
			app.handleErr(w, r, err)
			return
		}
		review.Title = ptrString(title)
		review.Body = ptrString(body)
		ratingTotal += review.Rating
		reviews = append(reviews, review)
	}
	average := 0.0
	if len(reviews) > 0 {
		average = float64(ratingTotal) / float64(len(reviews))
	}
	writeData(w, http.StatusOK, map[string]any{"reviews": reviews, "average_rating": average, "review_count": len(reviews)})
}

func isMissingTableError(err error) bool {
	return strings.Contains(strings.ToLower(err.Error()), "doesn't exist") ||
		strings.Contains(strings.ToLower(err.Error()), "no such table")
}
