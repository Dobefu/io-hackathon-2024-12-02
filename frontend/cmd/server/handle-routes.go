package server

import (
	"frontend/cmd/server/routes"
	"net/http"
)

func HandleRoutes(mux *http.ServeMux) {
	mux.HandleFunc("GET /static/{path...}", routes.StaticFiles)
	mux.HandleFunc("GET /quotes", routes.QuotesOverview)

	// Catch-all route.
	mux.HandleFunc("GET /{path...}", routes.Index)
}
