// Package cache — tag-based Redis cache
//
// Laravel: Cache::tags(['products'])->put(key, val, ttl)
//          Cache::tags(['products'])->flush()  ← invalidation
// Spring: TaggedCacheService.java (Redis SET-lər)
// Go: redis SET-lər ilə eyni implementasiya
package cache

import (
	"context"
	"time"

	"github.com/redis/go-redis/v9"
)

type TaggedCache struct {
	client *redis.Client
}

func New(client *redis.Client) *TaggedCache {
	return &TaggedCache{client: client}
}

func (c *TaggedCache) Put(ctx context.Context, key, value string, ttl time.Duration, tags ...string) error {
	if err := c.client.Set(ctx, key, value, ttl).Err(); err != nil {
		return err
	}
	for _, tag := range tags {
		c.client.SAdd(ctx, "tag:"+tag, key)
	}
	return nil
}

func (c *TaggedCache) Get(ctx context.Context, key string) (string, error) {
	return c.client.Get(ctx, key).Result()
}

// InvalidateTag — bütün tag altındakı cache key-ləri silir
// Laravel: Cache::tags(['products'])->flush()
func (c *TaggedCache) InvalidateTag(ctx context.Context, tag string) error {
	keys, err := c.client.SMembers(ctx, "tag:"+tag).Result()
	if err != nil {
		return err
	}
	if len(keys) > 0 {
		c.client.Del(ctx, keys...)
	}
	c.client.Del(ctx, "tag:"+tag)
	return nil
}
