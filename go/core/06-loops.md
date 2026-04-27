# Döngülər — Loops (Junior)

## İcmal

Go-da yalnız bir döngü konstruksiyası var: `for`. `while`, `do-while`, `foreach` kimi ayrı açar sözlər yoxdur — hamısının işini `for` görür. Bu sadəlik dilin filosofiyasını əks etdirir: bir şeyi bir yolla etmək. `for range` isə massivlər, slice-lar, map-lər, string-lər üzərindən keçmək üçün istifadə olunur.

## Niyə Vacibdir

Backend developer üçün döngülər hər yerdədir: API cavablarını parse etmək, verilənlər bazası sətirləri üzərindən keçmək, toplu əməliyyatlar etmək. Go-nun `for range` sintaksisi güclüdür: blank identifier (`_`) ilə lazımsız dəyişkənləri ignore etmək mümkündür, etiketlərlə (`label`) iç-içə döngülərdən xarici döngüyü dayandırmaq mümkündür.

## Əsas Anlayışlar

- **Klassik `for`** — `for init; condition; post { }` — C-stilindəki klassik döngü
- **`while` kimi `for`** — `for condition { }` — yalnız şərt olan döngü
- **Sonsuz döngü** — `for { }` — `break` ilə dayandırılır
- **`for range`** — kolleksiyalar üzərindən keçmə
- **`break`** — döngüdən çıxmaq
- **`continue`** — növbəti iterasiyaya keçmək
- **Label** — etiketli `break`/`continue`; iç-içə döngülərdə xarici döngüyü idarə etmək
- **Blank identifier `_`** — `for _, v := range slice` — index-i ignore etmək üçün

## Praktik Baxış

**Real layihədə istifadə:**
- `for range` — API response-dakı elementləri emal etmək
- `for range` map — konfiqurasiya key-value cütlərini oxumaq
- Sonsuz döngü + `break` — worker process, WebSocket message loop
- `continue` — validation: yanlış elementləri atla, düzgünləri emal et

**Trade-off-lar:**
- `for range` — oxunaqlı, amma hər iterasiyada dəyər kopyalanır; böyük struct-lar üçün pointer istifadə edin
- Sonsuz döngü — server process üçün normal pattern, amma `select {}` daha idomatic-dir
- Label-li break — nadir istifadə; çox istifadə kodun strukturunu pozur

**Common mistakes:**
- `for range` zamanı dəyəri dəyişdirməyə cəhd — kopya ilə işləyirsiz, orijinalı dəyişmir; pointer lazımdır
- Map üzərindən `for range` zamanı sıranın sabit olmasını gözləmək — map sırası qarantiya deyil
- Sonsuz döngüdə `break` şərtini unutmaq — proqram donur

## Nümunələr

### Nümunə 1: Klassik döngü formaları

```go
package main

import "fmt"

func main() {
    // 1. Klassik for — C kimi
    for i := 0; i < 5; i++ {
        fmt.Println("i =", i)
    }

    // 2. while kimi for
    sayi := 1
    for sayi <= 5 {
        fmt.Println("sayi =", sayi)
        sayi++
    }

    // 3. Sonsuz döngü — worker, server
    counter := 0
    for {
        if counter >= 3 {
            break
        }
        fmt.Println("İterasiya:", counter)
        counter++
    }

    // 4. continue — yalnız cüt ədədlər
    for i := 0; i < 10; i++ {
        if i%2 != 0 {
            continue // tək ədədləri atla
        }
        fmt.Println("Cüt:", i)
    }
}
```

### Nümunə 2: for range — kolleksiyalar üzərindən keçmə

```go
package main

import "fmt"

func main() {
    // Slice üzərindən
    meyveler := []string{"alma", "armud", "nar", "üzüm"}

    for index, deger := range meyveler {
        fmt.Printf("index: %d, dəyər: %s\n", index, deger)
    }

    // Yalnız dəyər lazımdırsa
    for _, meyve := range meyveler {
        fmt.Println("Meyvə:", meyve)
    }

    // Map üzərindən — sıra qarantiya deyil!
    qiymetler := map[string]float64{
        "çay":   1.50,
        "kofe":  3.00,
        "su":    0.50,
    }
    for mehsul, qiymet := range qiymetler {
        fmt.Printf("%s: %.2f AZN\n", mehsul, qiymet)
    }

    // String üzərindən — rune-larla (Azərbaycan hərfləri düzgün)
    for i, herf := range "Şəhər" {
        fmt.Printf("index %d: %c\n", i, herf)
    }
}
```

### Nümunə 3: Etiketli break — iç-içə döngülər

```go
package main

import "fmt"

func main() {
    // Matris üzərindən keçmə
    matris := [][]int{
        {1, 2, 3},
        {4, 5, 6},
        {7, 8, 9},
    }

    // Xəritə tapanda xarici döngüdən çıxmaq
    hedef := 5
    tapildi := false

xarici:
    for i, sira := range matris {
        for j, deger := range sira {
            if deger == hedef {
                fmt.Printf("Tapıldı: [%d][%d] = %d\n", i, j, deger)
                tapildi = true
                break xarici // hər iki döngüdən çıx
            }
        }
    }

    if !tapildi {
        fmt.Println("Tapılmadı")
    }

    // API pagination loop — real istifadə
    page := 1
    for {
        // items, err := fetchPage(page)
        // if err != nil || len(items) == 0 { break }
        fmt.Printf("Səhifə %d emal edildi\n", page)
        page++
        if page > 3 { // simulyasiya
            break
        }
    }
}
```

## Praktik Tapşırıqlar

1. JSON array emalı simulyasiyası: `users := []map[string]interface{}{ ... }` — `for range` ilə hər istifadəçinin adını və emailini çap et; aktiv olmayanları `continue` ilə atla.

2. Matris cəmi: `[][]int` tipli 3x3 matris yarat. İç-içə `for range` ilə bütün elementlərin cəmini hesabla.

3. Word frequency counter: `words := []string{...}` verilmiş sözlər siyahısından hər sözün neçə dəfə keçdiyini `map[string]int` ilə say. `for range` istifadə et.

4. Retry loop: bir funksiya 3 cəhd edir, uğur qazanarsa `break`, qaçırsa növbəti cəhdə keçir. Bütün cəhdlər uğursuz olarsa xəta log edir. `for i := 0; i < maxRetry; i++` strukturunu istifadə et.

## PHP ilə Müqayisə

- PHP: `foreach ($items as $key => $value)` → Go: `for key, value := range items`
- PHP: `foreach ($items as $item)` → Go: `for _, item := range items` (index lazım deyil)
- PHP-də `while`, `do-while` var; Go-da yalnız `for` (amma eyni davranışı simulyasiya edir)
- Go-da `for range` string üzərindən rune-larla gəzir, baytlarla deyil — Azərbaycan hərfləri üçün vacib
- PHP-də label yoxdur; Go-da `break label` ilə iç-içə döngülərdən çıxmaq mümkündür
- Map üzərindən `for range`: PHP-də sıra saxlanır; Go-da sıra qarantiya deyil

## Əlaqəli Mövzular

- [05-conditionals.md](05-conditionals.md) — şərt operatorları
- [07-functions.md](07-functions.md) — funksiyalar
- [08-arrays-and-slices.md](08-arrays-and-slices.md) — slice-lar
- [09-maps.md](09-maps.md) — map-lər
