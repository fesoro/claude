# Strangler Fig Pattern (Lead ⭐⭐⭐⭐)

## İcmal
Strangler Fig Pattern Martin Fowler tərəfindən 2004-cü ildə təklif edilmiş, köhnə sistemi tədricən yeni sistemlə əvəz etmək üçün migration strategiyasıdır. Strangler fig bitkisinin ağacı tədricən örtüb öldürməsindən ilham alınmışdır. Köhnə sistemin (monolith, legacy) birbaşa əvəz edilməsi riskini minimuma endirərək tədricən yeni arxitekturaya keçidi təmin edir. Interview-da bu mövzu real migration təcrübənizi, risk idarəsi bacarığınızı ölçür.

## Niyə Vacibdir
"Big Bang" migration — bütün sistemi bir anda yenidən yazmaq — çox risklidir. İllər çəkir, əksərən uğursuz olur. Netscape Navigator-un yenidən yazılması klassik nümunədir — 3 il çəkdi, rəqiblər öndə keçdi. NHS-in patient record sistemi £12B xərclənib yarımçıq qaldı. Strangler Fig tədricən, hissə-hissə keçid imkanı verir — köhnə sistem işləməyə davam edir, yeni hissələr paralel inkişaf edir. Hər mərhələdə rollback mümkündür. Lead developer kimi bu strategiyanı bilmək legacy sistemlərlə işləmə əsas kompetensiya sayılır.

## Əsas Anlayışlar

- **Facade/Proxy**: Köhnə sistemin önünə yerləşdirilən router — bəzi endpoint-ləri yeni sistemə, qalanlarını köhnəyə yönləndirir. Nginx, API Gateway, ya da application-level proxy ola bilər. Bütün traffic buradan keçir.

- **Tədricən migration ardıcıllığı**: Ən sadə/az riskli hissədən başlamaq. Read-only endpoints (GET) → Write endpoints (POST/PUT) → Core business logic. Her mərhələdə öyrənmək, növbəti mərhələdə tətbiq etmək.

- **Parallel run (Shadow mode)**: Köhnə və yeni sistem eyni anda işləyir. Köhnə cavab verir (primary), yeni sistem shadow mode-da çağrılır. Cavablar müqayisə edilir, fərqlər log-lanır. Real traffic ilə test edilir, amma istifadəçiyə görünmür.

- **Strangler Application**: Köhnə sistemin əvəzinə qurulan yeni sistem. Zamanla böyüyür, köhnəni "boğur". Hər yeni feature yeni sistemdə yazılır — köhnəyə heç nə əlavə edilmir.

- **Event Interception**: Köhnə sistemdən event-lər tutulur. CDC (Change Data Capture) ilə DB dəyişiklikləri event-ə çevrilir. Yeni sistem bu event-ləri consume edib öz state-ini qurur.

- **Branch by Abstraction**: Kod səviyyəsində tədricən refactoring. Interface əlavə edilir, köhnə implementasiya bu interface-i implement edir, yeni implementasiya yazılır, feature flag ilə keçid edilir.

- **Feature Flag ilə tədricən aktivləşdirmə**: Yeni implementation feature flag arxasında. Əvvəl 1% → 5% → 25% → 50% → 100%. Xəta aşkar edilsə flag söndürülür, rollback dərhal olur.

- **Data migration challenge**: Köhnə DB-dən yeni DB-ə canlı vəziyyətdə məlumat köçürməsi ən çətin hissədir. Dual-write → backfill → verify → switch read → decommission köhnə.

- **Strangler olunan hissənin silinməsi**: Hər keçiddən sonra köhnə kod silinir. Bu vacibdir — silməsən dual maintenance başlayır, iki sistemin mövcudluğu davam edir.

- **Service seam**: Köhnə sistemdə ayrılma nöqtəsi. Burada microservice çıxarılır. Aydın interface-i olan hissələr daha asan ayrılır.

