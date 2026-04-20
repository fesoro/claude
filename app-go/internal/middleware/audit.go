package middleware

import (
	"github.com/gin-gonic/gin"
	"github.com/orkhan/ecommerce/internal/shared/infrastructure/audit"
)

var auditMethods = map[string]bool{
	"POST": true, "PUT": true, "PATCH": true, "DELETE": true,
}

// Audit — write əməliyyatları audit_logs cədvəlinə yazır (async)
func Audit(svc *audit.Service) gin.HandlerFunc {
	return func(c *gin.Context) {
		c.Next()

		if !auditMethods[c.Request.Method] || c.Writer.Status() >= 500 {
			return
		}
		corrID, _ := c.Get(CorrelationIDKey)
		entry := audit.AuditLog{
			CorrelationID:  toString(corrID),
			Action:         audit.ActionFromMethod(c.Request.Method),
			EntityType:     audit.EntityFromURI(c.Request.URL.Path),
			Method:         c.Request.Method,
			URI:            c.Request.URL.Path,
			IPAddress:      c.ClientIP(),
			UserAgent:      c.GetHeader("User-Agent"),
			ResponseStatus: c.Writer.Status(),
		}
		svc.Record(c.Request.Context(), entry)
	}
}

func toString(v any) string {
	if s, ok := v.(string); ok {
		return s
	}
	return ""
}
