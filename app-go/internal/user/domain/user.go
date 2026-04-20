package domain

import (
	"github.com/orkhan/ecommerce/internal/shared/domain"
)

// User — Aggregate Root (saf POJO, GORM-dan asılı deyil)
//
// Laravel: src/User/Domain/Entities/User.php
// Spring: user/domain/User.java
// Go: struct + AggregateRoot embedding
//
// QEYD: GORM tag yoxdur — bu domain class-dır, infrastructure layer-də
// ayrı UserModel struct var (persistence ignorance).
type User struct {
	domain.AggregateRoot

	id              UserID
	email           Email
	name            string
	password        Password
	twoFactorEnabled bool
	twoFactorSecret string
	backupCodes     []string
}

// Register — factory method, domain event record edir
func Register(name string, email Email, password Password) (*User, error) {
	if name == "" {
		return nil, domain.NewDomainError("Ad boş ola bilməz")
	}
	id := GenerateUserID()
	u := &User{
		id:       id,
		email:    email,
		name:     name,
		password: password,
	}

	event := UserRegisteredEvent{
		BaseEvent: domain.NewBaseEvent(),
		UserID:    id,
		Email:     email.Value(),
		Name:      name,
	}
	u.RecordEvent(event)
	u.RecordEvent(UserRegisteredIntegrationEvent{
		BaseEvent: domain.NewBaseEvent(),
		UserID:    id.String(),
		Email:     email.Value(),
		Name:      name,
	})
	return u, nil
}

// Reconstitute — DB-dən yükləmə zamanı
func Reconstitute(id UserID, name string, email Email, password Password,
	twoFactorEnabled bool, twoFactorSecret string, backupCodes []string) *User {
	return &User{
		id:               id,
		email:            email,
		name:             name,
		password:         password,
		twoFactorEnabled: twoFactorEnabled,
		twoFactorSecret:  twoFactorSecret,
		backupCodes:      backupCodes,
	}
}

func (u *User) ChangePassword(newPassword Password) {
	u.password = newPassword
}

func (u *User) EnableTwoFactor(secret string, backupCodes []string) error {
	if u.twoFactorEnabled {
		return domain.NewDomainError("2FA artıq aktivdir")
	}
	u.twoFactorEnabled = true
	u.twoFactorSecret = secret
	u.backupCodes = backupCodes
	return nil
}

func (u *User) DisableTwoFactor() {
	u.twoFactorEnabled = false
	u.twoFactorSecret = ""
	u.backupCodes = nil
}

func (u *User) VerifyPassword(plaintext string) bool {
	return u.password.Matches(plaintext)
}

// Getters
func (u *User) ID() UserID                  { return u.id }
func (u *User) Email() Email                { return u.email }
func (u *User) Name() string                { return u.name }
func (u *User) Password() Password          { return u.password }
func (u *User) TwoFactorEnabled() bool      { return u.twoFactorEnabled }
func (u *User) TwoFactorSecret() string     { return u.twoFactorSecret }
func (u *User) BackupCodes() []string       { return u.backupCodes }

// Repository — interface (domain-də), implementation infrastructure-də
//
// Laravel: UserRepositoryInterface
// Spring: UserRepository (interface)
type Repository interface {
	Save(user *User) error
	FindByID(id UserID) (*User, error)
	FindByEmail(email Email) (*User, error)
	ExistsByEmail(email Email) (bool, error)
}
