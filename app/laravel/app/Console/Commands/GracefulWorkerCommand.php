<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * GRACEFUL SHUTDOWN & SIGNAL HANDLING
 * =====================================
 * Long-running (uzun müddət işləyən) prosesləri təhlükəsiz dayandıran komanda.
 *
 * ═══════════════════════════════════════════════════════════════
 * GRACEFUL SHUTDOWN NƏDİR?
 * ═══════════════════════════════════════════════════════════════
 *
 * Real həyat analogiyası — Restoran bağlanması:
 *
 * KOBUD BAĞLANMA (kill -9):
 *   Restoran dərhal bağlanır. Müştərilər yarıda qalır. Sifariş ödənilməyib.
 *   Mətbəx yarımçıq yeməklərlə dolu. Kassa hesablanmayıb.
 *
 * GRACEFUL BAĞLANMA (SIGTERM):
 *   1. Yeni müştəri qəbul edilmir (yeni iş qəbul etmə)
 *   2. Oturan müştərilər yeməklərini bitirir (cari işi tamamla)
 *   3. Hesablar ödənilir (tranzaksiyaları commit et)
 *   4. Mətbəx təmizlənir (resursları burax)
 *   5. Restoran bağlanır (proses dayanır)
 *
 * ═══════════════════════════════════════════════════════════════
 * UNIX SİGNALLARI
 * ═══════════════════════════════════════════════════════════════
 *
 * Signal — əməliyyat sisteminin prosesə göndərdiyi "mesaj"dır.
 *
 * SIGTERM (15) — "Xahiş edirəm dayan"
 *   - Kubernetes pod-u söndürəndə göndərir
 *   - `docker stop` göndərir
 *   - `kill <pid>` (default signal)
 *   - Proses bunu tuta (handle) və təhlükəsiz dayana bilər
 *
 * SIGINT (2) — "İstifadəçi Ctrl+C basdı"
 *   - Terminalda Ctrl+C basanda göndərilir
 *   - SIGTERM kimi handle edilə bilər
 *
 * SIGKILL (9) — "Dərhal öl!"
 *   - `kill -9 <pid>`
 *   - Proses bunu TUTA BİLMƏZ — dərhal öldürülür
 *   - Kubernetes: SIGTERM göndərir → 30 saniyə gözləyir → SIGKILL göndərir
 *
 * SIGUSR1 (10) / SIGUSR2 (12) — "İstifadəçi siqnalı"
 *   - Custom məqsədlər üçün: config reload, debug mode toggle
 *
 * ═══════════════════════════════════════════════════════════════
 * KUBERNETES LIFECYCLE
 * ═══════════════════════════════════════════════════════════════
 *
 * 1. Pod shutdown tələb olunur (scale down, update, restart)
 * 2. Kubernetes → SIGTERM göndərir
 * 3. Konteyner graceful shutdown başlayır
 *    ├── Yeni iş qəbul etmir
 *    ├── Cari işi tamamlayır
 *    └── Resursları buraxır
 * 4. terminationGracePeriodSeconds (default: 30 san) gözləyir
 * 5. Hələ işləyirsə → SIGKILL göndərir (məcburi öldürür)
 *
 * Əgər cari iş 30 saniyədən çox çəkirsə:
 *   - terminationGracePeriodSeconds artırılmalıdır
 *   - Və ya iş daha kiçik hissələrə bölünməlidir
 *
 * ═══════════════════════════════════════════════════════════════
 * BU KOMANDANIN İŞLƏMƏ PRİNSİPİ
 * ═══════════════════════════════════════════════════════════════
 *
 * $shouldStop = false;  // Dayandırma bayrağı
 *
 * pcntl_signal(SIGTERM, function() { $shouldStop = true; });
 *
 * while (!$shouldStop) {
 *     $job = getNextJob();      // Növbədən iş al
 *     processJob($job);          // İşi emal et (SIGTERM gəlsə belə, bitir!)
 *     // Loop-un əvvəlinə qayıt → $shouldStop yoxla → true → çıx
 * }
 *
 * cleanup();  // Resursları burax
 *
 * NİYƏ İŞ ORTASINDA DAYANMIRUQ?
 * - İş yarımçıq qalsa, data corruption ola bilər
 * - Ödəniş yarımçıq qalsa, müştəri pulunu itirə bilər
 * - Tranzaksiya yarımçıq qalsa, database-də inconsistent data qalar
 */
class GracefulWorkerCommand extends Command
{
    /**
     * Artisan komandanın imzası.
     * İstifadə: php artisan worker:graceful --sleep=5 --max-jobs=100
     */
    protected $signature = 'worker:graceful
        {--sleep=3 : Növbə boş olduqda neçə saniyə gözləsin}
        {--max-jobs=0 : Maksimum iş sayı (0 = limitsiz)}
        {--timeout=60 : Hər işin maksimum icra müddəti (saniyə)}';

    protected $description = 'Graceful shutdown dəstəkli queue worker (SIGTERM/SIGINT handle edir)';

    /** Dayandırma bayrağı — SIGTERM/SIGINT gəldikdə true olur */
    private bool $shouldStop = false;

