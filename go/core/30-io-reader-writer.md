# io.Reader və io.Writer (Middle)

## İcmal

`io.Reader` və `io.Writer` Go-nun ən fundamental interfeyslərindəndir. Fayllar, network bağlantıları, HTTP body, string buffer-lar, gzip stream-lər — hamısı bu iki interfeysi implement edir. Bu sayədə data mənbəyi nə olursa olsun eyni kod işləyir.

## Niyə Vacibdir

Real layihələrdə `http.Request.Body` bir `io.Reader`-dir — bu onu fayldan, network-dən, test buffer-ından eyni şəkildə oxumağa imkan verir. `json.NewDecoder(r io.Reader)` istənilən mənbədən JSON oxuyur. Bu interfeyslər olmadan hər mənbə üçün ayrı kod yazmaq lazım olardı. Composition prinsipi — kiçik interfeys + birləşdirmə (TeeReader, MultiReader) güclü abstraksiyanı mümkün edir.

## Əsas Anlayışlar

- **io.Reader** — `Read(p []byte) (n int, err error)` — p buffer-ına oxuyur, bitdikdə `io.EOF` qaytarır
- **io.Writer** — `Write(p []byte) (n int, err error)` — p-nin məlumatını yazır
- **io.ReadWriter** — hər iki interfeysi birləşdirir
- **io.Closer** — `Close() error` — resursları buraxa bilmək üçün
- **io.ReadCloser** — Reader + Closer (http.Response.Body tipik nümunə)
- **io.Copy** — Reader-dən Writer-ə köçürür (stream kimi)
- **bufio** — buffered Reader/Writer (performans üçün)
- **io.EOF** — oxuma bitdiyini bildirən sentinel error

## Praktik Baxış

**Composition nümunəsi:**

```
strings.NewReader("data")  →  gzip.NewReader  →  json.NewDecoder
     (io.Reader)               (io.Reader)          (istifadə edir)
```

**Trade-off-lar:**
- `io.ReadAll` — bütün məlumatı yaddaşa yükləyir; böyük fayllar üçün istifadə etməyin
- Chunk-by-chunk oxuma (4KB buffer) — memory-efficient, amma kod mürəkkəbdir
- `bufio.Reader` — kiçik oxuma əməliyyatlarını buffer edir (syscall azaltmaq üçün)

**Common mistakes:**
- `http.Response.Body`-ni bağlamамaq — connection leak
- `defer resp.Body.Close()` — həmişə read-dən sonra
- `Read()` tam doldurmayabilər — `n` dəyərini yoxlayın, `io.ReadFull` istifadə edin

## Nümunələr

### Nümunə 1: strings.Reader və bytes.Buffer

```go
package main

import (
    "bytes"
    "fmt"
    "io"
    "strings"
)

func main() {
    // strings.Reader — string-dən io.Reader
    reader := strings.NewReader("Salam Dünya!")

    buf := make([]byte, 5) // 5 byte-lıq buffer
    for {
        n, err := reader.Read(buf)
        if err == io.EOF {
            break
        }
        fmt.Print(string(buf[:n])) // n — həqiqətən oxunan byte sayı
    }
    fmt.Println()

    // bytes.Buffer — yaddaşda Reader + Writer
    var buffer bytes.Buffer
    buffer.WriteString("Birinci ")
    buffer.WriteString("İkinci ")
    buffer.WriteString("Üçüncü")
    fmt.Println("Buffer:", buffer.String())

    // Buffer-dən oxumaq
    oxundu := make([]byte, 7)
    n, _ := buffer.Read(oxundu)
    fmt.Println("Oxundu:", string(oxundu[:n])) // "Birinci"
    fmt.Println("Qalan:", buffer.String())       // "İkinci Üçüncü"
}
```

### Nümunə 2: io.Copy — stream kopyalama

```go
package main

import (
    "fmt"
    "io"
    "os"
    "strings"
)

func main() {
    // io.Copy — Reader-dən Writer-ə axın kimi köçürür
    // Bütün məlumatı yaddaşa yüklEmir — ideal böyük fayllar üçün
    mənbə := strings.NewReader("Bu mətn kopyalanır\n")
    yazılan, err := io.Copy(os.Stdout, mənbə)
    fmt.Printf("Yazılan byte sayı: %d, xəta: %v\n", yazılan, err)

    // Faydan fayla köçürmək (real istifadə)
    // src, _ := os.Open("input.txt")
    // defer src.Close()
    // dst, _ := os.Create("output.txt")
    // defer dst.Close()
    // io.Copy(dst, src)

    // io.ReadAll — bütün məlumatı oxu (kiçik fayllar üçün)
    reader := strings.NewReader("Tam mətn burada")
    data, _ := io.ReadAll(reader)
    fmt.Println("ReadAll:", string(data))
}
```

