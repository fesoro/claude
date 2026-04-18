<?php

declare(strict_types=1);

namespace Src\Order\Infrastructure\Outbox;

use Illuminate\Support\Facades\DB;

/**
 * OUTBOX REPOSITORY
 * ==================
 * Outbox mesajlarını DB-dən oxumaq və yazmaq üçün repository.
 *
 * BU REPOSITORY-NİN VƏZİFƏSİ:
 * 1. save(): Yeni outbox mesajını DB-yə yaz (CreateOrderHandler çağırır).
 * 2. findUnpublished(): Göndərilməmiş mesajları tap (OutboxPublisher çağırır).
 * 3. markAsPublished(): Göndərilmiş mesajı işarələ (OutboxPublisher çağırır).
 *
 * AXIN:
 * ┌────────────────────┐     ┌───────────────────────┐     ┌─────────────────┐
 * │ CreateOrderHandler │────→│ OutboxRepository.save │────→│ outbox_messages │
 * │ (mesajı yarat)     │     │ (DB-yə yaz)           │     │ (DB cədvəli)    │
 * └────────────────────┘     └───────────────────────┘     └────────┬────────┘
 *                                                                    │
 * ┌────────────────────┐     ┌───────────────────────┐              │
 * │ OutboxPublisher    │←────│ findUnpublished()     │←─────────────┘
 * │ (cron job)         │     │ (göndərilməmişləri tap)│
 * └────────┬───────────┘     └───────────────────────┘
 *          │
 *          ↓
 * ┌────────────────────┐
 * │ RabbitMQ-ya göndər │
 * │ markAsPublished()  │
 * └────────────────────┘
 */
class OutboxRepository
{
    /**
     * Outbox mesajını DB-yə saxla.
     * Bu metod Order save ilə EYNI DB TRANSACTION-DA çağırılmalıdır!
     */
    public function save(OutboxMessage $message): void
    {
        DB::table('outbox_messages')->insert([
            'id'           => $message->messageId(),
            'event_name'   => $message->eventName(),
            'payload'      => json_encode($message->payload()),
            'routing_key'  => $message->routingKey(),
            'published'    => false,
            'created_at'   => $message->createdAt()->format('Y-m-d H:i:s'),
            'published_at' => null,
        ]);
    }

    /**
     * Göndərilməmiş (unpublished) mesajları tap.
     * OutboxPublisher bu metodu çağırıb mesajları RabbitMQ-ya göndərir.
     *
     * DİQQƏT: Limit var ki, çox mesaj olduqda hamısını eyni anda emal etməyək.
     * Hər cron run-da məsələn 100 mesaj emal olunur.
     *
     * @param int $limit Maksimum mesaj sayı
     * @return OutboxMessage[] Göndərilməmiş mesajlar
     */
    public function findUnpublished(int $limit = 100): array
    {
        $rows = DB::table('outbox_messages')
            ->where('published', false)
            ->orderBy('created_at', 'asc') // Ən köhnə mesaj birinci göndərilir (FIFO)
            ->limit($limit)
            ->get();

        return $rows->map(fn ($row) => OutboxMessage::reconstitute(
            messageId: $row->id,
            eventName: $row->event_name,
            payload: json_decode($row->payload, true),
            routingKey: $row->routing_key,
            published: (bool) $row->published,
            createdAt: new \DateTimeImmutable($row->created_at),
            publishedAt: $row->published_at ? new \DateTimeImmutable($row->published_at) : null,
        ))->all();
    }

    /**
     * Mesajı "göndərildi" olaraq işarələ.
     * RabbitMQ-ya uğurla göndərildikdən sonra çağırılır.
     */
    public function markAsPublished(OutboxMessage $message): void
    {
        $message->markAsPublished();

        DB::table('outbox_messages')
            ->where('id', $message->messageId())
            ->update([
                'published'    => true,
                'published_at' => $message->publishedAt()->format('Y-m-d H:i:s'),
            ]);
    }
}
