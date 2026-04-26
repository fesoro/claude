# Shopify (Lead)

## Ümumi baxış
- Shopify kommersiya platformasıdır: hostlanmış onlayn mağazalar, checkout, ödənişlər, POS aparatı, göndərmə, tətbiqlər və developer platforması.
- Miqyas: ABŞ e-kommersiya GMV-nin təqribən 10%-ni təmin edir, ildə $200B-dən çox GMV (Shopify investor filings 2023). Black Friday / Cyber Monday zirvələri saniyədə 80,000 request ətrafında, dəqiqədə milyonlarla RPM davamlı partlayışlarla.
- Əsas tarixi anlar:
  - 2006 — Tobi Lütke, Daniel Weinand və Scott Lake Shopify-ı təsis edir. Tobi Ruby on Rails-də snowboard mağazası (Snowdevil) qurur və platformanı çıxarırdı.
  - 2008 — Shopify məhsul olaraq buraxılır. Tobi Rails core contributor olur.
  - 2015 — NYSE-də IPO.
  - 2017-2019 — Kirsten Westeinde və komandası tərəfindən "Deconstructing the Monolith" işi — sərhədli kontekstlərə modullaşdırma.
  - 2019-2021 — Pod-lar / shard-lar arxitekturası + MySQL miqyaslanması üçün Vitess.
  - 2022-2024 — Getdikcə böyüyən zirvələrdə Black Friday üçün əsas e-kommersiya infrastrukturu.

## Texnologiya yığını
| Qat | Texnologiya | Niyə |
|-------|-----------|-----|
| Dil | Ruby (əsas), Go (perf servislər), TypeScript (frontend) | Ruby Shopify-ın ürəyidir; CPU-yüklü hissələr üçün Go. |
| Web framework | Ruby on Rails | İlk gün seçimi; Tobi Rails contributor-dur. |
| Əsas DB | MySQL, Vitess vasitəsilə shardlanıb | Döyüşdə sınanmış; horizontal miqyas üçün Vitess. |
| Cache | Memcached, Redis | Səhifə/obyekt cache üçün Memcached, queue və rate limit-lər üçün Redis. |
| Queue/messaging | Redis (Resque → Sidekiq), hadisələr üçün Kafka | Sidekiq Ruby standartıdır; sistemlərarası hadisələr üçün Kafka. |
| Search | Elasticsearch | Məhsul boyu default axtarış engine. |
| İnfrastruktur | Google Cloud + spesifik hissələr üçün öz aparatı | GCP-yə köçürüldü; Shopify GCP-nin ən böyük Kubernetes müştərilərindən biridir. |
| Monitorinq | Prometheus, Grafana, Bugsnag, xüsusi SLO sistemləri, OpenTelemetry | Standart plus öz SRE tooling-i. |

## Dillər — Nə və niyə

### Ruby
Shopify qəsdən Ruby on Rails-dir. Tobi Lütke Rails core contributor-dur və Shopify Rails inkişafını sponsor edir (bir neçə Rails maintainer-i əmək haqqına saxlayır). Bu nadirdir — böyük Rails şirkətlərinin əksəriyyəti Rails-dən uzaqlaşır; Shopify ikiqat artırır.

Niyə Ruby: developer məhsuldarlığı və icma. Rails developer aşağı səviyyəli dildə günlər çəkəcək xüsusiyyəti bir neçə saatda göndərə bilər. Siz ildə yüzlərlə xüsusiyyət göndərən məhsul şirkəti olanda bu artır.

### Go
Shopify Ruby-nin GC-si və ya tək thread request modeli zərər verən spesifik servislər üçün Go istifadə edir: Shopify-in BFCM Runbook servisi, storefront pipeline-ın hissələri, milyardlarla webhook çatdırılması üçün platformasının hissələri, bəzi proxy-lər və load shedder-lər (Go-da "Toxiproxy" və "Bouncer" və digər alətləri yazdılar).

### TypeScript + React
Storefront, admin console və headless storefront-lar üçün Hydrogen framework. Hydrogen onların Oxygen edge şəbəkəsində işləmək üçün qurduqları React əsaslı framework-dür.

### Lua
Storefront rendering-də istifadə olunur (Liquid şablonları səmərəli koda kompilyasiya olunur və edge-in bəzi hissələri NGINX/OpenResty üçün Lua işlədir).

### Digərləri
Performansa həssas edge-lər üçün bəzi Rust (bəzi Rust alətlərini açıq mənbəyə çevirdilər). ML/data üçün Python.

