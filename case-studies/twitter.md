# Twitter (indi X)

## Ümumi baxış

Twitter real-time mikrobloq və sosial şəbəkə platformasıdır. 2006-cı ildə Jack Dorsey, Noah Glass, Biz Stone və Evan Williams tərəfindən quruldu, Odeo adlı podkast şirkətində yan layihədən böyüdü. 140 simvol limiti (sonradan 280), "fail whale" miqyaslama problem dövrü ilə məşhur idi və bir çox JVM əsaslı paylanmış sistemlərdə öncülük etdi. Oktyabr 2022-də Elon Musk tərəfindən alındı və 2023-də X olaraq yenidən brendləşdi.

- X yenidən brendindən əvvəl ~500M MAU (ictimai olaraq bildirildi)
- Normal vaxtlarda saniyədə minlərlə tweet; əsas hadisələrdə (Dünya Kuboku, Oscar, seçkilər) onun ~10 qatı
- 2022-yə qədər public şirkət (Musk alışından sonra özəlləşdi)
- Bootstrap, Finagle, Snowflake daxil olmaqla böyük açıq mənbə çıxışı yaratdı

Əsas tarixi anlar:

- 2006 — SXSW-də işə salındı, texnologiya dairələrində viral oldu
- 2008–2010 — "fail whale" dövrü, Ruby on Rails-də xroniki miqyaslama problemləri
- 2010 — backend-i Finagle ilə Scala/JVM-ə köçürməyə başladı
- 2011 — Bootstrap buraxıldı (CSS framework)
- 2013 — IPO
- 2014 — Manhattan paylanmış DB ictimai elan olundu
- 2017 — "The Infrastructure Behind Twitter" texniki blog yazısı miqyası izah etdi
- 2022 — Musk alışı, kütləvi işdən çıxarmalar, çoxlu ictimai arxitektural müzakirələr
- 2023 — X-ə yenidən brendləşdi

## Texnologiya yığını

| Layer | Technology | Niyə |
|-------|-----------|-----|
| Languages | Scala (ağır), Java, C++, Python, JavaScript/TypeScript, Go (bir az) | JVM Ruby-ni əvəz etdi; Scala əsas servislərdə dominantdır |
| Web framework | Finatra/Finagle (Scala), Ruby on Rails (azalmış legacy) | Finagle Twitter tərəfindən qurulub, JVM-native RPC stack-idir |
| Primary DB | Manhattan (Twitter-in öz paylanmış DB-si) | Yenidən qurdu çünki MySQL onların yazma nümunələrini idarə edə bilmirdi |
| Secondary DB | MySQL (legacy), Google Cloud Spanner (bəziləri), Redis tipli Cuckoo, FlockDB (qraf, deprecate olunub) | Fərqli data formaları |
| Cache | Twemcache (Twitter-in Memcached fork-u), Pelikan (Twitter-in cache engine-i), Redis | Timeline-lar üçün nəhəng cache təbəqəsi |
| Queue/messaging | Apache Kafka (çox ağır) | Tweet-lər, event-lər, analitika üçün event bus |
| Search | Earlybird (in-memory Lucene əsaslı, Twitter tərəfindən qurulub) | Real-time tweet axtarışı — sürət üçün in-memory indeks |
| Storage | HDFS, Google Cloud Storage (qismən köçürülüb) | Data lake və backup-lar |
| Infrastructure | Öz data mərkəzləri (tarixən), artan GCP | Hibrid |
| Compute | Aurora/Heron (stream processing, öz), Mesos (Apache) | Mesos əsas scheduler idi |
| Monitoring | Zipkin ilə observability stack (Twitter-də icad olunan distributed tracing) | Zipkin indi OSS standartdır |
| Frontend | React, köhnə templating stack-ləri | Zamanla modernləşdirildi |

## Dillər — Nə və niyə

Scala Twitter-də müəyyənedici dildir. 2010-dan başlayaraq Twitter Ruby monolith-in böyük hissələrini Scala-da yenidən yazdı. Niyə Scala:

- JVM-də işləyir — yetkin, sürətli, nəhəng ekosistem
- Funksional xüsusiyyətlər konkurrent kodla kömək edir (immutable data, pattern matching, future-lər)
- Tip təhlükəsizliyi — Ruby-nin yalnız runtime-da tapacağı bug-ları compile zamanı tutur
- Hazırda iddialı paylanmış sistem mühəndislərini işə götürmədə fərqləndirici verdi

Java Scala-nın compile vaxtları və ya mürəkkəbliyinin dəyməyəcəyi yerlərdə və komandalar arasında paylaşılan kitabxanalarda istifadə olunur.

