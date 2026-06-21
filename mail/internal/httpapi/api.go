package httpapi

import (
	"bytes"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"log"
	"mime"
	"net/http"
	"net/mail"
	"strconv"
	"strings"
	"sync"
	"time"

	"kgm-mail-service/internal/config"
	"kgm-mail-service/internal/ids"
	"kgm-mail-service/internal/mailer"
	"kgm-mail-service/internal/store"
)

type API struct {
	cfg    config.Config
	st     *store.Store
	mailer *mailer.Mailer
	queue  chan string
	rateMu sync.Mutex
	rate   map[string]rateEntry
}

type rateEntry struct {
	Count int
	Reset time.Time
}

type responseError struct {
	Message    string `json:"message"`
	Code       int    `json:"code"`
	ErrorUID   string `json:"error_uid"`
	RequestUID string `json:"request_uid"`
}

func New(cfg config.Config, st *store.Store, m *mailer.Mailer) *API {
	a := &API{cfg: cfg, st: st, mailer: m, queue: make(chan string, 2000), rate: map[string]rateEntry{}}
	go a.worker()
	return a
}

func (a *API) Routes() http.Handler {
	mux := http.NewServeMux()
	mux.HandleFunc("/health", a.handleHealth)
	mux.HandleFunc("/api/v1/mail/send", a.withAdmin(a.handleSend))
	mux.HandleFunc("/api/v1/mail/messages", a.withAdmin(a.handleMessages))
	mux.HandleFunc("/api/v1/mail/messages/", a.withAdmin(a.handleMessage))
	mux.HandleFunc("/api/v1/mail/templates", a.withAdmin(a.handleTemplates))
	mux.HandleFunc("/api/v1/mail/templates/", a.withAdmin(a.handleTemplate))
	mux.HandleFunc("/api/v1/mail/queue/stats", a.withAdmin(a.handleStats))
	mux.HandleFunc("/api/v1/inbound/webhook", a.withInboundToken(a.handleInboundWebhook))
	mux.HandleFunc("/api/v1/inbound/raw", a.withInboundToken(a.handleInboundRaw))
	mux.HandleFunc("/api/v1/inbox/messages", a.withAdmin(a.handleInboundList))
	mux.HandleFunc("/api/v1/inbox/messages/", a.withAdmin(a.handleInboundDetail))
	mux.HandleFunc("/api/v1/tickets", a.withAdmin(a.handleTickets))
	mux.HandleFunc("/api/v1/tickets/", a.withAdmin(a.handleTicketAction))
	mux.HandleFunc("/api/v1/mailboxes", a.withAdmin(a.handleMailboxes))
	mux.HandleFunc("/api/v1/system/summary", a.withAdmin(a.handleSystemSummary))
	fs := http.FileServer(http.Dir("web"))
	mux.Handle("/", fs)
	return a.withCommon(mux)
}

func (a *API) withCommon(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		requestUID := r.Header.Get("X-Request-UID")
		if requestUID == "" {
			requestUID = ids.New("req")
		}
		w.Header().Set("X-Request-UID", requestUID)
		w.Header().Set("X-Content-Type-Options", "nosniff")
		w.Header().Set("Referrer-Policy", "same-origin")
		w.Header().Set("X-Frame-Options", "DENY")
		w.Header().Set("Content-Security-Policy", "default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; img-src 'self' data: https://www.google.com https://*.google.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' data: https://fonts.gstatic.com; script-src 'self' 'unsafe-inline' https://static.cloudflareinsights.com https://*.cloudflareinsights.com https://webmail.karacabeygrossmarket.com https://*.cloudflare.com https://www.google-analytics.com https://analytics.google.com; script-src-elem 'self' 'unsafe-inline' https://static.cloudflareinsights.com https://*.cloudflareinsights.com https://webmail.karacabeygrossmarket.com https://*.cloudflare.com https://www.google-analytics.com https://analytics.google.com; script-src-attr 'self' 'unsafe-inline'; connect-src 'self' https://static.cloudflareinsights.com https://*.cloudflareinsights.com https://www.google-analytics.com https://analytics.google.com https://www.google.com https://*.google.com https://webmail.karacabeygrossmarket.com https://*.cloudflare.com; object-src 'none'")
		if a.rateLimited(r) {
			writeErr(w, r, http.StatusTooManyRequests, "Çok fazla istek gönderildi.")
			return
		}
		next.ServeHTTP(w, r.WithContext(withRequestUID(r.Context(), requestUID)))
	})
}

