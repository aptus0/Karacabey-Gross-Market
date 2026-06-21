package api

import (
	"context"
	"database/sql"
	"errors"
	"fmt"
	"net/http"
	"strconv"
	"strings"
	"sync"
)

func (app *App) identityFromRequest(ctx context.Context, r *http.Request) (CartIdentity, error) {
	readOnly := r.Method == http.MethodGet || r.Method == http.MethodHead
	if token := requestBearerToken(r); token != "" {
		user, err := app.resolveUser(ctx, token)
		if err != nil {
			return CartIdentity{}, err
		}
		if user != nil {
			uid := derefString(user.CustomerUID)
			if uid == "" {
				uid = requestIdentity(r.Context()).CustomerUID
			}
			return CartIdentity{UserID: &user.ID, CustomerUID: stringPtr(uid)}, nil
		}
	}
	cartToken := strings.TrimSpace(r.Header.Get("X-Cart-Token"))
	if cartToken == "" {
		cartToken = strings.TrimSpace(r.URL.Query().Get("cart_token"))
	}
	if len(cartToken) > 64 {
		cartToken = cartToken[:64]
	}
	identity := requestIdentity(r.Context())
	customerUID := sanitizeUID(identity.CustomerUID)
	if cartToken == "" && customerUID != "" {
		activeToken, err := app.activeCartToken(ctx, customerUID)
		if err != nil {
			return CartIdentity{}, err
		}
		cartToken = activeToken
	}
	if cartToken == "" && customerUID != "" {
		cartToken = newPublicUID("cart")
		app.upsertActiveCart(ctx, customerUID, cartToken, nil)
	}
	if cartToken == "" {
		return CartIdentity{}, ErrBadRequest
	}
	if customerUID != "" && !readOnly {
		app.upsertActiveCart(ctx, customerUID, cartToken, nil)
	}
	return CartIdentity{CartToken: &cartToken, CustomerUID: stringPtr(customerUID)}, nil
}

func (id CartIdentity) whereClause() (string, []any) {
	if id.UserID != nil {
		return "user_id=?", []any{*id.UserID}
	}
	return "cart_token=?", []any{*id.CartToken}
}

func (app *App) cart(ctx context.Context, id CartIdentity) (CartData, error) {
	where, args := id.whereClause()
	query := `SELECT ci.id,ci.quantity,p.id,p.name,p.slug,p.brand,p.price_cents,p.stock_quantity,COALESCE(NULLIF(p.cdn_image_url,''), p.image_url)
        FROM cart_items ci JOIN products p ON p.id=ci.product_id
        WHERE ci.tenant_id=? AND ` + where + ` AND p.is_active=1 AND p.price_cents>0 ORDER BY ci.created_at ASC,ci.id ASC`

	var (
		wg          sync.WaitGroup
		syncVersion int64
	)
	wg.Add(1)
	go func() {
		defer wg.Done()
		syncVersion = app.customerSyncVersion(ctx, id)
	}()

	rows, err := app.db.QueryContext(ctx, query, append([]any{app.cfg.TenantID}, args...)...)
	if err != nil {
		wg.Wait()
		return CartData{}, err
	}
	defer rows.Close()
	items := []CartLineItem{}
	var subtotal int64
	for rows.Next() {
		var item CartLineItem
		var product CartProduct
		var brand, image sql.NullString
		if err := rows.Scan(&item.ID, &item.Quantity, &product.ID, &product.Name, &product.Slug, &brand, &product.PriceCents, &product.StockQuantity, &image); err != nil {
			wg.Wait()
			return CartData{}, err
		}
		product.Brand = ptrString(brand)
		product.ImageURL = app.publicImageURL(ptrString(image))
		product.Price = moneyTRY(product.PriceCents)
		item.Product = product
		item.LineTotalCents = product.PriceCents * int64(item.Quantity)
		subtotal += item.LineTotalCents
		items = append(items, item)
	}
	if err := rows.Err(); err != nil {
		wg.Wait()
		return CartData{}, err
	}
	coupon, total, err := app.appliedCoupon(ctx, id, subtotal)
	if err != nil {
		wg.Wait()
		return CartData{}, err
	}
	wg.Wait()
	return CartData{CustomerUID: id.CustomerUID, SyncVersion: syncVersion, CartToken: id.CartToken, Items: items, AppliedCoupon: coupon, SubtotalCents: subtotal, TotalCents: total}, nil
}

