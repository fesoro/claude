<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OUTBOX PATTERN CƏDVƏLI
 * =======================
 * Bu cədvəl Outbox Pattern üçündür.
 *
 * PROBLEM:
 * Order yaradılır (DB-yə yazılır) VƏ RabbitMQ-ya event göndərilir.
 * Əgər DB yazılır amma RabbitMQ çökürsə → event itir!
 * Əgər RabbitMQ göndərilir amma DB çökürsə → event göndərilib amma order yoxdur!
 *
 * HƏLLİ (Outbox Pattern):
 * 1. Order VƏ outbox_message EYNI transaction-da DB-yə yazılır (atomik)
 * 2. Ayrı bir proses (OutboxPublisher) outbox_messages-dən oxuyur
 * 3. RabbitMQ-ya göndərir
 * 4. published_at sahəsini doldurur
 *
 * BELƏLİKLƏ:
 * - DB çöksə → nə order, nə message yazılır (rollback) → problem yoxdur
 * - RabbitMQ çöksə → message DB-də qalır, sonra göndərilər → problem yoxdur
 * - At-least-once delivery təmin olunur
 */
return new class extends Migration
{
    /**
     * Bu migrasiyanın hansı verilənlər bazası bağlantısında icra olunacağını təyin edir.
     * Outbox mesajları Order kontekstinin DB-sindədir (order_db) çünki
     * Order yaradılarkən outbox mesajı eyni transaction-da yazılmalıdır.
     */
    protected $connection = 'order_db';

    public function up(): void
    {
        Schema::connection('order_db')->create('outbox_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('event_type');          // Məs: "order.created"
            $table->string('routing_key');          // RabbitMQ routing key
            $table->json('payload');                // Event data (JSON)
            $table->timestamp('created_at');
            $table->timestamp('published_at')->nullable(); // null = hələ göndərilməyib
            $table->unsignedInteger('retry_count')->default(0);
        });
    }

    public function down(): void
    {
        Schema::connection('order_db')->dropIfExists('outbox_messages');
    }
};