func (a *API) withAdmin(next http.HandlerFunc) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		token := r.Header.Get("X-Mail-Admin-Token")
		if token == "" {
			if c, err := r.Cookie("mail_admin_token"); err == nil {
				token = c.Value
			}
		}
		if token == "" || token != a.cfg.AdminToken {
			writeErr(w, r, http.StatusUnauthorized, "Yetkisiz istek.")
			return
		}
		next(w, r)
	}
}

func (a *API) withInboundToken(next http.HandlerFunc) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		token := r.Header.Get("X-Mail-Inbound-Token")
		if token == "" || token != a.cfg.InboundToken {
			writeErr(w, r, http.StatusUnauthorized, "Inbound token geçersiz.")
			return
		}
		next(w, r)
	}
}

func (a *API) handleHealth(w http.ResponseWriter, r *http.Request) {
	writeJSON(w, http.StatusOK, map[string]any{"status": "ok", "service": "kgm-mail-service", "phase": "2-inbound-ticket", "maildir_poll": a.cfg.MaildirPollEnabled, "time": time.Now().UTC()})
}

func (a *API) handleSend(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		writeErr(w, r, http.StatusMethodNotAllowed, "Metot desteklenmiyor.")
		return
	}
	r.Body = http.MaxBytesReader(w, r.Body, a.cfg.MaxJSONBodyBytes)
	var in store.Message
	if err := json.NewDecoder(r.Body).Decode(&in); err != nil {
		writeErr(w, r, http.StatusBadRequest, "JSON gövdesi okunamadı.")
		return
	}
	if strings.TrimSpace(in.Subject) == "" || len(in.To) == 0 || (strings.TrimSpace(in.TextBody) == "" && strings.TrimSpace(in.HTMLBody) == "") {
		writeErr(w, r, http.StatusUnprocessableEntity, "Alıcı, konu ve içerik zorunludur.")
		return
	}
	recipients := append([]string{}, in.To...)
	recipients = append(recipients, in.CC...)
	recipients = append(recipients, in.BCC...)
	for _, recipient := range recipients {
		if _, err := mail.ParseAddress(recipient); err != nil {
			writeErr(w, r, http.StatusUnprocessableEntity, "Alıcı e-posta adreslerinden biri geçersiz.")
			return
		}
	}
	in.UID = ids.New("mail")
	in.RequestUID = requestUID(r)
	if in.FromName == "" {
		in.FromName = a.cfg.DefaultFromName
	}
	if in.FromEmail == "" {
		in.FromEmail = a.cfg.DefaultFromEmail
	}
	in.Status = store.StatusQueued
	in.CreatedAt = time.Now().UTC()
	if err := a.st.SaveMessage(in); err != nil {
		writeErr(w, r, http.StatusInternalServerError, "Mesaj kaydedilemedi.")
		return
	}
	if !a.enqueue(in.UID, &in) {
		writeErr(w, r, http.StatusServiceUnavailable, "Mail kuyruğu dolu.")
		return
	}
	writeJSON(w, http.StatusAccepted, map[string]any{"message": "Mail kuyruğa alındı.", "message_uid": in.UID, "request_uid": in.RequestUID})
}

