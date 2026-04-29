// Package feature — Auth endpoint HTTP testləri
//
// Laravel: tests/Feature/AuthApiTest.php
// Spring: AuthApiTest.java (MockMvc)
// Go: net/http/httptest + Gin
package feature

import (
	"bytes"
	"context"
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"sync"
	"testing"
	"time"

	"github.com/gin-gonic/gin"
	"github.com/google/uuid"
	"github.com/orkhan/ecommerce/internal/middleware"
	"github.com/orkhan/ecommerce/internal/shared/application/bus"
	"github.com/orkhan/ecommerce/internal/shared/domain"
	"github.com/orkhan/ecommerce/internal/shared/infrastructure/api"
	"github.com/orkhan/ecommerce/internal/shared/infrastructure/auth"
	userApp "github.com/orkhan/ecommerce/internal/user/application"
	userDomain "github.com/orkhan/ecommerce/internal/user/domain"
	userWeb "github.com/orkhan/ecommerce/internal/user/infrastructure/web"
	"github.com/stretchr/testify/assert"
)

// === In-memory user repo ===

type memUserRepo struct {
	mu    sync.Mutex
	byID  map[userDomain.UserID]*userDomain.User
	email map[string]*userDomain.User
}

func newMemUserRepo() *memUserRepo {
	return &memUserRepo{
		byID:  make(map[userDomain.UserID]*userDomain.User),
		email: make(map[string]*userDomain.User),
	}
}

func (r *memUserRepo) Save(u *userDomain.User) error {
	r.mu.Lock()
	defer r.mu.Unlock()
	r.byID[u.ID()] = u
	r.email[u.Email().Value()] = u
	return nil
}

func (r *memUserRepo) FindByID(id userDomain.UserID) (*userDomain.User, error) {
	r.mu.Lock()
	defer r.mu.Unlock()
	return r.byID[id], nil
}

func (r *memUserRepo) FindByEmail(email userDomain.Email) (*userDomain.User, error) {
	r.mu.Lock()
	defer r.mu.Unlock()
	return r.email[email.Value()], nil
}

func (r *memUserRepo) ExistsByEmail(email userDomain.Email) (bool, error) {
	r.mu.Lock()
	defer r.mu.Unlock()
	_, ok := r.email[email.Value()]
	return ok, nil
}

// === No-op event dispatcher ===

type noopUserDispatcher struct{}

func (d *noopUserDispatcher) DispatchAll(_ context.Context, _ interface{ PullDomainEvents() []domain.DomainEvent }) error {
	return nil
}

// === Test router setup ===

func newAuthRouter() (*gin.Engine, *memUserRepo) {
	gin.SetMode(gin.TestMode)

	repo := newMemUserRepo()
	jwtSvc := auth.NewJWTService("test-secret", time.Hour)
	dispatcher := &noopUserDispatcher{}

	cmdBus := bus.NewBus()
	queryBus := bus.NewQueryBus()

	bus.Register[userApp.RegisterUserCommand, uuid.UUID](cmdBus,
		userApp.NewRegisterUserHandler(repo, dispatcher))
	bus.RegisterQuery[userApp.GetUserQuery, userApp.UserDTO](queryBus,
		userApp.NewGetUserHandler(repo))

	ctrl := userWeb.NewAuthController(cmdBus, queryBus, repo, jwtSvc, nil)

	r := gin.New()
	r.Use(api.ErrorHandler())
	apiGroup := r.Group("/api")
	ctrl.RegisterRoutes(apiGroup, middleware.JWTAuth(jwtSvc))

	return r, repo
}

// === Tests ===

func TestAuthAPI_RegisterSuccess(t *testing.T) {
	r, _ := newAuthRouter()

	body, _ := json.Marshal(map[string]any{
		"name":     "Test İstifadəçi",
		"email":    "test@example.com",
		"password": "password123",
	})
	w := httptest.NewRecorder()
	req, _ := http.NewRequest("POST", "/api/auth/register", bytes.NewReader(body))
	req.Header.Set("Content-Type", "application/json")
	r.ServeHTTP(w, req)

	assert.Equal(t, http.StatusCreated, w.Code)
	var resp map[string]any
	_ = json.Unmarshal(w.Body.Bytes(), &resp)
	assert.True(t, resp["success"].(bool))
	data := resp["data"].(map[string]any)
	assert.NotEmpty(t, data["token"])
	assert.NotEmpty(t, data["user_id"])
}

