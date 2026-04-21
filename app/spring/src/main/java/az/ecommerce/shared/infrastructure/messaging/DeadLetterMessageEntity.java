package az.ecommerce.shared.infrastructure.messaging;

import jakarta.persistence.*;

import java.time.Instant;
import java.util.UUID;

/**
 * Laravel: DeadLetterQueue → dead_letter_messages cədvəli.
 * Spring: RabbitMQ DLX-dən gələn mesajlar bura yazılır,
 * admin onları yenidən cəhd edə bilər (FailedJobController).
 */
@Entity
@Table(name = "dead_letter_messages")
public class DeadLetterMessageEntity {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    @Column(name = "original_message_id", columnDefinition = "CHAR(36)")
    private UUID originalMessageId;

    @Column(name = "queue_name", nullable = false, length = 128)
    private String queueName;

    @Column(name = "event_type", nullable = false, length = 128)
    private String eventType;

    @Column(nullable = false, columnDefinition = "JSON")
    private String payload;

    @Column(name = "error_message", columnDefinition = "TEXT")
    private String errorMessage;

    @Column(name = "error_class")
    private String errorClass;

    @Column(name = "stack_trace", columnDefinition = "TEXT")
    private String stackTrace;

    @Column(name = "failed_at", nullable = false, updatable = false)
    private Instant failedAt = Instant.now();

    @Column(nullable = false)
    private boolean retried = false;

    public Long getId() { return id; }
    public UUID getOriginalMessageId() { return originalMessageId; }
    public void setOriginalMessageId(UUID id) { this.originalMessageId = id; }
    public String getQueueName() { return queueName; }
    public void setQueueName(String q) { this.queueName = q; }
    public String getEventType() { return eventType; }
    public void setEventType(String e) { this.eventType = e; }
    public String getPayload() { return payload; }
    public void setPayload(String p) { this.payload = p; }
    public void setErrorMessage(String m) { this.errorMessage = m; }
    public void setErrorClass(String c) { this.errorClass = c; }
    public void setStackTrace(String s) { this.stackTrace = s; }
    public boolean isRetried() { return retried; }
    public void markRetried() { this.retried = true; }
}
