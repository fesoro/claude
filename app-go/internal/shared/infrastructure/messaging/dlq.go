package messaging

import (
	"time"

	"github.com/google/uuid"
	"gorm.io/gorm"
)

// DeadLetterMessage — dead_letter_messages cədvəli
type DeadLetterMessage struct {
	ID                uint64     `gorm:"primaryKey;autoIncrement"`
	OriginalMessageID *uuid.UUID `gorm:"type:char(36)"`
	QueueName         string     `gorm:"size:128;not null"`
	EventType         string     `gorm:"size:128;not null"`
	Payload           string     `gorm:"type:json;not null"`
	ErrorMessage      string     `gorm:"type:text"`
	ErrorClass        string     `gorm:"size:255"`
	StackTrace        string     `gorm:"type:text"`
	FailedAt          time.Time
	Retried           bool `gorm:"not null;default:false;index"`
}

func (DeadLetterMessage) TableName() string { return "dead_letter_messages" }

type DLQRepository struct{ db *gorm.DB }

func NewDLQRepository(db *gorm.DB) *DLQRepository { return &DLQRepository{db: db} }

func (r *DLQRepository) Save(msg *DeadLetterMessage) error {
	return r.db.Create(msg).Error
}

func (r *DLQRepository) FindUnretriedCount() (int64, error) {
	var count int64
	err := r.db.Model(&DeadLetterMessage{}).Where("retried = ?", false).Count(&count).Error
	return count, err
}
