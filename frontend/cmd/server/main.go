package server

import (
	"fmt"
	"log"
	"net/http"
)

func Start(port uint) error {
	url := fmt.Sprintf(":%d", port)

	mux := http.NewServeMux()

	HandleRoutes(mux)

	log.Printf("Starting server on %s", url)
	err := http.ListenAndServe(url, mux)

	if err != nil {
		return err
	}

	return nil
}
