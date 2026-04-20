package az.ecommerce.payment.domain.service;

import az.ecommerce.payment.domain.enums.PaymentMethodEnum;
import az.ecommerce.payment.domain.strategy.PaymentGateway;
import az.ecommerce.shared.domain.exception.DomainException;
import org.springframework.stereotype.Service;

import java.util.List;
import java.util.Map;
import java.util.stream.Collectors;

/**
 * Laravel: src/Payment/Domain/Services/PaymentStrategyResolver.php
 *
 * Spring: bütün PaymentGateway @Component-ləri inject edir, Map-ə yığır.
 * if/else olmadan strategy seçimi (Open/Closed prinsipi).
 */
@Service
public class PaymentStrategyResolver {

    private final Map<PaymentMethodEnum, PaymentGateway> gateways;

    public PaymentStrategyResolver(List<PaymentGateway> all) {
        this.gateways = all.stream().collect(Collectors.toMap(PaymentGateway::supportedMethod, g -> g));
    }

    public PaymentGateway resolve(PaymentMethodEnum method) {
        PaymentGateway gateway = gateways.get(method);
        if (gateway == null) {
            throw new DomainException("Bu ödəmə üsulu dəstəklənmir: " + method);
        }
        return gateway;
    }
}
