# Real Use Cases (Lead)

## 1. Multi-language / Localization sistemi (Database-based)

**Problem:** Admin paneldən məhsulların adını, təsvirini müxtəlif dillərdə idarə etmək lazımdır.

```php
// translations table:
// id, translatable_type, translatable_id, locale, field, value

trait HasTranslations {
    public function translations(): MorphMany {
        return $this->morphMany(Translation::class, 'translatable');
    }

    public function translate(string $field, ?string $locale = null): ?string {
        $locale = $locale ?? app()->getLocale();

        // Cache ilə
        return Cache::remember(
            "translation:{$this->getMorphClass()}:{$this->id}:{$locale}:{$field}",
            3600,
            function () use ($field, $locale) {
                return $this->translations
                    ->where('locale', $locale)
                    ->where('field', $field)
                    ->first()?->value
                    ?? $this->translations
                        ->where('locale', config('app.fallback_locale'))
                        ->where('field', $field)
                        ->first()?->value
                    ?? $this->{$field}; // DB-dəki default dəyər
            }
        );
    }

    public function setTranslation(string $field, string $locale, string $value): void {
        $this->translations()->updateOrCreate(
            ['locale' => $locale, 'field' => $field],
            ['value' => $value],
        );

        Cache::forget("translation:{$this->getMorphClass()}:{$this->id}:{$locale}:{$field}");
    }

    // Bütün tərcümələri bir yerdə set et
    public function setTranslations(array $translations): void {
        // $translations = ['az' => ['name' => 'Telefon', 'desc' => '...'], 'en' => [...]]
        foreach ($translations as $locale => $fields) {
            foreach ($fields as $field => $value) {
                $this->setTranslation($field, $locale, $value);
            }
        }
    }
}

class Product extends Model {
    use HasTranslations;

    protected array $translatable = ['name', 'description', 'meta_title', 'meta_description'];
}

// İstifadə
$product->translate('name');            // Cari dildə
$product->translate('name', 'en');      // İngiliscə

// Admin panel-dən update
$product->setTranslations([
    'az' => ['name' => 'Simsiz qulaqlıq', 'description' => 'Bluetooth 5.0...'],
    'en' => ['name' => 'Wireless Headphones', 'description' => 'Bluetooth 5.0...'],
    'ru' => ['name' => 'Беспроводные наушники', 'description' => 'Bluetooth 5.0...'],
]);

// API response-da
class ProductResource extends JsonResource {
    public function toArray(Request $request): array {
        return [
            'id' => $this->id,
            'name' => $this->translate('name'),
            'description' => $this->translate('description'),
            'price' => $this->price,
        ];
    }
}
```

---

## 2. Subscription / Recurring Payment sistemi

**Problem:** SaaS platformada aylıq/illik abunəlik, plan dəyişmə, ləğv etmə.

