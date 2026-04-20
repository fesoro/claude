package az.ecommerce.product.infrastructure.persistence;

import org.springframework.data.jpa.repository.JpaRepository;

import java.util.List;
import java.util.UUID;

public interface ProductImageRepository extends JpaRepository<ProductImageEntity, UUID> {
    List<ProductImageEntity> findByProductIdOrderBySortOrderAsc(UUID productId);
    List<ProductImageEntity> findByProductIdAndIsPrimaryTrue(UUID productId);
}
