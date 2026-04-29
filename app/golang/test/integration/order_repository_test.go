//go:build integration
// +build integration

// Package integration — real MySQL container ilə Order repository testi
//
// Laravel: tests/Integration/EloquentOrderRepositoryTest.php
// Spring: JpaOrderRepositoryIT.java (Testcontainers MySQL)
// Go: testcontainers-go MySQL module
//
// Run: go test -tags=integration ./test/integration/...
package integration

import (
	"context"
	"fmt"
	"testing"

	orderDomain "github.com/orkhan/ecommerce/internal/order/domain"
	"github.com/orkhan/ecommerce/internal/order/infrastructure/persistence"
	productDomain "github.com/orkhan/ecommerce/internal/product/domain"
	"github.com/stretchr/testify/assert"
	"github.com/stretchr/testify/require"
	tcmysql "github.com/testcontainers/testcontainers-go/modules/mysql"
	"github.com/google/uuid"
	gormmysql "gorm.io/driver/mysql"
	"gorm.io/gorm"
)

func setupOrderDB(t *testing.T) *persistence.Repository {
	t.Helper()
	ctx := context.Background()

	mysqlContainer, err := tcmysql.Run(ctx,
		"mysql:8.0",
		tcmysql.WithDatabase("order_db"),
		tcmysql.WithUsername("test"),
		tcmysql.WithPassword("test"),
	)
	require.NoError(t, err)
	t.Cleanup(func() { _ = mysqlContainer.Terminate(ctx) })

	host, _ := mysqlContainer.Host(ctx)
	port, _ := mysqlContainer.MappedPort(ctx, "3306")
	dsn := fmt.Sprintf("test:test@tcp(%s:%s)/order_db?parseTime=true", host, port.Port())

	db, err := gorm.Open(gormmysql.Open(dsn), &gorm.Config{})
	require.NoError(t, err)
	require.NoError(t, db.AutoMigrate(&persistence.OrderModel{}, &persistence.OrderItemModel{}))

	return persistence.NewRepository(db)
}

func makeTestOrder(userID uuid.UUID) (*orderDomain.Order, error) {
	price, err := productDomain.NewMoney(1500, productDomain.AZN)
	if err != nil {
		return nil, err
	}
	item, err := orderDomain.NewOrderItem(uuid.New(), "Test Məhsul", price, 2)
	if err != nil {
		return nil, err
	}
	address, err := orderDomain.NewAddress("İstiqlaliyyət 5", "Bakı", "AZ1000", "AZ")
	if err != nil {
		return nil, err
	}
	return orderDomain.Create(userID, []orderDomain.OrderItem{item}, address, productDomain.AZN)
}

// Yoxlananlar:
// - Order save + findById round-trip
// - Status dəyişikliyi persist olunur
// - Ləğv olunmuş sifariş findById ilə gətirilir
// - findByUserId siyahı qaytarır

func TestOrderRepository_SaveAndRetrieve(t *testing.T) {
	repo := setupOrderDB(t)

	order, err := makeTestOrder(uuid.New())
	require.NoError(t, err)
	require.NoError(t, repo.Save(order))

	retrieved, err := repo.FindByID(order.ID())
	require.NoError(t, err)
	require.NotNil(t, retrieved)

	assert.Equal(t, order.ID(), retrieved.ID())
	assert.Equal(t, orderDomain.OrderStatusPending, retrieved.Status())
	assert.Len(t, retrieved.Items(), 1)
	assert.Equal(t, int64(3000), retrieved.TotalAmount().Amount()) // 2 × 1500
}

func TestOrderRepository_PersistStatusChange(t *testing.T) {
	repo := setupOrderDB(t)

	order, err := makeTestOrder(uuid.New())
	require.NoError(t, err)
	require.NoError(t, repo.Save(order))

	require.NoError(t, order.Confirm())
	require.NoError(t, repo.Save(order))

	retrieved, err := repo.FindByID(order.ID())
	require.NoError(t, err)
	assert.Equal(t, orderDomain.OrderStatusConfirmed, retrieved.Status())
}

func TestOrderRepository_CancelOrder(t *testing.T) {
	repo := setupOrderDB(t)

	order, err := makeTestOrder(uuid.New())
	require.NoError(t, err)
	require.NoError(t, repo.Save(order))

	require.NoError(t, order.Cancel("Müştəri ləğv etdi"))
	require.NoError(t, repo.Save(order))

	retrieved, err := repo.FindByID(order.ID())
	require.NoError(t, err)
	assert.Equal(t, orderDomain.OrderStatusCancelled, retrieved.Status())
}

func TestOrderRepository_ReturnNilForUnknownID(t *testing.T) {
	repo := setupOrderDB(t)

	found, err := repo.FindByID(orderDomain.OrderID(uuid.New()))
	require.NoError(t, err)
	assert.Nil(t, found)
}

func TestOrderRepository_FindByUserID(t *testing.T) {
	repo := setupOrderDB(t)

	userID := uuid.New()
	order1, _ := makeTestOrder(userID)
	order2, _ := makeTestOrder(userID)
	require.NoError(t, repo.Save(order1))
	require.NoError(t, repo.Save(order2))

	// Başqa user-in sifarişi — filter-ə girməməlidir
	other, _ := makeTestOrder(uuid.New())
	require.NoError(t, repo.Save(other))

	orders, err := repo.FindByUserID(userID)
	require.NoError(t, err)
	assert.Len(t, orders, 2)
}
