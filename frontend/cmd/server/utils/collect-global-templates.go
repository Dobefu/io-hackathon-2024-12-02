package utils

func CollectGlobalTemplates() []string {
	return []string{
		"cmd/templates/html.tpl.html",
		"cmd/templates/layout/header.tpl.html",
		"cmd/templates/layout/footer.tpl.html",
		"cmd/templates/partials/page-title.tpl.html",
	}
}