```php
// subscriptions table:
// id, user_id, plan_id, status, stripe_subscription_id,
// trial_ends_at, current_period_start, current_period_end, cancelled_at

enum SubscriptionStatus: string {
    case Active = 'active';
    case Trialing = 'trialing';
    case PastDue = 'past_due';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
}

class SubscriptionService {
    public function __construct(private StripeClient $stripe) {}

    public function subscribe(User $user, Plan $plan, ?string $paymentMethodId = null): Subscription {
        // Stripe customer yarat (əgər yoxdursa)
        if (!$user->stripe_customer_id) {
            $customer = $this->stripe->customers->create([
                'email' => $user->email,
                'name' => $user->name,
                'payment_method' => $paymentMethodId,
                'invoice_settings' => ['default_payment_method' => $paymentMethodId],
            ]);
            $user->update(['stripe_customer_id' => $customer->id]);
        }

        $stripeSubscription = $this->stripe->subscriptions->create([
            'customer' => $user->stripe_customer_id,
            'items' => [['price' => $plan->stripe_price_id]],
            'trial_period_days' => $plan->trial_days,
            'metadata' => ['user_id' => $user->id],
        ]);

        return Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'stripe_subscription_id' => $stripeSubscription->id,
            'status' => $stripeSubscription->status === 'trialing'
                ? SubscriptionStatus::Trialing
                : SubscriptionStatus::Active,
            'trial_ends_at' => $plan->trial_days ? now()->addDays($plan->trial_days) : null,
            'current_period_start' => Carbon::createFromTimestamp($stripeSubscription->current_period_start),
            'current_period_end' => Carbon::createFromTimestamp($stripeSubscription->current_period_end),
        ]);
    }

    public function changePlan(Subscription $subscription, Plan $newPlan): Subscription {
        $stripeSubscription = $this->stripe->subscriptions->retrieve($subscription->stripe_subscription_id);

        // Proration — fərq hesablanır (upgrade/downgrade)
        $this->stripe->subscriptions->update($subscription->stripe_subscription_id, [
            'items' => [[
                'id' => $stripeSubscription->items->data[0]->id,
                'price' => $newPlan->stripe_price_id,
            ]],
            'proration_behavior' => 'create_prorations',
        ]);

        $subscription->update(['plan_id' => $newPlan->id]);

        PlanChanged::dispatch($subscription, $newPlan);

        return $subscription->fresh();
    }

    public function cancel(Subscription $subscription, bool $immediately = false): Subscription {
        if ($immediately) {
            $this->stripe->subscriptions->cancel($subscription->stripe_subscription_id);
            $subscription->update([
                'status' => SubscriptionStatus::Cancelled,
                'cancelled_at' => now(),
            ]);
        } else {
            // Dövr sonunda ləğv et
            $this->stripe->subscriptions->update($subscription->stripe_subscription_id, [
                'cancel_at_period_end' => true,
            ]);
            $subscription->update(['cancelled_at' => now()]);
        }

        SubscriptionCancelled::dispatch($subscription);

        return $subscription->fresh();
    }

    public function resume(Subscription $subscription): Subscription {
        if (!$subscription->cancelled_at) {
            throw new SubscriptionNotCancelledException();
        }

        // Period bitməyibsə resume etmək olar
        if ($subscription->current_period_end->isFuture()) {
            $this->stripe->subscriptions->update($subscription->stripe_subscription_id, [
                'cancel_at_period_end' => false,
            ]);

            $subscription->update([
                'cancelled_at' => null,
                'status' => SubscriptionStatus::Active,
            ]);
        }

        return $subscription->fresh();
    }
}

// Feature access based on plan
class User extends Model {
    public function hasFeature(string $feature): bool {
        $subscription = $this->activeSubscription;
        if (!$subscription) return false;

        return in_array($feature, $subscription->plan->features ?? []);
    }

    public function getActiveSubscriptionAttribute(): ?Subscription {
        return $this->subscriptions()
            ->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::Trialing])
            ->first();
    }
}

// Middleware
class RequireSubscription {
    public function handle(Request $request, Closure $next, string ...$features): Response {
        $user = $request->user();

        if (!$user->activeSubscription) {
            return response()->json(['error' => 'Abunəlik tələb olunur.', 'upgrade_url' => '/pricing'], 403);
        }

        foreach ($features as $feature) {
            if (!$user->hasFeature($feature)) {
                return response()->json([
                    'error' => "Bu xüsusiyyət planınıza daxil deyil: {$feature}",
                    'upgrade_url' => '/pricing',
                ], 403);
            }
        }

        return $next($request);
    }
}

Route::middleware('subscription:api_access,export')->group(function () {
    Route::get('/api/reports', ReportController::class);
});

// Stripe Webhook — subscription lifecycle
class HandleStripeSubscriptionWebhook implements ShouldQueue {
    public function handle(array $event): void {
        $stripeSubscription = $event['data']['object'];
        $subscription = Subscription::where('stripe_subscription_id', $stripeSubscription['id'])->first();

        if (!$subscription) return;

        match($event['type']) {
            'invoice.payment_succeeded' => $subscription->update([
                'status' => SubscriptionStatus::Active,
                'current_period_start' => Carbon::createFromTimestamp($stripeSubscription['current_period_start']),
                'current_period_end' => Carbon::createFromTimestamp($stripeSubscription['current_period_end']),
            ]),

            'invoice.payment_failed' => $this->handleFailedPayment($subscription),

            'customer.subscription.deleted' => $subscription->update([
                'status' => SubscriptionStatus::Expired,
            ]),
        };
    }

    private function handleFailedPayment(Subscription $subscription): void {
        $subscription->update(['status' => SubscriptionStatus::PastDue]);

        $subscription->user->notify(new PaymentFailedNotification($subscription));

        // 3 uğursuz cəhddən sonra abunəliyi dayandır
        $failedAttempts = $subscription->invoices()
            ->where('status', 'failed')
            ->where('created_at', '>=', now()->subMonth())
            ->count();

        if ($failedAttempts >= 3) {
            app(SubscriptionService::class)->cancel($subscription, immediately: true);
        }
    }
}
```

