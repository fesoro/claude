<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OPTİMİSTİK KİLİDLƏMƏ ÜÇÜN VERSİYA SÜTUNU
 * =============================================
 *
 * Hər aggregate cədvəlinə `version` sütunu əlavə edir.
 * Bu sütun optimistic locking mexanizminin əsasını təşkil edir.
 *
 * NECƏ İSTİFADƏ OLUNUR?
 * ======================
 * UPDATE orders SET ..., version = version + 1 WHERE id = ? AND version = ?
 *
 * Əgər başqa proses artıq version-u artırıbsa → WHERE 0 row match edir → ConcurrencyException.
 *
 * INDEX:
 * ======
 * (id, version) üzərində composite index lazım deyil çünki
 * id artıq PRIMARY KEY-dir və WHERE şərti əvvəlcə id-yə baxır.
 * DB optimizer id ilə row-u tapır, sonra version-u yoxlayır.
 *
 * DEFAULT VALUE:
 * ==============
 * version default 1-dir. Mövcud row-lar version 1-dən başlayır.
 * Yeni yaradılan aggregate-lər CREATE zamanı version 1 ilə INSERT olunur.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Orders cədvəli
        if (Schema::hasTable('orders') && !Schema::hasColumn('orders', 'version')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->unsignedInteger('version')->default(1)->after('id');
            });
        }

        // Products cədvəli
        if (Schema::hasTable('products') && !Schema::hasColumn('products', 'version')) {
            Schema::table('products', function (Blueprint $table) {
                $table->unsignedInteger('version')->default(1)->after('id');
            });
        }

        // Payments cədvəli
        if (Schema::hasTable('payments') && !Schema::hasColumn('payments', 'version')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->unsignedInteger('version')->default(1)->after('id');
            });
        }
    }

    public function down(): void
    {
        foreach (['orders', 'products', 'payments'] as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'version')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('version');
                });
            }
        }
    }
};
