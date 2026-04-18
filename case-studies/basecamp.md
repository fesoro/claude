# Basecamp (37signals)

## Ümumi baxış
- 37signals kiçik, gəlirli proqram şirkətidir, layihə idarəetməsi (Basecamp) və email (HEY) məhsulları hazırlayır. Öz fikirli, əks-mədəni mühəndislik mövqeyi ilə məşhurdur.
- Miqyas: nəhənglərlə müqayisədə istifadəçi sayında təvazökar (milyonlarla istifadəçi, milyardlarla deyil). Amma komanda kiçik olduğu üçün işçi başına gəlir nəhəngdir — təqribən 60-80 nəfər (2023).
- Əsas tarixi anlar:
  - 1999 — 37signals olaraq Jason Fried tərəfindən web dizayn konsaltinq şirkəti kimi təsis edildi.
  - 2004 — Basecamp məhsulu buraxılır. David Heinemeier Hansson Basecamp-ın kod bazasından Ruby on Rails-i çıxarır.
  - 2006 — Rails 1.0 buraxılır.
  - 2014 — Şirkət Basecamp adlandırılır.
  - 2020 — HEY (email məhsulu) buraxılır.
  - 2022 — 37signals ictimai olaraq cloud-u tərk edir, AWS-dən öz aparatına köçürülür. DHH "Why We're Leaving the Cloud" yazısını yazır və xərc qənaətini dərc edir.
  - 2022 — Şirkət 37signals adına geri dönür.

## Texnologiya yığını
| Qat | Texnologiya | Niyə |
|-------|-----------|-----|
| Dil | Ruby | DHH Rails yaratdı; Ruby ev dilidir. |
| Web framework | Ruby on Rails | Onu ixtira etdilər. |
| Əsas DB | MySQL | Sübut olunmuş, açıq mənbəli, darıxdırıcı. |
| Cache | Redis, Memcached | Standart Rails cütlüyü. |
| Queue/messaging | Resque / Solid Queue / Sidekiq (dövrdən/məhsuldan asılı olaraq) | Tarixlərində təcrübə etdilər; indi öz "Solid" gem-lərini (Solid Queue, Solid Cache, Solid Cable) irəliləyirlər. |
| Search | Elasticsearch | Hazır. |
| İnfrastruktur | Öz aparatı (Dell serverlər) iki ABŞ kolokasiya obyektində, deployment üçün MRSK / Kamal istifadə edir | DHH-in "cloud-u tərk et" kampaniyası. |
| Monitorinq | Grafana, Prometheus, daxili alətlər | Standart açıq mənbəli. |

## Dillər — Nə və niyə

### Ruby (+ Rails)
Basecamp Rails dükanıdır, son. DHH Rails-i 2004-də Basecamp-dan çıxardı; ikisi birlikdə təkamül etdi. Rails xüsusiyyət əlavə edirsə, bu adətən Basecamp və ya HEY-in ona ehtiyacı olduğu üçündür. Son nümunələr:
- Turbo / Hotwire — Basecamp-ın front-end ehtiyacları üçün quruldu.
- Solid Queue, Solid Cache, Solid Cable — Basecamp-in Redis-ə ehtiyac olmadan düz MySQL/PostgreSQL-də queue, cache və websocket pub/sub işlədə bilməsi üçün quruldu.
- Kamal (əvvəllər MRSK) — onların "cloud-u tərk et" miqrasiyası üçün quruldu.

### JavaScript / Stimulus + Turbo
React yoxdur. Vue yoxdur. Öz alternativlərini yazdılar: **Hotwire** = Turbo (təxminən HTML over the wire) + Stimulus (HTML-ə qoşulan kiçik controller-lər). Fəlsəfə "server-rendered HTML minimum JS ilə"-dir. Stimulus controller-ləri `data-controller` atributu ilə elementləri tapan və hadisələri idarə edən kiçik siniflərdir.

Bu, SPA/React sənaye kompleksinə birbaşa reaksiyadır. Kiçik komandaya malik məhsul şirkəti üçün HTML + bir az JS göndərmək client-side app saxlamaqdan sürətlidir.

### Digər
Minimal. Bəzi shell script-ləri, bəzi SQL. Poliqlot servislər yoxdur.

## Framework seçimləri — Nə və niyə

**Ruby on Rails, monolit.** Təkcə framework deyil — *fəlsəfə*:
- "The Majestic Monolith" (DHH, 2015 blog post və sonrakı esselər).
- "One Person Framework" (DHH, 2022) — Rails tək developer-ə real məhsulu uçdan-uca qurmağa imkan verməlidir.
- "The Rails Doctrine" (DHH, 2016 esse) — inteqrasiya olunmuş sistemlər, convention over configuration, omakase, sabitlikdən çox irəliləyiş.

Microservis-ləri, Kubernetes-i, hər yerdə React-i və mürəkkəb cloud arxitekturalarını açıqca rədd edirlər — əsas bu ki, ölçüləri buna ehtiyac duymur və mürəkkəblik vergisi pulsuz deyil.

## Verilənlər bazası seçimləri — Nə və niyə

