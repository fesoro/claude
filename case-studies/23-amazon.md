# Amazon (Architect)

## Ümumi baxış

Amazon dünyanın ən böyük onlayn pərakəndə satıcısıdır və AWS vasitəsilə ən böyük bulud provayderidir. 1994-cü ildə Jeff Bezos tərəfindən Sietl-də onlayn kitab mağazası kimi qurulub. Amazon e-commerce, bulud hesablama (AWS), cihazlar (Kindle, Echo/Alexa), streaming (Prime Video), ərzaq (Whole Foods), logistika və bir çox digər şaquli sahələrə qədər böyümüşdür.

- 2023-də ~$600B gəlir
- S3-də trilyonlarla obyekt saxlanılır (AWS bir neçə il əvvəl "onlarla trilyon" açıqladı)
- AWS 30+ region, 100+ availability zone-da fəaliyyət göstərir, kommersiya internetin nəhəng faizini işlədir
- Milyonlarla kiçik satıcı Amazon Marketplace istifadə edir
- Qlobal olaraq yüz milyonlarla Prime üzvü
- Public şirkət (AMZN), dünyanın ən qiymətli şirkətlərindən biri

Əsas tarixi anlar:

- 1994 — quruldu, 1995-də ilk kitab satıldı
- 2002 — məşhur Bezos API Mandate memo-su
- 2006 — AWS işə salınır (S3, EC2); pərakəndə indi AWS-i daxildə dogfood edir
- 2007 — Dynamo məqaləsi yayımlanır (Cassandra, Riak, DynamoDB-yə ilham verir)
- 2010s — minlərlə microservice, "two-pizza team"-lər
- 2014 — Lambda işə salınır (serverless); sonradan Firecracker microVM onun altını çəkir
- 2018 — foundational infrastructure üçün ağır Rust qəbulunu elan edir
- 2023 — Prime Video audio/video monitoring servisi üçün microservices-dən monolith-ə qayıtmaq haqqında yazı yayımlayır, 90% xərc azalması. Nəhəng sənaye müzakirəsinə çevrildi.

## Texnologiya yığını

| Layer | Technology | Niyə |
|-------|-----------|-----|
| Languages | Java, C++, Rust (ağır şəkildə böyüyür), Python, Kotlin, bir az Go, JavaScript | Polyglot; aşağı səviyyəli infra üçün Rust, servis kodu üçün Java, data/ML üçün Python |
| Web framework | Daxili frameworks ("Coral" daxili servis framework-ları nəsli), bəzi yerlərdə Spring | Erkən Amazon özünü qurdu — Spring Boot tipli normalardan əvvəl gəlir |
| Primary DB | DynamoDB (daxili + ictimai), Aurora (MySQL/Postgres uyğun), RDS, daxili store-lar | Proqnozlaşdırıla bilən aşağı gecikməli KV üçün DynamoDB; relational iş yükləri üçün Aurora |
| Secondary DB | S3 (obyekt), ElastiCache (Redis/Memcached), ElasticSearch/OpenSearch, Redshift | Fərqli data formaları üçün fərqli store-lar |
| Cache | ElastiCache (Redis), daxili cache-lər | Servislər arasında paylaşılan |
| Queue/messaging | SQS (orijinal!), SNS, Kinesis, Amazon MQ, EventBridge | SQS pərakəndəni decouple etmək üçün AWS-in ilk daxili servisi idi |
| Search | Daxili axtarış (Amazon-un katalog axtarışı custom-dur), bəziləri üçün OpenSearch | Katalog axtarışı ən çətinlərdən biridir; onlar öz yaratdılar |
| Infrastructure | AWS özü (onlar buluddur); Nitro hypervisor, Firecracker microVM-lər | Amazon başqasının buludunda işləmir |
| Orchestration | ECS, EKS, Fargate, daxili mülkiyyət scheduler-lər | Fərqli komandalar fərqli alətlər istifadə edir |
| Monitoring | CloudWatch, daxili monitoring stack-lər | Hər servis defolt olaraq metrics yayır |
| Serverless | Firecracker microVM-lər üzərində Lambda | Alt-saniyəli cold start-larla sorğu başına izolyasiya |
| Alexa | Python + ML stack-lər | Səs/ML dünyasında Python üstün tutulur |

