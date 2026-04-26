# Instagram (Lead)

## Ümumi baxış
- **Nə edir:** Şəkil və qısa video paylaşma şəbəkəsi. İstifadəçilər şəkillər, Stories (24 saatlıq), Reels (qısa video), birbaşa mesajlar (DM) və canlı yayımlar paylaşır.
- **Qurulub:** 2010-da Kevin Systrom və Mike Krieger tərəfindən.
- **Alınıb:** 2012-də Facebook (indi Meta) tərəfindən təxminən **$1B**-a — ən məşhur texnologiya alışlarından biridir.
- **Miqyas (2024):**
  - ~2 milyard aylıq aktiv istifadəçi (MAU).
  - 100+ milyard şəkil və video saxlanılır.
  - Gündə yüz milyonlarla Stories post edilir.
  - Reels gündə milyardlarla dəfə izlənir.
- **Əsas tarixi anlar:**
  - 2010: iOS-da işə salındı, ilk gündə 25k istifadəçi.
  - 2012: Facebook tərəfindən alındı. İllərlə müstəqil işlədi.
  - 2013: video dəstəyi əlavə olundu.
  - 2016: Stories xüsusiyyəti (Snapchat-dan ilhamlanıb).
  - 2018: Systrom və Krieger Meta-dan ayrıldı.
  - 2020: TikTok rəqibi kimi Reels işə salındı.
  - 2023: Threads Instagram infrastrukturundan işə salındı.

Instagram çox vacib case study-dir, çünki **Python-un milyardlarla istifadəçiyə miqyaslaşa biləcəyini** göstərir — əgər onu diqqətlə mühəndislik etsəniz. O həmçinin Facebook tərəfindən alındıqdan sonra belə orijinal stack-də qaldı (Facebook daxildə Hack/PHP istifadə edir), bu da mədəni cəhətdən qeyri-adidir.

## Texnologiya yığını

| Layer | Technology | Niyə |
|-------|-----------|-----|
| Language | Python 3 (Python 2-dən köçürüldü) | Oxunaqlıq, sürətli iterasiya, nəhəng ekosistem |
| Web framework | Django (customize edilib) | Orijinal olaraq 2010-da seçildi, komanda tanışlığı və ORM gücü səbəbindən saxlanıldı |
| Primary DB | PostgreSQL (ağır şəkildə shard-lanmış) | ACID, güclü SQL, miqyasda yetkin |
| NoSQL | Cassandra | Feed/inbox və yüksək yazma data üçün istifadə olunur (Facebook inteqrasiyası vasitəsilə miras qalıb) |
| Cache | Memcached (kütləvi fleet), Redis | Feed-lər, sayğaclar, sessiyalar üçün aşağı gecikməli oxumalar |
| Queue/messaging | Custom broker üzərində Celery (əvvəlcə: RabbitMQ) | Arxa plan job-ları (bildirişlər, feed fanout) |
| Search | Custom (Facebook Unicorn tipli) | Hashtag, istifadəçi, yer axtarışı |
| Connection pooling | PgBouncer | Lazımdır, çünki bir çox Python prosesi DB bağlantılarını çoxaldır |
| Web server | Gunicorn + nginx | Standart Python WSGI deploy |
| Media storage | Facebook-un Haystack / TAO təbəqəsi | Milyardlarla şəkil üçün artıq döyüş sınağından keçmişdir |
| Monitoring | Scuba, ODS (daxili Facebook alətləri) | Meta miqyasında real-time metrics və tracing |

## Dillər — Nə və niyə

### Python (əsas backend)
- Django 2010-da seçildi, çünki Systrom əvvəlcə tək developer idi və sürət lazım idi.
- Alışdan sonra da Python-da qaldı, baxmayaraq ki, Facebook Hack/PHP-dir.
- **Python-u niyə saxladılar:** komanda məhsuldarlığı, işləyən sistemi yenidən yazmaq xərci nəhəngdir və isti yollar optimallaşdırıldıqda Python kifayət qədər sürətlidir.
- **C extension-larda isti yollar:** feed sıralaması və digər performans-kritik kod Python C extension və ya Cython kimi yazılır.
- **Cinder**-dən ağır istifadə, Instagram-ın performansa yönəlmiş Python runtime-ı (hissələrini açıq mənbə etdilər). Cinder JIT, strict modulları və static Python-u ehtiva edir.

