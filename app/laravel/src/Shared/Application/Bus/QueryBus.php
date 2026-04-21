<?php

declare(strict_types=1);

namespace Src\Shared\Application\Bus;

/**
 * QUERY BUS (CQRS Pattern)
 * ========================
 * Query Bus — Query-ləri müvafiq Handler-ə yönləndirir.
 *
 * COMMAND BUS-dan FƏRQI:
 * - Command Bus: Yazma əməliyyatları, data dəyişir, void qaytarır.
 * - Query Bus: Oxuma əməliyyatları, data dəyişMİR, data qaytarır.
 *
 * NƏYƏ AYRIYIQ?
 * - Oxuma və yazma fərqli optimizasiya tələb edir.
 * - Oxuma: Cache, read replica, denormalized view
 * - Yazma: Validation, transaction, event dispatch
 * - Ayrı saxlamaqla hər birini müstəqil scale edə bilərsən.
 */
interface QueryBus
{
    /**
     * Query-ni icra et və nəticəni qaytar.
     */
    public function ask(Query $query): mixed;
}
