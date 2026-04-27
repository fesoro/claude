# Qiymət Hesablama Mühərriki (Senior)

## Problem Təsviri

E-commerce sistemlərində qiymət hesablaması ən çox yanlış dizayn edilən sahələrdən biridir. Tipik problemlər:

- Qiymət məntiqi controller, model, view və helper siniflərinə yayılıb
- Floating point xətaları (0.1 + 0.2 = 0.30000000000000004) maliyyə itkisinə səbəb olur
- Endirimlərin hansı sıra ilə tətbiq ediləcəyi aydın deyil
- Valyuta konvertasiyası harda baş verir — bilinmir
- Alış anındakı qiymət qeyd edilmir, tarixçə itirilir
- Unit test yazmaq demək olar ki, mümkün deyil

**Hədəf:** DDD yanaşması ilə izolə edilmiş, test edilə bilən, genişlənə bilən qiymət mühərriki qurmaq.

---

## 1. Floating Point Təhlükələri və Pul Nümayəndəliyi

### Problem

*Bu kod float tipinin maliyyə hesablamalarında yaratdığı dəqiqlik problemini göstərir:*

```php
<?php

// YANLIS - heç vaxt float istifadə etmə
$price = 19.99;
$tax   = $price * 0.18; // 3.5982000000000003
$total = $price + $tax; // 23.588200000000003

echo number_format($total, 2); // görünür düzgündür, amma daxildə deyil
```

### Həll 1: Tam ədəd sent (integer cents)

*Bu kod qiyməti tam ədəd sentdə saxlayıb yalnız göstərilmə zamanı çevirən düzgün yanaşmanı göstərir:*

```php
<?php

// DOGRU - sentdə saxla, yalnız göstərərkən çevir
$priceInCents = 1999; // 19.99 AZN
$taxInCents   = (int) round($priceInCents * 0.18); // 360 sent
$totalInCents = $priceInCents + $taxInCents;       // 2359 sent

echo number_format($totalInCents / 100, 2); // 23.59
```

### Həll 2: BCMath ilə yüksək dəqiqlik

*Bu kod BCMath kitabxanası ilə yüksək dəqiqlikli string əsaslı pul hesablamalarını göstərir:*

```php
<?php

// Çox böyük məbləğlər və ya mürəkkəb hesablamalar üçün
$price = '19.99';
$rate  = '0.18';

$tax   = bcmul($price, $rate, 4);       // "3.5982"
$total = bcadd($price, $tax, 2);        // "23.59"

// Müqayisə
if (bccomp($total, '23.59', 2) === 0) {
    echo 'Bərabərdir';
}
```

---

## 2. Value Object: Money Pattern

Domain modelinin əsası — `Money` value object. Məbləğ və valyutanı birlikdə daşıyır, immutable-dır.

*Bu kod dəstəklənən valyutaları yoxlayan immutable Currency value object-ini göstərir:*

```php
<?php

declare(strict_types=1);

namespace App\Pricing\Domain\ValueObject;

use InvalidArgumentException;

final class Currency
{
    private const SUPPORTED = ['AZN', 'USD', 'EUR', 'TRY'];

    public function __construct(private readonly string $code)
    {
        if (!in_array($code, self::SUPPORTED, true)) {
            throw new InvalidArgumentException("Dəstəklənməyən valyuta: {$code}");
        }
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function equals(Currency $other): bool
    {
        return $this->code === $other->code;
    }

    public function __toString(): string
    {
        return $this->code;
    }
}
```

*Bu kod sentdə saxlanan, toplama/çıxma/faiz əməliyyatlarını dəstəkləyən immutable Money value object-ini göstərir:*

