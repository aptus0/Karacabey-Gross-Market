package mailer

import (
	"bytes"
	"crypto/tls"
	"fmt"
	"mime"
	"net"
	"net/mail"
	"net/smtp"
	"strings"
	"time"

	"kgm-mail-service/internal/config"
	"kgm-mail-service/internal/store"
)

type Mailer struct{ cfg config.Config }

func New(cfg config.Config) *Mailer { return &Mailer{cfg: cfg} }

func (m *Mailer) Send(msg store.Message) error {
	if m.cfg.SMTPDisabled {
		return nil
	}
	if len(msg.To) == 0 {
		return fmt.Errorf("recipient required")
	}
	from := mail.Address{Name: msg.FromName, Address: msg.FromEmail}
	if from.Address == "" {
		from.Address = m.cfg.DefaultFromEmail
	}
	if from.Name == "" {
		from.Name = m.cfg.DefaultFromName
	}

	raw := buildMessage(msg, from, time.Now().UTC())

	recipients := append([]string{}, msg.To...)
	recipients = append(recipients, msg.CC...)
	recipients = append(recipients, msg.BCC...)

	addr := net.JoinHostPort(m.cfg.SMTPHost, m.cfg.SMTPPort)
	var auth smtp.Auth
	if m.cfg.SMTPAuth && m.cfg.SMTPUsername != "" {
		auth = smtp.PlainAuth("", m.cfg.SMTPUsername, m.cfg.SMTPPassword, m.cfg.SMTPHost)
	}
	tlsServerName := m.cfg.SMTPTLSServerName
	if tlsServerName == "" {
		tlsServerName = m.cfg.SMTPHost
	}
	return sendMailStartTLS(addr, auth, from.Address, recipients, raw, tlsServerName, m.cfg.SMTPTLSInsecure)
}

func buildMessage(msg store.Message, from mail.Address, now time.Time) []byte {
	var body bytes.Buffer
	boundary := fmt.Sprintf("kgm_%d", now.UnixNano())
	writeHeader(&body, "From", from.String())
	writeHeader(&body, "To", strings.Join(msg.To, ", "))
	if len(msg.CC) > 0 {
		writeHeader(&body, "Cc", strings.Join(msg.CC, ", "))
	}
	writeHeader(&body, "Date", now.Format(time.RFC1123Z))
	writeHeader(&body, "Message-ID", messageID(from.Address, now))
	writeHeader(&body, "Subject", mime.QEncoding.Encode("utf-8", msg.Subject))
	writeHeader(&body, "MIME-Version", "1.0")
	if msg.HTMLBody != "" {
		writeHeader(&body, "Content-Type", "multipart/alternative; boundary=\""+boundary+"\"")
		body.WriteString("\r\n--" + boundary + "\r\n")
		body.WriteString("Content-Type: text/plain; charset=utf-8\r\n\r\n")
		body.WriteString(msg.TextBody + "\r\n")
		body.WriteString("--" + boundary + "\r\n")
		body.WriteString("Content-Type: text/html; charset=utf-8\r\n\r\n")
		body.WriteString(msg.HTMLBody + "\r\n")
		body.WriteString("--" + boundary + "--\r\n")
	} else {
		writeHeader(&body, "Content-Type", "text/plain; charset=utf-8")
		body.WriteString("\r\n" + msg.TextBody)
	}

	return body.Bytes()
}

func messageID(from string, now time.Time) string {
	domain := "localhost"
	if at := strings.LastIndex(from, "@"); at >= 0 && at+1 < len(from) {
		domain = from[at+1:]
	}
	return fmt.Sprintf("<%d@%s>", now.UnixNano(), domain)
}

func writeHeader(b *bytes.Buffer, key, value string) { b.WriteString(key + ": " + value + "\r\n") }

func sendMailStartTLS(addr string, auth smtp.Auth, from string, to []string, msg []byte, host string, insecureSkipVerify bool) error {
	c, err := smtp.Dial(addr)
	if err != nil {
		return err
	}
	defer c.Close()
	if ok, _ := c.Extension("STARTTLS"); ok {
		cfg := &tls.Config{ServerName: host, MinVersion: tls.VersionTLS12, InsecureSkipVerify: insecureSkipVerify}
		if err := c.StartTLS(cfg); err != nil {
			return err
		}
	}
	if auth != nil {
		if ok, _ := c.Extension("AUTH"); ok {
			if err := c.Auth(auth); err != nil {
				return err
			}
		}
	}
	if err := c.Mail(from); err != nil {
		return err
	}
	for _, rcpt := range to {
		if err := c.Rcpt(rcpt); err != nil {
			return err
		}
	}
	w, err := c.Data()
	if err != nil {
		return err
	}
	if _, err := w.Write(msg); err != nil {
		return err
	}
	if err := w.Close(); err != nil {
		return err
	}
	return c.Quit()
}
