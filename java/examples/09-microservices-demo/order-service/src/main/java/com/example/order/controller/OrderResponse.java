package com.example.order.controller;

import java.time.LocalDateTime;

public record OrderResponse(
    Long id,
    Long productId,
    int quantity,
    String customerEmail,
    String status,
    LocalDateTime createdAt
) {}
