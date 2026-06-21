package ids

import (
	"crypto/rand"
	"encoding/base32"
	"strings"
	"time"
)

func New(prefix string) string {
	b := make([]byte, 16)
	if _, err := rand.Read(b); err != nil {
		return prefix + "_" + strings.ReplaceAll(time.Now().UTC().Format("20060102150405.000000000"), ".", "")
	}
	enc := base32.StdEncoding.WithPadding(base32.NoPadding).EncodeToString(b)
	return prefix + "_" + strings.ToLower(enc)
}
