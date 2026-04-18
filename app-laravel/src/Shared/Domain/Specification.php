<?php

declare(strict_types=1);

namespace Src\Shared\Domain;

/**
 * SPECIFICATION PATTERN (DDD Pattern)
 * ====================================
 * Specification — biznes qaydalarını ayrı class-larda ifadə etmək üçün pattern.
 *
 * NƏDİR?
 * - Bir obyektin müəyyən şərtə uyğun olub-olmadığını yoxlayır.
 * - Hər qayda ayrı class-da olur → təmiz, test edilə bilən kod.
 *
 * NÜMUNƏ:
 * - OrderCanBeCancelledSpec: Sifariş ləğv edilə bilərmi?
 * - ProductIsInStockSpec: Məhsul stokda varmı?
 * - UserIsActiveSpec: İstifadəçi aktivdirmi?
 *
 * KOMBİNASİYA:
 * Specification-ları and(), or(), not() ilə birləşdirə bilərsən:
 * $canBuy = $isActive->and($hasBalance); // Aktiv VƏ balansı var
 *
 * NƏYƏ LAZIMDIR?
 * - if/else zəncirlərini azaldır.
 * - Qaydaları yenidən istifadə etmək (reuse) asanlaşır.
 * - Unit test yazmaq çox rahatdır.
 */
abstract class Specification
{
    /**
     * Obyektin bu qaydaya uyğun olub-olmadığını yoxla.
     */
    abstract public function isSatisfiedBy(mixed $candidate): bool;

    /**
     * AND — hər iki qayda doğru olmalıdır.
     * Məsələn: $isActive->and($hasBalance)
     */
    public function and(Specification $other): Specification
    {
        return new AndSpecification($this, $other);
    }

    /**
     * OR — ən azı biri doğru olmalıdır.
     */
    public function or(Specification $other): Specification
    {
        return new OrSpecification($this, $other);
    }

    /**
     * NOT — qaydanı tərsinə çevir.
     */
    public function not(): Specification
    {
        return new NotSpecification($this);
    }
}

/**
 * AND Specification — iki qaydanı "VƏ" ilə birləşdirir.
 */
class AndSpecification extends Specification
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

/**
 * OR Specification — iki qaydanı "VƏ YA" ilə birləşdirir.
 */
class OrSpecification extends Specification
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

/**
 * NOT Specification — qaydanı tərsinə çevirir.
 */
class NotSpecification extends Specification
{
    public function __construct(
        private Specification $spec,
    ) {}

    public function isSatisfiedBy(mixed $candidate): bool
    {
        return !$this->spec->isSatisfiedBy($candidate);
    }
}
