# GitHub (Lead)

## Ümumi baxış
- GitHub ən böyük kod hosting və developer əməkdaşlıq platformasıdır — Git repo-ları, pull request-lər, issue-lar, Actions (CI/CD), Packages, Copilot (AI cüt proqramlaşdırma) və Marketplace.
- Miqyas: 100 milyondan çox developer hesabı (GitHub "Octoverse" hesabatları, 2023), yüz milyonlarla repo, petabaytlarla Git datası.
- Əsas tarixi anlar:
  - 2008 — Tom Preston-Werner, Chris Wanstrath, PJ Hyett və Scott Chacon tərəfindən təsis edildi. Ruby on Rails üzərində quruldu.
  - 2011 — Peter Deng, Scott Chacon və başqaları Git storage-i miqyaslandırır ("smart HTTP" və xüsusi Git server proqramı).
  - 2013 — Atom redaktoru buraxıldı (sonra 2022-də bağlandı, amma Electron-u yaratdı).
  - 2018 — Microsoft GitHub-ı $7.5B-ə alır.
  - 2019 — GitHub Actions buraxıldı (daxili CI/CD).
  - 2021 — GitHub Copilot buraxıldı (OpenAI Codex ilə AI cüt proqramçı).
  - 2022-2024 — Codespaces (cloud dev mühitləri), daha dərin AI inteqrasiyaları.

## Texnologiya yığını
| Qat | Texnologiya | Niyə |
|-------|-----------|-----|
| Dil | Ruby (əsas), Go (Git/storage/infra), C (Git), TypeScript (frontend) | Məhsul sürəti üçün Ruby; infrastruktur üçün Go; Git-in özünün yaşadığı yerdə C. |
| Web framework | Ruby on Rails | Rails üzərində təsis edildi; bu gün də Rails-dir. |
| Əsas DB | MySQL, Vitess-bənzər nümunələrlə shardlanıb | Miqyasda sübut olunmuş. |
| Cache | Memcached, Redis | Obyektlər üçün Memcached; queue-lar, rate limit-lər, real-time xüsusiyyətlər üçün Redis. |
| Queue/messaging | Resque (əvvəlcə GitHub-da yazıldı), indi Sidekiq / Actions-spesifik queue-lar | Resque GitHub-dan gəldi. |
| Search | Elasticsearch (müxtəlif zamanlarda issue/PR/kod axtarışı üçün) + kod axtarışı üçün xüsusi "Blackbird" (Rust) | Standart + ev hazırı. |
| İnfrastruktur | Tarixən öz data mərkəzləri, indi əsasən Azure-da (Microsoft alışından sonra) | Alışdan sonra Azure istiqamətləndirilməsi. |
| Monitorinq | DataDog, xüsusi, plus GitHub-ın daxili observability-si | Müasir SRE yığını. |

## Dillər — Nə və niyə

### Ruby
Rails monolit GitHub-ın əsas tətbiqidir. Dünyanın ən böyük, ən köhnə və ən çox töhfə edilmiş Rails app-larından biridir. GitHub mühəndislik təşkilatının güclü Ruby/Rails bacarıqları var və Rails-ə upstream-də töhfə verir (Eileen Uchitelle Rails core və multi-DB dəstəyi üzərində işləyir; GitHub Ruby Central Foundation-ı sponsor etdi).

### Go
Git-ə üz tutan servislər üçün istifadə olunur:
- **Spokes** — GitHub-ın xüsusi Git storage və replikasiya sistemi. Əvvəlki sistemi ("DGit") əvəz etdi. Hər repo-nu konsensusla çoxlu storage node üzrə replikasiya edir.
- **GLB** — GitHub Load Balancer, qismən Go-da.
- Actions infrastrukturunun hissələri.
- Observability və platform işi üçün bir çox "infra Go" servisləri.

### C / C++
Git-in özü C-dədir. GitHub performans üçün patch-ləri ilə (partial clones, filter-branches və s.) öz Git forkunu saxlayır və bəzilərini upstream-ə basır.

### Rust
**Blackbird** — GitHub-ın yeni kod axtarış engine-i, əvvəlki Elasticsearch əsaslı kod axtarışını əvəz edir. Rust-da yazılıb. İctimai blog post: "The technology behind GitHub's new code search."

