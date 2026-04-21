package middleware

import (
	"context"

	"github.com/orkhan/ecommerce/internal/shared/application/bus"
	"gorm.io/gorm"
)

// TransactionMiddleware — order=40
//
// Bütün command-i bir DB tranzaksiyaya bürüyür.
//
// Laravel: TransactionMiddleware.php (DB::transaction(...))
// Spring: TransactionMiddleware.java (PlatformTransactionManager)
// Go: gorm.DB.Transaction(func(tx) { ... }) — context-ə tx əlavə edir
//
// QEYD: 4 ayrı DB var — burada default User DB-də başlayır.
// Multi-DB transaction üçün ya 2PC, ya hər context öz outbox-u (tövsiyə).
type TransactionMiddleware struct {
	db *gorm.DB
}

const TxContextKey = "gorm_tx"

func NewTransactionMiddleware(db *gorm.DB) *TransactionMiddleware {
	return &TransactionMiddleware{db: db}
}

func (m *TransactionMiddleware) Handle(ctx context.Context, cmd any, next bus.Next) (any, error) {
	var result any
	err := m.db.Transaction(func(tx *gorm.DB) error {
		txCtx := context.WithValue(ctx, TxContextKey, tx)
		var err error
		result, err = next(txCtx, cmd)
		return err
	})
	return result, err
}

// TxFromContext — handler-də cari transaction-ı əldə etmək üçün
func TxFromContext(ctx context.Context, fallback *gorm.DB) *gorm.DB {
	if tx, ok := ctx.Value(TxContextKey).(*gorm.DB); ok {
		return tx
	}
	return fallback
}
