.PHONY: build

default: build

build:
	@go build -ldflags="-s -w"
	@npx tailwindcss -m -i ./cmd/templates/css/main.css -o ./static/css/main.css

run: build
	@./frontend

dev: build
	@npx tailwindcss -i ./cmd/templates/css/main.css -o ./static/css/main.css --watch
