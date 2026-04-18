# Öyrənilmiş dərslər — Şirkətlərarası nümunələr

Bu fayl Instagram, WhatsApp, Discord, Figma, Spotify, Dropbox, Notion, Reddit, Booking, Zalando, plus Facebook, Twitter, Uber, Netflix və GitHub-dan kontekst ilə görülmüş nümunələri distillasiya edir. Beynəlxalq müsahibələrə hazırlaşan senior PHP/Laravel developer üçün yazılıb.

---

## 1. Dil miqrasiya nümunələri

### Şirkətlər niyə dilləri köçürür
Demək olar ki, heç bir şirkət "yeni dil havalıdır" deyə köçürmür. Onlar **spesifik, ölçülə bilən ağrıya** görə köçürürlər.

| Şirkət | Nədən | Nəyə | Əsl səbəb |
|---------|------|-----|-------------|
| Twitter | Ruby on Rails | Scala + JVM | Ruby MRI global interpreter lock; GC pauzaları; fanout altında yavaş request idarəetməsi |
| Dropbox | Python | Go (infra üçün) | Python şəbəkə/storage sistemləri üçün çox yavaş; Go konkurrentliyi + statik binary-lər |
| Discord | Go | Rust (bir servis) | Spesifik isti servisdə Go GC pauzaları p99 tail gecikməsinə zərər verdi |
| Instagram | Python 2 | Python 3 (eyni dil, yeni) | Python 2 dəstəyinin sonu; performans; daha yaxşı tiplənmə |
| Shopify | Ruby | Ruby (saxlandı) + Rust (bəzi servislər) | Köçürmədi; başqa bir alət əlavə etdi |
| Reddit | Common Lisp | Python | Web üçün kitabxana ekosistemi arıq idi |
| WhatsApp | (başladığı) ejabberd/Erlang | Erlang saxlandı | Konkurrentlik üçün düzgün dil — tərk etməyə ehtiyac yoxdur |
| Facebook | PHP | HHVM / Hack | PHP-ni tərk etməyə çalışdı (C++-a, tərk edildi), sonra öz kompilyatoru/dilini qurdu |

### Nümunə
1. **Hype-a görə köçürməyin.** Hazırkı yığının darboğaz olduğunu göstərən rəqəmləriniz olduğuna görə köçürün.
2. **Hər şeyi deyil, bir servisi köçürün.** Discord bütün yığını Rust-da yenidən yazmadı.
3. **Çox vaxt cavab "eyni dil, optimallaşdırılmış"dır.** Instagram-ın Cinder-i, Facebook-un HHVM-i, Shopify-in YJIT-i.
4. **Komanda xərci haqqında dürüst olun.** Miqrasiyalar aylarla/illərlə senior eng vaxtı yeyir.

### Miqrasiyanın lazım olduğunu göstərən siqnallar
- Profiling ilə sübut edilmiş, GC-nin səbəb olduğu p99 gecikmə.
- Dil runtime miqyasda çökür və upstream düzəltmir.
- Dildə daha çox işə götürə bilmirsiniz.
- Spesifik paradiqma (konkurrentlik, memory safety) hazırkı dilin təklif edə bilmədiyi.

### Miqrasiyanın lazım OLMADIĞINI göstərən siqnallar
- "Yeni dil mikrobenchmark-larda daha sürətlidir."
- "X şirkətinin Y-də yenidən yazdığı blog oxudum."
- "Recruiter-lər Rust-ın isti olduğunu deyir."
- "Monolitimiz üzərində işləmək çətindir." — bu kod keyfiyyəti problemidir, dil problemi deyil.

---

## 2. Monolit vs Microservice qərar matrisası

### Real nümunələr

**İşləməyə davam edən böyük monolitlər:**
- Instagram (Django, 2B istifadəçi)
- Facebook (Hack, milyardlarla istifadəçi)
- Shopify (Rails, nəhəng GMV)
- GitHub (Rails, nəhəng miqyas)
- Stack Overflow (.NET, klassik monolit, qutu başına çox yüksək performans)
- Wikipedia (PHP monolit)
- Basecamp (Rails monolit, məşhur "Majestic Monolith")
- Reddit (Python, ABŞ top-10 trafiki)
- Booking.com (Perl monolit, onlarla milyon sətir)

