# Netflix (Architect)

## Ümumi baxış

Netflix dünyanın ən böyük abunəlik əsaslı video streaming xidmətidir. 1997-ci ildə Kaliforniyada Reed Hastings və Marc Randolph tərəfindən DVD-ni poçtla göndərən icarə biznesi kimi qurulub. Streaming 2007-ci ildə yan funksiya kimi işə salındı və sonra bütün biznes modelinə çevrildi. Bu gün Netflix öz film və seriallarını da istehsal edir (Netflix Originals) və demək olar ki, bütün ölkələrdə fəaliyyət göstərir.

- 260M+ pullu abunəçi (2024, Q4 hesabatı)
- Pik saatlarda qlobal internet downstream trafikin təxminən 15%-i (illərlə aparılan Sandvine hesabatları)
- Minlərlə ad, onlarla dil, 190+ ölkə
- Public şirkət (NFLX), illik gəliri milyardlarla hesablanır

Əsas tarixi anlar:

- 1997 — DVD icarə şirkəti kimi quruldu
- 2007 — ABŞ-da streaming işə salındı
- 2008 — Oracle stack-lərində ciddi database korrupsiya hadisəsi baş verdi; bulud infrastrukturuna keçid qərarının səbəbi oldu
- 2008–2016 — öz data mərkəzlərindən AWS-ə 8 illik miqrasiya, 2016-da tamamlandı
- 2010 — cross-region replikasiya üçün Oracle/SQL-dən Cassandra-ya keçməyə başladı
- 2011 — Simian Army qurulmağa başlandı (Chaos Monkey tanıdıldı)
- 2013 — House of Cards, onların ilk əsl Original istehsalı, streaming-first kontentin işlədiyini sübut etdi
- 2016 — 130 ölkədə eyni vaxtda qlobal buraxılış
- 2023 — parol paylaşmaya qarşı sərt tədbirlər böyümə mühərrikinə çevrildi

## Texnologiya yığını

| Layer | Technology | Niyə |
|-------|-----------|-----|
| Languages | Java (backend-in 95%+), Python (data, ML), JavaScript/TypeScript (UI), Kotlin (Android), Swift (iOS), bir az Go | Yüksək ötürmə qabiliyyəti olan servislər üçün JVM, data science üçün Python, cihazlar üçün native |
| Web framework | Spring Boot, Spring Cloud | Yetkin JVM ekosistemi, yaxşı kitabxana dəstəyi, Netflix OSS təbii inteqrasiya olunur |
| Primary DB | Apache Cassandra | Multi-master cross-region yazılar, xətti miqyaslanma, eventual consistency streaming use case-lərinə uyğundur |
| Secondary DB | Amazon DynamoDB, Elasticsearch, MySQL (bəzi hallarda), CockroachDB (sınaqdan keçirilib) | İdarə olunan KV üçün DynamoDB, mətn axtarışı üçün Elasticsearch, spesifik OLTP ehtiyacları üçün MySQL |
| Cache | EVCache (Memcached əsaslı, Netflix tərəfindən hazırlanıb) | Regionlar arasında onlarla terabayt RAM, millisaniyədən az oxumalar, AZ-lər arasında replikasiya |
| Queue/messaging | Apache Kafka, SQS, Keystone pipeline | Event streaming üçün Kafka, Keystone gündə petabaytlarla event hərəkət etdirir |
| Search | Elasticsearch | Katalog və daxili axtarış üçün |
| Infrastructure | AWS (EC2, S3, VPC), Open Connect (video üçün öz CDN-i) | Control plane üçün AWS, miqyasda bant genişliyi üçün öz CDN |
| Container orchestration | Titus (Netflix tərəfindən hazırlanıb), son vaxtlar bir az Kubernetes | Titus yetkin K8s-dan əvvəl gəldi; Netflix Mesos üzərində öz container scheduler-ini qurdu |
| CI/CD | Spinnaker (Netflix-də yaradılıb, OSS) | Multi-cloud, multi-region deploy-lar canary və red/black ilə |
| Build | Nebula (Gradle plugin-ləri) | Yüzlərlə servis arasında vahid Gradle konfiqurasiyası |
| Monitoring | Atlas (Netflix tərəfindən hazırlanıb metrics DB), Mantis, Zipkin, Vector | Atlas nəhəng cardinality-də ölçülü time-series saxlayır |
| ML | Metaflow (Netflix tərəfindən hazırlanıb OSS), custom recommender stack | Metaflow data scientist-lərə lokal + AWS-də workflow-lar işə salmağa imkan verir |

## Dillər — Nə və niyə

