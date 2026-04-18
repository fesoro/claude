<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * STANDARTLAŞDİRILMIŞ API CAVAB SİNFİ
 * ======================================
 * Bu sinif JsonResource DEYİL — sadə helper sinifdir.
 * Bütün API endpoint-lərindən eyni formatda cavab qaytarmaq üçün istifadə olunur.
 *
 * ═══════════════════════════════════════════════════════════════════
 * NƏYƏ GÖRƏ STANDARTLAŞDİRILMIŞ API CAVAB VACIBDIR?
 * ═══════════════════════════════════════════════════════════════════
 *
 * 1. KONSİSTENTLİK (Ardıcıllıq):
 *    Frontend developer bilir ki, HƏR cavabda bu strukturu gözləsin:
 *    {
 *      "success": true/false,
 *      "message": "...",
 *      "data": { ... }
 *    }
 *    Əgər hər endpoint fərqli format qaytarırsa, frontend kod qarışır.
 *
 * 2. XƏTA İDARƏETMƏSİ:
 *    Uğurlu və uğursuz cavablar eyni strukturda olur.
 *    Frontend sadəcə "success" sahəsinə baxır:
 *    - success: true → datanı göstər
 *    - success: false → xəta mesajını göstər
 *
 * 3. SƏNƏDLƏŞDIRMƏ:
 *    Swagger/OpenAPI sənədlərində bir "wrapper" schema kifayətdir.
 *    Hər endpoint üçün ayrı-ayrı response schema yazmaq lazım deyil.
 *
 * 4. VERSİYALAMA:
 *    Gələcəkdə cavab formatını dəyişmək istəsək, yalnız bu sinfi dəyişirik.
 *    Bütün endpoint-lər avtomatik yeni formatda cavab qaytarır.
 *
 * ═══════════════════════════════════════════════════════════════════
 * İSTİFADƏ NÜMUNƏLƏRİ:
 * ═══════════════════════════════════════════════════════════════════
 *
 * Uğurlu cavab:
 *   ApiResponse::success(new UserResource($user), 'İstifadəçi tapıldı');
 *   // { "success": true, "message": "...", "data": { id: 1, name: "..." } }
 *
 * Xəta cavabı:
 *   ApiResponse::error('Validasiya xətası', ['email' => 'Email düzgün deyil'], 422);
 *   // { "success": false, "message": "...", "errors": { ... } }
 *
 * Paginasiyalı cavab:
 *   ApiResponse::paginated(new ProductCollection($products));
 *   // Resource-un öz pagination strukturunu qaytarır
 */
class ApiResponse
{
    /**
     * UĞURLU CAVAB
     *
     * @param mixed $data — Qaytarılan data (Resource, array, DTO və s.)
     * @param string $message — İstifadəçiyə mesaj
     * @param int $code — HTTP status kodu (default: 200 OK)
     *
     * Nümunə nəticə:
     * {
     *   "success": true,
     *   "message": "Əməliyyat uğurla tamamlandı",
     *   "data": { "id": 1, "name": "Orxan" }
     * }
     */
    public static function success(
        mixed $data = null,
        string $message = 'Əməliyyat uğurla tamamlandı',
        int $code = 200
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    /**
     * XƏTA CAVABI
     *
     * @param string $message — Xəta mesajı
     * @param array|null $errors — Detallı xəta siyahısı (validasiya xətaları kimi)
     * @param int $code — HTTP status kodu (default: 400 Bad Request)
     *
     * Nümunə nəticə:
     * {
     *   "success": false,
     *   "message": "Validasiya xətası",
     *   "errors": {
     *     "email": ["Email sahəsi tələb olunur"],
     *     "password": ["Şifrə ən azı 8 simvol olmalıdır"]
     *   }
     * }
     */
    public static function error(
        string $message = 'Xəta baş verdi',
        ?array $errors = null,
        int $code = 400
    ): JsonResponse {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        /**
         * errors sahəsi yalnız varsa əlavə olunur.
         * Məsələn, 404 Not Found xətasında adətən errors lazım deyil,
         * amma 422 Validation xətasında sahə-sahə xətalar göstərilir.
         */
        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }

    /**
     * PAGİNASİYALI CAVAB
     *
     * ResourceCollection ötürüldükdə, onun öz pagination strukturunu
     * standart wrapper ilə birləşdirir.
     *
     * @param ResourceCollection $resource — Paginasiyalı Resource Collection
     *
     * Nümunə nəticə:
     * {
     *   "success": true,
     *   "message": "...",
     *   "data": [ ... ],
     *   "meta": { "current_page": 1, "total": 75, ... },
     *   "links": { "first": "...", "next": "...", ... }
     * }
     */
    public static function paginated(
        ResourceCollection $resource,
        string $message = 'Siyahı uğurla alındı'
    ): JsonResponse {
        /**
         * Resource-un response()-unu JSON-a çevirib, üstünə wrapper əlavə edirik.
         * resolve() metodu Resource-un toArray() nəticəsini qaytarır.
         * additional() ilə əlavə sahələr əlavə edə bilərik.
         */
        $resourceResponse = $resource->response()->getData(true);

        return response()->json(array_merge(
            [
                'success' => true,
                'message' => $message,
            ],
            $resourceResponse
        ));
    }
}
