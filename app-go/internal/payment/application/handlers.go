// Package application — Payment context handlers (CQRS)
package application

import (
	"context"
	"time"

	"github.com/google/uuid"
	"github.com/orkhan/ecommerce/internal/payment/domain"
	productDomain "github.com/orkhan/ecommerce/internal/product/domain"
	sharedDomain "github.com/orkhan/ecommerce/internal/shared/domain"
)

// === ACL (Anti-Corruption Layer) ===
//
// Laravel: PaymentGatewayACL.php  ·  Spring: PaymentGatewayACL.java
// Go: GatewayACL struct + StrategyResolver injection
type GatewayACL struct {
	resolver *domain.StrategyResolver
}

func NewGatewayACL(resolver *domain.StrategyResolver) *GatewayACL {
	return &GatewayACL{resolver: resolver}
}

func (a *GatewayACL) ProcessCharge(method domain.PaymentMethod, amount productDomain.Money, reference string) (domain.GatewayResult, error) {
	gw, err := a.resolver.Resolve(method)
	if err != nil {
		return domain.GatewayResult{}, err
	}
	return gw.Charge(amount, reference), nil
}

// === DTO ===

type PaymentDTO struct {
	ID            uuid.UUID `json:"id"`
	OrderID       uuid.UUID `json:"order_id"`
	UserID        uuid.UUID `json:"user_id"`
	Amount        int64     `json:"amount"`
	Currency      string    `json:"currency"`
	Method        string    `json:"method"`
	Status        string    `json:"status"`
	TransactionID string    `json:"transaction_id,omitempty"`
	FailureReason string    `json:"failure_reason,omitempty"`
}

func DTOFromDomain(p *domain.Payment) PaymentDTO {
	return PaymentDTO{
		ID:            p.ID().UUID(),
		OrderID:       p.OrderID(),
		UserID:        p.UserID(),
		Amount:        p.Amount().Amount(),
		Currency:      string(p.Amount().Currency()),
		Method:        string(p.Method()),
		Status:        string(p.Status()),
		TransactionID: p.TransactionID(),
		FailureReason: p.FailureReason(),
	}
}

// === COMMANDS / QUERIES ===

type ProcessPaymentCommand struct {
	OrderID  uuid.UUID `validate:"required"`
	UserID   uuid.UUID `validate:"required"`
	Amount   int64     `validate:"gt=0"`
	Currency string    `validate:"required,oneof=USD EUR AZN"`
	Method   string    `validate:"required,oneof=CREDIT_CARD PAYPAL BANK_TRANSFER STRIPE"`
}

type GetPaymentQuery struct {
	PaymentID uuid.UUID
}

type EventDispatcher interface {
	DispatchAll(ctx context.Context, aggregate interface{ PullDomainEvents() []sharedDomain.DomainEvent }) error
}

// === HANDLERS ===

// Locker — distributed lock interface (test üçün mock edilə bilir)
type Locker interface {
	ExecuteLocked(ctx context.Context, key string, ttl time.Duration, action func() error) error
}

type ProcessPaymentHandler struct {
	repo            domain.Repository
	acl             *GatewayACL
	eventDispatcher EventDispatcher
	locker          Locker
}

func NewProcessPaymentHandler(repo domain.Repository, acl *GatewayACL, ed EventDispatcher, locker Locker) *ProcessPaymentHandler {
	return &ProcessPaymentHandler{repo: repo, acl: acl, eventDispatcher: ed, locker: locker}
}

// PaymentResult — named struct (anonim struct-lar generic type param-da gotcha yaradır)
type PaymentResult struct {
	ID string `json:"id"`
}

// Handle — distributed lock ilə wrap olunub ki, eyni orderID üçün
// 2 paralel payment-in eyni anda işləməsinin qarşısını alsın (race condition protection).
//
// Laravel: DistributedLock::acquire('payment:'.$orderId, ...)
// Spring: DistributedLock.executeLocked("payment:" + orderId, ...)
// Go: Redsync-based ExecuteLocked
func (h *ProcessPaymentHandler) Handle(ctx context.Context, cmd ProcessPaymentCommand) (PaymentResult, error) {
	var result PaymentResult
	lockKey := "payment:" + cmd.OrderID.String()

	err := h.locker.ExecuteLocked(ctx, lockKey, 30*time.Second, func() error {
		var innerErr error
		result, innerErr = h.process(ctx, cmd)
		return innerErr
	})
	return result, err
}

func (h *ProcessPaymentHandler) process(ctx context.Context, cmd ProcessPaymentCommand) (PaymentResult, error) {
	currency, err := productDomain.ParseCurrency(cmd.Currency)
	if err != nil {
		return PaymentResult{}, err
	}
	amount, err := productDomain.NewMoney(cmd.Amount, currency)
	if err != nil {
		return PaymentResult{}, err
	}

	payment := domain.Initiate(cmd.OrderID, cmd.UserID, amount, domain.PaymentMethod(cmd.Method))
	if err := h.repo.Save(payment); err != nil {
		return PaymentResult{}, err
	}

	if err := payment.StartProcessing(); err != nil {
		return PaymentResult{}, err
	}

	gr, _ := h.acl.ProcessCharge(payment.Method(), amount, cmd.OrderID.String())
	if gr.Success {
		_ = payment.Complete(gr.TransactionID)
	} else {
		_ = payment.Fail(gr.ErrorMessage)
	}

	if err := h.repo.Save(payment); err != nil {
		return PaymentResult{}, err
	}
	_ = h.eventDispatcher.DispatchAll(ctx, payment)
	return PaymentResult{ID: payment.ID().String()}, nil
}

type GetPaymentHandler struct {
	repo domain.Repository
}

func NewGetPaymentHandler(repo domain.Repository) *GetPaymentHandler {
	return &GetPaymentHandler{repo: repo}
}

func (h *GetPaymentHandler) Handle(ctx context.Context, q GetPaymentQuery) (PaymentDTO, error) {
	payment, err := h.repo.FindByID(domain.PaymentID(q.PaymentID))
	if err != nil || payment == nil {
		return PaymentDTO{}, sharedDomain.NewEntityNotFoundError("Payment", q.PaymentID.String())
	}
	return DTOFromDomain(payment), nil
}
