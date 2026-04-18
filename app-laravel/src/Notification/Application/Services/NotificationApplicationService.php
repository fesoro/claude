<?php

declare(strict_types=1);

namespace Src\Notification\Application\Services;

use Src\Notification\Application\DTOs\NotificationDTO;
use Src\Notification\Domain\Events\NotificationSentEvent;
use Src\Notification\Domain\Services\NotificationServiceInterface;
use Src\Shared\Domain\Exceptions\DomainException;
use Src\Shared\Infrastructure\Bus\EventDispatcher;

/**
 * NOTIFICATION APPLICATION SERVICE (Tətbiq Xidməti)
 * ===================================================
 * Bu service bildiriş göndərmə prosesini orkestrasiya edir (idarə edir).
 *
 * APPLICATION SERVICE NƏDİR?
 * ===========================
 * Application Service — use case-ləri (istifadə hallarını) koordinasiya edən class-dır.
 * Özündə biznes məntiqi saxlamır, əvəzinə digər service-ləri çağırır.
 *
 * Real həyat nümunəsi:
 * - Otel resepsiyonu (application service) kimidir.
 * - Özü otaq təmizləmir (domain logic), amma təmizlikçini çağırır.
 * - Özü yemək bişirmir, amma aşpazı çağırır.
 * - Koordinasiya edir — kimin nə etdiyini bilir.
 *
 * BU SERVICE NƏ EDİR?
 * ====================
 * 1. NotificationServiceInterface (domain service) vasitəsilə bildiriş göndərir.
 * 2. NotificationDTO yaradır — göndərilən bildirişin qeydini saxlamaq üçün.
 * 3. NotificationSentEvent yaradır — bildiriş göndərildikdən sonra.
 *
 * OBSERVER PATTERN-DƏ ROLU:
 * ==========================
 * Listener-lər (observers) event-i aldıqda, bu service-i çağırır.
 * Bu service bildirişin necə göndərilməsini idarə edir.
 *
 * AXIN:
 * ┌──────────┐     ┌──────────────────────────────┐     ┌───────────────────┐
 * │ Listener  │────▶│ NotificationApplicationService│────▶│ EmailChannel/     │
 * │ (Observer)│     │ (bu class — orkestrator)      │     │ SmsChannel        │
 * └──────────┘     └──────────────────────────────┘     └───────────────────┘
 *
 * DEPENDENCY INVERSION:
 * =====================
 * Bu service NotificationServiceInterface-dən asılıdır (abstraction).
 * Concrete class-dan (EmailChannel, SmsChannel) asılı deyil.
 * Bu o deməkdir ki, göndərmə üsulunu dəyişmək üçün bu service-ə toxunmaq lazım deyil.
 */
class NotificationApplicationService
{
    /**
     * Konstruktor — dependency injection vasitəsilə service alır.
     *
     * @param NotificationServiceInterface $notificationService
     *   Domain service interface — bildiriş göndərmə müqaviləsi.
     *   Infrastructure layer-də EmailChannel və ya SmsChannel implement edir.
     *   Laravel Service Container hansını inject edəcəyini ServiceProvider-dən bilir.
     */
    /**
     * Konstruktor — dependency injection vasitəsilə service-ləri alır.
     *
     * @param NotificationServiceInterface $notificationService
     *   Domain service interface — bildiriş göndərmə müqaviləsi.
     *   Infrastructure layer-də EmailChannel və ya SmsChannel implement edir.
     *
     * @param EventDispatcher $eventDispatcher
     *   Event-ləri listener-lərə çatdıran dispatcher.
     *   Bildiriş göndərildikdən sonra NotificationSentEvent dispatch edir.
     *   Bu event-i statistika, audit və ya digər listener-lər dinləyə bilər.
     */
    public function __construct(
        private readonly NotificationServiceInterface $notificationService,
        private readonly EventDispatcher $eventDispatcher,
    ) {
    }

    /**
     * Bildiriş göndərir və DTO qaytarır.
     *
     * Bu metod bütün bildiriş göndərmə axınını orkestrasiya edir:
     * 1. Validasiya — alıcı və kanal boş olmamalıdır.
     * 2. Göndərmə — NotificationServiceInterface vasitəsilə.
     * 3. DTO yaratma — göndərilən bildirişin qeydi.
     * 4. Event yaratma — bildiriş göndərildikdən sonra.
     *
     * @param string $to      Alıcı (email, telefon nömrəsi)
     * @param string $subject Bildirişin mövzusu
     * @param string $body    Bildirişin məzmunu
     * @param string $channel Göndərmə kanalı: "email", "sms" və s.
     *
     * @return NotificationDTO Göndərilən bildirişin məlumatları
     *
     * @throws DomainException Alıcı və ya kanal boş olduqda
     */
    public function send(string $to, string $subject, string $body, string $channel): NotificationDTO
    {
        // ─── 1. VALİDASİYA ───
        // Alıcı boş olmamalıdır — kimə göndərəcəyimizi bilməliyik.
        if (empty($to)) {
            throw new DomainException('Bildiriş alıcısı boş ola bilməz.');
        }

        // Kanal boş olmamalıdır — necə göndərəcəyimizi bilməliyik.
        if (empty($channel)) {
            throw new DomainException('Bildiriş kanalı boş ola bilməz.');
        }

        // ─── 2. GÖNDƏRMƏ ───
        // NotificationServiceInterface vasitəsilə bildiriş göndəririk.
        // Bu interface-in arxasında hansı implementation olduğunu bilmirik
        // (Dependency Inversion Principle).
        // Infrastructure layer-dəki NotificationChannelFactory düzgün kanalı seçir.
        $this->notificationService->send($to, $subject, $body, $channel);

        // ─── 3. DTO YARATMA ───
        // Göndərilən bildirişin məlumatlarını DTO-ya yığırıq.
        // Bu DTO log yazmaq, API response və ya audit üçün istifadə oluna bilər.
        $dto = new NotificationDTO(
            recipient: $to,
            subject: $subject,
            body: $body,
            channel: $channel,
            sentAt: new \DateTimeImmutable(),
        );

        // ─── 4. EVENT YARATMA (ixtiyari) ───
        // Bildiriş göndərildikdən sonra NotificationSentEvent yaradırıq.
        // Bu event-i digər listener-lər dinləyə bilər:
        // - Statistika listener: göndərilən bildiriş sayını artırır.
        // - Audit listener: bildiriş tarixçəsini yazır.
        // Real proyektdə bu event EventDispatcher vasitəsilə göndəriləcək.
        $event = new NotificationSentEvent(
            recipient: $to,
            subject: $subject,
            channel: $channel,
        );

        // ─── 5. EVENT DISPATCH ───
        // Event dispatcher vasitəsilə NotificationSentEvent göndəririk.
        // Bu event-i dinləyən listener-lər:
        // - StatisticsListener: göndərilən bildiriş sayını artırır.
        // - AuditListener: bildiriş tarixçəsini log-a yazır.
        // - AnalyticsListener: bildiriş performansını izləyir.
        //
        // dispatch() metodu array qəbul edir — bir neçə event eyni anda göndərilə bilər.
        // Burada yalnız bir event göndəririk.
        $this->eventDispatcher->dispatch([$event]);

        return $dto;
    }
}