### Python 2 → Python 3 miqrasiyası
- Tarixdə ən böyük Python 3 miqrasiyalarından biri.
- Blog: *"Python at Scale: Strict Modules"* və *"Python 3 migration at Instagram"*.
- İllər çəkdi; tədricən, modul-modul, uyğunluq shim-ləri ilə edildi.

### Static analiz
- Instagram **LibCST** (concrete syntax tree) və **Pyre** (Meta-dan tip yoxlayıcısı)-a ağır investisiya etdi.
- Blog: *"Static analysis at scale: An Instagram story"*.
- Səbəb: böyük Python kod bazası təhlükəsiz qalmaq üçün tip yoxlamasına ehtiyac duyur.

### Digər dillər
- Performans extension-ları üçün C/C++.
- Web client üçün JavaScript / TypeScript.
- iOS üçün Objective-C / Swift, Android üçün Java / Kotlin.

## Framework seçimləri — Nə və niyə
- **Django** 2010-da praqmatik seçim idi: ORM, admin, auth, templating — tez göndərmək üçün hər şey.
- Zamanla Instagram Django-nu soyundurdu: isti yollar üçün ORM-dən kənarlaşır və raw SQL və ya custom data-access təbəqələri yazır.
- Hələ də URL routing, middleware və ümumi HTTP həyat dövrü üçün Django istifadə edirlər, lakin miqyasda "stack" defolt Django tətbiqindən çox daha custom-dur.

## Verilənlər bazası seçimləri — Nə və niyə

### PostgreSQL — shard-lanmış
- **Sharding & IDs at Instagram (2012 blog)** web engineering-də ən çox istinad edilən yazılardan biridir.
- Problem: 64 bit-ə sığan və vaxta görə sıralanan qlobal unikal ID-lərə ehtiyac var idi.
- Həll: Snowflake tipli 64-bit ID-lər:
  - 41 bit: timestamp (custom epoch-dan millisaniyələr).
  - 13 bit: məntiqi shard ID.
  - 10 bit: shard başına auto-increment.
- **Postgres daxilində stored procedure-lər (PL/pgSQL) istifadə edərək generasiya edildi**, beləliklə ayrıca ID servisinə ehtiyac yox idi.
- Shard-lar məntiqidir (minlərlə), fiziki maşınlara xəritələnir. ID-ləri dəyişmədən shard-ları maşınlar arasında köçürə bilərsiniz.

```
       Application
           |
      [PgBouncer pool]
           |
  +--------+--------+-------- ...
  |        |        |
 shard1  shard2   shard3   (logical shards)
   \       |       /
    \      |      /
  physical Postgres hosts (many shards per host)
```

### Cassandra
- Inbox (Direct mesajlar), feed saxlama və digər yüksək yazma iş yükləri üçün istifadə olunur.
- Facebook infrastrukturundan miras qalıb.

### Memcached
- Nəhəng cluster. İsti hər şey cache edilir: istifadəçi profili, follower sayı, feed nəticələri.

### Redis
- Sayğaclar, rate limiting, bəzi feed strukturları.

## Proqram arxitekturası

Instagram məşhur şəkildə **ətrafında servislər olan Python monolithi**-dir. O, microservices fan-out dizaynı DEYİL.

```
       Mobile + Web Clients
              |
       [Edge / Load Balancer]
              |
         [Django Monolith - Gunicorn]
       /         |           \
      v          v            v
 [PgBouncer]  [Memcached]  [Celery workers]
      |
   PostgreSQL (thousands of shards)
      |
   Cassandra (feed/inbox)
      |
   Haystack/TAO (media at Meta)
```

### Feed generasiyası
- **Normal istifadəçilər (az follower):** push modeli — fan-out on write. Post etdiyimdə, şəkilim hər follower-in feed sətrinə yazılır.
- **Məşhurlar (milyonlarla follower):** pull modeli — follower-lər oxuma vaxtı məşhurun post-larını sorğulayır, çünki fan-out on write post başına 100M yazma olacaqdı.
- Hibrid model sosial tətbiqlərdə yaygındır. Twitter və Facebook oxşar bölünmə istifadə edir.

### Stories
- Fərqli giriş pattern-i səbəbindən ayrı sistem (24 saat ömür, ağır oxuma, sonsuz scrolling yoxdur).

### Reels
- Ağır ML-idarə olunan sıralama.
- Ayrı video encoding və sıralama pipeline-ları.

