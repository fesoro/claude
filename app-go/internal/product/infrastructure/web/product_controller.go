package web

import (
	"net/http"
	"strconv"

	"github.com/gin-gonic/gin"
	"github.com/google/uuid"
	productApp "github.com/orkhan/ecommerce/internal/product/application"
	"github.com/orkhan/ecommerce/internal/shared/application/bus"
	"github.com/orkhan/ecommerce/internal/shared/infrastructure/api"
)

// ProductController — Spring ProductController qarşılığı
type ProductController struct {
	cmdBus   *bus.Bus
	queryBus *bus.QueryHandlers
}

func NewProductController(cmdBus *bus.Bus, queryBus *bus.QueryHandlers) *ProductController {
	return &ProductController{cmdBus: cmdBus, queryBus: queryBus}
}

// GET /api/products
func (c *ProductController) Index(ctx *gin.Context) {
	page, _ := strconv.Atoi(ctx.DefaultQuery("page", "0"))
	size, _ := strconv.Atoi(ctx.DefaultQuery("size", "15"))

	dtos, err := bus.Ask[productApp.ListProductsQuery, []productApp.ProductDTO](
		ctx.Request.Context(), c.queryBus, productApp.ListProductsQuery{Page: page, Size: size})
	if err != nil {
		ctx.Error(err)
		return
	}
	ctx.JSON(http.StatusOK, api.Success(dtos))
}

// GET /api/products/:id
func (c *ProductController) Show(ctx *gin.Context) {
	id, err := uuid.Parse(ctx.Param("id"))
	if err != nil {
		ctx.Error(err)
		return
	}
	dto, err := bus.Ask[productApp.GetProductQuery, productApp.ProductDTO](
		ctx.Request.Context(), c.queryBus, productApp.GetProductQuery{ProductID: id})
	if err != nil {
		ctx.Error(err)
		return
	}
	ctx.JSON(http.StatusOK, api.Success(dto))
}

// POST /api/products (auth)
func (c *ProductController) Store(ctx *gin.Context) {
	var cmd productApp.CreateProductCommand
	if err := ctx.ShouldBindJSON(&cmd); err != nil {
		ctx.Error(err)
		return
	}
	id, err := bus.Dispatch[productApp.CreateProductCommand, uuid.UUID](
		ctx.Request.Context(), c.cmdBus, cmd)
	if err != nil {
		ctx.Error(err)
		return
	}
	ctx.JSON(http.StatusCreated, api.SuccessWithMessage(gin.H{"id": id}, "Məhsul yaradıldı"))
}

// PATCH /api/products/:id/stock (auth)
func (c *ProductController) UpdateStock(ctx *gin.Context) {
	id, err := uuid.Parse(ctx.Param("id"))
	if err != nil {
		ctx.Error(err)
		return
	}
	var body struct {
		Amount int    `json:"amount" binding:"required"`
		Type   string `json:"type" binding:"required,oneof=increase decrease"`
	}
	if err := ctx.ShouldBindJSON(&body); err != nil {
		ctx.Error(err)
		return
	}

	_, err = bus.Dispatch[productApp.UpdateStockCommand, struct{}](
		ctx.Request.Context(), c.cmdBus,
		productApp.UpdateStockCommand{ProductID: id, Amount: body.Amount, Type: body.Type})
	if err != nil {
		ctx.Error(err)
		return
	}
	ctx.JSON(http.StatusOK, api.SuccessWithMessage(nil, "Stok yeniləndi"))
}

func (c *ProductController) RegisterRoutes(public, authed *gin.RouterGroup) {
	public.GET("/products", c.Index)
	public.GET("/products/:id", c.Show)
	authed.POST("/products", c.Store)
	authed.PATCH("/products/:id/stock", c.UpdateStock)
}
