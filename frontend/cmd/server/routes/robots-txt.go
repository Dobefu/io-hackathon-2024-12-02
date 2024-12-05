package routes

import (
	"net/http"
	"os"
)

func RobotsTxt(w http.ResponseWriter, r *http.Request) {
	file, err := os.Stat("robots.txt")

	if err != nil || file.IsDir() {
		http.NotFound(w, r)
		return
	}

	http.ServeFile(w, r, "robots.txt")
}
