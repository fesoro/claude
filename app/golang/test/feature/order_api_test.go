// Package feature — Order endpoint HTTP testləri
//
// Laravel: tests/Feature/OrderApiTest.php
// Spring: OrderApiTest.java (MockMvc)
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
	orderApp "github.com/orkhan/ecommerce/internal/order/application"
	orderWeb "github.com/orkhan/ecommerce/internal/order/infrastructure/web"
	"github.com/orkhan/ecommerce/internal/shared/application/bus"
	"github.com/orkhan/ecommerce/internal/shared/infrastructure/api"
	"github.com/orkhan/ecommerce/internal/shared/infrastructure/auth"
	"github.com/stretchr/testify/assert"
)

// === Fake Order handlers ===

type fakeCreateOrderHandler struct{}

func (h *fakeCreateOrderHandler) Handle(_ context.Context, _ orderApp.CreateOrderCommand) (uuid.UUID, error) {
	return uuid.New(), nil
}

type fakeGetOrderHandler struct{}

func (h *fakeGetOrderHandler) Handle(_ context.Context, q orderApp.GetOrderQuery) (orderApp.OrderDTO, error) {
	return orderApp.OrderDTO{
		ID:     q.OrderID,
		Status: "PENDING",
	}, nil
}

type fakeListOrdersHandler struct{}

func (h *fakeListOrdersHandler) Handle(_ context.Context, _ orderApp.ListOrdersQuery) ([]orderApp.OrderDTO, error) {
	return []orderApp.OrderDTO{}, nil
}

type fakeCancelOrderHandler struct{}

func (h *fakeCancelOrderHandler) Handle(_ context.Context, _ orderApp.CancelOrderCommand) (struct{}, error) {
	return struct{}{}, nil
}

type fakeUpdateOrderStatusHandler struct{}

func (h *fakeUpdateOrderStatusHandler) Handle(_ context.Context, _ orderApp.UpdateOrderStatusCommand) (struct{}, error) {
	return struct{}{}, nil
}

// === Test router setup ===

func newOrderRouter() (*gin.Engine, string) {
	gin.SetMode(gin.TestMode)

	jwtSvc := auth.NewJWTService("test-secret", time.Hour)
	token, _ := jwtSvc.Issue(uuid.New(), "order@example.com")

	cmdBus := bus.NewBus()
	queryBus := bus.NewQueryBus()

	bus.Register[orderApp.CreateOrderCommand, uuid.UUID](cmdBus, &fakeCreateOrderHandler{})
	bus.Register[orderApp.CancelOrderCommand, struct{}](cmdBus, &fakeCancelOrderHandler{})
	bus.Register[orderApp.UpdateOrderStatusCommand, struct{}](cmdBus, &fakeUpdateOrderStatusHandler{})
	bus.RegisterQuery[orderApp.GetOrderQuery, orderApp.OrderDTO](queryBus, &fakeGetOrderHandler{})
	bus.RegisterQuery[orderApp.ListOrdersQuery, []orderApp.OrderDTO](queryBus, &fakeListOrdersHandler{})

	ctrl := orderWeb.NewOrderController(cmdBus, queryBus)

	r := gin.New()
	r.Use(api.ErrorHandler())
	authed := r.Group("/api")
	authed.Use(middleware.JWTAuth(jwtSvc))
	ctrl.RegisterRoutes(authed)

	return r, token
}

// === Tests ===

func TestOrderAPI_CreateWithoutAuth(t *testing.T) {
	r, _ := newOrderRouter()

	body, _ := json.Marshal(map[string]any{
		"user_id":  uuid.New(),
		"currency": "AZN",
		"items":    []map[string]any{{"product_id": uuid.New(), "quantity": 1}},
	})
	w := httptest.NewRecorder()
	req, _ := http.NewRequest("POST", "/api/orders", bytes.NewReader(body))
	req.Header.Set("Content-Type", "application/json")
	r.ServeHTTP(w, req)

	assert.Equal(t, http.StatusUnauthorized, w.Code)
}

func TestOrderAPI_CreateSuccess(t *testing.T) {
	r, token := newOrderRouter()

	body, _ := json.Marshal(map[string]any{
		"UserID":   uuid.New(),
		"Currency": "AZN",
		"Items": []map[string]any{{
			"ProductID":         uuid.New().String(),
			"ProductName":       "Test Məhsul",
			"UnitPriceAmount":   1000,
			"UnitPriceCurrency": "AZN",
			"Quantity":          1,
		}},
		"Address": map[string]any{
			"Street":  "Test 1",
			"City":    "Bakı",
			"Zip":     "AZ1000",
			"Country": "AZ",
		},
	})
	w := httptest.NewRecorder()
	req, _ := http.NewRequest("POST", "/api/orders", bytes.NewReader(body))
	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("Authorization", "Bearer "+token)
	r.ServeHTTP(w, req)

	// Fake handler uğurla qaytarır
	assert.Equal(t, http.StatusCreated, w.Code)
}

func TestOrderAPI_GetOrderWithoutAuth(t *testing.T) {
	r, _ := newOrderRouter()

	w := httptest.NewRecorder()
	req, _ := http.NewRequest("GET", "/api/orders/"+uuid.New().String(), nil)
	r.ServeHTTP(w, req)

	assert.Equal(t, http.StatusUnauthorized, w.Code)
}

func TestOrderAPI_GetOrderWithAuth(t *testing.T) {
	r, token := newOrderRouter()

	orderID := uuid.New()
	w := httptest.NewRecorder()
	req, _ := http.NewRequest("GET", "/api/orders/"+orderID.String(), nil)
	req.Header.Set("Authorization", "Bearer "+token)
	r.ServeHTTP(w, req)

	assert.Equal(t, http.StatusOK, w.Code)
	var resp map[string]any
	_ = json.Unmarshal(w.Body.Bytes(), &resp)
	assert.True(t, resp["success"].(bool))
}

func TestOrderAPI_ListOrdersByUser(t *testing.T) {
	r, token := newOrderRouter()

	w := httptest.NewRecorder()
	req, _ := http.NewRequest("GET", "/api/orders/user/"+uuid.New().String(), nil)
	req.Header.Set("Authorization", "Bearer "+token)
	r.ServeHTTP(w, req)

	assert.Equal(t, http.StatusOK, w.Code)
}

func TestOrderAPI_CancelOrder(t *testing.T) {
	r, token := newOrderRouter()

	body, _ := json.Marshal(map[string]any{"reason": "Müştəri ləğv etdi"})
	orderID := uuid.New()
	w := httptest.NewRecorder()
	req, _ := http.NewRequest("POST", "/api/orders/"+orderID.String()+"/cancel", bytes.NewReader(body))
	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("Authorization", "Bearer "+token)
	r.ServeHTTP(w, req)

	assert.Equal(t, http.StatusOK, w.Code)
}
