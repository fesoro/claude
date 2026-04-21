package az.ecommerce.user.application.command;

import az.ecommerce.shared.application.bus.Command;
import jakarta.validation.constraints.Email;
import jakarta.validation.constraints.NotBlank;
import jakarta.validation.constraints.Size;

/**
 * Laravel: RegisterUserCommand.php
 * Bean Validation annotation-ları ValidationMiddleware tərəfindən yoxlanılacaq.
 */
public record RegisterUserCommand(
        @NotBlank(message = "Ad boş ola bilməz")
        @Size(min = 2, max = 100)
        String name,

        @NotBlank @Email(message = "Email düzgün formatda olmalıdır")
        String email,

        @NotBlank @Size(min = 8, message = "Şifrə ən azı 8 simvol olmalıdır")
        String password
) implements Command<java.util.UUID> {}
