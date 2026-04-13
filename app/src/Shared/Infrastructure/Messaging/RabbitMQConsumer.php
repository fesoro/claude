<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Messaging;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Illuminate\Support\Facades\Log;

/**
 * RABBITMQ CONSUMER (Message Broker)
 * ====================================
 * RabbitMQ queue-dan mesajları oxuyub emal edir.
 *
 * NECƏ İŞLƏYİR?
 * 1. Queue-ya subscribe olur (qeydiyyat)
 * 2. Yeni mesaj gəldikdə callback çağırılır
 * 3. Mesaj uğurla emal olunursa → ACK (acknowledge) göndərilir
 * 4. Xəta olarsa → NACK göndərilir, mesaj yenidən queue-ya qayıdır
 *
 * ACK/NACK NƏDİR?
 * - ACK: "Mesajı aldım və emal etdim, silə bilərsən"
 * - NACK: "Xəta oldu, mesajı geri qoy, sonra yenidən cəhd edərəm"
 * Bu mexanizm mesajın itməməsini təmin edir.
 *
 * İSTİFADƏ:
 * Bu consumer Laravel Artisan command olaraq işləyir (background process):
 * php artisan rabbitmq:consume payment_queue
 */
class RabbitMQConsumer
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
     * Queue-nu dinləməyə başla.
     *
     * @param string $queueName Queue adı (məs: "payment_queue")
     * @param array $bindingKeys Hansı event-ləri dinləmək (məs: ["order.created", "order.cancelled"])
     * @param callable $handler Mesajı emal edən funksiya
     */
    public function consume(string $queueName, array $bindingKeys, callable $handler): void
    {
        $channel = $this->getConnection()->channel();

        // Exchange yaratmaq
        $channel->exchange_declare(
            exchange: $this->exchange,
            type: 'topic',
            passive: false,
            durable: true,
            auto_delete: false,
        );

        // Queue yaratmaq
        $channel->queue_declare(
            queue: $queueName,
            passive: false,
            durable: true,      // Queue restart-dan sonra da qalır
            exclusive: false,
            auto_delete: false,
        );

        // Queue-nu Exchange-ə bağlamaq (binding)
        // Hər binding key üçün ayrı binding yaradılır
        foreach ($bindingKeys as $bindingKey) {
            $channel->queue_bind(
                queue: $queueName,
                exchange: $this->exchange,
                routing_key: $bindingKey,
            );
        }

        // Mesaj gəldikdə çağırılacaq callback
        $callback = function (AMQPMessage $message) use ($handler, $queueName) {
            try {
                $data = json_decode($message->getBody(), true);

                Log::info("Mesaj alındı [{$queueName}]", [
                    'event_name' => $data['event_name'] ?? 'unknown',
                    'event_id' => $data['event_id'] ?? 'unknown',
                ]);

                // Mesajı emal et
                $handler($data);

                // ACK — mesaj uğurla emal olundu
                $message->ack();

                Log::info("Mesaj emal edildi [{$queueName}]", [
                    'event_id' => $data['event_id'] ?? 'unknown',
                ]);
            } catch (\Throwable $e) {
                Log::error("Mesaj emal xətası [{$queueName}]", [
                    'error' => $e->getMessage(),
                    'body' => $message->getBody(),
                ]);

                // NACK — mesajı geri qoy (requeue: true)
                $message->nack(requeue: true);
            }
        };

        // prefetch_count: 1 — bir anda yalnız 1 mesaj al
        // Bu consumer yüklənməsinin qarşısını alır
        $channel->basic_qos(prefetch_size: 0, prefetch_count: 1, a_global: false);

        // Consume-a başla
        $channel->basic_consume(
            queue: $queueName,
            consumer_tag: '',
            no_local: false,
            no_ack: false,       // Manual ACK — biz özümüz ACK göndəririk
            exclusive: false,
            nowait: false,
            callback: $callback,
        );

        // Sonsuz dövrə — mesaj gözlə
        Log::info("Consumer başladı: {$queueName}");
        while ($channel->is_consuming()) {
            $channel->wait();
        }
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
