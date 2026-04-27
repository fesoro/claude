package com.example.orders.entity;

import java.util.Set;

public enum OrderStatus {
    PENDING, CONFIRMED, SHIPPED, DELIVERED, CANCELLED;

    // State machine: hər statusdan hansı statuslara keçid mümkündür
    public Set<OrderStatus> allowedTransitions() {
        return switch (this) {
            case PENDING   -> Set.of(CONFIRMED, CANCELLED);
            case CONFIRMED -> Set.of(SHIPPED,   CANCELLED);
            case SHIPPED   -> Set.of(DELIVERED);
            case DELIVERED, CANCELLED -> Set.of();
        };
    }

    public void validateTransition(OrderStatus next) {
        if (!allowedTransitions().contains(next)) {
            throw new IllegalStateException(
                    "Yanlış keçid: %s → %s".formatted(this, next));
        }
    }
}
