# Real-World Company Architecture Case Studies

Dünyanın ən böyük texnoloji şirkətlərinin arxitektura seçimləri, dilləri, framework-ləri, verilənlər bazaları və bu seçimlərin **SƏBƏBLƏRİ**. Hər case study interview-də system design sualları üçün möhkəm reference verir.

## Necə istifadə etmək

- **System design interview-ə hazırlaşarkən**: uyğun case study-ni oxu (chat app → Discord/Slack, streaming → Netflix/Spotify, e-commerce → Shopify, şəkil paylaşma → Instagram).
- **Texniki qərarları qiymətləndirərkən**: niyə FB PHP saxladı? Niyə Shopify Rails monolitində qaldı? Niyə Discord Python-dan Rust-a keçdi? Bu sənin öz qərarlarına kontekst verir.
- **Senior müsahibədə**: "We chose PostgreSQL because..." deyəndə Instagram, Notion, Reddit-in necə etdiyini müqayisə edə bilərsən.

## Kateqoriyalar

### PHP mirası və monolit filosofları (Laravel developer-ə ən uyğun)
| Şirkət | Dil | Framework | DB | Arxitektura | Niyə vacibdir |
|---------|----------|-----------|-----|--------------|----------------|
| [Meta / Facebook](meta-facebook.md) | Hack (PHP dialekti) | XHP, Hack-native | MySQL (UDB), TAO | Monolit → SOA | PHP-nin dünya səviyyəsində miqyası |
| [Wikipedia](wikipedia.md) | PHP | MediaWiki (xüsusi) | MariaDB | Monolit | Ayda 10B+ baxışda PHP |
| [Slack](slack.md) | Hack (əvvəlcə PHP) | Xüsusi | Vitess (MySQL) | Monolit → SOA | PHP→Hack köçmə hekayəsi |
| [Etsy](etsy.md) | PHP → Scala | Xüsusi | MySQL, Redis | Monolit → SOA | PHP-də deployment mədəniyyətinin öncülü |
| [Shopify](shopify.md) | Ruby | Rails ("majestic monolith") | MySQL + Vitess | Modul monolit | Monolit bir fəlsəfə kimi |
| [Basecamp / 37signals](basecamp.md) | Ruby | Rails | MySQL | Majestic Monolith | Mikroservis əleyhinə reference |
| [Stack Overflow](stackoverflow.md) | C# / .NET | ASP.NET + Dapper | SQL Server | Monolit (cəmi 9 server!) | Sadəlik qalib gəlir |
| [GitHub](github.md) | Ruby | Rails | MySQL, Git | Modul monolit | Nəhəng miqyasda Rails |
| [Tumblr](tumblr.md) | PHP | Xüsusi | MySQL | Monolit | PHP miqyaslanma dərsləri |

### Mikroservis nəhəngləri
| Şirkət | Dil | Framework | DB | Arxitektura | Qeydlər |
|---------|----------|-----------|-----|--------------|-------|
| [Netflix](netflix.md) | Java | Spring Cloud | Cassandra, EVCache | 700+ mikroservis | Chaos engineering doğum yeri |
| [Uber](uber.md) | Go, Java, Python, Node | Xüsusi (Ringpop, Fx) | Schemaless, Cassandra | 4000+ mikroservis | Polyglot ekstremal |
| [Airbnb](airbnb.md) | Ruby → Java/Kotlin | Rails → SOA | MySQL + Vitess | Rails monolit → SOA | Miqrasiya case study |
| [Amazon](amazon.md) | Java, C++, Rust | Xüsusi | DynamoDB, Aurora | Xidmət yönlü | SOA öncülü (Bezos memosu) |
| [Twitter / X](twitter.md) | Scala, Java, Rust | Finagle | Manhattan (xüsusi) | Mikroservislər | Ruby → Scala köçməsi |
| [LinkedIn](linkedin.md) | Java, Scala | Play, Rest.li | Espresso, Voldemort | Mikroservislər | Kafka-nın doğum yeri |
| [Pinterest](pinterest.md) | Python, Java | Django → xüsusi | MySQL shardlı, HBase | SOA | Nəhəng miqyasda sharding |

### Dil yenilikçiləri / polyglot
| Şirkət | Əsas dillər | Niyə | Arxitektura |
|---------|---------------|-----|--------------|
| [Instagram](instagram.md) | Python (Django), C++ | Sadəlik; lazım olan yerdə performans | Monolit + servislər |
| [WhatsApp](whatsapp.md) | Erlang | Paralel bağlantılar | Sadə, az server |
| [Discord](discord.md) | Elixir, Go, Rust, Python | Hər biri öz gücü üçün | Mikroservislər |
| [Figma](figma.md) | TypeScript, C++, Rust | WASM performans | Client-ağır |
| [Spotify](spotify.md) | Java, Python | Squad muxtariyyəti | Mikroservislər |
| [Dropbox](dropbox.md) | Python → Go | Performans miqrasiyası | Xidmət yönlü |
| [Notion](notion.md) | TypeScript (Next.js) | Full-stack TS | Monolit + worker-lər |
| [Reddit](reddit.md) | Python | Başlanğıc hekayəsi | Monolit + servislər |
| [Booking.com](booking.md) | **Perl** (hələ də!) | Hype yerinə praqmatizm | Monolit + servislər |
| [Zalando](zalando.md) | Scala, Java, Python | Komanda muxtariyyəti | Mikroservislər (1000+) |

## Müqayisə: "Hansı DB nəyə üçün?"

