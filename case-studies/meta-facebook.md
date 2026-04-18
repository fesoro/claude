# Meta (Facebook)

## Ümumi baxış
- Meta Platforms (əvvəlki Facebook, Inc.) dünyanın ən böyük sosial şəbəkə ailəsini işlədir: Facebook, Instagram, WhatsApp, Messenger, Threads.
- Miqyas: tətbiq ailəsi üzrə təqribən 3 milyard aylıq aktiv istifadəçi (2024 açıq rəqəmləri). Petabaytlarla şəkil və video, sosial qrafda trilyonlarla kənar.
- Əsas tarixi anlar:
  - 2004 — Mark Zuckerberg Harvard yataqxanasında ilk versiyanı PHP-də (klassik LAMP) yazır.
  - 2008 — Miqyaslanma ağrıları başlayır; mühəndislər MySQL və Memcached üzərində xüsusi infra qatları qurmağa başlayır.
  - 2010 — HipHop for PHP (HPHPc) kompilyatoru buraxılır — PHP mənbəyini C++-a tərcümə edir.
  - 2013 — JIT ilə HHVM (HipHop Virtual Machine) HPHPc-ni əvəz edir.
  - 2013 — React ictimaiyyətə açılır.
  - 2014 — Hack dili buraxılır — PHP-nin tədricən tipli dialekti.
  - 2015-2020 — Klassik PHP daxildə əsasən təqaüdə çıxarılır; Hack + HHVM web tier olur.
  - 2021 — Şirkət Meta Platforms adlandırılır.

## Texnologiya yığını
| Qat | Texnologiya | Niyə |
|-------|-----------|-----|
| Dil | Hack (web tier), C++, Python, Rust, Java | Hack PHP-nin sürətli iterasiyasını saxlayır, amma tip və async əlavə edir; C++ perf sistemləri üçün; Python ML üçün; Rust yeni infra üçün. |
| Web framework | Daxili XHP / Hack frameworkləri | PHP şablonlarından böyüdü; hazır framework onların miqyasında sağ qala bilməzdi. |
| Əsas DB | MySQL (shardlanmış, UDB adlanır) | MySQL ilə başladılar və onu miqyasda işlətməyə çox sərmayə qoyduqları üçün qaldılar. |
| Cache | Memcached (dünyanın ən böyük deploymentlərindən biri), TAO | Memcached ümumi KV üçün; TAO isə qraf üçün xüsusi hazırlanmış cache qatıdır. |
| Queue/messaging | Scribe, LogDevice, Kafka (daxili forklar/variantlar) | Log-strukturlu, dayanıqlı, yüksək ötürücülüklü pipeline-lar. |
| Search | Unicorn (yaddaşdakı sosial qraf axtarışı), Elasticsearch variantları | Sosial qraf xüsusi strukturlar tələb edir. |
| İnfrastruktur | Öz data mərkəzləri, Open Compute Project aparatı, BGP əsaslı şəbəkə | Hiper-miqyas fiziki qatı sahib olmağı məcbur edir. |
| Monitorinq | ODS (Operational Data Store), Scuba (sürətli OLAP), Gorilla (time series) | Hazır alətlər onların metrik həcmini udmağı bacarmırdı. |

## Dillər — Nə və niyə

### PHP → Hack
Facebook PHP ilə başladı, çünki bu, 2000-ci illərin ortasının praqmatik web dili idi: ucuz hosting, böyük kitabxana, sadə request-response modeli və Zuckerberg onu artıq bilirdi. Uzun illər standart PHP-də qaldılar, amma kod bazası milyonlarla sətirdən keçəndə iki divara dəydilər:
1. Performans — hər request interpretator xərci ödəyirdi.
2. Baxım — tipləri olmayan dinamik dil 100M+ sətir koddan qorxuludur.

