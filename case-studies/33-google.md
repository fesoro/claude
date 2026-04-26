# Google (Architect)

## Ümumi baxış
- **Nə edir:** Axtarış (Search), reklam (Ads), YouTube, Gmail, Maps, Drive, Android, Cloud (GCP), AI (Gemini). Dünyanın ən böyük məlumat işləyən şirkətlərindən biri.
- **Yaradılıb:** 1998-ci ildə Larry Page və Sergey Brin tərəfindən Stanford-da.
- **Miqyas (açıq məlumat):**
  - Gündə milyardlarla axtarış sorğusu.
  - YouTube: hər dəqiqə 500+ saat video yüklənir.
  - Gmail: 1.8B+ istifadəçi.
  - Infrastructure: milyonlarla server, onlarla dünya üzrə data mərkəzi.
- **Əsas tarixi anlar:**
  - 2003: GFS (Google File System) paper.
  - 2004: MapReduce paper — distributed computing sahəsinə təsir.
  - 2006: BigTable paper.
  - 2010: Percolator (incremental indexing), Dremel (BigQuery-nin əcdadı).
  - 2012: Spanner paper — TrueTime və qlobal consistency.
  - 2014: Kubernetes açıq mənbəli oldu (Borg-dan doğuldu).
  - 2015: gRPC açıq mənbəli oldu (Stubby daxili sistemindən).
  - 2020+: Gemini (əvvəl Bard) AI platforması.

Google **distributed systems-in əksər akademik literaturasını** yaratdı — MapReduce, GFS, BigTable, Spanner, Borg, Dapper. Müasir cloud-native dünyası əsasən Google-un daxili tooling-ini kopyalayır.

## Texnologiya yığını

| Layer | Technology | Why |
|-------|-----------|-----|
| Performance-critical | **C++** | Search indexing, Chrome, video emalı — max performance |
| Services | **Java** | Gmail, Ads, enterprise backends — yetkin ekosistem |
| Infrastructure / new services | **Go** | Google tərəfindən ixtira olundu; Kubernetes, Docker daxili alternativi |
| ML, data science, scripting | **Python** | TensorFlow, data pipeline-ları, ML araşdırması |
| Storage — transactional global | **Spanner** | SQL + qlobal consistency (TrueTime) |
| Storage — wide-column | **Bigtable** | Web index, analytics |
| Storage — file system | **Colossus** (GFS-in varisi) | Exabyte-miqyasında saxlama |
| Analytics | **BigQuery** (Dremel-dən) | Petabyte ölçüsündə interactive SQL |
| Messaging | **Pub/Sub** | Global event bus |
| RPC | **gRPC** (daxili Stubby) | Google-un RPC standartı |
| Data format | **Protobuf** | Effektiv binar serialization |
| Orchestration | **Borg** (daxili), Kubernetes (OSS) | Container orchestration doğum yeri |
| Coordination | **Chubby** | Distributed locking, leader election |
| Tracing | **Dapper** (daxili) | Distributed tracing-in pioneri |

## Dillər — Nə və niyə

### C++ — performance-critical core
- Google Search indexing, ranking, serving — hamısı C++.
- Chrome, V8 JavaScript engine — C++.
- YouTube video emalı, codec işi — C++.
- **Niyə:** maksimum performance, nəzarət edilə bilən memory, SIMD optimizasiyaları.
- Google "Modern C++" pattern-ləri yaratdı (Abseil kitabxanası açıq mənbədir).

### Java — çox enterprise servislər
- Gmail, Google Ads, Google Docs backend-lərinin böyük hissəsi.
- **Niyə:** güclü type system, yetkin GC, ekosistem, JVM observability.
- Guice dependency injection Google-dan gəldi.

### Go — Google-un öz dilidir
- 2007-də Rob Pike, Ken Thompson, Robert Griesemer tərəfindən Google-da yaradıldı.
- Problem: C++ build vaxtları dözülməz idi, Python çox yavaş idi, Java çox ağır idi.
- Həll: sadə, statically typed, sürətli compile olan, goroutines ilə concurrency olan dil.
- Google daxilində: Kubernetes, bir çox SRE aləti, network proxies.

