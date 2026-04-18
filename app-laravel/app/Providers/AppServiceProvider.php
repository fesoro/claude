<?php

namespace App\Providers;

use App\Events\OrderPlacedEvent;
use App\Events\PaymentProcessedEvent;
use App\Events\ProductStockChangedEvent;
use App\Listeners\CheckLowStockListener;
use App\Listeners\DispatchPaymentJobListener;
use App\Listeners\SendOrderConfirmationListener;
use App\Listeners\FailedJobNotificationListener;
use App\Listeners\UpdateOrderOnPaymentListener;
use Illuminate\Queue\Events\JobFailed;
use App\Observers\OrderObserver;
use App\Observers\PaymentObserver;
use App\Observers\ProductObserver;
use App\Policies\OrderPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\ProductPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Http\Request;
use Src\Order\Infrastructure\Models\OrderModel;
use Src\Payment\Infrastructure\Models\PaymentModel;
use Src\Product\Infrastructure\Models\ProductModel;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * POLICY QEYDİYYATI (Registration):
     * ==================================
     * Laravel 11-də Policy-lər avtomatik tapılır (auto-discovery) əgər:
     *   - app/Policies/ qovluğundadırsa
     *   - Model adı + "Policy" formatındadırsa (User → UserPolicy)
     *
     * AMMA bizim layihədə Model-lər DDD strukturunda yerləşir:
     *   src/Order/Infrastructure/Models/OrderModel.php
     * Laravel bunu avtomatik tapa bilmir çünki standart yerdə deyil.
     *
     * Ona görə əl ilə qeydiyyat edirik: Gate::policy(Model, Policy)
     * Bu Laravel-ə deyir: "OrderModel üçün OrderPolicy istifadə et"
     *
     * Gate::policy() vs $policies array (köhnə üsul):
     * ------------------------------------------------
     * Laravel 10-da AuthServiceProvider-da $policies array var idi.
     * Laravel 11-də AuthServiceProvider silinib, ona görə
     * Gate::policy() istifadə edirik — bu yeni və təmiz üsuldur.
     */
    /**
     * boot() — bütün provider-lər register() olduqdan SONRA çağırılır.
     *
     * Burada 3 şey qeydiyyat olunur:
     * 1. Policy-lər (Gate::policy) — authorization üçün
     * 2. Event-Listener bağlamaları (Event::listen) — business event-lər üçün
     * 3. Observer-lər (Model::observe) — model lifecycle üçün
     */
    public function boot(): void
    {
        // === POLICY QEYDİYYATI ===
        // Hər Model-i öz Policy-si ilə əlaqələndiririk
        // Bu olmadan $this->authorize() controller-də işləməz
        Gate::policy(OrderModel::class, OrderPolicy::class);
        Gate::policy(PaymentModel::class, PaymentPolicy::class);
        Gate::policy(ProductModel::class, ProductPolicy::class);

        // === RATE LIMITER QEYDİYYATI ===
        $this->configureRateLimiting();

        // === EVENT-LISTENER VƏ OBSERVER QEYDİYYATI ===
        $this->registerEventListeners();
        $this->registerObservers();
    }

    /**
     * EVENT → LISTENER BAĞLAMALARI
     * =============================
     *
     * Event::listen() — "Bu event baş verəndə, bu listener-i çağır" deməkdir.
     *
     * Bir event-ə BİR NEÇƏ listener bağlamaq mümkündür.
     * Listener-lər bir-birindən müstəqildir — biri uğursuz olsa digərinə təsir etmir.
     *
     * Alternativ yol: EventServiceProvider-da $listen array (köhnə üsul).
     * Laravel 11-dən sonra AppServiceProvider-da Event::listen() tövsiyə olunur.
     */
    /**
     * CUSTOM RATE LIMITING (Xüsusi Sorğu Məhdudlaşdırması)
     * =====================================================
     *
     * RATE LIMITING NƏDİR?
     * Müəyyən vaxt ərzində göndərilə bilən sorğu sayını məhdudlaşdırmaq.
     * Məqsəd: brute force hücum, spam, DDoS, API sui-istifadəsinin qarşısını almaq.
     *
     * NƏYƏ ENDPOINT-LƏRƏ GÖRƏ FƏRQLI LİMİT?
     * - Login: 5/dəq — brute force qoruması (şifrə təxmin etmə)
     * - Register: 3/dəq — spam hesab yaratmanın qarşısını almaq
     * - Payment: 10/dəq — ödəniş sui-istifadəsi (kartı yoxlamaq cəhdi)
     * - Orders: 20/dəq — sifariş spam
     * - Products (GET): 120/dəq — oxuma daha tez-tez olur, daha yüksək limit
     * - API (default): 60/dəq — ümumi limit
     *
     * RateLimiter::for() NECƏ İŞLƏYİR?
     * 1. Bir ad verir (məs: 'login')
     * 2. Callback limiti təyin edir (Limit::perMinute())
     * 3. Route-da istifadə: Route::middleware('throttle:login')
     *
     * Limit::perMinute(5)->by($request->ip())
     *   - perMinute(5): dəqiqədə 5 sorğu
     *   - by($request->ip()): hər IP üçün ayrı limit
     *   - by($request->user()?->id ?: $request->ip()): login olubsa user-ə, yoxsa IP-yə görə
     *
     * RESPONSE (limit aşıldıqda):
     * HTTP 429 Too Many Requests
     * Headers: Retry-After: 60, X-RateLimit-Limit: 5, X-RateLimit-Remaining: 0
     */
    private function configureRateLimiting(): void
    {
        // Default API limiti — ümumi endpoint-lər üçün
        // Autentifikasiya olunmuş user-lər daha yüksək limit alır
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(
                $request->user() ? 120 : 60  // Login: 120, Guest: 60
            )->by(
                $request->user()?->id ?: $request->ip()
            );
        });

        // Login limiti — brute force qoruması
        // IP əsaslı: eyni IP-dən dəqiqədə 5 login cəhdi
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by(
                $request->ip()
            )->response(function () {
                return response()->json([
                    'success' => false,
                    'message' => 'Çox sayda giriş cəhdi. 1 dəqiqə gözləyin.',
                ], 429);
            });
        });

        // Register limiti — spam hesab qoruması
        RateLimiter::for('register', function (Request $request) {
            return Limit::perMinute(3)->by(
                $request->ip()
            );
        });

        // Payment limiti — ödəniş sui-istifadəsi qoruması
        // Kart yoxlama hücumlarının qarşısını almaq üçün
        RateLimiter::for('payment', function (Request $request) {
            return Limit::perMinute(10)->by(
                $request->user()?->id ?: $request->ip()
            );
        });

        // Order limiti — sifariş spam qoruması
        RateLimiter::for('orders', function (Request $request) {
            return Limit::perMinute(20)->by(
                $request->user()?->id ?: $request->ip()
            );
        });

        // Products oxuma limiti — daha yüksək (oxuma tez-tez olur)
        RateLimiter::for('products', function (Request $request) {
            return Limit::perMinute(120)->by(
                $request->ip()
            );
        });

        // Plan əsaslı limit — tenant-ın abunəlik planına görə
        // Free: 5/dəq, Starter: 15/dəq, Pro: 60/dəq, Enterprise: 200/dəq
        RateLimiter::for('plan', function (Request $request) {
            $plan = \Src\Shared\Infrastructure\RateLimiting\PlanBasedRateLimiter::resolvePlan($request);
            $limit = \Src\Shared\Infrastructure\RateLimiting\PlanBasedRateLimiter::perMinuteLimitForPlan($plan);
            $key = \Src\Shared\Infrastructure\RateLimiting\PlanBasedRateLimiter::resolveKey($request);

            return Limit::perMinute($limit)->by($key)->response(function () use ($plan, $limit) {
                return response()->json([
                    'success' => false,
                    'message' => "Rate limit aşıldı. Plan: {$plan}, limit: {$limit}/dəqiqə.",
                    'upgrade_url' => '/pricing',
                ], 429);
            });
        });
    }

    private function registerEventListeners(): void
    {
        // Sifariş yaradılanda → ödəniş başlat + email göndər
        Event::listen(OrderPlacedEvent::class, DispatchPaymentJobListener::class);
        Event::listen(OrderPlacedEvent::class, SendOrderConfirmationListener::class);

        // Ödəniş emal edildikdə → sifariş statusunu yenilə
        Event::listen(PaymentProcessedEvent::class, UpdateOrderOnPaymentListener::class);

        // Stok dəyişdikdə → aşağı stok yoxlaması
        Event::listen(ProductStockChangedEvent::class, CheckLowStockListener::class);

        // Queue job uğursuz olduqda → admin-ə bildiriş
        Event::listen(JobFailed::class, FailedJobNotificationListener::class);
    }

    /**
     * OBSERVER QEYDİYYATI
     * ====================
     *
     * Model::observe() — bu model-in lifecycle hook-larını Observer izləyəcək.
     *
     * DİQQƏT: Observer yalnız Eloquent vasitəsilə olan əməliyyatlarda işləyir!
     * DB::table('orders')->insert([...]) → Observer trigger OLMAZ!
     * OrderModel::create([...]) → Observer trigger OLACAQ.
     */
    private function registerObservers(): void
    {
        ProductModel::observe(ProductObserver::class);
        OrderModel::observe(OrderObserver::class);
        PaymentModel::observe(PaymentObserver::class);
    }
}
