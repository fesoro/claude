package com.example.notification.service;

import com.example.notification.event.OrderCreatedEvent;
import lombok.extern.slf4j.Slf4j;
import org.springframework.stereotype.Service;

@Service
@Slf4j
public class NotificationService {

    public void sendOrderConfirmation(OrderCreatedEvent event) {
        // Production-da: JavaMailSender, SendGrid, AWS SES
        log.info("Email göndərildi: {} → Order #{} ({}x məhsul {})",
            event.customerEmail(),
            event.orderId(),
            event.quantity(),
            event.productId()
        );
    }
}
