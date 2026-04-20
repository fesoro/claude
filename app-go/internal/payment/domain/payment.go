// Package domain — Payment context
package domain

import (
	"time"

	"github.com/google/uuid"
	productDomain "github.com/orkhan/ecommerce/internal/product/domain"
	sharedDomain "github.com/orkhan/ecommerce/internal/shared/domain"
)

type PaymentID uuid.UUID

func GeneratePaymentID() PaymentID  { return PaymentID(uuid.New()) }
func (p PaymentID) UUID() uuid.UUID { return uuid.UUID(p) }
func (p PaymentID) String() string  { return uuid.UUID(p).String() }

// === ENUMS ===

type PaymentMethod string

const (
	PaymentMethodCreditCard   PaymentMethod = "CREDIT_CARD"
	PaymentMethodPayPal       PaymentMethod = "PAYPAL"
	PaymentMethodBankTransfer PaymentMethod = "BANK_TRANSFER"
	PaymentMethodStripe       PaymentMethod = "STRIPE"
)

type PaymentStatus string

const (
	PaymentStatusPending    PaymentStatus = "PENDING"
	PaymentStatusProcessing PaymentStatus = "PROCESSING"
	PaymentStatusCompleted  PaymentStatus = "COMPLETED"
	PaymentStatusFailed     PaymentStatus = "FAILED"
	PaymentStatusRefunded   PaymentStatus = "REFUNDED"
)

func (s PaymentStatus) RequireTransitionTo(target PaymentStatus) error {
	allowed := map[PaymentStatus]map[PaymentStatus]bool{
		PaymentStatusPending:    {PaymentStatusProcessing: true},
		PaymentStatusProcessing: {PaymentStatusCompleted: true, PaymentStatusFailed: true},
		PaymentStatusCompleted:  {PaymentStatusRefunded: true},
	}
	if !allowed[s][target] {
		return sharedDomain.NewDomainError("Yanlış payment status keçidi: " + string(s) + " → " + string(target))
	}
	return nil
}

// === EVENTS ===

type PaymentCreatedEvent struct {
	sharedDomain.BaseEvent
	PaymentID PaymentID
	OrderID   uuid.UUID
}

func (PaymentCreatedEvent) EventName() string { return "PaymentCreated" }

type PaymentCompletedEvent struct {
	sharedDomain.BaseEvent
	PaymentID PaymentID
	OrderID   uuid.UUID
}

func (PaymentCompletedEvent) EventName() string { return "PaymentCompleted" }

type PaymentCompletedIntegrationEvent struct {
	sharedDomain.BaseEvent
	PaymentID uuid.UUID `json:"payment_id"`
	OrderID   uuid.UUID `json:"order_id"`
	Amount    int64     `json:"amount"`
}

func (PaymentCompletedIntegrationEvent) EventName() string  { return "PaymentCompletedIntegration" }
func (PaymentCompletedIntegrationEvent) RoutingKey() string { return "payment.completed" }

type PaymentFailedEvent struct {
	sharedDomain.BaseEvent
	PaymentID PaymentID
	OrderID   uuid.UUID
	Reason    string
}

func (PaymentFailedEvent) EventName() string { return "PaymentFailed" }

type PaymentFailedIntegrationEvent struct {
	sharedDomain.BaseEvent
	PaymentID uuid.UUID `json:"payment_id"`
	OrderID   uuid.UUID `json:"order_id"`
	Reason    string    `json:"reason"`
}

func (PaymentFailedIntegrationEvent) EventName() string  { return "PaymentFailedIntegration" }
func (PaymentFailedIntegrationEvent) RoutingKey() string { return "payment.failed" }

// === AGGREGATE ===

type Payment struct {
	sharedDomain.AggregateRoot

	id            PaymentID
	orderID       uuid.UUID
	userID        uuid.UUID
	amount        productDomain.Money
	method        PaymentMethod
	status        PaymentStatus
	transactionID string
	failureReason string
	completedAt   *time.Time
}

func Initiate(orderID, userID uuid.UUID, amount productDomain.Money, method PaymentMethod) *Payment {
	id := GeneratePaymentID()
	p := &Payment{
		id: id, orderID: orderID, userID: userID,
		amount: amount, method: method, status: PaymentStatusPending,
	}
	p.RecordEvent(PaymentCreatedEvent{
		BaseEvent: sharedDomain.NewBaseEvent(), PaymentID: id, OrderID: orderID,
	})
	return p
}