func (a *API) handleMessages(w http.ResponseWriter, r *http.Request) {
	limit, _ := strconv.Atoi(r.URL.Query().Get("limit"))
	messages, err := a.st.ListMessages(limit)
	if err != nil {
		writeErr(w, r, http.StatusInternalServerError, "Mesaj listesi alınamadı.")
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"items": messages})
}
func (a *API) handleMessage(w http.ResponseWriter, r *http.Request) {
	uid := strings.TrimPrefix(r.URL.Path, "/api/v1/mail/messages/")
	switch r.Method {
	case http.MethodGet:
		msg, err := a.st.GetMessage(uid)
		if err != nil {
			writeErr(w, r, http.StatusNotFound, "Mesaj bulunamadı.")
			return
		}
		writeJSON(w, http.StatusOK, msg)
	case http.MethodDelete:
		if err := a.st.DeleteMessage(uid); err != nil {
			writeErr(w, r, http.StatusInternalServerError, "Mesaj silinemedi.")
			return
		}
		writeJSON(w, http.StatusOK, map[string]any{"deleted": uid})
	default:
		writeErr(w, r, http.StatusMethodNotAllowed, "Metot desteklenmiyor.")
	}
}

func (a *API) handleTemplates(w http.ResponseWriter, r *http.Request) {
	switch r.Method {
	case http.MethodGet:
		items, err := a.st.ListTemplates()
		if err != nil {
			writeErr(w, r, http.StatusInternalServerError, "Şablonlar alınamadı.")
			return
		}
		writeJSON(w, http.StatusOK, map[string]any{"items": items})
	case http.MethodPost:
		r.Body = http.MaxBytesReader(w, r.Body, a.cfg.MaxJSONBodyBytes)
		var t store.Template
		if err := json.NewDecoder(r.Body).Decode(&t); err != nil {
			writeErr(w, r, http.StatusBadRequest, "JSON okunamadı.")
			return
		}
		if t.Key == "" || t.Subject == "" {
			writeErr(w, r, http.StatusUnprocessableEntity, "Şablon key ve subject zorunludur.")
			return
		}
		a.st.PrepareTemplate(&t)
		if err := a.st.SaveTemplate(t); err != nil {
			writeErr(w, r, http.StatusInternalServerError, "Şablon kaydedilemedi.")
			return
		}
		writeJSON(w, http.StatusCreated, t)
	default:
		writeErr(w, r, http.StatusMethodNotAllowed, "Metot desteklenmiyor.")
	}
}

func (a *API) handleTemplate(w http.ResponseWriter, r *http.Request) {
	uid := strings.TrimPrefix(r.URL.Path, "/api/v1/mail/templates/")
	uid = strings.TrimSuffix(uid, "/")
	if uid == "" {
		writeErr(w, r, http.StatusNotFound, "Şablon bulunamadı.")
		return
	}

	switch r.Method {
	case http.MethodGet:
		t, err := a.st.GetTemplate(uid)
		if err != nil {
			writeErr(w, r, http.StatusNotFound, "Şablon bulunamadı.")
			return
		}
		writeJSON(w, http.StatusOK, t)

	case http.MethodPut:
		r.Body = http.MaxBytesReader(w, r.Body, a.cfg.MaxJSONBodyBytes)
		var t store.Template
		if err := json.NewDecoder(r.Body).Decode(&t); err != nil {
			writeErr(w, r, http.StatusBadRequest, "JSON okunamadı.")
			return
		}
		if strings.TrimSpace(t.Subject) == "" {
			writeErr(w, r, http.StatusUnprocessableEntity, "Subject zorunludur.")
			return
		}
		if err := a.st.UpdateTemplate(uid, t); err != nil {
			writeErr(w, r, http.StatusNotFound, "Şablon güncellenemedi: "+err.Error())
			return
		}
		updated, _ := a.st.GetTemplate(uid)
		writeJSON(w, http.StatusOK, updated)

	case http.MethodDelete:
		if err := a.st.DeleteTemplate(uid); err != nil {
			writeErr(w, r, http.StatusInternalServerError, "Şablon silinemedi.")
			return
		}
		writeJSON(w, http.StatusOK, map[string]any{"deleted": uid})

	default:
		writeErr(w, r, http.StatusMethodNotAllowed, "Metot desteklenmiyor.")
	}
}

