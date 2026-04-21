package az.ecommerce.product.infrastructure.persistence;

import jakarta.persistence.*;

import java.time.Instant;
import java.util.UUID;

/**
 * Laravel: ProductImageModel
 * Migration: product/V2__create_product_images.sql
 */
@Entity
@Table(name = "product_images")
public class ProductImageEntity {

    @Id
    @Column(columnDefinition = "CHAR(36)")
    private UUID id;

    @Column(name = "product_id", nullable = false, columnDefinition = "CHAR(36)")
    private UUID productId;

    @Column(name = "file_path", nullable = false, length = 2048)
    private String filePath;

    @Column(name = "file_size", nullable = false)
    private long fileSize;

    @Column(name = "mime_type", length = 64)
    private String mimeType;

    @Column(name = "is_primary", nullable = false)
    private boolean isPrimary = false;

    @Column(name = "sort_order", nullable = false)
    private int sortOrder = 0;

    @Column(name = "created_at", nullable = false, updatable = false)
    private Instant createdAt = Instant.now();

    public UUID getId() { return id; }
    public void setId(UUID id) { this.id = id; }
    public UUID getProductId() { return productId; }
    public void setProductId(UUID p) { this.productId = p; }
    public String getFilePath() { return filePath; }
    public void setFilePath(String p) { this.filePath = p; }
    public long getFileSize() { return fileSize; }
    public void setFileSize(long s) { this.fileSize = s; }
    public String getMimeType() { return mimeType; }
    public void setMimeType(String m) { this.mimeType = m; }
    public boolean isPrimary() { return isPrimary; }
    public void setPrimary(boolean p) { this.isPrimary = p; }
    public int getSortOrder() { return sortOrder; }
    public void setSortOrder(int o) { this.sortOrder = o; }
}
