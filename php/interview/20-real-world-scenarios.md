# Real-World Scenarios — Praktiki Ssenari Sualları

## 1. "Payment sistemi double charge edir, necə həll edərsiniz?"

**Root cause analizi:**
- Network timeout → client retry → ikinci charge
- Webhook duplicate delivery
- Race condition — iki eyni vaxtda request

**Həll:**
```php
class PaymentService {
    public function charge(Order $order, string $idempotencyKey): PaymentResult {
        // 1. Atomic lock — eyni anda yalnız bir request
        return Cache::lock("payment:{$order->id}", 30)->block(5, function () use ($order, $idempotencyKey) {

            // 2. Idempotency — artıq charge olunubsa eyni nəticəni qaytar
            $existing = Payment::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return PaymentResult::fromExisting($existing);
            }

            // 3. Order artıq ödənilibsə
            if ($order->isPaid()) {
                return PaymentResult::alreadyPaid();
            }

            // 4. Charge et
            $result = $this->gateway->charge($order->total, $order->paymentMethod);

            // 5. Payment record yarat
            Payment::create([
                'order_id' => $order->id,
                'idempotency_key' => $idempotencyKey,
                'amount' => $order->total,
                'status' => $result->success ? 'completed' : 'failed',
                'gateway_transaction_id' => $result->transactionId,
            ]);

            return $result;
        });
    }
}
```

---

## 2. "API response vaxtı 5 saniyədir, necə azaldarsınız?"

**Diaqnostika addımları:**
```php
// 1. Hansı sorğular yavaşdır?
DB::enableQueryLog();
// ... endpoint
$queries = DB::getQueryLog();
// Sorğu sayı? Ən yavaş sorğu?

// 2. N+1 problemi var?
Model::preventLazyLoading();

// 3. Xarici servis gecikməsi?
$start = microtime(true);
Http::get('external-api.com/data');
Log::info('External call: ' . (microtime(true) - $start) . 's');
```

**Həllər (prioritet sırası ilə):**
```php
// 1. Eager loading
User::with(['posts', 'profile', 'roles'])->get();

// 2. Database index
Schema::table('orders', fn ($t) => $t->index(['user_id', 'status']));

// 3. Caching
$data = Cache::remember('dashboard:' . $userId, 300, fn () => $this->buildDashboard($userId));

// 4. Queue — ağır işləri arxa plana
ProcessReport::dispatch($order); // Response dərhal qayıdır

// 5. Select yalnız lazımlı sütunları
User::select('id', 'name', 'email')->get();

// 6. Pagination
User::paginate(20); // Hamısını yükləmə

// 7. Xarici API-ləri paralel çağır
$responses = Http::pool(fn (Pool $pool) => [
    $pool->get('service-a.com/data'),
    $pool->get('service-b.com/data'),
    $pool->get('service-c.com/data'),
]); // 3 request paralel — 3x sürətli
```

---

## 3. "100K email göndərməlisiniz, necə edərsiniz?"

```php
// 1. Chunk + Queue
class SendBulkEmailsCommand extends Command {
    protected $signature = 'emails:send-bulk {campaign_id}';

    public function handle(): void {
        $campaign = Campaign::findOrFail($this->argument('campaign_id'));

        User::where('subscribed', true)
            ->chunkById(500, function ($users) use ($campaign) {
                foreach ($users as $user) {
                    SendCampaignEmail::dispatch($user, $campaign)
                        ->onQueue('bulk-emails');
                }
            });

        $this->info('All jobs dispatched!');
    }
}

// 2. Job with rate limiting
class SendCampaignEmail implements ShouldQueue {
    public int $tries = 3;
    public array $backoff = [60, 300, 900]; // Exponential

    public function middleware(): array {
        return [
            new RateLimited('email-sending'), // 50/saniyə limit
        ];
    }

    public function handle(): void {
        Mail::to($this->user)->send(new CampaignMail($this->campaign));
    }
}

// 3. Rate limiter
RateLimiter::for('email-sending', fn () => Limit::perSecond(50));

// 4. Horizon — 10 worker, ayrı queue
// config/horizon.php
'bulk-emails' => [
    'connection' => 'redis',
    'queue' => ['bulk-emails'],
    'balance' => 'auto',
    'minProcesses' => 2,
    'maxProcesses' => 10,
],
```

---

## 4. "Cədvəldə 50 milyon sətir var, migration necə edəcəksiniz?"

```php
// 1. Sütun əlavə etmə (sürətli — metadata dəyişikliyi)
// nullable sütun əlavə et — table lock yoxdur
$table->string('phone')->nullable();

// 2. Data migration — batch ilə
class BackfillPhoneNumbers extends Command {
    public function handle(): void {
        $total = User::whereNull('phone_migrated')->count();
        $bar = $this->output->createProgressBar($total);

        User::whereNull('phone_migrated')
            ->chunkById(5000, function ($users) use ($bar) {
                $updates = [];
                foreach ($users as $user) {
                    $updates[] = [
                        'id' => $user->id,
                        'phone' => $this->normalize($user->old_phone),
                        'phone_migrated' => true,
                    ];
                }

                // Batch upsert — tək sorğu ilə 5000 sətir
                User::upsert($updates, ['id'], ['phone', 'phone_migrated']);
                $bar->advance(count($users));

                // CPU/DB-yə nəfəs ver
                usleep(100000); // 100ms pauza
            });
    }
}

// 3. Online Schema Change (pt-online-schema-change)
// Böyük cədvəldə sütun tipi dəyişmək lazımdırsa:
// pt-online-schema-change --alter "MODIFY column_name VARCHAR(500)" D=app,t=users

// 4. Zero-downtime: dual-write strategiyası
// Addım 1: Yeni sütun əlavə et (nullable)
// Addım 2: Kod dəyiş — hər iki sütuna yaz
// Addım 3: Backfill — köhnə datanı yeni sütuna köçür
// Addım 4: Kodu dəyiş — yalnız yeni sütundan oxu
// Addım 5: Köhnə sütunu sil
```

