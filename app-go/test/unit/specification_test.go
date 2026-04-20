package unit

import (
	"testing"

	"github.com/orkhan/ecommerce/internal/shared/domain"
	"github.com/stretchr/testify/assert"
)

func TestSpecification_And(t *testing.T) {
	isPositive := domain.SpecFunc[int](func(n int) bool { return n > 0 })
	isEven := domain.SpecFunc[int](func(n int) bool { return n%2 == 0 })
	posAndEven := domain.And[int](isPositive, isEven)

	assert.True(t, posAndEven.IsSatisfiedBy(4))
	assert.False(t, posAndEven.IsSatisfiedBy(3))
	assert.False(t, posAndEven.IsSatisfiedBy(-2))
}

func TestSpecification_Or(t *testing.T) {
	isPositive := domain.SpecFunc[int](func(n int) bool { return n > 0 })
	isEven := domain.SpecFunc[int](func(n int) bool { return n%2 == 0 })
	posOrEven := domain.Or[int](isPositive, isEven)

	assert.True(t, posOrEven.IsSatisfiedBy(3))
	assert.True(t, posOrEven.IsSatisfiedBy(-2))
	assert.False(t, posOrEven.IsSatisfiedBy(-1))
}

func TestSpecification_Not(t *testing.T) {
	isPositive := domain.SpecFunc[int](func(n int) bool { return n > 0 })
	isNonPositive := domain.Not[int](isPositive)

	assert.True(t, isNonPositive.IsSatisfiedBy(0))
	assert.True(t, isNonPositive.IsSatisfiedBy(-5))
	assert.False(t, isNonPositive.IsSatisfiedBy(5))
}
