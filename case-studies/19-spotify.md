# Spotify (Lead)

## Ümumi baxış
- **Nə edir:** Audio streaming xidməti: musiqi, podcast-lar, audiokitablar. Fərdi tövsiyələr (Discover Weekly, Daily Mixes), sosial funksiyalar, redaktor kurasiyası.
- **Yaradılıb:** 2006-cı ildə İsveçin Stokholm şəhərində Daniel Ek və Martin Lorentzon tərəfindən.
- **İşə salınma:** 2008-ci ildə Avropada; ABŞ-da 2011.
- **Miqyas (2024):**
  - ~600M aylıq aktiv istifadəçi.
  - ~240M premium abunəçi.
  - 100M+ trek.
  - 5M+ podcast.
- **Əsas tarixi anlar:**
  - 2008: Avropada peer-to-peer streaming arxitekturası ilə işə salındı.
  - 2011: ABŞ-da işə salınma.
  - 2012–2014: komanda təşkilatlanmasının "Spotify modeli" (squad, tribe, chapter, guild) haqqında yazıldı, geniş yayıldı (və sonra tənqid edildi).
  - 2016–2018: öz data mərkəzlərindən Google Cloud-a köçdülər.
  - 2018: IPO.
  - 2019+: podcast-lara böyük mərc; Gimlet, Anchor, The Ringer-in alınması.
  - 2020: Backstage open-source edildi (developer portal).

Spotify Avropanın ən böyük tech scale-up-ıdır. Engineering blogu həm uğurlar, həm də səhvlər haqqında şəffaflıqla məşhurdur.

## Texnologiya yığını

| Layer | Technology | Why |
|-------|-----------|-----|
| Backend services | Java (çox), Python, bəzi Scala | Komanda muxtariyyəti dövrü polyglot-a gətirdi |
| Data pipelines | Scala (tarixən), Python | Spark/Scio ekosistemi |
| Primary DB | Böyük iş yükləri üçün Cassandra; PostgreSQL; analytics üçün BigQuery | Hər giriş pattern üçün düzgün alət |
| Streaming infra | Custom CDN edge | Aşağı latency audio çatdırılması |
| Data ETL | Luigi (Spotify OSS), indi çox vaxt Airflow-tipli | Workflow orchestration |
| Messaging | Kafka (ağır) | Event əsası |
| Cache | Memcached, Redis | Fərdiləşdirmə və metadata |
| Search | Elasticsearch | Treklər, ifaçılar, podcast-lar |
| Client (desktop) | Electron / C++ (köhnə Win32/Cocoa) | Native-kimi hiss, amma cross-platform |
| Client (web) | React | Standart müasir web |
| Infrastructure | Google Cloud Platform (köçürmədən sonra) | İdarə olunan servislər, BigQuery |
| Developer portal | Backstage (öz OSS, indi CNCF) | Servis discovery, sənədlər |
| Orchestration | Helios (öz OSS) → Kubernetes | Sənaye standartı, daha az saxlanma işi |

## Dillər — Nə və niyə

### Java
- Əksər servislər üçün əsas backend dili.
- JVM tooling, observability, kitabxanalar yetkindir.
- Əksər servislər Java-əsaslı microservice-lərdir.

### Python
- Data pipeline-ları, scripting, ML təlimi, daxili alətlər.
- Luigi (orchestration), Scio (Apache Beam-ın Scala/Python wrapper-i).

### Scala
- Spark və Scio ilə data emalı.
- "Hər komanda öz stack-ini seçir" dövründə bəzi backend servisləri.

### Digər
- Aşağı səviyyəli client hissələri və codec-lər üçün C++.
- Web üçün TypeScript.
- Mobile üçün Swift / Kotlin.

## Framework seçimləri — Nə və niyə
- **Apollo / Hermes** — Spotify-ın daxili Java microservice framework-ləri (müxtəlif nəsillər).
- **Backstage** — onların developer portalı, indi CNCF layihəsidir. "Hansı komanda hansı servisə sahibdir" problemini həll edir.
- **Luigi** — Spotify tərəfindən qurulmuş workflow engine. Airflow-a güclü təsir etdi.
- **Scio** — Apache Beam/Dataflow üzərində Scala wrapper.
- Tarixən ağır "hər şeyi idarə edən bir framework"-dən qaçdılar — hər squad-ın muxtariyyəti var idi.

## Verilənlər bazası seçimləri — Nə və niyə

### Cassandra
- Wide-column modelin uyğun gəldiyi böyük iş yükləri: playlist-lər, istifadəçi dinləmə event-ləri, bəzi time-series.
- Yazı ağır, çoxregionlu replikasiya.

### PostgreSQL
- Güclü relational modelin qazandığı yerlərdə: billing, istifadəçi hesabları, bəzi metadata.

### BigQuery (GCP)
- Analytics və hesabat. GCP-yə köçürmədən sonra data platformasının özəyi.

### Memcached / Redis
- Sürətli metadata oxumaları üçün cache layer-ləri.

### Elasticsearch
- Treklər, ifaçılar, podcast-lar üçün axtarış.

### Object storage
- Audio faylları object storage-də saxlanılır, CDN vasitəsilə stream edilir.

## Proqram arxitekturası

```
    Clients (iOS, Android, Desktop, Web, Consoles)
            |
     [Edge CDN + API Gateway]
            |
     +---------------------------------------+
     |   ~1000 Java/Scala/Python Microservices  |
     +---------------------------------------+
       /     |      |        |          \
  Users   Playback  Search  Playlists    Ads
   |       |         |        |           |
  Postgres Cassandra ES       Cassandra   Custom
                 \    |     /
                  \   |    /
                 Kafka (event bus)
                     |
                 Data platform (BigQuery, Scio jobs)
                     |
                 ML (recommendations, Discover Weekly)
```

