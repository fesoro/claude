package com.example.order.controller;

import jakarta.validation.constraints.*;

public record CreateOrderRequest(
    @NotNull Long productId,
    @Min(1) int quantity,
    @NotBlank @Email String customerEmail
) {}
