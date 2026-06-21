package api

import (
	"context"
	"fmt"
	"net/http"
	"strconv"
	"strings"
	"time"
)

type CustomerSnapshotResponse struct {
	Identity    RequestIdentity `json:"identity"`
	User        *User           `json:"user,omitempty"`
	Cart        *CartData       `json:"cart,omitempty"`
	SyncVersion int64           `json:"sync_version"`
	ServerAt    time.Time       `json:"server_at"`
}

type CustomerSyncStateResponse struct {
	Identity      RequestIdentity `json:"identity"`
	ClientVersion int64           `json:"client_version"`
	ServerVersion int64           `json:"server_version"`
	HasChanges    bool            `json:"has_changes"`
	Reason        *string         `json:"reason,omitempty"`
	UpdatedAt     *time.Time      `json:"updated_at,omitempty"`
	ServerAt      time.Time       `json:"server_at"`
}

func (app *App) handleCustomerSnapshot(w http.ResponseWriter, r *http.Request) {
	ctx, cancel := withTimeout(r, 5*time.Second)
	defer cancel()

	identity := requestIdentity(r.Context())
	var user *User
	if token := requestBearerToken(r); token != "" {
		resolved, err := app.resolveUser(ctx, token)
		if err != nil {
			app.handleErr(w, r, err)
			return
		}
		user = resolved
		if user != nil && user.CustomerUID == nil && identity.CustomerUID != "" {
			user.CustomerUID = &identity.CustomerUID
		}
	}

	cartID, err := app.identityFromRequest(ctx, r)
	var cart *CartData
	if err == nil {
		cartData, err := app.cart(ctx, cartID)
		if err != nil {
			app.handleErr(w, r, err)
			return
		}
		setCartIdentityHeaders(w, cartData)
		cart = &cartData
	} else if user != nil {
		app.handleErr(w, r, err)
		return
	}

	syncVersion := int64(0)
	if user != nil && user.SyncVersion > syncVersion {
		syncVersion = user.SyncVersion
	}
	if cart != nil && cart.SyncVersion > syncVersion {
		syncVersion = cart.SyncVersion
	}
	w.Header().Set("X-Customer-Sync-Version", strconv.FormatInt(syncVersion, 10))
	writeData(w, http.StatusOK, CustomerSnapshotResponse{Identity: identity, User: user, Cart: cart, SyncVersion: syncVersion, ServerAt: time.Now().UTC()})
}

func (app *App) handleCustomerSyncState(w http.ResponseWriter, r *http.Request) {
	ctx, cancel := withTimeout(r, 3*time.Second)
	defer cancel()

	identity := requestIdentity(r.Context())
	clientVersion := parseInt64Query(r, "since", 0, 0, 0)
	syncID, err := app.lightweightSyncIdentity(ctx, r, identity)
	if err != nil {
		app.handleErr(w, r, err)
		return
	}

	serverVersion, reason, updatedAt := app.customerSyncState(ctx, syncID)
	w.Header().Set("X-Customer-Sync-Version", strconv.FormatInt(serverVersion, 10))
	if serverVersion > 0 {
		w.Header().Set("ETag", fmt.Sprintf("W/\"customer-%d\"", serverVersion))
	}

	writeData(w, http.StatusOK, CustomerSyncStateResponse{
		Identity:      identity,
		ClientVersion: clientVersion,
		ServerVersion: serverVersion,
		HasChanges:    serverVersion > clientVersion,
		Reason:        reason,
		UpdatedAt:     updatedAt,
		ServerAt:      time.Now().UTC(),
	})
}

func (app *App) lightweightSyncIdentity(ctx context.Context, r *http.Request, identity RequestIdentity) (CartIdentity, error) {
	if token := requestBearerToken(r); token != "" {
		user, err := app.resolveUser(ctx, token)
		if err != nil {
			return CartIdentity{}, err
		}
		if user != nil {
			uid := derefString(user.CustomerUID)
			if uid == "" {
				uid = identity.CustomerUID
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
	if cartToken == "" && identity.CustomerUID != "" {
		activeToken, err := app.activeCartToken(ctx, identity.CustomerUID)
		if err != nil {
			return CartIdentity{}, err
		}
		cartToken = activeToken
	}

	id := CartIdentity{CustomerUID: stringPtr(identity.CustomerUID)}
	if cartToken != "" {
		id.CartToken = &cartToken
	}
	return id, nil
}

func (app *App) customerSyncState(ctx context.Context, id CartIdentity) (int64, *string, *time.Time) {
	if id.UserID != nil {
		version, reason, updatedAt, ok := app.customerSyncStateByUser(ctx, *id.UserID)
		if ok {
			return version, reason, updatedAt
		}
	}
	if id.CustomerUID != nil && *id.CustomerUID != "" {
		version, reason, updatedAt, ok := app.customerSyncStateByCustomerUID(ctx, *id.CustomerUID)
		if ok {
			return version, reason, updatedAt
		}
	}
	return 0, nil, nil
}

func (app *App) customerSyncStateByUser(ctx context.Context, userID int64) (int64, *string, *time.Time, bool) {
	var version int64
	var reason string
	var updatedAt time.Time
	err := app.db.QueryRowContext(ctx, `SELECT COALESCE(version,0), COALESCE(reason,''), updated_at
		FROM customer_sync_versions
		WHERE tenant_id=? AND user_id=? AND scope='customer'
		ORDER BY updated_at DESC LIMIT 1`, app.cfg.TenantID, userID).Scan(&version, &reason, &updatedAt)
	if err == nil {
		return version, stringPtrOrNil(reason), &updatedAt, true
	}
	var userVersion int64
	if err := app.db.QueryRowContext(ctx, `SELECT COALESCE(sync_version,0) FROM users WHERE id=? LIMIT 1`, userID).Scan(&userVersion); err == nil && userVersion > 0 {
		return userVersion, nil, nil, true
	}
	return 0, nil, nil, false
}

func (app *App) customerSyncStateByCustomerUID(ctx context.Context, customerUID string) (int64, *string, *time.Time, bool) {
	var version int64
	var reason string
	var updatedAt time.Time
	err := app.db.QueryRowContext(ctx, `SELECT COALESCE(version,0), COALESCE(reason,''), updated_at
		FROM customer_sync_versions
		WHERE tenant_id=? AND customer_uid=? AND scope='customer'
		ORDER BY updated_at DESC LIMIT 1`, app.cfg.TenantID, customerUID).Scan(&version, &reason, &updatedAt)
	if err == nil {
		return version, stringPtrOrNil(reason), &updatedAt, true
	}
	return 0, nil, nil, false
}

func stringPtrOrNil(value string) *string {
	value = strings.TrimSpace(value)
	if value == "" {
		return nil
	}
	return &value
}
