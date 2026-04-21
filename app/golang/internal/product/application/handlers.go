// Package application — Product CQRS handler-ləri
package application

import (
	"context"

	"github.com/google/uuid"
	productDomain "github.com/orkhan/ecommerce/internal/product/domain"
	sharedDomain "github.com/orkhan/ecommerce/internal/shared/domain"
)

type ProductDTO struct {
	ID            uuid.UUID `json:"id"`
	Name          string    `json:"name"`
	Description   string    `json:"description"`
	PriceAmount   int64     `json:"price_amount"`
	PriceCurrency string    `json:"price_currency"`
	StockQuantity int       `json:"stock_quantity"`
	InStock       bool      `json:"in_stock"`
	LowStock      bool      `json:"low_stock"`
}

func DTOFromDomain(p *productDomain.Product) ProductDTO {
	return ProductDTO{
		ID:            p.ID().UUID(),
		Name:          p.Name().Value(),
		Description:   p.Description(),
		PriceAmount:   p.Price().Amount(),
		PriceCurrency: string(p.Price().Currency()),
		StockQuantity: p.Stock().Quantity(),
		InStock:       !p.Stock().IsOutOfStock(),
		LowStock:      p.Stock().IsLow(),
	}
}

// === COMMANDS ===

type CreateProductCommand struct {
	Name          string `validate:"required,max=255"`
	Description   string `validate:"max=5000"`
	PriceAmount   int64  `validate:"gte=0"`
	Currency      string `validate:"required,oneof=USD EUR AZN"`
	StockQuantity int    `validate:"gte=0"`
}

type UpdateStockCommand struct {
	ProductID uuid.UUID `validate:"required"`
	Amount    int       `validate:"gt=0"`
	Type      string    `validate:"required,oneof=increase decrease"`
}

// === QUERIES ===

type GetProductQuery struct {
	ProductID uuid.UUID
}

type ListProductsQuery struct {
	Page int
	Size int
}

// === EVENT DISPATCHER (interface — DI inversion) ===

type EventDispatcher interface {
	DispatchAll(ctx context.Context, aggregate interface{ PullDomainEvents() []sharedDomain.DomainEvent }) error
}

// === HANDLERS ===

type CreateProductHandler struct {
	repo            productDomain.Repository
	eventDispatcher EventDispatcher
}

func NewCreateProductHandler(repo productDomain.Repository, ed EventDispatcher) *CreateProductHandler {
	return &CreateProductHandler{repo: repo, eventDispatcher: ed}
}

func (h *CreateProductHandler) Handle(ctx context.Context, cmd CreateProductCommand) (uuid.UUID, error) {
	name, err := productDomain.NewProductName(cmd.Name)
	if err != nil {
		return uuid.Nil, err
	}
	currency, err := productDomain.ParseCurrency(cmd.Currency)
	if err != nil {
		return uuid.Nil, err
	}
	price, err := productDomain.NewMoney(cmd.PriceAmount, currency)
	if err != nil {
		return uuid.Nil, err
	}
	stock, err := productDomain.NewStock(cmd.StockQuantity)
	if err != nil {
		return uuid.Nil, err
	}

	product := productDomain.Create(name, cmd.Description, price, stock)

	if !productDomain.ProductPriceIsValid().IsSatisfiedBy(product) {
		return uuid.Nil, sharedDomain.NewDomainError("Məhsul qiyməti 0-dan böyük olmalıdır")
	}

	if err := h.repo.Save(product); err != nil {
		return uuid.Nil, err
	}
	_ = h.eventDispatcher.DispatchAll(ctx, product)
	return product.ID().UUID(), nil
}

type UpdateStockHandler struct {
	repo            productDomain.Repository
	eventDispatcher EventDispatcher
}

func NewUpdateStockHandler(repo productDomain.Repository, ed EventDispatcher) *UpdateStockHandler {
	return &UpdateStockHandler{repo: repo, eventDispatcher: ed}
}

func (h *UpdateStockHandler) Handle(ctx context.Context, cmd UpdateStockCommand) (struct{}, error) {
	product, err := h.repo.FindByID(productDomain.ProductID(cmd.ProductID))
	if err != nil {
		return struct{}{}, err
	}
	if product == nil {
		return struct{}{}, sharedDomain.NewEntityNotFoundError("Product", cmd.ProductID.String())
	}

	if cmd.Type == "increase" {
		err = product.IncreaseStock(cmd.Amount)
	} else {
		err = product.DecreaseStock(cmd.Amount)
	}
	if err != nil {
		return struct{}{}, err
	}

	if err := h.repo.Save(product); err != nil {
		return struct{}{}, err
	}
	_ = h.eventDispatcher.DispatchAll(ctx, product)
	return struct{}{}, nil
}

type GetProductHandler struct {
	repo productDomain.Repository
}

func NewGetProductHandler(repo productDomain.Repository) *GetProductHandler {
	return &GetProductHandler{repo: repo}
}

func (h *GetProductHandler) Handle(ctx context.Context, q GetProductQuery) (ProductDTO, error) {
	product, err := h.repo.FindByID(productDomain.ProductID(q.ProductID))
	if err != nil {
		return ProductDTO{}, err
	}
	if product == nil {
		return ProductDTO{}, sharedDomain.NewEntityNotFoundError("Product", q.ProductID.String())
	}
	return DTOFromDomain(product), nil
}

type ListProductsHandler struct {
	repo productDomain.Repository
}

func NewListProductsHandler(repo productDomain.Repository) *ListProductsHandler {
	return &ListProductsHandler{repo: repo}
}

func (h *ListProductsHandler) Handle(ctx context.Context, q ListProductsQuery) ([]ProductDTO, error) {
	products, err := h.repo.FindAll(q.Page, q.Size)
	if err != nil {
		return nil, err
	}
	dtos := make([]ProductDTO, len(products))
	for i, p := range products {
		dtos[i] = DTOFromDomain(p)
	}
	return dtos, nil
}
