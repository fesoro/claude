package com.example.ecommerce.presentation;

import com.example.ecommerce.application.OrderService;
import com.example.ecommerce.domain.order.Order;
import jakarta.validation.Valid;
import jakarta.validation.constraints.Email;
import jakarta.validation.constraints.NotBlank;
import jakarta.validation.constraints.NotEmpty;
import jakarta.validation.constraints.Positive;
import org.springframework.http.HttpStatus;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.*;

import java.util.List;
import java.util.Map;
import java.util.NoSuchElementException;

@RestController
@RequestMapping("/api/orders")
public class OrderController {

    private final OrderService service;

    public OrderController(OrderService service) { this.service = service; }

    @GetMapping("/{id}")
    public Order get(@PathVariable Long id) { return service.findById(id); }

    @PostMapping
    @ResponseStatus(HttpStatus.CREATED)
    public Order place(@RequestBody @Valid PlaceOrderRequest req) {
        List<OrderService.OrderLineDto> lines = req.items().stream()
                .map(i -> new OrderService.OrderLineDto(i.productId(), i.quantity()))
                .toList();
        return service.placeOrder(req.customerEmail(), lines);
    }

    @PatchMapping("/{id}/confirm")
    public Order confirm(@PathVariable Long id) { return service.confirm(id); }

    @PatchMapping("/{id}/cancel")
    public Order cancel(@PathVariable Long id) { return service.cancel(id); }

    @ExceptionHandler({NoSuchElementException.class, IllegalArgumentException.class, IllegalStateException.class})
    public ResponseEntity<Map<String, String>> handleError(RuntimeException ex) {
        int status = ex instanceof NoSuchElementException ? 404 : 400;
        return ResponseEntity.status(status).body(Map.of("error", ex.getMessage()));
    }

    record OrderItemRequest(@Positive Long productId, @Positive int quantity) {}

    record PlaceOrderRequest(
            @NotBlank @Email String customerEmail,
            @NotEmpty List<OrderItemRequest> items
    ) {}
}
