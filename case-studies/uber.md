# Uber

## Ümumi baxış

Uber qlobal mobillik və çatdırılma platformasıdır, 2009-cu ildə San-Fransiskoda Travis Kalanick və Garrett Camp tərəfindən quruldu. SF-də qara avtomobil xidməti kimi başladı və sürücü tutmaya (UberX), yemək çatdırılmasına (Uber Eats), yük daşınmasına və müxtəlif digər məhsullara qədər böyüdü. Uber texnologiya sənayesində son dərəcə aqressiv miqyaslama, polyglot mühəndisliyi və çoxlu açıq mənbə alətləri ilə tanınır — bəziləri çox təsirli (H3, Cadence/Temporal, Hudi).

- ~150M aylıq aktiv platforma istehlakçısı (2024)
- Qlobal olaraq gündə milyonlarla səfər
- 10M+ sürücü / kuryer
- 2019-dan public şirkət (UBER)
- 70+ ölkədə, minlərlə şəhərdə fəaliyyət göstərir

Əsas tarixi anlar:

- 2009 — UberCab kimi quruldu
- 2012 — UberX (aşağı qiymətli seçim) işə salındı
- 2014–2016 — geniş beynəlxalq ekspansiya, Uber Eats işə salınır
- 2015 — Uber Engineering 1000 mühəndisi keçir; bir çox OSS layihələri buraxılır (Ringpop, Cherami, TChannel)
- 2018 — "çox microservice" olduqlarını (4,000+) ictimai şəkildə etiraf etdi
- 2019 — IPO
- 2020 — Cadence workflow engine müstəqil şirkət kimi Temporal-a fork edildi
- 2022 — domain-oriented microservices (DOMA) yanaşmasına keçdi

## Texnologiya yığını

| Layer | Technology | Niyə |
|-------|-----------|-----|
| Languages | Python, Go, Node.js, Java, Scala, C++ | Polyglot — hər birinin rolu var; yüksək konkurentlikli servislər üçün Go, ML/data üçün Python, big data üçün Java/Scala |
| Web frameworks | Go: Fx (Uber tərəfindən hazırlanıb), Java: Dropwizard + Spring, Python: Tornado + Flask, Node.js: Express + Ringpop | Komanda və dövrə görə fərqli stack-lər |
| Primary DB | MySQL (Schemaless vasitəsilə), Cassandra, Postgres | Schemaless Uber-in shard-lanmış MySQL təbəqəsidir; bəzi iş yükləri üçün Cassandra |
| Secondary DB | ZooKeeper, Redis, etcd | Koordinasiya və cache |
| Cache | Redis, Memcached, per-service in-memory | Servis-lokal cache-lər + isti data üçün paylaşılan Redis |
| Queue/messaging | Apache Kafka (çox ağır), Cadence (indi workflow-lar üçün Temporal) | Kafka mərkəzi event bus-dur, gündə trilyonlarla mesaj |
| Search | Elasticsearch | Sürücü/restoran/axtarış indeksləşdirilməsi |
| Data lake | Apache Hudi (Uber-də icad olunub), HDFS, Presto, Spark | İnkremental data lake yenilənmələri üçün Hudi |
| ML platform | Michelangelo (Uber tərəfindən hazırlanıb) | Uclu-ucluq ML platforması |
| RPC | Apache Thrift (əvvəllər), gRPC, TChannel (Uber tərəfindən hazırlanıb) | Sxem üçün Thrift, multiplekslənmiş RPC üçün TChannel |
| Infrastructure | Öz data mərkəzləri + AWS/GCP hibrid, Mesos → Kubernetes | Uber miqyasında pik xərc saf buludu çox bahalı edirdi; hibrid pul qənaət edir |
| Orchestration | Cadence (workflow-lar), Peloton → Kubernetes | Peloton Uber-in Mesos əsaslı scheduler-i idi |
| Geospatial | H3 (Uber tərəfindən hazırlanıb, OSS) | Yerlər üçün altıbucaqlı indeksləşdirmə, lat/lng kvadratlarından daha yaxşıdır |
| Monitoring | M3 (metrics, OSS), Jaeger (distributed tracing, OSS, CNCF-ə bağışlanıb) | Şirkət daxilində qurulub, açıq mənbə edilib |

