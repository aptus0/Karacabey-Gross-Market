package api

import (
	"context"
	"crypto/subtle"
	"database/sql"
	"encoding/json"
	"errors"
	"fmt"
	"net/http"
	"strings"
	"time"
)

type supportConversation struct {
	ID            int64
	UserID        sql.NullInt64
	PublicToken   string
	Status        string
	Subject       sql.NullString
	CustomerName  sql.NullString
	LastMessageAt sql.NullTime
}

type supportMessage struct {
	ID         int64
	SenderType string
	SenderName sql.NullString
	Body       string
	CreatedAt  time.Time
}

func (app *App) handleSupportConversationCreate(w http.ResponseWriter, r *http.Request) {
	ctx, cancel := withTimeout(r, 6*time.Second)
	defer cancel()

	var body struct {
		Message    string         `json:"message"`
		Name       string         `json:"name"`
		Email      string         `json:"email"`
		Phone      string         `json:"phone"`
		Subject    string         `json:"subject"`
		GuestToken string         `json:"guest_token"`
		Metadata   map[string]any `json:"metadata"`
	}
	if err := parseJSON(r, &body); err != nil {
		writeError(w, r, http.StatusUnprocessableEntity, err.Error())
		return
	}
	body.Message = strings.TrimSpace(body.Message)
	if len([]rune(body.Message)) < 2 || len([]rune(body.Message)) > 1200 {
		writeError(w, r, http.StatusUnprocessableEntity, "Mesaj 2-1200 karakter arasında olmalıdır.")
		return
	}
	if len([]rune(body.Name)) > 120 || len([]rune(body.Email)) > 160 || len([]rune(body.Phone)) > 40 || len([]rune(body.Subject)) > 160 {
		writeError(w, r, http.StatusUnprocessableEntity, "Destek bilgileri izin verilen uzunluğu aşıyor.")
		return
	}

	user, err := app.optionalUser(ctx, r)
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	publicToken, err := newToken()
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	identity := requestIdentity(r.Context())
	guestToken := firstNonEmpty(strings.TrimSpace(body.GuestToken), identity.CustomerUID, newPublicUID("cus"))
	name := strings.TrimSpace(body.Name)
	email := strings.TrimSpace(body.Email)
	phone := strings.TrimSpace(body.Phone)
	var userID *int64
	if user != nil {
		userID = &user.ID
		name = firstNonEmpty(name, user.Name)
		email = firstNonEmpty(email, derefString(user.Email))
		phone = firstNonEmpty(phone, derefString(user.Phone))
	}
	meta := body.Metadata
	if meta == nil {
		meta = map[string]any{}
	}
	meta["ip"] = clientIP(r)
	meta["user_agent"] = truncate(r.UserAgent(), 400)
	rawMeta, _ := json.Marshal(meta)

	tx, err := app.db.BeginTx(ctx, &sql.TxOptions{Isolation: sql.LevelReadCommitted})
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	defer tx.Rollback()
	res, err := tx.ExecContext(ctx, `INSERT INTO support_conversations
		(tenant_id,user_id,public_token,guest_token,status,source,customer_name,customer_email,customer_phone,subject,metadata,created_at,updated_at)
		VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())`,
		app.cfg.TenantID, sqlNullInt64Ptr(userID), publicToken, nullableString(guestToken), "open", "storefront",
		nullableString(name), nullableString(email), nullableString(phone), nullableString(firstNonEmpty(body.Subject, "Müşteri desteği")), string(rawMeta))
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	conversationID, _ := res.LastInsertId()
	if _, err := app.insertSupportCustomerMessage(ctx, tx, conversationID, userID, firstNonEmpty(name, "Müşteri"), body.Message); err != nil {
		app.handleErr(w, r, err)
		return
	}
	if err := tx.Commit(); err != nil {
		app.handleErr(w, r, err)
		return
	}

	conversation, err := app.findSupportConversation(ctx, conversationID)
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	writeData(w, http.StatusCreated, serializeSupportConversation(conversation))
}

