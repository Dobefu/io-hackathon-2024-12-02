package routes

import (
	"encoding/json"
	"fmt"
	"frontend/cmd/server/utils"
	"html/template"
	"net/http"
	"os"
)

func QuoteById(w http.ResponseWriter, r *http.Request) {
	templates := utils.CollectGlobalTemplates()
	templates = append(templates, "cmd/templates/pages/quote.html.tmpl")
	templates = append(templates, "cmd/templates/partials/quote.html.tmpl")

	id := r.PathValue("id")
	quote, err := getQuoteById(id)

	if err != nil {
		http.NotFound(w, r)
		return
	}

	data := make(map[string]interface{})
	data["Quote"] = quote

	tpl := template.Must(template.ParseFiles(templates...))
	err = tpl.Execute(w, &data)

	if err != nil {
		return
	}
}

func getQuoteById(id string) (interface{}, error) {
	endpoint := os.Getenv("API_ENDPOINT")
	url := fmt.Sprintf("%s/quote?token=%s", endpoint, os.Getenv("API_KEY"))

	response, err := http.Get(url)

	if err != nil {
		return nil, err
	}

	defer response.Body.Close()

	var output []interface{}
	err = json.NewDecoder(response.Body).Decode(&output)

	if err != nil {
		return nil, err
	}

	return output[0], nil
}
