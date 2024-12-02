package server

import (
	"log"
	"net/http"
)

func Start() error {
	url := ":3000"

	mux := http.NewServeMux()

	HandleRoutes(mux)

	log.Printf("Starting server on %s", url)
	err := http.ListenAndServe(url, mux)

	if err != nil {
		return err
	}

	return nil
}
