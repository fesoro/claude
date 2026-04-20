package az.ecommerce.product.application.query.listproducts;

import az.ecommerce.product.application.dto.ProductDto;
import az.ecommerce.product.domain.repository.ProductRepository;
import az.ecommerce.shared.application.bus.QueryHandler;
import org.springframework.stereotype.Service;

import java.util.List;

@Service
public class ListProductsHandler implements QueryHandler<ListProductsQuery, List<ProductDto>> {

    private final ProductRepository repository;

    public ListProductsHandler(ProductRepository repository) { this.repository = repository; }

    @Override
    public List<ProductDto> handle(ListProductsQuery q) {
        return repository.findAll(q.page(), q.size()).stream().map(ProductDto::fromDomain).toList();
    }
}
