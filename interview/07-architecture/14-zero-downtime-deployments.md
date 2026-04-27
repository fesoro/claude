# Zero-Downtime Deployments (Lead ⭐⭐⭐⭐)

## İcmal
Zero-Downtime Deployment — istifadəçilərə heç bir xidmət kəsilişi olmadan yeni versiya çıxarmaq yanaşmasının kompleks mövzusudur. Sadəcə deployment strategiyası deyil — database migration, cache invalidation, session management, backward compatibility kimi bir neçə mövzunu əhatə edir. Interview-da bu mövzu production-da real deployment experience-inizi ölçür.

## Niyə Vacibdir
SLA 99.9% ← bu hər il yalnız 8.7 saat downtime deməkdir. Aylıq deployment schedule — hər release 30 dəqiqə maintenance window — artıq qəbul edilmir. E-commerce-də Black Friday-dəki deploy uğursuz olarsa şirkət milyonlar itirir. Zero-downtime deployment etmək üçün bütün pipeline-ı (kod, DB, cache, load balancer) nəzərə almaq lazımdır.

## Əsas Anlayışlar

- **Rolling Update**: Instance-lar bir-bir (ya qrup-qrup) yenilənir — heç vaxt bütün instance-lar eyni anda aşağı düşmür
- **Health Check**: Yeni instance-ın hazır olduğunu yoxlamaq — readinessProbe (traffic almağa hazır) vs livenessProbe (sağlıq)
- **Connection Draining**: Köhnə instance-a gələn aktiv sorğuların tamamlanmasına imkan vermək — terminationGracePeriodSeconds
- **Backward Compatible API**: Yeni versiyanın köhnə client-i dəstəkləməsi — additive changes
- **Database migration — forward compatibility**: Yeni kod həm köhnə, həm yeni schema ilə işləməlidir
- **Expand-Contract pattern**: Əvvəlcə genişlət (yeni column əlavə et), sonra köçür, sonra daralt (köhnəni sil)
- **Hot config reload**: Konfigurasiya dəyişikliyini server restart olmadan tətbiq etmək
- **Graceful shutdown**: SIGTERM siqnalı gəldikdə yeni sorğu qəbul etməyi dayandır, mövcud sorğuları tamamla
- **Cache versioning**: Cache key-lərinə versiyon əlavə etmək — `cache:v2:user:123` — köhnə cache-i invalidate etmədən yeni keş yaratmaq
- **Long-running requests**: WebSocket, SSE, chunked transfer — rolling update zamanı bunlar kəsilməsin
- **Queue workers**: Worker-lərin restart zamanı aktiv job-ları itirməmək — graceful shutdown
- **Session management**: Sticky session vs distributed session — rolling update zamanı session itirilməsin
- **Pre-flight checks**: Deploy başlamazdan əvvəl dependency-ləri yoxlamaq (DB connection, external service)
- **Post-deploy smoke tests**: Deploy bitdikdən sonra critical path-ları avtomatik test etmək

## Praktik Baxış

**Interview-da necə yanaşmaq:**
Bu mövzuda deployment strategiyasından çox database migration challenge-i haqqında danışın — bu daha çətin və daha maraqlıdır. Expand-contract pattern-i, graceful shutdown-ı, cache backward compatibility-ni izah edin.

**Follow-up suallar:**
- "DB migration-ı downtime olmadan necə edərsiniz?"
- "Rolling update zamanı iki versiyanı bir arada işlədərkən nə problemi ola bilər?"
- "Queue worker-ləri restart zamanı iş itirilmirmi?"
- "Session state rolling update-dən necə qorunur?"

**Ümumi səhvlər:**
- Deployment = zero-downtime düşünmək — yalnız kod deyil, DB migration, cache, session hamısı vacibdir
- Readiness probe olmadan rolling update — trafik hazır olmayan instance-a gedir
- Graceful shutdown-sız restart — aktiv sorğular kəsilir
- DB migration-ı deploy ilə eyni anda etmək — köhnə versiyanı pozur

**Yaxşı cavabı əla cavabdan fərqləndirən:**
Əla cavab database migration-ı deployment-dan ayrı bir process kimi idarə edir. "DB migration əvvəl, deploy sonra, köhnə schema silmə ən axırda" prinsipini bilir.

## Nümunələr

### Tipik Interview Sualı
"Zero-downtime deployment üçün database migration-ı necə idarə edərsiniz?"

### Güclü Cavab
"Database migration-ı deployment-dan tam ayırardım. Expand-contract pattern istifadə edərdim. Məsələn, `username` column-unu `first_name` + `last_name`-ə bölmək istəsəm: Step 1 — yeni column-ları əlavə edirəm (nullable), köhnə kod hələ `username` istifadə edir, yeni kod hər ikisini yazır. Step 2 — data migration script-i ilə köhnə datanı yeni column-lara köçürürəm. Step 3 — yeni kodu deploy edirəm, artıq yalnız `first_name`+`last_name` istifadə olunur. Step 4 — 1-2 deployment sonra köhnə `username` column-unu silirəm. Bu şəkildə heç bir mərhələdə sistem aşağı düşmür."

### Kod / Konfiqurasiya Nümunəsi

