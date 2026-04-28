# Real-World Company Architecture Case Studies

Dünyanın ən böyük texnoloji şirkətlərinin arxitektura seçimləri, dilləri, framework-ləri, verilənlər bazaları və bu seçimlərin **SƏBƏBLƏRİ**. Hər case study system design interview-ləri üçün möhkəm reference verir.

## Necə istifadə etmək

- **System design interview-ə hazırlaşarkən**: uyğun case study-ni oxu — chat app → Discord/Slack, streaming → Netflix/Spotify, e-commerce → Shopify, şəkil paylaşma → Instagram.
- **Texniki qərarları qiymətləndirərkən**: niyə FB PHP saxladı? Niyə Shopify Rails monolitində qaldı? Niyə Discord Python-dan Rust-a keçdi? Bu sənin öz qərarlarına kontekst verir.
- **Senior müsahibədə**: "We chose PostgreSQL because..." deyəndə Instagram, Notion, Reddit-in necə etdiyini müqayisə edə bilərsən.
- **Müsahibədə istinad etmək üçün**: hər faylda "Müsahibədə necə istinad etmək" bölməsi konkret cavab şablonları verir.

---

## ⭐⭐⭐ Senior — Monolit filosofları

Sadəlik prinsipini sübut edən şirkətlər. Stack Overflow cəmi 9 server ilə milyonlara xidmət edir; Booking.com 2024-cü ildə hələ də Perl işlədir.

| # | Şirkət | Dil | Arxitektura | Niyə vacibdir |
|---|---------|-----|-------------|----------------|
| [01](01-basecamp.md) | Basecamp / 37signals | Ruby on Rails | Majestic Monolith | Mikroservis əleyhinə ən güclü argument |
| [02](02-stackoverflow.md) | Stack Overflow | C# / .NET | Monolit (9 server) | Sadəliyin qalib gəldiyi ən məşhur nümunə |
| [03](03-wikipedia.md) | Wikipedia | PHP + MediaWiki | Monolit | Ayda 10B+ baxışda PHP |
| [04](04-tumblr.md) | Tumblr | PHP | Monolit | PHP miqyaslanma dərsləri |
| [05](05-notion.md) | Notion | TypeScript (Next.js) | Monolit + worker-lər | Full-stack TS monolit |
| [06](06-booking.md) | Booking.com | **Perl** (hələ də!) | Monolit + servislər | Hype yerinə praqmatizm |
| [07](07-whatsapp.md) | WhatsApp | Erlang | Sadə, az server | 900M istifadəçi, 50 engineer |

---

## ⭐⭐⭐⭐ Lead — Miqyaslanma qərarları

Maraqlı texniki qərarlar verən, böyümə zamanı kritik seçimlər etmiş şirkətlər.

| # | Şirkət | Dil | Arxitektura | Niyə vacibdir |
|---|---------|-----|-------------|----------------|
| [08](08-instagram.md) | Instagram | Python (Django) | Monolit + servislər | Sadəlik + performans |
| [09](09-reddit.md) | Reddit | Python | Monolit + servislər | 20 ildə Python sadiqliyi |
| [10](10-shopify.md) | Shopify | Ruby on Rails | Modul monolit | Rails-i "majestic monolith" anlayışına qovuşduran |
| [11](11-github.md) | GitHub | Ruby on Rails | Modul monolit | Nəhəng miqyasda Rails |
| [12](12-etsy.md) | Etsy | PHP → Scala | Monolit → SOA | Deployment mədəniyyəti öncülü |
| [13](13-slack.md) | Slack | PHP → Hack | Monolit → SOA | PHP→Hack köçmə hekayəsi |
| [14](14-meta-facebook.md) | Meta / Facebook | Hack (PHP dialekti) | Monolit → SOA | PHP-nin dünya miqyaslı nümunəsi |
| [15](15-twitter.md) | Twitter / X | Ruby → Scala | Mikroservislər | Ruby→Scala köçməsi |
| [16](16-stripe.md) | Stripe | Ruby + Sorbet | Monolit + servislər | API dizayn mədəniyyəti, idempotency |
| [17](17-discord.md) | Discord | Elixir, Go, Rust | Mikroservislər | Hər dil öz gücü üçün |
| [18](18-figma.md) | Figma | TypeScript + C++/Rust | Client-ağır | WASM performans |
| [19](19-spotify.md) | Spotify | Java, Python | Mikroservislər | Squad muxtariyyəti |
| [20](20-dropbox.md) | Dropbox | Python → Go | Xidmət yönlü | Performans miqrasiyası |
| [21](21-atlassian.md) | Atlassian | Java, Kotlin | Monolit → Cloud SaaS | Server → Cloud miqrasiyası, B2B SaaS |
| [22](22-zalando.md) | Zalando | Scala, Java | Mikroservislər (1000+) | Komanda muxtariyyəti |
| [35](35-snapchat.md) | Snap / Snapchat | Go, C++, Python | Monolit → mikroservislər | Stories ixtiraçısı, ephemeral dizayn, AR at scale |
| [37](37-lyft.md) | Lyft | Python, Go | Monolit → SOA | Uber ilə müqayisəli; Envoy + Feast ixtiraçısı |