## İnfrastruktur və deploy
- Alışdan sonra Meta-nın öz data mərkəzlərində işləyir (2012-dən əvvəl: AWS).
- Meta-nın daxili deploy alətlərini istifadə edir.
- Edge-də nginx arxasında Gunicorn worker prosesləri.
- DB bağlantı pooling üçün PgBouncer (Python üçün vacibdir, çünki hər proses öz bağlantısını açır).
- Shard miqrasiyaları və failover-lər üçün ağır avtomatlaşdırma.

## Arxitekturanın təkamülü

| İl | Dəyişiklik |
|------|--------|
| 2010 | Tək Django tətbiqi, bir Postgres, AWS EC2 |
| 2011 | Postgres-i shard-lamağa başladı; Memcached təbəqəsini tətbiq etdi |
| 2012 | Snowflake tipli ID-lər; Facebook tərəfindən alındı |
| 2013 | Video; saxlamanın hissələri Facebook-un Haystack-ına köçürüldü |
| 2014 | Inbox/feed üçün Cassandra; tam olaraq Meta data mərkəzlərinə köçdü |
| 2016 | Stories; yeni yüksək miqyaslı oxuma yolu |
| 2018 | Python 3 miqrasiyasına ciddi başladı |
| 2020 | Reels; ağır ML infra |
| 2021+ | Cinder (öz Python runtime); strict modullar; static Python |

## Əsas texniki qərarlar

1. **Postgres daxilində Snowflake ID-lər (2012)** — zərif həll, ayrıca servis yoxdur, bu günə qədər qalır.
2. **Facebook alışından sonra Python-da qalmaq** — Hack-a yenidən yazmaq təzyiqinə qarşı çıxdılar. Sürət və komanda biliyi "daha sürətli dil"i məğlub etdi.
3. **Cinder / static Python** — yenidən yazmaq əvəzinə, Python runtime-ın özünü təkmilləşdirdilər.
4. **Hibrid push/pull feed** — həm normal istifadəçiləri, həm də məşhurları partlamadan idarə edir.
5. **Monolith, microservices deyil** — hətta 2B istifadəçidə belə, əsas Django əsaslı monolith-dir. FAANG "microservices" hekayəsinə çox ziddir.

## Müsahibədə necə istinad etmək

1. **Dil tavanı təyin etmir.** Python 2B istifadəçiyə miqyaslaşdı. PHP Facebook, Wikipedia, Slack-i işlədir. Sizə "PHP miqyaslaşmır" desələr, Instagram, Facebook, Wikipedia-nı göstərin.
2. **Sharding pattern-ləri dil-müstəqildir.** Instagram-ın snowflake ID-ləri və məntiqi shard layout-u demək olar ki, heç bir dəyişiklik olmadan Laravel + MySQL/Postgres-ə tətbiq oluna bilər.
3. **Monolith hörmətlidir.** Yaxşı strukturlaşdırılmış Laravel monolith-i 1000 microservice-dən pis deyil. Instagram bir monolith-in milyardlara xidmət edə biləcəyini sübut edir.
4. **Connection pooling istifadə edin.** PgBouncer Python üçün vacibdir. Laravel istifadəçiləri PgBouncer, ProxySQL və davamlı bağlantıların niyə vacib olduğunu öyrənməlidir.
5. **Memcached/Redis ilə aqressiv cache edin.** Hər səhifə DB-dən əvvəl cache-ə vurur. Laravel-in cache təbəqəsi eyni fikirdir.
6. **Alətlərə investisiya edin.** Static analiz (Psalm, PHPStan) və PHP 8-də strict tiplər Instagram üçün Pyre/LibCST-in etdiyi şeyin PHP ekvivalentidir.
7. **Python üçün Celery = Laravel üçün Queue-lar.** Pattern-lər (gecikdirilmiş job-lar, queue worker-lər vasitəsilə fan-out) eynidir.

## Əlavə oxu üçün
- Instagram Engineering: *Sharding & IDs at Instagram* (2012)
- Instagram Engineering: *Static Analysis at Scale: An Instagram Story*
- Instagram Engineering: *Python at Scale: Strict Modules*
- Instagram Engineering: *Making Instagram.com faster*
- Meta Engineering: *Cinder: Meta's Internal Performance-Oriented CPython Runtime*
- Çıxış: *Instagram's Journey to Python 3* (PyCon)
- Çıxış: *Feed Architecture at Instagram* (müxtəlif konfranslar)
- Kitab fəsli: *High Performance Django*
