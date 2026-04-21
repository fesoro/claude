// Package web — User context Gin handler-ləri
package web

import (
	"net/http"

	"github.com/gin-gonic/gin"
	"github.com/google/uuid"
	"github.com/orkhan/ecommerce/internal/middleware"
	"github.com/orkhan/ecommerce/internal/shared/application/bus"
	"github.com/orkhan/ecommerce/internal/shared/domain"
	"github.com/orkhan/ecommerce/internal/shared/infrastructure/api"
	"github.com/orkhan/ecommerce/internal/shared/infrastructure/auth"
	userApp "github.com/orkhan/ecommerce/internal/user/application"
	userDomain "github.com/orkhan/ecommerce/internal/user/domain"
)

// AuthController — Spring AuthController + Gin handler funksiyaları
//
// Laravel: app/Http/Controllers/AuthController.php
// Spring: AuthController.java (@RestController)
// Go: struct + method-lar (Gin handler)
type AuthController struct {
	cmdBus        *bus.Bus
	queryBus      *bus.QueryHandlers
	repo          userDomain.Repository
	jwt           *auth.JWTService
	passwordReset *userApp.PasswordResetService
}

func NewAuthController(cmdBus *bus.Bus, queryBus *bus.QueryHandlers,
	repo userDomain.Repository, jwt *auth.JWTService,
	pwReset *userApp.PasswordResetService) *AuthController {
	return &AuthController{
		cmdBus: cmdBus, queryBus: queryBus, repo: repo, jwt: jwt,
		passwordReset: pwReset,
	}
}

// POST /api/auth/forgot-password  body: {email}
func (c *AuthController) ForgotPassword(ctx *gin.Context) {
	var body struct {
		Email string `json:"email" binding:"required,email"`
	}
	if err := ctx.ShouldBindJSON(&body); err != nil {
		ctx.Error(err)
		return
	}
	if err := c.passwordReset.RequestReset(ctx.Request.Context(), body.Email); err != nil {
		ctx.Error(err)
		return
	}
	ctx.JSON(http.StatusOK, api.SuccessWithMessage(nil, "Şifrə bərpa linki email ünvanına göndərildi"))
}

// POST /api/auth/reset-password  body: {email, token, password}
func (c *AuthController) ResetPassword(ctx *gin.Context) {
	var body struct {
		Email    string `json:"email" binding:"required,email"`
		Token    string `json:"token" binding:"required"`
		Password string `json:"password" binding:"required,min=8"`
	}
	if err := ctx.ShouldBindJSON(&body); err != nil {
		ctx.Error(err)
		return
	}
	if err := c.passwordReset.ResetPassword(ctx.Request.Context(), body.Email, body.Token, body.Password); err != nil {
		ctx.Error(err)
		return
	}
	ctx.JSON(http.StatusOK, api.SuccessWithMessage(nil, "Şifrə yeniləndi"))
}

// Register — POST /api/auth/register
func (c *AuthController) Register(ctx *gin.Context) {
	var req struct {
		Name     string `json:"name" binding:"required,min=2,max=100"`
		Email    string `json:"email" binding:"required,email"`
		Password string `json:"password" binding:"required,min=8"`
	}
	if err := ctx.ShouldBindJSON(&req); err != nil {
		ctx.Error(domain.NewValidationError(map[string]string{"body": err.Error()}))
		return
	}

	id, err := bus.Dispatch[userApp.RegisterUserCommand, uuid.UUID](
		ctx.Request.Context(), c.cmdBus,
		userApp.RegisterUserCommand{Name: req.Name, Email: req.Email, Password: req.Password})
	if err != nil {
		ctx.Error(err)
		return
	}

	token, _ := c.jwt.Issue(id, req.Email)
	ctx.JSON(http.StatusCreated, api.SuccessWithMessage(
		gin.H{"user_id": id, "token": token}, "Qeydiyyat uğurla tamamlandı"))
}

// Login — POST /api/auth/login
func (c *AuthController) Login(ctx *gin.Context) {
	var req struct {
		Email    string `json:"email" binding:"required,email"`
		Password string `json:"password" binding:"required"`
	}
	if err := ctx.ShouldBindJSON(&req); err != nil {
		ctx.Error(domain.NewValidationError(map[string]string{"body": err.Error()}))
		return
	}

	email, err := userDomain.NewEmail(req.Email)
	if err != nil {
		ctx.Error(domain.NewDomainError("Email və ya şifrə yanlışdır"))
		return
	}

	user, err := c.repo.FindByEmail(email)
	if err != nil || user == nil {
		ctx.Error(domain.NewDomainError("Email və ya şifrə yanlışdır"))
		return
	}
	if !user.VerifyPassword(req.Password) {
		ctx.Error(domain.NewDomainError("Email və ya şifrə yanlışdır"))
		return
	}

	if user.TwoFactorEnabled() {
		ctx.JSON(http.StatusOK, api.Success(gin.H{
			"require_2fa": true,
			"user_id":     user.ID().String(),
		}))
		return
	}

	token, _ := c.jwt.Issue(user.ID().UUID(), user.Email().Value())
	ctx.JSON(http.StatusOK, api.Success(gin.H{"token": token}))
}

// Me — GET /api/auth/me (auth required)
func (c *AuthController) Me(ctx *gin.Context) {
	id := ctx.MustGet(middleware.UserIDKey).(uuid.UUID)

	dto, err := bus.Ask[userApp.GetUserQuery, userApp.UserDTO](
		ctx.Request.Context(), c.queryBus, userApp.GetUserQuery{UserID: id})
	if err != nil {
		ctx.Error(err)
		return
	}
	ctx.JSON(http.StatusOK, api.Success(dto))
}

// Logout — POST /api/auth/logout (JWT stateless — server-side iş yoxdur)
func (c *AuthController) Logout(ctx *gin.Context) {
	ctx.JSON(http.StatusOK, api.SuccessWithMessage(nil, "Çıxış edildi"))
}

// RegisterRoutes — Gin router-də route-ları qeyd edir
func (c *AuthController) RegisterRoutes(r *gin.RouterGroup, authMW gin.HandlerFunc) {
	r.POST("/auth/register", c.Register)
	r.POST("/auth/login", c.Login)
	r.POST("/auth/forgot-password", c.ForgotPassword)
	r.POST("/auth/reset-password", c.ResetPassword)

	authed := r.Group("/auth")
	authed.Use(authMW)
	{
		authed.POST("/logout", c.Logout)
		authed.GET("/me", c.Me)
	}
}
