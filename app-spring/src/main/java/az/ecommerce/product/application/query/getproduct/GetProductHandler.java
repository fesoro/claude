package az.ecommerce.product.application.query.getproduct;

import az.ecommerce.product.application.dto.ProductDto;
import az.ecommerce.product.domain.repository.ProductRepository;
import az.ecommerce.product.domain.valueobject.ProductId;
import az.ecommerce.shared.application.bus.QueryHandler;
import az.ecommerce.shared.domain.exception.EntityNotFoundException;
import org.springframework.stereotype.Service;

@Service
public class GetProductHandler implements QueryHandler<GetProductQuery, ProductDto> {

    private final ProductRepository repository;

    public GetProductHandler(ProductRepository repository) { this.repository = repository; }

    @Override
    public ProductDto handle(GetProductQuery query) {
        return repository.findById(new ProductId(query.productId()))
                .map(ProductDto::fromDomain)
                .orElseThrow(() -> new EntityNotFoundException("Product", query.productId().toString()));
    }
}
