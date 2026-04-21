<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Messaging;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * OUTBOX RELAY — Transactional Outbox Pattern-in Nəqliyyat Hissəsi
 * ===================================================================
 *
 * PROBLEMİ ANLAYAQ — DUAL WRITE PROBLEM:
 * ========================================
 * Sifariş yaradılır → 2 şey baş verməlidir:
 *   1. DB-yə yaz: INSERT INTO orders ...
 *   2. RabbitMQ-ya göndər: OrderCreatedEvent
 *
 * PROBLEM: Əgər DB yazılır amma RabbitMQ göndərilmirsə?
 *   → Sifariş var amma Payment heç bilmir ki sifariş var!
 *
 * Əksi: RabbitMQ göndərilir amma DB yazılmırsa?
 *   → Payment ödənişi emal edir amma sifariş mövcud deyil!
 *
 * Bu, "dual-write problem" adlanır — iki fərqli sistemə atomik yazma mümkün deyil.
 * DB transaction-ı yalnız DB-ni əhatə edir, RabbitMQ-nu yox.
 *
 * HƏLLİ — TRANSACTIONAL OUTBOX:
 * ================================
 * Event-i RabbitMQ-ya birbaşa göndərmə!
 * Əvəzinə, DB-dəki outbox cədvəlinə yaz (eyni transaction-da):
 *
 *   BEGIN TRANSACTION
 *     INSERT INTO orders ...
 *     INSERT INTO outbox_messages (event_type, payload, status='pending')
 *   COMMIT
 *
 * Sonra ayrı proses (BU RELAY) outbox cədvəlini poll edib RabbitMQ-ya göndərəcək.
 *
 * NƏYƏ BU İŞLƏYİR?
 * Orders + outbox_messages eyni DB-dədir → eyni transaction-da yazılır → atomik!
 * Ya ikisi də yazılır, ya heç biri → data consistency təmin olunur.
 *
 * ANALOGİYA:
 * ==========
 * Poçt qutusu: Məktubu yazırsan → poçt qutusuna qoyursan (outbox).
 * Poçtçu (relay) vaxtaşırı gəlib poçt qutusunu yoxlayır → məktubları daşıyır.
 * Sən məktubu yazıb bitirdikdən sonra poçtçunun gəlməsini gözləmirsən.
 *
 * OUTBOX RELAY NƏDİR?
 * ====================
 * Outbox Relay — outbox cədvəlini vaxtaşırı yoxlayan və
 * "pending" mesajları RabbitMQ-ya (və ya digər message broker-a) göndərən komponentdir.
 *
 * RELAY-in 3 VƏZİFƏSİ:
 * 1. Pending mesajları oxu (SELECT ... WHERE status = 'pending')
 * 2. RabbitMQ-ya göndər (publish)
 * 3. Statusu yenilə (status = 'sent')
 *
 * AT-LEAST-ONCE DELIVERY:
 * ========================
 * Relay mesajı RabbitMQ-ya göndərib status yeniləyərkən çöksə nə olur?
 * → Mesaj göndərilib amma status hələ 'pending' → növbəti poll-da yenə göndəriləcək.
 * → Mesaj iki dəfə göndərilir — "at-least-once delivery".
 *
 * Buna görə consumer-lər (dinləyicilər) İDEMPOTENT olmalıdır:
 * Eyni mesajı iki dəfə emal etmək eyni nəticəni verməlidir.
 * IdempotentConsumer class-ı bu problemi həll edir.
 *
 * OUTBOX CLEANUP:
 * ================
 * Göndərilmiş mesajlar (status='sent') zamanla toplanır.
 * Cleanup job köhnə mesajları silir (məs: 7 gündən köhnə).
 * Bu, cədvəlin böyüməsinin qarşısını alır.
 *
 * ALTERNATİVLƏR:
 * ==============
 * 1. CDC (Change Data Capture) — Debezium:
 *    DB-nin transaction log-unu (WAL/binlog) oxuyub event-lərə çevirir.
 *    Outbox cədvəli lazım deyil — DB-nin öz log-u istifadə olunur.
 *    Problem: Mürəkkəb quraşdırma, DB-yə bağımlılıq.
 *
 * 2. Event Sourcing:
 *    Event-lər artıq DB-dədir (event_store) — ayrı outbox lazım deyil.
 *    Subscription event_store-u poll edir.
 *    Problem: Event Sourcing-i tam tətbiq etmək lazımdır.
 *
 * 3. Saga + Eventual Consistency:
 *    DB-yə yazıb, ayrı saga ilə consistency təmin etmək.
 *    Problem: Daha mürəkkəb, amma daha çevik.
 *
 * Bu implementasiya Outbox Relay (polling) yanaşmasıdır — ən sadə və etibarlı variant.
 */
class OutboxRelay
{
    public function __construct(
        private readonly RabbitMQPublisher $publisher,
        private readonly string $connection = 'sqlite',
        private readonly string $tableName = 'outbox_messages',
        private readonly int $batchSize = 50,
    ) {}

