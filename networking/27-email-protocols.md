# Email Protocols

## Nədir? (What is it?)

Email protokolları elektron poçt gondermek və almaq üçün istifade olunan standart protokollardır. Hər birinin xüsusi rolu var:

- **SMTP**: Email göndermə (Simple Mail Transfer Protocol)
- **POP3**: Email almaq (Post Office Protocol v3) - download və sil
- **IMAP**: Email almaq (Internet Message Access Protocol) - server-də saxla

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

## Necə İşləyir? (How does it work?)

### 1. SMTP (Port 25, 587, 465)

```
SMTP: Client-dən server-ə və server-lər arasında email göndərmə.

Conversation (plain text protocol):

C: [connect to smtp.example.com:587]
S: 220 smtp.example.com ESMTP ready
C: EHLO client.example.com
S: 250-smtp.example.com Hello
S: 250-STARTTLS
S: 250-AUTH PLAIN LOGIN
S: 250 OK
C: STARTTLS
S: 220 Ready to start TLS
[TLS handshake happens]
C: AUTH LOGIN
S: 334 VXNlcm5hbWU6
C: [base64 username]
S: 334 UGFzc3dvcmQ6
C: [base64 password]
S: 235 Authentication successful
C: MAIL FROM:<sender@example.com>
S: 250 OK
C: RCPT TO:<recipient@example.com>
S: 250 OK
C: DATA
S: 354 Start mail input; end with <CRLF>.<CRLF>
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
C: LIST
S: +OK 5 messages
S: 1 2048
S: 2 1024
S: 3 4096
S: .
C: RETR 1           (fetch message 1)
S: +OK 2048 octets
S: [message content]
S: .
C: DELE 1           (delete message 1)
S: +OK
C: QUIT
S: +OK Bye

Characteristics:
  - Download-and-delete model
  - Single-device access
  - Offline reading
  - Limited server storage
  - Hech bir folder konseptsi yoxdur
```

### 3. IMAP (Port 143, 993)

```
IMAP: Email server-də qalir. Multi-device sync dəstəyi.

C: [connect to imap.example.com:993 (SSL)]
S: * OK IMAP4rev1 server ready
C: A001 LOGIN user password
S: A001 OK LOGIN completed
C: A002 LIST "" "*"
S: * LIST (\HasNoChildren) "/" "INBOX"
S: * LIST (\HasNoChildren) "/" "Sent"
S: * LIST (\HasNoChildren) "/" "Drafts"
S: A002 OK
C: A003 SELECT INBOX
S: * 25 EXISTS
S: * 2 RECENT
S: A003 OK
C: A004 FETCH 1:5 (BODY[HEADER])
S: * 1 FETCH (BODY[HEADER] {342}
S: [headers]
S: )
S: A004 OK
C: A005 LOGOUT

Characteristics:
  - Server-based storage
  - Multi-device sync (phone, laptop, web)
  - Folders (INBOX, Sent, Drafts)
  - Flags (Read, Flagged, Deleted)
  - Server-side search
```

### 4. SPF (Sender Policy Framework)