func (app *App) handleSupportMessages(w http.ResponseWriter, r *http.Request) {
	ctx, cancel := withTimeout(r, 5*time.Second)
	defer cancel()
	conversation, err := app.authorizedSupportConversation(ctx, r)
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	messages, err := app.listSupportMessages(ctx, conversation.ID, 0)
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	writeData(w, http.StatusOK, map[string]any{
		"conversation": serializeSupportConversation(conversation),
		"messages":     serializeSupportMessages(messages),
	})
}

func (app *App) handleSupportMessageCreate(w http.ResponseWriter, r *http.Request) {
	ctx, cancel := withTimeout(r, 6*time.Second)
	defer cancel()
	conversation, err := app.authorizedSupportConversation(ctx, r)
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	var body struct {
		Message string `json:"message"`
	}
	if err := parseJSON(r, &body); err != nil {
		writeError(w, r, http.StatusUnprocessableEntity, err.Error())
		return
	}
	body.Message = strings.TrimSpace(body.Message)
	if len([]rune(body.Message)) < 1 || len([]rune(body.Message)) > 1200 {
		writeError(w, r, http.StatusUnprocessableEntity, "Mesaj 1-1200 karakter arasında olmalıdır.")
		return
	}
	user, err := app.optionalUser(ctx, r)
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	var userID *int64
	if user != nil {
		userID = &user.ID
	}
	tx, err := app.db.BeginTx(ctx, &sql.TxOptions{Isolation: sql.LevelReadCommitted})
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	defer tx.Rollback()
	message, err := app.insertSupportCustomerMessage(ctx, tx, conversation.ID, userID, firstNonEmpty(conversation.CustomerName.String, "Müşteri"), body.Message)
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	if err := tx.Commit(); err != nil {
		app.handleErr(w, r, err)
		return
	}
	conversation, _ = app.findSupportConversation(ctx, conversation.ID)
	writeData(w, http.StatusOK, map[string]any{
		"conversation": serializeSupportConversation(conversation),
		"message":      serializeSupportMessage(message),
	})
}

func (app *App) handleSupportStream(w http.ResponseWriter, r *http.Request) {
	ctx, cancel := context.WithTimeout(r.Context(), 28*time.Second)
	defer cancel()
	conversation, err := app.authorizedSupportConversation(ctx, r)
	if err != nil {
		app.handleErr(w, r, err)
		return
	}
	controller := http.NewResponseController(w)
	w.Header().Set("Content-Type", "text/event-stream")
	w.Header().Set("Cache-Control", "no-cache, no-transform")
	w.Header().Set("X-Accel-Buffering", "no")
	lastID := parseInt64(r.URL.Query().Get("after_id"), 0)
	ticker := time.NewTicker(2 * time.Second)
	defer ticker.Stop()
	for {
		messages, queryErr := app.listSupportMessages(ctx, conversation.ID, lastID)
		if queryErr != nil && !errors.Is(queryErr, context.Canceled) && !errors.Is(queryErr, context.DeadlineExceeded) {
			return
		}
		for _, message := range messages {
			lastID = message.ID
			raw, _ := json.Marshal(serializeSupportMessage(message))
			_, _ = fmt.Fprintf(w, "event: message\ndata: %s\n\n", raw)
		}
		_, _ = fmt.Fprint(w, "event: heartbeat\ndata: {\"ok\":true}\n\n")
		if err := controller.Flush(); err != nil {
			return
		}
		select {
		case <-ctx.Done():
			return
		case <-ticker.C:
		}
	}
}

func (app *App) optionalUser(ctx context.Context, r *http.Request) (*User, error) {
	token := requestBearerToken(r)
	if token == "" {
		return nil, nil
	}
	user, err := app.resolveUser(ctx, token)
	if err != nil || user == nil {
		return nil, ErrUnauthorized
	}
	return user, nil
}

