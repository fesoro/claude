package az.ecommerce.user.infrastructure.persistence;

import jakarta.persistence.*;

import java.time.Instant;

/**
 * Laravel: 2014_10_12_100000_create_password_reset_tokens_table
 * Migration: user/V2__create_password_reset_tokens.sql
 */
@Entity
@Table(name = "password_reset_tokens")
public class PasswordResetTokenEntity {

    @Id
    @Column(nullable = false)
    private String email;

    @Column(nullable = false)
    private String token;

    @Column(name = "created_at")
    private Instant createdAt = Instant.now();

    public String getEmail() { return email; }
    public void setEmail(String e) { this.email = e; }
    public String getToken() { return token; }
    public void setToken(String t) { this.token = t; }
    public Instant getCreatedAt() { return createdAt; }

    public boolean isExpired() {
        return createdAt.plusSeconds(3600).isBefore(Instant.now());
    }
}
