# Technical Debt Management (Lead ⭐⭐⭐⭐)

## İcmal
Technical Debt — qısamüddətli həllər seçməyin uzunmüddətli texniki xərcidir. Ward Cunningham tərəfindən 1992-ci ildə maliyyə metaforası kimi təqdim edilmişdir. Interview-da bu mövzu sizin böyük codebase-ləri idarə etmə, product vs engineering balansı qurmaq, team-ə debt-i izah etmək, biznes case qura bilmək bacarığını ölçür. Lead səviyyəsi üçün texniki biliklə yanaşı kommunikasiya və prioritetləşdirmə bacarığı da vacibdir.

## Niyə Vacibdir
Texniki borc getdikcə velocity-ni yavaşladır, bug sayını artırır, developer frustration yaradır, yeni mühəndislərin onboarding sürecini çətinləşdirir. Lakin sıfır texniki borc da mümkün deyil — bəzən qəsdən borc götürülür (startupda sürət üçün). Lead mühəndis debt-i idarə etməyi, prioritetləşdirməyi, business case qurub management-ə izah etməyi bacarmalıdır. "Bu refactoring-i etsək deployment frequency 2x artacaq" — bu dil management anlayır.

## Əsas Anlayışlar

- **Deliberate vs Inadvertent Debt**: Deliberate — qəsdən götürülmüş (sürət üçün bilinərək shortcuts). Inadvertent — bilmədən, bilgisizlik, standartlar yoxdur, ya da kod üzərindən vaxt keçib köhnəlib.

- **Reckless vs Prudent Debt** (Fowler 2x2 matrix): Reckless+Deliberate — "design etməyə vaxtımız yoxdu" (sərfəsiz). Prudent+Deliberate — "ship now, refactor later" (düşünülmüş, qəsdən). Reckless+Inadvertent — "Object-Oriented-in nə olduğunu bilmirdik" (bilgisizlik). Prudent+Inadvertent — "Onu şimdi görürük, əvvəlcə best solution-u bilmirdik" (öyrənmə prosesi).

- **Interest payment**: Debt üzərindəki faiz — hər bug fix, hər yeni feature yazarkən developer əvvəlcə debt ilə mübarizə aparır. Debt ikiqatlanır, bug fix üçün lazım olan vaxt artır. Compound interest effekti.

- **Code smell**: Debt-in simptomu. Long method (200+ sətir), Large class (God Object), Duplicate code (DRY pozulması), Feature envy (bir class başqa class-ın metodlarını çox çağırır), Primitive obsession (Value Object əvəzinə plain type).

- **Refactoring vs Rewrite fərqi**: Refactoring — davranışı dəyişmədən daxili strukturu yaxşılaşdırmaq. Incremental, test-covered. Rewrite — sıfırdan yazmaq. "Big bang rewrite" — adətən 2-3x vaxt aparır, yarısında uğursuz olur. Joel Spolsky: "Never rewrite from scratch" — eyni səviyyəyə çatmaq üçün illərlə toplanmış edge case-lər itir.

- **Boy Scout Rule**: "Buldundan daha təmiz qoy." Hər commit-dən sonra kod bir az yaxşılaşır. Böyük refactor lazım deyil — hər dəfə kiçik bir şey düzəlt.

- **Debt tracking metodları**: JIRA/Linear-da "Tech Debt" label. Architecture Decision Record (ADR). Tech Debt Register — problem, impact, effort, priority. TODO/FIXME comments (amma bunlar itir, tracking etmək çətin).

- **20% rule (time boxing)**: Hər sprint-in 20%-ni debt üçün ayırmaq. Google, Atlassian, Spotify praktikası. Product team ilə razılaşmaq: "Bu quarter 20% debt üçün xərclənir, velocity düşəcək, amma növbəti quarter 40% artar."

- **Technical Debt Register**: Debt-lərin inventarı. Hər item üçün: problem description, business impact, estimated effort, risk level, priority. Quantify etmək vacibdir — "hər bug fix bu class-da 3 saat əvəzinə 8 saat alır" deyə.

- **ROI hesabı**: Refactoring xərci vs velocity gain vs bug azalması. "Bu 2 sprint refactoring sonra hər feature 40% daha tez yazılacaq — 6 ayda tam ROI." Business language.

- **Hotspot analysis**: Git log ilə ən çox dəyişən faylları tapmaq. Ən çox dəyişən = ən çox developer toxunur = ən çox debt-in toplandığı yer = ən yüksək ROI ilə refactoring.

- **Coupling metrics**: Circular dependencies, efferent/afferent coupling. PHPStan, Psalm, Deptrac, ArchUnit — static analysis tool-ları. Tight coupling = change propagation = "bir yerdə dəyişdirəndə başqa yerlər sınır."

