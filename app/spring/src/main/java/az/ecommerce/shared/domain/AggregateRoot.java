package az.ecommerce.shared.domain;

import java.util.ArrayList;
import java.util.Collections;
import java.util.List;

/**
 * === AGGREGATE ROOT (DDD əsas primitivi) ===
 *
 * Laravel: src/Shared/Domain/AggregateRoot.php
 *   - protected array $domainEvents = [];
 *   - protected function recordEvent(DomainEvent $event)
 *   - public function pullDomainEvents(): array
 *
 * Spring: bu Java abstract class kimi yazılır. JPA Entity-lər bunu extend edir.
 * Domain event-lər aggregate daxilində yığılır, save-dən sonra publish olunur.
 *
 * NÜMUNƏ İSTİFADƏ:
 *   public class Order extends AggregateRoot {
 *       public void confirm() {
 *           this.status = OrderStatus.CONFIRMED;
 *           recordEvent(new OrderConfirmedEvent(this.id));
 *       }
 *   }
 */
public abstract class AggregateRoot {

    private final transient List<DomainEvent> domainEvents = new ArrayList<>();

    /**
     * Laravel: $this->recordEvent(...)
     */
    protected void recordEvent(DomainEvent event) {
        this.domainEvents.add(event);
    }

    /**
     * Laravel: $aggregate->pullDomainEvents()
     * Repository.save()-dən sonra çağrılır, sonra siyahı təmizlənir.
     */
    public List<DomainEvent> pullDomainEvents() {
        List<DomainEvent> events = Collections.unmodifiableList(new ArrayList<>(this.domainEvents));
        this.domainEvents.clear();
        return events;
    }

    public boolean hasDomainEvents() {
        return !this.domainEvents.isEmpty();
    }
}
