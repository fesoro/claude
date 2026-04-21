package web

import (
	"net/http"

	"github.com/gin-gonic/gin"
	"github.com/google/uuid"
	"github.com/orkhan/ecommerce/internal/middleware"
	"github.com/orkhan/ecommerce/internal/shared/domain"
	"github.com/orkhan/ecommerce/internal/shared/infrastructure/api"
	"github.com/orkhan/ecommerce/internal/shared/infrastructure/auth"
	userDomain "github.com/orkhan/ecommerce/internal/user/domain"
)

// TwoFactorController — 2FA TOTP endpoint-ləri
//
// Laravel: app/Http/Controllers/TwoFactorController.php
// Spring: TwoFactorController.java
type TwoFactorController struct {
	repo       userDomain.Repository
	twoFactor  *auth.TwoFactorService
	jwtService *auth.JWTService
}

func NewTwoFactorController(repo userDomain.Repository, tf *auth.TwoFactorService, jwt *auth.JWTService) *TwoFactorController {
	return &TwoFactorController{repo: repo, twoFactor: tf, jwtService: jwt}
}

// POST /api/auth/2fa/enable
func (c *TwoFactorController) Enable(ctx *gin.Context) {
	uid := ctx.MustGet(middleware.UserIDKey).(uuid.UUID)
	user, err := c.repo.FindByID(userDomain.UserID(uid))
	if err != nil || user == nil {
		ctx.Error(domain.NewEntityNotFoundError("User", uid.String()))
		return
	}

	setup, err := c.twoFactor.GenerateSecret(user.Email().Value())
	if err != nil {
		ctx.Error(err)
		return
	}
	ctx.JSON(http.StatusOK, api.SuccessWithMessage(gin.H{
		"secret":      setup.Secret,
		"qr_code_url": setup.OTPAuthURL,
	}, "QR-i scan edin və code ilə təsdiqləyin"))
}

// POST /api/auth/2fa/confirm  body: {secret, code}
func (c *TwoFactorController) Confirm(ctx *gin.Context) {
	uid := ctx.MustGet(middleware.UserIDKey).(uuid.UUID)
	var body struct {
		Secret string `json:"secret" binding:"required"`
		Code   string `json:"code" binding:"required"`
	}
	if err := ctx.ShouldBindJSON(&body); err != nil {
		ctx.Error(err)
		return
	}
	if !c.twoFactor.Verify(body.Secret, body.Code) {
		ctx.Error(domain.NewDomainError("2FA kodu yanlışdır"))
		return
	}

	user, err := c.repo.FindByID(userDomain.UserID(uid))
	if err != nil || user == nil {
		ctx.Error(domain.NewEntityNotFoundError("User", uid.String()))
		return
	}
	codes := c.twoFactor.GenerateBackupCodes(8)
	if err := user.EnableTwoFactor(body.Secret, codes); err != nil {
		ctx.Error(err)
		return
	}
	if err := c.repo.Save(user); err != nil {
		ctx.Error(err)
		return
	}
	ctx.JSON(http.StatusOK, api.SuccessWithMessage(codes,
		"2FA aktiv edildi. Backup kodları saxlayın!"))
}

// POST /api/auth/2fa/disable
func (c *TwoFactorController) Disable(ctx *gin.Context) {
	uid := ctx.MustGet(middleware.UserIDKey).(uuid.UUID)
	user, err := c.repo.FindByID(userDomain.UserID(uid))
	if err != nil || user == nil {
		ctx.Error(domain.NewEntityNotFoundError("User", uid.String()))
		return
	}
	user.DisableTwoFactor()
	if err := c.repo.Save(user); err != nil {
		ctx.Error(err)
		return
	}
	ctx.JSON(http.StatusOK, api.SuccessWithMessage(nil, "2FA deaktiv edildi"))
}

// POST /api/auth/2fa/verify  body: {user_id, code}
func (c *TwoFactorController) Verify(ctx *gin.Context) {
	var body struct {
		UserID string `json:"user_id" binding:"required"`
		Code   string `json:"code" binding:"required"`
	}
	if err := ctx.ShouldBindJSON(&body); err != nil {
		ctx.Error(err)
		return
	}

	uid, err := uuid.Parse(body.UserID)
	if err != nil {
		ctx.Error(err)
		return
	}

	user, err := c.repo.FindByID(userDomain.UserID(uid))
	if err != nil || user == nil {
		ctx.Error(domain.NewEntityNotFoundError("User", body.UserID))
		return
	}
	if !c.twoFactor.Verify(user.TwoFactorSecret(), body.Code) {
		ctx.Error(domain.NewDomainError("2FA kodu yanlışdır"))
		return
	}

	token, err := c.jwtService.Issue(user.ID().UUID(), user.Email().Value())
	if err != nil {
		ctx.Error(err)
		return
	}
	ctx.JSON(http.StatusOK, api.Success(gin.H{"token": token}))
}

func (c *TwoFactorController) RegisterRoutes(public, authed *gin.RouterGroup) {
	public.POST("/auth/2fa/verify", c.Verify)
	authed.POST("/auth/2fa/enable", c.Enable)
	authed.POST("/auth/2fa/confirm", c.Confirm)
	authed.POST("/auth/2fa/disable", c.Disable)
}
