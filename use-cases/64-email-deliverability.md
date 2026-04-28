# Email Deliverability və Bounce Handling (Senior)

## Problem Təsviri

Transactional email-lər (password reset, invoice, order confirmation) göndərmək texniki cəhətdən sadə görünür — `Mail::send()` çağırırsan, bitdi. Amma real production mühitdə bu yanaşma böyük problemlərə gətirib çıxarır:

1. **Email-lər spam qovluğuna düşür** — SPF, DKIM, DMARC konfiqurasiya olunmayıb; mail server-lər bu domain-ə etibar etmir
2. **Bounce handling yoxdur** — artıq mövcud olmayan email ünvanlarına göndərmək davam edir; bounce rate artar; Gmail/Outlook domain-i blacklist-ə alır
3. **Unsubscribe mexanizmi yoxdur** — istifadəçi "spam" klikləyir; spam complaint rate artır; bütün göndərmələr bloklanır
4. **IP reputasiyası qurulmayıb** — yeni IP-dən kütləvi email göndərmək = spam kimi qiymətləndirilmək

```
Düzgün olmayan yanaşma:
App → SMTP → Mail server → Recipient

Problem:
Mail server: "Bu domain-in SPF record-u yoxdur, DKIM yoxdur, IP-dən əvvəl spam görmüşük"
Nəticə:    → Spam qovluğu
            → Reject
            → Bounce → Reputasiya düşür → Bütün email-lər bloklanır
```

### Problem niyə yaranır?

Mail server-lər (Gmail, Outlook, Yahoo) gələn hər email-i bir neçə kriteriyaya görə qiymətləndirir. SPF record yoxdursa — "bu domain bu IP-dən email göndərmə icazəsi verib?" sualına cavab yoxdur. DKIM yoxdursa — email-in məzmunu yolda dəyişdirilib-dəyişdirilmədiyini yoxlamaq mümkün olmur. DMARC yoxdursa — domain-dən email göndərənin özü olduğunu təsdiqləyən siyasət yoxdur.

Bounce handling tətbiq olunmayıbsa, artıq mövcud olmayan ünvanlara göndərmək davam edir. Hard bounce rate 2%-i keçdikdə, böyük mail provider-lər bütün göndərmələri throttle edir və ya bloklayır. Spam complaint rate 0.1%-i keçdikdə isə domain permanent block-a düşə bilər.

---

## DNS Authentication Setup

### SPF Record (Sender Policy Framework)

SPF, domain-in adından email göndərməyə icazəli IP-lərin siyahısını DNS-də elan edir. Mail server gələn email-i alanda göndərənin IP-sini SPF record ilə müqayisə edir.

DNS TXT record olaraq əlavə olunur:

```
v=spf1 include:amazonses.com include:sendgrid.net ~all
```

**Hissələrin mənası:**
- `v=spf1` — SPF versiyası
- `include:amazonses.com` — Amazon SES IP-lərinə icazə ver
- `include:sendgrid.net` — SendGrid IP-lərinə icazə ver
- `~all` — Siyahıda olmayan IP-lərdən gələni "soft fail" kimi qeydə al (`-all` olsa "hard fail" — reject edir)

**DNS-ə əlavə etmək:**
```
Type:    TXT
Name:    @  (və ya yourdomain.com)
Value:   v=spf1 include:amazonses.com ~all
TTL:     3600
```

**Yoxlama:**
```bash
dig TXT yourdomain.com | grep spf
# və ya
nslookup -type=TXT yourdomain.com
```

> **Qeyd:** Bir domain üçün yalnız **bir SPF record** ola bilər. Birdən çox TXT record əlavə etsən, SPF validation fail olur. Bütün provider-ləri eyni record-a `include:` ilə əlavə et.

---

### DKIM (DomainKeys Identified Mail)

DKIM email header-larına kriptografik imza əlavə edir. Göndərən server private key ilə imzalayır; alıcı server DNS-dəki public key ilə doğrulayır. Email yolda dəyişdirilibsə, imza keçmir.

