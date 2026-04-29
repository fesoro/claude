<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;

/**
 * SİFARİŞ TƏSDİQ EMAİLİ (Order Confirmation Mailable)
 * ======================================================
 *
 * MAİLABLE NƏDİR VƏ NƏYƏ LAZIMDIR?
 * ====================================
 * Mailable — Laravel-in email göndərmək üçün xüsusi class-ıdır.
 * Hər bir Mailable bir "email şablonu" kimidir:
 *   - Kimə göndərilir? (envelope — zərf)
 *   - Nə yazılıb? (content — məzmun)
 *   - Nə əlavə olunub? (attachments — əlavələr, məsələn PDF)
 *
 * Real həyat analogiyası:
 *   Mailable = poçtla göndərilən məktubdur.
 *   Envelope = zərf (kimə, kimdən, mövzu yazılır).
 *   Content  = zərfin içindəki kağız (mətn, şəkillər).
 *   Attachment = zərfə əlavə edilən sənədlər (faktura PDF).
 *
 * NƏYƏ BİR CLASS LAZIMDIR? NƏYƏ BİRBAŞA GÖNDƏRƏ BİLMİRİK?
 * =============================================================
 * Əvvəllər email belə göndərilirdi (köhnə üsul):
 *   Mail::send('view', $data, function ($message) {
 *       $message->to('user@mail.com')->subject('Sifariş');
 *   });
 *
 * Bu qaydanın problemi — email məntiqi (subject, from, view) hər yerdə
 * səpələnirdi. Mailable class bütün email məntiqini BİR YERDƏ saxlayır.
 * Bu SOLID-in Single Responsibility prinsipidir: bir class = bir email növü.
 *
 * MAİLABLE VS NOTİFİCATİON — FƏRQ NƏDİR?
 * ==========================================
 * Mailable:
 *   - YALNIZ email göndərir.
 *   - Tam kontrol: HTML şablon, əlavələr (PDF), CC/BCC.
 *   - Mürəkkəb emaillər üçün idealdır (sifariş təsdiqi, faktura).
 *   - İstifadə: Mail::to($email)->send(new OrderConfirmationMail(...));
 *
 * Notification:
 *   - ÇOX KANALLI: email + SMS + database + Slack + push...
 *   - Bir bildirişi eyni anda bir neçə kanaldan göndərə bilər.
 *   - Sadə bildirişlər üçün (status dəyişikliyi, qısa xəbər).
 *   - İstifadə: $user->notify(new OrderStatusNotification($order));
 *
 * Qayda: Mürəkkəb email lazımdırsa → Mailable. Çox kanal lazımdırsa → Notification.
 * Notification da Mailable istifadə edə bilər: toMail() metodunda Mailable qaytarmaq olar.
 *
 * EMAİLİ NECƏ GÖNDƏRMƏLİ?
 * ==========================
 * Sinxron göndərmə (dərhal, istifadəçi gözləyir):
 *   Mail::to($email)->send(new OrderConfirmationMail($orderId, $email, $total, $items));
 *
 * Asinxron göndərmə (növbə/queue vasitəsilə, istifadəçi gözləmir):
 *   Mail::to($email)->queue(new OrderConfirmationMail($orderId, $email, $total, $items));
 *
 * queue() istifadə etdikdə email arxa planda göndərilir.
 * Bu daha sürətlidir — istifadəçi cavabı dərhal alır, email isə
 * queue worker tərəfindən göndərilir (php artisan queue:work).
 *
 * QUEUE İNTEQRASİYASI:
 * =====================
 * 1) queue() metodu — Mailable-ı queue-yə əlavə edir.
 *    Mailable class-da Queueable trait olmalıdır (artıq əlavə olunub).
 *
 * 2) ShouldQueue interface — class-ın özü həmişə queue ilə göndərilməsini təmin edir.
 *    Bu halda send() çağırsanız belə, avtomatik queue-yə düşür.
 *    Nümunə üçün LowStockAlertMail-a baxın.
 *
 * 3) Queue sürücüləri (.env faylında QUEUE_CONNECTION):
 *    - sync     → queue yoxdur, dərhal icra olunur (development üçün)
 *    - database → verilənlər bazasında saxlanılır
 *    - redis    → Redis ilə (production üçün ən yaxşı seçim)
 *    - sqs      → Amazon SQS (böyük layihələr üçün)
 */
