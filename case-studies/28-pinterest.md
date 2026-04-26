# Pinterest (Architect)

## Ümumi baxış

Pinterest vizual kəşf platformasıdır, burada insanlar "board"-larda təşkil olunmuş "pin"-lər şəklində ideyaları saxlayır və paylaşırlar. 2010-cu ildə Ben Silbermann, Paul Sciarra və Evan Sharp tərəfindən quruldu, qapalı beta-dan ən böyük şəkil əsaslı sosial platformalardan birinə çevrildi və ev, moda, yemək və səyahət kimi kateqoriyalarda ilham üçün ağır şəkildə istifadə olunur.

- ~500M aylıq aktiv istifadəçi (2024)
- ~200B saxlanmış pin
- ~4B board
- 2019-dan public şirkət (PINS)
- Əsas gəlir: reklam

Əsas tarixi anlar:

- 2010 — işə salındı (yalnız dəvətlə beta)
- 2012 — partlayıcı böyümə; MySQL çatlamağa başladı
- 2013 — məşhur "Sharding Pinterest" blog yazısı
- 2014 — Python → polyglot (bir çox servis üçün Java)
- 2016 — alış-veriş xüsusiyyətləri işə salınır
- 2019 — IPO
- 2020–2022 — "Idea Pins" (video) ağır təkan
- 2023–2024 — vizual axtarış + alış-veriş inteqrasiyası dərin təkan

## Texnologiya yığını

| Layer | Technology | Niyə |
|-------|-----------|-----|
| Languages | Python, Java, C++, JavaScript/TypeScript, bir az Go | Orijinal olaraq məhsul kodu üçün Python; performans servisləri üçün Java |
| Web framework | Django (legacy), Flask, daxili Python framework-ları, Java servisləri üçün Spring | Django onları başlatdı; polyglot-a keçdi |
| Primary DB | MySQL (shard-lanmış) | MySQL-i çox uzağa miqyaslamağı məşhur etdi |
| Secondary DB | HBase (ağır), DynamoDB, Redis, TiDB (bəziləri), Elasticsearch | Big data üçün HBase, KV üçün DynamoDB |
| Cache | Memcached (ağır) | Klassik web cache |
| Queue/messaging | Apache Kafka | Event onurğası |
| Search | Solr → Elasticsearch | Pin-lər, board-lar, istifadəçilər üzrə axtarış |
| Object storage | S3 | Şəkillər, videolar |
| CDN | Akamai, CloudFront | Milyardlarla şəklin çatdırılması |
| Infrastructure | AWS (primary) | Erkən günlərdən cloud-native |
| Orchestration | Kubernetes, daxili alətlər | Modern konteynerlər |
| ML | PinSage (qraf ML), vizual axtarış modelləri daxil olmaqla custom stack | Tövsiyələr, vizual axtarış, reklamlar |
| Frontend | React | Modern SPA |

## Dillər — Nə və niyə

Python Pinterest-in orijinal və ağır istifadə olunan dilidir. Web tətbiqi birinci gündən Django idi. Python erkən kiçik komandaya tez göndərməyə imkan verdi — klassik startup seçimi. Python ML, data science və backend servislərində istifadə olunmağa davam edir.

Java daha yüksək performanslı servislərdə istifadə olunur — real-time tövsiyə xidməti, reklam infrastrukturu və çox milyonlarla QPS endpoint-lərində alt-50ms gecikmənin vacib olduğu yerlərdə. Python müəyyən isti yollar üçün miqyaslama tavanına çatdıqda Pinterest Java-nı böyütdü.

C++ aşağı səviyyəli ML xidmətində və bəzi vizual axtarış infrastrukturunda istifadə olunur.

JavaScript və TypeScript frontend-i gücləndirir. React əsas framework-dur. Pinterest React üzərində qurulmuş dizayn sistemi olan Gestalt-u açıq mənbə etdi.

Go bir qədər qəbul görür, xüsusilə infrastruktur servislərində.

## Framework seçimləri — Nə və niyə

- Django — orijinal web framework-u. Nəhayət onları IPO-ya gətirdi. Solid ORM, təmiz admin.
- Flask — bəzi servislər Django-dan daha yüngül istədikdə Flask istifadə edir.
- Spring Boot / Dropwizard — Java servisləri üçün.
- React — UI.
- Gestalt — Pinterest-in React dizayn sistemi, açıq mənbə edilib.
- PinSage — tövsiyələr üçün in-house qraf neyron şəbəkə framework-u; tədqiqat nəşri layiqli.

