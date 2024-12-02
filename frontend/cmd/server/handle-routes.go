package server

import (
	"frontend/cmd/server/routes"
	"net/http"
)

func HandleRoutes(mux *http.ServeMux) {
	mux.HandleFunc("GET /", routes.Index)
}
