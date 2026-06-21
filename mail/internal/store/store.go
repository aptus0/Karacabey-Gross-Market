package store

import (
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"errors"
	"os"
	"path/filepath"
	"sort"
	"strings"
	"sync"
	"time"

	"kgm-mail-service/internal/ids"
)

type MessageStatus string

const (
	StatusQueued  MessageStatus = "queued"
	StatusSending MessageStatus = "sending"
	StatusSent    MessageStatus = "sent"
	StatusFailed  MessageStatus = "failed"
	StatusDryRun  MessageStatus = "dry_run"
)

type Message struct {
	UID        string        `json:"uid"`
	RequestUID string        `json:"request_uid"`
	FromName   string        `json:"from_name"`
	FromEmail  string        `json:"from_email"`
	To         []string      `json:"to"`
	CC         []string      `json:"cc,omitempty"`
	BCC        []string      `json:"bcc,omitempty"`
	Subject    string        `json:"subject"`
	TextBody   string        `json:"text_body,omitempty"`
	HTMLBody   string        `json:"html_body,omitempty"`
	TicketUID  string        `json:"ticket_uid,omitempty"`
	Status     MessageStatus `json:"status"`
	Attempts   int           `json:"attempts"`
	LastError  string        `json:"last_error,omitempty"`
	CreatedAt  time.Time     `json:"created_at"`
	UpdatedAt  time.Time     `json:"updated_at"`
	SentAt     *time.Time    `json:"sent_at,omitempty"`
}

type Template struct {
	UID       string    `json:"uid"`
	Key       string    `json:"key"`
	Name      string    `json:"name"`
	Subject   string    `json:"subject"`
	TextBody  string    `json:"text_body"`
	HTMLBody  string    `json:"html_body"`
	CreatedAt time.Time `json:"created_at"`
	UpdatedAt time.Time `json:"updated_at"`
}

type InboundEmail struct {
	UID          string    `json:"uid"`
	RequestUID   string    `json:"request_uid,omitempty"`
	TicketUID    string    `json:"ticket_uid,omitempty"`
	Source       string    `json:"source"`
	MaildirKey   string    `json:"maildir_key,omitempty"`
	MessageID    string    `json:"message_id,omitempty"`
	FromName     string    `json:"from_name,omitempty"`
	FromEmail    string    `json:"from_email"`
	To           []string  `json:"to"`
	CC           []string  `json:"cc,omitempty"`
	Subject      string    `json:"subject"`
	TextBody     string    `json:"text_body,omitempty"`
	RawHeaders   string    `json:"raw_headers,omitempty"`
	AttachmentNo int       `json:"attachment_count,omitempty"`
	ReceivedAt   time.Time `json:"received_at"`
	CreatedAt    time.Time `json:"created_at"`
}

type TicketStatus string

const (
	TicketOpen     TicketStatus = "open"
	TicketPending  TicketStatus = "pending"
	TicketResolved TicketStatus = "resolved"
	TicketClosed   TicketStatus = "closed"
)

type TicketMessage struct {
	UID        string    `json:"uid"`
	Direction  string    `json:"direction"`
	FromEmail  string    `json:"from_email"`
	To         []string  `json:"to"`
	Subject    string    `json:"subject"`
	TextBody   string    `json:"text_body"`
	MailUID    string    `json:"mail_uid,omitempty"`
	InboundUID string    `json:"inbound_uid,omitempty"`
	MessageID  string    `json:"message_id,omitempty"`
	CreatedAt  time.Time `json:"created_at"`
}

type Ticket struct {
	UID          string          `json:"uid"`
	Number       string          `json:"number"`
	Status       TicketStatus    `json:"status"`
	Priority     string          `json:"priority"`
	Subject      string          `json:"subject"`
	CustomerName string          `json:"customer_name,omitempty"`
	CustomerMail string          `json:"customer_email"`
	Mailbox      string          `json:"mailbox"`
	LastMessage  string          `json:"last_message,omitempty"`
	ThreadKey    string          `json:"thread_key"`
	Messages     []TicketMessage `json:"messages"`
	CreatedAt    time.Time       `json:"created_at"`
	UpdatedAt    time.Time       `json:"updated_at"`
	ClosedAt     *time.Time      `json:"closed_at,omitempty"`
}

