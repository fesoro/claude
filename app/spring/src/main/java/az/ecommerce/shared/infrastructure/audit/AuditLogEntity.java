package az.ecommerce.shared.infrastructure.audit;

import jakarta.persistence.*;

import java.time.Instant;
import java.util.UUID;

/**
 * Laravel: AuditMiddleware-in yazdığı audit_logs cədvəli.
 */
@Entity
@Table(name = "audit_logs")
public class AuditLogEntity {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    @Column(name = "user_id", columnDefinition = "CHAR(36)")
    private UUID userId;

    @Column(name = "correlation_id", length = 64)
    private String correlationId;

    @Column(nullable = false, length = 64)
    private String action;

    @Column(name = "entity_type", length = 64)
    private String entityType;

    @Column(name = "entity_id", length = 64)
    private String entityId;

    @Column(nullable = false, length = 8)
    private String method;

    @Column(nullable = false, length = 512)
    private String uri;

    @Column(name = "ip_address", length = 45)
    private String ipAddress;

    @Column(name = "user_agent", length = 512)
    private String userAgent;

    @Column(name = "request_payload", columnDefinition = "JSON")
    private String requestPayload;

    @Column(name = "response_status")
    private Integer responseStatus;

    @Column(columnDefinition = "JSON")
    private String metadata;

    @Column(name = "created_at", nullable = false, updatable = false)
    private Instant createdAt = Instant.now();

    public Long getId() { return id; }
    public void setUserId(UUID id) { this.userId = id; }
    public void setCorrelationId(String c) { this.correlationId = c; }
    public void setAction(String a) { this.action = a; }
    public void setEntityType(String t) { this.entityType = t; }
    public void setEntityId(String id) { this.entityId = id; }
    public void setMethod(String m) { this.method = m; }
    public void setUri(String u) { this.uri = u; }
    public void setIpAddress(String ip) { this.ipAddress = ip; }
    public void setUserAgent(String ua) { this.userAgent = ua; }
    public void setRequestPayload(String p) { this.requestPayload = p; }
    public void setResponseStatus(Integer s) { this.responseStatus = s; }
    public void setMetadata(String m) { this.metadata = m; }
}
