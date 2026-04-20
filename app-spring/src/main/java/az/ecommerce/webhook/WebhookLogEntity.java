package az.ecommerce.webhook;

import jakarta.persistence.*;

import java.time.Instant;
import java.util.UUID;

@Entity
@Table(name = "webhook_logs")
public class WebhookLogEntity {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    @Column(name = "webhook_id", nullable = false, columnDefinition = "CHAR(36)")
    private UUID webhookId;

    @Column(name = "event_type", nullable = false, length = 64)
    private String eventType;

    @Column(nullable = false, columnDefinition = "JSON")
    private String payload;

    @Column(name = "response_status")
    private Integer responseStatus;

    @Column(name = "response_body", columnDefinition = "TEXT")
    private String responseBody;

    @Column(name = "attempt_count", nullable = false)
    private int attemptCount = 1;

    @Column(nullable = false)
    private boolean success = false;

    @Column(name = "error_message", length = 1024)
    private String errorMessage;

    @Column(name = "sent_at", nullable = false, updatable = false)
    private Instant sentAt = Instant.now();

    public void setWebhookId(UUID id) { this.webhookId = id; }
    public void setEventType(String t) { this.eventType = t; }
    public void setPayload(String p) { this.payload = p; }
    public void setResponseStatus(Integer s) { this.responseStatus = s; }
    public void setResponseBody(String b) { this.responseBody = b; }
    public void setAttemptCount(int c) { this.attemptCount = c; }
    public void setSuccess(boolean s) { this.success = s; }
    public void setErrorMessage(String m) { this.errorMessage = m; }
}
