package api

import (
	"database/sql"
	"errors"
	"net/http"
	"strconv"
	"strings"
	"time"
)

func (app *App) handleHomepage(w http.ResponseWriter, r *http.Request) {
	ctx, cancel := withTimeout(r, 4*time.Second)
	defer cancel()
	channel := strings.ToLower(strings.TrimSpace(r.URL.Query().Get("channel")))
	channelFilter := ""
	if channel == "mobile" {
		channelFilter = " AND show_on_mobile=1"
	} else if channel == "web" {
		channelFilter = " AND show_on_web=1"
	}
	rows, err := app.db.QueryContext(ctx, `SELECT id,type,title,subtitle,image_url,link_url,link_label FROM homepage_blocks WHERE tenant_id=? AND is_active=1`+channelFilter+` ORDER BY sort_order ASC,id ASC LIMIT 20`, app.cfg.TenantID)
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	defer rows.Close()
	blocks := []map[string]any{}
	for rows.Next() {
		var id int64
		var typ string
		var title, subtitle, image, linkURL, linkLabel sql.NullString
		if err := rows.Scan(&id, &typ, &title, &subtitle, &image, &linkURL, &linkLabel); err != nil {
			app.handleErr(w, r, err)
			return
		}
		blocks = append(blocks, map[string]any{"id": id, "type": typ, "title": ptrString(title), "subtitle": ptrString(subtitle), "image_url": app.publicImageURL(ptrString(image)), "link_url": ptrString(linkURL), "action_url": ptrString(linkURL), "link_label": ptrString(linkLabel)})
	}
	if len(blocks) == 0 {
		fallbackImage := "/assets/kgm-logo-4k.png"
		blocks = []map[string]any{{"id": 1, "type": "carousel_slide", "title": "Karacabey Gross Market", "subtitle": "Market alışverişin kapında.", "image_url": app.publicImageURL(&fallbackImage), "link_url": "/products", "action_url": "/products", "link_label": "Alışverişe Başla"}}
	}
	writeData(w, http.StatusOK, map[string]any{"blocks": blocks})
}

func (app *App) handleNavigation(w http.ResponseWriter, r *http.Request) {
	writeData(w, http.StatusOK, map[string]any{
		"top":              []map[string]any{{"label": "Kargo Takip", "url": "/cargo-tracking", "icon": "package-search"}, {"label": "Teslimat Bölgeleri", "url": "/kurumsal/teslimat-bolgeleri", "icon": "map-pin"}, {"label": "Destek & İletişim", "url": "/kurumsal/iletisim", "icon": "phone"}},
		"header":           []map[string]any{{"label": "Ürünler", "url": "/products", "icon": "grid"}, {"label": "Kampanyalar", "url": "/kampanyalar", "icon": "tag"}, {"label": "Hesabım", "url": "/account", "icon": "user"}},
		"category":         []map[string]any{{"label": "Tüm Ürünler", "url": "/products", "icon": "grid"}},
		"footer_primary":   []map[string]any{{"label": "Ürünler", "url": "/products", "icon": "grid"}, {"label": "Kampanyalar", "url": "/kampanyalar", "icon": "tag"}, {"label": "Sepet", "url": "/checkout", "icon": "cart"}},
		"footer_corporate": []map[string]any{{"label": "Hakkımızda", "url": "/kurumsal/hakkimizda", "icon": "file-text"}, {"label": "İletişim", "url": "/kurumsal/iletisim", "icon": "phone"}, {"label": "KVKK", "url": "/kurumsal/kvkk", "icon": "shield"}},
		"footer_support":   []map[string]any{{"label": "İade ve Değişim", "url": "/kurumsal/iade-ve-degisim", "icon": "package-search"}, {"label": "SSS", "url": "/kurumsal/sss", "icon": "file-text"}},
		"footer_account":   []map[string]any{{"label": "Hesabım", "url": "/account", "icon": "user"}, {"label": "Favoriler", "url": "/favorites", "icon": "heart"}},
	})
}

