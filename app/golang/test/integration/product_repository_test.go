//go:build integration
// +build integration

// Package integration — real MySQL container ilə Product repository testi
//
// Laravel: tests/Integration/EloquentProductRepositoryTest.php
// Spring: JpaProductRepositoryIT.java (Testcontainers MySQL)
// Go: testcontainers-go MySQL module
//
// Run: go test -tags=integration ./test/integration/...
package integration

import (
	"context"
	"fmt"
	"testing"

	"github.com/orkhan/ecommerce/internal/product/domain"
	"github.com/orkhan/ecommerce/internal/product/infrastructure/persistence"
	"github.com/stretchr/testify/assert"
	"github.com/stretchr/testify/require"
	tcmysql "github.com/testcontainers/testcontainers-go/modules/mysql"
	gormmysql "gorm.io/driver/mysql"
	"gorm.io/gorm"
)

func setupProductDB(t *testing.T) *persistence.Repository {
	t.Helper()
	ctx := context.Background()

	mysqlContainer, err := tcmysql.Run(ctx,
		"mysql:8.0",
		tcmysql.WithDatabase("product_db"),
		tcmysql.WithUsername("test"),
		tcmysql.WithPassword("test"),
	)
	require.NoError(t, err)
	t.Cleanup(func() { _ = mysqlContainer.Terminate(ctx) })

	host, _ := mysqlContainer.Host(ctx)
	port, _ := mysqlContainer.MappedPort(ctx, "3306")
	dsn := fmt.Sprintf("test:test@tcp(%s:%s)/product_db?parseTime=true", host, port.Port())

	db, err := gorm.Open(gormmysql.Open(dsn), &gorm.Config{})
	require.NoError(t, err)
	require.NoError(t, db.AutoMigrate(&persistence.ProductModel{}))

	return persistence.NewRepository(db)
}

func makeTestProduct() *domain.Product {
	name, _ := domain.NewProductName("Test Məhsul")
	price, _ := domain.NewMoney(2599, domain.AZN)
	stock, _ := domain.NewStock(50)
	return domain.Create(name, "Açıqlama", price, stock)
}

// Yoxlananlar:
// - Product save + findById round-trip
// - Stok azaldılması persist olunur
// - FindAll siyahı qaytarır
// - Mövcud olmayan ID üçün nil qaytarır

func TestProductRepository_SaveAndRetrieve(t *testing.T) {
	repo := setupProductDB(t)

	product := makeTestProduct()
	require.NoError(t, repo.Save(product))

	found, err := repo.FindByID(product.ID())
	require.NoError(t, err)
	require.NotNil(t, found)

	assert.Equal(t, "Test Məhsul", found.Name().Value())
	assert.Equal(t, int64(2599), found.Price().Amount())
	assert.Equal(t, domain.AZN, found.Price().Currency())
	assert.Equal(t, 50, found.Stock().Quantity())
}

func TestProductRepository_PersistStockDecrease(t *testing.T) {
	repo := setupProductDB(t)

	product := makeTestProduct()
	require.NoError(t, repo.Save(product))

	require.NoError(t, product.DecreaseStock(10))
	require.NoError(t, repo.Save(product))

	updated, err := repo.FindByID(product.ID())
	require.NoError(t, err)
	assert.Equal(t, 40, updated.Stock().Quantity())
}

func TestProductRepository_ReturnNilForUnknownID(t *testing.T) {
	repo := setupProductDB(t)

	name, _ := domain.NewProductName("Tmp")
	price, _ := domain.NewMoney(100, domain.AZN)
	stock, _ := domain.NewStock(1)
	phantom := domain.Create(name, "", price, stock)

	found, err := repo.FindByID(phantom.ID())
	require.NoError(t, err)
	assert.Nil(t, found)
}

func TestProductRepository_ListAll(t *testing.T) {
	repo := setupProductDB(t)

	for i := 0; i < 3; i++ {
		require.NoError(t, repo.Save(makeTestProduct()))
	}

	products, err := repo.FindAll(0, 10)
	require.NoError(t, err)
	assert.Len(t, products, 3)
}
