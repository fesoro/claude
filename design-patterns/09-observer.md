# Observer (Middle ⭐⭐)

## İcmal
Observer pattern bir obyektin (Subject/Observable) state dəyişikliyini ona "abunə" olmuş bütün obyektlərə (Observer/Listener) avtomatik bildirməsini təmin edir. Subject, Observer-ların kim olduğunu bilmir — yalnız bildirişi göndərir. Laravel-in Event/Listener sistemi bu pattern-in tam implementasiyasıdır.

## Niyə Vacibdir
İstifadəçi qeydiyyat olunanda email göndərilməli, analytics-ə event yazılmalı, admin bildirilməlidir. Bu üç işi `UserController::register()` metoduna birbaşa yazmaq həmin controller-i agır edir və Single Responsibility prinsipini pozur. Observer/Event sistemi bu üç işi ayrı Listener-lərə paylayır — controller yalnız qeydiyyatı tamamlayıb event fire edir, qalanı Listener-lərin işidir.

## Əsas Anlayışlar
- **Subject (Observable)**: Observer-ları saxlayır, notify edir — `attach()`, `detach()`, `notify()`
- **Observer**: `update()` metodu olan interface — bildiriş gəldikdə çağırılır
- **Push model**: Subject Observer-a data göndərir — `update($data)`
- **Pull model**: Subject yalnız xəbər verir, Observer özü data çəkir — `update($subject)`, `$subject->getData()`
- **Laravel Event**: Plain PHP class — `new UserRegistered($user)` — data daşıyır
- **Laravel Listener**: `handle(UserRegistered $event)` metodu olan class — eventi işləyir
- **Model Observer**: `User::observe(UserObserver::class)` — Eloquent lifecycle eventlərini dinləyir (`creating`, `created`, `updating`, `updated`, `deleting`, `deleted`)
- **ShouldQueue**: Listener `implements ShouldQueue` olduqda asyncron işləyir — heavy işlər üçün vacib

## Praktik Baxış
- **Real istifadə**: İstifadəçi qeydiyyatı, sifariş tamamlanması, ödəniş alınması, fayl yüklənməsi, model yaradılması/silinməsi zamanı əlavə əməliyyatlar
- **Trade-off-lar**: Çox Observer olduqda hansı Observer-ın nə etdiyi aydın olmur; Observer-lar bir-birini trigger edə bilər — sonsuz dövrə (infinite loop) riski; async Listener xətaları bir müddət gizli qalır (queue monitor lazımdır)
- **İstifadə etməmək**: Sadə, bir yerə bağlı (local) əməliyyatlar üçün — `user->sendWelcomeEmail()` birbaşa çağırmaq daha aydın ola bilər; Observer-ların transaction-a qoşulması lazım olduqda — async queue-da transaction problem yaranır
- **Common mistakes**: Ağır əməliyyatları synchronous Listener-də etmək (email göndərmək, report yaratmaq) — `ShouldQueue` istifadə et; Observer-da başqa event fire etmək; Model Observer-da N+1 problemi (hər model üçün əlavə sorğu)

## Nümunələr

### Ümumi Nümunə
Sifariş tamamlandıqda 4 iş baş verir: müştəriyə email, anbara stok azaltma bildirişi, admin dashboarduna real-time update, analitika sistemine event. Bu 4 işi `OrderController`-a yazmaq əvəzinə, `OrderCompleted` event fire edilir — hər biri ayrı Listener-dir. Yeni bir iş əlavə etmək üçün yalnız yeni Listener yaradılır, mövcud kod toxunulmur.

### PHP/Laravel Nümunəsi