    /** Emal edilmiş iş sayı */
    private int $processedJobs = 0;

    public function handle(): int
    {
        $this->registerSignalHandlers();

        $sleep = (int) $this->option('sleep');
        $maxJobs = (int) $this->option('max-jobs');

        $this->info("Graceful Worker başladı. PID: " . getmypid());
        $this->info("Dayandırmaq üçün: kill -SIGTERM " . getmypid());

        // ─── ANA LOOP ───
        // Hər iterasiyada:
        // 1. $shouldStop bayrağını yoxla (SIGTERM gəlibmi?)
        // 2. Yeni iş al və emal et
        // 3. max-jobs limitini yoxla
        while (!$this->shouldStop) {
            // pcntl_signal_dispatch() — gözləyən siqnalları emal et.
            // PHP default olaraq siqnalları "saxlayır", bu metod onları
            // əvvəlcə qeydiyyat etdiyimiz handler-lərə çatdırır.
            // Bu çağırılmasa, $shouldStop heç vaxt true olmaz!
            pcntl_signal_dispatch();

            if ($this->shouldStop) {
                break;
            }

            // Simulyasiya: növbədən iş almaq
            $job = $this->getNextJob();

            if ($job === null) {
                // Növbə boşdur — gözlə
                $this->line("<comment>Növbə boşdur, {$sleep} saniyə gözlənilir...</comment>");
                sleep($sleep);
                continue;
            }

            // İşi emal et — SIGTERM gəlsə belə, bu iş tam bitəcək
            $this->processJob($job);
            $this->processedJobs++;

            // Max jobs limitini yoxla
            if ($maxJobs > 0 && $this->processedJobs >= $maxJobs) {
                $this->info("Maksimum iş sayına çatıldı ({$maxJobs}). Dayandırılır...");
                break;
            }
        }

        // ─── CLEANUP (Təmizlik) ───
        $this->cleanup();

        $this->info("Graceful Worker dayandırıldı. Emal edilmiş işlər: {$this->processedJobs}");

        return self::SUCCESS;
    }

    /**
     * UNIX siqnal handler-lərini qeydiyyat et.
     *
     * pcntl_signal() — PHP-nin POSIX siqnal handler funksiyasıdır.
     * Siqnal gəldikdə, verilmiş callback çağırılır.
     *
     * DİQQƏT: pcntl extension yalnız Linux/macOS-da mövcuddur.
     * Windows-da bu extension yoxdur — Docker istifadə edin.
     */
    private function registerSignalHandlers(): void
    {
        // SIGTERM — Kubernetes, Docker, systemd göndərir
        pcntl_signal(SIGTERM, function (int $signal) {
            $this->info("\n⚡ SIGTERM alındı — cari iş bitdikdən sonra dayanacaq...");
            Log::info('Worker SIGTERM aldı, graceful shutdown başladı', [
                'pid' => getmypid(),
                'processed_jobs' => $this->processedJobs,
            ]);
            $this->shouldStop = true;
        });

        // SIGINT — Ctrl+C basılanda göndərilir
        pcntl_signal(SIGINT, function (int $signal) {
            $this->info("\n⚡ SIGINT (Ctrl+C) alındı — cari iş bitdikdən sonra dayanacaq...");
            $this->shouldStop = true;
        });

        // SIGUSR1 — Custom siqnal: status göstər
        pcntl_signal(SIGUSR1, function (int $signal) {
            $this->info("📊 Status: {$this->processedJobs} iş emal edilib");
        });
    }

    /**
     * Növbədən növbəti işi al.
     * Real proyektdə bu Redis/RabbitMQ-dan iş alar.
     */
    private function getNextJob(): ?array
    {
        // Simulyasiya: təsadüfi olaraq iş qaytarırıq
        if (rand(1, 3) === 1) {
            return null; // Növbə boşdur
        }

        return [
            'id' => uniqid('job_'),
            'type' => ['email', 'payment', 'notification'][rand(0, 2)],
            'created_at' => now()->toISOString(),
        ];
    }

    /**
     * İşi emal et.
     *
     * VACİB: Bu metod SIGTERM gəlsə belə tam icra olunmalıdır!
     * Yarımçıq buraxmaq data corruption-a səbəb ola bilər.
     */
    private function processJob(array $job): void
    {
        $this->line("İş emal edilir: {$job['id']} ({$job['type']})");

        // Simulyasiya: iş 1-3 saniyə çəkir
        sleep(rand(1, 3));

        $this->info("✓ İş tamamlandı: {$job['id']}");
    }

    /**
     * Təmizlik — proses dayandırılmadan əvvəl resursları burax.
     *
     * Burada nələr edilə bilər:
     * - Database bağlantılarını bağla
     * - Açıq faylları bağla
     * - RabbitMQ bağlantısını bağla
     * - Temporary faylları sil
     * - Son statistikanı log-la
     */
    private function cleanup(): void
    {
        Log::info('Worker cleanup başladı', [
            'pid' => getmypid(),
            'processed_jobs' => $this->processedJobs,
        ]);

        // Resursları burax...
        $this->line('Resurslar buraxıldı, bağlantılar bağlandı.');
    }
}
