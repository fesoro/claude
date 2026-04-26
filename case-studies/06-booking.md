# Booking.com (Senior)

## Ümumi baxış
- **Nə edir:** Online travel agency (OTA). Otellər, mənzillər, uçuşlar, kirayə maşınlar, təcrübələr. Əsasən otel aggregator-u, dünyada ən böyüyü.
- **Yaradılıb:** 1996-cı ildə Amsterdam-da.
- **Alınma:** 2005-də Priceline tərəfindən (indi **Booking Holdings**, həmçinin Priceline, Kayak, Agoda, OpenTable-ə sahibdir).
- **Miqyas:**
  - 30M+ yaşayış listing-i.
  - İldə yüz milyonlarla bronlaşdırma.
  - Trafikə görə qlobal #1 səyahət saytı.
  - 40+ dildə mövcuddur.
- **Əsas tarixi anlar:**
  - 1996: Bookings.nl kimi yaradıldı.
  - 2005: Priceline tərəfindən alındı.
  - 2010-cu illər: SEO, konversiya optimizasiyası, davamlı A/B testing vasitəsilə dominant oldu.
  - 2020: COVID şoku; böyük gəlir düşüşü; bərpa gəldi.
  - 2024: ~hələ də böyük Perl monolit işlədir.

Booking.com **cansıxıcı texnologiyanın hype-dan uzunömürlü olmasının** əfsanəvi nümunəsidir. Hələ də **Perl 5** işlədirlər — dünyada mövcud ən böyük Perl kod bazalarından biri — və bunun üzərində dominant səyahət biznesini qurdular.

## Texnologiya yığını

| Layer | Technology | Why |
|-------|-----------|-----|
| Main language | **Perl 5** (object-oriented, öz framework-ü) | 1-ci gündən, güclü mətn emalı, yetkin ops |
| Newer services | Java, Go, Scala (seçici) | Uyğun olan yeni funksiyalar üçün |
| Primary DB | MySQL (çox-petabayt) | Yetkin, operator-lar onu dərindən bilir |
| Other DBs | CouchDB, Elasticsearch | Spesifik use case-lər |
| Cache | Memcached, Redis | Standart caching |
| Queue | Kafka, RabbitMQ | Messaging / event streaming |
| Search / recs | Elasticsearch, ML servisləri | Content axtarışı, tövsiyələr |
| Experimentation | Custom A/B platform | Yüzlərlə eyni anda aparılan eksperiment |
| Infrastructure | Öz data mərkəzləri + cloud qarışıq | Legacy + modernləşdirmə |
| Monitoring | Custom + standart alətlər | Dərin observability |

## Dillər — Nə və niyə

### Perl 5 — hələ də 2026-da
- Əsas backend **Perl 5**-dir.
- Booking.com dünyanın ən böyük Perl kod bazalarından birinə malikdir — on milyonlarla sətir, tarixən minlərlə Perl engineer.
- Böyük Perl töhfə verənləridir; bir çox CPAN (Perl-in paket ekosistemi) modulu Booking insanlarından gəlir və ya onlar tərəfindən saxlanılır.
- Öz framework-ü ilə OO Perl.

**Niyə Perl?**
- Perl 1990-cı illərdə web üçün trend idi (PHP dominantlaşmadan əvvəl).
- Mətn emalı, regex, string manipulasiyası üçün əladır — otel data-sının, email template-lərinin və s. parsing-i üçün faydalıdır.
- Şirkət iki onillik ərzində Perl tooling-inə, mod_perl deploy-una və daxili kitabxanalara sərmayə qoydu.
- Sistemi yenidən yazmaq yüz milyonlarla dollara başa gələrdi. Biznes bunu əsaslandırmır.

### Java, Scala, Go, Python
- Yeni servislər: ML platforması, data pipeline-ları, bəzi yeni məhsul təşəbbüsləri.
- Strategiya: **Perl core qalır, yeni iş uyğun müasir dildə ola bilər.**

### JavaScript / TypeScript
- Frontend: son illərdə React. Əvvəl, ağır server-render olunan Perl template-ləri (Mason / Template Toolkit).

## Framework seçimləri — Nə və niyə
- CPAN-standart Catalyst/Mojolicious deyil, daxili Perl framework.
- Əvvəllər templating üçün Template Toolkit / Mason.
- Yeni JVM servisləri üçün: Spring, Dropwizard-tipli.
- Monolit-in ölçüsünə görə custom build / CI tooling.

## Verilənlər bazası seçimləri — Nə və niyə

### MySQL — ağır
- Çox-petabayt MySQL, harada olursa olsun ən böyük deploy-lardan biri.
- Data mərkəzləri arasında sharded və replikasiya olunub.
- Percona / MySQL üçün öz patch-ləri geniş istifadə olunur.
- Tranzaksiya data-sı üçün InnoDB.

### CouchDB
- Bəzi distributed, schema çevik data üçün istifadə olunur.

### Elasticsearch
- Otel axtarışı və mövcudluq.

### Cassandra, digər
- Müxtəlif spesifik use case-lər.

