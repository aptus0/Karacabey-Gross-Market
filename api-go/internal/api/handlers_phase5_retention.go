package api

import (
	"context"
	"fmt"
	"net/http"
	"time"
)

type CustomerLoyaltyReward struct {
	ID             string `json:"id"`
	Title          string `json:"title"`
	Subtitle       string `json:"subtitle"`
	PointsRequired int64  `json:"points_required"`
	IsUnlocked     bool   `json:"is_unlocked"`
}

type CustomerLoyaltySummary struct {
	PointsBalance          int64                   `json:"points_balance"`
	LifetimePoints         int64                   `json:"lifetime_points"`
	Level                  string                  `json:"level"`
	LevelTitle             string                  `json:"level_title"`
	ProgressPercent        float64                 `json:"progress_percent"`
	NextRewardPoints       int64                   `json:"next_reward_points"`
	PurchasesToNextReward  int64                   `json:"purchases_to_next_reward"`
	SpendToNextRewardCents int64                   `json:"spend_to_next_reward_cents"`
	IsVIP                  bool                    `json:"is_vip"`
	AdFree                 bool                    `json:"ad_free"`
	Rewards                []CustomerLoyaltyReward `json:"rewards"`
}

type PersonalizedRecommendationsResponse struct {
	Title    string    `json:"title"`
	Subtitle string    `json:"subtitle"`
	Strategy string    `json:"strategy"`
	Products []Product `json:"products"`
}

// POST /api/v1/products/{slug}/view
// Ürün detay görüntülemelerini hafif şekilde kaydeder. Bu veri Faz 5 öneri
// sistemini besler; hata durumunda kullanıcı deneyimi kesilmez.
func (app *App) handleProductView(w http.ResponseWriter, r *http.Request) {
	ctx, cancel := withTimeout(r, 3*time.Second)
	defer cancel()

	product, err := app.productBySlug(ctx, r.PathValue("slug"))
	if err != nil {
		app.handleErr(w, r, err)
		return
	}

	identity, _ := app.identityFromRequest(ctx, r)
	reqIdentity := requestIdentity(r.Context())
	customerUID := firstNonEmpty(derefString(identity.CustomerUID), reqIdentity.CustomerUID)

	_, err = app.db.ExecContext(ctx, `INSERT INTO customer_product_views
		(tenant_id,product_id,user_id,customer_uid,session_uid,source,viewed_at,created_at,updated_at)
		VALUES (?,?,?,?,?,'ios',NOW(),NOW(),NOW())`,
		app.cfg.TenantID, product.ID, sqlNullInt64Ptr(identity.UserID), nullableString(customerUID), nullableString(reqIdentity.SessionUID))
	if err != nil {
		if isMissingTableError(err) {
			writeData(w, http.StatusAccepted, map[string]any{"status": "queued"})
			return
		}
		app.handleErr(w, r, err)
		return
	}

	writeData(w, http.StatusOK, map[string]any{"status": "recorded"})
}

// GET /api/v1/customer/loyalty
// KGM Puan özeti gerçek ödül ledger'ından ve users bakiyesinden okunur.
// Kural: ödeme alınmış her mobil alışveriş 1 puan.
func (app *App) handleCustomerLoyalty(w http.ResponseWriter, r *http.Request) {
	user, ok := app.requireUser(w, r)
	if !ok {
		return
	}
	ctx, cancel := withTimeout(r, 5*time.Second)
	defer cancel()

	var mobilePurchaseEvents int64
	if err := app.db.QueryRowContext(ctx, `SELECT COUNT(*)
		FROM customer_reward_events
		WHERE tenant_id=? AND user_id=? AND event_type='mobile_purchase'`, app.cfg.TenantID, user.ID).Scan(&mobilePurchaseEvents); err != nil {
		if !isMissingTableError(err) {
			app.handleErr(w, r, err)
			return
		}
	}

	pointsBalance := user.LoyaltyPoints
	lifetimePoints := user.LoyaltyPointsLifetime
	level, levelTitle, currentFloor, nextTarget := loyaltyLevel(lifetimePoints)
	remaining := nextTarget - lifetimePoints
	if remaining < 0 {
		remaining = 0
	}
	rangeSize := nextTarget - currentFloor
	progress := 100.0
	if rangeSize > 0 {
		progress = (float64(lifetimePoints-currentFloor) / float64(rangeSize)) * 100
		if progress < 0 {
			progress = 0
		}
		if progress > 100 {
			progress = 100
		}
	}

	rewards := []CustomerLoyaltyReward{
		{ID: "kgm-50", Title: "50 Puan", Subtitle: "Küçük sepet indirimi", PointsRequired: 50, IsUnlocked: pointsBalance >= 50},
		{ID: "kgm-150", Title: "150 Puan", Subtitle: "Teslimat avantajı", PointsRequired: 150, IsUnlocked: pointsBalance >= 150},
		{ID: "kgm-500", Title: "500 Puan", Subtitle: "Özel müşteri fırsatı", PointsRequired: 500, IsUnlocked: pointsBalance >= 500},
	}

	writeData(w, http.StatusOK, CustomerLoyaltySummary{
		PointsBalance:          pointsBalance,
		LifetimePoints:         lifetimePoints,
		Level:                  level,
		LevelTitle:             fmt.Sprintf("%s · %d mobil alışveriş", levelTitle, mobilePurchaseEvents),
		ProgressPercent:        progress,
		NextRewardPoints:       remaining,
		PurchasesToNextReward:  remaining,
		SpendToNextRewardCents: 0,
		IsVIP:                  user.IsVIP,
		AdFree:                 user.AdFree,
		Rewards:                rewards,
	})
}