class OrderConfirmationMail extends Mailable
{
    /**
     * Queueable — bu Mailable-ın queue (növbə) ilə göndərilməsini mümkün edir.
     * SerializesModels — Eloquent modellər queue-yə düşdükdə düzgün serializə olunur.
     *   (Məsələn, bütün model yerinə yalnız ID saxlanılır, sonra yenidən yüklənir.)
     */
    use Queueable, SerializesModels;

    /**
     * Konstruktor — emailin göndərilməsi üçün lazım olan məlumatları qəbul edir.
     *
     * PHP 8-in Constructor Promotion xüsusiyyəti istifadə olunur:
     *   public readonly int $orderId — həm parametr, həm property-dir.
     *   "readonly" — bir dəfə təyin olunur, sonra dəyişdirilə bilməz.
     *   "public" — Blade şablonunda $orderId kimi birbaşa istifadə oluna bilər.
     *
     * VACIB: Mailable-ın public property-ləri avtomatik Blade view-a ötürülür!
     *   Yəni $this->orderId yazmağa ehtiyac yoxdur — Laravel özü ötürür.
     *   Əgər property private olsa, content() metodunda with: [...] ilə ötürməlisiniz.
     *
     * @param string $orderId     Sifariş ID-si (UUID)
     * @param string $userEmail   Müştərinin email adresi
     * @param float  $totalAmount Ümumi məbləğ (məsələn: 150.50)
     * @param array  $items       Sifariş edilən məhsulların siyahısı
     *                            Hər element: ['name' => 'Laptop', 'quantity' => 1, 'price' => 999.99]
     */
    public function __construct(
        public readonly string $orderId,
        public readonly string $userEmail,
        public readonly float $totalAmount,
        public readonly array $items,
    ) {
        // Konstruktor avtomatik property-ləri təyin edir (PHP 8 promotion).
        // Əlavə kod yazmağa ehtiyac yoxdur.
    }

