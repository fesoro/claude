# Etsy

## Ümumi baxış
- Etsy əl işi, vintage və sənətkarlıq əşyaları üçün onlayn marketplace-dir. Alıcılar milyonlarla kiçik mağazada müstəqil satıcıları tapır.
- Miqyas: təqribən 90+ milyon aktiv alıcı və bir neçə milyon aktiv satıcı, ildə milyardlarla dollar GMV (Etsy 10-K filings, 2023).
- Əsas tarixi anlar:
  - 2005 — Brooklyn-də Rob Kalin, Chris Maguire və Haoyi Wei tərəfindən təsis edildi.
  - 2008-2010 — CTO Chad Dickerson + VP Ops John Allspaw dövründə məşhur miqyaslanma və mühəndislik mədəniyyəti transformasiyası.
  - 2009 — StatsD (metrik demon) daxildə yaradıldı; tezliklə açıq mənbəyə çevrildi.
  - 2010-2012 — "Deployinator" və davamlı deployment məşhur olur (gündə 50+ deploy).
  - 2015 — IPO.
  - 2017-2020 — Cloud-a (Google Cloud) tədrici keçid.

## Texnologiya yığını
| Qat | Texnologiya | Niyə |
|-------|-----------|-----|
| Dil | PHP (əsas web), Scala / Java (servislər), Python (data) | Monolit üçün PHP; JVM öz qazancını gətirdiyi yerlərdə Scala/Java (axtarış, ML, ödənişlər). |
| Web framework | Daxili PHP framework | Laravel/Symfony yetkinliyindən əvvəldir; nümunələri 2000-ci illərin ortasında qoyuldu. |
| Əsas DB | MySQL | Ağır şəkildə "Etsy üslubunda" shardlanıb (funksional + horizontal). |
| Cache | Memcached | Klassik LAMP. |
| Queue/messaging | Gearman (tarixi), sonra Kafka və öz job sistemləri | Sadə async job-lar üçün Gearman; event stream-lər üçün Kafka. |
| Search | Solr (orijinal) → "Etsyweb" / xüsusi axtarış | Relevance rəqabət fərqidir. |
| İnfrastruktur | Əvvəlcə öz data mərkəzləri, ~2018-dən Google Cloud-a köçürüldü | GCP-nin xərci və sürəti, data mərkəzləri saxlamaqla müqayisədə. |
| Monitorinq | Graphite + StatsD (onlar ixtira edib), Grafana, sonra Nagios və Prometheus | Müasir dev metrik mədəniyyətini sözün əsl mənasında təyin etdilər. |

## Dillər — Nə və niyə

### PHP
Etsy-nin web tier-i PHP-dir. Şirkət "miqyasda PHP" haqqında ən çox sitat gətirilən hekayələrdən biridir, qismən çünki bu barədə çox açıq yazıblar. Güclü convention over configuration mədəniyyəti ilə daxili framework işlədirlər.

PHP çörək-yağdır çünki:
- Komandanın onunla dərin əməliyyat təcrübəsi var.
- Sürətli request dövrü, sadə deploy modeli, asan işə götürmə.
- Davamlı deployment mədəniyyəti ilə yaxşı cütləşir — PHP deploy-u faylları kopyalamaq qədər sürətlidir.

### Scala / Java
Zamanla Etsy davamlı ötürücülük və ya aşağı gecikmə tələb edən iş yükləri üçün JVM əsaslı servislər əlavə etdi: axtarış (Solr JVM-dədir), ödəniş riski, ML feature pipeline-ları, tövsiyələr.

### Python
Data mühəndisliyi, ML modelləri (Apache Beam, Spark PySpark, TensorFlow/PyTorch vasitəsilə).

### Digərləri
Infra utility-ləri üçün bəzi Go. Front-end standart JS/TypeScript-də, server-rendered HTML plus progressive enhancement ilə.

## Framework seçimləri — Nə və niyə