```php
<?php

declare(strict_types=1);

namespace App\Pricing\Domain\ValueObject;

use InvalidArgumentException;
use OverflowException;

final class Money
{
    // Sentdə saxlanılır — tam ədəd
    public function __construct(
        private readonly int      $amount,   // məs: 1999 = 19.99
        private readonly Currency $currency,
    ) {
        if ($amount < 0) {
            throw new InvalidArgumentException('Mənfi məbləğ qəbul edilmir.');
        }
    }

    public static function of(int $amount, string $currencyCode): self
    {
        return new self($amount, new Currency($currencyCode));
    }

    // Formatlanmış stringdən yarat: "19.99"
    public static function fromDecimalString(string $decimal, string $currencyCode): self
    {
        $cents = (int) round(bcmul($decimal, '100', 0));
        return new self($cents, new Currency($currencyCode));
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function getCurrency(): Currency
    {
        return $this->currency;
    }

    public function toDecimalString(): string
    {
        return number_format($this->amount / 100, 2, '.', '');
    }

    public function add(Money $other): self
    {
        $this->assertSameCurrency($other);
        return new self($this->amount + $other->amount, $this->currency);
    }

    public function subtract(Money $other): self
    {
        $this->assertSameCurrency($other);
        $result = $this->amount - $other->amount;
        if ($result < 0) {
            throw new \UnderflowException('Mənfi nəticə.');
        }
        return new self($result, $this->currency);
    }

    public function multiplyByFloat(float $multiplier, int $roundingMode = PHP_ROUND_HALF_UP): self
    {
        $result = (int) round($this->amount * $multiplier, 0, $roundingMode);
        return new self($result, $this->currency);
    }

    public function percentage(int $percent): self
    {
        // Tam ədəd faiz — daha dəqiq
        $result = (int) round($this->amount * $percent / 100, 0, PHP_ROUND_HALF_UP);
        return new self($result, $this->currency);
    }

    public function isGreaterThan(Money $other): bool
    {
        $this->assertSameCurrency($other);
        return $this->amount > $other->amount;
    }

    public function isLessThan(Money $other): bool
    {
        $this->assertSameCurrency($other);
        return $this->amount < $other->amount;
    }

    public function equals(Money $other): bool
    {
        return $this->amount === $other->amount
            && $this->currency->equals($other->currency);
    }

    public function isZero(): bool
    {
        return $this->amount === 0;
    }

    private function assertSameCurrency(Money $other): void
    {
        if (!$this->currency->equals($other->currency)) {
            throw new InvalidArgumentException(
                "Valyuta uyğunsuzluğu: {$this->currency} vs {$other->currency}"
            );
        }
    }
}
```

---

## 3. Domain Model: Price və PriceContext

*Bu kod əsas qiymət, son qiymət, endirim və vergi məlumatlarını birlikdə saxlayan Price value object-ini göstərir:*

```php
<?php

declare(strict_types=1);

namespace App\Pricing\Domain\ValueObject;

final class Price
{
    public function __construct(
        private readonly Money    $baseAmount,
        private readonly Money    $finalAmount,
        private readonly Money    $discountAmount,
        private readonly Money    $taxAmount,
        private readonly string   $currencyCode,
        private readonly array    $appliedRules = [],  // audit üçün
    ) {}

    public static function fromBase(Money $base): self
    {
        $zero = Money::of(0, $base->getCurrency()->getCode());
        return new self($base, $base, $zero, $zero, $base->getCurrency()->getCode());
    }

    public function getBase(): Money      { return $this->baseAmount; }
    public function getFinal(): Money     { return $this->finalAmount; }
    public function getDiscount(): Money  { return $this->discountAmount; }
    public function getTax(): Money       { return $this->taxAmount; }
    public function getAppliedRules(): array { return $this->appliedRules; }

    public function withDiscount(Money $discount, string $ruleName): self
    {
        $newFinal = $this->finalAmount->subtract($discount);
        $newDiscount = $this->discountAmount->add($discount);
        return new self(
            $this->baseAmount,
            $newFinal,
            $newDiscount,
            $this->taxAmount,
            $this->currencyCode,
            array_merge($this->appliedRules, [$ruleName]),
        );
    }

    public function withTax(Money $tax): self
    {
        return new self(
            $this->baseAmount,
            $this->finalAmount->add($tax),
            $this->discountAmount,
            $tax,
            $this->currencyCode,
            $this->appliedRules,
        );
    }
}
```

*Bu kod qiymət hesablaması üçün lazımi kontekst məlumatlarını — istifadəçi, tarix, valyuta, miqdar — saxlayan immutable sinfi göstərir:*

```php
<?php

declare(strict_types=1);

namespace App\Pricing\Domain;

use App\Pricing\Domain\ValueObject\Money;

// Hesablama konteksti — kim alır, nə vaxt, hansı şərtlər var
final class PricingContext
{
    public function __construct(
        public readonly string    $userId,
        public readonly string    $userTier,       // 'bronze', 'silver', 'gold'
        public readonly \DateTimeImmutable $purchaseAt,
        public readonly string    $currencyCode,
        public readonly int       $quantity,
        public readonly array     $appliedCoupons = [],
    ) {}
}
```

---

## 4. Qiymət Qaydaları üçün Strategy Pattern

Her endirim növü ayrı strategiya sinfindədir. Hamısı eyni interface-i implement edir.

*Bu kod hər endirim növü üçün tətbiq oluna bilərlik yoxlaması, tətbiq əməliyyatı və prioritet müəyyən edən strategy interface-ini göstərir:*

```php
<?php

declare(strict_types=1);

namespace App\Pricing\Domain\Rule;

use App\Pricing\Domain\ValueObject\Price;
use App\Pricing\Domain\PricingContext;

interface PricingRuleInterface
{
    public function getName(): string;

    public function isApplicable(Price $price, PricingContext $context): bool;

    public function apply(Price $price, PricingContext $context): Price;

    public function getPriority(): int; // kiçik = əvvəl tətbiq olunur
}
```

### Həcm endirimi qaydası