    /**
     * ENVELOPE (ZƏRF) — Emailin "üz" hissəsi.
     * ==========================================
     * Zərf — emailin mövzusu, kimdən göndərildiyi, cavab adresi.
     *
     * Bu real poçtdakı zərf kimidir:
     *   - subject  → Mövzu sətri (inbox-da görünən yazı)
     *   - from     → Göndərən (əks halda .env-dəki MAIL_FROM_ADDRESS istifadə olunur)
     *   - replyTo  → Cavab adresi (müştəri "Reply" basdıqda bu adresə gedər)
     *
     * .env faylındakı MAIL_FROM_ADDRESS və MAIL_FROM_NAME:
     *   MAIL_FROM_ADDRESS=noreply@eshop.az
     *   MAIL_FROM_NAME="E-Shop Azerbaijan"
     * Bu dəyərlər from() təyin etmədikdə istifadə olunur.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            // subject — emailin mövzusu. Inbox-da bu yazı görünəcək.
            subject: "Sifariş #{$this->orderId} — Təsdiq edildi",

            // from — göndərən adresi. Təyin etməsəniz, .env-dən götürülür.
            // from: new Address('noreply@eshop.az', 'E-Shop Azerbaijan'),

            // replyTo — müştəri "Cavabla" basdıqda bu adresə yazacaq.
            // Adətən dəstək (support) emaili olur.
            replyTo: [
                'support@eshop.az',
            ],

            // Digər seçimlər:
            // cc  — kopyası göndərilənlər (Carbon Copy, hamı görür)
            // bcc — gizli kopyası göndərilənlər (heç kim görməz)
            // tags — email provayderləri üçün teqlər (Mailgun, Postmark)
            //   tags: ['order', 'confirmation'],
            // metadata — email ilə bağlı əlavə məlumat
            //   metadata: ['order_id' => $this->orderId],
        );
    }

    /**
     * CONTENT (MƏZMUN) — Emailin "gövdə" hissəsi.
     * ===============================================
     * Content — hansı Blade şablonunun istifadə ediləcəyini təyin edir.
     *
     * Blade view yolu:
     *   'emails.order-confirmation' → resources/views/emails/order-confirmation.blade.php
     *   Nöqtə (.) = qovluq ayırıcısı (/).
     *
     * View növləri:
     *   - view → HTML email (əsas, gözəl dizaynlı)
     *   - text → Düz mətn (plain text) versiyası. Bəzi email klientləri
     *            HTML göstərə bilmir — onlar üçün text versiya lazımdır.
     *   - markdown → Laravel-in hazır email komponentlərini istifadə edir.
     *               Markdown yazmaq asandır, Laravel onu gözəl HTML-ə çevirir.
     *
     * "with" parametri — əlavə dəyişənləri view-a ötürür.
     * Public property-lər avtomatik ötürülür, amma private/protected üçün
     * with istifadə etmək lazımdır.
     */
    public function content(): Content
    {
        return new Content(
            // Əsas HTML şablon (resources/views/emails/order-confirmation.blade.php)
            view: 'emails.order-confirmation',

            // with — Blade-ə əlavə dəyişənlər ötürmək üçün.
            // Public property-lər artıq avtomatik ötürülür ($orderId, $items və s.),
            // amma əlavə hesablanmış dəyərlər lazımdırsa burada göndərə bilərsiniz:
            with: [
                'orderDate' => now()->format('d.m.Y H:i'),
            ],

            // Markdown email istifadə etmək istəsəniz:
            // markdown: 'emails.order-confirmation-markdown',
        );
    }

    /**
     * ATTACHMENTS (ƏLAVƏLƏR) — Emailə fayl əlavə etmək.
     * ====================================================
     * Bu metod emailə PDF, şəkil, və digər faylları əlavə edir.
     *
     * Real nümunə — PDF faktura əlavə etmək:
     *
     *   return [
     *       // 1) Serverdəki fayldan əlavə etmək:
     *       Attachment::fromPath(storage_path("invoices/order-{$this->orderId}.pdf"))
     *           ->as("faktura-{$this->orderId}.pdf")    // Faylın adı (email-də görünən)
     *           ->withMime('application/pdf'),           // MIME tipi
     *
     *       // 2) Yaddaşdakı (string) məlumatdan əlavə etmək:
     *       Attachment::fromData(
     *           fn () => $this->generatePdfContent(),    // PDF-in byte məzmunu
     *           "faktura-{$this->orderId}.pdf"           // Fayl adı
     *       )->withMime('application/pdf'),
     *
     *       // 3) Storage diskindən (S3, local) əlavə etmək:
     *       Attachment::fromStorage("invoices/order-{$this->orderId}.pdf")
     *           ->as('faktura.pdf')
     *           ->withMime('application/pdf'),
     *
     *       // 4) Storageable obyektdən (məsələn, Eloquent model):
     *       Attachment::fromStorageDisk('s3', "invoices/{$this->orderId}.pdf"),
     *   ];
     *
     * @return array<int, Attachment> Əlavələrin siyahısı (boş array = əlavə yoxdur)
     */
    public function attachments(): array
    {
        // Hələlik əlavə yoxdur.
        // Real proyektdə burada PDF faktura əlavə edə bilərsiniz (yuxarıdakı nümunələrə baxın).
        return [];
    }
}
