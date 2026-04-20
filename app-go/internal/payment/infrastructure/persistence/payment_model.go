package persistence

import (
	"errors"
	"time"

	"github.com/google/uuid"
	"github.com/orkhan/ecommerce/internal/payment/domain"
	productDomain "github.com/orkhan/ecommerce/internal/product/domain"
	"gorm.io/gorm"
)

type PaymentModel struct {
	ID              uuid.UUID `gorm:"type:char(36);primaryKey"`
	OrderID         uuid.UUID `gorm:"type:char(36);not null;index"`
	UserID          uuid.UUID `gorm:"type:char(36);not null;index"`
	Amount          int64     `gorm:"not null"`
	Currency        string    `gorm:"size:3;not null"`
	PaymentMethod   string    `gorm:"size:32;not null"`
	Status          string    `gorm:"size:32;not null;index"`
	TransactionID   string    `gorm:"size:128;index"`
	GatewayResponse string    `gorm:"type:json"`
	FailureReason   string    `gorm:"size:512"`
	Version         int64     `gorm:"not null;default:0"`
	TenantID        *uuid.UUID `gorm:"type:char(36);index"`
	CreatedAt       time.Time
	UpdatedAt       time.Time
	CompletedAt     *time.Time
}

func (PaymentModel) TableName() string { return "payments" }

type Repository struct {
	db *gorm.DB
}

func NewRepository(db *gorm.DB) *Repository {
	return &Repository{db: db}
}

func (r *Repository) Save(p *domain.Payment) error {
	model := toModel(p)
	var existing PaymentModel
	err := r.db.Where("id = ?", model.ID).First(&existing).Error
	if errors.Is(err, gorm.ErrRecordNotFound) {
		return r.db.Create(model).Error
	}
	if err != nil {
		return err
	}
	model.CreatedAt = existing.CreatedAt
	return r.db.Save(model).Error
}

func (r *Repository) FindByID(id domain.PaymentID) (*domain.Payment, error) {
	var model PaymentModel
	err := r.db.Where("id = ?", id.UUID()).First(&model).Error
	if err != nil {
		if errors.Is(err, gorm.ErrRecordNotFound) {
			return nil, nil
		}
		return nil, err
	}
	return toDomain(&model), nil
}

func toModel(p *domain.Payment) *PaymentModel {
	return &PaymentModel{
		ID:            p.ID().UUID(),
		OrderID:       p.OrderID(),
		UserID:        p.UserID(),
		Amount:        p.Amount().Amount(),
		Currency:      string(p.Amount().Currency()),
		PaymentMethod: string(p.Method()),
		Status:        string(p.Status()),
		TransactionID: p.TransactionID(),
		FailureReason: p.FailureReason(),
	}
}

func toDomain(m *PaymentModel) *domain.Payment {
	currency, _ := productDomain.ParseCurrency(m.Currency)
	amount, _ := productDomain.NewMoney(m.Amount, currency)
	return domain.Reconstitute(
		domain.PaymentID(m.ID), m.OrderID, m.UserID, amount,
		domain.PaymentMethod(m.PaymentMethod), domain.PaymentStatus(m.Status),
		m.TransactionID, m.FailureReason)
}
