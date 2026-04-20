package unit

import (
	"testing"

	"github.com/orkhan/ecommerce/internal/order/domain"
	"github.com/stretchr/testify/assert"
)

func TestOrderStatus_PendingCanGoToConfirmed(t *testing.T) {
	assert.True(t, domain.OrderStatusPending.CanTransitionTo(domain.OrderStatusConfirmed))
}

func TestOrderStatus_PendingCannotGoToShipped(t *testing.T) {
	assert.False(t, domain.OrderStatusPending.CanTransitionTo(domain.OrderStatusShipped))
}

func TestOrderStatus_DeliveredIsFinal(t *testing.T) {
	assert.True(t, domain.OrderStatusDelivered.IsFinal())
	assert.False(t, domain.OrderStatusDelivered.CanTransitionTo(domain.OrderStatusCancelled))
}

func TestOrderStatus_RequireTransitionThrowsOnInvalid(t *testing.T) {
	err := domain.OrderStatusPending.RequireTransitionTo(domain.OrderStatusDelivered)
	assert.Error(t, err)
}

func TestOrderStatus_HasAzerbaijaniLabel(t *testing.T) {
	assert.Equal(t, "Çatdırılıb", domain.OrderStatusDelivered.Label())
	assert.Equal(t, "Ləğv edilib", domain.OrderStatusCancelled.Label())
}
