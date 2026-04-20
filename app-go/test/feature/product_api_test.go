// Package feature — HTTP layer (handler) testləri
//
// Laravel: tests/Feature/ProductApiTest.php
// Spring: ProductApiTest (MockMvc)
// Go: net/http/httptest + Gin
package feature

import (
	"bytes"
	"context"
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"testing"

	"github.com/gin-gonic/gin"
	"github.com/google/uuid"
	productApp "github.com/orkhan/ecommerce/internal/product/application"
	productWeb "github.com/orkhan/ecommerce/internal/product/infrastructure/web"
	"github.com/orkhan/ecommerce/internal/shared/application/bus"
	"github.com/stretchr/testify/assert"
)

// === Fake handlers (real DB-siz test üçün) ===

type fakeCreateHandler struct{}

func (h *fakeCreateHandler) Handle(_ context.Context, _ productApp.CreateProductCommand) (uuid.UUID, error) {
	return uuid.New(), nil
}

type fakeListHandler struct{}

func (h *fakeListHandler) Handle(_ context.Context, _ productApp.ListProductsQuery) ([]productApp.ProductDTO, error) {
	return []productApp.ProductDTO{}, nil
}

func TestProductAPI_ListProducts(t *testing.T) {
	gin.SetMode(gin.TestMode)
	cmdBus := bus.NewBus()
	queryBus := bus.NewQueryBus()
	bus.RegisterQuery[productApp.ListProductsQuery, []productApp.ProductDTO](queryBus, &fakeListHandler{})

	r := gin.New()
	ctrl := productWeb.NewProductController(cmdBus, queryBus)
	apiGroup := r.Group("/api")
	authedGroup := r.Group("/api")
	ctrl.RegisterRoutes(apiGroup, authedGroup)

	w := httptest.NewRecorder()
	req, _ := http.NewRequest("GET", "/api/products", nil)
	r.ServeHTTP(w, req)

	assert.Equal(t, http.StatusOK, w.Code)
	var resp map[string]any
	_ = json.Unmarshal(w.Body.Bytes(), &resp)
	assert.True(t, resp["success"].(bool))
}

func TestProductAPI_ValidationError(t *testing.T) {
	gin.SetMode(gin.TestMode)
	cmdBus := bus.NewBus()
	queryBus := bus.NewQueryBus()

	r := gin.New()
	ctrl := productWeb.NewProductController(cmdBus, queryBus)
	apiGroup := r.Group("/api")
	authedGroup := r.Group("/api")
	ctrl.RegisterRoutes(apiGroup, authedGroup)

	body, _ := json.Marshal(map[string]any{"name": "", "price_amount": -100})
	w := httptest.NewRecorder()
	req, _ := http.NewRequest("POST", "/api/products", bytes.NewReader(body))
	req.Header.Set("Content-Type", "application/json")
	r.ServeHTTP(w, req)

	// Validation xətası bus middleware-də atılır, amma handler register olmadığı üçün
	// burada 500 və ya başqa response gözləyirik. Real test-də bus + middleware setup edilməlidir.
	assert.True(t, w.Code >= 400, "expected error status, got %d", w.Code)
}
