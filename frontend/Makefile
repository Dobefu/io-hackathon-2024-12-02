.PHONY: build

default: build

build:
	@go build -ldflags="-s -w"

run: build
	@./frontend
