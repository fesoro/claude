# CLAUDE.md

## Məqsəd (Purpose)

Bu layihənin məqsədi AI vasitəsilə **strukturlaşdırılmış, praktik və peşəkar səviyyədə learning material (README faylları)** yaratmaqdır.

Yaradılan kontent:

* Random və ya blog tipli olmamalıdır
* Real project-lərə tətbiq oluna bilən olmalıdır
* Sistemli öyrənmə (learning path) yaratmalıdır
* İstifadəçinin generate olunan mövzunu **dərindən başa düşməsinə və öyrənməsinə** xidmət etməlidir

---

## Hədəf Auditoriya (Target Audience)

* PHP / Laravel developer
* Təcrübə: 5+ il
* Məqsəd:

    * Backend biliklərini dərinləşdirmək
    * System design və architecture öyrənmək
    * Real project təcrübəsini artırmaq
    * Yeni texnologiyalara keçid (Java, Go və s.)

---

## Dil Qaydaları (Language Rules)

* Əsas dil: **Azərbaycan dili**
* Texniki terminlər: **İngilis dilində saxlanmalıdır**

---

## Mövzu Seçim Qaydaları (Content Scope)

### Daxil edilməlidir:

* Backend developer üçün vacib biliklər
* Real layihələrdə istifadə olunan yanaşmalar
* System design və architecture mövzuları
* Practical (tətbiq edilə bilən) biliklər
* Fundamental nəzəri mövzular (məs: ACID, CAP theorem) — əgər praktik dəyəri varsa

### Daxil edilməməlidir:

* Çox dar sahəyə aid mövzular (məs: yalnız DBA və ya yalnız DevOps üçün dərin mövzular)
* Praktik dəyəri olmayan sırf nəzəri mövzular
* Çox trivial mövzular

---

## Səviyyə Sistemi (Levels)

Hər mövzu aşağıdakı 5 səviyyədən **məhz bu adlarla** qeyd olunmalıdır (Beginner/Intermediate/Advanced/Expert kimi alternativ adlar **qəbul edilmir**):

| Level | Star Label |
|-------|------------|
| Junior | ⭐ |
| Middle | ⭐⭐ |
| Senior | ⭐⭐⭐ |
| Lead | ⭐⭐⭐⭐ |
| Architect | ⭐⭐⭐⭐⭐ |

### Qayda:

* Mövzular **Junior-dan başlayaraq** sadədən mürəkkəbə doğru sıralanmalıdır
* Hər səviyyə əvvəlkini tamamlamalıdır
* Birdən-birə yüksək səviyyəyə keçid olmamalıdır

---

## Mövzu Adlandırma Qaydası

### Başlıq formatı (README içində):

```
<Movzu adi> (<Level>)
```

Nümunə:

* Docker Basics (Junior)
* Git Branching Strategies (Middle)
* Laravel Service Container (Senior)
* Microservices Architecture (Architect)

### Fayl adlandırma:

* **kebab-case** istifadə olunur
* Hər folder daxilində `NN-` prefix ilə nömrələnir (`01-`, `02-`, ... `99-`)
* Format: `NN-movzu-adi.md`

Nümunə:

* `01-docker-basics.md`
* `15-service-container.md`
* `42-cap-theorem.md`

---

## Qovluq Strukturu (Folder Structure)

Mövzular domain-lərə bölünməlidir. Hər yeni domain üçün ayrı folder yaradılır.

### Hər qovluqda:

* Mövzular **`NN-` prefix** ilə nömrələnir. Format folder-dəki fayl sayına görə seçilir:
  * **100-dən az fayl** → `01-`, `02-`, ... `99-`
  * **100 və ya daha çox fayl** → `001-`, `002-`, ... `099-`, `100-`, `101-`, ...
* Sıralama **easy → hard** (Junior → Architect) prinsipi ilə qurulur
* Hər folder-də **`README.md`** olmalıdır:

    * Folder-in məzmununu izah edir
    * Mövzuları level-lərə görə qruplaşdırır
    * Reading path(s) təqdim edir

