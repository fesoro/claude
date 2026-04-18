<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SİFARİŞ READ MODEL CƏDVƏLİ — CQRS-in "Query" Tərəfi
 * ======================================================
 *
 * CQRS (Command Query Responsibility Segregation) NƏDİR?
 * -------------------------------------------------------
 * CQRS — yazma (Command) və oxuma (Query) əməliyyatlarını AYRI strukturlarda saxlama yanaşmasıdır.
 *
 * ADİ YANAŞMA (tək cədvəl):
 *   orders cədvəli həm yazma, həm oxuma üçün istifadə olunur.
 *   Problem: oxuma sorğuları mürəkkəb JOIN-lər tələb edir (user adı, item sayı, cəmi məbləğ...).
 *   Bu, həm yavaşdır, həm mürəkkəb SQL yazmaq lazımdır.
 *
 * CQRS YANAŞMASI (ayrı cədvəllər):
 *   YAZMA: Event Store-a event yazılır (OrderCreated, ItemAdded, OrderPaid...).
 *   OXUMA: Bu read model cədvəlindən oxunur — artıq DENORMALİZƏ olunmuş, hazır data.
 *
 * DENORMALİZASİYA NƏDİR?
 * -----------------------
 * Normalizasiya: datanı ayrı cədvəllərə bölmək (users, orders, order_items).
 *   Üstünlüyü: data təkrarlanmır, yer qənaət olunur.
 *   Mənfi: oxuma zamanı JOIN lazımdır → yavaş.
 *
 * Denormalizasiya: datanı BİR cədvəldə birləşdirmək.
 *   Bu cədvəldə user_name var — ayrıca users cədvəlinə JOIN lazım deyil.
 *   item_count var — order_items cədvəlini COUNT etmək lazım deyil.
 *   Üstünlüyü: oxuma ŞİMŞƏK SÜRƏTLİ — SELECT * FROM order_read_model WHERE order_id = ?
 *   Mənfi: data təkrarlanır, amma bu qəbul edilən trade-off-dur.
 *
 * ANALOGİYA — KİTABXANA KATALOQU:
 * ================================
 * Kitabxanada iki sistem var:
 * 1. ANBAR (Event Store): Hər kitabın tam tarixi — nə vaxt alınıb, kim oxuyub, harada saxlanılır.
 * 2. KATALOQ (Read Model): Kitabın adı, müəllifi, rəfdəki yeri — tez tapmaq üçün sadələşdirilmiş kartlar.
 *
 * Kataloq ANBARdan yaradılır. Əgər kataloq itərsə, anbardan yenidən yaradıla bilər (rebuild).
 * Amma anbar itərsə, kataloqdan bərpa mümkün DEYİL — çünki kataloqda məhdud data var.
 *
 * EVENTUAL CONSISTENCY (Son Uyğunluq):
 * ====================================
 * Read Model DƏRHAL yenilənməyə bilər — bir az gecikmə (latency) ola bilər.
 * Event yazıldı → Projeksiyon dinlədi → Read Model yeniləndi (millisaniyələr).
 * Bu "eventual consistency" adlanır: "nəhayətdə uyğun olacaq".
 *
 * Real həyat nümunəsi:
 * Bank hesabınıza pul köçürdünüz. Mobil bankda dərhal görünməyə bilər.
 * Amma bir neçə saniyə sonra balans yenilənir. Bu, eventual consistency-dir.
 */
return new class extends Migration
{
    /**
     * order_db connection-unda icra olunur — Event Store ilə eyni DB-dədir.
     * Real layihədə Read Model tamamilə AYRI DB-də ola bilər (məs: PostgreSQL + Elasticsearch).
     */
    protected $connection = 'order_db';

    public function up(): void
    {
        Schema::connection($this->connection)->create('order_read_model', function (Blueprint $table) {
            /**
             * Sifarişin UUID-si — primary key.
             * Bu, Event Store-dakı aggregate_id ilə eynidir.
             * Auto-increment yoxdur çünki UUID istifadə edirik.
             */
            $table->uuid('order_id')->primary();

            /**
             * Sifarişi verən istifadəçinin ID-si.
             * İndeksləyirik çünki "bu istifadəçinin bütün sifarişləri" sorğusu tez-tez olacaq.
             */
            $table->string('user_id')->index();

            /**
             * İstifadəçinin adı — DENORMALİZƏ olunmuş sahə.
             * Normalizasiyada bu ayrı "users" cədvəlində olardı və JOIN lazım olardı.
             * Burada birbaşa saxlayırıq ki, oxuma zamanı JOIN lazım olmasın.
             *
             * PROBLEM: İstifadəçi adını dəyişsə nə olacaq?
             * HƏLLİ: UserNameChanged event-i dinlənir və bütün read model-lər yenilənir.
             * Və ya read model-i yenidən qurmaq olar (rebuild).
             */
            $table->string('user_name')->default('');

            /**
             * Sifarişin cari statusu — string formatında.
             * İndeksləyirik çünki "bütün ödənilmiş sifarişlər" kimi filtrləmə sorğuları olacaq.
             */
            $table->string('status')->index();

            /**
             * Cəmi məbləğ — qəpiklərlə (minor units) saxlanılır.
             * Məsələn: 15050 = 150.50 AZN.
             * Float istifadə etmirik — dəqiqlik itkisi ola bilər.
             */
            $table->bigInteger('total_amount')->default(0);

            /**
             * Valyuta kodu (ISO 4217).
             * Məsələn: 'AZN', 'USD', 'EUR'.
             */
            $table->string('currency', 3)->default('AZN');

            /**
             * Sifarişdəki məhsul sayı — DENORMALİZƏ olunmuş sahə.
             * Normalizasiyada COUNT(order_items) etmək lazım olardı.
             * Burada hazır rəqəm var — oxuma anında hesablama lazım deyil.
             */
            $table->unsignedInteger('item_count')->default(0);

            /**
             * Son yenilənmə vaxtı.
             * Hər event emal edildikdə bu sahə yenilənir.
             * Debug və monitoring üçün faydalıdır:
             *   "Bu read model nə vaxt son dəfə yenilənib?"
             */
            $table->timestamp('last_updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('order_read_model');
    }
};
