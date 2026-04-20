package az.ecommerce.product.application.command.updatestock;

import az.ecommerce.product.domain.Product;
import az.ecommerce.product.domain.repository.ProductRepository;
import az.ecommerce.product.domain.valueobject.ProductId;
import az.ecommerce.shared.application.bus.CommandHandler;
import az.ecommerce.shared.domain.exception.EntityNotFoundException;
import az.ecommerce.shared.infrastructure.bus.EventDispatcher;
import org.springframework.stereotype.Service;

@Service
public class UpdateStockHandler implements CommandHandler<UpdateStockCommand, Void> {

    private final ProductRepository repository;
    private final EventDispatcher eventDispatcher;

    public UpdateStockHandler(ProductRepository repository, EventDispatcher eventDispatcher) {
        this.repository = repository;
        this.eventDispatcher = eventDispatcher;
    }

    @Override
    public Void handle(UpdateStockCommand cmd) {
        Product product = repository.findById(new ProductId(cmd.productId()))
                .orElseThrow(() -> new EntityNotFoundException("Product", cmd.productId().toString()));

        if ("increase".equals(cmd.type())) {
            product.increaseStock(cmd.amount());
        } else {
            product.decreaseStock(cmd.amount());
        }

        repository.save(product);
        eventDispatcher.dispatchAll(product);
        return null;
    }
}