HipHop (2010) PHP-ni C++-a çevirərək və hər şeyi bir böyük binar fayla kompilyasiya edərək performans problemini həll etdi. İşləyirdi, amma kompilyasiya vaxtları nəhəng olduğu üçün iterasiya ağrılı idi. HHVM (2013) onu JIT ilə əvəz etdi — hər iki dünyanın ən yaxşısı: sürətli startup, JIT ilə kompilyasiya olunan isti yollar.

Hack (2014) dil cavabı oldu. O, PHP-uyğundur, amma əlavə edir:
- Tədricən statik tiplər (faylbəfayl tiplər əlavə edə bilərsiniz).
- Generics, enums, shape types.
- Async/await paralel I/O üçün.
- Kolleksiyalar (Vector, Map, Set) xam PHP massivlərini əvəz edir.
- Daha sərt rejimlər (`<<__Strict>>`) pis PHP nümunələrini qadağan edir.

2010-cu illərin sonunda klassik PHP web tier-dən faktiki olaraq getmişdi — hər şey Hack on HHVM idi.

### C++
Performansa həssas infrastruktur üçün istifadə olunur: TAO, memcached extension-ları, Thrift RPC, LogDevice, RocksDB, çox verilənlər bazası daxiliyyatları. C++ Facebook-un sistem mühəndislərinin yaşadığı yerdir.

### Python
ML (PyTorch Facebook AI Research-dən gəldi), data science, daxili alətlər, ops skriptləri üçün istifadə olunur.

### Rust
Yeni infra və client işi (Rust-da Mercurial server, bəzi source control alətləri). Meta ən böyük Rust istifadəçilərindən biridir.

### JavaScript / TypeScript / Flow
React JS-də yazılıb. Facebook həmçinin Flow-u (JS üçün type checker) sənaye TypeScript-i standartlaşdırmadan əvvəl ixtira etdi. Hack və Flow dizayn DNA-sını paylaşır.

## Framework seçimləri — Nə və niyə

Laravel/Rails mənasında "Facebook web framework" yoxdur. Bunun əvəzinə qatlar qurdular:
- **XHP** — PHP-yə (sonra Hack-ə) extension ki, dilin özündə HTML-bənzər komponentlər yazmağa imkan verir. Konseptual olaraq JSX-ə çox yaxındır. XHP React-dən əvvəl gəldi; React əslində XHP ideyalarının client-ə daşınması idi.
- **Hack standart kitabxanaları** — kolleksiyalar, async, tipli request/response obyektləri, tip təhlükəsizliyi ilə ORM-bənzər MySQL wrapper-ları.
- **React** (2013) — Jordan Walke tərəfindən Facebook-un UI mürəkkəbliyini (newsfeed, ads manager) həll etmək üçün daxili layihə kimi başladı. Açıq mənbəyə çevrildi, sonra sənayeni zəbt etdi.
- **GraphQL** (2012 daxildə, 2015 ictimaiyyətə) — "mobil client-lər çox spesifik data formaları istəyir, REST round-trip-ləri çox bahalıdır" problemini həll etmək üçün ixtira edildi. Bu gün Meta-nın bir çox məhsulu üçün default data qatıdır.

## Verilənlər bazası seçimləri — Nə və niyə

### MySQL (UDB)
İstifadəçi datası MySQL-də yaşayır. Facebook MySQL-i işlətməyə nəhəng sərmayə qoyub — dünyanın ən böyük fleet-lərindən birinə sahibdirlər və bir çox MySQL töhfəçisi indiki və ya əvvəlki Meta mühəndisləridir (RocksDB storage engine ilə öz forklarını, MyRocks-u, saxlayırlar).

MySQL ağır şəkildə shardlanıb. Bir "shard" istifadəçilərin bir dilimini saxlayır və bir çox başqa varlıq istifadəçidən asılıdır. Shardlar arasında qlobal JOIN yoxdur; bu, tətbiqin işidir.

