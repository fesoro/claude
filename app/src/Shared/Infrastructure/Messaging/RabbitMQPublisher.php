<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Messaging;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Src\Shared\Domain\IntegrationEvent;

/**
 * RABBITMQ PUBLISHER (Message Broker)
 * =====================================
 * Integration Event-ləri RabbitMQ-ya göndərir.
 *
 * RABBITMQ NƏDİR?
 * - Message Broker — mesajları bir servisdən digərinə çatdıran vasitəçi.
 * - Producer (göndərən) → RabbitMQ → Consumer (qəbul edən)
 * - Mesajlar queue-da (növbədə) saxlanılır, consumer hazır olanda emal edir.
 *
 * ƏSAS ANLAYIŞLAR:
 * - Exchange: Mesajın haraya getməsini müəyyən edən "poçt şöbəsi"
 * - Queue: Mesajların gözlədiyi növbə
 * - Routing Key: Mesajın hansı queue-ya düşməsini təyin edən açar
 * - Binding: Exchange ilə Queue arasındakı əlaqə
 *
 * NÜMUNƏ AXIN:
 * OrderCreatedEvent → Exchange: "events" → Routing Key: "order.created"
 *   → Queue: "payment_queue" (binding: "order.*")
 *   → Queue: "notification_queue" (binding: "order.*")
 */
class RabbitMQPublisher
{
    private ?AMQPStreamConnection $connection = null;

    public function __construct(
        private string $host = 'localhost',
        private int $port = 5672,
        private string $user = 'guest',
        private string $password = 'guest',
        private string $exchange = 'domain_events',
    ) {}

    /**
     * Integration Event-i RabbitMQ-ya göndər.
     */
    public function publish(IntegrationEvent $event): void
    {
        $channel = $this->getConnection()->channel();

        // Exchange yaratmaq (əgər yoxdursa)
        // 'topic' tipi — routing key pattern ilə mesaj yönləndirmə imkanı verir
        // Məsələn: "order.*" pattern-i "order.created" və "order.cancelled" mesajlarını tutur
        $channel->exchange_declare(
            exchange: $this->exchange,
            type: 'topic',
            passive: false,
            durable: true,     // RabbitMQ restart olsa belə exchange saxlanılır
            auto_delete: false,
        );

        // Mesajı hazırla
        $message = new AMQPMessage(
            body: json_encode([
                'event_id' => $event->eventId(),
                'event_name' => $event->eventName(),
                'source_context' => $event->sourceContext(),
                'occurred_at' => $event->occurredAt()->format('c'),
                'data' => $event->toArray(),
            ]),
            properties: [
                'content_type' => 'application/json',
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT, // Mesaj disk-ə yazılır, itmir
                'message_id' => $event->eventId(),
                'timestamp' => $event->occurredAt()->getTimestamp(),
                'type' => $event->eventName(),
            ],
        );

        // Mesajı göndər
        $channel->basic_publish(
            msg: $message,
            exchange: $this->exchange,
            routing_key: $event->routingKey(), // Məs: "order.created"
        );

        $channel->close();
    }

    private function getConnection(): AMQPStreamConnection
    {
        if ($this->connection === null || !$this->connection->isConnected()) {
            $this->connection = new AMQPStreamConnection(
                $this->host,
                $this->port,
                $this->user,
                $this->password,
            );
        }

        return $this->connection;
    }

    public function __destruct()
    {
        $this->connection?->close();
    }
}
