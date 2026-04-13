<?php

declare(strict_types=1);

namespace Src\Order\Infrastructure\Outbox;

use Src\Shared\Infrastructure\Messaging\RabbitMQPublisher;
use Src\Shared\Domain\IntegrationEvent;

/**
 * OUTBOX PUBLISHER (Outbox Pattern - Publisher/Relay hissəsi)
 * =============================================================
 * DB-dəki göndərilməmiş mesajları oxuyub RabbitMQ-ya göndərir.
 *
 * BU CLASS NƏ EDİR?
 * - Scheduled job (cron) olaraq müəyyən intervalda (məs: hər 10 saniyə) işləyir.
 * - outbox_messages cədvəlindən "published = false" olan mesajları tapır.
 * - Hər mesajı RabbitMQ-ya göndərir.
 * - Uğurlu göndəriləni "published = true" olaraq işarələyir.
 *
 * ═══════════════════════════════════════════════════════════════
 * OUTBOX PATTERN AXINI (TAM):
 * ═══════════════════════════════════════════════════════════════
 *
 * YAZI ZAMANI (CreateOrderHandler):
 * ┌──────────────────────────────────────────────────────────────┐
 * │ BEGIN TRANSACTION                                            │
 * │   INSERT INTO orders (...)        → Sifariş yaradılır       │
 * │   INSERT INTO outbox_messages (...) → Event DB-yə yazılır   │
 * │ COMMIT                                                       │
 * │                                                              │
 * │ DİQQƏT: Hər ikisi eyni transaction-da!                     │
 * │ Ya ikisi də olur, ya heç biri — atomicity!                  │
 * └──────────────────────────────────────────────────────────────┘
 *
 * GÖNDƏRMƏ ZAMANI (OutboxPublisher — bu class):
 * ┌──────────────────────────────────────────────────────────────┐
 * │ Cron job hər 10 saniyədə:                                   │
 * │ 1. SELECT * FROM outbox_messages WHERE published = false     │
 * │ 2. Hər mesaj üçün:                                          │
 * │    a. RabbitMQ-ya göndər (publish)                          │
 * │    b. UPDATE outbox_messages SET published = true            │
 * │ 3. Uğursuz olanlar növbəti run-da yenidən cəhd edilir       │
 * └──────────────────────────────────────────────────────────────┘
 *
 * ═══════════════════════════════════════════════════════════════
 * NƏYƏ BİRBAŞA RABBITMQ-YA GÖNDƏRMİRİK?
 * ═══════════════════════════════════════════════════════════════
 *
 * SUAL: Nəyə CreateOrderHandler birbaşa RabbitMQ-ya göndərmir?
 * CAVAB: Çünki "dual write" problemi yaranacaq!
 *
 * Ssenari 1 — DB uğurlu, RabbitMQ uğursuz:
 *   Sifariş yaranıb, amma heç kim bilmir (mesaj itib).
 *
 * Ssenari 2 — RabbitMQ uğurlu, DB uğursuz:
 *   Payment modulu ödəniş edəcək, amma sifariş yoxdur!
 *
 * Outbox HƏLLI: Hər ikisini eyni DB-yə yazırıq → sonra ayrıca göndəririk.
 *
 * ═══════════════════════════════════════════════════════════════
 * İDEMPOTENCY (təkrar mesaj problemi):
 * ═══════════════════════════════════════════════════════════════
 *
 * At-least-once delivery: mesaj ən azı 1 dəfə göndəriləcək.
 * Amma bəzən 2 dəfə göndərilə bilər (məs: RabbitMQ-ya göndərilib,
 * amma markAsPublished() işləmədən proses çökdü).
 *
 * HƏLL: Consumer (qəbul edən) idempotent olmalıdır:
 * - Eyni event_id-li mesajı 2-ci dəfə aldıqda, ignore etməlidir.
 * - Bu üçün "processed_events" cədvəli saxlanıla bilər.
 *
 * LARAVEL-DƏ İSTİFADƏ:
 * Bu class-ı Laravel Scheduled Command olaraq qeydiyyatdan keçir:
 * $schedule->call(fn() => app(OutboxPublisher::class)->publishPending())
 *          ->everyTenSeconds();
 */
class OutboxPublisher
{
    public function __construct(
        private readonly OutboxRepository $outboxRepository,
        private readonly RabbitMQPublisher $rabbitMQPublisher,
    ) {}

    /**
     * Göndərilməmiş bütün mesajları RabbitMQ-ya göndər.
     *
     * Bu metod scheduled job (cron) tərəfindən çağırılır.
     * Hər mesaj ayrı-ayrı emal olunur — biri uğursuz olsa,
     * digərləri hələ də göndərilə bilər.
     *
     * @return int Göndərilən mesaj sayı
     */
    public function publishPending(): int
    {
        // 1. Göndərilməmiş mesajları tap
        $messages = $this->outboxRepository->findUnpublished();
        $publishedCount = 0;

        // 2. Hər mesajı RabbitMQ-ya göndər
        foreach ($messages as $message) {
            try {
                // RabbitMQ-ya göndər
                // DİQQƏT: RabbitMQPublisher IntegrationEvent gözləyir,
                // amma biz ham datanı (array) göndəririk.
                // Real proyektdə bu adapter layer ilə həll olunur.
                $this->publishToRabbitMQ($message);

                // Uğurla göndərildisə, "published" olaraq işarələ
                $this->outboxRepository->markAsPublished($message);
                $publishedCount++;

            } catch (\Throwable $exception) {
                // Göndərmə uğursuz — bu mesaj növbəti run-da yenidən cəhd ediləcək.
                // REAL PROYEKTDƏ: retry count əlavə et, müəyyən saydan sonra
                // Dead Letter Queue-ya yaz (manual müdaxilə üçün).

                // Log-la ki, problem izlənilə bilsin
                logger()->error("Outbox mesajı göndərilə bilmədi: {$message->messageId()}", [
                    'event_name' => $message->eventName(),
                    'error'      => $exception->getMessage(),
                ]);
            }
        }

        return $publishedCount;
    }

    /**
     * OutboxMessage-i RabbitMQ-ya göndər.
     *
     * Bu metod OutboxMessage datası ilə RabbitMQ AMQPMessage yaradır
     * və exchange-ə publish edir.
     */
    private function publishToRabbitMQ(OutboxMessage $message): void
    {
        // RabbitMQPublisher-in publish metodu IntegrationEvent gözləyir.
        // Burada birbaşa AMQP mesajı yaratmaq daha düzgündür.
        // Sadəlik üçün anonymous class ilə IntegrationEvent yaradırıq:
        $event = new class($message) extends \Src\Shared\Domain\IntegrationEvent {
            public function __construct(private readonly OutboxMessage $msg)
            {
                parent::__construct();
            }

            public function sourceContext(): string
            {
                return 'order';
            }

            public function eventName(): string
            {
                return $this->msg->eventName();
            }

            public function toArray(): array
            {
                return $this->msg->payload();
            }
        };

        $this->rabbitMQPublisher->publish($event);
    }
}
