package com.example.orders.controller;

import com.example.orders.entity.Order;
import com.example.orders.entity.OrderStatus;
import com.example.orders.service.OrderService;
import jakarta.validation.Valid;
import jakarta.validation.constraints.Email;
import jakarta.validation.constraints.NotBlank;
import jakarta.validation.constraints.NotEmpty;
import jakarta.validation.constraints.Positive;
import org.springframework.http.HttpStatus;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.*;

import java.math.BigDecimal;
import java.util.List;
import java.util.Map;
import java.util.NoSuchElementException;

@RestController
@RequestMapping("/api/orders")
public class OrderController {

    private final OrderService service;

    public OrderController(OrderService service) { this.service = service; }

    @GetMapping
    public List<Order> list() {
        return service.findAll();
    }

    @GetMapping("/{id}")
    public Order get(@PathVariable Long id) {
        return service.findById(id);
    }

    @PostMapping
    @ResponseStatus(HttpStatus.CREATED)
    public Order create(@RequestBody @Valid CreateOrderRequest req) {
        List<OrderService.ItemDto> items = req.items().stream()
                .map(i -> new OrderService.ItemDto(i.productName(), i.quantity(), i.unitPrice()))
                .toList();
        return service.create(req.customerEmail(), items);
    }

    @PatchMapping("/{id}/confirm")
    public Order confirm(@PathVariable Long id) {
        return service.transition(id, OrderStatus.CONFIRMED);
    }

    @PatchMapping("/{id}/ship")
    public Order ship(@PathVariable Long id) {
        return service.transition(id, OrderStatus.SHIPPED);
    }

    @PatchMapping("/{id}/deliver")
    public Order deliver(@PathVariable Long id) {
        return service.transition(id, OrderStatus.DELIVERED);
    }

    @PatchMapping("/{id}/cancel")
    public Order cancel(@PathVariable Long id) {
        return service.transition(id, OrderStatus.CANCELLED);
    }

    @ExceptionHandler(NoSuchElementException.class)
    public ResponseEntity<Map<String, String>> notFound(NoSuchElementException ex) {
        return ResponseEntity.status(404).body(Map.of("error", ex.getMessage()));
    }

    @ExceptionHandler(IllegalStateException.class)
    public ResponseEntity<Map<String, String>> badTransition(IllegalStateException ex) {
        return ResponseEntity.badRequest().body(Map.of("error", ex.getMessage()));
    }

    // --- DTOs ---

    record ItemRequest(
            @NotBlank String productName,
            @Positive int quantity,
            @Positive BigDecimal unitPrice
    ) {}

    record CreateOrderRequest(
            @NotBlank @Email String customerEmail,
            @NotEmpty List<ItemRequest> items
    ) {}
}