### TypeScript / JavaScript
Frontend əsasən Rails-dən server-rendered HTML-dir progressive JavaScript ilə. Bəzi xüsusiyyətlər React istifadə edir (məsələn, Issues-da, PR-də yeni UI). Öz web komponentlər kitabxanasını yazdılar.

### Digər
Data / ML üçün Python. Tarixən bəzi spesifik servislər üçün Elixir. Alışdan sonra Microsoft-inteqrasiya hissələri üçün bəzi C#.

## Framework seçimləri — Nə və niyə

**Ruby on Rails** əsas framework-dür. GitHub-ın monoliti (danışıqda "github/github") böyük Rails app-dir:
- Repo-lar, issue-lar, PR-lər, wiki-lər, layihələr, təşkilatlar, billing, marketplace üçün web UI-ı idarə edir.
- REST API-i (`api.github.com`) və GraphQL API-in hissələrini təqdim edir (GraphQL API öz schema qatı ilə böyük sərmayədir).
- Resque/Sidekiq-də arxa plan job-ları webhook çatdırılması, fan-out və s. idarə edir.

**GraphQL** — GitHub-ın API v4-ü GraphQL-dir. Onlar icma ilə birlikdə graphql-ruby kitabxana ekosistemini yazdılar.

GitHub-da doğulan əlavə framework və kitabxanalar:
- **Resque** — Redis-backed job queue, 2009-da Chris Wanstrath tərəfindən yaradıldı. Sonra Sidekiq-ə (Mike Perham tərəfindən) təkamül etdi, bu gün standartdır.
- **Scientist** — production-da təcrübələr işlətmək üçün kitabxana (köhnə kod vs yeni kod real trafikdə, nəticələri müqayisə edir). Açıq mənbəyə çevrildi.
- **Hubot** — chatops bot framework.
- **Atom** / **Electron** — VS Code və saysız-hesabsız başqalarının istifadə etdiyi crossplatform desktop app framework.

## Verilənlər bazası seçimləri — Nə və niyə

### MySQL
Rails monolit üçün əsas store. İstifadəçilər, repo-lar (metadata), issue-lar, PR-lər, şərhlər, təşkilatlar, billing — hamısı MySQL-də.

İllər ərzində müxtəlif strategiyalarla shardlanıb:
- Funksional shardlama (fərqli domen-lər üçün fərqli kluster-lər).
- Ən böyük cədvəllər üçün horizontal shardlama.
- Resharding və orchestration üçün Vitess-üslubu tooling mənimsəməsi.

### Git storage (MySQL deyil)
Faktiki Git obyekt datası (commits, trees, blobs, packs) paylanmış storage sistemində — "Spokes" (Go servisi) — çoxlu storage node üzrə replikasiya ilə yaşayır. MySQL DB repo-lar *haqqında* metadata saxlayır; repo məzmunu Spokes-də yaşayır.

Spokes-dən əvvəl GitHub "DGit"-i işlədirdi, onun öz limitləri var idi. Spokes onu daha güclü consistency və daha asan əməliyyatlar ilə əvəz etmək üçün dizayn edilib.

### Memcached
Ağır obyekt caching — istifadəçi obyektləri, repo metadatası, icazə yoxlamaları.

### Redis
Queue-lar (Resque/Sidekiq), rate limiter-lər, real-time xüsusiyyətlər (presence, bəzi canlı yeniləmələr), ephemeral state.

### Elasticsearch
Issues/PR/diskusiya axtarışı, bildiriş axtarışı və (tarixən) kod axtarışı üçün.

### Blackbird (Rust, GitHub-ın öz)
Xüsusi hazırlanmış kod axtarış engine, 2023 ətrafında göndərildi. Kod token-lərinə, repo-şüurluluğa və icazə filtrləməsinə optimallaşdırılmış xüsusi indeks strukturu istifadə edir. Miqyas və relevance ilə mübarizə aparan əvvəlki Elasticsearch əsaslı kod axtarışını əvəz etdi.

## Proqram arxitekturası

GitHub ətrafında **ixtisaslaşmış servislər** olan **Rails monolit**-dir:

