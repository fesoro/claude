<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Messaging;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * IDEMPOTENT CONSUMER — Exactly-Once Mesaj Emalı
 * =================================================
 *
 * PROBLEMİ ANLAYAQ:
 * =================
 * Distributed sistemlərdə mesajlar üç şəkildə çatdırıla bilər:
 *
 * 1. AT-MOST-ONCE: Mesaj ən çoxu 1 dəfə çatdırılır. İtə bilər.
 *    → Email göndərmə: çatmasa fəlakət deyil.
 *
 * 2. AT-LEAST-ONCE: Mesaj ən azı 1 dəfə çatdırılır. Dublikat ola bilər.
 *    → RabbitMQ, Kafka default davranışı budur.
 *    → Problem: eyni ödəniş 2 dəfə emal oluna bilər!
 *
 * 3. EXACTLY-ONCE: Mesaj dəqiq 1 dəfə emal olunur.
 *    → İdeal, amma texniki cəhətdən guarantee etmək çox çətindir.
 *    → Həlli: at-least-once + idempotent consumer = effectively exactly-once.
 *
 * IDEMPOTENT CONSUMER NƏ EDİR?
 * =============================
 * Hər mesajın unikal ID-si var. Consumer mesajı emal etməzdən əvvəl yoxlayır:
 * "Bu ID-li mesajı əvvəl emal etmişəmmi?"
 *   - Bəli → Skip et (artıq emal olunub)
 *   - Xeyr → Emal et və ID-ni qeyd et
 *
 * ANALOGİYA:
 * ========
 * Bank transfer: Eyni transfer ID-si ilə iki sorğu gəlsə, bank yalnız birini icra edir.
 * İkincisini "artıq icra olunub" cavabı ilə rədd edir.
 * Bu, idempotent consumer-in bank versiyasıdır.
 *
 * İDEMPOTENT ≠ DEDUPLİKASİYA:
 * ============================
 * Deduplikasiya: Mesajı göndərmə tərəfində (publisher) dublikatdan qoruyur.
 * Idempotent Consumer: Mesajı qəbul tərəfində (consumer) dublikatdan qoruyur.
 * İkisi fərqli layer-dədir və bir-birini tamamlayır.
 *
 * İMPLEMENTASİYA DETALLARI:
 * =========================
 * 1. processed_messages cədvəli — emal olunmuş mesaj ID-lərini saxlayır.
 * 2. DB transaction — mesaj emalı + ID qeydi atomik olur.
 * 3. TTL (Time-To-Live) — köhnə qeydlər silinir ki, cədvəl şişməsin.
 */
class IdempotentConsumer
{
    private const TABLE = 'processed_messages';

    /**
     * Köhnə qeydlər neçə gün saxlanacaq.
     * Bundan köhnə qeydlər cleanup zamanı silinir.
     * 7 gün adətən kifayətdir — mesajlar nadir hallarda bu qədər gec gəlir.
     */
    private const RETENTION_DAYS = 7;

    public function __construct(
        private readonly string $connection = 'sqlite',
    ) {}

    /**
     * MESAJI İDEMPOTENT ŞƏKİLDƏ EMAL ET
     * ====================================
     * Mesajın əvvəl emal olunub-olunmadığını yoxlayır.
     * Əgər emal olunmayıbsa — handler-i çağırır və mesaj ID-ni qeyd edir.
     * Hamısını bir DB transaction-da edir — atomiklik təmin olunur.
     *
     * @param string   $messageId Mesajın unikal identifikatoru
     * @param string   $type      Mesaj tipi (məs: 'order.created', 'payment.processed')
     * @param callable $handler   Mesajı emal edən funksiya: fn(): void
     *
     * @return bool True — mesaj emal olundu, False — artıq əvvəl emal olunmuşdu
     *
     * İSTİFADƏ NÜMUNƏSİ:
     * $wasProcessed = $consumer->process(
     *     messageId: $message->id,
     *     type: 'order.created',
     *     handler: function () use ($message) {
     *         $this->commandBus->dispatch(new CreateOrderCommand(...));
     *     }
     * );
     */
    public function process(string $messageId, string $type, callable $handler): bool
    {
        /**
         * Əvvəlcə yoxla — bu mesaj artıq emal olunubmu?
         * Transaction-dan kənarda yoxlayırıq ki, gerəksiz transaction açmayaq.
         * Race condition riski var amma DB transaction bunu həll edəcək.
         */
        if ($this->isProcessed($messageId)) {
            Log::info('Mesaj artıq emal olunub, skip edilir', [
                'message_id' => $messageId,
                'type'       => $type,
            ]);

            return false;
        }

        /**
         * Transaction daxilində:
         * 1. Mesaj ID-ni cədvələ yaz (UNIQUE constraint ilə)
         * 2. Handler-i çağır (mesajı emal et)
         *
         * Əgər handler exception atsa → transaction rollback olur → ID də yazılmır.
         * Bu o deməkdir ki, mesaj yenidən cəhd oluna bilər (retry).
         *
         * UNIQUE CONSTRAINT vacibdir:
         * İki proses eyni anda eyni mesajı emal etmək istəsə,
         * birincisi INSERT edər, ikincisi UNIQUE violation alər.
         * Bu, DB səviyyəsində race condition-u həll edir.
         */
        DB::connection($this->connection)->transaction(function () use ($messageId, $type, $handler) {
            // İlk öncə ID-ni yaz — UNIQUE constraint dublikatı bloklayır
            DB::connection($this->connection)->table(self::TABLE)->insert([
                'message_id'   => $messageId,
                'message_type' => $type,
                'processed_at' => now(),
            ]);

            // Handler-i çağır
            $handler();
        });

        Log::info('Mesaj uğurla emal olundu', [
            'message_id' => $messageId,
            'type'       => $type,
        ]);

        return true;
    }

    /**
     * MESAJ ARTIQ EMAL OLUNUBMU?
     */
    public function isProcessed(string $messageId): bool
    {
        return DB::connection($this->connection)
            ->table(self::TABLE)
            ->where('message_id', $messageId)
            ->exists();
    }

    /**
     * KÖHNƏ QEYDLƏRI TƏMİZLƏ (Cleanup)
     * ====================================
     * processed_messages cədvəli zaman keçdikcə böyüyər.
     * Köhnə qeydlər artıq lazım deyil — o mesajlar çoxdan emal olunub.
     *
     * Bu metod cron job ilə gündəlik çağırılmalıdır:
     * php artisan schedule:run → $schedule->call(fn() => $consumer->cleanup())->daily();
     *
     * NƏYƏ 7 GÜN?
     * At-least-once delivery-də mesaj gecikmə ilə gələ bilər, amma 7 gündən çox nadir hallarda.
     * Əgər bir mesaj 7 gündən sonra gəlirsə — bu artıq "problem" kimi araşdırılmalıdır.
     */
    public function cleanup(): int
    {
        $cutoff = now()->subDays(self::RETENTION_DAYS);

        $deleted = DB::connection($this->connection)
            ->table(self::TABLE)
            ->where('processed_at', '<', $cutoff)
            ->delete();

        Log::info('Köhnə emal qeydləri silindi', [
            'deleted_count'  => $deleted,
            'retention_days' => self::RETENTION_DAYS,
        ]);

        return $deleted;
    }
}
