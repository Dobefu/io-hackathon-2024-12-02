package main

import (
	"flag"
	"frontend/cmd/server"
	"log"
)

func main() {
	isDev := flag.Bool("dev", false, "Run the server in dev mode")
	flag.Parse()

	var port uint = 3000

	if *isDev {
		port = 30000
	}

	err := server.Start(port)

	if err != nil {
		log.Panicf("Unable to start the server: %s\n", err.Error())
	}
}