*Bu kod alınan miqdar artdıqca faiz endirimi tətbiq edən həcm endirimi qaydasını göstərir:*

```php
<?php

declare(strict_types=1);

namespace App\Pricing\Domain\Rule;

use App\Pricing\Domain\ValueObject\Price;
use App\Pricing\Domain\PricingContext;

final class VolumeDiscountRule implements PricingRuleInterface
{
    // Həcm → faiz: 10+ ədəd → 5%, 50+ → 10%, 100+ → 15%
    private const TIERS = [
        100 => 15,
        50  => 10,
        10  => 5,
    ];

    public function getName(): string
    {
        return 'volume_discount';
    }

    public function getPriority(): int
    {
        return 10; // Baza endirimlər əvvəl
    }

    public function isApplicable(Price $price, PricingContext $context): bool
    {
        return $context->quantity >= 10;
    }

    public function apply(Price $price, PricingContext $context): Price
    {
        $discountPercent = $this->resolvePercent($context->quantity);
        $discount = $price->getFinal()->percentage($discountPercent);
        return $price->withDiscount($discount, $this->getName());
    }

    private function resolvePercent(int $quantity): int
    {
        foreach (self::TIERS as $minQty => $percent) {
            if ($quantity >= $minQty) {
                return $percent;
            }
        }
        return 0;
    }
}
```

### Kupon endirimi qaydası

*Bu kod kupon kodunu yoxlayıb faizli və ya sabit endirimi qiymətə tətbiq edən qaydanı göstərir:*

```php
<?php

declare(strict_types=1);

namespace App\Pricing\Domain\Rule;

use App\Pricing\Domain\ValueObject\Price;
use App\Pricing\Domain\ValueObject\Money;
use App\Pricing\Domain\PricingContext;
use App\Pricing\Domain\Entity\Coupon;
use App\Pricing\Domain\Repository\CouponRepositoryInterface;

final class CouponDiscountRule implements PricingRuleInterface
{
    public function __construct(
        private readonly CouponRepositoryInterface $coupons,
    ) {}

    public function getName(): string
    {
        return 'coupon_discount';
    }

    public function getPriority(): int
    {
        return 20; // Kupon həcm endirimi sonra tətbiq olunur
    }

    public function isApplicable(Price $price, PricingContext $context): bool
    {
        return count($context->appliedCoupons) > 0;
    }

    public function apply(Price $price, PricingContext $context): Price
    {
        foreach ($context->appliedCoupons as $couponCode) {
            $coupon = $this->coupons->findByCode($couponCode);

            if ($coupon === null || !$coupon->isValid($context->purchaseAt)) {
                continue;
            }

            $discount = $this->calculateDiscount($price, $coupon);
            $price = $price->withDiscount($discount, "coupon:{$couponCode}");
        }

        return $price;
    }

    private function calculateDiscount(Price $price, Coupon $coupon): Money
    {
        return match ($coupon->getType()) {
            'percentage' => $price->getFinal()->percentage($coupon->getValue()),
            'fixed'      => Money::of($coupon->getValue(), $price->getFinal()->getCurrency()->getCode()),
            default      => Money::of(0, $price->getFinal()->getCurrency()->getCode()),
        };
    }
}
```

### İstifadəçi tier endirimi

*Bu kod istifadəçinin bronze/silver/gold səviyyəsinə görə müəyyən faiz endirimi tətbiq edən qaydanı göstərir:*

```php
<?php

declare(strict_types=1);

namespace App\Pricing\Domain\Rule;

use App\Pricing\Domain\ValueObject\Price;
use App\Pricing\Domain\PricingContext;

final class UserTierDiscountRule implements PricingRuleInterface
{
    private const TIER_DISCOUNTS = [
        'gold'   => 10,
        'silver' => 5,
        'bronze' => 2,
    ];

    public function getName(): string
    {
        return 'user_tier_discount';
    }

    public function getPriority(): int
    {
        return 15;
    }

    public function isApplicable(Price $price, PricingContext $context): bool
    {
        return isset(self::TIER_DISCOUNTS[$context->userTier]);
    }

    public function apply(Price $price, PricingContext $context): Price
    {
        $percent = self::TIER_DISCOUNTS[$context->userTier];
        $discount = $price->getFinal()->percentage($percent);
        return $price->withDiscount($discount, "tier:{$context->userTier}");
    }
}
```

### Vaxta əsaslı qiymət (Flash sale)

*Bu kod flash sale müddətindəki alışlara vaxt aralığı yoxlaması ilə endirim tətbiq edən qaydanı göstərir:*

