package az.ecommerce.user.application.dto;

import az.ecommerce.user.domain.User;

import java.util.UUID;

/**
 * Laravel: src/User/Application/DTOs/UserDTO.php
 * Spring: Java record — sadə immutable DTO.
 */
public record UserDto(UUID id, String name, String email, boolean twoFactorEnabled) {

    public static UserDto fromDomain(User user) {
        return new UserDto(user.id().value(), user.name(), user.email().value(), user.twoFactorEnabled());
    }
}
