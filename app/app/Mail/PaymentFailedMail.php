<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * ÖDƏMƏNİN UĞURSUZ OLMASI EMAİLİ (Payment Failed Mailable)
 * ============================================================
 * Ödəmə uğursuz olduqda müştəriyə göndərilən bildiriş emaili.
 *
 * Müştəriyə nəyin səhv getdiyini izah edir və yenidən cəhd etməyi təklif edir.
 * Bu email vacibdir — müştəri sifarişinin nə vəziyyətdə olduğunu bilməlidir.
 *
 * İstifadə nümunəsi:
 *   Mail::to($customer->email)->send(new PaymentFailedMail(
 *       orderId: 1001,
 *       amount: 250.00,
 *       failureReason: 'Kartda kifayət qədər vəsait yoxdur',
 *   ));
 */
class PaymentFailedMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param int    $orderId       Sifariş nömrəsi
     * @param float  $amount        Ödənilməyə çalışılan məbləğ
     * @param string $failureReason Uğursuzluğun səbəbi (bank/gateway-dən gələn mesaj)
     */
    public function __construct(
        public readonly int $orderId,
        public readonly float $amount,
        public readonly string $failureReason,
    ) {
    }

    /**
     * Emailin zərfi — mövzu daha ciddi tondadır (problem haqqında xəbər verir).
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Ödəmə uğursuz oldu — Sifariş #{$this->orderId}",
            replyTo: [
                'support@eshop.az',
            ],
        );
    }

    /**
     * Emailin məzmunu.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.payment-failed',
        );
    }

    /**
     * Əlavələr — ödəmə uğursuzluğu emailində əlavə olmur.
     */
    public function attachments(): array
    {
        return [];
    }
}