```php
<?php

declare(strict_types=1);

namespace App\Pricing\Domain\Rule;

use App\Pricing\Domain\ValueObject\Price;
use App\Pricing\Domain\PricingContext;

final class TimeBasedPricingRule implements PricingRuleInterface
{
    public function __construct(
        private readonly \DateTimeImmutable $saleStart,
        private readonly \DateTimeImmutable $saleEnd,
        private readonly int                $discountPercent,
    ) {}

    public function getName(): string
    {
        return 'time_based_sale';
    }

    public function getPriority(): int
    {
        return 5; // Ən əvvəl tətbiq olunur
    }

    public function isApplicable(Price $price, PricingContext $context): bool
    {
        return $context->purchaseAt >= $this->saleStart
            && $context->purchaseAt <= $this->saleEnd;
    }

    public function apply(Price $price, PricingContext $context): Price
    {
        $discount = $price->getFinal()->percentage($this->discountPercent);
        return $price->withDiscount($discount, $this->getName());
    }
}
```

---

## 5. Chain of Responsibility: Qaydaları Sıra ilə Tətbiq Etmək

*Bu kod bütün qiymət qaydalarını prioritet sırası ilə tətbiq edən chain of responsibility-ni göstərir:*

```php
<?php

declare(strict_types=1);

namespace App\Pricing\Domain;

use App\Pricing\Domain\Rule\PricingRuleInterface;
use App\Pricing\Domain\ValueObject\Price;

final class PricingRuleChain
{
    /** @var PricingRuleInterface[] */
    private array $rules = [];

    public function addRule(PricingRuleInterface $rule): self
    {
        $this->rules[] = $rule;
        // Prioritetə görə sırala — kiçik prioritet əvvəl
        usort($this->rules, fn($a, $b) => $a->getPriority() <=> $b->getPriority());
        return $this;
    }

    public function process(Price $price, PricingContext $context): Price
    {
        foreach ($this->rules as $rule) {
            if ($rule->isApplicable($price, $context)) {
                $price = $rule->apply($price, $context);
            }
        }
        return $price;
    }
}
```

### Qaydaların yığılması (stacking) nəzarəti

*Bu kod ümumi endirimin müəyyən faizi aşmamasını təmin edən maksimum endirim limiti dekoratorunu göstərir:*

```php
<?php

declare(strict_types=1);

namespace App\Pricing\Domain;

use App\Pricing\Domain\Rule\PricingRuleInterface;
use App\Pricing\Domain\ValueObject\Price;
use App\Pricing\Domain\ValueObject\Money;

// Maksimum endirim həddini idarə edən dekorator
final class CappedDiscountChain
{
    public function __construct(
        private readonly PricingRuleChain $chain,
        private readonly int              $maxDiscountPercent = 40,
    ) {}

    public function process(Price $price, PricingContext $context): Price
    {
        $result = $this->chain->process($price, $context);

        // Maksimum endirim həddini yoxla
        $maxDiscount = $price->getBase()->percentage($this->maxDiscountPercent);

        if ($result->getDiscount()->isGreaterThan($maxDiscount)) {
            // Endirimi məhdudlaşdır
            $cappedFinal = $price->getBase()->subtract($maxDiscount);
            return Price::fromBase($price->getBase())
                ->withDiscount($maxDiscount, 'discount_cap_applied');
        }

        return $result;
    }
}
```

---

## 6. Vergi Hesablaması

*Bu kod vergidən kənar (exclusive) və vergidaxili (inclusive) vergi hesablama strategiyalarını göstərir:*

```php
<?php

declare(strict_types=1);

namespace App\Pricing\Domain\Tax;

use App\Pricing\Domain\ValueObject\Money;
use App\Pricing\Domain\ValueObject\Price;

interface TaxStrategyInterface
{
    public function calculate(Money $amount): Money;
    public function getName(): string;
}

// Vergidən kənar (exclusive) — vergi üstəlnir
final class ExclusiveTaxStrategy implements TaxStrategyInterface
{
    public function __construct(
        private readonly int    $ratePercent,  // 18 = 18%
        private readonly string $name = 'VAT',
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function calculate(Money $amount): Money
    {
        return $amount->percentage($this->ratePercent);
    }
}

// Vergidaxili (inclusive) — məbləğin içindən vergi çıxarılır
final class InclusiveTaxStrategy implements TaxStrategyInterface
{
    public function __construct(
        private readonly int    $ratePercent,
        private readonly string $name = 'VAT_inclusive',
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function calculate(Money $amount): Money
    {
        // Vergidaxili: tax = amount * rate / (100 + rate)
        $taxAmount = (int) round(
            $amount->getAmount() * $this->ratePercent / (100 + $this->ratePercent),
            0,
            PHP_ROUND_HALF_UP
        );
        return Money::of($taxAmount, $amount->getCurrency()->getCode());
    }
}
```

*Bu kod bir neçə vergi strategiyasını toplayıb qiymətə əlavə edən compound vergi kalkulyatorunu göstərir:*