- **Strangler Fig debt için**: Çox böyük, çox mürəkkəb debt üçün tədricən yanaşma. Tam sistemi yenidən yazmaq deyil, hissə-hissə müasirləşdirmək.

- **Documentation debt**: Kod yaxşıdır, amma documentation köhnəlmişdir. Yeni developer 2 həftə anlamaq üçün sərf edir. Runbook-lar yoxdur. ADR-lər yazılmamışdır.

- **Test coverage debt**: Kod var, test yoxdur. Refactoring etmək üçün test lazımdır, test yazmaq üçün isə kod refactor edilib test edilə bilən hala gətirilməlidir. Bu "vicious cycle" — test coverage artırmaq əvvəlcə gəlir.

- **Infrastructure debt**: Köhnə OS versiyası, deprecated library-lər, unsupported framework versiyaları, EOL PHP versiyası. Security vulnerability yaranır.

## Praktik Baxış

**Interview-da necə yanaşmaq:**
Bu mövzuda yalnız texniki tərəf yox, kommunikasiya tərəfini də göstərin. "Business-ə necə izah edərdiniz?" sualına hazır olun. Debt-i "feature freeze, refactor everything" ilə həll etmək yanlışdır — iterative approach vurğulayın. ROI dili ilə danışın.

**Junior-dan fərqlənən senior cavabı:**
Junior: "Refactoring vaxt lazımdır, amma time yoxdur."
Senior: "Hotspot analysis ilə ən çox tə'sir edən 5 faylı tapdım. 20% sprint capacity ayırıb hər sprint bir hotspot-u düzəldirəm."
Lead: "Management-ə belə izah etdim: 'OrderService-i refactor etsək hər feature 3 gün əvəzinə 1 günə yazılacaq. 2 sprint sərf edəcəyik, amma Q3-də 6 feature əlavə vaxt qazanacağıq.' Razılaşdıq."

**Follow-up suallar:**
- "Management debt refactoring-ə vaxt ayırmaq istəmir — necə inandırırsınız?"
- "Debt-i necə ölçürsünüz / quantify edirsiniz?"
- "Bütün debt-i həll etmək mümkündürmü? Lazımdırmı?"
- "Yeni feature vs debt refactoring — necə balans qurursunuz?"
- "Yeni developer-ın yaratdığı debt-i necə əngəlləyirsiniz?"

**Ümumi səhvlər:**
- Debt = pis kod düşünmək — bəzən deliberate debt ağıllı qərardır (startup MVP)
- Bütün debt-i birdən həll etməyə çalışmaq — "big bang refactor" risk altında
- Debt-i yalnız kod kimi görüb infrastructure, documentation, test-i unutmaq
- "Debt-i həll etməliyik" demək (mənasız) əvəzinə "X feature-ın build müddəti 3 həftə əvəzinə 3 gün olacaq" demək
- Refactoring üçün test coverage-sız başlamaq — regression risk çox yüksək

**Yaxşı cavabı əla cavabdan fərqləndirən:**
Əla cavab business ROI dili ilə danışır. Hotspot analysis, static analysis tool-larını, ADR-ləri bilmək texniki dərinlik göstərir. "Deliberate debt haqqında ADR yazırıq — niyə götürdük, nə vaxt ödəyəcəyik" — bu maturite göstərir.

## Nümunələr

### Tipik Interview Sualı
"Köhnə, çox texniki borcu olan bir layihəyə Lead kimi gəldiniz. İlk həftə nə edərdiniz?"

### Güclü Cavab
"İlk həftə anlamaq üçün istifadə edərdim. Git log ilə hotspot analizi — ən çox dəyişən faylları tapardım. PHPStan Level 5 ilə static analysis — mövcud xəta sayı. Team ilə söhbət: 'Günlük sizi ən çox nə yavaşladır?' Onboarding documentation oxuyardım — yeni developer üçün nə çatışmır. Sonra prioritetləşdirərdim: business impact × refactoring effort matrix. İlk refactoring seçimi: yüksək impact, aşağı effort. Sprint-lərin 20%-ni debt üçün ayırıb product team ilə razılaşardım. Hər refactoring-dən əvvəl test coverage artırardım — sığorta olmadan refactoring risk-lidir. Big bang rewrite etməzdim."

### Arxitektura / Kod Nümunəsi

