package routes

import (
	"encoding/json"
	"fmt"
	"frontend/cmd/server/utils"
	"html/template"
	"net/http"
	"os"
)

func QuotesOverview(w http.ResponseWriter, r *http.Request) {
	templates := utils.CollectGlobalTemplates()
	templates = append(templates, "cmd/templates/pages/quotes.tpl.html")

	quotes, err := getQuotes()

	if err != nil {
		http.NotFound(w, r)
		return
	}

	data := make(map[string]interface{})
	data["Quotes"] = quotes

	tpl := template.Must(template.ParseFiles(templates...))
	err = tpl.Execute(w, &data)

	if err != nil {
		http.NotFound(w, r)
	}
}

func getQuotes() (interface{}, error) {
	endpoint := os.Getenv("API_ENDPOINT")
	url := fmt.Sprintf("%s/quote", endpoint)

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