func (a *API) handleStats(w http.ResponseWriter, r *http.Request) {
	messages, _ := a.st.ListMessages(200)
	stats := map[string]int{"queued": 0, "sending": 0, "sent": 0, "failed": 0, "dry_run": 0}
	for _, m := range messages {
		stats[string(m.Status)]++
	}
	inbox, _ := a.st.ListInbound(200)
	tickets, _ := a.st.ListTickets(200)
	writeJSON(w, http.StatusOK, map[string]any{"stats": stats, "inbound_count": len(inbox), "ticket_count": len(tickets), "queue_depth": len(a.queue), "smtp_disabled": a.cfg.SMTPDisabled, "maildir_poll": a.cfg.MaildirPollEnabled})
}

func (a *API) handleInboundWebhook(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		writeErr(w, r, http.StatusMethodNotAllowed, "Metot desteklenmiyor.")
		return
	}
	r.Body = http.MaxBytesReader(w, r.Body, a.cfg.MaxJSONBodyBytes)
	var in store.InboundEmail
	if err := json.NewDecoder(r.Body).Decode(&in); err != nil {
		writeErr(w, r, http.StatusBadRequest, "Inbound JSON okunamadı.")
		return
	}
	if in.FromEmail == "" || len(in.To) == 0 {
		writeErr(w, r, http.StatusUnprocessableEntity, "Gönderen ve alıcı zorunludur.")
		return
	}
	in.UID = ids.New("inb")
	in.RequestUID = requestUID(r)
	in.Source = defaultStr(in.Source, "webhook")
	in.ReceivedAt = time.Now().UTC()
	saved, ticket, err := a.st.SaveInbound(in)
	if err != nil {
		writeErr(w, r, http.StatusInternalServerError, "Inbound mail kaydedilemedi.")
		return
	}
	writeJSON(w, http.StatusCreated, map[string]any{"inbound_uid": saved.UID, "ticket_uid": ticket.UID, "ticket_number": ticket.Number, "request_uid": requestUID(r)})
}

func (a *API) handleInboundRaw(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		writeErr(w, r, http.StatusMethodNotAllowed, "Metot desteklenmiyor.")
		return
	}
	r.Body = http.MaxBytesReader(w, r.Body, 10*a.cfg.MaxJSONBodyBytes)
	b, err := io.ReadAll(r.Body)
	if err != nil {
		writeErr(w, r, http.StatusBadRequest, "Raw mail okunamadı.")
		return
	}
	in, err := parseRawEmail(b)
	if err != nil {
		writeErr(w, r, http.StatusBadRequest, "Raw mail parse edilemedi.")
		return
	}
	in.UID = ids.New("inb")
	in.RequestUID = requestUID(r)
	in.Source = "raw-api"
	in.ReceivedAt = time.Now().UTC()
	saved, ticket, err := a.st.SaveInbound(in)
	if err != nil {
		writeErr(w, r, http.StatusInternalServerError, "Inbound mail kaydedilemedi.")
		return
	}
	writeJSON(w, http.StatusCreated, map[string]any{"inbound_uid": saved.UID, "ticket_uid": ticket.UID, "ticket_number": ticket.Number})
}

func (a *API) handleInboundList(w http.ResponseWriter, r *http.Request) {
	limit, _ := strconv.Atoi(r.URL.Query().Get("limit"))
	items, err := a.st.ListInbound(limit)
	if err != nil {
		writeErr(w, r, http.StatusInternalServerError, "Inbox alınamadı.")
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"items": items})
}
func (a *API) handleInboundDetail(w http.ResponseWriter, r *http.Request) {
	uid := strings.TrimPrefix(r.URL.Path, "/api/v1/inbox/messages/")
	switch r.Method {
	case http.MethodGet:
		item, err := a.st.GetInbound(uid)
		if err != nil {
			writeErr(w, r, http.StatusNotFound, "Inbound mail bulunamadı.")
			return
		}
		writeJSON(w, http.StatusOK, item)
	case http.MethodDelete:
		if err := a.st.DeleteInbound(uid); err != nil {
			writeErr(w, r, http.StatusInternalServerError, "Inbound mail silinemedi.")
			return
		}
		writeJSON(w, http.StatusOK, map[string]any{"deleted": uid})
	default:
		writeErr(w, r, http.StatusMethodNotAllowed, "Metot desteklenmiyor.")
	}
}

