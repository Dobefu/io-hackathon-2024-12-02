package routes

import (
	"encoding/json"
	"fmt"
	"frontend/cmd/server/utils"
	"html/template"
	"log"
	"net/http"
	"os"
	"strings"
)

func QuoteById(w http.ResponseWriter, r *http.Request) {
	templates := utils.CollectGlobalTemplates()
	templates = append(templates, "cmd/templates/pages/quote.html.tmpl")
	templates = append(templates, "cmd/templates/partials/quote.html.tmpl")
	templates = append(templates, "cmd/templates/partials/quote--teaser.html.tmpl")

	id := r.PathValue("id")
	quote, err := getQuoteById(id)

	if err != nil {
		log.Println(err.Error())
		http.NotFound(w, r)
		return
	}

	quotesByPerson, err := getQuotesByPerson(quote["person"].(string))

	var relatedQuotes []interface{}

	for _, quoteByPerson := range quotesByPerson {
		if quote["id"] == quoteByPerson.(map[string]interface{})["id"] {
			continue
		}

		if len(relatedQuotes) >= 3 {
			break
		}

		relatedQuotes = append(relatedQuotes, quoteByPerson)
	}

	if err != nil {
		log.Println(err.Error())
		http.NotFound(w, r)
		return
	}

	data := make(map[string]interface{})
	data["Quote"] = quote
	data["RelatedQuotes"] = relatedQuotes

	tpl := template.Must(template.ParseFiles(templates...))
	err = tpl.Execute(w, &data)

	if err != nil {
		log.Println(err.Error())
		return
	}
}

func getQuoteById(id string) (map[string]interface{}, error) {
	endpoint := os.Getenv("API_ENDPOINT")
	url := fmt.Sprintf("%s/quote/get/%s?token=%s", endpoint, id, os.Getenv("API_KEY"))

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

	return output.(map[string]interface{}), nil
}

func getQuotesByPerson(person string) ([]interface{}, error) {
	endpoint := os.Getenv("API_ENDPOINT")
	url := fmt.Sprintf("%s/quote/get?token=%s&sortOrder=desc", endpoint, os.Getenv("API_KEY"))

	if person != "" {
		person = strings.ReplaceAll(person, " ", "%20")
		url = fmt.Sprintf("%s&person=%s", url, person)
	}

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

	return output, nil
}
