<?php

declare(strict_types=1);

namespace Src\Notification\Infrastructure\Channels;

use App\Mail\OrderConfirmationMail;
use App\Mail\PaymentFailedMail;
use App\Mail\PaymentReceiptMail;
use App\Mail\LowStockAlertMail;
use Illuminate\Support\Facades\Mail;
use Src\Notification\Domain\Services\NotificationServiceInterface;
use Src\Shared\Domain\Exceptions\DomainException;

/**
 * EMAIL CHANNEL (Infrastructure Layer - Email göndərmə kanalı)
 * ==============================================================
 * Bu class email vasitəsilə bildiriş göndərir.
 * NotificationServiceInterface-i implement edir (müqaviləni yerinə yetirir).
 *
 * INFRASTRUCTURE LAYER NƏDİR?
 * =============================
 * Infrastructure — xarici sistemlərlə əlaqə quran təbəqədir:
 * - Email serveri (SMTP, SendGrid, Mailgun)
 * - SMS servisi (Twilio, SNS)
 * - Verilənlər bazası (MySQL, PostgreSQL)
 * - Mesaj broker (RabbitMQ)
 *
 * Domain layer xarici sistemləri tanımır — yalnız interface bilir.
 * Infrastructure bu interface-i implement edib real işi görür.
 *
 * Real həyat nümunəsi:
 * - Domain deyir: "Məktubu göndər" (interface).
 * - Infrastructure: "Mən bunu poçtçu (SMTP) ilə göndərəcəm" (implementation).
 * - Sabah poçtçu dəyişə bilər (SendGrid → Mailgun), amma domain bilmir.
 *
 * REAL MAİLABLE İNTEQRASİYASI:
 * ==============================
 * Artıq simulyasiya deyil — real Laravel Mail fasadı istifadə olunur.
 * Mail::to($to)->send(new SomeMailable(...)) çağırılır.
 *
 * Mail fasadı .env faylındakı konfiqurasiyaya görə email göndərir:
 *   MAIL_MAILER=smtp          → SMTP ilə göndərir
 *   MAIL_HOST=smtp.gmail.com  → Gmail SMTP serveri
 *   MAIL_PORT=587             → TLS portu
 *   MAIL_USERNAME=...         → SMTP istifadəçi adı
 *   MAIL_PASSWORD=...         → SMTP şifrəsi
 *
 * Development üçün MAIL_MAILER=log istifadə edin — email göndərilmir, log-a yazılır.
 * Və ya Mailpit/Mailtrap kimi alətlər istifadə edin (saxta SMTP server).
 *
 * OBSERVER PATTERN-DƏ ROLU:
 * ==========================
 * Observer (Listener) → Application Service → BU CLASS (real göndərmə).
 * Bu class zəncirin son halqasıdır — real işi görən hissədir.
 */
class EmailChannel implements NotificationServiceInterface
{
    /**
     * Email vasitəsilə bildiriş göndərir.
     *
     * Laravel Mail fasadı istifadə edərək real email göndərir.
     * Subject (mövzu) parametrinə görə uyğun Mailable class seçilir.
     *
     * MAILABLE SEÇİM MƏNTİQİ:
     * ==========================
     * Bu metod ümumi (generic) bir interface-dir — subject-ə görə uyğun Mailable təyin edir.
     * Real proyektdə daha yaxşı yanaşma: hər bir use case öz Mailable-ını birbaşa göndərsin.
     * Amma DDD arxitekturasında domain layer Mailable-ları tanımır (infrastructure detalıdır),
     * ona görə bu "mapping" (uyğunlaşdırma) burada — infrastructure layer-da edilir.
     *
     * @param string $to      Alıcının email adresi
     * @param string $subject Emailin mövzusu (hansı Mailable istifadə ediləcəyini müəyyən edir)
     * @param string $body    Emailin məzmunu (JSON formatında əlavə məlumatlar)
     * @param string $channel Kanal adı (burada "email" olmalıdır)
     *
     * @throws DomainException Email adresi düzgün formatda olmadıqda və ya göndərmə uğursuz olduqda
     */
    public function send(string $to, string $subject, string $body, string $channel): void
    {
        // Email formatını yoxlayırıq.
        // filter_var — PHP-nin daxili funksiyasıdır, email formatını yoxlayır.
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new DomainException(
                "Düzgün email adresi daxil edin: '{$to}' email formatında deyil."
            );
        }

        // ─── REAL EMAİL GÖNDƏRMƏ ───
        // Body JSON formatında əlavə məlumatlar saxlaya bilər.
        // json_decode ilə decode edirik — əgər JSON deyilsə, null qaytarır.
        $data = json_decode($body, true) ?? [];

