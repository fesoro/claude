# Books REST API (⭐⭐ Middle)

`net/http` ilə yalnız standart kitabxana istifadə edərək yazılmış tam CRUD REST API. In-memory storage, logging middleware.

## Öyrənilən Konseptlər

- `net/http` — server, `ServeMux`, handler interface
- JSON encode/decode (`encoding/json`)
- `sync.RWMutex` ilə thread-safe in-memory store
- Middleware pattern (logging)
- URL path parsing, HTTP status codes

## Endpoints

| Method | Path | Təsvir |
|--------|------|--------|
| GET | / | API info |
| GET | /books | Bütün kitablar |
| POST | /books | Yeni kitab əlavə et |
| GET | /books/{id} | ID ilə kitab gətir |
| PUT | /books/{id} | Kitabı yenilə |
| DELETE | /books/{id} | Kitabı sil |

## İşə Salma

```bash
go run main.go
# → http://localhost:8080
```

## İstifadə Nümunəsi

```bash
# Bütün kitablar
curl http://localhost:8080/books

# Yeni kitab
curl -X POST http://localhost:8080/books \
  -H "Content-Type: application/json" \
  -d '{"title":"Clean Code","author":"R.C. Martin","year":2008}'

# ID ilə gətir
curl http://localhost:8080/books/1

# Yenilə
curl -X PUT http://localhost:8080/books/1 \
  -H "Content-Type: application/json" \
  -d '{"title":"Clean Code Updated","author":"R.C. Martin","year":2008}'

# Sil
curl -X DELETE http://localhost:8080/books/1
```

## İrəli Getmək Üçün

- Request validation (boş title rədd et)
- Pagination (`?page=1&limit=10`)
- Persistent storage (SQLite, PostgreSQL)
- `gin` və ya `chi` router-ə keçid
