package api

import (
	"net/http"
	"time"
)

func setCartIdentityHeaders(w http.ResponseWriter, cart CartData) {
	if cart.CartToken != nil && *cart.CartToken != "" {
		w.Header().Set("X-Cart-Token", *cart.CartToken)
	}
	if cart.CustomerUID != nil && *cart.CustomerUID != "" {
		w.Header().Set("X-Customer-UID", *cart.CustomerUID)
	}
}

func (app *App) handleCartShow(w http.ResponseWriter, r *http.Request) {
	ctx, cancel := withTimeout(r, 8*time.Second)
	defer cancel()
	id, err := app.identityFromRequest(ctx, r)
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	cart, err := app.cart(ctx, id)
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	setCartIdentityHeaders(w, cart)
	writeJSON(w, http.StatusOK, cart)
}

func (app *App) handleCartAdd(w http.ResponseWriter, r *http.Request) {
	ctx, cancel := withTimeout(r, 10*time.Second)
	defer cancel()
	id, err := app.identityFromRequest(ctx, r)
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	var body struct {
		ProductID int64 `json:"product_id"`
		Quantity  int   `json:"quantity"`
	}
	if err := parseJSON(r, &body); err != nil {
		writeError(w, r, http.StatusUnprocessableEntity, err.Error())
		return
	}
	if body.ProductID <= 0 {
		writeError(w, r, http.StatusUnprocessableEntity, "Ürün seçimi geçersiz.")
		return
	}
	cart, err := app.addCartItem(ctx, id, body.ProductID, body.Quantity)
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	setCartIdentityHeaders(w, cart)
	writeJSON(w, http.StatusOK, cart)
}

func (app *App) handleCartUpdate(w http.ResponseWriter, r *http.Request) {
	ctx, cancel := withTimeout(r, 10*time.Second)
	defer cancel()
	id, err := app.identityFromRequest(ctx, r)
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	itemID, err := parsePathID(r, "id")
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	var body struct {
		Quantity int `json:"quantity"`
	}
	if err := parseJSON(r, &body); err != nil {
		writeError(w, r, http.StatusUnprocessableEntity, err.Error())
		return
	}
	cart, err := app.updateCartItem(ctx, id, itemID, body.Quantity)
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	setCartIdentityHeaders(w, cart)
	writeJSON(w, http.StatusOK, cart)
}

func (app *App) handleCartDelete(w http.ResponseWriter, r *http.Request) {
	ctx, cancel := withTimeout(r, 10*time.Second)
	defer cancel()
	id, err := app.identityFromRequest(ctx, r)
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	itemID, err := parsePathID(r, "id")
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	cart, err := app.deleteCartItem(ctx, id, itemID)
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	setCartIdentityHeaders(w, cart)
	writeJSON(w, http.StatusOK, cart)
}

func (app *App) handleCartClear(w http.ResponseWriter, r *http.Request) {
	ctx, cancel := withTimeout(r, 10*time.Second)
	defer cancel()
	id, err := app.identityFromRequest(ctx, r)
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	cart, err := app.clearCart(ctx, id)
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	setCartIdentityHeaders(w, cart)
	writeJSON(w, http.StatusOK, cart)
}

func (app *App) handleCouponApply(w http.ResponseWriter, r *http.Request) {
	ctx, cancel := withTimeout(r, 10*time.Second)
	defer cancel()
	id, err := app.identityFromRequest(ctx, r)
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	var body struct {
		Code string `json:"code"`
	}
	if err := parseJSON(r, &body); err != nil {
		writeError(w, r, http.StatusUnprocessableEntity, err.Error())
		return
	}
	coupon, err := app.applyCoupon(ctx, id, body.Code)
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	if coupon == nil {
		writeError(w, r, http.StatusUnprocessableEntity, "Kupon uygulanamadı.")
		return
	}
	writeData(w, http.StatusOK, coupon)
}

func (app *App) handleCouponRemove(w http.ResponseWriter, r *http.Request) {
	ctx, cancel := withTimeout(r, 4*time.Second)
	defer cancel()
	id, err := app.identityFromRequest(ctx, r)
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	if err := app.removeCoupon(ctx, id); err != nil {
		app.handleErr(w, r, err)
		return
	}
	writeData(w, http.StatusOK, map[string]bool{"removed": true})
}