func TestAuthAPI_RegisterShortPassword(t *testing.T) {
	r, _ := newAuthRouter()

	body, _ := json.Marshal(map[string]any{
		"name":     "Test",
		"email":    "short@example.com",
		"password": "123",
	})
	w := httptest.NewRecorder()
	req, _ := http.NewRequest("POST", "/api/auth/register", bytes.NewReader(body))
	req.Header.Set("Content-Type", "application/json")
	r.ServeHTTP(w, req)

	assert.Equal(t, http.StatusBadRequest, w.Code)
}

func TestAuthAPI_RegisterEmptyBody(t *testing.T) {
	r, _ := newAuthRouter()

	w := httptest.NewRecorder()
	req, _ := http.NewRequest("POST", "/api/auth/register", bytes.NewReader([]byte("{}")))
	req.Header.Set("Content-Type", "application/json")
	r.ServeHTTP(w, req)

	assert.Equal(t, http.StatusBadRequest, w.Code)
}

func TestAuthAPI_LoginSuccess(t *testing.T) {
	r, _ := newAuthRouter()

	// Register first
	reg, _ := json.Marshal(map[string]any{
		"name":     "Login Test",
		"email":    "login@example.com",
		"password": "password123",
	})
	w1 := httptest.NewRecorder()
	req1, _ := http.NewRequest("POST", "/api/auth/register", bytes.NewReader(reg))
	req1.Header.Set("Content-Type", "application/json")
	r.ServeHTTP(w1, req1)
	assert.Equal(t, http.StatusCreated, w1.Code)

	// Login
	body, _ := json.Marshal(map[string]any{
		"email":    "login@example.com",
		"password": "password123",
	})
	w := httptest.NewRecorder()
	req, _ := http.NewRequest("POST", "/api/auth/login", bytes.NewReader(body))
	req.Header.Set("Content-Type", "application/json")
	r.ServeHTTP(w, req)

	assert.Equal(t, http.StatusOK, w.Code)
	var resp map[string]any
	_ = json.Unmarshal(w.Body.Bytes(), &resp)
	assert.True(t, resp["success"].(bool))
}

func TestAuthAPI_LoginWrongPassword(t *testing.T) {
	r, _ := newAuthRouter()

	// Register first
	reg, _ := json.Marshal(map[string]any{
		"name":     "WrongPW Test",
		"email":    "wrongpw@example.com",
		"password": "password123",
	})
	w1 := httptest.NewRecorder()
	req1, _ := http.NewRequest("POST", "/api/auth/register", bytes.NewReader(reg))
	req1.Header.Set("Content-Type", "application/json")
	r.ServeHTTP(w1, req1)

	// Wrong password
	body, _ := json.Marshal(map[string]any{
		"email":    "wrongpw@example.com",
		"password": "wrongpassword",
	})
	w := httptest.NewRecorder()
	req, _ := http.NewRequest("POST", "/api/auth/login", bytes.NewReader(body))
	req.Header.Set("Content-Type", "application/json")
	r.ServeHTTP(w, req)

	assert.True(t, w.Code >= 400)
}

func TestAuthAPI_MeWithoutAuth(t *testing.T) {
	r, _ := newAuthRouter()

	w := httptest.NewRecorder()
	req, _ := http.NewRequest("GET", "/api/auth/me", nil)
	r.ServeHTTP(w, req)

	assert.Equal(t, http.StatusUnauthorized, w.Code)
}

func TestAuthAPI_MeWithValidToken(t *testing.T) {
	r, _ := newAuthRouter()

	// Register
	body, _ := json.Marshal(map[string]any{
		"name":     "Me Test",
		"email":    "me@example.com",
		"password": "password123",
	})
	w1 := httptest.NewRecorder()
	req1, _ := http.NewRequest("POST", "/api/auth/register", bytes.NewReader(body))
	req1.Header.Set("Content-Type", "application/json")
	r.ServeHTTP(w1, req1)

	var reg map[string]any
	_ = json.Unmarshal(w1.Body.Bytes(), &reg)
	token := reg["data"].(map[string]any)["token"].(string)

	// GET /auth/me
	w := httptest.NewRecorder()
	req, _ := http.NewRequest("GET", "/api/auth/me", nil)
	req.Header.Set("Authorization", "Bearer "+token)
	r.ServeHTTP(w, req)

	assert.Equal(t, http.StatusOK, w.Code)
	var resp map[string]any
	_ = json.Unmarshal(w.Body.Bytes(), &resp)
	assert.True(t, resp["success"].(bool))
}

func TestAuthAPI_LogoutWithoutAuth(t *testing.T) {
	r, _ := newAuthRouter()

	w := httptest.NewRecorder()
	req, _ := http.NewRequest("POST", "/api/auth/logout", nil)
	r.ServeHTTP(w, req)

	assert.Equal(t, http.StatusUnauthorized, w.Code)
}
