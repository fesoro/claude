<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * MONITOR FAILED JOBS COMMAND
 * ===========================
 * Uğursuz job-ları terminal-da göstərir.
 *
 * İSTİFADƏ:
 * php artisan queue:failed-monitor           → siyahı göstər
 * php artisan queue:failed-monitor --count   → yalnız say göstər
 *
 * NƏYƏ LAZIMDIR?
 * - Tez xülasə almaq üçün (dashboard olmadan)
 * - CI/CD pipeline-da health check kimi
 * - Cron ilə gündəlik hesabat üçün
 */
class MonitorFailedJobsCommand extends Command
{
    protected $signature = 'queue:failed-monitor
                            {--count : Yalnız sayı göstər}';

    protected $description = 'Uğursuz queue job-larını göstər';

    public function handle(): int
    {
        $failedJobs = DB::table('failed_jobs')
            ->orderByDesc('failed_at')
            ->get();

        if ($failedJobs->isEmpty()) {
            $this->info('Uğursuz job yoxdur. Hər şey qaydasındadır!');
            return self::SUCCESS;
        }

        if ($this->option('count')) {
            $this->warn("Uğursuz job sayı: {$failedJobs->count()}");
            return $failedJobs->count() > 0 ? self::FAILURE : self::SUCCESS;
        }

        $this->warn("Uğursuz job sayı: {$failedJobs->count()}");
        $this->newLine();

        $rows = $failedJobs->map(fn($job) => [
            $job->uuid,
            // Job class adını payload-dan çıxar
            json_decode($job->payload, true)['displayName'] ?? 'N/A',
            $job->queue,
            substr($job->exception, 0, 80) . '...',
            $job->failed_at,
        ])->toArray();

        $this->table(
            ['UUID', 'Job', 'Queue', 'Xəta', 'Tarix'],
            $rows,
        );

        $this->newLine();
        $this->info('Retry: php artisan queue:retry {uuid}');
        $this->info('Hamısını retry: php artisan queue:retry all');
        $this->info('Hamısını sil: php artisan queue:flush');

        return self::SUCCESS;
    }
}
