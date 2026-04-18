# Wikipedia (Wikimedia Foundation)

## Ümumi baxış
- Wikipedia dünyanın ən böyük ensiklopediyasıdır, qeyri-kommersiya Wikimedia Foundation (WMF) tərəfindən idarə olunan kollaborativ, çoxdilli, pulsuz kontent layihəsidir.
- Miqyas: yalnız Wikipedia-da ayda təqribən 10+ milyard səhifə baxışı (Wikimedia Statistics dashboard, 2024). Daim qlobal miqyasda top-10 saytdır.
- Əsas tarixi anlar:
  - 2001 — Jimmy Wales və Larry Sanger tərəfindən başladıldı, əvvəlcə Nupedia-nın yan-layihəsi idi.
  - 2002 — UseModWiki PHP-də "Phase II" proqram təminatı ilə əvəz olundu, bu da MediaWiki oldu.
  - 2003 — Wikimedia Foundation yaradıldı; MediaWiki açıq mənbəyə çevrildi.
  - 2014 — MediaWiki-ni sürətləndirmək üçün HHVM deploy olundu.
  - 2018-2019 — PHP özü performans fərqini bağladıqdan sonra HHVM-dən PHP 7-yə geri köçürüldü.
  - 2020-ci illər — MariaDB əksər kluster-lər üçün MySQL-i əvəz edir; job queue, axtarış və edge caching-ə davamlı sərmayə.

## Texnologiya yığını
| Qat | Texnologiya | Niyə |
|-------|-----------|-----|
| Dil | PHP (7.x → 8.x) | MediaWiki PHP-də yazılıb və icma onu bu şəkildə saxlayır. |
| Web framework | MediaWiki (öz) | Müasir framework-lərdən əvvəl yaradıldı; wiki semantikası ilə çox bağlıdır və əvəz edilə bilmir. |
| Əsas DB | MariaDB (əvvəlki MySQL) | Miqyasda sübut olunmuş, replikasiya-dostu, açıq mənbəli, güvəndikləri fork. |
| Cache | Memcached + Varnish (edge) + APCu (in-process) | Qatlı caching 2000 serverli saytın 10B səhifə baxışına xidmət etməsinin ən böyük səbəbidir. |
| Queue/messaging | JobQueue (Redis / Kafka backed) | Parsing, email, thumbnail, refresh link-ləri hamısı async gedir. |
| Search | Elasticsearch (CirrusSearch + Elastica vasitəsilə) | 2014 ətrafında Lucene əsaslı axtarışı əvəz etdi. |
| İnfrastruktur | Öz data mərkəzləri (Ashburn, Dallas, Amsterdam, Singapore, San Francisco) | Xərclərə diqqət yetirən missiyalı qeyri-kommersiya — cloud hesabları qəbul edilə bilməz idi. |
| Monitorinq | Prometheus, Grafana, Icinga, Logstash, ELK | Standart açıq mənbəli observability yığını. |

## Dillər — Nə və niyə

### PHP
MediaWiki PHP-də yazılıb. O, 2002-ci ildə PHP praqmatik web dili olduğu vaxt doğuldu və iki onillik mühəndislik ona tökülüb. MediaWiki komandasının intizamlı kod üslubu var, öz statik analizatoru (MediaWiki plugin-ləri ilə phan) var və Gerrit vasitəsilə güclü kod baxışı var.

HHVM ilə təcrübə var idi (2014-2018). Təqribən 2x performans yaxşılaşması verdi və o vaxt dəyərli idi, amma PHP 7 yetişdikcə — həmin performansın çoxunu daha kiçik baxım yükü və daha böyük töhfəçi bazası ilə verdi — WMF geri köçürüldü. Bu, böyük təşkilatın HHVM-dən PHP-yə *geri* köçdüyü nadir nümunədir.

### JavaScript
Client-tərəfli redaktor (VisualEditor), ResourceLoader (onların asset pipeline-ı), bir çox gadget. Əsas sayt üçün React kimi SPA framework istifadə etmirlər — əksər Wikipedia səhifələri server-rendered HTML-dir progressive enhancement ilə. VisualEditor tək böyük SPA-dır və serverdə Parsoid istifadə edir.

### Node.js
Parsoid (wikitext ilə HTML/DOM arasında ikitərəfli parser) əvvəlcə Node.js-də yazılıb, sonra PHP-yə köçürüldü ki, MediaWiki ilə in-process yaşaya bilsin.

### Lua
İstifadəçi tərəfindən yazılmış şablonlar MediaWiki-yə embed edilmiş Lua skripting engine Scribunto-dan istifadə edir. Bu böyük bir addım idi — oxunmaz wiki şablon makrolarını real kodla əvəz etdi və mürəkkəb infobox-ları baxımlı etdi.

