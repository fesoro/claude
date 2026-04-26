# LinkedIn (Architect)

## Ümumi baxış

LinkedIn dünyanın ən böyük peşəkar şəbəkə və məşğulluq platformasıdır. 2002-ci ildə qurulub və 2003-də Reid Hoffman və həmtəsisçiləri tərəfindən işə salındı. LinkedIn insanlara onlayn özgəçmişlər qurmağa, həmkarları ilə əlaqə saxlamağa, şirkətləri izləməyə, iş axtarmağa və peşəkar kontent istehlak etməyə imkan verir. Dekabr 2016-da Microsoft tərəfindən $26.2B-a alındı — hələ də ən böyük texnologiya alışlarından biri.

- 1B+ qeydiyyatlı üzv (2024)
- Milyonlarla aktiv şirkət, iş elanı və yazı
- 200+ ölkədə fəaliyyət göstərir
- B2B gəlir modeli: reklam, işə götürmə alətləri (LinkedIn Recruiter), öyrənmə (LinkedIn Learning), abunəliklər
- 2016-dan Microsoft-un hissəsi

Əsas tarixi anlar:

- 2003 — ictimai işə salındı
- 2008 — əsas böyümə; 10M istifadəçiyə çatdı
- 2010 — Leo monolith limitlərə çatır; dekompozisiya qərarı
- 2011 — LinkedIn-də Kafka icad olundu
- 2012 — Rest.li açıq mənbə edildi
- 2013 — Voldemort, Samza, Espresso elan olundu
- 2014 — Pinot ictimai müzakirə olundu
- 2015 — monolith miqrasiyasının əksəriyyəti tamamlandı
- 2016 — Microsoft LinkedIn-i alır
- 2018 — davamlı təkamül, graph database təkmilləşdirmələri

## Texnologiya yığını

| Layer | Technology | Niyə |
|-------|-----------|-----|
| Languages | Java (ağır), Scala, Python, JavaScript/TypeScript, bir az C++ | JVM dominant; streaming üçün Scala |
| Web framework | Rest.li (LinkedIn tərəfindən qurulub), Play Framework (Scala) | REST servisləri üçün Rest.li, bəziləri üçün Play |
| Primary DB | Espresso (LinkedIn tərəfindən qurulmuş MySQL üzərində paylanmış document DB) | Storage engine kimi MySQL, custom API və consistency təbəqəsi |
| Secondary DB | Voldemort (KV), Oracle (legacy, aradan qaldırılır), Pinot (analitika) | Fərqli data formaları |
| Cache | Memcached, bəzi yerlərdə Couchbase | Klassik cache |
| Queue/messaging | Apache Kafka (LinkedIn-də İCAD OLUNUB) | Mərkəzi event onurğası |
| Stream processing | Apache Samza (LinkedIn tərəfindən qurulub), Kafka Streams | Real-time stream processing |
| Search | Galene (Lucene əsaslı, daxili) | Profillər, işlər, yazılar üzrə axtarış |
| Analytics | Apache Pinot (LinkedIn tərəfindən qurulub, indi Apache) | Real-time OLAP sorğuları |
| Data lake | Apache Iceberg, HDFS, Spark | Modern data lake pattern-ləri |
| Infrastructure | Öz data mərkəzləri + Azure (Microsoft-dan sonra) | Tarixən on-prem |
| Deployment | LPS (daxili), konteynerlər, Kubernetes | Daxili platform engineering |
| Frontend | Tarixən Ember.js (ağır), yeni stack-lər yaranır | Erkən Ember seçdi; sonra miqrasiya xərcini ödədi |

## Dillər — Nə və niyə

Java LinkedIn-in dominant backend dilidir. Leo, orijinal monolith, Java + Spring + Tomcat idi. LinkedIn servislərə dekompoze edildikdə, yeni servislərin əksəriyyəti hələ də Java idi. JVM stack enterprise ehtiyaclarına uyğun idi.

