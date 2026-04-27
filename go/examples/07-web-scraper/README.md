# Web Scraper (⭐⭐⭐ Senior)

URL-ləri concurrent şəkildə fetch edən, title və linklər çıxaran scraper. Semaphore ilə concurrency limit.

## Öyrənilən Konseptlər

- `http.Client` — custom timeout, redirect policy
- **Semaphore pattern**: `chan struct{}` ilə concurrent request limit
- `regexp` ilə HTML-dən title/link çıxarma
- `net/url` — relative URL-i absolute-a çevirmə
- `sync.WaitGroup` + buffered channel ilə goroutine collect
- `bufio.Scanner` ilə URL siyahısı oxuma

## İşə Salma

```bash
# Default URL-lərlə (internet lazımdır)
go run main.go

# Konkret URL-lər
go run main.go https://go.dev https://pkg.go.dev

# Fayldan URL-lər (biri hər sırda)
go run main.go -f urls.txt

# Linklər də göstər
go run main.go -links https://go.dev

# Concurrency və timeout dəyiş
go run main.go -c 3 -t 5s https://go.dev https://github.com
```

## urls.txt Nümunəsi

```
https://go.dev
https://pkg.go.dev
# Bu şərh sayılmır
https://go.dev/blog
```

## Nümunə Output

```
Scraping 3 URL(s) [concurrency=5, timeout=10s]...

  ✓ [200] https://go.dev
    title: The Go Programming Language
    links: 47

  ✓ [200] https://pkg.go.dev
    title: Go Packages
    links: 23

Completed in 842ms
```

## Semaphore Necə İşləyir

```go
sem := make(chan struct{}, 5)  // max 5 concurrent

go func() {
    sem <- struct{}{}          // acquire (blocks if full)
    defer func() { <-sem }()  // release
    // ... iş gör
}()
```

## İrəli Getmək Üçün

- Depth-limited BFS crawl (visited map ilə)
- Rate limiting per domain (token bucket)
- Results CSV-ə export
- `golang.org/x/net/html` ilə daha dəqiq parsing
