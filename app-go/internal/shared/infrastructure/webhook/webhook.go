// Package webhook — webhook delivery service with HMAC-SHA256 signing
//
// Laravel: WebhookService.php (HMAC + retry)
// Spring: WebhookService.java
// Go: net/http + crypto/hmac
package webhook

import (
	"bytes"
	"context"
	"crypto/hmac"
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"fmt"
	"io"
	"log/slog"
	"net/http"
	"strings"
	"time"

	"github.com/google/uuid"
	"gorm.io/gorm"
)

type Webhook struct {
	ID       uuid.UUID `gorm:"type:char(36);primaryKey"`
	UserID   uuid.UUID `gorm:"type:char(36);not null;index"`
	URL      string    `gorm:"size:2048;not null"`
	Secret   string    `gorm:"size:255;not null"`
	Events   string    `gorm:"type:json;not null"` // ["order.created", "payment.completed"]
	IsActive bool      `gorm:"not null;default:true;index"`
	CreatedAt time.Time
	UpdatedAt time.Time
}

func (Webhook) TableName() string { return "webhooks" }

type WebhookLog struct {
	ID             uint64    `gorm:"primaryKey;autoIncrement"`
	WebhookID      uuid.UUID `gorm:"type:char(36);not null;index"`
	EventType      string    `gorm:"size:64;not null"`
	Payload        string    `gorm:"type:json;not null"`
	ResponseStatus int
	ResponseBody   string `gorm:"type:text"`
	AttemptCount   int    `gorm:"not null;default:1"`
	Success        bool   `gorm:"not null;default:false"`
	ErrorMessage   string `gorm:"size:1024"`
	SentAt         time.Time
}

func (WebhookLog) TableName() string { return "webhook_logs" }

type Service struct {
	db     *gorm.DB
	client *http.Client
}

func New(db *gorm.DB) *Service {
	return &Service{
		db:     db,
		client: &http.Client{Timeout: 10 * time.Second},
	}
}

// Deliver — bütün aktiv webhook-lara event göndərir
func (s *Service) Deliver(ctx context.Context, eventType string, payload any) error {
	var hooks []Webhook
	err := s.db.WithContext(ctx).Where("is_active = ?", true).Find(&hooks).Error
	if err != nil {
		return err
	}

	body, err := json.Marshal(payload)
	if err != nil {
		return err
	}

	for _, hook := range hooks {
		if !strings.Contains(hook.Events, eventType) {
			continue
		}
		s.sendOne(ctx, hook, eventType, body)
	}
	return nil
}

func (s *Service) sendOne(ctx context.Context, hook Webhook, eventType string, body []byte) {
	logEntry := WebhookLog{
		WebhookID: hook.ID,
		EventType: eventType,
		Payload:   string(body),
		SentAt:    time.Now(),
	}

	signature := computeHMAC(body, hook.Secret)
	req, err := http.NewRequestWithContext(ctx, "POST", hook.URL, bytes.NewReader(body))
	if err != nil {
		logEntry.ErrorMessage = err.Error()
		s.db.Create(&logEntry)
		return
	}
	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("X-Webhook-Signature", "sha256="+signature)
	req.Header.Set("X-Webhook-Event", eventType)

	resp, err := s.client.Do(req)
	if err != nil {
		logEntry.ErrorMessage = err.Error()
		slog.WarnContext(ctx, "webhook xətası", "url", hook.URL, "err", err)
	} else {
		defer resp.Body.Close()
		respBody, _ := io.ReadAll(resp.Body)
		logEntry.ResponseStatus = resp.StatusCode
		logEntry.ResponseBody = string(respBody)
		logEntry.Success = resp.StatusCode >= 200 && resp.StatusCode < 300
		slog.InfoContext(ctx, "webhook delivered", "url", hook.URL, "status", resp.StatusCode)
	}
	s.db.Create(&logEntry)
}

// computeHMAC — HMAC-SHA256(body, secret) → hex
func computeHMAC(body []byte, secret string) string {
	mac := hmac.New(sha256.New, []byte(secret))
	mac.Write(body)
	return hex.EncodeToString(mac.Sum(nil))
}

// HelperString — for debugging
func (s *Service) Helper() string { return fmt.Sprintf("WebhookService") }
