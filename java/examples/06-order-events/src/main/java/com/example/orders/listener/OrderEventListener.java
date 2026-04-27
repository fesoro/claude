package com.example.orders.listener;

import com.example.orders.event.OrderCreatedEvent;
import com.example.orders.event.OrderStatusChangedEvent;
import com.example.orders.entity.OrderStatus;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;
import org.springframework.scheduling.annotation.Async;
import org.springframework.stereotype.Component;
import org.springframework.transaction.event.TransactionPhase;
import org.springframework.transaction.event.TransactionalEventListener;

@Component
public class OrderEventListener {

    private static final Logger log = LoggerFactory.getLogger(OrderEventListener.class);

    // Transaction commit-dən SONRA işləyir, async — email/notification üçün ideal
    @Async
    @TransactionalEventListener(phase = TransactionPhase.AFTER_COMMIT)
    public void onOrderCreated(OrderCreatedEvent event) {
        var order = event.order();
        log.info("[EMAIL] Yeni sifariş bildirişi → {}, məbləğ: {}",
                order.getCustomerEmail(), order.totalAmount());
        // Real app-da: emailService.send(order.getCustomerEmail(), ...)
    }

    @Async
    @TransactionalEventListener(phase = TransactionPhase.AFTER_COMMIT)
    public void onStatusChanged(OrderStatusChangedEvent event) {
        var order = event.order();
        log.info("[AUDIT] Sifariş #{}: {} → {}", order.getId(), event.previousStatus(), event.newStatus());

        // Yalnız müştəri üçün vacib statuslarda bildiriş göndər
        if (event.newStatus() == OrderStatus.SHIPPED || event.newStatus() == OrderStatus.DELIVERED) {
            log.info("[EMAIL] Çatdırılma bildirişi → {}: sifarişiniz {}",
                    order.getCustomerEmail(), event.newStatus());
        }
    }
}
