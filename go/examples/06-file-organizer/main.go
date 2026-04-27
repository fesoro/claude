package main

import (
	"flag"
	"fmt"
	"os"
	"path/filepath"
	"strings"
)

var categories = map[string][]string{
	"images":    {".jpg", ".jpeg", ".png", ".gif", ".webp", ".svg", ".ico", ".bmp", ".tiff"},
	"videos":    {".mp4", ".mkv", ".avi", ".mov", ".wmv", ".flv", ".webm", ".m4v"},
	"audio":     {".mp3", ".wav", ".flac", ".aac", ".ogg", ".m4a", ".opus"},
	"documents": {".pdf", ".doc", ".docx", ".xls", ".xlsx", ".ppt", ".pptx", ".odt", ".ods"},
	"text":      {".txt", ".md", ".csv", ".log", ".json", ".xml", ".yaml", ".yml", ".toml", ".ini"},
	"code":      {".go", ".php", ".js", ".ts", ".py", ".java", ".c", ".cpp", ".rb", ".rs", ".sh", ".sql"},
	"archives":  {".zip", ".tar", ".gz", ".rar", ".7z", ".bz2", ".xz"},
	"fonts":     {".ttf", ".otf", ".woff", ".woff2"},
}

func classify(ext string) string {
	ext = strings.ToLower(ext)
	for cat, exts := range categories {
		for _, e := range exts {
			if e == ext {
				return cat
			}
		}
	}
	return "others"
}

func organize(dir string, dryRun bool) (int, error) {
	entries, err := os.ReadDir(dir)
	if err != nil {
		return 0, err
	}

	moved := 0
	for _, entry := range entries {
		if entry.IsDir() {
			continue
		}
		name := entry.Name()
		ext := filepath.Ext(name)
		if ext == "" {
			continue
		}

		category := classify(ext)
		src := filepath.Join(dir, name)
		destDir := filepath.Join(dir, category)
		dest := filepath.Join(destDir, name)

		if dryRun {
			fmt.Printf("  [dry] %-40s  →  %s/\n", name, category)
			continue
		}

		if err := os.MkdirAll(destDir, 0755); err != nil {
			return moved, err
		}
		if err := os.Rename(src, dest); err != nil {
			fmt.Printf("  warn: could not move %s: %v\n", name, err)
			continue
		}
		fmt.Printf("  moved: %-40s  →  %s/\n", name, category)
		moved++
	}
	return moved, nil
}

func main() {
	dir := flag.String("dir", ".", "Directory to organize")
	dry := flag.Bool("dry", false, "Preview changes without moving files")
	flag.Parse()

	info, err := os.Stat(*dir)
	if err != nil || !info.IsDir() {
		fmt.Fprintf(os.Stderr, "error: %q is not a valid directory\n", *dir)
		os.Exit(1)
	}

	abs, _ := filepath.Abs(*dir)
	fmt.Printf("Organizing: %s\n", abs)
	if *dry {
		fmt.Println("(dry-run — no files will be moved)\n")
	} else {
		fmt.Println()
	}

	moved, err := organize(*dir, *dry)
	if err != nil {
		fmt.Fprintf(os.Stderr, "error: %v\n", err)
		os.Exit(1)
	}

	if !*dry {
		fmt.Printf("\nDone. Moved %d file(s).\n", moved)
	}
}
