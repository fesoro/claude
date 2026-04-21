package domain

import (
	"github.com/google/uuid"
	productDomain "github.com/orkhan/ecommerce/internal/product/domain"
	sharedDomain "github.com/orkhan/ecommerce/internal/shared/domain"
)

type OrderID uuid.UUID

func GenerateOrderID() OrderID    { return OrderID(uuid.New()) }
func (o OrderID) UUID() uuid.UUID { return uuid.UUID(o) }
func (o OrderID) String() string  { return uuid.UUID(o).String() }

// === ADDRESS (embedded VO) ===
type Address struct {
	Street  string
	City    string
	Zip     string
	Country string
}

func NewAddress(street, city, zip, country string) (Address, error) {
	if street == "" || city == "" || zip == "" || country == "" {
		return Address{}, sharedDomain.NewDomainError("Address sahələri boş ola bilməz")
	}
	return Address{Street: street, City: city, Zip: zip, Country: country}, nil
}

func (a Address) Summary() string {
	return a.Street + ", " + a.City + " " + a.Zip + ", " + a.Country
}

// === ORDER ITEM (Entity within Order aggregate) ===
type OrderItem struct {
	ProductID   uuid.UUID
	ProductName string
	UnitPrice   productDomain.Money
	Quantity    int
}

func NewOrderItem(productID uuid.UUID, name string, unitPrice productDomain.Money, qty int) (OrderItem, error) {
	if productID == uuid.Nil {
		return OrderItem{}, sharedDomain.NewDomainError("ProductID boş")
	}
	if name == "" {
		return OrderItem{}, sharedDomain.NewDomainError("Məhsul adı boş")
	}
	if qty <= 0 {
		return OrderItem{}, sharedDomain.NewDomainError("Miqdar müsbət olmalıdır")
	}
	return OrderItem{ProductID: productID, ProductName: name, UnitPrice: unitPrice, Quantity: qty}, nil
}

func (i OrderItem) LineTotal() productDomain.Money {
	return i.UnitPrice.Multiply(i.Quantity)
}
