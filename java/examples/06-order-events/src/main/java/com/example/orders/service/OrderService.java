package com.example.orders.service;

import com.example.orders.entity.Order;
import com.example.orders.entity.OrderItem;
import com.example.orders.entity.OrderStatus;
import com.example.orders.event.OrderCreatedEvent;
import com.example.orders.event.OrderStatusChangedEvent;
import com.example.orders.repository.OrderRepository;
import org.springframework.context.ApplicationEventPublisher;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Transactional;

import java.math.BigDecimal;
import java.util.List;
import java.util.NoSuchElementException;

@Service
@Transactional(readOnly = true)
public class OrderService {

    private final OrderRepository repo;
    private final ApplicationEventPublisher events;

    public OrderService(OrderRepository repo, ApplicationEventPublisher events) {
        this.repo   = repo;
        this.events = events;
    }

    public List<Order> findAll() {
        return repo.findAll();
    }

    public Order findById(Long id) {
        return repo.findById(id)
                .orElseThrow(() -> new NoSuchElementException("Sifariş tapılmadı: " + id));
    }

    @Transactional
    public Order create(String customerEmail, List<ItemDto> itemDtos) {
        Order order = new Order();
        order.setCustomerEmail(customerEmail);
        for (ItemDto dto : itemDtos) {
            OrderItem item = new OrderItem();
            item.setProductName(dto.productName());
            item.setQuantity(dto.quantity());
            item.setUnitPrice(dto.unitPrice());
            item.setOrder(order);
            order.getItems().add(item);
        }
        Order saved = repo.save(order);

        // Transaction commit-dən sonra event fire olur (@TransactionalEventListener üçün)
        events.publishEvent(new OrderCreatedEvent(saved));
        return saved;
    }

    @Transactional
    public Order transition(Long id, OrderStatus next) {
        Order order = findById(id);
        OrderStatus prev = order.getStatus();
        order.transitionTo(next);
        repo.save(order);
        events.publishEvent(new OrderStatusChangedEvent(order, prev, next));
        return order;
    }

    public record ItemDto(String productName, int quantity, BigDecimal unitPrice) {}
}