## Dillər — Nə və niyə

Java pərakəndə microservices üçün ən çox istifadə olunan dildir. Amazon 2000-ci illərdə SOA-ya keçdikdə, Java əsas enterprise dil idi. Bir çox daxili framework (Coral, daxili RPC, servis şablonları) Java əsaslıdır. Kotlin yeni servislərdə yayılır.

C++ performans-kritik komponentlərdə istifadə olunur — Obidos (90-cı illərin sonlarında orijinal Amazon web server) C++ idi və bir çox əsas sistem hələ də belədir. S3-də C++ və Rust komponentləri var. Bir çox AWS servisinin nüvəsində C++ var.

Amazon-da Rust qəbulu aqressivdir. Bunlarda istifadə olunur:

- Firecracker microVM (Lambda-nı gücləndirir) — Rust-da yazılıb
- Bottlerocket (AWS-in minimal container OS-i) — Rust
- Nitro — AWS-in custom hypervisor və təhlükəsizlik çipi sistemi əsas nöqtələrdə Rust istifadə edir
- Yeni S3 komponentləri
- Rust üçün AWS SDK rəsmidir

Amazon Rust Foundation-u ictimai şəkildə dəstəkləyir və core Rust töhfəçilərini işə götürür.

Python data science, ML, Alexa və avtomatlaşdırmada ağırdır. Bir çox daxili ops aləti Python-dur.

Perl — çox erkən günlərdə (1995–2005), Amazon böyük Perl kod bazalarına sahib idi. Obidos Perl alətləri ilə birgə yaşayırdı. Bunun əksəriyyəti bundan sonra əvəzlənib, lakin küncələrdə Perl hekayələri var.

Go bəzi istifadəyə malikdir lakin yeni infrastruktur kodu üçün Rust qədər mərkəzi deyil.

## Framework seçimləri — Nə və niyə

Amazon OSS defoltlarından istifadə etmək əvəzinə çoxlu daxili framework qurur. Səbəblər:

- Miqyas: hazır məhsul tez-tez Amazon miqyasını idarə edə bilmir
- Minlərlə komanda arasında ardıcıllıq
- Təhlükəsizlik tələbləri sərtdir

Nümunələr:

- Coral / "daxili servis framework-ları" — Java servis inkişafı üçün Amazon-un Spring/Dropwizard daxili ekvivalentləri, daxili metrics, auth, RPC ilə.
- Daxili build sistemləri (Brazil) — minlərlə paket üzrə təkrarlanabilir build-lər və dependency idarəetməsi.
- Daxili deployment sistemləri (Apollo, Pipelines) — Spinnaker tipli alətlər ictimaiyə açılmadan əvvəl Amazon onlara daxildə sahib idi.

## Verilənlər bazası seçimləri — Nə və niyə

### DynamoDB
AWS-in idarə olunan NoSQL KV store-u. Dynamo məqaləsinə (2007) əsaslanır lakin əhəmiyyətli dərəcədə təkamül edib. Amazon daxilində DynamoDB kritik pərakəndə iş yüklərini işlədir. Xaricdə minlərlə müştəriyə xidmət edir. Əsas xüsusiyyətlər:

- Hər hansı miqyasda proqnozlaşdırıla bilən tək rəqəmli millisaniyə gecikməsi
- Avtomatik bölmələmə və replikasiya
- Sorğu başına və ya təmin edilmiş qiymətləndirmə
- Tək-cədvəl dizaynı yaygındır (yeni başlayanlarla mübahisəlidir, lakin səmərəlidir)

### Aurora
Amazon-un custom MySQL və Postgres uyğun relational verilənlər bazası. Hesablama və saxlama ayrılıb: hesablama node-ları 3 AZ-də 6 tərəfli replikasiya edən paylanmış saxlama təbəqəsi ilə danışır. Aurora ~5x MySQL ötürmə qabiliyyəti və çox daha yaxşı failover iddia edir. Aurora Serverless on-demand miqyaslama əlavə etdi.

