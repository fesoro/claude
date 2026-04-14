package main

import (
	"fmt"
	"sync"
	"time"
)

// ===============================================
// CACHING - KESHLEME
// ===============================================

// Cache - tez-tez istifade olunan melumati yaddashda saxlamaq
// Database/API sorqularini azaldir, suretlendrir

// -------------------------------------------
// 1. Sadə in-memory cache
// -------------------------------------------
type SadeCache struct {
	mu    sync.RWMutex
	items map[string]interface{}
}

func NewSadeCache() *SadeCache {
	return &SadeCache{items: make(map[string]interface{})}
}

func (c *SadeCache) Set(key string, value interface{}) {
	c.mu.Lock()
	defer c.mu.Unlock()
	c.items[key] = value
}

func (c *SadeCache) Get(key string) (interface{}, bool) {
	c.mu.RLock()
	defer c.mu.RUnlock()
	val, ok := c.items[key]
	return val, ok
}

func (c *SadeCache) Delete(key string) {
	c.mu.Lock()
	defer c.mu.Unlock()
	delete(c.items, key)
}

// -------------------------------------------
// 2. TTL ile cache (muddeti biten elementler)
// -------------------------------------------
type CacheItem struct {
	Value     interface{}
	ExpiresAt time.Time
}

func (ci CacheItem) Expired() bool {
	return time.Now().After(ci.ExpiresAt)
}

type TTLCache struct {
	mu    sync.RWMutex
	items map[string]CacheItem
}

func NewTTLCache(temizlemeInterval time.Duration) *TTLCache {
	c := &TTLCache{items: make(map[string]CacheItem)}

	// Arxa planda mudeti bitmis elementleri temizle
	go func() {
		ticker := time.NewTicker(temizlemeInterval)
		defer ticker.Stop()
		for range ticker.C {
			c.temizle()
		}
	}()

	return c
}

func (c *TTLCache) Set(key string, value interface{}, ttl time.Duration) {
	c.mu.Lock()
	defer c.mu.Unlock()
	c.items[key] = CacheItem{
		Value:     value,
		ExpiresAt: time.Now().Add(ttl),
	}
}

func (c *TTLCache) Get(key string) (interface{}, bool) {
	c.mu.RLock()
	defer c.mu.RUnlock()

	item, ok := c.items[key]
	if !ok || item.Expired() {
		return nil, false
	}
	return item.Value, true
}

func (c *TTLCache) Delete(key string) {
	c.mu.Lock()
	defer c.mu.Unlock()
	delete(c.items, key)
}

func (c *TTLCache) temizle() {
	c.mu.Lock()
	defer c.mu.Unlock()
	for key, item := range c.items {
		if item.Expired() {
			delete(c.items, key)
		}
	}
}

// -------------------------------------------
// 3. LRU Cache (Least Recently Used)
// -------------------------------------------
// En son istifade olunan element silinir (mehdud tutum)

type LRUNode struct {
	key        string
	value      interface{}
	prev, next *LRUNode
}

type LRUCache struct {
	mu       sync.Mutex
	capacity int
	items    map[string]*LRUNode
	head     *LRUNode // en yeni
	tail     *LRUNode // en kohne
}

func NewLRUCache(capacity int) *LRUCache {
	head := &LRUNode{}
	tail := &LRUNode{}
	head.next = tail
	tail.prev = head

	return &LRUCache{
		capacity: capacity,
		items:    make(map[string]*LRUNode),
		head:     head,
		tail:     tail,
	}
}

func (c *LRUCache) Get(key string) (interface{}, bool) {
	c.mu.Lock()
	defer c.mu.Unlock()

	node, ok := c.items[key]
	if !ok {
		return nil, false
	}
	c.moveToFront(node) // istifade olundu - one getirir
	return node.value, true
}

func (c *LRUCache) Set(key string, value interface{}) {
	c.mu.Lock()
	defer c.mu.Unlock()

	if node, ok := c.items[key]; ok {
		node.value = value
		c.moveToFront(node)
		return
	}

	node := &LRUNode{key: key, value: value}
	c.items[key] = node
	c.addToFront(node)

	if len(c.items) > c.capacity {
		// En kohnesini sil
		last := c.tail.prev
		c.removeNode(last)
		delete(c.items, last.key)
	}
}