Scala streaming və data infrastruktur-da çox istifadə olunur — Kafka orijinal olaraq Scala-da yazılmışdı (baxmayaraq ki, Kafka-nın çoxu indi Java-dır). Samza və bir çox Spark job-u Scala-dır. Play framework (həm də Scala əsaslı) bəzi servislər üçün istifadə olunub.

Python data science, ML və avtomatlaşdırmada istifadə olunur.

JavaScript və TypeScript frontend-i gücləndirir. LinkedIn məşhur şəkildə erkən Ember.js-ə tam bağlandı və bundan sonra modernləşdirir.

C++ bəzi performansa həssas sahələrdə istifadə olunur, xüsusilə xidmət infrastrukturunun hissələri və Pinot-un serveri.

## Framework seçimləri — Nə və niyə

- Rest.li — LinkedIn-in öz REST framework-u (Java). Açıq mənbə edilib. Güclü tipləndirmə, sxem təkamülü, səhifələmə, qismən yeniləmələri dəstəkləyir. Resource modelləşdirməsi haqqında fikirlidir.
- Play Framework — Scala web framework, bəzi servislər üçün istifadə olunur.
- Kafka — event streaming platforması, 2010–2011 ətrafında Jay Kreps, Jun Rao, Neha Narkhede tərəfindən LinkedIn-də icad olundu. Apache-a bağışlandı. İndi event streaming üçün de-fakto sənaye standartıdır. Confluent onu ticariləşdirmək üçün LinkedIn-dən ayrıldı.
- Samza — stream processing framework, LinkedIn-də icad olunub, Kafka ilə sıx işləyir. Apache layihəsidir.
- Databus — change data capture (CDC) sistemi. Orijinal olaraq Oracle-dan dəyişiklikləri stream etdi, sonradan MySQL-dən.
- Pegasus — Rest.li-nin altında yatan sxem sistemi (Avro/Protobuf kimidir lakin LinkedIn-in özünəməxsus).

## Verilənlər bazası seçimləri — Nə və niyə

### Espresso
LinkedIn-in custom paylanmış document verilənlər bazası. Storage engine kimi MySQL üzərində qurulub, replikasiya və sharding daha yüksək təbəqədə idarə olunur. Təmin edir:

- Document-oriented API (JSON tipli)
- Bölmə daxilində güclü consistency
- Bölmələmə vasitəsilə horizontal miqyaslama
- Databus (CDC) vasitəsilə change stream

Niyə qurdular: tək başına MySQL onların iş yüklərini miqyasladıra bilmirdi və o vaxtkı hazır NoSQL düzgün consistency təminatları təmin etmirdi. Etibar etdikləri MySQL-i nüvə kimi istifadə etdilər.

### Voldemort
LinkedIn-in KV store-u, Amazon-un Dynamo məqaləsindən çox ilhamlanıb. Açıq mənbə edilib. Əslində DynamoDB-nin ictimai buraxılışından əvvəl gəlir. Profil baxışları, cache-lənmiş axtarışlar kimi şeylər üçün istifadə olunur. Tənzimlənə bilən consistency.

### Oracle
Leo üçün çox orijinal DB Oracle idi. İllərlə istifadə olundu. Espresso və digər store-ların xeyrinə yavaş-yavaş aradan qaldırıldı. Databus orijinal olaraq Oracle CDC-ni digər sistemlərə stream etmək üçün mövcud idi.

### Pinot
Real-time OLAP verilənlər bazası. "Son 7 gündə profilimə neçə nəfər baxdı" kimi sorğulara milyardlarla sətir üzrə millisaniyə gecikməsi ilə xidmət edir. LinkedIn-də icad olunub, Apache Foundation-a bağışlanıb. Digər şirkətlər (Uber, Stripe) onu çox qəbul etdilər.

