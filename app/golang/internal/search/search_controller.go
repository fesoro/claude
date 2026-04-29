// Package search — global search endpoint
package search

import (
	"net/http"
	"strings"

	"github.com/gin-gonic/gin"
	productApp "github.com/orkhan/ecommerce/internal/product/application"
	"github.com/orkhan/ecommerce/internal/shared/application/bus"
	"github.com/orkhan/ecommerce/internal/shared/infrastructure/api"
)

type Controller struct {
	queryBus *bus.QueryHandlers
}

func New(queryBus *bus.QueryHandlers) *Controller {
	return &Controller{queryBus: queryBus}
}

// GET /api/search?q=...&page=0&size=20
func (c *Controller) Search(ctx *gin.Context) {
	q := strings.TrimSpace(ctx.Query("q"))

	page := 0
	size := 20

	products, err := bus.Ask[productApp.ListProductsQuery, []productApp.ProductDTO](
		ctx.Request.Context(), c.queryBus,
		productApp.ListProductsQuery{Page: page, Size: size},
	)
	if err != nil {
		ctx.Error(err)
		return
	}

	// Client-side filter by name if query string provided
	if q != "" {
		filtered := make([]productApp.ProductDTO, 0, len(products))
		lower := strings.ToLower(q)
		for _, p := range products {
			if strings.Contains(strings.ToLower(p.Name), lower) {
				filtered = append(filtered, p)
			}
		}
		products = filtered
	}

	ctx.JSON(http.StatusOK, api.Success(gin.H{
		"query":    q,
		"products": products,
	}))
}

func (c *Controller) RegisterRoutes(public *gin.RouterGroup) {
	public.GET("/search", c.Search)
}
