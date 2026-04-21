<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\EventSourcing;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * READ MODEL PROJECTOR — Event-dən Read Model-ə Proyeksiya
 * ==========================================================
 *
 * PROBLEMİ ANLAYAQ:
 * =================
 * CQRS-də write model (Event Store) və read model (proyeksiya) ayrıdır.
 * Event Store-da event-lər raw formada saxlanılır — aggregate-in bütün tarixçəsi.
 * Amma UI-a lazım olan data fərqli formadadır:
 *
 * Event Store:
 *   OrderCreated { orderId: "abc", userId: "123" }
 *   ItemAdded { productName: "Laptop", price: 2000 }
 *   ItemAdded { productName: "Mouse", price: 50 }
 *   OrderConfirmed { confirmedAt: "2024-01-01" }
 *
 * Read Model (UI-ın lazımı):
 *   { orderId: "abc", customerName: "Orxan", itemCount: 2, total: 2050, status: "confirmed" }
 *
 * Event Store-dan hər dəfə hesablamaq yavaşdır. Read Model — əvvəlcədən hesablanmış cədvəldir.
 *
 * PROJECTOR NƏ EDİR?
 * ==================
 * Event-i dinləyir və read model cədvəlini yeniləyir:
 *   OrderCreated → INSERT INTO order_read_models (id, status: 'pending', total: 0)
 *   ItemAdded    → UPDATE order_read_models SET total = total + 2000, item_count = item_count + 1
 *   Confirmed    → UPDATE order_read_models SET status = 'confirmed'
 *
 * ANALOGİYA:
 * Excel-də raw data bir sheet-dədir. Pivot table başqa sheet-dədir.
 * Raw data dəyişəndə pivot table avtomatik yenilənir.
 * Projector = pivot table-ı yeniləyən mexanizmdir.
 *
 * ƏSAS XÜSUSİYYƏTLƏR:
 * ====================
 * 1. İDEMPOTENT: Eyni event iki dəfə emal olunsa nəticə eyni qalır.
 * 2. REBUILD OLUNAN: Proyeksiyanı silib sıfırdan yenidən qurmaq olar.
 * 3. ASINXRON: Event baş verəndən sonra azacıq gecikmə ola bilər (eventual consistency).
 * 4. BİR NEÇƏ READ MODEL: Eyni event-dən fərqli proyeksiyalar qurula bilər.
 *    Məs: OrderListProjection (siyahı üçün), OrderStatisticsProjection (dashboard üçün)
 *
 * MATERIALIZED VIEW vs PROJECTOR:
 * ================================
 * DB-nin materialized view-u da oxşar işi görür. Fərq:
 * - Materialized view: DB avtomatik yeniləyir. Sadədir amma yalnız SQL ilə məhdudlaşır.
 * - Projector: Kod ilə yeniləyir. Mürəkkəb transformasiyalar (API call, hesablama) mümkündür.
 *
 * EVENTUAL CONSISTENCY:
 * =====================
 * Write model dəyişəndə read model DƏRHAL dəyişmir — çox qısa gecikmə (ms) var.
 * Bu, performance üçün qəbul edilən kompromisdir.
 * UI-da "Dəyişiklik bir neçə saniyə ərzində görünəcək" mesajı göstərilə bilər.
 */
abstract class Projector
{
    /**
     * Bu projector hansı event-ləri emal edir.
     * Alt class-lar bu metodu implement etməlidir.
     *
     * Konvensiya:
     * 'EventClass' => 'handleMethodName'
     * Məs: OrderCreatedEvent::class => 'onOrderCreated'
     *
     * @return array<class-string, string>
     */
    abstract protected function eventHandlerMap(): array;

    /**
     * Proyeksiyanın istifadə etdiyi cədvəl.
     * Rebuild zamanı bu cədvəl təmizlənir.
     */
    abstract protected function tableName(): string;

    /**
     * Hansı DB connection istifadə olunacaq.
     */
    protected function connection(): string
    {
        return 'sqlite';
    }

