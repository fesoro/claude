# Twitch (Architect)

## Ümumi baxış
- **Nə edir:** Canlı video yayımlama (live streaming), əsasən gaming, lakin IRL, musiqi, yaradıcı content də. Chat, subscriptions, bits, clips.
- **Yaradılıb:** 2011-də Justin.tv-dən ayrıldı (2007-də Justin Kan, Emmett Shear və b. tərəfindən). 2014-də Amazon tərəfindən $970M-a alındı.
- **Miqyas (açıq məlumat):**
  - 30M+ günlük aktiv istifadəçi (2023).
  - Pik-də milyonlarla eyni anda izləyən.
  - Minlərlə eyni anda yayımlayan.
  - Her ay milyarlarla saat izlənir.
- **Əsas tarixi anlar:**
  - 2007: Justin.tv launch (Justin Kan 24/7 yayımladı).
  - 2011: Twitch.tv gaming üçün ayrıldı.
  - 2014: Amazon-un $970M alışı.
  - 2015+: Məşhur *"Scaling Chat to Millions"* blog post-ları.
  - 2016: Rust-in Twitch Chat-də böyük istifadəsi.
  - 2020: pandemiya zamanı partlayış.
  - 2023: Amazon-da cost-cutting dövrü, layihə layoffs.

Twitch **real-time chat at scale** probleminin ən məşhur nümunələrindən biridir — Erlang/Elixir dünyasının böyük case-i. IRC əsaslı chat-dən başladı, sonra Go/Rust-a köçdü.

## Texnologiya yığını

| Layer | Technology | Why |
|-------|-----------|-----|
| Video ingest | Custom ingest (Go + C++) | RTMP/HLS handling, transcoding |
| Transcoding | FFmpeg + custom GPU encoding | Canlı video real-time encoding |
| Chat (əsas) | **Go** (müasir), **Rust** (performance), **Erlang** (tarixən) | Massive concurrent connections |
| Chat protocol | IRC-modified (TMI) | Sadə, tested |
| Backend services | **Go**, **Ruby** (legacy), **Python** | Polyglot, Amazon-a köçdükdən sonra əsasən Go |
| Primary DB | **PostgreSQL** + **DynamoDB** | RDBMS core, KV scale |
| Cache | **Redis**, **Memcached** | Hot data, rate limit |
| Event bus | **Kafka**, **Kinesis** (AWS) | Event streaming |
| Video storage | **S3** | Amazon-a aid |
| CDN | Amazon CloudFront + öz layer-lər | Global video delivery |
| Search | **Elasticsearch** | Stream axtarışı |
| Infrastructure | **AWS** (Amazon-a aid) | Amazon integration |

## Dillər — Nə və niyə

### Go — müasir dil
- Amazon alışından sonra stack Go-ya konverge etdi.
- Chat backend-i (müasir), API servisləri, video pipeline-ları — əsasən Go.
- **Niyə Go:** AWS ekosistemi, concurrency, yaxşı operator tooling, hiring asanlığı.

### Rust — hot-path komponentləri
- Chat-in bəzi hissələri Rust-a köçürüldü — ultra-low latency və GC-siz.
- Bəzi video pipeline hissələri.
- Amazon Rust-a ağır sərmayə qoyub (Firecracker, Rust-based AWS services).

### Erlang — tarixi chat
- Justin.tv dövründə chat Erlang idi, Ejabberd əsasında.
- Sonra daxili IRC-modified protocol üzərində qurulmuş Erlang server-lərə köçdülər.
- **Niyə Erlang:**
  - Milyonlarla eyni anda bağlantı — BEAM VM bunu pulsuz edir.
  - Soft real-time, aşağı tail latency.
  - Actor model chat room-larına doğal uyğundur.
- 2016+ Twitch Go + Rust-a tədricən keçdi — hiring easier, Amazon stack uyum.

### Ruby — legacy
- Early Twitch website Ruby/Rails idi.
- Miqrasiya edildi, amma bəzi servislər hələ də qalır.

### Python
- ML, recommendation, internal tools.

## Framework seçimləri — Nə və niyə
- **Go stdlib + gRPC + custom internals** — əksər backend.
- **Tokio (Rust)** — hot path async.
- **Ejabberd (Erlang, chat tarixən)** — artıq deyil, custom-a köçdü.
- **Rails (legacy)** — web site.
- **React** — frontend.

## Verilənlər bazası seçimləri — Nə və niyə

### PostgreSQL — core data
- İstifadəçilər, channel-lər, subscriptions, VOD metadata.
- Amazon RDS/Aurora üzərində.

### DynamoDB — scale-critical data
- Chat history (son mesajlar), view counts, streamer statistics.
- **Niyə DynamoDB:** Amazon ekosistemi, predictable latency, massive scale.

### Redis / Memcached
- Rate limiting, session state, hot cache.
- Chat user presence cache.

### Kafka / Kinesis
- Event bus — chat messages, viewer events, subscription events.

### S3
- VOD video saxlanması, clip-lər.

### Elasticsearch
- Stream axtarışı, user search.

## Proqram arxitekturası

Twitch **"Clusters"** arxitekturasını istifadə edir — cell-based, regional cluster-lər.

```
      Streamer app (OBS, etc.)
            |
        [RTMP ingest servers]
            |
        [Transcoding fleet (GPU)]
            |
        [Origin servers] → [CDN edge]
                              |
                        Viewer (web/mobile/console)
                              |
                        [Chat connection (Go/Rust)]
                              |
                         [Chat cluster]
                              |
                     [Kafka → stats/ML/storage]
                              |
                       [Postgres + DynamoDB]
```

