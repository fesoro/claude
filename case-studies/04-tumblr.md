# Tumblr (Senior)

## Ümumi baxış
- Tumblr qısa-format post-lar, reblog-lar və yaradıcı icma ətrafında qurulmuş microblogging / sosial platformadır. GIF-lər, fan mədəniyyəti və güclü estetik diqqət ilə tanınır.
- Zirvədə miqyas (2010-cu illərin ortası): təqribən 500 milyon blog, onlarla milyard post, nəhəng reblog qrafı əmələ gətirən milyardlarla reblog. Zirvədə bir neçə yüz milyon aylıq istifadəçi, siyasət dəyişikliklərindən sonra azalır.
- Əsas tarixi anlar:
  - 2007 — David Karp (20 yaşında) və Marco Arment (ilk CTO, sonra Instapaper və Overcast-ə getdi) tərəfindən təsis edildi.
  - 2008-2012 — Partlayıcı artım; yenidən arxitektura qurmadan əvvəl təmiz LAMP yığınında nəhəng miqyasa çatır.
  - 2013 — Yahoo tərəfindən ~$1.1B-ə alındı.
  - 2017 — Verizon Yahoo-nun alışını tamamlayır, Tumblr-i miras alır.
  - 2018 — Yetkin məzmunu qadağan etdi ("Tumblr adult content ban"), aktiv istifadəçilərin böyük hissəsini itirdi.
  - 2019 — Automattic-ə (WordPress.com) açıqlanmamış məbləğə satıldı, geniş olaraq $3M ətrafında olduğu bildirilir.
  - 2023+ — WordPress infrastrukturunda işləmək üçün davamlı texniki miqrasiya.

## Texnologiya yığını
| Qat | Texnologiya | Niyə |
|-------|-----------|-----|
| Dil | PHP (orijinal), Scala (sonra, perf-kritik yollar üçün) | Monolit üçün PHP; fan-out-da ötürücülük üçün Finagle ilə Scala. |
| Web framework | Xüsusi PHP framework | Müasir PHP framework-lərindən əvvəldir. |
| Əsas DB | MySQL, ağır shardlanıb | Relational, döyüşdə sınanmış, amma çoxlu əməliyyat yaradıcılığı tələb edirdi. |
| Cache | Memcached | LAMP default. |
| Queue/messaging | Gearman → RabbitMQ → Kafka (zamanla) | Miqyas tələb etdikcə queue-lar üzərində iterate edildi. |
| Search | Solr → Lucene əsaslı ev daxili | İllər ərzində qurulmuşdur. |
| Xüsusi store-lar | Redis (dashboard feed), HBase (analitika) | İsti feed-lər üçün Redis, geniş-sütun analitika üçün HBase. |
| İnfrastruktur | Əvvəlcə öz data mərkəzləri, sonra Yahoo/Verizon DC-ləri, indi Automattic infra-sı | Sahiblik zəncirini izlədi. |
| Monitorinq | Collectd, Graphite, Nagios, StatsD | Tipik 2010-cu illərin ortası yığını. |

## Dillər — Nə və niyə

### PHP
Tumblr-in mənşələri PHP-dədir — David Karp ilk versiyaları özü LAMP yığınında yazdı. PHP 2000-ci illərin ortasının sürətli-iterasiya dili idi.

PHP Tumblr-ı isti-yol problemlərinə dəyməyənə qədər yüz milyonlarla istifadəçiyə apardı. Əsas problemlər dil olaraq deyildi — onlar spesifik nümunələr idi (məsələn, reblog qrafında fan-out) ki, per-request, share-nothing PHP modelinə uyğun gəlmirdi.

### Scala (Finagle ilə)
Trafik artdıqca Tumblr perf-kritik backend servislər üçün Scala + Finagle (Twitter-in RPC/servis framework) mənimsədi:
- Dashboard feed yaradılması.
- Fan-out servisi (kimin feed-inə hansı post düşür).
- Reblog zəncirləri üçün qraf traversalı.

Scala məntiqli idi çünki Twitter Finagle-i açıq mənbəyə çevirmişdi və "məhdudlaşdırılmış gecikmə ilə çoxlu eyni zamanda backend çağırışları" nümunəsi tam Finagle-in qurulduğu şey idi. Twitter-dən və ya oxşar JVM təcrübəsinə sahib bir neçə mühəndis işə götürüldü.