| İstifadə halı | Ümumi seçim | Kim istifadə edir |
|----------|---------------|-------------|
| Tranzaksional (əsas biznes) | **MySQL** | Facebook, Shopify, GitHub, Uber, Wikipedia |
| Tranzaksional (PG üstün tutulur) | **PostgreSQL** | Instagram, Reddit, Notion |
| Geniş sütun / zaman seriyası yazma-ağır | **Cassandra** | Netflix, Instagram (inbox), Discord (ScyllaDB vasitəsilə), Uber |
| Key-value cache | **Redis** / **Memcached** | Hamı; FB-də Memcached (orijinal), Twitter-də Redis, GitHub |
| Axtarış | **Elasticsearch** | GitHub, Wikipedia, Uber, Slack |
| Analitika | **Presto / Trino**, **BigQuery** | Airbnb, Netflix, Spotify |
| Event log / streaming | **Kafka** | LinkedIn (ixtira edən), Netflix, Uber, Slack |
| Qraf | **Xüsusi** (TAO, ZippyDB, Dgraph) | Facebook, LinkedIn |

## Müqayisə: "Monolit vs Mikroservislər — kim nəyi seçdi?"

| Pattern | Şirkətlər | Səbəb |
|---------|-----------|-----------|
| **Majestic monolit** | Basecamp, Shopify, GitHub, Stack Overflow | Daha az hissə; sürətli iterasiya; kiçik komandanın gücü |
| **Modul monolit** | Shopify (komponentlər), Wikipedia | Paylanmış ağrı olmadan sərhədlər |
| **Xidmət yönlü (SOA)** | Amazon, Airbnb (miqrasiyadan sonra) | Tam mikroservis vergisi olmadan komanda sahibliyi |
| **Mikroservislər** | Netflix, Uber, Spotify | Nəhəng təşkilatda komanda muxtariyyəti; müstəqil deploy |
| **Cell-based** | Slack, Netflix (bəzi yollar) | Partlayış radiusunu azaltmaq |

## Müqayisə: "Framework seçimini nəyin təyin etdiyi"

| Framework | Seçilmə səbəbi | Kim |
|-----------|---------------|-----|
| **Rails** | Developer sürəti, konvensiya | GitHub, Shopify, Basecamp, Airbnb (ilkin) |
| **Django** | Sürətli dev + Python ML ekosistemi | Instagram, Pinterest, Reddit |
| **Spring (Java)** | Enterprise yetkinlik, ekosistem | Netflix, LinkedIn, Uber (hissə) |
| **Express/Node** | Komanda JS-i hər iki tərəfdə istifadə edir | Uber API gateway, Netflix edge |
| **Laravel / Symfony** | Sürətli dev + PHP ekosistemi | Bir çox SME; böyük tech-də deyil (böyük tech PHP-də Hack istifadə edir) |
| **Phoenix (Elixir)** | Miqyasda soft real-time | Discord (bəzi hissələr), Bleacher Report |
| **Go stdlib + kitabxanaları** | Az resurs, paralel | Uber (hissə), Dropbox Magic Pocket, Cloudflare |

## Arxitektura təkamülü pattern-ləri (ümumi hekayə arkları)

Demək olar ki, hər case bu hekayələrdən birinə uyğundur:

1. **"Sadə başladıq, sadə qaldıq"** — Stack Overflow, Basecamp, Wikipedia
2. **"Monolitdən SOA-ya böyüdük"** — Amazon, Airbnb, Shopify (qismən)
3. **"Daha sürətli dildə yenidən yazdıq"** — Twitter (Ruby→Scala), Dropbox (Py→Go), Discord (Py→Rust)
4. **"Orijinal dildə qaldıq, öz runtime-ımızı qurduq"** — Facebook (PHP→Hack+HHVM)
5. **"İlk gündən polyglot"** — Uber, Netflix

## Müsahibələrin üçün

"How would you design X?" soruşulanda:
1. Oxşar problemi həll edən bir şirkəti söylə.
2. Onların məhdudiyyətlərini və seçimlərini izah et.
3. SƏNİN məhdudiyyətlərini (fərqli ola bilər) və seçimlərini söylə.
4. Hype ilə yox, trade-off-larla müdafiə et.

Nümunə cavab template:
> "For a notifications system, I'd start with the approach LinkedIn uses — Kafka as the event log, Samza/workers for fan-out, because our read:write ratio is 100:1 and we need replay. But unlike LinkedIn, we're at much smaller scale, so I'd skip Samza and use a simple Laravel queue with Redis first, and plan the Kafka migration for when we hit ~X events/sec."

Bu göstərir ki, sən reference-i bilirsən VƏ onu adapt edə bilirsən.

## İndeks

1. [Meta / Facebook](meta-facebook.md)
2. [Wikipedia](wikipedia.md)
3. [Slack](slack.md)
4. [Etsy](etsy.md)
5. [Shopify](shopify.md)
6. [Basecamp / 37signals](basecamp.md)
7. [Stack Overflow](stackoverflow.md)
8. [GitHub](github.md)
9. [Tumblr](tumblr.md)
10. [Netflix](netflix.md)
11. [Uber](uber.md)
12. [Airbnb](airbnb.md)
13. [Amazon](amazon.md)
14. [Twitter / X](twitter.md)
15. [LinkedIn](linkedin.md)
16. [Pinterest](pinterest.md)
17. [Instagram](instagram.md)
18. [WhatsApp](whatsapp.md)
19. [Discord](discord.md)
20. [Figma](figma.md)
21. [Spotify](spotify.md)
22. [Dropbox](dropbox.md)
23. [Notion](notion.md)
24. [Reddit](reddit.md)
25. [Booking.com](booking.md)
26. [Zalando](zalando.md)
27. [Şirkətlər arası dərslər](lessons-learned.md)
