# JSON Encoding (Middle)

## İcmal

Go-da JSON işləmək üçün standart `encoding/json` paketi istifadə olunur. Struct tag-ləri ilə JSON field adları, omitempty davranışı və gizli sahələr idarə edilir. Paket reflection istifadə etdiyindən tipin məlum olması performans baxımından önəmlidir. Go-da marshal/unmarshal əməliyyatları strongly-typed struct-larla işləyir və xəta idarəetməsi açıq şəkildə aparılır.

## Niyə Vacibdir

Müasir backend servislərin demək olar ki, hamısı JSON API-lərdir. HTTP request body-ni parse etmək, response qayıtmaq, daxili servislərlə kommunikasiya, konfiqürasiya faylları oxumaq — hamısı JSON ilə bağlıdır. Go-da JSON işləmənin düzgün həyata keçirilməsi (struct tag-lər, custom marshal, streaming) production-da həm performansı, həm də məlumat bütövlüyünü birbaşa təsir edir.

## Əsas Anlayışlar

- **Struct tag-lər** — `` `json:"field_name"` `` — JSON field adını müəyyən edir
- **`omitempty`** — `` `json:"name,omitempty"` `` — sıfır dəyərli sahə JSON-a daxil edilmir
- **`json:"-"`** — sahə həmişə JSON-dan gizlədilir (şifrə, token və s.)
- **`json.Marshal`** — struct → `[]byte` (JSON)
- **`json.MarshalIndent`** — struct → formatlanmış (pretty-print) JSON
- **`json.Unmarshal`** — `[]byte` (JSON) → struct
- **`json.NewEncoder`** — `io.Writer`-ə stream kimi yazar; HTTP response üçün ideal
- **`json.NewDecoder`** — `io.Reader`-dən stream kimi oxur; HTTP request body üçün ideal
- **Custom `MarshalJSON`/`UnmarshalJSON`** — enum, şifrələnmiş sahə, xüsusi format üçün
- **`json.RawMessage`** — JSON-un bir hissəsini parse etmədən `[]byte` kimi saxlayır
- **`json.Number`** — böyük integer ID-lər üçün `float64` dəqiqlik itkisinin qarşısını alır
- **JSON rəqəmləri** — `interface{}` ilə decode olunanda həmişə `float64` gəlir (klassik tuzaq)

## Praktik Baxış

**Real istifadə ssenariləri:**
- HTTP handler-də `r.Body`-dən request struct decode etmək
- HTTP response olaraq struct marshal edib yazmaq
- External API cavablarını struct-a bind etmək
- Webhook payload-larını `RawMessage` ilə type-safe parse etmək

**Trade-off-lar:**
- `json.Marshal` + `json.Unmarshal` vs `json.Encoder/Decoder`: streaming varsa (HTTP body, böyük fayllar) encoder/decoder daha effektivdir; kiçik in-memory data üçün fərq yoxdur
- `map[string]interface{}` — elastiklik verir, lakin type safety yoxdur; struct daha üstündür
- Custom marshal mürəkkəblik artırır; yalnız real ehtiyac olduqda yazın

**Ümumi səhvlər:**
- `omitempty` boolean sahədə `false` dəyərini silir — çox vaxt istənilən davranış deyil
- `omitempty` int sahədə `0`-ı silir — ID sahəsinə qoymayın
- JSON rəqəmini `interface{}` ilə decode edib `int` assert etmək — həmişə `float64` gəlir
- `json.Unmarshal` xətasını yoxlamamaq — struct boş qala bilər
- Pointer field-lər üçün unmarshal zamanı nil pointer dereference

## Nümunələr

### Nümunə 1: Struct tag-lər — marshal/unmarshal

