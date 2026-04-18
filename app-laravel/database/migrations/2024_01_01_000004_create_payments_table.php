<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bu migrasiyanın hansı verilənlər bazası bağlantısında icra olunacağını təyin edir.
     * Payment kontekstinin cədvəlləri payment_db-də yaradılır.
     */
    protected $connection = 'payment_db';

    public function up(): void
    {
        Schema::connection('payment_db')->create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('order_id');
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('method');       // credit_card, paypal, bank_transfer
            $table->string('status')->default('pending');
            $table->string('transaction_id')->nullable(); // Xarici gateway-in transaction ID-si
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            /*
             * FOREIGN KEY SİLİNDİ: order_id → orders.id
             *
             * orders cədvəli order_db-dədir, payments isə payment_db-dədir.
             * Fərqli verilənlər bazaları arasında foreign key mümkün deyil.
             * order_id yalnız referans ID kimi saxlanılır.
             *
             * Ödəniş-sifariş əlaqəsi application səviyyəsində idarə olunur:
             * Payment Service order_id-ni saxlayır, amma Order Service-in DB-sinə
             * birbaşa müraciət etmir — domain event-lər vasitəsilə əlaqə qurulur.
             */
        });
    }

    public function down(): void
    {
        Schema::connection('payment_db')->dropIfExists('payments');
    }
};
