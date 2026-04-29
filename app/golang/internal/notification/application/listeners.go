// Package application — Notification listeners
//
// Laravel: 4 listener (OrderCreated, PaymentCompleted/Failed, LowStock)
// Spring: 4 @EventListener @Async
// Go: subscriber struct + RabbitMQ consumer (her event üçün method)
//
// Email şablonları: templates/emails/*.html (Go text/template sintaksisi)
// Şablonlar tapılmadıqda inline fallback-lər istifadə olunur.
package application

import (
	"context"
	"log/slog"
	"os"
	"path/filepath"

	"github.com/orkhan/ecommerce/internal/notification/infrastructure/channel"
	orderDomain "github.com/orkhan/ecommerce/internal/order/domain"
	paymentDomain "github.com/orkhan/ecommerce/internal/payment/domain"
	productDomain "github.com/orkhan/ecommerce/internal/product/domain"
)

// TemplatesDir — şablon qovluğunun yolu (runtime-da dəyişdirilə bilər)
// Docker-da: /app/templates/emails
// Local-da: templates/emails (işçi qovluğa görə)
var TemplatesDir = "templates/emails"

// loadTemplate — fayl sistemimdən HTML şablon yükləyir.
// Fayl tapılmadıqda fallback string qaytarır.
// Bu Laravel-in Blade ve Spring-in Thymeleaf-a bənzər file-based template yükləməsidir.
func loadTemplate(name, fallback string) string {
	data, err := os.ReadFile(filepath.Join(TemplatesDir, name))
	if err != nil {
		slog.Debug("email şablonu fayldan yüklənə bilmədi, inline fallback istifadə edilir",
			"template", name, "err", err)
		return fallback
	}
	return string(data)
}

// Inline fallback-lər — fayl sistemi olmadıqda (test mühiti) istifadə olunur.
// Laravel: resources/views/emails/*.blade.php
// Spring:  src/main/resources/templates/*.html
// Go:      templates/emails/*.html  (+ bu inline fallback-lər)
const (
	fallbackOrderConfirmation = `<h2>Salam!</h2><p>Sifarişiniz uğurla qəbul edildi.</p>
<p><strong>Sifariş ID:</strong> {{.OrderID}}</p>
<p><strong>Məbləğ:</strong> {{.Amount}} qəpik {{.Currency}}</p>`

	fallbackPaymentReceipt = `<h2>Ödəmə qəbzi</h2>
<p><strong>Sifariş:</strong> {{.OrderID}}</p>
<p><strong>Ödəniş ID:</strong> {{.PaymentID}}</p>
<p><strong>Məbləğ:</strong> {{.Amount}} qəpik</p>`

	fallbackPaymentFailed = `<h2>Üzr istəyirik!</h2>
<p>Sifarişiniz {{.OrderID}} üçün ödəniş uğursuz oldu.</p>
<p><strong>Səbəb:</strong> {{.Reason}}</p>`

	fallbackLowStock = `<h2>Diqqət: Az qalıq</h2>
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
	tpl := loadTemplate("order-confirmation.html", fallbackOrderConfirmation)
	_ = l.email.Send("customer@example.com", "Sifariş təsdiqi", tpl, map[string]any{
		"OrderID":  ev.OrderID,
		"Amount":   ev.TotalAmount,
		"Currency": ev.Currency,
	})
}

func (l *Listeners) OnPaymentCompleted(ctx context.Context, ev paymentDomain.PaymentCompletedIntegrationEvent) {
	tpl := loadTemplate("payment-receipt.html", fallbackPaymentReceipt)
	_ = l.email.Send("customer@example.com", "Ödəmə qəbzi", tpl, map[string]any{
		"OrderID":   ev.OrderID,
		"PaymentID": ev.PaymentID,
		"Amount":    ev.Amount,
	})
}

func (l *Listeners) OnPaymentFailed(ctx context.Context, ev paymentDomain.PaymentFailedIntegrationEvent) {
	tpl := loadTemplate("payment-failed.html", fallbackPaymentFailed)
	_ = l.email.Send("customer@example.com", "Ödəniş uğursuz oldu", tpl, map[string]any{
		"OrderID": ev.OrderID,
		"Reason":  ev.Reason,
	})
}

func (l *Listeners) OnLowStock(ctx context.Context, ev productDomain.LowStockIntegrationEvent) {
	tpl := loadTemplate("low-stock-alert.html", fallbackLowStock)
	_ = l.email.Send("admin@ecommerce.az", "Az qalıq xəbərdarlığı", tpl, map[string]any{
		"ProductName":  ev.ProductName,
		"CurrentStock": ev.CurrentStock,
	})
}
