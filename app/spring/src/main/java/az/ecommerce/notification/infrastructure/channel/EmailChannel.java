package az.ecommerce.notification.infrastructure.channel;

import jakarta.mail.MessagingException;
import jakarta.mail.internet.MimeMessage;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;
import org.springframework.mail.javamail.JavaMailSender;
import org.springframework.mail.javamail.MimeMessageHelper;
import org.springframework.stereotype.Component;
import org.thymeleaf.context.Context;
import org.thymeleaf.spring6.SpringTemplateEngine;

import java.util.Map;

/**
 * Laravel: src/Notification/Infrastructure/Channels/EmailChannel.php
 * Spring: JavaMailSender + Thymeleaf template engine.
 */
@Component
public class EmailChannel {

    private static final Logger log = LoggerFactory.getLogger(EmailChannel.class);
    private static final String FROM = "noreply@ecommerce.az";

    private final JavaMailSender mailSender;
    private final SpringTemplateEngine templateEngine;

    public EmailChannel(JavaMailSender mailSender, SpringTemplateEngine templateEngine) {
        this.mailSender = mailSender;
        this.templateEngine = templateEngine;
    }

    public void send(String to, String subject, String template, Map<String, Object> variables) {
        try {
            Context context = new Context();
            variables.forEach(context::setVariable);
            String body = templateEngine.process(template, context);

            MimeMessage message = mailSender.createMimeMessage();
            MimeMessageHelper helper = new MimeMessageHelper(message, true, "UTF-8");
            helper.setFrom(FROM);
            helper.setTo(to);
            helper.setSubject(subject);
            helper.setText(body, true);

            mailSender.send(message);
            log.info("Email göndərildi: {} → {}", subject, to);
        } catch (MessagingException ex) {
            log.error("Email xətası: {}", ex.getMessage(), ex);
            throw new RuntimeException(ex);
        }
    }
}
