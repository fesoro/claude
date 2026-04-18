<?php

declare(strict_types=1);

namespace Src\Notification\Domain\Events;

use Src\Shared\Domain\DomainEvent;

/**
 * NOTIFICATION SENT EVENT (Domain Event)
 * =======================================
 * Bu event bildiriş uğurla göndərildikdən sonra yaranır.
 *
 * OBSERVER PATTERN ilə ƏLAQƏSİ:
 * ================================
 * Observer Pattern-də bir "subject" (mövzu) var, bir də "observer" (müşahidəçi).
 * Subject-də dəyişiklik olduqda, bütün observer-lər avtomatik xəbərdar olur.
 *
 * Real həyat nümunəsi:
 * - YouTube kanalına abunə olursan (sən observer-sən).
 * - Kanal yeni video yükləyir (subject dəyişir).
 * - Sənə avtomatik bildiriş gəlir (notify edildin).
 *
 * Bu proyektdə:
 * - NotificationSentEvent yaranır (subject dəyişdi).
 * - Bu event-i dinləyən listener-lər avtomatik işə düşür.
 * - Məsələn: log yazmaq, statistika yeniləmək və s.
 *
 * EVENT-DRIVEN ARCHITECTURE (Hadisə Əsaslı Arxitektura):
 * ======================================================
 * Sistemdə modullar bir-birini birbaşa çağırmır.
 * Əvəzinə, hadisə (event) göndərirlər.
 * Digər modullar bu hadisəni dinləyir və reaksiya verir.
 *
 * Üstünlükləri:
 * - Loose coupling: Modullar bir-birindən asılı deyil.
 * - Scalability: Yeni listener əlavə etmək asandır, mövcud kodu dəyişmirsən.
 * - Flexibility: Bir event-ə neçə listener istəsən əlavə edə bilərsən.
 */
class NotificationSentEvent extends DomainEvent
{
    /**
     * NotificationSentEvent konstruktoru.
     *
     * @param string $recipient Bildirişi alan şəxs (email, telefon nömrəsi və s.)
     * @param string $subject   Bildirişin mövzusu
     * @param string $channel   Göndərmə kanalı: "email", "sms" və s.
     */
    public function __construct(
        private readonly string $recipient,
        private readonly string $subject,
        private readonly string $channel,
    ) {
        // Əvvəlcə parent konstruktoru çağırırıq — eventId və occurredAt yaranır.
        parent::__construct();
    }

    /**
     * Bildirişi alan şəxsi qaytarır.
     */
    public function recipient(): string
    {
        return $this->recipient;
    }

    /**
     * Bildirişin mövzusunu qaytarır.
     */
    public function subject(): string
    {
        return $this->subject;
    }

    /**
     * Hansı kanal ilə göndərildiyini qaytarır (email, sms və s.).
     */
    public function channel(): string
    {
        return $this->channel;
    }

    /**
     * Event-in adı — RabbitMQ routing key-də istifadə olunur.
     * "notification.sent" — bildiriş göndərildi mənasını verir.
     */
    public function eventName(): string
    {
        return 'notification.sent';
    }

    /**
     * Event-i array-ə çevir — RabbitMQ-ya JSON formatında göndərmək üçün.
     * Serialization — obyekti başqa formata çevirmək deməkdir (məsələn JSON).
     */
    public function toArray(): array
    {
        return [
            'event_id'   => $this->eventId(),
            'event_name' => $this->eventName(),
            'recipient'  => $this->recipient,
            'subject'    => $this->subject,
            'channel'    => $this->channel,
            'occurred_at' => $this->occurredAt()->format('Y-m-d H:i:s'),
        ];
    }
}
