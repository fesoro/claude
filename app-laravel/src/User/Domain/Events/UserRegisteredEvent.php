<?php

declare(strict_types=1);

namespace Src\User\Domain\Events;

use Src\Shared\Domain\DomainEvent;

/**
 * USER REGISTERED DOMAIN EVENT
 * ============================
 * Bu event istifadəçi qeydiyyatdan keçəndə yaranır.
 *
 * DOMAIN EVENT XÜSUSIYYƏTLƏRI:
 * - Keçmiş zamanda adlandırılır: "UserRegistered" (qeydiyyatdan keçDİ).
 * - Immutable-dir — baş vermiş hadisəni dəyişmək olmaz.
 * - Eyni bounded context (User modulu) daxilində istifadə olunur.
 *
 * BU EVENT-I KİM DİNLƏYİR? (nümunələr)
 * - UserWelcomeEmailListener → xoş gəldin emaili göndərir.
 * - UserActivityLogger → qeydiyyat hadisəsini log-a yazır.
 * - UserRegisteredIntegrationEvent-ə çevrilir → digər modullara göndərilir.
 *
 * AXIN:
 * User::create() → recordEvent(UserRegisteredEvent)
 *   → Repository::save() → pullDomainEvents()
 *   → EventDispatcher → Listener-lər işə düşür
 */
final class UserRegisteredEvent extends DomainEvent
{
    /**
     * Event-in daşıdığı data — bu hadisə haqqında lazım olan bütün məlumatlar.
     *
     * NƏYƏ BU DATALARI SAXLAYIRIQ?
     * - Listener-lər User Entity-yə birbaşa müraciət etməməlidir.
     * - Event özündə lazım olan datanı daşımalıdır (self-contained).
     * - Bu loose coupling təmin edir — listener User class-ından asılı deyil.
     */
    public function __construct(
        private readonly string $userId,
        private readonly string $email,
        private readonly string $name,
    ) {
        /**
         * Parent constructor eventId və occurredAt set edir.
         */
        parent::__construct();
    }

    public function userId(): string
    {
        return $this->userId;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function name(): string
    {
        return $this->name;
    }

    /**
     * Event adı — routing key kimi istifadə olunur.
     * Format: "context.hadisə" → "user.registered"
     */
    public function eventName(): string
    {
        return 'user.registered';
    }

    /**
     * Event-i array-ə çevir — serialization (JSON-a çevirmə) üçün.
     * RabbitMQ-ya göndərmək, bazaya yazmaq və ya log-a çıxarmaq üçün lazımdır.
     */
    public function toArray(): array
    {
        return [
            'event_id' => $this->eventId(),
            'event_name' => $this->eventName(),
            'occurred_at' => $this->occurredAt()->format('Y-m-d H:i:s'),
            'user_id' => $this->userId,
            'email' => $this->email,
            'name' => $this->name,
        ];
    }
}
