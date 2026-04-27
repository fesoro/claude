package com.example.order.event;

import java.math.BigDecimal;
import java.time.LocalDateTime;

// Notification Service ilə paylaşılan contract
// Production-da shared library yaxud schema registry istifadə olunur
public record OrderCreatedEvent(
    Long orderId,
    Long productId,
    int quantity,
    String customerEmail,
    LocalDateTime createdAt
) {}