**Məşhur microservice köçürənlər:**
- Uber (minlərlə servis)
- Netflix (çoxlu microservice, service mesh)
- Amazon (~2002-dən bəri servis-yönlü)
- Spotify (~1000+ servis)
- Zalando (~1000+ servis)
- Discord (poliqlot servislər)

**Monolitdən-microservice-ə tərsinə çevrilmələr:**
- **Amazon Prime Video** (2023): məşhur olaraq video monitorinq servisini microservice-lərdən monolitə GERİ köçürdü və infrastruktur xərclərinə 90% qənaət etdi.
- Bir neçə Uber servisi konsolidasiya olundu (daha az ictimai, amma bilinir).
- "Deathstar"-tipli servis qrafları yalnız bəzi servislərin yenidən birləşdirilməsi üçün parçalanır.

### Qərar matrisası

| Meyar | Monolit | Microservices |
|-----------|----------|---------------|
| Komanda ölçüsü < 20 | Güclü bəli | Demək olar ki, heç vaxt |
| Komanda ölçüsü 20–100 | Hələ yaxşı | Aydın sərhədlər üçün bəlkə |
| Komanda ölçüsü 100+ | Modullaşdırma lazımdır | Çox vaxt əsaslandırılır |
| Müstəqil deploy tələb olunur | - | Bəli |
| Paylaşılan DB schema-sı | Bəli | Adətən yox |
| Sadə domen | Monolit | Çox |
| Heterogen runtime ehtiyacları (ML, real-time, CRUD) | Hibrid: monolit + bir neçə servis | Tam SOA |
| Ops yetkinliyi aşağı | Yalnız monolit | Qaçın |
| Xərc-həssas | Monolit | Bahalı |

### Baş qaydası
> "Əvvəlcə monolit qurun. Servisi yalnız real, sübut olunmuş ağrı nöqtəsi tələb etdikdə çıxarın: müstəqil miqyaslanma, müstəqil deploy, fərqli tech stack və ya komanda sahiblik sərhədi."

Bu Sam Newman, Martin Fowler, DHH və Prime Video post-mortem-i ilə uyğun gəlir.

### Tipik təkamül mərhələləri
1. **Bir DB-də monolit.** Laravel MySQL-də, bir qutuda və ya kiçik HA quraşdırmasında.
2. **Read replika-lar əlavə edin** reportinq və ağır oxumalar üçün.
3. **Cache qatı əlavə edin** (Redis / Memcached) isti oxumalar üçün.
4. **Queue worker-lər əlavə edin** async job-lar üçün (mail, şəkil işlənməsi, webhook-lar).
5. **DB-ni shardlayın** yalnız yazma ötürücülüyü darboğaz olduqda.
6. **Servisləri çıxarın** spesifik isti nöqtələr üçün (real-time, media, axtarış).
7. **Tam SOA / microservices** yalnız çox böyük komanda + biznes ölçüsündə.

Əksər Laravel dükanları 1–4 mərhələlərində xoşbəxt yaşayır.

---

## 3. Verilənlər bazası seçimi nümunələri

### Giriş nümunəsi üzrə

| Ehtiyac | Qalib | Niyə |
|------|--------|-----|
| Transaksional e-kommersiya, sifarişlər, ödənişlər | **MySQL** (və ya Postgres) | Yetkin transaksional semantika; miqyasda tanınır |
| Mürəkkəb sorğular, JSONB, extension-lar | **PostgreSQL** | Daha zəngin SQL, qarışıq iş yükləri üçün daha yaxşı |
| Yazma-yüklü time-series, hadisələr | **Cassandra / ScyllaDB** | Horizontal miqyas, geniş-sütun, yazma-optimallaşdırılmış |
| İsti cache | **Redis / Memcached** | In-memory, mikrosaniyə oxumaları |
| Full-text axtarış | **Elasticsearch / Meilisearch / Typesense** | Inverted index, relevance scoring |
| Analitika / anbar | **BigQuery / Snowflake / ClickHouse** | Columnar, scan-yüklü |
| Qraf datası | **Neo4j / Dgraph** (və ya sadə hallar üçün Postgres recursive CTE) | Qraf traversalı |
| Blob-lar (şəkillər, fayllar) | **S3 / GCS / MinIO** | Ucuz, dayanıqlı; heç vaxt əsas DB deyil |

