package routes

import (
	"frontend/cmd/server/utils"
	"html/template"
	"net/http"
)

func Index(w http.ResponseWriter, r *http.Request) {
	if r.URL.Path != "/" {
		http.NotFound(w, r)
		return
	}

	templates := utils.CollectGlobalTemplates()
	templates = append(templates, "cmd/templates/pages/index.html.tmpl")

	tpl := template.Must(template.ParseFiles(templates...))
	err := tpl.Execute(w, nil)

	if err != nil {
		http.NotFound(w, r)
	}
}
