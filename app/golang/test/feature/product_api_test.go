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
	"time"

	"github.com/gin-gonic/gin"
	"github.com/google/uuid"
	"github.com/orkhan/ecommerce/internal/middleware"
	productApp "github.com/orkhan/ecommerce/internal/product/application"
	productWeb "github.com/orkhan/ecommerce/internal/product/infrastructure/web"
	"github.com/orkhan/ecommerce/internal/shared/application/bus"
	"github.com/orkhan/ecommerce/internal/shared/infrastructure/api"
	"github.com/orkhan/ecommerce/internal/shared/infrastructure/auth"
	"github.com/stretchr/testify/assert"
)

// === Fake handlers ===

type fakeCreateProductHandler struct{}

func (h *fakeCreateProductHandler) Handle(_ context.Context, _ productApp.CreateProductCommand) (uuid.UUID, error) {
	return uuid.New(), nil
}

type fakeListProductsHandler struct{}

func (h *fakeListProductsHandler) Handle(_ context.Context, _ productApp.ListProductsQuery) ([]productApp.ProductDTO, error) {
	return []productApp.ProductDTO{
		{ID: uuid.New(), Name: "Test Məhsul", PriceAmount: 2599, PriceCurrency: "AZN", StockQuantity: 50, InStock: true},
	}, nil
}

type fakeGetProductHandler struct{}

func (h *fakeGetProductHandler) Handle(_ context.Context, q productApp.GetProductQuery) (productApp.ProductDTO, error) {
	return productApp.ProductDTO{
		ID:            q.ProductID,
		Name:          "Test Məhsul",
		PriceAmount:   2599,
		PriceCurrency: "AZN",
		StockQuantity: 50,
		InStock:       true,
	}, nil
}

type fakeUpdateStockHandler struct{}

func (h *fakeUpdateStockHandler) Handle(_ context.Context, _ productApp.UpdateStockCommand) (struct{}, error) {
	return struct{}{}, nil
}

// === Router ===

func newProductRouter() (*gin.Engine, string) {
	gin.SetMode(gin.TestMode)

	jwtSvc := auth.NewJWTService("test-secret", time.Hour)
	token, _ := jwtSvc.Issue(uuid.New(), "product@example.com")

	cmdBus := bus.NewBus()
	queryBus := bus.NewQueryBus()

	bus.Register[productApp.CreateProductCommand, uuid.UUID](cmdBus, &fakeCreateProductHandler{})
	bus.Register[productApp.UpdateStockCommand, struct{}](cmdBus, &fakeUpdateStockHandler{})
	bus.RegisterQuery[productApp.ListProductsQuery, []productApp.ProductDTO](queryBus, &fakeListProductsHandler{})
	bus.RegisterQuery[productApp.GetProductQuery, productApp.ProductDTO](queryBus, &fakeGetProductHandler{})

	r := gin.New()
	r.Use(api.ErrorHandler())
	publicGroup := r.Group("/api")
	authedGroup := r.Group("/api")
	authedGroup.Use(middleware.JWTAuth(jwtSvc))

	ctrl := productWeb.NewProductController(cmdBus, queryBus)
	ctrl.RegisterRoutes(publicGroup, authedGroup)

	return r, token
}

// === Tests ===

func TestProductAPI_ListProductsPublic(t *testing.T) {
	r, _ := newProductRouter()

	w := httptest.NewRecorder()
	req, _ := http.NewRequest("GET", "/api/products", nil)
	r.ServeHTTP(w, req)

	assert.Equal(t, http.StatusOK, w.Code)
	var resp map[string]any
	_ = json.Unmarshal(w.Body.Bytes(), &resp)
	assert.True(t, resp["success"].(bool))
	assert.IsType(t, []any{}, resp["data"])
}

func TestProductAPI_GetProductByIDPublic(t *testing.T) {
	r, _ := newProductRouter()

	productID := uuid.New()
	w := httptest.NewRecorder()
	req, _ := http.NewRequest("GET", "/api/products/"+productID.String(), nil)
	r.ServeHTTP(w, req)

	assert.Equal(t, http.StatusOK, w.Code)
	var resp map[string]any
	_ = json.Unmarshal(w.Body.Bytes(), &resp)
	assert.True(t, resp["success"].(bool))
}

func TestProductAPI_CreateRequiresAuth(t *testing.T) {
	r, _ := newProductRouter()

	body, _ := json.Marshal(map[string]any{
		"Name": "Test", "PriceAmount": 1000, "Currency": "AZN", "StockQuantity": 10,
	})
	w := httptest.NewRecorder()
	req, _ := http.NewRequest("POST", "/api/products", bytes.NewReader(body))
	req.Header.Set("Content-Type", "application/json")
	r.ServeHTTP(w, req)

	assert.Equal(t, http.StatusUnauthorized, w.Code)
}

func TestProductAPI_CreateWithAuth(t *testing.T) {
	r, token := newProductRouter()

	body, _ := json.Marshal(map[string]any{
		"Name": "Yeni Məhsul", "PriceAmount": 2599, "Currency": "AZN", "StockQuantity": 100,
	})
	w := httptest.NewRecorder()
	req, _ := http.NewRequest("POST", "/api/products", bytes.NewReader(body))
	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("Authorization", "Bearer "+token)
	r.ServeHTTP(w, req)

	assert.Equal(t, http.StatusCreated, w.Code)
	var resp map[string]any
	_ = json.Unmarshal(w.Body.Bytes(), &resp)
	assert.True(t, resp["success"].(bool))
}

func TestProductAPI_UpdateStockRequiresAuth(t *testing.T) {
	r, _ := newProductRouter()

	body, _ := json.Marshal(map[string]any{"amount": 10, "type": "increase"})
	w := httptest.NewRecorder()
	req, _ := http.NewRequest("PATCH", "/api/products/"+uuid.New().String()+"/stock", bytes.NewReader(body))
	req.Header.Set("Content-Type", "application/json")
	r.ServeHTTP(w, req)

	assert.Equal(t, http.StatusUnauthorized, w.Code)
}

func TestProductAPI_UpdateStockWithAuth(t *testing.T) {
	r, token := newProductRouter()

	body, _ := json.Marshal(map[string]any{"amount": 10, "type": "increase"})
	w := httptest.NewRecorder()
	req, _ := http.NewRequest("PATCH", "/api/products/"+uuid.New().String()+"/stock", bytes.NewReader(body))
	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("Authorization", "Bearer "+token)
	r.ServeHTTP(w, req)

	assert.Equal(t, http.StatusOK, w.Code)
	var resp map[string]any
	_ = json.Unmarshal(w.Body.Bytes(), &resp)
	assert.True(t, resp["success"].(bool))
}

func TestProductAPI_ValidationError(t *testing.T) {
	r, token := newProductRouter()

	body, _ := json.Marshal(map[string]any{"name": "", "price_amount": -100})
	w := httptest.NewRecorder()
	req, _ := http.NewRequest("POST", "/api/products", bytes.NewReader(body))
	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("Authorization", "Bearer "+token)
	r.ServeHTTP(w, req)

	assert.True(t, w.Code >= 400, "expected error status, got %d", w.Code)
}
