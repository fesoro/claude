# Email Protocols (Middle)

## İcmal

Email protokolları elektron poçt göndərmək və almaq üçün istifadə olunan standart protokollardır. Hər birinin xüsusi rolu var:

- **SMTP**: Email göndərmə (Simple Mail Transfer Protocol)
- **POP3**: Email almaq (Post Office Protocol v3) — download və sil
- **IMAP**: Email almaq (Internet Message Access Protocol) — server-də saxla

Email authentication protokolları:
- **SPF**: Göndərənin IP-sini doğrula
- **DKIM**: Email məzmununu cryptographically imzala
- **DMARC**: SPF+DKIM siyasəti tətbiq et

```
Email flow:

Sender                  SMTP Server              Recipient Server          Recipient
  |  (Outlook)          (smtp.gmail.com)         (mail.yahoo.com)          (Yahoo Mail)
  |                          |                        |                        |
  |--- SMTP (send) --------->|                        |                        |
  |                          |--- SMTP (relay) ------>|                        |
  |                          |                        |-- DNS MX lookup       |
  |                          |                        |-- SPF/DKIM/DMARC check|
  |                          |                        |-- Store in mailbox    |
  |                          |                        |                        |
  |                          |                        |<-- IMAP/POP3 fetch -->|
```

## Niyə Vacibdir

Laravel tətbiqləri transactional email (order confirmation, password reset), marketing email, bounce handling, email verification kimi ssenarilərdə email göndərir. SPF, DKIM, DMARC olmadan göndərilen emaillər spam qovluğuna düşür və ya reject olunur. Deliverability problemi birbaşa biznes itkisinə çevrilir. Email bounce-larını handle etmək, queue-da göndərmək, SES/Mailgun inteqrasiyası backend developer-in bilməli olduğu praktik bilikdir.

## Əsas Anlayışlar

### 1. SMTP (Port 25, 587, 465)

```
SMTP: Client-dən server-ə və server-lər arasında email göndərmə.

Conversation (plain text protocol):

C: [connect to smtp.example.com:587]
S: 220 smtp.example.com ESMTP ready
C: EHLO client.example.com
S: 250-STARTTLS
S: 250-AUTH PLAIN LOGIN
C: STARTTLS
S: 220 Ready to start TLS
[TLS handshake]
C: AUTH LOGIN
S: 334 (base64 username prompt)
C: [base64 username]
S: 334 (base64 password prompt)
C: [base64 password]
S: 235 Authentication successful
C: MAIL FROM:<sender@example.com>
S: 250 OK
C: RCPT TO:<recipient@example.com>
S: 250 OK
C: DATA
S: 354 Start mail input
C: From: sender@example.com
C: To: recipient@example.com
C: Subject: Hello
C:
C: Hello, this is a test email.
C: .
S: 250 Message accepted for delivery
C: QUIT
S: 221 Bye

Ports:
  25  - MTA-to-MTA (server-to-server)
  587 - Client submission (with authentication) - RECOMMENDED
  465 - SMTPS (legacy, implicit TLS)
```

### 2. POP3 (Port 110, 995)

```
POP3: Server-dən email download edib yerli saxla, sonra server-dən sil.

C: [connect to pop.example.com:995 (SSL)]
S: +OK POP3 server ready
C: USER myemail@example.com
S: +OK
C: PASS mypassword
S: +OK 5 messages
C: RETR 1           (fetch message 1)
S: +OK 2048 octets
S: [message content]
C: DELE 1           (delete message 1)
S: +OK
C: QUIT

Characteristics:
  - Download-and-delete model
  - Single-device access
  - Offline reading
  - Heç bir folder konsepti yoxdur
```

### 3. IMAP (Port 143, 993)

```
IMAP: Email server-də qalır. Multi-device sync dəstəyi.

C: [connect to imap.example.com:993 (SSL)]
S: * OK IMAP4rev1 server ready
C: A001 LOGIN user password
S: A001 OK LOGIN completed
C: A002 LIST "" "*"
S: * LIST (\HasNoChildren) "/" "INBOX"
S: * LIST (\HasNoChildren) "/" "Sent"
S: * LIST (\HasNoChildren) "/" "Drafts"
C: A003 SELECT INBOX
S: * 25 EXISTS
C: A004 FETCH 1:5 (BODY[HEADER])
S: [headers]

Characteristics:
  - Server-based storage
  - Multi-device sync (phone, laptop, web)
  - Folders (INBOX, Sent, Drafts)
  - Flags (Read, Flagged, Deleted)
  - Server-side search
```

### 4. SPF (Sender Policy Framework)

```
SPF: Domain owner hansı server-lərin onun adından email göndərə biləcəyini elan edir.

DNS TXT record:
  example.com.  TXT  "v=spf1 ip4:192.168.1.0/24 include:_spf.google.com ~all"

Breakdown:
  v=spf1              -> SPF version 1
  ip4:192.168.1.0/24  -> Bu IP-lərə icazə ver
  include:_spf.google.com -> Google-un SPF record-inə görə
  ~all                -> Softfail for others

Qualifiers:
  +  Pass (default)
  -  Fail (reject)
  ~  Softfail (mark suspicious)
  ?  Neutral
```

