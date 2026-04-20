package az.ecommerce.product.infrastructure.persistence;

import az.ecommerce.product.domain.Product;
import az.ecommerce.product.domain.repository.ProductRepository;
import az.ecommerce.product.domain.valueobject.*;
import org.springframework.data.domain.PageRequest;
import org.springframework.stereotype.Repository;

import java.util.List;
import java.util.Optional;

@Repository
public class ProductRepositoryImpl implements ProductRepository {

    private final JpaProductRepository jpa;

    public ProductRepositoryImpl(JpaProductRepository jpa) { this.jpa = jpa; }

    @Override
    public Product save(Product product) {
        ProductEntity e = jpa.findById(product.id().value()).orElseGet(ProductEntity::new);
        e.setId(product.id().value());
        e.setName(product.name().value());
        e.setDescription(product.description());
        e.setPriceAmount(product.price().amount());
        e.setPriceCurrency(product.price().currency().name());
        e.setStockQuantity(product.stock().quantity());
        jpa.save(e);
        return product;
    }

    @Override
    public Optional<Product> findById(ProductId id) {
        return jpa.findById(id.value()).map(this::toDomain);
    }

    @Override
    public List<Product> findAll(int page, int size) {
        return jpa.findAll(PageRequest.of(page, size)).stream().map(this::toDomain).toList();
    }

    @Override
    public long count() { return jpa.count(); }

    private Product toDomain(ProductEntity e) {
        return Product.reconstitute(
                new ProductId(e.getId()),
                new ProductName(e.getName()),
                e.getDescription(),
                Money.of(e.getPriceAmount(), Currency.of(e.getPriceCurrency())),
                Stock.of(e.getStockQuantity()));
    }
}