type Mailbox struct {
	UID         string    `json:"uid"`
	Address     string    `json:"address"`
	Name        string    `json:"name"`
	Purpose     string    `json:"purpose"`
	Enabled     bool      `json:"enabled"`
	InboundMode string    `json:"inbound_mode"`
	CreatedAt   time.Time `json:"created_at"`
	UpdatedAt   time.Time `json:"updated_at"`
}

type Store struct {
	root string
	mu   sync.RWMutex
}

func (s *Store) DeleteInbound(uid string) error {
	s.mu.Lock()
	defer s.mu.Unlock()
	if !validUID(uid) {
		return errors.New("invalid uid")
	}
	if err := os.Remove(s.inboundPath(uid)); err != nil && !os.IsNotExist(err) {
		return err
	}
	return nil
}

func New(root string) (*Store, error) {
	dirs := []string{
		filepath.Join(root, "messages"), filepath.Join(root, "templates"), filepath.Join(root, "inbound"),
		filepath.Join(root, "tickets"), filepath.Join(root, "mailboxes"), filepath.Join(root, "seen"),
	}
	for _, d := range dirs {
		if err := os.MkdirAll(d, 0750); err != nil {
			return nil, err
		}
	}
	return &Store{root: root}, nil
}

func (s *Store) SaveMessage(m Message) error {
	s.mu.Lock()
	defer s.mu.Unlock()
	if m.UID == "" {
		m.UID = ids.New("mail")
	}
	if m.CreatedAt.IsZero() {
		m.CreatedAt = time.Now().UTC()
	}
	m.UpdatedAt = time.Now().UTC()
	return writeJSON(s.messagePath(m.UID), m)
}

func (s *Store) GetMessage(uid string) (Message, error) {
	s.mu.RLock()
	defer s.mu.RUnlock()
	if !validUID(uid) {
		return Message{}, errors.New("invalid uid")
	}
	var m Message
	err := readJSON(s.messagePath(uid), &m)
	return m, err
}

func (s *Store) DeleteMessage(uid string) error {
	s.mu.Lock()
	defer s.mu.Unlock()
	if !validUID(uid) {
		return errors.New("invalid uid")
	}
	if err := os.Remove(s.messagePath(uid)); err != nil && !os.IsNotExist(err) {
		return err
	}
	return nil
}

func (s *Store) ListMessages(limit int) ([]Message, error) {
	s.mu.RLock()
	defer s.mu.RUnlock()
	files, err := filepath.Glob(filepath.Join(s.root, "messages", "*.json"))
	if err != nil {
		return nil, err
	}
	out := make([]Message, 0, len(files))
	for _, f := range files {
		var m Message
		if err := readJSON(f, &m); err == nil {
			out = append(out, m)
		}
	}
	sort.Slice(out, func(i, j int) bool { return out[i].CreatedAt.After(out[j].CreatedAt) })
	return trimMessages(out, limit), nil
}

func (s *Store) SaveTemplate(t Template) error {
	s.mu.Lock()
	defer s.mu.Unlock()
	s.applyTemplateDefaultsLocked(&t)
	return writeJSON(filepath.Join(s.root, "templates", t.UID+".json"), t)
}

func (s *Store) PrepareTemplate(t *Template) {
	s.mu.Lock()
	defer s.mu.Unlock()
	s.applyTemplateDefaultsLocked(t)
}

func (s *Store) applyTemplateDefaultsLocked(t *Template) {
	if t.UID == "" {
		t.UID = ids.New("tmpl")
	}
	now := time.Now().UTC()
	if t.CreatedAt.IsZero() {
		t.CreatedAt = now
	}
	t.UpdatedAt = now
}

func (s *Store) GetTemplate(uid string) (Template, error) {
	s.mu.RLock()
	defer s.mu.RUnlock()
	if !validUID(uid) {
		return Template{}, errors.New("invalid uid")
	}
	var t Template
	err := readJSON(filepath.Join(s.root, "templates", uid+".json"), &t)
	return t, err
}

func (s *Store) DeleteTemplate(uid string) error {
	s.mu.Lock()
	defer s.mu.Unlock()
	if !validUID(uid) {
		return errors.New("invalid uid")
	}
	path := filepath.Join(s.root, "templates", uid+".json")
	if err := os.Remove(path); err != nil && !os.IsNotExist(err) {
		return err
	}
	return nil
}

