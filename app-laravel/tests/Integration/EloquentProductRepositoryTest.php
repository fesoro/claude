<?php

declare(strict_types=1);

namespace Tests\Integration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Src\Product\Domain\Entities\Product;
use Src\Product\Domain\ValueObjects\Money;
use Src\Product\Domain\ValueObjects\ProductId;
use Src\Product\Domain\ValueObjects\ProductName;
use Src\Product\Domain\ValueObjects\Stock;
use Src\Product\Infrastructure\Repositories\CachedProductRepository;
use Src\Product\Infrastructure\Repositories\EloquentProductRepository;
use Tests\TestCase;

/**
 * ELOQUENT PRODUCT REPOSITORY İNTEQRASİYA TESTLƏRİ
 * =================================================
 * Bu testlər EloquentProductRepository və CachedProductRepository (Decorator)
 * ilə real verilənlər bazası əməliyyatlarını yoxlayır.
 *
 * TEST STRUKTURU:
 * 1. EloquentProductRepository testləri — birbaşa DB əməliyyatları
 * 2. CachedProductRepository testləri — Decorator pattern-in cache davranışı
 *
 * CachedProductRepository DECORATOR PATTERN İSTİFADƏ EDİR:
 * - EloquentProductRepository-ni "sarır" (wrap edir)
 * - Əvvəlcə cache-ə baxır, tapılmadıqda əsl repository-yə müraciət edir
 * - save() zamanı cache invalidation edir (köhnə cache-i silir)
 *
 * RefreshDatabase hər testdən əvvəl bütün cədvəlləri təmizləyir.
 * Cache::flush() hər testdən əvvəl cache-i təmizləyir.
 */
class EloquentProductRepositoryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Birbaşa DB ilə işləyən repository instansı.
     */
    private EloquentProductRepository $eloquentRepository;

    /**
     * Cache qatı əlavə edilmiş Decorator repository instansı.
     * EloquentProductRepository-ni sarır.
     */
    private CachedProductRepository $cachedRepository;

    /**
     * Hər testdən əvvəl repository instanslarını yaradırıq.
     * Cache-i təmizləyirik ki, əvvəlki testlərdən qalan cache testə təsir etməsin.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Birbaşa DB repository
        $this->eloquentRepository = new EloquentProductRepository();

        // Cache decorator — EloquentProductRepository-ni sarır
        $this->cachedRepository = new CachedProductRepository($this->eloquentRepository);

        // Hər testdən əvvəl cache təmizlənir — test isolation üçün vacibdir
        Cache::flush();
    }

    // ============================================
    // EloquentProductRepository — save() TESTLƏRİ
    // ============================================

    /**
     * Yeni məhsul bazaya uğurla yazılmalıdır.
     *
     * AXIN:
     * 1. Product::create() ilə domain entity yaradırıq (ProductCreatedEvent qeydə alınır)
     * 2. repository->save() — entity bazaya yazılır
     * 3. repository->findById() ilə yoxlayırıq — round-trip testi
     *
     * Product::create() private constructor istifadə edir — yalnız factory method ilə yaradılır.
     * Bu, DDD-nin "entities are created through factory methods" prinsipinə uyğundur.
     */
    public function test_save_persists_new_product_to_database(): void
    {
        // Arrange — yeni domain entity yaradırıq
        $productId = ProductId::generate();
        $product = Product::create(
            id: $productId,
            name: new ProductName('Test Məhsul'),
            price: new Money(29.99, 'USD'),
            stock: new Stock(100),
        );

        // Act — bazaya yazırıq
        $this->eloquentRepository->save($product);

        // Assert — bazadan oxuyub yoxlayırıq
        $found = $this->eloquentRepository->findById($productId);

        $this->assertNotNull($found, 'Saxlanılmış məhsul bazadan tapılmalıdır');
        $this->assertEquals('Test Məhsul', $found->name()->value());
        $this->assertEquals(29.99, $found->price()->amount());
        $this->assertEquals('USD', $found->price()->currency());
        $this->assertEquals(100, $found->stock()->quantity());
    }

    /**
     * Mövcud məhsulu yeniləmək düzgün işləməlidir.
     * save() metodu updateOrInsert istifadə edir —
     * eyni ID ilə çağırıldıqda INSERT əvəzinə UPDATE icra edir.
     */
    public function test_save_updates_existing_product(): void
    {
        // Arrange — ilk versiyasını yaradıb saxlayırıq
        $productId = ProductId::generate();
        $product = Product::create(
            id: $productId,
            name: new ProductName('Əvvəlki Məhsul'),
            price: new Money(19.99, 'USD'),
            stock: new Stock(50),
        );
        $this->eloquentRepository->save($product);

        // Act — stoku dəyişib yenidən saxlayırıq
        // decreaseStock() ilə stoku azaldırıq — immutable Stock yeni instans yaradır
        $product->decreaseStock(10);
        $this->eloquentRepository->save($product);

        // Assert — yenilənmiş stok bazadan oxunmalıdır
        $found = $this->eloquentRepository->findById($productId);
        $this->assertNotNull($found);
        $this->assertEquals(40, $found->stock()->quantity());
    }

    // ============================================
    // EloquentProductRepository — findById() TESTLƏRİ
    // ============================================

    /**
     * Mövcud məhsul ID ilə tapılmalıdır.
     * findById() DB-dən sətri oxuyur və toDomainEntity() ilə çevirir.
     * Reflection istifadə edir — çünki Product-ın constructor-u private-dır.
     */
    public function test_find_by_id_returns_product_when_exists(): void
    {
        // Arrange — məhsul yaradıb saxlayırıq
        $productId = ProductId::generate();
        $product = Product::create(
            id: $productId,
            name: new ProductName('Tapılacaq Məhsul'),
            price: new Money(49.99, 'EUR'),
            stock: new Stock(25),
        );
        $this->eloquentRepository->save($product);

        // Act — ID ilə axtarırıq
        $found = $this->eloquentRepository->findById($productId);

        // Assert
        $this->assertNotNull($found);
        $this->assertInstanceOf(Product::class, $found);
        $this->assertEquals('Tapılacaq Məhsul', $found->name()->value());
        $this->assertEquals(49.99, $found->price()->amount());
        $this->assertEquals('EUR', $found->price()->currency());
    }

    /**
     * Mövcud olmayan ID ilə axtarış null qaytarmalıdır.
     */
    public function test_find_by_id_returns_null_when_not_found(): void
    {
        // Arrange — bazada olmayan UUID
        $nonExistentId = ProductId::generate();

        // Act
        $found = $this->eloquentRepository->findById($nonExistentId);

        // Assert — null olmalıdır
        $this->assertNull($found, 'Mövcud olmayan ID ilə axtarış null qaytarmalıdır');
    }

    // ============================================
    // EloquentProductRepository — findAll() TESTLƏRİ
    // ============================================

    /**
     * findAll() bütün məhsulları qaytarmalıdır.
     * Boş bazada boş massiv, məhsullar varsa hamısını qaytarır.
     */
    public function test_find_all_returns_all_products(): void
    {
        // Arrange — 3 məhsul yaradıb saxlayırıq
        for ($i = 1; $i <= 3; $i++) {
            $product = Product::create(
                id: ProductId::generate(),
                name: new ProductName("Məhsul {$i}"),
                price: new Money(10.00 * $i, 'USD'),
                stock: new Stock(10 * $i),
            );
            $this->eloquentRepository->save($product);
        }

        // Act — bütün məhsulları sorğulayırıq
        $products = $this->eloquentRepository->findAll();

        // Assert — 3 məhsul qaytarılmalıdır
        $this->assertCount(3, $products);

        // Hər element Product instansı olmalıdır
        foreach ($products as $product) {
            $this->assertInstanceOf(Product::class, $product);
        }
    }

    /**
     * Boş bazada findAll() boş massiv qaytarmalıdır.
     */
    public function test_find_all_returns_empty_array_when_no_products(): void
    {
        // Act — boş bazadan sorğulayırıq
        $products = $this->eloquentRepository->findAll();

        // Assert — boş massiv olmalıdır
        $this->assertIsArray($products);
        $this->assertEmpty($products);
    }

    // ============================================
    // CachedProductRepository — DECORATOR TESTLƏRİ
    // ============================================

    /**
     * CachedProductRepository findById() cache-dən oxumalıdır.
     *
     * DECORATOR AXINI:
     * 1. İlk çağırış: cache-də yoxdur → EloquentProductRepository-dən oxuyur → cache-ə yazır
     * 2. İkinci çağırış: cache-dən oxuyur → DB-yə müraciət etmir
     *
     * Bu test decorator-un cache-ə düzgün yazdığını yoxlayır.
     */
    public function test_cached_repository_returns_product_from_cache(): void
    {
        // Arrange — məhsul yaradıb saxlayırıq (Cached repository vasitəsilə)
        $productId = ProductId::generate();
        $product = Product::create(
            id: $productId,
            name: new ProductName('Cache Test Məhsul'),
            price: new Money(39.99, 'AZN'),
            stock: new Stock(75),
        );
        $this->cachedRepository->save($product);

        // Act — ilk oxuma (cache-ə yazacaq)
        $firstRead = $this->cachedRepository->findById($productId);
        // İkinci oxuma (cache-dən oxumalıdır)
        $secondRead = $this->cachedRepository->findById($productId);

        // Assert — hər iki oxuma eyni nəticəni qaytarmalıdır
        $this->assertNotNull($firstRead);
        $this->assertNotNull($secondRead);
        $this->assertEquals($firstRead->name()->value(), $secondRead->name()->value());
        $this->assertEquals('Cache Test Məhsul', $secondRead->name()->value());
    }

    /**
     * CachedProductRepository save() zamanı cache-i təmizləməlidir (cache invalidation).
     *
     * CACHE INVALIDATİON PRİNSİPİ:
     * Məhsul dəyişdikdə köhnə cache silinməlidir — əks halda "stale cache" (köhnəlmiş cache)
     * problemi yaranır: bazada yeni data var, amma cache köhnə datanı qaytarır.
     *
     * Bu test:
     * 1. Məhsul yaradır və cache-ə yazır (findById ilə)
     * 2. Məhsulu dəyişib save() edir — cache silinməlidir
     * 3. Yenidən findById — təzə data gəlməlidir
     */
    public function test_cached_repository_invalidates_cache_on_save(): void
    {
        // Arrange — məhsul yaradıb cache-ə yazırıq
        $productId = ProductId::generate();
        $product = Product::create(
            id: $productId,
            name: new ProductName('Cache Invalidation Test'),
            price: new Money(100.00, 'USD'),
            stock: new Stock(50),
        );
        $this->cachedRepository->save($product);

        // Cache-ə yazılması üçün oxuyuruq
        $this->cachedRepository->findById($productId);

        // Act — stoku dəyişib yenidən saxlayırıq
        $product->decreaseStock(20);
        $this->cachedRepository->save($product);

        // Yenidən oxuyuruq — yeni data gəlməlidir
        $found = $this->cachedRepository->findById($productId);

        // Assert — stok yenilənmiş olmalıdır (50 - 20 = 30)
        $this->assertNotNull($found);
        $this->assertEquals(30, $found->stock()->quantity());
    }

    /**
     * CachedProductRepository findAll() cache ilə işləməlidir.
     * İlk çağırışda DB-dən oxuyub cache-ə yazır, sonrakılarda cache-dən qaytarır.
     */
    public function test_cached_repository_caches_find_all_results(): void
    {
        // Arrange — 2 məhsul yaradırıq
        for ($i = 1; $i <= 2; $i++) {
            $product = Product::create(
                id: ProductId::generate(),
                name: new ProductName("Cached Məhsul {$i}"),
                price: new Money(10.00 * $i, 'USD'),
                stock: new Stock(10),
            );
            $this->cachedRepository->save($product);
        }

        // Act — ilk çağırış cache-ə yazır
        $firstCall = $this->cachedRepository->findAll();
        // İkinci çağırış cache-dən oxuyur
        $secondCall = $this->cachedRepository->findAll();

        // Assert — hər iki çağırış eyni sayda məhsul qaytarmalıdır
        $this->assertCount(2, $firstCall);
        $this->assertCount(2, $secondCall);
    }
}