### 5. DKIM (DomainKeys Identified Mail)

```
DKIM: Email-in bəzi hissələrini domain-in private key-i ilə imzalayır.

Sender:
  1. Email-in header+body hash-layır
  2. Private key ilə imzalayır
  3. DKIM-Signature header əlavə edir

DKIM-Signature: v=1; a=rsa-sha256; d=example.com;
  s=mail; h=from:to:subject; bh=base64_hash_body;
  b=base64_signature

Recipient:
  1. DNS TXT lookup: mail._domainkey.example.com
  2. Public key ilə signature verify edir
  3. Body hash match edərsə -> email authentic və tampered deyil

Benefit:
  - Authentication (sender domain verified)
  - Integrity (tampering detection)
```

### 6. DMARC (Domain-based Message Authentication)

```
DMARC: SPF və DKIM-in siyasətini müəyyən edir.

DNS TXT record:
  _dmarc.example.com  TXT  "v=DMARC1; p=reject; rua=mailto:dmarc@example.com; pct=100"

Tags:
  v=DMARC1           -> Version
  p=reject           -> Policy (none, quarantine, reject)
  rua=mailto:...     -> Aggregate reports address
  pct=100            -> % of emails to apply policy

Deployment stages:
  Stage 1: p=none (monitor, get reports)
  Stage 2: p=quarantine (soft enforcement)
  Stage 3: p=reject (full enforcement)
```

### Bounce Types

```
Hard bounce (permanent):
  - Invalid email address
  - Domain doesn't exist
  Action: Remove from list

Soft bounce (temporary):
  - Mailbox full
  - Server down
  Action: Retry later
```

### Email Deliverability Factors

```
1. Authentication: SPF + DKIM + DMARC configured
2. Sender reputation: IP, domain reputation
3. Content: Spam trigger words, HTML structure
4. Engagement: Open rate, click rate, unsubscribe rate
5. Bounce rate: Hard bounce (invalid), soft bounce (full mailbox)
6. Complaint rate: Recipient "spam" button
7. List hygiene: Invalid, inactive address-ləri təmizlə
8. IP warm-up: Yeni IP-dən gradually artır volume
```

### MX Records

```
MX (Mail Exchange) record email server-ləri elan edir:

  example.com.  MX 10  mail1.example.com.
  example.com.  MX 20  mail2.example.com.

Priority: Kiçik rəqəm = üstün. 10-a əvvəl cəhd, sonra 20.
```

### MIME

```
Email originally text-only. MIME attachment, HTML, non-ASCII support gətirdi.

Content-Type: multipart/mixed; boundary="----=_Part_1"

------=_Part_1
Content-Type: text/plain
(Plain text version)

------=_Part_1
Content-Type: text/html
(HTML version)

------=_Part_1
Content-Type: image/png; name="photo.png"
Content-Disposition: attachment
Content-Transfer-Encoding: base64
(base64 encoded image)
------=_Part_1--
```

## Praktik Baxış

**Trade-off-lar:**
- Synchronous mail sending — request vaxtını uzadır, SMTP fail olsa request fail olur
- Queue-da göndərmək — user cavabı dərhal alır, amma failed jobs monitoring lazım
- Dedicated IP (böyük həcmdə) — reputation ayrıdır, amma warm-up tələb edir

**Nə vaxt istifadə edilməməlidir:**
- Marketing və transactional email üçün eyni IP/domain istifadə etmək (reputation izolyasiyası üçün ayırın)
- POP3 — multi-device mühitdə IMAP daha uyğundur

**Anti-pattern-lər:**
- Hard bounce-ları list-dən silməmək — sender reputation pisləşir
- `p=reject` DMARC-ı test etmədən tətbiq etmək (legitimate email reject oluna bilər)
- `@gmail.com` kimi göndərici address-i istifadə etmək — SPF/DKIM alignment yoxdur
- Queue olmadan döngüdə email göndərmək (timeout + memory problem)

## Nümunələr

### Ümumi Nümunə

Laravel Mail facade — mailable class, queue integration, SES/Mailgun driver, bounce webhook. Transactional emaillər həmişə queue-da göndərilməlidir. Bounce handling SES SNS webhook vasitəsilə avtomatlaşdırılmalıdır.

### Kod Nümunəsi

**SMTP Configuration (.env):**

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailgun.org
MAIL_PORT=587
MAIL_USERNAME=postmaster@mg.example.com
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=hello@example.com
MAIL_FROM_NAME="${APP_NAME}"
```

**Basic Mail Sending:**

```php
use Illuminate\Support\Facades\Mail;
use App\Mail\WelcomeEmail;

class WelcomeEmail extends Mailable
{
    public function __construct(public User $user) {}

    public function build()
    {
        return $this->subject('Welcome!')
                    ->from('hello@example.com', 'App Name')
                    ->view('emails.welcome')
                    ->with(['name' => $this->user->name]);
    }
}