func (s *Store) UpdateTemplate(uid string, t Template) error {
	s.mu.Lock()
	defer s.mu.Unlock()
	if !validUID(uid) {
		return errors.New("invalid uid")
	}
	path := filepath.Join(s.root, "templates", uid+".json")
	var existing Template
	if err := readJSON(path, &existing); err != nil {
		return err
	}
	t.UID = existing.UID
	t.Key = existing.Key // key değiştirilemez
	t.CreatedAt = existing.CreatedAt
	t.UpdatedAt = time.Now().UTC()
	return writeJSON(path, t)
}

func (s *Store) ListTemplates() ([]Template, error) {
	s.mu.RLock()
	defer s.mu.RUnlock()
	files, err := filepath.Glob(filepath.Join(s.root, "templates", "*.json"))
	if err != nil {
		return nil, err
	}
	out := make([]Template, 0, len(files))
	for _, f := range files {
		var t Template
		if err := readJSON(f, &t); err == nil {
			out = append(out, t)
		}
	}
	sort.Slice(out, func(i, j int) bool { return out[i].CreatedAt.After(out[j].CreatedAt) })
	return out, nil
}

func (s *Store) SaveInbound(in InboundEmail) (InboundEmail, Ticket, error) {
	s.mu.Lock()
	defer s.mu.Unlock()
	if in.UID == "" {
		in.UID = ids.New("inb")
	}
	if in.ReceivedAt.IsZero() {
		in.ReceivedAt = time.Now().UTC()
	}
	if in.CreatedAt.IsZero() {
		in.CreatedAt = time.Now().UTC()
	}
	threadKey := makeThreadKey(in.FromEmail, in.Subject)
	t, err := s.findTicketByThreadKeyLocked(threadKey)
	if err != nil {
		t = Ticket{UID: ids.New("tkt"), Number: ticketNumber(), Status: TicketOpen, Priority: "normal", Subject: cleanSubject(in.Subject), CustomerName: in.FromName, CustomerMail: strings.ToLower(in.FromEmail), Mailbox: firstNonEmpty(in.To), ThreadKey: threadKey, CreatedAt: time.Now().UTC()}
	}
	msg := TicketMessage{UID: ids.New("tm"), Direction: "inbound", FromEmail: in.FromEmail, To: in.To, Subject: in.Subject, TextBody: in.TextBody, InboundUID: in.UID, MessageID: in.MessageID, CreatedAt: time.Now().UTC()}
	t.Messages = append(t.Messages, msg)
	t.LastMessage = preview(in.TextBody)
	t.UpdatedAt = time.Now().UTC()
	if t.Status == TicketClosed || t.Status == TicketResolved {
		t.Status = TicketOpen
		t.ClosedAt = nil
	}
	in.TicketUID = t.UID
	if err := writeJSON(s.inboundPath(in.UID), in); err != nil {
		return in, t, err
	}
	if err := writeJSON(s.ticketPath(t.UID), t); err != nil {
		return in, t, err
	}
	return in, t, nil
}

func (s *Store) ListInbound(limit int) ([]InboundEmail, error) {
	s.mu.RLock()
	defer s.mu.RUnlock()
	files, err := filepath.Glob(filepath.Join(s.root, "inbound", "*.json"))
	if err != nil {
		return nil, err
	}
	out := make([]InboundEmail, 0, len(files))
	for _, f := range files {
		var m InboundEmail
		if err := readJSON(f, &m); err == nil {
			out = append(out, m)
		}
	}
	sort.Slice(out, func(i, j int) bool { return out[i].ReceivedAt.After(out[j].ReceivedAt) })
	if limit <= 0 || limit > 200 {
		limit = 50
	}
	if len(out) > limit {
		out = out[:limit]
	}
	return out, nil
}

func (s *Store) GetInbound(uid string) (InboundEmail, error) {
	s.mu.RLock()
	defer s.mu.RUnlock()
	if !validUID(uid) {
		return InboundEmail{}, errors.New("invalid uid")
	}
	var m InboundEmail
	err := readJSON(s.inboundPath(uid), &m)
	return m, err
}

