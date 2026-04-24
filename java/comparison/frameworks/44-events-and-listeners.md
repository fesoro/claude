# Event ve Listener (Hadiseler ve Dinleyiciler)

> **Seviyye:** Intermediate ⭐⭐

## Giris

Event-driven arxitektura tetbiqin muxtelif hisselerini bir-birinden ayirmaq (decoupling) ucun istifade olunan guclu bir pattern-dir. Bir sey bash verdikde (event/hadise), bu barede maraqli olan komponentler (listener/dinleyici) melumatlandirilir ve muvafiq emeliyyatlari icra edir.

Meselan, yeni istifadeci qeydiyyatdan kecdikde: email gondermek, admin-e bildiris vermek, statistikani yenilemek kimi emeliyyatlari ayri-ayri listener-lere havale etmek olar. Bu zaman qeydiyyat mentigi bu yan emeliyyatlar haqqinda hec ne bilmir.

Spring-de `ApplicationEvent` ve `@EventListener` mexanizmi, Laravel-de ise Event/Listener sistemi ve Event Broadcasting istifade olunur.

## Spring-de istifadesi

### Event sinifi yaratmaq

```java
// Sade event
public class UserRegisteredEvent {

    private final User user;
    private final LocalDateTime registeredAt;

    public UserRegisteredEvent(User user) {
        this.user = user;
        this.registeredAt = LocalDateTime.now();
    }

    public User getUser() { return user; }
    public LocalDateTime getRegisteredAt() { return registeredAt; }
}

// ApplicationEvent-den miras almaq (kohne usul, mecburi deyil)
public class OrderPlacedEvent extends ApplicationEvent {

    private final Order order;

    public OrderPlacedEvent(Object source, Order order) {
        super(source);
        this.order = order;
    }

    public Order getOrder() { return order; }
}
```

### Event yaymaq (publish)

```java
@Service
public class UserService {

    @Autowired
    private ApplicationEventPublisher eventPublisher;

    @Autowired
    private UserRepository userRepository;

    @Transactional
    public User register(UserRegistrationDto dto) {
        User user = new User();
        user.setEmail(dto.getEmail());
        user.setName(dto.getName());
        user.setPassword(passwordEncoder.encode(dto.getPassword()));

        User savedUser = userRepository.save(user);

        // Event yay -- kim dinleyirse ona catacaq
        eventPublisher.publishEvent(new UserRegisteredEvent(savedUser));

        return savedUser;
    }
}

@Service
public class OrderService {

    @Autowired
    private ApplicationEventPublisher eventPublisher;

    @Transactional
    public Order placeOrder(OrderDto dto) {
        Order order = createOrder(dto);
        orderRepository.save(order);

        eventPublisher.publishEvent(
            new OrderPlacedEvent(this, order));

        return order;
    }
}
```

### @EventListener ile dinlemek

```java
@Component
public class UserEventListeners {

    @Autowired
    private EmailService emailService;

    @Autowired
    private AnalyticsService analyticsService;

    // Sade listener
    @EventListener
    public void handleUserRegistered(UserRegisteredEvent event) {
        User user = event.getUser();
        emailService.sendWelcomeEmail(user.getEmail());
        log.info("Xos geldiniz emaili gonderildi: {}", user.getEmail());
    }

    // Shertli listener
    @EventListener(condition = "#event.user.role == 'ADMIN'")
    public void handleAdminRegistered(UserRegisteredEvent event) {
        log.warn("Yeni admin qeydiyyati: {}", event.getUser().getEmail());
        // Admin qeydiyyatini loglashdir
    }

    // Listener-den yeni event qaytarmaq (event chaining)
    @EventListener
    public WelcomeEmailSentEvent onUserRegistered(
            UserRegisteredEvent event) {
        emailService.sendWelcomeEmail(event.getUser().getEmail());
        // Qaytarilan obyekt yeni event kimi yayilir
        return new WelcomeEmailSentEvent(event.getUser());
    }
}

@Component
public class AnalyticsListener {

    @EventListener
    public void trackRegistration(UserRegisteredEvent event) {
        analyticsService.track("user_registered", Map.of(
            "email", event.getUser().getEmail(),
            "timestamp", event.getRegisteredAt().toString()
        ));
    }
}
```

