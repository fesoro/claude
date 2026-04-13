<?php

declare(strict_types=1);

namespace Src\Order\Infrastructure\Outbox;

use Src\Shared\Domain\Entity;

/**
 * OUTBOX MESSAGE (Outbox Pattern)
 * =================================
 * RabbitMQ-ya göndəriləcək mesajı təmsil edən entity.
 *
 * ═══════════════════════════════════════════════════════════════
 * OUTBOX PATTERN NƏDİR?
 * ═══════════════════════════════════════════════════════════════
 *
 * Outbox Pattern — verilənlər bazası ilə mesaj broker (RabbitMQ) arasında
 * DATA CONSİSTENCY (məlumat uyğunluğu) təmin edən pattern-dir.
 *
 * ═══════════════════════════════════════════════════════════════
 * PROBLEM (Outbox olmadan):
 * ═══════════════════════════════════════════════════════════════
 *
 * ┌──────────────────────────────────────────────────────────────┐
 * │ 1. Sifarişi DB-yə yaz              → UĞURLU ✓              │
 * │ 2. RabbitMQ-ya event göndər         → UĞURSUZ ✗ (şəbəkə xətası) │
 * │                                                              │
 * │ NƏTİCƏ: Sifariş DB-dədir, amma Payment modulu BİLMİR!     │
 * │ Bu "dual write" problemidir — iki fərqli sistemə yazmaq.    │
 * └──────────────────────────────────────────────────────────────┘
 *
 * Əks halda:
 * ┌──────────────────────────────────────────────────────────────┐
 * │ 1. RabbitMQ-ya event göndər         → UĞURLU ✓              │
 * │ 2. Sifarişi DB-yə yaz              → UĞURSUZ ✗ (DB xətası) │
 * │                                                              │
 * │ NƏTİCƏ: Payment modulu ödəniş edəcək, amma sifariş yoxdur!│
 * └──────────────────────────────────────────────────────────────┘
 *
 * HƏR İKİ HAL PROBLEMLİDİR!
 *
 * ═══════════════════════════════════════════════════════════════
 * HƏLL (Outbox Pattern ilə):
 * ═══════════════════════════════════════════════════════════════
 *
 * ┌──────────────────────────────────────────────────────────────┐
 * │ EYNI DB TRANSACTION-DA:                                      │
 * │ 1. Sifarişi "orders" cədvəlinə yaz           ✓              │
 * │ 2. Event-i "outbox_messages" cədvəlinə yaz   ✓              │
 * │ → Transaction COMMIT — hər ikisi ya OLUR, ya OLMUR!         │
 * │                                                              │
 * │ AYRI PROSES (OutboxPublisher — cron job, hər 10 saniyə):    │
 * │ 3. outbox_messages-dən göndərilməmiş mesajları oxu          │
 * │ 4. Hər mesajı RabbitMQ-ya göndər                            │
 * │ 5. Göndərilmiş mesajları "published" olaraq işarələ         │
 * └──────────────────────────────────────────────────────────────┘
 *
 * ÜSTÜNLÜKLƏR:
 * 1. Atomicity: Sifariş + Event eyni transaction-da — ya ikisi də olur, ya heç biri.
 * 2. Reliability: RabbitMQ çöksə belə, mesaj DB-dədir, sonra göndəriləcək.
 * 3. At-least-once: Mesaj ən azı bir dəfə göndəriləcək (repeat ola bilər).
 * 4. Retry: Göndərilə bilməyən mesajlar yenidən cəhd edilə bilər.
 *
 * ÇATIŞMAZLIQLAR:
 * 1. At-least-once: Eyni mesaj 2 dəfə gələ bilər → consumer idempotent olmalıdır.
 * 2. Delay: Mesaj dərhal deyil, cron intervalında göndərilir (məs: 10 saniyə).
 * 3. Extra table: Əlavə DB cədvəli lazımdır.
 *
 * ═══════════════════════════════════════════════════════════════
 * OUTBOX CƏDVƏLİ STRUKTURU:
 * ═══════════════════════════════════════════════════════════════
 * ┌─────────────────────────────────────────────────┐
 * │ outbox_messages                                  │
 * ├────────────┬────────────────────────────────────┤
 * │ id         │ UUID (primary key)                 │
 * │ event_name │ "order.created" (event tipi)       │
 * │ payload    │ JSON (event datası)                │
 * │ routing_key│ "order.order.created" (RabbitMQ)   │
 * │ published  │ false (göndərilibmi?)              │
 * │ created_at │ timestamp (yaradılma vaxtı)        │
 * │ published_at│ timestamp (göndərilmə vaxtı)     │
 * └────────────┴────────────────────────────────────┘
 */
class OutboxMessage extends Entity
{
    private function __construct(
        private readonly string $messageId,
        private readonly string $eventName,
        private readonly array $payload,
        private readonly string $routingKey,
        private bool $published,
        private readonly \DateTimeImmutable $createdAt,
        private ?\DateTimeImmutable $publishedAt,
    ) {
        $this->id = $messageId;
    }

    /**
     * Yeni Outbox mesajı yarat.
     * Bu mesaj DB-yə yazılacaq və sonra RabbitMQ-ya göndəriləcək.
     */
    public static function create(
        string $eventName,
        array $payload,
        string $routingKey,
    ): self {
        return new self(
            messageId: uuid_create(),
            eventName: $eventName,
            payload: $payload,
            routingKey: $routingKey,
            published: false,
            createdAt: new \DateTimeImmutable(),
            publishedAt: null,
        );
    }

    /**
     * DB-dən oxunmuş datadan OutboxMessage yarat (reconstitution).
     */
    public static function reconstitute(
        string $messageId,
        string $eventName,
        array $payload,
        string $routingKey,
        bool $published,
        \DateTimeImmutable $createdAt,
        ?\DateTimeImmutable $publishedAt,
    ): self {
        return new self($messageId, $eventName, $payload, $routingKey, $published, $createdAt, $publishedAt);
    }

    /**
     * Mesajı "göndərildi" olaraq işarələ.
     * OutboxPublisher RabbitMQ-ya uğurla göndərdikdən sonra çağırır.
     */
    public function markAsPublished(): void
    {
        $this->published = true;
        $this->publishedAt = new \DateTimeImmutable();
    }

    public function messageId(): string
    {
        return $this->messageId;
    }

    public function eventName(): string
    {
        return $this->eventName;
    }

    public function payload(): array
    {
        return $this->payload;
    }

    public function routingKey(): string
    {
        return $this->routingKey;
    }

    public function isPublished(): bool
    {
        return $this->published;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function publishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }
}
