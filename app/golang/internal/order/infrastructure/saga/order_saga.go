// Package saga — OrderSaga (Spring @EventListener-based version)
//
// Laravel: OrderSaga.php  ·  Spring: OrderSaga.java (Spring @EventListener)
// Go: subscriber struct + RabbitMQ consumer (Watermill əvəzinə manual)
//
// Real production-da Watermill istifadə tövsiyə olunur:
//   https://watermill.io/docs/cqrs/
package saga

import (
	"context"
	"log/slog"

	orderApp "github.com/orkhan/ecommerce/internal/order/application"
	orderDomain "github.com/orkhan/ecommerce/internal/order/domain"
	paymentApp "github.com/orkhan/ecommerce/internal/payment/application"
	paymentDomain "github.com/orkhan/ecommerce/internal/payment/domain"
	"github.com/orkhan/ecommerce/internal/shared/application/bus"
)

// OrderSaga — orchestration saga
//
// Step 1: OrderCreated     → ProcessPayment dispatch
// Step 2: PaymentCompleted → Order PAID statusuna keçir
// Step 3: PaymentFailed    → Order ləğv et (compensation)
type OrderSaga struct {
	cmdBus *bus.Bus
}

func New(cmdBus *bus.Bus) *OrderSaga {
	return &OrderSaga{cmdBus: cmdBus}
}

// HandleOrderCreated — RabbitMQ-dan integration event tutur
func (s *OrderSaga) HandleOrderCreated(ctx context.Context, event orderDomain.OrderCreatedIntegrationEvent) {
	slog.InfoContext(ctx, "Saga step 1: OrderCreated → ProcessPayment", "orderID", event.OrderID)

	_, err := bus.Dispatch[paymentApp.ProcessPaymentCommand, paymentApp.PaymentResult](
		ctx, s.cmdBus,
		paymentApp.ProcessPaymentCommand{
			OrderID:  event.OrderID,
			UserID:   event.UserID,
			Amount:   event.TotalAmount,
			Currency: event.Currency,
			Method:   string(paymentDomain.PaymentMethodCreditCard),
		})
	if err != nil {
		slog.ErrorContext(ctx, "Saga step 1 failed", "orderID", event.OrderID, "err", err)
	}
}

// HandlePaymentCompleted — Saga step 2: ödəniş uğurlu → sifarişi PAID et
func (s *OrderSaga) HandlePaymentCompleted(ctx context.Context, event paymentDomain.PaymentCompletedIntegrationEvent) {
	slog.InfoContext(ctx, "Saga step 2: PaymentCompleted → UpdateOrderStatus PAID", "orderID", event.OrderID)

	_, err := bus.Dispatch[orderApp.UpdateOrderStatusCommand, struct{}](
		ctx, s.cmdBus,
		orderApp.UpdateOrderStatusCommand{
			OrderID: event.OrderID,
			Target:  orderDomain.OrderStatusPaid,
		})
	if err != nil {
		slog.ErrorContext(ctx, "Saga step 2 failed", "orderID", event.OrderID, "err", err)
	}
}

// HandlePaymentFailed — Saga step 3 (compensation): ödəniş uğursuz → sifarişi ləğv et
func (s *OrderSaga) HandlePaymentFailed(ctx context.Context, event paymentDomain.PaymentFailedIntegrationEvent) {
	slog.WarnContext(ctx, "Saga compensation: PaymentFailed → CancelOrder", "orderID", event.OrderID, "reason", event.Reason)

	_, err := bus.Dispatch[orderApp.CancelOrderCommand, struct{}](
		ctx, s.cmdBus,
		orderApp.CancelOrderCommand{
			OrderID: event.OrderID,
			Reason:  "Payment failed: " + event.Reason,
		})
	if err != nil {
		slog.ErrorContext(ctx, "Saga compensation failed", "orderID", event.OrderID, "err", err)
	}
}
