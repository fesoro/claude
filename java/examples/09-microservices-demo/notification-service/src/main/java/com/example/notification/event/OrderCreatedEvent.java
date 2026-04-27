package com.example.notification.event;

import java.time.LocalDateTime;

// Order Service ilə eyni contract — production-da shared library olmalı
public record OrderCreatedEvent(
    Long orderId,
    Long productId,
    int quantity,
    String customerEmail,
    LocalDateTime createdAt
) {}