### Asinxron event-ler

Default olaraq Spring event-leri sinxron icra olunur -- event publisher listener bitene qeder gozleyir. Asinxron etmek ucun:

```java
@Configuration
@EnableAsync
public class AsyncEventConfig {

    @Bean
    public ApplicationEventMulticaster applicationEventMulticaster() {
        SimpleApplicationEventMulticaster multicaster =
            new SimpleApplicationEventMulticaster();
        multicaster.setTaskExecutor(new SimpleAsyncTaskExecutor());
        return multicaster;
    }
}

// Ve ya ayri-ayri listener seviyyesinde
@Component
public class AsyncUserListeners {

    @Async
    @EventListener
    public void sendWelcomeEmail(UserRegisteredEvent event) {
        // Bu ayri thread-de icra olunur
        emailService.sendWelcomeEmail(event.getUser().getEmail());
    }

    // Bu sinxron qalir
    @EventListener
    public void updateStats(UserRegisteredEvent event) {
        statsService.incrementUserCount();
    }
}
```

### Transaction-a bagli event-ler

```java
@Component
public class OrderEventListeners {

    // Yalniz transaction ugurla commit olduqda icra olunur
    @TransactionalEventListener(phase = TransactionPhase.AFTER_COMMIT)
    public void handleOrderPlaced(OrderPlacedEvent event) {
        // Transaction ugurla bitdikden sonra email gonder
        emailService.sendOrderConfirmation(event.getOrder());
    }

    // Transaction rollback olduqda
    @TransactionalEventListener(phase = TransactionPhase.AFTER_ROLLBACK)
    public void handleOrderFailed(OrderPlacedEvent event) {
        log.error("Sifaris ugursuz oldu: {}",
                  event.getOrder().getId());
    }

    // Transaction bitmeye hazir olanda (commit-den evvel)
    @TransactionalEventListener(phase = TransactionPhase.BEFORE_COMMIT)
    public void beforeOrderCommit(OrderPlacedEvent event) {
        auditService.logOrderCreation(event.getOrder());
    }
}
```

### Generic Event-ler

```java
// Generic event sinifi
public class EntityCreatedEvent<T> {
    private final T entity;

    public EntityCreatedEvent(T entity) {
        this.entity = entity;
    }

    public T getEntity() { return entity; }
}

// Istifade
eventPublisher.publishEvent(
    new EntityCreatedEvent<>(newProduct));

@EventListener
public void handleProductCreated(
        EntityCreatedEvent<Product> event) {
    log.info("Yeni mehsul: {}", event.getEntity().getName());
}
```

## Laravel-de istifadesi

### Event ve Listener yaratmaq

```bash
php artisan make:event UserRegistered
php artisan make:listener SendWelcomeEmail --event=UserRegistered
```

**Event sinifi:**

```php
class UserRegistered
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public User $user
    ) {}
}

class OrderPlaced
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Order $order
    ) {}
}
```

**Listener sinifi:**

```php
class SendWelcomeEmail
{
    public function __construct(
        private EmailService $emailService
    ) {}

    public function handle(UserRegistered $event): void
    {
        $this->emailService->sendWelcome($event->user->email);
    }
}

class UpdateUserStatistics
{
    public function handle(UserRegistered $event): void
    {
        Statistics::increment('total_users');
        Statistics::recordRegistration($event->user);
    }
}

class NotifyAdminOfNewUser
{
    public function handle(UserRegistered $event): void
    {
        $admins = User::where('role', 'admin')->get();
        Notification::send(
            $admins,
            new NewUserNotification($event->user)
        );
    }
}
```

### Event qeydiyyati

**EventServiceProvider:**

