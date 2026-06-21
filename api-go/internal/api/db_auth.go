package api

import (
	"context"
	"database/sql"
	"errors"
	"strings"
	"time"

	"golang.org/x/crypto/bcrypt"
)

func (app *App) resolveUser(ctx context.Context, rToken string) (*User, error) {
	if rToken == "" {
		return nil, nil
	}
	tokenHash := sha256Hex(rToken)
	row := app.db.QueryRowContext(ctx, `SELECT u.id,u.public_uid,u.customer_uid,u.sync_version,
			COALESCE(u.loyalty_points,0),COALESCE(u.loyalty_points_lifetime,0),COALESCE(u.is_vip,0),u.vip_started_at,u.vip_expires_at,
			u.name,u.phone,u.email,u.avatar_url,u.email_verified_at
        FROM api_tokens t JOIN users u ON u.id=t.user_id
        WHERE t.token_hash=? AND (t.expires_at IS NULL OR t.expires_at>NOW()) LIMIT 1`, tokenHash)
	var u User
	var publicUID, customerUID, phone, email, avatar sql.NullString
	var verified, vipStarted, vipExpires sql.NullTime
	var isVIP sql.NullBool
	if err := row.Scan(&u.ID, &publicUID, &customerUID, &u.SyncVersion, &u.LoyaltyPoints, &u.LoyaltyPointsLifetime, &isVIP, &vipStarted, &vipExpires, &u.Name, &phone, &email, &avatar, &verified); err != nil {
		if errors.Is(err, sql.ErrNoRows) {
			return nil, ErrUnauthorized
		}
		return nil, err
	}
	u.PublicUID = ptrString(publicUID)
	u.CustomerUID = ptrString(customerUID)
	u.Phone = ptrString(phone)
	u.Email = ptrString(email)
	u.AvatarURL = ptrString(avatar)
	if verified.Valid {
		u.EmailVerifiedAt = &verified.Time
	}
	applyUserVIPFields(&u, isVIP, vipStarted, vipExpires)
	go func() {
		_, _ = app.db.Exec(`UPDATE api_tokens SET last_used_at=NOW(), updated_at=NOW() WHERE token_hash=?`, tokenHash)
	}()
	return &u, nil
}

func applyUserVIPFields(u *User, isVIP sql.NullBool, started, expires sql.NullTime) {
	u.IsVIP = isVIP.Valid && isVIP.Bool
	if started.Valid {
		u.VIPStartedAt = &started.Time
	}
	if expires.Valid {
		u.VIPExpiresAt = &expires.Time
	}
	u.AdFree = u.IsVIP && (u.VIPExpiresAt == nil || u.VIPExpiresAt.After(time.Now()))
}

func (app *App) createAPIToken(ctx context.Context, userID int64, name string) (string, time.Time, error) {
	token, err := newToken()
	if err != nil {
		return "", time.Time{}, err
	}
	expiresAt := time.Now().Add(30 * 24 * time.Hour)
	_, err = app.db.ExecContext(ctx, `INSERT INTO api_tokens (user_id,name,token_hash,abilities,expires_at,created_at,updated_at) VALUES (?,?,?,?,?,NOW(),NOW())`, userID, name, sha256Hex(token), `["*"]`, expiresAt)
	if err != nil {
		return "", time.Time{}, err
	}
	return token, expiresAt, nil
}

func compareBcrypt(hash, password string) bool {
	normalized := hash
	if strings.HasPrefix(normalized, "$2y$") {
		normalized = "$2a$" + strings.TrimPrefix(normalized, "$2y$")
	}
	return bcrypt.CompareHashAndPassword([]byte(normalized), []byte(password)) == nil
}

func passwordHash(password string) (string, error) {
	raw, err := bcrypt.GenerateFromPassword([]byte(password), 12)
	if err != nil {
		return "", err
	}
	return string(raw), nil
}
