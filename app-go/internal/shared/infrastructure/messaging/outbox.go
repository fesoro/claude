package messaging

import (
	"context"
	"encoding/json"
	"log/slog"
	"time"

	"github.com/google/uuid"
	"gorm.io/gorm"
)

// OutboxMessage — outbox_messages cədvəli
//
// Laravel: OutboxMessageModel  ·  Spring: OutboxMessageEntity (JPA)
// Go: GORM struct + tag-lar
type OutboxMessage struct {
	ID          uint64    `gorm:"primaryKey;autoIncrement"`
	MessageID   uuid.UUID `gorm:"type:char(36);uniqueIndex;not null"`
	AggregateID *uuid.UUID `gorm:"type:char(36)"`
	EventType   string    `gorm:"size:128;not null"`
	RoutingKey  string    `gorm:"size:255;not null"`
	Payload     string    `gorm:"type:json;not null"`
	Metadata    *string   `gorm:"type:json"`
	Published   bool      `gorm:"not null;default:false;index"`
	PublishedAt *time.Time
	RetryCount  int    `gorm:"not null;default:0"`
	LastError   string `gorm:"type:text"`
	CreatedAt   time.Time
}

func (OutboxMessage) TableName() string { return "outbox_messages" }

// OutboxRepository — Save + Pull pending messages
type OutboxRepository struct {
	db *gorm.DB
}

func NewOutboxRepository(db *gorm.DB) *OutboxRepository {
	return &OutboxRepository{db: db}
}

// SaveInTx — biznes tranzaksiyasına əlavə outbox yazır (transactional outbox pattern)
func (r *OutboxRepository) SaveInTx(tx *gorm.DB, eventType, routingKey string, payload any) error {
	msg, err := buildOutboxMessage(eventType, routingKey, payload)
	if err != nil {
		return err
	}
	return tx.Create(&msg).Error
}

// Save — transaction-sız — cari r.db üzərindən yazır
// (handler-də @Transactional olmayanda və ya event dispatcher-dən çağrıldıqda)
func (r *OutboxRepository) Save(eventType, routingKey string, payload any) error {
	msg, err := buildOutboxMessage(eventType, routingKey, payload)
	if err != nil {
		return err
	}
	return r.db.Create(&msg).Error
}

func buildOutboxMessage(eventType, routingKey string, payload any) (OutboxMessage, error) {
	body, err := json.Marshal(payload)
	if err != nil {
		return OutboxMessage{}, err
	}
	return OutboxMessage{
		MessageID:  uuid.New(),
		EventType:  eventType,
		RoutingKey: routingKey,
		Payload:    string(body),
		Published:  false,
		CreatedAt:  time.Now(),
	}, nil
}

func (r *OutboxRepository) FindPending(limit int) ([]OutboxMessage, error) {
	var msgs []OutboxMessage
	err := r.db.Where("published = ?", false).
		Order("created_at ASC").
		Limit(limit).
		Find(&msgs).Error
	return msgs, err
}

func (r *OutboxRepository) MarkPublished(id uint64) error {
	now := time.Now()
	return r.db.Model(&OutboxMessage{}).Where("id = ?", id).
		Updates(map[string]any{"published": true, "published_at": now}).Error
}

// OutboxPublisher — periodic job (cron) — Spring @Scheduled əvəzi
//
// Laravel: PublishOutboxMessagesJob (queue: outbox, ShouldBeUnique)
// Go: time.Ticker + goroutine
type OutboxPublisher struct {
	repo      *OutboxRepository
	publisher *Publisher
	interval  time.Duration
	batchSize int
}

func NewOutboxPublisher(repo *OutboxRepository, pub *Publisher, interval time.Duration, batch int) *OutboxPublisher {
	return &OutboxPublisher{repo: repo, publisher: pub, interval: interval, batchSize: batch}
}

// Start — background loop (main.go-da go publisher.Start(ctx) çağırılır)
func (p *OutboxPublisher) Start(ctx context.Context) {
	ticker := time.NewTicker(p.interval)
	defer ticker.Stop()
	for {
		select {
		case <-ctx.Done():
			return
		case <-ticker.C:
			p.publishBatch(ctx)
		}
	}
}

func (p *OutboxPublisher) publishBatch(ctx context.Context) {
	msgs, err := p.repo.FindPending(p.batchSize)
	if err != nil || len(msgs) == 0 {
		return
	}
	slog.InfoContext(ctx, "outbox batch publishing", "count", len(msgs))

	for _, msg := range msgs {
		var payload any
		_ = json.Unmarshal([]byte(msg.Payload), &payload)
		err := p.publisher.Publish(ctx, msg.RoutingKey, payload, map[string]any{
			"event-type": msg.EventType,
			"event-id":   msg.MessageID.String(),
		})
		if err != nil {
			slog.WarnContext(ctx, "outbox publish failed", "id", msg.ID, "err", err)
			continue
		}
		_ = p.repo.MarkPublished(msg.ID)
	}
}
