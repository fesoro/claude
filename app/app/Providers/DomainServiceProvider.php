<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Src\Shared\Application\Bus\CommandBus;
use Src\Shared\Application\Bus\QueryBus;
use Src\Shared\Application\Middleware\LoggingMiddleware;
use Src\Shared\Application\Middleware\TransactionMiddleware;
use Src\Shared\Application\Middleware\ValidationMiddleware;
use Src\Shared\Infrastructure\Bus\EventDispatcher;
use Src\Shared\Infrastructure\Bus\SimpleCommandBus;
use Src\Shared\Infrastructure\Bus\SimpleQueryBus;
use Src\Shared\Infrastructure\Messaging\RabbitMQConsumer;
use Src\Shared\Infrastructure\Messaging\RabbitMQPublisher;

/**
 * DOMAIN SERVICE PROVIDER (Composition Root)
 * ============================================
 * Bu provider bütün DDD arxitekturasının "composition root"-udur.
 *
 * COMPOSITION ROOT NƏDİR?
 * - Bütün dependency-lərin bir-birinə bağlandığı tək nöqtə.
 * - Interface → Implementation mapping burada edilir.
 * - Laravel-də bu ServiceProvider vasitəsilə həyata keçirilir.
 *
 * DEPENDENCY INJECTION (DI) PRİNSİPİ:
 * - Kod interface-ə bağlıdır, konkret class-a deyil.
 * - CommandBus interface → SimpleCommandBus implementation
 * - Bu sayədə implementation-ı dəyişmək çox asandır.
 *
 * MIDDLEWARE PIPELINE SİRASI:
 * Command → Logging → Validation → Transaction → Handler
 * Bu sıra vacibdir:
 * 1. Logging: Əvvəlcə log et (hətta validation xətası olsa belə loqda görünsün)
 * 2. Validation: Data düzgündürmü yoxla
 * 3. Transaction: Hər şeyi bir transaction-da icra et
 */
class DomainServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // RabbitMQ Publisher — Integration Event-ləri göndərmək üçün
        $this->app->singleton(RabbitMQPublisher::class, function () {
            $config = config('rabbitmq');

            return new RabbitMQPublisher(
                host: $config['host'],
                port: $config['port'],
                user: $config['user'],
                password: $config['password'],
                exchange: $config['exchange'],
            );
        });

        // RabbitMQ Consumer
        $this->app->singleton(RabbitMQConsumer::class, function () {
            $config = config('rabbitmq');

            return new RabbitMQConsumer(
                host: $config['host'],
                port: $config['port'],
                user: $config['user'],
                password: $config['password'],
                exchange: $config['exchange'],
            );
        });

        // Event Dispatcher — domain event-ləri dinləyicilərə çatdırır
        $this->app->singleton(EventDispatcher::class, function ($app) {
            return new EventDispatcher(
                rabbitMQPublisher: $app->make(RabbitMQPublisher::class),
            );
        });

        // Command Bus — middleware pipeline ilə
        $this->app->singleton(CommandBus::class, function ($app) {
            $bus = new SimpleCommandBus($app);

            // Middleware pipeline sırası (əvvəl əlavə olunan əvvəl işləyir)
            $bus->addMiddleware(new LoggingMiddleware());
            $bus->addMiddleware(new ValidationMiddleware());
            $bus->addMiddleware(new TransactionMiddleware());

            return $bus;
        });

        // Query Bus — middleware olmadan (oxuma əməliyyatına lazım deyil)
        $this->app->singleton(QueryBus::class, function ($app) {
            return new SimpleQueryBus($app);
        });
    }

    public function boot(): void
    {
        // Hər bounded context-in öz ServiceProvider-i var
        // Onlar burada yüklənir
    }
}
