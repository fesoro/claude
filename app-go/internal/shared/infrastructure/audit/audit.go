// Package audit — request-ləri audit_logs cədvəlinə yazır
//
// Laravel: AuditMiddleware + AuditService
// Spring: AuditInterceptor + AuditService (@Async)
// Go: Gin middleware + goroutine (async)
package audit

import (
	"context"
	"strings"
	"time"

	"github.com/google/uuid"
	"gorm.io/gorm"
)

type AuditLog struct {
	ID             uint64     `gorm:"primaryKey;autoIncrement"`
	UserID         *uuid.UUID `gorm:"type:char(36);index"`
	CorrelationID  string     `gorm:"size:64;index"`
	Action         string     `gorm:"size:64;not null"`
	EntityType     string     `gorm:"size:64;index"`
	EntityID       string     `gorm:"size:64"`
	Method         string     `gorm:"size:8;not null"`
	URI            string     `gorm:"size:512;not null"`
	IPAddress      string     `gorm:"size:45"`
	UserAgent      string     `gorm:"size:512"`
	RequestPayload string     `gorm:"type:json"`
	ResponseStatus int
	Metadata       string `gorm:"type:json"`
	CreatedAt      time.Time
}

func (AuditLog) TableName() string { return "audit_logs" }

type Service struct {
	db *gorm.DB
}

func New(db *gorm.DB) *Service {
	return &Service{db: db}
}

// Record — async, performansa təsir etməsin deyə goroutine-də işləyir
func (s *Service) Record(ctx context.Context, entry AuditLog) {
	go func() {
		_ = s.db.WithContext(context.Background()).Create(&entry).Error
	}()
}

// EntityFromURI — /api/orders/123 → "orders"
func EntityFromURI(uri string) string {
	parts := strings.Split(uri, "/")
	if len(parts) >= 3 {
		return parts[2]
	}
	return ""
}

// ActionFromMethod — HTTP method → CRUD action
func ActionFromMethod(method string) string {
	switch method {
	case "POST":
		return "CREATE"
	case "PUT", "PATCH":
		return "UPDATE"
	case "DELETE":
		return "DELETE"
	}
	return method
}