### Python
- ML (TensorFlow, JAX), data pipeline-ları, daxili tooling.
- Google 20-dən çox il ərzində CPython-a böyük töhfələr verdi.

### Dart + Flutter
- Google-dan gələn müştəri tərəfli dil/framework.

## Framework seçimləri — Nə və niyə
- **Bazel** — Google-un daxili `blaze` build sistemi, hermetic, reproducible build-lər üçün açıq mənbəli edildi.
- **Protobuf + gRPC** — servislər arasında universal communication.
- **Abseil** — Google-un C++ kitabxanaları.
- **Guice** — Java üçün dependency injection.
- **Angular** — web üçün Google-un frontend framework-u.
- **TensorFlow / JAX** — ML üçün.

## Verilənlər bazası seçimləri — Nə və niyə

### Spanner — qlobal tranzaksional DB
- Google-un ən bahalı və güclü daxili DB-si.
- **SQL + strong consistency + qlobal paylanma** — uzun illər "imkansız" hesab edilirdi.
- **TrueTime API:** atom saatlar + GPS istifadə edərək məhdud qeyri-dəqiqlik ilə qlobal vaxt verir. Spanner bu məhdudiyyəti linearizable tranzaksiyalar üçün istifadə edir.
- Ads sistemi, finans workload-ları burada yaşayır.
- Cloud-da açıqdır: Cloud Spanner.

### Bigtable
- Web search indexinin saxlandığı yer (tarixən).
- Petabyte-miqyasında wide-column store.
- HBase, Cassandra, DynamoDB bütün bu ideyaları qismən Bigtable paper-indən götürdü.

### Colossus
- GFS-in varisi, Google-un daxili distributed file system-i.
- Bigtable, Spanner, analytics storage altında yaşayır.
- Exabyte-miqyasında aktiv data saxlayır.

### BigQuery (Dremel)
- Petabyte ölçüsündə interactive analytical SQL.
- Columnar storage + distributed tree execution.
- Cloud-da açıqdır; Snowflake, Redshift ilə rəqabət edir.

### Digər
- **Megastore** (Spanner-dən əvvəl) — highly available sync replication.
- **Firestore / Firebase** — mobile app üçün real-time DB.
- **Memcached + öz cache layer-ləri** — hot data üçün.

## Proqram arxitekturası

Google **daxili monorepo** + **minlərlə microservice** modelidir. Hər şey Piper (daxili), indi Copybara vasitəsilə open-source-a köçürülür, bir monorepo-da (milyardlarla sətir kod) yaşayır.

```
   Internet
        |
   [Maglev (L4 LB)]
        |
   [GFE (Google Front End, HTTPS termination)]
        |
   [Service Mesh over Stubby/gRPC]
        |
   [Services (C++, Java, Go, Python)]
        |
   [Spanner / Bigtable / Colossus / Pub/Sub]
        |
   [Borg / Kubernetes cluster scheduling]
```

### Borg → Kubernetes
- **Borg** Google-un 2003-dən bəri olan daxili cluster manager-idir.
- Onunla milyonlarla container-i idarə edirlər.
- 2014-də Borg-dan dərs aldıqları şeyləri götürüb **Kubernetes**-i açıq mənbəli etdilər. Kubernetes Borg-un təkrar cəhdi deyil; onun varisidir (Omega-dan sonra).

### Chubby — distributed lock service
- Bütün Google daxili sistemləri Chubby istifadə edir — Paxos-əsaslı, cari dövrdə Spanner üçün locks, leader election, config, name resolution.
- ZooKeeper əsasən Chubby paper-indən götürüldü.

### Axtarış sistemi
- **Index:** Colossus üzərində saxlanılır, Bigtable-də metadata.
- **Crawler (Googlebot):** paylanmış, əxlaqlı (robots.txt).
- **Indexing:** incremental, Percolator-dan gəlir (2010).
- **Serving:** shard-lərə bölünmüş C++ servisləri; sorğu fan-out yüzlərlə shard-a.
- **Ranking:** hundreds of signals, RankBrain (ML), BERT (NLP), indi LLM-əsaslı.

