package main

import (
	"frontend/cmd/server"
	"log"
)

func main() {
	err := server.Start()

	if err != nil {
		log.Panicf("Unable to start the server: %s\n", err.Error())
	}
}
