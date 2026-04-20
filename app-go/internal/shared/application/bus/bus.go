// Package bus — CQRS Command/Query Bus (Laravel SimpleCommandBus + Spring CommandBus əvəzi)
//
// Go-da generic interface + reflection ilə type-safe bus.
package bus

import (
	"context"
	"fmt"
	"reflect"
	"sync"
)

// Command — write əməliyyatı (marker interface, generic R = response type)
//
// İstifadə nümunəsi:
//   type CreateOrderCommand struct { UserID uuid.UUID; Items []Item }
//   // bütün method-ları implement etmək lazım deyil, marker-dir
type Command interface{}

// Query — read əməliyyatı (marker interface)
type Query interface{}

// CommandHandler — generic handler interface
//
// Spring: CommandHandler<C, R>
// Go: Handle(ctx, command) (R, error)
type CommandHandler[C any, R any] interface {
	Handle(ctx context.Context, command C) (R, error)
}

type QueryHandler[Q any, R any] interface {
	Handle(ctx context.Context, query Q) (R, error)
}

// HandlerFunc — funksional adapter (lambda kimi yazmaq üçün)
type HandlerFunc[C any, R any] func(ctx context.Context, command C) (R, error)

func (f HandlerFunc[C, R]) Handle(ctx context.Context, c C) (R, error) { return f(ctx, c) }

// Bus — universal command/query dispatcher
//
// Reflection istifadə edir çünki Go generics interface-də runtime-da type-erasure-a malikdir.
// Handler-i type adına görə map-də saxlayır.
type Bus struct {
	mu       sync.RWMutex
	handlers map[reflect.Type]any // command type → handler instance
	mws      []Middleware
}

// NewBus — yeni bus yaradır, middleware-ləri sıraya görə əlavə edir
func NewBus(middlewares ...Middleware) *Bus {
	return &Bus{
		handlers: make(map[reflect.Type]any),
		mws:      middlewares,
	}
}

// Register — handler-i bus-a qeyd edir
//
// Spring: @Service annotation avtomatik
// Go: explicit registration (server.go-da)
//   bus.Register(bus, registerUserHandler)
func Register[C any, R any](b *Bus, h CommandHandler[C, R]) {
	b.mu.Lock()
	defer b.mu.Unlock()
	var zero C
	b.handlers[reflect.TypeOf(zero)] = h
}

// Dispatch — command icra edir, middleware pipeline-dan keçirir
//
// Generic syntax: Dispatch[CreateOrderCommand, uuid.UUID](ctx, bus, cmd)
func Dispatch[C any, R any](ctx context.Context, b *Bus, command C) (R, error) {
	var zero R
	b.mu.RLock()
	h, ok := b.handlers[reflect.TypeOf(command)]
	mws := b.mws
	b.mu.RUnlock()

	if !ok {
		return zero, fmt.Errorf("handler tapılmadı: %T", command)
	}
	handler, ok := h.(CommandHandler[C, R])
	if !ok {
		return zero, fmt.Errorf("handler type uyğun gəlmir: %T", h)
	}

	// Middleware pipeline qur (decorator pattern)
	final := func(ctx context.Context, cmd any) (any, error) {
		return handler.Handle(ctx, cmd.(C))
	}
	for i := len(mws) - 1; i >= 0; i-- {
		mw := mws[i]
		next := final
		final = func(ctx context.Context, cmd any) (any, error) {
			return mw.Handle(ctx, cmd, next)
		}
	}

	res, err := final(ctx, command)
	if err != nil {
		return zero, err
	}
	r, _ := res.(R)
	return r, nil
}

// QueryDispatch — Query üçün bus
type QueryHandlers struct {
	mu       sync.RWMutex
	handlers map[reflect.Type]any
}

func NewQueryBus() *QueryHandlers {
	return &QueryHandlers{handlers: make(map[reflect.Type]any)}
}

func RegisterQuery[Q any, R any](b *QueryHandlers, h QueryHandler[Q, R]) {
	b.mu.Lock()
	defer b.mu.Unlock()
	var zero Q
	b.handlers[reflect.TypeOf(zero)] = h
}

func Ask[Q any, R any](ctx context.Context, b *QueryHandlers, query Q) (R, error) {
	var zero R
	b.mu.RLock()
	h, ok := b.handlers[reflect.TypeOf(query)]
	b.mu.RUnlock()
	if !ok {
		return zero, fmt.Errorf("query handler tapılmadı: %T", query)
	}
	handler, ok := h.(QueryHandler[Q, R])
	if !ok {
		return zero, fmt.Errorf("query handler type uyğun gəlmir")
	}
	return handler.Handle(ctx, query)
}
