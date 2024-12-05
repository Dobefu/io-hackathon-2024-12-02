package server

import (
	"frontend/cmd/server/routes"
	"net/http"
)

func HandleRoutes(mux *http.ServeMux) {
	mux.HandleFunc("GET /static/{path...}", routes.StaticFiles)
	mux.HandleFunc("GET /robots.txt", routes.RobotsTxt)
	mux.HandleFunc("GET /quotes", routes.QuotesOverview)
	mux.HandleFunc("GET /quote/{id}", routes.QuoteById)

	// Catch-all route.
	mux.HandleFunc("GET /{path...}", routes.Index)
}
