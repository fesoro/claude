# Slack

## Ümumi baxış
- Slack komanda mesajlaşması və əməkdaşlıq platformasıdır: kanallar, thread-lər, DM-lər, səsli huddle-lər, inteqrasiyalar, Slack Connect (şirkətlərarası kanallar), Slack Grid (enterprise multi-workspace).
- Miqyas: təqribən 20+ milyon gündəlik aktiv istifadəçi, milyonlarla eyni zamanda websocket bağlantısı, yüz minlərlə pullu komanda (2022-2024 Salesforce mənfəət rəqəmləri).
- Əsas tarixi anlar:
  - 2013 — İctimai olaraq buraxıldı (daxildə Stewart Butterfield-in uğursuz oyunu Glitch-dən doğuldu).
  - 2016 — PHP kod bazasını Hack-ə (Facebook dili) köçürməyə başladı.
  - 2017 — Threading xüsusiyyəti göndərildi.
  - 2019 — NYSE-də birbaşa listinq.
  - 2021 — Salesforce tərəfindən ~$27.7B-ə alındı.
  - 2023-2024 — Salesforce ekosistemi ilə dərin inteqrasiya, AI xüsusiyyətləri, davam edən Vitess miqrasiyası.

## Texnologiya yığını
| Qat | Texnologiya | Niyə |
|-------|-----------|-----|
| Dil | Hack (web tier), Java (real-time), Go, TypeScript | LAMP-mənşəli monolit üçün Hack; aşağı gecikmə realtime üçün Java; yeni servislər üçün Go. |
| Web framework | Daxili Hack framework (PHP app-ın törəməsi) | Hazır Hack framework yoxdur. |
| Əsas DB | MySQL, Vitess vasitəsilə shardlanıb | İlk gündən MySQL; tək instance limitlərindən keçmək üçün Vitess. |
| Cache | Memcached | Klassik LAMP seçimi. |
| Queue/messaging | Əvvəlcə MySQL-backed, indi Kafka | Dayanıqlı, yüksək həcmli event pipeline-ları üçün Kafka. |
| Search | Tarixən Solr, sonra Elasticsearch / öz axtarış servisi | Mesaj axtarışı miqyasda çətindir; iterate ediblər. |
| İnfrastruktur | AWS (EC2, S3, ElastiCache, və s.) | Erkən günlərdən AWS-də hostlanır. |
| Monitorinq | Prometheus, Grafana, daxili dashboard-lar, Wallace | Standart yığın plus xüsusi gecikmə dashboard-ları. |

## Dillər — Nə və niyə

### PHP → Hack
Slack ilk illərində LAMP stack app idi: PHP, MySQL, Apache. 2016-da kod bazasını Hack-ə portlamağa başladılar. Səbəblər:
- Statik tip — onların kod bazası ölçüsündə (milyonlarla sətir) dinamik PHP refaktorinq üçün ağrılı idi.
- Hack-in `Awaitable` ilə async I/O — tək request bir çox backend çağırışa fan-out edir; async onu paralel edir.
- Tipli generiklərlə kolleksiyalar (Vector<T>, Map<Tk, Tv>).

Miqrasiya inkremental idi, faylbəfayl, Hack-in tədricən tiplənməsindən istifadə edərək: yuxarıda `<?hh` ilə PHP ilə eyni sintaksis, sonra tədricən tip annotasiyaları əlavə etmə və massivləri kolleksiyalara çevirmə.

Yaxşı ictimai mənbə: "Rewriting the Slack Python and PHP codebase in Hack" — infra komandasından müxtəlif mühəndislik blog post-ları və talks.

### Java
Realtime mesajlaşma tier-i (milyonlarla websocket bağlantısı saxlayan və mesajları fan-out edən servis) Java-dadır. Bu hissəyə davamlı aşağı gecikmə və diqqətli yaddaş idarəetməsi lazımdır, ikisi də Hack-in güclü tərəfi deyil.

### Go
Bəzi edge / infrastruktur servisləri üçün istifadə olunur, məsələn, Flannel (edge cache və sessiya servisi).

