// Package health — Kubernetes-compatible health check endpoints
package health

import (
	"context"
	"net/http"

	"github.com/gin-gonic/gin"
	"github.com/orkhan/ecommerce/internal/shared/infrastructure/api"
	"github.com/orkhan/ecommerce/internal/shared/infrastructure/database"
	amqp "github.com/rabbitmq/amqp091-go"
	"github.com/redis/go-redis/v9"
	"gorm.io/gorm"
)

type Controller struct {
	dbs       *database.Databases
	redis     *redis.Client
	rabbitURL string
}

func New(dbs *database.Databases, redis *redis.Client, rabbitURL string) *Controller {
	return &Controller{dbs: dbs, redis: redis, rabbitURL: rabbitURL}
}

// GET /api/health — bütün servisləri yoxla
func (c *Controller) Full(ctx *gin.Context) {
	checks := map[string]string{
		"user_db":    c.checkDB(c.dbs.User),
		"product_db": c.checkDB(c.dbs.Product),
		"order_db":   c.checkDB(c.dbs.Order),
		"payment_db": c.checkDB(c.dbs.Payment),
		"redis":      c.checkRedis(ctx),
		"rabbitmq":   c.checkRabbit(),
	}

	allOK := true
	for _, v := range checks {
		if v != "UP" {
			allOK = false
			break
		}
	}
	status := http.StatusOK
	if !allOK {
		status = http.StatusServiceUnavailable
	}
	ctx.JSON(status, api.Success(checks))
}

// GET /api/health/live — Kubernetes liveness
func (c *Controller) Live(ctx *gin.Context) {
	ctx.JSON(http.StatusOK, api.Success("UP"))
}

// GET /api/health/ready — Kubernetes readiness
func (c *Controller) Ready(ctx *gin.Context) {
	if c.checkDB(c.dbs.User) == "UP" && c.checkRedis(ctx) == "UP" {
		ctx.JSON(http.StatusOK, api.Success("READY"))
		return
	}
	ctx.JSON(http.StatusServiceUnavailable, api.Error("NOT_READY"))
}

func (c *Controller) RegisterRoutes(public *gin.RouterGroup) {
	public.GET("/health", c.Full)
	public.GET("/health/live", c.Live)
	public.GET("/health/ready", c.Ready)
}

// === HELPERS ===

func (c *Controller) checkDB(gormDB *gorm.DB) string {
	if gormDB == nil {
		return "DOWN: nil"
	}
	sqlDB, err := gormDB.DB()
	if err != nil {
		return "DOWN: " + err.Error()
	}
	if err := sqlDB.Ping(); err != nil {
		return "DOWN: " + err.Error()
	}
	return "UP"
}

func (c *Controller) checkRedis(ctx context.Context) string {
	if err := c.redis.Ping(ctx).Err(); err != nil {
		return "DOWN: " + err.Error()
	}
	return "UP"
}

func (c *Controller) checkRabbit() string {
	conn, err := amqp.Dial(c.rabbitURL)
	if err != nil {
		return "DOWN: " + err.Error()
	}
	_ = conn.Close()
	return "UP"
}
