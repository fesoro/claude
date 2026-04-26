# Airbnb (Architect)

## Ümumi baxış

Airbnb qısa və uzun müddətli yaşayış yerləri və təcrübələr üçün qlobal onlayn marketplace-dir. 2008-ci ildə San-Fransiskoda Brian Chesky, Joe Gebbia və Nathan Blecharczyk tərəfindən orijinal "Air Bed and Breakfast" adı altında quruldu. İdeya SF-də dizayn konfransı zamanı təsisçilərin mənzilində hava yataqlarını icarəyə vermək idi. O, ev sahiblərinin evləri siyahıya aldığı və qonaqların qonaqlama etdiyi platformaya çevrildi.

- 5M+ aktiv siyahı (2024)
- 200+ ölkə
- $75B+ kumulyativ Gross Booking Value, illik gəliri bir neçə milyardlıq
- Dekabr 2020-dən bəri public şirkət (ABNB)
- İldə milyonlarla gecə rezervasiya olunur

Əsas tarixi anlar:

- 2008 — quruldu
- 2009 — Y Combinator, bir neçə dəfə pivot etdi
- 2014 — qlobal ekspansiya sürətlənir
- 2015 — Airflow açıq mənbə edildi (data dünyasını dəyişdi)
- 2017 — Superset açıq mənbə edildi
- 2018 — Ruby monolith-dən əsas SOA miqrasiyasına ictimai şəkildə başlayır
- 2020 — pandemiya zamanı IPO (risqli vaxt, amma işlədi)
- 2023 — monolith → SOA 10 illik səyahətini ictimai şəkildə ətraflı izah etdi

## Texnologiya yığını

| Layer | Technology | Niyə |
|-------|-----------|-----|
| Languages | Ruby, Java, Kotlin, Scala, Python, JavaScript/TypeScript | Legacy monolith üçün Ruby, yeni servislər üçün Java/Kotlin, data üçün Scala, ML üçün Python |
| Web framework | Ruby on Rails (legacy monolith), Spring (servislər), Dropwizard (servislər), React (frontend) | Rails onları product-market fit-ə gətirdi; Spring yeni servislər üçün standartdır |
| Primary DB | MySQL (shard-lanmış, getdikcə Vitess üzərində) | Sharding ilə çətin qazanılmış miqyaslama; Vitess cloud-native MySQL miqyaslanması gətirir |
| Secondary DB | HBase, bir neçə yerdə DynamoDB, bir neçə yerdə Postgres | Spesifik iş yükləri |
| Cache | Memcached (ağır), Redis | Klassik LAMP tipli cache |
| Queue/messaging | Apache Kafka | Event streaming və pipeline onurğası |
| Search | Elasticsearch | Siyahı axtarışı, coğrafi axtarış |
| Data orchestration | Apache Airflow (Airbnb tərəfindən icad olunub) | Data pipeline-ları, indi sənaye standartı |
| Data viz | Apache Superset (Airbnb tərəfindən icad olunub) | BI dashboard-lar |
| Infrastructure | AWS (ağır), öz Kubernetes | Hər kəsdən çox əvvəl AWS-də başladı; ən böyük müştərilərindən biri |
| Deployment | BAU (Big Airbnb Unified) — daxili deploy pipeline-ı, indi Kubernetes-native | Bütün servislər üçün ardıcıl deploy |
| Frontend rendering | React + Hypernova (server-rendered React) | SEO dostu server render + client hydration |
| ML | Bighead | Daxildə ucdan-uca ML platforması |
| Design system | DLS (Design Language System) | Bir çox komanda üzrə brend-ardıcıl UI |

## Dillər — Nə və niyə

Ruby orijinal dil idi, xüsusilə Ruby on Rails. Bütün Airbnb məhsulu (siyahılar, axtarış, rezervasiya, ödənişlər, mesajlaşma, rəylər) Monorail adlanan bir nəhəng Rails monolith-də daxildə yaşadı. Rails seçildi, çünki iki və ya üç təsisçiyə işləyən bir marketplace tez göndərməyə imkan verdi — skaffolding, ActiveRecord ORM və convention-over-configuration fəlsəfəsi onların ehtiyacına uyğun idi.

Java və Kotlin indi yeni backend servisləri üçün əsas dillərdir. Airbnb 2018 ətrafında Monorail-i parçalamağa qərar verdikdə, JVM-i seçdilər, çünki: çoxlu kitabxana, yaxşı performans, yetkin concurrency, güclü tipləndirmə bug-ları erkən tutur. Kotlin xüsusilə yeni servislərdə daha çox istifadə olunur çünki daha az boilerplate var.