---

## 3. Invitation / Team Management sistemi

**Problem:** İstifadəçi komanda yaradır, emailə dəvətnamə göndərir, link ilə qoşulma.

```php
class TeamService {
    public function invite(Team $team, string $email, string $role = 'member'): Invitation {
        // Artıq komandada var?
        if ($team->members()->where('email', $email)->exists()) {
            throw new AlreadyMemberException($email);
        }

        // Artıq dəvət olunub?
        $existing = Invitation::where('team_id', $team->id)
            ->where('email', $email)
            ->where('status', 'pending')
            ->first();

        if ($existing) {
            // Yenidən göndər
            $existing->user?->notify(new TeamInvitationNotification($existing));
            return $existing;
        }

        $invitation = Invitation::create([
            'team_id' => $team->id,
            'email' => $email,
            'role' => $role,
            'token' => Str::random(64),
            'invited_by' => auth()->id(),
            'expires_at' => now()->addDays(7),
            'status' => 'pending',
        ]);

        // Email göndər (həm mövcud, həm yeni user üçün)
        Notification::route('mail', $email)->notify(
            new TeamInvitationNotification($invitation)
        );

        return $invitation;
    }

    public function accept(string $token, User $user): TeamMember {
        $invitation = Invitation::where('token', $token)
            ->where('status', 'pending')
            ->firstOrFail();

        if ($invitation->isExpired()) {
            throw new InvitationExpiredException();
        }

        if ($invitation->email !== $user->email) {
            throw new InvitationEmailMismatchException();
        }

        return DB::transaction(function () use ($invitation, $user) {
            $member = $invitation->team->members()->create([
                'user_id' => $user->id,
                'role' => $invitation->role,
            ]);

            $invitation->update(['status' => 'accepted', 'accepted_at' => now()]);

            MemberJoinedTeam::dispatch($invitation->team, $user);

            return $member;
        });
    }

    public function removeMember(Team $team, User $user): void {
        if ($team->owner_id === $user->id) {
            throw new CannotRemoveOwnerException();
        }

        $team->members()->where('user_id', $user->id)->delete();
        MemberRemovedFromTeam::dispatch($team, $user);
    }

    public function transferOwnership(Team $team, User $newOwner): void {
        if (!$team->members()->where('user_id', $newOwner->id)->exists()) {
            throw new NotAMemberException();
        }

        $team->update(['owner_id' => $newOwner->id]);
        OwnershipTransferred::dispatch($team, $newOwner);
    }
}
```

---

## 4. Wallet / Balance sistemi (Double-entry bookkeeping)

**Problem:** İstifadəçinin daxili balansı var. Yükləmə, xərcləmə, transfer, refund — hamısı izlənməli.