### MySQL
Əsas store. MySQL Classic-də (bəzi Percona dadı ilə) işləyirdilər; hazırda MySQL-dədirlər. Relational, darıxdırıcı, yaxşı başa düşülür.

### Redis
Cache və bəzi queue üçün (tarixən). "Solid" gem-ləri asılılıq kimi Redis-i də azaltmaq cəhdidir — Solid Queue MySQL/PostgreSQL istifadə edir, Solid Cache MySQL (sıxılma ilə) istifadə edir, Solid Cable Postgres listen/notify istifadə edir.

### Çoxlu-region yox
HA üçün bir neçə data mərkəzində işləyirlər, amma böyük SaaS şirkətlərinin vəsvəsə etdiyi növ multi-region active-active deyil. Basecamp müştəriləri əsasən bir qitədə olan biznes istifadəçiləridir; mürəkkəblik buna dəyməz.

## Proqram arxitekturası

**Rails monolit.** Bu qədər.

- Basecamp (əsas məhsul) bir Rails app-dir.
- HEY (email) başqa Rails app-dir.
- Hər biri kiçik sayda server-ə (onlarla, minlərlə deyil) deploy olunur.
- Arxa plan job-ları eyni maşınlarda worker prosesləri kimi işləyir.
- Bir neçə dəstək servisi (HEY üçün SMTP və s.) yan-vagon prosesləri kimidir.

```
 [User]
    |
    v
 [Load balancer (HAProxy or similar)]
    |
    v
 [App servers: Rails + Puma, running the monolith]
    |
    +--> [MySQL primary + replicas]
    +--> [Redis]
    +--> [Elasticsearch]
    +--> [Background workers (Sidekiq/Solid Queue)]
    +--> [S3-compatible object storage for attachments]
```

Bu diaqram qəsdən kiçikdir. Məqsəd budur.

## İnfrastruktur və deploy
- 2022-2023-cü illərdə 37signals AWS-dən öz aparatına miqrasiyanı başa çatdırdı — iki ABŞ data mərkəzində (kolokasiya təchizatçısı Deft vasitəsilə) Dell R7625 serverləri.
- DHH xərc müqayisəsini dərc etdi: bir ildə təqribən geri qaytarılan aparat CapEx-i ilə əvəz olunan bir neçə milyon dollarlıq cloud hesabları.
- Deployment: Kamal (əvvəlki MRSK) — yazdıqları deploy aləti, Docker + SSH istifadə edərək konteynerləri bare-metal server-lərə çıxarır. Açıq mənbəyə çevrildi.
- Lazım olduqda gündə bir neçə dəfə deploy edirlər, amma davamlı deployment kultu yoxdur. "Hazır olanda göndəririk."

## Arxitekturanın təkamülü

1. **2004**: Rails 0.x, Basecamp buraxılır. Kiçik komanda, kiçik app.
2. **2004-2010**: Rails məhsulla birlikdə böyüyür. Basecamp Classic, sonra Basecamp 2, sonra Basecamp 3 — hər biri mühüm yenidən yazma.
3. **2013-2017**: Turbolinks (Turbo-nun sələfi) SPA mürəkkəbliyi olmadan sürətli səhifə naviqasiyası üçün.
4. **2020**: HEY buraxıldı — başqa Rails monoliti, email-spesifik infra ilə (SMTP ingress, DKIM və s.).
5. **2021-2022**: Hotwire (Turbo + Stimulus) açıq mənbəyə çevrildi. Deployment üçün Kamal.
6. **2022-2023**: Cloud-u tərk etdilər. Production-u AWS-dən öz aparatına köçürdülər. Xərcləri və dərsləri dərc etdilər.
7. **2023-2024**: Solid Queue / Solid Cache / Solid Cable Rails 7.1/8-ə daxil oldu — hamısı Basecamp-dan çıxarıldı.

## Əsas texniki qərarlar

### 1. Cloud-u tərk etmək
**Problem**: AWS hesabları elastik miqyaslanmaya ehtiyacı olmayan iş yükləri üçün ildə milyonlarla idi.
**Seçim**: Dell serverlər alıb iki kolokasiya obyektində rack-ə yerləşdirin, hər şeyi bare metal-də işlədin. Onlara konteynerləri çıxarmaq üçün Kamal istifadə edin.
**Kompromislər**: Tutum planlaması, aparat həyat dövrü, fiziki ops (kolokasiya təchizatçısı əllə yerində işi idarə edir) sahibi olmaq lazımdır. Daha az elastik.
**Sonra nə oldu**: DHH konkret rəqəmləri dərc etdi — miqrasiyadan ildə təqribən $2M qənaət. Sənayeyə cloud default-larını şübhə altına almağa icazə verdi. "Cloud repatriation" müzakirə mövzusu oldu.