## Verilənlər bazası seçimləri — Nə və niyə

### MySQL (shard-lanmış)
Pinterest MySQL-i fövqəladə uzağa miqyaslatdı. Arxitektura məşhurdur. 2013 "Sharding Pinterest" yazısından əsas məqamlar:

- 4096 "virtual" shard, hər biri məntiqi MySQL verilənlər bazası
- Fiziki MySQL serveri başına bir çox virtual shard (8 fiziki ilə başladı, böyüdü)
- Hər obyektin (pin, board, user) shard-ı kodlaşdıran ID-si var: yüksək bit-lər = shard ID
- Bütün əlaqələr "sahiblik" tərəfi üçün shard vasitəsilə gedir; ehtiyac olduqda digər tərəf üçün de-normalize edilir
- Tutum əlavə etmək: fiziki serveri bölün; bəzi virtual shard-ları yeni maşına köçürün; tətbiq konfiqurasiyasını yenidən yazın. Heç bir resharding lazım deyil, çünki hər shard özü ilə qalır.
- DB təbəqəsində foreign key-lər və cross-shard join-lər istiqamətindən qaçılır; tətbiq təbəqəsində edilir

MySQL niyə: tanış, etibarlı, yaxşı başa düşülür. Uzun müddət NoSQL-dən çəkindilər, çünki komanda SQL-ə və proqnozlaşdırıla bilən performansa etibar edirdi.

### HBase
Big-data iş yükləri üçün ağır istifadə — "home feed" namizəd generasiyası, bəzi analitika və MySQL-in çox bahalı olacağı nəhəng KV iş yükləri. HDFS üzərində HBase kütləvi ardıcıl oxumalar üçün yaxşı işləyir.

### DynamoDB
Yeni iş yükləri idarə olunan KV məna kəsb etdikdə DynamoDB istifadə edir.

### Redis
İsti data, sayğaclar, bəzi queue-lar üçün istifadə olunur.

### Memcached
MySQL qarşısında kütləvi cache təbəqəsi. Pinterest cache hit rate-lərinə ağır investisiya etdi.

### Elasticsearch (Solr-dan sonra)
Pin-lər, board-lar, istifadəçilər üzrə axtarış. Facet-lərlə, sıralama ilə, uyğunluq tənzimləməsi ilə mətn axtarışı.

### Miqrasiya hekayələri
- Tək MySQL → shard-lanmış MySQL (2012): onların məşhur miqyaslama anı
- Solr → Elasticsearch: daha yaxşı operability üçün
- Bəzi servislər data forması relational-a uyğun olmadıqda MySQL-dən DynamoDB və ya HBase-ə köçdü

## Proqram arxitekturası

Pinterest-in arxitekturası servis-oriyentlidir. Uber kimi microservices partlayışı deyil, lakin bir çox fokuslanmış servis.

```
+-----------------------------------------------------------+
|              Web + Mobile clients                         |
+-----------------------------------------------------------+
                          |
+-----------------------------------------------------------+
|      CDN (Akamai, CloudFront) for images + static         |
+-----------------------------------------------------------+
                          |
+-----------------------------------------------------------+
|      API Gateway / Edge layer                             |
+-----------------------------------------------------------+
                          |
     +--------------------+--------------------+
     |                    |                    |
+----v-----+      +-------v------+     +-------v------+
| Pins/    |      | Home Feed    |     | Visual       |
| Boards   |      | (recommender)|     | Search       |
| service  |      | (Python+Java)|     | (C++/Python) |
+----+-----+      +------+-------+     +------+-------+
     |                   |                    |
+----v-------------------v--------------------v-------------+
|   MySQL (4096 virtual shards)                            |
|   HBase clusters                                         |
|   DynamoDB (newer)                                       |
|   Memcached, Redis                                       |
|   Kafka (events)                                         |
|   Elasticsearch                                          |
|   S3 (images, videos)                                    |
+----------------------------------------------------------+
                          |
+----------------------------------------------------------+
|   ML: PinSage (graph NN), visual embeddings,             |
|   recommender serving (Java), Spark/Flink pipelines      |
+----------------------------------------------------------+
                          |
+----------------------------------------------------------+
|          AWS (EC2, S3, EKS, Kubernetes)                  |
+----------------------------------------------------------+
```

