<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\Shared\Infrastructure\Search\SearchService;

/**
 * GLOBAL SEARCH CONTROLLER
 * ========================
 * Bütün entity-lərdə (products, orders, users) eyni anda axtarış.
 *
 * GET /api/search?q=laptop              → Hər yerdə "laptop" axtar
 * GET /api/search?q=laptop&type=product → Yalnız məhsullarda axtar
 * GET /api/search?q=laptop&limit=10     → Hər entity-dən max 10 nəticə
 *
 * AUTOCOMPLETE İSTİFADƏSİ:
 * Frontend-də axtarış sahəsində istifadəçi yazarkən bu endpoint çağırılır.
 * Debounce (300ms) tətbiq olunmalıdır ki, hər düyməyə basışda sorğu getməsin.
 */
class SearchController extends Controller
{
    public function __construct(
        private SearchService $searchService,
    ) {}

    public function search(Request $request): JsonResponse
    {
        $query = $request->query('q', '');
        $type = $request->query('type');       // product, order, user, null=all
        $limit = (int) $request->query('limit', 5);

        if (strlen($query) < 2) {
            return ApiResponse::error('Axtarış ən azı 2 simvol olmalıdır', code: 422);
        }

        if ($type) {
            $results = match ($type) {
                'product' => ['products' => $this->searchService->searchProducts($query, $limit)],
                'order' => ['orders' => $this->searchService->searchOrders($query, $limit)],
                'user' => ['users' => $this->searchService->searchUsers($query, $limit)],
                default => [],
            };
        } else {
            $results = $this->searchService->searchAll($query, $limit);
        }

        return ApiResponse::success(
            data: $results,
            message: "Axtarış nəticələri: \"{$query}\""
        );
    }
}