### Chat sistemi
- Hər channel bir "room" kimi qəbul edilir.
- Chat server-ləri müəyyən channel-lara bağlıdır (sticky routing).
- **Chat Clusters:** chat room-ları bir-birindən izolə olunmuş cluster-lərə bölünür — blast radius-u məhdudlaşdırır (bir cluster crash olsa, başqaları işləyir).
- Tarixən Erlang:
  - Hər channel ayrı Erlang process.
  - Supervision tree bütün channel tree-sini restart edir.
- Müasir Go/Rust:
  - Connection multiplexing tək server-də.
  - Goroutine/task per channel.
- **TMI protocol:** IRC-modified. Twitch öz extension-larını (bits, emote, mod actions) əlavə edib.

### Video ingest və yayımlama
- **RTMP ingest:** streamer OBS/X-Split ilə RTMP yayımlayır.
- **Transcoding:** multiple bitrates və qualities (source, 1080p, 720p, 480p) üçün ABR (Adaptive Bitrate Streaming).
- **HLS çıxışı:** browser/mobile/console HLS segmenti qəbul edir.
- **Low-latency HLS:** sub-3 saniyəli latency elde etmək üçün chunked transfer istifadə olunur.
- GPU encoding (Amazon gPu instance-ları ilə).

### Clusters arxitekturası
- Twitch backend "cluster" adlanan izolə edilmiş dilim-lər halında paylanır.
- Hər cluster tam stack: chat server, API server, cache.
- Bu cell-based deployment-dir — bir cluster çökəndə, digərləri işləyir.
- Netflix, Slack də bu pattern-i istifadə edir.

### Chat messages storage
- DynamoDB-də partition key olaraq `channel_id`, sort key olaraq `timestamp`.
- Read pattern: son N mesaj, scroll back.
- Message TTL var — hamısı forever saxlanılmır.

## İnfrastruktur və deploy
- AWS əsaslı (Amazon-a aid).
- Minlərlə EC2 instance, bir çox regionda.
- Kubernetes + öz tooling.
- CI/CD pipeline Amazon-un daxili tooling-i ilə.
- Ağır monitoring, autoscaling.

## Arxitekturanın təkamülü

| Year | Change |
|------|--------|
| 2007 | Justin.tv launch; Rails + Erlang chat (Ejabberd) |
| 2011 | Twitch.tv ayrılma |
| 2014 | Amazon alışı |
| 2015 | Blog posts: *Scaling Chat*, Go chat serverlərinin əsaslandırılması |
| 2016 | Rust Chat hot-path-larında başladı |
| 2018+ | Low-latency HLS (sub-3s) yaradıldı |
| 2020 | Pandemiya partlayışı — capacity ağır stress |
| 2023 | Amazon cost-cutting, layoffs; optimization focus |

## 3-5 Əsas texniki qərarlar

1. **Chat Erlang-dan Go-ya köçürmə.** Erlang texniki olaraq problemi həll etmişdi, amma hiring və Amazon ekosistemi inteqrasiyası Go-ya keçidi əsaslandırdı. Bu "good enough" dəyişikliyidir — həmişə texniki optimum deyil.
2. **Cell-based Clusters.** Monolit və ya hamı-bir-cluster əvəzinə, kiçik izolə olunmuş cluster-lər. Blast radius azalır.
3. **PostgreSQL + DynamoDB polyglot DB.** Core data PG, scale-heavy read DynamoDB. Bir DB-də iki pattern-i zorlamaq yerinə.
4. **Video pipeline üçün GPU transcoding.** CPU transcoding scale edə bilməzdi. AWS g-instance-ları üzərində GPU encoding əsaslandırıldı.
5. **Low-latency HLS.** Chunked HLS + tuning ilə sub-3s latency. WebRTC-dən seçmək əvəzinə — WebRTC-nin one-to-millions model-i yoxdur; HLS CDN-dostu.

## Müsahibədə necə istinad etmək

1. **Chat scale sualı:** "Twitch istifadə etdi Erlang (BEAM-da millions of connection), sonra Go (team hiring və simpler ops) + Rust (GC-sensitive hot paths). Əsas nümunə: hər channel → actor/goroutine, supervision/panic isolation, sticky routing."
2. **Live streaming arxitektura:** "RTMP ingest → transcode (GPU-ABR) → HLS segmenti → CDN edge. WebRTC deyil çünki WebRTC 1-to-N model-də scale çətindir."
3. **Cell-based deployment:** "Twitch Clusters pattern. Netflix də istifadə edir. Blast radius-u məhdudlaşdırmaq və regional failure-ları izolə etmək."
4. **Polyglot DB selection:** "Core user data PG, chat history DynamoDB. Write-heavy və predictable-read workload-ları PG-də lazım deyil."
5. **Video storage cost optimization:** "VOD S3-də Standard-IA tier. Popular clips active tier-də, arxiv tier-ə köçürmək lifecycle policy-lərlə."

## Əlavə oxu üçün
- Twitch Engineering Blog: *Scaling Twitch Chat*
- Twitch Engineering Blog: *How Twitch uses PostgreSQL at Scale*
- Twitch Engineering Blog: *Low Latency Video Streaming at Twitch*
- Talks: *QCon: Scaling Twitch Chat*
- Talks: Twitch engineering at Erlang Factory (tarixi chat)
- Blog: *Migrating from Ruby on Rails to Go at Twitch*
- Amazon/Twitch HLS-based low-latency streaming posts