**AWS SES üçün DKIM aktivləşdirmək:**

```
AWS Console → SES → Verified Identities → yourdomain.com
→ DKIM → Enable → "Easy DKIM" seç → RSA 2048 bit
```

SES 3 ədəd CNAME record verir, DNS-ə əlavə et:

```
Name:    abc123._domainkey.yourdomain.com
Type:    CNAME
Value:   abc123.dkim.amazonses.com

Name:    def456._domainkey.yourdomain.com
Type:    CNAME
Value:   def456.dkim.amazonses.com

Name:    ghi789._domainkey.yourdomain.com
Type:    CNAME
Value:   ghi789.dkim.amazonses.com
```

**Yoxlama:**
```bash
# SES console-da "DKIM status: Verified" görünməlidir
# Və ya:
dig CNAME abc123._domainkey.yourdomain.com
```

---

### DMARC (Domain-based Message Authentication, Reporting & Conformance)

DMARC, SPF və DKIM-in nəticəsinə əsaslanaraq mail server-in nə etməli olduğunu bildirir. Həmçinin domain sahibinə hesabat göndərilir.

```
v=DMARC1; p=quarantine; rua=mailto:dmarc@yourdomain.com; pct=100
```

**DNS-ə əlavə etmək:**
```
Type:    TXT
Name:    _dmarc.yourdomain.com
Value:   v=DMARC1; p=quarantine; rua=mailto:dmarc@yourdomain.com; pct=100
```

**Policy-nin mənası:**
- `p=none` — yalnız monitor et, heç nə etmə (ilk mərhələ)
- `p=quarantine` — uyğun gəlməyən email-i spam-ə göndər
- `p=reject` — uyğun gəlməyən email-i tam rədd et (ən güclü qoruma)

**Tədricən tətbiq:**
```
Həftə 1:  p=none;   pct=100  → izlə, heç nə bloklamır
Həftə 3:  p=quarantine; pct=10  → 10% üçün quarantine
Həftə 5:  p=quarantine; pct=100 → tam quarantine
Həftə 8:  p=reject; pct=100   → tam reject (hədəf)
```

