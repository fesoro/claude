// Package database — 4 ayrı GORM connection (Database-per-Bounded-Context)
//
// Laravel: config/database.php-də 4 connection
// Spring: 4 @Configuration class (UserDataSourceConfig, ProductDataSourceConfig, ...)
// Go: 1 struct, 4 *gorm.DB sahəsi — explicit, manual
package database

import (
	"fmt"
	"log/slog"
	"time"

	"github.com/golang-migrate/migrate/v4"
	"github.com/golang-migrate/migrate/v4/database/mysql"
	_ "github.com/golang-migrate/migrate/v4/source/file"
	"github.com/orkhan/ecommerce/internal/config"
	gormmysql "gorm.io/driver/mysql"
	"gorm.io/gorm"
	"gorm.io/gorm/logger"
)

// Databases — bütün 4 connection-ı bir yerdə saxlayır.
// Laravel: DB::connection('user_db')->...
// Spring: @Qualifier("userDataSource")
// Go: db.User, db.Product, db.Order, db.Payment
type Databases struct {
	User    *gorm.DB
	Product *gorm.DB
	Order   *gorm.DB
	Payment *gorm.DB
}

// Open — bütün 4 DB-ni paralel açır
func Open(cfg config.DatabaseConfig) (*Databases, error) {
	user, err := openOne(cfg.User)
	if err != nil {
		return nil, fmt.Errorf("user_db: %w", err)
	}
	product, err := openOne(cfg.Product)
	if err != nil {
		return nil, fmt.Errorf("product_db: %w", err)
	}
	order, err := openOne(cfg.Order)
	if err != nil {
		return nil, fmt.Errorf("order_db: %w", err)
	}
	payment, err := openOne(cfg.Payment)
	if err != nil {
		return nil, fmt.Errorf("payment_db: %w", err)
	}
	return &Databases{User: user, Product: product, Order: order, Payment: payment}, nil
}

func openOne(cfg config.DBConn) (*gorm.DB, error) {
	dsn := fmt.Sprintf("%s:%s@tcp(%s:%d)/%s?charset=utf8mb4&parseTime=True&loc=Local",
		cfg.Username, cfg.Password, cfg.Host, cfg.Port, cfg.Name)

	gormDB, err := gorm.Open(gormmysql.Open(dsn), &gorm.Config{
		Logger: logger.Default.LogMode(logger.Warn),
	})
	if err != nil {
		return nil, err
	}

	sqlDB, err := gormDB.DB()
	if err != nil {
		return nil, err
	}
	if cfg.MaxOpenConns > 0 {
		sqlDB.SetMaxOpenConns(cfg.MaxOpenConns)
	}
	if cfg.MaxIdleConns > 0 {
		sqlDB.SetMaxIdleConns(cfg.MaxIdleConns)
	}
	sqlDB.SetConnMaxLifetime(time.Hour)

	return gormDB, nil
}

// MigrateAll — bütün 4 DB üçün migration tətbiq edir
// Laravel: php artisan migrate (hər connection üçün ayrıca)
// Spring: 4 ayrıca Flyway @Bean
// Go: 4 dəfə migrate.Up()
func (d *Databases) MigrateAll(migrationsRoot string) error {
	mappings := map[string]*gorm.DB{
		"user":    d.User,
		"product": d.Product,
		"order":   d.Order,
		"payment": d.Payment,
	}
	for name, db := range mappings {
		if err := runMigrations(db, fmt.Sprintf("%s/%s", migrationsRoot, name)); err != nil {
			return fmt.Errorf("%s migration: %w", name, err)
		}
		slog.Info("migration tətbiq edildi", "db", name)
	}
	return nil
}

func runMigrations(gormDB *gorm.DB, sourcePath string) error {
	sqlDB, err := gormDB.DB()
	if err != nil {
		return err
	}
	driver, err := mysql.WithInstance(sqlDB, &mysql.Config{})
	if err != nil {
		return err
	}
	m, err := migrate.NewWithDatabaseInstance("file://"+sourcePath, "mysql", driver)
	if err != nil {
		return err
	}
	if err := m.Up(); err != nil && err != migrate.ErrNoChange {
		return err
	}
	return nil
}

// Close — bütün connection-ları bağla
func (d *Databases) Close() {
	for _, db := range []*gorm.DB{d.User, d.Product, d.Order, d.Payment} {
		if db == nil {
			continue
		}
		sqlDB, err := db.DB()
		if err == nil {
			_ = sqlDB.Close()
		}
	}
}
