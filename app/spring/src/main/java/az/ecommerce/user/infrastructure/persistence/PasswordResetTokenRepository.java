package az.ecommerce.user.infrastructure.persistence;

import org.springframework.data.jpa.repository.JpaRepository;

public interface PasswordResetTokenRepository extends JpaRepository<PasswordResetTokenEntity, String> {
}
