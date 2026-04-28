# Specification (Senior ⭐⭐⭐)

## İcmal

Specification pattern, business rule-ları müstəqil, reusable object-lərə çıxarır. Bu object-lər boolean logic (`and`, `or`, `not`) ilə kombine edilərək mürəkkəb eligibility şərtləri qurulur. Hər specification bir sual cavablandırır: "Bu entity bu criteria-ya uyğundurmu?"

## Niyə Vacibdir

Laravel layihələrində business rule-lar adətən controller-larda, scope-larda, ya da if-else chain-lərində dağınıq qalır. Zamanla bu rule-lar təkrarlanır, test edilmir, dəyişdikdə bir neçə yerdə update lazım olur. Specification ilə hər rule bir yerdə yazılır, test edilir, kombine olunur. Xüsusilə discount eligibility, feature flags, subscription tier checks, access control kimi mürəkkəb iş qaydaları üçün idealdir.

## Əsas Anlayışlar

- **Specification**: `isSatisfiedBy($entity): bool` method-u olan interface; bir business rule-u encapsulate edir
- **Composite Specification**: `AndSpecification`, `OrSpecification`, `NotSpecification` — birini kombinasiya etmək üçün wrapper-lar
- **Fluent interface**: `$spec->and($other)->or($third)->not()` — oxunaqlı composition
- **Query Specification**: `toQuery(Builder $query): Builder` method-u ilə database-ə translate olunma
- **Candidate**: specification-a tabi olan entity (`User`, `Order`, `Product`)

## Praktik Baxış

- **Real istifadə**: discount/promotion eligibility, user permission rules, product filtering (e-commerce), loan/credit approval, feature access (premium users), kompleks validation
- **Trade-off-lar**: hər rule reusable və test edilə bilər; lakin sadə `where` clause üçün overkill-dir; çox sayda specification olduqda kombinasiyaları izləmək çətin olur; DB ve PHP spec-ləri sync saxlamaq lazımdır
- **İstifadə etməmək**: sadə boolean şərtlər üçün (aktiv user check); rule-lar bir dəfə işlənib atılacaqsa; performance-critical tight loop-larda (hər iteration üçün object yaratmaq)

- **Common mistakes**:
  1. Specification-da side effects etmək (email göndərmək, DB write)
  2. Database-specific SQL-i specification özündə deyil `toQuery()` method-unda saxlamaq
  3. Specification-ı anemic filter yerinə domain concept kimi adlandırın

### Anti-Pattern Nə Zaman Olur?

**Specification for simple where()** — sadə sorğu üçün overkill:
```php
// BAD — bu üçün specification lazım deyil
class ActiveUserSpecification extends AbstractSpecification
{
    public function isSatisfiedBy(mixed $user): bool
    {
        return $user->is_active === true;
    }
}
// Bunun əvəzinə sadəcə: User::where('is_active', true)->get()
// Specification yalnız bu rule başqa rule-larla kombinasiya olunacaqsa lazımdır

// GOOD — specification çox rule-u kombine etdikdə dəyər qazanır
$spec = (new ActiveUserSpec())
    ->and(new PremiumUserSpec())
    ->and(new RecentLoginSpec(days: 30))
    ->and(new MinOrderCountSpec(minimum: 5));
// Bu if/else chain ilə yazılardı 15+ sətir
```

**Over-engineering simple filters:**
```php
// BAD — admin panel filter üçün specification sistemi qurmaq
// Admin panel-i sadə Eloquent scope-larla kifayətdir
class UserAgeRangeSpecification extends AbstractSpecification { ... }
class UserCitySpecification extends AbstractSpecification { ... }
class UserRegistrationDateSpecification extends AbstractSpecification { ... }
// Bu 3-ü bir admin filter forması üçün — overkill
```

## Nümunələr

### Ümumi Nümunə

Bir bank müştəriyə kredit verməzdən əvvəl bir neçə şərti yoxlayır: minimum yaş, minimum gəlir, kredit tarixi, mövcud borclar. Bu şərtlərin hər birini ayrı specification kimi modelləsəniz, "prime customer" = `AgeSpec.and(IncomeSpec).and(GoodHistorySpec).and(NoDefaultSpec)` kimi kombinasiya edə bilərsiniz.

