package com.example.orders.event;

import com.example.orders.entity.Order;

// Spring Events üçün POJO — extends ApplicationEvent lazım deyil (Spring 4.2+)
public record OrderCreatedEvent(Order order) {}