func (c *LRUCache) addToFront(node *LRUNode) {
	node.prev = c.head
	node.next = c.head.next
	c.head.next.prev = node
	c.head.next = node
}

func (c *LRUCache) removeNode(node *LRUNode) {
	node.prev.next = node.next
	node.next.prev = node.prev
}

func (c *LRUCache) moveToFront(node *LRUNode) {
	c.removeNode(node)
	c.addToFront(node)
}

// -------------------------------------------
// 4. Cache-Aside pattern (en cox istifade olunan)
// -------------------------------------------
type UserService struct {
	cache *TTLCache
	// db    *sql.DB  // real layihede database
}

func NewUserService() *UserService {
	return &UserService{
		cache: NewTTLCache(1 * time.Minute),
	}
}

func (s *UserService) GetUser(id string) string {
	// 1. Evvelce cache-e bax
	if cached, ok := s.cache.Get("user:" + id); ok {
		fmt.Println("  CACHE HIT")
		return cached.(string)
	}

	// 2. Cache-de yoxdursa, database-den oxu
	fmt.Println("  CACHE MISS - DB-den oxuyuram")
	user := "Istifadeci_" + id // DB sorgusu simulyasiyasi

	// 3. Cache-e yaz
	s.cache.Set("user:"+id, user, 5*time.Minute)

	return user
}

func main() {

	// 1. Sadə cache
	fmt.Println("=== Sadə Cache ===")
	cache := NewSadeCache()
	cache.Set("ad", "Orkhan")
	cache.Set("yas", 25)

	if val, ok := cache.Get("ad"); ok {
		fmt.Println("Ad:", val)
	}

	// 2. TTL cache
	fmt.Println("\n=== TTL Cache ===")
	ttlCache := NewTTLCache(10 * time.Second)
	ttlCache.Set("token", "abc123", 2*time.Second)

	if val, ok := ttlCache.Get("token"); ok {
		fmt.Println("Token (dərhal):", val)
	}

	time.Sleep(3 * time.Second)
	if _, ok := ttlCache.Get("token"); !ok {
		fmt.Println("Token mudeti bitmis!")
	}

	// 3. LRU cache
	fmt.Println("\n=== LRU Cache ===")
	lru := NewLRUCache(3) // max 3 element
	lru.Set("a", 1)
	lru.Set("b", 2)
	lru.Set("c", 3)
	lru.Set("d", 4) // "a" silinir (en kohne)

	_, aVar := lru.Get("a")
	_, dVar := lru.Get("d")
	fmt.Println("a var mi:", aVar) // false
	fmt.Println("d var mi:", dVar) // true

	// 4. Cache-Aside pattern
	fmt.Println("\n=== Cache-Aside ===")
	userSvc := NewUserService()
	fmt.Println("1-ci sorgu:", userSvc.GetUser("42"))  // MISS
	fmt.Println("2-ci sorgu:", userSvc.GetUser("42"))  // HIT
	fmt.Println("3-cu sorgu:", userSvc.GetUser("42"))  // HIT

	fmt.Println(`
=== Cache Strategiyalari ===

1. Cache-Aside (Lazy Loading):
   - Sorgu gelende: cache yoxla -> yoxdursa DB-den oxu -> cache-e yaz
   - En populyar, sadə

2. Write-Through:
   - Yazma zamani: hem DB-ye hem cache-e yaz
   - Daha tutarli, amma yavas yazma

3. Write-Behind (Write-Back):
   - Yalniz cache-e yaz, arxa planda DB-ye yaz
   - En suretli, amma melumat itirme riski

4. Read-Through:
   - Cache ozu DB-den oxuyur (cache kutubxanasi idare edir)

=== Redis istifade etmek ===
go get github.com/redis/go-redis/v9

import "github.com/redis/go-redis/v9"

rdb := redis.NewClient(&redis.Options{Addr: "localhost:6379"})
rdb.Set(ctx, "key", "value", 5*time.Minute)
val, err := rdb.Get(ctx, "key").Result()
`)
}
