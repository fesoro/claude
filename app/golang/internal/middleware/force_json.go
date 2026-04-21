package middleware

import "github.com/gin-gonic/gin"

// ForceJSON — bütün API request-lərini JSON cavabı qəbul etməyə məcbur edir
func ForceJSON() gin.HandlerFunc {
	return func(c *gin.Context) {
		c.Request.Header.Set("Accept", "application/json")
		c.Next()
	}
}
