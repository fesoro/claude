<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Bus;

use Illuminate\Contracts\Container\Container;
use Src\Shared\Application\Bus\Command;
use Src\Shared\Application\Bus\CommandBus;
use Src\Shared\Application\Bus\CommandHandler;
use Src\Shared\Application\Middleware\Middleware;

/**
 * SIMPLE COMMAND BUS (Infrastructure Implementation)
 * ===================================================
 * CommandBus interface-inin konkret implementasiyası.
 *
 * NECƏ İŞLƏYİR?
 * 1. Command gəlir (məs: CreateOrderCommand)
 * 2. Middleware pipeline-dan keçir: Logging → Validation → Transaction
 * 3. Handler tapılır (CreateOrderCommand → CreateOrderHandler)
 * 4. Handler icra olunur
 *
 * HANDLER MAPPING:
 * Command class adından "Command" sözünü "Handler" ilə əvəz edir:
 * CreateOrderCommand → CreateOrderHandler (eyni namespace-dən)
 *
 * DEPENDENCY INJECTION:
 * Laravel Container istifadə edir ki, Handler-in dependency-lərini resolve etsin.
 */
class SimpleCommandBus implements CommandBus
{
    /** @var Middleware[] */
    private array $middlewares = [];

    /** @var array<class-string<Command>, class-string<CommandHandler>> */
    private array $handlers = [];

    public function __construct(
        private Container $container,
    ) {}

    /**
     * Middleware əlavə et (sıra vacibdir — əvvəl əlavə olunan əvvəl işləyir).
     */
    public function addMiddleware(Middleware $middleware): void
    {
        $this->middlewares[] = $middleware;
    }

    /**
     * Command-Handler cütlüyünü qeydiyyatdan keçir.
     */
    public function register(string $commandClass, string $handlerClass): void
    {
        $this->handlers[$commandClass] = $handlerClass;
    }

    /**
     * Command-ı dispatch et — middleware pipeline-dan keçirib Handler-ə çatdır.
     */
    public function dispatch(Command $command): mixed
    {
        // Handler-i tap
        $handlerClass = $this->resolveHandler($command);

        // Middleware pipeline qur:
        // Son handler-dən başla, hər middleware-i onun ətrafına sar (wrap)
        // Nəticədə: middleware1 → middleware2 → middleware3 → handler
        $pipeline = function (Command $command) use ($handlerClass) {
            $handler = $this->container->make($handlerClass);
            return $handler->handle($command);
        };

        // Middleware-ləri tərsdən sarıyırıq ki, düz sırada işləsinlər
        foreach (array_reverse($this->middlewares) as $middleware) {
            $pipeline = function (Command $command) use ($middleware, $pipeline) {
                return $middleware->handle($command, $pipeline);
            };
        }

        return $pipeline($command);
    }

    private function resolveHandler(Command $command): string
    {
        $commandClass = get_class($command);

        if (isset($this->handlers[$commandClass])) {
            return $this->handlers[$commandClass];
        }

        // Convention: CreateOrderCommand → CreateOrderHandler
        $handlerClass = str_replace('Command', 'Handler', $commandClass);

        if (!class_exists($handlerClass)) {
            throw new \RuntimeException("Handler tapılmadı: {$handlerClass} ({$commandClass} üçün)");
        }

        return $handlerClass;
    }
}