---

## ⭐⭐⭐⭐⭐ Architect — Distributed systems nəhəngləri

Extreme-scale distributed sistemlər. System design interview-lərinin "reference əsərlər"i.

| # | Şirkət | Dil | Arxitektura | Niyə vacibdir |
|---|---------|-----|-------------|----------------|
| [23](23-amazon.md) | Amazon | Java, C++ | SOA | SOA öncülü (Bezos memosu) |
| [24](24-netflix.md) | Netflix | Java + Spring Cloud | 700+ mikroservis | Chaos engineering doğum yeri |
| [25](25-uber.md) | Uber | Go, Java, Python | 4000+ mikroservis | Polyglot ekstremal |
| [26](26-airbnb.md) | Airbnb | Ruby → Java/Kotlin | Rails monolit → SOA | Miqrasiya case study |
| [27](27-linkedin.md) | LinkedIn | Java, Scala | Mikroservislər | **Kafka-nın doğum yeri** |
| [28](28-pinterest.md) | Pinterest | Python, Java | SOA + sharding | Nəhəng miqyasda MySQL sharding |
| [29](29-doordash.md) | DoorDash | Python → Kotlin | Monolit → mikroservislər | Python→Kotlin miqrasiyası |
| [30](30-cloudflare.md) | Cloudflare | Go, Rust, C | Anycast + edge | Pingora (Rust), Workers (V8 isolates) |
| [31](31-tiktok.md) | TikTok / ByteDance | Go + Python (ML) | Mikroservislər + ML platform | Rec engine at scale |
| [32](32-twitch.md) | Twitch | Go, Rust, Erlang | Cell-based | Millions concurrent chat |
| [33](33-google.md) | Google | C++, Java, Go | Monorepo + mikroservislər | GFS, MapReduce, Spanner öncülü |
| [36](36-microsoft.md) | Microsoft | C#, TypeScript, Go | Cloud + developer platform | .NET Core, Azure, Teams, TypeScript ixtiraçısı |

---

## Reading Paths

### PHP/Laravel developer üçün — Interview hazırlığı

Sıra: **02 → 03 → 01 → 06 → 10 → 12 → 13 → 14 → 34**

*Stack Overflow sadəliyi → Wikipedia PHP-si → Basecamp fəlsəfəsi → Booking praqmatizmi → Shopify Rails-at-scale → Etsy PHP miqrasiyası → Slack PHP hekayəsi → Meta Hack → Lessons*

### Monolit → Mikroservis miqrasiya path

Sıra: **10 → 35 → 26 → 29 → 37 → 25 → 34**

*Shopify (qalmaq qərarı) → Snap (PHP→Go, ephemeral-first) → Airbnb (miqrasiya) → DoorDash (miqrasiya) → Lyft (konservativ polyglot) → Uber (ekstremal) → Lessons*

### System Design Interview — Core path

Sıra: **24 → 27 → 25 → 23 → 33**

*Netflix (microservices) → LinkedIn (event streaming) → Uber (scale) → Amazon (SOA) → Google (distributed systems)*

### Dil miqrasiyası case study-ləri

Sıra: **15 → 20 → 29 → 17 → 35 → 34**

*Twitter Ruby→Scala → Dropbox Python→Go → DoorDash Python→Kotlin → Discord Go→Rust → Snap PHP→Go → Lessons*

### B2B SaaS + Cloud architecture

Sıra: **21 → 36 → 30 → 22 → 34**

*Atlassian Server→Cloud → Microsoft Azure + Teams → Cloudflare edge → Zalando microservices → Lessons*

### Ride-sharing + Real-time matching

Sıra: **37 → 29 → 25 → 34**

*Lyft (Python+Go, Envoy, Feast, Redis Geospatial dispatch) → DoorDash (OR-tools MIP, Temporal workflows) → Uber (polyglot, H3, Cadence) → Lessons (dispatch + ML patterns)*

### Ephemeral design + Consumer scale

Sıra: **07 → 35 → 08 → 17 → 32 → 34**

*WhatsApp (mesajları sil) → Snap (TTL-based, Stories, AR) → Instagram (feed, Stories at Meta) → Discord (chat clusters) → Twitch (live streaming) → Lessons*

### Developer platform + Enterprise tools

Sıra: **11 → 36 → 21 → 27 → 34**

