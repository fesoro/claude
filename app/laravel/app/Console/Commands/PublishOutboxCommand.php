<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\PublishOutboxMessagesJob;
use Illuminate\Console\Command;

/**
 * OUTBOX PUBLISHER ARTISAN COMMAND (YENİLƏNMİŞ VERSİYA)
 * ========================================================
 * Outbox mesajlarını dərc etmək üçün PublishOutboxMessagesJob dispatch edir.
 *
 * ÖNCƏKİ VERSİYA:
 * ────────────────
 * Əvvəl bu command birbaşa OutboxPublisher-i çağırırdı (inline/sinxron).
 * Bu o deməkdir ki, `php artisan outbox:publish` çağırıldıqda,
 * bütün iş ELƏ O AN, EYNİ PROSESDƏ icra olunurdu.
 *
 * PROBLEMLƏRİ:
 * 1. Sinxron idi — cron çağırışı bitənə qədər gözləyirdi
 * 2. Eyni anda iki cron eyni mesajları emal edə bilərdi (race condition)
 * 3. Queue worker-dən asılı deyildi — ayrı monitoring lazım idi
 * 4. Retry mexanizmi yox idi — uğursuz olsa, növbəti cron-a qədər gözləyirdi
 *
 * YENİ VERSİYA:
 * ─────────────
 * İndi bu command PublishOutboxMessagesJob-u QUEUE-yə göndərir.
 * Job ShouldBeUnique interfeysi sayəsində eyni anda yalnız BİR instance işləyir.
 * Queue worker retry, backoff, timeout kimi xüsusiyyətlər təmin edir.
 *
 * İSTİFADƏ:
 * php artisan outbox:publish        — Job-u queue-yə göndərir
 * php artisan outbox:publish --sync — Job-u sinxron icra edir (debug üçün)
 *
 * SCHEDULER-DƏ:
 * Artıq command əvəzinə birbaşa Job dispatch etmək daha yaxşıdır:
 * $schedule->job(new PublishOutboxMessagesJob())->everyMinute();
 * Amma bu command hələ manual istifadə üçün saxlanılır.
 */
class PublishOutboxCommand extends Command
{
    protected $signature = 'outbox:publish
                            {--sync : Job-u sinxron icra et (queue istifadə etmədən)}';

    protected $description = 'Outbox mesajlarını dərc etmək üçün PublishOutboxMessagesJob dispatch edir';

    public function handle(): int
    {
        /**
         * --sync flaqı ilə dispatchSync() çağırılır.
         * Bu, debug və test üçün faydalıdır:
         * - php artisan outbox:publish --sync → elə indi icra et, nəticəni göstər
         * - php artisan outbox:publish → queue-yə göndər, worker emal edəcək
         */
        if ($this->option('sync')) {
            $this->info('Outbox Job sinxron icra olunur...');
            PublishOutboxMessagesJob::dispatchSync();
            $this->info('Outbox mesajları sinxron dərc olundu.');
        } else {
            PublishOutboxMessagesJob::dispatch();
            $this->info('PublishOutboxMessagesJob queue-yə göndərildi. Worker emal edəcək.');
        }

        return self::SUCCESS;
    }
}
