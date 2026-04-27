# Specification Pattern (Lead ⭐⭐⭐⭐)

## İcmal

Specification pattern — biznes qaydalarını (business rules) ayrı, composable object-lərə encapsulate edən domain-driven design pattern-dir. "Encapsulate a business rule in a single unit, and compose them using AND, OR, NOT boolean logic." Sorğu məntiqi, validation məntiqi, selection məntiqi — bunların hamısı class-a çıxarılır. `$spec = new ActiveUser()->and(new HasVerifiedEmail())->or(new IsAdmin())` — oxunaqlı, test edilə bilən, yenidən istifadə edilə bilən. Laravel-in Eloquent scope-ları — Specification pattern-in sadə variantıdır. Repository + Specification kombinasiyası DDD layihələrinin əsasıdır. Lead səviyyəsindəki interview-larda "Complex filtering logic necə idarə edirsiniz?", "Business rules-ı test edilə bilən şəkildə necə yazırsınız?" suallarında çıxır.

## Niyə Vacibdir

Specification pattern olmadan: Complex biznes qaydaları ya repository-yə (SQL leak), ya service-ə (fat service), ya da model-ə (fat model) yığılır. Heç birinin reusability-si yoxdur. Specification ilə: `EligibleForPromotionSpec` — bir dəfə yazılır, repository query-sində, validation-da, notification filter-ında, report generator-da istifadə olunur. Boolean composition: `$eligible->and($hasOrders)->not($isBlocked)` — SQL yazmadan business language ilə query ifadə edilir. Test edilə bilir: `$spec->isSatisfiedBy($user)` — database olmadan unit test. Bu pattern-i Lead səviyyəsindən dərindən anlayan developer: SQL generation, performance optimization, Composite pattern ilə əlaqəni izah edə bilir.

## Əsas Anlayışlar

**Specification interface:**
- `isSatisfiedBy(object $candidate): bool` — in-memory yoxlama
- `toQuery(): Builder` — database query-sinə çevirmə (optional, performance üçün)

**Boolean composition:**
- `AndSpecification`: Hər ikisi true olduqda true
- `OrSpecification`: Birinin true olması kifayət
- `NotSpecification`: Nəticəni ters çevirir
- Fluent: `$a->and($b)->or($c)->not()` — oxunaqlı chain

**İki istifadə tərzi:**

**1. In-memory filtering:**
- `$users->filter(fn($u) => $spec->isSatisfiedBy($u))`
- Kiçik dataset-lər, ya da already-loaded collection-lar üçün
- Advantage: Database-dən asılı deyil, test etmək asan

**2. Database query generation:**
- `$spec->toQuery(User::query())` — Eloquent query-ə where clause əlavə edir
- Böyük dataset-lər üçün — database-də filter edilir
- Daha kompleks implement etmək, lakin performanslı

**Specification vs Query Object:**
- Query Object: Tam query-ni encapsulate edir (select, where, order, limit)
- Specification: Yalnız filtering məntiği encapsulate edir (where condition)
- Specification-lar composable — Query Object-lər bunu o qədər rahat etmir

**Specification vs Laravel Scope:**
- Scope: Model-ə bağlıdır, reuse çətin, test etmək çətin
- Specification: Domain object-ə bağlıdır (User, Order), müstəqil test edilə bilər, reusable

**Composite Specification:**
- Specification-lar Composite pattern kimi qurulur — leaf və composite node-lar
- Leaf: `ActiveUserSpec`, `VerifiedEmailSpec`
- Composite: `AndSpec`, `OrSpec`, `NotSpec`

**Parameterized Specification:**
- `new OrderAmountGreaterThan(Money::of(100, 'usd'))` — parametr alan spec
- `new CreatedAfter(now()->subDays(30))` — dynamic predicate

**Performance consideration:**
- `isSatisfiedBy()` — hər element üçün çağrılır. O(n) — collection boyunca
- `toQuery()` — database-ə WHERE clause göndərir. O(log n) indexed query
- Böyük dataset üçün: Həmişə `toQuery()` (ya da hybrid: repository query alır)

**Specification + Repository:**
- `UserRepository::findSatisfying(Specification $spec): Collection`
- Repository spec-i SQL-ə çevirir. Service yalnız spec-i bilir

**Specification validation-da:**
- `$spec->isSatisfiedBy($dto)` — form validation-dan fərqli, biznes qaydası
- Məs: "Bu user bu plan-a yüksəlişə uyğundur?" — sadə validator ilə deyil, Specification ilə

**DDD (Domain-Driven Design) kontekstı:**
- Specification — Ubiquitous Language-i ifadə edir: `EligibleForPremiumSpec`, `OverdueInvoiceSpec`
- Domain layer-da olur — database bilmir
- Repository spec-i SQL-ə çevirmə işini alır

## Praktik Baxış

**Interview-da yanaşma:**
Specification-ı əvvəlcə problem üzərindən izah edin: "Repository-də 15 where clause olan `findActiveVerifiedNonBlockedUsersEligibleForPromotion()` metodu var. Bu oxunaqlı deyil, test edilmir, reuse olmur." Sonra Specification həllinə keçin. Boolean composition fluent interface-i göstərin.

