# Technical Debt & Fitness Functions (Lead)

## Mündəricat
1. [Technical Debt Növləri](#technical-debt-növləri)
2. [Architecture Decision Records (ADR)](#architecture-decision-records-adr)
3. [Fitness Functions](#fitness-functions)
4. [Refactoring Roadmap](#refactoring-roadmap)
5. [PHP İmplementasiyası](#php-implementasiyası)
6. [İntervyu Sualları](#intervyu-sualları)

---

## Technical Debt Növləri

```
Martin Fowler-in Technical Debt Quadrant:

                    Reckless          Prudent
                ┌─────────────────┬──────────────────┐
Deliberate      │"Design yoxdur,  │"Ship et, sonra   │
                │vaxt yoxdur"     │nəticələrini bilirik"│
                ├─────────────────┼──────────────────┤
Inadvertent     │"Layered arch    │"Amma indi daha   │
                │nədir bilmirdik" │yaxşı edərdik"    │
                └─────────────────┴──────────────────┘

Debt növləri:

1. Deliberate/Reckless:
   "Sprint çatışmır, test yazmayacağıq"
   → Ən zərərli. Bilinə-bilinə yaranır.

2. Deliberate/Prudent:
   "MVP üçün short-cut götürürük, sonra refactor"
   → Qəbul edilə bilər, açıq qeyd edilməli.

3. Inadvertent/Reckless:
   Texniki cəhətsizlik nəticəsində.
   → Training, code review ilə önlənir.

4. Inadvertent/Prudent:
   "Retrospektiv baxanda daha yaxşı edə bilərdik"
   → Normaldir, öyrənmənin bir hissəsidir.

Debt əlamətləri:
  - Dəyişiklik etmək getdikcə çətinləşir
  - Bug-lar bilinməyən yerlərdən çıxır
  - Yeni developer-lər tez-tez dolaşır
  - "Touch this code and something breaks"
```

---

## Architecture Decision Records (ADR)

```
ADR — Arxitektura qərarlarını sənədləşdirmək.
"Niyə belə etdik?" sualını cavablandırır.

Format (MADR — Markdown ADR):

# ADR-001: PostgreSQL seçimi MongoDB əvəzinə

## Status
Accepted (2024-01-15)

## Context
E-commerce platforma üçün primary database seçimi.
Əsas tələblər: ACID transactions, complex queries.

## Decision
PostgreSQL istifadə edirik.

## Consequences
Positive:
- ACID transactions ödəniş prosesini təmin edir
- Complex JOIN queries nativ dəstəyi
- Güclü full-text search (tsvector)

Negative:
- Horizontal sharding mürəkkəbdir
- Schema evolution daha ciddi planlaşdırma tələb edir

## Alternatives Considered
- MongoDB: Flexible schema, amma ACID yoxdur (ödəniş üçün riskli)
- MySQL: Güclü competitor, amma PostgreSQL JSON support daha yaxşı

ADR faydaları:
  ✓ "Niyə bu şəkil?" sualı 2 il sonra cavablanır
  ✓ Yeni team member-lar konteksti tez anlayır
  ✓ Qərar revizyonu — indi bu qərar hələ mantıqlıdırmı?
  ✓ Bilik paylaşımı

ADR harada saxlanır:
  git repo-da /docs/adr/ qovluğu
  Kod ilə birgə versiyonlanır
```

---

## Fitness Functions

```
Neal Ford, Rebecca Parsons — "Building Evolutionary Architectures"

Fitness Function:
  Arxitekturanın müəyyən xüsusiyyətini ölçən mexanizm.
  "Bu arxitektura xüsusiyyəti qorunurmu?" soruşur.

Misal:
  "Presentation layer Domain layer-ı import etməməlidir"
  → PHPStan kuralı bu invariantı CI-da yoxlayır.

  "API response 200ms-dan az olmalıdır"
  → Load test CI-da çalışır.

  "Modul A Modul B-yə birbaşa bağlı olmamalıdır"
  → Dependency analysis aləti.

Növləri:

Atomic (tək ölçü):
  - Siklomatik mürəkkəblik < 10
  - Test coverage > 80%
  - Zero high-severity security vulnerabilities

Holistic (sistem səviyyəsindəki):
  - End-to-end latency P99 < 500ms
  - Deployment frequency > həftədə bir dəfə
  - Availability > 99.9%

Triggered vs Continuous:
  CI-da → pull request-dən əvvəl
  Production monitoring → daim

"Fitness functions" arxitekturanı testable edir.
Kod testi kimi, arxitektura xüsusiyyətlərini test edirsən.
```

---

## Refactoring Roadmap

```
Böyük debt-in prioritetləndirilməsi:

Debt quadrant:
  High Impact + Easy → İlk yap
  High Impact + Hard → Plan et
  Low Impact + Easy  → Vaxt olanda
  Low Impact + Hard  → İcazəsiz toxunma

Strangler Fig pattern (topik 47):
  Monolith-i tədricən yeni sistemlə əvəzlə.
  Ayda bir modul → risk minimal.

Boy Scout Rule:
  "Kodu gördüyündən bir az daha yaxşı burax"
  Hər commit-də kiçik refactor.
  Böyük "refactoring sprint" lazım olmur.

Debt ledger:
  Bilinen debt-ləri siyahıda tut.
  Hər sprint-in %20-i debt-ə ayrılır.
  Prioritet iş tamamlananda ledger yenilənir.

Metrikalar:
  Cyclomatic complexity (mürəkkəblik)
  Code churn (çox dəyişdirilən fayllar = problem)
  Coupling metrics (afferent/efferent coupling)
  Test coverage (dead code, untested paths)
```

---

## PHP İmplementasiyası

```php
<?php
// PHPStan custom rule — Architecture fitness function
// "Application layer Infrastructure-u import etməməlidir"

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;

class NoInfrastructureInApplicationRule implements Rule
{
    public function getNodeType(): string
    {
        return Node\Stmt\Use_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $currentNamespace = $scope->getNamespace() ?? '';

        // Yalnız Application namespace-dəki fayllarda yoxla
        if (!str_starts_with($currentNamespace, 'App\\Application')) {
            return [];
        }

        $errors = [];
        foreach ($node->uses as $use) {
            $usedClass = $use->name->toString();

            if (str_starts_with($usedClass, 'App\\Infrastructure')) {
                $errors[] = sprintf(
                    'Application layer Infrastructure-u import edə bilməz: %s',
                    $usedClass
                );
            }
        }

        return $errors;
    }
}
```

```yaml
# phpstan.neon — fitness functions CI-da
parameters:
    level: 8
    paths:
        - src
    
services:
    - class: App\PhpStan\NoInfrastructureInApplicationRule
      tags:
          - phpstan.rules.rule
```

```php
<?php
// Cyclomatic complexity fitness function (CI-da)
// phploc, phpmetrics ilə ya da manual:

class ComplexityChecker
{
    private const MAX_COMPLEXITY = 10;

    public function checkFile(string $filePath): array
    {
        $violations = [];
        $tokens = token_get_all(file_get_contents($filePath));

        $currentFunction = null;
        $complexity = 1;

        foreach ($tokens as $token) {
            if (!is_array($token)) continue;

            switch ($token[0]) {
                case T_FUNCTION:
                    if ($currentFunction && $complexity > self::MAX_COMPLEXITY) {
                        $violations[] = "{$currentFunction}: complexity={$complexity}";
                    }
                    $complexity = 1;
                    break;
                case T_IF:
                case T_ELSEIF:
                case T_WHILE:
                case T_FOR:
                case T_FOREACH:
                case T_CASE:
                case T_CATCH:
                case T_LOGICAL_AND:
                case T_LOGICAL_OR:
                case T_BOOLEAN_AND:
                case T_BOOLEAN_OR:
                    $complexity++;
                    break;
            }
        }

        return $violations;
    }
}
```

```
# ADR template (docs/adr/template.md)

# ADR-{number}: {Title}

## Status
{Proposed | Accepted | Deprecated | Superseded by ADR-XXX}

## Context
{Hansı problem/qərar qabaqda idi?}

## Decision
{Nə qərara gəldik?}

## Consequences
### Positive
- ...

### Negative
- ...

## Alternatives Considered
- {Alternativ 1}: {Niyə seçilmədi}
- {Alternativ 2}: {Niyə seçilmədi}
```

---

## İntervyu Sualları

- Technical debt-in 4 quadrantını izah edin. Hansı ən zərərlidir?
- ADR nədir? Niyə git repo-da saxlanmalıdır?
- Fitness function arxitektura testindən nəylə fərqlənir?
- "Boy Scout Rule" debt management-da nədir?
- PHPStan custom rule ilə hansı fitness function-ları implementasiya edə bilərsiniz?
- "High Impact, Hard to Fix" debt-i necə prioritetləndirərdiniz?
- Refactoring "debt-i sıfırlamaq" strategiyası niyə praktikada işləmir?