```
SPF: Domain owner hansi server-lerin onun adindan email gondere bilecek elan edir.

DNS TXT record:
  example.com.  TXT  "v=spf1 ip4:192.168.1.0/24 include:_spf.google.com ~all"

Breakdown:
  v=spf1              -> SPF version 1
  ip4:192.168.1.0/24  -> Allow these IPs
  include:_spf.google.com -> Google-un SPF record-ine gore
  ~all                -> Softfail for others (~, -, +, ?)

Recipient validation:
  1. Email from: sender@example.com
  2. Received from IP: 203.0.113.5
  3. DNS lookup: example.com TXT records -> SPF
  4. IP 203.0.113.5 in allowed list? NO
  5. Action: ~all -> softfail (mark as spam)

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
  1. Email-in header+body hash-layir
  2. Private key ile imzalayir
  3. DKIM-Signature header əlavə edir

DKIM-Signature: v=1; a=rsa-sha256; d=example.com;
  s=mail; h=from:to:subject; bh=base64_hash_body;
  b=base64_signature

Recipient:
  1. DKIM-Signature header oxuyur
  2. DNS TXT lookup: selector._domainkey.example.com
       mail._domainkey.example.com TXT "v=DKIM1; k=rsa; p=public_key"
  3. Public key ile signature verify edir
  4. Body hash match edirse -> email authentic və tampered deyil

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
  ruf=mailto:...     -> Forensic reports address
  pct=100            -> % of emails to apply policy
  adkim=s            -> DKIM alignment (strict/relaxed)
  aspf=s             -> SPF alignment

Flow:
  1. Email arrives
  2. Check SPF (passed/failed)
  3. Check DKIM (passed/failed)
  4. Alignment check (From: domain == SPF/DKIM domain?)
  5. Apply DMARC policy:
     - none: monitor only
     - quarantine: spam folder
     - reject: refuse delivery

Deployment stages:
  Stage 1: p=none (monitor, get reports)
  Stage 2: p=quarantine (soft enforcement)
  Stage 3: p=reject (full enforcement)
```

### 7. Email Deliverability

```
Faktorlar:

1. Authentication: SPF + DKIM + DMARC configured
2. Sender reputation: IP, domain reputation (maillog, Talos, Sender Score)
3. Content: Spam trigger words, HTML structure
4. Engagement: Open rate, click rate, unsubscribe rate
5. Bounce rate: Hard bounce (invalid), soft bounce (full mailbox)
6. Complaint rate: Recipient "spam" button
7. List hygiene: Invalid, inactive address-lər temizlemə
8. IP warm-up: Yeni IP-dən gradually artir volume
```

### 8. Email Headers Analysis

```
Received: from mail-server.example.com ([203.0.113.5])
    by gmail.com with ESMTPS id xyz
    for <user@gmail.com>;
    Wed, 15 Mar 2024 10:30:00 -0700
Authentication-Results: gmail.com;
    spf=pass (google.com: domain of sender@example.com designates 203.0.113.5 as permitted sender)
    dkim=pass header.d=example.com
    dmarc=pass header.from=example.com
DKIM-Signature: v=1; a=rsa-sha256; d=example.com; s=mail; ...
From: Sender <sender@example.com>
To: User <user@gmail.com>
Subject: Test
Message-ID: <unique-id@example.com>
Date: Wed, 15 Mar 2024 10:30:00 -0700
MIME-Version: 1.0
Content-Type: multipart/alternative; boundary="xyz"
```

## Əsas Konseptlər (Key Concepts)

### MX Records

```
MX (Mail Exchange) record email server-leri elan edir:

  example.com.  MX 10  mail1.example.com.
  example.com.  MX 20  mail2.example.com.

Priority: Kicik rəqəm = üstün. 10-a əvvəl cəhd, sonra 20.
```

### Bounce Types

```
Hard bounce (permanent):
  - Invalid email address
  - Domain doesn't exist
  - User unknown
  Action: Remove from list

Soft bounce (temporary):
  - Mailbox full
  - Server down
  - Message too large
  Action: Retry later
```

### Email Marketing Compliance

```
CAN-SPAM (US):
  - Clear sender identification
  - Accurate subject lines
  - Opt-out mechanism
  - Physical address

GDPR (EU):
  - Explicit consent
  - Right to be forgotten
  - Data processing disclosure

CASL (Canada):
  - Express consent required
  - Identification
  - Unsubscribe mechanism
```

### MIME (Multipurpose Internet Mail Extensions)

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

## PHP/Laravel ilə İstifadə

### SMTP Configuration (.env)

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

### Basic Mail Sending

