# Email Delivery Deep Dive (Middle)

## İcmal
Laravel-in Mail sistemi email göndərməni Mailable class-lar vasitəsilə abstrakt edir. Sender konfiqurasiyasından (SMTP, Mailgun, SES, Postmark) şablonlara, queue-ya, attachments-a, testlərə qədər — bu mövzu real layihədə lazımlı olan hər şeyi əhatə edir.

## Niyə Vacibdir
Demək olar ki, hər layihədə xoş gəlmisiniz maili, şifrə sıfırlama, faktura, notification məktubu var. Email delivery düzgün qurulmasa: spam-a düşür, delivery rate aşağı olur, testlər mümkün olmur, xətalarda debug çətinləşir.

## Əsas Anlayışlar

### Mailable Class
```php
php artisan make:mail InvoicePaid

class InvoicePaid extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Invoice $invoice) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('billing@example.com', 'Example Billing'),
            replyTo: [new Address('support@example.com', 'Support')],
            subject: 'Invoice #' . $this->invoice->id . ' Ödənildi',
            tags: ['invoice', 'paid'],          // Mailgun/SES tag-ləri
            metadata: ['invoice_id' => $this->invoice->id],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.invoices.paid',
            // Ya da markdown:
            // markdown: 'emails.invoices.paid-markdown',
            with: ['invoiceUrl' => route('invoices.show', $this->invoice)],
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromPath(storage_path('invoices/' . $this->invoice->id . '.pdf'))
                ->as('faktura.pdf')
                ->withMime('application/pdf'),
            
            // Storage disk-dən
            Attachment::fromStorage('invoices/1.pdf')
                ->as('invoice.pdf'),
            
            // Raw data-dan
            Attachment::fromData(fn() => $this->invoice->toPdf(), 'invoice.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
```

### Göndərmə
```php
// Dərhal göndər
Mail::to($user)->send(new InvoicePaid($invoice));

// Queue-da göndər (ShouldQueue implement etsə belə, açıq göstərmək üçün)
Mail::to($user)->queue(new InvoicePaid($invoice));

// Gecikmə ilə
Mail::to($user)->later(now()->addMinutes(5), new InvoicePaid($invoice));

// Çoxlu alıcı
Mail::to($user)
    ->cc($manager)
    ->bcc('archive@company.com')
    ->send(new InvoicePaid($invoice));

// Email array ilə
Mail::to([
    ['address' => 'user@example.com', 'name' => 'User'],
    'admin@example.com',
])->send(new InvoicePaid($invoice));
```

## Şablonlar

### Blade Template
```html
<!-- resources/views/emails/invoices/paid.blade.php -->
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>/* inline CSS — email client uyğunluğu */ </style>
</head>
<body>
    <h1>Invoice #{{ $invoice->id }} Ödənildi</h1>
    <p>Məbləğ: {{ number_format($invoice->amount / 100, 2) }} AZN</p>
    <a href="{{ $invoiceUrl }}">Invoice-ə Bax</a>
</body>
</html>
```

### Markdown Mailable
Laravel-in built-in markdown mail komponentləri:
```php
// resources/views/emails/invoices/paid-markdown.blade.php
@component('mail::message')
# Invoice Ödənildi

Invoice #{{ $invoice->id }} uğurla ödənildi.

@component('mail::button', ['url' => $invoiceUrl, 'color' => 'green'])
Invoice-ə Bax
@endcomponent

@component('mail::panel')
Faktura bu məktuba əlavə edilib.
@endcomponent

Hörmətlə,
{{ config('app.name') }}
@endcomponent
```
```bash
# Markdown komponentlərini publish et (custom branding)
php artisan vendor:publish --tag=laravel-mail
# resources/views/vendor/mail/html/ altında edit olunur
```

## Konfiqurasiya

### Mailer Sürücüləri
```env
# .env
MAIL_MAILER=smtp

# SMTP
MAIL_HOST=smtp.mailgun.org
MAIL_PORT=587
MAIL_USERNAME=postmaster@mg.example.com
MAIL_PASSWORD=secret
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=hello@example.com
MAIL_FROM_NAME="Example App"

# Mailgun (API)
MAIL_MAILER=mailgun
MAILGUN_DOMAIN=mg.example.com
MAILGUN_SECRET=key-xxx

# SES
MAIL_MAILER=ses
AWS_ACCESS_KEY_ID=xxx
AWS_SECRET_ACCESS_KEY=xxx
AWS_DEFAULT_REGION=us-east-1

# Postmark
MAIL_MAILER=postmark
POSTMARK_TOKEN=xxx
```

### Çoxlu Mailer
```php
// config/mail.php
'mailers' => [
    'smtp'     => [...],
    'postmark' => [...],
    'log'      => ['transport' => 'log'],
    'array'    => ['transport' => 'array'],
],

// Kod-da
Mail::mailer('postmark')->to($user)->send(new InvoicePaid($invoice));
```

