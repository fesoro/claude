package az.ecommerce.user.application.command;

import az.ecommerce.shared.application.bus.CommandHandler;
import az.ecommerce.shared.domain.exception.DomainException;
import az.ecommerce.shared.infrastructure.bus.EventDispatcher;
import az.ecommerce.user.domain.User;
import az.ecommerce.user.domain.repository.UserRepository;
import az.ecommerce.user.domain.valueobject.Email;
import az.ecommerce.user.domain.valueobject.Password;
import org.springframework.stereotype.Service;

import java.util.UUID;

/**
 * Laravel: RegisterUserHandler.php
 * Spring: @Service — CommandBus avtomatik tapacaq.
 *
 * Pipeline-da işləyir:
 *   Logging → Idempotency → Validation → Transaction → RetryOnConcurrency → Handler
 */
@Service
public class RegisterUserHandler implements CommandHandler<RegisterUserCommand, UUID> {

    private final UserRepository repository;
    private final EventDispatcher eventDispatcher;

    public RegisterUserHandler(UserRepository repository, EventDispatcher eventDispatcher) {
        this.repository = repository;
        this.eventDispatcher = eventDispatcher;
    }

    @Override
    public UUID handle(RegisterUserCommand command) {
        Email email = new Email(command.email());

        if (repository.existsByEmail(email)) {
            throw new DomainException("Bu email artıq qeydiyyatdadır: " + command.email());
        }

        User user = User.register(command.name(), email, Password.fromPlaintext(command.password()));
        repository.save(user);
        eventDispatcher.dispatchAll(user);

        return user.id().value();
    }
}