- **Anti-pattern — "Strangle amma silmə"**: Yeni sistem yazılır, köhnə silinmir. İki sistem paralel yaşayır, komplekslik ikiqat artır. "Paralel icad" — hər bug iki yerdə düzəltmək lazımdır.

- **Dark Launch**: Yeni kod istifadəçilərə görünmədən real traffic ilə test edilir. Shadow mode ilə oxşar, lakin dark launch-da tam production flow işləyir.

- **Rollback imkanı**: Hər mərhələdə köhnə sistemə geri qayıtmaq mümkün olmalıdır. Nginx config dəyişdirərək traffic-i köhnəyə qaytarmaq < 5 dəqiqə çəkməlidir.

- **Database dual-write**: Yeni service işləyərkən həm köhnə DB-yə, həm yeni DB-yə yazılır. Məlumat sinxronluğunu qorumaq üçün. Sonra yeni DB-dən oxumağa keçilir. Nəhayət köhnə DB-yə yazma dayandırılır.

- **Seçim meyarları — hansı hissədən başlamaq**: Ən az dependency, ən az risk, ən çox ayrışmış, ən aydın interface-i olan hissə. "Notification service" — digər service-lərdən az asılıdır, xətası kritik deyil.

- **Migration test strategiyası**: Hər köçürülmüş service üçün consumer-driven contract test. Facade düzgün yönləndirdiyini integration test. Shadow mode nəticələrinin müqayisəsi automated.

## Praktik Baxış

**Interview-da necə yanaşmaq:**
Bu mövzuda ən güclü cavab keçid ardıcıllığını izah etməkdir: 1) Facade qur, 2) ən az riskli hissəni seç, 3) yeni service-i implement et, 4) shadow mode, 5) traffic-i yavaş-yavaş keçir, 6) köhnə kodu sil, 7) növbəti hissəyə keç. Data migration strategiyasını da izah etmək artıdır. "Big bang rewrite niyə risklidir" sualına konkret uğursuz nümunə bilmək güclü izlenim bağlayır.

**Junior-dan fərqlənən senior cavabı:**
Junior: "Tədricən köçürərik."
Senior: "Notification service-i ilk seçərdim — ən az dependency, fault tolerance yüksək. Shadow mode ilə 2 həftə parallel işlədib cavabları müqayisə edərdim. 5% traffic ilə başlayıb 100%-ə çatardım. Köhnə kod silinməzdən əvvəl: zero traffic 48 saat, sonra sil."
Lead: "Strangler tamamlanmasının roadmap-ini product team-ə quarterly OKR kimi çatdırıram. Data migration üçün expand-contract pattern istifadə edirik — schema dəyişikliyi backward compatible qalır."

**Follow-up suallar:**
- "Hansı hissədən başlamaq lazımdır? Seçim meyarlarınız nədir?"
- "Data migration canlı sistemdə necə həyata keçirilir?"
- "Köhnə və yeni sistem arasında data sync necə idarə olunur?"
- "Nə vaxt bu pattern uyğun deyil?"
- "Köhnə sistemi silmək üçün hansı kriterlər lazımdır?"

**Ümumi səhvlər:**
- Ən mürəkkəb hissədən başlamaq — əksinə, ən sadə hissədən başlamalı
- Köhnə sistemi silməmək — strangle tamamlanmır, dual maintenance başlayır
- Data migration strategiyasını planlaşdırmamaq — ən riskli hissədir
- Facade-i permanent saxlamaq — facade da tədricən aradan qalxmalıdır
- Shadow mode nəticələrini avtomatik müqayisə etməmək — manual müqayisə vaxt aparır, insan xətası

**Yaxşı cavabı əla cavabdan fərqləndirən:**
Əla cavab konkret sequence verir: hansı service-ləri hansı ardıcıllıqla keçirərdiniz, traffic splitting strategiyası nədir (1%, 5%, 25%, 50%, 100%), database dual-write zamanı consistency necə qorunur. Risk mitigation üçün shadow mode automation-ı bilir.

