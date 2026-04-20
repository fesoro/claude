package application

import (
	"context"

	"github.com/google/uuid"
	"github.com/orkhan/ecommerce/internal/shared/domain"
	userDomain "github.com/orkhan/ecommerce/internal/user/domain"
)

type GetUserQuery struct {
	UserID uuid.UUID
}

type GetUserHandler struct {
	repo userDomain.Repository
}

func NewGetUserHandler(repo userDomain.Repository) *GetUserHandler {
	return &GetUserHandler{repo: repo}
}

func (h *GetUserHandler) Handle(ctx context.Context, query GetUserQuery) (UserDTO, error) {
	user, err := h.repo.FindByID(userDomain.UserID(query.UserID))
	if err != nil {
		return UserDTO{}, err
	}
	if user == nil {
		return UserDTO{}, domain.NewEntityNotFoundError("User", query.UserID.String())
	}
	return DTOFromDomain(user), nil
}
