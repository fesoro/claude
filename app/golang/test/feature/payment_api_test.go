// Package feature — Payment endpoint HTTP testləri
//
// Laravel: tests/Feature/PaymentApiTest.php
// Spring: PaymentApiTest.java (MockMvc)
// Go: net/http/httptest + Gin
//
// Strategy Pattern — payment method-ə görə gateway seçilir:
//   CREDIT_CARD → CreditCardGateway
//   PAYPAL → PayPalGateway
//   BANK_TRANSFER → BankTransferGateway
//   STRIPE → StripeGateway
//
// Yoxlananlar:
// - Auth olmadan 401
// - Boş payload → validation/binding error
// - Process uğurlu → 200 + payment_id
// - GET /payments/:id auth olmadan 401
// - GET /payments/:id mövcud → 200
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
	paymentApp "github.com/orkhan/ecommerce/internal/payment/application"
	paymentWeb "github.com/orkhan/ecommerce/internal/payment/infrastructure/web"
	"github.com/orkhan/ecommerce/internal/shared/application/bus"
	"github.com/orkhan/ecommerce/internal/shared/infrastructure/api"
	"github.com/orkhan/ecommerce/internal/shared/infrastructure/auth"
	"github.com/stretchr/testify/assert"
)

// === Fake Payment handlers ===

type fakeProcessPaymentHandler struct{}

func (h *fakeProcessPaymentHandler) Handle(_ context.Context, cmd paymentApp.ProcessPaymentCommand) (paymentApp.PaymentResult, error) {
	return paymentApp.PaymentResult{ID: uuid.New().String()}, nil
}

type fakeGetPaymentHandler struct{}

func (h *fakeGetPaymentHandler) Handle(_ context.Context, q paymentApp.GetPaymentQuery) (paymentApp.PaymentDTO, error) {
	return paymentApp.PaymentDTO{
		ID:     q.PaymentID,
		Status: "COMPLETED",
	}, nil
}

// === Test router setup ===

func newPaymentRouter() (*gin.Engine, string) {
	gin.SetMode(gin.TestMode)

	jwtSvc := auth.NewJWTService("test-secret", time.Hour)
	token, _ := jwtSvc.Issue(uuid.New(), "payment@example.com")

	cmdBus := bus.NewBus()
	queryBus := bus.NewQueryBus()

	bus.Register[paymentApp.ProcessPaymentCommand, paymentApp.PaymentResult](cmdBus, &fakeProcessPaymentHandler{})
	bus.RegisterQuery[paymentApp.GetPaymentQuery, paymentApp.PaymentDTO](queryBus, &fakeGetPaymentHandler{})

	ctrl := paymentWeb.NewPaymentController(cmdBus, queryBus)

	r := gin.New()
	r.Use(api.ErrorHandler())
	authed := r.Group("/api")
	authed.Use(middleware.JWTAuth(jwtSvc))
	ctrl.RegisterRoutes(authed)

	return r, token
}

// === Tests ===

func TestPaymentAPI_ProcessWithoutAuth(t *testing.T) {
	r, _ := newPaymentRouter()

	body, _ := json.Marshal(map[string]any{
		"OrderID":  uuid.New().String(),
		"UserID":   uuid.New().String(),
		"Amount":   1000,
		"Currency": "AZN",
		"Method":   "CREDIT_CARD",
	})
	w := httptest.NewRecorder()
	req, _ := http.NewRequest("POST", "/api/payments/process", bytes.NewReader(body))
	req.Header.Set("Content-Type", "application/json")
	r.ServeHTTP(w, req)

	assert.Equal(t, http.StatusUnauthorized, w.Code)
}

func TestPaymentAPI_ProcessSuccess(t *testing.T) {
	r, token := newPaymentRouter()

	body, _ := json.Marshal(map[string]any{
		"OrderID":  uuid.New(),
		"UserID":   uuid.New(),
		"Amount":   1000,
		"Currency": "AZN",
		"Method":   "CREDIT_CARD",
	})
	w := httptest.NewRecorder()
	req, _ := http.NewRequest("POST", "/api/payments/process", bytes.NewReader(body))
	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("Authorization", "Bearer "+token)
	r.ServeHTTP(w, req)

	assert.Equal(t, http.StatusOK, w.Code)
	var resp map[string]any
	_ = json.Unmarshal(w.Body.Bytes(), &resp)
	assert.True(t, resp["success"].(bool))
	assert.NotEmpty(t, resp["data"].(map[string]any)["payment_id"])
}

func TestPaymentAPI_GetPaymentWithoutAuth(t *testing.T) {
	r, _ := newPaymentRouter()

	w := httptest.NewRecorder()
	req, _ := http.NewRequest("GET", "/api/payments/"+uuid.New().String(), nil)
	r.ServeHTTP(w, req)

	assert.Equal(t, http.StatusUnauthorized, w.Code)
}

func TestPaymentAPI_GetPaymentWithAuth(t *testing.T) {
	r, token := newPaymentRouter()

	paymentID := uuid.New()
	w := httptest.NewRecorder()
	req, _ := http.NewRequest("GET", "/api/payments/"+paymentID.String(), nil)
	req.Header.Set("Authorization", "Bearer "+token)
	r.ServeHTTP(w, req)

	assert.Equal(t, http.StatusOK, w.Code)
	var resp map[string]any
	_ = json.Unmarshal(w.Body.Bytes(), &resp)
	assert.True(t, resp["success"].(bool))
}

func TestPaymentAPI_ProcessEmptyBody(t *testing.T) {
	r, token := newPaymentRouter()

	w := httptest.NewRecorder()
	req, _ := http.NewRequest("POST", "/api/payments/process", bytes.NewReader([]byte("{}")))
	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("Authorization", "Bearer "+token)
	r.ServeHTTP(w, req)

	// Boş body-də required field-lər yoxdur → bus validation xətası (500) və ya handler xətası
	assert.True(t, w.Code >= 400)
}
