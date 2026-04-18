<?php

declare(strict_types=1);

namespace Src\Shared\Application\Bus;

/**
 * COMMAND HANDLER (CQRS Pattern)
 * ==============================
 * Command Handler — Command-ı qəbul edib biznes əməliyyatını icra edir.
 *
 * QAYDALAR:
 * 1. Hər Command-ın BİR Handler-i olur (1:1 əlaqə).
 * 2. Handler biznes logikasını koordinasiya edir.
 * 3. Handler birbaşa biznes qaydalarını ehtiva etmir —
 *    o, Domain layer-dəki Entity/Service-ləri çağırır.
 *
 * AXIN:
 * Controller → CreateOrderCommand → CommandBus → CreateOrderHandler
 *   Handler daxilində:
 *   1. DTO-dan domain obyekti yarat
 *   2. Biznes qaydalarını yoxla (Specification)
 *   3. Repository-yə saxla
 *   4. Domain Event-ləri dispatch et
 */
interface CommandHandler
{
    public function handle(Command $command): mixed;
}