### S3
Obyekt saxlama servisi. Onlarla trilyon obyekt. Orijinal olaraq ən ilk AWS servisi. Amazon daxilində data-at-rest olan demək olar ki, hər şey üçün istifadə olunur (log-lar, backup-lar, data lake-lər, media). S3 təkamül etdi: S3 Select, S3 Object Lambda, Strong consistency (2020 — bundan əvvəl üst-yazılardan sonrakı oxumalar üçün eventual consistent idi).

### Redshift
Data warehouse, PostgreSQL-dan çoxdan fork edilib və ağır şəkildə dəyişdirilib. Amazon daxilində və analitika üçün müştərilər tərəfindən istifadə olunur.

### ElastiCache / Memcached / Redis
İsti data üçün cache-lər.

### Miqrasiya hekayələri
Amazon-un erkən pərakəndə stack-i Oracle verilənlər bazası istifadə edirdi. Onlar məşhur şəkildə Oracle-dan çıxdılar (təxminən 2019–2020-də tamamlandı), daxildə DynamoDB və Aurora-ya köçdü. Bezos və Andy Jassy ictimai şəkildə Oracle miqrasiyasının tamamlanmasını qeyd etdilər.

## Proqram arxitekturası

Amazon-un arxitekturası microservices dərsliyi case study-sidir. 2002 Bezos API Mandate bəyan etdi:

1. Bütün komandalar data və funksionallığını servis interfeysləri vasitəsilə təqdim edəcək.
2. Komandalar bu interfeyslər vasitəsilə əlaqə qurmalıdır.
3. Başqa heç bir inter-process kommunikasiya forması olmayacaq — komandalar arasında birbaşa DB girişi yoxdur, arxa qapılar yoxdur.
4. İnterfeyslər externalizable olmaq üçün dizayn edilməlidir. Bu o deməkdir ki, interfeyi xarici dünyada developer-lərə təqdim etməyi planlaşdırmalısınız.
5. Bunu etməyən hər kəs işdən çıxarılacaq.

Bu effektiv şəkildə Amazon-u daxildə API şirkətinə çevirdi və daha sonra AWS xarici versiya oldu.

Two-pizza teams — hər servisə iki pizza ilə qidalandırılacaq qədər kiçik (~6–10 nəfər) komanda sahiblik edir. Komanda servisə ucdan-uca sahiblik edir: dizayn, kod, deploy, on-call, dəstək, xüsusiyyət təkamülü.

```
+------------------------------------------------------------+
|          Customers: web, mobile, Alexa, devices            |
+------------------------------------------------------------+
                          |
+------------------------------------------------------------+
|        Edge: CloudFront CDN + API gateways                 |
+------------------------------------------------------------+
                          |
      +-------------------+-------------------+
      |                   |                   |
+-----v-----+       +-----v-----+       +-----v-----+
| Catalog   |       | Orders    |       | Payments  |
| service   |       | service   |       | service   |
+-----+-----+       +-----+-----+       +-----+-----+
      |                   |                   |
+-----v-------------------v-------------------v-------------+
|   DynamoDB shards, Aurora clusters, S3 buckets            |
|   SQS queues, SNS topics, Kinesis streams                 |
+-----------------------------------------------------------+
                          |
+-----------------------------------------------------------+
|         AWS infrastructure (internal dogfood)             |
|   EC2 on Nitro, Lambda on Firecracker, Fargate, ECS/EKS   |
+-----------------------------------------------------------+
```

## İnfrastruktur və deploy

- Hər şeyə sahib olun. Amazon qlobal olaraq data mərkəzləri işlədir; öz avadanlığını dizayn edib (Graviton CPU-lar, Nitro kartları, custom şəbəkə).
- Nitro: AWS-in hypervisor və təhlükəsizlik modeli. Hypervisor funksiyalarını ayrıca avadanlığa köçürür ki, EC2 instance-ları bare-metal-a yaxın performans artı güclü izolyasiya əldə etsin.
- Firecracker: millisaniyələrdə yüngül VM-lər işə salan açıq mənbə microVM texnologiyası. Lambda və Fargate-i gücləndirir.
- Bottlerocket: Amazon-un konteynerləri işlətmək üçün Rust əsaslı minimal OS.
- Working backwards (PR/FAQ): kod yazmadan əvvəl komandalar məhsulun press release-i və FAQ-nı işə salınırmış kimi yazırlar. "Bu kim üçündür, niyə vacibdir?" haqqında aydınlığı məcbur edir.

