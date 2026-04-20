package az.ecommerce.user.domain.repository;

import az.ecommerce.user.domain.User;
import az.ecommerce.user.domain.valueobject.Email;
import az.ecommerce.user.domain.valueobject.UserId;

import java.util.Optional;

/**
 * Laravel: src/User/Domain/Repositories/UserRepositoryInterface.php
 * Spring: domain layer-də interface, infrastructure-də impl.
 */
public interface UserRepository {

    User save(User user);

    Optional<User> findById(UserId id);

    Optional<User> findByEmail(Email email);

    boolean existsByEmail(Email email);
}
