<?php

declare(strict_types=1);

namespace Src\Product\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Src\Product\Domain\Repositories\ProductRepositoryInterface;
use Src\Product\Infrastructure\Repositories\EloquentProductRepository;
use Src\Product\Infrastructure\Repositories\CachedProductRepository;
use Src\Shared\Infrastructure\Cache\TaggedCacheService;

/**
 * ProductServiceProvider - Product Bounded Context üçün Laravel Service Provider.
 *
 * Service Provider nədir?
 * - Laravel-in "qeydiyyat mərkəzi"dir.
 * - Siniflərin necə yaradılacağını burada təyin edirik (binding).
 * - Laravel başlayanda bütün provider-ləri yükləyir.
 *
 * Dependency Injection Container (IoC Container):
 * - Laravel avtomatik olaraq constructor-dakı asılılıqları həll edir.
 * - Biz burada deyirik: "ProductRepositoryInterface istəyəndə CachedProductRepository ver."
 * - Laravel lazım olanda avtomatik yaradır.
 *
 * Decorator Pattern-in qeydiyyatı:
 * - Burada CachedProductRepository-ni EloquentProductRepository-nin "üstünə" qoyuruq.
 * - İstənilən sinif ProductRepositoryInterface istəyəndə:
 *   CachedProductRepository(EloquentProductRepository) alacaq.
 */
class ProductServiceProvider extends ServiceProvider
{
    /**
     * Xidmətləri qeydiyyatdan keçiririk (register).
     *
     * register() metodunda:
     * - Sinif binding-ləri edirik.
     * - Heç bir başqa servisin hazır olmasını gözləmirik.
     *
     * $this->app->bind() vs $this->app->singleton():
     * - bind(): Hər dəfə yeni obyekt yaradır.
     * - singleton(): Yalnız bir dəfə yaradır, sonra eyni obyekti qaytarır.
     * - Repository üçün singleton istifadə edirik - bir sorğu daxilində eyni obyektdir.
     */
    public function register(): void
    {
        // ProductRepositoryInterface istəyəndə CachedProductRepository ver
        // CachedProductRepository isə daxilində EloquentProductRepository saxlayır (Decorator)
        $this->app->singleton(
            ProductRepositoryInterface::class,
            function ($app) {
                // Əvvəlcə əsl repository yaradırıq
                $eloquentRepository = new EloquentProductRepository();

                // Sonra cache Decorator-unu üstünə qoyuruq
                // Bu, Decorator pattern-in tətbiqidir:
                // CachedProductRepository "bükür" (wraps) EloquentProductRepository-ni
                return new CachedProductRepository(
                    inner: $eloquentRepository,
                    cache: $app->make(TaggedCacheService::class),
                );
            }
        );
    }

    /**
     * Xidmətləri işə salırıq (boot).
     *
     * boot() metodunda:
     * - Bütün provider-lər register olunandan SONRA çağırılır.
     * - Event listener-lər, route-lar, view composer-lər burada qeydiyyat olunur.
     */
    public function boot(): void
    {
        // Hələlik boşdur.
        // Gələcəkdə burada event listener-lər, migration-lar qeydiyyat oluna bilər.
    }
}
