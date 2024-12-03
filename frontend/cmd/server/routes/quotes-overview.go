package routes

import (
	"html/template"
	"net/http"
)

func QuotesOverview(w http.ResponseWriter, r *http.Request) {
	tpl := template.Must(template.ParseFiles(
		"cmd/templates/html.tpl.html",
		"cmd/templates/layout/header.tpl.html",
		"cmd/templates/layout/footer.tpl.html",
		"cmd/templates/pages/quotes.tpl.html",
	))

	err := tpl.Execute(w, nil)

	if err != nil {
		http.NotFound(w, r)
	}
}
