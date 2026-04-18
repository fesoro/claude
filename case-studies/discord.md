# Discord

## Ümumi baxış
- **Nə edir:** Real-time chat, səs və video platforması, ilkin olaraq gamer-lər üçün nəzərdə tutulmuşdu. Server-lər ("guild") mətn kanalları, səs kanalları, video, ekran paylaşımı və icma funksiyaları ilə. Həmçinin developer və kontent yaradanların icmaları üçün böyük platformadır.
- **Yaradılıb:** 2015-ci ildə Jason Citron və Stanislav Vishnevskiy tərəfindən.
- **Miqyas (açıq məlumat):**
  - ~200M aylıq aktiv istifadəçi (2023–2024 rəqəmləri).
  - On milyonlarla eyni anda aktiv olan istifadəçi.
  - Pik gaming saatlarında on milyonlarla eyni anda səs istifadəçisi.
  - Trilyonlarla mesaj saxlanılır (Discord-un öz 2022 blog-u).
- **Əsas tarixi anlar:**
  - 2015: işə salındı, gamer-lərə fokus.
  - 2017–2018: böyük artım; bir çox open-source icmalar tərəfindən seçildi.
  - 2020: pandemiya partlayışı; gaming-dən kənara çıxdı.
  - 2021: $10B+ dəyərində olduğu deyilən Microsoft alışını rədd etdi.
  - 2022: blog *"How Discord Stores Trillions of Messages"* (Cassandra-dan ScyllaDB-yə keçid).
  - Elixir, Rust və Go haqqında bir neçə məşhur engineering post.

Discord **bilərəkdən polyglot proqramlaşdırma** mövzusunda ən öyrədici müasir nümunədir: onlar bir dilin hər şey üçün uyğun olduğunu iddia etmirlər. Elixir, Rust, Go və Python istifadə edirlər — hər birini güclü tərəflərinə görə.

## Texnologiya yığını

| Layer | Technology | Why |
|-------|-----------|-----|
| Real-time messaging | Elixir (Erlang VM) | Milyonlarla eyni anda bağlantı, soft real-time |
| API gateway / services | Go (tarixən), indi daha çox Rust | Sürətli, statik tiplər, kiçik binary-lər |
| Read-state / hot paths | Rust | GC-siz, stabil tail latency |
| Scripting, ML | Python | ML üçün ekosistem, sürətli tooling |
| Web / desktop client | TypeScript + React (+ desktop üçün Electron) | Eyni web/desktop kod bazası |
| Mobile | React Native (ilkin) + native C++/Rust core-ları | Platformalar arası biznes məntiqi paylaşımı |
| Primary message store | ScyllaDB (Cassandra-dan köçürülüb) | Cassandra-uyğun, C++ ilə yazılıb, daha yaxşı tail latency |
| Other DBs | PostgreSQL (bəzi funksiyalar), Redis (cache), Elasticsearch (axtarış) | Hər funksiya üçün düzgün alət |
| Voice/video | Custom WebRTC stack (Elixir + C) | Ultra-aşağı latency, öz nəzarət |
| Queue | Kafka, custom Elixir pipeline-ları | Event yayılması |
| Infrastructure | Əsasən GCP | Commodity cloud |

## Dillər — Nə və niyə

### Elixir — real-time messaging-in özəyi
- Discord-un real-time sistemi (gateway, guild servisləri, presence) **Elixir üzərində BEAM (Erlang VM)** ilə işləyir.
- Məşhur miqyas mərhələlərinə çatdılar: *"Scaling Elixir to 5M Concurrent Users"*.
- **Nə üçün Elixir:**
  - Milyonlarla yüngül process — hər istifadəçi bağlantısı üçün bir, hər guild (server) üçün bir və s.
  - Soft real-time: səs/chat latency-ni öldürən GC pauza yoxdur.
  - Strukturlu concurrency üçün **Phoenix Channels** və **GenStage**.
  - Supervision tree ilə "Let it crash" (crash olsun) yanaşması.
- Elixir/BEAM-ə töhfə verdilər: VM-ə pull request-lər, `:ets` və ETS-əsaslı pattern-ləri sənədləşdirdilər.

