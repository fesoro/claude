package az.ecommerce.shared.infrastructure.messaging;

import jakarta.persistence.*;

import java.time.Instant;
import java.util.UUID;

/**
 * Laravel: InboxStore → inbox_messages cədvəli.
 * Spring: gələn message-ləri exactly-once işlətmək üçün — IdempotentConsumer
 * əvvəlcə bu cədvəldə message_id-ni yoxlayır.
 */
@Entity
@Table(name = "inbox_messages")
public class InboxMessageEntity {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    @Column(name = "message_id", nullable = false, unique = true, columnDefinition = "CHAR(36)")
    private UUID messageId;

    @Column(name = "event_type", nullable = false, length = 128)
    private String eventType;

    @Column(nullable = false, columnDefinition = "JSON")
    private String payload;

    @Column(name = "received_at", nullable = false, updatable = false)
    private Instant receivedAt = Instant.now();

    @Column(name = "processed_at")
    private Instant processedAt;

    public Long getId() { return id; }
    public UUID getMessageId() { return messageId; }
    public void setMessageId(UUID id) { this.messageId = id; }
    public String getEventType() { return eventType; }
    public void setEventType(String t) { this.eventType = t; }
    public String getPayload() { return payload; }
    public void setPayload(String p) { this.payload = p; }
    public Instant getProcessedAt() { return processedAt; }
    public void markProcessed() { this.processedAt = Instant.now(); }
}