```php
class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        UserRegistered::class => [
            SendWelcomeEmail::class,
            UpdateUserStatistics::class,
            NotifyAdminOfNewUser::class,
        ],
        OrderPlaced::class => [
            SendOrderConfirmation::class,
            ReserveInventory::class,
            NotifyWarehouse::class,
        ],
    ];

    // Avtomatik event discovery -- listener-ler
    // handle metodundaki type-hint-e gore tapilir
    public function shouldDiscoverEvents(): bool
    {
        return true;
    }
}
```

### Event yaymaq (dispatch)

```php
class UserController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // Event yay -- 3 ferkli usul
        event(new UserRegistered($user));
        // ve ya
        UserRegistered::dispatch($user);
        // ve ya
        Event::dispatch(new UserRegistered($user));

        return response()->json($user, 201);
    }
}
```

### Queued Listener (Asinxron)

Listener-i novbeye gondermek ucun `ShouldQueue` implement etmek kifayetdir:

```php
class SendWelcomeEmail implements ShouldQueue
{
    use InteractsWithQueue;

    // Hansı novbe istifade olunsun
    public string $queue = 'emails';

    // Gecikmeli icra
    public int $delay = 10;

    // Retry sayi
    public int $tries = 3;

    public function handle(UserRegistered $event): void
    {
        Mail::to($event->user->email)
            ->send(new WelcomeEmail($event->user));
    }

    // Ugursuz olduqda
    public function failed(
        UserRegistered $event,
        \Throwable $exception
    ): void {
        Log::error('Xos geldiniz emaili gonderilemedi', [
            'user_id' => $event->user->id,
            'error' => $exception->getMessage(),
        ]);
    }

    // Bu listener-i icra etmek lazimdir?
    public function shouldQueue(UserRegistered $event): bool
    {
        return $event->user->wants_emails;
    }
}
```

### Event Subscribers

Bir sinifde birden cox event-i dinlemek:

```php
class UserEventSubscriber
{
    public function handleUserRegistered(UserRegistered $event): void
    {
        Log::info('Yeni istifadeci: ' . $event->user->email);
    }

    public function handleUserLoggedIn(UserLoggedIn $event): void
    {
        $event->user->update(['last_login_at' => now()]);
    }

    public function handleUserDeleted(UserDeleted $event): void
    {
        Log::info('Istifadeci silindi: ' . $event->user->email);
    }

    /**
     * Subscriber-i qeydiyyatdan kecir
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            UserRegistered::class => 'handleUserRegistered',
            UserLoggedIn::class => 'handleUserLoggedIn',
            UserDeleted::class => 'handleUserDeleted',
        ];
    }
}

// EventServiceProvider-de:
protected $subscribe = [
    UserEventSubscriber::class,
];
```

### Event Broadcasting (Real-time)

Laravel event-leri WebSocket vasitesile frontend-e yaymaga imkan verir:

```php
class OrderStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Order $order
    ) {}

    // Hansı kanala yayilsin
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('orders.' . $this->order->user_id),
        ];
    }

    // Yayimlanan data
    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->order->id,
            'status' => $this->order->status,
            'updated_at' => $this->order->updated_at->toISOString(),
        ];
    }

    // Event adi (frontend-de dinlenecek)
    public function broadcastAs(): string
    {
        return 'order.updated';
    }
}
```

**Frontend-de dinlemek (Laravel Echo):**

```javascript
Echo.private(`orders.${userId}`)
    .listen('.order.updated', (event) => {
        console.log('Sifaris yenilendi:', event.order_id);
        console.log('Yeni status:', event.status);
        updateOrderUI(event);
    });
```

### Model Observers

Eloquent model hadiselerini dinlemek ucun:

```php
// php artisan make:observer UserObserver --model=User

class UserObserver
{
    public function creating(User $user): void
    {
        $user->uuid = Str::uuid();
    }

    public function created(User $user): void
    {
        Log::info("Istifadeci yaradildi: {$user->id}");
        Cache::forget('users_count');
    }

    public function updating(User $user): void
    {
        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }
    }

    public function updated(User $user): void
    {
        Cache::forget("user:{$user->id}");
    }

    public function deleted(User $user): void
    {
        // Elageli verilenleri temizle
        Storage::delete("avatars/{$user->id}");
        Cache::forget("user:{$user->id}");
    }

    public function forceDeleted(User $user): void
    {
        // Tam silindikde
    }
}

// Qeydiyyat (AppServiceProvider)
User::observe(UserObserver::class);

// Ve ya model-de attribute ile
#[ObservedBy(UserObserver::class)]
class User extends Authenticatable
{
    // ...
}
```