func loyaltyLevel(points int64) (level string, title string, floor int64, next int64) {
	switch {
	case points >= 500:
		return "gold", "KGM Gold", 500, 1000
	case points >= 150:
		return "silver", "KGM Silver", 150, 500
	case points >= 50:
		return "plus", "KGM Plus", 50, 150
	default:
		return "starter", "KGM Üyesi", 0, 50
	}
}

// GET /api/v1/customer/recommendations
// Önce son görüntülenen ve satın alınan kategorilere bakar; veri yoksa kampanya
// ve stokta olan ürünlerden güvenli fallback döndürür.
func (app *App) handleCustomerRecommendations(w http.ResponseWriter, r *http.Request) {
	user, ok := app.requireUser(w, r)
	if !ok {
		return
	}
	ctx, cancel := withTimeout(r, 6*time.Second)
	defer cancel()
	limit := parseIntQuery(r, "limit", 12, 1, 36)

	products, strategy, err := app.productsFromRecentViews(ctx, user.ID, user.CustomerUID, limit)
	if err != nil && !isMissingTableError(err) {
		app.handleErr(w, r, err)
		return
	}
	if len(products) < limit {
		purchaseProducts, err := app.productsFromPurchaseCategories(ctx, user.ID, limit-len(products), products)
		if err != nil {
			app.handleErr(w, r, err)
			return
		}
		products = appendUniqueProducts(products, purchaseProducts)
		if len(purchaseProducts) > 0 && strategy == "" {
			strategy = "purchase_categories"
		}
	}
	if len(products) < limit {
		fallback, err := app.productsFallbackForRecommendations(ctx, limit-len(products), products)
		if err != nil {
			app.handleErr(w, r, err)
			return
		}
		products = appendUniqueProducts(products, fallback)
		if strategy == "" {
			strategy = "popular_fallback"
		}
	}
	if strategy == "" {
		strategy = "empty"
	}

	writeData(w, http.StatusOK, PersonalizedRecommendationsResponse{
		Title:    "Sana Özel Reyon",
		Subtitle: recommendationSubtitle(strategy),
		Strategy: strategy,
		Products: products,
	})
}

func recommendationSubtitle(strategy string) string {
	switch strategy {
	case "recent_views":
		return "Son incelediğiniz ürünlere göre seçildi"
	case "purchase_categories":
		return "Önceki alışverişlerinize benzer ürünler"
	case "popular_fallback":
		return "Karacabey Gross Market'te öne çıkan ürünler"
	default:
		return "Alışveriş yaptıkça önerileriniz daha iyi olur"
	}
}

func (app *App) productsFromRecentViews(ctx context.Context, userID int64, customerUID *string, limit int) ([]Product, string, error) {
	args := []any{app.cfg.TenantID, userID}
	customerFilter := ""
	if customerUID != nil && *customerUID != "" {
		customerFilter = " OR v.customer_uid=?"
		args = append(args, *customerUID)
	}
	args = append(args, app.cfg.TenantID, limit)
	rows, err := app.db.QueryContext(ctx, `SELECT p.id,p.name,p.slug,p.description,p.brand,p.barcode,p.price_cents,p.compare_at_price_cents,p.stock_quantity,COALESCE(NULLIF(p.cdn_image_url,''), p.image_url),p.seo
		FROM products p
		JOIN category_product cp ON cp.product_id=p.id
		JOIN (
			SELECT cp2.category_id, MAX(v.viewed_at) last_viewed_at, COUNT(*) view_weight
			FROM customer_product_views v
			JOIN category_product cp2 ON cp2.product_id=v.product_id
			WHERE v.tenant_id=? AND (v.user_id=?`+customerFilter+`)
			GROUP BY cp2.category_id
		) seed ON seed.category_id=cp.category_id
		WHERE p.tenant_id=? AND p.is_active=1 AND p.price_cents>0 AND p.stock_quantity>0
		GROUP BY p.id,p.name,p.slug,p.description,p.brand,p.barcode,p.price_cents,p.compare_at_price_cents,p.stock_quantity,p.cdn_image_url,p.image_url,p.seo
		ORDER BY MAX(seed.view_weight) DESC, MAX(seed.last_viewed_at) DESC, p.id DESC
		LIMIT ?`, args...)
	if err != nil {
		return nil, "", err
	}
	defer rows.Close()
	products, err := scanProducts(rows)
	if err != nil {
		return nil, "", err
	}
	app.applyProductCDN(products)
	if len(products) > 0 {
		_ = app.attachProductCategories(ctx, products)
		return products, "recent_views", nil
	}
	return products, "", nil
}

