# Stack Overflow

## Ümumi baxış
- Stack Overflow proqramçılar üçün sual-cavab saytıdır və Stack Exchange şəbəkəsinin (Math-dan Cooking-ə qədər domen-lərdə 170+ bacı sayt) lövbərdir.
- Miqyas: zirvədə ayda təqribən 100 milyon səhifə baxışı, saniyədə minlərlə request, məşhur olaraq təəccüblü dərəcədə kiçik server fleet-i ilə xidmət göstərilir (2016 ətrafında təxminən 9 IIS web server-i məşhurdur).
- Əsas tarixi anlar:
  - 2008 — Jeff Atwood və Joel Spolsky tərəfindən təsis edildi.
  - 2009 — Stack Exchange şəbəkəsi başladı.
  - 2014 — Nick Craver-in "Stack Overflow: The Hardware" kiçik, amma güclü server fleet-ini ictimai olaraq təsvir edir.
  - 2018 — Windows IIS-dən Linux-a miqrasiya başlayır (Nick Craver-in ictimai post-ları).
  - 2021 — Prosus tərəfindən $1.8B-ə alındı.

## Texnologiya yığını
| Qat | Texnologiya | Niyə |
|-------|-----------|-----|
| Dil | C# (.NET) | Joel və Jeff Microsoft dünyasından gəldi; Stack onların bildikləri üzərində quruldu. |
| Web framework | ASP.NET MVC (sonra ASP.NET Core) | O vaxt standart Microsoft yığını. |
| Əsas DB | Microsoft SQL Server | Relational, güclü planner, komanda tərəfindən yaxşı tanınır. |
| Cache | Redis, in-process cache | Paylaşılan cache üçün Redis; server başına lokal cache. |
| Queue/messaging | Məhdud — işin çoxu inline və ya kiçik worker-lərdə baş verir | Çox async-a ehtiyac duymurlar; yaxşı perf bunu lazımsız edir. |
| Search | Elasticsearch | 2011 ətrafında gətirildi; relevance və sürət üçün SQL full-text-i əvəz etdi. |
| İnfrastruktur | İki data mərkəzində (NY + Denver) öz aparatı | Kiçik, güclü fleet-də işləmək üçün məşhur. |
| Monitorinq | Opserver (öz), Bosun, sonra Prometheus | Nick Craver bunların çoxunu yazdı. |

## Dillər — Nə və niyə

### C# (.NET)
Stack Overflow .NET-də işləyir. Təsisçilər (Atwood, Spolsky) Microsoft developer dünyasından gəldi — Joel Excel üzrə Microsoft program manager idi; Jeff görkəmli .NET blogger idi. Saytı ASP.NET-də qurmaq təbii idi və bu, müəyyənedici seçim olub:
- Statik tipli və kompilyasiya olunmuş — PHP/Ruby-nin işlədəcəyi xəta sinifləri tutur.
- Güclü tooling (Visual Studio, Rider, etibarlı profiler-lər).
- Request üzrə performans mükəmməldir; fleet-in niyə bu qədər kiçik olmasının böyük səbəbidir.

Komanda son illərdə .NET Core / .NET 5+ -ə köçdü (Nick Craver + başqalarının post-ları irəli hərəkət edərkən illərin legacy ilə məşğul olmağı təsvir edir).

### JavaScript
Front-end: server-rendered HTML plus progressive jQuery (tarixən) və vanilla JS. Əsas sayt üçün SPA framework mənimsəmədilər — səhifələri əsasən server-rendered-dir.

### Go / digər
Spesifik infrastruktur üçün kiçik miqdarda (load balancer köməkçiləri, bəzi command-line tooling).

## Framework seçimləri — Nə və niyə

**ASP.NET MVC (sonra ASP.NET Core).** Framework flip-flop hekayəsi deyil — Microsoft web yığınında qaldılar və onu təkamül etdirdilər.

