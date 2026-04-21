<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PRODUCT IMAGES CƏDVƏLİ
 * ========================
 * Hər məhsulun bir neçə şəkli ola bilər (1:N əlaqə).
 *
 * FAYL SAXLAMA STRATEGİYASI:
 * Şəkilin özü FAYIL SİSTEMİNDƏ (disk) saxlanılır, DB-də yalnız YOLU saxlanılır.
 *
 * NƏYƏ DB-DƏ SAXLAMIRUQ?
 * - Şəkillər böyük ola bilər (1-10 MB) — DB şişir, yavaşlayır
 * - DB backup-ı çox böyük olur
 * - Faylları CDN ilə paylaşmaq mümkün olmur
 *
 * DOĞRU YANAŞMA:
 * - Fayl → Storage disk-ə yazılır (local, S3, DigitalOcean Spaces)
 * - Yol → DB-yə yazılır ("products/abc123/image1.jpg")
 * - URL → Storage::url($path) ilə yaradılır
 */
return new class extends Migration
{
    protected $connection = 'product_db';

    public function up(): void
    {
        Schema::connection('product_db')->create('product_images', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('product_id');
            $table->string('path');                  // Storage yolu: "products/abc/image.jpg"
            $table->string('disk')->default('public'); // Hansı disk: public, s3
            $table->string('original_name');          // Orijinal fayl adı: "my-photo.jpg"
            $table->string('mime_type');               // Fayl tipi: "image/jpeg"
            $table->unsignedBigInteger('size');        // Fayl ölçüsü (byte)
            $table->unsignedSmallInteger('sort_order')->default(0); // Sıralama
            $table->boolean('is_primary')->default(false); // Əsas şəkil
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::connection('product_db')->dropIfExists('product_images');
    }
};