func (app *App) productsFromPurchaseCategories(ctx context.Context, userID int64, limit int, existing []Product) ([]Product, error) {
	if limit <= 0 {
		return []Product{}, nil
	}
	excludeSQL, excludeArgs := productExcludeSQL(existing)
	args := []any{app.cfg.TenantID, app.cfg.TenantID, userID, app.cfg.TenantID, userID}
	args = append(args, excludeArgs...)
	args = append(args, limit)
	rows, err := app.db.QueryContext(ctx, `SELECT p.id,p.name,p.slug,p.description,p.brand,p.barcode,p.price_cents,p.compare_at_price_cents,p.stock_quantity,COALESCE(NULLIF(p.cdn_image_url,''), p.image_url),p.seo
		FROM products p
		JOIN category_product cp ON cp.product_id=p.id
		WHERE p.tenant_id=? AND p.is_active=1 AND p.price_cents>0 AND p.stock_quantity>0
		  AND cp.category_id IN (
			SELECT DISTINCT cp2.category_id
			FROM category_product cp2
			JOIN order_items oi ON oi.product_id=cp2.product_id
			JOIN orders o ON o.id=oi.order_id
			WHERE o.tenant_id=? AND o.user_id=? AND o.status NOT IN ('cancelled','failed','refunded')
		  )
		  AND p.id NOT IN (
			SELECT DISTINCT oi2.product_id
			FROM order_items oi2
			JOIN orders o2 ON o2.id=oi2.order_id
			WHERE o2.tenant_id=? AND o2.user_id=? AND oi2.product_id IS NOT NULL
		  )`+excludeSQL+`
		GROUP BY p.id,p.name,p.slug,p.description,p.brand,p.barcode,p.price_cents,p.compare_at_price_cents,p.stock_quantity,p.cdn_image_url,p.image_url,p.seo
		ORDER BY p.id DESC
		LIMIT ?`, args...)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	products, err := scanProducts(rows)
	if err != nil {
		return nil, err
	}
	app.applyProductCDN(products)
	if len(products) > 0 {
		_ = app.attachProductCategories(ctx, products)
	}
	return products, nil
}

func (app *App) productsFallbackForRecommendations(ctx context.Context, limit int, existing []Product) ([]Product, error) {
	if limit <= 0 {
		return []Product{}, nil
	}
	excludeSQL, excludeArgs := productExcludeSQL(existing)
	args := append([]any{app.cfg.TenantID}, excludeArgs...)
	args = append(args, limit)
	rows, err := app.db.QueryContext(ctx, `SELECT p.id,p.name,p.slug,p.description,p.brand,p.barcode,p.price_cents,p.compare_at_price_cents,p.stock_quantity,COALESCE(NULLIF(p.cdn_image_url,''), p.image_url),p.seo
		FROM products p
		WHERE p.tenant_id=? AND p.is_active=1 AND p.price_cents>0 AND p.stock_quantity>0`+excludeSQL+`
		ORDER BY CASE WHEN p.compare_at_price_cents IS NOT NULL AND p.compare_at_price_cents>p.price_cents THEN 0 ELSE 1 END, p.id DESC
		LIMIT ?`, args...)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	products, err := scanProducts(rows)
	if err != nil {
		return nil, err
	}
	app.applyProductCDN(products)
	if len(products) > 0 {
		_ = app.attachProductCategories(ctx, products)
	}
	return products, nil
}

func productExcludeSQL(existing []Product) (string, []any) {
	if len(existing) == 0 {
		return "", nil
	}
	placeholders := ""
	args := make([]any, 0, len(existing))
	for i, product := range existing {
		if i > 0 {
			placeholders += ","
		}
		placeholders += "?"
		args = append(args, product.ID)
	}
	return " AND p.id NOT IN (" + placeholders + ")", args
}

func appendUniqueProducts(base []Product, incoming []Product) []Product {
	seen := map[int64]bool{}
	for _, product := range base {
		seen[product.ID] = true
	}
	for _, product := range incoming {
		if seen[product.ID] {
			continue
		}
		base = append(base, product)
		seen[product.ID] = true
	}
	return base
}
