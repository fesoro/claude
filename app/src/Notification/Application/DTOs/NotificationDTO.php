<?php

declare(strict_types=1);

namespace Src\Notification\Application\DTOs;

/**
 * NOTIFICATION DTO (Data Transfer Object)
 * ========================================
 * DTO — verilənləri bir layer-dən digərinə daşımaq üçün istifadə olunan obyektdir.
 *
 * DTO NƏDİR?
 * ===========
 * - "Data Transfer Object" — məlumat daşıyıcı obyekt deməkdir.
 * - Heç bir biznes məntiqi yoxdur — yalnız data saxlayır.
 * - Readonly-dir: yaradıldıqdan sonra dəyişdirilə bilməz (immutable).
 *
 * Real həyat nümunəsi:
 * - Poçtla göndərilən zərf (envelope) — DTO kimidir.
 * - Zərfin içində məktub var (data), amma zərf özü məktubu oxumur.
 * - Yalnız bir yerdən digər yerə daşıyır.
 *
 * NƏYƏ LAZIMDIR?
 * ===============
 * 1. Layer Separation (Təbəqə ayrılığı):
 *    - Domain Entity-ni birbaşa API-yə qaytarmaq təhlükəlidir.
 *    - DTO yalnız lazım olan məlumatları daşıyır.
 *
 * 2. Readonly (Dəyişməz):
 *    - PHP 8.2-nin readonly class xüsusiyyəti istifadə olunur.
 *    - Property-lər yaradıldıqdan sonra dəyişdirilə bilməz.
 *    - Bu, data-nın təhlükəsizliyini təmin edir.
 *
 * OBSERVER PATTERN-DƏ ROLU:
 * ==========================
 * Listener event-dən data-nı çıxarır → DTO-ya yığır → Service-ə göndərir.
 * DTO burada "event data-sının strukturlaşdırılmış forması" rolunu oynayır.
 *
 * Axın:
 * 1. OrderCreatedListener event-dən orderId, email və s. çıxarır.
 * 2. Bu data-ları NotificationDTO-ya yığır.
 * 3. NotificationApplicationService bu DTO-nu alıb bildiriş göndərir.
 */
readonly class NotificationDTO
{
    /**
     * NotificationDTO konstruktoru.
     *
     * PHP 8.2 "constructor promotion" istifadə olunur:
     * - Parametr eyni zamanda property olur.
     * - readonly — yaradıldıqdan sonra dəyişdirilə bilməz.
     *
     * @param string             $recipient Bildirişi alan şəxs (email və ya telefon)
     * @param string             $subject   Bildirişin mövzusu
     * @param string             $body      Bildirişin məzmunu
     * @param string             $channel   Kanal: "email", "sms" və s.
     * @param \DateTimeImmutable $sentAt    Göndərilmə vaxtı
     */
    public function __construct(
        public string $recipient,
        public string $subject,
        public string $body,
        public string $channel,
        public \DateTimeImmutable $sentAt,
    ) {
        // Readonly class-da constructor promotion ilə bütün property-lər
        // avtomatik readonly olur. Əlavə assignment lazım deyil.
    }

    /**
     * DTO-nu array-ə çevirir — API response və ya log üçün faydalıdır.
     *
     * @return array<string, string> DTO data-sı array formatında
     */
    public function toArray(): array
    {
        return [
            'recipient' => $this->recipient,
            'subject'   => $this->subject,
            'body'      => $this->body,
            'channel'   => $this->channel,
            'sent_at'   => $this->sentAt->format('Y-m-d H:i:s'),
        ];
    }
}
