<?php

declare(strict_types=1);

namespace Src\Shared\Domain;

/**
 * DOMAIN EVENT METADATA — Event-lərin İzlənilməsi üçün Metadata
 * ================================================================
 *
 * PROBLEMİ ANLAYAQ:
 * =================
 * Distributed sistemdə bir event zəncir reaksiyası yaradır:
 *
 *   HTTP Request (create order)
 *     → OrderCreatedEvent
 *       → PaymentRequestedEvent
 *         → PaymentCompletedEvent
 *           → StockDecreasedEvent
 *             → NotificationSentEvent
 *
 * Bug tapanda: "Bu notification nəyə görə göndərildi?"
 * Cavab: StockDecreasedEvent → onu PaymentCompletedEvent tetiklədi → onu OrderCreatedEvent tetiklədi.
 *
 * AMMA bu zənciri necə izləyəcəyik? Hər event ayrı-ayrı saxlanılır, əlaqəsi görünmür.
 *
 * HƏLLİ — CORRELATION ID + CAUSATION ID:
 * ========================================
 *
 * CORRELATION ID (Korrelyasiya ID-si):
 * =====================================
 * - Bütün zəncirdəki event-lərdə EYNI olur.
 * - "Bu event-lər hamısı EYNİ əməliyyatın nəticəsidir" bildirir.
 * - İlk event-dən son event-ə qədər eyni correlation ID daşıyır.
 * - Log-larda bu ID ilə axtarış edib bütün zənciri görə bilərsən.
 *
 * Nümunə (eyni correlation ID: "abc-123"):
 *   OrderCreated     [correlation: abc-123]
 *   PaymentRequested [correlation: abc-123]
 *   PaymentCompleted [correlation: abc-123]
 *   StockDecreased   [correlation: abc-123]
 *   NotificationSent [correlation: abc-123]
 *
 * CAUSATION ID (Səbəb ID-si):
 * ============================
 * - Hər event-in "səbəbi" olan event-in ID-sini saxlayır.
 * - "Bu event HANSI event-in NƏTİCƏSİNDƏ yarandı?" sualına cavab verir.
 * - Event zəncirinin tam graph-ını qurmaq üçün istifadə olunur.
 *
 * Nümunə:
 *   OrderCreated     [id: evt-1, causation: null]       ← ilk event, səbəbi yoxdur
 *   PaymentRequested [id: evt-2, causation: evt-1]      ← OrderCreated-dən yarandı
 *   PaymentCompleted [id: evt-3, causation: evt-2]      ← PaymentRequested-dən yarandı
 *   StockDecreased   [id: evt-4, causation: evt-3]      ← PaymentCompleted-dən yarandı
 *
 * ANALOGİYA:
 * ==========
 * Correlation ID = İş nömrəsi: "INC-456" — bütün əlaqəli tapşırıqlar bu nömrəyə bağlıdır.
 * Causation ID = "Bu tapşırıq KİMİN tapşırığından yarandı?" — birbaşa parent.
 *
 * Correlation ID bir "horizontal" izləmə (hamısı eyni əməliyyat),
 * Causation ID bir "vertical" izləmə (kim kimi tetiklədi).
 *
 * İKİSİ BİRLİKDƏ:
 * ================
 * Correlation ID ilə: "abc-123 əməliyyatında nə baş verdi?" — bütün event-ləri görürsən.
 * Causation ID ilə: "Bu event nəyə görə baş verdi?" — səbəb zəncirini izləyirsən.
 *
 * PRATİK İSTİFADƏ:
 * =================
 * 1. DEBUG: Production-da xəta olduqda, correlation ID ilə bütün əlaqəli log-ları tapırsan.
 * 2. MONİTORİNQ: Datadog/Kibana-da correlation ID ilə distributed trace qurulur.
 * 3. AUDIT: "Bu ödəniş nəyə görə edildi?" → causation chain-dən OrderCreated-ə çatırsan.
 * 4. REPLAY: Event replay zamanı eyni correlation ID ilə yenidən oynada bilərsən.
 *
 * HTTP + EVENT ƏLAQƏSI:
 * =====================
 * HTTP Request gəlir → CorrelationIdMiddleware unique ID yaradır.
 * Command handler bu ID-ni event-ə ötürür.
 * Event listener yeni event yaradanda:
 *   - correlation_id = eyni (ilk request-dən gəlir)
 *   - causation_id = öncəki event-in ID-si
 *
 * Bu, OpenTelemetry/Jaeger/Zipkin kimi distributed tracing alətlərinin əsasını təşkil edir.
 */
final readonly class DomainEventMetadata
{
    public function __construct(
        /**
         * Bütün əlaqəli event-ləri birləşdirən ID.
         * İlk HTTP request-dən yaranır və bütün zəncir boyu ötürülür.
         */
        public string $correlationId,

        /**
         * Bu event-in birbaşa "səbəbi" olan event-in ID-si.
         * İlk event üçün null olur (heç bir event tetikləməyib).
         */
        public ?string $causationId = null,

        /**
         * Event-i yaradan istifadəçinin ID-si.
         * Audit üçün — "kim tetiklədi?" sualına cavab verir.
         */
        public ?string $actorId = null,

        /**
         * Event-in yarandığı kontekst: 'http', 'console', 'queue', 'scheduler'.
         * Debug üçün faydalıdır — "bu event haradan gəldi?"
         */
        public string $source = 'unknown',
    ) {}

    /**
     * Yeni event yaradanda bu metadata-dan "uşaq" metadata yarat.
     * Correlation ID eyni qalır, causation ID isə indiki event-in ID-si olur.
     *
     * @param string $parentEventId Bu event-in ID-si (uşaq event-in causation-ı olacaq)
     */
    public function forChildEvent(string $parentEventId): self
    {
        return new self(
            correlationId: $this->correlationId,
            causationId: $parentEventId,
            actorId: $this->actorId,
            source: $this->source,
        );
    }

    /**
     * HTTP request-dən ilk metadata yaradanda istifadə olunur.
     */
    public static function fromHttpRequest(string $correlationId, ?string $actorId = null): self
    {
        return new self(
            correlationId: $correlationId,
            causationId: null,
            actorId: $actorId,
            source: 'http',
        );
    }

    /**
     * Queue/Job-dan event yarananda istifadə olunur.
     */
    public static function fromQueue(string $correlationId, string $causationId): self
    {
        return new self(
            correlationId: $correlationId,
            causationId: $causationId,
            source: 'queue',
        );
    }

    public function toArray(): array
    {
        return [
            'correlation_id' => $this->correlationId,
            'causation_id' => $this->causationId,
            'actor_id' => $this->actorId,
            'source' => $this->source,
        ];
    }
}