### PHP/Laravel Nümunəsi

```php
<?php

namespace App\Specifications;

// Base interface
interface Specification
{
    public function isSatisfiedBy(mixed $candidate): bool;

    public function and(Specification $other): Specification;
    public function or(Specification $other): Specification;
    public function not(): Specification;
}

// Abstract base — composite logic-i bir yerdə saxlayır; hər spec yazmır
abstract class AbstractSpecification implements Specification
{
    public function and(Specification $other): Specification
    {
        return new AndSpecification($this, $other);
    }

    public function or(Specification $other): Specification
    {
        return new OrSpecification($this, $other);
    }

    public function not(): Specification
    {
        return new NotSpecification($this);
    }
}

// Composite implementations — boolean algebra
class AndSpecification extends AbstractSpecification
{
    public function __construct(
        private readonly Specification $left,
        private readonly Specification $right,
    ) {}

    public function isSatisfiedBy(mixed $candidate): bool
    {
        // Short-circuit evaluation — $left false-dırsa $right-ı yoxlamır
        return $this->left->isSatisfiedBy($candidate)
            && $this->right->isSatisfiedBy($candidate);
    }
}

class OrSpecification extends AbstractSpecification
{
    public function __construct(
        private readonly Specification $left,
        private readonly Specification $right,
    ) {}

    public function isSatisfiedBy(mixed $candidate): bool
    {
        return $this->left->isSatisfiedBy($candidate)
            || $this->right->isSatisfiedBy($candidate);
    }
}

class NotSpecification extends AbstractSpecification
{
    public function __construct(private readonly Specification $spec) {}

    public function isSatisfiedBy(mixed $candidate): bool
    {
        return !$this->spec->isSatisfiedBy($candidate);
    }
}

// Concrete specifications — hər biri bir business rule
class ActiveUserSpecification extends AbstractSpecification
{
    public function isSatisfiedBy(mixed $candidate): bool
    {
        // İki şərt bir business rule-dur — "aktiv" = status + email verified
        return $candidate->status === 'active'
            && $candidate->email_verified_at !== null;
    }
}

class PremiumUserSpecification extends AbstractSpecification
{
    public function isSatisfiedBy(mixed $candidate): bool
    {
        return $candidate->subscription_tier === 'premium';
    }
}

class RecentlyLoggedInSpecification extends AbstractSpecification
{
    // Constructor-da parametr — flexible, reusable
    public function __construct(private readonly int $days = 30) {}

    public function isSatisfiedBy(mixed $candidate): bool
    {
        return $candidate->last_login_at !== null
            && $candidate->last_login_at->diffInDays(now()) <= $this->days;
    }
}

class MinimumOrderCountSpecification extends AbstractSpecification
{
    public function __construct(private readonly int $minimum) {}

    public function isSatisfiedBy(mixed $candidate): bool
    {
        // N+1 riski — bu spec yalnız az sayda user üçün istifadə edin
        return $candidate->orders()->count() >= $this->minimum;
    }
}

// Usage — xarici kod yalnız composition-la işləyir; spec-lərin içini bilmir
class DiscountEligibilityService
{
    public function isEligibleForLoyaltyDiscount(User $user): bool
    {
        // Oxunaqlı business rule — "aktiv VƏ son 90 günlük VƏ ən azı 5 sifarişi var"
        $spec = (new ActiveUserSpecification())
            ->and(new RecentlyLoggedInSpecification(days: 90))
            ->and(new MinimumOrderCountSpecification(minimum: 5));

        return $spec->isSatisfiedBy($user);
    }

    public function isEligibleForPremiumContent(User $user): bool
    {
        $active  = new ActiveUserSpecification();
        $premium = new PremiumUserSpecification();

        return $active->and($premium)->isSatisfiedBy($user);
    }
}
```

**Query Specification — Database integration:**

