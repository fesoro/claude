# Action Items (Lead)

## Məqsəd
Post-mortem-lər yalnız action item-lər həqiqətən edilirsə dəyərlidir. Hər post-mortem bir siyahı ilə bitir; əksər siyahılar 2 həftə ərzində unudulur. Yaxşı action item-lərin intizamı — SMART, izlənən, izləmə ilə — təşkilatın öyrənməsini və ya təkrarlamasını müəyyən edir.

## Saat 3 söz problemi

İncident zamanı və ya ondan dərhal sonra saat 3-də hər kəs dünyanı vəd edir:
- "Retry sistemini tamamilə yenidən yazacağıq"
- "100% test coverage əlavə edəcəyik"
- "Bunun bir daha olmasına heç vaxt icazə verməyəcəyik"

Ertəsi gün reallıq:
- Rewrite 3 aylıq işdir, heç kim prioritet verməyib
- Test coverage hərəkət etmədi
- Eyni sinif incident 2 ay sonra baş verir

Fix: SMART, dar əhatəli və post-mortem sənədindən kənar izlənən action item-lər yaz.

## SMART action item-lər

- **Specific** — tam olaraq nə ediləcək
- **Measurable** — edildi və ya edilmədi, qeyri-müəyyənlik yox
- **Assignable** — bir adlı owner (komanda deyil)
- **Realistic** — həqiqətən tamamlana bilər
- **Time-bound** — deadline-ı var

### Pis action item-lər

- "Test coverage-i yaxşılaşdır" — konkret deyil, ölçülə bilməz
- "Rollback-ları sürətləndir" — konkret deyil
- "@team monitoring əlavə edəcək" — şəxsə təyin olunmayıb
- "Payment sistemini refactor et" — sprint üçün real deyil
- "Tezliklə" — vaxt müəyyən deyil

### Yaxşı action item-lər

- "@alice 2026-04-24 tarixinə qədər pre-2024 discount=[] order-ları əhatə edən unit test əlavə edəcək"
- "@bob 2026-04-22 tarixinə qədər Datadog monitor `checkout-error-rate`-də canary alert threshold-u 5%-dən 1%-ə endirəcək"
- "@carlos 2026-05-15-ə qədər merge hədəfi ilə nightly prod→staging DB sync script əlavə edən PR yaradacaq"
- "@david 2026-05-30-a qədər payments-service üçün ArgoCD-də Argo Rollouts metric-əsaslı abort ilə rollback-ı avtomatlaşdıracaq"

Hər biri: bir şəxs, bir konkret deliverable, bir tarix.

## Owner təyinatı

Item başına bir owner. Co-owner-lər məsuliyyəti sulandırır.

Əgər iş bir neçə adam tələb edirsə, yenə də koordinasiya edən bir owner seç. O şəxs nəticəyə cavabdehdir, mütləq işi edən yeganə şəxs deyil.

Bunlara təyin etmə:
- "Komandaya" — hesabatlılığı yayır
- Post-mortem görüşündə olmayan birinə — razılıq verməyiblər
- "Onlara öyrətmək üçün" ən junior şəxsə — senior dəstəyi olmadan

## Deadline-lar

Deadline-ları prioritet və real tutuma görə qoy:
- Taktiki / cüzi: 1-3 gün
- Standart: 1-2 həftə
- Böyük: 4-6 həftə, alt-tapşırıqlara bölünmüş

Əgər bir item 3+ aydırsa: checkpoint-lərlə daha kiçik hissələrə böl. Checkpoint-siz 3-aylıq item baş verməyəcək.

## Prioritet səviyyələri

| Priority | Meaning | SLA |
|----------|---------|-----|
| P0 | Eyni incident-in təkrarını əngəlləyir | 1 həftə |
| P1 | Ehtimalı əhəmiyyətli dərəcədə azaldır | 2 həftə |
| P2 | Detection və ya mitigation-u yaxşılaşdırır | 4 həftə |
| P3 | Olsa yaxşı olar | Növbəti rüb |

