// Package search — global search endpoint
package search

import (
	"net/http"

	"github.com/gin-gonic/gin"
	"github.com/orkhan/ecommerce/internal/shared/infrastructure/api"
)

type Controller struct{}

func New() *Controller { return &Controller{} }

// GET /api/search?q=...
func (c *Controller) Search(ctx *gin.Context) {
	q := ctx.Query("q")
	ctx.JSON(http.StatusOK, api.Success(gin.H{
		"query":    q,
		"products": []any{},
		"orders":   []any{},
	}))
}

func (c *Controller) RegisterRoutes(public *gin.RouterGroup) {
	public.GET("/search", c.Search)
}
