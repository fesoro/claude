<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * EVENT STORE CƏDVƏLİ — Event Sourcing-in Ürəyi
 * ================================================
 *
 * EVENT SOURCING NƏDİR?
 * ---------------------
 * Event Sourcing — məlumatı "son vəziyyət" kimi deyil, "hadisələr ardıcıllığı" kimi saxlama yanaşmasıdır.
 *
 * ADİ YANAŞMA (State-based):
 *   Sifariş cədvəlində yalnız SON vəziyyət var:
 *   | id  | status    | total  |
 *   | 123 | cancelled | 150.00 |
 *   Sual: Bu sifariş nə vaxt yaradıldı? Kim ləğv etdi? Əvvəlki statusu nə idi?
 *   Cavab: BİLMİRİK — keçmiş itib!
 *
 * EVENT SOURCING YANAŞMASI:
 *   Event Store-da BÜTÜN hadisələr var:
 *   | aggregate_id | event_type     | payload                          | version |
 *   | 123          | OrderCreated   | {user: "ali", address: "Baku"}   | 1       |
 *   | 123          | ItemAdded      | {product: "laptop", qty: 1}      | 2       |
 *   | 123          | OrderConfirmed | {}                               | 3       |
 *   | 123          | OrderCancelled | {reason: "müştəri istədi"}       | 4       |
 *   İndi HƏR ŞEYİ bilirik: nə vaxt, nə baş verib, hansı ardıcıllıqla.
 *
 * REAL HƏYAT ANALOGİYALARI:
 * -------------------------
 *
 * 1. BANK HESABI (ən yaxşı analogiya):
 *    Bankda hesabınızın "son balansı" yalnız NƏTİCƏdir.
 *    Əsl məlumat — hər bir əməliyyatdır (tranzaksiya tarixi):
 *      +5000 AZN maaş
 *      -200 AZN market
 *      -50 AZN taksi
 *      = 4750 AZN (hesablanmış nəticə)
 *    Əgər yalnız "4750 AZN" saxlasanız, haradan gəldi, hara getdi bilməzsiniz.
 *    Event Sourcing = bank hesabının tam tarixçəsi.
 *
 * 2. GIT TARİXÇƏSİ:
 *    Git-də yalnız "son kod" saxlanmır — hər dəyişiklik (commit) ayrıca qeydə alınır.
 *    İstənilən vaxt keçmişə qayıda bilərsiniz (git checkout <commit>).
 *    Event Sourcing = kodunuzun git tarixi, amma biznes data üçün.
 *
 * 3. MÜHASİBATLIQ (Ledger):
 *    Mühasibatlıqda heç vaxt rəqəmi silmirsiniz — əks yazılış (reversal) edirsiniz.
 *    Hər əməliyyat qeydə alınır, balans hesablanır.
 *    Bu elə Event Sourcing-dir.
 *
 * EVENT SOURCING-in ÜSTÜNLÜKLƏRİ:
 * --------------------------------
 * 1. AUDIT TRAIL (təftiş izi) — PULSUZdur!
 *    Kim, nə vaxt, nə edib — hamısı avtomatik qeydə alınır.
 *    Maliyyə, tibb, hüquq sahələrində bu tələbdir.
 *
 * 2. TEMPORAL QUERIES (zamana əsaslanan sorğular):
 *    "Bu sifarişin 3 gün əvvəlki vəziyyəti nə idi?"
 *    Event-ləri həmin tarixə qədər replay edirsiniz — cavab hazırdır.
 *    Adi bazada bu mümkün DEYİL.
 *
 * 3. EVENT REPLAY (hadisə təkrarı):
 *    Yeni bir hesabat lazımdır? Event-ləri başdan oxuyub yeni cədvəl yarada bilərsiniz.
 *    Proyeksiya (Read Model) səhv işləyir? Silib, event-lərdən yenidən qurursunuz.
 *    Bu, "versiyalama" kimidir — data itirilmir.
 *
 * 4. DEBUG / PROBLEM TƏHLİLİ:
 *    "Bu sifariş niyə ləğv olunub?" — event tarixi tam cavab verir.
 *    Adi bazada: "cancelled" statusu görürsünüz, amma niyə bilmirsiniz.
 *
 * 5. ƏLAQƏSIZ SİSTEMLƏR (Decoupled systems):
 *    Digər modullar event-ləri dinləyib öz Read Model-lərini qurur (CQRS).
 *    Yeni modul əlavə olunanda, KEÇMIŞ event-ləri də emal edə bilər.
 *
 * EVENT SOURCING-in ÇƏTİNLİKLƏRİ:
 * --------------------------------
 * 1. Mürəkkəblik — sadə CRUD əvəzinə event-lər idarə etmək lazımdır.
 * 2. Event versioning — event strukturu dəyişəndə köhnə event-lərlə uyğunluq.
 * 3. Eventual consistency — Read Model ani deyil, bir az gecikmə ola bilər.
 * 4. Storage — çox event yaranır, disk sahəsi artır (amma ucuzdur).
 *
 * NƏ VAXT İSTİFADƏ ETMƏLİ?
 * -------------------------
 * - Audit trail vacib olan sistemlər (maliyyə, tibb).
 * - Mürəkkəb biznes prosesləri (sifariş dövriyyəsi, sığorta iddia prosesi).
 * - Zamana əsaslanan sorğular lazım olanda.
 * - Event-driven arxitektura artıq istifadə olunursa.
 *
 * NƏ VAXT İSTİFADƏ ETMƏMƏLİ?
 * ---------------------------
 * - Sadə CRUD əməliyyatları (blog, haqqında səhifəsi).
 * - Komanda çox kiçikdirsə və öyrənmə vaxtı yoxdursa.
 *
 * CƏDVƏLİN STRUKTURU:
 * -------------------
 * - id: Hər event-in unikal UUID-si.
 * - aggregate_id: Hansı Aggregate-ə (məs: hansı sifarişə) aiddir.
 * - aggregate_type: Aggregate-in tipi (məs: "Order", "Payment").
 * - event_type: Hadisənin tipi (məs: "OrderCreated", "ItemAdded").
 * - payload: Event-in data-sı JSON formatında (event-in toArray() nəticəsi).
 * - metadata: Əlavə məlumat — kim etdi, hansı IP-dən, correlation ID və s.
 * - version: Optimistic locking üçün — hər event Aggregate-in versiyasını artırır.
 * - created_at: Event-in baş vermə vaxtı.
 */
