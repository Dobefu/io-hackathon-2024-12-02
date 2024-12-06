package utils

import (
	"fmt"
	"net/http"
	"strings"
)

func Output(w http.ResponseWriter, r *http.Request, routeName string, content string) {
	etag := CreateEtag(content)
	ifNoneMatch := strings.TrimPrefix(strings.Trim(r.Header.Get("If-None-Match"), "\""), "W/")

	// Generate a hash of the content without the W/ prefix for comparison
	contentHash := strings.TrimPrefix(etag, "W/")

	// Check if the ETag matches; if so, return 304 Not Modified
	if ifNoneMatch == strings.Trim(contentHash, "\"") {
		w.WriteHeader(http.StatusNotModified)
		return
	}

	// If ETag does not match, return the content and the ETag
	w.Header().Set("ETag", etag) // Send weak ETag
	w.WriteHeader(http.StatusOK)
	fmt.Fprint(w, content)

}
