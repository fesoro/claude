<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\EventSourcing;

/**
 * EVENT UPCASTER — Event Schema Versiyalaması
 * =============================================
 *
 * PROBLEMİ ANLAYAQ:
 * =================
 * Event Sourcing-də event-lər HEÇ VAXT silinmir və ya dəyişdirilmir (append-only).
 * Amma zaman keçdikcə event-lərin strukturu dəyişə bilər:
 *
 * v1: OrderCreatedEvent { orderId, userId, amount }
 * v2: OrderCreatedEvent { orderId, userId, amount, currency }  ← yeni sahə əlavə olundu
 * v3: OrderCreatedEvent { orderId, customerId, totalAmount, currency }  ← sahələr adları dəyişdi
 *
 * Əgər event replay edəndə köhnə v1 event-i oxunsa, "currency" sahəsi olmayacaq → XƏTA!
 *
 * HƏLLİ — UPCASTING:
 * ==================
 * Event-i oxuyanda (deserialize edəndə) köhnə versiyadan yeni versiyaya çeviririk.
 * Bu, DB migration-a bənzəyir, amma EVENT üçündür:
 *
 * DB Migration:    ALTER TABLE ADD COLUMN currency DEFAULT 'AZN'
 * Event Upcasting: v1 {amount} → v2 {amount, currency: 'AZN'}
 *
 * VACİB FƏRQ:
 * - DB migration bazadakı datanı dəyişir (in-place).
 * - Event upcasting event-i dəyişmir — oxuyanda yaddaşda çevirir (on-the-fly).
 * - Event Store-dakı köhnə event olduğu kimi qalır. Biz onu "oxuyanda" çeviririk.
 *
 * ANALOGİYA:
 * Köhnə format video faylı (AVI) var. Onu silmirsiniz — player açanda avtomatik çevirir.
 * Event Upcaster = video codec converter.
 *
 * UPCASTER ZƏNCİRİ:
 * ==================
 * Bir event v1-dən v3-ə keçməlidirsə:
 * v1 → Upcaster(v1→v2) → v2 → Upcaster(v2→v3) → v3
 * Hər addım kiçik dəyişiklik edir. Bu, db migration-ların ardıcıl tətbiqi kimidir.
 *
 * NƏYƏ LƏĞVEDİLMƏZ?
 * Downcasting (v3→v1) olmur. Çünki v3-də əlavə data var ki, v1 bilmir.
 * Bu elə db migration-da "down" metodunun həmişə mövcud olmaması kimidir.
 *
 * REAL DÜNYA İSTİFADƏSİ:
 * ======================
 * - Axagon, EventSauce, Broadway (PHP ES kitabxanaları) bu pattern-i istifadə edir.
 * - Kafka/Avro dünyasında "schema evolution" adlanır.
 * - Protobuf-da backward/forward compatibility bu problem-ə cavabdır.
 */
class EventUpcaster
{
    /**
     * Qeydiyyatdan keçmiş upcaster-lər.
     * Açar: "EventType:fromVersion" (məs: "OrderCreatedEvent:1")
     * Dəyər: Callable — köhnə payload-ı yeni versiyaya çevirən funksiya.
     *
     * @var array<string, callable(array): array>
     */
    private array $upcasters = [];

    /**
     * UPCASTER QEYDİYYATI
     * ====================
     * Müəyyən bir event tipi üçün versiya çevirici funksiya əlavə edir.
     *
     * @param string   $eventType   Event-in class adı (FQCN)
     * @param int      $fromVersion Hansı versiyadan çevirmək (1, 2, 3...)
     * @param callable $upcaster    Çevirici funksiya: fn(array $oldPayload): array $newPayload
     *
     * NÜMUNƏ:
     * $upcaster->register(
     *     OrderCreatedEvent::class,
     *     1,
     *     fn(array $payload) => [...$payload, 'currency' => 'AZN']
     * );
     */
    public function register(string $eventType, int $fromVersion, callable $upcaster): void
    {
        $key = $this->makeKey($eventType, $fromVersion);
        $this->upcasters[$key] = $upcaster;
    }

    /**
     * EVENT PAYLOAD-INI SON VERSİYAYA YÜKSƏLt
     * ==========================================
     * Köhnə versiyadan başlayaraq bütün upcaster-ləri ardıcıl tətbiq edir.
     *
     * PROSES:
     * 1. Event-in versiyasına baxır.
     * 2. Bu versiya üçün upcaster varsa — tətbiq edir, versiya artır.
     * 3. Növbəti versiya üçün upcaster varsa — onu da tətbiq edir.
     * 4. Upcaster qalmayanda dayanır.
     *
     * Məsələn: v1 event gəlir
     * → v1→v2 upcaster var? Bəli → tətbiq et → v2 oldu
     * → v2→v3 upcaster var? Bəli → tətbiq et → v3 oldu
     * → v3→v4 upcaster var? Xeyr → dayan, v3 qaytarır.
     *
     * @param string $eventType      Event class adı
     * @param array  $payload        Event data (JSON-dan decode olunmuş)
     * @param int    $currentVersion Event-in saxlanmış versiyası
     *
     * @return array{payload: array, version: int} Yüksəldilmiş payload və yeni versiya
     */
    public function upcast(string $eventType, array $payload, int $currentVersion): array
    {
        $version = $currentVersion;

        /**
         * Upcaster zəncirini icra et.
         * while true istifadə edirik çünki neçə versiya atlanacağını bilmirik.
         * Hər dövrdə: "bu versiyadan növbətiyə keçid varmı?" yoxlayırıq.
         */
        while (true) {
            $key = $this->makeKey($eventType, $version);

            if (!isset($this->upcasters[$key])) {
                break; // Bu versiya üçün upcaster yoxdur — son versiyaya çatdıq
            }

            /**
             * Upcaster-i çağır — payload dəyişir.
             * Əvvəlki payload dəyişmir — yeni array qaytarılır (immutability).
             * Bu vacibdir: upcaster-lər side-effect-siz olmalıdır.
             */
            $payload = ($this->upcasters[$key])($payload);
            $version++;
        }

        return [
            'payload' => $payload,
            'version' => $version,
        ];
    }

    /**
     * Bu event tipi üçün upcaster qeydiyyatdan keçibmi?
     */
    public function hasUpcastersFor(string $eventType): bool
    {
        foreach ($this->upcasters as $key => $_) {
            if (str_starts_with($key, $eventType . ':')) {
                return true;
            }
        }

        return false;
    }

    private function makeKey(string $eventType, int $version): string
    {
        return $eventType . ':' . $version;
    }
}