## Nümunələr

### Tipik Interview Sualı
"Köhnə monolith-i microservices-ə keçirmək üçün strategiya izah edin. Big bang migration niyə risklidir?"

### Güclü Cavab
"Big bang migration risklidir — bütün komanın diqqəti yeni yazmağa yönəlir, köhnə sistemdəki funksionallığın 100%-ni əhatə etmək demək olar ki mümkün deyil, feature-lar qaçırılır. Strangler Fig istifadə edərdim. Mərhələ 1: Nginx facade qurardım, bütün traffic köhnə sistemə yönlənir. Mərhələ 2: Email notification service-i seçərdim — ən az dependency, xəta tolerable. Yeni service-i yazıb shadow mode-da işlədərdim: köhnə cavab verir, yeni parallel çalışır, fərqlər müqayisə edilir. 2 həftə sonra 5% traffic yeni service-ə keçirilir. Xəta yoxdursa həftəlik 10% → 25% → 50% → 100%. Köhnə kod 48 saat zero traffic-dən sonra silinir. Növbəti service-ə keçilir."

### Arxitektura Diaqramı

```
Mərhələ 1: Facade — bütün traffic köhnə sistemə
─────────────────────────────────────────────────
Client → [Nginx Facade] → [Legacy Monolith] → Legacy DB

Mərhələ 2: Notification Service köçürüldü
─────────────────────────────────────────────────
Client → [Nginx Facade] → /api/notifications → [New Notification Svc] → New DB
                        → /*                  → [Legacy Monolith]      → Legacy DB

Shadow Mode (paralel icra):
─────────────────────────────────────────────────
Client → [Nginx Facade] → [Legacy Monolith] → Legacy DB  (primary)
                        ↘ [Shadow Logger]  → [New Service] → Log diff

Mərhələ 3: 25% traffic yeni service-ə
─────────────────────────────────────────────────
Client → [Nginx Facade] → 75% → [Legacy Monolith]
                        → 25% → [New Notification Svc]

Mərhələ 4: Legacy silinib, növbəti service köçürülür
─────────────────────────────────────────────────
Client → [Nginx Facade] → /api/notifications → [New Notification Svc]
                        → /api/users        → [New User Svc]    (in progress)
                        → /*                → [Legacy Monolith]
```

### Kod Nümunəsi

```nginx
# Stage 1: Facade konfiqurasiyası — tədricən keçid
upstream legacy-system {
    server legacy-app:8080;
    keepalive 100;
}
upstream new-notification-service {
    server notification-svc:8080;
    keepalive 50;
}
upstream new-user-service {
    server user-svc:8080;
}

server {
    listen 80;

    # Stage 2 tamamlandı — notification tamamilə köçürüldü
    location /api/notifications {
        proxy_pass http://new-notification-service;
        proxy_set_header X-Service-Version "new";
    }

    # Stage 3 — user service 25% traffic ilə test edilir
    location /api/users {
        # split_clients ilə weighted traffic splitting
        set $upstream "";
        split_clients $request_id $upstream {
            25%    "new-user-service";
            *      "legacy-system";
        }
        proxy_pass http://$upstream;
    }

    # Hər şey hələ köhnə sistemdədir
    location / {
        proxy_pass http://legacy-system;
    }
}
```

