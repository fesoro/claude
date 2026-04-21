package az.ecommerce.user.application.query;

import az.ecommerce.shared.application.bus.Query;
import az.ecommerce.user.application.dto.UserDto;

import java.util.UUID;

public record GetUserQuery(UUID userId) implements Query<UserDto> {}
