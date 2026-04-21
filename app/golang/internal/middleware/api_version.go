package middleware

import "github.com/gin-gonic/gin"

const APIVersionKey = "api_version"

func APIVersion() gin.HandlerFunc {
	return func(c *gin.Context) {
		v := c.GetHeader("X-API-Version")
		if v == "" {
			v = "v2"
		}
		c.Set(APIVersionKey, v)
		c.Next()
	}
}
