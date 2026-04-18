<?php

declare(strict_types=1);

namespace Src\User\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Src\User\Domain\Repositories\UserRepositoryInterface;
use Src\User\Infrastructure\Repositories\EloquentUserRepository;

/**
 * USER SERVICE PROVIDER (Laravel + DDD)
 * =======================================
 * Bu ServiceProvider Laravel-in IoC Container-inə User modulunun
 * asılılıqlarını qeydiyyatdan keçirir.
 *
 * SERVICE PROVIDER NƏDİR?
 * - Laravel-in asılılıqları idarə etmə mexanizmidir.
 * - "Əgər kimsə UserRepositoryInterface istəsə, ona EloquentUserRepository ver"
 *   — bu qaydanı burada yazırıq.
 *
 * DEPENDENCY INJECTION (Dİ) NƏ ETDİYİMİZ:
 * - Handler constructor-da interface qəbul edir:
 *   __construct(UserRepositoryInterface $repo)
 * - Laravel avtomatik EloquentUserRepository inject edir.
 * - Handler konkret implementasiyanı BİLMİR — yalnız interface-i tanıyır.
 *
 * BU BİZƏ NƏ VERİR?
 * 1. Test zamanı: InMemoryUserRepository inject edə bilərsən (fake/mock).
 * 2. Baza dəyişəndə: Yalnız bu faylda bind-ı dəyişirsən, Handler-lərə toxunmursan.
 * 3. Loose coupling: Modullar bir-birinin konkret class-larından asılı deyil.
 *
 * INFRASTRUCTURE LAYER-DƏDİR çünki:
 * - ServiceProvider Laravel-ə (framework-ə) bağlıdır.
 * - Domain və Application layer heç bir framework-dən asılı olmamalıdır.
 * - Yalnız Infrastructure layer framework ilə işləyir.
 */
final class UserServiceProvider extends ServiceProvider
{
    /**
     * REGISTER METODU:
     * Burada interface → implementation binding-ləri qeydiyyatdan keçirilir.
     *
     * $this->app->bind(Interface, Implementation):
     * - "Əgər kimsə Interface istəsə, Implementation ver."
     * - Laravel avtomatik constructor injection edir.
     *
     * SINGLETON vs BIND:
     * - bind(): Hər istəkdə YENİ obyekt yaradır.
     * - singleton(): İlk istəkdə yaradır, sonrakılarda EYNI obyekti qaytarır.
     * - Repository üçün bind() istifadə edirik — hər request-də təzə olsun.
     */
    public function register(): void
    {
        $this->app->bind(
            UserRepositoryInterface::class,
            EloquentUserRepository::class,
        );
    }

    /**
     * BOOT METODU:
     * Bütün servis provider-lər register olunduqdan SONRA çağırılır.
     * Event listener-lar, route-lar, view composer-lər burada qeydiyyatdan keçirilir.
     *
     * Hələlik boşdur — gələcəkdə event listener binding-ləri əlavə oluna bilər.
     */
    public function boot(): void
    {
        //
    }
}
