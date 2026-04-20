package domain

import (
	"github.com/google/uuid"
	productDomain "github.com/orkhan/ecommerce/internal/product/domain"
	sharedDomain "github.com/orkhan/ecommerce/internal/shared/domain"
)

// Order — Aggregate Root
//
// Laravel: Order.php  ·  Spring: Order.java
type Order struct {
	sharedDomain.AggregateRoot

	id          OrderID
	userID      uuid.UUID
	items       []OrderItem
	status      OrderStatus
	totalAmount productDomain.Money
	address     Address
}

func Create(userID uuid.UUID, items []OrderItem, address Address, currency productDomain.Currency) (*Order, error) {
	if len(items) == 0 {
		return nil, sharedDomain.NewDomainError("Sifariş ən azı 1 məhsuldan ibarət olmalıdır")
	}

	id := GenerateOrderID()
	total := productDomain.ZeroMoney(currency)
	for _, item := range items {
		next, err := total.Add(item.LineTotal())
		if err != nil {
			return nil, err
		}
		total = next
	}

	o := &Order{
		id:          id,
		userID:      userID,
		items:       items,
		status:      OrderStatusPending,
		totalAmount: total,
		address:     address,
	}

	o.RecordEvent(OrderCreatedEvent{
		BaseEvent: sharedDomain.NewBaseEvent(),
		OrderID:   id,
		UserID:    userID,
	})
	o.RecordEvent(OrderCreatedIntegrationEvent{
		BaseEvent:   sharedDomain.NewBaseEvent(),
		OrderID:     id.UUID(),
		UserID:      userID,
		TotalAmount: total.Amount(),
		Currency:    string(currency),
	})
	return o, nil
}

func Reconstitute(id OrderID, userID uuid.UUID, items []OrderItem, address Address,
	status OrderStatus, totalAmount productDomain.Money) *Order {
	return &Order{
		id: id, userID: userID, items: items, address: address,
		status: status, totalAmount: totalAmount,
	}
}

func (o *Order) Confirm() error {
	if err := o.status.RequireTransitionTo(OrderStatusConfirmed); err != nil {
		return err
	}
	o.status = OrderStatusConfirmed
	o.RecordEvent(OrderConfirmedEvent{BaseEvent: sharedDomain.NewBaseEvent(), OrderID: o.id})
	return nil
}

func (o *Order) MarkAsPaid() error {
	if err := o.status.RequireTransitionTo(OrderStatusPaid); err != nil {
		return err
	}
	o.status = OrderStatusPaid
	o.RecordEvent(OrderPaidEvent{BaseEvent: sharedDomain.NewBaseEvent(), OrderID: o.id})
	return nil
}

func (o *Order) Ship() error {
	if err := o.status.RequireTransitionTo(OrderStatusShipped); err != nil {
		return err
	}
	o.status = OrderStatusShipped
	return nil
}

func (o *Order) Deliver() error {
	if err := o.status.RequireTransitionTo(OrderStatusDelivered); err != nil {
		return err
	}
	o.status = OrderStatusDelivered
	return nil
}

func (o *Order) Cancel(reason string) error {
	if o.status == OrderStatusDelivered {
		return sharedDomain.NewDomainError("Çatdırılmış sifarişi ləğv etmək olmaz")
	}
	if o.status == OrderStatusCancelled {
		return sharedDomain.NewDomainError("Sifariş artıq ləğv edilib")
	}
	o.status = OrderStatusCancelled
	o.RecordEvent(OrderCancelledEvent{
		BaseEvent: sharedDomain.NewBaseEvent(), OrderID: o.id, Reason: reason,
	})
	return nil
}

func (o *Order) ID() OrderID                       { return o.id }
func (o *Order) UserID() uuid.UUID                 { return o.userID }
func (o *Order) Items() []OrderItem                { return o.items }
func (o *Order) Status() OrderStatus               { return o.status }
func (o *Order) TotalAmount() productDomain.Money  { return o.totalAmount }
func (o *Order) Address() Address                  { return o.address }

// === REPOSITORY ===
type Repository interface {
	Save(order *Order) error
	FindByID(id OrderID) (*Order, error)
	FindByUserID(userID uuid.UUID) ([]*Order, error)
}

// === SPECIFICATION ===
func OrderCanBeCancelled() sharedDomain.Specification[*Order] {
	return sharedDomain.SpecFunc[*Order](func(o *Order) bool {
		return o.status == OrderStatusPending || o.status == OrderStatusConfirmed
	})
}