### Rust — hot read path-larda Go-nu əvəz edir
- Məşhur 2020 blog: *"Why Discord is switching from Go to Rust"*.
- Problem: Go-da yazılmış "Read States" servisinin tail latency-si pis idi, çünki Go-nun GC-si hər 2 dəqiqədə bir uzun GC pauzası ilə p99 latency-ni qaldırırdı.
- Rust-da yenidən yazıldı: GC yoxdur, proqnozlaşdırılan latency, eyni və ya daha az yaddaş istifadəsi.
- Rust həmçinin client kodunda istifadə olunur (mesaj parsing, səs işlənməsi).

### Go
- Stack-in bəzi hissələrində hələ də istifadə olunur (API gateway, dəstəkləyici servislər). Əvvəllər daha çox yer tuturdu.
- Go-ya nifrət etmirlər — onu GC qiymətinin qəbul oluna biləcəyi yerlərdə istifadə edirlər.

### Python
- İlkin Discord gateway Python idi, miqyas üçün əvəz olundu.
- Hələ də istifadə olunur: ML, data emalı, daxili alətlər, avtomatlaşdırma, bot-lar.

### TypeScript / React
- Bütün web client və Electron-əsaslı desktop client.
- Mobile üçün React Native (əvvəl), performans üçün native core-lar.

### C / C++
- Səs engine-i, codec-lər, aşağı səviyyəli hissələr.

## Framework seçimləri — Nə və niyə
- **Phoenix (Elixir web framework)** HTTP + kanallar üçün.
- **GenStage / Broadway** eyni zamanda işləyən pipeline-lar üçün.
- Client-də **React + Redux** (tarixən), indi daha müasir React state pattern-ləri.
- Rust servisləri üçün: `tokio`, `hyper`, `tonic` (gRPC).
- Go servisləri üçün: standart kitabxana, `gRPC-Go`.

## Verilənlər bazası seçimləri — Nə və niyə

### Messages storage — böyük hekayə
- **Faza 1 (erkən):** MongoDB. Discord-un yazı həcmi və giriş pattern-i üçün yetərli olmadı.
- **Faza 2:** Cassandra. İllərlə işlədi. Məşhur blog *"How Discord Stores Billions of Messages"* (2017).
- **Faza 3 (2022):** **ScyllaDB**-yə köçdülər. ScyllaDB API baxımından Cassandra ilə uyğundur, lakin C++ ilə shard-per-core arxitekturası ilə yenidən yazılıb.
  - Səbəb: Cassandra-da onların miqyasında GC pauzaları və əməliyyat ağrıları var idi.
  - Məşhur blog *"How Discord Stores Trillions of Messages"*.
  - Köçürmə zamanı Rust-da data servisi (`data-service`) istifadə olundu — keçid zamanı hər iki DB backend arasında körpü rolu oynadı.

### Digər store-lar
- **PostgreSQL** — bəzi metadata, ənənəvi relational funksiyalar.
- **Redis** — hot cache-lər, rate limiting, presence köməkçiləri.
- **Elasticsearch** — axtarış.
- **Google BigQuery** — analytics.

## Proqram arxitekturası

```
   Client (Web/Desktop/Mobile)
         |
   [Edge + TLS]
         |
   [API Gateway] ---- (Go/Rust services)
         |
   [Gateway (Elixir) - WebSocket / real-time]
         |
   [Guild services (Elixir)] --- [Presence] --- [Voice (Elixir+C)]
         |
   [Message service (Rust)] --> [ScyllaDB]
   [Read-state service (Rust)] --> [Cassandra/Scylla]
   [Cache (Redis)]
   [Search (Elasticsearch)]
```

- **Guild process modeli:** hər Discord server (guild) özünü koordinasiya edən Elixir process-ə malikdir, bu process mesajları və presence-i üzvlərə yayır.
- **Böyük guild-lər** (yüz minlərlə üzv) paralel emal üçün guild-i bir neçə process arasında bölməyi tələb etdi.

### Səs arxitekturası
- Dünyanın hər yerində aşağı-latency UDP media server-ləri (istifadəçilərə yaxın).
- Siqnallaşdırma Elixir vasitəsilə.
- Səs paketləri Elixir-dən keçmir — onlar C-də yazılmış xüsusi səs server-lərinə düşür.

## İnfrastruktur və deploy
- Əsasən Google Cloud Platform.
- Servislər üçün Kubernetes + custom tooling.
- Servislər arasında gRPC-nin geniş istifadəsi.
- Observability: Prometheus, Grafana, distributed tracing.

## Arxitekturanın təkamülü (zaman xətti)

