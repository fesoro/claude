// Package application — Order CQRS handler-ləri
package application

import (
	"context"

	"github.com/google/uuid"
	orderDomain "github.com/orkhan/ecommerce/internal/order/domain"
	productDomain "github.com/orkhan/ecommerce/internal/product/domain"
	sharedDomain "github.com/orkhan/ecommerce/internal/shared/domain"
)

// === DTOs ===

type AddressDTO struct {
	Street  string `json:"street" validate:"required"`
	City    string `json:"city" validate:"required"`
	Zip     string `json:"zip" validate:"required"`
	Country string `json:"country" validate:"required"`
}

type OrderItemDTO struct {
	ProductID         uuid.UUID `json:"product_id" validate:"required"`
	ProductName       string    `json:"product_name" validate:"required"`
	UnitPriceAmount   int64     `json:"unit_price_amount" validate:"gt=0"`
	UnitPriceCurrency string    `json:"unit_price_currency" validate:"required"`
	Quantity          int       `json:"quantity" validate:"gt=0"`
}

type OrderDTO struct {
	ID          uuid.UUID      `json:"id"`
	UserID      uuid.UUID      `json:"user_id"`
	Status      string         `json:"status"`
	StatusLabel string         `json:"status_label"`
	TotalAmount int64          `json:"total_amount"`
	Currency    string         `json:"currency"`
	Items       []OrderItemDTO `json:"items"`
	Address     AddressDTO     `json:"address"`
}

func DTOFromDomain(o *orderDomain.Order) OrderDTO {
	items := make([]OrderItemDTO, len(o.Items()))
	for i, item := range o.Items() {
		items[i] = OrderItemDTO{
			ProductID:         item.ProductID,
			ProductName:       item.ProductName,
			UnitPriceAmount:   item.UnitPrice.Amount(),
			UnitPriceCurrency: string(item.UnitPrice.Currency()),
			Quantity:          item.Quantity,
		}
	}
	addr := o.Address()
	return OrderDTO{
		ID:          o.ID().UUID(),
		UserID:      o.UserID(),
		Status:      string(o.Status()),
		StatusLabel: o.Status().Label(),
		TotalAmount: o.TotalAmount().Amount(),
		Currency:    string(o.TotalAmount().Currency()),
		Items:       items,
		Address:     AddressDTO{Street: addr.Street, City: addr.City, Zip: addr.Zip, Country: addr.Country},
	}
}

// === COMMANDS ===

type CreateOrderCommand struct {
	UserID   uuid.UUID      `validate:"required"`
	Items    []OrderItemDTO `validate:"required,min=1,dive"`
	Address  AddressDTO     `validate:"required"`
	Currency string         `validate:"required,oneof=USD EUR AZN"`
}

type CancelOrderCommand struct {
	OrderID uuid.UUID `validate:"required"`
	Reason  string
}

type UpdateOrderStatusCommand struct {
	OrderID uuid.UUID                 `validate:"required"`
	Target  orderDomain.OrderStatus   `validate:"required"`
}

type GetOrderQuery struct {
	OrderID uuid.UUID
}

type ListOrdersQuery struct {
	UserID uuid.UUID
}

// === EVENT DISPATCHER ===

type EventDispatcher interface {
	DispatchAll(ctx context.Context, aggregate interface{ PullDomainEvents() []sharedDomain.DomainEvent }) error
}

// === HANDLERS ===

type CreateOrderHandler struct {
	repo            orderDomain.Repository
	eventDispatcher EventDispatcher
}

func NewCreateOrderHandler(repo orderDomain.Repository, ed EventDispatcher) *CreateOrderHandler {
	return &CreateOrderHandler{repo: repo, eventDispatcher: ed}
}

func (h *CreateOrderHandler) Handle(ctx context.Context, cmd CreateOrderCommand) (uuid.UUID, error) {
	currency, err := productDomain.ParseCurrency(cmd.Currency)
	if err != nil {
		return uuid.Nil, err
	}

	items := make([]orderDomain.OrderItem, len(cmd.Items))
	for i, dto := range cmd.Items {
		unitPriceCurrency, _ := productDomain.ParseCurrency(dto.UnitPriceCurrency)
		unitPrice, err := productDomain.NewMoney(dto.UnitPriceAmount, unitPriceCurrency)
		if err != nil {
			return uuid.Nil, err
		}
		item, err := orderDomain.NewOrderItem(dto.ProductID, dto.ProductName, unitPrice, dto.Quantity)
		if err != nil {
			return uuid.Nil, err
		}
		items[i] = item
	}

	address, err := orderDomain.NewAddress(cmd.Address.Street, cmd.Address.City,
		cmd.Address.Zip, cmd.Address.Country)
	if err != nil {
		return uuid.Nil, err
	}

	order, err := orderDomain.Create(cmd.UserID, items, address, currency)
	if err != nil {
		return uuid.Nil, err
	}
	if err := h.repo.Save(order); err != nil {
		return uuid.Nil, err
	}
	_ = h.eventDispatcher.DispatchAll(ctx, order)
	return order.ID().UUID(), nil
}

