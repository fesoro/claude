<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bu migrasiyanın hansı verilənlər bazası bağlantısında icra olunacağını təyin edir.
     * Product kontekstinin cədvəlləri product_db-də yaradılır.
     */
    protected $connection = 'product_db';

    public function up(): void
    {
        Schema::connection('product_db')->create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->decimal('price', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->unsignedInteger('stock')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('product_db')->dropIfExists('products');
    }
};
