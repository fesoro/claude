<?php

declare(strict_types=1);

namespace Src\Product\Domain\Events;

use Src\Shared\Domain\IntegrationEvent;

/**
 * LowStockIntegrationEvent - Stok 5-dən aşağı düşdükdə yaradılan inteqrasiya hadisəsi.
 *
 * DomainEvent vs IntegrationEvent fərqi:
 *
 * DomainEvent:
 * - Eyni Bounded Context daxilində istifadə olunur.
 * - Məsələn: ProductCreatedEvent yalnız Product kontekstində işləyir.
 *
 * IntegrationEvent:
 * - Fərqli Bounded Context-lər arasında istifadə olunur.
 * - Məsələn: Bu event Product kontekstindən Notification kontekstinə göndərilir.
 * - "sourceContext" hansı kontekstdən gəldiyini göstərir.
 *
 * İstifadə nümunəsi:
 * - Stok 5-dən aşağı düşdükdə bu event yaranır.
 * - Notification sistemi bunu dinləyib anbarçıya xəbərdarlıq göndərə bilər.
 * - Satınalma sistemi avtomatik sifariş yarada bilər.
 */
final class LowStockIntegrationEvent extends IntegrationEvent
{
    /** Bu hadisənin hansı Bounded Context-dən gəldiyini göstərir */
    public const SOURCE_CONTEXT = 'product';

    /** Stokun "aşağı" sayıldığı hədd */
    public const LOW_STOCK_THRESHOLD = 5;

    public function __construct(
        public readonly string $productId,
        public readonly string $productName,
        public readonly int $currentStock,
    ) {
    }

    /**
     * Mənbə kontekstini qaytarır.
     * Bu, digər bounded context-lərin hadisənin haradan gəldiyini bilməsi üçündür.
     */
    public function sourceContext(): string
    {
        return self::SOURCE_CONTEXT;
    }
}