## Dillər — Nə və niyə

Uber məşhur şəkildə polyglot-dur. Müxtəlif komandalar müxtəlif vaxtlarda müxtəlif dilləri seçdi və şirkət birini məcbur etmək əvəzinə onunla yaşadı.

Python orijinal dil idi. İlk dispatch sistemi və bir çox erkən servis Python (Tornado) idi. Data science və ML hələ də əsasən Python-da işləyir. Python həmçinin bir çox daxili alət və Airflow tipli pipeline-lar üçün istifadə olunur.

Go 2014-2015 ətrafında yeni yüksək performanslı servislər üçün defolt oldu. Dispatch sistemi Go-da (Node.js ilə yanaşı) yenidən yazıldı, çünki Python real vaxtda sıx şəhərlərdə sürücüləri və sərnişinləri uyğunlaşdırmaq üçün lazım olan konkurensiyanı idarə edə bilmirdi. Go-nun goroutine-ləri, aşağı yaddaş xərci və sürətli başlanğıcı onu ideal etdi. Uber Fx (dependency injection) adlı öz Go framework-unu qurdu və açıq mənbə etdi.

Node.js edge-səviyyə servisləri üçün istifadə olundu (Ringpop cluster üzvlüyü, bəzi gateway və API servisləri). Real-time sürücü yerləşmə push servisi bir nöqtədə Node-u çox istifadə edirdi.

Java və Scala big-data iş yükləri üçün istifadə olunur. Scala data pipeline dünyasındadır (Spark, Flink tipli alətlər). Java bir çox backend servisi üçün istifadə olunur, xüsusilə sonrakı komandalardan və ya alınmış şirkətlərdən.

C++ performans-kritik kod yollarında görünür (routing algoritmləri, H3 kitabxanası nüvəsi).

## Framework seçimləri — Nə və niyə

- Fx (Go) — Uber-in Go microservices üçün dependency injection framework-u. Açıq mənbə edilib. Ən populyar Go DI kitabxanalarından birinə çevrildi.
- Dropwizard + Spring (Java) — standart JVM microservice starter.
- Tornado, Flask (Python) — async HTTP və daha kiçik web servisləri.
- Ringpop (Node.js/Go) — tətbiq-səviyyəli sharding və cluster üzvlüyü üçün SWIM əsaslı gossip kitabxanası; Uber bunu service mesh-lər mövcud olmazdan əvvəl qurdu.
- TChannel — Uber-in RPC protokolu, tek bir TCP bağlantısında multiplekslənmiş sorğuları dəstəkləyir, izləmə daxildir. Sonradan bir çox komanda gRPC-yə keçdi.
- Thrift — servis sxemləri və IDL üçün çox istifadə olunur.

## Verilənlər bazası seçimləri — Nə və niyə

### Schemaless vasitəsilə MySQL
Uber MySQL ilə başladı amma tez miqyas limitlərinə çatdı. Tamamilə NoSQL sisteminə keçmək əvəzinə Schemaless qurdular — MySQL üzərində append-only semantika və horizontal sharding təmin edən data store. Schemaless MySQL-i opaq binar hüceyrələr üçün storage engine kimi qəbul edir, onların öz sharding və replikasiyası üstdədir. Bu onlara MySQL-in əməliyyat tanışlığını saxlamaqla yazıları horizontal olaraq miqyaslamağa imkan verdi.

Niyə: MySQL döyüş sınağından keçmişdi, ops komandası onu bilirdi. O dövrdə (2014) NoSQL-də çoxlu kəskin kənarlar vardı. Üstündə bir təbəqə qurmaq onlara bildiklərini tərk etmədən horizontal miqyas əldə etməyə imkan verdi.

### Cassandra
Bəzi yüksək yazma ötürmə qabiliyyətli use case-lər və eventual consistency-nin məqbul olduğu servislər üçün istifadə olunur.