## Proqram arxitekturası

Booking.com **ətrafında servislər olan nəhəng Perl monolit**-dir. Onu soğan kimi düşünün: Perl core + yeni şeylər üçün Java/Go/Scala servis qatları.

```
        Users / Affiliates
              |
        [CDN + Edge + LB]
              |
        [Perl monolith] <-- the main brain
          /   |   \
         v    v    v
      MySQL  Cache  ES
              |
     [New Java/Scala/Go services for ML, new products]
              |
           Kafka bus
```

### Eksperimentasiya
- Booking çoxlu A/B testlərini eyni anda işlədir — tez-tez yüzlərlə.
- Konversiya optimizasiya mədəniyyətinə görə məşhurdur.
- Hər əsas buraxılış adətən eksperiment gating-dən keçir.

### Lokalizasiya
- 40+ dil, yüzlərlə valyuta, minlərlə ölkə/regional spesifiklik (vergi, hüquqi mətn).
- Nəhəng lokalizasiya infrastrukturu.

## İnfrastruktur və deploy
- Öz data mərkəzləri və cloud qarışıq.
- Çox yetkin deploy pipeline-ları; gündə çox dəfə deploy edirlər.
- Hər yerdə feature flag-lər.
- Rollback + eksperiment-əsaslı buraxılış güclü mədəniyyəti.

## Arxitekturanın təkamülü

| Year | Change |
|------|--------|
| 1996 | Perl CGI-əsaslı sayt |
| 2000s | mod_perl, böyük monolit böyüyür |
| 2005 | Priceline alışı; sərmayə artır |
| 2010s | Sharded MySQL; axtarış üçün Elasticsearch |
| 2015+ | ML və data platforması üçün Java/Scala servisləri əlavə olundu |
| 2020 | COVID şoku, xərc azaltma |
| 2020+ | Yeni funksiyaların daha çox modernləşdirilməsi; Perl core qalır |

## 3-5 Əsas texniki qərarlar

1. **Perl-i saxla — yenidən yazma.** Daha az praktik şirkət Java və ya Go-da yenidən yazmaq üçün yüz milyonlar xərcləyərdi və çox güman ki uğursuz olardı. Booking yayımlamağa davam edir.
2. **Yeni funksiyalar üçün strangler-fig pattern.** Yeni şeylər Perl core ilə danışan Java/Scala/Go servislərinə gedir. Böyük partlayış yoxdur.
3. **Eksperimentasiyaya kütləvi sərmayə.** A/B testlər və data-əsaslı qərarlar ətrafında qurulmuş mədəniyyət.
4. **Öz data mərkəzi + hybrid cloud.** Onların miqyasında qarışıq strategiya məna verir.
5. **Perl / CPAN-a upstream töhfə.** Ekosistemi canlı saxlayır və (azalmaqda olan) Perl talent hovuzu üçün hiring təklifidir.

## Müsahibədə necə istinad etmək

1. **Cansıxıcı texnologiya bir feature-dur, bug deyil.** Laravel 8 tətbiqiniz işləyirsə, Twitter etdi deyə Node-a yenidən yazmayın. Yayımlamağa davam edin.
2. **Monolit + edge servislər real pattern-dir.** Microservice-ləri məcbur etməyin. Monolit-i saxlayın. Yalnız əsaslandırıldıqda spesifik servisləri ayırın.
3. **Eksperimentasiya mədəniyyəti stack-inizdən daha önəmlidir.** Booking qazanır, çünki hər şeyi test edir. Laravel tətbiqiniz + yaxşı A/B testing quraşdırması eyni şeyi edə bilər.
4. **Lokalizasiya çətindir; erkən dizayn edin.** Əgər heç vaxt çoxlu dil/valyuta ola bilərsə, birinci gündən bu haqda düşünün. Laravel-in lokalizasiya köməkçiləri başlanğıcdır, amma daha dərin sual data modeli və testing-dir.
5. **Hiring stack-i idarə edir.** Booking Perl insanlarını eyni sürətlə işə götürə bilmədiyi üçün Java/Scala əlavə etdilər. Laravel shop-lar: PHP developer-lərini işə götürmək sizin üçün çətinləşərsə, yeni servislər üçün ikinci dili düşünmək vaxtı ola bilər.
6. **"On milyonlarla sətir" əksər developer-lərin heç vaxt görmədiyi miqyasdır.** Belə bir kod bazasından dərslər (statik analiz, kod sahibliyi, monorepo tooling) düşündüyünüzdən daha erkən tətbiq olunur.

## Əlavə oxu üçün
- Booking.com Tech Blog: müxtəlif Perl-in istehsalda postları
- Talks: YAPC / Perl Conference-də Booking.com (Perl miqyaslanması)
- Talk: *Scaling Booking.com's MySQL*
- High Scalability blog: *Booking.com's architecture*
- Book: chromatic-in *Modern Perl* (OO Perl üçün kontekst)
- Strangler-fig pattern haqqında məqalələr (Martin Fowler) — birbaşa burada tətbiq olunur
