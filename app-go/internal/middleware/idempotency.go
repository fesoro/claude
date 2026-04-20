package middleware

import (
	"context"

	"github.com/gin-gonic/gin"
	mw "github.com/orkhan/ecommerce/internal/shared/application/middleware"
)

// Idempotency — X-Idempotency-Key header-ı oxuyur, context-ə əlavə edir
// CommandBus middleware bunu oradan oxuyacaq.
func Idempotency() gin.HandlerFunc {
	return func(c *gin.Context) {
		key := c.GetHeader("X-Idempotency-Key")
		if key != "" {
			ctx := context.WithValue(c.Request.Context(), mw.IdempotencyKeyContextKey, key)
			c.Request = c.Request.WithContext(ctx)
		}
		c.Next()
	}
}