## Framework seçimləri — Nə və niyə

**Ruby on Rails** — açıq şəkildə. Məşhur "Majestic Monolith" fəlsəfəsi (DHH-nin blog post-u və Tobi tərəfindən təkrarlanan) deyir: bir böyük Rails app qurun, sərhədli kontekstlərlə mütəşəkkil saxlayın, microservis-lərin sirena səsinə müqavimət göstərin.

Shopify lakin təmiz monolit qalmadı. Onlar **monolit daxilində modullaşdırmaya** sərmayə qoydular:
- "Komponentlər" (`app/components/` altındakı top-level qovluqlar və ya repo kökündə `components/`) ki, sərhədli kontekstlərə sahibdir: Orders, Payments, Checkout, Shipping və s.
- Komponentlər arasında açıq ictimai API-lər — başqa komponentin daxili hissələrinə çata bilməzsiniz.
- Sərhədləri tətbiq etmək üçün statik analiz (Ruby üçün tədrici tiplənmə Sorbet).
- Zamanla bəzi komponentlər yalnız modullaşdırma onların faydalanacağını ortaya çıxaranda ayrı servislərə çıxarılır.

Kirsten Westeinde-nin "Deconstructing the Monolith" talk-ı (Shopify Unite / RailsConf) kanonik istinaddır.

**Sorbet** — Stripe-ın Ruby type checker-i, Shopify-da Ruby-də tip təhlükəsizliyi üçün ağır istifadə olunur. Onlar geri töhfə verirlər.

## Verilənlər bazası seçimləri — Nə və niyə

### MySQL
Əsas store. Shopify ictimai olaraq **shop** (kirayəçi) üzrə shardlamanı əsas dizayn qərarı kimi təsvir etdi. Shop-un datası — məhsullar, sifarişlər, müştərilər, temalar — "Pod" adlanan shard-da bir yerdə yaşayır.

### Pod-lar
"Pod" izolyasiya edilmiş vahiddir — öz verilənlər bazası, öz Redis-i, öz Memcached-i və öz job queue-sı. Shop üçün request o shop-un Pod-una yönləndirilir. Bir Pod-un uğursuzluğu yalnız orada olan shop-lara təsir edir.

Bu əsasən **cell-based arxitekturanın** kirayəçi-shardlanmış SaaS-ə tətbiqidir. Partlayış radiusunu məhdudlaşdırır və nəhəng horizontal miqyaslanmanı mümkün edir.

### Vitess
Pod-lar altında (və miqyaslanma üçün onlar arasında) Shopify resharding, sorğu yönləndirmə və downtime olmadan online schema dəyişiklikləri idarə etmək üçün Vitess-i — YouTube-un MySQL shardlama sistemini — mənimsədi.

### Memcached + Redis
Cache-lənmiş obyektlər (məhsul səhifələri, tema asset-ləri) üçün Memcached. Sidekiq queue-ları, rate limiter-lər, qısamüddətli state üçün Redis.

### Elasticsearch
Admin üçün axtarış (sifarişləri, məhsulları tapma) və storefront filtrləmə.

### Kafka
Platform boyu event şini. Webhooks, analitika pipeline-ları, cache invalidasiyaları, sifariş hadisələri. Shopify təmiz data-infra şirkətləri xaricində ən böyük Kafka istifadəçilərindən biridir.

## Proqram arxitekturası

Shopify arxitekturası **modul Rails monolit**-dir ("Shopify Core" və ya sadəcə "core" adlanır), **Pod-lar** / cell-based kirayəçi shardlama modelində işləyir və ətrafında bir neçə həsr olunmuş servis var:

- **Core monolith (Rails)**: əsas biznes məntiqi.
- **Checkout**: illərlə monolit daxilində yerləşirdi; sonra BFCM miqyaslanması və müstəqil buraxılış sürəti üçün ayrı servisə çıxarıldı.
- **Storefront renderer**: oxunma trafiki üçün optimallaşdırılıb (alıcıya üz tutan storefront). Cache-lənmiş Liquid şablonlarından istifadə edir, edge-dən xidmət göstərir (Oxygen).
- **Ödənişlər / Shopify Payments**: izolyasiya olunmuş servislər (PCI sərhədləri).
- **Shop Pay**: sürətləndirilmiş checkout şəbəkəsi üçün ayrı servis.
- **Webhook çatdırılma servisi** (Go): milyonlarla endpoint-ə fan-out.
- **Tətbiqlər / Partner platforması**: tətbiq ekosistemi üçün servislər.

