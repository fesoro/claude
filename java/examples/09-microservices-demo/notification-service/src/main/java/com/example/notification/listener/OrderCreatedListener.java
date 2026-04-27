package com.example.notification.listener;

import com.example.notification.event.OrderCreatedEvent;
import com.example.notification.service.NotificationService;
import lombok.RequiredArgsConstructor;
import lombok.extern.slf4j.Slf4j;
import org.apache.kafka.clients.consumer.ConsumerRecord;
import org.springframework.kafka.annotation.KafkaListener;
import org.springframework.kafka.support.Acknowledgment;
import org.springframework.stereotype.Component;

@Component
@RequiredArgsConstructor
@Slf4j
public class OrderCreatedListener {

    private final NotificationService notificationService;

    @KafkaListener(
        topics = "${kafka.topics.order-created}",
        groupId = "notification-service",
        concurrency = "3"           // 3 thread, 3 partition üçün
    )
    public void onOrderCreated(ConsumerRecord<String, OrderCreatedEvent> record,
                               Acknowledgment ack) {
        OrderCreatedEvent event = record.value();

        log.debug("Event alındı: topic={}, partition={}, offset={}, orderId={}",
            record.topic(), record.partition(), record.offset(), event.orderId());

        try {
            notificationService.sendOrderConfirmation(event);

            // Uğurlu işlədikdən sonra offset commit
            ack.acknowledge();

        } catch (Exception e) {
            log.error("Notification göndərilmədi: orderId={}", event.orderId(), e);
            // ack.acknowledge() çağrılmır → Kafka yenidən çatdırır
            // Production-da: DLQ (Dead Letter Queue) pattern
        }
    }
}