- **github/github (Rails)** — əsas app: UI, REST API, GraphQL API, biznes məntiqi, job-lar.
- **Spokes (Go)** — Git storage və replikasiya.
- **GLB (Go)** — GitHub Load Balancer.
- **Actions infrastrukturu** — workflow orchestration, runner idarəetməsi; Go daxil olmaqla qarışıq dillər.
- **Packages** — npm, Maven, NuGet, Docker və s. üçün artefakt registry.
- **Pages** — repo-lar üçün statik sayt hostingi.
- **Copilot service** — AI modelləri üçün proxy; IDE-lər və github.com ilə inteqrasiya olunur.
- **Blackbird (Rust)** — kod axtarışı.
- **Bildirişlər pipeline-ı** — @mention-lər, PR yeniləmələri və s. üçün fan-out.

```
 [User]
    |
    v
 [GLB (GitHub Load Balancer)]
    |
    v
 [Rails monolith — github/github]
    |
    +--> [MySQL (sharded)]
    +--> [Memcached]
    +--> [Redis]
    +--> [Elasticsearch (issues/PR search)]
    +--> [Blackbird (code search, Rust)]
    +--> [Spokes (Git storage, Go)] ---- Git packs replicated across storage nodes
    +--> [Sidekiq / Resque workers]
    +--> [Actions orchestrator] -- runners (ephemeral VMs/containers)
    +--> [Copilot proxy] -- external AI model providers
```

## İnfrastruktur və deploy
- Tarixən öz data mərkəzləri. Microsoft alışından sonra artan Azure istifadəsi. Codespaces Azure-da işləyir.
- Monolit üçün gündə bir çox deploy. Feature flag-lar (Flipper — başqa Rails dev-dən, amma GitHub-da çox istifadə olunur) hər yerdədir.
- CI self-hosted Actions workflow-larıdır. "Dogfooding" — GitHub GitHub ilə GitHub-ı qurur.

## Arxitekturanın təkamülü

1. **2008-2012**: Klassik Rails + MySQL. Git bir neçə güclü serverdən xidmət göstərilir.
2. **2012-2015**: İlk paylanmış Git storage (DGit). Job-lar üçün Resque. Ağır artım; API v3 REST.
3. **2015-2018**: GraphQL API v4. Daha çox shardlama. Issues, Projects və müasir Rails UI-ı yetişir.
4. **2018**: Microsoft tərəfindən alınır; mədəni və infra inteqrasiyası başlayır, amma Rails monolit qalır.
5. **2019-2021**: Actions buraxıldı. Copilot buraxıldı. Codespaces.
6. **2022-2024**: Spokes DGit-i əvəz edir. Blackbird köhnə kod axtarışını əvəz edir. AI xüsusiyyətləri dərinləşir.

## Əsas texniki qərarlar

### 1. GitHub miqyasında Rails-də qalmaq
**Problem**: GitHub miqyasında 15 yaşlı Rails monolit "Go/Elixir/Rust-da yenidən yazaq" üçün cazibədar hədəfdir.
**Seçim**: Rails-i saxlayın. Modullaşdırmaya (packwerk-üslubu sərhədlər), multi-DB dəstəyinə, Sorbet-bənzər tiplənmə cəhdlərinə (kəşflər dərc etdilər) sərmayə qoyun. Yalnız Rails yanlış alət olduğu yerlərdə ixtisaslaşmış servisləri (Spokes, Blackbird) ayırın.
**Kompromislər**: Bu ölçüdə kod bazasında Rails yeniləmələri çoxaylıq səylərdir. Request üzrə performans tavanları Go/Rust-dan aşağıdır.
**Sonra nə oldu**: GitHub Rails olaraq qalır, intizamlı olsanız Rails-in qlobal infra-nı təmin edə biləcəyini sübut edir (Shopify ilə yanaşı).

### 2. Spokes — Go-da xüsusi Git storage
**Problem**: Milyonlarla repo-nu etibarlı replikasiya və failover ilə hostlamaq heç bir hazır sistemin həll etmədiyi problemdir. Sələf (DGit) məhdudiyyətlərə sahib idi.
**Seçim**: Go-da Spokes yazın — repo yerləşdirmə və failover üçün öz konsensusu ilə xüsusi hazırlanmış Git storage + replikasiya sistemi.
**Kompromislər**: Nəhəng mühəndislik sərmayəsi; saxlamaq üçün başqa Git-bitişik kod bazası.
**Sonra nə oldu**: Daha yaxşı əlçatanlıq, daha asan tutum ops. Stateful storage-i miqyasda dizayn edirsinizsə, Spokes tələb olunan oxudur.

