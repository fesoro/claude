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
        // parent::__construct() DomainEvent-in eventId və occurredAt sahələrini yaradır.
        // Bu çağırış olmadan event-in unikal ID-si və vaxt damğası olmaz.
        parent::__construct();
    }

    /**
     * Mənbə kontekstini qaytarır.
     * Bu, digər bounded context-lərin hadisənin haradan gəldiyini bilməsi üçündür.
     */
    public function sourceContext(): string
    {
        return self::SOURCE_CONTEXT;
    }

    /**
     * Event-in adı — RabbitMQ routing key-in bir hissəsi olur.
     * sourceContext() + eventName() = "product.low_stock" routing key.
     * RabbitMQ bu key-ə görə mesajı düzgün queue-ya yönləndirir.
     */
    public function eventName(): string
    {
        return 'low_stock';
    }

    /**
     * Event-i array-ə çevir — serialization üçün.
     * RabbitMQ-ya JSON formatında göndərilir.
     * Consumer (dinləyici) bu array-dən məlumatları oxuyur.
     */
    public function toArray(): array
    {
        return [
            'event_id' => $this->eventId(),
            'occurred_at' => $this->occurredAt()->format('c'),
            'product_id' => $this->productId,
            'product_name' => $this->productName,
            'current_stock' => $this->currentStock,
            'threshold' => self::LOW_STOCK_THRESHOLD,
        ];
    }
}
