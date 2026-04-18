<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * INBOX MESSAGES CƏDVƏLİ — Inbox Pattern üçün
 * ================================================
 *
 * Outbox-ın əksi: gələn mesajları əvvəl DB-yə yazıb,
 * sonra emal etmək üçün istifadə olunur.
 *
 * Outbox: göndərici tərəf (reliable send).
 * Inbox: qəbul tərəfi (reliable receive + exactly-once processing).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inbox_messages', function (Blueprint $table) {
            $table->id();
            $table->string('message_id')->unique(); // Dublikat qoruması
            $table->string('message_type');          // Event adı
            $table->json('payload');                  // Mesaj məzmunu
            $table->string('source');                 // Hansı bounded context-dən
            $table->string('status')->default('pending'); // pending, processed, failed
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('created_at');
            $table->timestamp('processed_at')->nullable();

            $table->index(['status', 'attempts']);    // Pending mesajları tapmaq üçün
            $table->index('created_at');              // Cleanup üçün
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbox_messages');
    }
};