```php
<?php

declare(strict_types=1);

namespace App\Pricing\Domain\Tax;

use App\Pricing\Domain\ValueObject\Price;
use App\Pricing\Domain\ValueObject\Money;

// Çoxlu vergi dərəcələri (məs: əsas ƏDV + bələdiyyə vergisi)
final class CompoundTaxCalculator
{
    /** @var TaxStrategyInterface[] */
    private array $strategies = [];

    public function addStrategy(TaxStrategyInterface $strategy): self
    {
        $this->strategies[] = $strategy;
        return $this;
    }

    public function applyTo(Price $price): Price
    {
        $totalTax = Money::of(0, $price->getFinal()->getCurrency()->getCode());

        foreach ($this->strategies as $strategy) {
            $tax = $strategy->calculate($price->getFinal());
            $totalTax = $totalTax->add($tax);
        }

        return $price->withTax($totalTax);
    }
}
```

---

## 7. Valyuta Konvertasiyası

*Bu kod valyuta məzənnəsini alıb BCMath ilə dəqiq valyuta konvertasiyası aparan sinfi göstərir:*

```php
<?php

declare(strict_types=1);

namespace App\Pricing\Domain\Currency;

use App\Pricing\Domain\ValueObject\Money;
use App\Pricing\Domain\ValueObject\Currency;

interface ExchangeRateProviderInterface
{
    public function getRate(Currency $from, Currency $to): string; // BCMath üçün string
}

final class CurrencyConverter
{
    public function __construct(
        private readonly ExchangeRateProviderInterface $rateProvider,
    ) {}

    public function convert(Money $money, string $targetCurrencyCode): Money
    {
        $targetCurrency = new Currency($targetCurrencyCode);

        if ($money->getCurrency()->equals($targetCurrency)) {
            return $money; // Eyni valyuta — çevirmə lazım deyil
        }

        $rate = $this->rateProvider->getRate($money->getCurrency(), $targetCurrency);

        // BCMath ilə dəqiq çevirmə
        $convertedAmount = bcmul((string) $money->getAmount(), $rate, 0);

        return Money::of((int) $convertedAmount, $targetCurrencyCode);
    }
}
```

*Bu kod valyuta məzənnəsini cache-ə alıb təkrarlanan API çağırışlarını önləyən decorator-u göstərir:*

```php
<?php

declare(strict_types=1);

namespace App\Pricing\Infrastructure\Currency;

use App\Pricing\Domain\Currency\ExchangeRateProviderInterface;
use App\Pricing\Domain\ValueObject\Currency;
use Psr\Cache\CacheItemPoolInterface;

// Məzənnəni cache ilə saxlayan real implementation
final class CachedExchangeRateProvider implements ExchangeRateProviderInterface
{
    public function __construct(
        private readonly ExchangeRateProviderInterface $inner,
        private readonly CacheItemPoolInterface        $cache,
        private readonly int                           $ttl = 3600, // 1 saat
    ) {}

    public function getRate(Currency $from, Currency $to): string
    {
        $cacheKey = "exchange_rate_{$from}_{$to}";
        $item = $this->cache->getItem($cacheKey);

        if ($item->isHit()) {
            return $item->get();
        }

        $rate = $this->inner->getRate($from, $to);

        $item->set($rate)->expiresAfter($this->ttl);
        $this->cache->save($item);

        return $rate;
    }
}
```

**Konversiyanın harada tətbiq edilməsi:** Həmişə prezentasiya layerinde (son göstərişdə) valyuta çevrilməsi aparılmalıdır. Domain daxilindəki bütün hesablamalar eyni valyutada (baza valyutasında) aparılmalıdır.

---

## 8. Qiymət Snapshot-u

Alış anındakı qiyməti saxlamaq tarixi mühimdir — qiymətlər dəyişsə belə köhnə sifarişlər düzgün qalır.

*Bu kod alış anındakı bütün qiymət detallarını dəyişilməz şəkildə qeydə alan qiymət snapshot-unu göstərir:*

```php
<?php

declare(strict_types=1);

namespace App\Pricing\Domain\Snapshot;

use App\Pricing\Domain\ValueObject\Price;

final class PriceSnapshot
{
    public function __construct(
        public readonly int       $amountCents,
        public readonly string    $currency,
        public readonly int       $discountCents,
        public readonly int       $taxCents,
        public readonly int       $finalCents,
        public readonly array     $appliedRules,
        public readonly \DateTimeImmutable $capturedAt,
        public readonly string    $snapshotId,   // idempotency üçün UUID
    ) {}

    public static function capture(Price $price): self
    {
        return new self(
            amountCents:   $price->getBase()->getAmount(),
            currency:      $price->getBase()->getCurrency()->getCode(),
            discountCents: $price->getDiscount()->getAmount(),
            taxCents:      $price->getTax()->getAmount(),
            finalCents:    $price->getFinal()->getAmount(),
            appliedRules:  $price->getAppliedRules(),
            capturedAt:    new \DateTimeImmutable(),
            snapshotId:    \Ramsey\Uuid\Uuid::uuid4()->toString(),
        );
    }

    public function toArray(): array
    {
        return [
            'amount_cents'   => $this->amountCents,
            'currency'       => $this->currency,
            'discount_cents' => $this->discountCents,
            'tax_cents'      => $this->taxCents,
            'final_cents'    => $this->finalCents,
            'applied_rules'  => $this->appliedRules,
            'captured_at'    => $this->capturedAt->format(\DateTimeInterface::ATOM),
            'snapshot_id'    => $this->snapshotId,
        ];
    }
}
```

