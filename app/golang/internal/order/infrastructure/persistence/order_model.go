package persistence

import (
	"errors"
	"time"

	"github.com/google/uuid"
	"github.com/orkhan/ecommerce/internal/order/domain"
	productDomain "github.com/orkhan/ecommerce/internal/product/domain"
	"gorm.io/gorm"
)

type OrderModel struct {
	ID             uuid.UUID `gorm:"type:char(36);primaryKey"`
	UserID         uuid.UUID `gorm:"type:char(36);not null;index"`
	Status         string    `gorm:"size:32;not null;index"`
	TotalAmount    int64     `gorm:"not null"`
	TotalCurrency  string    `gorm:"size:3;not null"`
	AddressStreet  string    `gorm:"size:255;not null"`
	AddressCity    string    `gorm:"size:128;not null"`
	AddressZip     string    `gorm:"size:32;not null"`
	AddressCountry string    `gorm:"size:64;not null"`
	Version        int64     `gorm:"not null;default:0"`
	TenantID       *uuid.UUID `gorm:"type:char(36);index"`
	CreatedAt      time.Time
	UpdatedAt      time.Time
	Items          []OrderItemModel `gorm:"foreignKey:OrderID;constraint:OnDelete:CASCADE"`
}

func (OrderModel) TableName() string { return "orders" }

type OrderItemModel struct {
	ID                uuid.UUID `gorm:"type:char(36);primaryKey"`
	OrderID           uuid.UUID `gorm:"type:char(36);not null;index"`
	ProductID         uuid.UUID `gorm:"type:char(36);not null;index"`
	ProductName       string    `gorm:"size:255;not null"`
	UnitPriceAmount   int64     `gorm:"not null"`
	UnitPriceCurrency string    `gorm:"size:3;not null"`
	Quantity          int       `gorm:"not null"`
	LineTotal         int64     `gorm:"not null"`
}

func (OrderItemModel) TableName() string { return "order_items" }

type Repository struct {
	db *gorm.DB
}

func NewRepository(db *gorm.DB) *Repository {
	return &Repository{db: db}
}

func (r *Repository) Save(order *domain.Order) error {
	model := toModel(order)
	var existing OrderModel
	err := r.db.Where("id = ?", model.ID).First(&existing).Error
	if errors.Is(err, gorm.ErrRecordNotFound) {
		return r.db.Create(model).Error
	}
	if err != nil {
		return err
	}
	// Replace items (orphan removal)
	r.db.Where("order_id = ?", model.ID).Delete(&OrderItemModel{})
	model.CreatedAt = existing.CreatedAt
	return r.db.Save(model).Error
}

func (r *Repository) FindByID(id domain.OrderID) (*domain.Order, error) {
	var model OrderModel
	err := r.db.Preload("Items").Where("id = ?", id.UUID()).First(&model).Error
	if err != nil {
		if errors.Is(err, gorm.ErrRecordNotFound) {
			return nil, nil
		}
		return nil, err
	}
	return toDomain(&model), nil
}

func (r *Repository) FindByUserID(userID uuid.UUID) ([]*domain.Order, error) {
	var models []OrderModel
	err := r.db.Preload("Items").Where("user_id = ?", userID).
		Order("created_at DESC").Find(&models).Error
	if err != nil {
		return nil, err
	}
	orders := make([]*domain.Order, len(models))
	for i := range models {
		orders[i] = toDomain(&models[i])
	}
	return orders, nil
}

func toModel(o *domain.Order) *OrderModel {
	addr := o.Address()
	items := make([]OrderItemModel, len(o.Items()))
	for i, item := range o.Items() {
		items[i] = OrderItemModel{
			ID:                uuid.New(),
			OrderID:           o.ID().UUID(),
			ProductID:         item.ProductID,
			ProductName:       item.ProductName,
			UnitPriceAmount:   item.UnitPrice.Amount(),
			UnitPriceCurrency: string(item.UnitPrice.Currency()),
			Quantity:          item.Quantity,
			LineTotal:         item.LineTotal().Amount(),
		}
	}
	return &OrderModel{
		ID:             o.ID().UUID(),
		UserID:         o.UserID(),
		Status:         string(o.Status()),
		TotalAmount:    o.TotalAmount().Amount(),
		TotalCurrency:  string(o.TotalAmount().Currency()),
		AddressStreet:  addr.Street,
		AddressCity:    addr.City,
		AddressZip:     addr.Zip,
		AddressCountry: addr.Country,
		Items:          items,
	}
}

func toDomain(m *OrderModel) *domain.Order {
	currency := productDomain.Currency(m.TotalCurrency)
	items := make([]domain.OrderItem, len(m.Items))
	for i, im := range m.Items {
		unitPrice, _ := productDomain.NewMoney(im.UnitPriceAmount,
			productDomain.Currency(im.UnitPriceCurrency))
		items[i] = domain.OrderItem{
			ProductID:   im.ProductID,
			ProductName: im.ProductName,
			UnitPrice:   unitPrice,
			Quantity:    im.Quantity,
		}
	}
	address, _ := domain.NewAddress(m.AddressStreet, m.AddressCity, m.AddressZip, m.AddressCountry)
	totalAmount, _ := productDomain.NewMoney(m.TotalAmount, currency)
	return domain.Reconstitute(domain.OrderID(m.ID), m.UserID, items, address,
		domain.OrderStatus(m.Status), totalAmount)
}
