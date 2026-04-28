# Zero-Downtime Deployment (Senior)

## Mündəricat
1. [Deployment Strategiyaları](#deployment-strategiyaları)
2. [Blue/Green Deployment](#bluegreen-deployment)
3. [Canary Deployment](#canary-deployment)
4. [Rolling Deployment](#rolling-deployment)
5. [DB Migration Koordinasiyası](#db-migration-koordinasiyası)
6. [PHP İmplementasiyası](#php-implementasiyası)
7. [İntervyu Sualları](#intervyu-sualları)

---

## Deployment Strategiyaları

```
Zero-downtime = Deployment zamanı heç bir request xəta almır.

Köhnə üsul (Downtime):
  1. "Maintenance mode" aç
  2. Server-ları yenilə
  3. DB migration çalıştır
  4. "Maintenance mode" bağla
  Downtime: dəqiqələr - saatlar

Zero-downtime üsulları:
  Blue/Green    → İki mühit, traffic switch
  Canary        → Tədricən traffic yönləndir
  Rolling       → Instance-ları bir-bir yenilə
  Feature Flags → Kod deploy, feature sonra açılır

Hər üsulun ümumi tələbləri:
  - Health check endpoint
  - Graceful shutdown
  - DB backward compatibility
  - Stateless application (session DB-də)
```

---

## Blue/Green Deployment

```
İki eyni mühit: Blue (cari) və Green (yeni).

┌──────────────────────────────────────────────────┐
│                Load Balancer                     │
│           Traffic: Blue 100%                     │
└────────────────┬─────────────────────────────────┘
                 │
    ┌────────────▼───────────────┐
    │        Blue (v1)           │
    │  App-1  App-2  App-3       │
    └────────────────────────────┘

    ┌────────────────────────────┐
    │       Green (v2) - IDLE    │
    │  App-1  App-2  App-3       │
    └────────────────────────────┘

Deploy addımları:
  1. Green mühitinə yeni kod deploy et
  2. Green-dəki smoke test / health check
  3. Load Balancer: Blue → Green (instant switch!)
  4. Blue saxla (rollback üçün)
  5. Problem? → Load Balancer: Green → Blue (anında geri)

Blue-da traffic var → Green sınaqdan keçdi → switch
Rollback: LB-ni Blue-a çevir → çox sürətli!

Çatışmazlıqlar:
  - İki mühit = 2x infrastructure xərci
  - DB state: Blue ← → Green eyni DB-dən istifadə etməli
```

---

## Canary Deployment

```
Əvvəlcə az traffic, sonra daha çox.

                Load Balancer
           ┌─────────────────────┐
           │  v1: 95%  v2: 5%   │
           └────────┬─────┬──────┘
                    │     │
             ┌──────▼─┐  ┌▼──────┐
             │  v1    │  │  v2   │
             │ (köhnə)│  │(yeni) │
             └────────┘  └───────┘

Tədricən artır:
  5% → 10% → 25% → 50% → 100%

Hər mərhələdə:
  Error rate artıbmı?
  Latency artıbmı?
  Business metric düşürmü (conversion, etc.)?

Problem aşkarlandı:
  v2 traffic-i 0-a endir
  v2 instance-larını geri al
  Yalnız 5% user təsirləndi!

Header-based canary:
  X-Canary: true header olan requestlər v2-yə getsin.
  Internal testers + select users
```

---

## Rolling Deployment

```
Instance-ları bir-bir yenilə.

Başlanğıc: v1 x 4 instance
  [v1] [v1] [v1] [v1]

Addım 1: 1 instance yenilə
  [v2] [v1] [v1] [v1]  ← mixed version!

Addım 2:
  [v2] [v2] [v1] [v1]

Addım 3:
  [v2] [v2] [v2] [v1]

Son:
  [v2] [v2] [v2] [v2]

Önəmli:
  Rolling zamanı v1 + v2 EYNI ANDA işləyir!
  API backward compatible olmalıdır.
  DB schema hər iki versiyaya uyğun olmalıdır.

Min available:
  4 instance-dan 3-ü həmişə aktiv
  (1-i yenilənir)
  maxUnavailable: 1
  maxSurge: 1
```

---

## DB Migration Koordinasiyası

```
Ən çətin hissə: Schema dəyişikliyi deployment ilə əlaqələndirilir.

PROBLEM: Sütun adı dəyişdirilir
  old: column = "user_name"
  new: column = "username"

Yanlış yanaşma:
  1. DB-ni yenilə (rename)
  2. Kodu yenilə
  Rolling zamanı: köhnə kod yeni sütunu görmür → XƏTA!

Düzgün: Expand/Contract Pattern

Sütun əlavə etmək (3 deployment):
  Deploy 1: Yeni sütun əlavə et (nullable)
    ALTER TABLE users ADD COLUMN username VARCHAR(255);
    Köhnə kod: user_name oxuyur (hər ikisi var, nullable qəbul edir)

  Deploy 2: Kodu yenilə — hər ikisini doldur
    Yaz: username VƏ user_name
    Oxu: username-dən (user_name fallback)

  Deploy 3: Köhnə sütunu sil
    ALTER TABLE users DROP COLUMN user_name;
    Köhnə kod artıq production-da yoxdur.

Sütun silmək (2 deployment):
  Deploy 1: Kod sütunu artıq istifadə etməsin (migration yoxdur!)
  Deploy 2: Sütunu DB-dən sil

  İki deployment arası rolling period-da
  köhnə instance yeni schema ilə işləyir — problem yoxdur.
```

---

## PHP İmplementasiyası

```php
<?php
// Health Check Endpoint — Load Balancer üçün
class HealthController
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache'    => $this->checkCache(),
            'queue'    => $this->checkQueue(),
        ];

        $healthy = !in_array(false, $checks);

        return new JsonResponse(
            ['status' => $healthy ? 'ok' : 'degraded', 'checks' => $checks],
            $healthy ? 200 : 503
        );
    }

    private function checkDatabase(): bool
    {
        try {
            $this->db->query('SELECT 1');
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function checkCache(): bool
    {
        try {
            $key = 'health_check_' . uniqid();
            $this->cache->set($key, 1, 5);
            return $this->cache->get($key) === 1;
        } catch (\Throwable) {
            return false;
        }
    }
}
```

```php
<?php
// Graceful Shutdown — PHP-FPM üçün
// Kubernetes SIGTERM → FPM SIGTERM → Workers tamamlasın → Exit

// php-fpm.conf:
// process_control_timeout = 60s
// (SIGTERM-dən sonra 60s gözlə)

// Nginx upstream:
// upstream php_backend {
//     server unix:/var/run/php-fpm.sock;
//     keepalive 32;
// }

// Readiness vs Liveness probe (Kubernetes):
// Readiness: "Sorğu qəbul edə bilərəm?"
//   /health/ready → DB connection, cache OK?
// Liveness: "Proses işləyirmi?"
//   /health/live → sadəcə 200 qaytarır
```

```php
<?php
// Expand/Contract Migration nümunəsi (Laravel)

// Step 1: Yeni sütun əlavə et (nullable!)
return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->after('user_name');
        });

        // Mövcud dataları kopi et
        DB::statement('UPDATE users SET username = user_name');
    }
};

// Step 2: Application hər ikisini idarə edir
class UserObserver
{
    public function saving(User $user): void
    {
        // Köhnə sütunuda doldur (rolling deployment üçün)
        if ($user->username) {
            $user->user_name = $user->username;
        }
    }
}

// Step 3: Köhnə sütunu sil (ayrıca deployment-da)
return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('user_name');
        });
    }
};
```

---

## İntervyu Sualları

- Blue/Green vs Rolling deployment — hər birinin əsas trade-off-u nədir?
- DB migration zero-downtime deployment-ı niyə çətinləşdirir?
- Expand/Contract pattern nədir? Sütun silmək üçün neçə deployment lazımdır?
- Canary deployment rollback üçün blue/green-dən niyə yavaşdır?
- Rolling deployment zamanı v1 + v2 paralel işlədikdə API backward compatibility niyə vacibdir?
- Kubernetes readiness probe vs liveness probe fərqi nədir?
- Maintenance mode olmadan zero-downtime-ı necə həyata keçirərdiniz?

---

## Praktik Tapşırıqlar

1. Mövcud bir Laravel migration-ı götürün (sütun adını dəyişdirən): expand/contract pattern ilə 3 migration-a bölün; hər mərhələnin niyə ayrı deployment tələb etdiyini izah edin
2. `/health/ready` (readiness) və `/health/live` (liveness) endpointlər yarat: readiness DB+Redis+Queue yoxlasın, liveness sadəcə 200 qaytarsın
3. Nginx upstream konfiqurasiyasında health check əlavə edin: `health_check interval=5s fails=2`
4. Kubernetes Deployment YAML-ına readiness + liveness probe əlavə edin; readiness failure-ı simulyasiya edin (DB-ni dayandırın), trafikin dayandırıldığını yoxlayın
5. Bir "additive" migration yazın (yeni nullable sütun + indeks): migration zamanı production traffic-in kəsilmədiyini `pt-online-schema-change` ilə müqayisə edin
6. Blue-green simulate edin: iki local Docker container, Nginx `upstream` weight-i 0/100 → 100/0 dəyişdirin; session davamlılığını yoxlayın

## Əlaqəli Mövzular

- [Deployment Strategies](44-deployment-strategies.md) — Canary, A/B, Shadow, bütün strategiyalar
- [Infrastructure Patterns](27-infrastructure-patterns.md) — Deployer, Envoyer deployment tools
- [CI/CD Deployment](39-cicd-deployment.md) — Pipeline-da zero-downtime deploy
- [Nginx](11-nginx.md) — Load balancer, health check konfiqurasiyası
- [SLA/SLO/SLI](43-sla-slo-sli.md) — Availability hədəfləri, error budget
- [DORA Metrics](45-dora-metrics.md) — Change failure rate, MTTR ölçmə
