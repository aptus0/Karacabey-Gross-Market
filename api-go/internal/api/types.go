package api

import "time"

type Product struct {
	ID                  int64          `json:"id"`
	Name                string         `json:"name"`
	Slug                string         `json:"slug"`
	Description         *string        `json:"description,omitempty"`
	Brand               *string        `json:"brand,omitempty"`
	Barcode             *string        `json:"barcode,omitempty"`
	PriceCents          int64          `json:"price_cents"`
	Price               string         `json:"price"`
	CompareAtPriceCents *int64         `json:"compare_at_price_cents,omitempty"`
	StockQuantity       int            `json:"stock_quantity"`
	ImageURL            *string        `json:"image_url,omitempty"`
	SEO                 map[string]any `json:"seo,omitempty"`
	Categories          []CategoryMini `json:"categories,omitempty"`
}

type CategoryMini struct {
	ID   int64  `json:"id"`
	Name string `json:"name"`
	Slug string `json:"slug"`
}

type Category struct {
	ID           int64      `json:"id"`
	ParentID     *int64     `json:"parent_id,omitempty"`
	Name         string     `json:"name"`
	Slug         string     `json:"slug"`
	Description  *string    `json:"description,omitempty"`
	ImageURL     *string    `json:"image_url,omitempty"`
	ProductCount int64      `json:"product_count"`
	Children     []Category `json:"children"`
}

type CategoryProduct struct {
	ID         int64   `json:"id"`
	Name       string  `json:"name"`
	Slug       string  `json:"slug"`
	PriceCents int64   `json:"price_cents"`
	Price      string  `json:"price"`
	ImageURL   *string `json:"image_url,omitempty"`
}

type CategoryDetail struct {
	ID           int64             `json:"id"`
	ParentID     *int64            `json:"parent_id"`
	Name         string            `json:"name"`
	Slug         string            `json:"slug"`
	Description  *string           `json:"description,omitempty"`
	ImageURL     *string           `json:"image_url,omitempty"`
	SEO          map[string]any    `json:"seo,omitempty"`
	ProductCount int64             `json:"product_count"`
	Children     []Category        `json:"children"`
	Products     []CategoryProduct `json:"products"`
}

type Pagination struct {
	Data        []Product `json:"data"`
	Total       int64     `json:"total"`
	PerPage     int       `json:"per_page"`
	CurrentPage int       `json:"current_page"`
	LastPage    int       `json:"last_page"`
	From        *int      `json:"from"`
	To          *int      `json:"to"`
}

type ProductFilter struct {
	Query    string
	Category string
	Sort     string
	InStock  bool
	PriceMin *int64
	PriceMax *int64
	Page     int
	PerPage  int
}

type CartProduct struct {
	ID            int64   `json:"id"`
	Name          string  `json:"name"`
	Slug          string  `json:"slug"`
	Brand         *string `json:"brand,omitempty"`
	PriceCents    int64   `json:"price_cents"`
	Price         string  `json:"price"`
	StockQuantity int     `json:"stock_quantity"`
	ImageURL      *string `json:"image_url,omitempty"`
}

type CartLineItem struct {
	ID             int64       `json:"id"`
	Quantity       int         `json:"quantity"`
	LineTotalCents int64       `json:"line_total_cents"`
	Product        CartProduct `json:"product"`
}

type AppliedCoupon struct {
	Code          string `json:"code"`
	DiscountType  string `json:"discount_type"`
	DiscountValue int64  `json:"discount_value"`
	DiscountCents int64  `json:"discount_cents"`
	TotalCents    int64  `json:"total_cents"`
}

type CartData struct {
	CustomerUID   *string        `json:"customer_uid,omitempty"`
	SyncVersion   int64          `json:"sync_version,omitempty"`
	CartToken     *string        `json:"cart_token"`
	Items         []CartLineItem `json:"items"`
	AppliedCoupon *AppliedCoupon `json:"applied_coupon"`
	SubtotalCents int64          `json:"subtotal_cents"`
	TotalCents    int64          `json:"total_cents"`
}

type CartIdentity struct {
	UserID      *int64
	CartToken   *string
	CustomerUID *string
}

