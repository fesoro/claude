// Package admin — DLQ idarəetmə endpoint-ləri
package admin

import (
	"net/http"
	"strconv"

	"github.com/gin-gonic/gin"
	"github.com/orkhan/ecommerce/internal/shared/domain"
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
	count, err := c.dlq.FindUnretriedCount()
	if err != nil {
		ctx.Error(err)
		return
	}
	ctx.JSON(http.StatusOK, api.Success(gin.H{"unretried_count": count}))
}

func (c *FailedJobController) Retry(ctx *gin.Context) {
	id, err := strconv.ParseUint(ctx.Param("id"), 10, 64)
	if err != nil {
		ctx.Error(domain.NewDomainError("Yanlış ID formatı"))
		return
	}

	msg, err := c.dlq.FindByID(id)
	if err != nil {
		ctx.Error(err)
		return
	}
	if msg == nil {
		ctx.Error(domain.NewEntityNotFoundError("FailedJob", ctx.Param("id")))
		return
	}

	if err := c.dlq.MarkAsRetried(id); err != nil {
		ctx.Error(err)
		return
	}
	ctx.JSON(http.StatusOK, api.SuccessWithMessage(nil, "Yenidən cəhd üçün işarələndi"))
}

func (c *FailedJobController) Flush(ctx *gin.Context) {
	if err := c.dlq.DeleteAll(); err != nil {
		ctx.Error(err)
		return
	}
	ctx.JSON(http.StatusOK, api.SuccessWithMessage(nil, "DLQ təmizləndi"))
}

func (c *FailedJobController) RegisterRoutes(authed *gin.RouterGroup) {
	admin := authed.Group("/admin/failed-jobs")
	admin.GET("", c.Index)
	admin.POST("/:id/retry", c.Retry)
	admin.DELETE("", c.Flush)
}
