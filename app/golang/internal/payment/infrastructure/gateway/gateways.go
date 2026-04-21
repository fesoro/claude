// Package gateway — Strategy Pattern implementations + Circuit Breaker
//
// Laravel: 3 PaymentGateway impl  ·  Spring: 3 @Component PaymentGateway
// Go: 3 struct + Gateway interface
package gateway

import (
	"github.com/google/uuid"
	"github.com/orkhan/ecommerce/internal/payment/domain"
	productDomain "github.com/orkhan/ecommerce/internal/product/domain"
	"github.com/sony/gobreaker"
)

// === CREDIT CARD GATEWAY (Circuit Breaker ilə) ===
//
// Laravel: CreditCardGateway.php
// Spring: CreditCardGateway.java + @CircuitBreaker
// Go: gobreaker library — Resilience4j-ə oxşar API
type CreditCard struct {
	cb *gobreaker.CircuitBreaker
}

func NewCreditCard(failureThreshold uint32, recoveryTimeout int) *CreditCard {
	cb := gobreaker.NewCircuitBreaker(gobreaker.Settings{
		Name:        "creditCardGateway",
		MaxRequests: 1,
		ReadyToTrip: func(counts gobreaker.Counts) bool {
			return counts.ConsecutiveFailures >= failureThreshold
		},
	})
	return &CreditCard{cb: cb}
}

func (g *CreditCard) SupportedMethod() domain.PaymentMethod { return domain.PaymentMethodCreditCard }

func (g *CreditCard) Charge(amount productDomain.Money, reference string) domain.GatewayResult {
	res, err := g.cb.Execute(func() (any, error) {
		// Real-da Stripe/Adyen API çağrısı
		return "CC-" + uuid.New().String(), nil
	})
	if err != nil {
		return domain.GatewayFail("Credit Card gateway xətası: " + err.Error())
	}
	return domain.GatewayOK(res.(string))
}

// === PAYPAL ===
type PayPal struct{}

func NewPayPal() *PayPal { return &PayPal{} }

func (g *PayPal) SupportedMethod() domain.PaymentMethod { return domain.PaymentMethodPayPal }

func (g *PayPal) Charge(amount productDomain.Money, reference string) domain.GatewayResult {
	return domain.GatewayOK("PP-" + uuid.New().String())
}

// === BANK TRANSFER ===
type BankTransfer struct{}

func NewBankTransfer() *BankTransfer { return &BankTransfer{} }

func (g *BankTransfer) SupportedMethod() domain.PaymentMethod { return domain.PaymentMethodBankTransfer }

func (g *BankTransfer) Charge(amount productDomain.Money, reference string) domain.GatewayResult {
	return domain.GatewayOK("BT-" + uuid.New().String())
}
