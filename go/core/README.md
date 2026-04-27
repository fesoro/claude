# Go Core — 36 Mövzu (01-36)

Go dilinin özü: sintaksis, tip sistemi, standart kitabxana əsasları, interface, error handling, concurrency giriş, dil dərinliyi. Junior ⭐-dan Middle ⭐⭐-ya qədər.

**Öyrənmə yolu:** 01 → 36 sıra ilə. Hər fayl əvvəlkinin üzərində qurulur.

---

## Fazalar Xülasəsi

| # | Faza | Aralıq | Səviyyə | Qısa Mövzu |
|---|------|--------|---------|------------|
| 1 | Əsaslar | 01-15 | Junior ⭐ | Sintaksis, tip sistemi, funksiyalar, kolleksiyalar, pointer |
| 2 | Go İdiomları | 16-26 | Middle ⭐⭐ | Interface, error, JSON, test, logging, CLI |
| 3 | Concurrency Giriş | 27-32 | Middle ⭐⭐ | Goroutine, channel, context, generics, io |
| 4 | Dil Dərinliyi | 33-36 | Senior ⭐⭐⭐ | Embedding, slice/struct/pointer advanced |

---

## Faza 1: Əsaslar (01-15) — Junior ⭐

| # | Fayl | Mövzu |
|---|------|-------|
| [01](01-introduction.md) | Go dilinə giriş | Niyə Go, PHP ilə müqayisə, Hello World, go toolchain |
| [02](02-variables.md) | Dəyişkənlər | `var`, `:=`, sıfır dəyərlər, shadowing |
| [03](03-data-types.md) | Məlumat tipləri | int, float, bool, string, tip dönüşümü |
| [04](04-operators.md) | Operatorlar | Riyazi, müqayisə, məntiqi, bitwise |
| [05](05-conditionals.md) | Şərtlər | if/else, switch, tipik Go patterns |
| [06](06-loops.md) | Dövrələr | Yalnız `for`, range, break/continue |
| [07](07-functions.md) | Funksiyalar | Multiple return, variadic, first-class functions |
| [08](08-arrays-and-slices.md) | Array və Slice | Fərq, append, copy, capacity |
| [09](09-maps.md) | Map | Yaratma, oxuma, silmə, iteration, nil map |
| [10](10-structs.md) | Struct | Tərif, method-lar, PHP class müqayisəsi |
| [11](11-pointers.md) | Pointer | `&`, `*`, nil pointer, PHP reference fərqi |
| [12](12-strings-and-strconv.md) | String | Rune, UTF-8, strconv, strings paketi |
| [13](13-file-operations.md) | Fayl əməliyyatları | Oxuma, yazma, defer, os paketi |
| [14](14-packages-and-modules.md) | Paket və modul | go.mod, import, visibility qaydaları |
| [15](15-recursion.md) | Rekursiya | Klassik nümunələr, stack overflow, memoization |

---

## Faza 2: Go İdiomları (16-26) — Middle ⭐⭐

| # | Fayl | Mövzu |
|---|------|-------|
| [16](16-regexp.md) | Regular expressions | `regexp` paketi, compile, find, replace |
| [17](17-interfaces.md) | Interface | Implicit implementation, composition, duck typing |
| [18](18-error-handling.md) | Xəta idarəetməsi | `error`, `errors.Is`, `errors.As`, wrapping |
| [19](19-type-assertions.md) | Type assertion | Type switch, interface dönüşümü |
| [20](20-json-encoding.md) | JSON | Encoding/decoding, struct tags, custom marshaler |
| [21](21-enums.md) | Enum | `iota`, typed constants |
| [22](22-init-and-modules.md) | `init()` funksiyası | İcra sırası, package initialization |
| [23](23-time-and-scope.md) | Time & Scope | Time paketi, variable shadowing, closure scope |
| [24](24-testing.md) | Test yazma | `testing`, table-driven tests, `t.Run`, coverage |
| [25](25-logging.md) | Logging | `log`, `slog` (Go 1.21+), structured logging |
| [26](26-cli-app.md) | CLI tətbiq | `flag`, `cobra`, subcommand, argüman parsing |

---

## Faza 3: Concurrency Giriş (27-32) — Middle ⭐⭐

| # | Fayl | Mövzu |
|---|------|-------|
| [27](27-goroutines-and-channels.md) | Goroutine & Channel | `go`, `chan`, buffered, select |
| [28](28-context.md) | Context | Timeout, cancellation, deadline, propagation |
| [29](29-generics.md) | Generics | Type parameters, constraints (Go 1.18+) |
| [30](30-io-reader-writer.md) | io.Reader/Writer | Streaming, pipe, bufio |
| [31](31-go-embed.md) | go:embed | Statik faylları binary-ə daxil etmək |
| [32](32-go-workspace.md) | Go Workspace | Multi-module development, `go work` |

---

## Faza 4: Dil Dərinliyi (33-36) — Senior ⭐⭐⭐

| # | Fayl | Mövzu |
|---|------|-------|
| [33](33-embedding.md) | Struct Embedding | Anonymous field, promoted method, composition vs inheritance |
| [34](34-slice-advanced.md) | Slice Advanced | Append internals, copy, 3-index slice, memory sharing |
| [35](35-struct-advanced.md) | Struct Advanced | Tags, unexported fields, comparison, zero value |
| [36](36-pointers-advanced.md) | Pointers Advanced | Heap/stack, escape analysis, method receiver seçimi |

---

## PHP/Laravel → Go Core Müqayisəsi

| PHP/Laravel | Go Core |
|-------------|---------|
| `$var = "hello"` | `var s string = "hello"` / `s := "hello"` |
| `array`, `Collection` | slice `[]T`, map `map[K]V` |
| `class` | `struct` + method-lar |
| `interface` (explicit) | `interface` (implicit — duck typing) |
| `try/catch` | `error` return dəyəri |
| `null` | nil pointer, zero value |
| `extends` (inheritance) | struct embedding (composition) |
| `function` as variable | first-class functions, closure |
| PHP thread yoxdur | goroutine — yüngül concurrent proses |
| `phpunit`, `pest` | `testing` paketi, table-driven tests |
