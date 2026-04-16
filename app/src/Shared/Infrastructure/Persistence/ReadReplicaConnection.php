<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Persistence;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * READ REPLICA CONNECTION — Ayrı Oxuma Bazası İdarəsi
 * =====================================================
 *
 * PROBLEMİ ANLAYAQ:
 * =================
 * Bir verilənlər bazası həm yazma (INSERT/UPDATE/DELETE), həm oxuma (SELECT) edir.
 * Yük artdıqda DB darboğaz olur — xüsusən oxuma sorğuları yazma sorğularını yavaşladır.
 *
 * Statistika: Tipik web tətbiqdə sorğuların 80-90%-i oxumadır (SELECT).
 * Əgər oxuma sorğularını ayrı serverə yönləndirə bilsək, əsas DB-yə 80% az yük düşər.
 *
 * HƏLLİ — MASTER-SLAVE (PRIMARY-REPLICA) REPLİKASİYA:
 * =====================================================
 * Master (Primary): Yazma əməliyyatları burada olur. Tək nöqtə.
 * Slave (Replica): Master-in kopyası. Yalnız oxuma üçün. Bir neçə ola bilər.
 *
 * Necə işləyir?
 * 1. Tətbiq master-ə INSERT/UPDATE/DELETE göndərir.
 * 2. Master dəyişikliyi binary log-a yazır.
 * 3. Replica binary log-u oxuyub öz datasını yeniləyir (replication).
 * 4. Tətbiq oxuma sorğularını replica-dan edir.
 *
 *    ┌──────────┐     yazma      ┌──────────┐
 *    │ Tətbiq   │───────────────▶│  Master   │
 *    │          │                │ (Primary) │
 *    │          │     oxuma      └────┬──────┘
 *    │          │◀──────────────┐     │ replication
 *    └──────────┘               │     ▼
 *                          ┌────┴─────────┐
 *                          │   Replica    │
 *                          │  (Read-only) │
 *                          └──────────────┘
 *
 * EVENTUAL CONSISTENCY:
 * =====================
 * Replica master-dən BİR AZ GECİKMƏ ilə yenilənir (millisaniyələrdən saniyələrə qədər).
 * Bu o deməkdir ki, yazdıqdan dərhal sonra oxusan, köhnə data görə bilərsən.
 *
 * Nümunə: İstifadəçi profil şəklini dəyişdi (master-ə yazıldı).
 * Dərhal səhifəni yenilədi → replica hələ köhnə şəkli göstərir (1 saniyə gecikmə).
 * 2 saniyə sonra yeniləyir → yeni şəkil görünür.
 *
 * Bu problemi "read-your-own-writes" strategiyası ilə həll edirik:
 * Yazandan sonra müəyyən müddət oxumanı da master-dən et.
 *
 * CQRS İLƏ ƏLAQƏSİ:
 * ==================
 * CQRS artıq Command (yazma) və Query (oxuma) ayırır.
 * Read Replica bunu infrastructure səviyyəsində tamamlayır:
 * - CommandBus → handler → master DB-yə yazır.
 * - QueryBus → handler → replica DB-dən oxuyur.
 * Bu, CQRS-in tam potensialını açır.
 *
 * LARAVEL-DƏ STICKY OPTION:
 * ==========================
 * Laravel database.php-də `sticky => true` seçimi var.
 * Bu, bir request ərzində yazma oldusa, qalan oxumaları da master-dən edir.
 * Bu, read-your-own-writes problemini avtomatik həll edir.
 */
class ReadReplicaConnection
{
    /**
     * Yazma olduqda oxumanı master-dən etmək üçün flag.
     * Bu request ərzində yazma oldusa, true olur.
     * "Sticky session" mexanizmi — read-your-own-writes təmin edir.
     */
    private bool $hasWrittenInCurrentRequest = false;

    /**
     * Yazma üçün connection adı (master).
     */
    private string $writeConnection;

    /**
     * Oxuma üçün connection adı (replica).
     */
    private string $readConnection;

    public function __construct(
        string $writeConnection = 'mysql',
        string $readConnection = 'mysql_read',
    ) {
        $this->writeConnection = $writeConnection;
        $this->readConnection = $readConnection;
    }

    /**
     * YAZMA CONNECTİON-U AL (Master)
     * ================================
     * INSERT, UPDATE, DELETE əməliyyatları həmişə master-ə gedir.
     * Yazma olduqda flag-ı true edir ki, qalan oxumalar da master-dən olsun.
     */
    public function forWrite(): \Illuminate\Database\Connection
    {
        $this->hasWrittenInCurrentRequest = true;

        Log::debug('DB connection: MASTER (yazma)', [
            'connection' => $this->writeConnection,
        ]);

        return DB::connection($this->writeConnection);
    }

    /**
     * OXUMA CONNECTİON-U AL (Replica və ya Master)
     * ================================================
     * Əgər bu request-də yazma olubsa → master-dən oxu (read-your-own-writes).
     * Əgər yazma olmayıbsa → replica-dan oxu (yükü paylaş).
     *
     * NƏYƏ BU QƏDƏR SADƏ?
     * Real dünyada daha mürəkkəb ola bilər:
     * - Bir neçə replica varsa → round-robin və ya least-connections ilə seçmək.
     * - Replica lag çox böyükdürsə → health check ilə sağlam olanı seçmək.
     * - Kritik oxuma sorğularında → həmişə master-dən oxumaq.
     * Amma əsas prinsip eynidir: yazma master, oxuma replica.
     */
    public function forRead(): \Illuminate\Database\Connection
    {
        if ($this->hasWrittenInCurrentRequest) {
            Log::debug('DB connection: MASTER (read-after-write)', [
                'connection' => $this->writeConnection,
                'reason' => 'Bu request-də yazma olub, replica-da köhnə data ola bilər',
            ]);

            return DB::connection($this->writeConnection);
        }

        Log::debug('DB connection: REPLICA (oxuma)', [
            'connection' => $this->readConnection,
        ]);

        return DB::connection($this->readConnection);
    }

    /**
     * Request bitdikdə flag-ı sıfırla.
     * Middleware-in terminate() metodunda çağırılmalıdır.
     */
    public function resetForNewRequest(): void
    {
        $this->hasWrittenInCurrentRequest = false;
    }

    /**
     * REPLİKA LAG-INI YOXLA
     * =======================
     * Replica master-dən neçə saniyə geridədir?
     * MySQL-də "SHOW SLAVE STATUS" sorğusu ilə öyrənilir.
     *
     * Əgər lag çox böyükdürsə (məs: 10+ saniyə), oxumanı da master-ə yönləndir.
     * Bu, data consistency-ni qoruyur amma performansı azaldır.
     *
     * Real layihədə: Prometheus/Grafana ilə lag monitoring qurulur.
     * Lag threshold-u aşanda alert göndərilir.
     */
    public function getReplicaLagSeconds(): ?float
    {
        try {
            $result = DB::connection($this->readConnection)
                ->select('SHOW SLAVE STATUS');

            if (empty($result)) {
                return null; // Replica konfigurasiya olunmayıb
            }

            $status = (array) $result[0];

            return (float) ($status['Seconds_Behind_Master'] ?? 0);
        } catch (\Throwable $e) {
            Log::warning('Replica lag yoxlana bilmədi', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Hazırda yazma olubmu?
     */
    public function hasWritten(): bool
    {
        return $this->hasWrittenInCurrentRequest;
    }
}