### Real şirkətlər üzrə

| Şirkət | Əsas DB | Niyə |
|---------|-----------|-----|
| Instagram | PostgreSQL (shardlanıb) | Snowflake ID-ləri, məntiqi shard-lar |
| WhatsApp | Mnesia + istifadəçi başına fayllar | Sadə mesajlaşma domen-i |
| Discord | ScyllaDB (mesajlar) | Ex-Cassandra, tail gecikməsi üçün köçdü |
| Figma | PostgreSQL (shardlanıb) | Ənənəvi metadata |
| Spotify | Cassandra + Postgres + BigQuery | Qarışıq |
| Dropbox | MySQL (shardlanıb) + Edgestore + Magic Pocket | Metadata vs blob-lar |
| Notion | PostgreSQL (minlərlə shard) | Sətir başına blok + workspace shardlama |
| Reddit | PostgreSQL + Cassandra | Klassik cütləşmə |
| Booking | MySQL (petabayt miqyası) | Darıxdırıcı + yetkin |
| Zalando | PostgreSQL (Patroni/Spilo) | Postgres-i sevirlər |

### Nümunələr
- **Hər böyük şirkət nəhayət shardlayır.** Tək sual nə vaxtdır.
- **Təbii sərhəddə shardlayın** (istifadəçi id, workspace id, subreddit, kirayəçi).
- **Fiziki deyil, məntiqi shard-lar.** Notion ~480 məntiqi shard ilə başladı ki, daha az fiziki host-da yaşayır. ID-ləri dəyişmədən sonra yenidən balanslaşdırmaya imkan verir.
- **Blob-ları metadata-dan ayırın.** RDBMS-də metadata; obyekt storage-ində blob-lar. Dropbox-un Edgestore + Magic Pocket-i kanonik nümunədir.
- **Shardlamadan əvvəl read replika-lar.** Bir çox app bir primary + read replika-lar + caching ilə çox irəli gedir.
- **İkinci giriş nümunəsi üçün ikinci DB yaxşıdır.** Cache üçün Redis, axtarış üçün Elasticsearch, plus transaksiyalar üçün MySQL normal quraşdırmadır.

---

## 4. Öz-ünü qur vs OSS mənimsə

### Məşhur "öz-lərini qurdular" halları
| Şirkət | Qurdu | Səbəb |
|---------|-------|--------|
| Facebook | **HHVM, Hack** | PHP-ni miqyasda sürətləndirmək lazım idi |
| Facebook | **Cassandra** (açıq mənbədən əvvəl) | İnbox üçün uyğun DB yox idi |
| Facebook | **TAO, Haystack** | FB miqyasında qraf + şəkil store |
| LinkedIn | **Kafka** (sonra açıq mənbəyə çevrildi) | Mövcud queue-lar uyğun gəlmirdi |
| LinkedIn | **Samza, Voldemort** | Spesifik ehtiyaclar |
| Netflix | **Hystrix, Eureka, Zuul** | Istio-dan əvvəl service mesh |
| Netflix | **Chaos Monkey** | Resilience testing mədəniyyəti |
| Uber | **Schemaless, M3, Jaeger** | Daxili ehtiyaclar, sonra açıq mənbəyə çevrildi |
| Google | **Spanner, Bigtable, MapReduce** | Miqyas və problem forması |
| Dropbox | **Magic Pocket, Edgestore, Bandaid** | AWS-dən çıxdı; öz qatı lazım idi |
| Spotify | **Luigi, Backstage** | Workflow-lar, dev portal |
| Zalando | **Patroni, Spilo** | Postgres HA |
| Instagram | **Cinder** | Daha sürətli Python runtime |

### Nümunə: Niyə qurmaq?
1. **OSS-in vurmadığı miqyas.** Cassandra qurulduğu üçün FB inbox miqyasında heç nə yox idi.
2. **Spesifik xərc xətti.** Dropbox-un Magic Pocket-i S3-ə qarşı ildə ~$75M qənaət etdi — rasional.
3. **İşə götürmə / tərəfdar cəlb etmə.** OSS layihələri istedadı cəlb edir.
4. **Əsas şeyin bazara çıxma vaxtı.** Paylanmış tracing sistemi qurmaq Uber-in işi deyildi, amma 2014-də yaxşı heç nə yox idi, ona görə Jaeger qurdular.

