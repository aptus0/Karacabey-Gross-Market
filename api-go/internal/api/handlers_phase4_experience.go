package api

import (
	"errors"
	"fmt"
	"net/http"
	"strings"
	"time"
)

type StockAlertRequest struct {
	Email string `json:"email"`
	Phone string `json:"phone"`
}

type ReorderLineResult struct {
	ProductID int64  `json:"product_id"`
	Requested int    `json:"requested_quantity"`
	Added     int    `json:"added_quantity"`
	Status    string `json:"status"`
}

// GET /api/v1/customer/recent-purchases
// Müşterinin daha önce satın aldığı ürünleri son sipariş tarihine göre döndürür.
// Mobil ana sayfadaki "Son Aldıklarım" ve "Tekrar Sepete Ekle" alanını besler.
func (app *App) handleCustomerRecentPurchases(w http.ResponseWriter, r *http.Request) {
	user, ok := app.requireUser(w, r)
	if !ok {
		return
	}
	ctx, cancel := withTimeout(r, 5*time.Second)
	defer cancel()

	limit := parseIntQuery(r, "limit", 12, 1, 48)
	rows, err := app.db.QueryContext(ctx, `SELECT p.id,p.name,p.slug,p.description,p.brand,p.barcode,p.price_cents,p.compare_at_price_cents,p.stock_quantity,COALESCE(NULLIF(p.cdn_image_url,''), p.image_url),p.seo
        FROM products p
        INNER JOIN (
            SELECT oi.product_id, MAX(o.created_at) AS last_ordered_at
            FROM order_items oi
            INNER JOIN orders o ON o.id=oi.order_id
            WHERE o.tenant_id=? AND o.user_id=? AND oi.product_id IS NOT NULL
              AND o.status NOT IN ('cancelled','failed','refunded')
            GROUP BY oi.product_id
        ) recent ON recent.product_id=p.id
        WHERE p.tenant_id=? AND p.is_active=1 AND p.price_cents>0
        ORDER BY recent.last_ordered_at DESC, p.id DESC
        LIMIT ?`, app.cfg.TenantID, user.ID, app.cfg.TenantID, limit)
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	defer rows.Close()

	products, err := scanProducts(rows)
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	if len(products) > 0 {
		_ = app.attachProductCategories(ctx, products)
		app.applyProductCDN(products)
	}
	writeData(w, http.StatusOK, products)
}

// GET /api/v1/products/{slug}/frequently-bought-together
// Aynı siparişlerde beraber satın alınan ürünleri satış sayısına göre getirir.
func (app *App) handleProductFrequentlyBoughtTogether(w http.ResponseWriter, r *http.Request) {
	ctx, cancel := withTimeout(r, 5*time.Second)
	defer cancel()

	product, err := app.productBySlug(ctx, r.PathValue("slug"))
	if err != nil {
		app.handleErr(w, r, err)
		return
	}

	rows, err := app.db.QueryContext(ctx, `SELECT p.id,p.name,p.slug,p.description,p.brand,p.barcode,p.price_cents,p.compare_at_price_cents,p.stock_quantity,COALESCE(NULLIF(p.cdn_image_url,''), p.image_url),p.seo
        FROM order_items seed
        INNER JOIN order_items peer ON peer.order_id=seed.order_id AND peer.product_id<>seed.product_id
        INNER JOIN orders o ON o.id=seed.order_id
        INNER JOIN products p ON p.id=peer.product_id
        WHERE o.tenant_id=? AND seed.product_id=? AND p.tenant_id=? AND p.is_active=1 AND p.price_cents>0
          AND o.status NOT IN ('cancelled','failed','refunded')
        GROUP BY p.id,p.name,p.slug,p.description,p.brand,p.barcode,p.price_cents,p.compare_at_price_cents,p.stock_quantity,p.cdn_image_url,p.image_url,p.seo
        ORDER BY COUNT(*) DESC, MAX(o.created_at) DESC, p.id DESC
        LIMIT 8`, app.cfg.TenantID, product.ID, app.cfg.TenantID)
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	defer rows.Close()

	products, err := scanProducts(rows)
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	if len(products) > 0 {
		_ = app.attachProductCategories(ctx, products)
		app.applyProductCDN(products)
	}

	// Yeni/az siparişli ürünlerde boş dönmesin diye kategori bazlı güvenli fallback.
	if len(products) == 0 && len(product.Categories) > 0 {
		page, err := app.listProducts(ctx, ProductFilter{Category: product.Categories[0].Slug, Page: 1, PerPage: 8})
		if err == nil {
			for _, item := range page.Data {
				if item.ID != product.ID {
					products = append(products, item)
				}
			}
		}
	}

	writeData(w, http.StatusOK, products)
}

