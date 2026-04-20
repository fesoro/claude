package domain

import (
	"github.com/orkhan/ecommerce/internal/shared/domain"
)

// UserRegisteredEvent — domain event (sinxron)
//
// Laravel: UserRegisteredEvent.php
// Spring: UserRegisteredEvent.java (record)
// Go: struct + BaseEvent embed
type UserRegisteredEvent struct {
	domain.BaseEvent
	UserID UserID
	Email  string
	Name   string
}

func (UserRegisteredEvent) EventName() string { return "UserRegistered" }

// UserRegisteredIntegrationEvent — async (RabbitMQ) — Notification context dinləyir
type UserRegisteredIntegrationEvent struct {
	domain.BaseEvent
	UserID string `json:"user_id"`
	Email  string `json:"email"`
	Name   string `json:"name"`
}

func (UserRegisteredIntegrationEvent) EventName() string  { return "UserRegisteredIntegration" }
func (UserRegisteredIntegrationEvent) RoutingKey() string { return "user.registered" }
