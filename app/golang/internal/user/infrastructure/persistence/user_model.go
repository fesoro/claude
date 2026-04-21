// Package persistence — GORM modeli + Repository implementation
//
// Laravel: src/User/Infrastructure/Models/UserModel.php (Eloquent)
// Spring: user/infrastructure/persistence/UserEntity.java (JPA @Entity)
// Go: GORM struct + tag-lar
package persistence

import (
	"encoding/json"
	"errors"
	"time"

	"github.com/google/uuid"
	"github.com/orkhan/ecommerce/internal/user/domain"
	"gorm.io/gorm"
)

// UserModel — DB representation (domain User-dən AYRIDIR — Persistence Ignorance)
type UserModel struct {
	ID                   uint64    `gorm:"primaryKey;autoIncrement"`
	UUID                 uuid.UUID `gorm:"type:char(36);uniqueIndex;not null"`
	Name                 string    `gorm:"size:255;not null"`
	Email                string    `gorm:"size:255;uniqueIndex;not null"`
	EmailVerifiedAt      *time.Time
	Password             string `gorm:"size:255;not null"`
	RememberToken        string `gorm:"size:100"`
	TwoFactorEnabled     bool   `gorm:"not null;default:false"`
	TwoFactorSecret      string `gorm:"size:255"`
	TwoFactorBackupCodes string `gorm:"type:json"`
	Version              int64  `gorm:"not null;default:0"`
	TenantID             *uuid.UUID `gorm:"type:char(36);index"`
	CreatedAt            time.Time
	UpdatedAt            time.Time
}

func (UserModel) TableName() string { return "users" }

// Repository — domain interface-ni implement edir
type Repository struct {
	db *gorm.DB
}

func NewRepository(db *gorm.DB) *Repository {
	return &Repository{db: db}
}

func (r *Repository) Save(user *domain.User) error {
	model, err := toModel(user)
	if err != nil {
		return err
	}
	// Upsert based on uuid
	var existing UserModel
	err = r.db.Where("uuid = ?", model.UUID).First(&existing).Error
	if errors.Is(err, gorm.ErrRecordNotFound) {
		return r.db.Create(model).Error
	}
	if err != nil {
		return err
	}
	model.ID = existing.ID
	model.CreatedAt = existing.CreatedAt
	return r.db.Save(model).Error
}

func (r *Repository) FindByID(id domain.UserID) (*domain.User, error) {
	var model UserModel
	err := r.db.Where("uuid = ?", id.UUID()).First(&model).Error
	if err != nil {
		if errors.Is(err, gorm.ErrRecordNotFound) {
			return nil, nil
		}
		return nil, err
	}
	return toDomain(&model)
}

func (r *Repository) FindByEmail(email domain.Email) (*domain.User, error) {
	var model UserModel
	err := r.db.Where("email = ?", email.Value()).First(&model).Error
	if err != nil {
		if errors.Is(err, gorm.ErrRecordNotFound) {
			return nil, nil
		}
		return nil, err
	}
	return toDomain(&model)
}

func (r *Repository) ExistsByEmail(email domain.Email) (bool, error) {
	var count int64
	err := r.db.Model(&UserModel{}).Where("email = ?", email.Value()).Count(&count).Error
	return count > 0, err
}

// === Mappers (Domain ↔ Model) ===

func toModel(u *domain.User) (*UserModel, error) {
	var codesJSON string
	if codes := u.BackupCodes(); codes != nil {
		b, err := json.Marshal(codes)
		if err != nil {
			return nil, err
		}
		codesJSON = string(b)
	}
	return &UserModel{
		UUID:                 u.ID().UUID(),
		Name:                 u.Name(),
		Email:                u.Email().Value(),
		Password:             u.Password().Hash(),
		TwoFactorEnabled:     u.TwoFactorEnabled(),
		TwoFactorSecret:      u.TwoFactorSecret(),
		TwoFactorBackupCodes: codesJSON,
	}, nil
}

func toDomain(m *UserModel) (*domain.User, error) {
	email, err := domain.NewEmail(m.Email)
	if err != nil {
		return nil, err
	}
	var codes []string
	if m.TwoFactorBackupCodes != "" {
		_ = json.Unmarshal([]byte(m.TwoFactorBackupCodes), &codes)
	}
	return domain.Reconstitute(
		domain.UserID(m.UUID), m.Name, email,
		domain.NewPasswordFromHash(m.Password),
		m.TwoFactorEnabled, m.TwoFactorSecret, codes,
	), nil
}
