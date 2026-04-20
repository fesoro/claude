package az.ecommerce.payment.domain;

import az.ecommerce.payment.domain.enums.PaymentMethodEnum;
import az.ecommerce.payment.domain.enums.PaymentStatusEnum;
import az.ecommerce.payment.domain.event.PaymentCompletedEvent;
import az.ecommerce.payment.domain.event.PaymentCompletedIntegrationEvent;
import az.ecommerce.payment.domain.event.PaymentCreatedEvent;
import az.ecommerce.payment.domain.event.PaymentFailedEvent;
import az.ecommerce.payment.domain.event.PaymentFailedIntegrationEvent;
import az.ecommerce.payment.domain.valueobject.PaymentId;
import az.ecommerce.product.domain.valueobject.Money;
import az.ecommerce.shared.domain.AggregateRoot;

import java.time.Instant;
import java.util.UUID;

public class Payment extends AggregateRoot {

    private final PaymentId id;
    private final UUID orderId;
    private final UUID userId;
    private final Money amount;
    private final PaymentMethodEnum method;
    private PaymentStatusEnum status;
    private String transactionId;
    private String failureReason;
    private Instant completedAt;

    private Payment(PaymentId id, UUID orderId, UUID userId, Money amount, PaymentMethodEnum method) {
        this.id = id;
        this.orderId = orderId;
        this.userId = userId;
        this.amount = amount;
        this.method = method;
        this.status = PaymentStatusEnum.PENDING;
    }

    public static Payment initiate(UUID orderId, UUID userId, Money amount, PaymentMethodEnum method) {
        PaymentId id = PaymentId.generate();
        Payment p = new Payment(id, orderId, userId, amount, method);
        p.recordEvent(PaymentCreatedEvent.of(id, orderId));
        return p;
    }

    public static Payment reconstitute(PaymentId id, UUID orderId, UUID userId, Money amount,
                                       PaymentMethodEnum method, PaymentStatusEnum status,
                                       String transactionId, String failureReason) {
        Payment p = new Payment(id, orderId, userId, amount, method);
        p.status = status;
        p.transactionId = transactionId;
        p.failureReason = failureReason;
        return p;
    }

    public void startProcessing() {
        status.requireTransitionTo(PaymentStatusEnum.PROCESSING);
        this.status = PaymentStatusEnum.PROCESSING;
    }

    public void complete(String transactionId) {
        status.requireTransitionTo(PaymentStatusEnum.COMPLETED);
        this.status = PaymentStatusEnum.COMPLETED;
        this.transactionId = transactionId;
        this.completedAt = Instant.now();
        recordEvent(PaymentCompletedEvent.of(id, orderId));
        recordEvent(PaymentCompletedIntegrationEvent.of(id.value(), orderId, amount.amount()));
    }

    public void fail(String reason) {
        status.requireTransitionTo(PaymentStatusEnum.FAILED);
        this.status = PaymentStatusEnum.FAILED;
        this.failureReason = reason;
        recordEvent(PaymentFailedEvent.of(id, orderId, reason));
        recordEvent(PaymentFailedIntegrationEvent.of(id.value(), orderId, reason));
    }

    public PaymentId id() { return id; }
    public UUID orderId() { return orderId; }
    public UUID userId() { return userId; }
    public Money amount() { return amount; }
    public PaymentMethodEnum method() { return method; }
    public PaymentStatusEnum status() { return status; }
    public String transactionId() { return transactionId; }
    public String failureReason() { return failureReason; }
}