### Graph saxlama
LinkedIn-in sosial qrafı (əlaqələr, təsdiqlər, izləmələr) nəhəng bir qrafdır — yüzlərlə milyard ucu. Hazır graph DB istifadə etmək əvəzinə öz KV və document store-ları üzərində custom graph infrastrukturu qurdular.

### Miqrasiya hekayələri
- Oracle → Espresso: çox illik layihə; bir çox servis cədvəl-cədvəl dual-write sonra cut-over ilə miqrasiya edildi
- Leo monolith → SOA: 2010 ətrafında başladı, 2015-ə əsasən tamamlandı

## Proqram arxitekturası

LinkedIn-in arxitekturası bu gün Rest.li API-ləri və Kafka event-ləri ilə bağlı minlərlə Java/Scala servisi ilə böyük miqyaslı SOA-dır.

```
+-----------------------------------------------------------+
|            Web, Mobile, API consumers                     |
+-----------------------------------------------------------+
                          |
+-----------------------------------------------------------+
|      Traffic layer (load balancers, API gateways)         |
+-----------------------------------------------------------+
                          |
     +--------------------+--------------------+
     |                    |                    |
+----v-----+      +-------v------+     +-------v------+
| Profile  |      | Feed/Pulse   |     | Messaging    |
| service  |      | service      |     | service      |
| (Java)   |      | (Java/Scala) |     | (Java)       |
+----+-----+      +------+-------+     +------+-------+
     |                   |                    |
+----v-------------------v--------------------v-------------+
|    Espresso clusters (document DB on MySQL)              |
|    Voldemort (KV)                                        |
|    Graph infrastructure                                  |
|    Pinot (real-time OLAP)                                |
|    Kafka (event backbone) + Samza (stream processing)    |
|    Galene (search)                                       |
+----------------------------------------------------------+
                          |
+----------------------------------------------------------+
|   Hadoop + Iceberg data lake, Spark for batch            |
|   ML platforms (Pro-ML, notebooks)                       |
+----------------------------------------------------------+
                          |
+----------------------------------------------------------+
|  Own data centers (historical) + Azure (post-Microsoft)   |
+----------------------------------------------------------+
```

Kafka hər şeyin mərkəzində yerləşir: hər əhəmiyyətli fəaliyyət (profil redaktəsi, yeni əlaqə, göndərilən mesaj, feed yazısı) Kafka event-idir. Bir çox downstream sistem (axtarış indeksləşdirilməsi, analitika, bildirişlər, ML xüsusiyyətləri) abunə olur. Bu "log-oriented" arxitektura tam olaraq Jay Kreps-in "The Log: What every software engineer should know about real-time data's unifying abstraction" essesində təsvir etdiyi şeydir.

## İnfrastruktur və deploy

- Tarixən on-prem: LinkedIn böyük data mərkəzləri işlədirdi (məs. Oregon, Texas, Singapur)
- Microsoft alışından sonra: on-prem + Azure hibridi; bəzi iş yükləri Azure-a köçür
- Yeni iş yükləri üçün konteynerlər + Kubernetes
- Deploy-lar üçün daxili platform alətləri (LPS)
- Observability: daxili metrics platforması; dashboard-lar üçün InGraphs
- Developer productivity-ə ağır investisiya (LinkedIn Engineering blogunda bu barədə tez-tez yazılır)

## Arxitekturanın təkamülü

- 2003–2008: Leo — Oracle ilə tək Java monolith. Yaxşı işlədi, komandaya məhsula fokuslanmağa icazə verdi.
- 2008–2010: Leo yük altında çatlayır. Deploy-lar risqli, dependency cəhənnəmi, DB mübarizəsi.
- 2010–2013: SOA dekompozisiyası başlayır. Kafka icad olunur (2010–2011). Voldemort, Espresso qurulur. Oracle CDC üçün Databus.
- 2013–2016: Samza, Pinot, Rest.li daxildə geniş qəbul edilir və OSS edilir. Ember frontend dövrü.
- 2016: Microsoft LinkedIn-i alır.
- 2016–indi: Azure inteqrasiyası, Ember frontend-i modernləşdirmək, Oracle → Espresso miqrasiyasını davam etdirmək, ML-ə investisiya etmək (feed ranking, recruiter axtarışı), Pinot istifadəsi genişlənir.

