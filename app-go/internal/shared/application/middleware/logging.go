// Package middleware — CommandBus pipeline middleware-ləri
package middleware

import (
	"context"
	"fmt"
	"log/slog"
	"time"

	"github.com/orkhan/ecommerce/internal/shared/application/bus"
)

// LoggingMiddleware — order=10 (ən xarici)
//
// Laravel: LoggingMiddleware.php
// Spring: LoggingMiddleware.java
type LoggingMiddleware struct{}

func NewLoggingMiddleware() *LoggingMiddleware { return &LoggingMiddleware{} }

func (m *LoggingMiddleware) Handle(ctx context.Context, cmd any, next bus.Next) (any, error) {
	start := time.Now()
	name := fmt.Sprintf("%T", cmd)
	slog.InfoContext(ctx, "[CMD] başladı", "command", name)

	res, err := next(ctx, cmd)

	if err != nil {
		slog.ErrorContext(ctx, "[CMD] xəta", "command", name, "err", err,
			"duration_ms", time.Since(start).Milliseconds())
		return nil, err
	}
	slog.InfoContext(ctx, "[CMD] bitdi", "command", name,
		"duration_ms", time.Since(start).Milliseconds())
	return res, nil
}