### TAO — Qraf Cache
Facebook-un çətin hissəsi key-value axtarışı deyil — sosial qrafdır. Newsfeed-inizi yükləyəndə server sizə lazımdır: dostlarınız, onların son postları, həmin postlara like/şərh, kim şərh edib, *siz* onları like edib-etmədiyiniz və s. Səhifə baxışına minlərlə qraf traversalı.

TAO (~2013) tətbiq qatı ilə MySQL arasında oturur. O, obyektlər (node-lar) və asosiasiyalar (kənarlar) data modeli olan write-through cache-dir. `assoc_get` çağırışı nodedan kənarları tapır, paginasiya ilə, isteğe bağlı zaman filtrləri ilə. Arxada TAO asosiasiyaları Memcached plus MySQL-də saxlayır, coğrafi olaraq followers/leader nümunələri ilə paylanmış.

Məqalə: "TAO: Facebook's Distributed Data Store for the Social Graph" (USENIX ATC 2013).

### Memcached
Məşhur məqalə: "Scaling Memcache at Facebook" (NSDI 2013). Orada ixtira olunan və ya populyarlaşan konsepsiyalar:
- Leases (thundering herd-ləri və stale set-ləri qarşısını alır).
- Gutter pool-lar (kaskad yükü olmadan xətaları udmaq üçün).
- McSqueal / mcrouter — minlərlə memcached node-un qarşısında proxy ki, routing, consistent hashing, regionlar arası replikasiyanı idarə edir.

### Presto / Trino
Facebook-da (2012-2013) HDFS üzərində interaktiv SQL sorğuları üçün ixtira edildi — Hive çox yavaş idi. Bu gün Trino eyni kod bazasının açıq mənbəli davamıdır və standart analitika engine-idir.

### Cassandra
Əvvəlcə inbox search xüsusiyyəti üçün Facebook-da (~2008) Avinash Lakshman tərəfindən yazıldı (Amazon Dynamo-da da işləyib). Açıq mənbəyə çevrildi və Apache layihəsi oldu. Facebook sonradan inbox ehtiyacları üçün HBase və digər store-ların xeyrinə Cassandra-dan uzaqlaşdı.

### RocksDB
Yüksək performanslı embedded KV store, Facebook daxilində LevelDB-dən (2012) forklandı. İndi demək olar ki, hər yerdə storage engine kimi istifadə olunur (MyRocks, Kafka Streams, CockroachDB, TiKV hamısı onu istifadə edir).

## Proqram arxitekturası

- **Web tier**: tək nəhəng Hack monolit ("www" kod bazası — 100M+ sətir aralığında deyilir). Bir repo, davamlı deployment. Bəli, Meta miqyasında monolit.
- **Backend servislər**: C++/Java/Python-da yüzlərlə (minlərcə?) servis, Thrift RPC üzərindən danışılır.
- **Data tier**: qraf üçün MySQL + TAO, müxtəlif ixtisaslaşmış store-lar (mesajlar üçün HBase, KV üçün ZippyDB, blob üçün Manifold, şəkillər üçün Haystack).
- **Client tier**: React web, React Native mobile (qismən), native iOS/Android.

```
   [User]
      |
      v
 [Edge / PoP]---- TLS termination, routing
      |
      v
 [Load balancers, proxygen C++ HTTP server]
      |
      v
 [Hack web tier (monolith, HHVM)]  <-->  [Memcached fleet via mcrouter]
      |                                  [TAO — graph cache]
      |                                  |
      v                                  v
 [Thrift services:                 [MySQL shards (UDB)]
  search, ads, ranking,            [Haystack (photos)]
  ML inference, ...]               [HBase, ZippyDB, Manifold]
      |
      v
 [Data warehouse: HDFS, Presto/Trino, Spark]
```