Java Netflix-də dominant backend dilidir. Server kodunun 95%-dən çoxu JVM-dir — əsasən Java, yeni servislər və Android tətbiqləri üçün bir az Kotlin əlavə olunub. Onlar Java-nı əvvəlcə yetkin ekosistemi, Garbage Collector tənzimləmə qabiliyyəti, geniş kitabxana dəstəyi və bazarda senior JVM mühəndislərinin mövcudluğu səbəbindən seçdilər. 2010-cu illərin əvvəllərində microservices-ə keçdikdə, buraxdıqları demək olar ki, hər OSS layihəsi (Hystrix, Eureka, Zuul, Ribbon, Archaius) Java-da yazılıb və əksəriyyəti JVM runtime ətrafında qurulub.

Python ikinci əsas dildir, xüsusən data science, ML training, analitika və bəzi daxili vasitələrdə. Metaflow, onların insan dostu ML workflow aləti, Python-first-dir. Data mühəndisləri Spark və Airflow-tipli DAG-lərlə Python istifadə edir.

JavaScript və TypeScript web UI-ni (netflix.com) gücləndirir və Node.js bəzi BFF-tipli (backend-for-frontend) aqreqasiya təbəqələri üçün edge-də istifadə olunur. Netflix memory leak araşdırması haqqında "Node.js in Flames" adlı məşhur blog yazısı yazdı, klassikaya çevrildi. TV UI-ləri (Smart TV-lər, oyun konsolları) Gibbon adlı xüsusi rendering platformasından istifadə edir.

Kotlin Android tətbiq inkişafı üçün istifadə olunur və yeni backend servislərində də görünür. Swift iOS üçün istifadə olunur. Bir az C++ video encoder-də və Open Connect-də (onların CDN box-larında) istifadə olunur. Go məhdud yerlərdə istifadə olunur, tez-tez infra vasitələri üçün.

## Framework seçimləri — Nə və niyə

Spring Boot standart backend framework-dur. Əksər Netflix microservices onların metrics, logging və service discovery client-lərini ehtiva edən Netflix tipli Spring Boot şablonundan başlayır. Spring Cloud Netflix (bir neçə Netflix kitabxanasını Spring-ə yerləşdirən OSS layihəsi) bunu daha da yaxşı uyğunlaşdırdı.

Netflix çox sayda framework və kitabxananı yazıb açıq mənbə etdi, onların bir çoxu sənaye standartı oldu:

- Hystrix — ilk circuit breaker kitabxanası (indi deprecate olunub, resilience4j ilə əvəzlənib)
- Eureka — service discovery, AP sistem (consistency üzərində availability seçildi)
- Zuul — edge gateway, JVM əsaslı, daha sonra async Netty ilə Zuul 2 kimi yenidən yazıldı
- Ribbon — client-side load balancer (deprecate olunub)
- Archaius — dinamik konfiqurasiya
- RxJava — JVM üçün reactive extensions; Java-da reactive proqramlaşdırmanı geniş formalaşdırdı
- Falcor — UI-lar üçün data fetching (GraphQL qalib gəlməzdən əvvəl GraphQL bənzər yanaşma)

Bu gün Netflix circuit breaking üçün resilience4j istifadə edir (Hystrix maintenance rejiminə qoyulduqdan sonra) və bir çox servis Spring Cloud Gateway və ya Zuul 2 üzərində öz edge-lərini istifadə edir.

Streaming və reactive iş yükləri üçün Project Reactor və RxJava hələ də çox istifadə olunur.

## Verilənlər bazası seçimləri — Nə və niyə

### Apache Cassandra
Cassandra əksər Netflix servisləri üçün əsas əməliyyat verilənlər bazasıdır. Netflix açıq şəkildə dünyada ən böyük Cassandra istifadəçisidir — onlar bir neçə AWS region-da yüzlərlə cluster-də minlərlə node işlədir. Cassandra-nı seçdilər çünki:

- Multi-master, multi-region yazılar təbii işləyir (us-east-1, eu-west-1, us-west-2-dən eyni vaxtda yazın; data last-write-wins və ya CRDT vasitəsilə uzlaşdırılır)
- Xətti miqyaslanma — daha çox node əlavə edin, daha çox tutum əldə edin
- Single point of failure yoxdur
- Hər sorğuya görə tənzimlənə bilən consistency

Trade-off-lar: Cassandra defolt olaraq eventual consistent-dir. Netflix üstünə vasitələr yazdı (əvvəlcə Dynomite, digərləri) və hər use case üzrə consistency səviyyələrini diqqətlə seçdi. İstifadəçi baxma tarixçəsi kimi şeylər üçün eventual consistency qənaətbəxşdir.

