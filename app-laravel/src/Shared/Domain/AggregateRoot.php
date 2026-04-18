<?php

declare(strict_types=1);

namespace Src\Shared\Domain;

/**
 * AGGREGATE ROOT (DDD Pattern)
 * ============================
 * Aggregate Root — domen modelinin ən vacib konseptlərindən biridir.
 *
 * NƏDİR?
 * - Bir qrup əlaqəli obyekti (Entity + Value Object) birlikdə idarə edən əsas Entity-dir.
 * - Bütün dəyişikliklər YALNIZ Aggregate Root vasitəsilə edilir.
 * - Xaricdən heç vaxt daxili Entity-lərə birbaşa müraciət edilmir.
 *
 * NÜMUNƏ:
 * Order (Aggregate Root) → OrderItem (Entity) → Money (Value Object)
 * Yeni OrderItem əlavə etmək üçün birbaşa OrderItem yaratmırsan,
 * Order->addItem() metodunu çağırırsan.
 *
 * NƏYƏ LAZIMDIR?
 * - Data consistency (məlumat bütövlüyü) təmin edir.
 * - Biznes qaydalarını bir yerdə saxlayır.
 * - Domain Event-ləri burada yaranır.
 */
abstract class AggregateRoot extends Entity
{
    /**
     * Bu Aggregate daxilində baş verən domain event-lərin siyahısı.
     * Event-lər əvvəlcə burada toplanır, sonra persist edildikdən sonra dispatch olunur.
     *
     * @var DomainEvent[]
     */
    private array $domainEvents = [];

    /**
     * Yeni domain event qeydə al.
     * Bu metod yalnız Aggregate daxilindən çağırılmalıdır.
     *
     * Məsələn: Order yaradılanda $this->recordEvent(new OrderCreatedEvent(...))
     */
    protected function recordEvent(DomainEvent $event): void
    {
        $this->domainEvents[] = $event;
    }

    /**
     * Toplanmış bütün event-ləri al və siyahını təmizlə.
     * Bu metod adətən Repository-də persist-dən sonra çağırılır.
     *
     * @return DomainEvent[]
     */
    public function pullDomainEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];

        return $events;
    }

    /**
     * Gözləyən event-lərin sayını öyrən.
     */
    public function domainEventCount(): int
    {
        return count($this->domainEvents);
    }
}
