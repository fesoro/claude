-- Laravel: OrderItemModel → order_items (Order aggregate-in entity-si)
CREATE TABLE order_items (
    id CHAR(36) NOT NULL PRIMARY KEY,
    order_id CHAR(36) NOT NULL,
    product_id CHAR(36) NOT NULL COMMENT 'product_db.products.id-yə referans (cross-context FK YOXDUR)',
    product_name VARCHAR(255) NOT NULL COMMENT 'snapshot — Product silinsə də qalır',
    unit_price_amount BIGINT NOT NULL COMMENT 'qəpiklə',
    unit_price_currency CHAR(3) NOT NULL,
    quantity INT NOT NULL,
    line_total BIGINT NOT NULL COMMENT 'unit_price * quantity',
    INDEX idx_oi_order (order_id),
    INDEX idx_oi_product (product_id),
    CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
