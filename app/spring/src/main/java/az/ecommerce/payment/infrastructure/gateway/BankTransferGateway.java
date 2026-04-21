package az.ecommerce.payment.infrastructure.gateway;

import az.ecommerce.payment.domain.enums.PaymentMethodEnum;
import az.ecommerce.payment.domain.strategy.GatewayResult;
import az.ecommerce.payment.domain.strategy.PaymentGateway;
import az.ecommerce.product.domain.valueobject.Money;
import org.springframework.stereotype.Component;

import java.util.UUID;

@Component
public class BankTransferGateway implements PaymentGateway {
    @Override public PaymentMethodEnum supportedMethod() { return PaymentMethodEnum.BANK_TRANSFER; }
    @Override public GatewayResult charge(Money amount, String reference) {
        return GatewayResult.ok("BT-" + UUID.randomUUID());
    }
}