| Year | Change |
|------|--------|
| 2015 | Launch. Elixir + MongoDB. |
| 2016 | MongoDB-dən mesajları köçürdülər; Cassandra-nı qəbul etdilər. |
| 2017 | Blog: Storing Billions of Messages (Cassandra). |
| 2018–2019 | Scaling Elixir to 5M concurrent. Rust stack-ə daxil olur. |
| 2020 | Blog: Switching from Go to Rust (Read States). |
| 2022 | Blog: Trillions of Messages — Cassandra-dan ScyllaDB-yə köçürmə. |
| 2023+ | Hot path-larda daha çox Rust; çox dilli build-lər üçün daha yaxşı tooling. |

## 3-5 Əsas texniki qərarlar

1. **Birinci gündən real-time üçün Elixir.** Go və Node daha populyar olanda BEAM-a mərc etdilər. Qazanc böyük oldu.
2. **Tail-latency həssas servislər üçün Go → Rust.** Hər şeyi yenidən yazmadılar; GC pauzalarının önəmli olduğu konkret servisi yenidən yazdılar.
3. **MongoDB → Cassandra → ScyllaDB.** İki dəfə verilənlər bazasını dəyişdilər, hər dəfə konkret ağrını (yazı həcmi, tail latency) həll etmək üçün. Moda üçün deyil, ölçülə bilən qazanclar üçün dəyişdilər.
4. **Böyük server-lər üçün Guild sharding.** Tək Elixir process bir guild-də milyonlarla üzvü idarə edə bilmir; bölüblər.
5. **Səs-i chat plane-dən ayrı saxla.** Səs UDP + C-dir; chat TCP + Elixir-dir. Fərqli məhdudiyyətlər, fərqli stack-lər.

## PHP/Laravel developer üçün dərs

1. **Polyglot əsaslandırıldıqda OK-dir.** Doqmatik olmayın. Sizdə Laravel monolit + bir hot path üçün (şəkil işlənməsi, real-time yayılma) Go və ya Rust servisi ola bilər.
2. **Köçürmədən əvvəl ölç.** Discord bir servis yenidən yazdı, hamısını deyil. GC pauzalarının problem olduğunu göstərən rəqəmləri var idi. PHP developer-lər: heç vaxt "çünki microservice-dir" deyə yenidən yazmayın. p99 latency-niz pisdirsə və bunu sübut edə bilirsinizsə yenidən yazın.
3. **Verilənlər bazası seçimi bir qərardır, default deyil.** Discord giriş pattern-lərini düşündü və Mongo → Cassandra → Scylla-ya keçdi. Laravel dünyasında, layihələrin 99%-i üçün MySQL yaxşıdır — amma use case-iniz ağır yazı fanout-dursa, ScyllaDB, Cassandra, DynamoDB haqqında öyrənin.
4. **Real-time ≠ HTTP long polling.** WebSocket-ləri öyrənin, Laravel Reverb / Soketi / Pusher-in necə işlədiyini öyrənin. Discord-un realtime sistemi bağlantı idarəetməsi üzrə dərsdir.
5. **Tədricən köçürmə pattern-ləri.** Discord-un data-service-i (köçürmə zamanı həm Cassandra həm Scylla qarşısında olan Rust proxy) əla pattern-dir. Laravel tətbiqlərində köhnə DB-dən yeniyə keçəndə oxşar pattern-lər tətbiq olunur.
6. **Əksər workload-lar üçün GC-dən qorxmayın.** Discord yalnız GC-nin bottleneck olduğunu sübut edə bildiyi yerlərdə GC dillərindən uzaqlaşdı. PHP-nin yaddaş modeli onsuz da sorğu başına işləyir; nadir hallarda Go tipli GC pauza problemi olur.

## Əlavə oxu üçün (başlıqlar, URL yoxdur)
- Discord Engineering: *How Discord Stores Trillions of Messages*
- Discord Engineering: *How Discord Stores Billions of Messages*
- Discord Engineering: *Why Discord is switching from Go to Rust*
- Discord Engineering: *How Discord Scaled Elixir to 5,000,000 Concurrent Users*
- Discord Engineering: *How Discord Indexes Billions of Messages*
- Talk: *Scaling Elixir for Fun and Profit* (müxtəlif konfranslar)
- Blog: *Using Rust to Scale Elixir for 11M Concurrent Users* (Discord)
- ScyllaDB summit çıxışlarında Discord
