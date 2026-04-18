<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PERFORMANCE İNDEKSLƏRİ MİGRASİYASI
 * =====================================
 * Tez-tez istifadə olunan sorğuları sürətləndirmək üçün indekslər əlavə edir.
 *
 * İNDEKS SEÇİM PRİNSİPLƏRİ:
 * ===========================
 *
 * 1. WHERE şərtlərində tez-tez istifadə olunan sütunlar → İndeks əlavə et
 * 2. JOIN-da istifadə olunan foreign key-lər → İndeks əlavə et
 * 3. ORDER BY sütunları → İndeks əlavə et
 * 4. Composite index → Ən selektiv sütun solda olmalıdır
 *
 * COMPOSITE INDEX SİRASI NİYƏ VACİBDİR?
 * =======================================
 * İndeks: (user_id, status, created_at)
 *
 * Bu sorğuları sürətləndirir:
 * ✅ WHERE user_id = 5
 * ✅ WHERE user_id = 5 AND status = 'pending'
 * ✅ WHERE user_id = 5 AND status = 'pending' AND created_at > '2024-01-01'
 *
 * Bu sorğuları sürətləndirMİR:
 * ❌ WHERE status = 'pending' (user_id atlandı!)
 * ❌ WHERE created_at > '2024-01-01' (user_id və status atlandı!)
 *
 * Bu, "telefon kitabı" qaydası kimidir:
 * Telefon kitabı "Soyad → Ad → Ata adı" sırasında indekslənib.
 * Soyadı bilsən axtarış asan, amma yalnız ata adı bilsən kitab kömək etmir.
 *
 * DİQQƏT: Hər indeks:
 * - INSERT/UPDATE-u yavaşladır (indeks yenilənməlidir)
 * - Disk sahəsi tutur
 * Ona görə yalnız real ehtiyac olan yerdə əlavə edilməlidir!
 */
return new class extends Migration
{
    public function up(): void
    {
        // ─── ORDERS CƏDVƏLİ İNDEKSLƏRİ ───

        Schema::table('orders', function (Blueprint $table) {
            // İstifadəçinin sifarişlərini sıralamaq üçün:
            // SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC
            // Bu sorğu müştəri profilində "sifarişlərim" səhifəsində istifadə olunur.
            $table->index(['user_id', 'created_at'], 'idx_orders_user_date');

            // Status üzrə filtrasiya + tarix sıralaması:
            // SELECT * FROM orders WHERE status = 'pending' ORDER BY created_at
            // Admin paneldə "gözləyən sifarişlər" siyahısı üçün.
            $table->index(['status', 'created_at'], 'idx_orders_status_date');
        });

        // ─── PAYMENTS CƏDVƏLİ İNDEKSLƏRİ ───

        Schema::table('payments', function (Blueprint $table) {
            // Sifarişə aid ödənişləri tapmaq üçün:
            // SELECT * FROM payments WHERE order_id = ? AND status = 'completed'
            $table->index(['order_id', 'status'], 'idx_payments_order_status');
        });

        // ─── PRODUCTS CƏDVƏLİ İNDEKSLƏRİ ───

        Schema::table('products', function (Blueprint $table) {
            // Qiymət aralığı ilə axtarış üçün:
            // SELECT * FROM products WHERE price BETWEEN 100 AND 500 ORDER BY price
            // Kataloqda filtr tətbiq edəndə istifadə olunur.
            $table->index('price', 'idx_products_price');

            // Tam mətn axtarışı üçün:
            // SELECT * FROM products WHERE MATCH(name, description) AGAINST('laptop')
            // LIKE '%laptop%' əvəzinə FULLTEXT indeks istifadə edirik — 10-100x sürətli.
            $table->fullText(['name', 'description'], 'idx_products_fulltext');
        });

        // ─── DEAD LETTER MESSAGES CƏDVƏLİ ───

        Schema::create('dead_letter_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('queue', 100)->index();
            $table->json('payload');
            $table->string('exception_class', 255);
            $table->text('exception_message');
            $table->text('exception_trace');
            $table->json('metadata')->nullable();
            $table->enum('status', ['pending', 'retrying', 'resolved', 'discarded'])->default('pending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedTinyInteger('max_attempts')->default(3);
            $table->timestamp('last_retried_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            // Status üzrə axtarış (admin panel üçün):
            $table->index(['status', 'created_at'], 'idx_dlq_status_date');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('idx_orders_user_date');
            $table->dropIndex('idx_orders_status_date');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('idx_payments_order_status');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('idx_products_price');
            $table->dropIndex('idx_products_fulltext');
        });

        Schema::dropIfExists('dead_letter_messages');
    }
};