**"Nə vaxt Specification seçərdiniz?" sualına cavab:**
- Biznes qaydası bir neçə yerdə istifadə olunanda (repository + validator + notification filter)
- Complex filtering məntiği test edilməniyə ehtiyac olduqda
- Domain language-i kod-da ifadə etmək lazım olduqda
- Repository metodlarının partlamaması lazım olduqda (N distinct query əvəzinə N specification composition)

**Anti-pattern-lər:**
- Specification-a database query logic yazıb `isSatisfiedBy()` da SQL atmaq — `toQuery()` bunun üçündür
- Specification-ı sadə `where()` yerinə hər yerdə istifadə etmək — overkill kiçik sorgular üçün
- Stateful specification — spec-in özündə mutable state saxlamaq
- Spec-in test edilmədən production-a çıxması — bu pattern-in əsas faydası test edilə bilməsidir

**Follow-up suallar:**
- "Specification Eloquent scope-lardan nə ilə fərqlənir?" → Scope: Model-ə bağlı, test etmək çətin. Spec: Müstəqil class, unit test edilir, hər yerdə reuse
- "Spec + Repository necə birlikdə işləyir?" → Repo `applySpec(Builder $q, Specification $s)` metodu ilə spec-i query-yə çevirir
- "Çox spec-in AND composition-ı performansı yavaşladırmı?" → `toQuery()` implementasiyasında hər spec ayrı JOIN ya da subquery olursa bəli. Query planner-a etibar et, EXPLAIN analiz et
- "Specification vs Filter DTO fərqi?" → Filter DTO: Yalnız data daşıyır. Specification: Həm data, həm məntiq, həm `isSatisfiedBy()` metodu var — behavior-u olan domain object

## Nümunələr

### Tipik Interview Sualı

"Your e-commerce system needs to find users eligible for a promotional campaign. Criteria: active users, email verified, at least one order in the last 90 days, not already received this promotion, account age > 30 days. This logic is used in the admin dashboard filter, notification batch, and background job. How do you avoid duplicating this logic?"

### Güclü Cavab

Bu Specification pattern-in prime use-case-idir. 5 ayrı şərt var, hər biri müstəqil test edilə bilər, birlikdə composable.

5 ayrı Specification: `ActiveUserSpec`, `VerifiedEmailSpec`, `HasRecentOrderSpec(90)`, `NotReceivedPromotionSpec($promoId)`, `AccountAgeDaysSpec(30)`.

Composition: `$eligible = (new ActiveUserSpec())->and(new VerifiedEmailSpec())->and(new HasRecentOrderSpec(90))->and(new NotReceivedPromotionSpec($promoId))->and(new AccountAgeDaysSpec(30))`.

Repository: `UserRepository::findSatisfying($eligible)` — spec-i query-yə çevirir.

Admin dashboard, notification batch, background job — hamısı eyni `$eligible` spec-i istifadə edir. Şərt dəyişsə — yalnız bir spec dəyişir.

### Kod Nümunəsi

```php
// Specification Interface
interface Specification
{
    public function isSatisfiedBy(mixed $candidate): bool;

    public function and(Specification $other): Specification;
    public function or(Specification $other): Specification;
    public function not(): Specification;

    // Optional: database query generation
    public function toQueryBuilder(Builder $query): Builder;
}

// Abstract Base — boolean composition logic
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

// Composite: AND
class AndSpecification extends AbstractSpecification
{
    public function __construct(
        private readonly Specification $left,
        private readonly Specification $right,
    ) {}

    public function isSatisfiedBy(mixed $candidate): bool
    {
        return $this->left->isSatisfiedBy($candidate)
            && $this->right->isSatisfiedBy($candidate);
    }

    public function toQueryBuilder(Builder $query): Builder
    {
        return $this->right->toQueryBuilder(
            $this->left->toQueryBuilder($query)
        );
    }
}

// Composite: OR
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

    public function toQueryBuilder(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $this->left->toQueryBuilder($q);
            $this->right->toQueryBuilder($q->orWhere(fn() => null)); // simplified
        });
    }
}

// Composite: NOT
class NotSpecification extends AbstractSpecification
{
    public function __construct(private readonly Specification $wrapped) {}

    public function isSatisfiedBy(mixed $candidate): bool
    {
        return !$this->wrapped->isSatisfiedBy($candidate);
    }

    public function toQueryBuilder(Builder $query): Builder
    {
        return $query->whereNot(fn(Builder $q) => $this->wrapped->toQueryBuilder($q));
    }
}
```