Scala data engineering-də çox istifadə olunur — Spark, Kafka Streams, data pipeline alətləri. Scala-nın funksional xüsusiyyətləri batch və streaming data transformasiyalarına uyğun gəlir.

Python data science, ML (Bighead), Airflow özü (Airflow Python-dur) və notebook-larda istifadə olunur.

JavaScript və TypeScript web tətbiqində istifadə olunur. Airbnb-nin frontend-i Facebook-dan kənar React qəbulunda öncü idi. Onlar həmçinin GitHub-da ən çox ulduzlanan repo-lardan biri olan Airbnb JavaScript style guide-ini populyarlaşdırdılar.

## Framework seçimləri — Nə və niyə

- Ruby on Rails — Monorail monolith. Demək olar ki, bütün legacy biznes məntiqini ehtiva edir. Hətta 2024-də Airbnb-nin böyük hissələri hələ də Monorail tərəfindən xidmət edilir və Airbnb onu sağlam saxlamağa investisiya edir (Rails 7, modern Ruby).
- Spring Boot / Dropwizard — yeni microservices Spring (və ya bəziləri Dropwizard) istifadə edir. Standart JVM microservice stack.
- React — web və bəzi native qabıqlar. Airbnb React-in ən erkən və ən böyük qəbullarından biri idi.
- Hypernova — Airbnb-nin server-rendered React engine-i. Rails-ə HTTP üzərindən Node.js servisinə zəng edərək server-rendered React xidmət etməyə imkan verir. Açıq mənbə edilib.
- Airflow — Python DAG orkestrasiyası. Maxime Beauchemin tərəfindən Airbnb-də olarkən icad olunub. Apache-a bağışlanıb.
- Superset — BI / data vizualizasiyası. Maxime Beauchemin tərəfindən də. O da indi Apache-dır.
- Knowledge Repo — Airbnb-nin data analizlərini review oluna bilən artefakt kimi saxlayan açıq mənbə aləti.

## Verilənlər bazası seçimləri — Nə və niyə

### MySQL
Primary əməliyyat verilənlər bazası. Siyahılar, istifadəçilər, rezervasiyalar, ödənişlər — hamısı əvvəlcə bir nəhəng primary-də MySQL-də, sonra shard-lanmışdı. Sharding əvvəlcə əllə və Airlock adlı servis vasitəsilə edildi; son vaxtlar Airbnb Vitess-ə (YouTube/PlanetScale-dən yaranan açıq mənbə MySQL sharding platforması) keçir.

MySQL niyə: təsis edildikdə Rails + MySQL açıq-aydın seçim idi. Relational model rezervasiya domeninə təbii uyğunlaşır (istifadəçilər, siyahılar, rezervasiyalar, ödənişlər arasında foreign key-lər). Sonradan Vitess-ə keçmək onlara düzgün horizontal miqyaslama əldə edərkən MySQL-i saxlamağa imkan verdi.

### HBase
Bəzi big-data iş yükləri üçün istifadə olunur; Airbnb analitika və offline data üçün böyük Hadoop/HBase izinə malikdir.

### Elasticsearch
Siyahılar üçün axtarış — coğrafi axtarış, filtrləmə, sıralama.

### Memcached + Redis
Memcached MySQL əsaslı səhifələr üçün klassik cache-dir. Redis data strukturlarının (sorted set-lər, siyahılar) əhəmiyyət kəsb etdiyi və ya persistence-in lazım olduğu yerlərdə istifadə olunur.

### Miqrasiya hekayələri
- Manual sharding → Airlock → Vitess: bir neçə il ərzində Airbnb əl ilə MySQL shard-lamaqdan Vitess idarə olunan shard-lara köçdü. Bu cross-shard sorğuları və resharding-i daha asan edir.
- Monorail-in tək verilənlər bazasında əvvəlcə demək olar ki, hər şey var idi. Bir servisi çıxarmaq onun sxemini (və cədvəllərini) paylaşılan DB-dən çıxarmağı tələb edirdi. Bu SOA miqrasiyasının ən çətin hissələrindən biri idi.

## Proqram arxitekturası

Airbnb Ruby on Rails monolith kimi başladı və təxminən 10 il belə qaldı. SOA-ya doğru təkan 2018 ətrafında başladı, çünki:

- Rails test suite saatlar çəkirdi. Tam bir iş mühəndisin yarım gününü ala bilərdi.
- Deploy-lar riskli oldu — minlərlə mühəndisdən hər hansı biri təsadüfən böyük bir şeyi poza bilərdi.
- Tək kod bazası dependency konflikti demək idi, Ruby versiya yüksəldilmələri illər çəkirdi.
- Tək verilənlər bazası demək idi ki, hər komandanın sxem dəyişiklikləri başqalarına təsir edə bilərdi.

