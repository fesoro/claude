package az.ecommerce.user.infrastructure.web;

import az.ecommerce.shared.application.bus.QueryBus;
import az.ecommerce.shared.infrastructure.api.ApiResponse;
import az.ecommerce.user.application.dto.UserDto;
import az.ecommerce.user.application.query.GetUserQuery;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PathVariable;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.RestController;

import java.util.UUID;

/**
 * Laravel: app/Http/Controllers/UserController.php
 * GET /api/users/{id}
 */
@RestController
@RequestMapping("/api/users")
public class UserController {

    private final QueryBus queryBus;

    public UserController(QueryBus queryBus) {
        this.queryBus = queryBus;
    }

    @GetMapping("/{id}")
    public ApiResponse<UserDto> show(@PathVariable UUID id) {
        return ApiResponse.success(queryBus.ask(new GetUserQuery(id)));
    }
}
