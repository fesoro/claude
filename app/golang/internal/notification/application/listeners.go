// Package application — Notification listeners
//
// Laravel: 4 listener (OrderCreated, PaymentCompleted/Failed, LowStock)
// Spring: 4 @EventListener @Async
// Go: subscriber struct + RabbitMQ consumer (her event üçün method)
package application

import (
	"context"
	"log/slog"

	"github.com/orkhan/ecommerce/internal/notification/infrastructure/channel"
	orderDomain "github.com/orkhan/ecommerce/internal/order/domain"
	paymentDomain "github.com/orkhan/ecommerce/internal/payment/domain"
	productDomain "github.com/orkhan/ecommerce/internal/product/domain"
)

// Templates — yığcam HTML email template-lər (Thymeleaf əvəzi)
const (
	orderConfirmationTpl = `<h2>Salam!</h2><p>Sifarişiniz uğurla qəbul edildi.</p>
<p><strong>Sifariş ID:</strong> {{.OrderID}}</p>
<p><strong>Məbləğ:</strong> {{.Amount}} qəpik {{.Currency}}</p>`

	paymentReceiptTpl = `<h2>Ödəmə qəbzi</h2>
<p><strong>Sifariş:</strong> {{.OrderID}}</p>
<p><strong>Ödəniş ID:</strong> {{.PaymentID}}</p>
<p><strong>Məbləğ:</strong> {{.Amount}} qəpik</p>`

	paymentFailedTpl = `<h2>Üzr istəyirik!</h2>
<p>Sifarişiniz {{.OrderID}} üçün ödəniş uğursuz oldu.</p>
<p><strong>Səbəb:</strong> {{.Reason}}</p>`

	lowStockTpl = `<h2>Diqqət: Az qalıq</h2>
<p>Məhsul: <strong>{{.ProductName}}</strong></p>
<p>Cari stok: <strong>{{.CurrentStock}}</strong> ədəd</p>`
)

type Listeners struct {
	email *channel.EmailChannel
}

func NewListeners(email *channel.EmailChannel) *Listeners {
	return &Listeners{email: email}
}

func (l *Listeners) OnOrderCreated(ctx context.Context, ev orderDomain.OrderCreatedIntegrationEvent) {
	slog.InfoContext(ctx, "OrderCreated → email", "orderID", ev.OrderID)
	_ = l.email.Send("customer@example.com", "Sifariş təsdiqi", orderConfirmationTpl, map[string]any{
		"OrderID":  ev.OrderID, "Amount": ev.TotalAmount, "Currency": ev.Currency,
	})
}

func (l *Listeners) OnPaymentCompleted(ctx context.Context, ev paymentDomain.PaymentCompletedIntegrationEvent) {
	_ = l.email.Send("customer@example.com", "Ödəmə qəbzi", paymentReceiptTpl, map[string]any{
		"OrderID": ev.OrderID, "PaymentID": ev.PaymentID, "Amount": ev.Amount,
	})
}

func (l *Listeners) OnPaymentFailed(ctx context.Context, ev paymentDomain.PaymentFailedIntegrationEvent) {
	_ = l.email.Send("customer@example.com", "Ödəniş uğursuz oldu", paymentFailedTpl, map[string]any{
		"OrderID": ev.OrderID, "Reason": ev.Reason,
	})
}

func (l *Listeners) OnLowStock(ctx context.Context, ev productDomain.LowStockIntegrationEvent) {
	_ = l.email.Send("admin@ecommerce.az", "Az qalıq xəbərdarlığı", lowStockTpl, map[string]any{
		"ProductName": ev.ProductName, "CurrentStock": ev.CurrentStock,
	})
}
