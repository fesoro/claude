package middleware

import (
	"context"
	"errors"
	"log/slog"
	"strings"
	"time"

	"github.com/orkhan/ecommerce/internal/shared/application/bus"
)

// RetryOnConcurrencyMiddleware — order=50 (ən daxili)
//
// Optimistic locking konflikti olarsa, 3 dəfəyə qədər təkrar cəhd.
//
// Laravel: RetryOnConcurrencyMiddleware.php
// Spring: RetryOnConcurrencyMiddleware.java (ObjectOptimisticLockingFailureException)
// Go: GORM-da @Version sahəsi UPDATE-də 0 row affected verir → error qaytarırıq
type RetryOnConcurrencyMiddleware struct {
	maxAttempts int
}

func NewRetryOnConcurrencyMiddleware() *RetryOnConcurrencyMiddleware {
	return &RetryOnConcurrencyMiddleware{maxAttempts: 3}
}

func (m *RetryOnConcurrencyMiddleware) Handle(ctx context.Context, cmd any, next bus.Next) (any, error) {
	var lastErr error
	for attempt := 1; attempt <= m.maxAttempts; attempt++ {
		res, err := next(ctx, cmd)
		if err == nil {
			return res, nil
		}
		if !isConcurrencyError(err) {
			return nil, err
		}
		lastErr = err
		slog.WarnContext(ctx, "optimistic lock conflict",
			"attempt", attempt, "max", m.maxAttempts)
		time.Sleep(time.Duration(50*attempt) * time.Millisecond)
	}
	return nil, lastErr
}

// isConcurrencyError — GORM Version sahəsi konfliktini aşkar et
func isConcurrencyError(err error) bool {
	if err == nil {
		return false
	}
	return errors.Is(err, ErrOptimisticLock) ||
		strings.Contains(err.Error(), "optimistic")
}

// ErrOptimisticLock — repository-lər bu error-u qaytarır
var ErrOptimisticLock = errors.New("optimistic lock conflict")