    /**
     * PENDİNG MESAJLARI GÖNDƏR
     * =========================
     * Bu metod scheduler (cron) tərəfindən çağırılır.
     * Laravel schedule: $schedule->command(PublishOutboxCommand::class)->everyMinute()
     *
     * @return int Göndərilən mesaj sayı
     */
    public function publishPending(): int
    {
        $messages = DB::connection($this->connection)
            ->table($this->tableName)
            ->where('status', 'pending')
            ->orderBy('created_at')
            ->limit($this->batchSize)
            ->get();

        $published = 0;

        foreach ($messages as $message) {
            try {
                /**
                 * MESAJI RABBITMQ-YA GÖNDƏR:
                 * Event type → routing key kimi istifadə olunur.
                 * Payload → mesajın gövdəsi.
                 */
                $this->publisher->publish(
                    routingKey: $message->event_type,
                    payload: json_decode($message->payload, true),
                );

                /**
                 * STATUSU YENİLƏ:
                 * 'pending' → 'sent'
                 * published_at — nə vaxt göndərildiyini saxlayır (audit/debug üçün).
                 */
                DB::connection($this->connection)
                    ->table($this->tableName)
                    ->where('id', $message->id)
                    ->update([
                        'status' => 'sent',
                        'published_at' => now(),
                    ]);

                $published++;

            } catch (\Throwable $e) {
                /**
                 * GÖNDƏRMƏ XƏTASI:
                 * Mesajın retry_count-unu artır.
                 * Max retry-dan sonra 'failed' statusuna keçir.
                 *
                 * Nəyə davam edirik (break etmirik)?
                 * Bir mesajın uğursuzluğu digərlərini bloklamamalıdır.
                 * Head-of-line blocking problemi — bir yavaş mesaj hamını gözlədir.
                 *
                 * AMMA: Əgər RabbitMQ tamamilə əlçatmazdırsa, hər mesaj uğursuz olacaq.
                 * Bu halda 3 ardıcıl xəta olduqda dayandırırıq (aşağıda).
                 */
                $retryCount = ($message->retry_count ?? 0) + 1;
                $maxRetries = 5;

                DB::connection($this->connection)
                    ->table($this->tableName)
                    ->where('id', $message->id)
                    ->update([
                        'retry_count' => $retryCount,
                        'status' => $retryCount >= $maxRetries ? 'failed' : 'pending',
                        'last_error' => substr($e->getMessage(), 0, 500),
                    ]);

                Log::error("Outbox mesajı göndərilə bilmədi", [
                    'message_id' => $message->id,
                    'event_type' => $message->event_type,
                    'retry_count' => $retryCount,
                    'error' => $e->getMessage(),
                ]);

                // 3 ardıcıl xəta olduqda dayandır — RabbitMQ down ola bilər
                if ($published === 0 && $retryCount > 1) {
                    Log::critical("Outbox relay dayandırıldı — ardıcıl xətalar", [
                        'failed_message_id' => $message->id,
                    ]);
                    break;
                }
            }
        }

        if ($published > 0) {
            Log::info("Outbox relay: {$published} mesaj göndərildi");
        }

        return $published;
    }

    /**
     * KÖHNƏ MESAJLARI TƏMİZLƏ
     * ==========================
     * Göndərilmiş mesajları müəyyən müddətdən sonra silir.
     * Cədvəlin böyüməsinin qarşısını alır.
     *
     * @param int $daysOld Neçə gündən köhnə mesajlar silinsin
     * @return int Silinən mesaj sayı
     */
    public function cleanup(int $daysOld = 7): int
    {
        $deleted = DB::connection($this->connection)
            ->table($this->tableName)
            ->where('status', 'sent')
            ->where('published_at', '<', now()->subDays($daysOld))
            ->delete();

        if ($deleted > 0) {
            Log::info("Outbox cleanup: {$deleted} köhnə mesaj silindi", [
                'days_old' => $daysOld,
            ]);
        }

        return $deleted;
    }

    /**
     * UĞURSUZ MESAJLARI YENİDƏN CƏHD ET
     * =====================================
     * Admin tərəfindən çağırılır — 'failed' mesajları yenidən 'pending' edir.
     *
     * @return int Yenidən cəhd üçün hazırlanan mesaj sayı
     */
    public function retryFailed(): int
    {
        return DB::connection($this->connection)
            ->table($this->tableName)
            ->where('status', 'failed')
            ->update([
                'status' => 'pending',
                'retry_count' => 0,
                'last_error' => null,
            ]);
    }

    /**
     * Outbox vəziyyəti — monitoring/dashboard üçün.
     */
    public function status(): array
    {
        $counts = DB::connection($this->connection)
            ->table($this->tableName)
            ->selectRaw("status, COUNT(*) as count")
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $oldestPending = DB::connection($this->connection)
            ->table($this->tableName)
            ->where('status', 'pending')
            ->min('created_at');

        return [
            'pending' => $counts['pending'] ?? 0,
            'sent' => $counts['sent'] ?? 0,
            'failed' => $counts['failed'] ?? 0,
            'oldest_pending' => $oldestPending,
        ];
    }
}
