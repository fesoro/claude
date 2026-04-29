# Email və SMTP (Senior)

## İcmal

Go-da email göndərmə `net/smtp` standart paketi ilə mümkündür, lakin production üçün `gopkg.in/gomail.v2` daha praktikdir: HTML/text multipart, attachment, CC/BCC dəstəyi.

## Niyə Vacibdir

- Hər veb tətbiqin email funksiyası var: qeydiyyat, şifrə sıfırlama, bildirişlər, hesabatlar
- HTML email + text fallback — bütün mail client-lər üçün düzgün format
- Production-da SMTP birbaşa istifadə nadir — Mailgun, SendGrid, SES API-ları daha etibarlıdır
- Bulk email vs transactional email — fərqli strategiya

## Əsas Anlayışlar

**SMTP axını:**
1. TCP bağlantı → `EHLO` → `AUTH` → `MAIL FROM` → `RCPT TO` → `DATA` → `QUIT`
2. TLS: port 465 (implicit TLS) və ya port 587 (STARTTLS)

**Multipart email:**
- `text/plain` — fallback (mail client HTML göstərmirsə)
- `text/html` — əsas məzmun
- `multipart/alternative` — hər ikisini paketlə

**Transactional vs Bulk:**
- Transactional: qeydiyyat, şifrə sıfırlama — anında, yüksək deliverability
- Bulk: newsletter, kampaniya — throttle, unsubscribe mexanizmi lazımdır

## Praktik Baxış

**Production SMTP provider-ləri:**
- **Mailgun** — developer-friendly API, 100/gün pulsuz
- **SendGrid** — geniş analitika, 100/gün pulsuz
- **AWS SES** — ucuz, AWS ekosistemi
- **Resend** — yeni, developer UX yaxşıdır

**Nə vaxt `net/smtp` istifadə et:**
- Development/test — lokal mailhog ilə
- Korporativ SMTP server

**Nə vaxt API istifadə et:**
- Production — deliverability, bounce handling, unsubscribe

**Common mistakes:**
- Sync email göndərmə — request bloklanır
- Şəkilləri base64 embed etmək (böyük email) — CDN URL istifadə et
- HTML-i sanitize etməmək — XSS mümkündür

## Nümunələr

### Nümunə 1: Standart net/smtp — əsas istifadə

```go
package main

import (
    "crypto/tls"
    "fmt"
    "net/smtp"
)

type SMTPConfig struct {
    Host     string
    Port     int
    Username string
    Password string
    From     string
}

func sendPlainEmail(cfg SMTPConfig, to []string, subject, body string) error {
    auth := smtp.PlainAuth("", cfg.Username, cfg.Password, cfg.Host)

    msg := fmt.Sprintf(
        "From: %s\r\nTo: %s\r\nSubject: %s\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n%s",
        cfg.From, to[0], subject, body,
    )

    addr := fmt.Sprintf("%s:%d", cfg.Host, cfg.Port)
    return smtp.SendMail(addr, auth, cfg.From, to, []byte(msg))
}

func main() {
    cfg := SMTPConfig{
        Host:     "smtp.gmail.com",
        Port:     587,
        Username: "user@gmail.com",
        Password: "app-password", // Gmail app password
        From:     "user@gmail.com",
    }

    err := sendPlainEmail(cfg,
        []string{"recipient@example.com"},
        "Xoş gəldiniz!",
        "Qeydiyyatınız uğurla tamamlandı.",
    )
    if err != nil {
        fmt.Println("Göndərmə xətası:", err)
    }
}
```

### Nümunə 2: gomail ilə HTML + text multipart

```go
package main

import (
    "bytes"
    "crypto/tls"
    "html/template"
    "net/smtp"

    "gopkg.in/gomail.v2"
)

// go get gopkg.in/gomail.v2

type EmailService struct {
    dialer *gomail.Dialer
    from   string
}

func NewEmailService(host string, port int, user, pass string) *EmailService {
    d := gomail.NewDialer(host, port, user, pass)
    d.TLSConfig = &tls.Config{InsecureSkipVerify: false}
    return &EmailService{dialer: d, from: user}
}

type WelcomeData struct {
    Name      string
    LoginURL  string
    AppName   string
}

var welcomeHTML = template.Must(template.New("welcome").Parse(`
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
    <h1 style="color: #333;">Xoş gəldiniz, {{.Name}}!</h1>
    <p>{{.AppName}}-a qeydiyyatınız uğurla tamamlandı.</p>
    <p>
        <a href="{{.LoginURL}}" style="background: #007bff; color: white; padding: 12px 24px;
           text-decoration: none; border-radius: 4px; display: inline-block;">
            Daxil olun
        </a>
    </p>
    <p style="color: #666; font-size: 12px;">
        Bu emaili gözləmirdinizsə, lütfən nəzərə almayın.
    </p>
