package application

import (
	"context"
	"crypto/rand"
	"encoding/base64"
	"errors"
	"time"

	"github.com/orkhan/ecommerce/internal/notification/infrastructure/channel"
	sharedDomain "github.com/orkhan/ecommerce/internal/shared/domain"
	userDomain "github.com/orkhan/ecommerce/internal/user/domain"
	"golang.org/x/crypto/bcrypt"
	"gorm.io/gorm"
)

// PasswordResetToken — DB entity (1 saatlıq token)
type PasswordResetToken struct {
	Email     string `gorm:"primaryKey;size:255"`
	Token     string `gorm:"size:255;not null"`
	CreatedAt time.Time
}

func (PasswordResetToken) TableName() string { return "password_reset_tokens" }

// PasswordResetService — forgot/reset flow
//
// Laravel: AuthController::forgotPassword + resetPassword
// Spring: PasswordResetService.java
type PasswordResetService struct {
	userRepo userDomain.Repository
	db       *gorm.DB
	email    *channel.EmailChannel
}

func NewPasswordResetService(userRepo userDomain.Repository, db *gorm.DB, email *channel.EmailChannel) *PasswordResetService {
	return &PasswordResetService{userRepo: userRepo, db: db, email: email}
}

const passwordResetTokenTTL = time.Hour
const passwordResetTpl = `<h2>Salam, {{.UserName}}!</h2>
<p>Şifrənizi bərpa etmək üçün aşağıdakı linkə klikləyin:</p>
<p><a href="{{.ResetURL}}">{{.ResetURL}}</a></p>
<p>Link 1 saat ərzində etibarlıdır.</p>`

// RequestReset — yeni token yaradır + email göndərir
func (s *PasswordResetService) RequestReset(ctx context.Context, email string) error {
	addr, err := userDomain.NewEmail(email)
	if err != nil {
		return err
	}
	user, err := s.userRepo.FindByEmail(addr)
	if err != nil {
		return err
	}
	if user == nil {
		return sharedDomain.NewEntityNotFoundError("User", email)
	}

	rawToken, err := generateToken(32)
	if err != nil {
		return err
	}
	hashed, err := bcrypt.GenerateFromPassword([]byte(rawToken), 10)
	if err != nil {
		return err
	}

	// Mövcud tokenı silib yenisini yaz (upsert)
	s.db.Where("email = ?", email).Delete(&PasswordResetToken{})
	s.db.Create(&PasswordResetToken{
		Email: email, Token: string(hashed), CreatedAt: time.Now(),
	})

	resetURL := "https://app.ecommerce.az/reset-password?email=" + email + "&token=" + rawToken
	if s.email != nil {
		_ = s.email.Send(email, "Şifrə bərpası", passwordResetTpl, map[string]any{
			"UserName": user.Name(), "ResetURL": resetURL,
		})
	}
	return nil
}

// ResetPassword — token yoxlayır, şifrəni yeniləyir
func (s *PasswordResetService) ResetPassword(ctx context.Context, email, token, newPassword string) error {
	var entity PasswordResetToken
	err := s.db.Where("email = ?", email).First(&entity).Error
	if errors.Is(err, gorm.ErrRecordNotFound) {
		return sharedDomain.NewDomainError("Token tapılmadı")
	}
	if err != nil {
		return err
	}
	if time.Since(entity.CreatedAt) > passwordResetTokenTTL {
		return sharedDomain.NewDomainError("Token vaxtı bitib (1 saat keçib)")
	}
	if bcrypt.CompareHashAndPassword([]byte(entity.Token), []byte(token)) != nil {
		return sharedDomain.NewDomainError("Token yanlışdır")
	}

	addr, _ := userDomain.NewEmail(email)
	user, err := s.userRepo.FindByEmail(addr)
	if err != nil || user == nil {
		return sharedDomain.NewEntityNotFoundError("User", email)
	}
	pw, err := userDomain.NewPasswordFromPlaintext(newPassword)
	if err != nil {
		return err
	}
	user.ChangePassword(pw)
	if err := s.userRepo.Save(user); err != nil {
		return err
	}
	s.db.Where("email = ?", email).Delete(&PasswordResetToken{})
	return nil
}

func generateToken(n int) (string, error) {
	b := make([]byte, n)
	if _, err := rand.Read(b); err != nil {
		return "", err
	}
	return base64.RawURLEncoding.EncodeToString(b), nil
}