```php
// Branch by Abstraction — Interface əlavə et, tədricən dəyişdir

// Step 1: Interface müəyyənləşdir
interface NotificationSender
{
    public function send(Notification $notification): void;
    public function sendBulk(array $notifications): void;
}

// Step 2: Köhnə implementasiya interface-i implement edir
class LegacyNotificationSender implements NotificationSender
{
    public function send(Notification $notification): void
    {
        // Köhnə, mürəkkəb legacy kod — dəyişdirilmir
        legacy_send_notification(
            $notification->userId,
            $notification->message,
            $notification->type
        );
    }

    public function sendBulk(array $notifications): void
    {
        foreach ($notifications as $n) {
            $this->send($n);
        }
    }
}

// Step 3: Yeni implementasiya — HTTP microservice çağırışı
class NewNotificationService implements NotificationSender
{
    public function __construct(
        private HttpClient $client,
        private string $serviceUrl,
        private Logger $logger
    ) {}

    public function send(Notification $notification): void
    {
        $response = $this->client->post("{$this->serviceUrl}/notifications", [
            'json' => [
                'user_id'  => $notification->userId,
                'message'  => $notification->message,
                'type'     => $notification->type,
                'priority' => $notification->priority ?? 'normal',
            ],
        ]);

        if ($response->getStatusCode() >= 400) {
            throw new NotificationException('New service returned error: ' . $response->getStatusCode());
        }
    }

    public function sendBulk(array $notifications): void
    {
        $this->client->post("{$this->serviceUrl}/notifications/bulk", [
            'json' => ['notifications' => array_map(fn($n) => [
                'user_id' => $n->userId,
                'message' => $n->message,
                'type'    => $n->type,
            ], $notifications)],
        ]);
    }
}

// Step 4: Shadow mode — ikisini çağır, nəticəni müqayisə et
class ShadowNotificationSender implements NotificationSender
{
    public function __construct(
        private NotificationSender $primary,   // köhnə
        private NotificationSender $shadow,    // yeni
        private Logger $logger,
        private MetricsCollector $metrics
    ) {}

    public function send(Notification $notification): void
    {
        // Köhnə həmişə primary (istifadəçiyə görünən cavab)
        $this->primary->send($notification);

        // Yeni shadow mode-da — xəta versə görünmür, sadəcə log-lanır
        try {
            $start = microtime(true);
            $this->shadow->send($notification);
            $duration = microtime(true) - $start;

            $this->metrics->increment('shadow.notification.success');
            $this->metrics->timing('shadow.notification.duration', $duration);
            $this->logger->info('Shadow notification sent', [
                'user_id'  => $notification->userId,
                'type'     => $notification->type,
                'duration' => $duration,
            ]);
        } catch (\Exception $e) {
            $this->metrics->increment('shadow.notification.failure');
            $this->logger->warning('Shadow notification failed', [
                'user_id' => $notification->userId,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    public function sendBulk(array $notifications): void
    {
        $this->primary->sendBulk($notifications);

        try {
            $this->shadow->sendBulk($notifications);
        } catch (\Exception $e) {
            $this->logger->warning('Shadow bulk notification failed: ' . $e->getMessage());
        }
    }
}

// Step 5: Feature flag ilə tədricən keçid
class FeatureFlaggedNotificationSender implements NotificationSender
{
    public function __construct(
        private NotificationSender $legacy,
        private NotificationSender $new,
        private FeatureFlags $flags,
        private Logger $logger
    ) {}

    public function send(Notification $notification): void
    {
        if ($this->flags->isEnabledForUser('new-notification-service', $notification->userId)) {
            $this->logger->info('Using new notification service', ['user_id' => $notification->userId]);
            $this->new->send($notification);
        } else {
            $this->legacy->send($notification);
        }
    }

    public function sendBulk(array $notifications): void
    {
        // Bulk operation-da: user bazında ayır
        $newServiceNotifications = [];
        $legacyNotifications = [];

        foreach ($notifications as $n) {
            if ($this->flags->isEnabledForUser('new-notification-service', $n->userId)) {
                $newServiceNotifications[] = $n;
            } else {
                $legacyNotifications[] = $n;
            }
        }

        if (!empty($newServiceNotifications)) {
            $this->new->sendBulk($newServiceNotifications);
        }
        if (!empty($legacyNotifications)) {
            $this->legacy->sendBulk($legacyNotifications);
        }
    }
}
```