### TypeScript
Front-end web client və Electron desktop app. Desktop client məşhur olaraq yaddaşa acdır çünki hər workspace əvvəlcə öz webview-u idi — sonrakı versiyalar bunu konsolidasiya etdi.

## Framework seçimləri — Nə və niyə

Slack Laravel və ya Symfony istifadə etmir. Onların framework-ü 2013-dən bəri orqanik olaraq böyüyən daxili monolitdir. O, WordPress dövrü PHP-dən bir çox ideya götürür (Slack-in təsisçi mühəndisləri, o cümlədən Cal Henderson, Flickr-də işləyib və praqmatik LAMP nümunələrini gətiriblər). Hack miqrasiyası ilə əlavə etdilər:
- Tipli request/response obyektləri.
- DI container.
- Request-in bir çox Memcached / MySQL / servis çağırışını eyni zamanda await etməsinə imkan verən async I/O nümunələri.

Client tərəfdə Backbone.js-dən (orijinal) React-a (2017 ətrafında əsas yenidən yazmalar üçün) və nəhayət daha müasir TypeScript-öncə arxitekturaya keçdilər.

## Verilənlər bazası seçimləri — Nə və niyə

### MySQL
Hər vacib state parçası MySQL-də yaşayır: istifadəçilər, komandalar, kanallar, mesajlar, reaksiyalar, fayl metadatası. Fayllar özləri S3-dədir.

Schema komanda (workspace) üzrə shardlanıb — tək komandanın datası bir yerdə yaşayır, bu Slack-in giriş nümunəsinə mükəmməl uyğun gəlir (adətən bir workspace daxilində sorğu edirsiniz).

### Vitess
2010-cu illərin sonunda tək-shard MySQL instance-ları ən böyük müştərilər üçün limitlərə çatdı. Slack Vitess-ə köçdü — YouTube-un MySQL shardlama və orchestration sistemi, indi CNCF layihəsi.

Vitess onlara verir:
- Online bölünüb yenidən shardlana bilən məntiqi shardlar.
- Routing qaydalarını başa düşən SQL proxy (vtgate).
- Minimum downtime ilə schema miqrasiyaları.
- Bağlantı pooling, beləliklə Hack monolit MySQL bağlantılarını birbaşa tükəndirmir.

Miqrasiya qeydləri Slack-in mühəndislik blogundadır ("Scaling datastores at Slack with Vitess").

### Memcached
Obyekt caching üçün: istifadəçi obyektləri, kanal metadatası, icazə yoxlamaları və bunu tələb edəcək qədər isti olan hər şey.

### Kafka
Queue və event sütunu. Email-lər, axtarış indeksləşdirməsi, analitika pipeline-ları, job retry-ləri — hər şey Kafka-dan keçir. Kafka-dan əvvəl Slack job queue-ları MySQL-də işlədirdi (təəccüblü uzağa gedir, amma nəhayət darboğaz oldu).

### Solr → Elasticsearch
Mesaj axtarışı Solr-də başladı. Miqyas və relevance tələbləri artdıqca Slack axtarışa çox sərmayə qoydu — ictimai talks var ("Search at Slack" by Sergey Moor at QCon / Strange Loop) xüsusi relevance tuning, workspace üzrə shardlama və köhnə mesajlar üçün isti/soyuq tier-ləri təsvir edən.

## Proqram arxitekturası

Slack **Hack monolit**-dir, diqqətlə seçilmiş edge servislər ilə:

- **Monolit (Hack)**: HTTP API, söhbətlər, icazələr, billing, inteqrasiyalar, admin idarə edir.
- **Flannel (Go)**: region-lokal edge cache və sessiya servisi. Client qoşulduqda Flannel onun workspace-nin kompakt görünüşünü (istifadəçilər, kanallar) yaddaşda saxlayır ki, client hər şeyi yenidən gətirməyə ehtiyac duymasın. İctimai post: "Flannel: An Application-Level Edge Cache to Make Slack Scale" (Slack engineering blog).
- **Realtime (Java)**: websocket termination. Milyonlarla bağlantını açıq saxlayır, hadisələri onlara yönəldir.
- **Job worker-ləri**: Kafka-dan istehlak edir, async iş görür (email, webhooks, axtarış indeksləşdirməsi).
- **Search service**: sorğu qatı ilə Solr/ES kluster-ləri.