### JavaScript
Frontend server-rendered HTML idi progressive JS ilə. Sonra müxtəlif framework-lərdə single-page parçalar.

### Digərləri
Köməkçi infrastruktur üçün bəzi Java, bəzi Python, bəzi Go.

## Framework seçimləri — Nə və niyə

PHP tərəfi daxili xüsusi framework idi, Laravel/Symfony yetkinliyindən əvvəl gəldi. Klassik 2000-ci illər ortasının nümunələri: controller-lər, şablonlar, nazik DB qatı. Framework səviyyəsində xüsusilə yeni bir şey yoxdur — maraqlı mühəndislik aşağıda baş verdi (DB shardlama, caching, fan-out).

Scala tərəfində **Finagle** əsas framework idi:
- Servislər Thrift (və ya HTTP) endpoint təqdim edir.
- Client-lər `Future[T]` dəyərlərini timeout, retry və hedging ilə kompoze edir.
- Termindən əvvəl servis mesh.

Bu klassik 2010-cu illərin əvvəli JVM backend arxitekturasıdır — eyni dövrdə Foursquare, Twitter, LinkedIn ilə müqayisə edə bilərsiniz.

## Verilənlər bazası seçimləri — Nə və niyə

### MySQL (ağır shardlanıb)
Post-lar, blog-lar, istifadəçilər, reblog-lar, like-lar üçün əsas store. İstifadəçi/blog ID ilə shardlanıb. Çoxlu kluster, hər biri primary və replika ilə.

İctimai mühəndislik post-ları spesifik ağrı nöqtələrini təsvir edir:
- **Reblog qrafı** — reblog zənciri yüzlərlə post dərinlikdə ola bilər və çox vaxt bütün zəncirə ehtiyacınız var. Naiv traversal yavaşdır.
- Dashboard feed üçün **fan-out on write** — istifadəçi post etdikdə follower-lərin feed-ləri yenilənməlidir. Məşhur blog-larda milyonlarla follower ilə fan-out bahalı olur.

### Memcached
Ağır istifadə, cache-aside nümunələri. Əksər oxumalar əvvəlcə Memcached-ə dəyir.

### Redis
Xüsusilə **dashboard feed** üçün. İstifadəçi başına Redis siyahıları son görünmüş post ID-lərini ehtiva edir; feed SQL sorğusu deyil, Redis-də sürətli LRANGE idi. Bu klassik "Twitter-üslubu timeline qur" texnikasıdır.

### HBase
Analitika və time-series datası üçün (səhifə baxışları, engagement).

### Queue sistemləri (Gearman → RabbitMQ → Kafka)
Async iş adi mərhələlərdən keçdi: Gearman (sadə job dispatch) → RabbitMQ (daha zəngin routing, dayanıqlılıq) → Kafka (replay ilə dayanıqlı log).

## Proqram arxitekturası

Tumblr **PHP monolit** olaraq başladı və **hibrid**-ə təkamül etdi — üstdə PHP web tier, altında fan-out və qraf-ağır işi idarə edən **Scala / Finagle servislər**.

```
 [User]
    |
    v
 [Load balancer]
    |
    v
 [PHP monolith web tier]
    |
    +--> [Memcached]
    +--> [MySQL shards — posts, blogs, users, likes]
    +--> [Redis — dashboard feed cache]
    +--> [HBase — analytics]
    +--> [Scala/Finagle services over Thrift]:
               - Feed fan-out service
               - Reblog graph service
               - Notifications service
    +--> [Queue: Gearman → RabbitMQ → Kafka]
               |
               v
          [Async workers — PHP + Scala]
    +--> [Solr/Lucene search cluster]
```

Klassik "High Scalability" blog post-u "Tumblr Architecture - 15 Billion Page Views A Month" (təxminən 2012) bu arxitekturanın kanonik təsviridir.

## İnfrastruktur və deploy
- Tumblr-dövrü öz data mərkəzləri (New York + başqaları), sonra 2013-dən sonra Yahoo-nun infrastrukturu, sonra Verizon-un, bu gün isə Automattic-in WordPress infrastrukturu.
- Deployment: dövrə görə dəyişirdi. PHP-nin sürətli edit-reload modeli tez-tez göndərməyi təbii etdi; mühəndislik post-ları gündə bir neçə dəfə deploy təsvir edir.

