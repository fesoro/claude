<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Api;

/**
 * BACKEND FOR FRONTEND (BFF) — Klient Tipinə Görə API Gateway
 * ==============================================================
 *
 * PROBLEMİ ANLAYAQ:
 * =================
 * Fərqli klient-lərin fərqli ehtiyacları var:
 *
 * MOBİL: Kiçik ekran, yavaş internet → minimal data, kiçik response.
 *   Sifariş siyahısı: { id, status, total } — 3 sahə kifayətdir.
 *
 * WEB: Böyük ekran, sürətli internet → detallı data, geniş response.
 *   Sifariş siyahısı: { id, status, total, items[], dates, address } — tam detallar.
 *
 * ADMİN: İdarəetmə paneli → maksimal data, audit məlumatları.
 *   Sifariş siyahısı: { id, status, total, items[], user_details, audit_log, payment_info }
 *
 * ƏGƏr TƏK API VARSA:
 * - Mobil üçün lazım olmayan data göndərilir → trafik israfı, yavaş yüklənmə.
 * - Admin üçün lazım olan data mobil API-da yoxdur → əlavə sorğu lazımdır.
 * - Hər client-in tələbi dəyişəndə ümumi API dəyişməlidir → hər kəsə təsir edir.
 *
 * HƏLLİ — BFF (Backend For Frontend):
 * ====================================
 * Hər klient tipi üçün ayrı API layer yaradırıq:
 *
 *   ┌──────────┐     ┌──────────────┐     ┌─────────────────────┐
 *   │ Mobil App│────▶│ Mobile BFF   │────▶│                     │
 *   └──────────┘     └──────────────┘     │   Domain Services   │
 *                                         │   (eyni biznes       │
 *   ┌──────────┐     ┌──────────────┐     │    məntiqi)          │
 *   │ Web App  │────▶│ Web BFF      │────▶│                     │
 *   └──────────┘     └──────────────┘     │                     │
 *                                         │                     │
 *   ┌──────────┐     ┌──────────────┐     │                     │
 *   │ Admin    │────▶│ Admin BFF    │────▶│                     │
 *   └──────────┘     └──────────────┘     └─────────────────────┘
 *
 * Hər BFF öz klientinin ehtiyacına uyğun data format/filtr/aggregasiya edir.
 * Biznes məntiqi dəyişmir — yalnız presentation layer fərqlidir.
 *
 * BFF vs API GATEWAY:
 * ====================
 * API Gateway: Tək giriş nöqtəsi, routing, auth, rate limiting. İnfrastruktur.
 * BFF: Klient-spesifik data transformasiyası. Application layer.
 * BFF API Gateway-in ARXASINDA yerləşə bilər.
 *
 * BFF vs GraphQL:
 * ================
 * GraphQL: Klient özü hansı sahələri istədiyini müəyyən edir (query language).
 * BFF: Server klient tipinə görə əvvəlcədən müəyyən edilmiş format qaytarır.
 *
 * GraphQL daha çevikdir (klient nəyi istəsə alır), amma:
 * - Öyrənmə əyrisi daha dikdir.
 * - N+1 problemi ilə mübarizə lazımdır.
 * - Security daha mürəkkəbdir (klient istədiyi sorğunu göndərə bilər).
 *
 * BFF daha sadədir və REST API-ya uyğundur.
 *
 * NETFLIX NÜMUNƏSİ:
 * ==================
 * Netflix BFF pattern-in əsas populyarlaşdırıcısıdır:
 * - TV tətbiqi üçün ayrı BFF (böyük ekran, uzaqdan idarə).
 * - Mobil tətbiq üçün ayrı BFF (kiçik ekran, touch).
 * - Web üçün ayrı BFF (browser, keyboard/mouse).
 * Hər biri fərqli data formatı, fərqli pagination, fərqli şəkil ölçüsü istifadə edir.
 */
class BackendForFrontend
{
    /**
     * Dəstəklənən klient tipləri.
     */
    public const CLIENT_MOBILE = 'mobile';
    public const CLIENT_WEB = 'web';
    public const CLIENT_ADMIN = 'admin';

    /**
     * SİFARİŞ SİYAHISINI KLİENT TİPİNƏ GÖRƏ FORMAT ET
     * ====================================================
     * Eyni domain datasını fərqli formatlarda qaytarır.
     *
     * @param array $orders Raw sifariş datası (domain layer-dən)
     * @param string $clientType Klient tipi: mobile, web, admin
     *
     * @return array Formatlanmış data
     */
    public function formatOrderList(array $orders, string $clientType): array
    {
        return match ($clientType) {
            self::CLIENT_MOBILE => $this->formatForMobile($orders),
            self::CLIENT_WEB    => $this->formatForWeb($orders),
            self::CLIENT_ADMIN  => $this->formatForAdmin($orders),
            default             => $this->formatForWeb($orders),
        };
    }

