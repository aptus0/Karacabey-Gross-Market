package api

import (
	"context"
	"database/sql"
	"encoding/json"
	"errors"
	"strings"
)

func (app *App) awardMobilePurchasePointTx(ctx context.Context, tx *sql.Tx, orderID int64) error {
	var tenantID int64
	var userID sql.NullInt64
	var customerUID, checkoutUID, paymentUID, metadataRaw sql.NullString
	err := tx.QueryRowContext(ctx, `SELECT tenant_id,user_id,customer_uid,checkout_uid,payment_uid,metadata
		FROM orders WHERE id=? FOR UPDATE`, orderID).
		Scan(&tenantID, &userID, &customerUID, &checkoutUID, &paymentUID, &metadataRaw)
	if err != nil {
		if errors.Is(err, sql.ErrNoRows) {
			return nil
		}
		return err
	}
	if !userID.Valid {
		return nil
	}
	if !isMobileOrderMetadata(metadataRaw.String, checkoutUID.String, paymentUID.String) {
		return nil
	}

	var pointsBalance, lifetimePoints int64
	if err := tx.QueryRowContext(ctx, `SELECT COALESCE(loyalty_points,0),COALESCE(loyalty_points_lifetime,0)
		FROM users WHERE id=? FOR UPDATE`, userID.Int64).Scan(&pointsBalance, &lifetimePoints); err != nil {
		return err
	}

	nextBalance := pointsBalance + 1
	eventMetadata, _ := json.Marshal(map[string]any{
		"source":       "go_paytr_callback",
		"points_rule":  "mobile_paid_order_fixed_1",
		"checkout_uid": nullStringValue(checkoutUID),
		"payment_uid":  nullStringValue(paymentUID),
	})

	result, err := tx.ExecContext(ctx, `INSERT IGNORE INTO customer_reward_events
		(tenant_id,user_id,customer_uid,order_id,event_type,points_delta,balance_after,reason,metadata,created_at,updated_at)
		VALUES (?,?,?,?,?,?,?,?,?,NOW(),NOW())`,
		tenantID,
		userID.Int64,
		nullableString(nullStringValue(customerUID)),
		orderID,
		"mobile_purchase",
		1,
		nextBalance,
		"Mobil alışveriş puanı",
		string(eventMetadata),
	)
	if err != nil {
		if isMissingTableError(err) {
			return nil
		}
		return err
	}

	affected, _ := result.RowsAffected()
	if affected < 1 {
		return nil
	}

	_, err = tx.ExecContext(ctx, `UPDATE users
		SET loyalty_points=?, loyalty_points_lifetime=?, sync_version=?, updated_at=NOW()
		WHERE id=?`, nextBalance, lifetimePoints+1, syncVersionNow(), userID.Int64)
	return err
}

func isMobileOrderMetadata(rawMetadata, checkoutUID, paymentUID string) bool {
	metadata := map[string]any{}
	_ = json.Unmarshal([]byte(rawMetadata), &metadata)

	for _, key := range []string{"source", "order_source", "channel", "platform", "created_from"} {
		if normalizeOrderSource(metadataString(metadata[key])) != "web" {
			return true
		}
	}

	checkoutUID = strings.ToLower(strings.TrimSpace(checkoutUID))
	paymentUID = strings.ToLower(strings.TrimSpace(paymentUID))
	return strings.HasPrefix(checkoutUID, "ios-") ||
		strings.HasPrefix(paymentUID, "ios-") ||
		strings.HasPrefix(checkoutUID, "android-") ||
		strings.HasPrefix(paymentUID, "android-")
}

func metadataString(value any) string {
	switch v := value.(type) {
	case string:
		return v
	default:
		return ""
	}
}

func nullStringValue(value sql.NullString) string {
	if !value.Valid {
		return ""
	}
	return value.String
}
