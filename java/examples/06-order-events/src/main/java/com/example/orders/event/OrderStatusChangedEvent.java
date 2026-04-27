package com.example.orders.event;

import com.example.orders.entity.Order;
import com.example.orders.entity.OrderStatus;

public record OrderStatusChangedEvent(Order order, OrderStatus previousStatus, OrderStatus newStatus) {}
