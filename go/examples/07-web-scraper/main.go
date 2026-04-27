package main

import (
	"bufio"
	"flag"
	"fmt"
	"io"
	"net/http"
	"net/url"
	"os"
	"regexp"
	"strings"
	"sync"
	"time"
)

type Page struct {
	URL    string
	Status int
	Title  string
	Links  []string
	Err    error
}

var (
	titleRE = regexp.MustCompile(`(?i)<title[^>]*>([^<]+)</title>`)
	hrefRE  = regexp.MustCompile(`(?i)href="([^"#][^"]*)"`)
)

func fetch(rawURL string, client *http.Client) Page {
	p := Page{URL: rawURL}

	resp, err := client.Get(rawURL)
	if err != nil {
		p.Err = err
		return p
	}
	defer resp.Body.Close()
	p.Status = resp.StatusCode

	body, err := io.ReadAll(io.LimitReader(resp.Body, 1<<20)) // 1 MB cap
	if err != nil {
		p.Err = err
		return p
	}

	if m := titleRE.FindSubmatch(body); len(m) > 1 {
		p.Title = strings.TrimSpace(string(m[1]))
	}

	base, _ := url.Parse(rawURL)
	for _, m := range hrefRE.FindAllSubmatch(body, -1) {
		link := string(m[1])
		if strings.HasPrefix(link, "mailto:") || strings.HasPrefix(link, "javascript:") {
			continue
		}
		abs, err := base.Parse(link)
		if err == nil && (abs.Scheme == "http" || abs.Scheme == "https") {
			p.Links = append(p.Links, abs.String())
		}
	}
	return p
}

// Semaphore-based concurrent fetch
func scrape(urls []string, concurrency int, timeout time.Duration) []Page {
	client := &http.Client{
		Timeout: timeout,
		CheckRedirect: func(req *http.Request, via []*http.Request) error {
			if len(via) >= 5 {
				return fmt.Errorf("too many redirects")
			}
			return nil
		},
	}

	sem := make(chan struct{}, concurrency)
	out := make(chan Page, len(urls))
	var wg sync.WaitGroup

	for _, u := range urls {
		wg.Add(1)
		go func(target string) {
			defer wg.Done()
			sem <- struct{}{}
			defer func() { <-sem }()
			out <- fetch(target, client)
		}(u)
	}

	go func() {
		wg.Wait()
		close(out)
	}()

	var pages []Page
	for p := range out {
		pages = append(pages, p)
	}
	return pages
}

func readURLFile(path string) ([]string, error) {
	f, err := os.Open(path)
	if err != nil {
		return nil, err
	}
	defer f.Close()
	var lines []string
	scanner := bufio.NewScanner(f)
	for scanner.Scan() {
		line := strings.TrimSpace(scanner.Text())
		if line != "" && !strings.HasPrefix(line, "#") {
			lines = append(lines, line)
		}
	}
	return lines, scanner.Err()
}

func main() {
	file := flag.String("f", "", "File containing URLs (one per line)")
	concurrency := flag.Int("c", 5, "Max concurrent requests")
	timeout := flag.Duration("t", 10*time.Second, "Timeout per request")
	showLinks := flag.Bool("links", false, "Print extracted links")
	flag.Parse()

	var urls []string
	if *file != "" {
		lines, err := readURLFile(*file)
		if err != nil {
			fmt.Fprintf(os.Stderr, "error reading file: %v\n", err)
			os.Exit(1)
		}
		urls = lines
	} else if flag.NArg() > 0 {
		urls = flag.Args()
	} else {
		urls = []string{
			"https://go.dev",
			"https://pkg.go.dev",
			"https://go.dev/blog",
		}
		fmt.Println("(No URLs provided — using defaults. Try: go run main.go https://example.com)\n")
	}

	fmt.Printf("Scraping %d URL(s) [concurrency=%d, timeout=%v]...\n\n",
		len(urls), *concurrency, *timeout)

	start := time.Now()
	pages := scrape(urls, *concurrency, *timeout)

	for _, p := range pages {
		if p.Err != nil {
			fmt.Printf("  ✗ %s\n    error: %v\n\n", p.URL, p.Err)
			continue
		}
		fmt.Printf("  ✓ [%d] %s\n", p.Status, p.URL)
		if p.Title != "" {
			fmt.Printf("    title: %s\n", p.Title)
		}
		fmt.Printf("    links: %d\n", len(p.Links))
		if *showLinks {
			for _, l := range p.Links {
				fmt.Printf("      → %s\n", l)
			}
		}
		fmt.Println()
	}

	fmt.Printf("Completed in %v\n", time.Since(start).Round(time.Millisecond))
}
