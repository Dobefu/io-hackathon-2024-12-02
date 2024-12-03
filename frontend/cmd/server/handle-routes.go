package server

import (
	"frontend/cmd/server/routes"
	"net/http"
)

func HandleRoutes(mux *http.ServeMux) {
	mux.HandleFunc("GET /static/{path...}", routes.StaticFiles)

	// Catch-all route.
	mux.HandleFunc("GET /{path...}", routes.Index)
}