### Amazon DynamoDB
Seçilmiş metadata və tamamilə idarə olunan, istifadəyə görə ödəniş olunan KV store-un məna kəsb etdiyi bəzi yeni servislər üçün istifadə olunur. Netflix bəzən əməliyyat yükü istəmədiyi kiçik amma kritik cədvəllər üçün DynamoDB-ni üstün tutur.

### Elasticsearch
Axtarış üçün istifadə olunur, o cümlədən bəzi katalog axtarışı və bir çox daxili vasitə use case-ləri.

### MySQL
Bəzi künclərdə hələ də istifadə olunur, tez-tez relational SQL tələb edən kiçik servislər üçün.

### Miqrasiya hekayəsi
2008-ci ildə Netflix Oracle verilənlər bazasında ciddi korrupsiya hadisəsi yaşadı və bərpa üçün 3 gün sərf etdi. Bu hadisə iki qərara səbəb oldu: bulud infrastrukturuna (AWS) köçürülmək və tək instance, vertikal olaraq miqyaslanan relational verilənlər bazalarından çıxmaq. Təxminən 2010-cu ildən başlayaraq, Oracle-ın uyğun qiymətə təklif edə bilmədiyi cross-region active-active replikasiyasına ehtiyaca görə bir çox iş yükünü Oracle-dan Cassandra-ya köçürdülər.

## Proqram arxitekturası

Netflix necə saydığınızdan asılı olaraq təxminən 700 ilə 1,000+ servis arasında microservices arxitekturası ilə işləyir. Kritik yolda heç bir monolith mövcud deyil. Hər servisə kiçik komanda sahiblik edir, müstəqil deploy olunur və adətən REST və ya gRPC interfeysi təklif edir.

Yüksək səviyyədə:

```
+----------------------------------------------------------+
|                   End Users (TVs, phones, web)           |
+----------------------------------------------------------+
                          |
                  (HTTPS, video from CDN)
                          |
+----------------------------------------------------------+
|        Open Connect CDN (embedded in ISP networks)       |
|   Serves video bytes from cache boxes near the viewer    |
+----------------------------------------------------------+
                          |
                 (control plane only via AWS)
                          |
+----------------------------------------------------------+
|        AWS Edge: Zuul Gateway, API aggregation layer     |
+----------------------------------------------------------+
                          |
          +---------------+---------------+
          |               |               |
+---------v----+  +------v-------+ +------v-------+
| Playback API |  | Account/Auth | | Recommender  |
| (Java/Spring)|  | services     | | service      |
+------+-------+  +------+-------+ +------+-------+
       |                 |                |
+------v--------------------v--------v---------+
|   EVCache (Memcached) | Cassandra clusters         |
|   Kafka / Keystone    | S3 (archival, logs, data)  |
+----------------------------------------------------+
                          |
+----------------------------------------------------+
|  Spinnaker (deploy) | Atlas (metrics) | Titus      |
|  Simian Army (Chaos) | Mantis (real-time stream)   |
+----------------------------------------------------+
```

Open Connect arxitektura baxımından maraqlıdır: control plane (auth, katalog, tövsiyələr, lisenziyalaşdırma, hesablama) AWS-də işləyir, lakin video axınının faktiki baytları Netflix-in ISP data mərkəzləri içində yerləşən öz CDN avadanlıqlarından gəlir. Beləliklə, baxanda, HTTPS API çağırışları AWS us-east-1 və ya eu-west-1-ə gedir, lakin video özü 5km aralıda olan box-dan axır, bu da Netflix-in AWS egress xərcləri olmadan qlobal trafikin 15%-ni çatdıra bilməsinin səbəbidir.

## İnfrastruktur və deploy

- AWS buluddur: bir neçə region, multi-AZ, kritik servislər üçün regionlar arasında active-active
- Open Connect: Netflix-in öz CDN-i. Serverlər ISP data mərkəzlərində yerləşdirilir (Comcast, BT, Vodafone və s.). ISP-lər ucuz/pulsuz Netflix cache alırlar, Netflix ucuz çatdırılma alır — qarşılıqlı faydalı.
- Titus: Netflix-in container platforması. Mesos üzərində qurulub, sonradan genişləndirilib. Yetkin Kubernetes-dən əvvəl mövcud olub. Batch və servisləri işlədir.
- Spinnaker: onların multi-cloud deploy aləti, açıq mənbə edilib. Asgard-dan (onların köhnə AWS-only deploy aləti) yaranıb. Pipeline-lar bake → canary → red/black deploy-dan ibarətdir.
- Red/Black deploy-lar: köhnəsinin yanında yeni versiya ilə yeni ASG (auto-scaling group) qaldırın, trafiki köçürün, sonra köhnəni söküb atın. Rolling-dən daha təhlükəsizdir.
- Chaos engineering — Simian Army:
  - Chaos Monkey — production-da instance-ları təsadüfi öldürür
  - Latency Monkey — latency yeridir
  - Chaos Gorilla — bütöv AZ-ləri öldürür
  - Chaos Kong — bütöv bir region-u öldürür
  - Məqsəd: əgər sisteminiz prod-da bilərəkdən yeridilən xaosdan sağ çıxa bilmirsə, real sıradan çıxmalara da dözməyəcək