P0-ı həddən artıq istifadə etmə — mənasını itirir. "Bunu düzəltməsək, eyni incident gələn həftə baş verə bilər" üçün saxla.

## Post-mortem-dən kənarda izləmə

**Qayda**: yalnız post-mortem sənədi daxilində izlənən action item-lər unudulur.

Variantlar:
- **Jira / Linear / GitHub Issues**: action item başına bir ticket yarat, `post-mortem-INCIDENT-ID` etiketlə. Təyin et, deadline qoy, statusu izlə.
- **Komanda board-u**: backlog-a first-class ticket kimi əlavə et, sonradan gələnlər kimi deyil.
- **Dashboard**: təşkilat üzrə bütün açıq post-mortem action item-lərinin xülasəsi.

Post-mortem sənədindən geri link ver:
```
Action Items:
1. [JIRA-4521] Add test fixtures for pre-2024 orders — @alice, due 2026-04-24
2. [JIRA-4522] Lower canary threshold — @bob, due 2026-04-22
```

## Follow-up yoxlaması

**4-həftəlik review** planlaşdır: hər action item-i yoxla, edilmiş / davam edir / atılıb kimi işarələ.

- Edilmiş: bağla, qeyd et
- Davam edir: hələ hərəkət edirsə OK, ilişibsə challenge et
- Atılıb: açıq qərar, NİYƏ-ni sənədləşdir, post-mortem-i yenilə

Əsaslandırılmadan atılan action item-lər mədəni qırmızı bayraqdır.

## Preventiv yönümlü, reaktiv deyil

Yaxşı action item-lər yalnız bu konkret instansiyanı deyil, problemin sinifini əngəlləyir.

**Reaktiv (dar)**: "`$order->discount` üçün null check əlavə et"

**Preventiv (geniş)**: "Bu sinif bug-ları tutmaq üçün Psalm/PHPStan konfiqurasiyasına null-check alətini əlavə et"

Preventiv versiya bu bug-u VƏ bənzər hər gələcək bug-u tutur.

## Action item sinifləri

### 1. Kod fix
- Birbaşa bug fix
- Adətən yüksək prioritet, tez
- Bütün hekayə deyil — problem-sinfi fix də lazımdır

### 2. Test coverage
- Bu konkret hal üçün regression test
- Daha dəyərli: bu ümumi pattern-i tutacaq təmsilçi-data test-i

### 3. Monitoring / alerting
- Alert bunu daha tez tuta bilərdi?
- Alert əlavə et, threshold və kanal göstər

### 4. Tooling / process
- Linter, CI addımı, deploy gate bunu tuta bilərdi?
- Çox vaxt ən yüksək leverage

### 5. Documentation / runbook
- Daha yaxşı runbook MTTR-i azalda bilərdimi?
- İndi yaz, yaddaş təzə ikən

### 6. Arxitektura
- Struktur problem varmı?
- Bunlar böyükdür — daha kiçik item-lərə böl, rüblər boyu prioritet ver

## Template

```markdown
## Action Items

| # | Action | Owner | Tracker | Priority | Due |
|---|--------|-------|---------|----------|-----|
| 1 | Add test for pre-2024 discount=[] orders | @alice | [JIRA-4521] | P0 | 2026-04-24 |
| 2 | Lower canary alert threshold 5% → 1% | @bob | [JIRA-4522] | P0 | 2026-04-22 |
| 3 | Automate prod→staging DB sync (monthly) | @carlos | [JIRA-4523] | P1 | 2026-05-15 |
| 4 | Argo Rollouts auto-abort on metric regression | @david | [JIRA-4524] | P1 | 2026-05-30 |
| 5 | Update payments-service runbook | @orkhan | [JIRA-4525] | P2 | 2026-04-30 |
| 6 | Policy: feature-flag all payment flow changes | @eng-leads | [JIRA-4526] | P1 | 2026-04-30 |

**Follow-up review scheduled: 2026-05-15**
```

