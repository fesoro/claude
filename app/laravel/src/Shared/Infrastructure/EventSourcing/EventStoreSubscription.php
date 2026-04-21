<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\EventSourcing;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * EVENT STORE SUBSCRIPTION — Event Store-dan Real-Time Oxuma
 * =============================================================
 *
 * PROBLEMİ ANLAYAQ:
 * =================
 * Event Sourcing-də bütün event-lər event_store cədvəlinə yazılır.
 * Projector-lar bu event-ləri oxuyub read model-ləri yeniləyir.
 *
 * AMMA: Projector NƏZAMAN işləyir?
 *
 * VARIANT 1: Sinxron (event dispatch zamanı):
 *   OrderCreated → EventDispatcher → Projector → Read Model yenilənir.
 *   Problem: Projector yavaşdırsa, yazma əməliyyatı da yavaşlayır.
 *
 * VARIANT 2: Catch-up Subscription (bu pattern):
 *   1. Event-lər event_store-a yazılır (sürətli).
 *   2. Ayrı proses (subscription) event_store-u poll edir.
 *   3. Yeni event-lər tapıldıqda projector-lara göndərir.
 *   4. Son oxunan position saxlanılır (checkpoint).
 *
 * ANALOGİYA:
 * ==========
 * Email:
 * Sinxron = Hər email gəldikdə dərhal oxumaq (işini dayandırırsan).
 * Catch-up = 5 dəqiqədən bir email yoxlamaq (öz tempinlə oxuyursan).
 *
 * Kafka Consumer:
 * Kafka-da consumer group offset saxlayır — "sonuncu oxuduğum mesaj #456 idi".
 * Növbəti poll-da #457-dən başlayır. Bu, catch-up subscription-dur.
 *
 * CHECKPOINT NƏDİR?
 * ==================
 * Checkpoint = "Sonuncu oxuduğum event-in position-u (sıra nömrəsi)."
 *
 * Projector çöksə → restart olduqda checkpoint-dən davam edir.
 * Checkpoint olmasa → bütün event-ləri yenidən oxumalıdır (milyon event!).
 *
 * Checkpoint DB-də saxlanılır: projector_checkpoints cədvəli.
 *   { projector_name: "OrderListProjector", last_position: 456 }
 *
 * AT-LEAST-ONCE vs EXACTLY-ONCE:
 * ================================
 * Bu subscription "at-least-once" delivery təmin edir:
 * - Hər event ən azı bir dəfə emal olunacaq.
 * - Eyni event iki dəfə emal oluna bilər (sistem çökərsə checkpoint yazılmamış ola bilər).
 *
 * Bu problemi həll etmək üçün projector-lar idempotent olmalıdır:
 * - updateOrInsert() istifadə et (INSERT əvəzinə).
 * - Event ID-yə görə dublikat yoxla.
 *
 * "Exactly-once" delivery mümkün deyil distributed sistemdə — bu CS-in fundamental limitidir.
 * Amma "at-least-once + idempotent consumer" ilə "effectively-once" təmin edə bilərik.
 *
 * POLL vs PUSH:
 * ==============
 * Poll (bu yanaşma): Subscription müntəzəm olaraq DB-ni yoxlayır.
 *   Üstünlük: Sadə, etibarlı, DB-dən asılıdır yalnız.
 *   Mənfi: Kiçik gecikm var (polling interval).
 *
 * Push (LISTEN/NOTIFY): PostgreSQL LISTEN/NOTIFY və ya Change Data Capture (CDC).
 *   Üstünlük: Real-time, gecikm minimal.
 *   Mənfi: Mürəkkəb, DB-spesifik, reliability idarəsi çətin.
 *
 * Real layihələrdə adətən Poll + Notification hybrid istifadə olunur:
 * - Normal halda push notification gəlir → dərhal emal olunur.
 * - Push itərsə → poll mexanizmi catch-up edir.
 */
class EventStoreSubscription
{
    /**
     * @var array<string, callable> Subscription-lar: ad → handler
     */
    private array $subscribers = [];

    public function __construct(
        private readonly string $connection = 'sqlite',
        private readonly string $eventStoreTable = 'event_store',
        private readonly string $checkpointTable = 'projector_checkpoints',
        private readonly int $batchSize = 100,
    ) {}

    /**
     * SUBSCRİPTİON ƏLAVƏ ET
     * ======================
     * Projector-u subscription-a qeydiyyatdan keçir.
     * Hər projector-un öz checkpoint-i olur.
     *
     * @param string   $name    Projector adı (checkpoint key)
     * @param callable $handler Event emal edən funksiya: fn(array $event): void
     */
    public function subscribe(string $name, callable $handler): void
    {
        $this->subscribers[$name] = $handler;
    }

    /**
     * CATCH-UP — Yeni event-ləri oxu və emal et
     * ============================================
     * Bu metod cron job və ya queue worker tərəfindən çağırılır.
     *
     * ADDIMLAR:
     * 1. Hər subscriber üçün son checkpoint-i oxu.
     * 2. Checkpoint-dən sonrakı event-ləri batch-larla oxu.
     * 3. Hər event-i handler-ə göndər.
     * 4. Checkpoint-i yenilə.
     *
     * @return int Emal olunan ümumi event sayı
     */
    public function catchUp(): int
    {
        $totalProcessed = 0;

        foreach ($this->subscribers as $name => $handler) {
            $processed = $this->processSubscriber($name, $handler);
            $totalProcessed += $processed;

            if ($processed > 0) {
                Log::info("Event subscription catch-up tamamlandı", [
                    'subscriber' => $name,
                    'events_processed' => $processed,
                ]);
            }
        }

        return $totalProcessed;
    }

