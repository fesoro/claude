package bus

import "context"

// Next — middleware-də növbəti chain çağırışı
type Next func(ctx context.Context, cmd any) (any, error)

// Middleware — Spring CommandMiddleware analoq
//
// Pipeline sırası:
//   Logging (10) → Idempotency (20) → Validation (30) → Transaction (40) → RetryOnConcurrency (50) → Handler
type Middleware interface {
	Handle(ctx context.Context, cmd any, next Next) (any, error)
}

// MiddlewareFunc — funksional adapter
type MiddlewareFunc func(ctx context.Context, cmd any, next Next) (any, error)

func (f MiddlewareFunc) Handle(ctx context.Context, cmd any, next Next) (any, error) {
	return f(ctx, cmd, next)
}
