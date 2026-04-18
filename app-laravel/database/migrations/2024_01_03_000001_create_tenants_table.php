<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MULTI-TENANCY CƏDVƏLLƏRİ
 * ==========================
 * Multi-tenancy — BİR tətbiqin ÇOX müştəri (tenant) tərəfindən istifadə edilməsidir.
 *
 * REAL NÜMUNƏ:
 * Shopify — bir platform, minlərlə mağaza. Hər mağaza öz datası ilə işləyir.
 * Slack — bir app, minlərlə workspace. Hər workspace ayrı tenant-dır.
 *
 * MULTİ-TENANCY STRATEGİYALARI:
 *
 * 1. AYRI DATABASE (Database per Tenant):
 *    - Hər tenant üçün ayrı DB yaradılır.
 *    - Tam izolasiya, amma idarəetmə çətindir.
 *    - Migration hər DB-yə ayrı icra olunmalıdır.
 *
 * 2. AYRI SCHEMA (Schema per Tenant — PostgreSQL):
 *    - Eyni DB, amma hər tenant üçün ayrı schema.
 *    - Orta izolasiya, PostgreSQL-ə xas.
 *
 * 3. PAYLAŞILAN DATABASE + TENANT_ID (Shared Database):
 *    - Eyni DB, eyni cədvəllər, amma hər sətirdə tenant_id var.
 *    - Ən sadə, amma data izolasiyası diqqət tələb edir.
 *    - BU LAYİHƏDƏ BU STRATEGİYANI İSTİFADƏ EDİRİK.
 *
 * NƏYƏ TENANT_ID STRATEGİYASI?
 * - Öyrənmək asan, implementasiya sadə
 * - Kiçik-orta layihələr üçün ideal
 * - Global scope ilə avtomatik filter (unudulma riski yox)
 */
return new class extends Migration
{
    protected $connection = 'user_db';

    public function up(): void
    {
        // Tenant-lar cədvəli — hər tenant bir şirkət/mağaza
        Schema::connection('user_db')->create('tenants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');                    // Şirkət adı
            $table->string('slug')->unique();          // URL-friendly ad: "acme-corp"
            $table->string('domain')->nullable();      // Xüsusi domain: "shop.acme.com"
            $table->string('plan')->default('free');   // Abunəlik planı: free, pro, enterprise
            $table->json('settings')->nullable();      // Tenant-a xas konfiqurasiya
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // domain_users cədvəlinə tenant_id əlavə et
        Schema::connection('user_db')->table('domain_users', function (Blueprint $table) {
            $table->uuid('tenant_id')->nullable()->after('id');
            $table->index('tenant_id');
        });

        // products cədvəlinə tenant_id əlavə et
        Schema::connection('product_db')->table('products', function (Blueprint $table) {
            $table->uuid('tenant_id')->nullable()->after('id');
            $table->index('tenant_id');
        });

        // orders cədvəlinə tenant_id əlavə et
        Schema::connection('order_db')->table('orders', function (Blueprint $table) {
            $table->uuid('tenant_id')->nullable()->after('id');
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::connection('order_db')->table('orders', function (Blueprint $table) {
            $table->dropColumn('tenant_id');
        });
        Schema::connection('product_db')->table('products', function (Blueprint $table) {
            $table->dropColumn('tenant_id');
        });
        Schema::connection('user_db')->table('domain_users', function (Blueprint $table) {
            $table->dropColumn('tenant_id');
        });
        Schema::connection('user_db')->dropIfExists('tenants');
    }
};