func (app *App) addCartItem(ctx context.Context, id CartIdentity, productID int64, quantity int) (CartData, error) {
	quantity = clampQuantity(quantity)
	tx, err := app.db.BeginTx(ctx, &sql.TxOptions{Isolation: sql.LevelReadCommitted})
	if err != nil {
		return CartData{}, err
	}
	defer tx.Rollback()
	var stock int
	var active bool
	if err := tx.QueryRowContext(ctx, `SELECT stock_quantity,is_active FROM products WHERE tenant_id=? AND id=? FOR UPDATE`, app.cfg.TenantID, productID).Scan(&stock, &active); err != nil {
		if errors.Is(err, sql.ErrNoRows) {
			return CartData{}, ErrNotFound
		}
		return CartData{}, err
	}
	if !active {
		return CartData{}, ErrNotFound
	}
	if stock <= 0 {
		return CartData{}, fmt.Errorf("%w: Ürün stokta yok.", ErrConflict)
	}
	if quantity > stock {
		quantity = stock
	}
	_, err = tx.ExecContext(ctx, `INSERT INTO cart_items (tenant_id,user_id,cart_token,product_id,quantity,created_at,updated_at)
        VALUES (?,?,?,?,?,NOW(),NOW())
        ON DUPLICATE KEY UPDATE quantity=LEAST(cart_items.quantity+VALUES(quantity),?,99), updated_at=NOW()`,
		app.cfg.TenantID, sqlNullInt64Ptr(id.UserID), sqlNullStringPtr(id.CartToken), productID, quantity, stock)
	if err != nil {
		return CartData{}, err
	}
	if err := tx.Commit(); err != nil {
		return CartData{}, err
	}
	app.touchCustomerSync(ctx, id, "cart.item_added")
	return app.cart(ctx, id)
}

func (app *App) updateCartItem(ctx context.Context, id CartIdentity, itemID int64, quantity int) (CartData, error) {
	if quantity <= 0 {
		return app.deleteCartItem(ctx, id, itemID)
	}
	quantity = clampQuantity(quantity)
	where, args := id.whereClause()
	tx, err := app.db.BeginTx(ctx, &sql.TxOptions{Isolation: sql.LevelReadCommitted})
	if err != nil {
		return CartData{}, err
	}
	defer tx.Rollback()

	var stock int
	query := `SELECT p.stock_quantity
		FROM cart_items ci
		JOIN products p ON p.id=ci.product_id
		WHERE ci.tenant_id=? AND ci.id=? AND ` + where + ` AND p.is_active=1 FOR UPDATE`
	if err := tx.QueryRowContext(ctx, query, append([]any{app.cfg.TenantID, itemID}, args...)...).Scan(&stock); err != nil {
		if errors.Is(err, sql.ErrNoRows) {
			return CartData{}, ErrNotFound
		}
		return CartData{}, err
	}
	if stock <= 0 {
		return CartData{}, fmt.Errorf("%w: Ürün stokta yok.", ErrConflict)
	}
	if quantity > stock {
		quantity = stock
	}

	res, err := tx.ExecContext(ctx, `UPDATE cart_items SET quantity=?, updated_at=NOW() WHERE tenant_id=? AND id=? AND `+where, append([]any{quantity, app.cfg.TenantID, itemID}, args...)...)
	if err != nil {
		return CartData{}, err
	}
	affected, _ := res.RowsAffected()
	if affected == 0 {
		return CartData{}, ErrNotFound
	}
	if err := tx.Commit(); err != nil {
		return CartData{}, err
	}
	app.touchCustomerSync(ctx, id, "cart.item_updated")
	return app.cart(ctx, id)
}

func (app *App) deleteCartItem(ctx context.Context, id CartIdentity, itemID int64) (CartData, error) {
	where, args := id.whereClause()
	res, err := app.db.ExecContext(ctx, `DELETE FROM cart_items WHERE tenant_id=? AND id=? AND `+where, append([]any{app.cfg.TenantID, itemID}, args...)...)
	if err != nil {
		return CartData{}, err
	}
	affected, _ := res.RowsAffected()
	if affected == 0 {
		return CartData{}, ErrNotFound
	}
	app.touchCustomerSync(ctx, id, "cart.item_removed")
	return app.cart(ctx, id)
}

