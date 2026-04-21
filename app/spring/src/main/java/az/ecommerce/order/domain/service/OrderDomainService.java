package az.ecommerce.order.domain.service;

import az.ecommerce.product.domain.valueobject.Money;

import java.util.ArrayList;
import java.util.List;

/**
 * Laravel: src/Order/Domain/Services/OrderDomainService.php
 *   - calculateOrderPrice() — multiple discount rules
 *   - splitOrderForShipping()
 *
 * Spring: state-less domain service — pure business logic, side-effect-siz.
 */
public class OrderDomainService {

    public OrderPriceCalculation calculatePrice(Money subtotal, boolean isVip, int orderCount) {
        List<String> appliedDiscounts = new ArrayList<>();
        long discountPercent = 0;

        // Bulk discount: 1000+ AZN üçün 10%
        if (subtotal.amount() >= 100_000) {
            discountPercent += 10;
            appliedDiscounts.add("bulk-1000+");
        }

        // VIP discount: 15%
        if (isVip) {
            discountPercent += 15;
            appliedDiscounts.add("vip");
        }

        // Loyalty: 10+ sifariş → 5%
        if (orderCount >= 10) {
            discountPercent += 5;
            appliedDiscounts.add("loyalty-10+");
        }

        // Max cap: 25%
        if (discountPercent > 25) discountPercent = 25;

        long discountAmount = subtotal.amount() * discountPercent / 100;
        Money finalAmount = Money.of(subtotal.amount() - discountAmount, subtotal.currency());
        return new OrderPriceCalculation(subtotal, discountAmount, discountPercent, finalAmount, appliedDiscounts);
    }

    public record OrderPriceCalculation(
            Money subtotal, long discountAmount, long discountPercent,
            Money finalAmount, List<String> appliedDiscounts) {}
}