```php
/**
 * Technical Debt Register — Code-da sənədləşdirmə
 *
 * TECH DEBT: OrderService — God Class (700+ lines)
 *
 * Problem: OrderService handles ordering, pricing, shipping,
 *          notifications, inventory, analytics in a single class.
 *          Tight coupling makes testing impossible and every
 *          change risky.
 *
 * Metrics (measured):
 *   - 700+ lines, cyclomatic complexity: 47 (target: <10)
 *   - 6 developers modify weekly → merge conflicts daily
 *   - Average bug fix: 4 hours vs estimated 30 minutes
 *   - Test coverage: 8% (target: 80%)
 *   - 23 merge conflicts in last 30 days in this file
 *
 * Business Impact:
 *   - 3 P2 bugs in last month originated from this class
 *   - Feature velocity: OrderService features take 3x longer
 *   - Developer frustration: 4/5 developers mentioned in retro
 *
 * Business Case:
 *   Investment: 2 sprints (4 weeks) engineering
 *   Benefit: Each feature 40% faster for next 6 months
 *   ROI: Positive by week 6, payback in Q3
 *
 * Plan:
 *   Sprint 1: Add tests to cover existing behavior → extract PricingService
 *   Sprint 2: Extract ShippingService, NotificationService
 *   Sprint 3: Extract InventoryService (if needed)
 *
 * @debt-id TD-042
 * @priority P1 — blocks Q3 roadmap item PROD-234
 * @created 2024-01-15 @by engineering-lead
 * @deadline 2024-Q2
 */
class OrderService  // ← 700+ sətir — bu comment göründükdə düşün
{
    // ... çox şey
}
```

```php
// ADR — Architecture Decision Record
// docs/adr/0015-deliberate-raw-sql-for-reports.md

/**
 * # ADR-0015: Raw SQL for Report Queries (Deliberate Debt)
 * Status: ACCEPTED
 * Date: 2024-03-01
 * Deciders: @tech-lead, @backend-lead
 *
 * ## Context
 * Report queries are highly complex — 15+ table joins, aggregations,
 * conditional groupings. Eloquent/ORM attempts resulted in:
 * - 3x slower queries due to N+1 and eager loading limitations
 * - Unreadable query builder chains (120+ lines)
 * - Impossible to optimize with DB-specific features (CTEs, window functions)
 *
 * ## Decision
 * Use raw SQL for report queries in app/Repositories/Reports/.
 *
 * ## Trade-offs
 * Accepted cons:
 * - DB vendor lock-in (PostgreSQL-specific syntax)
 * - Integration test required (not easily unit testable)
 * - Harder to review for non-SQL-proficient developers
 *
 * ## Mitigation
 * - All raw SQL wrapped in Repository classes
 * - Integration tests cover all report queries
 * - SQL reviewed by @db-team in PRs
 *
 * ## Resolution Plan
 * 2025-Q1: Evaluate query builder abstraction (Doctrine DBAL custom layer)
 * Tracking: TD-089
 *
 * ## Alternatives Rejected
 * - Eloquent: Too slow, unreadable
 * - Stored Procedures: Version control nightmare, harder to review
 */
```

```bash
#!/bin/bash
# Hotspot Analysis Script — ən çox dəyişən faylları tap

echo "=== TOP 20 HOTSPOTS (son 6 ay) ==="
git log \
  --format=format: \
  --name-only \
  --since="6 months ago" \
  | grep -v "^$" \
  | sort \
  | uniq -c \
  | sort -rn \
  | head -20

# Output nümunəsi:
#  89 app/Services/OrderService.php    ← HOTSPOT!
#  67 app/Http/Controllers/OrderController.php
#  45 app/Models/Order.php
#  32 app/Services/PaymentService.php
#  12 app/Services/NotificationService.php

echo ""
echo "=== COMPLEXITY ANALYSIS (PHP Metrics) ==="
vendor/bin/phpmetrics \
  --report-html=reports/metrics/ \
  --violations-xml=reports/violations.xml \
  app/

echo ""
echo "=== HIGH COUPLING FILES ==="
# Deptrac ilə architecture violation
vendor/bin/deptrac analyse --config-file=deptrac.yaml

echo ""
echo "=== PHPSTAN ERRORS ==="
vendor/bin/phpstan analyse app/ --level=5 --error-format=github
```

```php
// PHPStan + Psalm konfiqurasiya — debt tracking
// phpstan.neon
// parameters:
//   level: 5
//   paths: [app]
//   ignoreErrors:
//     # Bu məlum debt-dir — TD-042 ticket-ında izlənir
//     - '#Call to an undefined method App\\Services\\LegacyOrderHelper::#'
//     - identifier: missingType.generics
//       paths: [app/Legacy/]  # Legacy folder — ayrı plan var
//   excludePaths:
//     - app/Generated/

// Deptrac — architecture layering qaydaları
// deptrac.yaml
// parameters:
//   layers:
//     - name: Domain
//       collectors: [{type: directory, value: src/Domain}]
//     - name: Application
//       collectors: [{type: directory, value: src/Application}]
//     - name: Infrastructure
//       collectors: [{type: directory, value: src/Infrastructure}]
//   ruleset:
//     Domain:      # Domain yalnız özünə depend ola bilər
//     Application: [Domain]
//     Infrastructure: [Application, Domain]
```

