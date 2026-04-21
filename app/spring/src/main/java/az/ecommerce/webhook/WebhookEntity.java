package az.ecommerce.webhook;

import jakarta.persistence.*;

import java.time.Instant;
import java.util.UUID;

@Entity
@Table(name = "webhooks")
public class WebhookEntity {

    @Id
    @Column(columnDefinition = "CHAR(36)")
    private UUID id;

    @Column(name = "user_id", nullable = false, columnDefinition = "CHAR(36)")
    private UUID userId;

    @Column(nullable = false, length = 2048)
    private String url;

    @Column(nullable = false)
    private String secret;

    @Column(nullable = false, columnDefinition = "JSON")
    private String events;

    @Column(name = "is_active", nullable = false)
    private boolean isActive = true;

    @Column(name = "created_at", nullable = false, updatable = false)
    private Instant createdAt = Instant.now();

    public UUID getId() { return id; }
    public void setId(UUID id) { this.id = id; }
    public UUID getUserId() { return userId; }
    public void setUserId(UUID u) { this.userId = u; }
    public String getUrl() { return url; }
    public void setUrl(String u) { this.url = u; }
    public String getSecret() { return secret; }
    public void setSecret(String s) { this.secret = s; }
    public String getEvents() { return events; }
    public void setEvents(String e) { this.events = e; }
    public boolean isActive() { return isActive; }
    public void setActive(boolean a) { this.isActive = a; }
}
