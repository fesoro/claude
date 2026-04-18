<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * AZ STOK BİLDİRİŞ EMAİLİ (Low Stock Alert Mailable)
 * =====================================================
 * Məhsulun stoku az olduqda adminə göndərilən xəbərdarlıq emaili.
 *
 * ShouldQueue İNTERFACE — ASİNXRON (NÖVBƏ İLƏ) EMAIL
 * ====================================================
 * Bu class ShouldQueue interface-ini implement edir.
 * Bu nə deməkdir?
 *
 * NORMAL Mailable (ShouldQueue OLMADAN):
 *   Mail::to($admin)->send(new SomeEmail());
 *   // Email DƏRHAL göndərilir. İstifadəçi gözləyir.
 *   // SMTP serverinə qoşulma 2-5 saniyə çəkə bilər.
 *   // Bu müddət ərzində istifadəçi "yüklənir..." görür.
 *
 * ShouldQueue İLƏ:
 *   Mail::to($admin)->send(new LowStockAlertMail(...));
 *   // send() çağırılsa BELƏ, email queue-yə düşür!
 *   // İstifadəçi DƏRHAL cavab alır (millisaniyələr).
 *   // Email arxa planda göndərilir (queue worker tərəfindən).
 *
 * Queue worker necə işləyir?
 *   Terminal: php artisan queue:work
 *   Bu əmr arxa planda işləyən prosesdir — queue-dəki tapşırıqları
 *   bir-bir götürüb icra edir (email göndərir, PDF yaradır və s.).
 *
 * ShouldQueue VS queue() metodu — FƏRQ:
 *   - queue() metodu → çağıran tərəf qərar verir (hər dəfə queue() yazmaq lazımdır).
 *   - ShouldQueue    → class özü qərar verir (həmişə asinxrondur, unutmaq mümkün deyil).
 *   Admin email-ləri üçün ShouldQueue daha yaxşıdır — heç vaxt istifadəçini gözlətməməlidir.
 *
 * QUEUE KONFİQURASİYASI:
 *   .env faylında: QUEUE_CONNECTION=redis (və ya database)
 *   Queue cədvəli yaratmaq: php artisan queue:table && php artisan migrate
 *   Worker başlatmaq: php artisan queue:work --tries=3
 *     --tries=3 → uğursuz tapşırıq 3 dəfə cəhd edilir, sonra failed_jobs cədvəlinə düşür.
 *
 * Queue-nin üstünlükləri:
 *   1) Sürət — istifadəçi gözləmir.
 *   2) Etibarlılıq — uğursuz email yenidən cəhd edilir (retry).
 *   3) Miqyas — çox email lazımdırsa, bir neçə worker işlədə bilərsiniz.
 *   4) Prioritet — vacib emaillər əvvəl göndərilə bilər (onQueue('high')).
 */
class LowStockAlertMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Queue-da hansı növbəyə düşəcək.
     * 'notifications' adlı xüsusi növbə — admin bildirişləri üçün ayrıca növbə.
     * Worker-i belə başlatmaq olar: php artisan queue:work --queue=notifications
     *
     * @var string|null
     */
    public ?string $queue = 'notifications';

    /**
     * Queue-da neçə dəfə cəhd ediləcək (uğursuz olsa).
     * 3 dəfə cəhd edilir — hər cəhddə email serveri ilə əlaqə qurulmağa çalışılır.
     * 3 cəhddən sonra uğursuz olarsa, failed_jobs cədvəlinə düşür.
     *
     * @var int
     */
    public int $tries = 3;

    /**
     * @param string $productName  Məhsulun adı (stoku az olan)
     * @param int    $currentStock Hazırkı stok miqdarı
     */
    public function __construct(
        public readonly string $productName,
        public readonly int $currentStock,
    ) {
    }

    /**
     * Emailin zərfi — admin üçün xəbərdarlıq mövzusu.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "XƏBƏRDARLIQ: \"{$this->productName}\" — stok azdır ({$this->currentStock} ədəd)",
        );
    }

    /**
     * Emailin məzmunu — admin xəbərdarlıq şablonu.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.low-stock-alert',
        );
    }

    /**
     * Əlavələr — xəbərdarlıq emailində əlavə olmur.
     */
    public function attachments(): array
    {
        return [];
    }
}