```
  [Buyer]
      |
      v
 [CDN + Oxygen edge (Hydrogen / storefront renderer)]
      |
      v
 [Cloud Load Balancer]
      |
      v
 [Router — picks Pod by shop_id]
      |
      v
  +---Pod 1--------+   +---Pod 2--------+   ... hundreds of pods
  | Rails monolith |   | Rails monolith |
  | MySQL shard    |   | MySQL shard    |
  | Redis          |   | Redis          |
  | Memcached      |   | Memcached      |
  | Sidekiq        |   | Sidekiq        |
  +----------------+   +----------------+
      |
      v
 [Cross-pod services: Checkout, Payments, Search, Webhooks, Kafka]
```

## İnfrastruktur və deploy
- Google Cloud-a köçürüldü (əvvəl öz data mərkəzləri idi). GCP-nin flaqman Kubernetes müştərilərindən biri.
- Deployment: Shipit vasitəsilə (öz alətləri, açıq mənbəyə çevrilib) monolitin davamlı deployment-i. Gündə bir çox deploy.
- BFCM (Black Friday / Cyber Monday) öz illik playbook-una sahibdir — pod-ları miqyaslama, əlavə tutum, hazırlıq məşqləri. Hər il mühəndislik retrospektivləri dərc edirlər.

## Arxitekturanın təkamülü

1. **2006-2010**: Kiçik Rails app, Postgres/MySQL, tək qutudan bir neçəsinə. Klassik startup Rails.
2. **2011-2015**: Mühüm Rails monolitinə qədər böyüdü, MySQL-ə köçdü, Resque/Sidekiq-də ağır arxa plan job iş yükləri.
3. **2015-2018**: Kirayəçi izolyasiyası üçün Pod-lar arxitekturası. Memcached, Redis, Elasticsearch əlavə olundu. Modullaşdırma işi başlayır.
4. **2018-2020**: "Deconstructing the Monolith" — komponent sərhədləri, Sorbet, Checkout və s. üçün diqqətli servis ayrılması.
5. **2020-2023**: Vitess, Hydrogen (edge-rendered storefront-lar), Oxygen (onların CDN-i), ağır Kafka istifadəsi.
6. **2024-indiki**: Davamlı modul monolit + cell-based arxitektura hekayəsi. AI xüsusiyyətləri ("Shopify Magic").

## Əsas texniki qərarlar

### 1. Pod-lar — partlayış radiusu izolyasiyası ilə kirayəçi shardlama
**Problem**: Tək qlobal DB onların miqyasında yaşaya bilməz. Ümumi horizontal shardlama izolyasiyanı itirir — bir səs-küylü kirayəçi hamına zərər verə bilər.
**Seçim**: Hər shop-u Pod-a təyin edin. Trafiki `shop_id` ilə yönləndirin. Hər pod-un öz DB, cache və job-ları var. Pod digərlərinə təsir etmədən köçürülə, yenidən shardlana və ya yenidən başladıla bilər.
**Kompromislər**: Pod-lar arası sorğular bahalıdır; admin reporting pod-lar arasında aqreqasiya etməlidir. Yönləndirmə qatı kritik olur.
**Sonra nə oldu**: Kirayəçi-shardlanmış SaaS üçün istinad arxitekturası oldu. Öz arxitekturalarını dizayn edərkən bir çox şirkət Shopify Pod-larını sitat gətirir.

### 2. Majestic Monolit-də qalmaq (modullaşdırma ilə)
**Problem**: Onların ölçüsündə, ənənəvi müdriklik "microservice-lərə gedin" deyir.
**Seçim**: Modul monolitə ikiqat. Komponent sərhədlərini tətbiq edin, tiplər üçün Sorbet istifadə edin, istifadə hadisəsi həqiqətən tələb etdikdə servisləri ayırın.
**Kompromislər**: Tooling-ə sərmayə qoymaq lazımdır (Packwerk — onların öz paket-sərhəd tətbiqediciləri, açıq mənbəyə çevrilib). Microservis dükanlarından gələn yeni işçilər vərdişləri unutmalıdırlar.
**Sonra nə oldu**: Shopify birləşmiş kod bazası ilə nəhəng miqyasda məhsuldar qalır. Onların "Modular Monolith" post-ları tələb olunan oxudur.