### Nümunə 3: io.MultiReader — bir neçə Reader-i birləşdir

```go
package main

import (
    "fmt"
    "io"
    "strings"
)

func main() {
    // MultiReader — bir neçə Reader-i ardıcıl birləşdirir
    r1 := strings.NewReader("Birinci ")
    r2 := strings.NewReader("İkinci ")
    r3 := strings.NewReader("Üçüncü!")

    combined := io.MultiReader(r1, r2, r3)
    data, _ := io.ReadAll(combined)
    fmt.Println("Birləşdirilmiş:", string(data))

    // Praktik istifadə: HTTP request body-ni qeyd etmək
    // body := io.MultiReader(strings.NewReader("[BODY] "), r.Body)
    // r.Body = io.NopCloser(body) // Body-ni yenidən qur
}
```

### Nümunə 4: io.TeeReader — oxuyarkən kopyalamaq

```go
package main

import (
    "bytes"
    "fmt"
    "io"
    "strings"
)

func main() {
    // TeeReader — oxunan hər byte-ı eyni zamanda Writer-ə yazır
    var log bytes.Buffer
    orijinal := strings.NewReader("Mühüm məlumat")

    tee := io.TeeReader(orijinal, &log) // oxunan hər şey &log-a da yazılır

    nəticə, _ := io.ReadAll(tee)
    fmt.Println("Nəticə:", string(nəticə)) // Mühüm məlumat
    fmt.Println("Log:", log.String())       // Mühüm məlumat (eyni)

    // Real istifadə: HTTP middleware-də request body-ni həm oxuyub,
    // həm də log-a yazırıq
    // body, _ := io.ReadAll(io.TeeReader(r.Body, &logBuffer))
    // r.Body = io.NopCloser(bytes.NewReader(body)) // yenidən qur
}
```

### Nümunə 5: io.Pipe — goroutine arası stream

```go
package main

import (
    "compress/gzip"
    "fmt"
    "io"
)

func main() {
    // io.Pipe — bir goroutine yazır, digəri oxuyur
    pr, pw := io.Pipe()

    go func() {
        defer pw.Close()
        pw.Write([]byte("Pipe vasitəsilə göndərildi"))
    }()

    data, _ := io.ReadAll(pr)
    fmt.Println("Pipe:", string(data))

    // Praktik: gzip sıxışdırma + Pipe
    gzPR, gzPW := io.Pipe()
    go func() {
        gzWriter := gzip.NewWriter(gzPW)
        gzWriter.Write([]byte("Sıxılacaq mətn"))
        gzWriter.Close()
        gzPW.Close()
    }()

    gzReader, _ := gzip.NewReader(gzPR)
    decompressed, _ := io.ReadAll(gzReader)
    fmt.Println("Açılmış:", string(decompressed))
}
```

### Nümunə 6: Xüsusi Reader yaratmaq

```go
package main

import (
    "fmt"
    "io"
)

// Sayaç Reader — ardıcıl sətirləri oxuyan reader
type SayaçReader struct {
    limit   int
    current int
}

func (s *SayaçReader) Read(p []byte) (int, error) {
    if s.current >= s.limit {
        return 0, io.EOF
    }
    s.current++
    mətn := fmt.Sprintf("Sətir %d\n", s.current)
    n := copy(p, mətn)
    return n, nil
}

// Böyük hərfli Writer — yazılanları böyük hərfə çevirir
type BöyükHərfWriter struct {
    hədəf io.Writer
}

func (b *BöyükHərfWriter) Write(p []byte) (int, error) {
    böyük := make([]byte, len(p))
    for i, c := range p {
        if c >= 'a' && c <= 'z' {
            böyük[i] = c - 32
        } else {
            böyük[i] = c
        }
    }
    return b.hədəf.Write(böyük)
}

func main() {
    // Xüsusi Reader
    sayaç := &SayaçReader{limit: 3}
    data, _ := io.ReadAll(sayaç)
    fmt.Print("Sayaç:\n", string(data))

    // Xüsusi Writer
    import "os"
    böyükYaz := &BöyükHərfWriter{hədəf: os.Stdout}
    fmt.Fprint(böyükYaz, "bu kiçik hərflərlə yazıldı\n") // BÖYÜK HƏRFLƏRLƏ çıxır
}
```

### Nümunə 7: bufio — buffered Reader/Writer