### Ads sistemi
- Real-time auction, millisaniyələr içində aparılır.
- Budget, keyword targeting, click tracking.
- Spanner tranzaksional, BigQuery analitika.

## İnfrastruktur və deploy
- Öz fiber network-u (qurdular), öz submarine cable-ləri.
- Öz custom TPU və Video Coding Unit (VCU) chip-ləri.
- Öz switch-ləri (Jupiter fabric).
- Borg vasitəsilə deploy; canary → regional rollout.
- SRE mədəniyyəti — Google "Site Reliability Engineering" kitabını yazdı.

## Arxitekturanın təkamülü

| Year | Change |
|------|--------|
| 1998 | Stanford-da başlanğıc; PageRank alqoritmi |
| 2003 | GFS paper |
| 2004 | MapReduce paper |
| 2006 | Bigtable paper |
| 2007 | Go dili daxili olaraq başlandı |
| 2010 | Percolator, Dremel, Megastore |
| 2012 | Spanner paper — TrueTime |
| 2014 | Kubernetes açıq mənbə, Borg-dan doğulur |
| 2015 | gRPC açıq mənbə |
| 2020+ | TPU-lar, ML/AI infrastrukturu, Gemini |

## 3-5 Əsas texniki qərarlar

1. **Monorepo + Blaze/Bazel.** Hər şey bir repo-da, hermetic build. Hiring və cross-team refactoring üçün üstünlük.
2. **Standard RPC + Standard serialization.** Hamı Stubby/gRPC + Protobuf istifadə edir. Bu heç bir engineer-in formatı təkrar ixtira etməməsi deməkdir.
3. **Öz hardware.** Əksər şirkətlər üçün həll yox, lakin Google miqyasında əsaslandırılır.
4. **Spanner ilə TrueTime.** Finans workload-ları üçün qlobal strong consistency — sənaye paradiqmasını dəyişdi.
5. **Borg → Kubernetes.** Onilliklərlə öyrənilmiş cluster management pattern-lərini açıq mənbəyə buraxdılar.

## Müsahibədə necə istinad etmək

1. **Distributed consensus sualı:** "Paxos və ya Raft? Google Chubby Paxos istifadə edir, Spanner də Paxos-əsaslıdır. ZooKeeper Chubby-dən ilhamlandı. Praktiki cəhətdən bu gün çoxu Raft seçir çünki daha anlaşıqlıdır."
2. **Global consistency sualı:** "Spanner TrueTime və atom saatlar vasitəsilə bunu həll etdi. Əgər belə hardware-iniz yoxdursa, məntiqi saatlar (Lamport, vector clocks) və ya CRDT yeganə seçimdir."
3. **Container orchestration:** "Kubernetes Google-un 10+ illik Borg təcrübəsindən gəldi. Kubernetes bir xüsusiyyət toplusu deyil — onu əldə etmək üçün ödədiyimiz vergidir."
4. **Analytics sualları:** "BigQuery Dremel paper-indən gəlir — columnar, distributed tree execution. Snowflake eyni konsepsiyanın açıq versiyasıdır."
5. **Search index sualı:** "Bigtable + Colossus. Crawler → parser → indexer → serving. Incremental updates üçün Percolator."

## Əlavə oxu üçün
- Paper: *The Google File System* (2003)
- Paper: *MapReduce: Simplified Data Processing on Large Clusters* (2004)
- Paper: *Bigtable: A Distributed Storage System for Structured Data* (2006)
- Paper: *Spanner: Google's Globally-Distributed Database* (2012)
- Paper: *Large-scale cluster management at Google with Borg* (2015)
- Paper: *Dapper, a Large-Scale Distributed Systems Tracing Infrastructure*
- Book: *Site Reliability Engineering* (O'Reilly)
- Book: *Software Engineering at Google* (O'Reilly)
