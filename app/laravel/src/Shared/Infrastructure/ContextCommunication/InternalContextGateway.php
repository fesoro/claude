<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\ContextCommunication;

use Illuminate\Support\Facades\DB;

/**
 * InternalContextGateway - Monolit arxitekturada kontekstlər arası əlaqə.
 *
 * ================================================================
 * MONOLİT vs MİCROSERVİCE KOMMUNİKASİYA FƏRQİ
 * ================================================================
 *
 * Bu sinif MONOLIT üçün yazılıb:
 * ─────────────────────────────
 * - Bütün kontekstlər eyni prosesdə, eyni DB-dədir.
 * - Birbaşa SQL sorğusu ilə digər kontekstin cədvəlinə müraciət edirik.
 * - Çox sürətlidir (eyni prosesdə DB çağırışı, ~1-5ms).
 * - Tranzaksiya dəstəyi var (bir əməliyyatda bir neçə kontekstə yaza bilərik).
 *
 * MİCROSERVİCE-də bu necə FƏRQLI olardı?
 * ────────────────────────────────────────
 * Microservice arxitekturada hər kontekst ayrı servisdir:
 * - Ayrı server, ayrı DB, ayrı deployment.
 * - DB-yə birbaşa müraciət MÜMKÜN DEYİL.
 * - HTTP/gRPC ilə digər servisə sorğu göndərməli oluruq.
 *
 * Microservice versiyası belə olardı:
 *   public function getUserById(string $userId): ?array
 *   {
 *       // HTTP çağırışı — user servisinə
 *       $response = Http::get("http://user-service/api/users/{$userId}");
 *       return $response->successful() ? $response->json() : null;
 *   }
 *
 * Monolit versiyasının üstünlükləri:
 * - Sürət: Şəbəkə gecikməsi yoxdur (~1ms vs ~50-200ms HTTP).
 * - Sadəlik: HTTP xətaları, timeout-lar barədə düşünməyə ehtiyac yoxdur.
 * - Tranzaksiya: Eyni DB tranzaksiyasında bir neçə kontekstə yaza bilərik.
 *
 * Monolit versiyasının çatışmazlıqları:
 * - DB cədvəllərinə birbaşa müraciət — kontekst sərhədini "zəiflədir".
 * - Gələcəkdə microservice-ə keçmək çətinləşir (SQL-i HTTP-yə çevirməli).
 *
 * ƏSAS İDEYA: İnterfeys (ContextGateway) dəyişmir!
 * Yalnız implementasiyanı dəyişirik: InternalContextGateway -> HttpContextGateway.
 * Dependency Injection sayəsində qalan kod heç nə bilmir.
 *
 * Niyə DB::table() istifadə edirik, Model yox?
 * ─────────────────────────────────────────────
 * - Eloquent Model digər kontekstin Domain qatına aiddir.
 * - Biz yalnız "xam" məlumat istəyirik (array), Entity yox.
 * - DB::table() daha sadə və kontekst sərhədini pozmur.
 */
class InternalContextGateway implements ContextGateway
{
    /**
     * Users cədvəlindən istifadəçini birbaşa oxuyur.
     *
     * Qeyd: Monolitdə bu sadə DB sorğusudur.
     * Microservice-də bu HTTP GET /api/users/{id} olardı.
     *
     * @param string $userId İstifadəçi ID-si
     * @return array|null İstifadəçi məlumatları və ya null (tapılmadıqda)
     */
    public function getUserById(string $userId): ?array
    {
        /**
         * DB::table() — Laravel-in Query Builder-idir.
         * Eloquent Model istifadə etmirik çünki:
         * 1. User Model → User kontekstinə aiddir (biz Shared kontekstdəyik).
         * 2. Biz yalnız sadə array istəyirik, tam Entity yox.
         * 3. Kontekst sərhədlərini qoruyuruq.
         */
        $user = DB::table('users')
            ->where('id', $userId)
            ->first(['id', 'name', 'email']);

        /**
         * stdClass-ı array-ə çeviririk.
         * Niyə? Çünki interfeys array qaytarır — ümumi format.
         * Hər kontekst array-i öz DTO/Value Object-inə çevirə bilər.
         */
        return $user ? (array) $user : null;
    }

    /**
     * Products cədvəlindən məhsulu birbaşa oxuyur.
     *
     * Microservice-də bu belə olardı:
     *   Http::get("http://product-service/api/products/{$productId}")
     *
     * Sürət müqayisəsi:
     * - Monolit (bu kod): ~2ms (eyni prosesdə DB sorğusu)
     * - Microservice (HTTP): ~50-200ms (şəbəkə gecikməsi + serialization)
     *
     * @param string $productId Məhsul ID-si
     * @return array|null Məhsul məlumatları və ya null
     */
    public function getProductById(string $productId): ?array
    {
        $product = DB::table('products')
            ->where('id', $productId)
            ->first(['id', 'name', 'price', 'stock_quantity']);

        return $product ? (array) $product : null;
    }

    /**
     * Orders cədvəlindən sifarişi birbaşa oxuyur.
     *
     * Diqqət: Yalnız lazımi sütunları seçirik (SELECT *-dan qaçınırıq).
     * Niyə?
     * 1. Performans: Az məlumat = sürətli sorğu.
     * 2. Təhlükəsizlik: Digər kontekstə lazımsız məlumat vermirik.
     * 3. Kontekst sərhədi: Yalnız "ictimai" sahələri paylaşırıq.
     *
     * @param string $orderId Sifariş ID-si
     * @return array|null Sifariş məlumatları və ya null
     */
    public function getOrderById(string $orderId): ?array
    {
        $order = DB::table('orders')
            ->where('id', $orderId)
            ->first(['id', 'user_id', 'status', 'total_amount']);

        return $order ? (array) $order : null;
    }
}
