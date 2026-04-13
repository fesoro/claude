<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * USER DOMAIN TABLE
 * DDD-də hər bounded context-in öz cədvəlləri olur.
 * Laravel-in default users cədvəlindən fərqli olaraq,
 * bu cədvəl bizim domain model-imizə uyğundur.
 */
return new class extends Migration
{
    /**
     * Bu migrasiyanın hansı verilənlər bazası bağlantısında icra olunacağını təyin edir.
     * User kontekstinin cədvəlləri user_db-də yaradılır.
     */
    protected $connection = 'user_db';

    public function up(): void
    {
        Schema::connection('user_db')->create('domain_users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('user_db')->dropIfExists('domain_users');
    }
};
