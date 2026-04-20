package api

import (
	"errors"
	"log/slog"
	"net/http"

	"github.com/gin-gonic/gin"
	"github.com/orkhan/ecommerce/internal/shared/domain"
)

// ErrorHandler — Gin middleware, error → HTTP status mapping
//
// Laravel: app/Exceptions/Handler.php
// Spring: GlobalExceptionHandler @RestControllerAdvice
// Go: c.Errors-i yoxlayır, ya da panic-recovery + custom error type
func ErrorHandler() gin.HandlerFunc {
	return func(c *gin.Context) {
		c.Next()

		if len(c.Errors) == 0 {
			return
		}
		err := c.Errors.Last().Err

		var notFound *domain.EntityNotFoundError
		var validation *domain.ValidationError
		var domainErr *domain.DomainError

		switch {
		case errors.As(err, &notFound):
			c.JSON(http.StatusNotFound, Error(notFound.Error()))
		case errors.As(err, &validation):
			c.JSON(http.StatusBadRequest, ValidationFailed(validation.Errors))
		case errors.As(err, &domainErr):
			c.JSON(http.StatusUnprocessableEntity, Error(domainErr.Error()))
		default:
			slog.Error("internal error", "err", err, "path", c.Request.URL.Path)
			c.JSON(http.StatusInternalServerError, Error("Daxili server xətası"))
		}
	}
}