func (app *App) handleCampaigns(w http.ResponseWriter, r *http.Request) {
	ctx, cancel := withTimeout(r, 4*time.Second)
	defer cancel()
	channelFilter := contentChannelFilter(r.URL.Query().Get("channel"))
	rows, err := app.db.QueryContext(ctx, `SELECT id,name,slug,description,image_path,banner_image_url,meta_image_url,badge_label,color_hex,discount_type,discount_value,starts_at,ends_at,seo,
		(SELECT COUNT(*) FROM coupons WHERE coupons.campaign_id=campaigns.id AND coupons.is_active=1)
		FROM campaigns
		WHERE tenant_id=? AND is_active=1
			AND (starts_at IS NULL OR starts_at<=NOW())
			AND (ends_at IS NULL OR ends_at>=NOW())`+channelFilter+`
		ORDER BY sort_order ASC,id DESC LIMIT 60`, app.cfg.TenantID)
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	defer rows.Close()
	items := []map[string]any{}
	for rows.Next() {
		var id int64
		var name, slug, discountType string
		var description, imagePath, banner, metaImage, badge, color, seo sql.NullString
		var discount, couponsCount int64
		var starts, ends sql.NullTime
		if err := rows.Scan(&id, &name, &slug, &description, &imagePath, &banner, &metaImage, &badge, &color, &discountType, &discount, &starts, &ends, &seo, &couponsCount); err != nil {
			app.handleErr(w, r, err)
			return
		}
		items = append(items, map[string]any{
			"id": id, "name": name, "slug": slug, "description": ptrString(description),
			"banner_image_url": campaignImageURL(app, imagePath, banner), "meta_image_url": app.publicImageURL(ptrString(metaImage)),
			"badge_label": ptrString(badge), "color_hex": stringOr(color, "#FF7A00"),
			"discount_type": discountType, "discount_value": discount, "discount_label": campaignDiscountLabel(discountType, discount),
			"starts_at": nullTime(starts), "ends_at": nullTime(ends), "coupons_count": couponsCount, "seo": parseJSONMap(seo),
		})
	}
	writeData(w, http.StatusOK, items)
}

func (app *App) handleCampaignShow(w http.ResponseWriter, r *http.Request) {
	ctx, cancel := withTimeout(r, 4*time.Second)
	defer cancel()
	slug := r.PathValue("slug")
	var id int64
	var name, slugOut, discountType string
	var description, body, imagePath, banner, metaImage, badge, color, seo sql.NullString
	var discount, couponsCount int64
	var starts, ends sql.NullTime
	err := app.db.QueryRowContext(ctx, `SELECT id,name,slug,description,body,image_path,banner_image_url,meta_image_url,badge_label,color_hex,discount_type,discount_value,starts_at,ends_at,seo,
		(SELECT COUNT(*) FROM coupons WHERE coupons.campaign_id=campaigns.id AND coupons.is_active=1)
		FROM campaigns
		WHERE tenant_id=? AND slug=? AND is_active=1
			AND (starts_at IS NULL OR starts_at<=NOW())
			AND (ends_at IS NULL OR ends_at>=NOW())
		LIMIT 1`, app.cfg.TenantID, slug).Scan(&id, &name, &slugOut, &description, &body, &imagePath, &banner, &metaImage, &badge, &color, &discountType, &discount, &starts, &ends, &seo, &couponsCount)
	if err != nil {
		if errors.Is(err, sql.ErrNoRows) {
			app.handleErr(w, r, ErrNotFound)
			return
		}
		app.handleErr(w, r, err)
		return
	}
	writeData(w, http.StatusOK, map[string]any{
		"id": id, "name": name, "slug": slugOut, "description": ptrString(description), "body": ptrString(body),
		"banner_image_url": campaignImageURL(app, imagePath, banner), "meta_image_url": app.publicImageURL(ptrString(metaImage)),
		"badge_label": ptrString(badge), "color_hex": stringOr(color, "#FF7A00"),
		"discount_type": discountType, "discount_value": discount, "discount_label": campaignDiscountLabel(discountType, discount),
		"starts_at": nullTime(starts), "ends_at": nullTime(ends), "coupons_count": couponsCount, "coupons": []map[string]any{}, "seo": parseJSONMap(seo),
	})
}