    /**
     * Tək subscriber üçün catch-up əməliyyatı.
     */
    private function processSubscriber(string $name, callable $handler): int
    {
        $lastPosition = $this->getCheckpoint($name);
        $processed = 0;

        /**
         * BATCH PROCESSING:
         * Event-ləri bir-bir deyil, batch (dəstə) ilə oxuyuruq.
         * 1 milyon event varsa, 100-lük batch-larla 10.000 sorğu göndəririk.
         * Bu, DB load-u azaldır və performansı artırır.
         *
         * do-while istifadə edirik çünki ən azı bir dəfə yoxlamalıyıq.
         * Batch boş gəlsə → bitir.
         */
        do {
            $events = DB::connection($this->connection)
                ->table($this->eventStoreTable)
                ->where('id', '>', $lastPosition)
                ->orderBy('id')
                ->limit($this->batchSize)
                ->get()
                ->toArray();

            foreach ($events as $event) {
                $eventArray = (array) $event;

                try {
                    $handler($eventArray);
                    $lastPosition = $eventArray['id'];
                    $processed++;
                } catch (\Throwable $e) {
                    /**
                     * EVENT EMAL XƏTASI:
                     * Xəta olduqda checkpoint yenilənmir → növbəti catch-up eyni event-dən başlayır.
                     * Bu, "at-least-once" delivery-nin necə işlədiyini göstərir.
                     *
                     * Real layihədə: Dead Letter Queue-ya göndərilə bilər.
                     * Və ya retry policy tətbiq oluna bilər (3 cəhd, sonra DLQ).
                     */
                    Log::error("Event emalı xətası — subscription dayandırıldı", [
                        'subscriber' => $name,
                        'event_id' => $eventArray['id'] ?? 'unknown',
                        'error' => $e->getMessage(),
                    ]);

                    // Checkpoint-i son uğurlu event-ə yenilə
                    $this->saveCheckpoint($name, $lastPosition);
                    return $processed;
                }
            }

            // Batch bitdikdən sonra checkpoint yenilə
            if ($processed > 0) {
                $this->saveCheckpoint($name, $lastPosition);
            }

        } while (count($events) === $this->batchSize);

        return $processed;
    }

    /**
     * Son oxunan position-u oxu.
     * İlk dəfə çağırılırsa → 0 qaytarır (başdan başla).
     */
    private function getCheckpoint(string $subscriberName): int
    {
        $checkpoint = DB::connection($this->connection)
            ->table($this->checkpointTable)
            ->where('subscriber_name', $subscriberName)
            ->value('last_position');

        return (int) ($checkpoint ?? 0);
    }

    /**
     * Checkpoint-i yenilə.
     * updateOrInsert istifadə edirik — ilk dəfə INSERT, sonrakı UPDATE.
     */
    private function saveCheckpoint(string $subscriberName, int $position): void
    {
        DB::connection($this->connection)
            ->table($this->checkpointTable)
            ->updateOrInsert(
                ['subscriber_name' => $subscriberName],
                [
                    'last_position' => $position,
                    'updated_at' => now(),
                ],
            );
    }

    /**
     * Subscriber-in checkpoint-ini sıfırla.
     * Projector yenidən qurulmalı olanda istifadə olunur (rebuild).
     *
     * DİQQƏT: Bu əməliyyatdan sonra projector bütün event-ləri yenidən emal edəcək.
     * Read model cədvəlini əvvəlcə TRUNCATE etməyi unutma!
     */
    public function resetCheckpoint(string $subscriberName): void
    {
        DB::connection($this->connection)
            ->table($this->checkpointTable)
            ->where('subscriber_name', $subscriberName)
            ->delete();
    }

    /**
     * Bütün subscriber-lərin vəziyyətini göstər — monitoring üçün.
     *
     * @return array<string, array{subscriber: string, last_position: int, lag: int}>
     */
    public function status(): array
    {
        $latestEventId = (int) DB::connection($this->connection)
            ->table($this->eventStoreTable)
            ->max('id');

        $status = [];

        foreach (array_keys($this->subscribers) as $name) {
            $lastPosition = $this->getCheckpoint($name);

            /**
             * LAG — Projector-un event store-dan nə qədər geridə olduğu.
             * Lag = son event ID - projector-un oxuduğu son ID.
             *
             * Lag 0: Projector tam güncel — bütün event-ləri emal edib.
             * Lag 100: 100 event geridə — tezliklə catch-up edəcək.
             * Lag 10000: Çox geridə — problem var (projector çöküb?).
             *
             * Monitoring alətlərində (Grafana, Datadog) lag alert-ləri qurulur:
             * "Lag > 1000 olduqda alarm" → ops team xəbərdar olur.
             */
            $status[] = [
                'subscriber' => $name,
                'last_position' => $lastPosition,
                'latest_event' => $latestEventId,
                'lag' => $latestEventId - $lastPosition,
            ];
        }

        return $status;
    }
}