**Dapper** — Sam Saffron və Marc Gravell tərəfindən Stack Overflow-da yazılmış mikro-ORM. İsti səhifələr üçün Entity Framework çox yavaş olduqda (obyekt materialization əlavə yükü), onlar POCO-lara mapping olunan əl ilə tənzimlənmiş SQL-i minimum əlavə yüklə etmək üçün Dapper yazdılar. Açıq mənbəli buraxıldı və .NET icmasında geniş istifadə olunur.

**MiniProfiler** — həmçinin Stack Overflow-dan. Hər səhifənin yalnız dev küncündə SQL sorğularını, cache hit-lərini və zaman ölçmələrini göstərən drop-in profiler. N+1 sorğularını və yavaş endpoint-ləri aşkar etməyi asan etdi.

**StackExchange.Redis** — .NET üçün öz Redis client-ləri. Standart `ServiceStack.Redis` client kifayət qədər yaxşı deyildi; öz-lərini yazdılar və açıq mənbəyə çevirdilər.

Nümunə: ekosistem aləti onların çubuğuna çatmasa, öz-lərini yazdılar və paylaşdılar.

## Verilənlər bazası seçimləri — Nə və niyə

### SQL Server
Əsas store. Hər Stack Exchange instance üçün bir böyük SQL Server (Stack Overflow özü bir SQL Server verilənlər bazasıdır, plus log-lar kimi şeylər üçün bir neçə köməkçi DB).

Niyə bir böyük verilənlər bazası: oxu yolu ağır şəkildə cache-lənir (bir çox səhifə Redis-də cache-lənmiş HTML və ya tam render olunmuş output cache-dir) və yazmalar kiçik hissədir. Kifayət qədər RAM-a sahib yaxşı tənzimlənmiş SQL Server iş yükünü yaxşı idarə edir.

Əsas detal: mikro-shard etmirlər. Düzgün indekslər və yaddaşla tək primary SQL Server saatda milyonlarla sorğu edə bilər. Nick Craver-in post-ları konkret rəqəmlərlə doludur — məsələn, "SQL server-lərin X GB RAM-ı, Y nüvəsi var, working set yaddaşa sığır."

### Redis
Paylanmış cache üçün (in-process L1 cache arxasında L2 cache), sessiya saxlama, tag engine output-ları və bəzi ephemeral sayğaclar.

### Elasticsearch
Axtarış üçün. SQL full-text relevance və ya sürət üçün kifayət qədər yaxşı deyildi; 2011 ətrafında axtarışı Elasticsearch-ə köçürdülər və illər boyu tənzimlədilər.

