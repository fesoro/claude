package com.example.ecommerce.domain.order;

import java.math.BigDecimal;

// Domain Event: Order yarananda bu event publish olur
public record OrderPlacedEvent(Long orderId, String customerEmail, BigDecimal totalAmount) {}
