# Mail (E-poct gonderme)

> **Seviyye:** Intermediate ⭐⭐

## Giris

E-poct gonderme demek olar ki, her tetbiqin vacib hissesidir - qeydiyyat tesdiq mektublari, parol sifirlama, bildirisler ve s. Spring `JavaMailSender` interfeysi ve Thymeleaf sablon mexanizmi ile isleyir, Laravel ise `Mail` facade ve `Mailable` sinif abstraksiyasi ile son derece temiz ve oxunaqli bir yanasma teklif edir.

## Spring-de istifadesi

### Konfiqurasiya

```yaml
# application.yml
spring:
  mail:
    host: smtp.gmail.com
    port: 587
    username: ${MAIL_USERNAME}
    password: ${MAIL_PASSWORD}
    properties:
      mail.smtp.auth: true
      mail.smtp.starttls.enable: true
      mail.smtp.starttls.required: true
```

### Sade metn mesaji gonderme

```java
@Service
public class EmailService {

    private final JavaMailSender mailSender;

    public EmailService(JavaMailSender mailSender) {
        this.mailSender = mailSender;
    }

    public void sendSimpleEmail(String to, String subject, String text) {
        SimpleMailMessage message = new SimpleMailMessage();
        message.setFrom("noreply@example.com");
        message.setTo(to);
        message.setSubject(subject);
        message.setText(text);

        mailSender.send(message);
    }
}
```

### HTML mesaj gonderme (MimeMessage)

```java
@Service
public class HtmlEmailService {

    private final JavaMailSender mailSender;

    public HtmlEmailService(JavaMailSender mailSender) {
        this.mailSender = mailSender;
    }

    public void sendHtmlEmail(String to, String subject, String htmlContent)
            throws MessagingException {

        MimeMessage message = mailSender.createMimeMessage();
        MimeMessageHelper helper = new MimeMessageHelper(message, true, "UTF-8");

        helper.setFrom("noreply@example.com");
        helper.setTo(to);
        helper.setSubject(subject);
        helper.setText(htmlContent, true); // true = HTML formatdir

        mailSender.send(message);
    }

    // Elaqeli faylla (attachment) gonderme
    public void sendEmailWithAttachment(String to, String subject,
            String htmlContent, File attachment) throws MessagingException {

        MimeMessage message = mailSender.createMimeMessage();
        MimeMessageHelper helper = new MimeMessageHelper(message, true, "UTF-8");

        helper.setFrom("noreply@example.com");
        helper.setTo(to);
        helper.setSubject(subject);
        helper.setText(htmlContent, true);
        helper.addAttachment(attachment.getName(), attachment);

        // Inline sekil elave etmek
        // helper.addInline("logo", new ClassPathResource("static/logo.png"));

        mailSender.send(message);
    }
}
```

### Thymeleaf ile sablon istifadesi

```java
@Service
public class TemplatedEmailService {

    private final JavaMailSender mailSender;
    private final TemplateEngine templateEngine;

    public TemplatedEmailService(JavaMailSender mailSender,
                                  TemplateEngine templateEngine) {
        this.mailSender = mailSender;
        this.templateEngine = templateEngine;
    }

    public void sendWelcomeEmail(User user) throws MessagingException {
        // Thymeleaf kontekstini hazirlayiriq
        Context context = new Context();
        context.setVariable("user", user);
        context.setVariable("activationUrl",
            "https://example.com/activate?token=" + user.getActivationToken());

        // Sablonu isledirik
        String htmlContent = templateEngine.process("emails/welcome", context);

        // Mesaji gonderirik
        MimeMessage message = mailSender.createMimeMessage();
        MimeMessageHelper helper = new MimeMessageHelper(message, true, "UTF-8");
        helper.setFrom("noreply@example.com");
        helper.setTo(user.getEmail());
        helper.setSubject("Xos gelmisiniz!");
        helper.setText(htmlContent, true);

        mailSender.send(message);
    }
}
```

Thymeleaf sablon fayil:

```html
<!-- src/main/resources/templates/emails/welcome.html -->
<!DOCTYPE html>
<html xmlns:th="http://www.thymeleaf.org">
<head>
    <meta charset="UTF-8">
</head>
<body>
    <div style="max-width: 600px; margin: 0 auto; font-family: Arial, sans-serif;">
        <h1>Salam, <span th:text="${user.name}">Ad</span>!</h1>

        <p>Platformamiza qosuldugunuz ucun tesekkur edirik.</p>

        <p>Hesabinizi aktivlesdirmek ucun asagidaki duymeye basin:</p>

        <a th:href="${activationUrl}"
           style="display:inline-block; padding:12px 24px;
                  background-color:#007bff; color:white;
                  text-decoration:none; border-radius:4px;">
            Hesabi Aktivlesdir
        </a>

        <p style="color: #666; margin-top: 20px;">
            Bu link 24 saat erzinde etiberlidir.
        </p>
    </div>
</body>
</html>
```

