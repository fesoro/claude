# Domain Service vs Application Service

## Mündəricat
1. [Fərq nədir?](#fərq-nədir)
2. [Domain Service](#domain-service)
3. [Application Service](#application-service)
4. [Tələlər](#tələlər)
5. [PHP İmplementasiyası](#php-implementasiyası)
6. [İntervyu Sualları](#intervyu-sualları)

---

## Fərq nədir?

```
Çox developer bu ikisini qarışdırır.

Domain Service:
  Domain layer-da yaşayır.
  Biznes məntiqini ifadə edir.
  Infrastructure bilmir.
  Domain object-lər ilə işləyir.
  "Transferi yalnız eyni valyutada etmək olar" → domain qayda

Application Service:
  Application layer-da yaşayır.
  Use case-i orkestrasiya edir.
  Domain service + repository + event + transaction koordinasiya edir.
  Infrastructure-dan xəbərdardır (transaction, event bus).
  "Transfer et" use case-ni idarə edir.

Sual: "Bu kod domain məntiqi içərir, yoxsa use case koordinasiyasıdır?"
  Domain məntiqi → Domain Service
  Koordinasiya → Application Service
```

---

## Domain Service

```
Nə vaxt Domain Service lazımdır:
  Biznes məntiqi bir entity-yə "sığmır".
  Çox entity-ni əhatə edən qayda var.
  "Bu qaydanın adı domain dilindədir."

Domain Service xüsusiyyətləri:
  ✓ Stateless (state saxlamır)
  ✓ Domain object-lər qəbul edir, qaytarır
  ✓ Infrastructure yoxdur (DB, email, HTTP yoxdur)
  ✓ Domain language ilə adlandırılır
  ✗ Repository inject edilmir
  ✗ Event publish etmir

Nümunələr:
  MoneyTransferDomainService    → Transfer qaydaları
  PricingDomainService          → Qiymət hesablaması
  OrderEligibilityDomainService → Sifariş icazəsi
  TaxCalculationDomainService   → Vergi hesabı
```

---

## Application Service

```
Application Service xüsusiyyətləri:
  ✓ Use case-i orkestrasiya edir
  ✓ Repository-dən entity yükləyir
  ✓ Domain service-i çağırır
  ✓ Transaction idarəsi
  ✓ Event publish edir
  ✓ DTO-ları domain object-lərə çevirir
  ✗ Biznes məntiqi YOX (yalnız koordinasiya)
  ✗ Domain object-lər haqqında qərar vermir

Nümunələr:
  TransferMoneyApplicationService → "Transfer et" use case
  CreateOrderApplicationService   → "Sifariş yarat" use case
  ActivateUserApplicationService  → "User aktivləşdir" use case

Qayda:
  Application Service-i oxuyanda "nə" baş verdiyini görməlisən.
  "Necə" baş verdiyi Domain Service-dədir.
```

---

## Tələlər

```
Tələ 1 — Biznes məntiqini Application Service-ə qoymaq:
  class TransferService {
      public function transfer(...): void {
          if ($from->currency !== $to->currency) throw ...; // ← YANLIŞDIR
          if ($from->balance < $amount) throw ...;          // ← YANLIŞDIR
          // Bu qaydalar Domain-ə aiddir!
      }
  }

Düzgün:
  // Domain Service
  class MoneyTransferPolicy {
      public function canTransfer(Account $from, Account $to, Money $amount): void {
          if (!$from->currency->equals($to->currency)) throw new CurrencyMismatch();
          if ($from->balance()->lessThan($amount)) throw new InsufficientFunds();
      }
  }

Tələ 2 — Domain Service-ə repository inject etmək:
  class PricingService {
      public function __construct(
          private ProductRepository $products // ← YANLIŞDIR!
      ) {}
  }
  // Domain Service infrastructure bilməməlidir.
  // Repository Application Service-ə aiddir.

Tələ 3 — Hər şeyi service etmək (Anemic Domain):
  Order::calculateTotal() → Order entity-nin metodudur.
  OrderTotalCalculator service-ə çevirmək Anemic Domain yaradır.
  Entity öz məntiqini özü daşımalıdır.
```

---

## PHP İmplementasiyası

```php
<?php
// Domain Service — pure business logic
namespace App\Domain\Service;

class MoneyTransferPolicy
{
    /**
     * Transfer mümkündürmü? Domain qaydaları yoxlayır.
     * Infrastructure yoxdur, repository yoxdur.
     */
    public function validate(Account $from, Account $to, Money $amount): void
    {
        if (!$from->getCurrency()->equals($to->getCurrency())) {
            throw new CurrencyMismatchException(
                "Transfer yalnız eyni valyutada mümkündür"
            );
        }

        if ($from->getBalance()->lessThan($amount)) {
            throw new InsufficientFundsException(
                "Balans kifayət etmir: {$from->getBalance()} < {$amount}"
            );
        }

        if ($amount->isNegativeOrZero()) {
            throw new InvalidAmountException("Məbləğ müsbət olmalıdır");
        }

        if ($from->isFrozen() || $to->isFrozen()) {
            throw new AccountFrozenException("Hesab bloklanmışdır");
        }
    }
}
```

```php
<?php
// Application Service — use case orkestrasiyası
namespace App\Application\Transfer;

class TransferMoneyHandler
{
    public function __construct(
        private AccountRepository    $accounts,      // infrastructure
        private MoneyTransferPolicy  $policy,        // domain service
        private EventBus             $eventBus,      // infrastructure
    ) {}

    public function handle(TransferMoneyCommand $cmd): void
    {
        // 1. Entity-ləri yüklə (repository — infrastructure)
        $from = $this->accounts->findById($cmd->fromAccountId);
        $to   = $this->accounts->findById($cmd->toAccountId);

        if (!$from || !$to) {
            throw new AccountNotFoundException();
        }

        // 2. Domain service ilə biznes qaydaları yoxla
        $this->policy->validate($from, $to, $cmd->amount);

        // 3. Domain entity metodunu çağır (entity öz state-ini dəyişir)
        $from->debit($cmd->amount);
        $to->credit($cmd->amount);

        // 4. Persist et (repository)
        $this->accounts->save($from);
        $this->accounts->save($to);

        // 5. Domain event publish et
        $this->eventBus->publish(new MoneyTransferredEvent(
            fromAccountId: $cmd->fromAccountId,
            toAccountId:   $cmd->toAccountId,
            amount:        $cmd->amount,
        ));
    }
}
```

---

## İntervyu Sualları

- Domain Service vs Application Service — əsas fərq nədir?
- Domain Service-ə repository inject etmək niyə yanlışdır?
- "Anemic Domain Model" nədir? Bunu necə müəyyən edirsiniz?
- Qiymət hesablaması — Domain Service, yoxsa entity metodu?
- Application Service-dəki biznes məntiqini Domain Service-ə necə köçürərdiniz?
- Domain Service stateless olmalıdır — niyə?
