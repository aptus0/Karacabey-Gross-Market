package api

import (
	"context"
	"database/sql"
	"fmt"
	"strings"
)

func (app *App) listProducts(ctx context.Context, filter ProductFilter) (Pagination, error) {
	if filter.Page <= 0 {
		filter.Page = 1
	}
	if filter.PerPage <= 0 {
		filter.PerPage = 12
	}
	if filter.PerPage > 96 {
		filter.PerPage = 96
	}

	cacheKey := fmt.Sprintf("products:%d:%s:%s:%s:%v:%v:%d:%d", app.cfg.TenantID, filter.Query, filter.Category, filter.Sort, filter.PriceMin, filter.PriceMax, filter.Page, filter.PerPage)
	return cached(app.cache, cacheKey, func() (Pagination, error) {
		where, args, join := app.productWhere(filter)
		countSQL := "SELECT COUNT(DISTINCT p.id) FROM products p " + join + " WHERE " + where
		var total int64
		if err := app.db.QueryRowContext(ctx, countSQL, args...).Scan(&total); err != nil {
			return Pagination{}, err
		}

		orderBy := "CASE WHEN COALESCE(NULLIF(p.cdn_image_url,''), p.image_url) IS NULL OR COALESCE(NULLIF(p.cdn_image_url,''), p.image_url) = '' THEN 1 ELSE 0 END ASC, p.id DESC"
		switch filter.Sort {
		case "price_asc":
			orderBy = "p.price_cents ASC, p.id DESC"
		case "price_desc":
			orderBy = "p.price_cents DESC, p.id DESC"
		}
		offset := (filter.Page - 1) * filter.PerPage
		query := `SELECT p.id,p.name,p.slug,p.description,p.brand,p.barcode,p.price_cents,p.compare_at_price_cents,p.stock_quantity,COALESCE(NULLIF(p.cdn_image_url,''), p.image_url),p.seo
            FROM products p ` + join + ` WHERE ` + where + ` ORDER BY ` + orderBy + ` LIMIT ? OFFSET ?`
		rows, err := app.db.QueryContext(ctx, query, append(args, filter.PerPage, offset)...)
		if err != nil {
			return Pagination{}, err
		}
		defer rows.Close()

		products, err := scanProducts(rows)
		if err != nil {
			return Pagination{}, err
		}
		app.applyProductCDN(products)
		if len(products) > 0 {
			if err := app.attachProductCategories(ctx, products); err != nil {
				return Pagination{}, err
			}
		}
		from, to := paginationRange(total, filter.Page, filter.PerPage, len(products))
		return Pagination{Data: products, Total: total, PerPage: filter.PerPage, CurrentPage: filter.Page, LastPage: ceilDiv(total, filter.PerPage), From: from, To: to}, nil
	})
}

func (app *App) productBySlug(ctx context.Context, slug string) (Product, error) {
	cacheKey := fmt.Sprintf("product:%d:%s", app.cfg.TenantID, slug)
	return cached(app.cache, cacheKey, func() (Product, error) {
		rows, err := app.db.QueryContext(ctx, `SELECT p.id,p.name,p.slug,p.description,p.brand,p.barcode,p.price_cents,p.compare_at_price_cents,p.stock_quantity,COALESCE(NULLIF(p.cdn_image_url,''), p.image_url),p.seo
            FROM products p WHERE p.tenant_id=? AND p.is_active=1 AND p.price_cents>0 AND p.slug=? LIMIT 1`, app.cfg.TenantID, slug)
		if err != nil {
			return Product{}, err
		}
		defer rows.Close()
		products, err := scanProducts(rows)
		if err != nil {
			return Product{}, err
		}
		if len(products) == 0 {
			return Product{}, ErrNotFound
		}
		if err := app.attachProductCategories(ctx, products); err != nil {
			return Product{}, err
		}
		app.applyProductCDN(products)
		return products[0], nil
	})
}

func (app *App) suggestProducts(ctx context.Context, term string) ([]Product, error) {
	term = strings.TrimSpace(term)
	if len([]rune(term)) < 2 {
		return []Product{}, nil
	}
	cacheKey := fmt.Sprintf("suggest:%d:%s", app.cfg.TenantID, strings.ToLower(term))
	return cached(app.cache, cacheKey, func() ([]Product, error) {
		like := "%" + escapeLike(term) + "%"
		rows, err := app.db.QueryContext(ctx, `SELECT p.id,p.name,p.slug,p.description,p.brand,p.barcode,p.price_cents,p.compare_at_price_cents,p.stock_quantity,COALESCE(NULLIF(p.cdn_image_url,''), p.image_url),p.seo
            FROM products p WHERE p.tenant_id=? AND p.is_active=1 AND p.price_cents>0 AND (p.name LIKE ? OR p.brand LIKE ? OR p.barcode LIKE ?)
            ORDER BY CASE WHEN p.name LIKE ? THEN 0 ELSE 1 END, p.id DESC LIMIT 8`, app.cfg.TenantID, like, like, like, like)
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
	})
}