## Əsas texniki qərarlar

### 1. Kafka-nı icad etmək
- Problem: LinkedIn-də eyni data-ya ehtiyacı olan bir çox data sistemi (DB, axtarış, data warehouse, tövsiyə sistemləri) var idi. Point-to-point pipeline-lar spagetti qarışıqlığı idi — N sistem N² inteqrasiya yaratdı.
- Seçim: hər sistemin yaza və oxuya biləcəyi paylanmış commit log (Kafka) qurmaq. Producer-ləri consumer-lərdən ayırmaq.
- Trade-off: yeni infrastruktur; at-least-once semantikası; consumer-lər replay idarə etməlidir.
- Nəticə: Kafka indi modern data infrastrukturunun müəyyənedici hissəsidir. Confluent ayrıldı. Apache Kafka demək olar ki, hər texnologiya şirkətində işləyir. Log-abstraksiya kimi indi standart düşüncədir.

### 2. Espresso — MySQL üzərində custom document DB
- Problem: Oracle bahalı idi, horizontal yaxşı miqyaslaşmırdı, satıcı kilidi; o vaxtkı hazır NoSQL onlara lazım olan consistency-ni vermirdi.
- Seçim: hər bölmə üçün əsas storage engine kimi MySQL ilə paylanmış document DB qurmaq; öz replikasiyasını, sharding-ini və API-sini əlavə etmək.
- Trade-off: böyük mühəndislik investisiyası; saxlamaq üçün mülkiyyət sistem.
- Nəticə: illər ərzində Oracle-ı əvəz etdi; LinkedIn-in əksəriyyətini bu gün işlədir; CDC üçün Databus ilə inteqrasiya olunub.

### 3. Pinot — real-time OLAP
- Problem: istifadəçilər "profilinizə kim baxıb" və "yazınıza impression-lar"-ın saniyələrdə yenilənməsini görmək istəyirdi. Ənənəvi data warehouse-lar batch idi; OLTP DB-lər səmərəli şəkildə aqreqatlaşdıra bilmirdi.
- Seçim: yeni ingest edilmiş data üzrə aşağı gecikməli aqreqat sorğuları üçün optimallaşdırılmış xüsusi sütunlu store qurmaq.
- Trade-off: işlətmək üçün başqa sistem; Pinot-un data modeli xüsusidir.
- Nəticə: LinkedIn UI-nin böyük hissələrini gücləndirir; Apache-a bağışlandı; Uber, Stripe, Meta və başqaları tərəfindən qəbul edildi.

### 4. Rest.li — fikirli REST framework-u
- Problem: bir çox komanda servis qurur, hər biri öz REST pattern-lərini icad edir; səhifələmə, qismən yeniləmələr, batching-in hamısı yenidən icad olunur.
- Seçim: güclü tipləndirmə, sxem təkamülü, standartlaşdırılmış pattern-ləri olan framework qurmaq.
- Trade-off: düz Spring-dən daha sıldırım öyrənmə əyrisi; fikirli.
- Nəticə: yüzlərlə LinkedIn servisi arasında ardıcıllıq; icma üçün açıq mənbə edildi.

### 5. Web frontend üçün Ember.js
- Problem: 2010-cu illərin əvvəllərində web tətbiqi üçün zəngin SPA lazım idi.
- Seçim: React hələ mövcud olmayanda Ember.js-ə böyük mərc qoymaq.
- Trade-off: sonradan React qalib gəldikdə, LinkedIn Ember bacarıqlarını saxlamalı və/və ya miqrasiya etməli idi.
- Nəticə: Ember onları uzaq götürdü; miqrasiya xərcləri realdır. Erkən framework mərcləri haqqında dərs.