### Postgres
Bir neçə servisdə istifadə olunur, xüsusilə miras qalmış və ya alınmışlar.

### Riak
Bir müddət istifadə edildi, sonra silindi. Uber niyə olduğunu sənədləşdirdi — əməliyyat mürəkkəbliyi və kifayət qədər fayda olmaması.

### ZooKeeper və etcd
Koordinasiya, leader election, konfiqurasiya üçün.

### Miqrasiya hekayələri
- MySQL → Schemaless: 2014-2016 ətrafında baş verdi. Schemaless defolt OLTP store oldu.
- Postgres yeni servislər üçün nəzərdən keçirildi və əsasən rədd edildi, çünki ops tanışlığı MySQL idi.
- Bəzi servislər Cassandra-nı DynamoDB tipli idarə olunan store-lara və ya öz key-value fabric-lərinə köçürdü.

## Proqram arxitekturası

Uber-in arxitekturası microservices-dir. Pikdə Uber-in 4,000-dən çox microservice-i var idi. Onlar ictimai şəkildə bunun çox olduğunu etiraf etdilər — bu qədər servis arasında dependency-ləri, deploy-ları və oncall-ları koordinasiya etmək vergi oldu. 2020 ətrafından DOMA — Domain-Oriented Microservices Architecture — tətbiq etməyə başladılar, bu servisləri biznes domenlərinə (dispatch, ödənişlər, identity, səfərlər, sürücü onboarding və s.) daha aydın sərhədlərlə və hər domen daxilində daha az, daha böyük servislərlə qruplaşdırır.

```
+--------------------------------------------------------------+
|                     Rider app / Driver app                   |
+--------------------------------------------------------------+
                              |
                        (HTTPS, mobile)
                              |
+--------------------------------------------------------------+
|           Edge Gateway (Go + Node.js, Ringpop-backed)        |
+--------------------------------------------------------------+
                              |
             +----------------+----------------+
             |                |                |
+------------v----+ +---------v-------+ +------v-----------+
|  Dispatch       | |  Payments       | |  Identity/Auth   |
|  domain (Go)    | |  domain (Java)  | |  domain (Java)   |
|  matching       | |  fraud, cards   | |  users, sessions |
+--------+--------+ +---------+-------+ +--------+---------+
         |                    |                  |
+--------v--------------------v------------------v-------------+
|       Shared infra: Schemaless (MySQL shards), Cassandra,    |
|       Kafka, Redis, H3 geospatial, Cadence workflows         |
+--------------------------------------------------------------+
                              |
+--------------------------------------------------------------+
|   Data lake: Apache Hudi + HDFS + Presto + Spark             |
|   ML: Michelangelo                                           |
+--------------------------------------------------------------+
                              |
+--------------------------------------------------------------+
|   Own data centers + AWS / GCP regions (hybrid)              |
|   Mesos (Peloton) → Kubernetes                               |
+--------------------------------------------------------------+
```

Dispatch sistemi Uber-in ürəyidir — sərnişinləri sürücülərlə bir neçə yüz millisaniyədə uyğunlaşdırır, isti şəhərlərdə milyonlarla eyni vaxtda sessiyaları idarə edir. H3 (altıbucaqlı geospatial indeks), Go servisləri və in-memory data strukturlarından istifadə edir. Əvvəlcə Python monolith idi, indi Go + bir az Node.js.

## İnfrastruktur və deploy

- Hibrid: bazis tutum üçün öz data mərkəzləri (sabit vəziyyətdə daha ucuz) + burst və bəzi servislər üçün AWS/GCP
- Orchestration: Uber Peloton-u (Mesos üzərində) qurdu, çünki Uber miqyasında Mesos batch + online iş yükləri arasında daha yaxşı bin-packing verirdi. K8s yetkinləşdikcə Kubernetes qəbulu böyüdü.
- Deploy: CI/CD pipeline-ları (ruhən Spinnaker-ə bənzər), canary deploy-lar standart
- Monitoring: M3 (açıq mənbə edilib) Uber-in Prometheus uyğun metrics platformasıdır, trilyonlarla data nöqtəsini saxlamaq üçün qurulub; distributed tracing üçün Jaeger (CNCF-ə bağışlanıb və indi sənaye standartıdır); log-lar üçün ELK
- Workflow-lar: Cadence uzun müddət davam edən biznes proseslərini (səfər həyat dövrləri, ödəniş təkrarları, arxa plan iş) işlədir. Əsas mühəndislər Temporal.io qurmaq üçün ayrıldıqda Cadence → Temporal oldu.