### Nə vaxt qurmamalı
- Hazır alətlər ehtiyaclarınızı 20% daxilində ödəyir (Pareto).
- FAANG miqyasında deyilsiniz.
- Qurulan şeyi illərlə saxlamaq üçün 10+ mühəndisiniz yoxdur.
- Rəqabət edən OSS seçimini hələ deploy etməmisiniz.

### Anti-nümunə: NIH (Not Invented Here)
- Eloquent/Prisma mövcud olduqda xüsusi ORM qurmaq.
- Laravel Horizon və ya Sidekiq bunu edəndə öz queue sisteminizi yazmaq.
- LaunchDarkly / Unleash / Flipper işləyəndə öz feature flag sisteminizi yazmaq.
- Patroni istifadə etmək əvəzinə öz Postgres HA-nı yazmaq.

**Qayda:** dünyaca məşhur OSS aləti ehtiyacınızın 80%-ni həll edirsə, onu istifadə edin. Yalnız lazım olduqda genişləndirin.

---

## 5. Ümumi arxitektura anti-nümunələri

### 1. Vaxtından əvvəl microservices
- "Netflix edir" deyə 10k LOC app-i 20 servisə bölmək.
- **Nəticə:** ops yükü, deploy mürəkkəbliyi, paylanmış tracing ağrısı, data uyğunluğu qorxulu yuxuları.
- **Düzəliş:** monolit-lə başlayın, yalnız sübut olunmuş səbəblə çıxarın.

### 2. NIH sindromu
- Artıq mövcud olan və sizin quracağınızdan daha yaxşı olan alətləri yenidən qurmaq.
- **Düzəliş:** OSS mənimsəyin; geri töhfə verin; yalnız məhsulunuza həqiqətən əsas olanı qurun.

### 3. FAANG cargo-cult
- "Netflix Kubernetes istifadə edir, gəlin Kubernetes istifadə edək."
- "Uber Schemaless shardlayır, gəlin DB-mizi shardlayaq."
- **10-nəfərlik startup-ınız FAANG deyil.** Onların problemləri sizin problemləriniz deyil.

### 4. Böyük yenidən yazma
- "Gəlin hər şeyi sıfırdan yenidən yazaq."
- Joel Spolsky-nin klassik məqaləsi: *"Things You Should Never Do, Part I"*.
- **Düzəliş:** strangler-fig nümunəsi. Köhnəni sarın, ətrafında yeni əlavə edin, köhnəni yavaş-yavaş öldürün.

### 5. Resume əsaslı inkişaf
- Mühəndisin onu CV-yə yazmaq istədiyi üçün tech seçmək.
- **Simptom:** 500 istifadəçiyə xidmət göstərən CRUD app üçün Kotlin + Kafka + gRPC + K8s yığını.
- **Düzəliş:** biznes problemini həll edən ən sadə tech-i seçin.

### 6. Platformasız microservices
- Çox servis, amma servis şablonu yoxdur, observability yoxdur, deploy avtomatlaşdırılması yoxdur.
- **Düzəliş:** servisləri çıxarmadan əvvəl platformanı qurun; ya da monolit saxlayın.

### 7. Data qatının həddindən artıq mühəndisliyi
- Hexagonal, CQRS, event sourcing, DDD — hamısı to-do app üçün.
- **Düzəliş:** mürəkkəbliyi domen-ə uyğunlaşdırın. Əksər app-lar hadisələrlə CRUD-dur.

### 8. Paylanmış monolit
- Birlikdə deploy olunmalı və ya qırılan 20 microservice.
- **Simptom:** paylaşılan DB, sıx əlaqə, sinxron zəncirlər.
- **Düzəliş:** ya həqiqətən ayırın (hadisələr, servis başına öz DB) ya da monolitə geri birləşdirin.

---

## 6. PHP/Laravel senior üçün əsas fikirlər

