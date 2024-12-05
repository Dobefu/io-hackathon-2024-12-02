package utils

func CollectGlobalTemplates() []string {
	return []string{
		"cmd/templates/html.html.tmpl",
		"cmd/templates/layout/header.html.tmpl",
		"cmd/templates/layout/footer.html.tmpl",
		"cmd/templates/partials/page-title.html.tmpl",
	}
}