## Arxitekturanın təkamülü

- 1997–2007: DVD günlərində Oracle + monolitik web tətbiqi (Los Gatos-dakı data mərkəzlərindən göndərilib)
- 2007–2009: streaming monolitik Java/Tomcat stack üzərində işə salınır, hələ də öz data mərkəzlərində Oracle ilə
- 2008: Oracle korrupsiya hadisəsi → buluda keçmək qərarı
- 2009–2010: ilk servislər AWS-ə köçürüldü; Cassandra qiymətləndirilməsi başlayır
- 2010–2013: microservices dekompozisiyası; Netflix OSS dövrü başlayır (Hystrix, Eureka, Zuul)
- 2013–2016: tam AWS miqrasiyası tamamlanır (Avqust 2016); >1000 servis; Simian Army yetkin; Spinnaker işə salınır
- 2016–2020: Open Connect yetkinləşir; Atlas, Mantis, Keystone; Titus container platforması
- 2020–indi: konsolidasiya, SRE yetkinliyi, ML-first (Metaflow); resilience4j Hystrix-i əvəz edir; experience təbəqəsi üçün GraphQL/Federation

## Əsas texniki qərarlar

### 1. Oracle-dan Cassandra-ya keçmək (2010)
- Problem: Oracle uyğun qiymətə multi-region active-active yazılar edə bilmirdi. 2008-ci ildə baş verən database korrupsiya hadisəsi günlərlə kəsintiyə səbəb oldu.
- Seçim: tənzimlənə bilən consistency, multi-master replikasiya və xətti miqyaslanma ilə Cassandra.
- Trade-off: eventual consistency o deməkdir ki, yazılışdan saniyələr içində köhnə data oxuya bilərsiniz. Komanda buna uyğun dizayn etməyi öyrənməli idi.
- Nəticə: Netflix DB-lə əlaqəli kəsintilər olmadan yüz milyonlarla istifadəçiyə miqyaslandı. Cassandra əməliyyatları əsas bacarığa çevrildi; bir çox Cassandra committer-i işə götürürlər.

### 2. Open Connect CDN-in qurulması
- Problem: Netflix miqyasında video üçüncü tərəf CDN-lərdə (Akamai, Limelight) böyük pul qiymətləndirilərdi. Təkcə AWS egress milyardlar olacaqdı.
- Seçim: öz CDN avadanlığını qur və onu ISP şəbəkələrinin içinə yerləşdir. ISP-lər üçün pulsuz, Netflix üçün ucuz.
- Trade-off: avadanlıq, logistika və partnyorluqlara böyük başlanğıc investisiya. Box-ları qlobal olaraq göndərmək lazımdır.
- Nəticə: Netflix Open Connect-dən çox az bayt başına xərclə 200+ Tbps çatdırır; həmçinin cache-lər istifadəçilərə yaxın olduğu üçün təcrübə keyfiyyətini yaxşılaşdırır.

### 3. Mədəniyyət kimi chaos engineering
- Problem: bir neçə region üzrə 700+ servisdə nasazlıqlar daimi lakin böyük bir şey sınana qədər görünməzdir.
- Seçim: production-da bilərəkdən nasazlıq yerit (Chaos Monkey, Chaos Gorilla, Chaos Kong).
- Trade-off: qorxulu. Təhlükəsiz etmək üçün mühəndislik nizam-intizamı və yaxşı observability tələb edir.
- Nəticə: sənaye miqyaslı dəyişiklik. "Chaos engineering" indi real bir elmdir. Netflix artıq nadir hallarda tam regional kəsintilər yaşayır, çünki komandalar defolt olaraq nasazlıq üçün dizayn edir.