// POST /api/v1/products/{slug}/stock-alert
// Stokta olmayan ürünlerde "Gelince haber ver" talebi oluşturur.
func (app *App) handleProductStockAlert(w http.ResponseWriter, r *http.Request) {
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

	var body StockAlertRequest
	if r.Body != nil && r.ContentLength != 0 {
		if err := parseJSON(r, &body); err != nil {
			app.handleErr(w, r, fmt.Errorf("%w: Bildirim bilgileri okunamadı.", ErrBadRequest))
			return
		}
	}
	body.Email = strings.TrimSpace(firstNonEmpty(body.Email, derefString(user.Email)))
	body.Phone = strings.TrimSpace(body.Phone)

	_, err = app.db.ExecContext(ctx, `INSERT INTO product_stock_alerts
        (tenant_id,product_id,user_id,email,phone,status,created_at,updated_at)
        VALUES (?,?,?,?,?,'waiting',NOW(),NOW())
        ON DUPLICATE KEY UPDATE email=VALUES(email), phone=VALUES(phone), status='waiting', updated_at=NOW()`,
		app.cfg.TenantID, product.ID, user.ID, nullableTrimmed(body.Email), nullableTrimmed(body.Phone))
	if err != nil {
		if isMissingTableError(err) {
			writeData(w, http.StatusAccepted, map[string]any{
				"status":  "queued",
				"message": "Talebiniz alındı. Stok bildirim altyapısı canlıya alındığında işleme alınacak.",
			})
			return
		}
		app.handleErr(w, r, err)
		return
	}

	app.touchCustomerSync(ctx, CartIdentity{UserID: &user.ID, CustomerUID: user.CustomerUID}, "product.stock_alert")
	writeData(w, http.StatusOK, map[string]any{
		"status":  "waiting",
		"message": "Ürün stoğa girince bildirimlerden haber vereceğiz.",
	})
}

// POST /api/v1/orders/{id}/reorder
// Eski siparişteki stokta olan ürünleri mevcut sepete tekrar ekler.
func (app *App) handleOrderReorder(w http.ResponseWriter, r *http.Request) {
	user, ok := app.requireUser(w, r)
	if !ok {
		return
	}
	orderID, err := parsePathID(r, "id")
	if err != nil {
		app.handleErr(w, r, err)
		return
	}

	ctx, cancel := withTimeout(r, 12*time.Second)
	defer cancel()

	var exists int
	if err := app.db.QueryRowContext(ctx, `SELECT COUNT(*) FROM orders WHERE tenant_id=? AND user_id=? AND id=?`, app.cfg.TenantID, user.ID, orderID).Scan(&exists); err != nil {
		app.handleErr(w, r, err)
		return
	}
	if exists == 0 {
		app.handleErr(w, r, ErrNotFound)
		return
	}

	rows, err := app.db.QueryContext(ctx, `SELECT oi.product_id, oi.quantity, p.stock_quantity, p.is_active
        FROM order_items oi
        INNER JOIN products p ON p.id=oi.product_id
        WHERE oi.order_id=? AND p.tenant_id=?
        ORDER BY oi.id ASC`, orderID, app.cfg.TenantID)
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	defer rows.Close()

	lines := []ReorderLineResult{}

	identity, err := app.identityFromRequest(ctx, r)
	if err != nil {
		// Authenticated kullanıcı için sepet kimliği token'dan çözümlenir; yine de fallback açık kalsın.
		identity = CartIdentity{UserID: &user.ID, CustomerUID: user.CustomerUID}
	}
	if identity.UserID == nil {
		identity.UserID = &user.ID
	}

	var finalCart CartData
	var addedCount int
	for rows.Next() {
		var productID int64
		var requested, stock int
		var active bool
		if err := rows.Scan(&productID, &requested, &stock, &active); err != nil {
			app.handleErr(w, r, err)
			return
		}
		line := ReorderLineResult{ProductID: productID, Requested: requested, Status: "skipped"}
		if !active || stock <= 0 {
			line.Status = "out_of_stock"
			lines = append(lines, line)
			continue
		}
		qty := requested
		if qty > stock {
			qty = stock
			line.Status = "partial"
		} else {
			line.Status = "added"
		}
		cart, err := app.addCartItem(ctx, identity, productID, qty)
		if err != nil {
			if errors.Is(err, ErrConflict) || errors.Is(err, ErrNotFound) {
				line.Status = "skipped"
				lines = append(lines, line)
				continue
			}
			app.handleErr(w, r, err)
			return
		}
		finalCart = cart
		line.Added = qty
		addedCount += qty
		lines = append(lines, line)
	}
	if err := rows.Err(); err != nil {
		app.handleErr(w, r, err)
		return
	}
	if finalCart.Items == nil {
		finalCart, _ = app.cart(ctx, identity)
	}
	setCartIdentityHeaders(w, finalCart)
	app.touchCustomerSync(ctx, identity, "order.reordered")
	writeData(w, http.StatusOK, map[string]any{
		"cart":        finalCart,
		"added_count": addedCount,
		"lines":       lines,
		"message":     reorderMessage(addedCount, lines),
	})
}

func reorderMessage(addedCount int, lines []ReorderLineResult) string {
	if addedCount == 0 {
		return "Bu siparişte tekrar sepete eklenebilecek stokta ürün bulunamadı."
	}
	for _, line := range lines {
		if line.Status == "partial" || line.Status == "out_of_stock" || line.Status == "skipped" {
			return "Stokta olan ürünler sepetinize eklendi. Bazı ürünler stok durumuna göre atlandı veya adetleri düşürüldü."
		}
	}
	return "Sipariş ürünleri sepetinize eklendi."
}
