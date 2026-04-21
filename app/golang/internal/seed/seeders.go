// Package seed — test data seeders
//
// Laravel: database/seeders/*.php
// Spring: @CommandLineRunner @Profile("seed")
// Go: CLI subcommand (go run ./cmd/cli seed)
package seed

import (
	"fmt"
	"log/slog"

	productDomain "github.com/orkhan/ecommerce/internal/product/domain"
	"github.com/orkhan/ecommerce/internal/shared/infrastructure/database"
	userDomain "github.com/orkhan/ecommerce/internal/user/domain"
	userPersistence "github.com/orkhan/ecommerce/internal/user/infrastructure/persistence"
	productPersistence "github.com/orkhan/ecommerce/internal/product/infrastructure/persistence"
)

// RunAll — bütün seeder-ləri ardıcıl icra edir (DatabaseSeeder.php analoq)
func RunAll(dbs *database.Databases) error {
	if err := seedUsers(dbs); err != nil {
		return fmt.Errorf("users: %w", err)
	}
	if err := seedProducts(dbs); err != nil {
		return fmt.Errorf("products: %w", err)
	}
	slog.Info("Bütün seeder-lər uğurla işlədi")
	return nil
}

func seedUsers(dbs *database.Databases) error {
	repo := userPersistence.NewRepository(dbs.User)

	email, _ := userDomain.NewEmail("admin@ecommerce.az")
	exists, _ := repo.ExistsByEmail(email)
	if exists {
		return nil
	}

	pw, _ := userDomain.NewPasswordFromPlaintext("admin12345")
	admin, _ := userDomain.Register("Admin", email, pw)
	if err := repo.Save(admin); err != nil {
		return err
	}

	for i := 1; i <= 10; i++ {
		ue, _ := userDomain.NewEmail(fmt.Sprintf("user%d@example.com", i))
		up, _ := userDomain.NewPasswordFromPlaintext("password123")
		u, _ := userDomain.Register(fmt.Sprintf("User %d", i), ue, up)
		_ = repo.Save(u)
	}
	slog.Info("UserSeeder: 1 admin + 10 user")
	return nil
}

func seedProducts(dbs *database.Databases) error {
	repo := productPersistence.NewRepository(dbs.Product)

	for i := 1; i <= 14; i++ {
		name, _ := productDomain.NewProductName(fmt.Sprintf("Məhsul %d", i))
		price, _ := productDomain.NewMoney(int64(1000*i), productDomain.AZN)
		stock, _ := productDomain.NewStock(100)
		p := productDomain.Create(name, fmt.Sprintf("Açıqlama %d", i), price, stock)
		_ = repo.Save(p)
	}
	slog.Info("ProductSeeder: 14 məhsul")
	return nil
}