func (app *App) clearCart(ctx context.Context, id CartIdentity) (CartData, error) {
	where, args := id.whereClause()
	_, err := app.db.ExecContext(ctx, `DELETE FROM cart_items WHERE tenant_id=? AND `+where, append([]any{app.cfg.TenantID}, args...)...)
	if err != nil {
		return CartData{}, err
	}
	_, _ = app.db.ExecContext(ctx, `DELETE FROM cart_coupons WHERE tenant_id=? AND `+where, append([]any{app.cfg.TenantID}, args...)...)
	app.touchCustomerSync(ctx, id, "cart.cleared")
	return app.cart(ctx, id)
}

func (app *App) appliedCoupon(ctx context.Context, id CartIdentity, subtotal int64) (*AppliedCoupon, int64, error) {
	where, args := id.whereClause()
	row := app.db.QueryRowContext(ctx, `SELECT co.code,co.discount_type,co.discount_value,co.minimum_order_cents
        FROM cart_coupons cc JOIN coupons co ON co.id=cc.coupon_id
        WHERE cc.tenant_id=? AND `+where+` AND co.is_active=1 AND (co.starts_at IS NULL OR co.starts_at<=NOW()) AND (co.ends_at IS NULL OR co.ends_at>=NOW()) LIMIT 1`, append([]any{app.cfg.TenantID}, args...)...)
	var code, kind string
	var value, minimum int64
	if err := row.Scan(&code, &kind, &value, &minimum); err != nil {
		if errors.Is(err, sql.ErrNoRows) {
			return nil, subtotal, nil
		}
		return nil, 0, err
	}
	if subtotal < minimum {
		return nil, subtotal, nil
	}
	discount := value
	if kind == "percent" {
		discount = subtotal * value / 100
	}
	if discount > subtotal {
		discount = subtotal
	}
	total := subtotal - discount
	return &AppliedCoupon{Code: code, DiscountType: kind, DiscountValue: value, DiscountCents: discount, TotalCents: total}, total, nil
}

func (app *App) applyCoupon(ctx context.Context, id CartIdentity, code string) (*AppliedCoupon, error) {
	code = strings.ToUpper(strings.TrimSpace(code))
	if code == "" {
		return nil, fmt.Errorf("%w: Kupon kodu boş olamaz.", ErrBadRequest)
	}
	var couponID int64
	var minimum int64
	if err := app.db.QueryRowContext(ctx, `SELECT id,minimum_order_cents FROM coupons WHERE tenant_id=? AND code=? AND is_active=1 AND (starts_at IS NULL OR starts_at<=NOW()) AND (ends_at IS NULL OR ends_at>=NOW()) LIMIT 1`, app.cfg.TenantID, code).Scan(&couponID, &minimum); err != nil {
		if errors.Is(err, sql.ErrNoRows) {
			return nil, fmt.Errorf("%w: Kupon bulunamadı veya aktif değil.", ErrBadRequest)
		}
		return nil, err
	}
	cart, err := app.cart(ctx, id)
	if err != nil {
		return nil, err
	}
	if cart.SubtotalCents < minimum {
		return nil, fmt.Errorf("%w: Bu kupon için sepet tutarı yetersiz.", ErrBadRequest)
	}
	_, err = app.db.ExecContext(ctx, `INSERT INTO cart_coupons (tenant_id,user_id,cart_token,coupon_id,created_at,updated_at) VALUES (?,?,?,?,NOW(),NOW())
        ON DUPLICATE KEY UPDATE coupon_id=VALUES(coupon_id), updated_at=NOW()`, app.cfg.TenantID, sqlNullInt64Ptr(id.UserID), sqlNullStringPtr(id.CartToken), couponID)
	if err != nil {
		return nil, err
	}
	app.touchCustomerSync(ctx, id, "cart.coupon_applied")
	cart, err = app.cart(ctx, id)
	if err != nil {
		return nil, err
	}
	return cart.AppliedCoupon, nil
}

func (app *App) removeCoupon(ctx context.Context, id CartIdentity) error {
	where, args := id.whereClause()
	_, err := app.db.ExecContext(ctx, `DELETE FROM cart_coupons WHERE tenant_id=? AND `+where, append([]any{app.cfg.TenantID}, args...)...)
	if err == nil {
		app.touchCustomerSync(ctx, id, "cart.coupon_removed")
	}
	return err
}

func parsePathID(r *http.Request, name string) (int64, error) {
	value, err := strconv.ParseInt(r.PathValue(name), 10, 64)
	if err != nil || value <= 0 {
		return 0, ErrBadRequest
	}
	return value, nil
}