Etsy heç vaxt Laravel və ya Symfony mənimsəmədi; onların PHP framework-ü ev hazırıdır və həmin versiyaların yetkin olmasından əvvəl gəlir. Bəzi ictimai xüsusiyyətlər:
- Klassik MVC-bənzər, URL ilə map olunmuş PHP fayllarında controller-lər.
- Hər səviyyəyə yerləşdirilmiş feature flag-lar — istifadəçi üzrə, kohort üzrə, faiz üzrə rollout şeyləri açmaq göndərmə üsulunuzdur.
- Framework səviyyəsində A/B test inteqrasiyası: hər göndərmə eksperiment ola bilər.
- Dependency injection yüngüldür; test edilə bilənlik Symfony-üslublu container-dan daha çox yaxşı PHPUnit intizamı haqqındadır.

*Framework*-dən çox *proses* haqqında çox yazdılar: post-mortem-lər ("təqsirsiz post-mortem" termini Etsy-dən gəlir), production-da test etmə, tədrici rolloutlar, dark oxumalar. Framework seçimləri müqayisədə demək olar ki, darıxdırıcı görünür — dərs odur ki, proses framework-ü döyür.

## Verilənlər bazası seçimləri — Nə və niyə

### MySQL
Əsas store. Etsy-nin shardlama nümunəsi blog post-lar və talk-lardan yaxşı tanınır:
- **Funksional shardlama**: fərqli data növləri fərqli kluster-lərdə yaşayır (listings, orders, users, messages...).
- **Horizontal shardlama**: kluster daxilində istifadəçi ID (və ya listing ID) shard-a map olunur.
- **Ticket server**: kiçik xüsusi MySQL cütlüyü MySQL-in `AUTO_INCREMENT`-i vasitəsilə qlobal unikal ID verir — onların "Ticket Servers: Distributed Unique Primary Keys on the Cheap"-da dərc etdiyi bir fənddir.

Bu, "darıxdırıcı aləti istifadə etməyə davam et, amma onu necə istifadə etdiyin barədə çox intizamlı ol" ifadəsinin gözəl nümunəsidir.

### Memcached
Oxunma yüklü sorğular üçün ağır istifadə (listing səhifələri, istifadəçi profilləri, home-feed tövsiyələri).

### Axtarış — Solr, sonra xüsusi
Axtarış Solr-də başladı. Zamanla Etsy axtarış infrastrukturuna rəqabət xüsusiyyəti kimi sərmayə qoydu — relevance tuning, personalisation, learning-to-rank modelləri. Etsy mühəndislərindən listings-i necə indeksləşdirdikləri, sorğu etdikləri və sıraladıqları haqqında ictimai talks var.

### HBase / analitika store-ları
Log-lar və analitika (time-series hadisə datası, clickstream-lər) üçün illər ərzində HBase, Vertica, BigQuery (GCP miqrasiyasından sonra) və digərlərini istifadə ediblər.

## Proqram arxitekturası

Etsy arxitekturası tarixən **PHP monolit**-dir, servis ayrılmaları ilə:
- **PHP monolit**: əsas etsy.com web app. Səhifələrə xidmət göstərir, API-ları idarə edir.
- **Search service** (JVM): Solr əsaslı, sonra xüsusi.
- **Payment service** (JVM): PCI-həssas axınları idarə edir, uyğunluq üçün izolyasiya edilib.
- **ML / tövsiyələr** (Python + JVM): batch + online modellər.
- **Mobile backend** (mobile API-larına həsr olunmuş bəzi servislər).
- **Data platform**: batch (Hadoop → cloud köçürmədən sonra BigQuery), streaming (Kafka), warehousing.

```
 [User]
   |
   v
 [CDN + edge]
   |
   v
 [Load balancer]
   |
   v
 [PHP monolith (etsy.com)]
   |
   +--> [Memcached]
   +--> [MySQL — sharded functionally and horizontally]
   +--> [Search service (Solr / custom)]
   +--> [Payments service]
   +--> [Recommendations / ML]
   +--> [Kafka / Gearman job queues]
          |
          v
   [Async workers — PHP and JVM]
```