```go
package main

import (
    "encoding/json"
    "fmt"
    "log"
)

type Istifadeci struct {
    ID     int    `json:"id"`
    Ad     string `json:"ad"`
    Email  string `json:"email,omitempty"` // boş olduqda JSON-a daxil edilmir
    Yas    int    `json:"yas,omitempty"`
    Shifre string `json:"-"`               // heç vaxt JSON-a daxil edilmir
    Aktiv  bool   `json:"aktiv"`
}

func main() {
    user := Istifadeci{
        ID:     1,
        Ad:     "Orkhan",
        Email:  "o@mail.az",
        Yas:    28,
        Shifre: "gizli123", // JSON-da görünməyəcək
        Aktiv:  true,
    }

    // Marshal — struct → JSON
    data, err := json.Marshal(user)
    if err != nil {
        log.Fatal(err)
    }
    fmt.Println(string(data))
    // {"id":1,"ad":"Orkhan","email":"o@mail.az","yas":28,"aktiv":true}
    // Shifre yoxdur!

    // MarshalIndent — pretty-print
    gozel, _ := json.MarshalIndent(user, "", "  ")
    fmt.Println(string(gozel))

    // omitempty nümunəsi — boş sahələr çıxır
    bosh := Istifadeci{ID: 2, Ad: "Aysel", Aktiv: false}
    boshData, _ := json.MarshalIndent(bosh, "", "  ")
    fmt.Println(string(boshData))
    // email və yas yoxdur (omitempty); aktiv false olsa da göründür (bool-da omitempty false-u silir!)

    // Unmarshal — JSON → struct
    jsonStr := `{"id":3,"ad":"Kenan","email":"k@test.com","yas":30,"aktiv":true}`
    var yeni Istifadeci
    if err := json.Unmarshal([]byte(jsonStr), &yeni); err != nil {
        log.Fatal(err)
    }
    fmt.Printf("Ad: %s, Yaş: %d\n", yeni.Ad, yeni.Yas)
}
```

### Nümunə 2: Nested struct və array

```go
package main

import (
    "encoding/json"
    "fmt"
)

type Unvan struct {
    Sheher  string `json:"sheher"`
    Kuce    string `json:"kuce"`
    PostKod string `json:"post_kod,omitempty"`
}

type Ishci struct {
    Ad         string   `json:"ad"`
    Vezife     string   `json:"vezife"`
    Unvan      Unvan    `json:"unvan"`
    Bacariqlar []string `json:"bacariqlar"`
}

func main() {
    // Nested struct unmarshal
    jsonStr := `{
        "ad": "Nigar",
        "vezife": "Backend Developer",
        "unvan": {
            "sheher": "Bakı",
            "kuce": "Nizami 15"
        },
        "bacariqlar": ["Go", "PostgreSQL", "Docker"]
    }`

    var ishci Ishci
    json.Unmarshal([]byte(jsonStr), &ishci)
    fmt.Printf("İşçi: %s, Şəhər: %s\n", ishci.Ad, ishci.Unvan.Sheher)
    fmt.Println("Bacarıqlar:", ishci.Bacariqlar)

    // Struct slice marshal
    ishciler := []Ishci{
        {
            Ad:         "Rauf",
            Vezife:     "DevOps",
            Unvan:      Unvan{Sheher: "Bakı"},
            Bacariqlar: []string{"Docker", "K8s"},
        },
        {
            Ad:         "Sevinc",
            Vezife:     "Frontend",
            Unvan:      Unvan{Sheher: "Gəncə"},
            Bacariqlar: []string{"React", "TypeScript"},
        },
    }

    data, _ := json.MarshalIndent(ishciler, "", "  ")
    fmt.Println(string(data))
}
```

### Nümunə 3: json.NewEncoder / json.NewDecoder — streaming