func (s *Store) ListTickets(limit int) ([]Ticket, error) {
	s.mu.RLock()
	defer s.mu.RUnlock()
	files, err := filepath.Glob(filepath.Join(s.root, "tickets", "*.json"))
	if err != nil {
		return nil, err
	}
	out := make([]Ticket, 0, len(files))
	for _, f := range files {
		var t Ticket
		if err := readJSON(f, &t); err == nil {
			out = append(out, t)
		}
	}
	sort.Slice(out, func(i, j int) bool { return out[i].UpdatedAt.After(out[j].UpdatedAt) })
	if limit <= 0 || limit > 200 {
		limit = 50
	}
	if len(out) > limit {
		out = out[:limit]
	}
	return out, nil
}

func (s *Store) GetTicket(uid string) (Ticket, error) {
	s.mu.RLock()
	defer s.mu.RUnlock()
	if !validUID(uid) {
		return Ticket{}, errors.New("invalid uid")
	}
	var t Ticket
	err := readJSON(s.ticketPath(uid), &t)
	return t, err
}

func (s *Store) SaveTicket(t Ticket) error {
	s.mu.Lock()
	defer s.mu.Unlock()
	return s.saveTicketLocked(t)
}

func (s *Store) saveTicketLocked(t Ticket) error {
	if t.UID == "" {
		t.UID = ids.New("tkt")
	}
	if t.Number == "" {
		t.Number = ticketNumber()
	}
	if t.CreatedAt.IsZero() {
		t.CreatedAt = time.Now().UTC()
	}
	t.UpdatedAt = time.Now().UTC()
	return writeJSON(s.ticketPath(t.UID), t)
}

func (s *Store) UpdateTicketStatus(ticketUID string, status TicketStatus) (Ticket, error) {
	s.mu.Lock()
	defer s.mu.Unlock()
	if !validUID(ticketUID) {
		return Ticket{}, errors.New("invalid ticket uid")
	}
	var t Ticket
	if err := readJSON(s.ticketPath(ticketUID), &t); err != nil {
		return Ticket{}, err
	}
	t.Status = status
	if status == TicketClosed || status == TicketResolved {
		now := time.Now().UTC()
		t.ClosedAt = &now
	} else {
		t.ClosedAt = nil
	}
	if err := s.saveTicketLocked(t); err != nil {
		return Ticket{}, err
	}
	return t, nil
}

func (s *Store) AppendTicketReply(ticketUID string, msg Message) (Ticket, error) {
	s.mu.Lock()
	defer s.mu.Unlock()
	if !validUID(ticketUID) {
		return Ticket{}, errors.New("invalid ticket uid")
	}
	var t Ticket
	if err := readJSON(s.ticketPath(ticketUID), &t); err != nil {
		return Ticket{}, err
	}
	t.Messages = append(t.Messages, TicketMessage{UID: ids.New("tm"), Direction: "outbound", FromEmail: msg.FromEmail, To: msg.To, Subject: msg.Subject, TextBody: msg.TextBody, MailUID: msg.UID, CreatedAt: time.Now().UTC()})
	t.LastMessage = preview(msg.TextBody)
	t.Status = TicketPending
	t.UpdatedAt = time.Now().UTC()
	return t, writeJSON(s.ticketPath(t.UID), t)
}

func (s *Store) SaveMailbox(m Mailbox) error {
	s.mu.Lock()
	defer s.mu.Unlock()
	if m.UID == "" {
		m.UID = ids.New("mbx")
	}
	if m.CreatedAt.IsZero() {
		m.CreatedAt = time.Now().UTC()
	}
	m.UpdatedAt = time.Now().UTC()
	return writeJSON(filepath.Join(s.root, "mailboxes", m.UID+".json"), m)
}

