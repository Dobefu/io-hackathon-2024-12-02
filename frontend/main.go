package main

import (
	"flag"
	"frontend/cmd/server"
	"log"

	"github.com/joho/godotenv"
)

func main() {
	err := godotenv.Load()

	if err != nil {
		log.Fatal("Error loading .env file")
	}

	isDev := flag.Bool("dev", false, "Run the server in dev mode")
	flag.Parse()

	var port uint = 3000

	if *isDev {
		port = 30000
	}

	err = server.Start(port)

	if err != nil {
		log.Panicf("Unable to start the server: %s\n", err.Error())
	}
}