### 4. Deploy-lar üçün Spinnaker
- Problem: yüzlərlə servis, multi-region, bir çox komanda. Deploy-lar təhlükəsiz, audit edilə bilən və ardıcıl olmalı idi.
- Seçim: canary və red/black deploy-ları ilə pipeline əsaslı deploy aləti qur və onu açıq mənbə et.
- Trade-off: Spinnaker idarə etmək üçün ağırdır; kiçik təşkilatlar onu həddindən artıq tapır.
- Nəticə: geniş istifadə olunan OSS deploy platformasına çevrildi. Daxildə, Netflix gündə minlərlə dəyişikliyi əminliklə göndərir.

### 5. Hystrix-dən resilience4j-ə keçmək
- Problem: Hystrix işləyirdi lakin maintenance rejiminə qoyuldu. Thread-pool modeli async/reactive-ə o qədər də uyğun deyildi.
- Seçim: daha modulyar, reactive-dostu və fəal saxlanan resilience4j-ə köçmək.
- Trade-off: bir çox servis üzrə miqrasiya xərci; kitabxanalar 1:1 deyil.
- Nəticə: kod Reactor/RxJava ilə daha idiomatik olur; indi resilience4j ətrafında fəal icma var.

## Müsahibədə necə istinad etmək

Netflix-in stack-i əsasən JVM-dir, ona görə kodlarını kopyala-yapışdır etməyəcəksiniz. Lakin bir neçə prinsip birbaşa Laravel tətbiqlərinə tətbiq olunur:

1. Circuit breaker-lər vacibdir — Laravel tətbiqiniz xarici API-yə (ödəniş, mail, axtarış) zəng edirsə, zənglər ətrafında circuit breaker yerləşdirin. PHP üçün `ackintosh/ganesha` kimi kitabxanalar və ya Guzzle ətrafında kiçik custom wrapper kömək edə bilər. Məqsəd: əgər downstream yavaşdırsa/xarabdırsa, queue worker-ləri yığmaq əvəzinə tez uğursuz olun.

2. Çoxlu təbəqələrdə aqressiv cache edin — Netflix-də EVCache var (onlarla TB). Laravel tətbiqinizdə OPcache → Redis → MySQL query cache təbəqələri ola bilər. "Cache soyuq olmasa DB-yə dəymə" nümunəsi universaldır.

3. Mümkün olduqda eventual consistency üçün dizayn edin — hər şeyi DB transaction-larında sarmaqdansa, queue job-ları + idempotent handler-lər istifadə edə bilərsinizmi deyə soruşun. Laravel-in unique job-lar və düzgün retry backoff ilə queue sistemi yaxşı uyğunlaşır.

4. Feature flag-lər və canary deploy-lar — Spinnaker-in canary ideyasını feature flag-lərdən (Flagsmith, LaunchDarkly və ya sadə konfiqurasiya) istifadə edərək təqlid edə bilərsiniz. 1% → 10% → 100% roll out edin. Laravel Pennant son birinci tərəf seçimdir.

5. Birinci dərəcəli vətəndaş kimi observability — metrics (Prometheus/Atlas tipli), strukturlaşdırılmış log-lar və distributed tracing (OpenTelemetry). Laravel Telescope dev alətidir, lakin prod-da real APM (New Relic, Datadog) və ya OSS (Prometheus + Grafana + Tempo) istəyərsiniz.

6. Chaos Monkey olmadan da Chaos tipli düşüncə — Redis söndükdə, queue worker iş ortasında öldükdə, xarici API bir saat 503 qaytardıqda nə baş verdiyini test edin.

7. Microservices-ə çox erkən keçməyin — Netflix onları onlarla milyon istifadəçidən sonra qazandı. Yaxşı məhdudlaşdırılmış modullara malik Laravel monolith əksər məhsullar üçün çox uzağa miqyaslanır.

## Əlavə oxu üçün

- Netflix Tech Blog (illərlə olan yazılar, netflixtechblog.com)
- "Chaos Engineering" (O'Reilly kitabı Casey Rosenthal və Nora Jones tərəfindən)
- "Mastering Chaos — A Netflix Guide to Microservices" (Josh Evans çıxışı)
- "Node.js in Flames" (Netflix Tech Blog)
- "Completing the Netflix Cloud Migration" (Yuri Izrailevsky, 2016)
- "Scaling Netflix on AWS" (AWS re:Invent çıxışları, bir neçə il)
- "Lessons Netflix Learned from the AWS Outage" (2011)
- "Active-Active for Multi-Regional Resiliency" (Netflix Tech Blog)
- "Open Connect" icmalı (Netflix texniki ağ sənəd)
- "Evolution of Netflix Conductor" (workflow engine)
- Spinnaker rəsmi sənədləri
- Metaflow kitabı və sənədləri (ML workflow-ları üçün)
