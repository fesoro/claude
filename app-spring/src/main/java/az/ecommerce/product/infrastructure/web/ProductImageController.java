package az.ecommerce.product.infrastructure.web;

import az.ecommerce.product.infrastructure.persistence.ProductImageEntity;
import az.ecommerce.product.infrastructure.persistence.ProductImageRepository;
import az.ecommerce.shared.domain.exception.EntityNotFoundException;
import az.ecommerce.shared.infrastructure.api.ApiResponse;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.web.bind.annotation.*;
import org.springframework.web.multipart.MultipartFile;

import java.util.List;
import java.util.UUID;

/**
 * Laravel: app/Http/Controllers/ProductImageController.php
 *   GET    /api/products/{id}/images
 *   POST   /api/products/{id}/images
 *   DELETE /api/products/{id}/images/{imageId}
 *   PATCH  /api/products/{id}/images/{imageId}/primary
 */
@RestController
@RequestMapping("/api/products/{productId}/images")
public class ProductImageController {

    private final ProductImageRepository repository;

    public ProductImageController(ProductImageRepository repository) {
        this.repository = repository;
    }

    @GetMapping
    public ApiResponse<List<ProductImageEntity>> index(@PathVariable UUID productId) {
        return ApiResponse.success(repository.findByProductIdOrderBySortOrderAsc(productId));
    }

    @PostMapping
    @PreAuthorize("isAuthenticated()")
    public ApiResponse<ProductImageEntity> store(@PathVariable UUID productId,
                                                  @RequestParam("file") MultipartFile file) {
        ProductImageEntity img = new ProductImageEntity();
        img.setId(UUID.randomUUID());
        img.setProductId(productId);
        // Real layihədə file storage-a yüklənir (S3 və ya local)
        img.setFilePath("/storage/products/" + productId + "/" + img.getId() + ".jpg");
        img.setFileSize(file.getSize());
        img.setMimeType(file.getContentType());
        img.setSortOrder(repository.findByProductIdOrderBySortOrderAsc(productId).size());
        return ApiResponse.success(repository.save(img), "Şəkil yükləndi");
    }

    @DeleteMapping("/{imageId}")
    @PreAuthorize("isAuthenticated()")
    public ApiResponse<Void> destroy(@PathVariable UUID productId, @PathVariable UUID imageId) {
        repository.deleteById(imageId);
        return ApiResponse.success(null, "Silindi");
    }

    @PatchMapping("/{imageId}/primary")
    @PreAuthorize("isAuthenticated()")
    public ApiResponse<Void> setPrimary(@PathVariable UUID productId, @PathVariable UUID imageId) {
        repository.findByProductIdAndIsPrimaryTrue(productId).forEach(p -> {
            p.setPrimary(false);
            repository.save(p);
        });
        ProductImageEntity img = repository.findById(imageId)
                .orElseThrow(() -> new EntityNotFoundException("ProductImage", imageId.toString()));
        img.setPrimary(true);
        repository.save(img);
        return ApiResponse.success(null, "Primary olaraq təyin edildi");
    }
}
