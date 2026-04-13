<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NOTIFICATION PREFERENCES CƏDVƏLİ
 * ===================================
 * İstifadəçinin bildiriş seçimlərini saxlayır.
 *
 * Hər istifadəçi hər event tipi üçün hansı kanalları (email, SMS) istədiyini seçir.
 * Məsələn: "Sifariş yaradılanda email istəyirəm, SMS istəmirəm"
 *
 * GDPR / İSTİFADƏÇİ HÜQUQLARI:
 * İstifadəçi bildirişləri öz istəyinə görə idarə edə bilməlidir.
 * Bu həm qanuni tələbdir, həm də istifadəçi təcrübəsi üçün vacibdir.
 */
return new class extends Migration
{
    protected $connection = 'user_db';

    public function up(): void
    {
        Schema::connection('user_db')->create('notification_preferences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('event_type');           // order.created, payment.completed, etc.
            $table->boolean('email_enabled')->default(true);
            $table->boolean('sms_enabled')->default(false);
            $table->boolean('push_enabled')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'event_type']); // Hər user + event cütü unikal
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::connection('user_db')->dropIfExists('notification_preferences');
    }
};
