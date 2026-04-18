<?php

declare(strict_types=1);

namespace Src\Product\Application\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Src\Product\Infrastructure\Models\ProductImageModel;

/**
 * PRODUCT IMAGE SERVICE
 * =====================
 * Məhsul şəkillərinin yüklənməsi, silinməsi və idarə edilməsi.
 *
 * FAYL YÜKLƏMƏ AXINI:
 * ════════════════════
 * 1. İstifadəçi şəkil seçir (frontend → multipart/form-data)
 * 2. Laravel UploadedFile obyekti yaradır (temp faylda)
 * 3. Validation: tip, ölçü, ölçülər yoxlanılır
 * 4. Unikal ad yaradılır (UUID + extension)
 * 5. Storage disk-ə yazılır
 * 6. DB-yə metadata qeydiyyat olunur (yol, tip, ölçü)
 * 7. Temp fayl silinir (avtomatik)
 *
 * FAYL ADI STRATEGİYASI:
 * Orijinal ad istifadə EDİLMİR! Çünki:
 * - Eyni adlı fayllar üst-üstə yazıla bilər
 * - Fayl adında xüsusi simvollar ola bilər (XSS riski)
 * - Yol tapmaq çətinləşir
 *
 * Əvəzinə: UUID + orijinal extension istifadə olunur.
 * "my photo.jpg" → "a1b2c3d4-e5f6-7890-abcd-ef1234567890.jpg"
 *
 * QOVLUQ STRUKTURU:
 * products/{product_id}/
 *   ├── a1b2c3d4.jpg (əsas şəkil)
 *   ├── b2c3d4e5.jpg
 *   └── c3d4e5f6.png
 */
class ProductImageService
{
    private string $disk = 'public';

    /**
     * Şəkil yüklə.
     *
     * @param string $productId Məhsul ID-si
     * @param UploadedFile $file Yüklənən fayl
     * @param bool $isPrimary Əsas şəkildirmi
     * @return ProductImageModel Yaradılmış model
     *
     * UploadedFile NƏDİR?
     * Laravel HTTP request-dən gələn faylı bu obyektə çevirir.
     * Metodları:
     *   $file->getClientOriginalName() → "my-photo.jpg"
     *   $file->getClientMimeType() → "image/jpeg"
     *   $file->getSize() → 1536000 (byte)
     *   $file->extension() → "jpg"
     *   $file->store('path', 'disk') → disk-ə yaz, yolu qaytar
     *   $file->storeAs('path', 'name', 'disk') → xüsusi adla yaz
     */
    public function upload(string $productId, UploadedFile $file, bool $isPrimary = false): ProductImageModel
    {
        // Unikal fayl adı yarat
        $fileName = Str::uuid() . '.' . $file->getClientOriginalExtension();

        // Qovluq yolu: products/{product_id}/
        $directory = "products/{$productId}";

        /**
         * storeAs() — faylı disk-ə xüsusi adla yazır.
         *
         * storeAs($directory, $fileName, $disk) parametrləri:
         * - $directory: "products/abc123" → disk-dəki qovluq
         * - $fileName: "uuid.jpg" → fayl adı
         * - $disk: "public" → hansı disk (config/filesystems.php)
         *
         * Qaytarır: tam nisbi yol → "products/abc123/uuid.jpg"
         */
        $path = $file->storeAs($directory, $fileName, $this->disk);

        // Əgər primary təyin olunursa, digər primary-ləri söndür
        if ($isPrimary) {
            ProductImageModel::where('product_id', $productId)
                ->where('is_primary', true)
                ->update(['is_primary' => false]);
        }

        // Sıralama: mövcud şəkillərin sonuna əlavə et
        $maxSort = ProductImageModel::where('product_id', $productId)
            ->max('sort_order') ?? -1;

        return ProductImageModel::create([
            'product_id' => $productId,
            'path' => $path,
            'disk' => $this->disk,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'sort_order' => $maxSort + 1,
            'is_primary' => $isPrimary,
        ]);
    }

    /**
     * Şəkli sil — həm disk-dən, həm DB-dən.
     *
     * SİLMƏ SIRASI:
     * 1. Əvvəlcə disk-dən sil (fayl fiziki silinir)
     * 2. Sonra DB-dən sil (metadata silinir)
     *
     * NƏYƏ BU SIRADA?
     * DB silinib fayl qalsa → orphan fayl (disk dolur, amma DB-dən tapılmır)
     * Fayl silinib DB qalsa → 404 URL (amma DB-dən tapıb yenidən silmək olar)
     * İkinci hal daha təhlükəsizdir.
     */
    public function delete(ProductImageModel $image): void
    {
        // Disk-dən sil
        Storage::disk($image->disk)->delete($image->path);

        // DB-dən sil
        $image->delete();
    }

    /**
     * Məhsulun BÜTÜN şəkillərini sil.
     * Məhsul silinəndə çağırılır (cascade əvəzinə manual).
     */
    public function deleteAllForProduct(string $productId): void
    {
        $images = ProductImageModel::where('product_id', $productId)->get();

        foreach ($images as $image) {
            Storage::disk($image->disk)->delete($image->path);
        }

        // Bütün qovluğu sil
        Storage::disk($this->disk)->deleteDirectory("products/{$productId}");

        ProductImageModel::where('product_id', $productId)->delete();
    }

    /**
     * Əsas şəkli dəyiş.
     */
    public function setPrimary(string $productId, string $imageId): void
    {
        // Hamısını söndür
        ProductImageModel::where('product_id', $productId)
            ->update(['is_primary' => false]);

        // Seçilmişi yandır
        ProductImageModel::where('id', $imageId)
            ->where('product_id', $productId)
            ->update(['is_primary' => true]);
    }
}