    /**
     * EVENT-İ EMAL ET
     * ================
     * Gələn event-ə uyğun handler metodu tapır və çağırır.
     * Əgər bu event üçün handler yoxdursa — heç nə etmir (silent ignore).
     *
     * NƏYƏ SİLENT İGNORE?
     * Çünki bir projector bütün event-ləri emal etmir — yalnız özünə lazım olanları.
     * OrderListProjector yalnız Order event-lərini emal edir, Payment event-lərini ignorə edir.
     */
    public function handle(object $event): void
    {
        $map = $this->eventHandlerMap();
        $eventClass = get_class($event);

        if (!isset($map[$eventClass])) {
            return; // Bu event bizə aid deyil
        }

        $method = $map[$eventClass];

        if (!method_exists($this, $method)) {
            Log::warning("Projector handler metodu tapılmadı", [
                'projector' => static::class,
                'event'     => $eventClass,
                'method'    => $method,
            ]);
            return;
        }

        /**
         * İDEMPOTENCY YOXLAMASI:
         * Eyni event-i iki dəfə emal etməmək üçün.
         * Distributed sistemlərdə event-lər dublikat ola bilər (at-least-once delivery).
         * processedEvents cədvəlini yoxlayırıq.
         */
        $eventId = method_exists($event, 'eventId') ? $event->eventId() : null;

        if ($eventId !== null && $this->isAlreadyProcessed($eventId)) {
            Log::info("Event artıq emal olunub, skip edilir", [
                'projector' => static::class,
                'event_id'  => $eventId,
            ]);
            return;
        }

        // Handler-i çağır
        $this->$method($event);

        // Event-i "emal olundu" kimi qeyd et
        if ($eventId !== null) {
            $this->markAsProcessed($eventId);
        }
    }

    /**
     * PROYEKSİYANI SIFIRDAN YENİDƏN QUR (Rebuild)
     * ==============================================
     * Bütün read model cədvəlini silir və Event Store-dakı bütün event-ləri
     * yenidən emal edir.
     *
     * NƏ VAXT LAZIMDIR?
     * 1. Projector kodunda bug düzəldildikdə — read model yanlış data ilə doludur.
     * 2. Yeni sahə əlavə olunduqda — köhnə sətirlərdə bu sahə yoxdur.
     * 3. Proyeksiya strukturu dəyişdikdə — köhnə cədvəl uyğun gəlmir.
     *
     * PROBLEMLƏRİ:
     * - Böyük event store üçün çox vaxt apara bilər (saatlarla).
     * - Rebuild zamanı read model natamam olur — UI-da köhnə data görünər.
     * - Real layihədə blue-green deployment istifadə olunur:
     *   1. Yeni cədvəl yarat, ora rebuild et.
     *   2. Hazır olanda köhnə cədvəli yenisi ilə əvəz et (atomik swap).
     *
     * @param iterable $allEvents Event Store-dakı bütün event-lər
     */
    public function rebuild(iterable $allEvents): void
    {
        Log::info("Proyeksiya rebuild başladı", [
            'projector' => static::class,
            'table'     => $this->tableName(),
        ]);

        // Read model cədvəlini təmizlə
        DB::connection($this->connection())->table($this->tableName())->truncate();

        // Emal olunmuş event-lərin siyahısını da təmizlə
        $this->clearProcessedEvents();

        $count = 0;

        foreach ($allEvents as $event) {
            $this->handle($event);
            $count++;

            // Hər 1000 event-dən sonra progress log et
            if ($count % 1000 === 0) {
                Log::info("Rebuild progress", [
                    'projector'       => static::class,
                    'processed_count' => $count,
                ]);
            }
        }

        Log::info("Proyeksiya rebuild tamamlandı", [
            'projector'   => static::class,
            'total_events' => $count,
        ]);
    }

    /**
     * Bu event artıq emal olunubmu?
     */
    private function isAlreadyProcessed(string $eventId): bool
    {
        return DB::connection($this->connection())
            ->table('projector_processed_events')
            ->where('projector', static::class)
            ->where('event_id', $eventId)
            ->exists();
    }

    /**
     * Event-i emal olunmuş kimi qeyd et.
     */
    private function markAsProcessed(string $eventId): void
    {
        DB::connection($this->connection())
            ->table('projector_processed_events')
            ->insert([
                'projector'    => static::class,
                'event_id'     => $eventId,
                'processed_at' => now(),
            ]);
    }

    private function clearProcessedEvents(): void
    {
        DB::connection($this->connection())
            ->table('projector_processed_events')
            ->where('projector', static::class)
            ->delete();
    }
}
