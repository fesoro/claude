package com.example.ecommerce.presentation;

import com.example.ecommerce.application.ProductService;
import com.example.ecommerce.domain.product.Product;
import jakarta.validation.Valid;
import jakarta.validation.constraints.DecimalMin;
import jakarta.validation.constraints.Min;
import jakarta.validation.constraints.NotBlank;
import org.springframework.http.HttpStatus;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.*;

import java.math.BigDecimal;
import java.util.List;
import java.util.Map;
import java.util.NoSuchElementException;

@RestController
@RequestMapping("/api/products")
public class ProductController {

    private final ProductService service;

    public ProductController(ProductService service) { this.service = service; }

    @GetMapping
    public List<Product> list() { return service.findAll(); }

    @GetMapping("/{id}")
    public Product get(@PathVariable Long id) { return service.findById(id); }

    @PostMapping
    @ResponseStatus(HttpStatus.CREATED)
    public Product create(@RequestBody @Valid CreateProductRequest req) {
        return service.create(req.name(), req.price(), req.stock());
    }

    @ExceptionHandler({NoSuchElementException.class, IllegalArgumentException.class})
    public ResponseEntity<Map<String, String>> handleError(RuntimeException ex) {
        int status = ex instanceof NoSuchElementException ? 404 : 400;
        return ResponseEntity.status(status).body(Map.of("error", ex.getMessage()));
    }

    record CreateProductRequest(
            @NotBlank String name,
            @DecimalMin("0.01") BigDecimal price,
            @Min(0) int stock
    ) {}
}