---

## 5. "Multi-tenant SaaS necə quracaqsınız?"

```php
// Tenant middleware
class IdentifyTenant {
    public function handle(Request $request, Closure $next): Response {
        $tenant = Tenant::where('domain', $request->getHost())->firstOrFail();
        // Və ya subdomain: explode('.', $request->getHost())[0]

        app()->instance(Tenant::class, $tenant);
        config(['database.connections.tenant.database' => $tenant->database]);

        return $next($request);
    }
}

// Data isolation options:
// A. Shared DB + tenant_id (asan, amma riskli)
// B. Schema per tenant — PostgreSQL (orta)
// C. Database per tenant (təhlükəsiz, amma bahalı)

// Shared DB approach
trait BelongsToTenant {
    protected static function booted(): void {
        static::addGlobalScope('tenant', function (Builder $builder) {
            if ($tenant = app(Tenant::class)) {
                $builder->where('tenant_id', $tenant->id);
            }
        });

        static::creating(function (Model $model) {
            if ($tenant = app(Tenant::class)) {
                $model->tenant_id = $tenant->id;
            }
        });
    }
}

// Queue jobs-da tenant context
class TenantAwareJob implements ShouldQueue {
    public function __construct(
        private int $tenantId,
    ) {}

    public function handle(): void {
        $tenant = Tenant::find($this->tenantId);
        app()->instance(Tenant::class, $tenant);
        // İndi bütün sorğular bu tenant-a aiddir
    }
}
```

---

## 6. "API-nizi DDoS-dan necə qoruyarsınız?"

```php
// Layer 1: Cloudflare / AWS WAF (infrastructure)

// Layer 2: Nginx rate limiting
// limit_req_zone $binary_remote_addr zone=api:10m rate=10r/s;
// limit_req zone=api burst=20 nodelay;

// Layer 3: Laravel rate limiting
RateLimiter::for('api', function (Request $request) {
    return [
        // IP-yə görə
        Limit::perMinute(60)->by($request->ip()),
        // User-ə görə
        Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()),
    ];
});

// Layer 4: Specific endpoint protection
RateLimiter::for('login', function (Request $request) {
    return Limit::perMinute(5)->by(
        $request->input('email') . '|' . $request->ip()
    )->response(function () {
        return response()->json([
            'message' => 'Too many login attempts. Please try after 1 minute.',
        ], 429);
    });
});

// Layer 5: Suspicious activity detection
class DetectAbuseMiddleware {
    public function handle(Request $request, Closure $next): Response {
        $key = 'requests:' . $request->ip();
        $count = Cache::increment($key);

        if ($count === 1) {
            Cache::expire($key, 60);
        }

        if ($count > 300) { // 300 req/min — şübhəli
            Log::warning('Possible abuse', ['ip' => $request->ip(), 'count' => $count]);
            abort(429);
        }

        return $next($request);
    }
}
```

---

## 7. "Deployment zamanı 502 error oldu, nə edərsiniz?"

**İmmediately:**
```bash
# 1. Öncəki versiyaya rollback
cd /var/www/app && git checkout previous-tag
# və ya Envoyer-da "Revert" düyməsi

# 2. PHP-FPM/Nginx status yoxla
systemctl status php8.3-fpm
systemctl status nginx
tail -f /var/log/nginx/error.log
tail -f /var/www/app/storage/logs/laravel.log

# 3. Memory/disk yoxla
free -h
df -h

# 4. Ən çox yayılmış səbəblər:
# - composer install uğursuz (memory limit)
# - migration uğursuz
# - .env faylında xəta
# - Permission problemi (storage/, bootstrap/cache/)
# - PHP extension əskik
```

**Qarşısını almaq:**
```bash
# Zero-downtime deployment:
# 1. Yeni release folder
# 2. composer install
# 3. php artisan migrate
# 4. Testlər keçirsə → symlink dəyiş
# 5. php-fpm reload (graceful)

# Health check
curl -f http://localhost/health || rollback
```

---

## 8. "Yaddaş sızıntısı (memory leak) var, necə taparsınız?"

```php
// 1. Harda baş verir?
// Queue worker → uzun işləyir → yaddaş artır

// 2. Monitoring
Log::info('Memory: ' . memory_get_usage(true) / 1024 / 1024 . 'MB');

// 3. Ən çox yayılmış səbəblər:
// a) Query log aktiv qalıb
DB::disableQueryLog(); // Production-da

// b) Event listener-lər accumulate edir
// c) Static variable-lar böyüyür
// d) Eloquent model-lər yaddaşda qalır (Octane)

// 4. Həll — queue worker restart
php artisan queue:work --max-jobs=1000 --max-time=3600
// 1000 job-dan və ya 1 saatdan sonra restart

// 5. Debug
// Xdebug profiler
// Blackfire.io
// memory_get_usage() ilə manual tracking
```
