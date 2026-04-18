<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CIRCUIT BREAKER STATE TABLE
 * ===========================
 * Circuit Breaker-in vəziyyətini DB-də saxlayır.
 * Bu sayədə application restart olsa belə, circuit breaker state qorunur.
 */
return new class extends Migration
{
    /**
     * Bu migrasiyanın hansı verilənlər bazası bağlantısında icra olunacağını təyin edir.
     * Circuit Breaker Payment kontekstinə aiddir çünki ödəniş gateway-inin
     * mövcudluğunu izləyir — ona görə payment_db-də saxlanılır.
     */
    protected $connection = 'payment_db';

    public function up(): void
    {
        Schema::connection('payment_db')->create('circuit_breaker_states', function (Blueprint $table) {
            $table->string('service_name')->primary(); // Məs: "payment_gateway"
            $table->string('state')->default('closed'); // closed, open, half_open
            $table->unsignedInteger('failure_count')->default(0);
            $table->timestamp('last_failure_at')->nullable();
            $table->timestamp('opened_at')->nullable();    // Circuit nə vaxt açılıb
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('payment_db')->dropIfExists('circuit_breaker_states');
    }
};
