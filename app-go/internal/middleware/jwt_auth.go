package middleware

import (
	"net/http"
	"strings"

	"github.com/gin-gonic/gin"
	"github.com/orkhan/ecommerce/internal/shared/infrastructure/api"
	"github.com/orkhan/ecommerce/internal/shared/infrastructure/auth"
)

const UserIDKey = "user_id"

// JWTAuth — Bearer token yoxlayır, user_id-ni context-ə qoyur
//
// Laravel: Sanctum auth middleware  ·  Spring: SecurityFilterChain
func JWTAuth(jwt *auth.JWTService) gin.HandlerFunc {
	return func(c *gin.Context) {
		header := c.GetHeader("Authorization")
		if !strings.HasPrefix(header, "Bearer ") {
			c.AbortWithStatusJSON(http.StatusUnauthorized, api.Error("Token tələb olunur"))
			return
		}
		claims, err := jwt.Parse(strings.TrimPrefix(header, "Bearer "))
		if err != nil {
			c.AbortWithStatusJSON(http.StatusUnauthorized, api.Error("Yanlış token"))
			return
		}
		c.Set(UserIDKey, claims.UserID)
		c.Next()
	}
}