func (a *API) handleTickets(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodGet {
		writeErr(w, r, http.StatusMethodNotAllowed, "Metot desteklenmiyor.")
		return
	}
	limit, _ := strconv.Atoi(r.URL.Query().Get("limit"))
	items, err := a.st.ListTickets(limit)
	if err != nil {
		writeErr(w, r, http.StatusInternalServerError, "Ticket listesi alınamadı.")
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"items": items})
}

func (a *API) handleTicketAction(w http.ResponseWriter, r *http.Request) {
	path := strings.TrimPrefix(r.URL.Path, "/api/v1/tickets/")
	parts := strings.Split(strings.Trim(path, "/"), "/")
	if len(parts) == 0 || parts[0] == "" {
		writeErr(w, r, http.StatusNotFound, "Ticket bulunamadı.")
		return
	}
	uid := parts[0]
	if len(parts) == 1 && r.Method == http.MethodGet {
		t, err := a.st.GetTicket(uid)
		if err != nil {
			writeErr(w, r, http.StatusNotFound, "Ticket bulunamadı.")
			return
		}
		writeJSON(w, http.StatusOK, t)
		return
	}
	if len(parts) == 2 && parts[1] == "reply" && r.Method == http.MethodPost {
		a.handleTicketReply(w, r, uid)
		return
	}
	if len(parts) == 2 && parts[1] == "status" && r.Method == http.MethodPatch {
		a.handleTicketStatus(w, r, uid)
		return
	}
	writeErr(w, r, http.StatusNotFound, "Ticket işlemi bulunamadı.")
}

func (a *API) handleTicketReply(w http.ResponseWriter, r *http.Request, ticketUID string) {
	r.Body = http.MaxBytesReader(w, r.Body, a.cfg.MaxJSONBodyBytes)
	var in struct {
		TextBody  string `json:"text_body"`
		FromEmail string `json:"from_email"`
		FromName  string `json:"from_name"`
	}
	if err := json.NewDecoder(r.Body).Decode(&in); err != nil {
		writeErr(w, r, http.StatusBadRequest, "JSON okunamadı.")
		return
	}
	t, err := a.st.GetTicket(ticketUID)
	if err != nil {
		writeErr(w, r, http.StatusNotFound, "Ticket bulunamadı.")
		return
	}
	if strings.TrimSpace(in.TextBody) == "" {
		writeErr(w, r, http.StatusUnprocessableEntity, "Yanıt metni zorunludur.")
		return
	}
	fromEmail := defaultStr(in.FromEmail, a.cfg.SupportEmail)
	fromName := defaultStr(in.FromName, "Karacabey Gross Market Destek")
	msg := store.Message{UID: ids.New("mail"), RequestUID: requestUID(r), FromName: fromName, FromEmail: fromEmail, To: []string{t.CustomerMail}, Subject: "Re: " + t.Subject, TextBody: in.TextBody, TicketUID: t.UID, Status: store.StatusQueued, CreatedAt: time.Now().UTC()}
	if err := a.st.SaveMessage(msg); err != nil {
		writeErr(w, r, http.StatusInternalServerError, "Yanıt maili kaydedilemedi.")
		return
	}
	if _, err := a.st.AppendTicketReply(ticketUID, msg); err != nil {
		writeErr(w, r, http.StatusInternalServerError, "Ticket güncellenemedi.")
		return
	}
	if !a.enqueue(msg.UID, &msg) {
		writeErr(w, r, http.StatusServiceUnavailable, "Mail kuyruğu dolu.")
		return
	}
	writeJSON(w, http.StatusAccepted, map[string]any{"message": "Yanıt kuyruğa alındı.", "message_uid": msg.UID, "ticket_uid": ticketUID})
}