### Python
Analitika, data pipeline-ları, bəzi tooling bot-ları.

## Framework seçimləri — Nə və niyə

**MediaWiki özü framework-dür.** O, köhnə məktəb monolit PHP tətbiqidir və:
- Qlobal `$wg*` konfiqurasiya sistemi (yüzlərlə config dəyişəni).
- Hook-lar — extension-ların əsas hadisələrə qoşulmaq üçün istifadə etdiyi publish/subscribe mexanizmi.
- CSS/JS bundling, modullar və dil-aware minifikasiya üçün `ResourceLoader`.
- `ObjectCache` və `WANObjectCache` abstraksiyaları ki, cache-aside, cache-stampede qoruması və tombstone idarəetməsini verir (ruhən Facebook-un Memcached lease-lərinə çox bənzər).
- Plug-in backend-ləri olan `JobQueue` abstraksiyası.
- Təqdimatı məntiqdən ayıran Skin-lər (Vector, Minerva).

Laravel / Symfony-yə köçmədilər. MediaWiki hər ikisindən əvvəl gəlir və minlərlə wiki tərəfindən istifadə olunan 20 illik, döyüşdə sınanmış, extension ilə zəngin platformanı yenidən yazmağın xərci nəhəng olardı. Bunun əvəzinə tədricən daxildə modernləşdirirlər — Composer, PSR standartları, namespace-lər, dependency injection (öz "ServiceContainer" vasitəsilə) və faydalı olduqda Symfony komponentlərinin hissələrini mənimsəyirlər.

## Verilənlər bazası seçimləri — Nə və niyə

### MariaDB (MySQL idi)
Hər wiki layihəsi (English Wikipedia, German Wikipedia, Commons, Wikidata...) öz verilənlər bazasıdır. Hər DB-nin bir primary + çoxlu replica-sı var, DB kluster-lərinə (s1, s2, s3, ..., plus external storage, parser cache və s. üçün ixtisaslaşmış klusterlər) qruplaşdırılıb.

Niyə MariaDB: WMF adi səbəblərdən (Oracle haqqında açıq idarəçilik narahatlıqları, upstream-ə daha yaxın kontakt, xüsusiyyət paritəsi) MySQL-dən MariaDB-yə köçdü. Replikasiya MariaDB-nin statement-based / row-based kombinasiyasıdır. Onlar topologiyanı kluster başına bir primary və çoxlu read replica ilə işlədirlər və MediaWiki kodunda LoadBalancer lag və çəkiyə görə replica seçir.

### External Storage (ES)
Səhifə mətni versiyaları "External Storage" adlı ayrı blob kluster-də saxlanılır. Mətn blob-ları nəhəng ola bilər və bir dəfə yazılır / nadirən oxunur — onları əsas metadata DB-dən kənarda saxlamaq o DB-ni sürətli və kiçik saxlayır.

### Parser Cache
Hər məqalənin parse olunmuş HTML-ini yaratmaq bahalıdır (şablonlar, parser funksiyaları, Lua çağırışları ilə wikitext → HTML). Parser cache HTML-i məqalə versiyası + parser seçimləri ilə açarlanmış şəkildə saxlayır. O, öz MariaDB kluster-ində yaşayır və tamamilə kritikdir — onsuz hər səhifə baxışı wikitext-i yenidən parse edərdi.

### Wikidata (Blazegraph → yeni SPARQL engine)
Wikidata, strukturlaşdırılmış-data bacı layihəsi, SPARQL endpoint təqdim edir. İllərlə Blazegraph-da (Java RDF triplestore) işlədi, WMF Blazegraph tərk edildiyi üçün ondan köçürür; Qlever və ya bənzərinə əsaslanan yeni backend-ə keçəcəklərini elan etdilər. (Bu davamlı hekayədir — son WMF tech post-larını yoxlayın.)

### Elasticsearch
CirrusSearch extension vasitəsilə bütün wiki-lərdə axtarışı təmin edir. 2014 ətrafında köhnə MySQL full-text + Lucene həllini əvəz etdi. Elasticsearch onlara çoxdilli analizatorlar, relevance tuning və "more like this" sorğularının edilməsi imkanını verir.

## Proqram arxitekturası

