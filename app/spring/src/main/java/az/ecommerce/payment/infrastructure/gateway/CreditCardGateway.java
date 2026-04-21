package az.ecommerce.payment.infrastructure.gateway;

import az.ecommerce.payment.domain.enums.PaymentMethodEnum;
import az.ecommerce.payment.domain.strategy.GatewayResult;
import az.ecommerce.payment.domain.strategy.PaymentGateway;
import az.ecommerce.product.domain.valueobject.Money;
import io.github.resilience4j.circuitbreaker.annotation.CircuitBreaker;
import io.github.resilience4j.retry.annotation.Retry;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;
import org.springframework.stereotype.Component;

import java.util.UUID;

/**
 * Laravel: src/Payment/Domain/Strategies/CreditCardGateway.php
 * Spring: @Component + Resilience4j @CircuitBreaker + @Retry annotation-ları.
 */
@Component
public class CreditCardGateway implements PaymentGateway {

    private static final Logger log = LoggerFactory.getLogger(CreditCardGateway.class);

    @Override
    public PaymentMethodEnum supportedMethod() {
        return PaymentMethodEnum.CREDIT_CARD;
    }

    @Override
    @CircuitBreaker(name = "paymentGateway", fallbackMethod = "fallback")
    @Retry(name = "paymentGateway")
    public GatewayResult charge(Money amount, String reference) {
        log.info("Credit Card charge: {} for {}", amount, reference);
        // Real call: stripe API və s.
        return GatewayResult.ok("CC-" + UUID.randomUUID());
    }

    public GatewayResult fallback(Money amount, String reference, Throwable ex) {
        log.error("Credit Card gateway DOWN, fallback aktivləşdi: {}", ex.getMessage());
        return GatewayResult.fail("Gateway müvəqqəti olaraq əlçatmazdır");
    }
}