func (a *API) handleTicketStatus(w http.ResponseWriter, r *http.Request, ticketUID string) {
	var in struct {
		Status store.TicketStatus `json:"status"`
	}
	if err := json.NewDecoder(r.Body).Decode(&in); err != nil {
		writeErr(w, r, http.StatusBadRequest, "JSON okunamadı.")
		return
	}
	switch in.Status {
	case store.TicketOpen, store.TicketPending, store.TicketResolved, store.TicketClosed:
		// valid
	default:
		writeErr(w, r, http.StatusUnprocessableEntity, "Status geçersiz.")
		return
	}
	t, err := a.st.UpdateTicketStatus(ticketUID, in.Status)
	if err != nil {
		writeErr(w, r, http.StatusInternalServerError, "Ticket kaydedilemedi.")
		return
	}
	writeJSON(w, http.StatusOK, t)
}

func (a *API) handleMailboxes(w http.ResponseWriter, r *http.Request) {
	switch r.Method {
	case http.MethodGet:
		items, err := a.st.ListMailboxes()
		if err != nil {
			writeErr(w, r, http.StatusInternalServerError, "Mailbox listesi alınamadı.")
			return
		}
		writeJSON(w, http.StatusOK, map[string]any{"items": items})
	case http.MethodPost:
		var m store.Mailbox
		if err := json.NewDecoder(r.Body).Decode(&m); err != nil {
			writeErr(w, r, http.StatusBadRequest, "JSON okunamadı.")
			return
		}
		if _, err := mail.ParseAddress(m.Address); err != nil {
			writeErr(w, r, http.StatusUnprocessableEntity, "Mailbox adresi geçersiz.")
			return
		}
		if m.InboundMode == "" {
			m.InboundMode = "maildir"
		}
		m.Enabled = true
		if err := a.st.SaveMailbox(m); err != nil {
			writeErr(w, r, http.StatusInternalServerError, "Mailbox kaydedilemedi.")
			return
		}
		writeJSON(w, http.StatusCreated, m)
	default:
		writeErr(w, r, http.StatusMethodNotAllowed, "Metot desteklenmiyor.")
	}
}

func (a *API) handleSystemSummary(w http.ResponseWriter, r *http.Request) {
	messages, _ := a.st.ListMessages(200)
	inbound, _ := a.st.ListInbound(200)
	tickets, _ := a.st.ListTickets(200)
	mailboxes, _ := a.st.ListMailboxes()
	open := 0
	for _, t := range tickets {
		if t.Status == store.TicketOpen || t.Status == store.TicketPending {
			open++
		}
	}
	writeJSON(w, http.StatusOK, map[string]any{"outbound": len(messages), "inbound": len(inbound), "tickets": len(tickets), "open_tickets": open, "mailboxes": mailboxes, "smtp_disabled": a.cfg.SMTPDisabled, "maildir_poll": a.cfg.MaildirPollEnabled, "maildir_root": a.cfg.MaildirRoot})
}

func (a *API) enqueue(uid string, msg *store.Message) bool {
	select {
	case a.queue <- uid:
		return true
	default:
		if msg != nil {
			msg.Status = store.StatusFailed
			msg.LastError = "queue full"
			_ = a.st.SaveMessage(*msg)
		}
		return false
	}
}

func (a *API) worker() {
	for uid := range a.queue {
		msg, err := a.st.GetMessage(uid)
		if err != nil {
			continue
		}
		msg.Status = store.StatusSending
		msg.Attempts++
		_ = a.st.SaveMessage(msg)
		if a.cfg.SMTPDisabled {
			now := time.Now().UTC()
			msg.Status = store.StatusDryRun
			msg.SentAt = &now
			msg.LastError = ""
			_ = a.st.SaveMessage(msg)
			continue
		}
		if err := a.mailer.Send(msg); err != nil {
			msg.Status = store.StatusFailed
			msg.LastError = err.Error()
			_ = a.st.SaveMessage(msg)
			log.Printf("mail send failed uid=%s err=%v", uid, err)
			continue
		}
		now := time.Now().UTC()
		msg.Status = store.StatusSent
		msg.SentAt = &now
		msg.LastError = ""
		_ = a.st.SaveMessage(msg)
	}
}

