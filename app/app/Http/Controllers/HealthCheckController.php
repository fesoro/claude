<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Connection\AMQPStreamConnection;

/**
 * HEALTH CHECK CONTROLLER
 * ========================
 * Sistemin sağlamlıq vəziyyətini yoxlayan endpoint.
 *
 * ═══════════════════════════════════════════════════════════════════
 * HEALTH CHECK PATTERN — NƏDİR VƏ NİYƏ LAZIMDIR?
 * ═══════════════════════════════════════════════════════════════════
 *
 * Health check — xidmətin işlək olub-olmadığını yoxlayan xüsusi endpoint-dir.
 * Onu insanlar yox, maşınlar (load balancer, orchestrator) çağırır.
 *
 * KİM İSTİFADƏ EDİR?
 *
 * 1. LOAD BALANCER (Nginx, AWS ALB, HAProxy):
 *    - Hər 10-30 saniyədən bir /health endpoint-inə sorğu göndərir
 *    - "healthy" cavab almasa, traffic-i bu server-ə göndərməyi dayandırır
 *    - Server düzələndə avtomatik yenidən traffic göndərir
 *    - Bu sayədə istifadəçi heç vaxt "xarab" serverə düşmür
 *
 * 2. KUBERNETES / DOCKER:
 *    - Liveness probe: Konteyner donubmu? Donubsa restart et.
 *    - Readiness probe: Konteyner traffic qəbul etməyə hazırdırmı?
 *    - Startup probe: Konteyner hələ başlayırsa, gözlə.
 *
 * 3. MONİTORİNQ SİSTEMLƏRİ (Prometheus, Datadog, Grafana):
 *    - Health check nəticələrini qrafik şəklində göstərir
 *    - Xəta halında alert göndərir (Slack, email, SMS)
 *
 * ═══════════════════════════════════════════════════════════════════
 * STATUS SƏVİYYƏLƏRİ: HEALTHY vs DEGRADED vs UNHEALTHY
 * ═══════════════════════════════════════════════════════════════════
 *
 * HEALTHY (Sağlam — HTTP 200):
 *   Bütün xidmətlər işləyir. Heç bir problem yoxdur.
 *   Load balancer: traffic göndərməyə davam et.
 *
 * DEGRADED (Zəifləmiş — HTTP 200):
 *   Əsas xidmətlər işləyir, amma bəzi köməkçi xidmətlər xarabdır.
 *   Məsələn: Redis işləmir (cache yoxdur, amma app işləyir),
 *   RabbitMQ düşüb (async əməliyyatlar gecikmə ilə işləyəcək).
 *   Load balancer: traffic göndər, amma alert göndər.
 *
 * UNHEALTHY (Xəstə — HTTP 503):
 *   Əsas xidmətlər işləmir. Məsələn, heç bir DB bağlantısı yoxdur.
 *   Load balancer: traffic göndərmə, bu server-i çıxart.
 *
 * NƏYƏ GÖRƏ DEGRADED LAZIMDİR?
 *   Redis düşəndə app-i tamamilə söndürmək düzgün deyil.
 *   App hələ də işləyir, sadəcə yavaşdır (cache yoxdur).
 *   Degraded status bunu bildirməyə imkan verir.
 */
class HealthCheckController extends Controller
{
    /**
     * Əsas verilənlər bazası bağlantıları.
     * Bunlar OLMADAN app işləyə bilməz — unhealthy olur.
     *
     * @var string[]
     */
    private const CRITICAL_DB_CONNECTIONS = [
        'user_db',
        'product_db',
        'order_db',
        'payment_db',
    ];

