# Zalando (Lead)

## Ümumi baxış
- **Nə edir:** Avropanın ən böyük online moda pərakəndə satıcısı. Paltar, ayaqqabı, gözəllik, idman. Marketplace + öz inventar modeli. ~25 Avropa bazarında fəaliyyət göstərir.
- **Yaradılıb:** 2008-ci ildə Berlində Robert Gentz, David Schneider və Rocket Internet tərəfindən.
- **IPO:** 2014-də Frankfurt Stock Exchange-də.
- **Miqyas:**
  - ~50M aktiv müştəri.
  - İllik milyardlarla avro GMV.
  - Avropaya fokus (ABŞ deyil).
- **Əsas tarixi anlar:**
  - 2008: ABŞ şirkəti Zappos-un Rocket Internet klonu kimi işə salındı.
  - 2011–2014: Avropada sürətli genişlənmə.
  - ~2015: Magento-tipli monolitdən microservice-lərə keçid.
  - ~2015: "**Radical Agility**" manifesto — proses üzərində komanda muxtariyyəti.
  - 2016+: əsas Postgres HA tooling: **Patroni, Spilo** — open-source edildi.
  - 2019+: həddindən artıq muxtariyyətdən qismən geri çəkilmə; daha çox guardrail.

Zalando **microservice-lər + komanda muxtariyyətinin, həm yüksəlişləri həm düşüşləri** və həmçinin **Postgres ekosistemi üçün əla töhfəçi** olmaq baxımından Avropa nümunəsidir.

## Texnologiya yığını

| Layer | Technology | Why |
|-------|-----------|-----|
| Primary languages | Scala, Java, Python | Komanda muxtariyyəti + JVM yetkinliyi |
| Frontend | React / TypeScript | Müasir standart |
| Primary DB | PostgreSQL | Zəngin SQL, JSONB, güclü əməliyyatlar |
| HA Postgres | Patroni (öz OSS), Spilo (Patroni ilə Postgres container-i) | Miqyasda HA-ya ehtiyac var idi, qurdular |
| Cache | Redis, Memcached | Standart |
| Messaging | Apache Kafka (ağır) | Event-əsaslı arxitektura |
| Search | Elasticsearch, Solr | Məhsul kataloqu axtarışı |
| Infrastructure | AWS (STUPS platforması, sonra Kubernetes vasitəsilə) | Cloud-native |
| Observability | Prometheus, Grafana, ELK | Müasir stack |
| Feature flagging | Custom + sənaye standartı | Təhlükəsiz deploy-lar |

## Dillər — Nə və niyə

### Scala
- Zalando-nun servis zirvəsində populyar seçim. Funksional pattern-lərin geniş istifadəsi.
- Mövcud Java sistemləri ilə JVM interop.

### Java
- Bir çox servisin əsas backend-i.
- Spring Boot çox yayılıb.

### Python
- Data, ML, tooling.

### Go
- İnfra-tipli servislər üçün seçici.

### Kotlin
- Getdikcə daha çox qəbul edilir.

**Tarixi qeyd:** "Radical agility" dövründə Zalando-da istehsalda onlarla dil var idi, çünki hər komanda öz seçimini edə bilərdi. Zamanla, əməliyyat qabiliyyəti üçün daha kiçik "golden path" dəstinə konsolidasiya etdilər.

## Framework seçimləri — Nə və niyə
- Java servisləri üçün **Spring Boot**.
- Tarixən Scala üçün **Play Framework / Akka**.
- Frontend-də **React + Redux / müasir React**.
- Servislər arasında **OpenAPI** (Swagger) müqavilələri — erkən standartlaşdırıldı.
- Logging, tracing, error handling üçün öz kitabxanaları, daxili maven/Gradle repo-ları vasitəsilə deploy edilir.

## Verilənlər bazası seçimləri — Nə və niyə

### PostgreSQL
- Default seçim. Zalando çox Postgres-mərkəzli şirkətdir.
- E-ticarətdə relational bütövlük vacibdir: sifarişlər, stok, ödənişlər.

### Patroni (onların yaradılması)
- Zalando-da qurulmuş open-source Postgres cluster meneceri.
- İndi self-hosted Postgres HA üçün sənaye standartıdır.
- Leader seçimi üçün etcd / ZooKeeper / Consul istifadə edir.

### Spilo (onların yaradılması)
- Container mühitlərində asan HA üçün Postgres + Patroni + WAL-E (backup-lar) paketləyən Docker imicidir.
- Zalando-dan kənarda geniş istifadə olunur.

### Kafka
- Event əsası.
- Servislər event yayır; digər servislər qəbul edir.
- Sistemin hissələrində event sourcing pattern-ləri.

### Elasticsearch
- Məhsul axtarışı, kataloq sorğuları.

## Proqram arxitekturası

```
     Users
       |
   [CDN + LB]
       |
   [API Gateway]
       |
 +---- Microservices (many hundreds) ----+
 |   Catalog  Cart  Checkout  Payment   |
 |   Search   Recs  Inventory  Ship     |
 +--------------------------------------+
       |
    Kafka event bus
       |
   Postgres (Patroni/Spilo HA) + Elasticsearch + Redis + S3
       |
   Data platform (warehouse, ML training)
```