Home feed ən çox mühəndislik olunan sistemlərdən biridir: istifadəçi verildikdə, millisaniyələrdə sıralanmış pin siyahısı istehsal edin. Namizəd generasiyasını (PinSage qraf embed-ləri, mövzu maraqları, son tarixçə istifadə edərək əlaqəli pin-ləri çəkmək) sıralama (hər namizədi qiymətləndirən ML modeli) ilə birləşdirir, bir çox servis üzərində fan-out pattern-ində edilir.

## İnfrastruktur və deploy

- Əsasən AWS — EC2, S3, EKS (Kubernetes), DynamoDB, müxtəlif idarə olunan servislər
- S3 milyardlarla şəkil və video saxlayır; CloudFront + Akamai onları çatdırır
- Konteynerləşdirilmiş servislər üçün Kubernetes; onun ətrafında daxili alətlər
- Canary-lər və tədricən roll-out-larla CI/CD pipeline-ları
- Observability: Statsboard (daxili), Datadog və açıq mənbə stack-ləri ilə inteqrasiyalar

## Arxitekturanın təkamülü

- 2010–2012: Bir neçə maşında Django monolith + MySQL. Redis, Memcached. Kiçik komanda. Hiper-böyümə fazası.
- 2012–2014: MySQL sharding (4096 virtual shard); Memcached miqyaslaşması; polyglot backend yaranır. HBase qəbul edilir.
- 2014–2018: ML-first — home feed ağır şəkildə ML-idarə olunan olur; PinSage və dərin öyrənmə stack-ləri qurulur.
- 2018–2022: vizual axtarış yetkinləşir (şəkil embedding-ləri, ANN axtarış); alış-veriş inteqrasiyaları.
- 2022–indi: Idea Pins (video), alış-veriş oluna bilən pin-lər, daha çox DynamoDB və modern data stack; Kubernetes ağır.

## Əsas texniki qərarlar

### 1. 4096 virtual shard ilə MySQL sharding
- Problem: 2012-yə qədər tək MySQL serverləri Pinterest-in böyüməsini idarə edə bilmirdi. Komanda NoSQL-i nəzərdən keçirdi amma yetkinlik haqqında narahat idi.
- Seçim: MySQL ilə qalmaq, tətbiq təbəqəsində 4096 virtual shard ilə shard-lamaq, hər biri məntiqi DB. Fiziki yerləşdirmə çevik.
- Trade-off: mürəkkəb tətbiq məntiqi; cross-shard SQL join-ləri yoxdur; tətbiq obyektin hansı shard-da yaşadığını bilməlidir.
- Nəticə: MySQL Pinterest-i hiper-böyümə boyunca gücləndirdi. Resharding sadə idi: serveri bölün, virtual shard-ları köçürün. Kanonik sharding case study-ə çevrildi.

### 2. Big-data iş yükləri üçün HBase-i tətbiq etmək
- Problem: MySQL sharding kiçik sətir OLTP üçün əla işləyir amma kütləvi fan-out oxumalar və ya nəhəng append-ağır data üçün deyil.
- Seçim: home feed namizəd generasiyası, nəhəng əlaqə qrafları, bəzi log-lar/event-lər üçün HBase-i tətbiq et.
- Trade-off: HBase əməliyyat mürəkkəbliyi, JVM tənzimləmə, HDFS asılılığı.
- Nəticə: HBase recommender data üçün əsas sistem oldu; MySQL tranzaksiya data üçün qaldı.

### 3. Python → polyglot (Java əlavələri)
- Problem: bəzi isti yollar (reklam xidməti, sıralama) Python-da latency SLA-larını vura bilmədi.
- Seçim: ən isti servisləri Java-da yenidən yazmaq; məhsula yönəlmiş xüsusiyyətlər üçün Python saxlamaq.
- Trade-off: iki runtime, iki build sistemi, iki deploy yolu.
- Nəticə: latency hədəfləri tutuldu; Python məhsuldarlığı vacib olduğu yerdə qorundu.

### 4. PinSage və qraf ML-ə investisiya
- Problem: klassik kollaborativ süzgəc və kontent xüsusiyyətləri platoya çıxır; Pinterest-in əsas aktivi nəhəng istifadəçi-pin qrafıdır.
- Seçim: milyardlarla node üzərində işləyən qraf konvolyusiyalı şəbəkə olan PinSage qur. Tədqiqatı nəşr et.
- Trade-off: nəhəng hesablama xərci; çətin ML tədqiqat problemi.
- Nəticə: əhəmiyyətli engagement artması; qraf ML-də istinad arxitekturasına çevrildi; tövsiyə keyfiyyət benchmark-larını dəyişdirdi.