Bu gün Airbnb-də 1,000-dən çox servis var, lakin monolith hələ də mövcuddur və hələ də çoxlu əsas trafiki idarə edir. Miqrasiya on illik bir hekayədir.

```
+---------------------------------------------------------+
|                 Web + Mobile Clients                    |
+---------------------------------------------------------+
                        |
              (HTTPS, GraphQL + REST)
                        |
+---------------------------------------------------------+
|              Edge / API Gateway (Envoy-based)           |
+---------------------------------------------------------+
               |                     |
+--------------v------+   +----------v-------------------+
|   Monorail          |   |   New services               |
|   (Rails, 10+ years)|   |   (Java/Kotlin/Spring)       |
|   Search UI, many   |   |   Payments, Pricing, Trust,  |
|   booking flows     |   |   Messaging, Identity, etc.  |
+----------+----------+   +----------+-------------------+
           |                         |
+----------v-------------------------v---------------------+
|  MySQL (sharded, moving to Vitess)                       |
|  HBase / Hadoop                                          |
|  Elasticsearch (listings search)                         |
|  Memcached, Redis                                        |
|  Kafka (event bus)                                       |
+----------------------------------------------------------+
                        |
+----------------------------------------------------------+
|  Airflow (pipelines) + Superset (BI) + Bighead (ML)      |
+----------------------------------------------------------+
                        |
+----------------------------------------------------------+
|  AWS: EC2, S3, RDS/Aurora in some places, Kubernetes     |
+----------------------------------------------------------+
```

Frontend axını: Rails React komponentlərini ehtiva edən səhifəni xidmət edir. Hypernova həmin React komponentlərini Node.js-də server-render edir, Rails HTML-i səhifəyə tikər, brauzer hydrate edir. Bu hibrid onlara Next.js/Remix mövcud olmazdan əvvəl sürətli time-to-first-byte plus interactive React verdi.

## İnfrastruktur və deploy

- Erkən günlərdən AWS — EC2, S3, bəzi iş yükləri üçün RDS/Aurora
- Kubernetes — əsasən daxili, konteynerləşdirilmiş servislər üçün
- BAU ("Big Airbnb Unified") — onların birləşdirilmiş deploy platforması; servislərin necə qurulması, test edilməsi, deploy edilməsi və roll out edilməsini standartlaşdırır
- Kafka — event bus; hər SOA servisi domen event-ləri publish edir
- Service mesh — servislər arasında trafik idarəetməsi üçün Envoy əsaslı
- Observability — Prometheus tipli metrics üzərində daxili dashboard-lar, plus Datadog və custom alətlər

## Arxitekturanın təkamülü

- 2008–2011: bir maşında, sonra bir neçəsində Rails + MySQL
- 2011–2014: Rails monolith böyüyür; Memcached, shard-lanmış MySQL; AWS ağır istifadə
- 2014–2017: frontend-də React gəlir; Hypernova SSR; Airflow daxildə icad olunur; Monorail nəhəng
- 2017–2019: Airflow OSS buraxılışı və Apache bağışlanması; Superset OSS; SOA miqrasiyası rəsmi olaraq başlayır
- 2019–2023: SOA ekspansiyası, event bus kimi Kafka, Vitess qəbulu; ML üçün Bighead; 1000+ servis
- 2023–indi: SOA + qalan monolith hibridi; developer productivity, platform komandalarına güclü fokus

## Əsas texniki qərarlar

### 1. Təxminən bir onillik Rails monolith-də qalmaq
- Problem: milyardlıq şirkətdə "biz hələ də Rails-dəyik" tez-tez technical debt kimi qəbul edilir.
- Seçim: yenidən yazmaq əvəzinə monolith-ə investisiya etmək (Ruby-ni güncəl saxlamaq, testləri optimallaşdırmaq, daxildə modulyarlaşdırmaq).
- Trade-off: monolith nəhəng oldu; test suite ağrısı; deploy riski.
- Nəticə: Rails-də unicorn və sonra public-şirkət miqyasına çatdılar. Monolith illər boyu doğru seçim idi — erkən SOA onları yavaşladacaqdı.

### 2. Airflow-u icad etmək
- Problem: data pipeline-ları cron job-lar və bash skriptləri idi. Dependency-lər, retry-lər, backfill-lər ağrılı idi.
- Seçim: pipeline-ların kod kimi DAG olduğu, UI, scheduler və operator modeli olan Python framework qurmaq.
- Trade-off: işlətmək üçün daha çox infrastruktur; öyrənmək üçün başqa sistem.
- Nəticə: Airflow sənayedə ən çox istifadə olunan data orkestratoruna çevrildi. Açıq mənbə edildi, Apache-a bağışlandı. Airbnb öz alətinə töhfə verən qlobal icmadan faydalanır.

