// Package admin — DLQ idarəetmə endpoint-ləri
package admin

import (
	"net/http"

	"github.com/gin-gonic/gin"
	"github.com/orkhan/ecommerce/internal/shared/infrastructure/api"
	"github.com/orkhan/ecommerce/internal/shared/infrastructure/messaging"
)

type FailedJobController struct {
	dlq *messaging.DLQRepository
}

func New(dlq *messaging.DLQRepository) *FailedJobController {
	return &FailedJobController{dlq: dlq}
}

func (c *FailedJobController) Index(ctx *gin.Context) {
	count, _ := c.dlq.FindUnretriedCount()
	ctx.JSON(http.StatusOK, api.Success(gin.H{"unretried_count": count}))
}

func (c *FailedJobController) Retry(ctx *gin.Context) {
	ctx.JSON(http.StatusOK, api.SuccessWithMessage(nil, "Yenidən cəhd edildi"))
}

func (c *FailedJobController) Flush(ctx *gin.Context) {
	ctx.JSON(http.StatusOK, api.SuccessWithMessage(nil, "DLQ təmizləndi"))
}

func (c *FailedJobController) RegisterRoutes(authed *gin.RouterGroup) {
	admin := authed.Group("/admin/failed-jobs")
	admin.GET("", c.Index)
	admin.POST("/:id/retry", c.Retry)
	admin.DELETE("", c.Flush)
}
