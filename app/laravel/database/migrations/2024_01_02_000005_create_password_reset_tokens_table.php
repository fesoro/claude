<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PASSWORD RESET TOKENS CƏDVƏLİ
 * ==============================
 * Şifrə sıfırlama token-lərini saxlayır.
 * User bounded context-inə aiddir (user_db).
 *
 * AXIN:
 * 1. forgot-password → token yaradılır, bu cədvələ hash-lənib yazılır
 * 2. reset-password → token yoxlanılır, şifrə dəyişdirilir, token silinir
 */
return new class extends Migration
{
    protected $connection = 'user_db';

    public function up(): void
    {
        Schema::connection('user_db')->create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('user_db')->dropIfExists('password_reset_tokens');
    }
};
