// Package domain — User context domain primitives
package domain

import (
	"regexp"

	"github.com/google/uuid"
	"github.com/orkhan/ecommerce/internal/shared/domain"
	"golang.org/x/crypto/bcrypt"
)

// === USER ID (Value Object) ===
//
// Laravel: UserId.php (immutable PHP class)
// Spring: UserId.java (Java record)
// Go: type alias (idiomatic) — UUID üzərində type-safety
type UserID uuid.UUID

func GenerateUserID() UserID { return UserID(uuid.New()) }

func ParseUserID(s string) (UserID, error) {
	u, err := uuid.Parse(s)
	if err != nil {
		return UserID{}, err
	}
	return UserID(u), nil
}

func (u UserID) UUID() uuid.UUID { return uuid.UUID(u) }
func (u UserID) String() string  { return uuid.UUID(u).String() }

// === EMAIL (Value Object) ===
//
// Validation constructor-da, format yoxlanılır.
type Email struct {
	value string
}

var emailRegex = regexp.MustCompile(`^[A-Za-z0-9+_.\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}$`)

func NewEmail(value string) (Email, error) {
	if value == "" {
		return Email{}, domain.NewDomainError("Email boş ola bilməz")
	}
	if !emailRegex.MatchString(value) {
		return Email{}, domain.NewDomainError("Email düzgün formatda deyil: " + value)
	}
	return Email{value: value}, nil
}

func (e Email) Value() string { return e.value }
func (e Email) String() string { return e.value }

// === PASSWORD (Value Object) ===
//
// fromPlaintext: yeni qeydiyyat (bcrypt hash)
// fromHash:      DB-dən yükləmə (artıq hashed)
type Password struct {
	hash string
}

const minPasswordLength = 8
const bcryptCost = 12

func NewPasswordFromPlaintext(plaintext string) (Password, error) {
	if len(plaintext) < minPasswordLength {
		return Password{}, domain.NewDomainError("Şifrə ən azı 8 simvol olmalıdır")
	}
	h, err := bcrypt.GenerateFromPassword([]byte(plaintext), bcryptCost)
	if err != nil {
		return Password{}, err
	}
	return Password{hash: string(h)}, nil
}

func NewPasswordFromHash(hash string) Password {
	return Password{hash: hash}
}

func (p Password) Hash() string { return p.hash }

func (p Password) Matches(plaintext string) bool {
	return bcrypt.CompareHashAndPassword([]byte(p.hash), []byte(plaintext)) == nil
}
