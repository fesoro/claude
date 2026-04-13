<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UploadProductImageRequest;
use App\Http\Resources\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\Product\Application\Services\ProductImageService;
use Src\Product\Infrastructure\Models\ProductImageModel;
use Src\Product\Infrastructure\Models\ProductModel;

/**
 * PRODUCT IMAGE CONTROLLER
 * ========================
 * Məhsul şəkillərinin CRUD əməliyyatları.
 *
 * ENDPOINT-LƏR:
 * POST   /api/products/{id}/images         → Şəkil yüklə
 * GET    /api/products/{id}/images         → Şəkil siyahısı
 * DELETE /api/products/{id}/images/{imgId} → Şəkil sil
 * PATCH  /api/products/{id}/images/{imgId}/primary → Əsas şəkil təyin et
 *
 * MULTIPART/FORM-DATA:
 * ═══════════════════
 * Şəkil yükləmə JSON deyil, multipart/form-data formatında göndərilir.
 * Bu format faylları binary olaraq göndərməyə imkan verir.
 *
 * Frontend-dən nümunə (JavaScript):
 * const formData = new FormData();
 * formData.append('image', fileInput.files[0]);
 * formData.append('is_primary', 'true');
 * fetch('/api/products/123/images', { method: 'POST', body: formData });
 *
 * cURL nümunəsi:
 * curl -X POST /api/products/123/images \
 *   -H "Authorization: Bearer token" \
 *   -F "image=@/path/to/photo.jpg" \
 *   -F "is_primary=true"
 */
class ProductImageController extends Controller
{
    public function __construct(
        private ProductImageService $imageService,
    ) {}

    /**
     * POST /api/products/{id}/images
     * Şəkil yüklə.
     */
    public function store(UploadProductImageRequest $request, string $productId): JsonResponse
    {
        // Məhsulun mövcudluğunu yoxla
        ProductModel::findOrFail($productId);

        $image = $this->imageService->upload(
            productId: $productId,
            file: $request->file('image'),
            isPrimary: (bool) $request->input('is_primary', false),
        );

        return ApiResponse::success(
            data: [
                'image_id' => $image->id,
                'url' => $image->url,
                'original_name' => $image->original_name,
                'size' => $image->human_size,
                'is_primary' => $image->is_primary,
            ],
            message: 'Şəkil uğurla yükləndi',
            code: 201,
        );
    }

    /**
     * GET /api/products/{id}/images
     * Məhsulun şəkil siyahısı.
     */
    public function index(string $productId): JsonResponse
    {
        ProductModel::findOrFail($productId);

        $images = ProductImageModel::where('product_id', $productId)
            ->orderBy('sort_order')
            ->get()
            ->map(fn($img) => [
                'id' => $img->id,
                'url' => $img->url,
                'original_name' => $img->original_name,
                'size' => $img->human_size,
                'mime_type' => $img->mime_type,
                'is_primary' => $img->is_primary,
                'sort_order' => $img->sort_order,
            ]);

        return ApiResponse::success(data: $images);
    }

    /**
     * DELETE /api/products/{id}/images/{imageId}
     * Şəkil sil.
     */
    public function destroy(string $productId, string $imageId): JsonResponse
    {
        $image = ProductImageModel::where('product_id', $productId)
            ->where('id', $imageId)
            ->firstOrFail();

        $this->imageService->delete($image);

        return ApiResponse::success(message: 'Şəkil silindi');
    }

    /**
     * PATCH /api/products/{id}/images/{imageId}/primary
     * Əsas şəkli dəyiş.
     */
    public function setPrimary(string $productId, string $imageId): JsonResponse
    {
        ProductImageModel::where('product_id', $productId)
            ->where('id', $imageId)
            ->firstOrFail();

        $this->imageService->setPrimary($productId, $imageId);

        return ApiResponse::success(message: 'Əsas şəkil dəyişdirildi');
    }
}
