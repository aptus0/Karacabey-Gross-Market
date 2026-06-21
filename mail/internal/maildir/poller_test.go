package maildir

import (
	"os"
	"path/filepath"
	"testing"

	"kgm-mail-service/internal/store"
)

func TestScanOnceDeduplicatesMessageIDAcrossMailboxes(t *testing.T) {
	root := t.TempDir()
	storeRoot := t.TempDir()
	st, err := store.New(storeRoot)
	if err != nil {
		t.Fatalf("store.New: %v", err)
	}

	raw := []byte("From: Sender <sender@example.com>\r\n" +
		"To: destek@karacabeygrossmarket.com, siparis@karacabeygrossmarket.com\r\n" +
		"Subject: Duplicate delivery test\r\n" +
		"Message-ID: <same-message@example.com>\r\n" +
		"\r\n" +
		"Hello\r\n")

	for _, mailbox := range []string{"destek", "siparis"} {
		dir := filepath.Join(root, mailbox, "new")
		if err := os.MkdirAll(dir, 0o755); err != nil {
			t.Fatalf("MkdirAll: %v", err)
		}
		if err := os.WriteFile(filepath.Join(dir, "message.eml"), raw, 0o644); err != nil {
			t.Fatalf("WriteFile: %v", err)
		}
	}

	Poller{Root: root, Store: st, MaxBytes: 1 << 20}.scanOnce()

	inbound, err := st.ListInbound(20)
	if err != nil {
		t.Fatalf("ListInbound: %v", err)
	}
	if len(inbound) != 1 {
		t.Fatalf("inbound count = %d, want 1", len(inbound))
	}

	tickets, err := st.ListTickets(20)
	if err != nil {
		t.Fatalf("ListTickets: %v", err)
	}
	if len(tickets) != 1 || len(tickets[0].Messages) != 1 {
		t.Fatalf("tickets/messages = %d/%d, want 1/1", len(tickets), len(tickets[0].Messages))
	}
}