    /**
     * MOBİL FORMAT — Minimal data, sürətli yüklənmə
     *
     * Yalnız siyahıda göstəriləcək sahələr:
     * - id, status, total, item_count, created_at
     * - Şəkil URL-ləri kiçik variant (thumbnail)
     * - Pagination: 10 item per page (mobil ekranda az yer var)
     */
    private function formatForMobile(array $orders): array
    {
        return array_map(fn (array $order) => [
            'id'         => $order['id'],
            'status'     => $order['status'],
            'total'      => $order['total_amount'],
            'item_count' => $order['item_count'] ?? 0,
            'created_at' => $this->formatDateShort($order['created_at'] ?? ''),
        ], $orders);
    }

    /**
     * WEB FORMAT — Orta detallar, standart baxış
     *
     * Siyahıda + bəzi əlavə detallar:
     * - id, status, total, items preview, dates, currency
     * - Şəkil URL-ləri orta variant (medium)
     * - Pagination: 20 item per page
     */
    private function formatForWeb(array $orders): array
    {
        return array_map(fn (array $order) => [
            'id'           => $order['id'],
            'status'       => $order['status'],
            'status_label' => $this->translateStatus($order['status']),
            'total_amount' => $order['total_amount'],
            'currency'     => $order['currency'] ?? 'AZN',
            'item_count'   => $order['item_count'] ?? 0,
            'created_at'   => $order['created_at'] ?? '',
            'updated_at'   => $order['updated_at'] ?? '',
        ], $orders);
    }

    /**
     * ADMİN FORMAT — Maksimal data, idarəetmə üçün
     *
     * Bütün sahələr + əlavə admin məlumatları:
     * - Bütün web sahələri + user details, audit log, payment info
     * - Internal ID-lər, timestamps, metadata
     * - Pagination: 50 item per page (admin geniş cədvəl istifadə edir)
     */
    private function formatForAdmin(array $orders): array
    {
        return array_map(fn (array $order) => [
            'id'            => $order['id'],
            'user_id'       => $order['user_id'] ?? null,
            'status'        => $order['status'],
            'status_label'  => $this->translateStatus($order['status']),
            'total_amount'  => $order['total_amount'],
            'currency'      => $order['currency'] ?? 'AZN',
            'item_count'    => $order['item_count'] ?? 0,
            'created_at'    => $order['created_at'] ?? '',
            'updated_at'    => $order['updated_at'] ?? '',
            'payment_status' => $order['payment_status'] ?? 'unknown',
            'internal_notes' => $order['internal_notes'] ?? '',
        ], $orders);
    }

    /**
     * Klient tipinə görə pagination limiti.
     */
    public function paginationLimit(string $clientType): int
    {
        return match ($clientType) {
            self::CLIENT_MOBILE => 10,
            self::CLIENT_WEB    => 20,
            self::CLIENT_ADMIN  => 50,
            default             => 20,
        };
    }

    /**
     * HTTP request-dən klient tipini müəyyən et.
     * X-Client-Type header-i və ya User-Agent-dən oxunur.
     */
    public static function resolveClientType(\Illuminate\Http\Request $request): string
    {
        // Əvvəlcə explicit header-ə bax
        $clientType = $request->header('X-Client-Type');

        if ($clientType !== null && in_array($clientType, [self::CLIENT_MOBILE, self::CLIENT_WEB, self::CLIENT_ADMIN], true)) {
            return $clientType;
        }

        // User-Agent-dən müəyyən et (fallback)
        $userAgent = strtolower($request->userAgent() ?? '');

        if (str_contains($userAgent, 'mobile') || str_contains($userAgent, 'android') || str_contains($userAgent, 'iphone')) {
            return self::CLIENT_MOBILE;
        }

        return self::CLIENT_WEB;
    }

    private function formatDateShort(string $date): string
    {
        if (empty($date)) {
            return '';
        }

        try {
            return (new \DateTimeImmutable($date))->format('d.m.Y');
        } catch (\Throwable) {
            return $date;
        }
    }

    private function translateStatus(string $status): string
    {
        return match ($status) {
            'pending'   => 'Gözləyir',
            'confirmed' => 'Təsdiqləndi',
            'paid'      => 'Ödənildi',
            'shipped'   => 'Göndərildi',
            'delivered' => 'Çatdırıldı',
            'cancelled' => 'Ləğv edildi',
            default     => $status,
        };
    }
}
