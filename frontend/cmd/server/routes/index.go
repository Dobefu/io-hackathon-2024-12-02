package routes

import (
	"html/template"
	"net/http"
)

func Index(w http.ResponseWriter, r *http.Request) {
	if r.URL.Path != "/" {
		http.NotFound(w, r)
		return
	}

	tpl := template.Must(template.ParseFiles(
		"cmd/templates/html.tpl.html",
		"cmd/templates/layout/header.tpl.html",
		"cmd/templates/layout/footer.tpl.html",
	))
	err := tpl.Execute(w, nil)

	if err != nil {
		http.NotFound(w, r)
	}
}
