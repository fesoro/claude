<?php

declare(strict_types=1);

namespace Src\Notification\Infrastructure\Channels;

use Src\Notification\Domain\Services\NotificationServiceInterface;
use Src\Shared\Domain\Exceptions\DomainException;

/**
 * SMS CHANNEL (Infrastructure Layer - SMS göndərmə kanalı)
 * ==========================================================
 * Bu class SMS vasitəsilə bildiriş göndərir.
 * NotificationServiceInterface-i implement edir.
 *
 * ÇOXKANALLI BİLDİRİŞ SİSTEMİ (Multi-Channel):
 * ================================================
 * E-commerce sistemlərində bir neçə bildiriş kanalı olur:
 * - Email: Ətraflı məlumatlar, qəbzlər, təsdiq məktubları.
 * - SMS: Təcili bildirişlər, OTP kodları, qısa xəbərdarlıqlar.
 * - Push Notification: Mobil tətbiq bildirişləri.
 * - Slack/Telegram: Admin bildirişləri.
 *
 * Hər kanal eyni interface-i (NotificationServiceInterface) implement edir.
 * Bu, Strategy Pattern-ə bənzəyir:
 * - Eyni iş (bildiriş göndər) fərqli üsullarla (email, SMS) edilir.
 * - Runtime-da (işləmə zamanı) hansı üsulun istifadə olunacağına qərar verilir.
 *
 * SİMULYASİYA:
 * ==============
 * Bu öyrənmə proyektidir — real SMS göndərmirik.
 * Real proyektdə burada Twilio, AWS SNS və ya digər SMS provider olardı:
 *   $twilio->messages->create($to, ['from' => $from, 'body' => $body]);
 *
 * OBSERVER PATTERN-DƏ ROLU:
 * ==========================
 * LowStockListener admin-ə SMS göndərmək istəyir.
 * NotificationApplicationService → NotificationChannelFactory → SmsChannel.
 * Bu class zəncirin son halqasıdır — real SMS göndərən hissədir.
 */
class SmsChannel implements NotificationServiceInterface
{
    /**
     * SMS vasitəsilə bildiriş göndərir.
     *
     * Real proyektdə burada Twilio API çağırılardı:
     *   $client = new Twilio\Rest\Client($sid, $token);
     *   $client->messages->create($to, ['from' => $from, 'body' => $body]);
     *
     * Bu simulyasiyadır — yalnız log yazır.
     *
     * @param string $to      Alıcının telefon nömrəsi (+994XXXXXXXXX formatında)
     * @param string $subject SMS-də subject istifadə olunmur, amma interface tələb edir
     * @param string $body    SMS-in məzmunu (qısa olmalıdır — 160 simvol limiti)
     * @param string $channel Kanal adı (burada "sms" olmalıdır)
     *
     * @throws DomainException Telefon nömrəsi düzgün formatda olmadıqda
     */
    public function send(string $to, string $subject, string $body, string $channel): void
    {
        // Telefon nömrəsinin formatını yoxlayırıq.
        // + ilə başlamalı və yalnız rəqəmlərdən ibarət olmalıdır.
        if (!preg_match('/^\+\d{10,15}$/', $to)) {
            throw new DomainException(
                "Düzgün telefon nömrəsi daxil edin: '{$to}' telefon formatında deyil. "
                . "Format: +994XXXXXXXXX"
            );
        }

        // SMS-in uzunluğunu yoxlayırıq.
        // Standart SMS limiti 160 simvoldur.
        // 160-dan uzun mesaj bölünərək bir neçə SMS kimi göndərilir (concatenated SMS).
        if (mb_strlen($body) > 160) {
            // Xəbərdarlıq log-a yazırıq, amma göndərməyə davam edirik.
            logger()->warning('SMS 160 simvoldan uzundur, bölünəcək', [
                'to'     => $to,
                'length' => mb_strlen($body),
            ]);
        }

        // ─── SİMULYASİYA: SMS göndərmə ───
        // Real proyektdə Twilio, AWS SNS və ya digər provider çağırılardı.

        // Simulyasiya — log yazırıq.
        logger()->info('SMS GÖNDƏRİLDİ (simulyasiya)', [
            'to'      => $to,
            'subject' => $subject,
            'body'    => $body,
            'channel' => $channel,
        ]);
    }
}
