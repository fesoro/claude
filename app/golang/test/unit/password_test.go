// Package unit — Password value object testləri
//
// Laravel: tests/Unit/User/PasswordValueObjectTest.php
// Spring: (bcrypt Spring Security-də avtomatik idarə olunur)
// Go: testify/assert
package unit

import (
	"testing"

	"github.com/orkhan/ecommerce/internal/user/domain"
	"github.com/stretchr/testify/assert"
	"github.com/stretchr/testify/require"
)

func TestPassword_AcceptsMinimumLength(t *testing.T) {
	pw, err := domain.NewPasswordFromPlaintext("12345678") // 8 simvol
	require.NoError(t, err)
	assert.NotEmpty(t, pw.Hash())
}

func TestPassword_RejectsShortPassword(t *testing.T) {
	_, err := domain.NewPasswordFromPlaintext("1234567") // 7 simvol
	assert.Error(t, err)
}

func TestPassword_RejectsEmptyPassword(t *testing.T) {
	_, err := domain.NewPasswordFromPlaintext("")
	assert.Error(t, err)
}

func TestPassword_VerifiesCorrectPassword(t *testing.T) {
	pw, err := domain.NewPasswordFromPlaintext("password123")
	require.NoError(t, err)
	assert.True(t, pw.Matches("password123"))
}

func TestPassword_RejectsIncorrectPassword(t *testing.T) {
	pw, err := domain.NewPasswordFromPlaintext("password123")
	require.NoError(t, err)
	assert.False(t, pw.Matches("wrongpassword"))
}

func TestPassword_HashIsDifferentFromPlaintext(t *testing.T) {
	pw, err := domain.NewPasswordFromPlaintext("mysecret!")
	require.NoError(t, err)
	assert.NotEqual(t, "mysecret!", pw.Hash())
}

func TestPassword_ReconstitutedHashVerifies(t *testing.T) {
	// DB-dən yükləmə simulyasiyası
	original, _ := domain.NewPasswordFromPlaintext("password123")
	reconstituted := domain.NewPasswordFromHash(original.Hash())
	assert.True(t, reconstituted.Matches("password123"))
	assert.False(t, reconstituted.Matches("wrongpassword"))
}
