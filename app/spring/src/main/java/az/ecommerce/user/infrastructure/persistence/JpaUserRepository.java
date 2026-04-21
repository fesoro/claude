package az.ecommerce.user.infrastructure.persistence;

import org.springframework.data.jpa.repository.JpaRepository;

import java.util.Optional;
import java.util.UUID;

/**
 * Spring Data JPA — Laravel Eloquent-in qarşılığı.
 * Bu interface-i implement etməyə ehtiyac yoxdur — Spring runtime-da yaradır.
 */
public interface JpaUserRepository extends JpaRepository<UserEntity, Long> {

    Optional<UserEntity> findByUuid(UUID uuid);

    Optional<UserEntity> findByEmail(String email);

    boolean existsByEmail(String email);
}