type CancelOrderHandler struct {
	repo            orderDomain.Repository
	eventDispatcher EventDispatcher
}

func NewCancelOrderHandler(repo orderDomain.Repository, ed EventDispatcher) *CancelOrderHandler {
	return &CancelOrderHandler{repo: repo, eventDispatcher: ed}
}

func (h *CancelOrderHandler) Handle(ctx context.Context, cmd CancelOrderCommand) (struct{}, error) {
	order, err := h.repo.FindByID(orderDomain.OrderID(cmd.OrderID))
	if err != nil || order == nil {
		return struct{}{}, sharedDomain.NewEntityNotFoundError("Order", cmd.OrderID.String())
	}

	if !orderDomain.OrderCanBeCancelled().IsSatisfiedBy(order) {
		return struct{}{}, sharedDomain.NewDomainError("Bu sifariş hazırkı statusda ləğv edilə bilməz: " + string(order.Status()))
	}

	reason := cmd.Reason
	if reason == "" {
		reason = "İstifadəçi tərəfindən"
	}
	if err := order.Cancel(reason); err != nil {
		return struct{}{}, err
	}
	if err := h.repo.Save(order); err != nil {
		return struct{}{}, err
	}
	_ = h.eventDispatcher.DispatchAll(ctx, order)
	return struct{}{}, nil
}

type UpdateOrderStatusHandler struct {
	repo            orderDomain.Repository
	eventDispatcher EventDispatcher
}

func NewUpdateOrderStatusHandler(repo orderDomain.Repository, ed EventDispatcher) *UpdateOrderStatusHandler {
	return &UpdateOrderStatusHandler{repo: repo, eventDispatcher: ed}
}

func (h *UpdateOrderStatusHandler) Handle(ctx context.Context, cmd UpdateOrderStatusCommand) (struct{}, error) {
	order, err := h.repo.FindByID(orderDomain.OrderID(cmd.OrderID))
	if err != nil || order == nil {
		return struct{}{}, sharedDomain.NewEntityNotFoundError("Order", cmd.OrderID.String())
	}

	switch cmd.Target {
	case orderDomain.OrderStatusConfirmed:
		err = order.Confirm()
	case orderDomain.OrderStatusPaid:
		err = order.MarkAsPaid()
	case orderDomain.OrderStatusShipped:
		err = order.Ship()
	case orderDomain.OrderStatusDelivered:
		err = order.Deliver()
	case orderDomain.OrderStatusCancelled:
		err = order.Cancel("Status keçidi ilə")
	default:
		return struct{}{}, sharedDomain.NewDomainError("Bu status birbaşa təyin edilə bilməz: " + string(cmd.Target))
	}
	if err != nil {
		return struct{}{}, err
	}

	if err := h.repo.Save(order); err != nil {
		return struct{}{}, err
	}
	_ = h.eventDispatcher.DispatchAll(ctx, order)
	return struct{}{}, nil
}

type GetOrderHandler struct {
	repo orderDomain.Repository
}

func NewGetOrderHandler(repo orderDomain.Repository) *GetOrderHandler {
	return &GetOrderHandler{repo: repo}
}

func (h *GetOrderHandler) Handle(ctx context.Context, q GetOrderQuery) (OrderDTO, error) {
	order, err := h.repo.FindByID(orderDomain.OrderID(q.OrderID))
	if err != nil || order == nil {
		return OrderDTO{}, sharedDomain.NewEntityNotFoundError("Order", q.OrderID.String())
	}
	return DTOFromDomain(order), nil
}

type ListOrdersHandler struct {
	repo orderDomain.Repository
}

func NewListOrdersHandler(repo orderDomain.Repository) *ListOrdersHandler {
	return &ListOrdersHandler{repo: repo}
}

func (h *ListOrdersHandler) Handle(ctx context.Context, q ListOrdersQuery) ([]OrderDTO, error) {
	orders, err := h.repo.FindByUserID(q.UserID)
	if err != nil {
		return nil, err
	}
	dtos := make([]OrderDTO, len(orders))
	for i, o := range orders {
		dtos[i] = DTOFromDomain(o)
	}
	return dtos, nil
}