### 5. Embed-lərlə vizual axtarış
- Problem: istifadəçilər "buna bənzəyən bir şey"-i şəkildən axtarmaq istəyir.
- Seçim: milyardlarla pin üzərində vizual embed-lər (CNN əsaslı, sonra transformer əsaslı) və təxmini ən yaxın qonşu (ANN) axtarışı qurmaq.
- Trade-off: model təlimi/xidməti xərci; ANN indeksləri (FAISS tipli) ilə infra mürəkkəbliyi.
- Nəticə: vizual axtarış əsas məhsul fərqləndiricisi oldu; alış-veriş xüsusiyyətlərini gücləndirir.

## Müsahibədə necə istinad etmək

1. MySQL sharding sizi çox uzağa apara bilər. NoSQL və ya multi-DB arxitekturalarına keçməzdən əvvəl yaxşı dizayn edilmiş shard-lanmış MySQL-in ehtiyaclarınızı əhatə edib etmədiyini düşünün. Laravel-in çoxlu DB bağlantı dəstəyi var; model təbəqəsində və ya bağlantını seçən servis sinfində shard yönləndirilməsi həyata keçirə bilərsiniz. Pinterest tipli virtual shard-lar təqlid edilə bilər: hər sətrin ID-sində `shard_id` saxlayın, sorğuları müvafiq olaraq yönləndirin.

2. Memcached/Redis sizin dostunuzdur. Pinterest NoSQL-dən əvvəl aqressiv cache-ləmə ilə miqyaslandı. Laravel-də: model axtarışlarını cache edin, bahalı sorğu nəticələrini cache edin, render olunmuş fraqmentləri cache edin. İnvalidasiya üçün tag-lar istifadə edin.

3. Kiçik miqyasda yenidən icad etməyin. PinSage sərindir; 100k istifadəçili tətbiq üçün ona ehtiyacınız yoxdur. Kollaborativ süzgəc (Laravel + SQL) və ya hazır tövsiyə kitabxanaları milyonlarla qarşılıqlı təsirə sahib olana qədər yaxşıdır.

4. Yalnız əsaslandırıldıqda polyglot. Spesifik servislər latency divarlarına çatana qədər Python Pinterest üçün yaxşı idi. Laravel tətbiqinizdə PHP-nin çətinlik çəkdiyi bir isti yol varsa (deyək ki, real-time şəkil oxşarlığı), yalnız həmin yolu kiçik Go/Python servisinə çıxarın və onu Laravel-dən çağırın. Bütün tətbiqi bölməyin.

5. Feed-lər sıralanır, xronoloji deyil. Modern sosial feed-lər ML-sıralanır. Hətta kiçik Laravel tətbiqləri üçün, engagement vacibdirsə, xronoloji defolt etməyin — hər hansı ML-dən əvvəl sadə skorlamanı (yenilik × uyğunluq × yaxınlıq) nəzərdən keçirin.

6. Miqyasda şəkil + video = CDN + obyekt saxlama. Pinterest-in əsas pipeline-ı: yükləmə → S3 → CDN → client. Laravel-də S3 və CloudFront ilə Laravel Medialibrary və ya Vapor istifadə edin. İstifadəçi tərəfindən yüklənmiş şəkilləri web serverlərinizdən birbaşa xidmət etməyin.

7. Observability və cache hit rate-lər. Pinterest cache hit rate-lərlə məşğul oldu. Production-da cache hit rate-inizi bilin. Dashboard qurun. İsti endpoint-lərdə 90%-dən aşağıdırsa, araşdırın.

## Əlavə oxu üçün

- "Sharding Pinterest: How we scaled our MySQL fleet" (2015 Pinterest Engineering yazısı)
- "Learning a Personalized Homepage" (Pinterest engineering)
- "PinSage: Graph Convolutional Neural Networks for Web-Scale Recommender Systems" (KDD məqalə)
- "Unified Visual Embeddings for Pinterest" (engineering blog)
- "Pinterest engineering blog" (medium.com/pinterest-engineering)
- "Gestalt: A Design System for Pinterest" (açıq mənbə sənədləri)
- "HBase at Pinterest" (blog yazıları)
- "Event-driven architecture at Pinterest" (Kafka istifadəsi)
- "Scaling Kubernetes at Pinterest"
- "Using DynamoDB at Pinterest" (miqrasiya hekayələri)
- "Cache strategies at Pinterest" (müxtəlif yazılar)
