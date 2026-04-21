<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bu migrasiyanın hansı verilənlər bazası bağlantısında icra olunacağını təyin edir.
     * Order kontekstinin cədvəlləri order_db-də yaradılır.
     */
    protected $connection = 'order_db';

    public function up(): void
    {
        Schema::connection('order_db')->create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('status')->default('pending');
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            // Address Value Object — ayrı cədvəl deyil, eyni cədvəldə saxlanılır
            // DDD-də Value Object-ləri embedded saxlamaq adi praktikadır
            $table->string('address_street')->nullable();
            $table->string('address_city')->nullable();
            $table->string('address_zip')->nullable();
            $table->string('address_country')->nullable();
            $table->timestamps();

            /*
             * FOREIGN KEY SİLİNDİ: user_id → domain_users.id
             *
             * Əvvəllər burada foreign key var idi:
             *   $table->foreign('user_id')->references('id')->on('domain_users');
             *
             * Amma domain_users cədvəli user_db-dədir, orders isə order_db-dədir.
             * Foreign key fərqli verilənlər bazaları arasında işləmir!
             * SQLite, MySQL, PostgreSQL — heç birində cross-database foreign key dəstəklənmir.
             *
             * DDD-də bu normaldır: bounded context-lər arası referential integrity
             * database səviyyəsində deyil, application səviyyəsində (domain event-lər,
             * eventual consistency) təmin olunur.
             *
             * user_id burada yalnız referans ID kimi saxlanılır — data bütövlüyü
             * Order Service-in biznes qaydaları ilə təmin olunur.
             */
        });

        // Order Items — Order Aggregate-in daxili Entity-si
        Schema::connection('order_db')->create('order_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('order_id');
            $table->uuid('product_id');
            $table->unsignedInteger('quantity');
            $table->decimal('price', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->timestamps();

            // order_id foreign key saxlanılır — eyni DB-dədir (order_db)
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');

            /*
             * FOREIGN KEY SİLİNDİ: product_id → products.id
             *
             * products cədvəli product_db-dədir, order_items isə order_db-dədir.
             * Cross-database foreign key mümkün deyil.
             * product_id yalnız referans ID kimi saxlanılır.
             */
        });
    }

    public function down(): void
    {
        Schema::connection('order_db')->dropIfExists('order_items');
        Schema::connection('order_db')->dropIfExists('orders');
    }
};