return new class extends Migration
{
    /**
     * Bu migration 'order_db' connection-unda icra olunur.
     * Hər bounded context-in öz DB-si ola bilər (database-per-service pattern).
     */
    protected $connection = 'order_db';

    public function up(): void
    {
        Schema::connection($this->connection)->create('event_store', function (Blueprint $table) {
            /**
             * Unikal event identifikatoru (UUID).
             * Hər hadisə unikal olmalıdır — idempotency üçün.
             */
            $table->uuid('id')->primary();

            /**
             * Aggregate ID — bu event hansı Aggregate-ə aiddir?
             * Məsələn: sifarişin UUID-si.
             * İndeksləyirik çünki ən çox "bu aggregate-in bütün event-ləri" sorğusu olacaq.
             */
            $table->uuid('aggregate_id')->index();

            /**
             * Aggregate tipi — "Order", "Payment" və s.
             * Eyni event_store cədvəlində fərqli aggregate tipləri ola bilər.
             * Amma bəzi layihələrdə hər aggregate üçün ayrı event_store olur.
             */
            $table->string('aggregate_type');

            /**
             * Event tipi — hadisənin class adı.
             * Məsələn: "OrderCreated", "OrderCancelled".
             * Deserializasiya zamanı hansı class-a çevirmək lazım olduğunu bildirir.
             */
            $table->string('event_type');

            /**
             * Event-in data-sı — JSON formatında.
             * Hər event tipinin öz strukturu var:
             *   OrderCreated: {order_id: "...", user_id: "..."}
             *   ItemAdded: {product_id: "...", quantity: 2, price: 50.00}
             * JSON istifadə edirik çünki event-lər fərqli strukturlarda ola bilər.
             */
            $table->json('payload');

            /**
             * Metadata — əlavə kontekst məlumatı.
             * Bu, event-in özünə aid deyil, amma faydalı məlumatdır:
             *   - user_id: kim etdi (audit üçün)
             *   - ip_address: hansı IP-dən
             *   - correlation_id: əlaqəli əməliyyatları izləmək üçün
             *   - causation_id: bu event-i hansı event/command yaratdı
             */
            $table->json('metadata')->nullable();

            /**
             * VERSİYA — Optimistic Concurrency Control üçün ən vacib sütun!
             *
             * HƏR AGGREGATE-İN ÖZ VERSİYA SAYI VAR:
             * - İlk event: version = 1
             * - İkinci event: version = 2
             * - ...
             *
             * NƏYƏ LAZIMDIR? (Optimistic Locking izahı):
             * Fərz edin iki istifadəçi eyni sifarişi eyni anda dəyişmək istəyir:
             *
             *   İstifadəçi A: Sifarişi oxuyur (version=3) → dəyişiklik edir → version=4 yazır ✅
             *   İstifadəçi B: Sifarişi oxuyur (version=3) → dəyişiklik edir → version=4 yazmaq istəyir ❌
             *
             *   B uğursuz olur çünki version=4 artıq mövcuddur!
             *   Bu "race condition" problemini həll edir.
             *
             * (aggregate_id + version) cütlüyü unikal olmalıdır — eyni aggregate üçün
             * eyni version nömrəli iki event ola bilməz.
             */
            $table->unsignedInteger('version');

            /**
             * Event-in baş vermə vaxtı.
             * Bu, DomainEvent::occurredAt ilə eyni ola bilər,
             * amma DB-yə yazılma vaxtı da faydalıdır.
             */
            $table->timestamp('created_at')->useCurrent();

            /**
             * KOMPOZİT UNİKAL İNDEKS:
             * Eyni aggregate üçün eyni versiya nömrəsi iki dəfə ola bilməz.
             * Bu, optimistic locking-in DB səviyyəsində təminatıdır.
             * Əgər iki proses eyni anda eyni versiya ilə yazmağa çalışsa,
             * biri uğurlu olacaq, digəri unique constraint violation alacaq.
             */
            $table->unique(['aggregate_id', 'version'], 'event_store_aggregate_version_unique');

            /**
             * Əlavə indeks — event tipinə görə axtarış.
             * "Bütün OrderCreated event-ləri" kimi sorğular üçün faydalıdır.
             */
            $table->index('event_type');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('event_store');
    }
};