- **MediaWiki monolit** PHP-də, təqribən yüzlərlə apache/php-fpm app server-ə horizontal miqyaslanıb.
- **Varnish (və ATS)** edge-də HTTP caching edir. Əksər anonim səhifə baxışları PHP tier-ə heç vaxt çatmır — birbaşa Varnish-dən verilir. Wikipedia-nın kiçik ops komandası ilə miqyaslanmasının əsas səbəbi budur.
- **Memcached** fleet obyekt caching üçün (parser nəticələri, istifadəçi sessiyaları, sessiya datası).
- **JobQueue worker-ləri** Redis/Kafka-dan istehlak edərək link tables yeniləmə, bildiriş email-ləri göndərmə, son dəyişiklikləri hesablama kimi tapşırıqları yerinə yetirir.
- **Elasticsearch kluster** axtarış üçün.
- **Servislər (bir neçəsi)**: Parsoid (indi in-process), RESTBase (REST API facade), EventStreams (hər redaktəni public olaraq server-sent events kimi yayımlayır), ORES (redaktə keyfiyyəti qiymətləndirməsi üçün ML servisi).

```
 [Anonymous user]  --->  [CDN: Varnish / Traffic edge]
                              |
                              |-- cache hit: done
                              |
                              v  (cache miss or logged-in)
                    [Apache/PHP-FPM + MediaWiki]
                              |
    +-------------------------+------------------------+
    |           |             |          |             |
    v           v             v          v             v
 [Memcached] [MariaDB   [Parser     [Elastic-   [JobQueue:
  fleet]      clusters   cache]      search]     Redis/Kafka]
              s1..sN]                             |
                                                  v
                                           [Job runners:
                                            refresh links,
                                            email, thumbs]
```

## İnfrastruktur və deploy
- Öz data mərkəzlərində işləyir, təxminən 2000-3000 fiziki server (ictimai WMF infrastruktur hesabatları).
- AWS yox / GCP yox. Əsas səbəb xərc intizamı (qeyri-kommersiya) və müstəqillikdir — Wikipedia kommersiya cloud-un mərhəmətində olmaq istəmir.
- Konfiqurasiya idarəetməsi: əvvəlcə Puppet, indi bəzi servislər üçün Kubernetes də.
- Deployment: MediaWiki "scap" vasitəsilə deploy olunur — klaster-ə kodu rsync edən və reload tetikleyen Python aləti. Deploy-lar adətən həftədə iki dəfə baş verir (Çərşənbə axşamı + Cümə axşamı "train" modeli) — yeni versiya test wiki-lərinə çıxır, sonra group 1-ə, sonra group 2-yə (English Wikipedia).

## Arxitekturanın təkamülü

1. **2001**: UseModWiki, Perl əsaslı, kontent üçün bir flat fayl. Yük altında tez öldü.
2. **2002**: Phase II → Phase III (MediaWiki). PHP + MySQL + Apache. Caching primitiv.
3. **2004-2008**: Nəhəng artım. Squid (Varnish-in sələfi) edge-ə deploy olundu; Memcached əlavə olundu; MySQL replikasiya topologiyası yetişdi.
4. **2010-2014**: Parsoid, VisualEditor, CirrusSearch / Elasticsearch, RESTBase. MediaWiki Composer ilə modernləşdirildi.
5. **2014-2018**: HHVM dövrü. Standart PHP 5.5-dən təqribən 2x sürət.
6. **2018-2019**: PHP 7-yə geri köçürüldü. Daha kiçik yaddaş izi, kifayət qədər yaxşı performans, daha sadə ops.
7. **2020-ci illər**: Konteynerləşdirmə (Kubernetes-də MediaWiki pilot), faydalı olduqda servis dekompozisiyası (EventStreams, ORES, Mobile Content Service), davamlı kiçik yaxşılaşdırmalar.

## Əsas texniki qərarlar

### 1. Varnish ilə aqressiv edge caching
**Problem**: Qeyri-kommersiya ölçülü ops komandası ilə milyardlarla səhifə baxışına necə xidmət göstərirsən?
**Seçim**: Əksər trafiki anonim oxu trafiki hesab edin və tam render olunmuş HTML-i edge-də uzun TTL ilə cache edin. Məqalə redaktə edildikdə, cache-i invalidasiya etmək üçün mesaj şininə purge hadisəsi gedir.
**Kompromislər**: Purge infrastrukturu çətindir (edge nodelar arasında fan-out, eventual consistency). Daxil olmuş istifadəçilər edge cache-i atlayır.
**Sonra nə oldu**: Edge cache Wikipedia-nın ~2000 serverdə işləyə bilməsinin səbəbidir. Sahib olduqları ən çox leverage-li infra parçasıdır.

### 2. HHVM-ə *doğru* köçmə, sonra PHP 7-yə *geri*
**Problem**: 2013-də PHP 5 yavaş idi. HHVM 2x vəd etdi.
**Seçim**: 2014-də istehsalda HHVM mənimsəmə.
**Kompromislər**: Dəstəkləmək üçün iki runtime (HHVM və PHP). Extension ekosistemi bölünür. İcma töhfəçiləri lokal olaraq HHVM-ə sahib olmaya bilər.
**Sonra nə oldu**: PHP 7 (2015) performansın çoxunu verdi. HHVM 2018-də yalnız Hack-in xeyrinə PHP uyğunluğunu buraxdı. WMF PHP 7-yə geri köçdü — böyük təşkilatın alternativ tutduğu üçün böyük mərci geri qaytardığı nadir hadisə.

