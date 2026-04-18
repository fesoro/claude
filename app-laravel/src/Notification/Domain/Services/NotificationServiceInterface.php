<?php

declare(strict_types=1);

namespace Src\Notification\Domain\Services;

/**
 * NOTIFICATION SERVICE INTERFACE (Domain Layer - Müqavilə)
 * =========================================================
 * Bu interface bildiriş göndərmə xidmətinin müqaviləsini (contract) təyin edir.
 *
 * INTERFACE NƏDİR?
 * ================
 * Interface — "nə etməli" deyir, amma "necə etməli" demir.
 * Müqavilə kimidir: "Sən mənə bildiriş göndərə biləcəksən" deyir,
 * amma email ilə, SMS ilə, yoxsa push notification ilə — bunu demir.
 *
 * Real həyat nümunəsi:
 * - Bir restoranın menyu-su (interface) var: "pizza", "burger".
 * - Aşpaz (implementation) bunu necə bişirəcəyinə özü qərar verir.
 * - Müştərini aşpazın üsulu maraqlandırmır, nəticə vacibdir.
 *
 * NƏYƏ LAZIMDIR?
 * ===============
 * 1. Dependency Inversion Principle (DIP):
 *    - Domain layer concrete class-dan asılı olmamalıdır.
 *    - Interface vasitəsilə Infrastructure layer-i çağırır.
 *    - Bu, "plug and play" imkanı verir: email provider-i dəyişmək istəsən,
 *      yalnız Infrastructure-da yeni class yazırsan, Domain-a toxunmursan.
 *
 * 2. Testing (Test yazmaq):
 *    - Test zamanı real email göndərmək istəmirsən.
 *    - Mock (saxta) class yaradıb interface-i implement edirsən.
 *    - Test sürətli və etibarlı olur.
 *
 * OBSERVER PATTERN ilə ƏLAQƏSİ:
 * ================================
 * Observer Pattern-də observer (müşahidəçi) subject-dən (mövzudan) xəbər alır.
 * Bu interface — observer-in "necə reaksiya verəcəyini" təyin edir.
 * Listener event-i alır → NotificationServiceInterface vasitəsilə bildiriş göndərir.
 *
 * AXIN:
 * 1. RabbitMQ-dan OrderCreatedIntegrationEvent gəlir.
 * 2. OrderCreatedListener bu event-i tutur.
 * 3. Listener → NotificationServiceInterface::send() çağırır.
 * 4. Infrastructure layer-dəki EmailChannel və ya SmsChannel real göndərməni edir.
 */
interface NotificationServiceInterface
{
    /**
     * Bildiriş göndərir.
     *
     * @param string $to      Alıcı (email, telefon nömrəsi və s.)
     * @param string $subject Bildirişin mövzusu
     * @param string $body    Bildirişin məzmunu (mətni)
     * @param string $channel Göndərmə kanalı: "email", "sms" və s.
     *
     * @throws \Src\Shared\Domain\Exceptions\DomainException Bildiriş göndərilə bilmədikdə
     *
     * Nümunə:
     * $service->send('user@example.com', 'Sifariş təsdiqi', 'Sifarişiniz yaradıldı!', 'email');
     * $service->send('+994501234567', 'Ödəniş', 'Ödənişiniz uğurludur', 'sms');
     */
    public function send(string $to, string $subject, string $body, string $channel): void;
}
