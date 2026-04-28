# Notion (Senior)

## Ümumi baxış
- **Nə edir:** Hər şeyi bir yerdə iş sahəsi: qeydlər, sənədlər, wiki-lər, verilənlər bazaları, layihə idarəetməsi. Notion-da hər şey "block"-dur (mətn bloku, səhifə bloku, database bloku, embed bloku). Real-time birgə redaktə.
- **Yaradılıb:** 2013-cü ildə Ivan Zhao və Simon Last tərəfindən.
- **İşə salınma:** 2015-də erkən giriş, 2.0 yenidən yazılması 2018-də işə salındı.
- **Miqyas:**
  - 100M+ istifadəçi.
  - Fərdlər, kiçik komandalar və böyük korporasiyalar tərəfindən istifadə olunur.
- **Əsas tarixi anlar:**
  - 2015: 1.0 yayımlandı, amma komanda razı deyildi; yenidən yazdılar.
  - 2018: Notion 2.0 — müasir versiya, sürətlə böyüdü.
  - 2020: pandemiya dövrü artım.
  - 2020–2021: **Postgres yazı miqyas tavanına çatdılar**; sharding layihəsi başladılar.
  - 2022: məşhur blog *"Herding elephants: Lessons learned from sharding Postgres at Notion"*.
  - 2023: Notion AI; alışlar (Cron, Skiff).

Notion **TypeScript full-stack monolit** və **praktik gec mərhələ Postgres sharding**-i üçün əsas müasir nümunədir.

## Texnologiya yığını

| Layer | Technology | Why |
|-------|-----------|-----|
| Language (server + client) | TypeScript | Front və back-də bir dil, güclü tipizasiya |
| Framework | Next.js (React) | SSR + client React; yaxşı dəstəklənir |
| API | Node.js services | Eyni dil stack-i |
| Primary DB | PostgreSQL (minlərlə məntiqi shard-a bölünüb) | ACID, bloklar üçün JSONB, güclü ekosistem |
| Cache | Redis | Hot oxumalar |
| Real-time | WebSockets | Birgə redaktə |
| Search | Əvvəlcə Postgres; sonra öz Elasticsearch-əsaslı axtarış | Postgres full-text məhdud idi |
| Queue/messaging | SQS, Kafka | Async job-lar |
| Object storage | S3 | Fayl əlavələri |
| Infrastructure | AWS | Default cloud |
| Container orchestration | Kubernetes | Sənaye standartı |
| Monitoring | Datadog (tipik müasir stack) | Adi seçimlər |

## Dillər — Nə və niyə

### Hər yerdə TypeScript
- Notion TypeScript-ə ağırlıq verən shop-dur. React komponentindən Node API-yə qədər eyni dil.
- Əsaslandırma: kiçik komanda, az kontekst dəyişikliyi, client və server arasında paylaşılan tiplər.
- Client-də render / editor məntiqi üçün contenteditable + CRDT-lite pattern-ləri ətrafında çoxlu custom kod.

### Bəzi Go, Python
- Data pipeline-ları, ML, daxili tooling digər dilləri istifadə edir.
- Notion AI inference əlaqələri üçün Python.

### Rust (məhdud)
- Bəzi performansa kritik və ya sandboxing işi — dominant deyil.

## Framework seçimləri — Nə və niyə
- Web məhsulu üçün **Next.js** (SSR, routing, şəkil optimizasiyası).
- Tarixən state üçün **React + Redux**; daha müasir pattern-lərə keçdilər.
- Contenteditable üzərində qurulmuş custom editor (ProseMirror / TipTap deyil — özlərini yazdılar).
- Node.js servisləri adətən ağır TypeScript tipləşdirmə ilə Express/Koa-tipli pattern-lər istifadə edir.

## Verilənlər bazası seçimləri — Nə və niyə

### PostgreSQL — tək instansdan çox shard-a
Bu Notion-da əsas database hekayəsidir.

- İllərlə **tək Postgres database** üzərində. Uzun müddət vertikal miqyasladılar.
- Hər "block" sətirdir. Bir Notion səhifəsi minlərlə block ola bilər. Bu o deməkdir ki, kiçik bir komanda belə çoxlu sətir yarada bilər.
- Təxminən 2020-ci ildə **write-ahead log (WAL)** throughput bottleneck oldu. Yazılar yığılırdı; vakuum geri qalırdı; replikasiya gecikməsi artdı.
- Qərar: **iş sahəsinə görə Postgres-i shard-la**.

### Sharding layihəsi
- *"Herding elephants"* blogu (2021) kanonik təsvirdir.
- **Çoxlu kiçik məntiqi shard-ları** seçdilər (480-dən başladı, minlərlə-ə qədər böyüdü), fiziki host-larda qruplaşdırıldı ki, yenidən balanslaşdıra bilsinlər.
- Əsas prinsip: **təbii sərhəd — iş sahəsi üzərində shard-la**. Demək olar ki bütün sorğular iş sahəsi ilə məhdudlaşdırılıb.
- Keçid diqqətli double-write, shadow read və backfill ilə həyata keçirildi.

### Redis
- Hot block-lar, istifadəçi session-ları üçün cache.

