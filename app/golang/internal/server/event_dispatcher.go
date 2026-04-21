package server

import (
	"context"
	"log/slog"
	"strings"

	"github.com/orkhan/ecommerce/internal/shared/domain"
	"github.com/orkhan/ecommerce/internal/shared/infrastructure/messaging"
)

// EventDispatcher — context-aware dispatcher
//
// 1. Domain events → in-process listener-lər (sinxron)
// 2. Integration events → **uyğun bounded context-in** outbox-una yazılır
//    (biznes data ilə eyni DB — transactional guarantee)
//
// Per-context outbox pattern: routing key prefix-ə görə DB seçilir:
//   "user.registered"     → user_db.outbox_messages
//   "product.stock.low"   → product_db.outbox_messages
//   "order.created"       → order_db.outbox_messages
//   "payment.completed"   → payment_db.outbox_messages
//
// Bu multi-DB transactional outbox problemini həll edir: hər context
// öz biznes tranzaksiyası ilə eyni DB-də öz outbox-una yazır.
type EventDispatcher struct {
	outboxes  map[string]*messaging.OutboxRepository
	fallback  *messaging.OutboxRepository
	listeners []DomainListener
}

// DomainListener — sinxron in-process listener (Spring @EventListener analoq)
type DomainListener interface {
	Handles(eventName string) bool
	Handle(ctx context.Context, event domain.DomainEvent) error
}

// NewEventDispatcher — hər context üçün ayrıca outbox repo qəbul edir
func NewEventDispatcher(outboxes map[string]*messaging.OutboxRepository) *EventDispatcher {
	d := &EventDispatcher{outboxes: outboxes}
	// Fallback — routing key-i tanımayanlar üçün order outbox
	if repo, ok := outboxes["order"]; ok {
		d.fallback = repo
	}
	return d
}

func (d *EventDispatcher) RegisterListener(l DomainListener) {
	d.listeners = append(d.listeners, l)
}

func (d *EventDispatcher) DispatchAll(ctx context.Context,
	aggregate interface{ PullDomainEvents() []domain.DomainEvent }) error {

	for _, ev := range aggregate.PullDomainEvents() {
		// In-process sinxron listener-lər
		for _, l := range d.listeners {
			if l.Handles(ev.EventName()) {
				if err := l.Handle(ctx, ev); err != nil {
					slog.WarnContext(ctx, "listener error", "event", ev.EventName(), "err", err)
				}
			}
		}

		// Integration event → uyğun context-in outbox-una yaz
		if integ, ok := ev.(domain.IntegrationEvent); ok {
			ctxName := contextFromRoutingKey(integ.RoutingKey())
			repo, exists := d.outboxes[ctxName]
			if !exists {
				slog.WarnContext(ctx, "outbox context tapılmadı, fallback",
					"routingKey", integ.RoutingKey(), "context", ctxName)
				repo = d.fallback
			}
			if repo == nil {
				continue
			}
			if err := repo.Save(integ.EventName(), integ.RoutingKey(), integ); err != nil {
				slog.ErrorContext(ctx, "outbox write failed", "err", err)
				return err
			}
			slog.DebugContext(ctx, "integration event → outbox",
				"context", ctxName, "event", integ.EventName(), "routing", integ.RoutingKey())
		}
	}
	return nil
}

// contextFromRoutingKey — "order.created" → "order"
// Routing key format: <context>.<entity>.<action>  (yaxud <context>.<action>)
func contextFromRoutingKey(routingKey string) string {
	if idx := strings.Index(routingKey, "."); idx > 0 {
		return routingKey[:idx]
	}
	return ""
}
