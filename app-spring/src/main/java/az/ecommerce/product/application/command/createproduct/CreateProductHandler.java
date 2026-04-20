package az.ecommerce.product.application.command.createproduct;

import az.ecommerce.product.domain.Product;
import az.ecommerce.product.domain.repository.ProductRepository;
import az.ecommerce.product.domain.specification.ProductPriceIsValidSpec;
import az.ecommerce.product.domain.valueobject.*;
import az.ecommerce.shared.application.bus.CommandHandler;
import az.ecommerce.shared.domain.exception.DomainException;
import az.ecommerce.shared.infrastructure.bus.EventDispatcher;
import org.springframework.stereotype.Service;

import java.util.UUID;

@Service
public class CreateProductHandler implements CommandHandler<CreateProductCommand, UUID> {

    private final ProductRepository repository;
    private final EventDispatcher eventDispatcher;

    public CreateProductHandler(ProductRepository repository, EventDispatcher eventDispatcher) {
        this.repository = repository;
        this.eventDispatcher = eventDispatcher;
    }

    @Override
    public UUID handle(CreateProductCommand cmd) {
        Product product = Product.create(
                new ProductName(cmd.name()),
                cmd.description(),
                Money.of(cmd.priceAmount(), Currency.of(cmd.currency())),
                Stock.of(cmd.stockQuantity()));

        if (!new ProductPriceIsValidSpec().isSatisfiedBy(product)) {
            throw new DomainException("Məhsul qiyməti 0-dan böyük olmalıdır");
        }

        repository.save(product);
        eventDispatcher.dispatchAll(product);
        return product.id().value();
    }
}