## Arxitekturanın təkamülü

1. **2007-2010**: LAMP. PHP monolit, MySQL, Memcached. İstifadəçi bazası hər bir neçə ayda ikiqat artdıqca miqyaslanma böhranı başlayır.
2. **2010-2013**: Ağır MySQL shardlama. Dashboard üçün Redis. Job-lar üçün Gearman. Perf-kritik backend üçün Scala/Finagle tətbiqi.
3. **2013**: Yahoo alışı; performans və platform xüsusiyyətlərinə sürətli sərmayə.
4. **2014-2017**: RabbitMQ / Kafka miqrasiyaları. Davamlı Scala mənimsəməsi. GIF idarəetmə pipeline-ı yetişdi.
5. **2018**: Yetkin məzmun qadağası; istifadəçi bazası kəskin şəkildə sıxılır; mühəndislik sərmayəsi düşür.
6. **2019-indiki**: Automattic tərəfindən alındı. WordPress infrastrukturunda işləmək üçün miqrasiya — çoxillik layihə, çünki Tumblr-in data modeli WordPress-dən fərqlidir. Automattic mühəndislik post-larında irəliləyişi ictimai müzakirə etdi.

## Əsas texniki qərarlar

### 1. Reblog zənciri data modeli
**Problem**: Reblog sadəcə "post haqqında post" deyil — bu zəncirdir ki, orijinal müəllifin mətni və hər reblogger-in əlavələri sıra ilə qorunur. Bu zəncirləri səmərəli göstərmək və redaktə etmək çətindir.
**Seçim**: Reblog-ları valideyn reblog-lara (və orijinal post-a) bağlanan node kimi saxlayın, sürətli göstərmə üçün denormalizasiya olunmuş snippet-lərlə. Lazım olduqda zənciri gəzin, aqressiv cache edin.
**Kompromislər**: Zəncirlər yüzlərlə posta böyüyə bilər; traversal məhdudlaşdırılmalıdır; ara post-lara yeniləmələr yayılmır.
**Sonra nə oldu**: Zəncir modeli Tumblr-in şəxsiyyətinin əsası oldu. O, konseptual olaraq Twitter-in düz retweet modelindən daha çox Git-in DAG-ına yaxındır. Yeni platformalarda (Mastodon, Bluesky) xüsusiyyətlərə ilham verdi.

### 2. Redis-backed dashboard feed
**Problem**: İstifadəçinin dashboard-ını o scroll etdiyi zaman MySQL sorğularından yaratmaq yavaşdır — çox JOIN, çox shard.
**Seçim**: Hər istifadəçinin feed-ini Redis siyahısı kimi əvvəlcədən hesablayın. İzlənən blog post edəndə, post ID-ni bütün follower-lərin Redis siyahılarına əlavə edin (fan-out-on-write). Feed-i oxumaq Redis-də O(1)-dir.
**Kompromislər**: Milyonlarla follower-i olan məşhur blog-lar üçün fan-out bahalıdır; hibrid push/pull ilə qurtarırsınız (əksər istifadəçilər üçün fan-out, amma megablog-lar üçün oxuma zamanı pull).
**Sonra nə oldu**: "Fan-out on write" nümunəsi üçün dərslik nümunəsi oldu. Twitter, Instagram və başqaları müstəqil olaraq oxşar nümunələri ixtira etdi.

### 3. PHP ilə yanaşı Scala / Finagle əlavə etmək
**Problem**: Bəzi backend iş yükləri (feed yaradılması, fan-out, axtarış) PHP-nin per-request modelinə uyğun gəlməyən davamlı konkurrentlik tələb edir.
**Seçim**: Scala + Finagle ilə JVM tier əlavə edin. PHP Scala servislərini Thrift üzərindən çağırır. PHP web/edge-də qalır, Scala ağır backend-ə sahibdir.
**Kompromislər**: İki dil ekosistemi, iki deploy modeli, iki işə götürmə kanalı.
**Sonra nə oldu**: Tumblr-ə ön tərəfdə PHP sürətini saxlamağa, arxa tərəfdə isə JVM performansını əldə etməyə imkan verdi. Slack-ə (realtime üçün Hack + Java), LinkedIn-ə (Rails mənşəyi + JVM/Scala) və başqalarına bənzər bölgü.