```php
<?php

// Query-capable specification interface — PHP + DB hər ikisini destekleyir
interface QuerySpecification extends Specification
{
    public function toQuery(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder;
}

class ActiveUserQuerySpec extends AbstractSpecification implements QuerySpecification
{
    // PHP in-memory check
    public function isSatisfiedBy(mixed $candidate): bool
    {
        return $candidate->status === 'active' && $candidate->email_verified_at !== null;
    }

    // DB query — N+1 olmadan paginated list üçün
    public function toQuery(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query
            ->where('status', 'active')
            ->whereNotNull('email_verified_at');
    }
}

class PremiumUserQuerySpec extends AbstractSpecification implements QuerySpecification
{
    public function isSatisfiedBy(mixed $candidate): bool
    {
        return $candidate->subscription_tier === 'premium';
    }

    public function toQuery(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('subscription_tier', 'premium');
    }
}

// Repository with specification support
class UserRepository
{
    // Specification-ı qəbul edir — hansi spec-i bilmir, sadəcə interface bilir
    public function findSatisfying(QuerySpecification $spec, int $perPage = 20): \Illuminate\Pagination\LengthAwarePaginator
    {
        return $spec->toQuery(User::query())->paginate($perPage);
    }
}

// Usage — eyni spec həm PHP, həm DB üçün
$repo    = new UserRepository();
$active  = new ActiveUserQuerySpec();
$premium = new PremiumUserQuerySpec();

// In-memory filtering — kiçik collection üçün
$users = User::all()->filter(fn($u) => $active->and($premium)->isSatisfiedBy($u));

// Database query — böyük data üçün; N+1 yoxdur
$query = $premium->toQuery($active->toQuery(User::query()));
$users = $query->paginate(20);
```

**Unit testing specifications:**

```php
class ActiveUserSpecificationTest extends TestCase
{
    public function test_active_verified_user_satisfies(): void
    {
        $spec = new ActiveUserSpecification();
        $user = new User(['status' => 'active', 'email_verified_at' => now()]);

        $this->assertTrue($spec->isSatisfiedBy($user));
    }

    public function test_unverified_user_does_not_satisfy(): void
    {
        $spec = new ActiveUserSpecification();
        $user = new User(['status' => 'active', 'email_verified_at' => null]);

        $this->assertFalse($spec->isSatisfiedBy($user));
    }

    public function test_and_composition(): void
    {
        $active  = new ActiveUserSpecification();
        $premium = new PremiumUserSpecification();
        $spec    = $active->and($premium);

        $user = new User([
            'status' => 'active',
            'email_verified_at' => now(),
            'subscription_tier' => 'premium',
        ]);

        $this->assertTrue($spec->isSatisfiedBy($user));
    }
}
```

## Praktik Tapşırıqlar

1. E-commerce layihəsindəki discount şərtlərini (minimum cart amount, user tier, promo period) ayrı specification-lara çevirin; `and()` ilə kombine edin
2. `PasswordStrengthSpecification` yaradın: minimum uzunluq, böyük hərf, rəqəm, xüsusi simvol — hər şərt ayrı spec; fail olana qədər `and()` chain edin, hansının fail olduğunu user-ə göstərin
3. `QuerySpecification` interface-inə `toQuery()` əlavə edin; `ActiveUserQuerySpec` yazın; həm PHP in-memory, həm də DB query ilə eyni spec işləsin; unit test + integration test yazın
4. `SpecificationFactory` class-ı yaradın — config/array-dan specification qurur (rule engine ilə integration üçün)

## Əlaqəli Mövzular

- [01-repository-pattern.md](01-repository-pattern.md) — `findSatisfying(Specification)` natural integration
- [../behavioral/02-strategy.md](../behavioral/02-strategy.md) — Specification bir strategy növüdür; boolean combination əlavədir
- [../behavioral/06-chain-of-responsibility.md](../behavioral/06-chain-of-responsibility.md) — Specification-ları sequential check kimi istifadə etmək
- [../ddd/02-value-objects.md](../ddd/02-value-objects.md) — Specification value object kimi modelləmək
- [../ddd/04-ddd-aggregates.md](../ddd/04-ddd-aggregates.md) — Domain rule-ları specification ilə encapsulate etmək
- [../architecture/02-solid-principles.md](../architecture/02-solid-principles.md) — OCP: yeni rule = yeni specification class
