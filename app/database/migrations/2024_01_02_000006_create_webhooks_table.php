<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WEBHOOK SİSTEMİ CƏDVƏLLƏRİ
 * ============================
 * Webhook — xarici sistemlərə hadisə bildirişi göndərmə mexanizmidir.
 *
 * NÜMUNƏ: Müştərinin ERP sistemi sifariş yaradılanda avtomatik bilmək istəyir.
 * Webhook qeyd edir: "order.created" hadisəsində bu URL-ə POST göndər.
 *
 * webhooks: Webhook abunəlikləri (hansı URL, hansı event-lər)
 * webhook_logs: Göndərilmə tarixçəsi (uğurlu/uğursuz, cavab kodu)
 */
return new class extends Migration
{
    protected $connection = 'order_db';

    public function up(): void
    {
        Schema::connection('order_db')->create('webhooks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');              // Kim yaradıb
            $table->string('url');                 // Callback URL
            $table->json('events');                // Dinlənilən event-lər: ["order.created", "payment.completed"]
            $table->string('secret_key');           // HMAC imza üçün gizli açar
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::connection('order_db')->create('webhook_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('webhook_id');
            $table->string('event_type');           // Hansı event göndərildi
            $table->json('payload');                // Göndərilən data
            $table->integer('response_code')->nullable();  // HTTP cavab kodu (200, 500, null=timeout)
            $table->text('response_body')->nullable();     // Cavab mətni
            $table->integer('attempt')->default(1);        // Neçənci cəhd
            $table->string('status');               // success, failed, pending
            $table->timestamps();

            $table->foreign('webhook_id')->references('id')->on('webhooks')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::connection('order_db')->dropIfExists('webhook_logs');
        Schema::connection('order_db')->dropIfExists('webhooks');
    }
};