```
 [Client (web / desktop / mobile)]
         |
         v
   [AWS ALB / edge]
         |
         +--> [Flannel (Go)] ---- session, presence, workspace metadata
         |
         +--> [Realtime (Java)] ---- websocket, events fan-out
         |
         v
   [Slack monolith (Hack on HHVM)]
         |
         +--> [Memcached]
         +--> [Vitess / MySQL shards by team]
         +--> [Kafka]  --->  [Job workers]
         +--> [Search cluster]
         +--> [S3 for files]
```

## İnfrastruktur və deploy
- AWS-də işləyir — EC2, S3, ElastiCache, RDS (əvvəl), indi Vitess vasitəsilə EC2-də daha çox özünü idarə edən MySQL.
- Deployment: canary + feature flag-lar ilə monolitin davamlı deployment-i. Gündə bir çox deploy.
- CI: daxili pipeline-lar, ağır test gating (məşhur olaraq nəhəng test suite-ləri var).

## Arxitekturanın təkamülü

1. **2013-2015**: Klassik LAMP. PHP + MySQL + Memcached + Solr. Tək monolit, komanda-shardlanmış DB.
2. **2015-2016**: Realtime Java servisi olaraq ayrılır. Flannel (Go) edge metadatasını boşaltmaq üçün təqdim edilir.
3. **2016-2019**: Hack miqrasiyası. Kafka MySQL-backed queue-ları əvəz edir. Elasticsearch Solr ilə yanaşı böyüyür.
4. **2019-indiki**: Horizontal MySQL miqyaslanması üçün Vitess. Slack Connect və Grid multi-workspace nümunələrini irəliləyir. AI və Salesforce inteqrasiyası.

## Əsas texniki qərarlar

### 1. PHP-dən Hack-ə keçmək
**Problem**: Kod bazası dinamik PHP-də təhlükəsiz refaktorinq üçün çox böyük oldu. Performans böhran deyildi, amma sürət yavaşlayırdı.
**Seçim**: Hack + HHVM mənimsəmək; tədricən tiplənmə ilə faylbəfayl miqrasiya.
**Kompromislər**: İşçi qüvvəsi hovuzu kiçildi (Hack developer-ləri az); istehsalda HHVM saxlamaq lazımdır.
**Sonra nə oldu**: Tipli monolit, daha yaxşı tooling, daha az runtime xətaları. Slack-in mühəndislik təşkilatı Meta xaricində böyük Hack mənimsəməsi üçün istinad oldu.

### 2. Flannel — monolitin qarşısında edge cache
**Problem**: Slack client qoşulduqda ona yüzlərlə KB workspace metadatası (istifadəçilər, kanallar, emoji) lazımdır. Hər bağlantının bunun üçün monolitə dəyməsi bahalı idi, xüsusilə regionlar arasında.
**Seçim**: Go-da Flannel qurun — regional cache ki, dəyişiklik hadisələrinə abunə olur və hər workspace-nin kompakt, yenilənmiş görünüşünü yaddaşda saxlayır. Client-lər bu oxumalar üçün Flannel ilə danışır.
**Kompromislər**: İşlətmək üçün yeni servis, Flannel replikaları ilə monolit arasında cache koherensiyası.
**Sonra nə oldu**: Monolitdə yük dramatik şəkildə düşdü; client bağlantısı üçün gecikmə regionlar arasında yaxşılaşdı. Kirayəçi başına oxunma yüklü metadata olan hər yerdə təkrar istifadə edilə biləndir.

