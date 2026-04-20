// Package domain — Order context
package domain

import (
	"github.com/orkhan/ecommerce/internal/shared/domain"
)

// OrderStatus — state machine
//
// Laravel: OrderStatusEnum.php  ·  Spring: OrderStatusEnum.java
// Go: typed string constants + transition map
type OrderStatus string

const (
	OrderStatusPending   OrderStatus = "PENDING"
	OrderStatusConfirmed OrderStatus = "CONFIRMED"
	OrderStatusPaid      OrderStatus = "PAID"
	OrderStatusShipped   OrderStatus = "SHIPPED"
	OrderStatusDelivered OrderStatus = "DELIVERED"
	OrderStatusCancelled OrderStatus = "CANCELLED"
)

var allowedTransitions = map[OrderStatus]map[OrderStatus]bool{
	OrderStatusPending:   {OrderStatusConfirmed: true, OrderStatusCancelled: true},
	OrderStatusConfirmed: {OrderStatusPaid: true, OrderStatusCancelled: true},
	OrderStatusPaid:      {OrderStatusShipped: true, OrderStatusCancelled: true},
	OrderStatusShipped:   {OrderStatusDelivered: true},
	OrderStatusDelivered: {},
	OrderStatusCancelled: {},
}

func (s OrderStatus) CanTransitionTo(target OrderStatus) bool {
	return allowedTransitions[s][target]
}

func (s OrderStatus) RequireTransitionTo(target OrderStatus) error {
	if !s.CanTransitionTo(target) {
		return domain.NewDomainError("Yanlış status keçidi: " + string(s) + " → " + string(target))
	}
	return nil
}

func (s OrderStatus) IsFinal() bool {
	return s == OrderStatusDelivered || s == OrderStatusCancelled
}

func (s OrderStatus) Label() string {
	switch s {
	case OrderStatusPending:
		return "Gözləyir"
	case OrderStatusConfirmed:
		return "Təsdiqlənib"
	case OrderStatusPaid:
		return "Ödənilib"
	case OrderStatusShipped:
		return "Göndərilib"
	case OrderStatusDelivered:
		return "Çatdırılıb"
	case OrderStatusCancelled:
		return "Ləğv edilib"
	}
	return string(s)
}
