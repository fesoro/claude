package az.ecommerce.payment.infrastructure.gateway;

import az.ecommerce.payment.domain.enums.PaymentMethodEnum;
import az.ecommerce.payment.domain.strategy.GatewayResult;
import az.ecommerce.payment.domain.strategy.PaymentGateway;
import az.ecommerce.product.domain.valueobject.Money;
import org.springframework.stereotype.Component;

import java.util.UUID;

/**
 * Laravel: src/Payment/Infrastructure/Gateway/StripeGatewayAdapter.php
 * Go: payment/infrastructure/gateway/gateways.go (Stripe struct)
 */
@Component
public class StripeGateway implements PaymentGateway {

    @Override
    public PaymentMethodEnum supportedMethod() {
        return PaymentMethodEnum.STRIPE;
    }

    @Override
    public GatewayResult charge(Money amount, String reference) {
        return GatewayResult.ok("ST-" + UUID.randomUUID());
    }
}
