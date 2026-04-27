CREATE TABLE orders (
    id            BIGSERIAL PRIMARY KEY,
    product_id    BIGINT          NOT NULL,
    quantity      INT             NOT NULL CHECK (quantity > 0),
    customer_email VARCHAR(255)   NOT NULL,
    status        VARCHAR(50)     NOT NULL DEFAULT 'PENDING',
    created_at    TIMESTAMP       NOT NULL DEFAULT NOW(),
    updated_at    TIMESTAMP       NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_orders_customer_email ON orders(customer_email);
CREATE INDEX idx_orders_status ON orders(status);
