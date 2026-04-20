package middleware

import (
	"github.com/gin-gonic/gin"
	"github.com/orkhan/ecommerce/internal/shared/infrastructure/featureflags"
)

func FeatureFlag(ff *featureflags.FeatureFlag) gin.HandlerFunc {
	return func(c *gin.Context) {
		c.Set("feature_flag", ff)
		c.Next()
	}
}
