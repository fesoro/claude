package com.example.ecommerce.infrastructure;

import com.example.ecommerce.domain.order.Order;
import com.example.ecommerce.domain.order.OrderRepository;
import org.springframework.data.jpa.repository.JpaRepository;
import org.springframework.stereotype.Repository;

@Repository
public interface JpaOrderRepository extends OrderRepository, JpaRepository<Order, Long> {}
