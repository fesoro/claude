# Concurrent Word Counter (⭐⭐ Middle)

Çoxlu faylı paralel işləyən söz sayğacı. **Worker pool pattern**-i nümayiş etdirir — fix sayda goroutine job queue-dan iş götürür.

## Öyrənilən Konseptlər

- **Worker pool**: N goroutine, 1 job channel
- `sync.WaitGroup` ilə goroutine-ların bitməsini gözləmə
- Closed channel ilə fan-out signalling (`close(fileCh)`)
- Buffered result channel
- `bufio.Scanner` ilə effektiv fayl oxuma
- `unicode` paketi ilə düzgün söz ayrımı

## İşə Salma

```bash
# Avtomatik sample fayllar yaradır
go run main.go

# Öz fayllarınla
go run main.go file1.txt file2.txt

# Worker sayını dəyiş
go run main.go -w 8 *.txt

# Glob pattern
go run main.go -w 4 docs/*.md
```

## Nümunə Output

```
Processing 3 file(s) with 4 workers...

  ✓ sample/alice.txt             lines:  100  words:   1400  chars:   8400
  ✓ sample/go.txt                lines:   80  words:    960  chars:   6720
  ✓ sample/lorem.txt             lines:  120  words:   1560  chars:   9840

  TOTAL                          lines:  300  words:   3920  chars:  24960
```

## Worker Pool Diaqramı

```
main()
 ├── goroutine: fileCh ← [file1, file2, file3] → close(fileCh)
 ├── worker 1: fileCh → countFile → resultCh
 ├── worker 2: fileCh → countFile → resultCh
 ├── worker 3: fileCh → countFile → resultCh
 └── goroutine: wg.Wait() → close(resultCh)
```

## İrəli Getmək Üçün

- Top-N frequent words (`map[string]int` + sort)
- Recursive directory walk
- Progress bar (ANSI escape codes)
- CSV/JSON export
