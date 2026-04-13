<?php

declare(strict_types=1);

namespace Src\Notification\Infrastructure\Channels;

use Src\Notification\Domain\Services\NotificationServiceInterface;
use Src\Shared\Domain\Exceptions\DomainException;

/**
 * NOTIFICATION CHANNEL FACTORY (Factory Pattern)
 * ================================================
 * Bu class Factory Pattern istifadə edərək düzgün bildiriş kanalını yaradır.
 *
 * FACTORY PATTERN NƏDİR?
 * ========================
 * Factory Pattern — obyekt yaratma məntiqini bir yerə toplayan design pattern-dir.
 * "Mənə lazım olan obyekti yarat" deyirsən, Factory hansını yaratmağı özü bilir.
 *
 * Real həyat nümunəsi:
 * - Restoranda "pizza" sifarişi verirsən (channel = "pizza").
 * - Mətbəx (factory) hansı pizzanı bişirəcəyinə qərar verir.
 * - Sən mətbəxdə nə baş verdiyini bilmirsən — nəticəni alırsan.
 *
 * NƏYƏ FACTORY PATTERN?
 * =======================
 * 1. Yaratma məntiqini bir yerə toplamaq:
 *    - "email" gəldikdə EmailChannel yarat.
 *    - "sms" gəldikdə SmsChannel yarat.
 *    - Yeni kanal əlavə etmək üçün yalnız bu factory-ə əlavə et.
 *
 * 2. Open/Closed Principle:
 *    - Yeni kanal əlavə etmək üçün factory-ə əlavə edirsən.
 *    - Mövcud listener-ləri dəyişmirsən.
 *
 * 3. Single Responsibility:
 *    - Listener-lər bildiriş kanalını tanımır.
 *    - Factory kanalı yaradır, listener sadəcə istifadə edir.
 *
 * OBSERVER PATTERN ilə ƏLAQƏSİ:
 * ================================
 * Listener (observer) event-i alır → Application Service-i çağırır →
 * Application Service bu Factory-dən düzgün kanalı alır → kanal göndərir.
 *
 * AXIN:
 * Listener → ApplicationService → Factory.create("email") → EmailChannel → göndər!
 * Listener → ApplicationService → Factory.create("sms")   → SmsChannel   → göndər!
 *
 * YENİ KANAL ƏLAVƏ ETMƏK:
 * ========================
 * Gələcəkdə push notification əlavə etmək istəsən:
 * 1. PushNotificationChannel class-ı yaz (NotificationServiceInterface implement et).
 * 2. Bu factory-ə "push" case əlavə et.
 * 3. Bitdi! Heç bir listener-ə toxunmaq lazım deyil.
 */
class NotificationChannelFactory
{
    /**
     * Dəstəklənən kanalları saxlayır.
     *
     * @var array<string, NotificationServiceInterface>
     *
     * Bu map (xəritə) kanal adını concrete class-a bağlayır.
     * Lazy loading istifadə olunmur — bütün kanallar əvvəlcədən yaradılır.
     * Real proyektdə Laravel Container-dən resolve etmək daha yaxşı olardı.
     */
    private array $channels = [];

    /**
     * Factory konstruktoru — mövcud kanalları qeydiyyatdan keçirir.
     *
     * Dependency Injection vasitəsilə kanallar verilir.
     * Laravel ServiceProvider-da bu kanallar bind olunur.
     *
     * @param EmailChannel $emailChannel Email göndərmə kanalı
     * @param SmsChannel   $smsChannel   SMS göndərmə kanalı
     */
    public function __construct(
        EmailChannel $emailChannel,
        SmsChannel $smsChannel,
    ) {
        // Kanalları map-ə əlavə edirik.
        // "email" açarı → EmailChannel obyekti.
        // "sms" açarı → SmsChannel obyekti.
        $this->channels = [
            'email' => $emailChannel,
            'sms'   => $smsChannel,
        ];
    }

    /**
     * Kanal adına görə düzgün bildiriş kanalını qaytarır.
     *
     * Bu, Factory Pattern-in əsas metodudur:
     * - Giriş: kanal adı (string), məsələn "email" və ya "sms".
     * - Çıxış: həmin kanala uyğun obyekt (NotificationServiceInterface).
     *
     * @param string $channel Kanal adı: "email", "sms" və s.
     *
     * @return NotificationServiceInterface Həmin kanala uyğun service
     *
     * @throws DomainException Dəstəklənməyən kanal adı verildikdə
     *
     * Nümunə:
     * $channel = $factory->create('email');  // EmailChannel qaytarır
     * $channel = $factory->create('sms');    // SmsChannel qaytarır
     * $channel = $factory->create('pigeon'); // DomainException atır!
     */
    public function create(string $channel): NotificationServiceInterface
    {
        // Kanal adını kiçik hərflərə çeviririk — "Email", "EMAIL" hamısı işləsin.
        $normalizedChannel = strtolower(trim($channel));

        // Map-dən kanalı axtarırıq.
        if (!isset($this->channels[$normalizedChannel])) {
            // Dəstəklənən kanalları siyahıya alırıq — xəta mesajında göstərmək üçün.
            $supportedChannels = implode(', ', array_keys($this->channels));

            throw new DomainException(
                "Dəstəklənməyən bildiriş kanalı: '{$channel}'. "
                . "Dəstəklənən kanallar: {$supportedChannels}"
            );
        }

        return $this->channels[$normalizedChannel];
    }

    /**
     * Dəstəklənən kanal adlarını qaytarır.
     *
     * @return array<string> Kanal adları siyahısı, məsələn: ["email", "sms"]
     */
    public function supportedChannels(): array
    {
        return array_keys($this->channels);
    }
}