```yaml
# Kubernetes — Rolling Update + Health Checks
apiVersion: apps/v1
kind: Deployment
metadata:
  name: order-service
spec:
  replicas: 6
  strategy:
    type: RollingUpdate
    rollingUpdate:
      maxSurge: 2        # Eyni anda 2 əlavə instance qaldırıla bilər
      maxUnavailable: 0  # Heç bir instance aşağı düşməməlidir (zero downtime)
  template:
    spec:
      terminationGracePeriodSeconds: 30  # Graceful shutdown
      containers:
        - name: order-service
          image: myregistry/order-service:v1.6.0

          # Readiness Probe — traffic almağa hazır olduqda
          readinessProbe:
            httpGet:
              path: /health/ready
              port: 8080
            initialDelaySeconds: 5
            periodSeconds: 5
            successThreshold: 1
            failureThreshold: 3

          # Liveness Probe — işləyib-işlənmədiyini yoxlamaq
          livenessProbe:
            httpGet:
              path: /health/live
              port: 8080
            initialDelaySeconds: 30
            periodSeconds: 10
            failureThreshold: 3

          lifecycle:
            preStop:
              exec:
                # SIGTERM gəlməzdən əvvəl load balancer-dən çıxmaq üçün gözlə
                command: ["/bin/sh", "-c", "sleep 5"]
```

```php
// Health Check Endpoint
class HealthController extends Controller
{
    public function ready(): JsonResponse
    {
        $checks = [
            'database'  => $this->checkDatabase(),
            'redis'     => $this->checkRedis(),
            'queue'     => $this->checkQueue(),
        ];

        $allHealthy = !in_array(false, $checks);

        return response()->json([
            'status' => $allHealthy ? 'ready' : 'not_ready',
            'checks' => $checks,
        ], $allHealthy ? 200 : 503);
    }

    public function live(): JsonResponse
    {
        // Sadə: process sağdır
        return response()->json(['status' => 'alive']);
    }

    private function checkDatabase(): bool
    {
        try {
            DB::select('SELECT 1');
            return true;
        } catch (\Exception) {
            return false;
        }
    }
}

// Graceful Shutdown — PHP-FPM / Octane
// bootstrap/app.php və ya AppServiceProvider-da
class GracefulShutdownHandler
{
    public function register(): void
    {
        // SIGTERM gəldikdə yeni sorğu qəbulunu dayandır
        pcntl_signal(SIGTERM, function () {
            Log::info('SIGTERM received, stopping gracefully...');
            // Oktane: server.stop() çağır
            // FPM: FPM özü idarə edir terminationGracePeriodSeconds ilə
        });
    }
}

// Queue Worker — Graceful Shutdown
// Supervisor + Laravel Queue
// worker.conf:
// stopwaitsecs=30  ← Bu qədər saniyə gözlər mövcud iş tamamlansın

class ProcessOrderJob implements ShouldQueue
{
    public int $timeout = 25; // terminationGracePeriod-dan az olmalı

    public function handle(): void
    {
        // İş 25 saniyədən az çəkir — shutdown zamanı tamamlanır
        $this->processOrder();
    }
}
```

```php
// Cache Backward Compatibility — Versioned Cache Keys
class UserCacheService
{
    // Deployment zamanı köhnə cache-i invalidate etməyə ehtiyac yoxdur
    private const CACHE_VERSION = 'v2';

    public function get(int $userId): ?UserDTO
    {
        return Cache::remember(
            $this->key($userId),
            3600,
            fn() => $this->buildDTO($userId)
        );
    }

    public function invalidate(int $userId): void
    {
        Cache::forget($this->key($userId));
    }

    private function key(int $userId): string
    {
        return self::CACHE_VERSION . ':user:' . $userId;
        // v1:user:123 → v2:user:123 — v1 keşi öz-özünə expire olur
    }
}
```

```php
// Database Expand-Contract — Real Migration Sequence

// === STEP 1: EXPAND (köhnə kod işləyir, yeni migration deploy edilir) ===
// Migration: add_columns_to_users_table
Schema::table('users', function (Blueprint $table) {
    $table->string('first_name', 100)->nullable()->after('username');
    $table->string('last_name', 100)->nullable()->after('first_name');
});

// Model: hər iki column-a yazılır (backward + forward compatible)
class User extends Model
{
    protected static function booted(): void
    {
        static::saving(function (User $user) {
            // Köhnə sistemi dəstəklə: username hələ var
            if ($user->first_name && $user->last_name) {
                $user->username = $user->first_name . ' ' . $user->last_name;
            }
        });
    }
}

// === STEP 2: DATA MIGRATION (background job) ===
class MigrateUserNamesJob implements ShouldQueue
{
    public function handle(): void
    {
        User::whereNull('first_name')
            ->chunkById(500, function ($users) {
                foreach ($users as $user) {
                    [$first, $last] = $this->parseName($user->username);
                    $user->update([
                        'first_name' => $first,
                        'last_name'  => $last,
                    ]);
                }
            });
    }
}

// === STEP 3: SWITCH (yeni kod deploy edilir, yalnız new columns istifadə olunur) ===

// === STEP 4: CONTRACT (1-2 deployment sonra, köhnə kod tamam yoxdur) ===
// Migration: remove_username_from_users_table
Schema::table('users', function (Blueprint $table) {
    $table->dropColumn('username');
    $table->string('first_name', 100)->nullable(false)->change();
    $table->string('last_name', 100)->nullable(false)->change();
});
```

## Praktik Tapşırıqlar

- Laravel health check endpoint yazın — DB + Redis + Queue yoxlamaqla
- Kubernetes-də `maxUnavailable: 0` + `maxSurge: 1` ilə rolling update test edin
- Queue worker-i graceful shutdown üçün konfiqurasiya edin
- Expand-contract migration yazın: `full_address` column-unu `street`, `city`, `country`-ya bölün
- Cache versioning implement edin — version dəyişdikdə köhnə cache-in expire olmasını test edin

## Əlaqəli Mövzular

- `13-blue-green-canary.md` — Deployment strategiyaları
- `12-feature-flags.md` — Risk azaltma
- `10-strangler-fig.md` — Migration + zero-downtime
- `07-service-mesh.md` — Traffic management deployment zamanı