func (app *App) handlePages(w http.ResponseWriter, r *http.Request) {
	writeData(w, http.StatusOK, []map[string]any{})
}
func (app *App) handlePageShow(w http.ResponseWriter, r *http.Request) {
	app.handleErr(w, r, ErrNotFound)
}
func (app *App) handleMarketing(w http.ResponseWriter, r *http.Request) {
	ctx, cancel := withTimeout(r, 4*time.Second)
	defer cancel()

	var trackingEnabled int
	var announcement, googleAnalytics, googleAds, googleAdsLabel, googleSite, googleGTM, googleMerchant, googleMaps sql.NullString
	var admobApp, admobIOSBanner, admobIOSInterstitial, admobAndroidBanner, admobAndroidInterstitial sql.NullString
	var metaPixel, metaCatalog, metaBusiness sql.NullString
	var yandexMetrica, yandexVerification, yandexDirect sql.NullString
	var microsoftUET, microsoftClarity, bingVerification sql.NullString
	var tiktokPixel sql.NullString

	err := app.db.QueryRowContext(ctx, `SELECT
			announcement_text,COALESCE(tracking_enabled,0),
			google_analytics_id,google_ads_id,google_ads_conversion_label,google_site_verification,google_gtm_id,google_merchant_id,google_maps_api_key,
			google_admob_app_id,google_admob_ios_banner_unit_id,google_admob_ios_interstitial_unit_id,google_admob_android_banner_unit_id,google_admob_android_interstitial_unit_id,
			meta_pixel_id,meta_catalog_id,meta_business_id,
			yandex_metrica_id,yandex_verification,yandex_direct_counter_id,
			microsoft_uet_tag_id,microsoft_clarity_id,bing_verification,
			tiktok_pixel_id
		FROM marketing_settings
		WHERE tenant_id=?
		LIMIT 1`, app.cfg.TenantID).Scan(
		&announcement, &trackingEnabled,
		&googleAnalytics, &googleAds, &googleAdsLabel, &googleSite, &googleGTM, &googleMerchant, &googleMaps,
		&admobApp, &admobIOSBanner, &admobIOSInterstitial, &admobAndroidBanner, &admobAndroidInterstitial,
		&metaPixel, &metaCatalog, &metaBusiness,
		&yandexMetrica, &yandexVerification, &yandexDirect,
		&microsoftUET, &microsoftClarity, &bingVerification,
		&tiktokPixel,
	)
	if err != nil {
		if errors.Is(err, sql.ErrNoRows) || isMissingTableError(err) {
			writeData(w, http.StatusOK, map[string]any{"tracking_enabled": false})
			return
		}
		app.handleErr(w, r, err)
		return
	}

	if trackingEnabled == 0 {
		writeData(w, http.StatusOK, map[string]any{
			"tracking_enabled":  false,
			"announcement_text": ptrString(announcement),
		})
		return
	}

	payload := map[string]any{
		"tracking_enabled":  true,
		"announcement_text": ptrString(announcement),
	}
	if google := publicStringSection(map[string]sql.NullString{
		"analytics_id":                       googleAnalytics,
		"ads_id":                             googleAds,
		"ads_conversion_label":               googleAdsLabel,
		"site_verification":                  googleSite,
		"gtm_id":                             googleGTM,
		"merchant_id":                        googleMerchant,
		"maps_api_key":                       googleMaps,
		"admob_app_id":                       admobApp,
		"admob_ios_banner_unit_id":           admobIOSBanner,
		"admob_ios_interstitial_unit_id":     admobIOSInterstitial,
		"admob_android_banner_unit_id":       admobAndroidBanner,
		"admob_android_interstitial_unit_id": admobAndroidInterstitial,
	}); len(google) > 0 {
		payload["google"] = google
	}
	if meta := publicStringSection(map[string]sql.NullString{
		"pixel_id":    metaPixel,
		"catalog_id":  metaCatalog,
		"business_id": metaBusiness,
	}); len(meta) > 0 {
		payload["meta"] = meta
	}
	if yandex := publicStringSection(map[string]sql.NullString{
		"metrica_id":        yandexMetrica,
		"verification":      yandexVerification,
		"direct_counter_id": yandexDirect,
	}); len(yandex) > 0 {
		payload["yandex"] = yandex
	}
	if microsoft := publicStringSection(map[string]sql.NullString{
		"uet_tag_id":        microsoftUET,
		"clarity_id":        microsoftClarity,
		"bing_verification": bingVerification,
	}); len(microsoft) > 0 {
		payload["microsoft"] = microsoft
	}
	if tiktok := publicStringSection(map[string]sql.NullString{
		"pixel_id": tiktokPixel,
	}); len(tiktok) > 0 {
		payload["tiktok"] = tiktok
	}

	writeData(w, http.StatusOK, payload)
}