### 3. SOA miqrasiya yanaşması — diqqətlə çıxarın, big-bang etməyin
- Problem: Monorail yenidən yazmaq üçün çox böyükdür.
- Seçim: strangler pattern ilə eyni vaxtda bir məhdudlaşdırılmış kontekst çıxarın. Monolith-in qarşısına servis qoyun, məntiqi köçürün, monolith kod yolunu təqaüdə göndərin, təkrarlayın.
- Trade-off: yavaş; miqrasiya illər çəkdi.
- Nəticə: big-bang yenidən yazmadan katastrofik kəsinti olmadı; miqrasiya 2024-də biznesə zərər vermədən davam edir.

### 4. Server-rendered React üçün Hypernova
- Problem: React-in komponent modelini istəyirdilər lakin SEO və performans üçün server-side HTML-ə ehtiyac duyurdular.
- Seçim: Rails React komponentlərini render etmək üçün Node.js servisini (Hypernova) çağırır, sonra HTML-i Rails cavabına daxil edir. Aləti açıq mənbə et.
- Trade-off: render yolunda yeni hop; işlətmək üçün Node.js servisi.
- Nəticə: Next.js populyar olmadan çox əvvəl React + SEO əldə etdilər.

### 5. MySQL sharding üçün Vitess-ə keçmək
- Problem: Airlock sharding işləyirdi lakin custom və kövrək idi; resharding ağrılı idi.
- Seçim: MySQL sharding-i standartlaşdıran Vitess-i (əvvəlcə YouTube-dan) qəbul et.
- Trade-off: miqrasiya səyi; Vitess öyrənmə əyrisinə malikdir.
- Nəticə: daha avtomatlaşdırılmış resharding; daha yaxşı alətlər; komandanın yaxşı bildiyi MySQL-də qalır.

## Müsahibədə necə istinad etmək

1. Monolith-lər utanc deyil. Airbnb-nin Rails monolith-i tipik Laravel monolith-in demək olar ki, dəqiq arxitektural əkizidir. Onlar illərlə bununla uğur qazandılar. Yaxşı strukturlaşdırılmış Laravel monolith məhdudiyyət deyil, düzgün defoltdur.

2. Servisləri yalnız spesifik ağrıya sahib olduqda çıxarın: test vaxtı, deploy riski, komanda miqyaslaşması, database mübarizəsi. Laravel tətbiqində bunların heç biri yoxdursa, onu parçalamayın.

3. Çıxardıqda strangler pattern istifadə edin. Monolith-i həqiqət mənbəyi kimi saxlayın, yeni servis qaldırın, spesifik trafiki yeni servisə yönləndirin, köhnə kodu yavaş-yavaş təqaüdə göndərin. Laravel-in HTTP kernel və middleware-i bunu asanlaşdırır — spesifik route-ları microservice-ə proxy edə bilərsiniz.

4. Test suite-ə investisiya edin. Airbnb-nin Rails test ağrısı SOA üçün əsas səbəb idi. Laravel-də Pest/PHPUnit-i paralel testing, `DatabaseTransactions` ilə sürətli saxlayın və unit test-lər kifayət edəcəksə feature test-lərə çox etibar etməyin.

5. Event-lər onurğa kimi. Hətta monolith daxilində domen event-lərini push edin (`OrderPlaced`, `PaymentSucceeded`). Laravel event-ləri/listener-ləri (üstəlik queue-lu listener-lər) məntiqi daxildə decouple etməyə imkan verir. Sonradan bir servis çıxardığınızda, bu event-lər Kafka/Redis mesajları ola bilər.

6. React + SSR dərsləri tətbiq olunur. Laravel-də React/Vue ilə Inertia.js istifadə edirsinizsə, artıq oxşar SSR hekayəsinə sahibsiniz. Hydration və SEO trade-off-larını anlayın.

7. Observability: Datadog tipli APM, xətalar üçün Sentry. Production Laravel tətbiqini xəta izləməsi + log aqreqasiyası olmadan idarə etməyin.

## Əlavə oxu üçün

- Airbnb Engineering Blog (medium.com/airbnb-engineering)
- "Airbnb's 10 Years of SOA Migration"
- "Building Services at Airbnb" (seriya)
- "Maxime Beauchemin: The Rise of the Data Engineer"
- "Airflow: a workflow management platform" (orijinal Airbnb yazısı)
- "Hypernova: Airbnb's server-side rendering service"
- Airbnb JavaScript style guide
- Apache Airflow rəsmi sənədləri
- Apache Superset rəsmi sənədləri
- Vitess sənədləri
- "Bighead: A Framework-Agnostic, End-to-End ML Platform at Airbnb" (çıxışlar)
