package main

import (
	"bufio"
	"flag"
	"fmt"
	"os"
	"path/filepath"
	"sort"
	"strings"
	"sync"
	"unicode"
)

type Stats struct {
	File  string
	Lines int
	Words int
	Chars int
	Err   error
}

func countFile(path string) Stats {
	s := Stats{File: path}
	f, err := os.Open(path)
	if err != nil {
		s.Err = err
		return s
	}
	defer f.Close()

	scanner := bufio.NewScanner(f)
	for scanner.Scan() {
		line := scanner.Text()
		s.Lines++
		s.Chars += len([]rune(line)) + 1 // +1 for newline
		for _, word := range strings.FieldsFunc(line, func(r rune) bool {
			return unicode.IsSpace(r) || unicode.IsPunct(r)
		}) {
			if word != "" {
				s.Words++
			}
		}
	}
	s.Err = scanner.Err()
	return s
}

// Worker pool: N goroutines pull from fileCh, push results to resultCh
func processFiles(paths []string, workers int) []Stats {
	fileCh := make(chan string)
	resultCh := make(chan Stats, len(paths))
	var wg sync.WaitGroup

	for i := 0; i < workers; i++ {
		wg.Add(1)
		go func() {
			defer wg.Done()
			for path := range fileCh {
				resultCh <- countFile(path)
			}
		}()
	}

	go func() {
		for _, p := range paths {
			fileCh <- p
		}
		close(fileCh)
	}()

	go func() {
		wg.Wait()
		close(resultCh)
	}()

	var results []Stats
	for r := range resultCh {
		results = append(results, r)
	}
	return results
}

func createSamples(dir string) []string {
	samples := map[string]string{
		"alice.txt": strings.Repeat("Alice was beginning to get very tired of sitting by her sister on the bank. ", 100),
		"go.txt":    strings.Repeat("Go is an open source programming language that makes it easy to build simple reliable and efficient software. ", 80),
		"lorem.txt": strings.Repeat("Lorem ipsum dolor sit amet consectetur adipiscing elit sed do eiusmod tempor incididunt. ", 120),
	}
	os.MkdirAll(dir, 0755)
	var paths []string
	for name, content := range samples {
		path := filepath.Join(dir, name)
		os.WriteFile(path, []byte(content), 0644)
		paths = append(paths, path)
	}
	return paths
}

func main() {
	workers := flag.Int("w", 4, "Number of worker goroutines")
	flag.Parse()

	paths := flag.Args()
	if len(paths) == 0 {
		fmt.Println("No files given — creating sample files in ./sample/\n")
		paths = createSamples("sample")
	}

	// Expand glob patterns (e.g. *.txt)
	var expanded []string
	for _, p := range paths {
		matches, err := filepath.Glob(p)
		if err != nil || len(matches) == 0 {
			expanded = append(expanded, p)
		} else {
			expanded = append(expanded, matches...)
		}
	}
	paths = expanded

	fmt.Printf("Processing %d file(s) with %d workers...\n\n", len(paths), *workers)
	results := processFiles(paths, *workers)

	sort.Slice(results, func(i, j int) bool {
		return results[i].File < results[j].File
	})

	var totalLines, totalWords, totalChars int
	for _, r := range results {
		if r.Err != nil {
			fmt.Printf("  ✗ %-35s  error: %v\n", r.File, r.Err)
			continue
		}
		fmt.Printf("  ✓ %-35s  lines:%5d  words:%7d  chars:%8d\n",
			r.File, r.Lines, r.Words, r.Chars)
		totalLines += r.Lines
		totalWords += r.Words
		totalChars += r.Chars
	}

	fmt.Printf("\n  %-35s  lines:%5d  words:%7d  chars:%8d\n",
		"TOTAL", totalLines, totalWords, totalChars)
}
