package unit

import (
	"testing"

	"github.com/orkhan/ecommerce/internal/product/domain"
	"github.com/stretchr/testify/assert"
	"github.com/stretchr/testify/require"
)

func TestStock_Create(t *testing.T) {
	s, err := domain.NewStock(50)
	require.NoError(t, err)
	assert.Equal(t, 50, s.Quantity())
	assert.False(t, s.IsOutOfStock())
	assert.False(t, s.IsLow())
}

func TestStock_RejectsNegative(t *testing.T) {
	_, err := domain.NewStock(-1)
	assert.Error(t, err)
}

func TestStock_Decrease(t *testing.T) {
	s, _ := domain.NewStock(10)
	after, err := s.Decrease(3)
	require.NoError(t, err)
	assert.Equal(t, 7, after.Quantity())
}

func TestStock_RejectsDecreaseBeyondAvailable(t *testing.T) {
	s, _ := domain.NewStock(5)
	_, err := s.Decrease(10)
	assert.Error(t, err)
}

func TestStock_DetectsLow(t *testing.T) {
	low, _ := domain.NewStock(3)
	assert.True(t, low.IsLow())
	normal, _ := domain.NewStock(10)
	assert.False(t, normal.IsLow())
}

func TestStock_DetectsOutOfStock(t *testing.T) {
	s, _ := domain.NewStock(0)
	assert.True(t, s.IsOutOfStock())
}
