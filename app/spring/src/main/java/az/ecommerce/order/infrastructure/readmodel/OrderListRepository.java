package az.ecommerce.order.infrastructure.readmodel;

import org.springframework.data.jpa.repository.JpaRepository;

import java.util.UUID;

public interface OrderListRepository extends JpaRepository<OrderReadModelEntity, UUID> {
}