### S3
- Fayl və şəkil əlavələri.

### Axtarış
- Postgres full-text axtarış kimi başladı — block sayına görə miqyaslanmadı.
- Xüsusi axtarış servisinə (Elasticsearch-əsaslı) keçdi.

## Proqram arxitekturası

Notion microservice buludu deyil, **dəstəkləyici servislərlə TypeScript monolit**dir.

```
   Browser / Desktop (Electron) / Mobile
          |
     [CDN + LB]
          |
     [Next.js + API (Node)]  <-- monolith
          |
     +----+----+-----+------+
     |         |     |      |
   Postgres  Redis  S3    Search service
   (sharded)               (Elasticsearch)
          |
     WebSocket servers (collab)
          |
     Block change stream
```

### Block data modeli
- Hər şey blokdur. Blokun: id, type (mətn, səhifə, database), parent id, content, properties, icazələr var.
- Səhifələr blok ağaclarıdır.
- Database-lər sətirlərini səhifə olan bloklardır.
- Bu vahid model funksiyaları birləşə bilən edir, amma yazıları granular edir (çoxlu kiçik sətir).

### Real-time birgə iş
- Mətn blokları üçün Operational-transform-tipli yanaşma.
- Tam sənaye CRDT sistemi deyil (Figma kimi). Sənəd-tipli redaktə üçün uyğun daha sadə yanaşma.

## İnfrastruktur və deploy
- AWS.
- Servislər üçün Kubernetes.
- Tipik blue/green / rolling deploy-lar.
- Datadog / observability stack.

## Arxitekturanın təkamülü

| İl | Dəyişiklik |
|------|--------|
| 2015 | Notion 1.0 — kiçik komanda, tək Postgres |
| 2018 | Notion 2.0 yenidən yazılması; yenə də tək Postgres |
| 2019–2020 | Artım; vertikal Postgres miqyaslanması |
| 2020 | Yazı bottleneck-i ortaya çıxdı; sharding layihəsi başladı |
| 2021 | Sharding tamamlandı; blog post dərc olundu |
| 2022 | Öz axtarış servisi |
| 2023 | Notion AI; AI infra əlavə edildi |
| 2024 | Daha çox shard; davamlı miqyaslanma |

## Əsas texniki qərarlar

1. **Hər şey block data modelidir.** Güclü və birləşə bilən. "Database" funksiyasını təbii etdi. Yazı həcmi qiymətini ödədi.
2. **Mümkün qədər uzun tək Postgres-də qal.** Praktik — lazım olmayana qədər shard-lama.
3. **İş sahəsinə görə shard.** Əksər sorğuların artıq yaşadığı təbii sərhəd.
4. **Postgres full-text-i əlavə etmək əvəzinə öz axtarış qur.** FTS çatlamağa başlayanda düzgün qərar idi.
5. **Full-stack TypeScript.** Vahid tip sistemi; kiçik komanda sürətlə hərəkət edə bilər.

## Müsahibədə necə istinad etmək

1. **Tək relational DB insanların düşündüyündən daha güclüdür.** Əksər Laravel tətbiqlərinin heç vaxt sharding-ə ehtiyacı olmayacaq. Vertikal miqyaslayın, read replika əlavə edin, aqressiv cache edin, SONRA shard-ı düşünün.
2. **Təbii shard açarları ən yaxşısıdır.** Notion iş sahəsinə görə shard edir, bu giriş ilə uyğundur. Laravel multi-tenant SaaS-də tenant ID açıq shard açarıdır — hətta hələ shard-lamasanız da, sonra edə biləcəyiniz şəkildə dizayn edin.
3. **Granular yazıların artan qiyməti var.** Dizaynınız istifadəçi əməli başına 1000 sətir yazırsa, WAL/IOPS limitlərinə düşündüyünüzdən daha tez çatacaqsınız. Mümkün olan yerdə batch edin.
4. **Data modelinin çevikliyinin qiyməti var.** "Hər şey blokdur" çevikdir, amma bahalıdır. Laravel-də EAV pattern-ləri (type + JSON column ilə bir cədvəl) oxşar kompromislərə malikdir.
5. **Öz axtarışınızı qurmaqdan qorxmayın.** Scout + Meilisearch və ya Elasticsearch əksər Laravel tətbiqləri üçün yaxşıdır. Amma ciddi axtarış üçün Postgres/MySQL full-text-in hədlərini bilin.
6. **Köçürmə pattern-i: double-write, shadow-read, cut-over.** Hər DB köçürməsi, hər framework üçün eyni pattern. Öyrənin — lazım olacaq.

## Əlavə oxu üçün
- Notion Blog: *Herding elephants: Lessons learned from sharding Postgres at Notion*
- Notion Blog: *The data model behind Notion's flexibility*
- Notion Blog: *Scaling Notion's data lake*
- Notion Blog: *How we built an AI editor in Notion*
- Talks: Müxtəlif Postgres və TypeScript konfranslarında Notion-un engineering çıxışları
- Book: Martin Kleppmann-ın *Designing Data-Intensive Applications* — konsepsiyalarla uyğun gəlir
- Citus-un blogu: *How to shard Postgres* — ümumi fon
