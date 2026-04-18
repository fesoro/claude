<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Messaging;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * INBOX PATTERN — Gələn Mesajları Etibarlı Emal Etmə
 * =====================================================
 *
 * OUTBOX vs INBOX:
 * ================
 * Outbox: GÖNDƏRMƏ tərəfi. "Mesajı əvvəl DB-yə yaz, sonra RabbitMQ-ya göndər."
 *   → Mesaj itməz — DB-dədir, RabbitMQ-ya göndərilmələrini izləyirik.
 *
 * Inbox: QƏBUL tərəfi. "Mesajı əvvəl DB-yə yaz, sonra emal et."
 *   → Mesaj təkrarlanmaz — DB-dədir, emal olunub-olunmadığını izləyirik.
 *
 * Outbox (göndərici tərəf):
 *   Handler → DB yazır + Outbox-a event yazır (1 transaction)
 *   OutboxPublisher → Outbox-dan oxuyub RabbitMQ-ya göndərir
 *
 * Inbox (qəbul tərəfi):
 *   RabbitMQ → Consumer → Inbox-a yazır (ack)
 *   InboxProcessor → Inbox-dan oxuyub handler-i çağırır
 *
 * NƏYƏ INBOX LAZIMDIR?
 * =====================
 * 1. DUBLIKAT MESAJ: RabbitMQ eyni mesajı 2 dəfə göndərə bilər (at-least-once).
 *    Inbox dublikatı DB-dəki unique constraint ilə bloklayır.
 *
 * 2. SIRALAma: Mesajlar fərqli sırada gələ bilər.
 *    Inbox mesajları timestamp-ə görə sıralayıb emal edə bilər.
 *
 * 3. RETRY: Handler xəta verərsə, mesaj inbox-da qalır.
 *    InboxProcessor onu yenidən cəhd edə bilər.
 *
 * 4. MONİTORİNG: Emal olunmamış mesajların sayını izləmək olar.
 *    Dashboard-da: "50 mesaj gözləyir" göstərmək olar.
 *
 * Outbox + Inbox = "Transactional Messaging" pattern-in tam implementasiyası.
 * Bu, distributed sistemlərdə etibarlı mesajlaşmanın əsas pattern-idir.
 *
 * ANALOGİYA:
 * ===========
 * Outbox = Poçt qutusu (göndərmək üçün). Məktubu qutuna atırsan, poçtçu aparır.
 * Inbox = Gələn poçt qutusu. Məktub gəlir, sən oxuyursan, işləyirsən, "oxundu" qeyd edirsən.
 * Əgər eyni məktub 2 dəfə gəlsə — "artıq oxunub" deyib atırsan.
 *
 * IDEMPOTENT CONSUMER VS INBOX:
 * ==============================
 * IdempotentConsumer: Yüngül versiya — yalnız message_id yoxlayır.
 * InboxStore: Tam versiya — mesajı saxlayır, statusunu izləyir, retry idarə edir.
 * IdempotentConsumer-dan fərqi: Inbox mesajın məzmununu da saxlayır və monitoring imkanı verir.
 */
class InboxStore
{
    private const TABLE = 'inbox_messages';

    public function __construct(
        private readonly string $connection = 'sqlite',
    ) {}

    /**
     * MESAJI INBOX-A YAZ
     * ====================
     * RabbitMQ-dan gələn mesajı əvvəlcə DB-yə yazırıq.
     * Sonra mesajı ACK edirik (RabbitMQ-ya "aldım" deyirik).
     * Emal sonra olacaq — InboxProcessor tərəfindən.
     *
     * @param string $messageId Mesajın unikal ID-si
     * @param string $type      Mesaj tipi (event adı)
     * @param array  $payload   Mesajın məzmunu
     * @param string $source    Hansı bounded context-dən gəlib
     *
     * @return bool True — yazıldı, False — dublikatdır (artıq var)
     */
    public function store(string $messageId, string $type, array $payload, string $source): bool
    {
        // Dublikat yoxlaması — unique constraint ilə
        $exists = DB::connection($this->connection)
            ->table(self::TABLE)
            ->where('message_id', $messageId)
            ->exists();

        if ($exists) {
            Log::info('Inbox: dublikat mesaj, skip edilir', [
                'message_id' => $messageId,
                'type' => $type,
            ]);
            return false;
        }

        DB::connection($this->connection)->table(self::TABLE)->insert([
            'message_id'   => $messageId,
            'message_type' => $type,
            'payload'      => json_encode($payload),
            'source'       => $source,
            'status'       => 'pending',
            'attempts'     => 0,
            'created_at'   => now(),
            'processed_at' => null,
        ]);

        return true;
    }

    /**
     * GÖZLƏYƏn MESAJLARI AL (Emal Üçün)
     * =====================================
     * Status = 'pending' olan mesajları qaytarır.
     * InboxProcessor bu mesajları bir-bir emal edəcək.
     *
     * @param int $limit Bir dəfədə neçə mesaj almaq
     */
    public function getPendingMessages(int $limit = 50): array
    {
        return DB::connection($this->connection)
            ->table(self::TABLE)
            ->where('status', 'pending')
            ->where('attempts', '<', 5) // Max 5 cəhd
            ->orderBy('created_at', 'asc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * MESAJI "EMAL OLUNDU" OLARAQ QEYD ET
     */
    public function markAsProcessed(string $messageId): void
    {
        DB::connection($this->connection)
            ->table(self::TABLE)
            ->where('message_id', $messageId)
            ->update([
                'status' => 'processed',
                'processed_at' => now(),
            ]);
    }

    /**
     * MESAJI "UĞURSUZ" OLARAQ QEYD ET
     * Cəhd sayını artırır. Max cəhddən sonra 'failed' olur.
     */
    public function markAsFailed(string $messageId, string $error): void
    {
        $message = DB::connection($this->connection)
            ->table(self::TABLE)
            ->where('message_id', $messageId)
            ->first();

        $attempts = ($message->attempts ?? 0) + 1;
        $status = $attempts >= 5 ? 'failed' : 'pending';

        DB::connection($this->connection)
            ->table(self::TABLE)
            ->where('message_id', $messageId)
            ->update([
                'status'     => $status,
                'attempts'   => $attempts,
                'last_error' => $error,
            ]);
    }

    /**
     * MONİTORİNG — Status üzrə mesaj sayları
     */
    public function getStats(): array
    {
        $stats = DB::connection($this->connection)
            ->table(self::TABLE)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return [
            'pending'   => $stats['pending'] ?? 0,
            'processed' => $stats['processed'] ?? 0,
            'failed'    => $stats['failed'] ?? 0,
            'total'     => array_sum($stats),
        ];
    }
}
