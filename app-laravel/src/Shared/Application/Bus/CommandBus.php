<?php

declare(strict_types=1);

namespace Src\Shared\Application\Bus;

/**
 * COMMAND BUS (CQRS Pattern)
 * ==========================
 * Command Bus — Command-ları müvafiq Handler-ə yönləndirən vasitədir.
 *
 * CQRS-də ROLU:
 * - CQRS yazma (Command) və oxuma (Query) əməliyyatlarını ayırır.
 * - Command Bus yalnız YAZMA əməliyyatları üçündür.
 * - CreateOrderCommand → CommandBus → CreateOrderHandler
 *
 * MIDDLEWARE PIPELINE:
 * Command Bus-a middleware əlavə etmək olur:
 * Command → [Validation] → [Logging] → [Transaction] → Handler
 * Hər middleware Command-ı emal edib növbəti middleware-ə ötürür.
 */
interface CommandBus
{
    /**
     * Command-ı icra et.
     * Bus command-ı middleware pipeline-dan keçirib Handler-ə çatdırır.
     *
     * @throws \Exception Handler və ya middleware xəta atarsa
     */
    public function dispatch(Command $command): mixed;
}