*GitHub (Rails monolith, Spokes, Blackbird) → Microsoft (Azure, TypeScript, Teams) → Atlassian (B2B SaaS migration) → LinkedIn (Kafka ixtirası) → Lessons*

### ML/AI platform arxitekturası

Sıra: **31 → 35 → 37 → 29 → 34**

*TikTok (Monolith rec engine, real-time online learning) → Snap (SnapML on-device, Spotlight feed) → Lyft (Feast feature store, dispatch optimization) → DoorDash (OR-tools MIP, Temporal workflows) → Lessons (ML platform patterns)*

---

## Müqayisə cədvəlləri

### "Hansı DB nəyə üçün?"

| İstifadə halı | Ümumi seçim | Kim istifadə edir |
|--------------|-------------|-------------------|
| Tranzaksional (əsas biznes) | **MySQL** | Facebook, Shopify, GitHub, Uber, Wikipedia |
| Tranzaksional (PG üstün tutulur) | **PostgreSQL** | Instagram, Reddit, Notion, Atlassian Cloud |
| Tranzaksional (qlobal, multi-region) | **Google Spanner** | Snap, Google internal |
| Geniş sütun / yazma-ağır | **Cassandra** | Netflix, Discord (ScyllaDB), Uber |
| Key-value cache | **Redis / Memcached** | Hamı; FB Memcached, Twitter Redis |
| Axtarış | **Elasticsearch** | GitHub, Wikipedia, Uber, Slack, Atlassian |
| Analitika | **Presto / Trino, BigQuery** | Airbnb, Netflix, Spotify |
| Event log / streaming | **Kafka** | LinkedIn (ixtira edən), Netflix, Uber, Slack, Lyft |
| Qraf | **Xüsusi** (TAO, ZippyDB) | Facebook, LinkedIn |
| Enterprise managed SQL | **Azure SQL / Cosmos DB** | Microsoft (Teams, M365) |
| ML Feature Store | **Feast** (açıq mənbə, Lyft yaratdı) | Lyft, Uber, Shopify, Twitter |
| HTAP (OLTP + OLAP bir DB-də) | **TiDB** (MySQL-uyğun, horizontal scale) | TikTok/ByteDance (ən böyük istifadəçi), PingCAP |
| Qlobal qeyri-ephemeral storage | **Google Cloud Storage + TTL** | Snap (media, Stories 24h TTL) |

### "Monolit vs Mikroservislər — kim nəyi seçdi?"

| Pattern | Şirkətlər | Səbəb |
|---------|-----------|-------|
| **Majestic monolit** | Basecamp, Stack Overflow, Wikipedia | Sürətli iterasiya; kiçik komanda gücü |
| **Modul monolit** | Shopify, GitHub, Atlassian (əvvəl) | Paylanmış ağrı olmadan sərhədlər |
| **SOA** | Amazon, Airbnb (miqrasiyadan sonra), Lyft | Komanda sahibliyi; tam mikroservis vergisi olmadan |
| **Mikroservislər** | Netflix, Uber, Spotify, Snap, TikTok | Nəhəng təşkilatda komanda muxtariyyəti |
| **Cell-based** | Slack, Twitch | Partlayış radiusunu azaltmaq; blast radius məhdudlaşdırmaq |
| **Monorepo + mikroservislər** | Google (Piper/Blaze), Meta | Bir repo, milyardlarla sətir — cross-team refactor asan |
| **Edge-first (heç vaxt mərkəzi region yoxdur)** | Cloudflare (Anycast), TikTok | Global PoP-lar, user ən yaxın node-a gedir |
| **Cloud-native platform** | Microsoft (Azure, Teams) | Managed services + developer ecosystem |

### "Framework seçimini nəyin təyin etdiyi"

| Framework | Seçilmə səbəbi | Kim |
|-----------|----------------|-----|
| **Rails** | Developer sürəti, konvensiya | GitHub, Shopify, Basecamp, Airbnb (ilkin) |
| **Django** | Sürətli dev + Python ML ekosistemi | Instagram, Pinterest, Reddit |
| **Spring (Java)** | Enterprise yetkinlik | Netflix, LinkedIn, Atlassian |
| **Laravel / Symfony** | Sürətli dev + PHP ekosistemi | Çox SME; böyük tech PHP-ni Hack-ə köçürdü |
| **Phoenix (Elixir)** | Miqyasda soft real-time | Discord (bəzi hissələr) |
| **Go stdlib** | Az resurs, paralellik | Uber (hissə), Dropbox Magic Pocket, Cloudflare, Lyft (dispatch) |
| **ASP.NET Core** | Enterprise yetkinlik + cross-platform performans | Microsoft (Teams, Azure) |
| **C++ (Emscripten/WASM)** | Max performance: AR, codec, rendering | Snap Lenses (AR engine), Figma (WebGL), Cloudflare Quiche |
| **Erlang/OTP + Ejabberd** | Soft real-time, milyonlarla concurrent bağlantı | WhatsApp (saxlandı), Twitch (köçürdü), Discord (başlanğıc) |
| **Kitex / Hertz (Go)** | Yüksək performanslı Go RPC/HTTP, ByteDance yaratdı | TikTok (ByteDance), CloudWeGo topluluğu |

