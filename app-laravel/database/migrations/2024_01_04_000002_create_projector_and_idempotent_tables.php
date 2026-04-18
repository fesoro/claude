<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PROJECTOR + IDEMPOTENT CONSUMER CƏDVƏLLƏRİ
 * =============================================
 *
 * Bu migration iki əsas cədvəl yaradır:
 *
 * 1. projector_processed_events — Projector-un hansı event-ləri emal etdiyini izləyir.
 *    İdempotentlik təmin edir — eyni event iki dəfə emal olunmur.
 *
 * 2. processed_messages — Idempotent Consumer-in hansı mesajları emal etdiyini izləyir.
 *    Distributed sistemdə exactly-once semantika üçün vacibdir.
 */
return new class extends Migration
{
    public function up(): void
    {
        /**
         * PROJECTOR PROCESSED EVENTS
         * ==========================
         * Hər projector öz emal etdiyi event-ləri burada qeyd edir.
         * Composite unique key: projector + event_id
         * Çünki eyni event fərqli projector-lar tərəfindən emal oluna bilər.
         */
        Schema::create('projector_processed_events', function (Blueprint $table) {
            $table->id();
            $table->string('projector');
            $table->string('event_id');
            $table->timestamp('processed_at');

            $table->unique(['projector', 'event_id']);
            $table->index('processed_at'); // Cleanup sorğusu üçün
        });

        /**
         * PROCESSED MESSAGES
         * ==================
         * RabbitMQ-dan gələn mesajların emal qeydləri.
         * message_id UNIQUE-dir — dublikat insert cəhdi DB xətası verir.
         * Bu, race condition-u DB səviyyəsində həll edir.
         */
        Schema::create('processed_messages', function (Blueprint $table) {
            $table->id();
            $table->string('message_id')->unique();
            $table->string('message_type');
            $table->timestamp('processed_at');

            $table->index('processed_at'); // Cleanup sorğusu üçün
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('processed_messages');
        Schema::dropIfExists('projector_processed_events');
    }
};