```php
use Illuminate\Support\Facades\Mail;
use App\Mail\WelcomeEmail;

// Mailable class
class WelcomeEmail extends Mailable
{
    public function __construct(public User $user) {}

    public function build()
    {
        return $this->subject('Welcome!')
                    ->from('hello@example.com', 'App Name')
                    ->to($this->user->email)
                    ->view('emails.welcome')
                    ->with(['name' => $this->user->name]);
    }
}

// Controller
Mail::to($user->email)
    ->cc('manager@example.com')
    ->bcc('audit@example.com')
    ->send(new WelcomeEmail($user));

// Queue (async)
Mail::to($user->email)->queue(new WelcomeEmail($user));
```

### Multiple Recipients

```php
// Collection
$users = User::where('newsletter', true)->get();
foreach ($users as $user) {
    Mail::to($user->email)->queue(new NewsletterEmail($user));
}

// Bulk (single email, multiple recipients - be careful with privacy!)
Mail::to(['user1@example.com', 'user2@example.com'])
    ->send(new BulkEmail());
```

### Mail with Attachment

```php
public function build()
{
    return $this->subject('Invoice')
                ->view('emails.invoice')
                ->attach(storage_path('app/invoices/invoice-123.pdf'), [
                    'as' => 'Invoice.pdf',
                    'mime' => 'application/pdf',
                ])
                ->attachData($csvContent, 'data.csv', [
                    'mime' => 'text/csv',
                ]);
}
```

### Markdown Mail

```bash
php artisan make:mail OrderShipped --markdown=emails.orders.shipped
```

```php
class OrderShipped extends Mailable
{
    public function build()
    {
        return $this->markdown('emails.orders.shipped')
                    ->with(['order' => $this->order]);
    }
}
```

```blade
{{-- resources/views/emails/orders/shipped.blade.php --}}
@component('mail::message')
# Your Order Has Shipped!

Thank you for your purchase.

@component('mail::button', ['url' => $order->trackingUrl])
Track Order
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent
```

### Mailgun Integration

```env
MAIL_MAILER=mailgun
MAILGUN_DOMAIN=mg.example.com
MAILGUN_SECRET=key-xxx
MAILGUN_ENDPOINT=api.mailgun.net
```

```php
// Installation
// composer require symfony/mailgun-mailer symfony/http-client

// Usage same as normal Mail:: facade
Mail::to($user)->send(new WelcomeEmail($user));
```

### AWS SES Integration

```env
MAIL_MAILER=ses
AWS_ACCESS_KEY_ID=xxx
AWS_SECRET_ACCESS_KEY=xxx
AWS_DEFAULT_REGION=us-east-1
```

```php
// composer require aws/aws-sdk-php

// Standard usage
Mail::to($user)->send(new WelcomeEmail($user));

// With configuration options
use Aws\Ses\SesClient;

$ses = new SesClient([
    'version' => '2010-12-01',
    'region' => 'us-east-1',
]);

$result = $ses->sendEmail([
    'Source' => 'sender@example.com',
    'Destination' => ['ToAddresses' => ['user@example.com']],
    'Message' => [
        'Subject' => ['Data' => 'Hello'],
        'Body' => ['Text' => ['Data' => 'Hi there']],
    ],
    'Tags' => [
        ['Name' => 'campaign', 'Value' => 'welcome'],
    ],
]);
```

### Bounce/Complaint Handling (SES SNS Webhook)

```php
// Route
Route::post('/webhooks/ses', [SesWebhookController::class, 'handle']);

// Controller
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
                // Hard bounce - suppress future emails
                User::where('email', $email)->update(['email_verified_at' => null]);
                SuppressedEmail::create(['email' => $email, 'reason' => 'hard_bounce']);
            }
        }
    }

    if ($message['notificationType'] === 'Complaint') {
        foreach ($message['complaint']['complainedRecipients'] as $recipient) {
            // User marked as spam - add to suppression
            SuppressedEmail::create([
                'email' => $recipient['emailAddress'],
                'reason' => 'complaint',
            ]);
        }
    }

    return response('OK');
}
```

### Email Verification

