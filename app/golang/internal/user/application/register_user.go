package application

import (
	"context"

	"github.com/google/uuid"
	"github.com/orkhan/ecommerce/internal/shared/domain"
	userDomain "github.com/orkhan/ecommerce/internal/user/domain"
)

// RegisterUserCommand — input
//
// validator/v10 tag-ları ValidationMiddleware tərəfindən yoxlanılır
// Laravel Form Request, Spring Bean Validation əvəzi.
type RegisterUserCommand struct {
	Name     string `validate:"required,min=2,max=100"`
	Email    string `validate:"required,email"`
	Password string `validate:"required,min=8"`
}

// RegisterUserHandler — Spring @Service equivalent
type RegisterUserHandler struct {
	repo            userDomain.Repository
	eventDispatcher EventDispatcher
}

// EventDispatcher — interface (dependency inversion)
type EventDispatcher interface {
	DispatchAll(ctx context.Context, aggregate interface{ PullDomainEvents() []domain.DomainEvent }) error
}

func NewRegisterUserHandler(repo userDomain.Repository, dispatcher EventDispatcher) *RegisterUserHandler {
	return &RegisterUserHandler{repo: repo, eventDispatcher: dispatcher}
}

// Handle — bus.CommandHandler[RegisterUserCommand, uuid.UUID] interface-ni implement edir
func (h *RegisterUserHandler) Handle(ctx context.Context, cmd RegisterUserCommand) (uuid.UUID, error) {
	email, err := userDomain.NewEmail(cmd.Email)
	if err != nil {
		return uuid.Nil, err
	}

	exists, err := h.repo.ExistsByEmail(email)
	if err != nil {
		return uuid.Nil, err
	}
	if exists {
		return uuid.Nil, domain.NewDomainError("Bu email artıq qeydiyyatdadır: " + cmd.Email)
	}

	password, err := userDomain.NewPasswordFromPlaintext(cmd.Password)
	if err != nil {
		return uuid.Nil, err
	}

	user, err := userDomain.Register(cmd.Name, email, password)
	if err != nil {
		return uuid.Nil, err
	}

	if err := h.repo.Save(user); err != nil {
		return uuid.Nil, err
	}

	_ = h.eventDispatcher.DispatchAll(ctx, user)
	return user.ID().UUID(), nil
}
