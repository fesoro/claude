package main

import (
	"encoding/json"
	"fmt"
	"log"
	"net/http"
)

// ===============================================
// HTTP SERVER ve REST API
// ===============================================

// Go-da veb server yaratmaq cox sadedir
// Xarici framework lazim deyil - standart "net/http" paketi kifayetdir

// -------------------------------------------
// Model
// -------------------------------------------
type Kitab struct {
	ID     int    `json:"id"`
	Ad     string `json:"ad"`
	Muellif string `json:"muellif"`
	Qiymet float64 `json:"qiymet"`
}

// Sadə in-memory verilənlər bazası
var kitablar = []Kitab{
	{ID: 1, Ad: "Go Proqramlasdirma", Muellif: "Eli", Qiymet: 25.99},
	{ID: 2, Ad: "Web Development", Muellif: "Veli", Qiymet: 19.99},
}
var novbetiID = 3

// -------------------------------------------
// Handler funksiyalari
// -------------------------------------------

// GET / - Salam
func salamHandler(w http.ResponseWriter, r *http.Request) {
	fmt.Fprintf(w, "Salam! Go HTTP Server isleyir.")
}

// GET/POST /kitablar
func kitablarHandler(w http.ResponseWriter, r *http.Request) {
	switch r.Method {
	case http.MethodGet:
		// Butun kitablari qaytir
		w.Header().Set("Content-Type", "application/json")
		json.NewEncoder(w).Encode(kitablar)

	case http.MethodPost:
		// Yeni kitab elave et
		var yeniKitab Kitab
		err := json.NewDecoder(r.Body).Decode(&yeniKitab)
		if err != nil {
			http.Error(w, "Yanlis JSON formati", http.StatusBadRequest)
			return
		}
		yeniKitab.ID = novbetiID
		novbetiID++
		kitablar = append(kitablar, yeniKitab)

		w.Header().Set("Content-Type", "application/json")
		w.WriteHeader(http.StatusCreated)
		json.NewEncoder(w).Encode(yeniKitab)

	default:
		http.Error(w, "Icaze verilmir", http.StatusMethodNotAllowed)
	}
}

// JSON cavab gondermek ucun helper
func jsonCavab(w http.ResponseWriter, status int, data interface{}) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(status)
	json.NewEncoder(w).Encode(data)
}

// -------------------------------------------
// Middleware ornegi
// -------------------------------------------
func loggingMiddleware(next http.HandlerFunc) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		log.Printf("[%s] %s", r.Method, r.URL.Path)
		next(w, r)
	}
}

func main() {

	// Route-lar
	http.HandleFunc("/", loggingMiddleware(salamHandler))
	http.HandleFunc("/kitablar", loggingMiddleware(kitablarHandler))

	// Statik fayllar (HTML, CSS, JS)
	// http.Handle("/static/", http.StripPrefix("/static/", http.FileServer(http.Dir("static"))))

	port := ":8080"
	fmt.Printf("Server %s portunda isleyir...\n", port)
	fmt.Println("Brauzerden: http://localhost:8080")
	fmt.Println("Kitablar:   http://localhost:8080/kitablar")
	fmt.Println()
	fmt.Println("Test emrleri:")
	fmt.Println("  curl http://localhost:8080/")
	fmt.Println("  curl http://localhost:8080/kitablar")
	fmt.Println(`  curl -X POST -H "Content-Type: application/json" -d '{"ad":"Yeni Kitab","muellif":"Muellif","qiymet":15.99}' http://localhost:8080/kitablar`)

	log.Fatal(http.ListenAndServe(port, nil))
}

// QEYDLER:
// - Bu sadə bir ornek. Real layihelerde gorilla/mux ve ya chi router istifade olunur
// - Go 1.22+ http.NewServeMux ile daha yaxsi routing destekleyir
// - Production ucun graceful shutdown, CORS, rate limiting elave edin
