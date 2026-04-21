package unit

import (
	"testing"

	"github.com/orkhan/ecommerce/internal/user/domain"
	"github.com/stretchr/testify/assert"
	"github.com/stretchr/testify/require"
)

func TestEmail_AcceptsValid(t *testing.T) {
	e, err := domain.NewEmail("user@example.com")
	require.NoError(t, err)
	assert.Equal(t, "user@example.com", e.Value())
}

func TestEmail_RejectsBlank(t *testing.T) {
	_, err := domain.NewEmail("")
	assert.Error(t, err)
}

func TestEmail_RejectsInvalidFormat(t *testing.T) {
	for _, invalid := range []string{"not-email", "@example.com", "user@"} {
		_, err := domain.NewEmail(invalid)
		assert.Error(t, err, "reject: %q", invalid)
	}
}