```php
// wallets table: id, user_id, balance, currency, created_at
// wallet_transactions table: id, wallet_id, type, amount, balance_after,
//   reference_type, reference_id, description, metadata, created_at

class WalletService {
    public function deposit(Wallet $wallet, float $amount, string $description, ?Model $reference = null): WalletTransaction {
        if ($amount <= 0) {
            throw new InvalidAmountException('Məbləğ müsbət olmalıdır.');
        }

        return DB::transaction(function () use ($wallet, $amount, $description, $reference) {
            // Pessimistic lock — race condition-a qarşı
            $wallet = Wallet::where('id', $wallet->id)->lockForUpdate()->first();

            $newBalance = $wallet->balance + $amount;

            $transaction = $wallet->transactions()->create([
                'type' => 'deposit',
                'amount' => $amount,
                'balance_after' => $newBalance,
                'description' => $description,
                'reference_type' => $reference ? get_class($reference) : null,
                'reference_id' => $reference?->id,
            ]);

            $wallet->update(['balance' => $newBalance]);

            return $transaction;
        });
    }

    public function withdraw(Wallet $wallet, float $amount, string $description, ?Model $reference = null): WalletTransaction {
        if ($amount <= 0) {
            throw new InvalidAmountException('Məbləğ müsbət olmalıdır.');
        }

        return DB::transaction(function () use ($wallet, $amount, $description, $reference) {
            $wallet = Wallet::where('id', $wallet->id)->lockForUpdate()->first();

            if ($wallet->balance < $amount) {
                throw new InsufficientBalanceException(
                    balance: $wallet->balance,
                    requested: $amount,
                );
            }

            $newBalance = $wallet->balance - $amount;

            $transaction = $wallet->transactions()->create([
                'type' => 'withdrawal',
                'amount' => -$amount,
                'balance_after' => $newBalance,
                'description' => $description,
                'reference_type' => $reference ? get_class($reference) : null,
                'reference_id' => $reference?->id,
            ]);

            $wallet->update(['balance' => $newBalance]);

            return $transaction;
        });
    }

    public function transfer(Wallet $from, Wallet $to, float $amount, string $description): array {
        if ($from->id === $to->id) {
            throw new SameWalletTransferException();
        }

        return DB::transaction(function () use ($from, $to, $amount, $description) {
            // Həmişə kiçik ID-li wallet-i əvvəl lock et — deadlock-a qarşı
            $wallets = collect([$from->id, $to->id])->sort()->values();
            Wallet::whereIn('id', $wallets)->lockForUpdate()->get();

            $from->refresh();
            $to->refresh();

            $withdrawal = $this->withdraw($from, $amount, "Transfer to #{$to->id}: {$description}");
            $deposit = $this->deposit($to, $amount, "Transfer from #{$from->id}: {$description}");

            return [$withdrawal, $deposit];
        });
    }

    public function refund(WalletTransaction $originalTransaction): WalletTransaction {
        if ($originalTransaction->type !== 'withdrawal') {
            throw new InvalidRefundException('Yalnız withdrawal refund oluna bilər.');
        }

        if ($originalTransaction->refunded_at) {
            throw new AlreadyRefundedException();
        }

        $wallet = $originalTransaction->wallet;
        $amount = abs($originalTransaction->amount);

        $refundTransaction = $this->deposit(
            $wallet,
            $amount,
            "Refund: {$originalTransaction->description}",
            $originalTransaction,
        );

        $originalTransaction->update(['refunded_at' => now()]);

        return $refundTransaction;
    }
}

// Statement / Hesabat
class WalletStatementService {
    public function getStatement(Wallet $wallet, Carbon $from, Carbon $to): array {
        $transactions = $wallet->transactions()
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('created_at')
            ->get();

        $openingBalance = $wallet->transactions()
            ->where('created_at', '<', $from)
            ->latest()
            ->value('balance_after') ?? 0;

        return [
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'opening_balance' => $openingBalance,
            'closing_balance' => $transactions->last()?->balance_after ?? $openingBalance,
            'total_deposits' => $transactions->where('type', 'deposit')->sum('amount'),
            'total_withdrawals' => abs($transactions->where('type', 'withdrawal')->sum('amount')),
            'transaction_count' => $transactions->count(),
            'transactions' => $transactions,
        ];
    }
}
```

---

## 5. Activity Feed / Timeline (Social media kimi)

**Problem:** İstifadəçi öz feed-ində friends-in aktivliklərini görür.