        try {
            // Mövzuya (subject) görə uyğun Mailable seçirik.
            // Bu "mapping" yanaşmasıdır — subject string-inə görə Mailable class müəyyən edilir.
            //
            // str_contains() — PHP 8 funksiyasıdır, string-in içində başqa string axtarır.
            // match() — PHP 8-in match ifadəsidir, switch-in daha qısa versiyasıdır.
            // default → heç bir şərt uyğun gəlmədikdə çalışır.
            $mailable = $this->resolveMailable($subject, $to, $data);

            // Mail::to($to)->send($mailable) — Laravel-in email göndərmə mexanizmi.
            //
            // Mail — fasaddır (Facade), arxasında Illuminate\Mail\Mailer class-ı dayanır.
            // to($to) — alıcını təyin edir.
            // send($mailable) — Mailable obyektini göndərir (sinxron).
            //
            // Asinxron göndərmək üçün: Mail::to($to)->queue($mailable);
            // Bu halda email queue worker tərəfindən arxa planda göndərilir.
            // LowStockAlertMail ShouldQueue implement edir — send() çağırılsa belə queue-yə düşür.
            Mail::to($to)->send($mailable);

            // Uğurlu göndərmədən sonra log yazırıq (monitoring üçün).
            logger()->info('EMAIL GÖNDƏRİLDİ', [
                'to'      => $to,
                'subject' => $subject,
                'channel' => $channel,
                'mailable' => get_class($mailable),
            ]);
        } catch (\Exception $e) {
            // Email göndərmə uğursuz olduqda DomainException atırıq.
            // Bu exception yuxarı təbəqələrdə (Application layer) tutulub idarə oluna bilər.
            logger()->error('EMAIL GÖNDƏRMƏ UĞURSUZ', [
                'to'      => $to,
                'subject' => $subject,
                'error'   => $e->getMessage(),
            ]);

            throw new DomainException(
                "Email göndərilə bilmədi: {$e->getMessage()}"
            );
        }
    }

    /**
     * Subject (mövzu) və məlumatlara görə uyğun Mailable obyekti yaradır.
     *
     * Bu metod "Factory" pattern-ə bənzəyir — daxil olan məlumata görə
     * uyğun obyekt yaradıb qaytarır.
     *
     * @param string $subject Email mövzusu
     * @param string $to      Alıcı email
     * @param array  $data    Əlavə məlumatlar (JSON-dan decode edilmiş)
     *
     * @return \Illuminate\Mail\Mailable Uyğun Mailable obyekti
     */
    private function resolveMailable(string $subject, string $to, array $data): \Illuminate\Mail\Mailable
    {
        // str_contains — string-in içində axtarış aparır.
        // Mövzuya görə uyğun Mailable seçirik.

        // Sifariş təsdiqi emaili
        if (str_contains($subject, 'sifariş') || str_contains($subject, 'order')) {
            return new OrderConfirmationMail(
                orderId: $data['order_id'] ?? 0,
                userEmail: $to,
                totalAmount: (float) ($data['total_amount'] ?? 0),
                items: $data['items'] ?? [],
            );
        }

        // Ödəmə uğursuzluğu emaili (uğursuzluq əvvəl yoxlanılır, çünki "ödəmə" hər ikisində var)
        if (str_contains($subject, 'uğursuz') || str_contains($subject, 'failed')) {
            return new PaymentFailedMail(
                orderId: $data['order_id'] ?? 0,
                amount: (float) ($data['amount'] ?? 0),
                failureReason: $data['failure_reason'] ?? 'Naməlum səbəb',
            );
        }

        // Ödəmə qəbzi emaili
        if (str_contains($subject, 'ödəmə') || str_contains($subject, 'payment')) {
            return new PaymentReceiptMail(
                orderId: $data['order_id'] ?? 0,
                amount: (float) ($data['amount'] ?? 0),
                currency: $data['currency'] ?? 'AZN',
                transactionId: $data['transaction_id'] ?? '',
                paymentMethod: $data['payment_method'] ?? 'card',
            );
        }

        // Stok xəbərdarlığı emaili
        if (str_contains($subject, 'stok') || str_contains($subject, 'stock')) {
            return new LowStockAlertMail(
                productName: $data['product_name'] ?? 'Naməlum məhsul',
                currentStock: (int) ($data['current_stock'] ?? 0),
            );
        }

        // Heç bir şərt uyğun gəlmədikdə — ümumi sifariş təsdiqi göndəririk.
        // Real proyektdə burada GenericMail class-ı istifadə oluna bilər.
        return new OrderConfirmationMail(
            orderId: $data['order_id'] ?? 0,
            userEmail: $to,
            totalAmount: (float) ($data['total_amount'] ?? 0),
            items: $data['items'] ?? [],
        );
    }
}