C++ Earlybird-də (axtarış indeksi) və performansa həssas yerlərdə istifadə olunur.

Python data science, ML və bəzi daxili alətlərdə istifadə olunur.

JavaScript/TypeScript web UI-ni gücləndirir (böyük React investisiyası idi).

Ruby orijinal dil idi. Rails monolith ilkin məhsul idi. İllər ərzində azaldı lakin hissələri hələ də ola bilər.

## Framework seçimləri — Nə və niyə

- Finagle (Scala) — Twitter-in RPC framework-u. Service discovery, load balancing, circuit breaking, retry-lər, distributed tracing idarə edir. Netty üzərində qurulub. Açıq mənbə edilib.
- Finatra — Scala servisləri üçün Finagle üzərində web framework.
- Bootstrap (CSS/JS) — orijinal olaraq Twitter-də daxildə qurulub, 2011-də açıq mənbə edildi, tarixdə ən populyar frontend framework-larından birinə çevrildi.
- Scalding — data pipeline-ları üçün Hadoop/Cascading üzərində Scala wrapper. Scala-nı data-engineering dili etdi.
- Summingbird — batch + stream birləşmiş API (Lambda arxitektura ideyasının sələfi).
- Heron — stream processing framework, onların Apache Storm istifadəsini əvəz etdi; açıq mənbə edilib.

## Verilənlər bazası seçimləri — Nə və niyə

### Manhattan
Twitter-in öz-evində hazırlanmış paylanmış, multi-tenant verilənlər bazası. 2013–2014 ətrafında başladı. Niyə qurdular:

- Onların miqyasında MySQL ağrılı idi — əllə sharding, nasazlıqlar risqli idi
- Multi-tenancy-yə ehtiyac vardı: bir çox komanda, bir çox dataset, paylaşılan saxlama toxuması
- Bəziləri üçün eventual, digərləri üçün güclü consistency lazım idi

Manhattan Twitter-ə məxsus avadanlıqda işləyir, data-nı bir çox node arasında replikasiya ilə saxlayır və namespace başına fərqli consistency modelləri təklif edir. Yüzlərlə petabayt data.

### MySQL
Legacy və bəzi spesifik iş yüklərində hələ də istifadə olunur. Tweet-lər əvvəlcə FlockDB-nin qardaş sistemləri vasitəsilə shard-lanmış MySQL-də idi.

### FlockDB
Follower/following əlaqələri üçün MySQL üzərində qurulmuş qraf store. Sadə amma effektiv; açıq mənbə edilib. Nəhayət əvəz edildi və ya artırıldı.

### Snowflake
Verilənlər bazası deyil — amma Twitter-in paylanmış ID generatoru. Timestamp + machine ID + ardıcıllıq nömrəsi əsasında 64-bit unikal ID-lər istehsal edir. Faydalıdır çünki tweet-lərə qlobal unikal, təqribən vaxt-sıralı ID-lər lazım idi. Açıq mənbə edilib; bir çox şirkət öz ID generasiyası üçün Snowflake ID-lərini (və ya variantlarını) istifadə edir.

### Cuckoo
Daxildə istifadə etdikləri Redis tipli cache.

### Google Cloud Spanner
Hibrid bulud təkanından sonra qismən qəbul edildi.

## Proqram arxitekturası

Twitter-in həll etdiyi əsas problem timeline fan-out-dur. Tweet yazdığınızda, followers-inizin home timeline-ları onu göstərməlidir. İki strategiya:

- Fan-out on write: tweet atdığınızda, sistem tweet ID-ni hər follower-in timeline data strukturuna push edir. Oxumalar sonra ucuzdur — sadəcə siyahını götürün. Amma yazılar bahalıdır — 1M follower-iniz varsa, hər tweet 1M qeyd yazır.
- Fan-out on read: istifadəçi timeline-ını yüklədikdə, onun izlədiyi hər kəsdən son tweet-ləri götür, birləşdir. Yazılar ucuzdur (sadəcə tweet-i yadda saxla), oxumalar bahalıdır.

Twitter hibrid istifadə edir. Əksər istifadəçilər fan-out on write istifadə edir. Məşhurlar (milyonlarla follower-i olanlar) fan-out on read istifadə edir — onların tweet-ləri hər follower-ə push edilmir; o follower-lər timeline-larını yüklədikdə çəkilir.

