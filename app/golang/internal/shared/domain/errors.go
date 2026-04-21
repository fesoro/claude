package domain

import (
	"errors"
	"fmt"
)

// Go-da exception yoxdur, errors var. errors.As/Is ilə tip yoxlaması.
//
// Laravel: DomainException, EntityNotFoundException, ValidationException
// Spring: RuntimeException hierarchy
// Go: error interface + custom error type-lar + sentinel errors

// DomainError — domain qayda pozuntusu (HTTP 422)
type DomainError struct {
	Message string
	Cause   error
}

func (e *DomainError) Error() string {
	if e.Cause != nil {
		return fmt.Sprintf("%s: %v", e.Message, e.Cause)
	}
	return e.Message
}

func (e *DomainError) Unwrap() error { return e.Cause }

func NewDomainError(msg string) *DomainError {
	return &DomainError{Message: msg}
}

// EntityNotFoundError — HTTP 404
type EntityNotFoundError struct {
	EntityType string
	ID         string
}

func (e *EntityNotFoundError) Error() string {
	return fmt.Sprintf("%s tapılmadı: id=%s", e.EntityType, e.ID)
}

func NewEntityNotFoundError(entityType, id string) *EntityNotFoundError {
	return &EntityNotFoundError{EntityType: entityType, ID: id}
}

// ValidationError — HTTP 400, sahə-xəta map-i
type ValidationError struct {
	Errors map[string]string
}

func (e *ValidationError) Error() string {
	return fmt.Sprintf("validasiya xətası: %v", e.Errors)
}

func NewValidationError(errors map[string]string) *ValidationError {
	return &ValidationError{Errors: errors}
}

// IsDomainError — interceptor-da error type yoxlaması
func IsDomainError(err error) bool {
	var d *DomainError
	return errors.As(err, &d)
}

func IsEntityNotFoundError(err error) bool {
	var e *EntityNotFoundError
	return errors.As(err, &e)
}

func IsValidationError(err error) bool {
	var v *ValidationError
	return errors.As(err, &v)
}