### 1. Siz FAANG deyilsiniz
- Şirkətiniz milyardlara deyil, minlərcədən milyonlarcaya xidmət edir.
- Komandanız 10,000 deyil, 5-dən 50-yə qədərdir.
- Məhdudiyyətləriniz fərqlidir, ona görə seçimləriniz fərqli olmalıdır.

### 2. Darıxdırıcı tech qalib gəlir
- PHP Wikipedia-nı 20+ ildir işlədir.
- Perl Booking.com-u işlədir.
- Rails Shopify/GitHub/Basecamp-i işlədir.
- Python Instagram, Reddit, Dropbox-u işlədir.
- Laravel + MySQL + Redis + queue + S3 yüzlərlə milyon dollar dəyərində məhsul işlədə bilər.

### 3. Microservice-lərə "yox" deyə bilməyi bilin
- Müsahibədə default cavab: "Modul monolit ilə başlayardım və xüsusi səbəb ortaya çıxanda servisləri çıxarırdım."
- Qeyd edin: deploy sürəti, komanda sərhədləri, runtime ehtiyacları, miqyas.
- **Amazon Prime Video 2023**-ü AWS-nin də servisləri monolitə geri qaytardığına sübut olaraq gətirin.

### 4. Yaxşı sərhədli monolit > pis sərhədli microservices
- Aydın domen-ləri, hadisələri və job-ları olan Laravel app çox vaxt DB paylaşan dörd servisdən yaxşıdır.

### 5. Universal nümunələrdə təcrübə qazanın
Bunlar dillər və şirkətlər arasında keçərlidir:
- **Caching** (Redis nümunələri, cache invalidasiya, cache stampede-in qarşısının alınması).
- **Queue-lar** (idempotency, retry-lər, dead-letter queue-lar).
- **Shardlama** (təbii açar, məntiqi shard-lar, yenidən balanslaşdırma).
- **Read replika-lar və replikasiya lag-ı** (təcrübədə eventual consistency).
- **Bağlantı pooling** (PgBouncer, ProxySQL).
- **Feature flag-lar və canary deploy-lar**.
- **Observability** (log-lar, metriklər, trace-lər).
- **Event-driven dizayn** (domen hadisələri, inteqrasiya hadisələri, outbox nümunəsi).

Bunlar Laravel, Rails, Django, Spring və ya Node istifadə etsəniz də faydalı olacaq.

### 6. Müsahibəyə hazır nümunələr
Müsahibə aparan dizayn sualı verəndə real şirkətlərə istinad edin. Bu oxuduğunuzu və düşündüyünüzü göstərir.

---

## 7. Müsahibə cavab şablonları

### Şablon A — "X kimi sistem dizayn edin"

1. **Presedent adlandırın.** "Bu Instagram / Discord / Notion-un etdiyinə oxşayır."
2. **Onların məhdudiyyətlərini bildirin.** Miqyas, komanda, tech stack, giriş nümunəsi.
3. **Öz-ünüzünkünü bildirin.** "100k MAU, oxunma-yüklü, kiçik komanda, mövcud Laravel app fərz edək…"
4. **Uyğunlaşdırın.** "Instagram-ın tam etdiyini etməzdim, çünki daha kiçik miqyasdayam. Amma sharding + Snowflake ID ideyası hələ də tətbiq olunur."
5. **Kompromislərdən danışın.** Bir həll təqdim etməyin. İki təqdim edin və niyə birinin qalib gəldiyini deyin.

### Şablon B — "Bunu necə miqyaslanarsınız?"

7-mərhələli piramidadan istifadə edin:
1. Real darboğazın harada olduğunu profil et / ölç.
2. Sorğuları və indeksləri optimallaşdır.
3. Read replika-lar əlavə et.
4. Caching əlavə et (Redis / Memcached).
5. Async iş üçün queue-lar əlavə et.
6. Yazmalar darboğaz olsa DB-ni shardla.
7. Yalnız son mərhələdə servisləri çıxart.

### Şablon C — "Monolit yoxsa microservices?"