## İnfrastruktur və deploy
- Əvvəlcə öz data mərkəzlərində işləyirdi. Google Cloud-a miqrasiya 2018 ətrafında başladı və bir neçə il içində əsasən başa çatdı.
- **Deployinator** — daxili deploy aləti. Böyük düymə, kim nə vaxt nəyi push etdi dashboard-u və kodu fleet-ə təhlükəsiz şəkildə çıxaran pipeline. Mühəndislik blogunda məşhur oldu.
- **Davamlı deployment** — zirvədə ictimai olaraq gündə 50+ deploy təsvir etdilər, hər mühəndis ilk həftəsində deploy edə bilməli idi.
- **Feature flag-lar** — hər xüsusiyyət admin UI vasitəsilə idarə olunan flag arxasında göndərilir.
- **Production-da test etmə** / **dark launches** — yeni kod yolları trafikin 1%-i üçün açılır, switch etməzdən əvvəl düzgünlük üçün köhnə yollarla müqayisə edilir.

## Arxitekturanın təkamülü

1. **2005-2008**: LAMP, kiçik komanda, miqyaslanma ağrıları. Hələ mühəndislik üçün poster child deyil.
2. **2008-2011**: Chad Dickerson və John Allspaw mühəndislik mədəniyyətini dəyişdirir. Monitorinq (StatsD), deploy-lar (Deployinator), post-mortem-lər, feature flag-lar.
3. **2011-2015**: Miqyaslanma davam edir; axtarış öz JVM yığınını alır; tövsiyələr yetişir; mobil böyüyür.
4. **2015-2018**: IPO. Scala-da bəzi daxili servislər. ML infrastrukturu böyüyür.
5. **2018-sonrakı**: Google Cloud-a miqrasiya. Anbar kimi BigQuery. Servislər üçün Kubernetes.

## Əsas texniki qərarlar

### 1. StatsD ixtirası
**Problem**: Dev komandalarının kodu sayğaclar və taymer-lərlə instrumentasiya etmək lazım idi, amma metrik-ləri mərkəzi store-a sinxron yazmaq bahalı idi.
**Seçim**: Tətbiqlər lokal (və ya şəbəkə) StatsD demon-una UDP paketlər atır. StatsD sayğacları/taymer-ləri aqreqat edir və hər 10 saniyədə Graphite-ə göndərir. Fire-and-forget, itkiyə dözümlü.
**Kompromislər**: UDP doyma altında bəzi paket itkisini bildirir — statistikalar üçün qəbul ediləndir.
**Sonra nə oldu**: StatsD (əvvəlcə Flickr / Etsy / Erik Kastner-dən) sənaye standartı oldu. "Hər şeyi ölç, hər şeyi qrafiklə göstər" mədəniyyəti onunla yayıldı.

### 2. Deployinator ilə davamlı deployment
**Problem**: Köhnə məktəb həftəlik buraxılışlar böyük-partlayış dəyişikliklər, yavaş əks-əlaqə, qorxulu rollback-lar demək idi.
**Seçim**: Hər biri feature flag arxasında olan gündəlik çoxlu kiçik dəyişiklik göndərin. Deployinator pipeline-ı idarə edir: testlər, staging, canary, tam rollout.
**Kompromislər**: Güclü test mədəniyyəti, yaxşı observability və main-i yaşıl saxlamaq intizamına ehtiyacınız var.
**Sonra nə oldu**: Etsy 2010-cu illərdə CD üçün istinad nümunəsi oldu. Jez Humble "Continuous Delivery" / "Accelerate" əsərlərində onları sitat gətirir.

