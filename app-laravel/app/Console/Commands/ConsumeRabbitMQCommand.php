<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Src\Shared\Infrastructure\Messaging\RabbitMQConsumer;

/**
 * RABBITMQ CONSUMER ARTISAN COMMAND
 * ==================================
 * Bu command RabbitMQ queue-dan mesajları oxuyur.
 *
 * İSTİFADƏ:
 * php artisan rabbitmq:consume payment_queue order.created,order.cancelled
 *
 * Bu command background process kimi işləyir (dayanmır).
 * Production-da supervisor ilə idarə olunur.
 */
class ConsumeRabbitMQCommand extends Command
{
    protected $signature = 'rabbitmq:consume
        {queue : Queue adı (məs: payment_queue)}
        {bindings : Binding key-lər, vergüllə ayrılmış (məs: order.created,order.cancelled)}';

    protected $description = 'RabbitMQ queue-dan mesajları dinlə və emal et';

    public function handle(RabbitMQConsumer $consumer): int
    {
        $queue = $this->argument('queue');
        $bindings = explode(',', $this->argument('bindings'));

        $this->info("Queue dinlənilir: {$queue}");
        $this->info("Binding keys: " . implode(', ', $bindings));

        $consumer->consume(
            queueName: $queue,
            bindingKeys: $bindings,
            handler: function (array $data) {
                $this->info("Mesaj alındı: " . ($data['event_name'] ?? 'unknown'));
                // Burada event-ə uyğun listener çağırılacaq
                // Production-da bu daha mürəkkəb olacaq
            },
        );

        return self::SUCCESS;
    }
}