```php
// Data Migration — Dual Write Strategy
// Köhnə DB-dən yeni DB-ə canlı migration

class OrderRepository
{
    public function __construct(
        private LegacyDB $legacyDb,
        private NewDB $newDb,
        private FeatureFlags $flags,
        private Logger $logger
    ) {}

    public function save(Order $order): void
    {
        // Həmişə köhnəyə yazılır (source of truth hələ köhnədir)
        $this->legacyDb->save($order);

        // Yeni DB-yə də paralel yazılır
        if ($this->flags->isEnabled('dual-write-orders')) {
            try {
                $this->newDb->save($order);
            } catch (\Exception $e) {
                // Log al amma exception atmaqdan çəkin
                // Köhnə DB primary-dir, yeni DB secondary
                $this->logger->error('Dual-write to new DB failed', [
                    'order_id' => $order->id(),
                    'error'    => $e->getMessage(),
                ]);
            }
        }
    }

    public function find(string $id): ?Order
    {
        if ($this->flags->isEnabled('read-from-new-db')) {
            $order = $this->newDb->find($id);
            if ($order === null) {
                // Yeni DB-də yoxdursa köhnədən al — backfill tamamlanmamış ola bilər
                $this->logger->warning('Order not found in new DB, falling back', ['id' => $id]);
                return $this->legacyDb->find($id);
            }
            return $order;
        }
        return $this->legacyDb->find($id);
    }
}

// Reconciliation Script — iki DB-nin sinxron olduğunu yoxla
class OrderReconciliationJob implements ShouldQueue
{
    public function handle(): void
    {
        $discrepancies = 0;

        // Son 24 saatın order-larını müqayisə et
        Order::where('created_at', '>', now()->subDay())
            ->chunk(500, function ($orders) use (&$discrepancies) {
                foreach ($orders as $legacyOrder) {
                    $newOrder = NewDB::find($legacyOrder->id);

                    if ($newOrder === null) {
                        Log::error("Reconciliation: Order missing in new DB", [
                            'id' => $legacyOrder->id
                        ]);
                        $discrepancies++;
                        // Auto-fix: yeni DB-yə kopyala
                        NewDB::insert($legacyOrder->toNewFormat());
                    } elseif ($legacyOrder->total !== $newOrder->total) {
                        Log::error("Reconciliation: Order total mismatch", [
                            'id'      => $legacyOrder->id,
                            'legacy'  => $legacyOrder->total,
                            'new'     => $newOrder->total,
                        ]);
                        $discrepancies++;
                    }
                }
            });

        Metrics::gauge('migration.discrepancies', $discrepancies);
        // $discrepancies > 0 olarsa alert göndər
    }
}
```

## Praktik Tapşırıqlar

1. Köhnə monolith-dən hansı service-i birinci çıxarardınız? Seçim meyarınızı izah edin — dependency sayı, risk, team ownership.
2. Shadow mode implement edin: köhnə və yeni service-i parallel çağırın, cavabları müqayisə edin, fərqləri log-layın.
3. Feature flag ilə 1% → 5% → 25% → 100% traffic keçidini simulate edin — her mərhələdə error rate monitor edin.
4. Dual-write strategiyası ilə data migration implement edin — reconciliation script yazın.
5. Nginx upstream-də `split_clients` ilə weighted traffic splitting konfiqurasiya edin.
6. "Strangler tamamlandı" kriteriyaları müəyyən edin: sıfır traffic köhnəyə, 48 saat izləmə, köhnə kod silindi.
7. Köhnə sistemdə yanlışlıqla yeni feature yazan developer-ı necə əngəlləyərsiniz? ADR, team agreement, CI check?
8. Strangle zamanı database schema dəyişikliyi lazımdırsa expand-contract pattern-i tətbiq edin.

## Əlaqəli Mövzular

- `01-monolith-vs-microservices.md` — Migration konteksti
- `12-feature-flags.md` — Tədricən keçiddə feature flag
- `15-technical-debt-management.md` — Migration = debt idarəsi
- `13-blue-green-canary.md` — Traffic keçid strategiyaları
