package main

import (
	"encoding/json"
	"fmt"
	"log"
	"math/rand"
	"net/http"
	"strings"
	"sync"
	"time"
)

type entry struct {
	Code      string    `json:"code"`
	Original  string    `json:"original"`
	ShortURL  string    `json:"short_url"`
	Clicks    int       `json:"clicks"`
	CreatedAt time.Time `json:"created_at"`
}

type store struct {
	mu      sync.RWMutex
	entries map[string]*entry
}

func newStore() *store {
	return &store{entries: make(map[string]*entry)}
}

const alphabet = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789"

func randomCode(n int) string {
	b := make([]byte, n)
	for i := range b {
		b[i] = alphabet[rand.Intn(len(alphabet))]
	}
	return string(b)
}

func (s *store) shorten(original, baseURL string) *entry {
	s.mu.Lock()
	defer s.mu.Unlock()
	code := randomCode(6)
	for {
		if _, exists := s.entries[code]; !exists {
			break
		}
		code = randomCode(6)
	}
	e := &entry{
		Code:      code,
		Original:  original,
		ShortURL:  baseURL + "/" + code,
		CreatedAt: time.Now(),
	}
	s.entries[code] = e
	return e
}

func (s *store) get(code string) (*entry, bool) {
	s.mu.Lock()
	defer s.mu.Unlock()
	e, ok := s.entries[code]
	if ok {
		e.Clicks++
	}
	return e, ok
}

func (s *store) all() []*entry {
	s.mu.RLock()
	defer s.mu.RUnlock()
	result := make([]*entry, 0, len(s.entries))
	for _, e := range s.entries {
		result = append(result, e)
	}
	return result
}

func respond(w http.ResponseWriter, status int, v any) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(status)
	json.NewEncoder(w).Encode(v)
}

func main() {
	const addr = ":8080"
	const baseURL = "http://localhost" + addr

	db := newStore()
	mux := http.NewServeMux()

	// POST /shorten  {"url": "https://..."}
	mux.HandleFunc("/shorten", func(w http.ResponseWriter, r *http.Request) {
		if r.Method != http.MethodPost {
			http.Error(w, "method not allowed", http.StatusMethodNotAllowed)
			return
		}
		var req struct {
			URL string `json:"url"`
		}
		if err := json.NewDecoder(r.Body).Decode(&req); err != nil || req.URL == "" {
			respond(w, 400, map[string]string{"error": "url is required"})
			return
		}
		if !strings.HasPrefix(req.URL, "http") {
			req.URL = "https://" + req.URL
		}
		respond(w, 201, db.shorten(req.URL, baseURL))
	})

	// GET /stats
	mux.HandleFunc("/stats", func(w http.ResponseWriter, r *http.Request) {
		respond(w, 200, db.all())
	})

	// GET /{code} — redirect
	mux.HandleFunc("/", func(w http.ResponseWriter, r *http.Request) {
		code := strings.TrimPrefix(r.URL.Path, "/")
		if code == "" {
			respond(w, 200, map[string]string{
				"usage": "POST /shorten {\"url\":\"...\"} | GET /{code} | GET /stats",
			})
			return
		}
		e, ok := db.get(code)
		if !ok {
			http.Error(w, "not found", http.StatusNotFound)
			return
		}
		http.Redirect(w, r, e.Original, http.StatusTemporaryRedirect)
	})

	fmt.Printf("URL Shortener: %s\n\n", baseURL)
	fmt.Println("POST /shorten  {\"url\": \"https://example.com\"}")
	fmt.Println("GET  /{code}   → redirect to original")
	fmt.Println("GET  /stats    → all entries with click counts")
	log.Fatal(http.ListenAndServe(addr, mux))
}
