package web

import (
	"net/http"

	"github.com/gin-gonic/gin"
	"github.com/google/uuid"
	paymentApp "github.com/orkhan/ecommerce/internal/payment/application"
	"github.com/orkhan/ecommerce/internal/shared/application/bus"
	"github.com/orkhan/ecommerce/internal/shared/infrastructure/api"
)

type PaymentController struct {
	cmdBus   *bus.Bus
	queryBus *bus.QueryHandlers
}

func NewPaymentController(cmdBus *bus.Bus, queryBus *bus.QueryHandlers) *PaymentController {
	return &PaymentController{cmdBus: cmdBus, queryBus: queryBus}
}

func (c *PaymentController) Process(ctx *gin.Context) {
	var cmd paymentApp.ProcessPaymentCommand
	if err := ctx.ShouldBindJSON(&cmd); err != nil {
		ctx.Error(err)
		return
	}
	res, err := bus.Dispatch[paymentApp.ProcessPaymentCommand, paymentApp.PaymentResult](
		ctx.Request.Context(), c.cmdBus, cmd)
	if err != nil {
		ctx.Error(err)
		return
	}
	ctx.JSON(http.StatusOK, api.SuccessWithMessage(gin.H{"payment_id": res.ID}, "Ödəniş emal edildi"))
}

func (c *PaymentController) Show(ctx *gin.Context) {
	id, err := uuid.Parse(ctx.Param("id"))
	if err != nil {
		ctx.Error(err)
		return
	}
	dto, err := bus.Ask[paymentApp.GetPaymentQuery, paymentApp.PaymentDTO](
		ctx.Request.Context(), c.queryBus, paymentApp.GetPaymentQuery{PaymentID: id})
	if err != nil {
		ctx.Error(err)
		return
	}
	ctx.JSON(http.StatusOK, api.Success(dto))
}

func (c *PaymentController) RegisterRoutes(authed *gin.RouterGroup) {
	authed.POST("/payments/process", c.Process)
	authed.GET("/payments/:id", c.Show)
}