</body>
</html>
`))

var welcomeText = template.Must(template.New("welcome_text").Parse(`
Xoş gəldiniz, {{.Name}}!

{{.AppName}}-a qeydiyyatınız uğurla tamamlandı.
Daxil olmaq üçün: {{.LoginURL}}
`))

func (s *EmailService) SendWelcome(to, name, loginURL string) error {
    data := WelcomeData{
        Name:     name,
        LoginURL: loginURL,
        AppName:  "MyApp",
    }

    var htmlBuf, textBuf bytes.Buffer
    if err := welcomeHTML.Execute(&htmlBuf, data); err != nil {
        return err
    }
    if err := welcomeText.Execute(&textBuf, data); err != nil {
        return err
    }

    m := gomail.NewMessage()
    m.SetHeader("From", s.from)
    m.SetHeader("To", to)
    m.SetHeader("Subject", "MyApp-a xoş gəldiniz!")
    m.SetBody("text/plain", textBuf.String())   // fallback
    m.AddAlternative("text/html", htmlBuf.String()) // əsas

    return s.dialer.DialAndSend(m)
}

func (s *EmailService) SendPasswordReset(to, name, resetURL string) error {
    m := gomail.NewMessage()
    m.SetHeader("From", s.from)
    m.SetHeader("To", to)
    m.SetHeader("Subject", "Şifrə sıfırlama tələbi")

    html := fmt.Sprintf(`
        <h2>Salam, %s!</h2>
        <p>Şifrə sıfırlama tələbi aldıq.</p>
        <p><a href="%s">Şifrəni sıfırla</a></p>
        <p>Link 1 saat keçərlidir.</p>
        <p>Bu tələbi siz göndərməmisinizsə, nəzərə almayın.</p>
    `, name, resetURL)

    m.SetBody("text/plain", fmt.Sprintf("Şifrə sıfırlama: %s", resetURL))
    m.AddAlternative("text/html", html)

    return s.dialer.DialAndSend(m)
}
```

### Nümunə 3: Attachment ilə email

```go
package main

import "gopkg.in/gomail.v2"

func (s *EmailService) SendInvoice(to, name string, pdfPath string) error {
    m := gomail.NewMessage()
    m.SetHeader("From", s.from)
    m.SetHeader("To", to)
    m.SetHeader("Subject", "Faktura - "+name)
    m.SetBody("text/html", "<p>Fakturanız əlavə olunub.</p>")

    // PDF əlavə et
    m.Attach(pdfPath,
        gomail.SetHeader(map[string][]string{
            "Content-Disposition": {`attachment; filename="faktura.pdf"`},
        }),
    )

    // Şəkil inline embed
    m.Embed("logo.png")
    // HTML-də: <img src="cid:logo.png">

    return s.dialer.DialAndSend(m)
}
```

### Nümunə 4: Async email — goroutine ilə (request bloklanmır)

```go
package main

import (
    "context"
    "log/slog"
    "sync"
)

type AsyncEmailService struct {
    svc    *EmailService
    queue  chan emailJob
    wg     sync.WaitGroup
    logger *slog.Logger
}

type emailJob struct {
    fn func() error
}

func NewAsyncEmailService(svc *EmailService, workers int) *AsyncEmailService {
    a := &AsyncEmailService{
        svc:    svc,
        queue:  make(chan emailJob, 100),
        logger: slog.Default(),
    }

    for i := 0; i < workers; i++ {
        a.wg.Add(1)
        go a.worker()
    }

    return a
}

func (a *AsyncEmailService) worker() {
    defer a.wg.Done()
    for job := range a.queue {
        if err := job.fn(); err != nil {
            a.logger.Error("Email göndərilmədi", slog.String("error", err.Error()))
        }
    }
}

func (a *AsyncEmailService) SendWelcomeAsync(to, name, loginURL string) {
    select {
    case a.queue <- emailJob{fn: func() error {
        return a.svc.SendWelcome(to, name, loginURL)
    }}:
    default:
        a.logger.Warn("Email queue dolu, atlandı", slog.String("to", to))
    }
}

func (a *AsyncEmailService) Shutdown() {
    close(a.queue)
    a.wg.Wait()
}
```