### 3. Scribunto (şablonlar üçün Lua)
**Problem**: Mürəkkəb infobox-lar və sitat şablonları dərin iç-içə wikitext makroları kimi tətbiq edilmişdi. Oxunmaz, yavaş, sərhədsiz.
**Seçim**: Lua-nı (sandbox vasitəsilə) embed edin ki, şablonlar real məntiq, limit və testability ilə funksiya kimi yazıla bilsin.
**Kompromislər**: Redaktorlar indi Lua öyrənməlidirlər (azlıq öyrənir); Lua-nı sandbox etmək təhlükəsizləşdirmək üçün başqa səthdir.
**Sonra nə oldu**: Wikimedia-da böyük şablonlar Lua-ya köçürüldü. Parse vaxtları düşdü, etibarlılıq artdı, mürəkkəblik idarə oluna bildi.

### 4. Hər wiki öz verilənlər bazasıdır
**Problem**: Paylaşılan cədvəllər ilə çoxlu kirayəçi qorxulu yuxudur — səs-küylü qonşular, backup mürəkkəbliyi və sorğu performansı ilə ödəyirsən.
**Seçim**: Hər layihə (enwiki, dewiki, commons, wikidata) ayrı MariaDB verilənlər bazasıdır, kluster-lərə qruplaşdırılıb.
**Kompromislər**: Schema miqrasiyaları bir çox verilənlər bazası üzrə icra edilməlidir. Wiki-lər arası sorğular yöndəmsizdir.
**Sonra nə oldu**: Bu o miqyasda ən təmiz çoxlu kirayəçi nümunələrindən biridir. Səs-küylü German Wikipedia sorğusu English Wikipedia-ya zərər verə bilməz.

### 5. Cloud-a getməmək
**Problem**: Hər startup AWS-ə köçürdü — niyə WMF yox?
**Seçim**: Öz data mərkəzlərində qalın. Məntiqi ictimai olaraq dərc edin.
**Kompromislər**: Fiziki infrastruktur, şəbəkə, DCIM, tədarükü sahibi olmaq lazımdır. Yeni tutumu çevirmək daha yavaşdır.
**Sonra nə oldu**: WMF cloud-un onların egress trafik nisbətlərində dəfələrlə daha bahalı olacağını təxmin edir. Web-də kommersiya cloud-da olmayan ən böyük saytlardan biri olaraq qalırlar.

## PHP/Laravel developer üçün dərs
- Cache strategiyanız düzgün olsa, PHP ilə ayda 10B səhifə baxışına xidmət göstərə bilərsiniz. "PHP miqyaslana bilmir" arqumentləri sizi azdırmasın — qeyri-səmərəli arxitektura miqyaslana bilmir, dildən asılı olmayaraq.
- Cache-nizi qatlaşdırın: brauzer, CDN, Varnish, Memcached/Redis, APCu, parser cache. Hər qatın fərqli TTL və invalidasiya hekayəsi var.
- İkitərəfli şablonlaşdırma DSL-i makro dili böyütməkdənsə skripting dili (Scribunto) embed etməyə dəyər.
- Kirayəçi başına verilənlər bazası kirayəçilər ölçücə dəyişdikdə real əməliyyat faydaları verir; Laravel-də çoxlu kirayəçi SaaS üçün bunu düşünün.
- Yenidən yazmadan əvvəl ölçün. HHVM 2014-də dəyərli idi, amma o nöqtədə Java-ya keçməyə çalışsaydılar israf olardı — və nəhayət PHP-yə geri köçürüldülər çünki fərq bağlandı.

## Əlavə oxu üçün
- "Wikipedia: Site internals, configuration, code examples and management issues" (Domas Mituzas, MySQL Users Conference 2007, slides on Wikitech).
- WMF Technical Blog: "How we migrated from HHVM to PHP 7".
- WMF Technical Blog: "Scaling Wikipedia" (various posts).
- Domas Mituzas talks on MySQL and Memcached at scale.
- MediaWiki documentation on Manual:Job_queue, Manual:WANObjectCache, Manual:LoadBalancer.
- Ops handbook: "Wikitech" wiki at wikitech.wikimedia.org (architecture notes, data center topology, cluster inventory).
- Paper: "CirrusSearch: The Migration of Wikipedia Search to Elasticsearch".
- Talk: "MediaWiki Scaling" — WikiConferences, various years.
