# Dropbox (Lead)

## Ümumi baxış
- **Nə edir:** Cloud fayl saxlama, sinxronizasiya və birgə iş. Desktop client cihazlarla cloud arasında qovluqları sinxronlaşdırır; web və mobile client-lər; sənəd preview; Paper (docs), Sign (e-imza).
- **Yaradılıb:** 2007-ci ildə Drew Houston və Arash Ferdowsi tərəfindən.
- **Miqyas:**
  - ~700M qeydiyyatdan keçmiş istifadəçi.
  - Yüzlərlə petabayt istifadəçi datası.
  - Hər gün milyardlarla fayl sinxronlaşdırılır.
- **Əsas tarixi anlar:**
  - 2008: məşhur demo videosu ilə ictimai işə salınma.
  - 2011: böyük qəbul, freemium miqyaslanır.
  - 2015–2016: **The Great Migration** — data AWS S3-dən öz storage-inə (Magic Pocket) öz data mərkəzlərində köçürüldü.
  - 2018: IPO.
  - 2019+: məhsuldarlıq funksiyalarına fokus (Paper, Sign).
  - 2020+: pandemiya dəstəyi, sonra daha çox enterprise fokus.

Dropbox miqyas öz infrastrukturlarını işlətməyi ucuz etdikdə **cloud-u tərk edən** şirkətin kanonik nümunəsidir. Həmçinin klassik **miqyasda Python** hekayəsi və sistem işi üçün Go qəbulu.

## Texnologiya yığını

| Layer | Technology | Why |
|-------|-----------|-----|
| Backend (app logic) | Python | Tez yazılır, böyük kod bazası, mypy-tipli |
| Systems / infra code | Go | Concurrency, tək-binary deploy-ları, şəbəkə servisləri |
| Performance-critical services | Rust (bəzi) | Go və ya Python yetərli olmadığı yerdə |
| Desktop client | Rust + C++ (indi); Python (ilkin) | Cross-platform native sync engine |
| Primary DB | MySQL (ağır sharded) | Sınaqdan keçmiş, miqyasda davranışı məlumdur |
| Cache | Memcached, Edgestore cache-lər | Standart |
| Storage backend | Magic Pocket (öz, Go) | S3-ü əvəz etdi, onların miqyasında pul qənaət etdi |
| Graph / metadata | Edgestore (öz) | Dropbox-miqyasında metadata |
| HTTP edge | Bandaid (öz, Go) | Custom HTTP proxy |
| Queue/messaging | Kafka, custom | Async pipeline-lar |
| Search | Nautilus (öz axtarış sistemi) | Fayl content axtarışı |
| Static typing | Mypy (ona töhfə verirlər) | Python-u idarə olunan saxla |
| Infrastructure | Öz data mərkəzləri + bəzi AWS | Miqyasda qiymət |

## Dillər — Nə və niyə

### Python
- Dropbox-un dili. Milyonlarla sətir Python.
- Guido van Rossum (Python-un yaradıcısı) illərlə Dropbox-da işlədi.
- **mypy**-ə (Python statik tipizasiyası) və geniş Python ekosisteminə böyük töhfə verənlərdir.
- Python-da qalma səbəbləri: böyük mövcud kod bazası, komanda təcrübəsi, mypy böyük Python kod bazalarını idarə oluna bilən edir.

### Go — sistem proqramlaşdırması üçün
- Məşhur blog: *"Go at Dropbox: The first year"* (təxminən 2014).
- Seçildi: Magic Pocket (storage sistemi), Bandaid (HTTP proxy) və digər şəbəkə-ağır servislər üçün.
- Səbəblər: daxili concurrency, asan deploy edilən statik binary-lər, şəbəkə performansı.

### Rust
- Sonradan spesifik performansa kritik və yaddaş-təhlükəsizliyinə kritik kod üçün qəbul edildi.
- Desktop sync engine ("Nucleus") yenidən yazılması Python desktop sync-in tavana çatmasından sonra Rust + C++ istifadə etdi.

### C / C++
- Aşağı səviyyəli client kodu, bəzi native kitabxanalar.

## Framework seçimləri — Nə və niyə
- Tarixən **Pyramid / custom Python web framework-ləri**.
- Servislər üçün gRPC-tipli protokollar üzərində öz yüngül RPC framework-ləri.
- **Mypy** framework deyil, amma disiplindir — demək olar ki bütün Python tipləşdirilib.
- Build sistemi üçün **Bazel** (polyglot Python/Go/Rust build-lərini yaxşı idarə edir).

## Verilənlər bazası seçimləri — Nə və niyə

### MySQL — ağır sharded
- İstifadəçi metadata-sı, fayl metadata-sı sharded MySQL-də oturur.
- Üstündə tətbiq-əsaslı layer olaraq Edgestore-dan (yuxarıda) istifadə etdiklərini yazdılar.
- MySQL yetkinliyi, məlum failure mode-ları, güclü operator ekosistemi üçün seçildi.

### Edgestore
- Dropbox tərəfindən qurulmuş metadata servisi.
- MySQL shard-ları üzərində graph-tipli model.
- Fayl ağacı metadata-sı üçün istifadə olunur (təbii olaraq graph-strukturlu: istifadəçilər qovluqlara sahibdir, qovluqlar fayllara, fayllar versiyalara).

### Magic Pocket
- Əsl fayl baytları üçün storage sistemi.
- Go-da yazılıb, Dropbox-un öz data mərkəzlərində işləyir.
- Custom hardware dizaynı da: yüksək sıxlıq üçün ixtisaslaşdırılmış "SMR" disklər (shingled magnetic recording).
- Magic Pocket-dən əvvəl: hər şey Amazon S3-də idi. Dropbox-un AWS-ə illik hesabı on rəqəmli rəqəmlərdə idi.