## Arxitekturanın təkamülü

- 1994–2001: C++ (Obidos) + Perl, Oracle DB, monolitik-ə yaxın arxitektura (backend ilə "kitab mağazası")
- 2001–2005: SOA doğuş ağrıları, sonra 2002 mandate; servislərə parçalayır
- 2006–2010: AWS işə salınır (S3, EC2, SQS); daxili komandalar AWS servislərini istifadə etməyə başlayır; minlərlə pərakəndə microservice
- 2010–2015: DynamoDB ictimai, Aurora ictimai; Lambda işə salınır (2014); serverless dövrü başlayır
- 2015–2020: Rust qəbulu böyüyür; Nitro işə salınır; Firecracker açıq mənbə edilir (2018)
- 2020–indi: Oracle miqrasiyası tamamlandı; Prime Video monolith hekayəsi serverless dogma-ya çağırış edir; Graviton (ARM CPU-ları) geniş qəbul edilir

## Əsas texniki qərarlar

### 1. Bezos API Mandate (2002)
- Problem: daxili komandalar birbaşa bir-birinin verilənlər bazalarına qoşulurdular. Bir komandadakı dəyişikliklər digərini pozurdu. Heç bir şeyi externalize etmək yolu yox idi.
- Seçim: bütün komandalar arası kommunikasiyanın servis interfeysləri vasitəsilə getməsini əmr etmək; xarici təqdimat üçün plan.
- Trade-off: böyük qısamüddətli xərc; bir çox komanda yeni API-lər qurmalı idi.
- Nəticə: Amazon icra əmri ilə servis-oriyentli təşkilata çevrildi. Nəhayət AWS oldu. Bəlkə də texnologiya tarixində ən yüksək leverage-li arxitektural qərardır.

### 2. DynamoDB-nin qurulması (və Dynamo məqalə nəsli)
- Problem: səbət mövcudluğu consistency-dən daha vacibdir. DB uğursuz olsa, səbət də uğursuz olur — pis UX. Relational DB-lər miqyasda mövcudluq çatdıra bilmədilər.
- Seçim: Dynamo-nu daxildə qur — eventual consistent, AP-yönlü KV store. Sonradan DynamoDB (AWS servisi) Dynamo-dan öyrəndi və daha güclü seçimlər təklif etdi.
- Trade-off: eventual consistency bəzi use case-lər üçün tətbiq səviyyəli uzlaşmanı məcbur etdi.
- Nəticə: bir çox kritik yol işlədi; Cassandra, Riak, Voldemort-a ilham verdi; DynamoDB indi AWS-ə milyardlar qazandırır.

### 3. Aurora — relational DB-də hesablama və saxlamanı ayırmaq
- Problem: böyük miqyasda MySQL/Postgres işlətmək ağrılı idi. Replikasiya, failover, backup-ların hamısı yavaş.
- Seçim: 3 AZ-də 6 tərəfli replikasiya edən, SQL engine işlədən hesablama node-ları tərəfindən paylaşılan saxlama təbəqəsi qurmaq. Log-lar həqiqət mənbəyidir, data blokları deyil.
- Trade-off: qurmaq mürəkkəbdir; vanil MySQL/Postgres ilə xüsusiyyət pariteti vaxt aparır.
- Nəticə: ~5x daha sürətli MySQL iş yükləri; çox sürətli failover; nəhəng AWS gəlir axını.

### 4. Firecracker — serverless üçün microVM-lər
- Problem: Lambda güclü izolyasiyalı host başına bir çox etibarsız funksiya işlətməli idi. Docker konteynerləri kernel səviyyəli izolyasiya vermir. Tam VM-lər başlamaq üçün çox yavaşdır.
- Seçim: ~125ms-də microVM-lər işə salan və VM başına ~5MB yaddaş istifadə edən Rust-da minimal VMM qurmaq.
- Trade-off: yeni texnologiya; debug etmək üçün daha çox təbəqə.
- Nəticə: AWS Lambda və Fargate-i gücləndirir; açıq mənbə edilib; serverless ekosistemində yüksək qəbul.

