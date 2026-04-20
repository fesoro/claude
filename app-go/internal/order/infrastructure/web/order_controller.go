package web

import (
	"net/http"

	"github.com/gin-gonic/gin"
	"github.com/google/uuid"
	orderApp "github.com/orkhan/ecommerce/internal/order/application"
	orderDomain "github.com/orkhan/ecommerce/internal/order/domain"
	"github.com/orkhan/ecommerce/internal/shared/application/bus"
	"github.com/orkhan/ecommerce/internal/shared/infrastructure/api"
)

type OrderController struct {
	cmdBus   *bus.Bus
	queryBus *bus.QueryHandlers
}

func NewOrderController(cmdBus *bus.Bus, queryBus *bus.QueryHandlers) *OrderController {
	return &OrderController{cmdBus: cmdBus, queryBus: queryBus}
}

func (c *OrderController) Store(ctx *gin.Context) {
	var cmd orderApp.CreateOrderCommand
	if err := ctx.ShouldBindJSON(&cmd); err != nil {
		ctx.Error(err)
		return
	}
	id, err := bus.Dispatch[orderApp.CreateOrderCommand, uuid.UUID](
		ctx.Request.Context(), c.cmdBus, cmd)
	if err != nil {
		ctx.Error(err)
		return
	}
	ctx.JSON(http.StatusCreated, api.SuccessWithMessage(gin.H{"id": id}, "Sifariş yaradıldı"))
}

func (c *OrderController) Show(ctx *gin.Context) {
	id, err := uuid.Parse(ctx.Param("id"))
	if err != nil {
		ctx.Error(err)
		return
	}
	dto, err := bus.Ask[orderApp.GetOrderQuery, orderApp.OrderDTO](
		ctx.Request.Context(), c.queryBus, orderApp.GetOrderQuery{OrderID: id})
	if err != nil {
		ctx.Error(err)
		return
	}
	ctx.JSON(http.StatusOK, api.Success(dto))
}

func (c *OrderController) ListByUser(ctx *gin.Context) {
	id, err := uuid.Parse(ctx.Param("userId"))
	if err != nil {
		ctx.Error(err)
		return
	}
	dtos, err := bus.Ask[orderApp.ListOrdersQuery, []orderApp.OrderDTO](
		ctx.Request.Context(), c.queryBus, orderApp.ListOrdersQuery{UserID: id})
	if err != nil {
		ctx.Error(err)
		return
	}
	ctx.JSON(http.StatusOK, api.Success(dtos))
}

func (c *OrderController) Cancel(ctx *gin.Context) {
	id, err := uuid.Parse(ctx.Param("id"))
	if err != nil {
		ctx.Error(err)
		return
	}
	var body struct {
		Reason string `json:"reason"`
	}
	_ = ctx.ShouldBindJSON(&body)

	_, err = bus.Dispatch[orderApp.CancelOrderCommand, struct{}](
		ctx.Request.Context(), c.cmdBus,
		orderApp.CancelOrderCommand{OrderID: id, Reason: body.Reason})
	if err != nil {
		ctx.Error(err)
		return
	}
	ctx.JSON(http.StatusOK, api.SuccessWithMessage(nil, "Sifariş ləğv edildi"))
}

func (c *OrderController) UpdateStatus(ctx *gin.Context) {
	id, err := uuid.Parse(ctx.Param("id"))
	if err != nil {
		ctx.Error(err)
		return
	}
	var body struct {
		Target string `json:"target" binding:"required"`
	}
	if err := ctx.ShouldBindJSON(&body); err != nil {
		ctx.Error(err)
		return
	}

	_, err = bus.Dispatch[orderApp.UpdateOrderStatusCommand, struct{}](
		ctx.Request.Context(), c.cmdBus,
		orderApp.UpdateOrderStatusCommand{
			OrderID: id, Target: orderDomain.OrderStatus(body.Target),
		})
	if err != nil {
		ctx.Error(err)
		return
	}
	ctx.JSON(http.StatusOK, api.SuccessWithMessage(nil, "Status yeniləndi"))
}

func (c *OrderController) RegisterRoutes(authed *gin.RouterGroup) {
	authed.POST("/orders", c.Store)
	authed.GET("/orders/:id", c.Show)
	authed.GET("/orders/user/:userId", c.ListByUser)
	authed.POST("/orders/:id/cancel", c.Cancel)
	authed.PATCH("/orders/:id/status", c.UpdateStatus)
}
