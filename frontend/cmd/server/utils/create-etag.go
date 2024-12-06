package utils

import (
	"crypto/sha256"
	"encoding/hex"
	"fmt"
)

func CreateEtag(content string) string {
	hasher := sha256.New()
	hasher.Write([]byte(content))
	hash := hex.EncodeToString(hasher.Sum(nil))
	return fmt.Sprintf("W/\"%s\"", hash)
}
