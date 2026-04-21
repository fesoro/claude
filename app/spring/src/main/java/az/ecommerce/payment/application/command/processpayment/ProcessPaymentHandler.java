package az.ecommerce.payment.application.command.processpayment;

import az.ecommerce.payment.application.acl.PaymentGatewayACL;
import az.ecommerce.payment.domain.Payment;
import az.ecommerce.payment.domain.repository.PaymentRepository;
import az.ecommerce.payment.domain.strategy.GatewayResult;
import az.ecommerce.product.domain.valueobject.Currency;
import az.ecommerce.product.domain.valueobject.Money;
import az.ecommerce.shared.application.bus.CommandHandler;
import az.ecommerce.shared.infrastructure.bus.EventDispatcher;
import org.springframework.stereotype.Service;

import java.util.UUID;

/**
 * Bütün pattern-lərin birləşdiyi yer:
 *   - Strategy (PaymentGateway seçimi)
 *   - ACL (xarici cavabın çevrilməsi)
 *   - Circuit Breaker (Gateway-də annotation)
 *   - State machine (Payment.startProcessing/complete/fail)
 *   - Domain + Integration events
 */
@Service
public class ProcessPaymentHandler implements CommandHandler<ProcessPaymentCommand, UUID> {

    private final PaymentRepository repository;
    private final PaymentGatewayACL gatewayACL;
    private final EventDispatcher eventDispatcher;

    public ProcessPaymentHandler(PaymentRepository repository, PaymentGatewayACL gatewayACL,
                                 EventDispatcher eventDispatcher) {
        this.repository = repository;
        this.gatewayACL = gatewayACL;
        this.eventDispatcher = eventDispatcher;
    }

    @Override
    public UUID handle(ProcessPaymentCommand cmd) {
        Money amount = Money.of(cmd.amount(), Currency.of(cmd.currency()));
        Payment payment = Payment.initiate(cmd.orderId(), cmd.userId(), amount, cmd.method());
        repository.save(payment);

        payment.startProcessing();
        GatewayResult result = gatewayACL.processCharge(cmd.method(), amount, cmd.orderId().toString());

        if (result.success()) {
            payment.complete(result.transactionId());
        } else {
            payment.fail(result.errorMessage());
        }

        repository.save(payment);
        eventDispatcher.dispatchAll(payment);
        return payment.id().value();
    }
}