### 3. Paylanmış ID-lər üçün Ticket server-lər
**Problem**: MySQL-i horizontal shardladığınız zaman hər shard-ın auto-increment-i lokaldır — onu qlobal ID kimi istifadə edə bilməzsiniz.
**Seçim**: Tək işi ID vermək olan xüsusi MySQL cütlüyü. `REPLACE INTO Tickets64 (stub) VALUES ('a'); SELECT LAST_INSERT_ID();` sonrakı ID-ni verir. İkisini offset konfiqurasiyasında işlədin (biri tək ID, digəri cüt ID verir) ki, biri ID yaradılmasını bloklamadan uğursuz ola bilsin.
**Kompromislər**: Twitter-in Snowflake-i qədər təntənəli deyil — embedded timestamp yoxdur — amma ölümcül sadə və etibarlıdır.
**Sonra nə oldu**: Snowflake ixtira etmədən paylanmış ID-ləri həll etməyin ən sadə yolu kimi geniş istinad edilir.

### 4. Təqsirsiz post-mortem-lər
**Problem**: Mühəndislər xarab olmalardan sonra bir-birlərini günahlandırır; risk almağı dayandırırlar; təşkilat öyrənməyi dayandırır.
**Seçim**: Post-mortem-lər sistemin nə baş verməsinə icazə verdiyinə fokuslanır, kimin etdiyinə deyil. John Allspaw bu barədə geniş yazdı.
**Kompromislər**: Mədəni intizam tələb edir — ego və iyerarxiya buna qarşı işləyir.
**Sonra nə oldu**: Sənaye standartı oldu. Google SRE kitabı eyni yanaşmanı götürür. Bu, texnikadan çox proses qərarıdır, amma Etsy-nin ən davamlı töhfələrindən biridir.

### 5. Google Cloud-a köçmə
**Problem**: Öz data mərkəzlərini işlətmək kapitalı və mühəndisləri müştərilərin görmədiyi infra-ya bağlayırdı.
**Seçim**: Google Cloud-a köçmə — əvvəlcə VM-lər, sonra daha dərin GCP servisləri (BigQuery, GKE).
**Kompromislər**: Xərc modeli CapEx-dən OpEx-ə keçir və cloud qiymətləndirməsinə açıqsınız.
**Sonra nə oldu**: Daha sürətli data platformasını (BigQuery) təmin etdi, ops vaxtını azad etdi, amma mühəndislik təşkilatının fəxr etdiyi "öz metalimizdə işləyirik" nişanını da götürdü.

## PHP/Laravel developer üçün dərs
- Davamlı deployment PHP-də mümkündür. Feature flag-lardan istifadə edin (Laravel Pennant və ya sadə DB-backed), kiçik göndərin, ölçün və main-i həmişə deploy edilə bilən saxlayın.
- Hər şeyi instrumentasiya edin. StatsD-üslublu client əlavə edin (PHP-nin var) və kritik yollara sayğacları/taymer-ləri qoşun. Qrafiklər sistem haqqında düşüncənizi dəyişir.
- Shardlama kitabxana deyil, intizamdır. Etsy göstərdi ki, funksional *və* horizontal şəkildə shardlasanız, ID-lər üçün ticket server-lərlə, standart MySQL-də nəhəng trafik işlədə bilərsiniz.
- Təqsirsiz post-mortem-lər kiçik startup-larda da işləyir. Onları yazın, şirkət daxilində ictimai paylaşın və heç vaxt "X bunu etdi" deməyin.
- Proses framework-ü döyür. Əla prosesi olan darıxdırıcı framework kovboy deploy-ları olan parlaq framework-dən yaxşıdır.

## Əlavə oxu üçün
- Book: "Web Operations" edited by John Allspaw and Jesse Robbins (O'Reilly). Multiple Etsy chapters.
- Book: "The DevOps Handbook" — Etsy is a central case study.
- Talk: "Ops Meta-Metrics" — John Allspaw.
- Blog: "Deployinator" posts on codeascraft.com (Etsy engineering blog).
- Blog: "Ticket Servers: Distributed Unique Primary Keys on the Cheap" — Etsy engineering blog.
- Paper/essay: "How Complex Systems Fail" — Richard Cook (cited by Allspaw constantly).
- Talk: "A Mature Role for Automation" — John Allspaw (Velocity).
- Blog: codeascraft.com, long-running engineering blog with many scaling posts.
- Talk: "Continuous Deployment at Etsy" — Mike Brittain.