### Streaming arxitekturası
- Əvvəlcə (2008–2014) bandwidth xərcini azaltmaq üçün **peer-to-peer** istifadə edildi — istifadəçilər bir-biri üçün qismən mənbə rolunu oynayırdı.
- CDN qiymətləri düşdükcə və P2P mürəkkəblik əlavə etdikcə, Spotify təmiz CDN-ə keçdi.
- Öz edge layer-i artı kommersiya CDN-lər.

### Tövsiyələr
- Ağır data pipeline Kafka-dan dinləmə event-lərini qəbul edir.
- Offline batch job-lar istifadəçi embedding-lərini, playlist tövsiyələrini hesablayır.
- Nəticələr playback zamanı aşağı-latency key-value store vasitəsilə göstərilir.
- Discover Weekly həftəlik olaraq ML modelləri ilə yaradılır.

## İnfrastruktur və deploy
- **Öz data mərkəzlərindən Google Cloud-a böyük köçürmə (2016–2018)** yayımlanan ən böyük cloud köçürmələrindən biridir.
- Səbəblər: BigQuery (data platforması), idarə olunan servislər, innovasiya sürəti.
- Orchestration: öz Helios-larından Kubernetes-ə keçdilər.
- Blog: *"Why We Switched from Helios to Kubernetes"*.
- Servislər kataloqu olaraq Backstage.

## Arxitekturanın təkamülü

| İl | Dəyişiklik |
|------|--------|
| 2006–2008 | Erkən Java + Python, P2P streaming |
| 2010–2013 | Microservice-lər artır; Cassandra istifadədə |
| 2013–2015 | Komandaların "Spotify modeli"; ağır polyglot dövrü |
| 2016 | GCP köçürməsi başlayır; Kubernetes qəbul |
| 2018 | Data platforması BigQuery-də; Backstage daxili |
| 2020 | Backstage open-source |
| 2022+ | "Golden path" stack-lərin standartlaşdırılması; daha az dil təşviq edilir |

## Əsas texniki qərarlar

1. **Squad / Tribe / Chapter / Guild təşkilat modeli (Spotify modeli).** Komandalara muxtariyyət verdi. Məşhurlaşdı, amma Spotify özü sonra bunun fraqmentasiyaya səbəb olduğunu etiraf etdi və ötdü.
2. **Öz data mərkəzlərindən GCP-yə köçürmə.** Böyük mərc. Data platforması (BigQuery, Dataflow) böyük sürücü idi.
3. **Luigi (sonra Airflow-uyğun alətlər).** Data orchestration-da sənayeyə liderlik etdi.
4. **Daxili portal olaraq Backstage, sonra open-source.** OSS-ə geri verib sənaye alətlərinə təsir etmək modeli.
5. **P2P streaming-dən imtina.** CDN iqtisadiyyatı yaxşılaşdıqca sadəlik qalib gəldi.

## Müsahibədə necə istinad etmək

1. **Komanda muxtariyyətinin həddləri var.** Hər komanda fərqli stack seçirsə, hiring, ops və paylaşılan tooling ilə bunu ödəyirsən. Laravel shop-larında "golden path"-i ardıcıl saxla — eyni framework versiyası, eyni queue driver, eyni test pattern-ləri — və yalnız əsaslandırılmış istisnalara icazə ver.
2. **Cloud-a nə vaxt köçürülməli olduğunu bil.** Spotify-ın GCP-yə köçməsi "cloud coolldur" deyil, BigQuery və idarə olunan servislər səbəbindən oldu. AWS/GCP-yə köçən PHP shop-larının da oxşar spesifik səbəbi olmalıdır (RDS HA, idarə olunan Redis, serverless cron).
3. **Developer portal-a sərmayə qoyun.** Hər servisi və sahibini sadalayan sadə bir wiki və ya Notion səhifəsi sürtünməni azaldır. Kiçik Laravel shop-ları üçün hər layihənin README-si plus yuxarı səviyyəli kataloq kifayətdir.
4. **Data platforması tətbiq DB-sindən ayrıdır.** Kafka-ya (və ya RabbitMQ / Redis Streams) axan və warehouse-a (BigQuery, Snowflake, ClickHouse) düşən event-lər istehsal DB-nizə zərər vermədən analytics suallarına cavab vermək üsuludur. Laravel shop-ları eyni pattern-i qura bilər.
5. **ML sorğu zamanı təlim edilmir, xidmət edilir.** Training pipeline yavaş və ayrıdır. Serving pipeline sürətli və kiçikdir. ML öyrənən bir çox PHP developer ikisini qarışdırır.
6. **Öz stack-ini öldürmək cəsarəti.** Spotify Kubernetes üçün Helios-u öldürdü. OSS daxili alətləri aşanda onları təqaüdə göndərməkdən qorxmayın.

## Əlavə oxu üçün
- Spotify Engineering: *Scaling Spotify's Infrastructure*
- Spotify Engineering: *Why We Switched From Helios to Kubernetes*
- Spotify Engineering: *Backstage: an open platform for building developer portals*
- Spotify Engineering: *Event Delivery at Spotify*
- Spotify R&D: Discover Weekly haqqında müxtəlif postlar
- Henrik Kniberg məqaləsi: *Scaling Agile @ Spotify* ("Spotify modeli" məqaləsi)
- Talks: *Luigi: Building Complex Pipelines* (PyData)
- Talk: *Spotify's Journey to GCP* (Google Cloud Next)
