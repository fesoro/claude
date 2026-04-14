package main

import (
	"encoding/json"
	"fmt"
	"log"
	"net/http"
	"time"
)

// ===============================================
// MIDDLEWARE VE ROUTING (Go 1.22+)
// ===============================================

// Go 1.22 ile ServeMux guclendirildi:
// - Metod filteri: "GET /users"
// - URL parametrleri: "/users/{id}"
// - Daha deqiq uygunlasma

// -------------------------------------------
// 1. Middleware tipi
// -------------------------------------------
// Middleware - handler-den evvel/sonra isleyen funksiya
// Logging, auth, CORS, rate limiting ucun istifade olunur

type Middleware func(http.Handler) http.Handler

// -------------------------------------------
// 2. Logging middleware
// -------------------------------------------
func LoggingMiddleware(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		baslangic := time.Now()

		// Novbeti handler-i cagir
		next.ServeHTTP(w, r)

		muddet := time.Since(baslangic)
		log.Printf("[%s] %s %s", r.Method, r.URL.Path, muddet)
	})
}

// -------------------------------------------
// 3. CORS middleware
// -------------------------------------------
func CORSMiddleware(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Access-Control-Allow-Origin", "*")
		w.Header().Set("Access-Control-Allow-Methods", "GET, POST, PUT, DELETE, OPTIONS")
		w.Header().Set("Access-Control-Allow-Headers", "Content-Type, Authorization")

		// Preflight sorgusu
		if r.Method == "OPTIONS" {
			w.WriteHeader(http.StatusOK)
			return
		}

		next.ServeHTTP(w, r)
	})
}

// -------------------------------------------
// 4. Auth middleware
// -------------------------------------------
func AuthMiddleware(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		token := r.Header.Get("Authorization")
		if token == "" {
			http.Error(w, `{"xeta": "token lazimdir"}`, http.StatusUnauthorized)
			return
		}

		// Token-i yoxla (real layihede JWT yoxlamasi olardi)
		if token != "Bearer gizli-token" {
			http.Error(w, `{"xeta": "yanlis token"}`, http.StatusForbidden)
			return
		}

		next.ServeHTTP(w, r)
	})
}

// -------------------------------------------
// 5. Recovery middleware (panic tutucu)
// -------------------------------------------
func RecoveryMiddleware(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		defer func() {
			if err := recover(); err != nil {
				log.Printf("PANIC: %v", err)
				http.Error(w, `{"xeta": "daxili server xetasi"}`, http.StatusInternalServerError)
			}
		}()
		next.ServeHTTP(w, r)
	})
}

// -------------------------------------------
// 6. Middleware zenciri
// -------------------------------------------
func ZencirMiddleware(handler http.Handler, middlewares ...Middleware) http.Handler {
	// Sondan evvele dogru sariyiq ki, birinci elan olunan birinci islesin
	for i := len(middlewares) - 1; i >= 0; i-- {
		handler = middlewares[i](handler)
	}
	return handler
}

// -------------------------------------------
// 7. JSON helper funksiyalar
// -------------------------------------------
func jsonCavab(w http.ResponseWriter, status int, data interface{}) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(status)
	json.NewEncoder(w).Encode(data)
}

func jsonXeta(w http.ResponseWriter, status int, mesaj string) {
	jsonCavab(w, status, map[string]string{"xeta": mesaj})
}

// -------------------------------------------
// 8. Modeller ve handler-lar
// -------------------------------------------
type Istifadeci struct {
	ID    string `json:"id"`
	Ad    string `json:"ad"`
	Email string `json:"email"`
}

var istifadeciler = map[string]Istifadeci{
	"1": {ID: "1", Ad: "Orkhan", Email: "orkhan@mail.az"},
	"2": {ID: "2", Ad: "Eli", Email: "eli@mail.az"},
}

func main() {
	mux := http.NewServeMux()

	// -------------------------------------------
	// Go 1.22+ Routing
	// -------------------------------------------

	// GET /
	mux.HandleFunc("GET /", func(w http.ResponseWriter, r *http.Request) {
		jsonCavab(w, http.StatusOK, map[string]string{
			"mesaj":   "Salam! API isleyir.",
			"versiya": "1.0",
		})
	})

	// GET /istifadeciler - hamisi
	mux.HandleFunc("GET /istifadeciler", func(w http.ResponseWriter, r *http.Request) {
		list := make([]Istifadeci, 0, len(istifadeciler))
		for _, ist := range istifadeciler {
			list = append(list, ist)
		}
		jsonCavab(w, http.StatusOK, list)
	})

	// GET /istifadeciler/{id} - URL parametri ile
	mux.HandleFunc("GET /istifadeciler/{id}", func(w http.ResponseWriter, r *http.Request) {
		id := r.PathValue("id") // Go 1.22+ URL parametrini almaq

		ist, var_mi := istifadeciler[id]
		if !var_mi {
			jsonXeta(w, http.StatusNotFound, "istifadeci tapilmadi")
			return
		}
		jsonCavab(w, http.StatusOK, ist)
	})

	// POST /istifadeciler - yeni yaratmaq
	mux.HandleFunc("POST /istifadeciler", func(w http.ResponseWriter, r *http.Request) {
		var yeni Istifadeci
		if err := json.NewDecoder(r.Body).Decode(&yeni); err != nil {
			jsonXeta(w, http.StatusBadRequest, "yanlis JSON formati")
			return
		}

		if yeni.Ad == "" || yeni.Email == "" {
			jsonXeta(w, http.StatusBadRequest, "ad ve email mecburidir")
			return
		}

		yeni.ID = fmt.Sprintf("%d", len(istifadeciler)+1)
		istifadeciler[yeni.ID] = yeni
		jsonCavab(w, http.StatusCreated, yeni)
	})

	// DELETE /istifadeciler/{id}
	mux.HandleFunc("DELETE /istifadeciler/{id}", func(w http.ResponseWriter, r *http.Request) {
		id := r.PathValue("id")
		if _, var_mi := istifadeciler[id]; !var_mi {
			jsonXeta(w, http.StatusNotFound, "istifadeci tapilmadi")
			return
		}
		delete(istifadeciler, id)
		jsonCavab(w, http.StatusOK, map[string]string{"mesaj": "silindi"})
	})

	// -------------------------------------------
	// Middleware-leri tetbiq et
	// -------------------------------------------
	handler := ZencirMiddleware(
		mux,
		RecoveryMiddleware,
		LoggingMiddleware,
		CORSMiddleware,
	)

	// Yalniz mueyyen route-lar ucun auth
	// AuthMiddleware yalniz lazim olan handler-lere elave olunur

	port := ":8080"
	fmt.Printf("Server %s portunda isleyir...\n", port)
	fmt.Println()
	fmt.Println("Test emrleri:")
	fmt.Println("  curl http://localhost:8080/")
	fmt.Println("  curl http://localhost:8080/istifadeciler")
	fmt.Println("  curl http://localhost:8080/istifadeciler/1")
	fmt.Println(`  curl -X POST -H "Content-Type: application/json" -d '{"ad":"Veli","email":"veli@mail.az"}' http://localhost:8080/istifadeciler`)
	fmt.Println("  curl -X DELETE http://localhost:8080/istifadeciler/1")

	log.Fatal(http.ListenAndServe(port, handler))
}