```php
// activities table:
// id, user_id, type, subject_type, subject_id, metadata, created_at

class ActivityFeedService {
    // Feed — fanout on read (kiçik/orta scale üçün)
    public function getFeed(User $user, int $perPage = 20): CursorPaginator {
        $followingIds = $user->following()->pluck('id');

        return Activity::whereIn('user_id', $followingIds->push($user->id))
            ->with(['user:id,name,avatar', 'subject'])
            ->latest()
            ->cursorPaginate($perPage);
    }

    // Fanout on write (böyük scale üçün — Redis)
    public function recordActivity(User $user, string $type, Model $subject, array $metadata = []): void {
        $activity = Activity::create([
            'user_id' => $user->id,
            'type' => $type,
            'subject_type' => get_class($subject),
            'subject_id' => $subject->id,
            'metadata' => $metadata,
        ]);

        // Follower-ların feed-inə yaz (async)
        FanoutActivityToFollowers::dispatch($activity);
    }
}

class FanoutActivityToFollowers implements ShouldQueue {
    public string $queue = 'feeds';

    public function __construct(private Activity $activity) {}

    public function handle(): void {
        $followerIds = $this->activity->user->followers()->pluck('id');

        foreach ($followerIds as $followerId) {
            // Redis sorted set — score = timestamp
            Redis::zadd(
                "feed:{$followerId}",
                $this->activity->created_at->timestamp,
                $this->activity->id,
            );

            // Feed ölçüsünü limitlə (son 1000 aktivlik)
            Redis::zremrangebyrank("feed:{$followerId}", 0, -1001);
        }
    }
}

// Redis-based feed oxuma
class RedisFeedService {
    public function getFeed(int $userId, int $page = 1, int $perPage = 20): array {
        $start = ($page - 1) * $perPage;
        $end = $start + $perPage - 1;

        $activityIds = Redis::zrevrange("feed:{$userId}", $start, $end);

        if (empty($activityIds)) return [];

        return Activity::whereIn('id', $activityIds)
            ->with(['user:id,name,avatar', 'subject'])
            ->orderByDesc('created_at')
            ->get()
            ->toArray();
    }
}

// Activity recording nümunələri
// Post yaradıldı
$feedService->recordActivity($user, 'created_post', $post, [
    'post_title' => $post->title,
]);

// Məhsul almaq
$feedService->recordActivity($user, 'purchased_product', $order, [
    'product_names' => $order->items->pluck('product_name')->toArray(),
    'total' => $order->total,
]);

// Review yazmaq
$feedService->recordActivity($user, 'wrote_review', $review, [
    'product_name' => $review->product->name,
    'rating' => $review->rating,
]);
```

---

## 6. Scheduled Maintenance Mode (Planned Downtime)

```php
class MaintenanceService {
    // Gələcəkdə planlaşdırılmış downtime
    public function schedule(Carbon $startsAt, Carbon $endsAt, string $reason): MaintenanceWindow {
        $window = MaintenanceWindow::create([
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'reason' => $reason,
            'status' => 'scheduled',
        ]);

        // 1 saat qabaq xəbərdarlıq
        NotifyUsersOfMaintenance::dispatch($window)
            ->delay($startsAt->copy()->subHour());

        // Avtomatik maintenance mode
        ActivateMaintenanceMode::dispatch($window)
            ->delay($startsAt);

        DeactivateMaintenanceMode::dispatch($window)
            ->delay($endsAt);

        return $window;
    }
}

// Middleware — maintenance banner göstər
class MaintenanceBanner {
    public function handle(Request $request, Closure $next): Response {
        $response = $next($request);

        $upcoming = MaintenanceWindow::where('starts_at', '<=', now()->addHour())
            ->where('ends_at', '>', now())
            ->where('status', 'scheduled')
            ->first();

        if ($upcoming && $response instanceof JsonResponse) {
            $data = $response->getData(true);
            $data['_maintenance'] = [
                'message' => "Texniki xidmət planlaşdırılıb: {$upcoming->starts_at->format('H:i')} - {$upcoming->ends_at->format('H:i')}",
                'starts_at' => $upcoming->starts_at->toISOString(),
            ];
            $response->setData($data);
        }

        return $response;
    }
}
```

---

## 7. Retry Pattern ilə External API inteqrasiyası

**Problem:** Xarici API bəzən 500 qaytarır, bəzən timeout olur. Stabil inteqrasiya lazımdır.

