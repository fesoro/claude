<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Messaging;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * DEAD LETTER QUEUE (DLQ) — Ölü Məktub Növbəsi
 * ===============================================
 * Emal edilə bilməyən (failed) mesajları saxlayan xüsusi növbədir.
 *
 * REAL HƏYAT ANALOGİYASI:
 * ========================
 * Poçt müəssisəsini düşünün:
 * - Məktubu ünvana çatdırmağa çalışırlar → Ünvan yanlışdır → Yenidən cəhd → Yenə uğursuz
 * - 3 cəhddən sonra məktubu "ölü məktub şöbəsi"nə (dead letter office) göndərirlər
 * - Orada operator məktubu yoxlayır: ünvanı düzəldir, göndərənə qaytarır, və ya atır
 *
 * Proqramlaşdırmada da eynidir:
 * - Mesaj emal edilə bilmir (format xətası, business rule pozulub, timeout)
 * - Retry cəhdləri bitdi → Mesaj DLQ-ya düşür
 * - Developer/admin DLQ-nu yoxlayır, problemi düzəldir, mesajı yenidən göndərir
 *
 * DLQ NƏYƏ LAZIMDIR?
 * ===================
 *
 * 1. MESAJ İTKİSİNİN QARŞISINI ALIR:
 *    Retry bitdikdən sonra mesaj silinməməlidir — DLQ-da saxlanılır.
 *    Developer problemi düzəldib mesajı yenidən emal edə bilər.
 *
 * 2. ANA NÖVBƏNI BLOKLAMAQ OLMAZ:
 *    "Poison message" — heç vaxt emal edilə bilməyən mesaj.
 *    DLQ olmasa, bu mesaj növbəni bloklar (digər mesajlar gözləyir).
 *    DLQ ilə "zəhərli mesaj" ayrılır, digər mesajlar normal emal olunur.
 *
 * 3. MONİTORİNQ VƏ ALERTİNG:
 *    DLQ-dakı mesaj sayı artırsa → alarm! Sistemdə problem var.
 *    Grafana/Datadog ilə DLQ ölçülərini izləmək mümkündür.
 *
 * 4. DEBUG VƏ ANALİZ:
 *    DLQ-dakı mesajlara baxaraq xətanın səbəbini tapmaq asandır.
 *    Original mesaj + xəta mesajı + cəhd sayı — hamısı saxlanılır.
 *
 * DLQ ARXİTEKTURASI:
 * ==================
 *
 * ┌──────────┐    ┌────────────┐    ┌────────────┐
 * │ Producer │───▶│ Ana Növbə  │───▶│  Consumer  │
 * └──────────┘    └────────────┘    └─────┬──────┘
 *                                         │
 *                                   Emal uğursuz?
 *                                   Retry bitdi?
 *                                         │
 *                                         ▼
 *                                  ┌──────────────┐
 *                                  │     DLQ      │ ← Ölü mesajlar burada
 *                                  │  (dead_letter │
 *                                  │   _messages)  │
 *                                  └──────┬───────┘
 *                                         │
 *                              Developer yoxlayır
 *                              Problemi düzəldir
 *                                         │
 *                                         ▼
 *                                  ┌──────────────┐
 *                                  │   Yenidən    │
 *                                  │  Ana Növbəyə │
 *                                  │   göndərilir │
 *                                  └──────────────┘
 *
 * MESAJ STATUSları:
 * ================
 * - pending:   Yenidən emal gözləyir (developer hələ baxmayıb)
 * - retrying:  Yenidən emal edilir
 * - resolved:  Problem həll olundu, mesaj uğurla emal edildi
 * - discarded: Mesaj atıldı (artıq aktuallığını itirib)
 *
 * İSTİFADƏ NÜMUNƏLƏRİ:
 * =====================
 *
 * 1. Job failed() metodunda:
 *   public function failed(\Throwable $e): void {
 *       $dlq = app(DeadLetterQueue::class);
 *       $dlq->push('payment.process', $this->payload, $e, ['order_id' => $this->orderId]);
 *   }
 *
 * 2. Admin paneldə DLQ-nu yoxlamaq:
 *   $dlq->getPending();              // Gözləyən mesajları gör
 *   $dlq->retry('dlq-uuid-here');    // Mesajı yenidən göndər
 *   $dlq->discard('dlq-uuid-here');  // Mesajı at
 *
 * 3. Monitoring:
 *   $count = $dlq->pendingCount();
 *   if ($count > 100) alert('DLQ-da 100+ mesaj var!');
 */
class DeadLetterQueue
{
    /** Database cədvəli */
    private const TABLE = 'dead_letter_messages';