```
+------------------------------------------------------------+
|            Web, Mobile, API consumers                      |
+------------------------------------------------------------+
                          |
+------------------------------------------------------------+
|      TFE (Twitter Front End) / API gateway                 |
+------------------------------------------------------------+
               |                     |
+--------------v------+   +----------v-----------+
|   Tweet service     |   |  Timeline service    |
|   (Scala / Finagle) |   |  (Scala / Finagle)   |
|   Posts, stores     |   |  Builds home TL      |
+----------+----------+   +----------+-----------+
           |                         |
           |              +----------+------------+
           |              |                       |
           |     +--------v--------+   +----------v---------+
           |     | Timeline Cache  |   | Earlybird Search   |
           |     | (Twemcache/     |   | (in-memory Lucene) |
           |     |  Pelikan)       |   +--------------------+
           |     +--------+--------+
           |              |
+----------v--------------v----------------------------------+
|     Manhattan (distributed KV DB)                           |
|     MySQL (legacy)                                          |
|     Kafka (event bus)                                       |
+------------------------------------------------------------+
                          |
+------------------------------------------------------------+
|     Own data centers + GCP (some workloads)                 |
|     Mesos schedulers, internal deploy tooling               |
+------------------------------------------------------------+
```

## İnfrastruktur və deploy

- Öz data mərkəzləri — Twitter böyük data mərkəzlərinə sahib idi və onları işlədirdi (məs. Sacramento, Atlanta). Avadanlıq real capex maddəsidir.
- Mesos — Apache Mesos scheduling üçün çox istifadə olundu. Twitter əsas Mesos töhfəçisi idi.
- Hibrid bulud — 2018 və ya ətrafdan sonra Twitter bəzi iş yüklərini GCP-yə yükləməyə başladı (xüsusilə data / Hadoop əvəzləmə iş yükləri).
- Deploy alətləri — daxili; canary-lərlə pipeline-lar.
- Observability: tracing üçün Zipkin (Twitter-də icad olunub), custom metrics və log-lar stack-ləri.

## Arxitekturanın təkamülü

- 2006–2008: Ruby on Rails monolith + MySQL. Sadə lakin kövrək.
- 2008–2010: Fail Whale dövrü. Ruby trafiki idarə edə bilmirdi. Verilənlər bazası isti nöqtələri. Timeline generasiya darboğazları.
- 2010–2013: JVM miqrasiyasına başladı. Scala qəbulu. Finagle buraxıldı. ID-lər üçün Snowflake. Rails monolith-i servislərə parçaladı.
- 2013–2017: Manhattan qurulur. Stream-lər üçün Heron. Kafka event bus-a çevrilir. Axtarış üçün Earlybird.
- 2017–2022: yetkinlik — bir çox OSS alət, aqressiv optimallaşdırma, GCP ilə hibrid bulud.
- 2022–indi: Musk alışı; böyük arxitektural dəyişikliklər, işdən çıxarmalar, xərc azaltmaları hesabatları; X brend roll-out.

## Əsas texniki qərarlar

### 1. Rails → JVM/Scala miqrasiyası (2010)
- Problem: Rails Twitter miqyasında timeline generasiyasını idarə edə bilmirdi. "Fail whale" backend həddini aşmanın istifadəçi-görünən simvolu idi.
- Seçim: JVM-də Scala-da isti yolları yenidən yazmaq. RPC üçün Finagle qurmaq.
- Trade-off: böyük yenidən yazma xərci; Scala öyrənmə əyrisi.
- Nəticə: fail whale yox oldu. Twitter JVM dükanı oldu. Stack hələ də əsasən bu gün JVM-dir.

### 2. Manhattan — öz DB-lərini qurmaq
- Problem: MySQL sharding əmək intensiv və kövrək idi. Heç bir hazır DB onlara per-namespace consistency idarəetmələri ilə multi-tenant saxlama verə bilmədi.
- Seçim: öz paylanmış DB-ni qurmaq.
- Trade-off: nəhəng mühəndislik investisiyası; yalnız Twitter miqyasında məna kəsb edir.
- Nəticə: Twitter-in saxlamasının böyük hissələrini işlədir; daxili komandaların etibar etdiyi əsas platforma çevrildi.

### 3. Timeline fan-out hibridi
- Problem: fan-out on write məşhurlar üçün (Justin Bieber, 100M+ follower-li Obama) sınır. Fan-out on read hər kəs üçün sınır (timeline-ı sıfırdan hesablamaq çox yavaşdır).
- Seçim: hibrid. Defolt fan-out on write, yüksək-follower-li hesablar üçün xüsusi idarəetmə fan-out on read vasitəsilə.
- Trade-off: iki kod yolu; sərhəddə consistency kənar halları.
- Nəticə: normal istifadəçilər üçün timeline-lar millisaniyələrdə yüklənir; məşhurların tweet-ləri infrastrukturu partlatmadan işləyir.

