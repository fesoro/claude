<?php

declare(strict_types=1);

namespace Src\Order\Domain\ValueObjects;

use Src\Shared\Domain\ValueObject;

/**
 * ADDRESS (Value Object)
 * ======================
 * Çatdırılma ünvanını təmsil edən Value Object.
 *
 * NƏYƏ VALUE OBJECT?
 * - Ünvanın öz ID-si yoxdur — dəyərləri ilə müəyyən edilir.
 * - Eyni küçə, şəhər, zip, ölkə = eyni ünvan.
 * - Immutable-dir: ünvanı dəyişmək istəsən, yeni Address yaradırsan.
 *
 * NÜMUNƏ:
 *   $address = new Address('Nizami küçəsi 10', 'Bakı', 'AZ1000', 'Azərbaycan');
 *   // Ünvanı dəyişmək üçün:
 *   $newAddress = new Address('Füzuli küçəsi 5', 'Bakı', 'AZ1001', 'Azərbaycan');
 *   // Köhnə $address dəyişmədi — yeni obyekt yaratdıq.
 *
 * REAL PROYEKTDƏ:
 * - Ünvan validasiyası daha mürəkkəb ola bilər (poçt kodu formatı, ölkə kodu və s.).
 * - Google Maps API ilə ünvanı yoxlamaq olar.
 */
class Address extends ValueObject
{
    /**
     * @param string $street Küçə adı və nömrəsi
     * @param string $city Şəhər
     * @param string $zip Poçt kodu (zip code)
     * @param string $country Ölkə
     *
     * @throws \InvalidArgumentException Boş sahə olduqda
     */
    public function __construct(
        private readonly string $street,
        private readonly string $city,
        private readonly string $zip,
        private readonly string $country,
    ) {
        // Self-validation: Value Object yaradılanda öz qaydalarını yoxlayır
        if (empty(trim($street))) {
            throw new \InvalidArgumentException('Küçə adı boş ola bilməz.');
        }

        if (empty(trim($city))) {
            throw new \InvalidArgumentException('Şəhər adı boş ola bilməz.');
        }

        if (empty(trim($zip))) {
            throw new \InvalidArgumentException('Poçt kodu boş ola bilməz.');
        }

        if (empty(trim($country))) {
            throw new \InvalidArgumentException('Ölkə adı boş ola bilməz.');
        }
    }

    public function street(): string
    {
        return $this->street;
    }

    public function city(): string
    {
        return $this->city;
    }

    public function zip(): string
    {
        return $this->zip;
    }

    public function country(): string
    {
        return $this->country;
    }

    /**
     * Ünvanı array formatına çevir — DB-yə yazmaq və ya API cavabı üçün.
     */
    public function toArray(): array
    {
        return [
            'street'  => $this->street,
            'city'    => $this->city,
            'zip'     => $this->zip,
            'country' => $this->country,
        ];
    }

    /**
     * Array-dən Address yarat — DB-dən oxuyanda istifadə olunur.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            street: $data['street'],
            city: $data['city'],
            zip: $data['zip'],
            country: $data['country'],
        );
    }

    /**
     * İki ünvanı müqayisə et — bütün sahələr eyni olmalıdır.
     */
    public function equals(ValueObject $other): bool
    {
        return $other instanceof self
            && $this->street === $other->street
            && $this->city === $other->city
            && $this->zip === $other->zip
            && $this->country === $other->country;
    }

    public function __toString(): string
    {
        return "{$this->street}, {$this->city}, {$this->zip}, {$this->country}";
    }
}