### 4. Queue təkamülü: Gearman → RabbitMQ → Kafka
**Problem**: Tumblr-in async iş yükləri böyüdü və şaxələndi: mail, webhooks, fan-out, indeksləmə, analitika.
**Seçim**: Gearman (sadə job dispatcher) ilə başlayın, daha zəngin routing/dayanıqlılıq üçün RabbitMQ-ya keçin, event-sourcing-üslublu ehtiyaclar üçün dayanıqlı log kimi Kafka-da bitirin.
**Kompromislər**: Hər miqrasiyanın xərci var — producer/consumer yenilənməlidir; semantika dəyişir.
**Sonra nə oldu**: Ümumi qövs — çox yüksək miqyaslı sistem hadisələrin job-lardan daha faydalı olduğunu anladıqda Kafka-da qurtarır. Tumblr-in tarixçəsi kompakt dərslikdir.

### 5. WordPress infrastrukturuna miqrasiya (Automattic dövrü)
**Problem**: WordPress.com ilə yanaşı ikinci, kifayət qədər fərqli platformanı (Tumblr) işlətmək bahalıdır. Konsolidasiya pul və mühəndislik saatlarına qənaət edir.
**Seçim**: Tumblr datasını və xidmətini yavaş-yavaş WordPress infrastrukturuna miqrasiya edin — Tumblr məhsulu və UX-ini saxlayın, amma onu WordPress-in storage və xidmətiylə dəstəkləyin.
**Kompromislər**: Data modelləri tam uyğun gəlmir. Reblog zəncirləri WordPress-də yoxdur. Bir çox edge hadisəsi.
**Sonra nə oldu**: Automattic irəliləyiş yenilikləri dərc etdi. Miqrasiya böyük uzunmüddətli layihədir, amma Tumblr-ə Verizon-un heç vaxt vermədiyindən daha ucuz, daha uzunmüddətli ev verir.

## Müsahibədə necə istinad etmək
- PHP yüz milyonlarla istifadəçiyə miqyaslana bilər — amma spesifik iş yüklərinə (qraf traversalı, fan-out on write) aşağı səviyyəli dildə ayrı servis lazım ola bilər. Bu PHP uğursuzluğu deyil; yaxşı arxitektura seçimidir.
- Aktivlik feed-ləri üçün fan-out-on-write standart nümunədir. Laravel-də Redis + queued job-ları istifadə edərdiniz; məşhur istifadəçiləri oxuma zamanı pull hibridi ilə idarə edərdiniz.
- Queue seçimi təkamül edir. Sadəlik üçün Laravel-in database queue-su ilə başlayın, həcm tələb etdikdə Redis + Horizon-a keçin, dayanıqlılıq və sistemlərarası hadisələr vacib olduqda SQS və ya Kafka-ya keçin.
- İsti yolları denormalizasiya edin. Oxumaları sürətli etsə, şeyləri bir neçə dəfə saxlayın — reblog zəncirinə zəncirin başındakı mətnin denormalizasiya olunması lazımdır.
- Miqrasiyaları planlaşdırın. Tumblr-in sahiblik dəyişiklikləri bir neçə infra miqrasiyasını məcbur etdi — portabilliyə görə dizayn edin: cloud-spesifik lock-in-dən qaçın, data modelinizi sənədləşdirin, məntiqi schema-ları açıq saxlayın.

## Əlavə oxu üçün
- Blog post: "Tumblr Architecture - 15 Billion Page Views a Month" on High Scalability (around 2012).
- Blog post: "Staircar: Redis-powered notifications" — Tumblr engineering.
- Talk: "Scaling Tumblr" — various engineering talks at Surge / QCon in the 2012-2015 era.
- Blog posts: Tumblr engineering blog (tumblr-engineering archive) on MySQL sharding, search, and Scala migrations.
- Blog posts: Automattic engineering posts (2020+) on migrating Tumblr to WordPress infrastructure.
- Book: "Designing Data-Intensive Applications" by Martin Kleppmann — covers fan-out and feed generation patterns that directly apply to Tumblr's challenges.
- Talk: "Twitter Timeline" talks from 2013-2015 — parallels to Tumblr's dashboard.
- Engineering post: Blake Matheny and others on Tumblr's Finagle + Thrift adoption.
- Podcast: Debug or Software Engineering Daily episodes featuring former Tumblr engineers (including David Karp interviews).
