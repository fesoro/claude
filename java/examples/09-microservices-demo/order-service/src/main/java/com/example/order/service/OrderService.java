package com.example.order.service;

import com.example.order.controller.CreateOrderRequest;
import com.example.order.controller.OrderResponse;
import com.example.order.entity.Order;
import com.example.order.entity.OrderStatus;
import com.example.order.event.OrderCreatedEvent;
import com.example.order.repository.OrderRepository;
import lombok.RequiredArgsConstructor;
import lombok.extern.slf4j.Slf4j;
import org.springframework.beans.factory.annotation.Value;
import org.springframework.kafka.core.KafkaTemplate;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Transactional;

import java.util.List;

@Service
@RequiredArgsConstructor
@Slf4j
public class OrderService {

    private final OrderRepository orderRepository;
    private final KafkaTemplate<String, OrderCreatedEvent> kafkaTemplate;

    @Value("${kafka.topics.order-created}")
    private String orderCreatedTopic;

    @Transactional
    public OrderResponse createOrder(CreateOrderRequest request) {
        Order order = Order.builder()
            .productId(request.productId())
            .quantity(request.quantity())
            .customerEmail(request.customerEmail())
            .status(OrderStatus.PENDING)
            .build();

        order = orderRepository.save(order);
        log.debug("Sifariş yaradıldı: id={}", order.getId());

        // DB-yə yazıldı → Kafka-ya event göndər
        OrderCreatedEvent event = new OrderCreatedEvent(
            order.getId(),
            order.getProductId(),
            order.getQuantity(),
            order.getCustomerEmail(),
            order.getCreatedAt()
        );

        // Key = orderId → eyni sifariş üçün eventlər eyni partition-a gedir
        kafkaTemplate.send(orderCreatedTopic, order.getId().toString(), event)
            .whenComplete((result, ex) -> {
                if (ex == null) {
                    log.debug("Event göndərildi: topic={}, partition={}, offset={}",
                        result.getRecordMetadata().topic(),
                        result.getRecordMetadata().partition(),
                        result.getRecordMetadata().offset());
                } else {
                    log.error("Event göndərilmədi: orderId={}", order.getId(), ex);
                }
            });

        return toResponse(order);
    }

    public List<OrderResponse> findByCustomer(String email) {
        return orderRepository.findByCustomerEmail(email)
            .stream()
            .map(this::toResponse)
            .toList();
    }

    private OrderResponse toResponse(Order order) {
        return new OrderResponse(
            order.getId(),
            order.getProductId(),
            order.getQuantity(),
            order.getCustomerEmail(),
            order.getStatus().name(),
            order.getCreatedAt()
        );
    }
}
