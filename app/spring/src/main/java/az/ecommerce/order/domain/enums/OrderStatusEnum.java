package az.ecommerce.order.domain.enums;

import az.ecommerce.shared.domain.exception.DomainException;

import java.util.Map;
import java.util.Set;

/**
 * Laravel: src/Order/Domain/Enums/OrderStatusEnum.php
 *
 * State machine: PENDING → CONFIRMED → PAID → SHIPPED → DELIVERED
 *                Hər mərhələdən → CANCELLED (delivered xaric)
 *
 * canTransitionTo() metodu yanlış keçidləri blok edir.
 */
public enum OrderStatusEnum {
    PENDING,
    CONFIRMED,
    PAID,
    SHIPPED,
    DELIVERED,
    CANCELLED;

    private static final Map<OrderStatusEnum, Set<OrderStatusEnum>> ALLOWED_TRANSITIONS = Map.of(
            PENDING,   Set.of(CONFIRMED, CANCELLED),
            CONFIRMED, Set.of(PAID, CANCELLED),
            PAID,      Set.of(SHIPPED, CANCELLED),
            SHIPPED,   Set.of(DELIVERED),
            DELIVERED, Set.of(),
            CANCELLED, Set.of()
    );

    public boolean canTransitionTo(OrderStatusEnum target) {
        return ALLOWED_TRANSITIONS.get(this).contains(target);
    }

    public void requireTransitionTo(OrderStatusEnum target) {
        if (!canTransitionTo(target)) {
            throw new DomainException(String.format(
                    "Yanlış status keçidi: %s → %s mümkün deyil", this, target));
        }
    }

    public boolean isFinal() {
        return this == DELIVERED || this == CANCELLED;
    }

    public String label() {
        return switch (this) {
            case PENDING -> "Gözləyir";
            case CONFIRMED -> "Təsdiqlənib";
            case PAID -> "Ödənilib";
            case SHIPPED -> "Göndərilib";
            case DELIVERED -> "Çatdırılıb";
            case CANCELLED -> "Ləğv edilib";
        };
    }
}
