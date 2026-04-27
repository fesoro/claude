package com.example.ecommerce.infrastructure;

import com.example.ecommerce.domain.order.OrderPlacedEvent;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;
import org.springframework.context.event.EventListener;
import org.springframework.scheduling.annotation.Async;
import org.springframework.stereotype.Component;

@Component
public class OrderEventHandler {

    private static final Logger log = LoggerFactory.getLogger(OrderEventHandler.class);

    @Async
    @EventListener
    public void handle(OrderPlacedEvent event) {
        log.info("[EVENT] Yeni sifariş: #{}, müştəri: {}, məbləğ: {}",
                event.orderId(), event.customerEmail(), event.totalAmount());
        // Real app-da: email göndər, warehouse-a bildiriş ver, analytics-ə yaz
    }
}
