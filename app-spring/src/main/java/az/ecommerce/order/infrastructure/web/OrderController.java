package az.ecommerce.order.infrastructure.web;

import az.ecommerce.order.application.command.cancelorder.CancelOrderCommand;
import az.ecommerce.order.application.command.createorder.CreateOrderCommand;
import az.ecommerce.order.application.command.updateorderstatus.UpdateOrderStatusCommand;
import az.ecommerce.order.application.dto.OrderDto;
import az.ecommerce.order.application.query.getorder.GetOrderQuery;
import az.ecommerce.order.application.query.listorders.ListOrdersQuery;
import az.ecommerce.order.domain.enums.OrderStatusEnum;
import az.ecommerce.shared.application.bus.CommandBus;
import az.ecommerce.shared.application.bus.QueryBus;
import az.ecommerce.shared.infrastructure.api.ApiResponse;
import jakarta.validation.Valid;
import org.springframework.http.HttpStatus;
import org.springframework.http.ResponseEntity;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.web.bind.annotation.*;

import java.util.List;
import java.util.Map;
import java.util.UUID;

/**
 * Laravel: app/Http/Controllers/OrderController.php
 *   POST   /api/orders              — auth + throttle:orders
 *   GET    /api/orders/{id}         — auth
 *   GET    /api/orders/user/{id}    — auth
 *   POST   /api/orders/{id}/cancel  — auth + policy
 *   PATCH  /api/orders/{id}/status  — auth + policy
 */
@RestController
@RequestMapping("/api/orders")
@PreAuthorize("isAuthenticated()")
public class OrderController {

    private final CommandBus commandBus;
    private final QueryBus queryBus;

    public OrderController(CommandBus commandBus, QueryBus queryBus) {
        this.commandBus = commandBus;
        this.queryBus = queryBus;
    }

    @PostMapping
    public ResponseEntity<ApiResponse<Map<String, UUID>>> store(@RequestBody @Valid CreateOrderCommand cmd) {
        UUID id = commandBus.dispatch(cmd);
        return ResponseEntity.status(HttpStatus.CREATED).body(
                ApiResponse.success(Map.of("id", id), "Sifariş yaradıldı"));
    }

    @GetMapping("/{id}")
    public ApiResponse<OrderDto> show(@PathVariable UUID id) {
        return ApiResponse.success(queryBus.ask(new GetOrderQuery(id)));
    }

    @GetMapping("/user/{userId}")
    public ApiResponse<List<OrderDto>> listByUser(@PathVariable UUID userId) {
        return ApiResponse.success(queryBus.ask(new ListOrdersQuery(userId)));
    }

    @PostMapping("/{id}/cancel")
    public ApiResponse<Void> cancel(@PathVariable UUID id, @RequestBody(required = false) CancelBody body) {
        commandBus.dispatch(new CancelOrderCommand(id, body != null ? body.reason() : null));
        return ApiResponse.success(null, "Sifariş ləğv edildi");
    }

    @PatchMapping("/{id}/status")
    public ApiResponse<Void> updateStatus(@PathVariable UUID id, @RequestBody StatusBody body) {
        commandBus.dispatch(new UpdateOrderStatusCommand(id, body.target()));
        return ApiResponse.success(null, "Status yeniləndi");
    }

    public record CancelBody(String reason) {}
    public record StatusBody(OrderStatusEnum target) {}
}