### 3. MySQL horizontal miqyası üçün Vitess
**Problem**: Bəzi workspace-lər (nəhəng müəssisələr) tək MySQL shard-ından böyüdü. Əl ilə resharding ağrılı idi.
**Seçim**: Vitess mənimsəmə — onlayn resharding, shard-ları bölmə, minimum lock ilə schema miqrasiyaları işlətməyə imkan verir.
**Kompromislər**: Vitess işlətmək üçün mühüm bir sistemdir; onun əməliyyat modelini öyrənməlisiniz.
**Sonra nə oldu**: Davam edən çoxillik miqrasiya, arxitektura yenidən dizaynı olmadan artımı təmin edir.

### 4. Thread-lər (2017)
**Problem**: Məşğul kanalları izləmək çətin idi — söhbətlər bir-birinə qarışırdı.
**Seçim**: Thread-ləri paralel struktur kimi əlavə edin — mesaj thread-in əsası ola bilər; thread mesajlarında `parent_ts` olur.
**Kompromislər**: Data modeli dəyişikliyi axtarış, oxunmamış sayğaclar, bildirişlər, mobil sync üzərində dalğa yaradır.
**Sonra nə oldu**: Thread-ləri mövcud schema-ya retroaktiv olaraq uyğunlaşdırmaq klassik miqrasiya hekayəsidir — diqqətli indeksləşdirmə, read-path dəyişiklikləri və mobil app yeniləmələri tələb edir.

### 5. Job queue-nu MySQL-dən Kafka-ya köçürmək
**Problem**: MySQL-backed job queue kiçik olduqda işlətmək asandır, amma miqyasda isti nöqtəyə çevrilir: hər enqueue yazmadır, hər dequeue locking oxumadır.
**Seçim**: Kafka-ya köçün. Producer-lər topiclərə dərc edir; consumer-lər bölünmüş sırada oxuyur; Kafka dayanıqlılıq semantikası retry-ləri və replay-ləri idarə edir.
**Kompromislər**: Kafka işlətmək üçün başqa sistemdir; retry-lər/poison mesajlar strategiya tələb edir.
**Sonra nə oldu**: Queue ötürücülüyü artdı, əsas DB təzyiqi düşdü və digər servislərin də istehlak edə biləcəyi event şininə sahib oldular.

## PHP/Laravel developer üçün dərs
- Əsas DB-nizi kirayəçi (komanda, təşkilat, şirkət) üzrə shardlayın. Slack-in "komanda-shardlanmış MySQL" nümunəsi Laravel çoxlu-kirayəçi app-larına birbaşa tətbiq olunur.
- Kirayəçi başına metadata üçün edge cache-ləri (Flannel ideyası) kiçik olsanız belə öz servislərinə dəyə bilər. Laravel terminlərində bu, hadisələrlə isinən Redis-backed cache ola bilər, client-lərin danışdığı yüngül servisin arxasında oturaraq.
- Tədricən statik tip böyük kod bazalarında dividend verir. Hack-siz də, PHPStan level 8 + `@template` vasitəsilə generiklər təhlükəsizliyin çoxunu təxmin edir.
- Arxa plan job-ları dayanıqlı queue-a aiddir (Redis + Horizon, ya da SQS, ya da RabbitMQ, ya da Kafka). MySQL-backed jobs cədvəli nəhayət sizi dişləyəcək.
- Monolitdən uzaqlaşmayın, ta ki spesifik, ölçülmüş səbəbiniz olmasın. Slack servisi yalnız spesifik iş yükü problemini həll etdikdə ayırır.

## Əlavə oxu üçün
- Talk: "Scaling Slack" — Mark Christian, Strange Loop.
- Blog: "Flannel: An Application-Level Edge Cache to Make Slack Scale" (Slack Engineering).
- Blog: "Scaling Datastores at Slack with Vitess" (Slack Engineering).
- Talk: "Search at Slack" — Sergey Moor (QCon).
- Blog: "Rewriting the Slack codebase in Hack" (Slack Engineering).
- Talk: "How Big Technical Changes Happen at Slack" — Keith Adams.
- Blog: "Data Wrangling at Slack" (engineering posts on data pipelines).
- Talk: "Building Slack Grid" — engineering overviews of the enterprise tier.