### Memcached
- Tətbiq ilə MySQL arasında caching layer.

## Proqram arxitekturası

```
  Clients (desktop sync, web, mobile)
        |
   [Bandaid HTTP edge in Go]
        |
   [Python monolith + services]
        |
        +--- [Edgestore (metadata)] --- MySQL shards
        |
        +--- [Magic Pocket (bytes)] --- own DCs, exabytes
        |
        +--- [Nautilus (search)]
        |
        +--- [Pipelines: Kafka, ML, analytics]
```

### Sync engine
- Erkən desktop sync Python idi. İşlədi, amma Windows fayl sistemi edge case-lərində, çox böyük qovluqlarda və performansda çətinliklərlə üzləşdi.
- Sync engine-i Rust + C++-da "Nucleus" kimi yenidən yazdılar. Çox hissəli blog seriyasında təsvir edilən böyük layihə.

### Magic Pocket
- Trilyonlarla fayl data chunk-ı saxlayır.
- Zone-lar arasında replikasiya; davamlılıq üçün erasure coding.
- Soyuqca storage üçün optimallaşdırılmış custom hardware "brick-lər".

## İnfrastruktur və deploy

- Əsasən öz data mərkəzləri, bəzi edge iş yükləri və yeni regionlar üçün AWS saxlanılır.
- HTTP edge-də Bandaid.
- Python və Go servislərini deploy etmək üçün daxili platforma.
- Observability-yə böyük sərmayə: metrics, tracing, log pipeline-ları.

## Arxitekturanın təkamülü

| Year | Change |
|------|--------|
| 2007–2010 | AWS-də Python-da monolit; Python-da sync client |
| 2011–2013 | Python miqyaslanır; MySQL sharding; Memcached |
| 2014 | İnfra işi üçün Go qəbul edilir; daxili framework-lər |
| 2015–2016 | Magic Pocket qurulur və yayılır; S3-dən The Great Migration |
| 2017 | Köçürmə təxminən tamamlanır; illik ~$75M-dən çox qənaət bildirilir |
| 2019+ | Rust/C++-da Nucleus sync engine |
| 2021+ | Hot path-larda daha çox Rust; davamlı mypy qəbulu |

## 3-5 Əsas texniki qərarlar

1. **Öz storage-i (Magic Pocket) üçün AWS S3-ü tərk etmək.** Dropbox-un miqyasında iqtisadiyyat tərsinə döndü. Öz quraraq illik ~$75M qənaət etdilər (bildirilir).
2. **Sistem proqramlaşdırması üçün Go, amma app məntiqi üçün Python qalır.** Tətbiqi yenidən yazmadılar — Go-nu ən çox kömək etdiyi yerdə istifadə etdilər.
3. **Sync engine-i Rust + C++-da yenidən yazmaq.** İlkin Python sync engine tavana çatdı; yaddaş təhlükəsizliyi + performans seçimi idarə etdi.
4. **Böyük Python kod bazaları üçün mypy-ə sərmayə.** Tip yoxlaması olmadan, milyonlarla sətirlik Python kod bazası idarə oluna bilməz olur.
5. **Polyglot build-lər üçün Bazel.** Bir monorepo-da Python, Go və Rust-un ardıcıl build edilməsini saxlayır.

## Müsahibədə necə istinad etmək

1. **Cloud həmişə ucuz deyil.** Müəyyən miqyasda öz hardware-in cloud qiymətlərindən ucuzdur. Əksər Laravel startup-ları üçün cloud hələ də düzgün default-dur. Amma bunun əbədi olmadığını bilin.
2. **Bütün tətbiqi yenidən yazma; hot hissəni yenidən yaz.** Dropbox Python tətbiqini Go-da yenidən yazmadı. Python-un zəif olduğu yerlərdə (şəbəkə performansı, storage) Go servisləri yazdılar. Laravel developer-lər: Laravel-i saxlayın; lazım olarsa video transcoding və ya ağır emal üçün Go/Rust servisi əlavə edin.
3. **Kod bazası böyüdükcə statik tipizasiya qazanc gətirir.** Python üçün Mypy = PHP üçün PHPStan / Psalm = JS üçün TypeScript. Birinci gündən istifadə edin, xüsusilə komandalarda.
4. **Metadata vs blob ayrılığı.** MySQL/Postgres-də metadata, object storage-də blob-lar. Dropbox-un Edgestore + Magic Pocket ayrılığı klassik pattern-dir. Laravel-in S3/local üzərində daxili filesystem abstraksiyası daha kiçik miqyasda eyni fikirdir.
5. **Monorepo işləyə bilər.** Bazel əksər shop-lar üçün çoxdur, amma paylaşılan kitabxanalarla monorepo (Composer path repo-lar vasitəsilə) kiçik-orta Laravel komandaları üçün yaxşı işləyir.
6. **Asılı olduğunuz alətlərə töhfə verin.** Dropbox mypy-ni maliyyələşdirir. Laravel shop-ları Laravel, PHPStan və ya asılı olduqları ekosistem paketlərinə töhfə verə bilər. Fork edib saxlamaqdan ucuzdur.

## Əlavə oxu üçün
- Wired: *The Epic Story of Dropbox's Exodus From the Amazon Cloud Empire*
- Dropbox Tech Blog: *Go at Dropbox: The First Year*
- Dropbox Tech Blog: *Scaling Magic Pocket*
- Dropbox Tech Blog: *How we've scaled Dropbox*
- Dropbox Tech Blog: *Inside the Magic Pocket*
- Dropbox Tech Blog: *Rewriting the heart of our sync engine* (Nucleus seriyası)
- Dropbox Tech Blog: *Our journey to type-check 4 million lines of Python*
- Talk: *Scaling Python at Dropbox*
