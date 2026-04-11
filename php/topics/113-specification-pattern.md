# Specification Pattern

## Mündəricat
1. [Problem: Biznes Qaydaları Yayılmış Koddadır](#problem-biznes-qaydaları-yayılmış-koddadır)
2. [Specification nədir?](#specification-nədir)
3. [Composite Specifications](#composite-specifications)
4. [Repository ilə İnteqrasiya](#repository-ilə-inteqrasiya)
5. [SQL Generasiyası](#sql-generasiyası)
6. [PHP İmplementasiyası](#php-implementasiyası)
7. [İntervyu Sualları](#intervyu-sualları)

---

## Problem: Biznes Qaydaları Yayılmış Koddadır

```
Eyni qaydanın müxtəlif yerlərdə yenidən yazılması:

Controller-da:
  if ($order->status === 'pending' && $order->total > 1000 && $user->isPremium()) {
      // ...
  }

Service-də:
  if ($order->isPending() && $order->isHighValue() && $order->customer->isPremium()) {
      // ...
  }

Repository-də:
  ->where('status', 'pending')
  ->where('total', '>', 1000)
  ->whereHas('customer', fn($q) => $q->where('plan', 'premium'))

Problemlər:
  - Eyni qayda 3 yerdə → sinxronizasiya çətin
  - Unit test etmək çətin (hər yerdə ayrıca)
  - Domain language gizlənir
  - Kombinasiya etmək çətin

Həll: Specification — biznes qaydalarını domain object kimi ifadə et.
```

---

## Specification nədir?

```
Specification — "Bu obyekt bu şərtə uyğundurmu?" sualını cavablayan obyekt.

interface Specification<T>
  isSatisfiedBy(T candidate): bool

Bir qayda = bir class.
Kombinasiyalar = And/Or/Not ilə.

$spec = new HighValueOrderSpec(1000)
    ->and(new PendingOrderSpec())
    ->and(new PremiumCustomerOrderSpec());

$spec->isSatisfiedBy($order); // true/false

Faydaları:
  ✓ Domain language açıq
  ✓ Reusable (eyni spec çox yerdə)
  ✓ Composable (and, or, not)
  ✓ Unit testable (hər spec ayrıca test)
  ✓ Repository ilə inteqrasiya (SQL-ə çevirmək mümkün)
```

---

## Composite Specifications

```
3 əsas kompozisiya:

AndSpecification:
  Hər iki spec uyğun olmalıdır.
  A.and(B).isSatisfiedBy(x) = A.isSatisfiedBy(x) && B.isSatisfiedBy(x)

OrSpecification:
  Ən azı biri uyğun olmalıdır.
  A.or(B).isSatisfiedBy(x) = A.isSatisfiedBy(x) || B.isSatisfiedBy(x)

NotSpecification:
  Spec uyğun deyil.
  A.not().isSatisfiedBy(x) = !A.isSatisfiedBy(x)

Kombinasiya:
  (A and B) or (C and not D)

  $spec = (new SpecA())->and(new SpecB())
      ->or((new SpecC())->and((new SpecD())->not()));
```

---

## Repository ilə İnteqrasiya

```
2 üsul:

1. In-memory filter:
   Bütün entity-ləri yüklə, spec ilə filter et.
   
   $orders = $repository->findAll();
   $filtered = array_filter($orders, fn($o) => $spec->isSatisfiedBy($o));
   
   ❌ Performans problemi — hər şeyi yüklürsən

2. SQL generation (Criteria):
   Spec → SQL WHERE clause
   DB-də filter et.
   
   $criteria = $spec->toCriteria();
   $orders = $repository->findByCriteria($criteria);
   → SELECT * FROM orders WHERE status='pending' AND total > 1000
   
   ✅ Effektiv — yalnız lazımi data yüklənir
```

---

## SQL Generasiyası

```
Specification → Criteria/QueryBuilder əlavəsi

interface Specification {
    isSatisfiedBy(entity): bool;    // In-memory
    toQueryBuilder(qb): QueryBuilder; // SQL
}

AndSpecification.toQueryBuilder():
  $this->left->toQueryBuilder($qb);
  $this->right->toQueryBuilder($qb);
  // Her ikisi AND ilə əlavə olunur

PendingOrderSpec.toQueryBuilder():
  $qb->andWhere('o.status = :status')
     ->setParameter('status', 'pending');

HighValueSpec.toQueryBuilder():
  $qb->andWhere('o.total > :minTotal')
     ->setParameter('minTotal', $this->minTotal);

Kombinasiya:
  PendingOrderSpec + HighValueSpec → 
  WHERE status = 'pending' AND total > 1000
```

---

## PHP İmplementasiyası

```php
<?php
// Base Specification interface
interface Specification
{
    public function isSatisfiedBy(mixed $candidate): bool;

    public function and(Specification $other): Specification;
    public function or(Specification $other): Specification;
    public function not(): Specification;
}

// Abstract base class — and/or/not metodları
abstract class AbstractSpecification implements Specification
{
    abstract public function isSatisfiedBy(mixed $candidate): bool;

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

// Composite specifications
class AndSpecification extends AbstractSpecification
{
    public function __construct(
        private Specification $left,
        private Specification $right,
    ) {}

    public function isSatisfiedBy(mixed $candidate): bool
    {
        return $this->left->isSatisfiedBy($candidate)
            && $this->right->isSatisfiedBy($candidate);
    }
}

class OrSpecification extends AbstractSpecification
{
    public function __construct(
        private Specification $left,
        private Specification $right,
    ) {}

    public function isSatisfiedBy(mixed $candidate): bool
    {
        return $this->left->isSatisfiedBy($candidate)
            || $this->right->isSatisfiedBy($candidate);
    }
}

class NotSpecification extends AbstractSpecification
{
    public function __construct(private Specification $spec) {}

    public function isSatisfiedBy(mixed $candidate): bool
    {
        return !$this->spec->isSatisfiedBy($candidate);
    }
}
```

```php
<?php
// Domain specifications
class PendingOrderSpecification extends AbstractSpecification
{
    public function isSatisfiedBy(mixed $order): bool
    {
        return $order->getStatus() === OrderStatus::PENDING;
    }
}

class HighValueOrderSpecification extends AbstractSpecification
{
    public function __construct(private int $minAmountCents) {}

    public function isSatisfiedBy(mixed $order): bool
    {
        return $order->getTotalCents() >= $this->minAmountCents;
    }
}

class PremiumCustomerOrderSpecification extends AbstractSpecification
{
    public function isSatisfiedBy(mixed $order): bool
    {
        return $order->getCustomer()->isPremium();
    }
}
```

```php
<?php
// Doctrine QueryBuilder ilə SQL generasiya
interface DoctrineSpecification extends Specification
{
    public function applyToQueryBuilder(QueryBuilder $qb, string $alias): void;
}

class PendingOrderDoctrineSpec extends AbstractSpecification
    implements DoctrineSpecification
{
    public function isSatisfiedBy(mixed $order): bool
    {
        return $order->getStatus() === OrderStatus::PENDING;
    }

    public function applyToQueryBuilder(QueryBuilder $qb, string $alias): void
    {
        $qb->andWhere("{$alias}.status = :status")
           ->setParameter('status', OrderStatus::PENDING->value);
    }
}

class AndDoctrineSpecification extends AbstractSpecification
    implements DoctrineSpecification
{
    public function __construct(
        private DoctrineSpecification $left,
        private DoctrineSpecification $right,
    ) {}

    public function isSatisfiedBy(mixed $candidate): bool
    {
        return $this->left->isSatisfiedBy($candidate)
            && $this->right->isSatisfiedBy($candidate);
    }

    public function applyToQueryBuilder(QueryBuilder $qb, string $alias): void
    {
        $this->left->applyToQueryBuilder($qb, $alias);
        $this->right->applyToQueryBuilder($qb, $alias);
    }
}

// Repository-da istifadə
class OrderRepository
{
    public function findBySpecification(DoctrineSpecification $spec): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('o')
            ->from(Order::class, 'o');

        $spec->applyToQueryBuilder($qb, 'o');

        return $qb->getQuery()->getResult();
    }
}

// İstifadə — Domain language!
$spec = new PendingOrderDoctrineSpec()
    ->and(new HighValueOrderDoctrineSpec(minAmountCents: 100000));

$orders = $orderRepo->findBySpecification($spec);
```

---

## İntervyu Sualları

- Specification pattern hansı problemi həll edir?
- `and()`, `or()`, `not()` metodları niyə base class-da saxlanır?
- In-memory filter vs SQL generation — nə vaxt hansını seçərsiniz?
- Specification DDD-nin hansı layer-ına aid edilir?
- Repository-yə specification qəbul etmək interface segregation baxımından necə dizayn edilməlidir?
- Specification pattern Strategy pattern-dən nəylə fərqlənir?
- Bu pattern over-engineering olduğu ssenarilər hansılardır?
