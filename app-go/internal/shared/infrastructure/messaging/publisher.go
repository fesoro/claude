// Package messaging — RabbitMQ Publisher + Consumer + Outbox + Inbox + DLQ
package messaging

import (
	"context"
	"encoding/json"
	"log/slog"

	amqp "github.com/rabbitmq/amqp091-go"
)

const ExchangeName = "domain_events"

// Publisher — RabbitMQ-yə publish edir
//
// Laravel: RabbitMQPublisher.php (php-amqplib)
// Spring: RabbitTemplate (spring-boot-starter-amqp)
// Go: amqp091-go (RabbitMQ rəsmi Go client-i)
type Publisher struct {
	channel *amqp.Channel
}

func NewPublisher(conn *amqp.Connection) (*Publisher, error) {
	ch, err := conn.Channel()
	if err != nil {
		return nil, err
	}

	if err := ch.ExchangeDeclare(ExchangeName, "topic", true, false, false, false, nil); err != nil {
		return nil, err
	}

	// DLQ exchange
	if err := ch.ExchangeDeclare(ExchangeName+".dlx", "topic", true, false, false, false, nil); err != nil {
		return nil, err
	}

	return &Publisher{channel: ch}, nil
}

// Publish — JSON payload routing key ilə exchange-ə göndərir
func (p *Publisher) Publish(ctx context.Context, routingKey string, payload any, headers map[string]any) error {
	body, err := json.Marshal(payload)
	if err != nil {
		return err
	}

	err = p.channel.PublishWithContext(ctx, ExchangeName, routingKey, false, false, amqp.Publishing{
		ContentType:  "application/json",
		DeliveryMode: amqp.Persistent,
		Body:         body,
		Headers:      amqp.Table(headers),
	})
	if err != nil {
		slog.ErrorContext(ctx, "RabbitMQ publish xətası", "routingKey", routingKey, "err", err)
		return err
	}
	slog.InfoContext(ctx, "event yayımlandı", "routingKey", routingKey)
	return nil
}

func (p *Publisher) Close() error {
	return p.channel.Close()
}
