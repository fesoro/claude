package az.ecommerce.user.infrastructure.persistence;

import com.fasterxml.jackson.databind.ObjectMapper;
import jakarta.persistence.*;

import java.io.IOException;
import java.time.Instant;
import java.util.List;
import java.util.UUID;

/**
 * === JPA ENTITY (infrastructure layer) ===
 *
 * Laravel: src/User/Infrastructure/Models/UserModel.php (Eloquent)
 * Spring: JPA @Entity — domain User-dən AYRIDIR.
 *
 * Niyə ayrı? Bu, "Persistence Ignorance" prinsipidir:
 *   - Domain User saf POJO (test edilməsi asan, framework asılılığı yoxdur)
 *   - Infrastructure UserEntity JPA məntiqini saxlayır
 *   - Repository impl ikisi arasında map edir
 */
@Entity
@Table(name = "users")
public class UserEntity {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    @Column(nullable = false, unique = true, columnDefinition = "CHAR(36)")
    private UUID uuid;

    @Column(nullable = false)
    private String name;

    @Column(nullable = false, unique = true)
    private String email;

    @Column(name = "email_verified_at")
    private Instant emailVerifiedAt;

    @Column(nullable = false)
    private String password;

    @Column(name = "remember_token")
    private String rememberToken;

    @Column(name = "two_factor_enabled", nullable = false)
    private boolean twoFactorEnabled = false;

    @Column(name = "two_factor_secret")
    private String twoFactorSecret;

    @Column(name = "two_factor_backup_codes", columnDefinition = "JSON")
    private String backupCodesJson;

    @Version
    private Long version;

    @Column(name = "tenant_id", columnDefinition = "CHAR(36)")
    private UUID tenantId;

    @Column(name = "created_at", nullable = false, updatable = false)
    private Instant createdAt = Instant.now();

    @Column(name = "updated_at")
    private Instant updatedAt = Instant.now();

    @PreUpdate
    void onUpdate() {
        this.updatedAt = Instant.now();
    }

    public List<String> getBackupCodes(ObjectMapper mapper) {
        if (backupCodesJson == null) return null;
        try {
            return mapper.readValue(backupCodesJson, mapper.getTypeFactory()
                    .constructCollectionType(List.class, String.class));
        } catch (IOException e) {
            throw new RuntimeException(e);
        }
    }

    public void setBackupCodes(List<String> codes, ObjectMapper mapper) {
        if (codes == null) { this.backupCodesJson = null; return; }
        try {
            this.backupCodesJson = mapper.writeValueAsString(codes);
        } catch (IOException e) {
            throw new RuntimeException(e);
        }
    }

    public Long getId() { return id; }
    public UUID getUuid() { return uuid; }
    public void setUuid(UUID uuid) { this.uuid = uuid; }
    public String getName() { return name; }
    public void setName(String name) { this.name = name; }
    public String getEmail() { return email; }
    public void setEmail(String email) { this.email = email; }
    public String getPassword() { return password; }
    public void setPassword(String password) { this.password = password; }
    public boolean isTwoFactorEnabled() { return twoFactorEnabled; }
    public void setTwoFactorEnabled(boolean v) { this.twoFactorEnabled = v; }
    public String getTwoFactorSecret() { return twoFactorSecret; }
    public void setTwoFactorSecret(String secret) { this.twoFactorSecret = secret; }
    public Long getVersion() { return version; }
    public Instant getCreatedAt() { return createdAt; }
}