func (app *App) authorizedSupportConversation(ctx context.Context, r *http.Request) (supportConversation, error) {
	id, err := parsePathID(r, "id")
	if err != nil {
		return supportConversation{}, err
	}
	conversation, err := app.findSupportConversation(ctx, id)
	if err != nil {
		return supportConversation{}, err
	}
	token := firstNonEmpty(r.URL.Query().Get("token"), r.Header.Get("X-Support-Token"))
	if token != "" && subtle.ConstantTimeCompare([]byte(token), []byte(conversation.PublicToken)) == 1 {
		return conversation, nil
	}
	user, err := app.optionalUser(ctx, r)
	if err == nil && user != nil && conversation.UserID.Valid && conversation.UserID.Int64 == user.ID {
		return conversation, nil
	}
	return supportConversation{}, ErrNotFound
}

func (app *App) findSupportConversation(ctx context.Context, id int64) (supportConversation, error) {
	var conversation supportConversation
	err := app.db.QueryRowContext(ctx, `SELECT id,user_id,public_token,status,subject,customer_name,last_message_at
		FROM support_conversations WHERE id=? AND tenant_id=? LIMIT 1`, id, app.cfg.TenantID).
		Scan(&conversation.ID, &conversation.UserID, &conversation.PublicToken, &conversation.Status, &conversation.Subject, &conversation.CustomerName, &conversation.LastMessageAt)
	if errors.Is(err, sql.ErrNoRows) {
		return supportConversation{}, ErrNotFound
	}
	return conversation, err
}

func (app *App) insertSupportCustomerMessage(ctx context.Context, tx *sql.Tx, conversationID int64, userID *int64, senderName, body string) (supportMessage, error) {
	res, err := tx.ExecContext(ctx, `INSERT INTO support_messages
		(support_conversation_id,user_id,sender_type,sender_name,body,created_at,updated_at)
		VALUES (?,?, 'customer',?,?,NOW(),NOW())`, conversationID, sqlNullInt64Ptr(userID), nullableString(senderName), body)
	if err != nil {
		return supportMessage{}, err
	}
	messageID, _ := res.LastInsertId()
	if _, err := tx.ExecContext(ctx, `UPDATE support_conversations
		SET status='open',last_message_preview=?,last_message_at=NOW(),updated_at=NOW() WHERE id=? AND tenant_id=?`,
		truncate(body, 500), conversationID, app.cfg.TenantID); err != nil {
		return supportMessage{}, err
	}
	return supportMessage{ID: messageID, SenderType: "customer", SenderName: sql.NullString{String: senderName, Valid: senderName != ""}, Body: body, CreatedAt: time.Now().UTC()}, nil
}

func (app *App) listSupportMessages(ctx context.Context, conversationID, afterID int64) ([]supportMessage, error) {
	rows, err := app.db.QueryContext(ctx, `SELECT id,sender_type,sender_name,body,created_at
		FROM support_messages WHERE support_conversation_id=? AND id>? ORDER BY id ASC LIMIT 200`, conversationID, afterID)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	messages := make([]supportMessage, 0)
	for rows.Next() {
		var message supportMessage
		if err := rows.Scan(&message.ID, &message.SenderType, &message.SenderName, &message.Body, &message.CreatedAt); err != nil {
			return nil, err
		}
		messages = append(messages, message)
	}
	return messages, rows.Err()
}

func serializeSupportConversation(conversation supportConversation) map[string]any {
	return map[string]any{
		"id":              conversation.ID,
		"token":           conversation.PublicToken,
		"status":          conversation.Status,
		"subject":         nullableValue(conversation.Subject),
		"customer_name":   nullableValue(conversation.CustomerName),
		"last_message_at": nullTime(conversation.LastMessageAt),
	}
}

func serializeSupportMessages(messages []supportMessage) []map[string]any {
	out := make([]map[string]any, 0, len(messages))
	for _, message := range messages {
		out = append(out, serializeSupportMessage(message))
	}
	return out
}

func serializeSupportMessage(message supportMessage) map[string]any {
	return map[string]any{
		"id":          message.ID,
		"sender_type": message.SenderType,
		"sender_name": nullableValue(message.SenderName),
		"body":        message.Body,
		"created_at":  message.CreatedAt.UTC().Format(time.RFC3339),
	}
}

func nullableValue(value sql.NullString) any {
	if !value.Valid {
		return nil
	}
	return value.String
}