## Local Testing

### Mailpit (ən rahat yol)
```yaml
# docker-compose.yml
services:
  mailpit:
    image: axllent/mailpit
    ports:
      - "8025:8025"   # Web UI
      - "1025:1025"   # SMTP
```
```env
MAIL_HOST=localhost
MAIL_PORT=1025
```
Bütün mail-lər `localhost:8025`-də görünür, real göndərilmir.

### Log Driver
```env
MAIL_MAILER=log
```
`storage/logs/laravel.log`-a yazır.

### Array Driver (Testlər üçün)
```php
// phpunit.xml
<env name="MAIL_MAILER" value="array"/>
```

## Email Testləri

### Mail::fake()
```php
use Illuminate\Support\Facades\Mail;

it('sends invoice paid email', function () {
    Mail::fake();
    
    $invoice = Invoice::factory()->create();
    $user    = User::factory()->create();
    
    app(InvoiceService::class)->markAsPaid($invoice, $user);
    
    Mail::assertSent(InvoicePaid::class);
    Mail::assertSent(InvoicePaid::class, 1);  // tam bir dəfə
    Mail::assertSent(InvoicePaid::class, function (InvoicePaid $mail) use ($user, $invoice) {
        return $mail->hasTo($user->email)
            && $mail->invoice->is($invoice);
    });
    Mail::assertNotSent(WelcomeMail::class);
    Mail::assertQueued(MonthlyReport::class);
    Mail::assertNothingOutgoing();
});
```

## Deliverability

### SPF, DKIM, DMARC
Email spam-a düşməməsi üçün DNS konfiqurasiyası:

```
# SPF — hansı serverlər sizin adınıza mail göndərə bilər
TXT @ "v=spf1 include:mailgun.org include:amazonses.com ~all"

# DKIM — mail imzalanır (Mailgun avtomatik qurur)
TXT mg._domainkey "v=DKIM1; k=rsa; p=MIGfMA0..."

# DMARC — SPF/DKIM fail olduqda nə edilsin
TXT _dmarc "v=DMARC1; p=quarantine; rua=mailto:dmarc@example.com"
```

### Deliverability Tips
- `FROM` address-də subdomain istifadə et: `noreply@mail.example.com`
- Unsubscribe link həmişə olsun — CAN-SPAM tələbi
- HTML + plain text hər ikisini göndər
- List-Unsubscribe header əlavə et
- Bounce / complaint handler qur (Mailgun/SES webhook-ları)
- Email warm-up: yeni domain-dən yavaş-yavaş həcm artır

### Bounce Handling
```php
// Mailgun webhook handler
Route::post('/webhooks/mailgun', function (Request $request) {
    $event = $request->input('event-data');
    
    if ($event['event'] === 'permanent_fail') {
        User::where('email', $event['recipient'])
            ->update(['email_bounced' => true]);
    }
    if ($event['event'] === 'complained') {  // spam şikayəti
        User::where('email', $event['recipient'])
            ->update(['email_unsubscribed' => true]);
    }
});
```

### Trade-off-lar
- **SMTP vs API (Mailgun/SES)**: API daha sürətli, daha yaxşı bounce handling, daha az konfiqurasiya. SMTP vendor-dən asılısız amma daha yavaş.
- **ShouldQueue**: Hər zaman queue et — request cycle-ını bloklamasın, retry mümkün olsun.
- **Mailable vs Notification mail channel**: Birbaşa Mailable — mail-specific məntiqi; Notification mail channel — çox kanallı bildiriş sisteminin parçasıdırsa.

### Common Mistakes
- Queue olmadan loop içərisində yüzlərlə mail göndərmək
- `Mail::fake()` istifadə etmədən testdə real mail göndərmək
- DKIM/SPF qurmadaq production-a çıxmaq → spam
- HTML email-i email client-lərdə test etməmək (Litmus / Email on Acid)

## Praktik Tapşırıqlar

1. `WelcomeEmail` Mailable yaz: markdown template, attachment ilə PDF xoş gəlmisiniz məktubu
2. Queue ilə gündəlik digest maili: users cədvəlindən aktiv istifadəçiləri tapıb `daily()` scheduler ilə göndər
3. `Mail::fake()` ilə tam test suite yaz: göndərildi, alıcı doğru, attachment var
4. Mailpit-i Docker Compose-a əlavə et, local-da bütün mail-ləri orada gör
5. Mailgun webhook handler yaz: bounce olan email-lər DB-dəki `email_verified=false`-a çəkilsin

## Əlaqəli Mövzular
- [Laravel Notifications](201-laravel-notifications-channels.md)
- [Queues & Jobs](057-queues.md)
- [Laravel Horizon](058-laravel-horizon-queue.md)
- [Security Best Practices](067-security-best-practices.md)