### Nümunə 5: MailHog ilə development

```bash
# Docker ilə lokal SMTP server
docker run -d -p 1025:1025 -p 8025:8025 mailhog/mailhog

# Go-da konfiqurasiya
cfg := SMTPConfig{
    Host:     "localhost",
    Port:     1025,
    Username: "",
    Password: "",
    From:     "test@localhost",
}

# Web UI: http://localhost:8025 — bütün emailləri görürsən
```

### Nümunə 6: Mailgun API ilə (SMTP-dən üstün)

```go
package main

import (
    "context"
    "time"

    "github.com/mailgun/mailgun-go/v4"
)

// go get github.com/mailgun/mailgun-go/v4

type MailgunService struct {
    mg     *mailgun.MailgunImpl
    domain string
    from   string
}

func NewMailgunService(domain, apiKey string) *MailgunService {
    return &MailgunService{
        mg:     mailgun.NewMailgun(domain, apiKey),
        domain: domain,
        from:   "MyApp <noreply@" + domain + ">",
    }
}

func (s *MailgunService) Send(to, subject, html, text string) error {
    m := s.mg.NewMessage(s.from, subject, text, to)
    m.SetHtml(html)

    ctx, cancel := context.WithTimeout(context.Background(), 30*time.Second)
    defer cancel()

    _, _, err := s.mg.Send(ctx, m)
    return err
}
```

## Praktik Tapşırıqlar

**Tapşırıq 1:**
MailHog Docker ilə qurun. Qeydiyyat emaili göndərin: HTML şablon, istifadəçi adı, aktivasiya linki. MailHog UI-da yoxlayın.

**Tapşırıq 2:**
Şifrə sıfırlama axını: `POST /forgot-password` → token generate → email göndər → `GET /reset-password?token=X` → şifrəni yenilə. Token 1 saat keçərli.

**Tapşırıq 3:**
Async email queue: 3 worker goroutine, 100 buffered queue. Usecase-dən `SendWelcomeAsync` çağırın — HTTP response gecikmə olmasın.

## PHP ilə Müqayisə

Laravel `Mail` facade `Mailable` siniflərini queue-ya göndərir. Go-da eyni pattern goroutine + channel ilə tətbiq edilir.

```php
// Laravel — Mail facade + Mailable
class WelcomeEmail extends Mailable
{
    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Xoş Gəldiniz!');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.welcome');
    }
}

// Göndər (sync)
Mail::to($user->email)->send(new WelcomeEmail($user));

// Queue-ya göndər (async)
Mail::to($user->email)->queue(new WelcomeEmail($user));
```

```go
// Go — gomail + async worker
emailSvc := NewEmailService("smtp.mailgun.org", 587, user, pass)
asyncSvc := NewAsyncEmailService(emailSvc, 3) // 3 worker

// Sync
emailSvc.SendWelcome(user.Email, user.Name, loginURL)

// Async — request bloklanmır
asyncSvc.SendWelcomeAsync(user.Email, user.Name, loginURL)
```

**Əsas fərqlər:**
- Laravel: `Mailable` sinif — template, subject, attachment — bir yerdə; Go: `gomail.Message` manual quraşdırma
- Laravel queue: Redis/database driver — persistent; Go async: in-memory channel — process restart-da itirilir
- Laravel: `Mail::fake()` ilə test asandır; Go: interface inject edərək mock lazımdır
- Production retry: Laravel Horizon avtomatik idarə edir; Go-da özün retry yazmaq lazımdır

## Əlaqəli Mövzular

- [10-text-templates.md](10-text-templates.md) — HTML/text template
- [65-jwt-and-auth.md](../advanced/10-jwt-and-auth.md) — Token generasiya
- [20-errgroup.md](20-errgroup.md) — Parallel göndərmə
- [24-cron-scheduler.md](24-cron-scheduler.md) — Scheduled email
