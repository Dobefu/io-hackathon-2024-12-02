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

	tpl := template.Must(template.ParseFiles("cmd/templates/html.tpl.html"))
	tpl.Execute(w, nil)
}
