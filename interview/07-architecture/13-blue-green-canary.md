# Blue-Green and Canary Deployments (Lead ⭐⭐⭐⭐)

## İcmal
Blue-Green və Canary Deployment — production-da zero (ya da minimum) downtime ilə yeni versiya çıxarmaq üçün istifadə olunan deployment strategiyalarıdır. Blue-Green tam hazır ikinci mühitə birbaşa keçid edir, Canary isə trafiği tədricən yeni versiyaya yönləndirir. Interview-da bu mövzu continuous delivery, risk management, production operations biliyini ölçür.

## Niyə Vacibdir
"Deploy edib dua etmək" dövrü bitdi. Böyük şirkətlər gündə yüzlərlə deploy edir, hər biri risk daşıyır. Blue-Green anında rollback imkanı verir. Canary problemi yalnız kiçik user qrupunda tutmağa imkan verir. Bu strategiyaları bilmək production-a confidence ilə deploy edə biləcəyinizi göstərir. DevOps kültürünün əsas elementlərindəndir.

## Əsas Anlayışlar

- **Blue-Green Deployment**: İki eyni production mühiti — Blue (cari), Green (yeni). Keçid load balancer switch ilə
- **Instant rollback**: Green problem yaratsa load balancer Blue-ya geri keçirilir — 0 downtime
- **Warm-up**: Green mühit trafiк almadan əvvəl JVM warm-up, cache fill edilə bilər
- **Database migration challenge**: Blue-Green-in ən çətin hissəsi — schema dəyişiklikləri hər iki versiyanı dəstəkləməlidir
- **Canary Deployment**: Yeni versiya əvvəlcə kiçik faizə (1%, 5%) göstərilir, problem yoxsa artırılır
- **Canary analysis**: Canary qrupu ilə control qrupu arasında metrikalar müqayisəsi — error rate, latency, business metric
- **Automated rollback**: Canary metrikası threshold-u keçsə avtomatik rollback
- **Traffic splitting**: Nginx weighted upstream, Istio VirtualService, Kubernetes service mesh
- **Shadow deployment**: Yeni versiya real traffic kopyasını alır amma cavab göndərmir — risk sıfır test
- **A/B Testing fərqi**: A/B → business metric (conversion), Canary → technical metric (error rate, latency)
- **Rolling deployment**: Instance-ları bir-bir yeniləmək — Kubernetes default-u. Yavaş amma bütün instance-lar dəyişir
- **Recreate deployment**: Köhnəni sil, yenisini qur — downtime var, development üçün istifadə olunur
- **Version coexistence**: Canary zamanı iki versiya eyni anda işləyir — API backward compatibility vacibdir
- **Kubernetes strategies**: `strategy.type: RollingUpdate`, `Canary` (Argo Rollouts ilə), `BlueGreen` (Argo Rollouts ilə)

## Praktik Baxış

**Interview-da necə yanaşmaq:**
Bu mövzuda Blue-Green vs Canary fərqini, hər birinin nə vaxt uyğun olduğunu izah edin. Database migration-ı necə idarə edəcəyinizi — expand-contract pattern — vurğulayın. Automated rollback meyarlarını (SLO threshold) bildirin.

**Follow-up suallar:**
- "Schema dəyişikliyi Blue-Green ilə necə idarə olunur?"
- "Canary analiz meyarlarınız nələrdir — rollback nə vaxt trigger olunur?"
- "Rolling deployment-dən Canary-nin fərqi nədir?"
- "Shadow deployment nə vaxt istifadə olunur?"

**Ümumi səhvlər:**
- Database migration-ı Blue-Green ilə birbaşa etmək — köhnə versiyanı pozur
- Canary-ni monitoring olmadan etmək — problem 30 dəqiqə sonra anlaşılır
- Blue-Green mühiti deployment-dən sonra söndürmək — rollback üçün hazır saxlanmalıdır
- Canary faizini çox sürətli artırmaq

**Yaxşı cavabı əla cavabdan fərqləndirən:**
Əla cavab database expand-contract pattern-ni izah edə bilir, SLO-based automated rollback meyarlarını bilir, Argo Rollouts kimi concrete tool-lardan danışır.

## Nümunələr

### Tipik Interview Sualı
"Blue-Green vs Canary Deployment — fərqi nədir, nə zaman hansını seçərsiniz?"

### Güclü Cavab
"Blue-Green tam hazır ikinci mühitə birbaşa keçiddir — anında rollback imkanı var, amma iki tam mühit xərci var. Canary isə trafiqi tədricən keçirir — 1%, 5%, 25% — hər mərhələdə metrikalar yoxlanılır. Blue-Green qısa switchover lazım olanda (maintenance window qısa), Canary isə risk minimuma endirilmək istəndikdə seçilir. Database migration üçün expand-contract pattern lazımdır — əvvəlcə yeni column əlavə et (hər iki versiyanı dəstəkləyir), köçür, sonra köhnə column-u sil."

### Kod / Konfiqurasiya Nümunəsi