    /**
     * GET /api/health
     * Sistemin sağlamlıq vəziyyətini yoxla.
     *
     * CAVAB FORMATI:
     * {
     *   "status": "healthy" | "degraded" | "unhealthy",
     *   "checks": {
     *     "database": { "user_db": true, "product_db": false, ... },
     *     "redis": true,
     *     "rabbitmq": true,
     *     "disk_space": { "free_gb": 15.5, "total_gb": 50.0, "usage_percent": 69.0 }
     *   },
     *   "timestamp": "2026-04-13T12:00:00Z",
     *   "version": "v1"
     * }
     *
     * HTTP STATUS KODLARI:
     *   200 → healthy və ya degraded
     *   503 → unhealthy (Service Unavailable)
     *
     * @return JsonResponse Sağlamlıq hesabatı
     */
    public function __invoke(): JsonResponse
    {
        // Bütün yoxlamaları aparırıq
        $dbChecks = $this->checkDatabases();
        $redisCheck = $this->checkRedis();
        $rabbitMqCheck = $this->checkRabbitMq();
        $diskCheck = $this->checkDiskSpace();

        // Ümumi statusu hesabla
        $status = $this->calculateOverallStatus($dbChecks, $redisCheck, $rabbitMqCheck, $diskCheck);

        // HTTP status kodu: unhealthy → 503, digərləri → 200
        $httpCode = ($status === 'unhealthy') ? 503 : 200;

        return response()->json([
            'status' => $status,
            'checks' => [
                'database' => $dbChecks,
                'redis' => $redisCheck,
                'rabbitmq' => $rabbitMqCheck,
                'disk_space' => $diskCheck,
            ],
            'timestamp' => now()->toIso8601String(),
            'version' => config('api.version', 'v1'),
        ], $httpCode);
    }

    /**
     * Bütün verilənlər bazası bağlantılarını yoxla.
     *
     * HƏR BİR BAĞLANTI ÜÇÜN:
     * 1. DB::connection($name) ilə bağlantı al
     * 2. getPdo() ilə real bağlantı qur (lazy connection)
     * 3. SELECT 1 sorğusu göndər
     * 4. Uğurludursa true, deyilsə false
     *
     * NƏYƏ GÖRƏ SELECT 1?
     * - Ən sadə və ən sürətli SQL sorğusudur
     * - Heç bir cədvəl lazım deyil
     * - Bağlantının real işlək olduğunu sübut edir
     * - getPdo() bağlantı pool-dan alır, amma bağlantı "köhnə" ola bilər
     *   SELECT 1 isə real sorğu göndərir
     *
     * @return array<string, bool> Hər bağlantının statusu
     */
    private function checkDatabases(): array
    {
        $results = [];

        foreach (self::CRITICAL_DB_CONNECTIONS as $connection) {
            try {
                // DB::connection() — Laravel-in database manager-indən bağlantı al
                // getPdo() — PHP Data Objects bağlantısını qur (lazy — yalnız çağrılanda bağlanır)
                DB::connection($connection)->getPdo();

                // SELECT 1 — verilənlər bazasının real cavab verdiyini yoxla
                DB::connection($connection)->select('SELECT 1');

                $results[$connection] = true;
            } catch (\Throwable $e) {
                // Bağlantı xətasını log-la, amma app-i çökdürmə
                Log::warning("Health check: {$connection} bağlantısı uğursuz", [
                    'error' => $e->getMessage(),
                ]);

                $results[$connection] = false;
            }
        }

        return $results;
    }

