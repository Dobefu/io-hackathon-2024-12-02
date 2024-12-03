package routes

import (
	"frontend/cmd/server/utils"
	"html/template"
	"net/http"
)

func QuotesOverview(w http.ResponseWriter, r *http.Request) {
	templates := utils.CollectGlobalTemplates()
	templates = append(templates, "cmd/templates/pages/quotes.tpl.html")

	tpl := template.Must(template.ParseFiles(templates...))

	err := tpl.Execute(w, nil)

	if err != nil {
		http.NotFound(w, r)
	}
}
