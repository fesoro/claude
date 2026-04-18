<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Src\Order\Infrastructure\Outbox\OutboxPublisher;

/**
 * OUTBOX MESAJLARINI DƏRC ETMƏ JOB-U (Outbox Pattern)
 * ======================================================
 * Outbox cədvəlindəki göndərilməmiş mesajları RabbitMQ-ya göndərir.
 *
 * ÖNCƏKİ YANAŞMA:
 * ────────────────
 * Əvvəl bu iş PublishOutboxCommand (Artisan command) ilə görülürdü:
 * php artisan outbox:publish
 *
 * Bu yanaşmanın problemləri:
 * 1. Cron hər dəqiqə işləyir — əgər çox mesaj varsa, növbəti cron gəlmədən bitməyə bilər
 * 2. Eyni anda iki proses eyni mesajları emal edə bilər (race condition)
 * 3. Artisan command queue worker-dən asılı deyil — ayrı monitoring lazımdır
 *
 * YENİ YANAŞMA (Bu Job):
 * ─────────────────────
 * Job queue-yə göndərilir və worker tərəfindən emal olunur.
 * ShouldBeUnique interfeysi ilə eyni anda yalnız BİR instance işləyir.
 * Laravel Scheduler hər dəqiqə bu Job-u dispatch edir.
 *
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ShouldBeUnique İNTERFEYSİ                                            ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                        ║
 * ║  ShouldBeUnique — eyni Job-un eyni anda BİRDƏN ÇOX instance-ının     ║
 * ║  queue-da olmasını QADAĞAN edir.                                       ║
 * ║                                                                        ║
 * ║  PROBLEM (ShouldBeUnique olmadan):                                     ║
 * ║  ─────────────────────────────────                                     ║
 * ║  Scheduler hər dəqiqə PublishOutboxMessagesJob dispatch edir.         ║
 * ║  Əgər job 2 dəqiqə çəkirsə:                                          ║
 * ║  - Dəqiqə 1: Job#1 başlayır (işləyir...)                             ║
 * ║  - Dəqiqə 2: Job#2 dispatch olunur → İNDİ 2 EYNI JOB İŞLƏYİR!      ║
 * ║  - Dəqiqə 3: Job#3 dispatch olunur → 3 EYNI JOB!                     ║
 * ║  Bu, eyni mesajın 2-3 dəfə RabbitMQ-ya göndərilməsinə səbəb olur.    ║
 * ║                                                                        ║
 * ║  HƏLLİ (ShouldBeUnique ilə):                                          ║
 * ║  ────────────────────────────                                          ║
 * ║  - Dəqiqə 1: Job#1 başlayır (işləyir...)                             ║
 * ║  - Dəqiqə 2: Job#2 dispatch edilir → RƏDD EDİLİR (Job#1 hələ işləyir)║
 * ║  - Dəqiqə 3: Job#1 bitib → Job#3 dispatch edilir → QƏBUL EDİLİR     ║
 * ║                                                                        ║
 * ║  NECƏ İŞLƏYİR?                                                        ║
 * ║  ─────────────                                                         ║
 * ║  Laravel cache driver (Redis/DB) vasitəsilə "lock" (kilit) qoyur.    ║
 * ║  Job dispatch olunanda:                                                ║
 * ║  1. Cache-dən yoxla: bu Job artıq queue-dadır?                        ║
 * ║  2. Bəli → dispatch-ı rədd et (dublikat)                             ║
 * ║  3. Xeyr → lock qoy, dispatch et                                      ║
 * ║  4. Job bitdikdə lock silinir                                         ║
 * ║                                                                        ║
 * ║  KONFIQURASIYA:                                                        ║
 * ║  - uniqueId() — lock açarını təyin edir (default: class adı)          ║
 * ║  - uniqueFor() — lock neçə saniyə saxlansın (default: job bitənə qədər)║
 * ║  - uniqueVia() — hansı cache store istifadə olunsun                   ║
 * ║                                                                        ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 *
 * OUTBOX PATTERN XATIRLATMASI:
 * ═══════════════════════════
 * 1. Handler → Order + OutboxMessage eyni DB transaction-da yazılır
 * 2. Bu Job → outbox_messages-dən published_at IS NULL olanları oxuyur
 * 3. Hər birini RabbitMQ-ya göndərir
 * 4. Uğurlu olanları published_at ilə işarələyir
 *
 * SCHEDULER QEYDIYYATI (app/Console/Kernel.php):
 * $schedule->job(new PublishOutboxMessagesJob())->everyMinute();
 */
class PublishOutboxMessagesJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Bu Job idempotent-dir — neçə dəfə icra olunsa da eyni nəticə verir.
     * Çünki artıq published_at olan mesajları yenidən göndərmir.
     * Buna görə retry etmək təhlükəsizdir.
     */
    public int $tries = 3;

    /**
     * Cəhdlər arası gözləmə: 5, 15 saniyə.
     * Outbox publish tez olmalıdır, uzun gözləmə lazım deyil.
     */
    public array $backoff = [5, 15];

    /**
     * Timeout: 120 saniyə.
     * Çox mesaj olduqda emal uzun çəkə bilər.
     */
    public int $timeout = 120;

    /**
     * Unique lock müddəti — 120 saniyə.
     *
     * Əgər Job 120 saniyədən çox çəkirsə (çökmə, timeout),
     * lock avtomatik silinir ki, növbəti Job dispatch oluna bilsin.
     * Bu "deadlock" (ölü kilit) vəziyyətinin qarşısını alır.
     *
     * @return int Lock müddəti (saniyə)
     */
    public function uniqueFor(): int
    {
        return 120;
    }

    /**
     * Outbox mesajlarını RabbitMQ-ya göndər.
     *
     * OutboxPublisher dependency injection ilə inject olunur.
     * publishPending() metodu:
     * 1. published_at IS NULL olan mesajları oxuyur
     * 2. Hər birini RabbitMQ-ya göndərir
     * 3. Uğurlu olanları published_at = now() ilə yeniləyir
     * 4. Göndərilən mesaj sayını qaytarır
     *
     * @param OutboxPublisher $publisher Outbox mesaj göndəricisi
     */
    public function handle(OutboxPublisher $publisher): void
    {
        Log::info('Outbox mesajları dərc olunur...');

        $count = $publisher->publishPending();

        if ($count > 0) {
            Log::info("{$count} outbox mesajı RabbitMQ-ya göndərildi");
        } else {
            Log::debug('Göndəriləcək outbox mesajı yoxdur');
        }
    }

    /**
     * Outbox publish uğursuz oldu.
     * Bu kritikdir — mesajlar RabbitMQ-ya çatmırsa, digər servisler
     * yeni sifarişlərdən xəbərsiz qalır.
     */
    public function failed(\Throwable $exception): void
    {
        Log::critical('Outbox mesajları dərc edilə bilmədi!', [
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }

    /**
     * Outbox job-ları "outbox" adlı ayrı queue-da işləyir.
     * Bu, digər job-lardan asılı olmadan, müstəqil emal olunmasını təmin edir.
     */
    public function queue(): string
    {
        return 'outbox';
    }
}