> "Default cavab: modul monolit. Konkret səbəb görəndə servisləri çıxarardım — müstəqil miqyaslanma, müstəqil buraxılış sürəti, spesifik iş yükü üçün lazım olan fərqli dil və ya aydın komanda sahibliyi sərhədi. Amazon-un 2023 Prime Video halı göstərir ki, onlar belə daha ucuz və daha sadə olduqda servisləri monolitə qaytarırlar. Məhsul üçün deyərdim: aydın domen sərhədləri ilə bir Laravel app saxlayın, real-time push və ya ağır video işlənməsi kimi real səbəb varsa kiçik Go/Node servisi əlavə edin."

### Şablon D — "Hansı verilənlər bazasını seçərdiniz?"

> "Giriş nümunəsindən asılıdır. Transaksional CRUD üçün MySQL və ya Postgres istifadə edərdim. Nəhəng yazma həcmi ilə real-time hadisələr və ya time-series üçün Cassandra və ya ScyllaDB-yə baxardım. Axtarış üçün Elasticsearch və ya Meilisearch əlavə edərdim. Cache üçün Redis. Modalı olduğu üçün verilənlər bazası seçməzdim — onu dataya və oxumalar/yazmalar nümunəsinə uyğunlaşdırardım. Instagram Postgres shardlayır, Discord tail gecikməsi üçün Cassandra-dan ScyllaDB-yə köçdü, Dropbox blob storage üçün Magic Pocket qurdu — hər seçim spesifik səbəbə görə edildi."

### Şablon E — "Çətin bir texniki qərar haqqında danışın"

Struktur (STAR-bənzər):
- **Situation**: sistemi, miqyası, ağrını təsvir edin.
- **Task**: qərar verməli olduğunuz (məsələn, shard vs replika vs yenidən yazma).
- **Action**: nə etdiniz, hansı alternativləri nəzərdən keçirdiniz, niyə seçdiniz.
- **Result**: rəqəmlərlə nəticə (gecikmə X% düşdü, xərc Y düşdü).

Öyrənə biləcəyiniz əla real nümunələr:
- Discord-un Go → Rust (bir servis).
- Notion-un Postgres shardlama.
- Dropbox-un S3 → Magic Pocket.
- Amazon Prime Video-nun microservices → monolit.

---

## 8. Kənar vərəqi — Kimi nə vaxt sitat gətirməli

| Müsahibə mövzusu | Sitat |
|-----------------|------|
| Python miqyaslanması | Instagram (Django-da 2B istifadəçi), Reddit, Dropbox |
| PHP miqyaslanması | Facebook (Hack), Wikipedia, Slack |
| Monolit müdafiəsi | Shopify, GitHub, Basecamp, Stack Overflow, Amazon Prime Video 2023 |
| Microservices kompromisləri | Spotify, Zalando, Uber |
| Real-time / konkurrentlik | WhatsApp (Erlang), Discord (Elixir) |
| Verilənlər bazası shardlaması | Instagram (Postgres), Notion (Postgres), Discord (ScyllaDB) |
| Cloud-u tərk etmək | Dropbox (S3 → Magic Pocket) |
| CRDT / əməkdaşlıq | Figma, Notion |
| Dinamik dillərdə statik tip | Instagram (Pyre, Cinder), Dropbox (mypy) |
| Darıxdırıcı tech | Booking.com (Perl), Wikipedia (PHP) |
| Developer portal / platforması | Spotify (Backstage) |
| Postgres HA | Zalando (Patroni, Spilo) |
| Event-driven arxitektura | Zalando, Uber, Netflix |

---

## 9. Son prinsiplər

1. **Sadə ağıllını döyür.**
2. **Optimallaşdırmadan əvvəl ölçün.**
3. **Əvvəlcə monolit; lazım olduğu sübut olunanda servislər.**
4. **Darıxdırıcı tech xüsusiyyətdir.**
5. **Yalnız OSS-in verə bilmədiyini qurun.**
6. **Cache, queue və shard üç əsas miqyaslanma alətidir — onları mənimsəyin.**
7. **Statik tiplər böyük kod bazalarını xilas edir.**
8. **Observability oyuncaqdan yuxarı hər miqyasda seçim deyil.**
9. **Hype üçün deyil, ölçülə bilən səbəblər üçün tech miqrasiya edin.**
10. **Arxitekturanız komanda ölçünüzə uyğun olmalıdır, Netflix-ə yox.**