- Zirvədə ~1000+ microservice.
- Kafka vasitəsilə event-əsaslı koordinasiya.
- İllərlə STUPS (öz AWS deploy framework-ləri) istifadə olundu; sonra Kubernetes-ə köçürüldü.

### STUPS → Kubernetes
- STUPS Zalando-nun Kubernetes-dən əvvəlki dövrün AWS deploy tooling-i idi: AMI-lər, security group-lar, deploy qaydaları.
- Təmiz əməliyyat zəmanətləri verdi (hər istehsal qutusu məlum konfiqurasiyalı immutable AMI idi).
- Kubernetes yetişdikcə nəhayət onunla əvəz olundu.

## İnfrastruktur və deploy
- Ağır AWS.
- STUPS dövrü üçün immutable AMI-lər (Taupage).
- İndi Kubernetes.
- Ağır CI/CD, feature flag-lər, canary deploy-lar.
- OpenAPI müqavilələri və API guild nəzarəti.

## Arxitekturanın təkamülü

| İl | Dəyişiklik |
|------|--------|
| 2008 | Magento-əsaslı Rocket Internet stack-i |
| 2012–2014 | Artım zamanı monolit stressi |
| 2015 | Radical Agility elan edildi; microservice partlayışı; komanda muxtariyyəti |
| 2016 | Patroni + Spilo buraxıldı |
| 2017–2018 | Event əsası olaraq Kafka möhkəmləndirildi |
| 2019+ | Standartlaşdırma; daha az golden path stack-ləri; daha çox guardrail |
| 2020+ | Kubernetes STUPS-u əvəz edir |
| 2023+ | ML, fərdiləşdirmə sərmayəsi |

## Əsas texniki qərarlar

1. **Radical Agility.** Komandaların stack seçmək muxtariyyəti. Qısa müddətdə moral və sürət üçün əla; uzun müddətdə fraqmentasiyaya və ops xərcinə səbəb oldu.
2. **Patroni + Spilo.** Mülkiyyət Postgres HA üçün ödəməkdənsə, qurdular və ən çox istifadə olunan Postgres HA stack-lərindən birini open-source etdilər.
3. **Sinir sistemi olaraq Kafka.** Servislər əksər async flow-lar üçün birbaşa çağırışlar deyil, event-lər vasitəsilə ünsiyyət qurur.
4. **Kubernetes hazır olmazdan əvvəl STUPS.** Kubernetes yetişəndə praktik, düşünülmüş deploy sistemi.
5. **Golden path-a geri konsolidasiya.** Miqyasda tam muxtariyyətin çox bahalı olduğunu etiraf etmək yaxşı mədəni addım idi.

## Müsahibədə necə istinad etmək

1. **Microservice-lərin xərc əyrisi var.** Hər əlavə servis monitoring, deploy, CI, DB miqrasiya işi əlavə edir. Zalando bunu çətin yolla öyrəndi. Konkret ağrı olmayana qədər Laravel monolitlərini saxlayın.
2. **Muxtariyyət vs ardıcıllıq açar deyil, spektrdir.** Komandalara queue driver-ləri, caching strategiyaları seçməyə icazə verə bilərsiniz — amma yəqin ki dili yox. Golden path seçin, əsaslandırma ilə istisnalara icazə verin.
3. **Postgres HA sehr deyil.** Tək Postgres ilə VPS-də Laravel işlədirsinizsə, bir elektrik kəsintisinin kənarındasınız. Patroni/Spilo-nu öyrənin və ya idarə olunan RDS/Cloud SQL istifadə edin.
4. **Event-əsaslı koordinasiya yaxşı miqyaslanır.** Laravel + Redis Streams / Kafka daha kiçik miqyasda eyni pattern-ləri həyata keçirə bilər. Event-lər servisləri decouple edir.
5. **Əvvəlcə OpenAPI.** Kod yazmadan əvvəl OpenAPI vasitəsilə API müqavilələrini müəyyən etmək bir sürü bug tutur. Laravel-in L5-Swagger və ya Scribe-i bunu edə bilər.
6. **Uyğun yoxdursa deploy platforması qur.** Əksər shop-lar üçün AWS Elastic Beanstalk / ECS / sadə Laravel Forge kifayətdir. Amma bilin ki Zalando o zaman ümumi alətlər onlara uyğun gəlmədiyi üçün öz platformalarını qurdu. Leverage olan yerdə miqyas.

## Əlavə oxu üçün
- Zalando Tech Blog: *Radical Agility at Zalando*
- Zalando Tech Blog: *Patroni: a template for Postgres HA*
- Zalando Tech Blog: *Spilo: Postgres in the cloud*
- Zalando Tech Blog: *Our Journey to Kubernetes*
- Zalando Tech Blog: *STUPS — Our AWS platform*
- Postgres konfranslarında çıxışlar (Patroni, Spilo)
- Book: Sam Newman-ın *Building Microservices* — Zalando hekayəsi üçün düzgün nəzəriyyə
- OpenAPI Guild documentation (Zalando-nun API qaydaları, GitHub-da açıq)
