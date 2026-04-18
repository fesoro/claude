<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * FAILED JOB CONTROLLER
 * =====================
 * Uğursuz queue job-larını idarə etmək üçün API.
 *
 * QUEUE FAILED JOBS NƏDİR?
 * Job bütün retry cəhdlərindən sonra hələ uğursuz olursa,
 * Laravel onu failed_jobs cədvəlinə yazır.
 *
 * Bu controller admin-lərə imkan verir:
 * - Uğursuz job-ları görmək
 * - Yenidən cəhd etmək (retry)
 * - Silmək
 *
 * REAL DÜNYADA:
 * - Admin dashboard-da "Failed Jobs" bölməsi olur
 * - Monitoring tool (Horizon, Telescope) bunu vizual göstərir
 * - Alert sistemi uğursuz job-lar haqqında bildiriş göndərir
 */
class FailedJobController extends Controller
{
    /**
     * GET /api/admin/failed-jobs
     * Bütün uğursuz job-ların siyahısı.
     */
    public function index(): JsonResponse
    {
        $failedJobs = DB::table('failed_jobs')
            ->orderByDesc('failed_at')
            ->paginate(20);

        return ApiResponse::success(data: $failedJobs);
    }

    /**
     * GET /api/admin/failed-jobs/{id}
     * Tək uğursuz job-un detalları.
     */
    public function show(string $id): JsonResponse
    {
        $job = DB::table('failed_jobs')->where('uuid', $id)->first();

        if (!$job) {
            return ApiResponse::error('Uğursuz job tapılmadı', code: 404);
        }

        return ApiResponse::success(data: $job);
    }

    /**
     * POST /api/admin/failed-jobs/{id}/retry
     * Uğursuz job-u yenidən queue-ya göndər.
     *
     * queue:retry artisan command-ı istifadə edir.
     * Job failed_jobs-dan silinir və orijinal queue-ya qayıdır.
     */
    public function retry(string $id): JsonResponse
    {
        $job = DB::table('failed_jobs')->where('uuid', $id)->first();

        if (!$job) {
            return ApiResponse::error('Uğursuz job tapılmadı', code: 404);
        }

        Artisan::call('queue:retry', ['id' => [$id]]);

        return ApiResponse::success(message: 'Job yenidən queue-ya göndərildi');
    }

    /**
     * POST /api/admin/failed-jobs/retry-all
     * Bütün uğursuz job-ları yenidən cəhd et.
     */
    public function retryAll(): JsonResponse
    {
        $count = DB::table('failed_jobs')->count();

        if ($count === 0) {
            return ApiResponse::success(message: 'Uğursuz job yoxdur');
        }

        Artisan::call('queue:retry', ['id' => ['all']]);

        return ApiResponse::success(
            message: "{$count} job yenidən queue-ya göndərildi"
        );
    }

    /**
     * DELETE /api/admin/failed-jobs/{id}
     * Uğursuz job-u sil.
     */
    public function destroy(string $id): JsonResponse
    {
        $deleted = DB::table('failed_jobs')->where('uuid', $id)->delete();

        if ($deleted === 0) {
            return ApiResponse::error('Uğursuz job tapılmadı', code: 404);
        }

        return ApiResponse::success(message: 'Uğursuz job silindi');
    }

    /**
     * DELETE /api/admin/failed-jobs
     * Bütün uğursuz job-ları sil.
     */
    public function flush(): JsonResponse
    {
        $count = DB::table('failed_jobs')->count();
        Artisan::call('queue:flush');

        return ApiResponse::success(
            message: "{$count} uğursuz job silindi"
        );
    }
}