## Action item-lər üzrə aylıq metriklər

Bütün post-mortem-lərdə izlə:
- Vaxtında tamamlanan action item-lər: %
- İzahsız atılan action item-lər: sayı
- Eyni sinfin təkrarlanan incident-ləri: sayı
- Prioritetə görə tamamlanmaya orta vaxt

Əgər action item-lərin > 30%-i atılır və ya gecikirsə, onları necə əhatələndirdiyində nəsə səhvdir.

## Laravel-xüsusi nümunələr

Laravel incident-indən tipik action item-lər:

- "@alice: Deployment pipeline-a `php artisan horizon:status` yoxlaması əlavə et, status != healthy olsa deploy-u uğursuz et, 2026-04-24-ə qədər [JIRA-4530]"
- "@bob: Production-da `config/telescope.php`-də Telescope söndürmə əlavə et, `.env.prod`-da `TELESCOPE_ENABLED=false` olmasını təmin et, 2026-04-22-ə qədər"
- "@carlos: 'Horizon worker-ləri crash edir' üçün runbook yaz — memory, timeout və OOM pattern-lərini əhatə et, `docs/runbooks/horizon-crash.md`-ə commit et, 2026-04-30-a qədər"
- "@david: Qeyri-production mühitləri üçün AppServiceProvider-də `Model::preventLazyLoading()` əlavə edən PR yarat, test-lərdə N+1 tutmaq üçün, 2026-04-26-ya qədər"

## Anti-pattern-lər

### "Yəqin ki, etməliyik"
Qeyri-müəyyən dil = commit yoxdur = action yoxdur.

### "Hər kəs" və ya "komanda"
Hesabatlılıq yoxdur = heç nə baş vermir.

### "Nəhayət" / "vaxt tapanda"
Heç vaxt düzgün cavab deyil. Real tarix seç və ya əsaslandırma ilə açıq şəkildə təxirə sal.

### "Bütün şeyi yenidən yaz"
Real deyil, ruhdan salır. Aralıq milestone-larla rüblərə böl.

### Action item sadəcə "müzakirə et"-dir
"Növbəti sprint planning-də X-i müzakirə et" iynəni tərpətmir. Müzakirənin nəticəsi konkret action item olmalıdır.

## Müsahibə bucağı

"Post-mortem-lərdən action item-lərin həqiqətən edilməsini necə təmin edirsən?"

Güclü cavab:
- "SMART item-lər: konkret, ölçülə bilən, bir şəxsə təyin edilə bilən, real, vaxt müəyyən."
- "Post-mortem sənədindən kənar — Jira / Linear / GitHub Issues-də — first-class ticket kimi izlənir. Basdırılmır."
- "Prioritetə görə deadline-lar. P0 = 1 həftə. P2 = 4 həftə."
- "Dörd-həftəlik follow-up review: hər item edilmiş, davam edən, və ya səbəblə açıq atılmış."
- "Tamamlanma dərəcəsini komanda metriki kimi izləyirəm. Vaxtında < 70%-dirsə, niyə olduğunu araşdırıram — adətən əhatə səhv idi və ya tutum ayrılmamışdı."
- "Preventiv yönümlü: bu konkret bug-u deyil, problem sinfini əngəlləyən action item-lərə üstünlük verirəm. Linter qaydası əlavə etmək > bir null check düzəltmək."

Bonus hekayə: "Bir şirkətdə post-mortem-lər çox detallı idi, amma action item-lər nadir hallarda edilirdi. Aylıq review görüş əlavə etdik, dashboard-da tamamlanmanı izlədik. Tamamlanma dərəcəsi iki rüb ərzində ~40%-dən ~85%-ə qalxdı. Eyni sinfin təkrarlanan incident-ləri nəzərəçarpacaq dərəcədə azaldı."