### Async e-poct gonderme

```java
@Configuration
@EnableAsync
public class AsyncConfig {

    @Bean
    public Executor emailExecutor() {
        ThreadPoolTaskExecutor executor = new ThreadPoolTaskExecutor();
        executor.setCorePoolSize(2);
        executor.setMaxPoolSize(5);
        executor.setQueueCapacity(100);
        executor.setThreadNamePrefix("email-");
        executor.initialize();
        return executor;
    }
}

@Service
public class AsyncEmailService {

    private final JavaMailSender mailSender;
    private final TemplateEngine templateEngine;

    // ... constructor

    @Async("emailExecutor")
    public CompletableFuture<Void> sendWelcomeEmailAsync(User user)
            throws MessagingException {
        // E-poct gonderme metiqi - ayri thread-de isleyir
        Context context = new Context();
        context.setVariable("user", user);
        String html = templateEngine.process("emails/welcome", context);

        MimeMessage message = mailSender.createMimeMessage();
        MimeMessageHelper helper = new MimeMessageHelper(message, true);
        helper.setTo(user.getEmail());
        helper.setSubject("Xos gelmisiniz!");
        helper.setText(html, true);
        mailSender.send(message);

        return CompletableFuture.completedFuture(null);
    }
}
```

## Laravel-de istifadesi

### Konfiqurasiya

```env
# .env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@example.com
MAIL_FROM_NAME="${APP_NAME}"
```

### Mailable sinif yaratma

```bash
php artisan make:mail WelcomeMail
```

```php
// app/Mail/WelcomeMail.php
class WelcomeMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Xos gelmisiniz!',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.welcome',
            with: [
                'activationUrl' => url('/activate?token=' . $this->user->activation_token),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
```

### Blade sablon

```blade
<!-- resources/views/emails/welcome.blade.php -->
<div style="max-width: 600px; margin: 0 auto; font-family: Arial, sans-serif;">
    <h1>Salam, {{ $user->name }}!</h1>

    <p>Platformamiza qosuldugunuz ucun tesekkur edirik.</p>

    <p>Hesabinizi aktivlesdirmek ucun asagidaki duymeye basin:</p>

    <a href="{{ $activationUrl }}"
       style="display:inline-block; padding:12px 24px;
              background-color:#007bff; color:white;
              text-decoration:none; border-radius:4px;">
        Hesabi Aktivlesdir
    </a>

    <p style="color: #666; margin-top: 20px;">
        Bu link 24 saat erzinde etiberlidir.
    </p>
</div>
```

### E-poct gonderme

```php
// Sadece gonderme
Mail::to($user->email)->send(new WelcomeMail($user));

// Queue ile gonderme (asinxron) - ShouldQueue implement etmek kifayetdir
Mail::to($user->email)->queue(new WelcomeMail($user));

// Gec gonderme
Mail::to($user->email)->later(now()->addMinutes(10), new WelcomeMail($user));

// Birden cox alaici
Mail::to($users)
    ->cc('manager@example.com')
    ->bcc('admin@example.com')
    ->send(new WelcomeMail($user));
```

### Markdown Mail

Laravel-in en guclu xususiyyetlerinden biri Markdown mailleridir:

```bash
php artisan make:mail OrderShipped --markdown=emails.orders.shipped
```

```php
class OrderShipped extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Sifarisiz gonderildi!',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.orders.shipped',
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromPath('/path/to/invoice.pdf')
                ->as('qebz.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
```

```blade
<!-- resources/views/emails/orders/shipped.blade.php -->
<x-mail::message>
# Sifarisiz gonderildi!

Hormetli {{ $order->user->name }},

**{{ $order->id }}** nomreli sifarisiz gonderildi.

<x-mail::table>
| Mehsul | Miqdar | Qiymet |
|:-------|:------:|-------:|
@foreach($order->items as $item)
| {{ $item->name }} | {{ $item->quantity }} | {{ $item->price }} AZN |
@endforeach
| | **Umumi:** | **{{ $order->total }} AZN** |
</x-mail::table>

<x-mail::button :url="route('orders.show', $order)">
Sifarise bax
</x-mail::button>

Tesekkurler,<br>
{{ config('app.name') }}
</x-mail::message>
```

### Queued Mail (Novebeye alinmis)

