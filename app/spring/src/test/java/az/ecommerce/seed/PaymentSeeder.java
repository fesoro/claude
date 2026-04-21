package az.ecommerce.seed;

import az.ecommerce.payment.domain.Payment;
import az.ecommerce.payment.domain.enums.PaymentMethodEnum;
import az.ecommerce.payment.domain.repository.PaymentRepository;
import az.ecommerce.product.domain.valueobject.Currency;
import az.ecommerce.product.domain.valueobject.Money;
import org.springframework.boot.CommandLineRunner;
import org.springframework.context.annotation.Profile;
import org.springframework.core.annotation.Order;
import org.springframework.stereotype.Component;

import java.util.UUID;

/**
 * Laravel: PaymentSeeder (ödənilmiş sifarişlər + 2 failed)
 */
@Component
@Profile("seed")
@Order(4)
public class PaymentSeeder implements CommandLineRunner {

    private final PaymentRepository repository;

    public PaymentSeeder(PaymentRepository repository) { this.repository = repository; }

    @Override
    public void run(String... args) {
        // 8 uğurlu ödəniş
        for (int i = 0; i < 8; i++) {
            Payment payment = Payment.initiate(UUID.randomUUID(), UUID.randomUUID(),
                    Money.of(5000 + i * 1000, Currency.AZN),
                    i % 2 == 0 ? PaymentMethodEnum.CREDIT_CARD : PaymentMethodEnum.PAYPAL);
            payment.startProcessing();
            payment.complete("TX-SEED-" + i);
            repository.save(payment);
        }

        // 2 failed
        for (int i = 0; i < 2; i++) {
            Payment payment = Payment.initiate(UUID.randomUUID(), UUID.randomUUID(),
                    Money.of(1000, Currency.AZN), PaymentMethodEnum.CREDIT_CARD);
            payment.startProcessing();
            payment.fail("Test failure " + i);
            repository.save(payment);
        }
        System.out.println("PaymentSeeder: 8 uğurlu + 2 failed ödəniş");
    }
}
