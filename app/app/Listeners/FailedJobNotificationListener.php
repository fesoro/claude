<?php

declare(strict_types=1);

namespace App\Listeners;

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Log;

/**
 * FAILED JOB NOTIFICATION LISTENER
 * ==================================
 * Queue job uğursuz olduqda avtomatik bildiriş göndərir.
 *
 * Laravel JobFailed event-ini dinləyir — bu event hər hansı
 * bir job bütün retry cəhdlərindən sonra uğursuz olduqda fire olur.
 *
 * NƏYƏ LAZIMDIR?
 * - Admin dərhal xəbər tutsun ki, bir proses çöküb
 * - Problemi tez həll etmək üçün (SLA, müştəri gözləntisi)
 * - failed_jobs cədvəlinə baxmağı unutmamaq üçün
 *
 * QEYDİYYAT:
 * AppServiceProvider-da Event::listen(JobFailed::class, FailedJobNotificationListener::class)
 */
class FailedJobNotificationListener
{
    public function handle(JobFailed $event): void
    {
        $jobName = $event->job->resolveName();
        $exception = $event->exception->getMessage();
        $connectionName = $event->connectionName;

        Log::critical("Queue Job uğursuz oldu!", [
            'job' => $jobName,
            'connection' => $connectionName,
            'exception' => $exception,
            'failed_at' => now()->toIso8601String(),
        ]);

        // Production-da admin-ə email göndərilə bilər:
        // Mail::to(config('mail.admin_email'))->send(new JobFailedAlertMail($jobName, $exception));
    }
}