## İnfrastruktur və deploy
- Bir neçə qitədə öz data mərkəzləri; AWS/GCP-dən asılılıq yoxdur.
- Serverlər Facebook-un 2011-ci ildə başlatdığı və açıq mənbəyə çevirdiyi Open Compute Project (OCP) dizaynları üzərində qurulub.
- Şəbəkə: xüsusi top-of-rack switchlər, Facebook-un Wedge/Backpack platformaları, BGP daxili routing (BGP-in-the-DC-ni populyarlaşdırmağa kömək etdilər).
- CI/CD: www monolit üçün davamlı deployment. Gündə çoxlu deploy, avtomatlaşdırılmış testlər və canary ilə qorunur. Məşhur "push" sistemi yeni build-i maşınların kiçik faizinə tədricən buraxır, sonra artırır.
- Feature flag-lar ("gatekeeper" adlanır) istifadəçi alt dəstləri üçün xüsusiyyətləri açıb bağlamağın universal yoludur.

## Arxitekturanın təkamülü

1. **2004-2007**: Klassik LAMP monolit. PHP, MySQL, Memcached.
2. **2008-2010**: Miqyaslanma böhranı — istifadəçi üzrə MySQL shardlama, mcrouter qurma, TAO sələflərini ixtira etmə, PHP perf-ə sərmayə.
3. **2010**: HipHop kompilyatoru — onlara vaxt qazandırır.
4. **2013**: HHVM + TAO + Memcached lease-ləri + React-in ilk versiyası. Müasir forma yaranır.
5. **2014-2018**: Hack kod bazasında PHP-ni faylbəfayl əvəz edir. GraphQL yetişir. Infra komandası öz aparatına (OCP), öz storage-inə (Haystack, f4) keçir.
6. **2019-indiki**: Hack-də dərin tiplər, nəhəng ML infrastrukturu (PyTorch, feature store-lar), Rust mənimsənilməsi, privacy infra diqqəti.

## Əsas texniki qərarlar

### 1. Yenidən yazmaq əvəzinə PHP-də (sonra Hack-də) qalmaq
**Problem**: 2008-ə qədər PHP-nin dinamik təbiəti və interpretator əlavə yükü ekzistensial təhlükə kimi görünürdü. Bir çox şirkət Java və ya C++-da yenidən yazardı.
**Seçim**: PHP kod bazasını saxlamaq, PHP-ni sürətli (HipHop, HHVM) və təhlükəsiz (Hack) etməyə sərmayə qoymaq.
**Kompromislər**: Nəhəng ilkin xərc (kompilyator + VM + dil qurmaq kiçik deyil), amma illərlə məhsul kodunu və developer sürətini qoruyub saxladı.
**Sonra nə oldu**: HHVM və Hack onlara əsası düzəldərkən göndərməyə davam etməyə imkan verdi. Risk özünü doğrultdu — web tier bu gün də Hack-dir.

### 2. TAO qurmaq
**Problem**: Newsfeed və profil səhifələri minlərlə qraf axtarışı edir. MySQL + ümumi Memcached ardıcıl, aşağı gecikməli, regionlar arası qraf oxumalarını təmin edə bilmədi.
**Seçim**: Obyektlər və asosiasiyalar haqqında bilən, cache-i idarə edən, regionlar arasında read-your-writes ardıcıllığı ilə replikasiya edən xüsusi servis qurmaq.
**Kompromislər**: Başqa bir hərəkətli hissə və regionlar arasında sonunda ardıcıl (tanınan stale-read pəncərələri var).
**Sonra nə oldu**: TAO Meta-da hər sosial məhsulun sütunu oldu. Məqalə qraf verilənlər bazası mühəndisləri üçün tələb olunan oxudur.

### 3. React ixtirası
**Problem**: Ads Manager və News Feed mutasiya bug-larının sürəti məhv etdiyi mürəkkəb statefull UI-lara sahib idi. İkitərəfli binding (Angular 1 üslubu) miqyaslana bilmədi.
**Seçim**: Jordan Walke birtərəfli data axını və virtual DOM diff-ləmə ətrafında kitabxana qurdu. Serverdə XHP, client-də JSX.
**Kompromislər**: Yeni zehni model, JSX-ə ilkin icma müqaviməti.
**Sonra nə oldu**: 2013-də açıq mənbəyə çevrildi, növbəti onilliyin dominant front-end framework-ü oldu.