## Arxitekturanın təkamülü

- 2009–2012: bir provayderdən icarə edilmiş bare metal üzərində bir neçə MySQL instance-da Python monolith
- 2012–2014: monolith gərginləşir; ilk microservices görünür; dispatch hələ Python
- 2014–2016: Go qəbul dalğası; Schemaless qurulur; Ringpop, TChannel, Jaeger, Cherami buraxılır
- 2016–2018: 1000+ servis; over-microservicing problemə çevrilir; platform komandaları (Eng Effectiveness) yaranır
- 2018–2020: Cadence böyüyür; Hadoop/HDFS yeniləmə problemlərini həll etmək üçün Hudi icad olunur; Michelangelo yetkinləşir
- 2020–indi: DOMA — microservices-i domenlərə birləşdirmək; Go + Java stack-lərini standartlaşdırmaq; hər yerdə Kubernetes; Temporal spin-off

## Əsas texniki qərarlar

### 1. Dispatch-i Python-dan Go + Node.js-ə yenidən yazmaq
- Problem: Python (Tornado) dispatch sıx şəhərlərdə real vaxt uyğunlaşdırmasına ayaqlaşa bilmirdi. Hər sorğu üçün çox yavaş, GIL bottleneck, ağır yaddaş.
- Seçim: Ringpop ilə cluster sharding üçün core dispatching məntiqi üçün Go; bəzi IO-ağır kənarlar üçün Node.js.
- Trade-off: Python mühəndislərini Go-da hazırlamaq lazım idi; idarə etmək üçün iki runtime.
- Nəticə: miqyasda alt-saniyəli uyğunlaşdırma, pik hadisələrdə (Yeni il gecəsi, konsertlər, pis hava) sabit.

### 2. MySQL üzərində Schemaless qurmaq
- Problem: MySQL tək instance-lar vertikal limitlərə çatdı; vanil sharding kövrək idi; o vaxtlar NoSQL-ə kifayət qədər etibar edilmirdi.
- Seçim: MySQL-i custom sharding + append-only data modeli (Schemaless) ilə sarmaq.
- Trade-off: çoxlu custom proqram qurdular; data modeli relational-dan daha çox KV store-a bənzəyir.
- Nəticə: sadəcə shard əlavə etməklə miqyaslandı; MySQL nüvədə qaldı; ops komandası təcrübəsini saxladı.

### 3. H3 — altıbucaqlı geospatial indeks
- Problem: kvadrat plitələr (S2, Mercator grid-lər kimi) enliliyə görə təhrif olunur və fərqli qonşu saylarına malikdir, bu da algoritmləri çaşdırır.
- Seçim: Yer kürəsini bir neçə qətnamə ilə əhatə edən altıbucaqlı grid; hər hüceyrənin dəqiq 6 qonşusu var (yaxşı, demək olar ki — qlobal olaraq 12 beşbucaqlı).
- Trade-off: implementasiyada bir qədər mürəkkəblik; beşbucaqlılar xüsusi işləmə tələb edir.
- Nəticə: H3 açıq mənbə edildi; mobillik, logistika, çatdırılma şirkətlərində geniş istifadə olunur; daha təmiz qonşu algoritmləri.

### 4. Workflow-lar üçün Cadence / Temporal
- Problem: Uber-də biznes prosesləri (səfər həyat dövrləri, geri qaytarmalar, sürücü arxa yoxlamaları) uzun müddət davam edir, retry-ləri əhatə edir, davamlı vəziyyətə ehtiyac duyur.
- Seçim: Cadence qur — kod-olaraq-workflow-lar, vəziyyət Cassandra-da saxlanılan, history replay modeli olan davamlı workflow engine.
- Trade-off: mühəndislər üçün sıldırım öyrənmə əyrisi; başqa infrastruktur asılılığı.
- Nəticə: Cadence 2019/2020-də yaradıcıları tərəfindən Temporal-a fork edildi; Temporal indi sənayedə əsas workflow engine-dir.

