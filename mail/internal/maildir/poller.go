package maildir

import (
	"context"
	"crypto/sha256"
	"encoding/hex"
	"fmt"
	"io/fs"
	"log"
	"os"
	"path/filepath"
	"strings"
	"time"

	"kgm-mail-service/internal/httpapi"
	"kgm-mail-service/internal/ids"
	"kgm-mail-service/internal/store"
)

type Poller struct {
	Root     string
	Every    time.Duration
	Store    *store.Store
	MaxBytes int64
}

func (p Poller) Run(ctx context.Context) {
	if p.Every <= 0 {
		p.Every = 20 * time.Second
	}
	if p.MaxBytes <= 0 {
		p.MaxBytes = 10 << 20
	}
	p.scanOnce()
	ticker := time.NewTicker(p.Every)
	defer ticker.Stop()
	for {
		select {
		case <-ctx.Done():
			return
		case <-ticker.C:
			p.scanOnce()
		}
	}
}

func (p Poller) scanOnce() {
	root := p.Root
	if root == "" {
		root = "/maildata"
	}
	_ = filepath.WalkDir(root, func(path string, d fs.DirEntry, err error) error {
		if err != nil || d == nil || d.IsDir() {
			return nil
		}
		if !isMaildirMessage(path) {
			return nil
		}
		info, err := d.Info()
		if err != nil || info.Size() <= 0 || info.Size() > p.MaxBytes {
			return nil
		}
		key := fmt.Sprintf("%s|%s|%d", path, info.ModTime().UTC().Format(time.RFC3339Nano), info.Size())
		if p.Store.HasSeen(key) {
			return nil
		}
		b, err := os.ReadFile(path)
		if err != nil {
			return nil
		}
		in, err := httpapi.ParseRawEmailForStore(b, "maildir", fingerprint(path, b))
		if err != nil {
			_ = p.Store.MarkSeen(key)
			return nil
		}
		messageIDKey := messageIDSeenKey(in.MessageID)
		if messageIDKey != "" && p.Store.HasSeen(messageIDKey) {
			_ = p.Store.MarkSeen(key)
			return nil
		}
		if in.UID == "" {
			in.UID = ids.New("inb")
		}
		if in.ReceivedAt.IsZero() {
			in.ReceivedAt = time.Now().UTC()
		}
		if _, _, err := p.Store.SaveInbound(in); err != nil {
			log.Printf("maildir inbound save failed path=%s err=%v", path, err)
			return nil
		}
		_ = p.Store.MarkSeen(key)
		_ = p.Store.MarkSeen(messageIDKey)
		return nil
	})
}

func isMaildirMessage(path string) bool {
	clean := filepath.ToSlash(path)
	return (strings.Contains(clean, "/new/") || strings.Contains(clean, "/cur/")) && !strings.HasSuffix(clean, ".json") && !strings.HasSuffix(clean, ".lock")
}

func fingerprint(path string, b []byte) string {
	h := sha256.Sum256(append([]byte(path), b...))
	return hex.EncodeToString(h[:])
}

func messageIDSeenKey(messageID string) string {
	messageID = strings.ToLower(strings.TrimSpace(messageID))
	if messageID == "" {
		return ""
	}
	return "message-id:" + messageID
}
