// Package web — NotificationPreference CRUD
package web

import (
	"net/http"
	"time"

	"github.com/gin-gonic/gin"
	"github.com/google/uuid"
	"github.com/orkhan/ecommerce/internal/middleware"
	"github.com/orkhan/ecommerce/internal/shared/infrastructure/api"
	"gorm.io/gorm"
)

type Preference struct {
	ID           uint64    `gorm:"primaryKey;autoIncrement"`
	UserID       uuid.UUID `gorm:"type:char(36);not null"`
	EventType    string    `gorm:"size:64;not null"`
	EmailEnabled bool      `gorm:"not null;default:true"`
	SmsEnabled   bool      `gorm:"not null;default:false"`
	PushEnabled  bool      `gorm:"not null;default:false"`
	CreatedAt    time.Time
	UpdatedAt    time.Time
}

func (Preference) TableName() string { return "notification_preferences" }

type PreferenceController struct {
	db *gorm.DB
}

func NewPreferenceController(db *gorm.DB) *PreferenceController {
	return &PreferenceController{db: db}
}

// GET /api/notifications/preferences
func (c *PreferenceController) Index(ctx *gin.Context) {
	uid := ctx.MustGet(middleware.UserIDKey).(uuid.UUID)
	var prefs []Preference
	c.db.Where("user_id = ?", uid).Find(&prefs)
	ctx.JSON(http.StatusOK, api.Success(prefs))
}

// PUT /api/notifications/preferences/:eventType  body: {email, sms, push}
func (c *PreferenceController) Update(ctx *gin.Context) {
	uid := ctx.MustGet(middleware.UserIDKey).(uuid.UUID)
	eventType := ctx.Param("eventType")
	var body struct {
		Email bool `json:"email"`
		Sms   bool `json:"sms"`
		Push  bool `json:"push"`
	}
	if err := ctx.ShouldBindJSON(&body); err != nil {
		ctx.Error(err)
		return
	}

	var pref Preference
	err := c.db.Where("user_id = ? AND event_type = ?", uid, eventType).First(&pref).Error
	if err != nil {
		// not found → create
		pref = Preference{UserID: uid, EventType: eventType}
	}
	pref.EmailEnabled = body.Email
	pref.SmsEnabled = body.Sms
	pref.PushEnabled = body.Push
	c.db.Save(&pref)
	ctx.JSON(http.StatusOK, api.SuccessWithMessage(pref, "Üstünlüklər yeniləndi"))
}

func (c *PreferenceController) RegisterRoutes(authed *gin.RouterGroup) {
	authed.GET("/notifications/preferences", c.Index)
	authed.PUT("/notifications/preferences/:eventType", c.Update)
}
