<?php

declare(strict_types=1);

namespace Src\Product\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * PRODUCT IMAGE MODEL
 * ====================
 * Məhsul şəklinin DB qeydini təmsil edir.
 *
 * ACCESSOR:
 * $image->url → Storage::disk($disk)->url($path) → tam URL qaytarır
 * DB-də yalnız nisbi yol saxlanılır: "products/abc/image.jpg"
 * URL isə disk-ə görə dəyişir:
 *   - public disk: http://localhost/storage/products/abc/image.jpg
 *   - s3 disk: https://bucket.s3.amazonaws.com/products/abc/image.jpg
 */
class ProductImageModel extends Model
{
    use HasUuids;

    protected $connection = 'product_db';
    protected $table = 'product_images';

    protected $fillable = [
        'id', 'product_id', 'path', 'disk', 'original_name',
        'mime_type', 'size', 'sort_order', 'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'sort_order' => 'integer',
            'is_primary' => 'boolean',
        ];
    }

    /**
     * Şəklin tam URL-ini qaytar.
     *
     * LARAVEL STORAGE DİSK-LƏRİ:
     * ═══════════════════════════
     * config/filesystems.php-də təyin olunur:
     *
     * 'local' disk: storage/app/ — Private fayllar (invoice PDF, export)
     *   → URL yoxdur, yalnız server oxuya bilər
     *   → Storage::disk('local')->get($path) ilə oxunur
     *
     * 'public' disk: storage/app/public/ → public/storage/ (symlink)
     *   → URL var: http://localhost/storage/products/image.jpg
     *   → php artisan storage:link ilə symlink yaradılır
     *   → Şəkillər, avatar, publik fayllar üçün
     *
     * 's3' disk: Amazon S3 bucket
     *   → URL var: https://bucket.s3.amazonaws.com/products/image.jpg
     *   → Production-da ən yaxşı seçim (CDN, scalability)
     *   → .env-də AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY, AWS_BUCKET təyin olunur
     */
    public function getUrlAttribute(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    /**
     * Fayl ölçüsünü oxunaqlı formatda qaytar.
     * 1536000 → "1.5 MB"
     */
    public function getHumanSizeAttribute(): string
    {
        $bytes = $this->size;
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 1) . ' ' . $units[$i];
    }

    public function product()
    {
        return $this->belongsTo(ProductModel::class, 'product_id');
    }
}
