package main

import (
	"fmt"
	htmltemplate "html/template"
	"os"
	"strings"
	texttemplate "text/template"
)

// ===============================================
// TEXT VE HTML TEMPLATES
// ===============================================

// Go-da iki template paketi var:
// text/template  - umumi metn generasiyasi
// html/template  - HTML ucun (XSS qorunmasi ile)

func main() {

	// -------------------------------------------
	// 1. Sadə text template
	// -------------------------------------------
	fmt.Println("=== Sadə Template ===")

	tmpl := texttemplate.Must(texttemplate.New("salam").Parse(
		"Salam, {{.Ad}}! Sen {{.Yas}} yasindasan.\n",
	))

	data := struct {
		Ad  string
		Yas int
	}{"Orkhan", 25}

	tmpl.Execute(os.Stdout, data)

	// -------------------------------------------
	// 2. Map ile template
	// -------------------------------------------
	tmpl2 := texttemplate.Must(texttemplate.New("map").Parse(
		"Sheher: {{.sheher}}, Olke: {{.olke}}\n",
	))
	tmpl2.Execute(os.Stdout, map[string]string{
		"sheher": "Baku",
		"olke":   "Azerbaycan",
	})

	// -------------------------------------------
	// 3. Sertler (if/else)
	// -------------------------------------------
	fmt.Println("\n=== Sertler ===")

	sertTmpl := texttemplate.Must(texttemplate.New("sert").Parse(
		`{{if .Admin}}Salam Admin {{.Ad}}!{{else}}Salam {{.Ad}}, siz adi istifadecisiz.{{end}}
`))

	sertTmpl.Execute(os.Stdout, map[string]interface{}{"Ad": "Orkhan", "Admin": true})
	sertTmpl.Execute(os.Stdout, map[string]interface{}{"Ad": "Eli", "Admin": false})

	// -------------------------------------------
	// 4. Dongu (range)
	// -------------------------------------------
	fmt.Println("\n=== Dongu ===")

	donguTmpl := texttemplate.Must(texttemplate.New("dongu").Parse(
		`Meyveler:
{{range .Meyveler}}- {{.}}
{{end}}Nomreli:
{{range $i, $v := .Ededler}}  {{$i}}: {{$v}}
{{end}}`))

	donguTmpl.Execute(os.Stdout, map[string]interface{}{
		"Meyveler": []string{"Alma", "Armud", "Nar"},
		"Ededler":  []int{10, 20, 30},
	})

	// -------------------------------------------
	// 5. Xususi funksiyalar (FuncMap)
	// -------------------------------------------
	fmt.Println("\n=== Xususi Funksiyalar ===")

	funcMap := texttemplate.FuncMap{
		"boyuk":  strings.ToUpper,
		"tekrar": strings.Repeat,
		"topla":  func(a, b int) int { return a + b },
	}

	funcTmpl := texttemplate.Must(
		texttemplate.New("func").Funcs(funcMap).Parse(
			"Boyuk: {{boyuk .Ad}}\nTekrar: {{tekrar \"Go! \" 3}}\nToplam: {{topla 5 3}}\n",
		),
	)
	funcTmpl.Execute(os.Stdout, map[string]string{"Ad": "orkhan"})

	// -------------------------------------------
	// 6. Template-leri birlesdirme (define/template)
	// -------------------------------------------
	fmt.Println("\n=== Template Birlesdirme ===")

	masterTmpl := texttemplate.Must(texttemplate.New("master").Parse(
		`{{define "header"}}=== {{.Bashliq}} ==={{end}}{{define "footer"}}--- Son ---{{end}}{{template "header" .}}
Mezmun: {{.Mezmun}}
{{template "footer"}}
`))
	masterTmpl.Execute(os.Stdout, map[string]string{
		"Bashliq": "Menim Sehifem",
		"Mezmun":  "Salam dunya!",
	})

	// -------------------------------------------
	// 7. HTML Template (XSS qorunmasi)
	// -------------------------------------------
	fmt.Println("\n=== HTML Template ===")

	htmlTmpl := htmltemplate.Must(htmltemplate.New("html").Parse(
		"<h1>{{.Bashliq}}</h1>\n<p>{{.Mezmun}}</p>\n",
	))

	// Tehlukeli giris avtomatik escape olunur
	htmlTmpl.Execute(os.Stdout, map[string]string{
		"Bashliq": "<script>alert('XSS')</script>", // escape olunacaq
		"Mezmun":  "Normal metn",
	})

	// -------------------------------------------
	// 8. Fayldan template yukleme
	// -------------------------------------------
	fmt.Println(`
=== Fayldan Template ===

// Tek fayl
tmpl, err := template.ParseFiles("templates/index.html")

// Glob ile
tmpl, err := template.ParseGlob("templates/*.html")

// HTTP handler-de:
func handler(w http.ResponseWriter, r *http.Request) {
    tmpl.ExecuteTemplate(w, "layout", data)
}`)

	// -------------------------------------------
	// 9. Daxili funksiyalar
	// -------------------------------------------
	// and, or, not          - mentiqi
	// eq, ne, lt, le, gt, ge - muqayise
	// len, index, slice     - kolleksiya
	// printf, print, println - formatlama
	// call                   - funksiya cagirmaq
	// html, js, urlquery     - escape

	fmt.Println("\n=== Pipe ornegi ===")
	pipeTmpl := texttemplate.Must(texttemplate.New("pipe").Parse(
		"Uzunluq: {{len .Siyahi}}\nIndex 1: {{index .Siyahi 1}}\n",
	))
	pipeTmpl.Execute(os.Stdout, map[string]interface{}{
		"Siyahi": []string{"a", "b", "c"},
	})
}