### "Arxitektura təkamülü pattern-ləri"

1. **"Sadə başladıq, sadə qaldıq"** — Stack Overflow, Basecamp, Wikipedia
2. **"Monolitdən SOA-ya böyüdük"** — Amazon, Airbnb, Shopify (qismən)
3. **"Daha sürətli dildə yenidən yazdıq"** — Twitter (Ruby→Scala), Dropbox (Py→Go), Discord (Py→Rust)
4. **"Orijinal dildə qaldıq, öz runtime-ımızı qurduq"** — Facebook (PHP→Hack+HHVM)
5. **"İlk gündən polyglot"** — Uber, Netflix
6. **"Server → Cloud miqrasiyası"** — Atlassian (on illik yol)
7. **"Tam cloud-a committed (vendor lock-in qəbul edildi)"** — Snap (100% GCP)
8. **"Desktop/Enterprise → Cloud-first pivotu"** — Microsoft (Nadella dövrü, 2014)
9. **"Konservativ polyglot: 2 dil, bəsdir"** — Lyft (Python + Go, digər dillərə yox deməyi bilmək)
10. **"ML-first arxitektura: recommendation engine = core product"** — TikTok (Monolith, real-time online learning), Snap (SnapML, on-device), Lyft (Feast feature store), DoorDash (OR-tools dispatch)
11. **"Öz infrastruktur alətini yaratdıq, açıq mənbəyə çevirdik"** — Lyft (Envoy, Feast, Flyte), LinkedIn (Kafka, Samza, Pinot), Uber (Jaeger, M3, Cadence), Zalando (Patroni, Spilo)

---

## Müsahibədə istinad etmək

"How would you design X?" soruşulanda:

1. Oxşar problemi həll edən bir şirkəti söylə.
2. Onların məhdudiyyətlərini və seçimlərini izah et.
3. SƏNİN məhdudiyyətlərini (fərqli ola bilər) və seçimlərini söylə.
4. Hype ilə yox, trade-off-larla müdafiə et.

Nümunə cavab template:
> "For a notifications system, I'd start with the approach LinkedIn uses — Kafka as the event log, workers for fan-out, because our read:write ratio is 100:1 and we need replay. But unlike LinkedIn, we're at much smaller scale, so I'd skip Kafka and use a simple Laravel queue with Redis first, and plan the Kafka migration for when we hit ~X events/sec."

Bu göstərir ki, sən reference-i bilirsən VƏ onu adapt edə bilərsən.

---

## İndeks

### Senior ⭐⭐⭐
1. [Basecamp](01-basecamp.md)
2. [Stack Overflow](02-stackoverflow.md)
3. [Wikipedia](03-wikipedia.md)
4. [Tumblr](04-tumblr.md)
5. [Notion](05-notion.md)
6. [Booking.com](06-booking.md)
7. [WhatsApp](07-whatsapp.md)

### Lead ⭐⭐⭐⭐
8. [Instagram](08-instagram.md)
9. [Reddit](09-reddit.md)
10. [Shopify](10-shopify.md)
11. [GitHub](11-github.md)
12. [Etsy](12-etsy.md)
13. [Slack](13-slack.md)
14. [Meta / Facebook](14-meta-facebook.md)
15. [Twitter / X](15-twitter.md)
16. [Stripe](16-stripe.md)
17. [Discord](17-discord.md)
18. [Figma](18-figma.md)
19. [Spotify](19-spotify.md)
20. [Dropbox](20-dropbox.md)
21. [Atlassian](21-atlassian.md)
22. [Zalando](22-zalando.md)
35. [Snap / Snapchat](35-snapchat.md)
37. [Lyft](37-lyft.md)

### Architect ⭐⭐⭐⭐⭐
23. [Amazon](23-amazon.md)
24. [Netflix](24-netflix.md)
25. [Uber](25-uber.md)
26. [Airbnb](26-airbnb.md)
27. [LinkedIn](27-linkedin.md)
28. [Pinterest](28-pinterest.md)
29. [DoorDash](29-doordash.md)
30. [Cloudflare](30-cloudflare.md)
31. [TikTok / ByteDance](31-tiktok.md)
32. [Twitch](32-twitch.md)
33. [Google](33-google.md)
36. [Microsoft](36-microsoft.md)

### Sintez
34. [Şirkətlər arası dərslər](34-lessons-learned.md)
