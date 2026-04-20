package middleware

import "github.com/gin-gonic/gin"

const TenantIDKey = "tenant_id"

func Tenant() gin.HandlerFunc {
	return func(c *gin.Context) {
		tid := c.GetHeader("X-Tenant-ID")
		if tid != "" {
			c.Set(TenantIDKey, tid)
		}
		c.Next()
	}
}