**Verilənlər bazasında saxlama:** `orders` cədvəlində `price_snapshot JSON` sütunu. Sifariş tamamlandıqdan sonra bu snapshot dəyişdirilmir.

---

## 9. Promosyon Mühərriki — Uyğunluq Qaydaları

*Bu kod minimum alış məbləği yoxlaması və mürəkkəb AND şərtlərini dəstəkləyən promosyon uyğunluq yoxlayıcısını göstərir:*

```php
<?php

declare(strict_types=1);

namespace App\Pricing\Domain\Promotion;

use App\Pricing\Domain\PricingContext;
use App\Pricing\Domain\ValueObject\Price;

interface PromotionEligibilityInterface
{
    public function isEligible(PricingContext $context, Price $price): bool;
}

// Minimum alış məbləği şərti
final class MinimumPurchaseEligibility implements PromotionEligibilityInterface
{
    public function __construct(
        private readonly int    $minimumCents,
        private readonly string $currency,
    ) {}

    public function isEligible(PricingContext $context, Price $price): bool
    {
        return $price->getFinal()->getAmount() >= $this->minimumCents
            && $price->getFinal()->getCurrency()->getCode() === $this->currency;
    }
}

// Kombinasiya — bütün şərtlər ödənilməli
final class CompositeEligibility implements PromotionEligibilityInterface
{
    /** @var PromotionEligibilityInterface[] */
    private array $conditions;

    public function __construct(PromotionEligibilityInterface ...$conditions)
    {
        $this->conditions = $conditions;
    }

    public function isEligible(PricingContext $context, Price $price): bool
    {
        foreach ($this->conditions as $condition) {
            if (!$condition->isEligible($context, $price)) {
                return false;
            }
        }
        return true;
    }
}
```

---

## 10. Qiymət Mühərriki — Hər Şeyi Birləşdirən Fasad

*Bu kod endirimlər, vergi və snapshot-u ardıcıl tətbiq edən mərkəzi qiymət hesablama mühərrikini göstərir:*

```php
<?php

declare(strict_types=1);

namespace App\Pricing\Application;

use App\Pricing\Domain\PricingContext;
use App\Pricing\Domain\PricingRuleChain;
use App\Pricing\Domain\CappedDiscountChain;
use App\Pricing\Domain\Tax\CompoundTaxCalculator;
use App\Pricing\Domain\ValueObject\Price;
use App\Pricing\Domain\ValueObject\Money;
use App\Pricing\Domain\Snapshot\PriceSnapshot;
use App\Pricing\Domain\Currency\CurrencyConverter;

final class PriceCalculationEngine
{
    public function __construct(
        private readonly CappedDiscountChain   $discountChain,
        private readonly CompoundTaxCalculator $taxCalculator,
        private readonly CurrencyConverter     $currencyConverter,
    ) {}

    public function calculate(
        Money          $basePrice,
        PricingContext $context,
    ): PriceCalculationResult {
        // 1. Baza qiymətindən başla
        $price = Price::fromBase($basePrice);

        // 2. Endirimləri tətbiq et (chain of responsibility)
        $price = $this->discountChain->process($price, $context);

        // 3. Vergi hesabla
        $price = $this->taxCalculator->applyTo($price);

        // 4. Snapshot çək
        $snapshot = PriceSnapshot::capture($price);

        return new PriceCalculationResult($price, $snapshot);
    }
}
```

*return new PriceCalculationResult($price, $snapshot); üçün kod nümunəsi:*
```php
<?php

declare(strict_types=1);

namespace App\Pricing\Application;

use App\Pricing\Domain\ValueObject\Price;
use App\Pricing\Domain\Snapshot\PriceSnapshot;

final class PriceCalculationResult
{
    public function __construct(
        public readonly Price         $price,
        public readonly PriceSnapshot $snapshot,
    ) {}
}
```

---

## 11. Real Ssenari: E-Commerce Səbəti Hesablaması