// Controller
Mail::to($user->email)
    ->cc('manager@example.com')
    ->bcc('audit@example.com')
    ->send(new WelcomeEmail($user));

// Queue (async — tövsiyə olunur)
Mail::to($user->email)->queue(new WelcomeEmail($user));
```

**Mail with Attachment:**

```php
public function build()
{
    return $this->subject('Invoice')
                ->view('emails.invoice')
                ->attach(storage_path('app/invoices/invoice-123.pdf'), [
                    'as'   => 'Invoice.pdf',
                    'mime' => 'application/pdf',
                ])
                ->attachData($csvContent, 'data.csv', [
                    'mime' => 'text/csv',
                ]);
}
```

**Mailgun Integration:**

```env
MAIL_MAILER=mailgun
MAILGUN_DOMAIN=mg.example.com
MAILGUN_SECRET=key-xxx
MAILGUN_ENDPOINT=api.mailgun.net
```

**AWS SES Integration:**

```env
MAIL_MAILER=ses
AWS_ACCESS_KEY_ID=xxx
AWS_SECRET_ACCESS_KEY=xxx
AWS_DEFAULT_REGION=us-east-1
```

**Bounce/Complaint Handling (SES SNS Webhook):**

```php
Route::post('/webhooks/ses', [SesWebhookController::class, 'handle']);

public function handle(Request $request)
{
    $payload = json_decode($request->getContent(), true);

    if ($payload['Type'] === 'SubscriptionConfirmation') {
        file_get_contents($payload['SubscribeURL']);
        return response('OK');
    }

    $message = json_decode($payload['Message'], true);

    if ($message['notificationType'] === 'Bounce') {
        foreach ($message['bounce']['bouncedRecipients'] as $recipient) {
            $email = $recipient['emailAddress'];

            if ($message['bounce']['bounceType'] === 'Permanent') {
                // Hard bounce — suppress future emails
                SuppressedEmail::create(['email' => $email, 'reason' => 'hard_bounce']);
            }
        }
    }

    if ($message['notificationType'] === 'Complaint') {
        foreach ($message['complaint']['complainedRecipients'] as $recipient) {
            SuppressedEmail::create([
                'email'  => $recipient['emailAddress'],
                'reason' => 'complaint',
            ]);
        }
    }

    return response('OK');
}
```

**Email Verification:**

```php
use Illuminate\Contracts\Auth\MustVerifyEmail;

class User extends Authenticatable implements MustVerifyEmail
{
    use Notifiable;
}

// Send verification email
$user->sendEmailVerificationNotification();

// Protect route until verified
Route::middleware(['auth', 'verified'])->group(function () {
    // ...
});
```

**IMAP ilə Email oxumaq (webklex/laravel-imap):**

```php
// composer require webklex/laravel-imap

use Webklex\IMAP\Facades\Client;

$client  = Client::account('default');
$client->connect();

$folder   = $client->getFolder('INBOX');
$messages = $folder->query()->unseen()->get();

foreach ($messages as $message) {
    echo $message->getSubject();
    echo $message->getFrom()[0]->mail;
    echo $message->getTextBody();

    $message->setFlag(['Seen']);

    foreach ($message->getAttachments() as $attachment) {
        $attachment->save(storage_path('attachments/'));
    }
}
```

## Praktik Tapşırıqlar

1. **SPF + DKIM + DMARC qoymaq:** `mail-tester.com` istifadə edərək baseline email score-u yoxlayın. DNS-ə SPF, DKIM, DMARC record-larını əlavə edin. Yenidən test edib 10/10 score almağa çalışın.

2. **Bounce handling webhook:** AWS SES-i konfiqurasiya edin. SNS topic yaradıb SES bounce/complaint notification-larını bu topic-ə göndərin. Webhook-da hard bounce email-lərini `suppressed_emails` cədvəlinə əlavə edin. Test üçün `+invalid` address-ə email göndərin.

3. **Queue-da email:** `WelcomeEmail` mailable-ını `->queue()` ilə göndərin. `php artisan queue:work` ilə işləyin. SMTP şəbəkə gecikmə simulyasiyası edib (`tc qdisc` və ya sadəcə yavaş SMTP) queue-un user-i gözlətmədiyini müşahidə edin.

4. **IMAP inbox monitoring:** Daxil olan email-ləri IMAP vasitəsilə oxuyub, müəyyən subject pattern-ə uyğun olanları avtomatik işləyin (məsələn support@ inbox-undan ticket yaratmaq).

5. **Email preview testing:** `mailhog` (local) və ya `Mailtrap` (staging) qoşun. `.env`-də `MAIL_MAILER=log` əvəzinə Mailtrap konfiqurasiyasını yazın. `php artisan tinker`-dən test email göndərib Mailtrap-da HTML/plain text görünüşünü yoxlayın.

## Əlaqəli Mövzular

- [DNS](07-dns.md)
- [HTTPS / SSL / TLS](06-https-ssl-tls.md)
- [API Security](17-api-security.md)
- [Webhooks](23-webhooks.md)
- [Network Security](26-network-security.md)