    /**
     * Redis bağlantısını yoxla.
     *
     * Redis — in-memory cache və session store-dur.
     * PING əmri göndəririk, PONG cavabı gəlməldir.
     *
     * Redis OLMADAN app işləyə bilər (cache miss olacaq, DB-dən oxuyacaq),
     * ona görə Redis xətası "degraded" status yaradır, "unhealthy" yox.
     *
     * @return bool Redis işləyirsə true
     */
    private function checkRedis(): bool
    {
        try {
            // Redis::ping() → Redis serverinə PING əmri göndərir
            // Cavab: PONG (string) və ya true (PhpRedis driver)
            $response = Redis::ping();

            // PhpRedis driver true qaytarır, Predis driver "+PONG" qaytarır
            return $response === true || $response === '+PONG' || $response === 'PONG';
        } catch (\Throwable $e) {
            Log::warning('Health check: Redis bağlantısı uğursuz', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * RabbitMQ bağlantısını yoxla.
     *
     * RabbitMQ — mesaj broker-dir (async kommunikasiya üçün).
     * Bağlantı qurmağa çalışırıq, uğurludursa dərhal bağlayırıq.
     *
     * TRY/CATCH YANAŞMASI:
     * RabbitMQ xarici servisdir, hər an düşə bilər.
     * Əgər düşübsə, app hələ də işləyə bilər (outbox mesajlar DB-də gözləyir).
     * Ona görə RabbitMQ xətası "degraded" yaradır.
     *
     * @return bool RabbitMQ işləyirsə true
     */
    private function checkRabbitMq(): bool
    {
        try {
            // Config-dən RabbitMQ parametrlərini oxu
            $host = config('rabbitmq.host', 'localhost');
            $port = (int) config('rabbitmq.port', 5672);
            $user = config('rabbitmq.user', 'guest');
            $password = config('rabbitmq.password', 'guest');
            $vhost = config('rabbitmq.vhost', '/');

            // AMQPStreamConnection — RabbitMQ-ya TCP bağlantısı qurur
            // Bağlantı uğurludursa, dərhal bağlayırıq (yalnız yoxlama məqsədlidir)
            // connection_timeout: 3 saniyə — çox gözləməmək üçün
            $connection = new AMQPStreamConnection(
                $host,
                $port,
                $user,
                $password,
                $vhost,
                false,       // insist
                'AMQPLAIN',  // login_method
                null,        // login_response
                'en_US',     // locale
                3,           // connection_timeout (saniyə)
            );

            // isConnected() — bağlantının aktiv olduğunu yoxla
            $isConnected = $connection->isConnected();

            // Bağlantını bağla — resurs sızması olmasın
            $connection->close();

            return $isConnected;
        } catch (\Throwable $e) {
            Log::warning('Health check: RabbitMQ bağlantısı uğursuz', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Disk sahəsini yoxla.
     *
     * Disk dolduqda app çökə bilər (log yaza bilmir, fayl upload olmur).
     * 90%-dən çox istifadə "xəbərdarlıq" səviyyəsidir.
     *
     * disk_free_space() — PHP-nin daxili funksiyası, baytlarla boş yer qaytarır
     * disk_total_space() — ümumi disk həcmi
     *
     * @return array{free_gb: float, total_gb: float, usage_percent: float} Disk statistikası
     */
    private function checkDiskSpace(): array
    {
        // storage_path() — Laravel-in storage/ qovluğunun yolu
        // Bu qovluq log, cache, session, upload fayllarını saxlayır
        $path = storage_path();

        // Baytdan GB-a çevirmək üçün 1024^3-ə bölürük
        $freeBytes = disk_free_space($path);
        $totalBytes = disk_total_space($path);

        $freeGb = round($freeBytes / (1024 ** 3), 2);
        $totalGb = round($totalBytes / (1024 ** 3), 2);

        // İstifadə faizi: (istifadə olunan / ümumi) * 100
        $usagePercent = round((($totalBytes - $freeBytes) / $totalBytes) * 100, 1);

        return [
            'free_gb' => $freeGb,
            'total_gb' => $totalGb,
            'usage_percent' => $usagePercent,
        ];
    }

    /**
     * Ümumi sağlamlıq statusunu hesabla.
     *
     * ALGORİTM:
     * 1. Hər hansı kritik DB bağlantısı düşübsə → UNHEALTHY
     * 2. Bütün DB-lər işləyir, amma Redis/RabbitMQ düşübsə → DEGRADED
     * 3. Disk istifadəsi 90%-dən çoxdursa → DEGRADED
     * 4. Hamısı yaxşıdırsa → HEALTHY
     *
     * PRİORİTET:
     *   unhealthy > degraded > healthy
     *   Əgər həm degraded həm unhealthy şərt varsa, unhealthy qalib gəlir.
     *
     * @param array<string, bool> $dbChecks DB yoxlama nəticələri
     * @param bool $redisOk Redis statusu
     * @param bool $rabbitMqOk RabbitMQ statusu
     * @param array{usage_percent: float} $diskCheck Disk statistikası
     * @return string 'healthy' | 'degraded' | 'unhealthy'
     */
    private function calculateOverallStatus(
        array $dbChecks,
        bool $redisOk,
        bool $rabbitMqOk,
        array $diskCheck,
    ): string {
        // Kritik yoxlama: Hər hansı DB bağlantısı düşübsə → unhealthy
        // in_array(false, ...) — massivdə false dəyər varsa true qaytarır
        $anyDbDown = in_array(false, $dbChecks, true);

        if ($anyDbDown) {
            return 'unhealthy';
        }

        // Qeyri-kritik xidmətlər yoxlaması
        // Redis və RabbitMQ olmadan app işləyə bilər, amma optimal deyil
        if (!$redisOk || !$rabbitMqOk) {
            return 'degraded';
        }

        // Disk sahəsi yoxlaması — 90%-dən çox istifadə xəbərdarlıqdır
        if ($diskCheck['usage_percent'] > 90.0) {
            return 'degraded';
        }

        return 'healthy';
    }
}
