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

	"github.com/orkhan/ecommerce/internal/order/domain"
	"github.com/orkhan/ecommerce/internal/payment/application"
	paymentDomain "github.com/orkhan/ecommerce/internal/payment/domain"
	"github.com/orkhan/ecommerce/internal/shared/application/bus"
)

// OrderSaga — orchestration saga
//
// Step 1: OrderCreated → ProcessPayment dispatch
// Step 2: PaymentCompleted → Order PAID
// Step 3: PaymentFailed → Order Cancel (compensation)
type OrderSaga struct {
	cmdBus *bus.Bus
}

func New(cmdBus *bus.Bus) *OrderSaga {
	return &OrderSaga{cmdBus: cmdBus}
}

// HandleOrderCreated — RabbitMQ-dan integration event tutur
func (s *OrderSaga) HandleOrderCreated(ctx context.Context, event domain.OrderCreatedIntegrationEvent) {
	slog.InfoContext(ctx, "Saga step 1: OrderCreated → ProcessPayment", "orderID", event.OrderID)

	_, err := bus.Dispatch[application.ProcessPaymentCommand, application.PaymentResult](
		ctx, s.cmdBus,
		application.ProcessPaymentCommand{
			OrderID:  event.OrderID,
			UserID:   event.UserID,
			Amount:   event.TotalAmount,
			Currency: event.Currency,
			Method:   string(paymentDomain.PaymentMethodCreditCard),
		})
	if err != nil {
		slog.ErrorContext(ctx, "Saga step 1 failed", "err", err)
	}
}