### 3. MySQL üçün Vitess
**Problem**: Pod-larla belə, ən böyük shop-lar tək MySQL instance-dan daşır. Və əməliyyat işi (reshardlama, schema miqrasiyaları) ağrılıdır.
**Seçim**: Vitess-i mənimsəyin. Onlayn reshardlama, minimum lock ilə schema miqrasiyaları, sorğu yönləndirmə.
**Kompromislər**: Vitess işlətmək üçün ağır sistemdir; həsr olunmuş komanda tələb edir.
**Sonra nə oldu**: Miqyaslanma tavanını yüngülləşdirdi; yenidən platform dəyişmədən daha çox artımı mümkün etdi.

### 4. Hydrogen + Oxygen — öz storefront framework-ləri + edge
**Problem**: Headless kommersiya (Shopify API-ları ilə danışan React əsaslı storefront-lar) tacirlərə öz storefront-larını host etməyi tələb edirdi, bu da əməliyyat baxımından ağır idi.
**Seçim**: Hydrogen (React əsaslı, Remix-üslubu framework) və Oxygen (Hydrogen storefront-ları üçün Shopify-hostlanmış edge runtime) qurun.
**Kompromislər**: Saxlamaq üçün yeni framework; sıx boşluqda mövcud framework-lərlə rəqabət edir.
**Sonra nə oldu**: Shopify-a fərqləndirilmiş headless təklif verdi; edge-rendering-i platformaya birbaşa gətirdi.

### 5. Rails-i sponsor etmək
**Problem**: Rails icması qocalırdı; böyük şirkətlər Rails-i tərk edirdi (Twitter və s.). Rails durğun qala bilərdi.
**Seçim**: Shopify Rails core developer-lərini (Aaron Patterson, Eileen Uchitelle və başqalarını) işə götürür və upstream-ə mühüm töhfələr verir. Rails yaxşılaşdırmalarını multi-database dəstəyi, async sorğular, MySQL xüsusiyyətləri ətrafında irəlilətdi.
**Kompromislər**: Daxili xüsusiyyətlər əvəzinə OSS-ə mühəndislik sərmayəsi.
**Sonra nə oldu**: Rails sağlam qalır; Shopify özlərinin spesifik olaraq ehtiyac duyduğu xüsusiyyətlərdən faydalanır. Rails ekosistemi üçün böyük qələbə.

## Müsahibədə necə istinad etmək
- Məhsuldar framework-də (Rails, Laravel) yaxşı strukturlaşdırılmış monolit onlarla milyardlarla GMV-yə miqyaslana bilər. "Shopify microservice-lərə getdi" şayiələrinin sizi aldatmasına icazə verməyin — onlar əksini etdilər.
- Kirayəçi shardlama (Pod-lar) PHP/Laravel SaaS app-larının birbaşa istifadə edə biləcəyi bir nümunədir. Laravel app kirayəçi-spesifik DB bağlantısına yönləndirə bilər (Tenancy for Laravel, Spatie Multitenancy) və hüceyrələr tam Kubernetes namespace-ləri ola bilər.
- Modul sərhədlərinə erkən sərmayə qoyun. CI-də arxitektura qaydalarını tətbiq etmək üçün Deptrac (Packwerk-in PHP ekvivalenti) kimi bir şey istifadə edin. Monolit yalnız sərhədlər tooling ilə tətbiq edildikdə baxımlı qalır.
- Tədrici tiplənmə (Ruby-də Sorbet, PHP-də PHPStan/Psalm) böyük dinamik kod bazasını tərbiyələndirmə üsuludur. PHPStan level 8-ə bağlanın.
- Asılı olduğunuz OSS-i sponsor edin. Şirkətiniz Laravel-ə mərc edirsə, ekosistemi üçün ödəməyi nəzərdən keçirin (Tinkerwell, Laravel sponsorlar proqramı və ya PR töhfələri).

## Əlavə oxu üçün
- Talk: "Deconstructing the Monolith" — Kirsten Westeinde (Shopify Unite, RailsConf).
- Talk: "Under Deconstruction: The State of Shopify's Monolith" — Kirsten Westeinde (GOTO).
- Blog: "The Magic of Shopify's Pods Architecture" — Shopify engineering.
- Blog: "How Shopify Manages API Versioning" — Shopify engineering.
- Blog: "Deploying New Code at Shopify" — Shopify engineering.
- Talk: "Writing a DSL in Ruby" — Tobi Lütke (RailsConf).
- Open source: Packwerk, Shipit, Toxiproxy, Bouncer, Maintenance Tasks — many at github.com/Shopify.
- Blog: "Hydrogen & Oxygen: Shopify's React Framework and Edge" — Shopify engineering.
- Book: "The Rails Doctrine" (DHH, applicable to Shopify's philosophy).
