package az.ecommerce.product.infrastructure.persistence;

import az.ecommerce.product.domain.Product;
import az.ecommerce.product.domain.repository.ProductRepository;
import az.ecommerce.product.domain.valueobject.ProductId;
import az.ecommerce.shared.infrastructure.cache.TaggedCacheService;
import com.fasterxml.jackson.core.JsonProcessingException;
import com.fasterxml.jackson.databind.ObjectMapper;
import org.springframework.context.annotation.Primary;
import org.springframework.stereotype.Repository;

import java.time.Duration;
import java.util.List;
import java.util.Optional;

/**
 * === DECORATOR PATTERN ===
 *
 * Laravel: src/Product/Infrastructure/Repositories/CachedProductRepository.php
 *   wraps EloquentProductRepository
 *
 * Spring: @Primary olduğu üçün ProductRepository inject olunduqda bu seçilir.
 * Real ProductRepositoryImpl-ə @Qualifier("delegate") və ya başqa ad verə bilərik.
 *
 * Cache strategy: cache-aside, tag-based invalidation.
 */
@Repository
@Primary
public class CachedProductRepository implements ProductRepository {

    private static final String CACHE_PREFIX = "product:";
    private static final String CACHE_TAG = "products";
    private static final Duration TTL = Duration.ofMinutes(10);

    private final ProductRepositoryImpl delegate;
    private final TaggedCacheService cache;
    private final ObjectMapper objectMapper;

    public CachedProductRepository(ProductRepositoryImpl delegate,
                                    TaggedCacheService cache,
                                    ObjectMapper objectMapper) {
        this.delegate = delegate;
        this.cache = cache;
        this.objectMapper = objectMapper;
    }

    @Override
    public Product save(Product product) {
        Product saved = delegate.save(product);
        cache.invalidateTag(CACHE_TAG);   // Bütün product cache-i invalidasiya
        return saved;
    }

    @Override
    public Optional<Product> findById(ProductId id) {
        // Cache məqsəd ilə domain object-i cache etmirik (serialization mürəkkəbdir),
        // bunun əvəzinə DTO və ya entity cache edilir. Burada sadə pass-through.
        return delegate.findById(id);
    }

    @Override
    public List<Product> findAll(int page, int size) { return delegate.findAll(page, size); }

    @Override
    public long count() { return delegate.count(); }
}
