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

func Index(w http.ResponseWriter, r *http.Request) {
	if r.URL.Path != "/" {
		http.NotFound(w, r)
		return
	}

	templates := utils.CollectGlobalTemplates()
	templates = append(templates, "cmd/templates/pages/index.html.tmpl")

	quote, err := getRandomQuote()

	if err != nil {
		log.Println(err.Error())
		http.NotFound(w, r)
		return
	}

	data := make(map[string]interface{})
	data["Quote"] = quote

	tpl := template.Must(template.ParseFiles(templates...))
	err = tpl.Execute(w, nil)

	if err != nil {
		log.Println(err.Error())
		http.NotFound(w, r)
		return
	}
}

func getRandomQuote() (map[string]interface{}, error) {
	endpoint := os.Getenv("API_ENDPOINT")
	url := fmt.Sprintf("%s/quote/random?token=%s", endpoint, os.Getenv("API_KEY"))

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
