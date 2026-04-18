<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * ÖDƏMƏ QƏBZ EMAİLİ (Payment Receipt Mailable)
 * ================================================
 * Müştəri ödəmə etdikdən sonra göndərilən təsdiq emaili.
 *
 * Bu Mailable sinxron işləyir — yəni send() çağrıldıqda dərhal göndərilir.
 * Asinxron göndərmək istəsəniz, çağırarkən queue() istifadə edin:
 *   Mail::to($email)->queue(new PaymentReceiptMail(...));
 *
 * Və ya class-a ShouldQueue interface əlavə edin (LowStockAlertMail nümunəsinə baxın).
 */
class PaymentReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Ödəmə məlumatlarını qəbul edir.
     *
     * @param int    $orderId       Sifariş nömrəsi
     * @param float  $amount        Ödənilən məbləğ
     * @param string $currency      Valyuta kodu (AZN, USD, EUR)
     * @param string $transactionId Ödəmə əməliyyatının unikal ID-si (bank/gateway tərəfindən verilir)
     * @param string $paymentMethod Ödəmə üsulu (card, bank_transfer, cash_on_delivery)
     */
    public function __construct(
        public readonly int $orderId,
        public readonly float $amount,
        public readonly string $currency,
        public readonly string $transactionId,
        public readonly string $paymentMethod,
    ) {
    }

    /**
     * Emailin zərfi — mövzu və cavab adresi.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Ödəmə qəbzi — Sifariş #{$this->orderId}",
            replyTo: [
                'support@eshop.az',
            ],
        );
    }

    /**
     * Emailin məzmunu — Blade şablonu.
     * Bütün public property-lər avtomatik şablona ötürülür.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.payment-receipt',
            with: [
                'paymentDate' => now()->format('d.m.Y H:i'),
                // Ödəmə üsulunun Azərbaycan dilində adı
                'paymentMethodLabel' => match ($this->paymentMethod) {
                    'card'             => 'Bank kartı',
                    'bank_transfer'    => 'Bank köçürməsi',
                    'cash_on_delivery' => 'Qapıda ödəmə',
                    default            => $this->paymentMethod,
                },
            ],
        );
    }

    /**
     * Əlavələr — hələlik boşdur.
     * Real proyektdə burada ödəmə qəbzinin PDF versiyası əlavə oluna bilər.
     */
    public function attachments(): array
    {
        return [];
    }
}
