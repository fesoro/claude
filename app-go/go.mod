module github.com/orkhan/ecommerce

go 1.23

require (
	// Web framework — ən populyar Go HTTP framework
	github.com/gin-gonic/gin v1.10.0
	github.com/gin-contrib/cors v1.7.2

	// ORM — Go-da ən populyar ORM (Eloquent / JPA əvəzi)
	gorm.io/gorm v1.25.12
	gorm.io/driver/mysql v1.5.7

	// Migration — Flyway / Doctrine Migrations əvəzi
	github.com/golang-migrate/migrate/v4 v4.18.1

	// Validation — Bean Validation əvəzi (struct tag-lar ilə)
	github.com/go-playground/validator/v10 v10.22.1

	// Redis client + distributed lock
	github.com/redis/go-redis/v9 v9.7.0
	github.com/go-redsync/redsync/v4 v4.13.0

	// RabbitMQ
	github.com/rabbitmq/amqp091-go v1.10.0

	// Watermill — message bus, CQRS, Outbox üçün (Spring Modulith Events analoq)
	github.com/ThreeDotsLabs/watermill v1.4.4
	github.com/ThreeDotsLabs/watermill-amqp/v3 v3.0.1

	// Circuit Breaker — Resilience4j əvəzi
	github.com/sony/gobreaker v1.0.0

	// Rate limiting
	golang.org/x/time v0.8.0

	// JWT — Sanctum / Spring Security JWT əvəzi
	github.com/golang-jwt/jwt/v5 v5.2.1

	// 2FA TOTP — googleauth əvəzi
	github.com/pquerna/otp v1.4.0

	// Structured logging — Spring StructuredLogger əvəzi (slog stdlib + JSON handler)
	// stdlib log/slog ilə kifayətlənirik

	// Config — viper YAML + env (application.yml əvəzi)
	github.com/spf13/viper v1.19.0

	// CLI framework — Artisan command əvəzi
	github.com/spf13/cobra v1.8.1

	// UUID generation
	github.com/google/uuid v1.6.0

	// Password hashing — Spring BCryptPasswordEncoder əvəzi
	golang.org/x/crypto v0.31.0

	// Testing
	github.com/stretchr/testify v1.10.0

	// Testcontainers — Spring Testcontainers əvəzi
	github.com/testcontainers/testcontainers-go v0.34.0
	github.com/testcontainers/testcontainers-go/modules/mysql v0.34.0

	// Email — JavaMailSender əvəzi (gomail)
	github.com/wneessen/go-mail v0.5.2
)