```php
// ===== Laravel Events & Listeners =====

// 1. Event class — data container
class UserRegistered
{
    public function __construct(
        public readonly User $user,
        public readonly string $ipAddress,
        public readonly \DateTimeImmutable $registeredAt
    ) {}
}

class OrderCompleted
{
    public function __construct(
        public readonly Order $order,
        public readonly User $customer
    ) {}
}


// 2. Listeners
class SendWelcomeEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $queue = 'emails';
    public int $tries   = 3;

    public function handle(UserRegistered $event): void
    {
        Mail::to($event->user)->send(new WelcomeMail($event->user));
    }

    public function failed(UserRegistered $event, \Throwable $exception): void
    {
        Log::error('Welcome email failed', [
            'user_id' => $event->user->id,
            'error'   => $exception->getMessage(),
        ]);
    }
}

class TrackUserRegistration
{
    // ShouldQueue yoxdur — synchronous işləyir (sürətli, sadədir)
    public function handle(UserRegistered $event): void
    {
        UserAnalytics::create([
            'user_id'       => $event->user->id,
            'ip_address'    => $event->ipAddress,
            'registered_at' => $event->registeredAt,
        ]);
    }
}

class NotifyAdminOfNewUser implements ShouldQueue
{
    public string $queue = 'notifications';

    public function handle(UserRegistered $event): void
    {
        $admins = User::where('role', 'admin')->get();
        Notification::send($admins, new NewUserNotification($event->user));
    }
}


// 3. EventServiceProvider-də qeydiyyat
class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        UserRegistered::class => [
            SendWelcomeEmail::class,
            TrackUserRegistration::class,
            NotifyAdminOfNewUser::class,
        ],

        OrderCompleted::class => [
            SendOrderConfirmationEmail::class,
            DeductInventory::class,
            UpdateSalesAnalytics::class,
        ],
    ];
}


// 4. Controller-də event fire et — Listener-ləri bilmir
class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create($request->validated());

        event(new UserRegistered(
            user:         $user,
            ipAddress:    $request->ip(),
            registeredAt: new \DateTimeImmutable()
        ));

        return response()->json(['id' => $user->id], 201);
    }
}


// ===== Eloquent Model Observer =====
class UserObserver
{
    public function creating(User $user): void
    {
        // Hashed password kontrolu — artıq hash-ləndimi?
        if (!str_starts_with($user->password, '$2y$')) {
            $user->password = Hash::make($user->password);
        }
    }

    public function created(User $user): void
    {
        // Yeni user üçün default settings yarat
        UserSettings::create(['user_id' => $user->id]);
    }

    public function updating(User $user): void
    {
        if ($user->isDirty('email')) {
            $user->email_verified_at = null; // email dəyişdisə verify sıfırla
        }
    }

    public function deleted(User $user): void
    {
        // Soft delete — bağlı data-nı da soft delete et
        $user->orders()->update(['deleted_at' => now()]);
    }
}

// AppServiceProvider-də qeydiyyat
class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        User::observe(UserObserver::class);
    }
}


// ===== Vanilla PHP Observer (Laravel olmadan) =====
interface ObserverInterface
{
    public function update(string $event, mixed $data): void;
}

interface SubjectInterface
{
    public function attach(string $event, ObserverInterface $observer): void;
    public function detach(string $event, ObserverInterface $observer): void;
    public function notify(string $event, mixed $data): void;
}

class EventEmitter implements SubjectInterface
{
    private array $listeners = [];

    public function attach(string $event, ObserverInterface $observer): void
    {
        $this->listeners[$event][] = $observer;
    }

    public function detach(string $event, ObserverInterface $observer): void
    {
        $this->listeners[$event] = array_filter(
            $this->listeners[$event] ?? [],
            fn($o) => $o !== $observer
        );
    }

    public function notify(string $event, mixed $data): void
    {
        foreach ($this->listeners[$event] ?? [] as $observer) {
            $observer->update($event, $data);
        }
    }
}

class StockMonitor implements ObserverInterface
{
    public function update(string $event, mixed $data): void
    {
        if ($event === 'order.completed') {
            // stok azalt
        }
    }
}
```

## Praktik Tapşırıqlar
1. `PasswordChanged` event yaz: `User $user`, `string $newPasswordHash`, `string $changedFromIp` — `SendPasswordChangedNotification` (async, email), `RevokeAllSessions` (sync, bütün session-ları invalidate et), `LogSecurityEvent` (sync, audit log) Listener-lərini yaz
2. `ProductObserver` yaz: `saving` — slug avtomatik yarat (`str()->slug($product->name)`); `deleted` — bağlı `ProductImage` fayllarını Storage-dan sil; `restored` (soft delete geri qaytarıldı) — `updated_at` yenilə
3. Listener-in xətasını test et: `SendWelcomeEmail` Listener-ini `Mail::fake()` ilə test et; `Mail::assertSent(WelcomeMail::class)` ilə göndərildiyi yoxla; mailer-i failure simulyasiya et — `failed()` metodu çağırıldığını sübut et

## Əlaqəli Mövzular
- [16-event-listener.md](16-event-listener.md) — Laravel Event/Listener daha dərindən
- [11-command.md](11-command.md) — Command işi encapsulate edir, Observer onu trigger edir
- [20-state.md](20-state.md) — State dəyişikliyi Observer event-ini trigger etmək üçün istifadə olunur
