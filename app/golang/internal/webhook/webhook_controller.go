// Package webhook — Webhook CRUD controller
package webhook

import (
	"encoding/json"
	"net/http"

	"github.com/gin-gonic/gin"
	"github.com/google/uuid"
	"github.com/orkhan/ecommerce/internal/middleware"
	"github.com/orkhan/ecommerce/internal/shared/infrastructure/api"
	webhookInfra "github.com/orkhan/ecommerce/internal/shared/infrastructure/webhook"
	"gorm.io/gorm"
)

// Controller — webhook idarəetmə endpoint-ləri
type Controller struct {
	db *gorm.DB
}

func New(db *gorm.DB) *Controller { return &Controller{db: db} }

// POST /api/webhooks  body: {url, events: ["order.created", ...]}
func (c *Controller) Store(ctx *gin.Context) {
	uid := ctx.MustGet(middleware.UserIDKey).(uuid.UUID)
	var body struct {
		URL    string   `json:"url" binding:"required,url"`
		Events []string `json:"events" binding:"required,min=1"`
	}
	if err := ctx.ShouldBindJSON(&body); err != nil {
		ctx.Error(err)
		return
	}

	eventsJSON, _ := json.Marshal(body.Events)
	w := webhookInfra.Webhook{
		ID:       uuid.New(),
		UserID:   uid,
		URL:      body.URL,
		Secret:   uuid.New().String(),
		Events:   string(eventsJSON),
		IsActive: true,
	}
	if err := c.db.Create(&w).Error; err != nil {
		ctx.Error(err)
		return
	}
	ctx.JSON(http.StatusCreated, api.SuccessWithMessage(w, "Webhook yaradıldı"))
}

// GET /api/webhooks
func (c *Controller) Index(ctx *gin.Context) {
	uid := ctx.MustGet(middleware.UserIDKey).(uuid.UUID)
	var hooks []webhookInfra.Webhook
	if err := c.db.Where("user_id = ?", uid).Find(&hooks).Error; err != nil {
		ctx.Error(err)
		return
	}
	ctx.JSON(http.StatusOK, api.Success(hooks))
}

// PATCH /api/webhooks/:id  → toggle
func (c *Controller) Toggle(ctx *gin.Context) {
	id, err := uuid.Parse(ctx.Param("id"))
	if err != nil {
		ctx.Error(err)
		return
	}
	var w webhookInfra.Webhook
	if err := c.db.First(&w, "id = ?", id).Error; err != nil {
		ctx.Error(err)
		return
	}
	w.IsActive = !w.IsActive
	if err := c.db.Save(&w).Error; err != nil {
		ctx.Error(err)
		return
	}
	ctx.JSON(http.StatusOK, api.Success(w))
}

// DELETE /api/webhooks/:id
func (c *Controller) Destroy(ctx *gin.Context) {
	id, err := uuid.Parse(ctx.Param("id"))
	if err != nil {
		ctx.Error(err)
		return
	}
	if err := c.db.Delete(&webhookInfra.Webhook{}, "id = ?", id).Error; err != nil {
		ctx.Error(err)
		return
	}
	ctx.JSON(http.StatusOK, api.SuccessWithMessage(nil, "Silindi"))
}

func (c *Controller) RegisterRoutes(authed *gin.RouterGroup) {
	authed.POST("/webhooks", c.Store)
	authed.GET("/webhooks", c.Index)
	authed.PATCH("/webhooks/:id", c.Toggle)
	authed.DELETE("/webhooks/:id", c.Destroy)
}
