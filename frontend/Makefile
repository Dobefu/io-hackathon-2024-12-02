.PHONY: build

default: build

build:
	@go build -ldflags="-s -w"
	@bun tailwindcss -m -i ./cmd/templates/css/main.css -o ./static/css/main.css

build-dev:
	@go build
	@bun tailwindcss -i ./cmd/templates/css/main.css -o ./static/css/main.css

run: build
	@./frontend

dev: build
	@air