```php
use Illuminate\Contracts\Auth\MustVerifyEmail;

class User extends Authenticatable implements MustVerifyEmail
{
    use Notifiable;
    // ...
}

// Send verification email
$user->sendEmailVerificationNotification();

// Route
Route::get('/email/verify', function () {
    return view('auth.verify-email');
})->middleware('auth')->name('verification.notice');

Route::get('/email/verify/{id}/{hash}', function ($id, $hash) {
    // Auto-handled by EmailVerificationController
})->middleware(['auth', 'signed'])->name('verification.verify');

// Protect route until verified
Route::middleware(['auth', 'verified'])->group(function () {
    // ...
});
```

### IMAP with PHP (read emails)

```php
// composer require webklex/laravel-imap

use Webklex\IMAP\Facades\Client;

$client = Client::account('default');
$client->connect();

$folder = $client->getFolder('INBOX');
$messages = $folder->query()->unseen()->get();

foreach ($messages as $message) {
    echo $message->getSubject();
    echo $message->getFrom()[0]->mail;
    echo $message->getTextBody();

    // Mark as read
    $message->setFlag(['Seen']);

    // Save attachments
    foreach ($message->getAttachments() as $attachment) {
        $attachment->save(storage_path('attachments/'));
    }
}
```

## Interview Sualları

**Q1: SMTP, POP3, IMAP arasında fərq?**

**SMTP** (Simple Mail Transfer Protocol): Email **göndermək** üçün. Port 25 (server-to-server), 587 (client submission), 465 (SMTPS).

**POP3** (Post Office Protocol): Email **alıb silmək** üçün. Download-and-delete model. Port 110, 995 (SSL). Single-device.

**IMAP** (Internet Message Access Protocol): Email **almaq, server-də saxlamaq** üçün. Multi-device sync, folders, flags. Port 143, 993 (SSL).

Modern istifadə: IMAP + SMTP (POP3 legacy).

**Q2: SPF, DKIM, DMARC ilə fərq və əlaqə?**

**SPF**: IP-based. Domain owner "bu IP-lər mənim adimdan göndərə bilər" elan edir. DNS TXT record.

**DKIM**: Content-based. Email cryptographically imzalanir (private key), recipient public key ilə verify edir. Tampering detect.

**DMARC**: Policy layer. SPF və/ya DKIM fail olanda nə etməli (none, quarantine, reject). Alignment və reporting.

Birlikdə: SPF + DKIM authentication; DMARC enforcement + reporting.

**Q3: Hard bounce vs soft bounce?**

**Hard bounce** (permanent): Email ünvani mövcud deyil, domain yoxdur, user bloklanib. Action: list-dən sil, suppress.

**Soft bounce** (temporary): Mailbox full, server down, message too large. Action: retry bir neçə dəfə, sonra soft bounce 5+ dəfə olarsa hard bounce-a çevir.

Bounce rate yüksək olsa sender reputation pisləşir.

**Q4: Email deliverability-i necə yaxşılaşdırmaq?**

1. SPF, DKIM, DMARC configure et
2. Dedicated IP (volume varsa) + warm-up
3. List hygiene (invalid, inactive sil)
4. Double opt-in (subscribe confirmation)
5. Unsubscribe link hər emaildə
6. Spam trigger words-dən qac ("FREE!", "URGENT!!!")
7. HTML struktur düzgün, image/text balance
8. Consistent sending pattern
9. Monitor reputation (Google Postmaster Tools)
10. Engagement-based filtering (inactive user-lari sil)

**Q5: Port 25, 587, 465 fərqi?**

**Port 25**: Original SMTP. MTA-to-MTA (server-server). ISP-lər outbound 25-i adəten bloklayir (spam control).

**Port 587**: **Submission port**. Client-to-MTA. STARTTLS ile opportunistic TLS. Authentication mandatory. **Modern standard**.

**Port 465**: SMTPS (implicit TLS). Legacy, amma hələ istifadə olunur. TLS əvvəldən qurulur.

Modern: 587 + STARTTLS recommended.

**Q6: MX record nədir?**

MX (Mail Exchange) DNS record domain üçün email server-leri elan edir:

```
example.com.  MX 10 mail1.example.com.
example.com.  MX 20 mail2.example.com.
```

