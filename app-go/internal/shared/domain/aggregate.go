// Package domain — DDD əsas primitivləri (Shared Kernel)
package domain

import (
	"time"

	"github.com/google/uuid"
)

// AggregateRoot — bütün aggregate-lərin əsası
//
// Laravel: src/Shared/Domain/AggregateRoot.php (abstract class + recordEvent)
// Spring: shared/domain/AggregateRoot.java (abstract class)
// Go: struct embedding (composition) — Java extends əvəzinə
//
// İstifadə:
//   type Order struct {
//       AggregateRoot
//       ID OrderID
//       ...
//   }
//   func (o *Order) Confirm() {
//       o.RecordEvent(OrderConfirmedEvent{...})
//   }
type AggregateRoot struct {
	domainEvents []DomainEvent
}

// RecordEvent — domain event əlavə edir
func (a *AggregateRoot) RecordEvent(event DomainEvent) {
	a.domainEvents = append(a.domainEvents, event)
}

// PullDomainEvents — yığılan event-ləri qaytarır və silir
// Repository.Save()-dən sonra çağrılır
func (a *AggregateRoot) PullDomainEvents() []DomainEvent {
	events := a.domainEvents
	a.domainEvents = nil
	return events
}

// HasDomainEvents — event yığılıb mı yoxlayır
func (a *AggregateRoot) HasDomainEvents() bool {
	return len(a.domainEvents) > 0
}

// DomainEvent — bütün domain event-lərin interface-i
//
// Laravel: src/Shared/Domain/DomainEvent.php (abstract)
// Spring: shared/domain/DomainEvent.java (interface)
// Go: interface — methodlar implicit implement olunur (duck typing)
type DomainEvent interface {
	EventID() uuid.UUID
	OccurredAt() time.Time
	EventName() string
	EventVersion() int
}

// IntegrationEvent — bounded context-lər arası asinxron event
// (RabbitMQ-yə publish olunur)
//
// Laravel: IntegrationEvent.php
// Spring: IntegrationEvent.java
// Go: DomainEvent-i embed edir + RoutingKey() metodu
type IntegrationEvent interface {
	DomainEvent
	RoutingKey() string
}

// BaseEvent — DomainEvent default implementation (composition üçün)
type BaseEvent struct {
	ID         uuid.UUID `json:"event_id"`
	OccurredOn time.Time `json:"occurred_at"`
}

func NewBaseEvent() BaseEvent {
	return BaseEvent{ID: uuid.New(), OccurredOn: time.Now().UTC()}
}

func (b BaseEvent) EventID() uuid.UUID  { return b.ID }
func (b BaseEvent) OccurredAt() time.Time { return b.OccurredOn }
func (b BaseEvent) EventVersion() int   { return 1 }