## Müsahibədə necə istinad etmək

1. Event-lər onurğadır. LinkedIn-in "hər dəyişiklik bir Kafka event-dir" pattern-i birbaşa tərcümə olunur. Laravel-də: ikinci dərəcəli işi (indeksləşdirilmə, bildirişlər, analitika) edən queue-lu listener-lərlə `Event::dispatch(new ProfileUpdated(...))` istifadə edin. Böyüsəniz, həmin daxili event-lər SNS/SQS/Kafka üzərində real xarici event-lər ola bilər.

2. CDC (change data capture) güclüdür. Databus DB dəyişikliklərini downstream sistemlərə stream edir. Bunu Laravel-də model səviyyəsində `saved`/`updated`/`deleted` hook-ları ilə təkrarlaya bilərsiniz, queue-ya push edərək. Daha ağır lazım olsa, `debezium` kimi kitabxanalar MySQL binlog səviyyəsində CDC edir.

3. Real-time aqreqatlar fərqli saxlama tələb edir. Milyonlarla sətir üzrə "bu gün profilimə kim baxıb" tipli sorğulara ehtiyacınız varsa, tətbiq MySQL-iniz kifayət etməyəcək. Pinot, ClickHouse və ya Druid düzgün alətlərdir. Hətta daha kiçik Laravel tətbiqləri üçün Redis sayğacları və ya əvvəlcədən aqreqatlaşdırılmış cədvəllər ümumi hallar üçün işləyir.

4. Yalnız lazım olduqda özünü qur. Kafka, Pinot, Espresso, Voldemort — LinkedIn-də çoxu var. Bu onların miqyasında məna kəsb edir. Əksər Laravel tətbiqləri əvvəlcə AWS/idarə olunan servisləri seçməlidir. Yalnız OSS seçimlərin aydın şəkildə miqyaslaşa bilmədikləri halda custom infra qurun.

5. Monolith ağrısından sonra SOA dekompozisiyası. Leo illərlə Java + Oracle idi. Monolith-in ağrısı parçalanmanın ağrısını aşdıqda dekompoze etdilər. Laravel monolith-ləri eyni səbr layiqdir.

6. Frontend framework-ları diqqətlə seçin. Ember 2012-də məqbul mərc idi; 2024-də niş-dir. Laravel tətbiqiniz Livewire/Inertia/Vue/React istifadə edirsə, hər birinin miqrasiya hekayəsini anlayın.

7. Graph düşüncəsi. LinkedIn-in məhsulu qrafdır. Məhsulunuzda şəbəkələşdirmə, tövsiyələr və ya müraciətlər varsa, MySQL-də saxlansa belə, erkən qraf kimi modelləşdirin. Rekursiv CTE-lər, `neo4j-php-client` və ya `laravel-nestedset` kimi Laravel paketləri kömək edir.

## Əlavə oxu üçün

- "The Log: What every software engineer should know about real-time data's unifying abstraction" (Jay Kreps esse)
- "I Heart Logs" (Jay Kreps tərəfindən qısa kitab)
- "Kafka: A Distributed Messaging System for Log Processing" (orijinal məqalə)
- "Project Voldemort: A distributed database" (LinkedIn blog)
- "Espresso: LinkedIn's Distributed Document Store" (SIGMOD məqalə)
- "Pinot: Realtime OLAP for 530 Million Users" (LinkedIn blog, VLDB məqalə)
- "Samza: Stateful Scalable Stream Processing at LinkedIn"
- "Rest.li Framework" (açıq mənbə sənədləri)
- "A Brief History of the Feed" (LinkedIn engineering)
- LinkedIn Engineering Blog (engineering.linkedin.com)
- Confluent blog (Kafka-mərkəzli)
- "Data Infrastructure at LinkedIn" (müxtəlif çıxışlar)