### 2. Hotwire — React-i rədd etmək
**Problem**: Basecamp 3-də React-üslublu SPA-lar vasitəsilə zəngin UI-lar qurmaq kod bazasını ikiqat artırmaq və progressive enhancement-i itirmək demək olardı.
**Seçim**: Turbo (`<turbo-frame>` və `<turbo-stream>` vasitəsilə HTML-over-the-wire, qismən səhifə yenilənmələri) + Stimulus (kiçik JS çiləmələri) qurun. Server HTML render edir; client HTML fraqmentlərini yeniləyir.
**Kompromislər**: Offline-first yox, React ekosistemi yox. Bəzi interaktiv UI React-lə qurmaqdan daha çətindir.
**Sonra nə oldu**: Hotwire interaktivlik üçün default Rails cavabı oldu. PHP-də (Laravel dünyası üçün Livewire, Alpine) bənzər kitabxanalara ilham verdi.

### 3. Solid Gem-ləri — Redis-i MySQL/PostgreSQL ilə əvəz etmək
**Problem**: Hər müasir Rails app cache, queue və websocket-lər üçün Redis-dən asılıdır. Bu idarə etmək üçün başqa verilənlər bazasıdır.
**Seçim**: Solid Queue (SQL-də queue), Solid Cache (SQL-də sıxılma ilə cache), Solid Cable (PostgreSQL listen/notify-da pub/sub) qurun. Onları Rails 7.1/8-də default kimi göndərin.
**Kompromislər**: Performans tavanı Redis-dən aşağı ola bilər. Əsas SQL DB-nizə yük qoyur.
**Sonra nə oldu**: Kiçik Rails app-ları Redis olmadan tək SQL DB-də işləyə bilər. Əməliyyatlar sadələşdi. "One-Person Framework" fəlsəfəsi gücləndi.

### 4. Majestic Monolit
**Problem**: Sənaye təzyiqi app-ı microservis-lərə ayırmaq üçün.
**Seçim**: Tək Rails app olaraq qalın. Aydın model/controller sərhədləri ilə təşkil edin, yalnız birdən çox app-ə ehtiyac olduqda gem-ləri çıxarın.
**Kompromislər**: Microservis-lərin icazə verdiyi kimi komandaları yüzlərlə mühəndisə miqyaslana bilməzsiniz (yaxşıdır — onların 60-ı var).
**Sonra nə oldu**: "Majestic Monolith" blog post-u sənaye toxunuşuna çevrildi, Shopify, GitHub və bir çox başqaları tərəfindən monolit seçimlərinin əsaslandırılması kimi sitat gətirildi.

### 5. Deployment üçün Kamal
**Problem**: Cloud-u tərk etdilər, amma Kubernetes idarə etmək istəmədilər. Capistrano konteyner-aware deyildi.
**Seçim**: Kamal yazın — Docker konteynerlərini SSH vasitəsilə bare-metal server-lərə deploy edən MRSK dövrü alət. Kubernetes-dən sadə.
**Kompromislər**: Çox böyük fleet-lər və ya mürəkkəb servis mesh-lər üçün uyğun deyil.
**Sonra nə oldu**: Kamal kiçikdən-orta deployment-lər üçün Kubernetes-ə real alternativ oldu. Rails 8 Kamal konfiqurasiya şablonları ilə göndərilir.

## PHP/Laravel developer üçün dərs
- Radikal sadəlik etibarlı mühəndislik strategiyasıdır. Yaxşı seçilmiş monolitdə kiçik komanda microservis-lərdə itmiş çox böyük komandaları üstələyə bilər.
- Server-rendered HTML + JS çiləməsi (Hotwire-üslubu və ya Laravel-də Livewire) bir çox məhsul səthi üçün React-dən qurmaq daha sürətlidir.
- Cloud default-larına meydan oxuyun. İş yükünüz sabit vəziyyətdədirsə, bir neçə kirayələnmiş həsr olunmuş server (Hetzner, OVH, kolokasiya) AWS-dən dramatik şəkildə ucuz ola bilər.
- Framework-ünüzü tərəfdaş kimi qəbul edin: geri töhfə verin, maintainer-ləri sponsor edin, daxili nümunələrinizi açıq mənbə olaraq göndərin (Laravel fəlsəfəcə oxşar güclü birinci-tərəf ekosistemə sahibdir — Horizon, Cashier, Nova).
- Yaxşı məhsul qurmaq üçün Redis, Kafka və 12 microservis-ə ehtiyacınız yoxdur. Sadə başlayın; yalnız ölçülmüş sübutla ehtiyacınız olduqda mürəkkəblik əlavə edin.

## Əlavə oxu üçün
- Blog: "The Majestic Monolith" — DHH (2015).
- Blog: "Why We're Leaving the Cloud" — DHH (2022).
- Blog: "Our cloud spend in 2022" and follow-up posts — DHH / 37signals.
- Essay: "The Rails Doctrine" — DHH (2016).
- Book: "Getting Real" — 37signals (free online).
- Book: "Rework" — Jason Fried + DHH.
- Book: "Remote: Office Not Required" — Jason Fried + DHH.
- Book: "Shape Up" — Ryan Singer. Product shaping at 37signals.
- Blog: "One Person Framework" — DHH (2022).
- Open source: Kamal (kamal-deploy.org), Hotwire (hotwire.dev), Solid Queue / Solid Cache / Solid Cable.
- Talk: "Writing Software" — DHH at various Rails conferences.
- Signal v. Noise blog archive — 37signals engineering and product posts since 2004.
