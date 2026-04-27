package main

import (
	"encoding/json"
	"fmt"
	"log"
	"net/http"
	"strconv"
	"strings"
	"sync"
	"time"
)

type Book struct {
	ID        int       `json:"id"`
	Title     string    `json:"title"`
	Author    string    `json:"author"`
	Year      int       `json:"year"`
	CreatedAt time.Time `json:"created_at"`
}

type Store struct {
	mu     sync.RWMutex
	books  map[int]Book
	nextID int
}

func NewStore() *Store {
	return &Store{books: make(map[int]Book), nextID: 1}
}

func (s *Store) Create(b Book) Book {
	s.mu.Lock()
	defer s.mu.Unlock()
	b.ID = s.nextID
	b.CreatedAt = time.Now()
	s.books[b.ID] = b
	s.nextID++
	return b
}

func (s *Store) GetAll() []Book {
	s.mu.RLock()
	defer s.mu.RUnlock()
	books := make([]Book, 0, len(s.books))
	for _, b := range s.books {
		books = append(books, b)
	}
	return books
}

func (s *Store) GetByID(id int) (Book, bool) {
	s.mu.RLock()
	defer s.mu.RUnlock()
	b, ok := s.books[id]
	return b, ok
}

func (s *Store) Update(id int, b Book) (Book, bool) {
	s.mu.Lock()
	defer s.mu.Unlock()
	existing, ok := s.books[id]
	if !ok {
		return Book{}, false
	}
	b.ID = existing.ID
	b.CreatedAt = existing.CreatedAt
	s.books[id] = b
	return b, true
}

func (s *Store) Delete(id int) bool {
	s.mu.Lock()
	defer s.mu.Unlock()
	_, ok := s.books[id]
	if ok {
		delete(s.books, id)
	}
	return ok
}

func respond(w http.ResponseWriter, status int, data any) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(status)
	if data != nil {
		json.NewEncoder(w).Encode(data)
	}
}

type handler struct{ store *Store }

func (h *handler) ServeHTTP(w http.ResponseWriter, r *http.Request) {
	// strip /books prefix and trailing slash
	path := strings.Trim(strings.TrimPrefix(r.URL.Path, "/books"), "/")

	if path == "" {
		switch r.Method {
		case http.MethodGet:
			respond(w, 200, h.store.GetAll())
		case http.MethodPost:
			var b Book
			if err := json.NewDecoder(r.Body).Decode(&b); err != nil {
				respond(w, 400, map[string]string{"error": "invalid body"})
				return
			}
			respond(w, 201, h.store.Create(b))
		default:
			http.Error(w, "method not allowed", http.StatusMethodNotAllowed)
		}
		return
	}

	id, err := strconv.Atoi(path)
	if err != nil {
		respond(w, 400, map[string]string{"error": "invalid id"})
		return
	}

	switch r.Method {
	case http.MethodGet:
		b, ok := h.store.GetByID(id)
		if !ok {
			respond(w, 404, map[string]string{"error": "not found"})
			return
		}
		respond(w, 200, b)
	case http.MethodPut:
		var b Book
		if err := json.NewDecoder(r.Body).Decode(&b); err != nil {
			respond(w, 400, map[string]string{"error": "invalid body"})
			return
		}
		updated, ok := h.store.Update(id, b)
		if !ok {
			respond(w, 404, map[string]string{"error": "not found"})
			return
		}
		respond(w, 200, updated)
	case http.MethodDelete:
		if !h.store.Delete(id) {
			respond(w, 404, map[string]string{"error": "not found"})
			return
		}
		respond(w, 204, nil)
	default:
		http.Error(w, "method not allowed", http.StatusMethodNotAllowed)
	}
}

func logging(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		start := time.Now()
		next.ServeHTTP(w, r)
		log.Printf("%s %s %v", r.Method, r.URL.Path, time.Since(start))
	})
}

func main() {
	store := NewStore()
	store.Create(Book{Title: "The Go Programming Language", Author: "Donovan & Kernighan", Year: 2015})
	store.Create(Book{Title: "Clean Code", Author: "Robert C. Martin", Year: 2008})
	store.Create(Book{Title: "Designing Data-Intensive Applications", Author: "Martin Kleppmann", Year: 2017})

	h := &handler{store: store}
	mux := http.NewServeMux()
	mux.Handle("/books", logging(h))
	mux.Handle("/books/", logging(h))
	mux.HandleFunc("/", func(w http.ResponseWriter, r *http.Request) {
		respond(w, 200, map[string]string{"message": "Books API v1", "docs": "/books"})
	})

	addr := ":8080"
	fmt.Printf("Server: http://localhost%s\n", addr)
	fmt.Println("GET    /books          - list all")
	fmt.Println("POST   /books          - create")
	fmt.Println("GET    /books/{id}     - get one")
	fmt.Println("PUT    /books/{id}     - update")
	fmt.Println("DELETE /books/{id}     - delete")
	log.Fatal(http.ListenAndServe(addr, mux))
}