func (app *App) productWhere(filter ProductFilter) (string, []any, string) {
	where := []string{"p.tenant_id=?", "p.is_active=1", "p.price_cents>0"}
	args := []any{app.cfg.TenantID}
	join := ""
	if filter.Query != "" {
		like := "%" + escapeLike(filter.Query) + "%"
		where = append(where, "(p.name LIKE ? OR p.slug LIKE ? OR p.brand LIKE ? OR p.barcode LIKE ?)")
		args = append(args, like, like, like, like)
	}
	if filter.InStock {
		where = append(where, "p.stock_quantity > 0")
	}
	if filter.PriceMin != nil {
		where = append(where, "p.price_cents >= ?")
		args = append(args, *filter.PriceMin)
	}
	if filter.PriceMax != nil {
		where = append(where, "p.price_cents <= ?")
		args = append(args, *filter.PriceMax)
	}
	if filter.Category != "" {
		join = " JOIN category_product cp ON cp.product_id=p.id JOIN categories c ON c.id=cp.category_id "
		where = append(where, `c.tenant_id=? AND c.is_active=1 AND (c.slug=? OR c.parent_id=(SELECT root.id FROM categories root WHERE root.tenant_id=? AND root.slug=? LIMIT 1))`)
		args = append(args, app.cfg.TenantID, filter.Category, app.cfg.TenantID, filter.Category)
	}
	return strings.Join(where, " AND "), args, join
}

func scanProducts(rows *sql.Rows) ([]Product, error) {
	products := make([]Product, 0)
	for rows.Next() {
		var p Product
		var desc, brand, barcode, image, seo sql.NullString
		var compare sql.NullInt64
		if err := rows.Scan(&p.ID, &p.Name, &p.Slug, &desc, &brand, &barcode, &p.PriceCents, &compare, &p.StockQuantity, &image, &seo); err != nil {
			return nil, err
		}
		p.Description = ptrString(desc)
		p.Brand = ptrString(brand)
		p.Barcode = ptrString(barcode)
		p.CompareAtPriceCents = ptrInt64(compare)
		p.ImageURL = ptrString(image)
		p.SEO = parseJSONMap(seo)
		p.Price = moneyTRY(p.PriceCents)
		products = append(products, p)
	}
	return products, rows.Err()
}

func (app *App) attachProductCategories(ctx context.Context, products []Product) error {
	ids := make([]string, 0, len(products))
	idArgs := make([]any, 0, len(products))
	byID := map[int64]int{}
	for i := range products {
		ids = append(ids, "?")
		idArgs = append(idArgs, products[i].ID)
		byID[products[i].ID] = i
	}
	query := `SELECT cp.product_id,c.id,c.name,c.slug FROM category_product cp JOIN categories c ON c.id=cp.category_id WHERE cp.product_id IN (` + strings.Join(ids, ",") + `) AND c.is_active=1 ORDER BY c.sort_order ASC,c.name ASC`
	rows, err := app.db.QueryContext(ctx, query, idArgs...)
	if err != nil {
		return err
	}
	defer rows.Close()
	for rows.Next() {
		var productID int64
		var category CategoryMini
		if err := rows.Scan(&productID, &category.ID, &category.Name, &category.Slug); err != nil {
			return err
		}
		if idx, ok := byID[productID]; ok {
			products[idx].Categories = append(products[idx].Categories, category)
		}
	}
	return rows.Err()
}

func (app *App) listCategories(ctx context.Context) ([]Category, error) {
	cacheKey := fmt.Sprintf("categories:%d", app.cfg.TenantID)
	return cached(app.cache, cacheKey, func() ([]Category, error) {
		rows, err := app.db.QueryContext(ctx, `SELECT c.id,c.parent_id,c.name,c.slug,c.description,c.image_url,COUNT(cp.product_id) product_count
            FROM categories c LEFT JOIN category_product cp ON cp.category_id=c.id
            WHERE c.tenant_id=? AND c.is_active=1
            GROUP BY c.id,c.parent_id,c.name,c.slug,c.description,c.image_url
            ORDER BY c.sort_order ASC,c.name ASC`, app.cfg.TenantID)
		if err != nil {
			return nil, err
		}
		defer rows.Close()
		byParent := map[int64][]Category{}
		roots := []Category{}
		for rows.Next() {
			var cat Category
			var parent sql.NullInt64
			var desc, image sql.NullString
			if err := rows.Scan(&cat.ID, &parent, &cat.Name, &cat.Slug, &desc, &image, &cat.ProductCount); err != nil {
				return nil, err
			}
			cat.Description = ptrString(desc)
			cat.ImageURL = ptrString(image)
			cat.ParentID = ptrInt64(parent)
			cat.Children = []Category{}
			if parent.Valid {
				byParent[parent.Int64] = append(byParent[parent.Int64], cat)
			} else {
				roots = append(roots, cat)
			}
		}
		if err := rows.Err(); err != nil {
			return nil, err
		}
		for i := range roots {
			roots[i].Children = byParent[roots[i].ID]
		}
		app.applyCategoryCDN(roots)
		return roots, nil
	})
}

