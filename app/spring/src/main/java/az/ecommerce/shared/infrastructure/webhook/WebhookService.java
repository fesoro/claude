package az.ecommerce.shared.infrastructure.webhook;

import az.ecommerce.webhook.WebhookEntity;
import az.ecommerce.webhook.WebhookLogEntity;
import az.ecommerce.webhook.WebhookLogRepository;
import az.ecommerce.webhook.WebhookRepository;
import com.fasterxml.jackson.core.type.TypeReference;
import com.fasterxml.jackson.databind.ObjectMapper;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;
import org.springframework.http.MediaType;
import org.springframework.stereotype.Service;
import org.springframework.web.client.RestClient;

import javax.crypto.Mac;
import javax.crypto.spec.SecretKeySpec;
import java.nio.charset.StandardCharsets;
import java.util.HexFormat;
import java.util.List;

/**
 * Laravel: src/Shared/Infrastructure/Webhook/WebhookService.php
 *
 * Spring: HMAC-SHA256 signing + RestClient ilə HTTP POST.
 * Hər delivery cəhdi webhook_logs cədvəlinə yazılır.
 */
@Service
public class WebhookService {

    private static final Logger log = LoggerFactory.getLogger(WebhookService.class);

    private final WebhookRepository webhookRepository;
    private final WebhookLogRepository logRepository;
    private final ObjectMapper objectMapper;
    private final RestClient restClient = RestClient.create();

    public WebhookService(WebhookRepository webhookRepository,
                          WebhookLogRepository logRepository,
                          ObjectMapper objectMapper) {
        this.webhookRepository = webhookRepository;
        this.logRepository = logRepository;
        this.objectMapper = objectMapper;
    }

    /**
     * Bir event üçün bütün aktiv webhook-lara göndərir.
     * SendWebhookJob bunu RabbitMQ-dan tetikləyir.
     */
    public void deliver(String eventType, String payload) {
        List<WebhookEntity> webhooks = webhookRepository.findAll().stream()
                .filter(WebhookEntity::isActive)
                .filter(w -> subscribesToEvent(w, eventType))
                .toList();

        for (WebhookEntity webhook : webhooks) {
            sendOne(webhook, eventType, payload);
        }
    }

    /** events sütunu JSON array-dir: ["order.created", "payment.completed"] */
    private boolean subscribesToEvent(WebhookEntity webhook, String eventType) {
        try {
            List<String> events = objectMapper.readValue(webhook.getEvents(),
                    new TypeReference<List<String>>() {});
            return events.contains(eventType);
        } catch (Exception ex) {
            log.warn("Webhook events parse xətası, fallback substring: {}", ex.getMessage());
            return webhook.getEvents().contains(eventType);
        }
    }

    private void sendOne(WebhookEntity webhook, String eventType, String payload) {
        WebhookLogEntity logEntry = new WebhookLogEntity();
        logEntry.setWebhookId(webhook.getId());
        logEntry.setEventType(eventType);
        logEntry.setPayload(payload);

        try {
            String signature = computeHmac(payload, webhook.getSecret());
            var response = restClient.post()
                    .uri(webhook.getUrl())
                    .contentType(MediaType.APPLICATION_JSON)
                    .header("X-Webhook-Signature", "sha256=" + signature)
                    .header("X-Webhook-Event", eventType)
                    .body(payload)
                    .retrieve()
                    .toEntity(String.class);

            logEntry.setResponseStatus(response.getStatusCode().value());
            logEntry.setResponseBody(response.getBody());
            logEntry.setSuccess(response.getStatusCode().is2xxSuccessful());
            log.info("Webhook delivered: {} → {} ({})",
                    eventType, webhook.getUrl(), response.getStatusCode());
        } catch (Exception ex) {
            logEntry.setSuccess(false);
            logEntry.setErrorMessage(ex.getMessage());
            log.warn("Webhook xətası {}: {}", webhook.getUrl(), ex.getMessage());
        }
        logRepository.save(logEntry);
    }

    /** HMAC-SHA256(payload, secret) → hex string */
    private String computeHmac(String payload, String secret) {
        try {
            Mac mac = Mac.getInstance("HmacSHA256");
            mac.init(new SecretKeySpec(secret.getBytes(StandardCharsets.UTF_8), "HmacSHA256"));
            return HexFormat.of().formatHex(mac.doFinal(payload.getBytes(StandardCharsets.UTF_8)));
        } catch (Exception e) {
            throw new RuntimeException("HMAC compute xətası", e);
        }
    }
}