### 4. Open Compute Project
**Problem**: Dell/HP-dən hazır serverlərdə Facebook-un ehtiyacı olmayan komponentlər və Facebook-un ödəmək istəmədiyi marjlar var idi.
**Seçim**: Öz serverlərini, rack-lərini və soyutma sistemlərini dizayn etmək, sonra 2011-də dizaynları açıq mənbəyə çevirmək.
**Kompromislər**: Nəhəng aparat R&D sərmayəsi.
**Sonra nə oldu**: OCP sənaye standartı oldu; Microsoft, Goldman Sachs və başqaları onu mənimsədilər. Facebook milyardlar qənaət etdi.

### 5. GraphQL
**Problem**: Yavaş şəbəkələrdə mobil tətbiqlərə kiçik, dəqiq cavablar lazım idi. REST zəncirvari round-trip-lər və ya şişman endpoint-lər tələb edirdi.
**Seçim**: Datanı tipli qraf kimi təsvir etmək, client-lərə tam nəyə ehtiyac duyduqlarını bildirməyə imkan vermək, bir round-trip göndərmək.
**Kompromislər**: Server mürəkkəbliyi artır (resolver-lər, N+1 problemləri, caching REST-dən çətindir).
**Sonra nə oldu**: 2012-də daxildə, 2015-də açıq mənbəyə, indi sənaye standartıdır.

## PHP/Laravel developer üçün dərs
- PHP monolit milyardlarla istifadəçiyə miqyaslana bilər — amma yalnız platformaya sərmayə qoysanız (Hack-üslublu tiplər, güclü alətlər, xüsusi runtime, ya da minimum PHP 8 JIT, OPcache və intizamlı statik analiz).
- Cache qatları maraqlı mühəndisliyin baş verdiyi yerdir. "Scaling Memcache at Facebook" oxuyun və Laravel sistemində lease-ləri və ya gutter pool-ları necə tətbiq edəcəyinizi düşünün.
- Tiplər böyük kod bazalarında qüvvə artırıcılarıdır. Hack-ə keçə bilmirsinizsə, ən azı PHPStan / Psalm-ı ən yüksək səviyyədə işlədin və generics-üslublu `@template` annotasiyalarını istifadə edin.
- Data modelinizi başa düşən xüsusi cache (qraflar üçün TAO kimi) spesifik iş yükləri üçün ümumi Redis-i üstələyə bilər — amma onu yalnız ümumi alətlərin darboğaz olduğunu sübut etdikdə ixtira edin.
- Monolit üzərində davamlı deployment mümkündür. Etsy və Facebook bunu sübut etdi; sehr feature flag-lar, canary və observability-dədir — arxitekturada deyil.

## Əlavə oxu üçün
- Paper: "TAO: Facebook's Distributed Data Store for the Social Graph" (USENIX ATC 2013).
- Paper: "Scaling Memcache at Facebook" (NSDI 2013).
- Paper: "HipHop for PHP: Moving Fast" (Facebook engineering, 2010).
- Paper: "Finding a Needle in Haystack: Facebook's Photo Storage".
- Talk: "Hack: A New Programming Language for HHVM" — Strange Loop / other conferences.
- Talk: "React: Rethinking Best Practices" by Pete Hunt (JSConf EU 2013).
- Book chapter: "Facebook" in "The Architecture of Open Source Applications".
- Blog series: Facebook Engineering blog posts on Scribe, LogDevice, Gorilla time series, Scuba.
- Paper: "Gorilla: A Fast, Scalable, In-Memory Time Series Database" (VLDB 2015).
- Paper: "Presto: SQL on Everything" (ICDE 2019).