*11. Real Ssenari: E-Commerce Səbəti Hesablaması üçün kod nümunəsi:*
```php
<?php

declare(strict_types=1);

namespace App\Pricing\Application;

use App\Pricing\Domain\PricingContext;
use App\Pricing\Domain\ValueObject\Money;

// Ssenari: Qızıl üzv 25 ədəd məhsul alır, kupon tətbiq edir, flash sale davam edir

$basePrice = Money::of(5000, 'AZN'); // 50.00 AZN per item

$context = new PricingContext(
    userId:         'user-123',
    userTier:       'gold',
    purchaseAt:     new \DateTimeImmutable('2026-04-09 14:00:00'),
    currencyCode:   'AZN',
    quantity:       25,
    appliedCoupons: ['SUMMER20'],
);

/** @var PriceCalculationEngine $engine — DI container tərəfindən inject edilir */
$result = $engine->calculate($basePrice, $context);

$price    = $result->price;
$snapshot = $result->snapshot;

echo "Baza qiymət:  " . $price->getBase()->toDecimalString() . " AZN\n";
// Baza qiymət:  50.00 AZN

echo "Endirim:      " . $price->getDiscount()->toDecimalString() . " AZN\n";
// Endirim:      (flash 5% + volume 10% + gold 10% + kupon 20% — cap 40% tətbiq olunur)

echo "ƏDV (18%):    " . $price->getTax()->toDecimalString() . " AZN\n";
echo "Yekun:        " . $price->getFinal()->toDecimalString() . " AZN\n";
echo "Qaydalar:     " . implode(', ', $price->getAppliedRules()) . "\n";
echo "Snapshot ID:  " . $snapshot->snapshotId . "\n";

// Snapshot-u sifariş ilə birlikdə saxla
$order->setPriceSnapshot($snapshot->toArray());
```

### Tətbiq olunan qaydaların sırası

```
1. time_based_sale      (priority: 5)  → 5%  endirim
2. volume_discount      (priority: 10) → 10% endirim (25 ədəd)
3. user_tier_discount   (priority: 15) → 10% endirim (gold)
4. coupon_discount      (priority: 20) → 20% endirim (SUMMER20)
   Yığılmış endirim: ~40%+ → cap tətbiq olunur: 40%
5. tax_calculator                      → 18% ƏDV əlavə olunur
```

---

## 12. Unit Test Nümunəsi

*12. Unit Test Nümunəsi üçün kod nümunəsi:*
```php
<?php

declare(strict_types=1);

namespace Tests\Pricing\Domain;

use App\Pricing\Domain\ValueObject\Money;
use App\Pricing\Domain\ValueObject\Currency;
use App\Pricing\Domain\ValueObject\Price;
use App\Pricing\Domain\PricingContext;
use App\Pricing\Domain\Rule\VolumeDiscountRule;
use PHPUnit\Framework\TestCase;

final class VolumeDiscountRuleTest extends TestCase
{
    private VolumeDiscountRule $rule;

    protected function setUp(): void
    {
        $this->rule = new VolumeDiscountRule();
    }

    public function test_not_applicable_below_minimum_quantity(): void
    {
        $context = $this->makeContext(quantity: 5);
        $price   = Price::fromBase(Money::of(10000, 'AZN'));

        $this->assertFalse($this->rule->isApplicable($price, $context));
    }

    public function test_applies_five_percent_for_ten_to_forty_nine(): void
    {
        $context = $this->makeContext(quantity: 15);
        $price   = Price::fromBase(Money::of(10000, 'AZN')); // 100.00 AZN

        $result = $this->rule->apply($price, $context);

        // 5% endirim = 500 sent = 5.00 AZN
        $this->assertSame(500, $result->getDiscount()->getAmount());
        $this->assertSame(9500, $result->getFinal()->getAmount());
    }

    public function test_applies_ten_percent_for_fifty_or_more(): void
    {
        $context = $this->makeContext(quantity: 50);
        $price   = Price::fromBase(Money::of(10000, 'AZN'));

        $result = $this->rule->apply($price, $context);

        $this->assertSame(1000, $result->getDiscount()->getAmount()); // 10%
    }

    public function test_rule_name_is_stable(): void
    {
        $this->assertSame('volume_discount', $this->rule->getName());
    }

    private function makeContext(int $quantity): PricingContext
    {
        return new PricingContext(
            userId:         'u1',
            userTier:       'bronze',
            purchaseAt:     new \DateTimeImmutable(),
            currencyCode:   'AZN',
            quantity:       $quantity,
        );
    }
}
```

---

## Əsas Nəticələr

### DDD Prinsipləri

- **Value Object** — `Money` və `Currency` immutable-dır, equality dəyərə görədir
- **Domain Service** — `PriceCalculationEngine` hesablama məntiqini birləşdirir
- **Ubiquitous Language** — `Price`, `Discount`, `TaxStrategy`, `PricingContext` — domain dili ilə adlanır

### Texniki Qərarlar