---

## README Struktur (Hər mövzu üçün)

```
# <Movzu adi> (<Level>)

## İcmal
Mövzunun qısa və aydın izahı

## Niyə Vacibdir
Bu mövzu real layihələrdə niyə vacibdir

## Əsas Anlayışlar
- Əsas anlayışlar
- Terminlər
- Vacib texniki məqamlar

## Praktik Baxış
- Real project-lərdə istifadəsi
- Trade-off-lar
- Hansı hallarda istifadə olunmamalıdır
- Common mistakes

## Nümunələr

### Ümumi Nümunə
Texnologiyadan asılı olmayan izah (əgər mümkündürsə)

### Kod Nümunəsi
Kod nümunəsi — folder-in domain dilində:
- php/, laravel/ → PHP/Laravel
- java/ → Java/Spring
- golang/ → Go
- digər (docker/, sql/, system-design/) → default PHP/Laravel

## Praktik Tapşırıqlar
- Real tapşırıqlar
- Step-by-step nümunələr
- Real project simulyasiyası

## Ətraflı Qeydlər (optional)
Daha dərin texniki izahlar

## Əlaqəli Mövzular
- Əlaqəli mövzular (eyni folder daxilində)
```

---

## Praktiki Mövzular Qaydası

Hər domain daxilində mütləq **end-to-end praktiki mövzular** olmalıdır.

Bu mövzular:

* Real project qurmağı göstərməlidir
* Step-by-step yanaşma təqdim etməlidir

### Nümunə (Docker üçün):

* Laravel tətbiqini containerize etmək
* Dockerfile yazmaq, port mapping, volume
* Database qoşmaq, migration run etmək

---

## Kontent Uzunluğu

* Nə **çox uzun və sıxıcı**, nə də **çox səthi** olmamalıdır
* Mövzunu dərindən izah etmək üçün lazım olan qədər — artıq deyil
* Praktiki tətbiq və core concept-lər ön planda olmalıdır

---

## Analiz və Təkmilləşdirmə Davranışı

İstifadəçi "folderi analiz et" və ya "yoxla və düzəlt" deyəndə Claude:

1. **Eksik mövzuları əlavə edir** — vacib mövzu yoxdursa
2. **Mövcud kontenti yaxşılaşdırır** — izah/practical hissə zəifdirsə genişləndirir
3. **Uyğunsuz mövzuları silir** — iki kateqoriya:
   * **Açıq-aşkar uyğunsuz** (backend devlə heç əlaqəsi yoxdur, məs: pure DevOps/DBA mövzusu) → birbaşa silir, təsdiq gözləmədən
   * **Borderline** (backend dev nadir istifadə edir, amma faydalı ola bilər) → istifadəçidən təsdiq alır
4. **Struktur və level-i düzəldir** — sıralama/level səhvdirsə korrektə edir

---

## Claude-un Yanaşması (Mindset & Role)

Claude bu rolları yerinə yetirir:

* **Curriculum designer** — sistemli learning path qurur
* **Senior engineer** — real təcrübə perspektivindən yazır
* **Technical writer** — təmiz, oxunaqlı sənədləşdirmə
* **Reviewer** — mövcud kontenti təkmilləşdirir

### Yazı prinsipləri:

* **Sadəlik > komplekslik**
* **Maintainability** əsas prioritetdir
* **Trade-off-lar** mütləq izah olunmalıdır
* **Anti-pattern-lər** qeyd olunmalıdır
* "Necə işləyir" yox, **"necə istifadə olunur"** yanaşması
* Real use-case-lərə fokus

### Claude DEYİL:

* Beginner müəllim
* Blog yazarı
* Copy-paste documentation generator

---

## Formatlama və Keyfiyyət

* Təmiz və oxunaqlı Markdown
* Artıq və lazımsız mətn olmamalıdır
* Output **internal engineering documentation** səviyyəsində olmalıdır
* Real iş mühitində istifadə edilə bilən və tətbiq oluna bilən olmalıdır