```php
class ResilientHttpClient {
    public function request(string $method, string $url, array $options = []): Response {
        $config = array_merge([
            'timeout' => 10,
            'retries' => 3,
            'retry_delay' => [100, 500, 2000], // ms — exponential
            'retry_on' => [500, 502, 503, 504],
            'circuit_breaker' => null,
        ], $options);

        $lastException = null;

        for ($attempt = 0; $attempt <= $config['retries']; $attempt++) {
            // Circuit breaker yoxla
            if ($config['circuit_breaker']) {
                $breaker = app(CircuitBreaker::class, ['service' => $config['circuit_breaker']]);
                if (!$breaker->isAvailable()) {
                    throw new ServiceUnavailableException("Circuit open: {$config['circuit_breaker']}");
                }
            }

            try {
                // Retry delay (ilk cəhd istisna)
                if ($attempt > 0) {
                    $delay = $config['retry_delay'][$attempt - 1] ?? end($config['retry_delay']);
                    usleep($delay * 1000);

                    Log::info("Retrying {$method} {$url}", ['attempt' => $attempt + 1]);
                }

                $response = Http::timeout($config['timeout'])
                    ->withHeaders($options['headers'] ?? [])
                    ->{strtolower($method)}($url, $options['body'] ?? []);

                if ($response->successful()) {
                    return $response;
                }

                // Retry edilməli status?
                if (!in_array($response->status(), $config['retry_on'])) {
                    return $response; // Retry etmə, nəticəni qaytar
                }

                $lastException = new HttpRequestException("HTTP {$response->status()}", $response->status());

            } catch (ConnectionException $e) {
                $lastException = $e;
                Log::warning("Connection failed: {$url}", ['error' => $e->getMessage()]);
            }
        }

        // Bütün cəhdlər uğursuz
        Log::error("All retries exhausted for {$method} {$url}", [
            'attempts' => $config['retries'] + 1,
        ]);

        throw $lastException ?? new ServiceUnavailableException("Failed after {$config['retries']} retries");
    }
}

// İstifadə
class ExchangeRateService {
    public function __construct(private ResilientHttpClient $http) {}

    public function getRate(string $from, string $to): float {
        // Əvvəlcə cache yoxla
        $cacheKey = "exchange_rate:{$from}:{$to}";
        $cached = Cache::get($cacheKey);
        if ($cached) return $cached;

        try {
            $response = $this->http->request('GET', "https://api.exchangerate.com/latest/{$from}", [
                'retries' => 3,
                'circuit_breaker' => 'exchange-rate-api',
            ]);

            $rate = $response->json("rates.{$to}");
            Cache::put($cacheKey, $rate, 300); // 5 dəqiqə cache
            return $rate;

        } catch (ServiceUnavailableException) {
            // Fallback — köhnə cache-dən istifadə et
            $staleRate = Cache::get("{$cacheKey}:stale");
            if ($staleRate) {
                Log::warning("Using stale exchange rate for {$from}/{$to}");
                return $staleRate;
            }
            throw new ExchangeRateUnavailableException();
        }
    }
}
```

---

## 8. Tagging sistemi (Polymorphic Many-to-Many)

```php
// tags table: id, name, slug, type (category, skill, topic), created_at
// taggables table: tag_id, taggable_type, taggable_id

trait HasTags {
    public function tags(): MorphToMany {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    public function syncTags(array $tagNames, string $type = 'general'): void {
        $tagIds = collect($tagNames)->map(function ($name) use ($type) {
            return Tag::firstOrCreate(
                ['slug' => Str::slug($name), 'type' => $type],
                ['name' => $name],
            )->id;
        });

        $this->tags()->sync($tagIds);
    }

    public function hasTag(string $tagSlug): bool {
        return $this->tags->contains('slug', $tagSlug);
    }

    public function scopeWithAnyTags(Builder $query, array $tagSlugs): Builder {
        return $query->whereHas('tags', fn ($q) => $q->whereIn('slug', $tagSlugs));
    }

    public function scopeWithAllTags(Builder $query, array $tagSlugs): Builder {
        foreach ($tagSlugs as $slug) {
            $query->whereHas('tags', fn ($q) => $q->where('slug', $slug));
        }
        return $query;
    }
}

// İstifadə
class Post extends Model { use HasTags; }
class Product extends Model { use HasTags; }
class Question extends Model { use HasTags; }

$post->syncTags(['php', 'laravel', 'api']);
$products = Product::withAnyTags(['electronics', 'sale'])->get();
$posts = Post::withAllTags(['php', 'tutorial'])->get();

// Popular tags
$popularTags = Tag::withCount('posts')
    ->orderByDesc('posts_count')
    ->limit(20)
    ->get();
```
