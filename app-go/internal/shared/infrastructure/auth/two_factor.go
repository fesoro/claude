// Package auth — 2FA TOTP service
//
// Laravel: TwoFactorService.php
// Spring: TwoFactorService.java (googleauth)
// Go: pquerna/otp library — RFC 6238 TOTP
package auth

import (
	"crypto/rand"
	"fmt"

	"github.com/pquerna/otp/totp"
)

type TwoFactorService struct{}

func NewTwoFactorService() *TwoFactorService {
	return &TwoFactorService{}
}

type TwoFactorSetup struct {
	Secret     string
	OTPAuthURL string
	QRCodeData []byte
}

func (s *TwoFactorService) GenerateSecret(userEmail string) (*TwoFactorSetup, error) {
	key, err := totp.Generate(totp.GenerateOpts{
		Issuer:      "Ecommerce",
		AccountName: userEmail,
	})
	if err != nil {
		return nil, err
	}
	return &TwoFactorSetup{
		Secret:     key.Secret(),
		OTPAuthURL: key.URL(),
	}, nil
}

func (s *TwoFactorService) Verify(secret, code string) bool {
	return totp.Validate(code, secret)
}

func (s *TwoFactorService) GenerateBackupCodes(count int) []string {
	codes := make([]string, count)
	for i := 0; i < count; i++ {
		codes[i] = randomCode()
	}
	return codes
}

func randomCode() string {
	b := make([]byte, 4)
	_, _ = rand.Read(b)
	return fmt.Sprintf("%08x", b)
}
