<?php

declare(strict_types=1);

namespace Src\Product\Domain\Enums;

/**
 * VALYUTA ENUM
 * =============
 * Dəstəklənən valyutaları təmsil edən backed enum.
 *
 * Hər valyutanın ISO 4217 kodu (USD, EUR, AZN), simvolu ($, EUR, AZN)
 * və tam adı var. symbol() və name() metodları bu məlumatları qaytarır.
 *
 * İSTİFADƏ NÜMUNƏSİ:
 *   $currency = CurrencyEnum::AZN;
 *   echo $currency->value;       // 'AZN'
 *   echo $currency->symbol();    // '₼'
 *   echo $currency->currencyName(); // 'Azərbaycan manatı'
 *
 * NƏYƏ ENUM?
 * Valyuta kodları sabit dəyərlərdir — yeni valyuta nadir hallarda əlavə olunur.
 * Enum istifadə etməklə etibarsız valyuta kodunun sistemə düşməsinin qarşısı alınır.
 */
enum CurrencyEnum: string
{
    /**
     * ABŞ dolları — beynəlxalq ticarətin əsas valyutası.
     */
    case USD = 'USD';

    /**
     * Avro — Avropa İttifaqının ortaq valyutası.
     */
    case EUR = 'EUR';

    /**
     * Azərbaycan manatı — ölkə daxili əsas valyuta.
     */
    case AZN = 'AZN';

    /**
     * Valyutanın simvolunu qaytarır.
     * Qiymət göstərərkən istifadə olunur: "$29.99", "EUR29.99", "₼29.99"
     *
     * @return string - valyuta simvolu
     */
    public function symbol(): string
    {
        return match ($this) {
            self::USD => '$',
            self::EUR => '€',
            self::AZN => '₼',
        };
    }

    /**
     * Valyutanın tam adını Azərbaycan dilində qaytarır.
     *
     * QEYD: PHP enum-larında name() adlı metod yaratmaq OLMAZ,
     * çünki ->name property artıq mövcuddur (case adını qaytarır).
     * Ona görə currencyName() adlandırılıb.
     *
     * @return string - valyutanın tam adı
     */
    public function currencyName(): string
    {
        return match ($this) {
            self::USD => 'ABŞ dolları',
            self::EUR => 'Avro',
            self::AZN => 'Azərbaycan manatı',
        };
    }

    /**
     * Qiyməti formatlanmış şəkildə qaytarır.
     * Məsələn: CurrencyEnum::USD->format(29.99) → "$29.99"
     *
     * @param float $amount - formatlanacaq məbləğ
     * @return string - simvol + məbləğ
     */
    public function format(float $amount): string
    {
        return $this->symbol() . number_format($amount, 2);
    }
}