```php
// Variant 1: ShouldQueue implement etmek - butun gondermeler avtomatik queue-ya dusur
class WelcomeMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    // Queue konfiqurasiyasi
    public $queue = 'emails';
    public $delay = 10; // 10 saniye gec gonder
    public $tries = 3;  // 3 defe cehd et

    // ...
}

// Variant 2: Manual queue-ya salmaq
Mail::to($user)->queue(new WelcomeMail($user));

// Variant 3: Mueyyen vaxta planlasdirmaq
Mail::to($user)->later(now()->addMinutes(30), new WelcomeMail($user));
```

### Mail onizleme (Preview)

```php
// routes/web.php - yalniz development ucun
Route::get('/mail-preview', function () {
    $user = User::factory()->make();
    return new WelcomeMail($user);
});
```

### Ferqli mail driver-ler

```php
// Mueyyen bir mail ucun ferqli driver istifade etmek
Mail::mailer('postmark')
    ->to($user)
    ->send(new WelcomeMail($user));

// config/mail.php-de birden cox mailer tanimlamaq mumkundur
'mailers' => [
    'smtp' => [
        'transport' => 'smtp',
        // ...
    ],
    'postmark' => [
        'transport' => 'postmark',
    ],
    'ses' => [
        'transport' => 'ses',
    ],
    'mailgun' => [
        'transport' => 'mailgun',
    ],
],
```

## Esas ferqler

| Xususiyyet | Spring | Laravel |
|---|---|---|
| **Esas sinif** | `JavaMailSender` interfeysi | `Mailable` sinfi |
| **Sablon** | Thymeleaf, FreeMarker | Blade, Markdown |
| **HTML mail** | `MimeMessageHelper` ile | Blade view ile |
| **Markdown mail** | Yoxdur (built-in) | Built-in komponent sistemi |
| **Queue** | `@Async` ile manual | `ShouldQueue` implement etmekle |
| **Gec gonderme** | Manual planlasdirma | `later()` metodu |
| **Mail onizleme** | Yoxdur (built-in) | Route-dan birbaşa gormek mumkun |
| **Attachment** | `MimeMessageHelper.addAttachment()` | `attachments()` metodu |
| **Driver deyisme** | Konfiqurasiya deyisikliyi | `Mail::mailer()` ile runtime-da |
| **Sinif yaratma** | Manual | `php artisan make:mail` |

## Niye bele ferqler var?

**Spring-in yanasmasi:** Spring JavaMail API uezerine quruludur ve bu API Java EE (Jakarta EE) standartinin bir hissesidir. `MimeMessage`, `MimeMessageHelper` sinfleri Java ekosisteminin standart aletleridir. Spring bunu sadece DI ile birlesdirmis olur. Bu yanasma coxlu esneklik verir, amma hec bir "sehirli" abstraksiya yoxdur - her seyi ozunuz qurmalisiniz.

**Laravel-in yanasmasi:** Laravel e-poct gondermeni bir sinif olaraq modellesdirir (`Mailable`). Her e-poct mesaji bir sinifdir - zarf (`Envelope`), mezmun (`Content`), ve elaqeler (`Attachments`). Bu Object-Oriented yanasma kodu cox temiz ve test edilebilir edir. Bundan elave, Markdown mail sistemi inkisaf etdirici tecrubeni daha da yaxsilasdirmir - Markdown yazirsiniz, Laravel onu gozal HTML-e cevirir.

**Queue ferqi:** Spring-de asinxron gonderme ucun `@Async` annotasiyasi ve thread pool konfiqurasiyasi lazimdir. Laravel-de ise sadece `ShouldQueue` interfeysi implement etmek kifayetdir - qalanini framework ozune helel edir. Bu, Laravel-in "developer experience" (DX) uzerinde ne qeder dusunduyunu gosterir.

## Hansi framework-de var, hansinda yoxdur?

- **Markdown mail** - Yalniz Laravel-de var. Cedvel, duyma, panel kimi komponentlerle Markdown yazmaq olar ve avtomatik responsive HTML-e cevrilir.
- **Mail onizleme** - Laravel-de Mailable sinifini birbaşa route-dan qaytararaq brauzerde onizlemek mumkundur. Spring-de bele built-in imkan yoxdur.
- **`later()` metodu** - Laravel-de mesaji gelecekde mueyyen bir vaxta planlasdirmaq bir metod cagirisla mumkundur.
- **`artisan make:mail`** - Laravel-de emr ile Mailable sinfi yaranir. Spring-de manual yaratmaq lazimdir.
- **Spring-in Thymeleaf inteqrasiyasi** - Spring-de sablon mexanizmi secimi daha genisdir (Thymeleaf, FreeMarker, Mustache). Laravel-de yalniz Blade istifade olunur.
- **Inline resource** - Spring-de `addInline()` ile sekilleri birbaşa e-pocta yerlesdirmek daha asandir.
