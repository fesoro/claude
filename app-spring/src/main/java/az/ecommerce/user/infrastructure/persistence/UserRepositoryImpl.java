package az.ecommerce.user.infrastructure.persistence;

import az.ecommerce.user.domain.User;
import az.ecommerce.user.domain.repository.UserRepository;
import az.ecommerce.user.domain.valueobject.Email;
import az.ecommerce.user.domain.valueobject.Password;
import az.ecommerce.user.domain.valueobject.UserId;
import com.fasterxml.jackson.databind.ObjectMapper;
import org.springframework.stereotype.Repository;

import java.util.Optional;

/**
 * Domain UserRepository → JPA UserEntity arasında map edən adapter.
 * Laravel: src/User/Infrastructure/Repositories/EloquentUserRepository.php
 */
@Repository
public class UserRepositoryImpl implements UserRepository {

    private final JpaUserRepository jpa;
    private final ObjectMapper objectMapper;

    public UserRepositoryImpl(JpaUserRepository jpa, ObjectMapper objectMapper) {
        this.jpa = jpa;
        this.objectMapper = objectMapper;
    }

    @Override
    public User save(User user) {
        UserEntity entity = jpa.findByUuid(user.id().value())
                .orElseGet(() -> {
                    UserEntity newEntity = new UserEntity();
                    newEntity.setUuid(user.id().value());
                    return newEntity;
                });
        entity.setName(user.name());
        entity.setEmail(user.email().value());
        entity.setPassword(user.password().hash());
        entity.setTwoFactorEnabled(user.twoFactorEnabled());
        entity.setTwoFactorSecret(user.twoFactorSecret());
        entity.setBackupCodes(user.backupCodes(), objectMapper);
        jpa.save(entity);
        return user;
    }

    @Override
    public Optional<User> findById(UserId id) {
        return jpa.findByUuid(id.value()).map(this::toDomain);
    }

    @Override
    public Optional<User> findByEmail(Email email) {
        return jpa.findByEmail(email.value()).map(this::toDomain);
    }

    @Override
    public boolean existsByEmail(Email email) {
        return jpa.existsByEmail(email.value());
    }

    private User toDomain(UserEntity e) {
        return User.reconstitute(
                new UserId(e.getUuid()),
                e.getName(),
                new Email(e.getEmail()),
                Password.fromHash(e.getPassword()),
                e.isTwoFactorEnabled(),
                e.getTwoFactorSecret(),
                e.getBackupCodes(objectMapper));
    }
}
