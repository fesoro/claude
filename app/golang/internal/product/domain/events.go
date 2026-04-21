package domain

import (
	"github.com/google/uuid"
	"github.com/orkhan/ecommerce/internal/shared/domain"
)

type ProductCreatedEvent struct {
	domain.BaseEvent
	ProductID ProductID
	Name      string
	Price     Money
}

func (ProductCreatedEvent) EventName() string { return "ProductCreated" }

type StockDecreasedEvent struct {
	domain.BaseEvent
	ProductID   ProductID
	Amount      int
	NewQuantity int
}

func (StockDecreasedEvent) EventName() string { return "StockDecreased" }

// LowStockIntegrationEvent — Notification context dinləyir → admin email
type LowStockIntegrationEvent struct {
	domain.BaseEvent
	ProductID    uuid.UUID `json:"product_id"`
	ProductName  string    `json:"product_name"`
	CurrentStock int       `json:"current_stock"`
}

func (LowStockIntegrationEvent) EventName() string  { return "LowStockIntegration" }
func (LowStockIntegrationEvent) RoutingKey() string { return "product.stock.low" }
