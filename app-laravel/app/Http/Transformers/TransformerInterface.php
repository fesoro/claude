<?php

declare(strict_types=1);

namespace App\Http\Transformers;

/**
 * TRANSFORMER INTERFACE (Versioned API Pattern)
 * ===============================================
 * API versiyasına görə fərqli response formatı qaytarmaq üçün interface.
 *
 * VERSİYALI API TRANSFORMER NƏDİR?
 * ══════════════════════════════════
 * Eyni endpoint fərqli versiyalarda fərqli JSON strukturu qaytarır.
 *
 * PROBLEM:
 * API v1 yayımlanıb, 1000 müştəri istifadə edir.
 * İndi response formatını dəyişmək istəyirik — amma köhnə müştərilər sınar!
 *
 * HƏLLİ:
 * v1 müştəriləri köhnə formatı, v2 müştəriləri yeni formatı alır.
 * Eyni endpoint, eyni data, fərqli format.
 *
 * NÜMUNƏ:
 * GET /api/v1/products/123 →
 * {
 *   "id": "123",
 *   "name": "Laptop",
 *   "price": 999.99,        ← v1: düz rəqəm
 *   "currency": "USD"        ← v1: ayrı sahə
 * }
 *
 * GET /api/v2/products/123 →
 * {
 *   "id": "123",
 *   "name": "Laptop",
 *   "price": {               ← v2: obyekt
 *     "amount": 999.99,
 *     "currency": "USD",
 *     "formatted": "$999.99"
 *   },
 *   "stock": {               ← v2: əlavə məlumat
 *     "quantity": 50,
 *     "available": true
 *   }
 * }
 *
 * TRANSFORMER vs RESOURCE FƏRQI:
 * - Resource: Bir format, bütün versiyalar üçün (sadə layihələr)
 * - Transformer: Hər versiya üçün ayrı format (böyük API-lər)
 *
 * REALİTƏDƏ KİM İSTİFADƏ EDİR?
 * - Stripe API: v1, v2... versiyaları var, köhnə versiyalar illərlə dəstəklənir
 * - GitHub API: v3 (REST) və v4 (GraphQL) paralel işləyir
 * - Twilio: versiya tarixində köhnə format saxlanılır
 */
interface TransformerInterface
{
    /**
     * Datanı API versiyasına uyğun formata çevir.
     *
     * @param mixed $data Çevriləcək data (Model, DTO, array)
     * @return array Formatlanmış cavab
     */
    public function transform(mixed $data): array;

    /**
     * Kolleksiyanı çevir.
     */
    public function transformCollection(iterable $items): array;
}