Priority: kiçik rəqəm = yüksək üstünlük. Sender domain-a email gondermek istərkən:
1. DNS MX lookup
2. Ən yüksək priority server-ə cəhd (10)
3. Fail olsa - 20-yə keç

**Q7: Queue-da email göndərmək niyə önəmlidir?**

Synchronous mail sending:
- Request-in vaxtı 2-5 saniye uzanir (user görür)
- SMTP fail olsa request fail olur
- Timeout riski
- External service-ə bağlı response time

Queue ilə (`->queue()`):
- User rahat, instant response
- Retry logic automatic
- Failed jobs monitoring
- Rate limiting
- Background worker işləyir

```php
Mail::to($user)->queue(new WelcomeEmail($user)); // async
```

**Q8: Transactional vs marketing email fərqi?**

**Transactional**: Istifadəçi action-a response (password reset, order confirmation, receipt). Tək bir user-ə. Yüksək deliverability, legal-də exempt.

**Marketing/Bulk**: Promosyon, newsletter. Çoxlu recipient. Explicit consent tələb edir (GDPR, CAN-SPAM). Unsubscribe link mandatory. Daha aggressive spam filtering.

Ayri IP-lər, ayri domain-lər tövsiyə olunur (deliverability isolation).

**Q9: DMARC p=reject riski nədir?**

DMARC `p=reject` əvvəlcədən test edilməyibsə, legitimate email də reject olunur:
- Forwarded emails (forwarding SPF-i break edir)
- Third-party services (CRM, marketing tools)
- Mailing lists

Deployment strategy:
1. `p=none` (monitor, 2-4 weeks reports oxu)
2. `p=quarantine; pct=10` (gradual)
3. `p=quarantine; pct=100`
4. `p=reject; pct=10`
5. `p=reject; pct=100`

**Q10: Email-də phishing attack necə tanımaq olar?**

Technical signals:
- SPF/DKIM/DMARC fail
- From domain spoofing
- Reply-To fərqli domain
- Suspicious links (URL mismatch)
- Mixed script (cyrillic/latin)

User signals:
- Urgency, threats
- Unusual requests (password, transfer)
- Poor grammar
- Generic greetings

Protection:
- DMARC enforcement
- Link scanning (Proofpoint, Mimecast)
- User training
- Multi-factor authentication

## Best Practices

1. **SPF + DKIM + DMARC hamisi qur** - email authentication triad.

2. **Port 587 + TLS** - port 25 yerine submission port istifade et.

3. **Queue-da email göndər** - request response time-a tesir etmemek ucun.

4. **Bounce handling** - hard bounce-lari list-dən sil, suppress et.

5. **Complaint handling** - "Report spam" clicked user-lari suppress et.

6. **Double opt-in** - marketing email ucun, deliverability artirir.

7. **Unsubscribe link mandatory** - hər marketing emaildə, tək klik.

8. **HTML + plain text** - multipart/alternative, spam filters yaxsilashdirir.

9. **Image ratio optimal** - 60/40 text/image, spam score azaldir.

10. **Consistent from address** - sender reputation build et.

11. **Warm-up new IP** - gradually artir volume (1 gun 100, sonra 500, 1000...).

12. **List hygiene regular** - 90 gun inactive user-lari remove et.

13. **Monitor deliverability** - Google Postmaster Tools, Microsoft SNDS.

14. **DKIM key rotation** - annually key-i dəyiş.

15. **Dedicated sending IP** - boyuk volume-lərdə shared IP reputation-a bağlı qalmamaq üçün.

16. **Transactional vs marketing separation** - ayri IP, ayri subdomain.

17. **Rate limiting** - recipient server-i spam etmemek ucun.

18. **Error handling** - SMTP fail, retry logic, fallback provider.

19. **Compliance check** - GDPR, CAN-SPAM, CASL requirement-ləri.

20. **Email preview test** - multiple client-lərdə (Gmail, Outlook, Apple Mail) görünüş yoxla.
