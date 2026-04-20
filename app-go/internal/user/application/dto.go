// Package application — User context CQRS handler-ləri
package application

import (
	"github.com/google/uuid"
	"github.com/orkhan/ecommerce/internal/user/domain"
)

// UserDTO — output DTO (controller → response)
type UserDTO struct {
	ID               uuid.UUID `json:"id"`
	Name             string    `json:"name"`
	Email            string    `json:"email"`
	TwoFactorEnabled bool      `json:"two_factor_enabled"`
}

func DTOFromDomain(u *domain.User) UserDTO {
	return UserDTO{
		ID:               u.ID().UUID(),
		Name:             u.Name(),
		Email:            u.Email().Value(),
		TwoFactorEnabled: u.TwoFactorEnabled(),
	}
}
