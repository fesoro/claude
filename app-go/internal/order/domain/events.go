package domain

import (
	"github.com/google/uuid"
	"github.com/orkhan/ecommerce/internal/shared/domain"
)

type OrderCreatedEvent struct {
	domain.BaseEvent
	OrderID OrderID
	UserID  uuid.UUID
}

func (OrderCreatedEvent) EventName() string { return "OrderCreated" }

type OrderConfirmedEvent struct {
	domain.BaseEvent
	OrderID OrderID
}

func (OrderConfirmedEvent) EventName() string { return "OrderConfirmed" }

type OrderPaidEvent struct {
	domain.BaseEvent
	OrderID OrderID
}

func (OrderPaidEvent) EventName() string { return "OrderPaid" }

type OrderCancelledEvent struct {
	domain.BaseEvent
	OrderID OrderID
	Reason  string
}

func (OrderCancelledEvent) EventName() string { return "OrderCancelled" }

// === INTEGRATION EVENT (RabbitMQ üçün) ===
type OrderCreatedIntegrationEvent struct {
	domain.BaseEvent
	OrderID     uuid.UUID `json:"order_id"`
	UserID      uuid.UUID `json:"user_id"`
	TotalAmount int64     `json:"total_amount"`
	Currency    string    `json:"currency"`
}

func (OrderCreatedIntegrationEvent) EventName() string  { return "OrderCreatedIntegration" }
func (OrderCreatedIntegrationEvent) RoutingKey() string { return "order.created" }