### 4. Snowflake — paylanmış ID generasiyası
- Problem: tweet-lər üçün 64-bit unikal, təqribən vaxt-sıralı ID-lərə ehtiyac var idi. Mərkəzi auto-increment darboğazdır.
- Seçim: 64 bit-ə paketlənmiş timestamp + machine ID + ardıcıllıq nömrəsi.
- Trade-off: saat qeyri-bərabərliyi problem yarada bilər; diqqətli vaxt sinxronizasiyası lazımdır.
- Nəticə: nümunə sənayedə kopyalandı. Bir çox dildə "Snowflake ID" kitabxanaları var.

### 5. Earlybird — real-time in-memory axtarış
- Problem: yeni yazılan tweet-ləri saniyələr içində axtarmaq lazımdır. Diskdə Lucene kifayət qədər sürətli deyil.
- Seçim: bir çox serverdə bölmələnmiş in-memory Lucene əsaslı indeks qurmaq.
- Trade-off: yaddaş bahalıdır; restart-da yenidən qurulur.
- Nəticə: real-time axtarış Twitter miqyasında işləyir.

## PHP/Laravel developer üçün dərs

1. Fan-out strategiyaları hər hansı feed tipli xüsusiyyətə tətbiq olunur. Laravel-də bildiriş mərkəzi, fəaliyyət feed-i və ya mesajlaşma sistemi qurursunuzsa, write-da (hər alıcının queue-suna) push edib-etməyəcəyinizi və ya read-də (baxarkən aqreqatlaşdırmaq) pull edib-etməyəcəyinizi düşünün. Adətən hibrid: əksəriyyət üçün push, nəhəng auditoriyalı "power user"-lər üçün pull.

2. Miqyaslaşdıqca paylanmış ID-lər vacibdir. Multi-database və ya multi-region keçirsinizsə, auto-increment ID-lər zərər verir. UUID, ULID və ya Snowflake tipli ID-lər istifadə edin. Laravel-də daxili olaraq `Str::ulid()` və `Str::uuid()` var və öz Snowflake-ınızı asanlıqla qura bilərsiniz.

3. Cache-ləmə miqyasda necə sağ qalmağınızdır. Twemcache → Pelikan nəhəng cache investisiyası idi. Laravel üçün çoxlu təbəqələrdə Redis cache-ləmə (route cache, query cache, hesablanmış timeline fraqmentləri) ekvivalentidir.

4. Yalnız həqiqətən lazım olduqda yenidən yazın. Twitter-in Rails-dən Scala-ya yenidən yazılması illər çəkdi və qısamüddətli məhsuldarlığa zərər verdi. "Daha modern olmaq üçün" aydın miqyaslama ağrısı olmasa Laravel tətbiqini yenidən yazmayın.

5. Hər yerdə async. Twitter-in Kafka istifadəsi o deməkdir ki, event-lər sistemi bloklamadan yayılır. Laravel-də queue-lar, event-lər, listener-lər və broadcasting (Pusher/Reverb) qeyri-kritik-yol hər şey üçün istifadə edin.

6. Əvvəlcə observability. Zipkin icad olundu, çünki Twitter-in servislər arasında sorğuları izləməyə ehtiyacı vardı. Laravel tətbiqiniz birdən çox servis istifadə etdikdə (hətta sadəcə Redis + MySQL + 3-cü tərəf API), distributed tracing debugging-ə dramatik şəkildə kömək edir.

7. Trade-off-ları dürüst qəbul edin. Məşhur timeline-ları fərqli strategiya istifadə edir. Hər istifadəçi eyni deyil. Orta üçün deyil, istifadənin paylanması üçün dizayn edin.

## Əlavə oxu üçün

- "The Infrastructure Behind Twitter: Scale" (Twitter blog, 2017)
- "Twitter's migration to Google Cloud" (müxtəlif Twitter engineering yazıları)
- "Finagle: A Protocol-Agnostic RPC System" (Twitter Engineering blog)
- "Manhattan, our real-time, multi-tenant distributed database for Twitter scale"
- "Announcing Snowflake" (ID generator haqqında orijinal Twitter yazısı)
- "Earlybird: Real-Time Search at Twitter"
- "FlockDB" (Twitter yazısı)
- "Building and scaling Bootstrap" (frontend hekayəsi)
- "Using Scala at Twitter" (Marius Eriksen çıxışları)
- "Scaling Twitter" (John Adams tərəfindən əvvəlki dövr çıxışları)
- "A Brief History of Kafka at Twitter" (event bus ilə qarşılıqlı təsir)
- "Zipkin: Distributed Tracing System" (Twitter/indi OpenZipkin sənədləri)