```go
package main

import (
    "encoding/json"
    "fmt"
    "strings"
)

type Sifaris struct {
    ID      int     `json:"id"`
    Mehsul  string  `json:"mehsul"`
    Qiymet  float64 `json:"qiymet"`
    Miqdar  int     `json:"miqdar"`
}

func main() {
    sifaris := Sifaris{ID: 101, Mehsul: "Laptop", Qiymet: 1200.00, Miqdar: 2}

    // Encoder — io.Writer-ə yazar
    // Real layihədə: json.NewEncoder(w).Encode(response)
    var buf strings.Builder
    enc := json.NewEncoder(&buf)
    enc.SetIndent("", "  ")
    enc.Encode(sifaris) // avtomatik \n əlavə edir
    fmt.Println("Encoder çıxışı:")
    fmt.Println(buf.String())

    // Decoder — io.Reader-dən oxuyur
    // Real layihədə: json.NewDecoder(r.Body).Decode(&req)
    jsonInput := strings.NewReader(`{"id":102,"mehsul":"Phone","qiymet":800.00,"miqdar":1}`)
    dec := json.NewDecoder(jsonInput)

    var oxunan Sifaris
    if err := dec.Decode(&oxunan); err != nil {
        fmt.Println("Xəta:", err)
        return
    }
    fmt.Printf("Oxunan: %s, Qiymət: %.2f\n", oxunan.Mehsul, oxunan.Qiymet)

    // Çox JSON obyek ardıcıl oxumaq
    multiJSON := strings.NewReader(`
        {"id":1,"mehsul":"A","qiymet":10.0,"miqdar":1}
        {"id":2,"mehsul":"B","qiymet":20.0,"miqdar":2}
        {"id":3,"mehsul":"C","qiymet":30.0,"miqdar":3}
    `)
    dec2 := json.NewDecoder(multiJSON)
    for dec2.More() {
        var s Sifaris
        dec2.Decode(&s)
        fmt.Printf("  ID: %d, Məhsul: %s\n", s.ID, s.Mehsul)
    }
}
```

### Nümunə 4: Custom MarshalJSON / UnmarshalJSON

```go
package main

import (
    "encoding/json"
    "fmt"
)

type Status int

const (
    StatusAktiv   Status = 1
    StatusPassiv  Status = 2
    StatusSilinib Status = 3
)

var statusAdlari = map[Status]string{
    StatusAktiv:   "aktiv",
    StatusPassiv:  "passiv",
    StatusSilinib: "silinib",
}

var statusDeyerleri = map[string]Status{
    "aktiv":   StatusAktiv,
    "passiv":  StatusPassiv,
    "silinib": StatusSilinib,
}

// JSON-da rəqəm əvəzinə mətn yazılır
func (s Status) MarshalJSON() ([]byte, error) {
    ad, ok := statusAdlari[s]
    if !ok {
        return nil, fmt.Errorf("naməlum status: %d", s)
    }
    return json.Marshal(ad) // "aktiv" kimi
}

// JSON-da mətn → rəqəm
func (s *Status) UnmarshalJSON(data []byte) error {
    var ad string
    if err := json.Unmarshal(data, &ad); err != nil {
        return err
    }
    val, ok := statusDeyerleri[ad]
    if !ok {
        return fmt.Errorf("naməlum status: %s", ad)
    }
    *s = val
    return nil
}

type Hesab struct {
    Ad     string `json:"ad"`
    Status Status `json:"status"`
}

func main() {
    hesab := Hesab{Ad: "Orkhan", Status: StatusAktiv}
    data, _ := json.MarshalIndent(hesab, "", "  ")
    fmt.Println(string(data))
    // {"ad":"Orkhan","status":"aktiv"} — rəqəm deyil, mətn!

    var yeni Hesab
    json.Unmarshal([]byte(`{"ad":"Vəfa","status":"passiv"}`), &yeni)
    fmt.Printf("Hesab: %s, Status: %d\n", yeni.Ad, int(yeni.Status))
    // Hesab: Vəfa, Status: 2
}
```

### Nümunə 5: json.RawMessage — variant payload

