//go:build integration
// +build integration

// Package integration — real MySQL container ilə test
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

func TestProductRepository_SaveAndFind(t *testing.T) {
	ctx := context.Background()

	// 1. MySQL container start
	mysqlContainer, err := tcmysql.Run(ctx,
		"mysql:8.0",
		tcmysql.WithDatabase("product_db"),
		tcmysql.WithUsername("test"),
		tcmysql.WithPassword("test"),
	)
	require.NoError(t, err)
	defer mysqlContainer.Terminate(ctx)

	// 2. Connection string
	host, _ := mysqlContainer.Host(ctx)
	port, _ := mysqlContainer.MappedPort(ctx, "3306")
	dsn := fmt.Sprintf("test:test@tcp(%s:%s)/product_db?parseTime=true", host, port.Port())

	db, err := gorm.Open(gormmysql.Open(dsn), &gorm.Config{})
	require.NoError(t, err)

	// 3. Schema yarat (real-da migration runner)
	require.NoError(t, db.AutoMigrate(&persistence.ProductModel{}))

	// 4. Test
	repo := persistence.NewRepository(db)

	name, _ := domain.NewProductName("Test Məhsul")
	price, _ := domain.NewMoney(2599, domain.AZN)
	stock, _ := domain.NewStock(50)
	product := domain.Create(name, "Açıqlama", price, stock)

	require.NoError(t, repo.Save(product))

	found, err := repo.FindByID(product.ID())
	require.NoError(t, err)
	require.NotNil(t, found)
	assert.Equal(t, "Test Məhsul", found.Name().Value())
	assert.Equal(t, int64(2599), found.Price().Amount())
	assert.Equal(t, 50, found.Stock().Quantity())
}