### 5. "Çox microservice" olduğunu etiraf etmək və DOMA-ya keçmək
- Problem: 4,000+ microservice böyük koqnitiv yük, qeyri-müəyyən sahiblik, çətin debugging yaratdı.
- Seçim: servisləri domenlərə qruplaşdırın, mümkün olduqda birləşdirin, domenlər arasında daha aydın API-lər.
- Trade-off: böyük yenidən təşkilatlanma; komandalar arası koordinasiya.
- Nəticə: daha az on-call kabusları, domen komandalarında daha aydın karyera böyüməsi.

## PHP/Laravel developer üçün dərs

1. Sadə başlayın, yalnız ağrı real olduqda bölün. Uber-in ilk dispatch-i Python monolith idi. Laravel tətbiqləri bölmənin ağrısı (koqnitiv, miqyaslama, deploy) birlikdə qalmağın ağrısını açıq şəkildə üstələyənə qədər monolitik qalmalıdır.

2. Polyglot səbəb varsa yaxşıdır — lakin defolt saxlayın. Uber indi əsas backend kimi Go + Java-ya malikdir. Laravel dükanı üçün bir çox isti yol üçün Go microservice yazmaq yaxşıdır, lakin ehtiyac olmadan texnologiya səpələməyin.

3. Uzun müddət davam edən biznes prosesləri üçün Workflow-lar > ad-hoc queue-lar. Çox mərhələli bir proses qurursunuzsa (onboarding, ödəniş, 3 gündə 5 təkrar ilə geri qaytarma), Temporal-a baxın, və ya Laravel-native qalacaqsınızsa, diqqətlə queue job-ları + state machine-lərlə (Laravel-in job-ları + Saloon pattern-ləri və ya `spatie/laravel-model-states`) modelləşdirin.

4. Geospatial düşüncəsi vacibdir. Laravel tətbiqiniz hər hansı yer əsaslı xüsusiyyətlər edirsə, xam lat/lng ilə yenidən kəşf etməkdənsə H3-ə baxın (PHP binding-ləri var).

5. Kafka və ya SNS/SQS ilə davamlı event-lər. Uber-in event bus-u birləşdirici toxumadır. Laravel-də queue-lu listener-lərlə event-lər istifadə edin və ya cross-service event-lər üçün SNS/SQS/Kafka ilə inteqrasiya edin.

6. Hər şeyi müşahidə edin. Onların stack-i M3 + Jaeger-dir; Laravel üçün Grafana Tempo ilə OpenTelemetry və ya SaaS APM (Datadog, New Relic) istifadə edə bilərsiniz. Servis sərhədlərini keçən request ID-ləri daxil edin.

7. Hazır məhsul uğursuz olanda öz alətinizi qurmaqdan qorxmayın — amma xərci ölçün. Uber çox şey qurdu. Əksər Laravel dükanları qurmamalıdır.

## Əlavə oxu üçün

- Uber Engineering Blog (eng.uber.com, bir çox dərin dalma yazıları)
- "Why We Built Uber Engineering's Infrastructure for Billions of Requests"
- "Domain-Oriented Microservice Architecture" (Uber engineering yazısı)
- "Designing Schemaless" (çox hissəli Uber blog)
- "H3: Uber's Hexagonal Hierarchical Spatial Index"
- "Cadence: Uber's Fault-Tolerant Workflow Orchestrator"
- "Hudi: Uber's Incremental Data Lake"
- "Michelangelo: Uber's Machine Learning Platform"
- "Jaeger: Distributed Tracing at Uber" (çıxışlar və yazılar)
- "Peloton: Uber's Unified Resource Scheduler"
- "Ringpop: Uber's Scalable Application-Layer Sharding"
- Cadence və Temporal mənşəyi haqqında Maxim Fateev çıxışları