type CheckoutRequest struct {
	OrderType string `json:"order_type"`
	Source    string `json:"source"`
	Customer  struct {
		Name        string `json:"name"`
		Email       string `json:"email"`
		Phone       string `json:"phone"`
		TCIdentity  string `json:"tc_identity"`
		CompanyName string `json:"company_name"`
		TaxOffice   string `json:"tax_office"`
		TaxNumber   string `json:"tax_number"`
	} `json:"customer"`
	Invoice struct {
		Type        string `json:"type"`
		TCIdentity  string `json:"tc_identity"`
		CompanyName string `json:"company_name"`
		TaxOffice   string `json:"tax_office"`
		TaxNumber   string `json:"tax_number"`
	} `json:"invoice"`
	Shipping struct {
		City     string  `json:"city"`
		District string  `json:"district"`
		Address  string  `json:"address"`
		Carrier  string  `json:"carrier"`
		Lat      float64 `json:"lat"`
		Lng      float64 `json:"lng"`
	} `json:"shipping"`
	ShippingQuote struct {
		Carrier       string `json:"carrier"`
		LocalDelivery bool   `json:"local_delivery"`
		ShippingCents int64  `json:"shipping_cents"`
	} `json:"shipping_quote"`
	CartToken   string              `json:"cart_token"`
	CouponCode  string              `json:"coupon_code"`
	AddressID   string              `json:"address_id"`
	CheckoutKey string              `json:"checkout_key"`
	CheckoutUID string              `json:"checkout_uid"`
	PaymentUID  string              `json:"payment_uid"`
	PaymentFlow string              `json:"payment_flow"`
	Items       []CheckoutLineInput `json:"items"`
}

type CheckoutLineInput struct {
	ProductID int64 `json:"product_id"`
	Quantity  int   `json:"quantity"`
}

type PayTRMobilePaymentRequest struct {
	OrderID     string                  `json:"orderId"`
	UserID      string                  `json:"userId"`
	Email       string                  `json:"email"`
	Phone       string                  `json:"phone"`
	AmountKurus int64                   `json:"amountKurus"`
	Currency    string                  `json:"currency"`
	AddressID   string                  `json:"addressId"`
	Basket      []PayTRMobileBasketItem `json:"basket"`
}

type PayTRMobileBasketItem struct {
	ProductID      string `json:"productId"`
	Name           string `json:"name"`
	Quantity       int    `json:"quantity"`
	UnitPriceKurus int64  `json:"unitPriceKurus"`
}

type CheckoutResponse struct {
	MerchantOID        string              `json:"merchant_oid,omitempty"`
	OrderID            int64               `json:"order_id,omitempty"`
	CheckoutURL        string              `json:"checkout_url,omitempty"`
	IframeSrc          string              `json:"iframe_src,omitempty"`
	PaymentID          int64               `json:"payment_id,omitempty"`
	Status             string              `json:"status,omitempty"`
	TotalCents         int64               `json:"total_cents,omitempty"`
	Currency           string              `json:"currency,omitempty"`
	PaymentFlow        string              `json:"payment_flow,omitempty"`
	CashOnDelivery     bool                `json:"cash_on_delivery,omitempty"`
	PaymentUnavailable bool                `json:"payment_unavailable,omitempty"`
	Message            string              `json:"message,omitempty"`
	ProviderReason     string              `json:"provider_reason,omitempty"`
	TraceID            string              `json:"trace_id,omitempty"`
	DirectPayment      *PayTRDirectPayment `json:"direct_payment,omitempty"`
}

type PayTRDirectPayment struct {
	PostURL string            `json:"post_url"`
	Fields  map[string]string `json:"fields"`
}

type OrderRecord struct {
	ID              int64
	PaymentID       int64
	MerchantOID     string
	CustomerEmail   string
	CustomerName    string
	CustomerPhone   string
	ShippingAddress string
	TotalCents      int64
	SubtotalCents   int64
	DiscountCents   int64
	UserID          *int64
	CartToken       *string
	Items           []OrderItemRecord
}

type OrderItemRecord struct {
	ProductID      *int64 `json:"product_id,omitempty"`
	Name           string `json:"name"`
	UnitPriceCents int64  `json:"unit_price_cents"`
	Quantity       int    `json:"quantity"`
	LineTotalCents int64  `json:"line_total_cents"`
}

type User struct {
	ID                    int64      `json:"id"`
	PublicUID             *string    `json:"public_uid,omitempty"`
	CustomerUID           *string    `json:"customer_uid,omitempty"`
	SyncVersion           int64      `json:"sync_version,omitempty"`
	LoyaltyPoints         int64      `json:"loyalty_points"`
	LoyaltyPointsLifetime int64      `json:"loyalty_points_lifetime"`
	IsVIP                 bool       `json:"is_vip"`
	VIPStartedAt          *time.Time `json:"vip_started_at,omitempty"`
	VIPExpiresAt          *time.Time `json:"vip_expires_at,omitempty"`
	AdFree                bool       `json:"ad_free"`
	Name                  string     `json:"name"`
	Phone                 *string    `json:"phone"`
	Email                 *string    `json:"email"`
	AvatarURL             *string    `json:"avatar_url"`
	EmailVerifiedAt       *time.Time `json:"email_verified_at"`
}