### Tag Engine
Bütün tag-ları, onların kəsişmələrini və sayğaclarını bilən xüsusi in-memory servis (C#-də). "PHP və MySQL ilə tag olunmuş amma Laravel istisna olmaqla" kimi sorğulara bitmap-ları əvvəlcədən hesablayaraq ümumi sorğu engine-dən daha sürətli cavab verir.

## Proqram arxitekturası

**IIS-də (sonra Kestrel) işləyən C#-də monolit.**

- Bir böyük ASP.NET app Stack Overflow saytına xidmət göstərir.
- Digər Stack Exchange saytları fərqli config/DB ilə eyni kodun öz deploy-larında işləyir.
- Bir neçə yan servis: Tag Engine, Elasticsearch kluster, Redis kluster, log-lar servisi.

```
 [User]
   |
   v
 [HAProxy (on BGP anycast for the two data centers)]
   |
   v
 [IIS / Kestrel web servers — about 9 historically]
   |
   +-- [Redis cluster]
   +-- [SQL Server primary + replicas]
   +-- [Elasticsearch cluster]
   +-- [Tag Engine service]
   +-- [Logs service (custom, eventually BOSUN / Opserver feeding from it)]
```

Qeyd edək ki, bu top-100 web saytı üçün kiçik arxitekturadır. Bütün məqsəd odur — diqqətli mühəndislik horizontal yayılmanı döyür.

## İnfrastruktur və deploy
- İki data mərkəzində öz aparatı: New York (əsas) və Colorado (failover). Dell serverlər.
- Yük balansı üçün HAProxy; iki DC arasında BGP anycast.
- Deployment: tarixən TeamCity + PowerShell script-ləri vasitəsilə ki, binaries-i fleet-ə kopyalayır və cache-ləri isidir. Nick Craver-in "How We Do Deployment" post-u qızıl-standart hal tədqiqatıdır.
- Deploy-lar uçdan-uca dəqiqələr çəkir. Lazım olduqda gündə bir çox, amma davamlı deployment kultu deyil.

## Arxitekturanın təkamülü

1. **2008-2011**: Tək SQL Server, bir neçə IIS qutusu, SQL full-text axtarış. Hər maşını böyüdərək miqyaslanma.
2. **2011-2014**: Axtarış üçün Elasticsearch. Dapper isti yollarda LINQ-to-SQL/EF-i əvəz edir. Tag Engine qurulur.
3. **2014-2018**: Fleet əsas sayt üçün tək rəqəmli web server-də stabilləşir. Nick Craver-in ictimai mühəndislik post-ları sənayeyi öyrədir. Redis deployment yetişir.
4. **2018-2022**: Windows + IIS-dən Linux + .NET Core-a miqrasiya. Nginx/HAProxy arxasında Kestrel. Bəzi servislər üçün konteynerlər.
5. **2022+**: Prosus sahibliyi altında, AI/Overflow AI-ə sərmayələr, yenilənmiş infra, amma arxitektura nüvəsi eyni qalır.

## Əsas texniki qərarlar

### 1. Monolit + bir böyük DB + ağır caching
**Problem**: Top-100 web sayt miqyası adətən paylanmış sistemləri, shardlamanı, microservis-ləri nəzərdə tutur.
**Seçim**: Sayt başına bir monolit və bir böyük (yaxşı tənzimlənmiş) SQL Server ilə qalın. Qatlı caching (in-process L1, Redis L2) aqressiv istifadə edin. Böyük aparat alın.
**Kompromislər**: DB yazmalarını horizontal miqyaslana bilməzsiniz; SQL Server nə vaxtsa darboğaz olsa, çətinliyiniz var. Vertical miqyasın limitləri var.
**Sonra nə oldu**: Həmin limitləri heç vaxt vurmadılar — çünki oxu yolu əsasən cache-dir və yazma yolu idarə olunandır. "Monolit + böyük dəmir" nəhəng oxunma yüklü saytlar üçün işlədiyinə sənaye sübutuna çevrildi.

### 2. Dapper yazmaq
**Problem**: Entity Framework isti səhifələr üçün çox yavaş idi (obyekt materialization əlavə yükü, sorğu tərcüməsi).
**Seçim**: SQL nəticələrini cache-lənmiş IL ilə obyektlərə map edən mikro-ORM yazın. Tracking yoxdur, lazy loading yoxdur, minimum əlavə yük.
**Kompromislər**: Hər yerdə xam SQL — sorğuları əllə yazırsınız. Gözəl dəyişiklik izləmə yoxdur.
**Sonra nə oldu**: Dapper ən çox istifadə olunan .NET kitabxanalarından biri oldu. Stack Overflow saytı isti yollarda Dapper-powered-dir; admin / CRUD üçün EF.

### 3. Xüsusi servis kimi Tag Engine
**Problem**: "(python OR go) NOT deprecated ilə tag olunmuş ən yeni suallar" kimi sorğular ümumi SQL engine-də yavaşdır, xüsusilə pagination və sayğac-larla.
**Seçim**: Tag datasını C#-də xüsusi servisdə yaddaşda saxlayın. Set əməliyyatları üçün bitmap-bənzər strukturlar istifadə edin.
**Kompromislər**: Saxlamaq üçün əlavə servis; DB ilə sinxron saxlanmalıdır.
**Sonra nə oldu**: Tag-əsaslı baxma sürətli qaldı; bu "isti sorğunuzu müəyyən edin, onun üçün xüsusi hazırlanmış servis qurun" üçün dərslik nümunəsidir.

### 4. Windows-da qalmaq (sonra miqrasiya)
**Problem**: Hər kəs illər əvvəl Linux-a köçmüşdü. Windows lisenziyası bahalıdır; əksər .NET developer-ləri .NET Core-a keçirdi.
**Seçim**: Cost/benefit aydın şəkildə çevrilənə qədər Windows + IIS-də qalın, sonra .NET Core və Kestrel yetkinləşdikdən sonra metodiki şəkildə miqrasiya edin.
**Kompromislər**: Windows illəri ərzində yüksək infra xərcləri; bəzi icma trendlərindən geri qalır.
**Sonra nə oldu**: Miqrasiya illər və bir neçə mühəndislik post-u aldı sənədləşdirmək üçün. Dramatik yenidən yazma deyil, sadəcə inkremental irəliləyiş.

### 5. Öz aparatında işləmək
**Problem**: Cloud zirvələri elastik şəkildə uda bilər, amma Stack Overflow trafiki son dərəcə proqnozlaşdırılandır.
**Seçim**: Öz Dell server-lərində iki data mərkəzində işləyin. Auto-scale-ə heç vaxt ehtiyac duymamaq üçün kifayət qədər headroom.
**Kompromislər**: Tutum planlaması, aparat həyat dövrünə sahib olmaq lazımdır. Infra servislərinin cloud "pulsuz tier"-i yoxdur.
**Sonra nə oldu**: Onların miqyasında hər hansı cloud ekvivalentindən request üzrə çox ucuz. Nick Craver-in aparat spec-ləri + xərclərlə post-ları sənayedə cloud əleyhinə arqumentlər oldu.

## PHP/Laravel developer üçün dərs
- Miqyaslamadan əvvəl ölçün. Stack Overflow modeli deyir ki, əksər app-ların onlara satılan paylanmış arxitekturaya ehtiyacı yoxdur. Yaxşı tənzimlənmiş Laravel monolit Redis cache və yaxşı aparatda böyük MySQL/Postgres ilə çox uzağa gedir.
- İsti yollar üçün nazik SQL qatı yazın (və ya mənimsəyin). PHP-də bu, trafikə hakim olan endpoint-lərin 5%-i üçün Eloquent-i Doctrine DBAL və ya xam PDO ilə əvəz etmək ola bilər.
- Dev-də MiniProfiler-üslubu overlay qurun. Laravel Debugbar yaxındır; onu genişləndirməyi və ya staging-də request üzrə sorğu sayğaclarını aktivləşdirməyi nəzərdən keçirin.
- Axtarış və tag-bənzər sorğular üçün hər şeyi SQL-ə basmayın. Elasticsearch və ya kiçik in-memory servis xüsusi sorğu formaları üçün SQL performansını üstələyə bilər.
- Infra-nı mümkün qədər sadə saxlayın. İki güclü DB server mürəkkəblik lazım olduğunu sübut edənə qədər shardlanmış/paylanmış pozulmuş sistemi döyür.

## Əlavə oxu üçün
- Blog series: Nick Craver's "Stack Overflow: The Architecture" (2016 update), "Stack Overflow: The Hardware" (2016), "Stack Overflow: How We Do Deployment" (2016), and follow-up posts on the Linux migration. On nickcraver.com and the Stack Overflow engineering blog.
- Blog: Marc Gravell on Dapper design, perf tuning.
- Blog: Sam Saffron on perf engineering at Stack Overflow.
- Talk: "Stack Overflow: A Technical Deconstruction" — Nick Craver at various conferences.
- Tool/library: Dapper, MiniProfiler, StackExchange.Redis, Opserver, Bosun (all open-sourced by Stack Exchange).
- Blog: Jeff Atwood's "Coding Horror" early posts on Stack Overflow architecture.
- Post: "Why Does Stack Overflow Use So Few Servers?" — various analyses and Nick Craver's reply.
- Post: "Farewell, Windows" (internal Stack Overflow engineering retrospective on Linux migration).
