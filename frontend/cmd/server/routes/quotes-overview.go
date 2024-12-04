package routes

import (
	"encoding/json"
	"fmt"
	"frontend/cmd/server/utils"
	"html/template"
	"log"
	"net/http"
	"os"
)

func QuotesOverview(w http.ResponseWriter, r *http.Request) {
	templates := utils.CollectGlobalTemplates()
	templates = append(templates, "cmd/templates/pages/quotes.html.tmpl")
	templates = append(templates, "cmd/templates/partials/quote--teaser.html.tmpl")

	query := r.URL.Query()
	quotes, err := getQuotes(query.Get("person"))

	if err != nil {
		log.Println(err.Error())
		http.NotFound(w, r)
		return
	}

	data := make(map[string]interface{})
	data["Quotes"] = quotes
	data["Search"] = query

	tpl := template.Must(template.ParseFiles(templates...))
	err = tpl.Execute(w, &data)

	if err != nil {
		log.Println(err.Error())
		return
	}
}

func getQuotes(person string) (interface{}, error) {
	endpoint := os.Getenv("API_ENDPOINT")
	url := fmt.Sprintf("%s/quote/get?token=%s", endpoint, os.Getenv("API_KEY"))

	if person != "" {
		url = fmt.Sprintf("%s&person=%s", url, person)
	}

	response, err := http.Get(url)

	if err != nil {
		return nil, err
	}

	defer response.Body.Close()

	var output interface{}
	err = json.NewDecoder(response.Body).Decode(&output)

	if err != nil {
		return nil, err
	}

	return output, nil
}
