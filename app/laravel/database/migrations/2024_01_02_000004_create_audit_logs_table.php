<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit Logs cədvəli üçün migration.
 *
 * ================================================================
 * AUDIT TRAIL (Audit İzi) NƏDİR?
 * ================================================================
 *
 * Audit Trail — sistemdə baş verən bütün dəyişikliklərin qeydidir.
 * "Kim, nə vaxt, nə etdi, nəyi dəyişdi?" suallarına cavab verir.
 *
 * Real həyatdan misal:
 * - Bankda hesab balansı dəyişdikdə, köhnə və yeni dəyər qeyd olunur.
 * - Xəstəxanada həkim xəstənin reseptini dəyişdikdə, tarixçə saxlanılır.
 * - E-ticarətdə admin məhsul qiymətini dəyişdikdə, log yazılır.
 *
 * Niyə vacibdir?
 * ─────────────
 * 1. UYĞUNLUQ (Compliance): GDPR, SOX, PCI-DSS kimi qaydalar audit trail tələb edir.
 * 2. TƏHLÜKƏSİZLİK: Haker hesaba girdisə, nə etdiyini görə bilərik.
 * 3. XƏTAAXTARMA (Debugging): "Məhsul qiyməti niyə dəyişib?" — audit log cavab verir.
 * 4. GERİQAYTARMA: Səhv dəyişikliyi geri qaytarmaq üçün köhnə dəyərləri bilirik.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            /** Unikal identifikator — hər log yazısının öz ID-si */
            $table->id();

            /**
             * user_id — Əməliyyatı kim etdi?
             *
             * Nullable çünki bəzi əməliyyatlar sistem tərəfindən edilir (cron, queue).
             * Bu halda user_id null olur — "sistem özü etdi" deməkdir.
             */
            $table->string('user_id')->nullable();

            /**
             * action — Nə edildi?
             *
             * Nümunələr: "created", "updated", "deleted", "exported", "login", "logout"
             * String istifadə edirik çünki müxtəlif action-lar ola bilər.
             */
            $table->string('action');

            /**
             * auditable_type — Hansı entity (varlıq) üzərində əməliyyat edildi?
             *
             * Laravel-in Polymorphic Relation konsepsiyası:
             * - "Product" → məhsul dəyişdirilib.
             * - "Order" → sifariş dəyişdirilib.
             * - "User" → istifadəçi dəyişdirilib.
             *
             * Bu, bir cədvəldə BÜTÜN entity-lərin audit-ini saxlamağa imkan verir.
             * Alternativ: hər entity üçün ayrı audit cədvəli (product_audit, order_audit...).
             * Amma bu, cədvəl sayını artırır və idarəni çətinləşdirir.
             */
            $table->string('auditable_type');

            /**
             * auditable_id — Hansı konkret entity? (ID-si)
             *
             * Nümunə: auditable_type = "Product", auditable_id = "abc-123"
             * Bu deməkdir: "abc-123" ID-li məhsul üzərində əməliyyat edilib.
             *
             * String istifadə edirik çünki UUID-lər istifadə edirik.
             */
            $table->string('auditable_id');

            /**
             * old_values — Dəyişiklikdən ƏVVƏLKİ dəyərlər (JSON).
             *
             * Nümunə: {"price": 100, "name": "Köhnə Ad"}
             *
             * Nullable çünki "created" əməliyyatında köhnə dəyər yoxdur
             * (entity yeni yaradılıb, əvvəlki vəziyyət mövcud deyil).
             */
            $table->json('old_values')->nullable();

            /**
             * new_values — Dəyişiklikdən SONRAKİ dəyərlər (JSON).
             *
             * Nümunə: {"price": 150, "name": "Yeni Ad"}
             *
             * Nullable çünki "deleted" əməliyyatında yeni dəyər yoxdur
             * (entity silinib, sonrakı vəziyyət mövcud deyil).
             */
            $table->json('new_values')->nullable();

            /**
             * ip_address — İstifadəçinin IP ünvanı.
             *
             * Təhlükəsizlik üçün çox vacibdir:
             * - Şübhəli giriş cəhdlərini aşkar etmək.
             * - "Bu əməliyyat harada edildi?" sualına cavab vermək.
             * - Haker hücumlarını izləmək.
             */
            $table->string('ip_address', 45)->nullable();

            /**
             * user_agent — İstifadəçinin brauzeri/cihazı.
             *
             * Nümunə: "Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0"
             * Bu, hansı cihazdan əməliyyat edildiyini göstərir.
             * Nullable çünki API çağırışlarında olmaya bilər.
             */
            $table->string('user_agent')->nullable();

            /**
             * created_at — Əməliyyat nə vaxt edildi?
             * updated_at lazım deyil çünki audit loglar DEYİŞDİRİLMƏZ (immutable).
             * Bir dəfə yazılır, heç vaxt yenilənmir.
             */
            $table->timestamp('created_at')->useCurrent();

            /**
             * İndekslər — Sürətli axtarış üçün.
             *
             * Ən çox istifadə edilən sorğular:
             * 1. "Bu istifadəçi nə edib?" → user_id index
             * 2. "Bu entity-yə nə olub?" → auditable_type + auditable_id index
             * 3. "Bu tarixdə nə olub?" → created_at index
             */
            $table->index('user_id');
            $table->index(['auditable_type', 'auditable_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
