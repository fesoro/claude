package az.ecommerce.payment.application.query.getpayment;

import az.ecommerce.payment.application.dto.PaymentDto;
import az.ecommerce.payment.domain.repository.PaymentRepository;
import az.ecommerce.payment.domain.valueobject.PaymentId;
import az.ecommerce.shared.application.bus.QueryHandler;
import az.ecommerce.shared.domain.exception.EntityNotFoundException;
import org.springframework.stereotype.Service;

@Service
public class GetPaymentHandler implements QueryHandler<GetPaymentQuery, PaymentDto> {
    private final PaymentRepository repository;
    public GetPaymentHandler(PaymentRepository r) { this.repository = r; }
    @Override public PaymentDto handle(GetPaymentQuery q) {
        return repository.findById(new PaymentId(q.paymentId()))
                .map(PaymentDto::fromDomain)
                .orElseThrow(() -> new EntityNotFoundException("Payment", q.paymentId().toString()));
    }
}
