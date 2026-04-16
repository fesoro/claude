<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PROCESS MANAGER STATE CƏDVƏLİ
 * =================================
 *
 * Process Manager-in cari vəziyyətini saxlayır.
 * Hər sifariş üçün bir sətir var — prosesin harada olduğunu göstərir.
 *
 * NƏYƏ STATE-İ DB-DƏ SAXLAYIRIQ?
 * Process Manager bir neçə dəqiqə, saat, hətta gün davam edə bilər.
 * Server restart olsa və ya worker çöksə, proses itməməlidir.
 * DB-dəki state prosesi davam etdirməyə imkan verir.
 *
 * ANALOGİYA:
 * Online sifariş izləmə: "Ödəniş gözlənilir" → "Göndərilir" → "Çatdırıldı"
 * Bu status DB-dədir — səhifəni yeniləsən belə statusu görmək olur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('process_manager_states', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Proses hansı aggregate-ə aiddir
            $table->string('aggregate_id')->index();

            // Process Manager-in tipi (class adı)
            // Bir neçə fərqli Process Manager ola bilər
            $table->string('process_type');

            // Cari vəziyyət: initiated, payment_pending, completed, failed...
            $table->string('state');

            // Proses boyu toplanan data (JSON)
            $table->json('process_data')->nullable();

            // Tamamlanmış addımlar (kompensasiya üçün)
            $table->json('completed_steps')->nullable();

            // Son event-in zamanı — timeout yoxlaması üçün
            $table->timestamp('last_event_at')->nullable();

            $table->timestamps();

            // Bir aggregate üçün eyni tipli proses yalnız bir ola bilər
            $table->unique(['aggregate_id', 'process_type']);

            // State-ə görə axtarış (monitoring dashboard)
            $table->index(['process_type', 'state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('process_manager_states');
    }
};
