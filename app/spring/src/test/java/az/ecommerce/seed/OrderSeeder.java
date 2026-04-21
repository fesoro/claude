package az.ecommerce.seed;

import az.ecommerce.order.domain.repository.OrderRepository;
import az.ecommerce.order.domain.valueobject.Address;
import az.ecommerce.order.domain.valueobject.OrderItem;
import az.ecommerce.product.domain.repository.ProductRepository;
import az.ecommerce.product.domain.valueobject.Currency;
import az.ecommerce.user.domain.repository.UserRepository;
import az.ecommerce.user.domain.valueobject.Email;
import org.springframework.boot.CommandLineRunner;
import org.springframework.context.annotation.Profile;
import org.springframework.core.annotation.Order;
import org.springframework.stereotype.Component;

import java.util.List;

/**
 * Laravel: OrderSeeder (15 sifariş müxtəlif status-larla)
 *
 * QEYD: Domain Order class-ı tam adı ilə (az.ecommerce.order.domain.Order) istifadə olunur,
 * çünki @Order (Spring) annotation ilə qarışıqlığın qarşısını almaq üçün.
 */
@Component
@Profile("seed")
@Order(3)
public class OrderSeeder implements CommandLineRunner {

    private final OrderRepository orderRepository;
    private final UserRepository userRepository;
    private final ProductRepository productRepository;

    public OrderSeeder(OrderRepository orderRepository,
                        UserRepository userRepository,
                        ProductRepository productRepository) {
        this.orderRepository = orderRepository;
        this.userRepository = userRepository;
        this.productRepository = productRepository;
    }

    @Override
    public void run(String... args) {
        var user = userRepository.findByEmail(new Email("user1@example.com")).orElse(null);
        var products = productRepository.findAll(0, 5);
        if (user == null || products.isEmpty()) {
            System.out.println("OrderSeeder: user və ya product yoxdur, skip");
            return;
        }

        var address = new Address("Nizami küçəsi 10", "Bakı", "AZ1000", "Azerbaijan");

        for (int i = 0; i < 15; i++) {
            var product = products.get(i % products.size());
            var item = new OrderItem(product.id().value(), product.name().value(),
                    product.price(), 1 + i % 3);

            az.ecommerce.order.domain.Order order = az.ecommerce.order.domain.Order.create(
                    user.id().value(), List.of(item), address, Currency.AZN);

            // Müxtəlif status-lar yarat
            if (i % 5 == 0) order.confirm();
            if (i % 5 == 1) { order.confirm(); order.markAsPaid(); }
            if (i % 5 == 2) { order.confirm(); order.markAsPaid(); order.ship(); }
            if (i % 5 == 3) order.cancel("Test ləğvi");

            orderRepository.save(order);
        }
        System.out.println("OrderSeeder: 15 sifariş yaradıldı");
    }
}
