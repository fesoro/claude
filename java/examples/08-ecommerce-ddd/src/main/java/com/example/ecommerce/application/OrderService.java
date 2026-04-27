package com.example.ecommerce.application;

import com.example.ecommerce.domain.order.Order;
import com.example.ecommerce.domain.order.OrderItem;
import com.example.ecommerce.domain.order.OrderPlacedEvent;
import com.example.ecommerce.domain.order.OrderRepository;
import com.example.ecommerce.domain.product.Product;
import com.example.ecommerce.domain.product.ProductRepository;
import org.springframework.context.ApplicationEventPublisher;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Transactional;

import java.util.List;
import java.util.NoSuchElementException;

@Service
@Transactional(readOnly = true)
public class OrderService {

    private final OrderRepository orderRepo;
    private final ProductRepository productRepo;
    private final ApplicationEventPublisher events;

    public OrderService(OrderRepository orderRepo, ProductRepository productRepo,
                        ApplicationEventPublisher events) {
        this.orderRepo   = orderRepo;
        this.productRepo = productRepo;
        this.events      = events;
    }

    public Order findById(Long id) {
        return orderRepo.findById(id).orElseThrow(() -> new NoSuchElementException("Sifariş tapılmadı: " + id));
    }

    @Transactional
    public Order placeOrder(String customerEmail, List<OrderLineDto> lines) {
        Order order = Order.create(customerEmail);

        for (OrderLineDto line : lines) {
            Product product = productRepo.findById(line.productId())
                    .orElseThrow(() -> new NoSuchElementException("Məhsul tapılmadı: " + line.productId()));

            // Domain method — stok azalır, aggregate invariantını qoruyur
            product.decreaseStock(line.quantity());
            productRepo.save(product);

            OrderItem item = new OrderItem(
                    product.getId(), product.getName(),
                    line.quantity(), product.getPrice().getAmount()
            );
            order.addItem(item);
        }

        Order saved = orderRepo.save(order);
        // Domain event publish et
        events.publishEvent(new OrderPlacedEvent(saved.getId(), saved.getCustomerEmail(), saved.totalAmount()));
        return saved;
    }

    @Transactional
    public Order confirm(Long id) {
        Order order = findById(id);
        order.confirm();  // domain logic burada
        return orderRepo.save(order);
    }

    @Transactional
    public Order cancel(Long id) {
        Order order = findById(id);
        order.cancel();
        return orderRepo.save(order);
    }

    public record OrderLineDto(Long productId, int quantity) {}
}
