// Package unit — Money value object testləri
//
// Laravel: tests/Unit/Product/MoneyValueObjectTest.php
// Spring: MoneyValueObjectTest.java (JUnit 5)
// Go: testify/assert + stdlib testing
package unit

import (
	"testing"

	"github.com/orkhan/ecommerce/internal/product/domain"
	"github.com/stretchr/testify/assert"
	"github.com/stretchr/testify/require"
)

func TestMoney_CreateValid(t *testing.T) {
	m, err := domain.NewMoney(2599, domain.AZN)
	require.NoError(t, err)
	assert.Equal(t, int64(2599), m.Amount())
	assert.Equal(t, domain.AZN, m.Currency())
}

func TestMoney_RejectsNegative(t *testing.T) {
	_, err := domain.NewMoney(-100, domain.AZN)
	assert.Error(t, err)
}

func TestMoney_AddSameCurrency(t *testing.T) {
	a, _ := domain.NewMoney(1000, domain.AZN)
	b, _ := domain.NewMoney(500, domain.AZN)
	sum, err := a.Add(b)
	require.NoError(t, err)
	assert.Equal(t, int64(1500), sum.Amount())
}

func TestMoney_RejectsDifferentCurrencies(t *testing.T) {
	usd, _ := domain.NewMoney(100, domain.USD)
	azn, _ := domain.NewMoney(100, domain.AZN)
	_, err := usd.Add(azn)
	assert.Error(t, err)
}

func TestMoney_MultiplyByPositiveFactor(t *testing.T) {
	price, _ := domain.NewMoney(2599, domain.AZN)
	assert.Equal(t, int64(7797), price.Multiply(3).Amount())
}

func TestMoney_FormatToString(t *testing.T) {
	m, _ := domain.NewMoney(2599, domain.AZN)
	assert.Equal(t, "25.99 AZN", m.String())
}
