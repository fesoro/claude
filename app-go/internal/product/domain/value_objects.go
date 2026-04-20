// Package domain — Product context VOs and aggregate
package domain

import (
	"fmt"

	"github.com/google/uuid"
	"github.com/orkhan/ecommerce/internal/shared/domain"
)

// === PRODUCT ID ===
type ProductID uuid.UUID

func GenerateProductID() ProductID { return ProductID(uuid.New()) }
func (p ProductID) UUID() uuid.UUID { return uuid.UUID(p) }
func (p ProductID) String() string  { return uuid.UUID(p).String() }

// === CURRENCY ENUM ===
type Currency string

const (
	USD Currency = "USD"
	EUR Currency = "EUR"
	AZN Currency = "AZN"
)

func ParseCurrency(s string) (Currency, error) {
	switch s {
	case "USD", "EUR", "AZN":
		return Currency(s), nil
	}
	return "", domain.NewDomainError("Dəstəklənməyən valyuta: " + s)
}

// === MONEY VALUE OBJECT (ən vacib) ===
//
// Laravel: Money.php (qəpiklə)
// Spring: Money.java (Java record + arithmetic)
// Go: struct + value receiver method-lar (immutable)
type Money struct {
	amount   int64    // qəpiklə (cent)
	currency Currency
}

func NewMoney(amount int64, currency Currency) (Money, error) {
	if amount < 0 {
		return Money{}, domain.NewDomainError(fmt.Sprintf("Money mənfi ola bilməz: %d", amount))
	}
	return Money{amount: amount, currency: currency}, nil
}

func MustMoney(amount int64, currency Currency) Money {
	m, err := NewMoney(amount, currency)
	if err != nil {
		panic(err)
	}
	return m
}

func ZeroMoney(currency Currency) Money { return Money{amount: 0, currency: currency} }

func (m Money) Amount() int64       { return m.amount }
func (m Money) Currency() Currency  { return m.currency }
func (m Money) IsZero() bool        { return m.amount == 0 }

func (m Money) Add(other Money) (Money, error) {
	if m.currency != other.currency {
		return Money{}, domain.NewDomainError(fmt.Sprintf(
			"Müxtəlif valyutalar: %s vs %s", m.currency, other.currency))
	}
	return Money{amount: m.amount + other.amount, currency: m.currency}, nil
}

func (m Money) Multiply(factor int) Money {
	return Money{amount: m.amount * int64(factor), currency: m.currency}
}

func (m Money) String() string {
	return fmt.Sprintf("%d.%02d %s", m.amount/100, m.amount%100, m.currency)
}

// === STOCK VALUE OBJECT ===
const LowStockThreshold = 5

type Stock struct {
	quantity int
}

func NewStock(quantity int) (Stock, error) {
	if quantity < 0 {
		return Stock{}, domain.NewDomainError("Stok mənfi ola bilməz")
	}
	return Stock{quantity: quantity}, nil
}

func MustStock(quantity int) Stock {
	s, _ := NewStock(quantity)
	return s
}

func (s Stock) Quantity() int   { return s.quantity }
func (s Stock) IsOutOfStock() bool { return s.quantity == 0 }
func (s Stock) IsLow() bool { return s.quantity > 0 && s.quantity <= LowStockThreshold }

func (s Stock) Decrease(amount int) (Stock, error) {
	if amount < 0 {
		return Stock{}, domain.NewDomainError("Azalma müsbət olmalıdır")
	}
	if s.quantity < amount {
		return Stock{}, domain.NewDomainError(fmt.Sprintf(
			"Yetərli stok yoxdur: mövcud %d, tələb %d", s.quantity, amount))
	}
	return Stock{quantity: s.quantity - amount}, nil
}

func (s Stock) Increase(amount int) (Stock, error) {
	if amount < 0 {
		return Stock{}, domain.NewDomainError("Artma müsbət olmalıdır")
	}
	return Stock{quantity: s.quantity + amount}, nil
}

// === PRODUCT NAME ===
type ProductName struct {
	value string
}

func NewProductName(s string) (ProductName, error) {
	if s == "" {
		return ProductName{}, domain.NewDomainError("Məhsul adı boş ola bilməz")
	}
	if len(s) > 255 {
		return ProductName{}, domain.NewDomainError("Məhsul adı 255 simvoldan uzun ola bilməz")
	}
	return ProductName{value: s}, nil
}

func (p ProductName) Value() string  { return p.value }
func (p ProductName) String() string { return p.value }
