package az.ecommerce.payment.domain.strategy;

import az.ecommerce.payment.domain.enums.PaymentMethodEnum;
import az.ecommerce.product.domain.valueobject.Money;

/**
 * === STRATEGY PATTERN ===
 *
 * Laravel: src/Payment/Domain/Strategies/PaymentGatewayInterface.php
 * Spring: hər implementation @Component, PaymentStrategyResolver-də map-ə yığılır.
 */
public interface PaymentGateway {

    PaymentMethodEnum supportedMethod();

    GatewayResult charge(Money amount, String reference);
}