```go
package main

import (
    "bufio"
    "fmt"
    "strings"
)

func main() {
    // bufio.Reader — sətir-sətir oxumaq üçün ideal
    mətn := "Birinci sətir\nİkinci sətir\nÜçüncü sətir"
    reader := bufio.NewReader(strings.NewReader(mətn))

    for {
        sətir, err := reader.ReadString('\n') // '\n' tapana qədər oxu
        if len(sətir) > 0 {
            fmt.Print("Sətir:", sətir)
        }
        if err != nil { // io.EOF daxil olmaqla
            break
        }
    }

    // bufio.Scanner — daha rahat sətir oxuma
    scanner := bufio.NewScanner(strings.NewReader(mətn))
    for scanner.Scan() {
        fmt.Println("Scan:", scanner.Text())
    }

    // bufio.Writer — kiçik yazmaları buffer edir
    var sb strings.Builder
    writer := bufio.NewWriter(&sb)
    for i := 0; i < 5; i++ {
        fmt.Fprintf(writer, "Dəyər: %d\n", i)
    }
    writer.Flush() // buffer-i boşalt — unutmayın!
    fmt.Print("Nəticə:\n", sb.String())
}
```

### Nümunə 8: io.LimitReader — məhdud oxuma

```go
package main

import (
    "fmt"
    "io"
    "strings"
)

func main() {
    // io.LimitReader — maksimum N byte oxu
    // HTTP request body ölçüsünü məhdudlaşdırmaq üçün istifadə olunur
    uzunMəlumat := strings.NewReader("Bu çox uzun bir mətn ola bilərdi...")
    məhdud := io.LimitReader(uzunMəlumat, 10) // yalnız 10 byte
    data, _ := io.ReadAll(məhdud)
    fmt.Println("Məhdud:", string(data)) // "Bu çox uz"

    // HTTP request body məhdudlaşdırması:
    // r.Body = http.MaxBytesReader(w, r.Body, 1<<20) // 1MB
    // json.NewDecoder(r.Body).Decode(&payload)
}
```

## Praktik Tapşırıqlar

**Tapşırıq 1: Fayl kopyalayıcı**

Böyük faylları chunk-by-chunk kopyalayan proqram yazın. `io.Copy` istifadə edin. Progress (neçə % kopyalandı) göstərən xüsusi Writer wrapper yazın.

```go
// type ProgressWriter struct { total int64; written int64; writer io.Writer }
// func (pw *ProgressWriter) Write(p []byte) (int, error) { ... }
// io.Copy(progressWriter, srcFile)
```

**Tapşırıq 2: Multi-format JSON oxuyucu**

Eyni JSON məlumatı üç mənbədən oxuyun: fayl, string, HTTP response body. Kod ixtilafı olmamalıdır — `json.NewDecoder(reader)` istifadə edin.

```go
// func decode(r io.Reader, v any) error {
//     return json.NewDecoder(r).Decode(v)
// }
// decode(file, &data)
// decode(strings.NewReader(jsonStr), &data)
// decode(resp.Body, &data)
```

**Tapşırıq 3: Request body logger middleware**

HTTP middleware yazın: request body-ni oxusun, log-a yazsın, sonra yenidən body-ni bərpa etsin ki handler da oxuya bilsin.

```go
// var body bytes.Buffer
// tee := io.TeeReader(r.Body, &body)
// io.ReadAll(tee) // həm oxu həm body-yə kopyala
// r.Body = io.NopCloser(&body) // body-ni yenidən qur
// slog.Info("Request body", "body", body.String())
```

## PHP ilə Müqayisə

```
PHP                        →  Go
fread($handle, 8192)       →  reader.Read(buf)
fwrite($handle, $data)     →  writer.Write(data)
stream_get_contents($f)    →  io.ReadAll(reader)
stream_copy_to_stream()    →  io.Copy(dst, src)
$request->getBody()        →  r.Body (io.ReadCloser)
GzipStream                 →  gzip.NewReader(reader)
```

PHP stream funksiyaları prosedural-dır; Go-da `io.Reader` / `io.Writer` interfeyslərini implement edən istənilən struct eyni `io.Copy`, `json.NewDecoder` kimi funksiyalarla işləyir — bu daha güclü kompozisiyaya imkan verir.

## Əlaqəli Mövzular

- `13-file-operations` — os.File (io.Reader + io.Writer)
- `33-http-server` — http.Request.Body (io.ReadCloser)
- `34-http-client` — response body oxuma
- `49-files-advanced` — bufio, large file handling
- `50-xml-and-url` — xml.NewDecoder(reader)
- `27-goroutines-and-channels` — io.Pipe ilə goroutine-lar arası stream