func Reconstitute(id PaymentID, orderID, userID uuid.UUID, amount productDomain.Money,
	method PaymentMethod, status PaymentStatus, transactionID, failureReason string) *Payment {
	return &Payment{
		id: id, orderID: orderID, userID: userID,
		amount: amount, method: method, status: status,
		transactionID: transactionID, failureReason: failureReason,
	}
}

func (p *Payment) StartProcessing() error {
	if err := p.status.RequireTransitionTo(PaymentStatusProcessing); err != nil {
		return err
	}
	p.status = PaymentStatusProcessing
	return nil
}

func (p *Payment) Complete(transactionID string) error {
	if err := p.status.RequireTransitionTo(PaymentStatusCompleted); err != nil {
		return err
	}
	now := time.Now()
	p.status = PaymentStatusCompleted
	p.transactionID = transactionID
	p.completedAt = &now
	p.RecordEvent(PaymentCompletedEvent{
		BaseEvent: sharedDomain.NewBaseEvent(), PaymentID: p.id, OrderID: p.orderID,
	})
	p.RecordEvent(PaymentCompletedIntegrationEvent{
		BaseEvent: sharedDomain.NewBaseEvent(),
		PaymentID: p.id.UUID(), OrderID: p.orderID, Amount: p.amount.Amount(),
	})
	return nil
}

func (p *Payment) Fail(reason string) error {
	if err := p.status.RequireTransitionTo(PaymentStatusFailed); err != nil {
		return err
	}
	p.status = PaymentStatusFailed
	p.failureReason = reason
	p.RecordEvent(PaymentFailedEvent{
		BaseEvent: sharedDomain.NewBaseEvent(),
		PaymentID: p.id, OrderID: p.orderID, Reason: reason,
	})
	p.RecordEvent(PaymentFailedIntegrationEvent{
		BaseEvent: sharedDomain.NewBaseEvent(),
		PaymentID: p.id.UUID(), OrderID: p.orderID, Reason: reason,
	})
	return nil
}

// Getters
func (p *Payment) ID() PaymentID                     { return p.id }
func (p *Payment) OrderID() uuid.UUID                { return p.orderID }
func (p *Payment) UserID() uuid.UUID                 { return p.userID }
func (p *Payment) Amount() productDomain.Money       { return p.amount }
func (p *Payment) Method() PaymentMethod             { return p.method }
func (p *Payment) Status() PaymentStatus             { return p.status }
func (p *Payment) TransactionID() string             { return p.transactionID }
func (p *Payment) FailureReason() string             { return p.failureReason }

// Repository
type Repository interface {
	Save(payment *Payment) error
	FindByID(id PaymentID) (*Payment, error)
}

// === STRATEGY PATTERN ===

// Gateway — Strategy interface
//
// Laravel: PaymentGatewayInterface  ·  Spring: PaymentGateway
// Go: interface (implicit implementation)
type Gateway interface {
	SupportedMethod() PaymentMethod
	Charge(amount productDomain.Money, reference string) GatewayResult
}

type GatewayResult struct {
	Success       bool
	TransactionID string
	ErrorMessage  string
}

func GatewayOK(txID string) GatewayResult {
	return GatewayResult{Success: true, TransactionID: txID}
}

func GatewayFail(msg string) GatewayResult {
	return GatewayResult{Success: false, ErrorMessage: msg}
}

// StrategyResolver — Strategy pattern resolver (DI map)
type StrategyResolver struct {
	gateways map[PaymentMethod]Gateway
}

func NewStrategyResolver(gws ...Gateway) *StrategyResolver {
	m := make(map[PaymentMethod]Gateway)
	for _, g := range gws {
		m[g.SupportedMethod()] = g
	}
	return &StrategyResolver{gateways: m}
}

func (r *StrategyResolver) Resolve(method PaymentMethod) (Gateway, error) {
	g, ok := r.gateways[method]
	if !ok {
		return nil, sharedDomain.NewDomainError("Bu ödəmə üsulu dəstəklənmir: " + string(method))
	}
	return g, nil
}
