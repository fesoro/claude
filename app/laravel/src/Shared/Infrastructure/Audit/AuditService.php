<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Audit;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * AuditService - Sistemdə baş verən bütün dəyişiklikləri qeyd edən xidmət.
 *
 * ================================================================
 * AUDIT TRAIL (Audit İzi) — ƏTRAFLI İZAH
 * ================================================================
 *
 * Audit Trail — sistemdə "kim, nə vaxt, nə etdi, nəyi dəyişdi?" suallarına
 * cavab verən dəyişiklik tarixçəsidir. Bu, müasir proqram təminatının
 * ən vacib komponentlərindən biridir.
 *
 * ─────────────────────────────────────────────
 * 1. UYĞUNLUQ (Compliance / Qanuni tələblər)
 * ─────────────────────────────────────────────
 * Bir çox qanun və standart audit trail tələb edir:
 *
 * - GDPR (Avropa): İstifadəçi məlumatlarının kim tərəfindən, nə vaxt
 *   dəyişdirildiyini sübut etməlisiniz. Məsələn, istifadəçi "məlumatlarımı
 *   silin" dedikdə, silindiyini audit log-da göstərməlisiniz.
 *
 * - PCI-DSS (Ödəniş kartları): Kart məlumatlarına kimin baxdığını,
 *   dəyişdirdiyini izləməlisiniz. Audit log olmadan PCI sertifikatı ala bilməzsiniz.
 *
 * - SOX (ABŞ maliyyə): Maliyyə məlumatlarının dəyişiklik tarixçəsi
 *   olmalıdır. CEO/CFO bu məlumatların düzgünlüyünə şəxsən cavabdehdir.
 *
 * ─────────────────────────────────────────────
 * 2. XƏTAAXTARMA (Debugging)
 * ─────────────────────────────────────────────
 * Produksiyada problem yarandıqda audit log ilk baxdığımız yerdir:
 *
 * Ssenari: "Məhsulun qiyməti 100 AZN idi, indi 1 AZN göstərir!"
 * Audit log-a baxırıq:
 *   - 2024-01-15 14:23:00 | admin_5 | updated | Product | abc-123
 *   - old: {"price": 100} | new: {"price": 1}
 * Cavab: admin_5 istifadəçisi səhvən qiyməti 1 AZN edib.
 *
 * Audit log olmasa, bu problemi tapmaq saatlar/günlər çəkər.
 *
 * ─────────────────────────────────────────────
 * 3. TƏHLÜKƏSİZLİK FORENZİKASI (Security Forensics)
 * ─────────────────────────────────────────────
 * Haker hücumu baş verdikdə audit log "cinayət yeri sübutu" kimidir:
 *
 * Ssenari: Haker admin hesabına daxil olub.
 * Audit log-dan görürük:
 *   - 03:00-da Rusiyadan login (IP: 185.x.x.x)
 *   - 100+ məhsulun qiyməti 0-a endirildi
 *   - 50 sifarişin statusu "delivered" edildi
 *   - Bütün əməliyyatlar 15 dəqiqə ərzində
 *
 * Bu məlumat olmadan:
 *   - Hakerin nə etdiyini bilməzdik
 *   - Hansı məlumatları geri qaytarmaq lazım olduğunu bilməzdik
 *   - Hüquq-mühafizə orqanlarına sübut təqdim edə bilməzdik
 *
 * ─────────────────────────────────────────────
 * 4. GERİQAYTARMA (Rollback / Undo)
 * ─────────────────────────────────────────────
 * old_values sahəsi sayəsində istənilən dəyişikliyi geri qaytara bilərik.
 * Bu, "Ctrl+Z" kimi işləyir, amma verilənlər bazası səviyyəsində.
 */
class AuditService
{
    /**
     * Audit log yazısı yaradır.
     *
     * Bu metod hər dəyişiklikdə çağırılır və audit_logs cədvəlinə yazır.
     * Əməliyyat uğursuz olsa belə, istisna atılmır — audit log-un
     * əsas əməliyyatı dayandırmasına icazə vermirik.
     *
     * @param string|null $userId     Kim etdi? (null = sistem əməliyyatı, məs: cron, queue)
     * @param string      $action     Nə edildi? (created, updated, deleted, exported, login...)
     * @param string      $entityType Hansı entity? (Product, Order, User...)
     * @param string      $entityId   Hansı konkret entity? (UUID)
     * @param array|null  $oldValues  Əvvəlki dəyərlər (created-da null olur)
     * @param array|null  $newValues  Yeni dəyərlər (deleted-da null olur)
     * @param string|null $ipAddress  İstifadəçinin IP ünvanı
     * @param string|null $userAgent  İstifadəçinin brauzeri/cihazı
     */
    public function log(
        ?string $userId,
        string $action,
        string $entityType,
        string $entityId,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): void {
        try {
            /**
             * Birbaşa DB::table istifadə edirik, Eloquent model deyil.
             *
             * Səbəb: Audit log YAZI əməliyyatıdır, sadəcə insert edirik.
             * Eloquent model-in "event" mexanizmi sonsuz dövrə səbəb ola bilər:
             *   Model saved → Audit log → Model saved → Audit log → ...
             *
             * DB::table ilə bu riskdən qaçırıq və performans da daha yaxşıdır.
             */
            DB::table('audit_logs')->insert([
                'user_id'         => $userId,
                'action'          => $action,
                'auditable_type'  => $entityType,
                'auditable_id'    => $entityId,
                'old_values'      => $oldValues ? json_encode($oldValues, JSON_UNESCAPED_UNICODE) : null,
                'new_values'      => $newValues ? json_encode($newValues, JSON_UNESCAPED_UNICODE) : null,
                'ip_address'      => $ipAddress,
                'user_agent'      => $userAgent,
                'created_at'      => now(),
            ]);
        } catch (\Throwable $e) {
            /**
             * Audit log yazılması uğursuz olsa, əsas əməliyyatı dayandırmırıq.
             *
             * Niyə? Çünki audit log — köməkçi funksiyadır. İstifadəçinin sifarişi
             * audit log yazıla bilmədiyi üçün uğursuz olmamalıdır.
             *
             * Amma xətanı mütləq log-a yazırıq ki, sonra araşdıraq.
             * Produksiyada bu xəta monitoring sisteminə (Sentry, Datadog) düşməlidir.
             */
            Log::error('Audit log yazılması uğursuz oldu', [
                'error'       => $e->getMessage(),
                'action'      => $action,
                'entity_type' => $entityType,
                'entity_id'   => $entityId,
            ]);
        }
    }
}