```yaml
# Kubernetes — Blue-Green Deployment

# Blue (cari aktiv) deployment
apiVersion: apps/v1
kind: Deployment
metadata:
  name: order-service-blue
  labels:
    app: order-service
    version: blue
spec:
  replicas: 3
  selector:
    matchLabels:
      app: order-service
      version: blue
  template:
    metadata:
      labels:
        app: order-service
        version: blue
    spec:
      containers:
        - name: order-service
          image: myregistry/order-service:v1.5.0

---
# Green (yeni) deployment — əvvəlcə replicas: 0, test üçün artırılır
apiVersion: apps/v1
kind: Deployment
metadata:
  name: order-service-green
  labels:
    app: order-service
    version: green
spec:
  replicas: 3
  selector:
    matchLabels:
      app: order-service
      version: green
  template:
    metadata:
      labels:
        app: order-service
        version: green
    spec:
      containers:
        - name: order-service
          image: myregistry/order-service:v1.6.0

---
# Service — selector dəyişdirməklə Blue→Green keçid
apiVersion: v1
kind: Service
metadata:
  name: order-service
spec:
  selector:
    app: order-service
    version: blue   # ← bunu "green" etmək = instant switch
  ports:
    - port: 80
      targetPort: 8080
```

```yaml
# Argo Rollouts — Canary Deployment (automated analysis)
apiVersion: argoproj.io/v1alpha1
kind: Rollout
metadata:
  name: order-service
spec:
  replicas: 10
  strategy:
    canary:
      steps:
        - setWeight: 5       # 5% traffic canary-yə
        - pause: {duration: 5m}
        - analysis:           # Automated metric check
            templates:
              - templateName: success-rate
        - setWeight: 25
        - pause: {duration: 10m}
        - analysis:
            templates:
              - templateName: success-rate
              - templateName: latency-p99
        - setWeight: 50
        - pause: {duration: 10m}
        - setWeight: 100     # Tam keçid

      # Canary traffic yönləndirmə
      canaryService: order-service-canary
      stableService: order-service-stable
      trafficRouting:
        istio:
          virtualService:
            name: order-service-vsvc
            routes:
              - primary

---
# Analysis Template — rollback meyarı
apiVersion: argoproj.io/v1alpha1
kind: AnalysisTemplate
metadata:
  name: success-rate
spec:
  metrics:
    - name: success-rate
      successCondition: result[0] >= 0.95   # 95% success threshold
      failureLimit: 2
      interval: 60s
      provider:
        prometheus:
          address: http://prometheus:9090
          query: |
            sum(rate(http_requests_total{
              app="order-service",
              version="{{args.revision}}",
              status!~"5.."
            }[2m])) /
            sum(rate(http_requests_total{
              app="order-service",
              version="{{args.revision}}"
            }[2m]))

---
# Latency Analysis
apiVersion: argoproj.io/v1alpha1
kind: AnalysisTemplate
metadata:
  name: latency-p99
spec:
  metrics:
    - name: p99-latency
      successCondition: result[0] <= 500    # 500ms P99 threshold
      provider:
        prometheus:
          address: http://prometheus:9090
          query: |
            histogram_quantile(0.99,
              rate(http_request_duration_ms_bucket{
                app="order-service",
                version="{{args.revision}}"
              }[5m])
            )
```

```php
// Database Migration — Expand-Contract Pattern (Blue-Green ilə uyğun)

// Phase 1: EXPAND — yeni column əlavə et (hər iki versiya işləyir)
// Migration 1 — Blue (v1) və Green (v2) hər ikisi nullable column qəbul edir
Schema::table('orders', function (Blueprint $table) {
    $table->string('new_status', 50)->nullable()->after('status');
    // Köhnə 'status' column hələ var — backward compatible
});

// Kod: v2 hər iki column-a yazır
class Order extends Model
{
    public function setStatusAttribute(string $value): void
    {
        $this->attributes['status']     = $value;  // köhnə column (Blue üçün)
        $this->attributes['new_status'] = $value;  // yeni column (Green üçün)
    }
}

// Phase 2: MIGRATE — köhnə datanı yeni column-a köçür
DB::statement("UPDATE orders SET new_status = status WHERE new_status IS NULL");

// Phase 3: SWITCH — v2 yalnız new_status-a baxır
// Phase 4: CONTRACT — Blue tam söndürüldükdən sonra köhnə column silinir
Schema::table('orders', function (Blueprint $table) {
    $table->dropColumn('status');
    $table->renameColumn('new_status', 'status');
});
```

```bash
# Blue-Green switch script
#!/bin/bash

echo "Switching traffic from Blue to Green..."

# Green-in hazır olduğunu yoxla
kubectl rollout status deployment/order-service-green -n production

# Service selector dəyişdir
kubectl patch service order-service -n production \
  -p '{"spec":{"selector":{"version":"green"}}}'

echo "Traffic switched to Green."
echo "Monitor metrics. Run ./rollback.sh if needed."

# rollback.sh
# kubectl patch service order-service -n production \
#   -p '{"spec":{"selector":{"version":"blue"}}}'
```

## Praktik Tapşırıqlar

- Kubernetes-də Blue-Green deployment implement edin — switch script yazın
- Argo Rollouts ilə Canary 5% → 25% → 100% qurun
- Database expand-contract pattern-ni real migration-da tətbiq edin
- Canary rollback meyarlarınızı müəyyən edin: hansı metrika nə threshold-da rollback trigger edir?
- Shadow deployment qurun — cavab göndərməyən, yalnız log tutan

## Əlaqəli Mövzular

- `14-zero-downtime-deployments.md` — Deployment strategiyalarının geniş mənzərəsi
- `12-feature-flags.md` — Canary + Feature Flags birlikdə
- `07-service-mesh.md` — Istio ilə traffic splitting
- `10-strangler-fig.md` — Tədricən migration ilə əlaqə