    /**
     * Uğursuz mesajı DLQ-ya əlavə et.
     *
     * @param string $queue Orijinal növbə adı (məs: "payment.process", "order.created")
     * @param array $payload Mesajın məzmunu — orijinal data olduğu kimi saxlanılır
     * @param \Throwable $exception Xəta — niyə emal edilə bilmədiyini izah edir
     * @param array $metadata Əlavə məlumat (order_id, user_id, attempt sayı və s.)
     * @return string Yaradılan DLQ mesajının UUID-si
     */
    public function push(string $queue, array $payload, \Throwable $exception, array $metadata = []): string
    {
        $id = (string) \Illuminate\Support\Str::uuid();

        DB::table(self::TABLE)->insert([
            'id' => $id,
            'queue' => $queue,
            'payload' => json_encode($payload),
            'exception_class' => get_class($exception),
            'exception_message' => mb_substr($exception->getMessage(), 0, 2000),
            'exception_trace' => mb_substr($exception->getTraceAsString(), 0, 5000),
            'metadata' => json_encode($metadata),
            'status' => 'pending',
            'attempts' => 0,
            'max_attempts' => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Log::warning('Mesaj DLQ-ya əlavə edildi', [
            'dlq_id' => $id,
            'queue' => $queue,
            'exception' => $exception->getMessage(),
        ]);

        return $id;
    }

    /**
     * DLQ-dakı mesajı yenidən emal etmək üçün ana növbəyə qaytar.
     *
     * Axın:
     * 1. Mesajı DLQ-dan oxu
     * 2. Status-u "retrying" et
     * 3. Mesajı ana növbəyə göndər (dispatch)
     * 4. Uğurlu olsa → "resolved", uğursuz olsa → yenə "pending"
     *
     * @param string $id DLQ mesajının UUID-si
     * @return bool Yenidən göndərilə bildimi?
     */
    public function retry(string $id): bool
    {
        $message = DB::table(self::TABLE)->find($id);

        if (!$message || $message->status === 'resolved' || $message->status === 'discarded') {
            return false;
        }

        if ($message->attempts >= $message->max_attempts) {
            Log::error('DLQ mesajı maksimum retry sayına çatıb', [
                'dlq_id' => $id,
                'attempts' => $message->attempts,
            ]);
            return false;
        }

        DB::table(self::TABLE)->where('id', $id)->update([
            'status' => 'retrying',
            'attempts' => $message->attempts + 1,
            'last_retried_at' => now(),
            'updated_at' => now(),
        ]);

        // Real proyektdə burada mesaj orijinal queue-ya dispatch olunardı.
        // Məsələn: dispatch(new ProcessFailedMessageJob($message->queue, json_decode($message->payload, true)));
        Log::info('DLQ mesajı yenidən emal üçün göndərildi', [
            'dlq_id' => $id,
            'queue' => $message->queue,
            'attempt' => $message->attempts + 1,
        ]);

        return true;
    }

    /**
     * Mesajı "həll olundu" kimi işarələ.
     * Retry uğurlu olduqda və ya manual həll edildikdə çağırılır.
     */
    public function resolve(string $id): void
    {
        DB::table(self::TABLE)->where('id', $id)->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Mesajı at — artıq aktuallığını itirib, emal etməyə ehtiyac yoxdur.
     *
     * Nümunə: 3 gün əvvəlki "email göndər" mesajı — artıq göndərməyin mənası yoxdur.
     */
    public function discard(string $id): void
    {
        DB::table(self::TABLE)->where('id', $id)->update([
            'status' => 'discarded',
            'updated_at' => now(),
        ]);
    }

    /**
     * Gözləyən (pending) mesajları siyahıla — admin panel üçün.
     *
     * @param int $limit Maksimum nəticə sayı
     * @return \Illuminate\Support\Collection
     */
    public function getPending(int $limit = 50): \Illuminate\Support\Collection
    {
        return DB::table(self::TABLE)
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Gözləyən mesajların sayı — monitoring/alerting üçün.
     *
     * Bu ölçü Grafana/Datadog dashboard-da izlənməlidir:
     * - Normal: 0-5 mesaj
     * - Xəbərdarlıq: 10+ mesaj (nəsə problem var)
     * - Kritik: 100+ mesaj (ciddi problem!)
     */
    public function pendingCount(): int
    {
        return DB::table(self::TABLE)->where('status', 'pending')->count();
    }

    /**
     * Statistika — DLQ-nun ümumi vəziyyəti.
     * Admin dashboard üçün faydalıdır.
     */
    public function stats(): array
    {
        $stats = DB::table(self::TABLE)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return [
            'pending' => $stats['pending'] ?? 0,
            'retrying' => $stats['retrying'] ?? 0,
            'resolved' => $stats['resolved'] ?? 0,
            'discarded' => $stats['discarded'] ?? 0,
            'total' => array_sum($stats),
        ];
    }
}
