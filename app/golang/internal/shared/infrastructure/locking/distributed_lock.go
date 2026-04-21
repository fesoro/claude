// Package locking — distributed lock (multi-instance app üçün)
//
// Laravel: DistributedLock.php (Redis SETNX)
// Spring: Redisson RLock (watchdog, fairness)
// Go: redsync (Redis-based, multiple instance dəstəyi)
package locking

import (
	"context"
	"errors"
	"time"

	"github.com/go-redsync/redsync/v4"
	redsyncgoredis "github.com/go-redsync/redsync/v4/redis/goredis/v9"
	"github.com/redis/go-redis/v9"
)

type DistributedLock struct {
	rs *redsync.Redsync
}

func New(redisClient *redis.Client) *DistributedLock {
	pool := redsyncgoredis.NewPool(redisClient)
	return &DistributedLock{rs: redsync.New(pool)}
}

var ErrLockNotAcquired = errors.New("lock alına bilmədi")

// ExecuteLocked — kritik bölmə bir proses tərəfdən icra olunur
//
//   err := lock.ExecuteLocked(ctx, "payment:" + orderID, 30*time.Second, func() error {
//       return processPayment(orderID)
//   })
func (l *DistributedLock) ExecuteLocked(ctx context.Context, key string, ttl time.Duration,
	action func() error) error {

	mutex := l.rs.NewMutex("lock:"+key,
		redsync.WithExpiry(ttl),
		redsync.WithTries(3),
		redsync.WithRetryDelay(100*time.Millisecond))

	if err := mutex.LockContext(ctx); err != nil {
		return ErrLockNotAcquired
	}
	defer mutex.UnlockContext(ctx)

	return action()
}
