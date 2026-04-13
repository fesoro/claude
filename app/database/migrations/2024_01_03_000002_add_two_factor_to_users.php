<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 2FA (Two-Factor Authentication) sahələri
 * ==========================================
 * İstifadəçi cədvəlinə 2FA üçün lazım olan sahələri əlavə edir.
 *
 * two_factor_secret: TOTP secret key (base32 encoded).
 *   Google Authenticator bu key-i istifadə edərək 6 rəqəmli kod yaradır.
 *
 * two_factor_enabled: 2FA aktiv/deaktiv.
 *
 * two_factor_backup_codes: JSON array — hər biri bir dəfəlik istifadə olunan kodlar.
 *   Telefon itdikdə/pozulduqda hesaba daxil olmaq üçün.
 *   Hər istifadə olunan kod array-dən silinir.
 */
return new class extends Migration
{
    protected $connection = 'user_db';

    public function up(): void
    {
        Schema::connection('user_db')->table('domain_users', function (Blueprint $table) {
            $table->string('two_factor_secret')->nullable()->after('password');
            $table->boolean('two_factor_enabled')->default(false)->after('two_factor_secret');
            $table->json('two_factor_backup_codes')->nullable()->after('two_factor_enabled');
        });
    }

    public function down(): void
    {
        Schema::connection('user_db')->table('domain_users', function (Blueprint $table) {
            $table->dropColumn(['two_factor_secret', 'two_factor_enabled', 'two_factor_backup_codes']);
        });
    }
};
