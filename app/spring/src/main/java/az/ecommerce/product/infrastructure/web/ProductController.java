package az.ecommerce.product.infrastructure.web;

import az.ecommerce.product.application.command.createproduct.CreateProductCommand;
import az.ecommerce.product.application.command.updatestock.UpdateStockCommand;
import az.ecommerce.product.application.dto.ProductDto;
import az.ecommerce.product.application.query.getproduct.GetProductQuery;
import az.ecommerce.product.application.query.listproducts.ListProductsQuery;
import az.ecommerce.shared.application.bus.CommandBus;
import az.ecommerce.shared.application.bus.QueryBus;
import az.ecommerce.shared.infrastructure.api.ApiResponse;
import jakarta.validation.Valid;
import org.springframework.http.HttpStatus;
import org.springframework.http.ResponseEntity;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.web.bind.annotation.*;

import java.util.List;
import java.util.Map;
import java.util.UUID;

/**
 * Laravel: app/Http/Controllers/ProductController.php
 * Endpoint-lər (Laravel routes/api.php-dən):
 *   GET    /api/products
 *   GET    /api/products/{id}
 *   POST   /api/products            — auth
 *   PATCH  /api/products/{id}/stock — auth
 */
@RestController
@RequestMapping("/api/products")
public class ProductController {

    private final CommandBus commandBus;
    private final QueryBus queryBus;

    public ProductController(CommandBus commandBus, QueryBus queryBus) {
        this.commandBus = commandBus;
        this.queryBus = queryBus;
    }

    @GetMapping
    public ApiResponse<List<ProductDto>> index(
            @RequestParam(defaultValue = "0") int page,
            @RequestParam(defaultValue = "15") int size) {
        return ApiResponse.success(queryBus.ask(new ListProductsQuery(page, size)));
    }

    @GetMapping("/{id}")
    public ApiResponse<ProductDto> show(@PathVariable UUID id) {
        return ApiResponse.success(queryBus.ask(new GetProductQuery(id)));
    }

    @PostMapping
    @PreAuthorize("isAuthenticated()")
    public ResponseEntity<ApiResponse<Map<String, UUID>>> store(@RequestBody @Valid CreateProductCommand cmd) {
        UUID id = commandBus.dispatch(cmd);
        return ResponseEntity.status(HttpStatus.CREATED).body(
                ApiResponse.success(Map.of("id", id), "Məhsul yaradıldı"));
    }

    @PatchMapping("/{id}/stock")
    @PreAuthorize("isAuthenticated()")
    public ApiResponse<Void> updateStock(@PathVariable UUID id, @RequestBody UpdateStockBody body) {
        commandBus.dispatch(new UpdateStockCommand(id, body.amount(), body.type()));
        return ApiResponse.success(null, "Stok yeniləndi");
    }

    public record UpdateStockBody(int amount, String type) {}
}
