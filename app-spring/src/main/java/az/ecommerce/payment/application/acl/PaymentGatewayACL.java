package az.ecommerce.payment.application.acl;

import az.ecommerce.payment.domain.enums.PaymentMethodEnum;
import az.ecommerce.payment.domain.service.PaymentStrategyResolver;
import az.ecommerce.payment.domain.strategy.GatewayResult;
import az.ecommerce.product.domain.valueobject.Money;
import org.springframework.stereotype.Component;

/**
 * === ANTI-CORRUPTION LAYER ===
 *
 * Laravel: src/Payment/Application/ACL/PaymentGatewayACL.php
 *
 * Domain-i xarici sistem (Stripe, PayPal API) dəyişikliklərindən qoruyur.
 * Burada gateway response-u ümumi GatewayResult-a çevrilir.
 */
@Component
public class PaymentGatewayACL {

    private final PaymentStrategyResolver resolver;

    public PaymentGatewayACL(PaymentStrategyResolver resolver) {
        this.resolver = resolver;
    }

    public GatewayResult processCharge(PaymentMethodEnum method, Money amount, String reference) {
        return resolver.resolve(method).charge(amount, reference);
    }
}
