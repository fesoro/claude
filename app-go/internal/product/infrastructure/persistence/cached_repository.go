package persistence

import (
	"context"
	"time"

	"github.com/orkhan/ecommerce/internal/product/domain"
	"github.com/orkhan/ecommerce/internal/shared/infrastructure/cache"
)

// CachedRepository — Decorator pattern (Repository wrap edir, cache layer əlavə edir)
//
// Laravel: CachedProductRepository.php
// Spring: CachedProductRepository.java (@Primary)
// Go: explicit composition (no DI annotation)
type CachedRepository struct {
	delegate *Repository
	cache    *cache.TaggedCache
	ttl      time.Duration
	ctx      context.Context
}

const (
	cacheTagProducts = "products"
)

func NewCachedRepository(delegate *Repository, c *cache.TaggedCache, ttl time.Duration) *CachedRepository {
	return &CachedRepository{
		delegate: delegate,
		cache:    c,
		ttl:      ttl,
		ctx:      context.Background(),
	}
}

func (r *CachedRepository) Save(product *domain.Product) error {
	if err := r.delegate.Save(product); err != nil {
		return err
	}
	// Save zamanı bütün product cache-i invalidate et
	_ = r.cache.InvalidateTag(r.ctx, cacheTagProducts)
	return nil
}

func (r *CachedRepository) FindByID(id domain.ProductID) (*domain.Product, error) {
	// Domain object cache etmək çətindir (private fields, JSON serialization yoxdur),
	// production-da DTO cache edilir. Hələlik birbaşa delegate.
	return r.delegate.FindByID(id)
}

func (r *CachedRepository) FindAll(page, size int) ([]*domain.Product, error) {
	return r.delegate.FindAll(page, size)
}

func (r *CachedRepository) Count() (int64, error) {
	return r.delegate.Count()
}