```go
package main

import (
    "encoding/json"
    "fmt"
)

type Bildirish struct {
    Tip     string          `json:"tip"`
    Melumat json.RawMessage `json:"melumat"` // tip bilinənədək parse edilmir
}

type EmailMelumat struct {
    Kimden string `json:"kimden"`
    Kime   string `json:"kime"`
    Movzu  string `json:"movzu"`
}

type SMSMelumat struct {
    Nomre string `json:"nomre"`
    Metn  string `json:"metn"`
}

func bildirisiIslaParse(bildJSON string) {
    var bild Bildirish
    json.Unmarshal([]byte(bildJSON), &bild)

    switch bild.Tip {
    case "email":
        var em EmailMelumat
        json.Unmarshal(bild.Melumat, &em)
        fmt.Printf("Email: %s → %s | Mövzu: %s\n", em.Kimden, em.Kime, em.Movzu)
    case "sms":
        var sm SMSMelumat
        json.Unmarshal(bild.Melumat, &sm)
        fmt.Printf("SMS: %s | Mətn: %s\n", sm.Nomre, sm.Metn)
    }
}

func main() {
    emailBild := `{"tip":"email","melumat":{"kimden":"a@b.com","kime":"c@d.com","movzu":"Salam"}}`
    smsBild := `{"tip":"sms","melumat":{"nomre":"+994501234567","metn":"Kod: 1234"}}`

    bildirisiIslaParse(emailBild)
    bildirisiIslaParse(smsBild)
}
```

## Praktik Tapşırıqlar

1. **API response wrapper:** `ApiResponse[T any]` generic struct yarat (`Success bool`, `Data T`, `Error string`, `Timestamp int64`). İstənilən struct-ı `T` olaraq sarıb JSON qaytarsın.

2. **Sensitive field masking:** `User` struct-ında `Password` sahəsini `MarshalJSON` ilə gizlət. `json:"-"` əvəzinə custom marshal yaz — hash-lənmiş formasını qaytar.

3. **Webhook dispatcher:** `WebhookEvent` struct-ı `RawMessage` ilə yarat. `OrderEvent`, `PaymentEvent`, `UserEvent` tipləri üçün dispatcher — `Tip` sahəsinə görə müvafiq struct-a parse etsin.

4. **Config loader:** JSON konfiqürasiya faylını (`config.json`) `json.Decoder` ilə oxuyan funksiya yaz. Sahə yoxdursa default dəyər istifadə etsin. Mühit dəyişkənlərini struct tag custom annotation ilə override etsin.

5. **JSON stream processor:** Böyük JSON array-ı (məsələn 100k sətir log) yaddaşa tamamilə yükləmədən `json.Decoder` + `dec.Token()` ilə sətir-sətir oxu. Yalnız `level == "ERROR"` olan sətirləri çap et.

## PHP ilə Müqayisə

| PHP | Go |
|-----|-----|
| `json_encode($arr)` — istənilən tip | `json.Marshal(v)` — struct tag-lərlə idarə |
| `json_decode($str, true)` — associative array | `json.Unmarshal(data, &s)` — typed struct |
| `#[JsonSerialize]` attribute-u (PHP 8) | `MarshalJSON()` metodu |
| `$obj->field` null olsa problem yoxdur | `omitempty` açıq yazılmalıdır |
| Avtomatik `camelCase` → `snake_case` yoxdur | Struct tag ilə açıq yazılır |
| `json_decode` rəqəmləri float və ya int avtomatik seçir | `interface{}` ilə decode — həmişə `float64` |

## Əlaqəli Mövzular

- [17-interfaces.md](17-interfaces.md) — `MarshalJSON`/`UnmarshalJSON` interface metodu kimi
- [19-type-assertions.md](19-type-assertions.md) — `interface{}` decode zamanı float64 tuzağı
- [21-enums.md](21-enums.md) — enum-ların JSON serialize edilməsi
- [01-http-server.md](../backend/01-http-server.md) — HTTP handler-də JSON encode/decode
- [02-http-client.md](../backend/02-http-client.md) — external API-dən JSON cavabı parse etmək
- [07-environment-and-config.md](../backend/07-environment-and-config.md) — JSON konfiqürasiya faylları