func (s *Store) ListMailboxes() ([]Mailbox, error) {
	s.mu.RLock()
	defer s.mu.RUnlock()
	files, err := filepath.Glob(filepath.Join(s.root, "mailboxes", "*.json"))
	if err != nil {
		return nil, err
	}
	out := make([]Mailbox, 0, len(files))
	for _, f := range files {
		var m Mailbox
		if err := readJSON(f, &m); err == nil {
			out = append(out, m)
		}
	}
	if len(out) == 0 {
		now := time.Now().UTC()
		out = []Mailbox{
			{UID: "mbx_support", Address: "destek@karacabeygrossmarket.com", Name: "Destek", Purpose: "Müşteri destek ve ticket", Enabled: true, InboundMode: "maildir", CreatedAt: now, UpdatedAt: now},
			{UID: "mbx_order", Address: "siparis@karacabeygrossmarket.com", Name: "Sipariş", Purpose: "Sipariş bildirimleri", Enabled: true, InboundMode: "maildir", CreatedAt: now, UpdatedAt: now},
			{UID: "mbx_noreply", Address: "noreply@karacabeygrossmarket.com", Name: "No Reply", Purpose: "Sistem bildirimleri", Enabled: true, InboundMode: "outbound-only", CreatedAt: now, UpdatedAt: now},
		}
	}
	return out, nil
}

func (s *Store) HasSeen(key string) bool {
	s.mu.RLock()
	defer s.mu.RUnlock()
	if key == "" {
		return false
	}
	_, err := os.Stat(filepath.Join(s.root, "seen", hashKey(key)+".json"))
	return err == nil
}

func (s *Store) MarkSeen(key string) error {
	s.mu.Lock()
	defer s.mu.Unlock()
	if key == "" {
		return nil
	}
	return writeJSON(filepath.Join(s.root, "seen", hashKey(key)+".json"), map[string]any{"key": key, "seen_at": time.Now().UTC()})
}

func (s *Store) messagePath(uid string) string { return filepath.Join(s.root, "messages", uid+".json") }
func (s *Store) inboundPath(uid string) string { return filepath.Join(s.root, "inbound", uid+".json") }
func (s *Store) ticketPath(uid string) string  { return filepath.Join(s.root, "tickets", uid+".json") }

func (s *Store) findTicketByThreadKeyLocked(key string) (Ticket, error) {
	files, err := filepath.Glob(filepath.Join(s.root, "tickets", "*.json"))
	if err != nil {
		return Ticket{}, err
	}
	for _, f := range files {
		var t Ticket
		if err := readJSON(f, &t); err == nil && t.ThreadKey == key {
			return t, nil
		}
	}
	return Ticket{}, os.ErrNotExist
}

func writeJSON(path string, v any) error {
	b, err := json.MarshalIndent(v, "", "  ")
	if err != nil {
		return err
	}
	tmp := path + ".tmp"
	if err := os.WriteFile(tmp, b, 0640); err != nil {
		return err
	}
	return os.Rename(tmp, path)
}
func readJSON(path string, v any) error {
	b, err := os.ReadFile(path)
	if err != nil {
		return err
	}
	return json.Unmarshal(b, v)
}
func validUID(uid string) bool {
	return uid != "" && !strings.Contains(uid, "/") && !strings.Contains(uid, "..")
}
func trimMessages(out []Message, limit int) []Message {
	if limit <= 0 || limit > 200 {
		limit = 50
	}
	if len(out) > limit {
		return out[:limit]
	}
	return out
}
func cleanSubject(s string) string {
	s = strings.TrimSpace(s)
	if s == "" {
		return "Konu yok"
	}
	return s
}
func firstNonEmpty(v []string) string {
	for _, x := range v {
		if strings.TrimSpace(x) != "" {
			return strings.ToLower(strings.TrimSpace(x))
		}
	}
	return "destek@karacabeygrossmarket.com"
}
func preview(s string) string {
	s = strings.Join(strings.Fields(s), " ")
	if len(s) > 160 {
		return s[:160] + "..."
	}
	return s
}
func ticketNumber() string { return "KGM-" + time.Now().UTC().Format("060102150405") }
func makeThreadKey(from, subject string) string {
	return hashKey(strings.ToLower(strings.TrimSpace(from)) + "|" + normalizeSubject(subject))
}
func normalizeSubject(s string) string {
	s = strings.ToLower(strings.TrimSpace(s))
	for _, p := range []string{"re:", "fw:", "fwd:"} {
		s = strings.TrimSpace(strings.TrimPrefix(s, p))
	}
	if s == "" {
		s = "no-subject"
	}
	return s
}
func hashKey(s string) string { h := sha256.Sum256([]byte(s)); return hex.EncodeToString(h[:]) }
