<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Bus;

use Illuminate\Contracts\Container\Container;
use Src\Shared\Application\Bus\Query;
use Src\Shared\Application\Bus\QueryBus;

/**
 * SIMPLE QUERY BUS (Infrastructure Implementation)
 * =================================================
 * QueryBus interface-inin konkret implementasiyası.
 *
 * Command Bus-dan fərqi:
 * - Middleware yoxdur (oxuma əməliyyatında transaction/validation lazım deyil).
 * - Həmişə data qaytarır.
 */
class SimpleQueryBus implements QueryBus
{
    /** @var array<class-string<Query>, class-string> */
    private array $handlers = [];

    public function __construct(
        private Container $container,
    ) {}

    public function register(string $queryClass, string $handlerClass): void
    {
        $this->handlers[$queryClass] = $handlerClass;
    }

    public function ask(Query $query): mixed
    {
        $handlerClass = $this->resolveHandler($query);
        $handler = $this->container->make($handlerClass);

        return $handler->handle($query);
    }

    private function resolveHandler(Query $query): string
    {
        $queryClass = get_class($query);

        if (isset($this->handlers[$queryClass])) {
            return $this->handlers[$queryClass];
        }

        // Convention: GetOrderQuery → GetOrderHandler
        $handlerClass = str_replace('Query', 'Handler', $queryClass);

        if (!class_exists($handlerClass)) {
            throw new \RuntimeException("Query Handler tapılmadı: {$handlerClass}");
        }

        return $handlerClass;
    }
}
