package persistence

import (
	"errors"
	"time"

	"github.com/google/uuid"
	"github.com/orkhan/ecommerce/internal/product/domain"
	"gorm.io/gorm"
)

type ProductModel struct {
	ID            uuid.UUID `gorm:"type:char(36);primaryKey"`
	Name          string    `gorm:"size:255;not null;index"`
	Description   string    `gorm:"type:text"`
	PriceAmount   int64     `gorm:"not null"`
	PriceCurrency string    `gorm:"size:3;not null;index"`
	StockQuantity int       `gorm:"not null;default:0;index"`
	Version       int64     `gorm:"not null;default:0"`
	TenantID      *uuid.UUID `gorm:"type:char(36);index"`
	CreatedAt     time.Time
	UpdatedAt     time.Time
}

func (ProductModel) TableName() string { return "products" }

type Repository struct {
	db *gorm.DB
}

func NewRepository(db *gorm.DB) *Repository {
	return &Repository{db: db}
}

func (r *Repository) Save(product *domain.Product) error {
	model := toModel(product)
	var existing ProductModel
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

func (r *Repository) FindByID(id domain.ProductID) (*domain.Product, error) {
	var model ProductModel
	err := r.db.Where("id = ?", id.UUID()).First(&model).Error
	if err != nil {
		if errors.Is(err, gorm.ErrRecordNotFound) {
			return nil, nil
		}
		return nil, err
	}
	return toDomain(&model), nil
}

func (r *Repository) FindAll(page, size int) ([]*domain.Product, error) {
	var models []ProductModel
	err := r.db.Limit(size).Offset(page * size).Find(&models).Error
	if err != nil {
		return nil, err
	}
	products := make([]*domain.Product, len(models))
	for i, m := range models {
		products[i] = toDomain(&m)
	}
	return products, nil
}

func (r *Repository) Count() (int64, error) {
	var count int64
	err := r.db.Model(&ProductModel{}).Count(&count).Error
	return count, err
}

func toModel(p *domain.Product) *ProductModel {
	return &ProductModel{
		ID:            p.ID().UUID(),
		Name:          p.Name().Value(),
		Description:   p.Description(),
		PriceAmount:   p.Price().Amount(),
		PriceCurrency: string(p.Price().Currency()),
		StockQuantity: p.Stock().Quantity(),
	}
}

func toDomain(m *ProductModel) *domain.Product {
	name, _ := domain.NewProductName(m.Name)
	currency, _ := domain.ParseCurrency(m.PriceCurrency)
	price, _ := domain.NewMoney(m.PriceAmount, currency)
	stock, _ := domain.NewStock(m.StockQuantity)
	return domain.Reconstitute(domain.ProductID(m.ID), name, m.Description, price, stock)
}