func (a *API) rateLimited(r *http.Request) bool {
	ip := strings.Split(r.RemoteAddr, ":")[0]
	if xf := r.Header.Get("X-Forwarded-For"); xf != "" {
		ip = strings.TrimSpace(strings.Split(xf, ",")[0])
	}
	now := time.Now()
	a.rateMu.Lock()
	defer a.rateMu.Unlock()
	ent := a.rate[ip]
	if ent.Reset.IsZero() || now.After(ent.Reset) {
		ent = rateEntry{Count: 0, Reset: now.Add(time.Minute)}
	}
	ent.Count++
	a.rate[ip] = ent
	return ent.Count > a.cfg.RateLimitPerMin
}

func ParseRawEmailForStore(b []byte, source, key string) (store.InboundEmail, error) {
	in, err := parseRawEmail(b)
	in.Source = source
	in.MaildirKey = key
	return in, err
}

func parseRawEmail(b []byte) (store.InboundEmail, error) {
	m, err := mail.ReadMessage(bytes.NewReader(b))
	if err != nil {
		return store.InboundEmail{}, err
	}
	fromName, fromEmail := parseAddress(m.Header.Get("From"))
	to := parseAddressList(m.Header.Get("To"))
	cc := parseAddressList(m.Header.Get("Cc"))
	subject := decodeHeader(m.Header.Get("Subject"))
	body, _ := io.ReadAll(io.LimitReader(m.Body, 256*1024))
	return store.InboundEmail{MessageID: m.Header.Get("Message-Id"), FromName: fromName, FromEmail: fromEmail, To: to, CC: cc, Subject: subject, TextBody: strings.TrimSpace(string(body)), RawHeaders: compactHeaders(m.Header), ReceivedAt: time.Now().UTC()}, nil
}
func parseAddress(v string) (string, string) {
	a, err := mail.ParseAddress(v)
	if err != nil {
		return "", strings.TrimSpace(v)
	}
	return a.Name, strings.ToLower(a.Address)
}
func parseAddressList(v string) []string {
	list, err := mail.ParseAddressList(v)
	if err != nil {
		if strings.TrimSpace(v) == "" {
			return nil
		}
		return []string{strings.ToLower(strings.TrimSpace(v))}
	}
	out := make([]string, 0, len(list))
	for _, a := range list {
		out = append(out, strings.ToLower(a.Address))
	}
	return out
}
func decodeHeader(v string) string {
	dec := new(mime.WordDecoder)
	out, err := dec.DecodeHeader(v)
	if err == nil {
		return out
	}
	return v
}
func compactHeaders(h mail.Header) string {
	keys := []string{"From", "To", "Cc", "Subject", "Date", "Message-Id", "In-Reply-To"}
	var b strings.Builder
	for _, k := range keys {
		if v := h.Get(k); v != "" {
			fmt.Fprintf(&b, "%s: %s\n", k, v)
		}
	}
	return b.String()
}
func defaultStr(v, d string) string {
	if strings.TrimSpace(v) == "" {
		return d
	}
	return v
}

func writeJSON(w http.ResponseWriter, code int, v any) {
	w.Header().Set("Content-Type", "application/json; charset=utf-8")
	w.WriteHeader(code)
	_ = json.NewEncoder(w).Encode(v)
}
func writeErr(w http.ResponseWriter, r *http.Request, code int, msg string) {
	errUID := ids.New("err")
	w.Header().Set("X-Error-UID", errUID)
	if code >= http.StatusInternalServerError {
		log.Printf("api error uid=%s request_uid=%s status=%d method=%s path=%s msg=%s", errUID, requestUID(r), code, r.Method, r.URL.Path, msg)
	}
	writeJSON(w, code, responseError{Message: msg, Code: code, ErrorUID: errUID, RequestUID: requestUID(r)})
}
func validateMethod(r *http.Request, m string) error {
	if r.Method != m {
		return errors.New("method")
	}
	return nil
}