func publicStringSection(values map[string]sql.NullString) map[string]string {
	out := map[string]string{}
	for key, value := range values {
		if value.Valid && strings.TrimSpace(value.String) != "" {
			out[key] = strings.TrimSpace(value.String)
		}
	}
	return out
}

// GET /api/v1/content/stories
// Admin tarafında StoryController üzerinden yönetilen story kayıtlarını döner.
// Tablo: stories (id, tenant_id, title, subtitle, image_path, category_slug,
// custom_url, gradient_start, gradient_end, icon, sort_order, is_active).
func (app *App) handleStories(w http.ResponseWriter, r *http.Request) {
	ctx, cancel := withTimeout(r, 4*time.Second)
	defer cancel()
	channelFilter := contentChannelFilter(r.URL.Query().Get("channel"))
	rows, err := app.db.QueryContext(ctx, `SELECT id,title,subtitle,image_path,category_slug,custom_url,gradient_start,gradient_end,icon,sort_order
		FROM stories
		WHERE tenant_id=? AND is_active=1`+channelFilter+`
		ORDER BY sort_order ASC, id DESC
		LIMIT 30`, app.cfg.TenantID)
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	defer rows.Close()

	items := []map[string]any{}
	for rows.Next() {
		var id int64
		var gradStart, gradEnd, icon string
		var title, subtitle, imagePath, categorySlug, customURL sql.NullString
		var sortOrder int
		if err := rows.Scan(&id, &title, &subtitle, &imagePath, &categorySlug, &customURL, &gradStart, &gradEnd, &icon, &sortOrder); err != nil {
			app.handleErr(w, r, err)
			return
		}
		items = append(items, map[string]any{
			"id":              id,
			"title":           ptrString(title),
			"subtitle":        ptrString(subtitle),
			"image_url":       storyImageURL(app.cfg.CDNURL, imagePath),
			"cover_image_url": storyImageURL(app.cfg.CDNURL, imagePath),
			"category_slug":   ptrString(categorySlug),
			"custom_url":      ptrString(customURL),
			"deep_link":       storyDeepLink(categorySlug, customURL),
			"gradient_start":  gradStart,
			"gradient_end":    gradEnd,
			"icon":            icon,
			"sort_order":      sortOrder,
		})
	}
	writeData(w, http.StatusOK, items)
}

func storyImageURL(cdnURL string, imagePath sql.NullString) any {
	if !imagePath.Valid || imagePath.String == "" {
		return nil
	}
	path := imagePath.String
	if cdnURL != "" {
		return cdnURL + "/storage/" + path
	}
	return "/storage/" + path
}

func contentChannelFilter(channel string) string {
	switch strings.ToLower(strings.TrimSpace(channel)) {
	case "mobile":
		return " AND show_on_mobile=1"
	case "web":
		return " AND show_on_web=1"
	default:
		return ""
	}
}

func campaignImageURL(app *App, imagePath, banner sql.NullString) any {
	if imagePath.Valid && strings.TrimSpace(imagePath.String) != "" {
		return storyImageURL(app.cfg.CDNURL, imagePath)
	}
	return app.publicImageURL(ptrString(banner))
}

func stringOr(value sql.NullString, fallback string) string {
	if value.Valid && strings.TrimSpace(value.String) != "" {
		return value.String
	}
	return fallback
}

func campaignDiscountLabel(discountType string, discount int64) string {
	if discountType == "percent" {
		return "%" + strconv.FormatInt(discount, 10) + " İndirim"
	}
	return moneyTRY(discount) + " İndirim"
}

func storyDeepLink(categorySlug, customURL sql.NullString) any {
	if customURL.Valid && customURL.String != "" {
		return customURL.String
	}
	if categorySlug.Valid && categorySlug.String != "" {
		return "/categories/" + categorySlug.String
	}
	return nil
}

func nullTime(t sql.NullTime) any {
	if t.Valid {
		return t.Time.Format(time.RFC3339)
	}
	return nil
}