```php
// Leaf Specifications — konkret biznes qaydaları

class ActiveUserSpec extends AbstractSpecification
{
    public function isSatisfiedBy(mixed $user): bool
    {
        return $user->status === 'active';
    }

    public function toQueryBuilder(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}

class VerifiedEmailSpec extends AbstractSpecification
{
    public function isSatisfiedBy(mixed $user): bool
    {
        return $user->email_verified_at !== null;
    }

    public function toQueryBuilder(Builder $query): Builder
    {
        return $query->whereNotNull('email_verified_at');
    }
}

class HasRecentOrderSpec extends AbstractSpecification
{
    public function __construct(private readonly int $days = 90) {}

    public function isSatisfiedBy(mixed $user): bool
    {
        return $user->orders()
            ->where('created_at', '>=', now()->subDays($this->days))
            ->exists();
    }

    public function toQueryBuilder(Builder $query): Builder
    {
        return $query->whereHas('orders', fn(Builder $q) =>
            $q->where('created_at', '>=', now()->subDays($this->days))
        );
    }
}

class NotReceivedPromotionSpec extends AbstractSpecification
{
    public function __construct(private readonly int $promotionId) {}

    public function isSatisfiedBy(mixed $user): bool
    {
        return !$user->promotions()
            ->where('promotion_id', $this->promotionId)
            ->exists();
    }

    public function toQueryBuilder(Builder $query): Builder
    {
        return $query->whereDoesntHave('promotions', fn(Builder $q) =>
            $q->where('promotion_id', $this->promotionId)
        );
    }
}

class AccountAgeDaysSpec extends AbstractSpecification
{
    public function __construct(private readonly int $minDays) {}

    public function isSatisfiedBy(mixed $user): bool
    {
        return $user->created_at->diffInDays(now()) >= $this->minDays;
    }

    public function toQueryBuilder(Builder $query): Builder
    {
        return $query->where('created_at', '<=', now()->subDays($this->minDays));
    }
}
```

```php
// Repository — Specification-ı SQL-ə çevirir
class UserRepository
{
    public function findSatisfying(Specification $spec): Collection
    {
        $query = User::query();
        $query = $spec->toQueryBuilder($query);
        return $query->get();
    }

    public function countSatisfying(Specification $spec): int
    {
        $query = User::query();
        $query = $spec->toQueryBuilder($query);
        return $query->count();
    }

    public function findInMemory(Collection $users, Specification $spec): Collection
    {
        return $users->filter(fn($u) => $spec->isSatisfiedBy($u));
    }
}

// Composite Specification — promotion eligibility
class PromotionEligibilitySpec extends AbstractSpecification
{
    private Specification $composed;

    public function __construct(int $promotionId)
    {
        $this->composed = (new ActiveUserSpec())
            ->and(new VerifiedEmailSpec())
            ->and(new HasRecentOrderSpec(90))
            ->and(new NotReceivedPromotionSpec($promotionId))
            ->and(new AccountAgeDaysSpec(30));
    }

    public function isSatisfiedBy(mixed $user): bool
    {
        return $this->composed->isSatisfiedBy($user);
    }

    public function toQueryBuilder(Builder $query): Builder
    {
        return $this->composed->toQueryBuilder($query);
    }
}

// İstifadə — admin, batch job, notifikasiya — hamısı eyni spec
$eligibleSpec = new PromotionEligibilitySpec(promotionId: 42);

// Admin dashboard — paginated
$users = $userRepo->findSatisfying($eligibleSpec);

// Background job — chunk ile process
User::query()->tap(fn($q) => $eligibleSpec->toQueryBuilder($q))
    ->chunk(200, fn($batch) => dispatch(new SendPromotionJob($batch, 42)));

// Unit test — database olmadan
class PromotionEligibilitySpecTest extends TestCase
{
    public function test_active_verified_user_with_recent_order_is_eligible(): void
    {
        $user = $this->makeUser(status: 'active', verified: true, orderDaysAgo: 30, ageDays: 60);
        $spec = new PromotionEligibilitySpec(promotionId: 1);

        $this->assertTrue($spec->isSatisfiedBy($user));
    }

    public function test_unverified_user_is_not_eligible(): void
    {
        $user = $this->makeUser(status: 'active', verified: false, orderDaysAgo: 30, ageDays: 60);
        $spec = new PromotionEligibilitySpec(promotionId: 1);

        $this->assertFalse($spec->isSatisfiedBy($user));
    }
}
```

## Praktik Tapşırıqlar

- `InvoiceOverdueSpec` yazın: Due date keçib, ödənilməyib, miqdar > $100
- Boolean composition test edin: `$a->and($b)`, `$a->or($b)`, `$not->not()` — truth table ilə yoxlayın
- `ProductSearchSpec` yazın: Qiymət aralığı, kateqoriya, stok durumu — `toQueryBuilder()` ilə
- Repository-ə `findSatisfying(Specification $spec): Collection` əlavə edin — Eloquent query ilə
- Eloquent scope-larını Specification-a refactor edin: 3 scope → 3 ayrı Spec, birlikdə composable

## Əlaqəli Mövzular

- [Repository Pattern](07-repository-pattern.md) — Specification + Repository kombinasiyası DDD-nin özəyidir
- [SOLID Principles](01-solid-principles.md) — SRP: Hər spec bir qayda. OCP: Yeni spec mövcudu dəyişmir
- [Strategy Pattern](05-strategy-pattern.md) — Spec selection strategy kimi istifadə oluna bilər
- [Chain of Responsibility](14-chain-of-responsibility.md) — Spec chain-i validation pipeline kimi
- [Builder Pattern](10-builder-pattern.md) — Specification builder fluent interface ilə qurulur
