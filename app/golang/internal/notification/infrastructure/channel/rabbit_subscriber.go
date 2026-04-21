// Package channel — RabbitMQ consumer-lər notification listener-ləri üçün
package channel

import (
	"context"
	"encoding/json"
	"log/slog"

	"github.com/orkhan/ecommerce/internal/notification/application"
	orderDomain "github.com/orkhan/ecommerce/internal/order/domain"
	paymentDomain "github.com/orkhan/ecommerce/internal/payment/domain"
	productDomain "github.com/orkhan/ecommerce/internal/product/domain"
	amqp "github.com/rabbitmq/amqp091-go"
)

// SubscribeAll — 4 queue-ya bağlanır, gələn mesajı deserialize edib uyğun listener çağırır
//
// Laravel: SendOrderConfirmationListener → queue dinləyici
// Spring: @RabbitListener(queues = "...")
// Go: amqp091-go ilə manual consumer
func SubscribeAll(ctx context.Context, conn *amqp.Connection, listeners *application.Listeners) error {
	ch, err := conn.Channel()
	if err != nil {
		return err
	}

	// Exchange + 4 queue bind (OutboxPublisher artıq exchange-ə yazır)
	const exchange = "domain_events"
	if err := ch.ExchangeDeclare(exchange, "topic", true, false, false, false, nil); err != nil {
		return err
	}

	queueBindings := map[string]string{
		"notifications.order.created":      "order.created",
		"notifications.payment.completed":  "payment.completed",
		"notifications.payment.failed":     "payment.failed",
		"notifications.product.stock.low":  "product.stock.low",
	}

	for queue, routingKey := range queueBindings {
		if _, err := ch.QueueDeclare(queue, true, false, false, false, nil); err != nil {
			return err
		}
		if err := ch.QueueBind(queue, routingKey, exchange, false, nil); err != nil {
			return err
		}
	}

	// Hər queue üçün consumer goroutine
	go consume(ctx, ch, "notifications.order.created", func(body []byte) {
		var ev orderDomain.OrderCreatedIntegrationEvent
		if err := json.Unmarshal(body, &ev); err != nil {
			slog.Warn("decode error", "err", err)
			return
		}
		listeners.OnOrderCreated(ctx, ev)
	})

	go consume(ctx, ch, "notifications.payment.completed", func(body []byte) {
		var ev paymentDomain.PaymentCompletedIntegrationEvent
		if err := json.Unmarshal(body, &ev); err != nil {
			return
		}
		listeners.OnPaymentCompleted(ctx, ev)
	})

	go consume(ctx, ch, "notifications.payment.failed", func(body []byte) {
		var ev paymentDomain.PaymentFailedIntegrationEvent
		if err := json.Unmarshal(body, &ev); err != nil {
			return
		}
		listeners.OnPaymentFailed(ctx, ev)
	})

	go consume(ctx, ch, "notifications.product.stock.low", func(body []byte) {
		var ev productDomain.LowStockIntegrationEvent
		if err := json.Unmarshal(body, &ev); err != nil {
			return
		}
		listeners.OnLowStock(ctx, ev)
	})

	return nil
}

func consume(ctx context.Context, ch *amqp.Channel, queue string, handler func([]byte)) {
	msgs, err := ch.Consume(queue, "", false, false, false, false, nil)
	if err != nil {
		slog.Error("consume start failed", "queue", queue, "err", err)
		return
	}
	slog.Info("RabbitMQ consumer başladı", "queue", queue)

	for {
		select {
		case <-ctx.Done():
			return
		case msg, ok := <-msgs:
			if !ok {
				return
			}
			handler(msg.Body)
			_ = msg.Ack(false)
		}
	}
}