### 3. Blackbird — Rust-da kod axtarışını yenidən yazmaq
**Problem**: Elasticsearch-də kod axtarışı indeks ölçüsü, relevance və icazə filtrləməsi limitlərinə çatdı. Developer-lər şikayət etdi.
**Seçim**: Rust-da kod-spesifik axtarış engine qurun, kod token-lərini başa düşən (camelCase, snake_case, identifier sərhədləri), incə-qranul repo icazələrinə hörmət edən və petabaytlara miqyaslanan indeks ilə.
**Kompromislər**: İllərlə mühəndislik səyi; işləyən-amma-ağrılı sistemi əvəz edir.
**Sonra nə oldu**: Kod axtarışı dramatik şəkildə sürətli və daha relevantdır. İctimai blog post "The technology behind GitHub's new code search" inverted-index dizaynını izah edir.

### 4. Scientist — təcrübə əsaslı refaktorinq
**Problem**: Kritik kod yolunu yenidən yazanda downtime olmadan yeni kodun ekvivalent olduğunu necə sübut edirsiniz?
**Seçim**: Scientist kitabxanası — köhnə və yeni kodu real production trafikində paralel işlədin, köhnə nəticəni qaytarın və uyğunsuzluqları log edin. Metrik təmiz olduqda yeni yola çevirin.
**Kompromislər**: Təcrübə zamanı əlavə yük; yan təsirlərin diqqətli idarəetməsi.
**Sonra nə oldu**: GitHub-a böyük hissələri (icazələr engine, push pipeline) təhlükəsiz yenidən yazmağa imkan verdi. Kitabxana açıq mənbəyə çevrildi və geni mənimsənildi.

### 5. Git-in özünə davamlı sərmayə
**Problem**: GitHub-ın biznesi Git-in nəhəng repo-lar və monorepo-lar (Linux kernel, Chromium, Microsoft-un öz monorepo-su) üçün sürətli olmasından asılıdır.
**Seçim**: Git-ə upstream-də ağır töhfə verin. Partial clones, `git sparse-checkout`, multi-pack index-lər, commit-graph və reftable (yeni ref backend) sponsor edin.
**Kompromislər**: Yavaş upstream qəbul dövrləri; bəzi patch-lər əvvəlcə GitHub-spesifik forklardır.
**Sonra nə oldu**: Git bir neçə il əvvəl mümkün olmayacaq repo-lara miqyaslanır.

## Müsahibədə necə istinad etmək
- 15 yaşlı Rails/Laravel monolit hələ də rəqabət üstünlüyü ola bilər — davamlı yeniləsəniz, əsas framework versiyalarından çox geri qalmasanız və zamanla modullaşdırsanız.
- Stateful paylanmış sistemlər üçün (Git storage kimi) aşağı səviyyəli dildə (Go, Rust) servis qaldırın. Rails/Laravel job-da replikasiya etməyə çalışmayın.
- Təcrübə əsaslı refaktorinqlər istifadə edin. Scientist-in PHP portu (github/scientist-php) var, onu Laravel-də köhnə və yeni kod yollarını real trafikdə müqayisə etmək üçün birbaşa istifadə edə bilərsiniz.
- Framework səviyyəsində feature flag-lar — Rails üçün Flipper, Laravel üçün Laravel Pennant. Kiçik göndərin, hər şeyi flag-layın, ölçün.
- Yalnız ölçmə tələb etdikdə ixtisaslaşdırın. Hətta GitHub Blackbird-ı yalnız Elasticsearch ehtiyacı sübut edilmiş şəkildə ödəyə bilmədikdən sonra qurdu.

## Əlavə oxu üçün
- Blog: "How GitHub uses GitHub to be productive" — GitHub engineering.
- Blog: "The technology behind GitHub's new code search" (Blackbird).
- Blog: "Introducing Spokes" / "Scaling GitHub's Git infrastructure" (storage posts).
- Talk: "Move Fast and Fix Things" — GitHub's use of Scientist.
- Open source: Resque, Scientist, Hubot, Atom/Electron, many at github.com/github.
- Talk: "How GitHub Designs for Developer Experience" — various conferences.
- Blog: Eileen Uchitelle on Rails multi-database support and GitHub's use of it.
- Blog: "Upgrading GitHub to Ruby 3.x" — engineering posts about long Rails/Ruby upgrades.
- Book: "Pro Git" — Scott Chacon (co-founder), background on Git itself.
- Paper / talks: Kyle Daigle and others on Actions architecture.
