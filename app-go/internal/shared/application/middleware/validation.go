package middleware

import (
	"context"

	"github.com/go-playground/validator/v10"
	"github.com/orkhan/ecommerce/internal/shared/application/bus"
	"github.com/orkhan/ecommerce/internal/shared/domain"
)

// ValidationMiddleware — order=30
//
// Laravel: ValidationMiddleware.php (Form Request rules)
// Spring: ValidationMiddleware.java (Bean Validation @Valid)
// Go: validator/v10 — struct tag-larla validation (`validate:"required,email"`)
type ValidationMiddleware struct {
	validator *validator.Validate
}

func NewValidationMiddleware() *ValidationMiddleware {
	return &ValidationMiddleware{validator: validator.New()}
}

func (m *ValidationMiddleware) Handle(ctx context.Context, cmd any, next bus.Next) (any, error) {
	if err := m.validator.Struct(cmd); err != nil {
		errors := make(map[string]string)
		if vErrs, ok := err.(validator.ValidationErrors); ok {
			for _, ve := range vErrs {
				errors[ve.Field()] = ve.Tag() + " qaydası pozulub"
			}
		}
		return nil, domain.NewValidationError(errors)
	}
	return next(ctx, cmd)
}
