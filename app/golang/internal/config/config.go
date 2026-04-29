// Package config viper ilə YAML + env load edir.
// Laravel: config/*.php fayllarının yüklənməsi
// Spring: @ConfigurationProperties + application.yml
package config

import (
	"strings"
	"time"

	"github.com/spf13/viper"
)

type Config struct {
	App            AppConfig            `mapstructure:"app"`
	Database       DatabaseConfig       `mapstructure:"database"`
	Redis          RedisConfig          `mapstructure:"redis"`
	RabbitMQ       RabbitMQConfig       `mapstructure:"rabbitmq"`
	Mail           MailConfig           `mapstructure:"mail"`
	JWT            JWTConfig            `mapstructure:"jwt"`
	RateLimit      RateLimitConfig      `mapstructure:"rate_limit"`
	CircuitBreaker CircuitBreakerConfig `mapstructure:"circuit_breaker"`
	Features       map[string]bool      `mapstructure:"features"`
	Outbox         OutboxConfig         `mapstructure:"outbox"`
	Bulkhead       BulkheadConfig       `mapstructure:"bulkhead"`
	Cache          CacheConfig          `mapstructure:"cache"`
}

type AppConfig struct {
	Name             string `mapstructure:"name"`
	Port             int    `mapstructure:"port"`
	Env              string `mapstructure:"env"`
	ResetPasswordURL string `mapstructure:"reset_password_url"`
}

type DBConn struct {
	Host         string `mapstructure:"host"`
	Port         int    `mapstructure:"port"`
	Name         string `mapstructure:"name"`
	Username     string `mapstructure:"username"`
	Password     string `mapstructure:"password"`
	MaxOpenConns int    `mapstructure:"max_open_conns"`
	MaxIdleConns int    `mapstructure:"max_idle_conns"`
}

type DatabaseConfig struct {
	User    DBConn `mapstructure:"user"`
	Product DBConn `mapstructure:"product"`
	Order   DBConn `mapstructure:"order"`
	Payment DBConn `mapstructure:"payment"`
}

type RedisConfig struct {
	Host string `mapstructure:"host"`
	Port int    `mapstructure:"port"`
	DB   int    `mapstructure:"db"`
}

type RabbitMQConfig struct {
	URL      string `mapstructure:"url"`
	Exchange string `mapstructure:"exchange"`
}

type MailConfig struct {
	Host string `mapstructure:"host"`
	Port int    `mapstructure:"port"`
	From string `mapstructure:"from"`
}

type JWTConfig struct {
	Secret string        `mapstructure:"secret"`
	TTL    time.Duration `mapstructure:"ttl"`
}

type RateLimitConfig struct {
	Register   int `mapstructure:"register"`
	Login      int `mapstructure:"login"`
	Products   int `mapstructure:"products"`
	Orders     int `mapstructure:"orders"`
	Payment    int `mapstructure:"payment"`
	APIDefault int `mapstructure:"api_default"`
}

type CircuitBreakerConfig struct {
	FailureThreshold int           `mapstructure:"failure_threshold"`
	RecoveryTimeout  time.Duration `mapstructure:"recovery_timeout"`
	RetryAttempts    int           `mapstructure:"retry_attempts"`
}

type OutboxConfig struct {
	PublishInterval time.Duration `mapstructure:"publish_interval"`
	BatchSize       int           `mapstructure:"batch_size"`
	PruneAfterDays  int           `mapstructure:"prune_after_days"`
}

type BulkheadConfig struct {
	PaymentConcurrent      int `mapstructure:"payment_concurrent"`
	NotificationConcurrent int `mapstructure:"notification_concurrent"`
}

type CacheConfig struct {
	ProductTTL time.Duration `mapstructure:"product_ttl"`
	OrderTTL   time.Duration `mapstructure:"order_ttl"`
	UserTTL    time.Duration `mapstructure:"user_ttl"`
}

// Load — config.yaml + ENV variable override (məsələn: APP_PORT=8081)
func Load(path string) (*Config, error) {
	v := viper.New()
	v.SetConfigFile(path)
	v.SetEnvPrefix("APP")
	v.SetEnvKeyReplacer(strings.NewReplacer(".", "_"))
	v.AutomaticEnv()

	if err := v.ReadInConfig(); err != nil {
		return nil, err
	}

	cfg := &Config{}
	if err := v.Unmarshal(cfg); err != nil {
		return nil, err
	}
	return cfg, nil
}
