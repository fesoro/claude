<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\CreateProductRequest;
use App\Http\Requests\UpdateStockRequest;
use App\Http\Resources\ApiResponse;
use App\Http\Resources\ProductCollection;
use App\Http\Resources\ProductResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\Shared\Application\Bus\CommandBus;
use Src\Shared\Application\Bus\QueryBus;
use Src\Product\Application\Commands\CreateProduct\CreateProductCommand;
use Src\Product\Application\DTOs\CreateProductDTO;
use Src\Product\Application\Commands\UpdateStock\UpdateStockCommand;
use Src\Product\Application\Queries\GetProduct\GetProductQuery;
use Src\Product\Application\Queries\ListProducts\ListProductsQuery;
use Src\Product\Infrastructure\Models\ProductModel;

/**
 * PRODUCT CONTROLLER
 * ==================
 * Məhsul əməliyyatları üçün HTTP endpoint-ləri.
 *
 * DECORATOR PATTERN burada gizli işləyir:
 * Controller → ProductRepositoryInterface istifadə edir
 * Amma ServiceProvider-da CachedProductRepository bind olunub.
 * CachedProductRepository → EloquentProductRepository-ni wrap edir.
 * Controller bundan xəbərsizdir — bu Decorator Pattern-in gücüdür!
 */
class ProductController extends Controller
{
    public function __construct(
        private CommandBus $commandBus,
        private QueryBus $queryBus,
    ) {}

    /**
     * GET /api/products
     * Bütün məhsulların siyahısı.
     * Cache-dən gəlir (CachedProductRepository sayəsində).
     */
    public function index(Request $request): JsonResponse
    {
        /**
         * SEARCH / FILTERING
         * Request-dən filter parametrlərini alıb ListProductsQuery-yə ötürürük.
         *
         * NÜMUNƏ SORĞULAR:
         * GET /api/products?search=laptop
         * GET /api/products?min_price=100&max_price=500&currency=USD
         * GET /api/products?in_stock=true&sort_by=price&sort_dir=asc
         * GET /api/products?search=phone&min_price=200&sort_by=name&page=2
         */
        $query = new ListProductsQuery(
            page: (int) $request->query('page', 1),
            perPage: (int) $request->query('per_page', 15),
            search: $request->query('search'),
            minPrice: $request->query('min_price') !== null ? (float) $request->query('min_price') : null,
            maxPrice: $request->query('max_price') !== null ? (float) $request->query('max_price') : null,
            currency: $request->query('currency'),
            inStock: $request->query('in_stock') !== null ? (bool) $request->query('in_stock') : null,
            sortBy: $request->query('sort_by', 'created_at'),
            sortDir: $request->query('sort_dir', 'desc'),
        );
        $products = $this->queryBus->ask($query);

        /**
         * ProductCollection — məhsul siyahısını formatlamaq üçün ResourceCollection.
         * Əgər $products paginate() nəticəsidirsə, pagination metadata avtomatik əlavə olunur.
         * ApiResponse::paginated() standart wrapper əlavə edir.
         */
        return ApiResponse::paginated(new ProductCollection($products));
    }

    /**
     * GET /api/products/{id}
     * Tək məhsul detalları.
     */
    public function show(string $id): JsonResponse
    {
        $query = new GetProductQuery(productId: $id);
        $product = $this->queryBus->ask($query);

        if ($product === null) {
            return ApiResponse::error('Məhsul tapılmadı', code: 404);
        }

        /**
         * ProductResource — tək məhsulu API formatına çevirir.
         * Qiymət {amount, currency} obyekti, is_in_stock hesablanmış sahə olaraq qaytarılır.
         */
        return ApiResponse::success(
            data: new ProductResource($product),
            message: 'Məhsul tapıldı'
        );
    }

    /**
     * POST /api/products
     * Yeni məhsul yarat.
     *
     * Request body: { "name": "...", "price": 29.99, "currency": "USD", "stock": 100 }
     *
     * SPECIFICATION PATTERN burada işləyir:
     * Handler daxilində ProductIsInStockSpec və ProductPriceIsValidSpec yoxlanılır.
     */
    public function store(CreateProductRequest $request): JsonResponse
    {
        /**
         * POLICY — Məhsul yaratma icazəsi.
         *
         * MODEL-SİZ authorize() ÇAĞIRIŞI:
         * Məhsul hələ yaradılmayıb, ona görə model instance yox, CLASS adı ötürülür.
         * Laravel CLASS adından Policy-ni tapır və create($user) metodunu çağırır.
         *
         * Fərq:
         *   $this->authorize('view', $product)        → model instance (mövcud resurs)
         *   $this->authorize('create', ProductModel::class) → class adı (yeni resurs)
         */
        $this->authorize('create', ProductModel::class);

        $command = new CreateProductCommand(
            dto: new CreateProductDTO(
                name: $request->input('name'),
                priceAmount: (int) round($request->input('price') * 100), // AZN → qəpik
                currency: $request->input('currency', 'AZN'),
                stock: (int) $request->input('stock'),
            ),
        );

        $productId = $this->commandBus->dispatch($command);

        return ApiResponse::success(
            data: ['product_id' => $productId],
            message: 'Məhsul uğurla yaradıldı',
            code: 201
        );
    }

    /**
     * PATCH /api/products/{id}/stock
     * Stoku yenilə.
     *
     * Request body: { "quantity": 10, "operation": "increase" | "decrease" }
     */
    public function updateStock(string $id, UpdateStockRequest $request): JsonResponse
    {
        /**
         * POLICY — Stok yeniləmə icazəsi.
         * Yalnız admin (is_active) istifadəçilər stoku dəyişə bilər.
         * CLASS adı ötürülür çünki updateStock() model-siz metoddur.
         */
        $this->authorize('updateStock', ProductModel::class);

        $command = new UpdateStockCommand(
            productId: $id,
            amount: (int) $request->input('quantity'),
            type: $request->input('operation', 'decrease'),
        );

        $this->commandBus->dispatch($command);

        return ApiResponse::success(message: 'Stok yeniləndi');
    }
}
