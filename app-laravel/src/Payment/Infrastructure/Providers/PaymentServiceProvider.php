<?php

declare(strict_types=1);

namespace Src\Payment\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Src\Payment\Application\ACL\PaymentGatewayACL;
use Src\Payment\Domain\Repositories\PaymentRepositoryInterface;
use Src\Payment\Domain\Services\PaymentStrategyResolver;
use Src\Payment\Domain\Strategies\BankTransferGateway;
use Src\Payment\Domain\Strategies\CreditCardGateway;
use Src\Payment\Domain\Strategies\PayPalGateway;
use Src\Payment\Domain\ValueObjects\PaymentMethod;
use Src\Payment\Infrastructure\CircuitBreaker\CircuitBreaker;
use Src\Payment\Infrastructure\CircuitBreaker\RetryHandler;
use Src\Payment\Infrastructure\Repositories\EloquentPaymentRepository;

/**
 * PAYMENT SERVICE PROVIDER
 * ========================
 * Laravel-in Dependency Injection (DI) Container-inə Payment modulunun
 * bütün asılılıqlarını (dependencies) qeydiyyata alır (register edir).
 *
 * SERVICE PROVIDER NƏ EDİR?
 * ─────────────────────────
 * 1. Interface-ləri konkret implementasiyalara bind edir:
 *    PaymentRepositoryInterface → EloquentPaymentRepository
 *
 * 2. Strategy pattern üçün gateway-ləri qeydiyyata alır:
 *    'credit_card' → CreditCardGateway
 *    'paypal' → PayPalGateway
 *    'bank_transfer' → BankTransferGateway
 *
 * 3. CircuitBreaker və RetryHandler-i konfiqurasiya edir
 *
 * NƏYƏ SERVICE PROVIDER LAZIMDIR?
 * - Bütün modul konfiqurasiyası bir yerdə toplanır
 * - Asılılıqları dəyişmək çox asandır (məsələn: test üçün InMemoryRepository)
 * - Lazy loading — yalnız lazım olanda yaradılır
 */
final class PaymentServiceProvider extends ServiceProvider
{
    /**
     * Xidmətləri qeydiyyata al.
     *
     * register() — Laravel application boot olmazdan ƏVVƏL çağırılır.
     * Burada yalnız binding-lər olmalıdır, heç bir iş görülməməlidir.
     */
    public function register(): void
    {
        // 1. Repository interface-ini Eloquent implementasiyasına bind et
        //    Artıq kodda PaymentRepositoryInterface istifadə olunanda,
        //    Laravel avtomatik EloquentPaymentRepository yaradacaq.
        //    EventDispatcher injection edilir ki, domain event-lər dispatch olunsun.
        $this->app->bind(PaymentRepositoryInterface::class, function ($app) {
            return new EloquentPaymentRepository(
                eventDispatcher: $app->make(\Src\Shared\Infrastructure\Bus\EventDispatcher::class),
            );
        });

        // 2. Strategy pattern — ödəniş gateway-lərini qeydiyyata al
        //    PaymentStrategyResolver-ə hansı gateway-lərin mövcud olduğunu bildiririk.
        //    Yeni gateway əlavə etmək üçün yalnız buraya bir sətir əlavə etmək kifayətdir!
        $this->app->singleton(PaymentStrategyResolver::class, function () {
            return new PaymentStrategyResolver([
                PaymentMethod::CREDIT_CARD => new CreditCardGateway(),
                PaymentMethod::PAYPAL => new PayPalGateway(),
                PaymentMethod::BANK_TRANSFER => new BankTransferGateway(),

                // Yeni gateway əlavə etmək çox asandır:
                // PaymentMethod::CRYPTO => new CryptoGateway(),
                // PaymentMethod::APPLE_PAY => new ApplePayGateway(),
            ]);
        });

        // 3. Circuit Breaker — konfiqurasiya ilə birlikdə qeydiyyata al
        //    singleton: Bütün application boyu BİR instansiya istifadə olunur.
        //    Nəyə? Çünki failure count paylaşılmalıdır — hər sorğuda yeni CircuitBreaker
        //    yaradılsa, say heç vaxt threshold-a çatmaz.
        $this->app->singleton(CircuitBreaker::class, function () {
            return new CircuitBreaker(
                failureThreshold: 5,    // 5 ardıcıl uğursuzluqdan sonra OPEN
                resetTimeoutSeconds: 30, // 30 saniyə sonra HALF_OPEN-a keçir
            );
        });

        // 4. Retry Handler — konfiqurasiya ilə birlikdə
        $this->app->singleton(RetryHandler::class, function () {
            return new RetryHandler(
                maxRetries: 3,      // Maksimum 3 təkrar cəhd
                baseDelayMs: 100,   // Baza gecikməl 100ms
                useJitter: true,    // Təsadüfi gecikməl aktiv
            );
        });

        // 5. Anti-Corruption Layer — sadəcə bind et (asılılığı yoxdur)
        $this->app->singleton(PaymentGatewayACL::class);
    }

    /**
     * Boot — application tam hazır olduqdan sonra çağırılır.
     * Event listener-lər, route-lar və s. burada qeydiyyata alınır.
     */
    public function boot(): void
    {
        // Gələcəkdə burada event listener-lər qeydiyyata alına bilər:
        // Event::listen(PaymentCompletedEvent::class, PublishPaymentCompletedIntegrationEvent::class);
    }
}
