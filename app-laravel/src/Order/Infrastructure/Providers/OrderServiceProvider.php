<?php

declare(strict_types=1);

namespace Src\Order\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Src\Order\Domain\Factories\OrderFactory;
use Src\Order\Domain\Repositories\OrderRepositoryInterface;
use Src\Order\Domain\Specifications\OrderCanBeCancelledSpec;
use Src\Order\Infrastructure\Outbox\OutboxPublisher;
use Src\Order\Infrastructure\Outbox\OutboxRepository;
use Src\Order\Infrastructure\Repositories\EloquentOrderRepository;

/**
 * ORDER SERVICE PROVIDER (Laravel DI Container)
 * ================================================
 * Order bounded context-in bütün dependency-lərini qeydiyyatdan keçirir.
 *
 * SERVICE PROVIDER NƏDİR?
 * - Laravel-in Dependency Injection (DI) Container-ini konfiqurasiya edir.
 * - "Bu interfeysi istəyəndə, bu implementasiyanı ver" qaydalarını müəyyən edir.
 * - Tək bir yerdə bütün binding-lər olur — asanlıqla dəyişdirmək olar.
 *
 * DEPENDENCY INJECTION (DI) NƏDİR?
 * - Class-lar öz dependency-lərini ÖZLƏRI YARATMIR, XARICDƏN ALIR.
 * - Bu "Inversion of Control" (IoC) prinsipinə uyğundur.
 *
 * NÜMUNƏ:
 * ┌──────────────────────────────────────────────────────────────┐
 * │ YANLIŞ (DI olmadan):                                        │
 * │ class CancelOrderHandler {                                  │
 * │     public function __construct() {                         │
 * │         $this->repo = new EloquentOrderRepository(); // ✗   │
 * │     }                                                       │
 * │ }                                                           │
 * │ Problem: Test-də başqa repository istifadə edə bilmirsən!   │
 * └──────────────────────────────────────────────────────────────┘
 *
 * ┌──────────────────────────────────────────────────────────────┐
 * │ DÜZGÜN (DI ilə):                                            │
 * │ class CancelOrderHandler {                                  │
 * │     public function __construct(                            │
 * │         private OrderRepositoryInterface $repo, // ✓        │
 * │     ) {}                                                    │
 * │ }                                                           │
 * │ ServiceProvider: Interface → Eloquent (production)          │
 * │ Test-də: Interface → InMemory (test)                        │
 * └──────────────────────────────────────────────────────────────┘
 *
 * BU PROVIDER-DA QEYDİYYAT OLUNANLAR:
 * 1. OrderRepositoryInterface → EloquentOrderRepository (interfeys binding)
 * 2. OrderFactory (singleton — bir instance kifayətdir)
 * 3. OrderCanBeCancelledSpec (singleton)
 * 4. OutboxRepository (singleton)
 * 5. OutboxPublisher (singleton)
 */
class OrderServiceProvider extends ServiceProvider
{
    /**
     * Binding-ləri qeydiyyatdan keçir.
     *
     * register() metodu yalnız binding təyin edir — heç bir iş görmür.
     * boot() metodu isə binding-lər hazır olduqdan sonra işə düşür.
     */
    public function register(): void
    {
        // 1. Repository interfeys binding
        // "OrderRepositoryInterface lazımdır" deyəndə → EloquentOrderRepository ver
        // Test-də bunu InMemoryOrderRepository ilə əvəz edə bilərsən
        $this->app->bind(
            OrderRepositoryInterface::class,
            EloquentOrderRepository::class,
        );

        // 2. OrderFactory — singleton (bir instance bütün sorğular üçün)
        // Factory-nin state-i yoxdur, ona görə singleton təhlükəsizdir
        $this->app->singleton(OrderFactory::class);

        // 3. OrderCanBeCancelledSpec — singleton
        // Specification-ların state-i yoxdur, singleton olaraq istifadə oluna bilər
        $this->app->singleton(OrderCanBeCancelledSpec::class);

        // 4. Outbox Repository — singleton
        $this->app->singleton(OutboxRepository::class);

        // 5. Outbox Publisher — singleton
        $this->app->singleton(OutboxPublisher::class);
    }

    /**
     * Boot metodu — binding-lər hazır olduqdan sonra əlavə konfiqurasiya.
     *
     * Burada event listener-lər, route-lar, migration-lar qeydiyyat oluna bilər.
     */
    public function boot(): void
    {
        // Gələcəkdə burada:
        // - Event listener qeydiyyatı (OrderCreatedEvent → listener)
        // - Migration yollarının qeydiyyatı
        // - Route fayllarının yüklənməsi
        // - Scheduled job-ların qeydiyyatı (OutboxPublisher)
    }
}