### 5. Prime Video monolith hekayəsi (2023)
- Problem: Prime Video-nun audio/video monitoring sistemi Lambda + Step Functions microservices kimi qurulmuşdu. Yüksək miqyasda invocation başına xərclər və orkestrasiya xərcləri nəhəng idi.
- Seçim: EC2/ECS-də işləyən vahid monolitik servisə birləşdirin. 90% xərc azalması bildirilir.
- Trade-off: daha az granular miqyaslama; daha çətin sahiblik modeli.
- Nəticə: "hər yerdə microservices"-ə qarşı nümunə kimi viral oldu. Sənaye müzakirəsi: "həqiqətən serverless microservices-ə defolt olaraq getməlisinizmi?" Amazon hələ də microservices + serverless satır, amma bu dürüst və vacib bir incəlik idi.

## Müsahibədə necə istinad etmək

1. API-lər sizin müqavilənizdir. Servis API-lərini bir gün ictimai olaraq təqdim edəcəyiniz kimi dizayn edin. Hətta Laravel monolith daxilində təmiz modul sərhədlərini müqavilələrlə cızın. Sonradan microservice çıxarırsanız, sərhəd artıq mövcuddur.

2. Mümkün olduqda sıxıcı verilənlər bazaları seçin. Amazon pərakəndəni Oracle-dan qarışığa köçürdü (kritik yollar üçün DynamoDB, SQL üçün Aurora). Əksər Laravel tətbiqləri üçün RDS-də MySQL və ya Postgres mükəmməl işləyir. Həddindən artıq mürəkkəbləşdirməyin.

3. Two-pizza komandalar. Hətta kiçik dükanlarda, modulda çox adam varsa, bölün. Bir nəfər bir çox modulun yeganə sahibidirsə, bu bus-factor riskidir.

4. Serverless pulsuz deyil. Prime Video hekayəsi ibrətamizdir: Lambda + Step Functions per-invocation xərc əlavə olunana qədər sehirli səslənir. Sabit iş yükləri üçün sıxıcı EC2/ECS və ya Forge-da Laravel tez-tez daha ucuzdur. Ölçün.

5. Queue-larla event-driven arxitektura. SQS/SNS AWS-in ilk daxili-sonra-xarici servisləri idi bir səbəbdən. Laravel-də işi decouple etmək üçün queue-lar (Redis, SQS, Beanstalkd) istifadə edin. Sizə retry-lər, backoff, nasazlıq izolyasiyası verir.

6. Xaos / əməliyyat mükəmməlliyi. Amazon çoxlu əməliyyat ciddiliyi icad etdi: game-day-lər, correction-of-error (COE) sənədləri, həftəlik ops müzakirələri. Yüngül versiyasını qəbul edin: hər hadisə yazılı postmortem alır, tədbir maddələri izlənilir.

7. Working backwards sənədləri. Xüsusiyyət qurmadan əvvəl onun üçün "release notes"-u yazın — istifadəçi-görünən davranışı aydınlaşdırmağa məcbur edir.

## Əlavə oxu üçün

- "The Amazon Builders' Library" (AWS-in öz arxitektura esseləri)
- "Working Backwards: Insights, Stories, and Secrets from Inside Amazon" (Colin Bryar və Bill Carr tərəfindən kitab)
- "Dynamo: Amazon's Highly Available Key-value Store" (2007 məqalə)
- "Amazon Aurora: Design Considerations..." (SIGMOD məqalə)
- "Firecracker: Lightweight Virtualization for Serverless Applications" (NSDI 2020 məqalə)
- "Scaling up the Prime Video audio/video monitoring service and reducing costs by 90%" (Amazon 2023 yazısı)
- "How Amazon finished migrating off Oracle" (müxtəlif çıxışlar və yazılar)
- AWS re:Invent keynote-ları (illik)
- Werner Vogels tərəfindən "All Things Distributed" blog
- Bezos-un 2002 API mandate (bir çox yazılarda istinad edilir)
- "Building Resilient Distributed Systems" (Marc Brooker çıxışları)
- consistency və mövcudluq haqqında "Never-fail distributed systems" AWS məqalələri