`rua` ünvanına gündəlik XML hesabatlar gəlir — kimlərin domain-in adından email göndərdiyini görürsən. Pullu alternativ: [dmarcian.com](https://dmarcian.com) bu XML-ləri oxunaqlı formata çevirir.

---

## Laravel + AWS SES Konfiqurasiyası

**`.env`:**
```
MAIL_MAILER=ses
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="Your App"

AWS_ACCESS_KEY_ID=AKIA...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=eu-west-1
```

**`config/services.php`:**
```php
'ses' => [
    'key'    => env('AWS_ACCESS_KEY_ID'),
    'secret' => env('AWS_SECRET_ACCESS_KEY'),
    'region' => env('AWS_DEFAULT_REGION', 'eu-west-1'),
    'options' => [
        // SES-ə öz configuration set-ini göndər
        'ConfigurationSetName' => env('SES_CONFIGURATION_SET', 'my-app-prod'),
    ],
],
```

**`config/mail.php`:**
```php
'mailers' => [
    'ses' => [
        'transport' => 'ses',
    ],
],
```

**Composer:**
```bash
composer require aws/aws-sdk-php
```

**SES Configuration Set** yaratmaq — bounce/complaint notification-larını SNS-ə yönləndirməyə imkan verir:

```
AWS Console → SES → Configuration sets → Create
→ Add destination → SNS → Event types: Bounces, Complaints
→ SNS topic: arn:aws:sns:eu-west-1:...:ses-notifications
```

---

## Bounce Handling — Tam Implementation

### Arxitektura

```
Email göndər
     ↓
SES → Bounce/Complaint baş verdi
     ↓
SES → SNS Topic-ə notification
     ↓
SNS → HTTP POST → Laravel webhook endpoint
     ↓
Webhook → Suppression list-ə əlavə et
     ↓
Növbəti email göndərmədən əvvəl → Suppression yoxla → Skip
```

---

### Migration: Suppression List

*Bu migration email_suppressions cədvəlini yaradır — bounce, complaint və unsubscribe olmuş ünvanları saxlayır:*

```php
// database/migrations/2024_01_01_create_email_suppressions_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_suppressions', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();
            $table->enum('type', [
                'hard_bounce',   // Ünvan mövcud deyil — heç vaxt göndərmə
                'soft_bounce',   // Müvəqqəti problem — məhdud cəhd
                'complaint',     // "Spam" kliklədi — marketing göndərmə
                'unsubscribe',   // Özü çıxdı — növ əsasında qadağa
            ]);
            $table->string('email_type')->nullable(); // 'marketing', 'transactional', null = hamısı
            $table->unsignedTinyInteger('bounce_count')->default(0);
            $table->json('raw_payload')->nullable();
            $table->timestamp('suppressed_at')->useCurrent();
            $table->timestamps();

            // Eyni email üçün eyni tip ikinci dəfə əlavə olunmasın
            $table->unique(['email', 'type', 'email_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_suppressions');
    }
};
```

---

### Model

*EmailSuppression model-i — email-in suppressed olub-olmadığını yoxlayan scope-ları ehtiva edir:*

```php
// app/Models/EmailSuppression.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class EmailSuppression extends Model
{
    protected $fillable = [
        'email',
        'type',
        'email_type',
        'bounce_count',
        'raw_payload',
        'suppressed_at',
    ];

    protected $casts = [
        'raw_payload' => 'array',
        'suppressed_at' => 'datetime',
    ];

    /**
     * Bu email ünvanı üçün hər hansı suppression var mı?
     */
    public static function isSuppressed(string $email, string $emailType = null): bool
    {
        return static::where('email', strtolower($email))
            ->where(function (Builder $q) use ($emailType) {
                // Hard bounce və complaint — hər növ email üçün bloklayır
                $q->whereIn('type', ['hard_bounce', 'complaint'])
                  ->orWhere(function (Builder $q2) use ($emailType) {
                      // Unsubscribe — yalnız həmin email növü üçün
                      $q2->where('type', 'unsubscribe')
                         ->where(function (Builder $q3) use ($emailType) {
                             $q3->whereNull('email_type')
                                ->orWhere('email_type', $emailType);
                         });
                  });
            })
            ->exists();
    }

    /**
     * Soft bounce — 3 dəfədən çox cəhd olubsa suppress et.
     */
    public static function shouldSuppressSoftBounce(string $email): bool
    {
        $record = static::where('email', strtolower($email))
            ->where('type', 'soft_bounce')
            ->first();

        return $record && $record->bounce_count >= 3;
    }
}
```

---

### SES Webhook Controller

*Bu controller AWS SNS-dən gələn bounce və complaint notification-larını qəbul edib suppression list-ə əlavə edir:*

```php
// app/Http/Controllers/SesWebhookController.php
namespace App\Http\Controllers;

use App\Models\EmailSuppression;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SesWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);

        if (!$payload || !isset($payload['Type'])) {
            return response()->json(['error' => 'Invalid payload'], 400);
        }

        // 1. SNS subscription confirmation — ilk qeydiyyatda bir dəfə gəlir
        if ($payload['Type'] === 'SubscriptionConfirmation') {
            Http::get($payload['SubscribeURL']);
            Log::info('SNS subscription confirmed');
            return response()->json(['status' => 'confirmed']);
        }

        // 2. Notification — bounce və ya complaint
        if ($payload['Type'] === 'Notification') {
            $message = json_decode($payload['Message'], true);

            match ($message['notificationType'] ?? null) {
                'Bounce'    => $this->handleBounce($message['bounce'], $payload),
                'Complaint' => $this->handleComplaint($message['complaint'], $payload),
                default     => Log::info('SES: Unknown notification type', ['type' => $message['notificationType'] ?? 'none']),
            };
        }

        return response()->json(['status' => 'processed']);
    }

    /**
     * Bounce handling:
     * - Hard bounce: ünvan mövcud deyil → həmişəlik suppress
     * - Soft bounce: müvəqqəti problem (mailbox full, server down) → 3 cəhddən sonra suppress
     */
    private function handleBounce(array $bounce, array $rawPayload): void
    {
        $bounceType = $bounce['bounceType']; // 'Permanent' | 'Transient'

        foreach ($bounce['bouncedRecipients'] as $recipient) {
            $email = strtolower($recipient['emailAddress']);

            Log::warning('SES bounce received', [
                'email'      => $email,
                'type'       => $bounceType,
                'subtype'    => $bounce['bounceSubType'] ?? null,
                'action'     => $recipient['action'] ?? null,
                'status'     => $recipient['status'] ?? null,
            ]);

            if ($bounceType === 'Permanent') {
                // Hard bounce — bu ünvan mövcud deyil, bir daha göndərmə
                EmailSuppression::updateOrCreate(
                    ['email' => $email, 'type' => 'hard_bounce', 'email_type' => null],
                    [
                        'raw_payload'   => $rawPayload,
                        'suppressed_at' => now(),
                    ]
                );
            } else {
                // Soft bounce — sayğacı artır, hədd keçibsə suppress et
                $record = EmailSuppression::firstOrCreate(
                    ['email' => $email, 'type' => 'soft_bounce', 'email_type' => null],
                    ['bounce_count' => 0, 'raw_payload' => $rawPayload, 'suppressed_at' => now()]
                );

                $record->increment('bounce_count');

                if ($record->bounce_count >= 3) {
                    Log::warning('SES: Soft bounce threshold reached, suppressing', ['email' => $email]);
                }
            }
        }
    }

    /**
     * Complaint handling:
     * Recipient "spam" kliklədi → marketing email-lər dayanmalıdır.
     * Transactional email-lər (password reset) isə göndərilə bilər.
     */
    private function handleComplaint(array $complaint, array $rawPayload): void
    {
        foreach ($complaint['complainedRecipients'] as $recipient) {
            $email = strtolower($recipient['emailAddress']);

            Log::warning('SES complaint received', ['email' => $email]);

            EmailSuppression::updateOrCreate(
                ['email' => $email, 'type' => 'complaint', 'email_type' => 'marketing'],
                [
                    'raw_payload'   => $rawPayload,
                    'suppressed_at' => now(),
                ]
            );
        }
    }
}
```

**Route qeydiyyatı:**

```php
// routes/api.php
use App\Http\Controllers\SesWebhookController;

// CSRF middleware-dən çıxartmaq lazımdır — SNS CSRF token göndərmir
Route::post('/webhooks/ses', [SesWebhookController::class, 'handle'])
    ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
```

**Laravel 11+ üçün CSRF exclusion:**

```php
// bootstrap/app.php
->withMiddleware(function (Middleware $middleware) {
    $middleware->validateCsrfTokens(except: [
        'api/webhooks/ses',
    ]);
})
```

---

### Suppression Yoxlaması — Mailable Listener

*Bu listener hər Mailable göndərilməzdən əvvəl suppression list-i yoxlayır:*

```php
// app/Listeners/CheckEmailSuppressionListener.php
namespace App\Listeners;

use App\Models\EmailSuppression;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Log;

class CheckEmailSuppressionListener
{
    public function handle(MessageSending $event): bool|null
    {
        $recipients = array_keys($event->message->getTo() ?? []);

        foreach ($recipients as $email) {
            // Email növünü Mailable-dan oxu (əgər varsa)
            $emailType = $event->data['emailType'] ?? null;

            if (EmailSuppression::isSuppressed($email, $emailType)) {
                Log::info('Email sending skipped — suppressed', [
                    'email'      => $email,
                    'email_type' => $emailType,
                ]);

                // false qaytarmaq göndərməni dayandırır
                return false;
            }

            if (EmailSuppression::shouldSuppressSoftBounce($email)) {
                Log::info('Email sending skipped — soft bounce threshold', ['email' => $email]);
                return false;
            }
        }

        return null; // null = göndər
    }
}
```

**Event Service Provider-də qeydiyyat:**

```php
// app/Providers/EventServiceProvider.php
use Illuminate\Mail\Events\MessageSending;
use App\Listeners\CheckEmailSuppressionListener;

protected $listen = [
    MessageSending::class => [
        CheckEmailSuppressionListener::class,
    ],
];
```

**Laravel 11+ üçün `bootstrap/app.php`-da:**

```php
->withEvents(function (Discover $events) {
    $events->in(app_path('Listeners'));
})
```

---

### Mailable-da Email Növü Bildirmək

*Bu Mailable marketing email növünü data kimi listener-ə ötürür ki, suppression düzgün yoxlanılsın:*

```php
// app/Mail/WeeklyNewsletterMail.php
namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class WeeklyNewsletterMail extends Mailable
{
    /**
     * Email-in növü — suppression listener bu məlumatı oxuyur.
     */
    public string $emailType = 'marketing';

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Həftəlik Xülasə',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.weekly-newsletter');
    }

    /**
     * Serialize olunmuş data listener-ə $event->data kimi çatır.
     */
    public function buildViewData(): array
    {
        return array_merge(parent::buildViewData(), [
            'emailType' => $this->emailType,
        ]);
    }
}
```

---

## Unsubscribe Flow

### Signed Unsubscribe URL

*Bu kod Laravel signed URL ilə unsubscribe link-i yaradan Mailable-ı göstərir:*

```php
// app/Mail/PromotionalMail.php
namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\URL;

class PromotionalMail extends Mailable
{
    public string $emailType = 'marketing';

    public function __construct(
        private User $user
    ) {}

    public function envelope(): Envelope
    {
        // RFC 8058 — one-click unsubscribe header (Gmail, Outlook dəstəkləyir)
        return new Envelope(
            subject: 'Xüsusi Təklif',
            using: [
                function ($message) {
                    $unsubscribeUrl = URL::signedRoute('unsubscribe', [
                        'user' => $this->user->id,
                        'type' => 'marketing',
                    ]);

                    // One-click unsubscribe header — mail client-lər bu link-i göstərir
                    $message->getHeaders()->addTextHeader(
                        'List-Unsubscribe',
                        "<{$unsubscribeUrl}>"
                    );
                    $message->getHeaders()->addTextHeader(
                        'List-Unsubscribe-Post',
                        'List-Unsubscribe=One-Click'
                    );
                },
            ]
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.promotional',
            with: [
                'unsubscribeUrl' => URL::signedRoute('unsubscribe', [
                    'user' => $this->user->id,
                    'type' => 'marketing',
                ]),
                'user' => $this->user,
            ]
        );
    }
}
```

### Unsubscribe Controller

*Bu controller signed URL-i doğrulayır və istifadəçini suppression list-ə əlavə edir:*

```php
// app/Http/Controllers/UnsubscribeController.php
namespace App\Http\Controllers;

use App\Models\EmailSuppression;
use App\Models\User;
use Illuminate\Http\Request;

class UnsubscribeController extends Controller
{
    /**
     * Signed URL ilə gəlir — URL dəyişdirilibsə 403 qaytarır.
     */
    public function unsubscribe(Request $request, User $user): \Illuminate\View\View
    {
        // Signed URL doğrulaması
        if (!$request->hasValidSignature()) {
            abort(403, 'Bu link etibarsızdır və ya müddəti bitib.');
        }

        $type = $request->query('type', 'marketing'); // 'marketing' | 'all'

        EmailSuppression::updateOrCreate(
            [
                'email'      => strtolower($user->email),
                'type'       => 'unsubscribe',
                'email_type' => $type === 'all' ? null : $type,
            ],
            ['suppressed_at' => now()]
        );

        return view('unsubscribe.success', [
            'user' => $user,
            'type' => $type,
        ]);
    }

    /**
     * One-click unsubscribe — RFC 8058 (POST sorğusu)
     * Gmail "Abunəliyi ləğv et" düyməsi bu endpoint-i POST ilə çağırır.
     */
    public function oneClick(Request $request, User $user): \Illuminate\Http\JsonResponse
    {
        if (!$request->hasValidSignature()) {
            abort(403);
        }

        EmailSuppression::updateOrCreate(
            ['email' => strtolower($user->email), 'type' => 'unsubscribe', 'email_type' => 'marketing'],
            ['suppressed_at' => now()]
        );

        return response()->json(['status' => 'unsubscribed']);
    }
}
```

**Route-lar:**

```php
// routes/web.php
use App\Http\Controllers\UnsubscribeController;

Route::get('/unsubscribe/{user}', [UnsubscribeController::class, 'unsubscribe'])
    ->name('unsubscribe');

Route::post('/unsubscribe/{user}', [UnsubscribeController::class, 'oneClick'])
    ->name('unsubscribe.one-click');
```

---

## Email Warm-up Strategiyası

Yeni IP və ya domain-dən birbaşa minlərlə email göndərmək = spam kimi qiymətləndirilmək. Mail server-lər yeni IP-yə etibar etmir. Reputasiya tədricən qurulmalıdır.

**Warm-up cədvəli:**

| Gün | Göndərilə bilən say | Hədəf |
|-----|---------------------|-------|
| 1   | 50                  | Ən aktiv istifadəçilər |
| 2   | 100                 | Aktiv istifadəçilər |
| 3   | 200                 | Son 30 gün aktiv |
| 5   | 500                 | Son 60 gün aktiv |
| 7   | 1 000               | Son 90 gün aktiv |
| 14  | 5 000               | Son 6 ay aktif |
| 21  | 20 000              | Bütün list |
| 30  | Full volume         | — |

**Warm-up zamanı monitorinq:**
- Bounce rate **< 2%** saxla — həddini keçsə, göndərməni dayandır
- Spam complaint rate **< 0.1%** saxla
- Open rate-i izlə — 20%+ yaxşı əlamətdir
- Google Postmaster Tools, Microsoft SNDS-i qur

**Laravel-də rate limiting ilə warm-up:**

```php
// app/Jobs/SendMarketingEmailJob.php
namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;

class SendMarketingEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Rate limiter middleware — saatda maximum 500 email.
     * Warm-up mərhələsini tamamlandıqca bu hədd artırılır.
     */
    public function middleware(): array
    {
        return [new RateLimited('marketing-emails')];
    }
}
```

```php
// app/Providers/AppServiceProvider.php
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

public function boot(): void
{
    RateLimiter::for('marketing-emails', function () {
        return Limit::perHour(500); // Warm-up bitdikdə artır
    });
}
```

---

## Email Provider Müqayisəsi

| Kriteriya | AWS SES | SendGrid | Mailgun | Postmark |
|-----------|---------|----------|---------|----------|
| **Xərc (1k email)** | $0.10 | $0 (100/gün), sonra $19.95/ay | $0 (100/gün), sonra $35/ay | $1.50 |
| **Deliverability** | Yüksək (öz IP seçimi) | Yüksək (dedicated IP) | Yaxşı | Ən yüksək (transactional üçün) |
| **Bounce handling** | SNS webhook | Webhook + Event webhook | Webhook | Webhook |
| **Dedicated IP** | Bəli ($24.95/ay) | Bəli (Pro plan) | Bəli | Bəli (Enterprise) |
| **DKIM/SPF** | Tam dəstək | Tam dəstək | Tam dəstək | Tam dəstək |
| **Analytics** | CloudWatch | Ətraflı dashboard | Ətraflı | Ətraflı |
| **Nə zaman seç** | Yüksək həcm, AWS ekosistemi | Marketing + transactional birlikdə | Flexible API, developer-friendly | Transactional-only, ən yüksək deliverability |

**Praktik tövsiyə:**
- Startup, < 10k email/ay → **Mailgun** (pulsuz tier)
- AWS-də olan app, yüksək həcm → **SES** (ucuz)
- Marketing kampaniyaları → **SendGrid**
- Kritik transactional (bank, medical) → **Postmark** (en yaxşı deliverability, dedicated IP)

---

## Anti-patternlər

**1. SPF/DKIM/DMARC olmadan email göndərmək**
Mail server-lər bu record-lara baxır. Yoxdursa, email spam-ə düşür. DNS record-larını qurmaq 30 dəqiqə çəkir — etməmək üçün heç bir səbəb yoxdur.

**2. Hard bounce-u suppression list-ə əlavə etməmək**
Mövcud olmayan ünvanlara göndərməyə davam etmək = bounce rate artır = domain bloklanır. İlk hard bounce-da ünvanı həmişəlik suppression list-ə əlavə et, bir daha göndərmə.

**3. Unsubscribe linkini gözdən gizlətmək**
Kiçik font, solğun rəng, footer-in lap altında. İstifadəçi linki tapa bilmirsə "spam" klikləyir. Bu complaint rate-i artırır. Unsubscribe link aydın, asan tapıla bilən yerdə olmalıdır.

**4. Transactional və marketing email-i eyni IP-dən göndərmək**
Marketing kampaniyası bounce rate-i artırsa, password reset email-lər də bloklanır. Ayrı IP və ya ayrı subdomain istifadə et: `mail.yourdomain.com` transactional, `news.yourdomain.com` marketing.

**5. Bounce rate-ı monitoring etməmək**
2%-i keçdikdə Gmail throttle edir. Xəbər tutmadan rate 5%-ə çatır, bütün email-lər spam-ə düşür. SES Metrics, Google Postmaster Tools-u qur, alertlər əlavə et.

**6. Stale list-ə toplu email göndərmək**
1 il ərzində email göndərilməyib, indi 50k-a kampaniya göndərilir. Aktivsiz istifadəçilərin böyük hissəsi "spam" klikləyir. Warm-up et, əvvəlcə yalnız son 6 ayda aktiv olanları hədəf al.

**7. `noreply@` ünvanı istifadə etmək**
Mail server-lər cavab yazıla bilən ünvanları daha yüksək qiymətləndirir. İstifadəçi cavab verməyə çalışanda xəta alır — UX pisdir. `support@` istifadə et, mail-lər bir inbox-a çatır, Reply-To header-ini əlavə et.

---

## Interview Sualları və Cavablar

**S1: SPF, DKIM və DMARC nədir, aralarındakı fərq nədir?**

SPF, domain-dən email göndərməyə icazəli IP-ləri DNS-də elan edir — "bu IP-lər bizim adımızdan göndərə bilər" deyir. DKIM isə email header-larına kriptografik imza əlavə edir — email-in yolda dəyişdirilmədiyini sübut edir. DMARC isə bu ikisinin nəticəsinə əsaslanaraq mail server-ə nə etməli olduğunu bildirir (reject, quarantine, none) və domain sahibinə hesabat göndərir. Üçü birlikdə işlədikdə güclü autentifikasiya yaranır — biri olub digəri olmazsa, protection tam deyil.

**S2: Hard bounce ilə soft bounce arasındakı fərq nədir? Hər birinə necə davranmaq lazımdır?**

Hard bounce — ünvan tamamilə mövcud deyil (`550 5.1.1 User unknown`). Bu ünvana bir daha göndərmə — suppression list-ə əlavə et, həmişəlik. Soft bounce — müvəqqəti problem: mailbox dolu, server müvəqqəti down (`452 Insufficient storage`). Bir neçə gün arayla 3 dəfə cəhd et, sonra da alınmırsa suppress et. Hər iki növü SES webhook-dan fərqləndirmək mümkündür: `bounceType` field-i `Permanent` (hard) vs `Transient` (soft) dəyər qaytarır.

**S3: Email-in spam score-u nəyə görə yüksəlir, necə azaltmaq olar?**

Spam score bir neçə faktorun cəmidir: SPF/DKIM/DMARC yoxdursa (+xətt), IP reputasiyası zəifdirsə, email məzmunu şübhəli key word-lər ehtiva edirsə ("free money", CAPS LOCK, həddindən artıq exclamation), HTML-dən text ratio pis nisbətdədirsə, link-lər shortened URL-lərdirsə. Azaltmaq üçün: DNS authentication qur, dedicated IP al, warm-up et, məzmunu test et (mail-tester.com), plain text alternativi əlavə et, öz domain-indən linklər istifadə et.

**S4: Email deliverability-ni necə monitorinq etmək lazımdır?**

Bir neçə mənbə birlikdə: **Google Postmaster Tools** — Gmail üçün domain/IP reputasiyasını, spam rate-i, delivery errors-u göstərir. **Microsoft SNDS** — Outlook üçün analoji. **SES CloudWatch Metrics** — bounce rate, complaint rate, delivery rate real-time. **Bounce/complaint webhook-ları** — hər hadisəni log-a yaz, bounce rate 1.5%-i keçsə Slack alert. Həftəlik olaraq bu metrikaları nəzərdən keçir — problem böyüməzdən əvvəl aşkarlanır.

**S5: Domain-in email reputasiyası düşübsə necə bərpa etmək olar?**

Birinci addım — problemi müəyyənləşdir: Google Postmaster Tools-da spam rate-ə bax, SES dashboard-da bounce/complaint rate-ə bax. Kök səbəb: köhnə list-ə göndərmə, suppression yoxdur, SPF/DKIM düzgün qurulmayıb. Bərpa: göndərməni dayandır, suppression list-i təmizlə, DNS record-larını yoxla, yalnız son 30 gündə aktiv olanlardan başla, həftədə 2 dəfə kiçik batch göndər. Reputasiyanın bərpası 4-8 həftə çəkə bilər. Çox ağır hallarda yeni subdomain + yeni IP ilə başlamaq daha sürətlidir.

---

## Praktik Tapşırıqlar

**Tapşırıq 1: SPF/DKIM/DMARC qurulumu**
1. Domain DNS panel-ini aç
2. SPF TXT record əlavə et: `v=spf1 include:amazonses.com ~all`
3. AWS SES-də DKIM aktivləşdir, 3 CNAME record-u DNS-ə əlavə et
4. DMARC TXT record əlavə et: `v=DMARC1; p=none; rua=mailto:dmarc@yourdomain.com`
5. [mail-tester.com](https://mail-tester.com) ilə test email göndər — skor 10/10 olmalıdır
6. [mxtoolbox.com/dmarc](https://mxtoolbox.com/dmarc) ilə DMARC record-u yoxla

**Tapşırıq 2: Tam bounce handling sistemi**
1. Migration ilə `email_suppressions` cədvəlini yarat
2. `SesWebhookController` implement et
3. AWS SNS topic yarat, SES configuration set-i bu topic-ə qoş
4. Webhook endpoint-ini SNS-ə qeydiyyat et
5. `CheckEmailSuppressionListener`-i `MessageSending` event-inə qoş
6. Test: SES Simulator ilə `bounce@simulator.amazonses.com`-a email göndər, webhook-un işləyib-işləmədiyini yoxla

**Tapşırıq 3: Unsubscribe mexanizmi**
1. `unsubscribe` route-u yarat, signed URL tətbiq et
2. Marketing Mailable-a `List-Unsubscribe` header əlavə et
3. Unsubscribe səhifəsi yarat (istifadəçi hansı email növündən çıxmaq istədiyini seçsin)
4. Suppression listener-ini marketing vs transactional ayırd etmək üçün yenilə

---

## Əlaqəli Mövzular

- `02-double-charge-prevention.md` — Idempotency key, webhook deduplication
- `05-queue-system.md` — Email job-larının queue ilə göndərilməsi
- `13-rate-limiting.md` — Email göndərmə rate-i məhdudlaşdırma
- `22-monitoring-alerting.md` — Bounce rate alertləri, Postmaster Tools inteqrasiyası
