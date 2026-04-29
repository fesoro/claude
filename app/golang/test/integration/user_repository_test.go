//go:build integration
// +build integration

// Package integration — real MySQL container ilə User repository testi
//
// Laravel: tests/Integration/EloquentUserRepositoryTest.php
// Spring: JpaUserRepositoryIT.java (Testcontainers MySQL)
// Go: testcontainers-go MySQL module
//
// Run: go test -tags=integration ./test/integration/...
package integration

import (
	"context"
	"fmt"
	"testing"

	"github.com/orkhan/ecommerce/internal/user/domain"
	"github.com/orkhan/ecommerce/internal/user/infrastructure/persistence"
	"github.com/stretchr/testify/assert"
	"github.com/stretchr/testify/require"
	tcmysql "github.com/testcontainers/testcontainers-go/modules/mysql"
	gormmysql "gorm.io/driver/mysql"
	"gorm.io/gorm"
)

func setupUserDB(t *testing.T) *persistence.Repository {
	t.Helper()
	ctx := context.Background()

	mysqlContainer, err := tcmysql.Run(ctx,
		"mysql:8.0",
		tcmysql.WithDatabase("user_db"),
		tcmysql.WithUsername("test"),
		tcmysql.WithPassword("test"),
	)
	require.NoError(t, err)
	t.Cleanup(func() { _ = mysqlContainer.Terminate(ctx) })

	host, _ := mysqlContainer.Host(ctx)
	port, _ := mysqlContainer.MappedPort(ctx, "3306")
	dsn := fmt.Sprintf("test:test@tcp(%s:%s)/user_db?parseTime=true", host, port.Port())

	db, err := gorm.Open(gormmysql.Open(dsn), &gorm.Config{})
	require.NoError(t, err)
	require.NoError(t, db.AutoMigrate(&persistence.UserModel{}))

	return persistence.NewRepository(db)
}

// Yoxlananlar:
// - User save → findByEmail round-trip
// - Password hash persist olunur (plaintext saxlanmır)
// - verifyPassword düzgün işləyir
// - 2FA flag-ı persist olunur

func TestUserRepository_SaveAndFindByEmail(t *testing.T) {
	repo := setupUserDB(t)

	email, err := domain.NewEmail("testuser@example.com")
	require.NoError(t, err)
	password, err := domain.NewPasswordFromPlaintext("password123")
	require.NoError(t, err)

	user, err := domain.Register("Test İstifadəçi", email, password)
	require.NoError(t, err)
	require.NoError(t, repo.Save(user))

	found, err := repo.FindByEmail(email)
	require.NoError(t, err)
	require.NotNil(t, found)

	assert.Equal(t, "testuser@example.com", found.Email().Value())
	assert.Equal(t, "Test İstifadəçi", found.Name())
}

func TestUserRepository_HashPasswordOnSave(t *testing.T) {
	repo := setupUserDB(t)

	email, _ := domain.NewEmail("hashtest@example.com")
	password, _ := domain.NewPasswordFromPlaintext("mySecretPassword")
	user, _ := domain.Register("Hash Test", email, password)
	require.NoError(t, repo.Save(user))

	retrieved, err := repo.FindByEmail(email)
	require.NoError(t, err)
	require.NotNil(t, retrieved)

	// Plaintext saxlanmamalıdır
	assert.NotEqual(t, "mySecretPassword", retrieved.Password().Hash())
	// Amma doğrulama işləməlidir
	assert.True(t, retrieved.VerifyPassword("mySecretPassword"))
	assert.False(t, retrieved.VerifyPassword("wrongPassword"))
}

func TestUserRepository_FindByIDRoundTrip(t *testing.T) {
	repo := setupUserDB(t)

	email, _ := domain.NewEmail("byid@example.com")
	password, _ := domain.NewPasswordFromPlaintext("password123")
	user, _ := domain.Register("ByID Test", email, password)
	require.NoError(t, repo.Save(user))

	found, err := repo.FindByID(user.ID())
	require.NoError(t, err)
	require.NotNil(t, found)
	assert.Equal(t, user.ID(), found.ID())
}

func TestUserRepository_ReturnNilForUnknownEmail(t *testing.T) {
	repo := setupUserDB(t)

	email, _ := domain.NewEmail("nobody@example.com")
	found, err := repo.FindByEmail(email)
	require.NoError(t, err)
	assert.Nil(t, found)
}

func TestUserRepository_PersistTwoFactor(t *testing.T) {
	repo := setupUserDB(t)

	email, _ := domain.NewEmail("twofa@example.com")
	password, _ := domain.NewPasswordFromPlaintext("password123")
	user, _ := domain.Register("2FA Test", email, password)
	require.NoError(t, repo.Save(user))

	loaded, _ := repo.FindByEmail(email)
	assert.False(t, loaded.TwoFactorEnabled())

	err := user.EnableTwoFactor("JBSWY3DPEHPK3PXP", []string{"BACKUP1", "BACKUP2"})
	require.NoError(t, err)
	require.NoError(t, repo.Save(user))

	updated, _ := repo.FindByEmail(email)
	assert.True(t, updated.TwoFactorEnabled())
	assert.Equal(t, "JBSWY3DPEHPK3PXP", updated.TwoFactorSecret())
}
