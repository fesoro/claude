# Spring Mail — Geniş İzah

## Mündəricat
1. [Spring Mail quraşdırması](#spring-mail-quraşdırması)
2. [JavaMailSender](#javamailsender)
3. [MimeMessage (HTML, attachment)](#mimemessage-html-attachment)
4. [Thymeleaf şablonu ilə mail](#thymeleaf-şablonu-ilə-mail)
5. [Async mail göndərmə](#async-mail-göndərmə)
6. [İntervyu Sualları](#intervyu-sualları)

---

## Spring Mail quraşdırması

```xml
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-mail</artifactId>
</dependency>

<!-- HTML şablon üçün (optional) -->
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-thymeleaf</artifactId>
</dependency>
```

```yaml
# application.yml
spring:
  mail:
    host: smtp.gmail.com
    port: 587
    username: ${MAIL_USERNAME}
    password: ${MAIL_PASSWORD}  # App password (2FA aktiv olarsa)
    properties:
      mail:
        smtp:
          auth: true
          starttls:
            enable: true
            required: true
        debug: false  # SMTP debug log

# Öz SMTP server
# spring:
#   mail:
#     host: smtp.company.com
#     port: 25
#     username: noreply@company.com
#     password: password
```

---

## JavaMailSender

```java
@Service
public class EmailService {

    private final JavaMailSender mailSender;

    @Value("${spring.mail.username}")
    private String fromEmail;

    public EmailService(JavaMailSender mailSender) {
        this.mailSender = mailSender;
    }

    // Sadə text mail
    public void sendSimpleEmail(String to, String subject, String text) {
        SimpleMailMessage message = new SimpleMailMessage();
        message.setFrom(fromEmail);
        message.setTo(to);
        message.setSubject(subject);
        message.setText(text);

        mailSender.send(message);
    }

    // Bir neçə alıcıya
    public void sendEmailToMultiple(String[] to, String subject, String text) {
        SimpleMailMessage message = new SimpleMailMessage();
        message.setFrom(fromEmail);
        message.setTo(to);
        message.setCc("manager@example.com");
        message.setBcc("audit@example.com");
        message.setSubject(subject);
        message.setText(text);

        mailSender.send(message);
    }

    // Reply-To ilə
    public void sendWithReplyTo(String to, String replyTo,
                                 String subject, String text) {
        SimpleMailMessage message = new SimpleMailMessage();
        message.setFrom(fromEmail);
        message.setTo(to);
        message.setReplyTo(replyTo);
        message.setSubject(subject);
        message.setText(text);

        mailSender.send(message);
    }
}
```

---

## MimeMessage (HTML, attachment)

```java
@Service
public class RichEmailService {

    private final JavaMailSender mailSender;

    @Value("${spring.mail.username}")
    private String fromEmail;

    // HTML mail
    public void sendHtmlEmail(String to, String subject, String htmlContent) {
        try {
            MimeMessage message = mailSender.createMimeMessage();
            MimeMessageHelper helper = new MimeMessageHelper(message, "UTF-8");

            helper.setFrom(fromEmail);
            helper.setTo(to);
            helper.setSubject(subject);
            helper.setText(htmlContent, true); // true — HTML

            mailSender.send(message);
        } catch (MessagingException e) {
            throw new RuntimeException("Mail göndərilə bilmədi", e);
        }
    }

    // Attachment ilə mail
    public void sendEmailWithAttachment(String to, String subject,
                                        String text, File attachment) {
        try {
            MimeMessage message = mailSender.createMimeMessage();
            MimeMessageHelper helper = new MimeMessageHelper(
                message, true, "UTF-8"); // true — multipart

            helper.setFrom(fromEmail);
            helper.setTo(to);
            helper.setSubject(subject);
            helper.setText(text);

            // File attachment
            helper.addAttachment(attachment.getName(),
                                 new FileSystemResource(attachment));

            mailSender.send(message);
        } catch (MessagingException e) {
            throw new RuntimeException("Mail göndərilə bilmədi", e);
        }
    }

    // Resource (classpath) attachment
    public void sendEmailWithResourceAttachment(String to, String subject,
                                                 String text) {
        try {
            MimeMessage message = mailSender.createMimeMessage();
            MimeMessageHelper helper = new MimeMessageHelper(message, true);

            helper.setFrom(fromEmail);
            helper.setTo(to);
            helper.setSubject(subject);

            // Inline image
            ClassPathResource image = new ClassPathResource("static/images/logo.png");
            helper.setText("<html><body><img src='cid:logo'/><br/>" + text + "</body></html>",
                          true);
            helper.addInline("logo", image);

            // PDF attachment
            ClassPathResource pdf = new ClassPathResource("static/docs/terms.pdf");
            helper.addAttachment("şərtlər.pdf", pdf);

            mailSender.send(message);
        } catch (MessagingException e) {
            throw new RuntimeException("Mail göndərilə bilmədi", e);
        }
    }
}
```

---

## Thymeleaf şablonu ilə mail

```java
@Service
public class TemplateEmailService {

    private final JavaMailSender mailSender;
    private final SpringTemplateEngine templateEngine;

    @Value("${spring.mail.username}")
    private String fromEmail;

    // Thymeleaf şablonu ilə HTML mail
    public void sendWelcomeEmail(User user) {
        try {
            Context context = new Context(Locale.of("az")); // Azərbaycan dili
            context.setVariable("user", user);
            context.setVariable("loginUrl", "https://app.example.com/login");
            context.setVariable("year", Year.now().getValue());

            String htmlContent = templateEngine.process("emails/welcome", context);

            MimeMessage message = mailSender.createMimeMessage();
            MimeMessageHelper helper = new MimeMessageHelper(message, "UTF-8");

            helper.setFrom(fromEmail, "MyApp Team");
            helper.setTo(user.getEmail());
            helper.setSubject("Xoş gəldiniz, " + user.getName() + "!");
            helper.setText(htmlContent, true);

            mailSender.send(message);
        } catch (MessagingException | UnsupportedEncodingException e) {
            throw new RuntimeException("Welcome mail göndərilmədi", e);
        }
    }

    public void sendOrderConfirmation(Order order) {
        try {
            Context context = new Context();
            context.setVariable("order", order);
            context.setVariable("items", order.getItems());
            context.setVariable("total", order.getTotalAmount());

            String htmlContent = templateEngine.process("emails/order-confirmation", context);

            MimeMessage message = mailSender.createMimeMessage();
            MimeMessageHelper helper = new MimeMessageHelper(message, true, "UTF-8");

            helper.setFrom(fromEmail);
            helper.setTo(order.getUser().getEmail());
            helper.setSubject("Sifariş Təsdiqi #" + order.getId());
            helper.setText(htmlContent, true);

            // Invoice PDF əlavə et
            byte[] invoicePdf = generateInvoicePdf(order);
            helper.addAttachment("invoice-" + order.getId() + ".pdf",
                new ByteArrayResource(invoicePdf));

            mailSender.send(message);
        } catch (MessagingException e) {
            throw new RuntimeException("Order mail göndərilmədi", e);
        }
    }
}
```

**Thymeleaf şablonu** (`resources/templates/emails/welcome.html`):
```html
<!DOCTYPE html>
<html xmlns:th="http://www.thymeleaf.org">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .button { background-color: #007bff; color: white; padding: 10px 20px;
                  text-decoration: none; border-radius: 4px; }
    </style>
</head>
<body>
<div class="container">
    <h2>Xoş gəldiniz, <span th:text="${user.name}">İstifadəçi</span>!</h2>
    <p>Hesabınız uğurla yaradıldı.</p>
    <a th:href="${loginUrl}" class="button">Daxil ol</a>
    <p style="color: gray; font-size: 12px;">
        &copy; <span th:text="${year}">2026</span> MyApp. Bütün hüquqlar qorunur.
    </p>
</div>
</body>
</html>
```

---

## Async mail göndərmə

```java
@Service
public class AsyncEmailService {

    private final JavaMailSender mailSender;

    // @Async — mail göndərmə ayrı thread-də
    @Async
    public CompletableFuture<Void> sendEmailAsync(String to, String subject,
                                                    String content) {
        try {
            MimeMessage message = mailSender.createMimeMessage();
            MimeMessageHelper helper = new MimeMessageHelper(message, "UTF-8");
            helper.setTo(to);
            helper.setSubject(subject);
            helper.setText(content, true);
            mailSender.send(message);
            return CompletableFuture.completedFuture(null);
        } catch (MessagingException e) {
            CompletableFuture<Void> future = new CompletableFuture<>();
            future.completeExceptionally(e);
            return future;
        }
    }

    // Mail göndərməyi queue-ya əlavə et (Kafka/RabbitMQ ilə)
    // Controller:
    // emailProducer.publish(new EmailEvent(to, subject, content));
    // Consumer:
    // emailService.sendEmail(event);
}

// Async-i aktivləşdirmək
@SpringBootApplication
@EnableAsync
public class App { }
```

---

## İntervyu Sualları

### 1. SimpleMailMessage vs MimeMessage fərqi?
**Cavab:** `SimpleMailMessage` — yalnız sadə text mail üçün, çox az kod. `MimeMessage` + `MimeMessageHelper` — HTML, attachment, inline image, encoding dəstəkləyir. Multipart (attachment) üçün `MimeMessageHelper(message, true)` — ikinci argument `true` olmalıdır.

### 2. Mail göndərməni niyə async etmək lazımdır?
**Cavab:** SMTP serverlə kommunikasiya şəbəkə I/O əməliyyatıdır — gecikə bilər. Sync halda istifadəçi API cavabı gözləməli olur. `@Async` ilə mail göndərmə ayrı thread-də işləyir, API dərhal cavab verir. Production-da Kafka/RabbitMQ queue tövsiyə olunur — SMTP server çökərsə mail itirilmir.

### 3. Gmail SMTP istifadə edərkən nə tələb olunur?
**Cavab:** Gmail 2FA aktiv olarsa, "App Password" yaratmaq lazımdır (adi şifrə işləmir). `spring.mail.properties.mail.smtp.starttls.enable=true` aktivləşdirilməlidir. Port 587 (STARTTLS) yaxud 465 (SSL/TLS) istifadə olunur.

### 4. Mail şablonlarını necə idarə etmək olar?
**Cavab:** Thymeleaf ilə `resources/templates/emails/` qovluğunda HTML şablonlar saxlanır. `SpringTemplateEngine.process()` şablonu doldurub HTML string qaytarır. Bu yanaşma HTML-i kod-dan ayırır, design team-in dəyişiklik edə bilməsinə imkan verir.

### 5. Mail göndərmə uğursuz olduqda necə idarə etmək?
**Cavab:** Try-catch ilə tutub `MessagingException` handle etmək. Retry mexanizmi əlavə etmək (Spring Retry `@Retryable`). Production-da mesaj queue (Kafka/RabbitMQ) istifadə etmək — SMTP uğursuz olduqda mesaj queue-da qalır, sonra yenidən cəhd edilir.

*Son yenilənmə: 2026-04-10*
