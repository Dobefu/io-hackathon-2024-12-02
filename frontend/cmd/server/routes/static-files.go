package routes

import (
	"fmt"
	"net/http"
	"os"
)

func StaticFiles(w http.ResponseWriter, r *http.Request) {

	path := fmt.Sprintf("static/%s", r.PathValue("path"))

	file, err := os.Stat(path)

	if err != nil || file.IsDir() {
		http.NotFound(w, r)
		return
	}

	http.ServeFile(w, r, path)
}
