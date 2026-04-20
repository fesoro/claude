// Package middleware — Gin HTTP middleware (Spring HandlerInterceptor analoq)
package middleware

import (
	"github.com/gin-gonic/gin"
	"github.com/google/uuid"
)

const CorrelationIDHeader = "X-Correlation-ID"
const CorrelationIDKey = "correlation_id"

// CorrelationID — hər request-ə unikal ID təyin edir, response header-ə qoyur
//
// Laravel: CorrelationIdMiddleware  ·  Spring: CorrelationIdInterceptor
func CorrelationID() gin.HandlerFunc {
	return func(c *gin.Context) {
		id := c.GetHeader(CorrelationIDHeader)
		if id == "" {
			id = uuid.New().String()
		}
		c.Set(CorrelationIDKey, id)
		c.Header(CorrelationIDHeader, id)
		c.Next()
	}
}