func (app *App) categoryBySlug(ctx context.Context, slug string) (CategoryDetail, error) {
	slug = strings.TrimSpace(slug)
	if slug == "" {
		return CategoryDetail{}, ErrNotFound
	}

	cacheKey := fmt.Sprintf("category:%d:%s", app.cfg.TenantID, slug)
	return cached(app.cache, cacheKey, func() (CategoryDetail, error) {
		var category CategoryDetail
		var parent sql.NullInt64
		var desc, image, seo sql.NullString
		err := app.db.QueryRowContext(ctx, `SELECT c.id,c.parent_id,c.name,c.slug,c.description,c.image_url,c.seo,
                (SELECT COUNT(*) FROM category_product cp WHERE cp.category_id=c.id) product_count
            FROM categories c
            WHERE c.tenant_id=? AND c.is_active=1 AND c.slug=?
            LIMIT 1`, app.cfg.TenantID, slug).Scan(&category.ID, &parent, &category.Name, &category.Slug, &desc, &image, &seo, &category.ProductCount)
		if err != nil {
			if err == sql.ErrNoRows {
				return CategoryDetail{}, ErrNotFound
			}
			return CategoryDetail{}, err
		}
		category.ParentID = ptrInt64(parent)
		category.Description = ptrString(desc)
		category.ImageURL = app.publicImageURL(ptrString(image))
		category.SEO = parseJSONMap(seo)
		category.Children = []Category{}
		category.Products = []CategoryProduct{}

		children, err := app.categoryChildren(ctx, category.ID)
		if err != nil {
			return CategoryDetail{}, err
		}
		category.Children = children

		products, err := app.categoryProducts(ctx, category.ID)
		if err != nil {
			return CategoryDetail{}, err
		}
		category.Products = products

		return category, nil
	})
}

func (app *App) categoryChildren(ctx context.Context, parentID int64) ([]Category, error) {
	rows, err := app.db.QueryContext(ctx, `SELECT c.id,c.parent_id,c.name,c.slug,c.description,c.image_url,COUNT(cp.product_id) product_count
        FROM categories c LEFT JOIN category_product cp ON cp.category_id=c.id
        WHERE c.tenant_id=? AND c.is_active=1 AND c.parent_id=?
        GROUP BY c.id,c.parent_id,c.name,c.slug,c.description,c.image_url
        ORDER BY c.sort_order ASC,c.name ASC`, app.cfg.TenantID, parentID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	children := []Category{}
	for rows.Next() {
		var cat Category
		var parent sql.NullInt64
		var desc, image sql.NullString
		if err := rows.Scan(&cat.ID, &parent, &cat.Name, &cat.Slug, &desc, &image, &cat.ProductCount); err != nil {
			return nil, err
		}
		cat.ParentID = ptrInt64(parent)
		cat.Description = ptrString(desc)
		cat.ImageURL = ptrString(image)
		cat.Children = []Category{}
		children = append(children, cat)
	}
	if err := rows.Err(); err != nil {
		return nil, err
	}
	app.applyCategoryCDN(children)
	return children, nil
}

func (app *App) categoryProducts(ctx context.Context, categoryID int64) ([]CategoryProduct, error) {
	rows, err := app.db.QueryContext(ctx, `SELECT p.id,p.name,p.slug,p.price_cents,COALESCE(NULLIF(p.cdn_image_url,''), p.image_url)
        FROM products p JOIN category_product cp ON cp.product_id=p.id
        WHERE p.tenant_id=? AND p.is_active=1 AND p.price_cents>0 AND cp.category_id=?
        ORDER BY p.id DESC
        LIMIT 12`, app.cfg.TenantID, categoryID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	products := []CategoryProduct{}
	for rows.Next() {
		var product CategoryProduct
		var image sql.NullString
		if err := rows.Scan(&product.ID, &product.Name, &product.Slug, &product.PriceCents, &image); err != nil {
			return nil, err
		}
		product.Price = moneyTRY(product.PriceCents)
		product.ImageURL = app.publicImageURL(ptrString(image))
		products = append(products, product)
	}
	return products, rows.Err()
}

func paginationRange(total int64, page, perPage, count int) (*int, *int) {
	if total == 0 || count == 0 {
		return nil, nil
	}
	from := (page-1)*perPage + 1
	to := from + count - 1
	return &from, &to
}

func escapeLike(s string) string {
	s = strings.ReplaceAll(s, `\\`, `\\\\`)
	s = strings.ReplaceAll(s, `%`, `\\%`)
	s = strings.ReplaceAll(s, `_`, `\\_`)
	return strings.TrimSpace(s)
}
