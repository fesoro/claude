package domain

import (
	"github.com/orkhan/ecommerce/internal/shared/domain"
)

// Product — Aggregate Root
type Product struct {
	domain.AggregateRoot

	id          ProductID
	name        ProductName
	description string
	price       Money
	stock       Stock
}

func Create(name ProductName, description string, price Money, stock Stock) *Product {
	id := GenerateProductID()
	p := &Product{
		id:          id,
		name:        name,
		description: description,
		price:       price,
		stock:       stock,
	}
	p.RecordEvent(ProductCreatedEvent{
		BaseEvent: domain.NewBaseEvent(),
		ProductID: id,
		Name:      name.Value(),
		Price:     price,
	})
	return p
}

func Reconstitute(id ProductID, name ProductName, description string, price Money, stock Stock) *Product {
	return &Product{id: id, name: name, description: description, price: price, stock: stock}
}

func (p *Product) DecreaseStock(amount int) error {
	newStock, err := p.stock.Decrease(amount)
	if err != nil {
		return err
	}
	p.stock = newStock
	p.RecordEvent(StockDecreasedEvent{
		BaseEvent:   domain.NewBaseEvent(),
		ProductID:   p.id,
		Amount:      amount,
		NewQuantity: newStock.Quantity(),
	})
	if newStock.IsLow() {
		p.RecordEvent(LowStockIntegrationEvent{
			BaseEvent:    domain.NewBaseEvent(),
			ProductID:    p.id.UUID(),
			ProductName:  p.name.Value(),
			CurrentStock: newStock.Quantity(),
		})
	}
	return nil
}

func (p *Product) IncreaseStock(amount int) error {
	newStock, err := p.stock.Increase(amount)
	if err != nil {
		return err
	}
	p.stock = newStock
	return nil
}

func (p *Product) ID() ProductID         { return p.id }
func (p *Product) Name() ProductName     { return p.name }
func (p *Product) Description() string   { return p.description }
func (p *Product) Price() Money          { return p.price }
func (p *Product) Stock() Stock          { return p.stock }

// Repository interface
type Repository interface {
	Save(product *Product) error
	FindByID(id ProductID) (*Product, error)
	FindAll(page, size int) ([]*Product, error)
	Count() (int64, error)
}

// === SPECIFICATIONS ===

func ProductIsInStock() domain.Specification[*Product] {
	return domain.SpecFunc[*Product](func(p *Product) bool { return !p.Stock().IsOutOfStock() })
}

func ProductPriceIsValid() domain.Specification[*Product] {
	return domain.SpecFunc[*Product](func(p *Product) bool { return !p.Price().IsZero() })
}