| Mövzu | Yanlış yanaşma | Düzgün yanaşma |
|---|---|---|
| Pul nümayəndəliyi | `float` | Integer cents və ya BCMath |
| Endirim məntiqi | `if-else` zinciri | Strategy pattern |
| Qaydaların sırası | Hardcoded | Priority ilə sıralama |
| Vergi daxilliyi | Bir dərəcə | `InclusiveTaxStrategy` / `ExclusiveTaxStrategy` |
| Valyuta çevirmə | Domain daxilində | Prezentasiya layerında |
| Alış tarixi qiymət | Cari qiymət | Snapshot pattern |

### Performans Məsləhətləri

- Məzənnələri cache-ləyin (TTL: 1 saat)
- Qiymət qaydalarını verilənlər bazasından lazım olduqda yükləyin, hər sorğuda yox
- `PriceSnapshot`-u sifariş ilə eyni transaction-da saxlayın

### İnterview Sualları

1. **"Float niyə istifadə etməməliyik?"** — IEEE 754 binary float dəqiqlik itkisi, 0.1 + 0.2 ≠ 0.3
2. **"Endirimlərin sırası niyə vacibdir?"** — 50 AZN-ə əvvəlcə 10% sonra 20% tətbiq etmək, əvvəlcə 20% sonra 10% tətbiqindən fərqlidir
3. **"Snapshot niyə lazımdır?"** — Qiymət dəyişsə belə keçmiş sifarişlər qorunur, audit trail var
4. **"Chain of Responsibility nə zaman Strategy-dən üstündür?"** — Bir neçə qayda ardıcıl tətbiq olunmalı olduqda, hər qayda əvvəlkinin nəticəsi üzərindən işlədikdə
5. **"Inclusive vs Exclusive ƏDV fərqi nədir?"** — Exclusive: qiymətə üstəlnir (50 AZN + 18% = 59 AZN). Inclusive: qiymət artıq ƏDV daxildir (59 AZN-nin içindən 18% = 9 AZN). Formula: `tax = amount * rate / (100 + rate)`. B2C-də adətən inclusive, B2B-də exclusive.
6. **"Endirim cap-i niyə lazımdır?"** — Bir neçə qayda üst-üstə gəldikdə toplam endirim baza qiymətini aşa bilər (ya da gəliri kəskin azaldır). `CappedDiscountChain` maksimum endirim faizini tətbiq edir. Tipik cap: 30–50%. Business requirement-dən asılı.

---

## Anti-patternlər

**1. Qiymət hesablamalarında float istifadə etmək**
`float` tipli dəyişənlərlə pul hesablamaları aparmaq — IEEE 754 binary float dəqiqlik itkisi yaradır (`0.1 + 0.2 = 0.30000000000000004`), maliyyə hesabatlarında yanlış məbləğlər görünür. Integer cents (qəpik) və ya `BCMath` istifadə et, heç vaxt `float` ilə pul hesablama.

**2. Endirim qaydalarını hardcoded if-else zənciri ilə yazmaq**
Hər yeni endirim növü üçün mövcud koda `if ($type === 'vip') ... elseif ($type === 'seasonal') ...` əlavə etmək — kod böyüdükcə dəyişikliklər çətinləşir, yeni qayda əlavə etmək mövcudu sındırma riski daşıyır. Strategy pattern tətbiq et, hər endirim növü ayrı class olsun.

**3. Endirimlər tətbiq edilərkən sıraya əhəmiyyət verməmək**
Eyni məbləğə ardıcıl endirimlərin hansı sırayla tətbiq olunduğunu nəzərə almamaq — 100 AZN-ə əvvəl 10%, sonra 20% tətbiq etmək ilə əvvəl 20%, sonra 10% fərqli nəticə verir. Qaydalara priority təyin et, müəyyən sıra ilə tətbiq et, bu sıranı sənədləşdir.

**4. Sifarişdə qiymət snapshot-u saxlamamaq**
Sifarişi yaradarkən yalnız `product_id` saxlamaq, qiymət məlumatını saxlamamaq — məhsulun qiyməti sonra dəyişsə keçmiş sifarişlər yanlış məbləğ göstərir, audit trail yoxdur. Sifarişlə eyni transaction-da `PriceSnapshot` yarat, o andakı qiyməti, vergi dərəcəsini, tətbiq edilmiş endirimi qeyd et.

**5. Məzənnələri hər qiymət hesablamasında API-dən çəkmək**
Valyuta çevirmə üçün hər request-də xarici məzənnə API-sinə sorğu atmaq — API gecikmə yaradır, rate limit aşılır, API down olarsa qiymət hesablamaları çökür. Məzənnələri Redis-də cache-lə (TTL: 1 saat), background job ilə yenilə.

**6. Valyuta çevirməni domain logic içinde etmək**
`Money` obyektini hesablama zamanı fərqli valyutaya çevirmək — hesablama məntiqi valyuta kontekstindən asılı olur, test etmək çətinləşir, çevirmə xətaları qiymətə sirayət edir. Valyuta çevirməsini prezentasiya layerında et; domain logic həmişə əsas valyutada işlə.