```yaml
# 20% Rule — Sprint Planning
# JIRA Epic: "Tech Health Q2 2024"
# Sprint Velocity: 40 story points
# Feature work: 32 points (80%)
# Tech debt:     8 points (20%)

# Sprint 24 Tech Debt Items:
# ----------------------------
# TD-042: Extract PricingService from OrderService
#   - Effort: 5 points
#   - Impact: OrderService coverage artır, pricing logic test edilə bilir
#   - Risk: High → test coverage yazıldıqdan sonra başla

# TD-055: Add missing DB indexes (orders.user_id, orders.status)
#   - Effort: 2 points
#   - Impact: Dashboard query 3s → 200ms
#   - Risk: Low → migration safe

# TD-061: Remove deprecated /api/v1/old-orders endpoint
#   - Effort: 1 point
#   - Impact: Code clarity, no clients using it (verified in logs)
#   - Risk: Low → verify no traffic first
```

```php
// Boy Scout Rule — həmişə bir az yaxşılaşdır

// ƏVVƏL (problematic legacy kod):
function proc($d, $f = false) {
    $r = [];
    foreach($d as $x) {
        if($x['s'] == 1 || ($f && $x['s'] == 2)) {
            $r[] = ['id' => $x['i'], 'name' => $x['n']];
        }
    }
    return $r;
}

// SONRA (Boy Scout Rule tətbiq edildi — əl toxunduqda düzəlt):
/**
 * Filter users by active status.
 * @param array[] $users  Each user: ['id' => int, 'name' => string, 'status' => int]
 * @param bool $includePending  Include status=2 (pending) users
 * @return array[]  Filtered users with 'id' and 'name' keys
 */
function filterActiveUsers(array $users, bool $includePending = false): array
{
    return array_values(array_filter(
        array_map(
            fn(array $user) => ['id' => $user['id'], 'name' => $user['name']],
            $users
        ),
        fn(array $user, int $index) => $users[$index]['status'] === 1
            || ($includePending && $users[$index]['status'] === 2),
        ARRAY_FILTER_USE_BOTH
    ));
}
// Eyni davranış, daha oxunaqlı, type-hinted, documented
```

### Müqayisə Cədvəli — Debt Prioritetləşdirmə Matrisi

| Debt | Business Impact | Refactor Effort | Risk | Priority |
|------|----------------|-----------------|------|----------|
| OrderService God Class | Yüksək (hər feature yavaş) | Yüksək (2 sprint) | Yüksək | P1 |
| Missing DB indexes | Yüksək (dashboard yavaş) | Aşağı (migration) | Aşağı | P1 |
| Deprecated endpoint | Aşağı | Aşağı | Aşağı | P3 |
| PHP 8.0 → 8.3 upgrade | Orta (security) | Orta | Orta | P2 |
| No API documentation | Orta (onboarding) | Aşağı | Aşağı | P2 |

## Praktik Tapşırıqlar

1. `git log --name-only --since="6 months ago"` ilə öz layihənizdəki hotspot-ları tapın. Top 5 file-ı sıralayın.
2. PHPStan Level 5-də layihənizi analiz edin — neçə issue var? Bunları kategoriyalara bölün: known debt vs unknown.
3. Bir God Class-ı müəyyən edin, onu necə bölərdiniz? Step-by-step plan yazın.
4. Bir tech debt üçün business case hazırlayın — ROI hesabı edin: investment, payback period, velocity gain.
5. Team üçün Tech Debt Register yaradın — ən vacib 5 problemi sıralayın, hər birini quantify edin.
6. Sprint planning-ə 20% rule tətbiq edin — product owner ilə razılaşma prosesini simulyasiya edin.
7. ADR (Architecture Decision Record) yazın: mövcud deliberate debt-lərinizdən biri üçün.
8. Deptrac ilə architecture layer-larını müəyyən edin — hansı violations var?

## Əlaqəli Mövzular

- `10-strangler-fig.md` — Böyük debt üçün migration strategiyası
- `12-feature-flags.md` — Risk azaltmaq üçün tədricən dəyişiklik
- `03-clean-architecture.md` — Debt-in kökü: bad architecture
- `01-monolith-vs-microservices.md` — Monolith debt vs distributed complexity
