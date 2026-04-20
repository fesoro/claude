package middleware

import (
	"context"
	"time"

	"github.com/orkhan/ecommerce/internal/shared/application/bus"
	"github.com/orkhan/ecommerce/internal/shared/domain"
	"github.com/redis/go-redis/v9"
)

// IdempotencyMiddleware — order=20
//
// Eyni X-Idempotency-Key ilə təkrar gələn command-i 24 saat ərzində blok edir.
// Key gin context-dən gəlir → context.Context-ə yerləşdirilir → bu MW oxuyur.
//
// Laravel: IdempotencyMiddleware.php
// Spring: IdempotencyMiddleware.java (Redis ilə)
type IdempotencyMiddleware struct {
	redis *redis.Client
}

const IdempotencyKeyContextKey = "idempotency_key"

func NewIdempotencyMiddleware(r *redis.Client) *IdempotencyMiddleware {
	return &IdempotencyMiddleware{redis: r}
}

func (m *IdempotencyMiddleware) Handle(ctx context.Context, cmd any, next bus.Next) (any, error) {
	key, _ := ctx.Value(IdempotencyKeyContextKey).(string)
	if key == "" {
		return next(ctx, cmd)
	}

	redisKey := "cmd:idempotency:" + key
	ok, err := m.redis.SetNX(ctx, redisKey, "processing", 24*time.Hour).Result()
	if err != nil {
		return next(ctx, cmd) // redis xətasında bypass et
	}
	if !ok {
		return nil, domain.NewDomainError("Bu idempotency key artıq istifadə olunub: " + key)
	}

	res, err := next(ctx, cmd)
	if err != nil {
		_ = m.redis.Del(ctx, redisKey).Err()
		return nil, err
	}
	_ = m.redis.Set(ctx, redisKey, "completed", 24*time.Hour).Err()
	return res, nil
}