## Esas ferqler

| Xususiyyet | Spring | Laravel |
|---|---|---|
| **Event sinifi** | POJO (her hansi sinif ola biler) | `Dispatchable` trait istifade edir |
| **Listener qeydiyyati** | Avtomatik (`@EventListener`) | `EventServiceProvider`-de ve ya avtomatik |
| **Event yaymaq** | `ApplicationEventPublisher` | `event()`, `Event::dispatch()` |
| **Asinxron** | `@Async` + `@EventListener` | `ShouldQueue` interface |
| **Transaction** | `@TransactionalEventListener` | Yoxdur (manual idare) |
| **Broadcasting** | Yoxdur (xarici hell) | `ShouldBroadcast` daxili |
| **Model Observer** | JPA `@EntityListeners` | Eloquent Observer |
| **Event Chaining** | Listener-den event qaytarmaq | Manual dispatch |
| **Shertli listener** | `condition` SpEL ifadesi | `shouldQueue()` metodu |
| **Event Discovery** | Avtomatik (`@EventListener`) | `shouldDiscoverEvents()` |

## Niye bele ferqler var?

**Spring-in yanasmasi -- annotation-based ve sinxron default:**
Spring-de event-ler default olaraq sinxron icra olunur -- bu, transaction icerisinde event-lerin etibarlı islenmesini temin edir. `@TransactionalEventListener` xususi annotasiya ile event-i transaction fazalarina baglamaq mumkundur -- meselen, yalniz commit-den sonra email gondermek. Bu enterprise tetbiqlerde data consistency ucun cox vacibdir.

**Laravel-in yanasmasi -- sade ve broadcasting ile:**
Laravel event sistemini sade saxlayir ve event broadcasting ile genishlendirir. `ShouldBroadcast` interface-i ile event-leri WebSocket vasitesile real-time olaraq frontend-e catdirmaq mumkundur -- bu, SPA (Single Page Application) ve mobile app-ler ucun eladir. Spring-de buna benzer funksionalliq ucun Spring WebSocket ve ya xarici hell (Pusher, Socket.IO) lazimdir.

**Model Observers:**
Laravel-in Eloquent Observer-leri model lifecycle hadiseleri ucun meqsede uygun hell teqdim edir -- `creating`, `created`, `updating`, `updated`, `deleting`, `deleted` kimi hadiseler avtomatik fire olunur. Spring-de JPA `@EntityListeners` oxsar imkan verir, amma Laravel-in Observer-leri daha temiz ve istifadesi asandir.

## Hansi framework-de var, hansinda yoxdur?

**Yalniz Spring-de:**
- `@TransactionalEventListener` -- event-i transaction fazalarina baglamaq (AFTER_COMMIT, AFTER_ROLLBACK, BEFORE_COMMIT)
- Event chaining -- listener-den event qaytarmaq ile avtomatik yeni event yaymaq
- SpEL ile shertli listener (`condition = "#event.user.role == 'ADMIN'"`)
- `ApplicationEventMulticaster` -- qlobal event dispatch strategiyasini deyishdirmek

**Yalniz Laravel-de:**
- Event Broadcasting -- `ShouldBroadcast` ile real-time WebSocket yayin
- Laravel Echo -- frontend-de event dinleme kitabxanasi
- Model Observers -- Eloquent model lifecycle hadiselerini dinlemek
- `shouldDiscoverEvents()` -- listener-lerin avtomatik tapilmasi
- Event Subscriber -- bir sinifde birden cox event-i dinlemek
- `broadcastOn()`, `broadcastAs()`, `broadcastWith()` -- broadcasting konfiqurasiyasi
- Private/Presence kanallar -- broadcasting ucun avtorizasiya
