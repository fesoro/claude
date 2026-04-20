package messaging

import (
	"context"
	"errors"
	"log/slog"
	"time"

	"github.com/google/uuid"
	"gorm.io/gorm"
)

// InboxMessage — at-least-once delivery → exactly-once üçün
//
// Laravel: InboxStore.php
// Spring: InboxMessageEntity + IdempotentConsumer
type InboxMessage struct {
	ID          uint64    `gorm:"primaryKey;autoIncrement"`
	MessageID   uuid.UUID `gorm:"type:char(36);uniqueIndex;not null"`
	EventType   string    `gorm:"size:128;not null"`
	Payload     string    `gorm:"type:json;not null"`
	ReceivedAt  time.Time
	ProcessedAt *time.Time
}

func (InboxMessage) TableName() string { return "inbox_messages" }

// IdempotentConsumer — eyni message_id ilə təkrarlanan işi blok edir
type IdempotentConsumer struct {
	db *gorm.DB
}

func NewIdempotentConsumer(db *gorm.DB) *IdempotentConsumer {
	return &IdempotentConsumer{db: db}
}

// ProcessOnce — handler yalnız bir dəfə işləyəcək
func (c *IdempotentConsumer) ProcessOnce(ctx context.Context, messageID uuid.UUID, eventType, payload string,
	handler func(payload string) error) error {

	// Əvvəlcə inbox-a yaz (unique constraint duplicate-i blok edir)
	now := time.Now()
	msg := InboxMessage{
		MessageID:  messageID,
		EventType:  eventType,
		Payload:    payload,
		ReceivedAt: now,
	}
	err := c.db.WithContext(ctx).Create(&msg).Error
	if err != nil {
		// Duplicate (already processed)
		if isDuplicateKey(err) {
			slog.InfoContext(ctx, "duplicate message, skip", "messageID", messageID)
			return nil
		}
		return err
	}

	// Real iş
	if err := handler(payload); err != nil {
		return err
	}

	// processed_at update
	processedAt := time.Now()
	c.db.Model(&msg).Update("processed_at", processedAt)
	return nil
}

func isDuplicateKey(err error) bool {
	if err == nil {
		return false
	}
	// MySQL error code 1062
	return errors.Is(err, gorm.ErrDuplicatedKey) ||
		(err.Error() != "" && (containsAny(err.Error(), "Duplicate", "1062", "uniqueIndex")))
}

func containsAny(s string, subs ...string) bool {
	for _, sub := range subs {
		if len(s) >= len(sub) {
			for i := 0; i+len(sub) <= len(s); i++ {
				if s[i:i+len(sub)] == sub {
					return true
				}
			}
		}
	}
	return false
}
