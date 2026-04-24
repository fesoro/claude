# 018 — Strangler Fig Pattern — Geniş İzah
**Səviyyə:** Ekspert


## Mündəricat
1. [Strangler Fig Pattern nədir?](#strangler-fig-pattern-nədir)
2. [Tətbiq addımları](#tətbiq-addımları)
3. [Spring Boot-da Strangler Fig](#spring-boot-da-strangler-fig)
4. [Database migration strategiyası](#database-migration-strategiyası)
5. [Antipatterns — nələrdən qaçmaq lazım](#antipatterns--nələrdən-qaçmaq-lazım)
6. [Real dünya nümunəsi](#real-dünya-nümunəsi)
7. [İntervyu Sualları](#intervyu-sualları)

---

## Strangler Fig Pattern nədir?

```
Ad haradan gəlir:
  Strangler Fig (boğan əncir) — tropik bitki
  → Köhnə ağacın üzərinə sarılır
  → Tədricən böyüyür
  → Köhnə ağac ölür, yeni bitki qalır

Martin Fowler — 2004, Strangler Fig Application

Monolit → Microservice miqrasiyası:
  "Big Bang" yanaşması (yanlış):
    ❌ Monolit dayandır → Hamısını yenidən yaz → Deploy
    ❌ Risklər: çox böyük dəyişiklik, test edilməmiş, geri dönüş yoxdur

  Strangler Fig yanaşması (düzgün):
    ✅ Monolit çalışmağa davam edir
    ✅ Tədricən funksiyalar yeni servisə köçürülür
    ✅ Hər addım test edilir, deploy edilir
    ✅ Geri dönüş mümkündür
    ✅ Riskli yox, inkremental

Mərhələlər:
  1. Transform: yeni servis yaz (funksiya)
  2. Co-exist: köhnə + yeni paralel çalışır
  3. Eliminate: köhnə funksiya monolitdən silinir

  Monolit          Strangler Facade (API Gateway)
  [A B C D E]  →  [A B C]  +  [D] (yeni servis)
  [A B C D E]  →  [A B C]  +  [D] [E] (2 yeni servis)
  [A B C D E]  →  [A B]    +  [C] [D] [E]
  ...
  []           →  [A] [B] [C] [D] [E] (tam microservice)
```

---

## Tətbiq addımları

```
Addım 1: Strangler Facade qur
  Bütün traffic → Facade (API Gateway/Nginx/Spring Cloud Gateway)
  Facade monoliti çağırır (hər şey eyni kimi çalışır)

Addım 2: İlk servisi ayır
  Ən az riskli, ən müstəqil funksiya seçin
  Yeni servis yaz + test et
  Feature flag: yeni servisə yönləndir

Addım 3: Data miqrasiyası
  Monolitdən data-nı yeni servise köçür
  Dual write: həm köhnə, həm yeni DB-yə yaz
  Migrate → doğrula → monolitdən kəs

Addım 4: Monolitdən funksiyaları sil
  Köhnə funksiyalar yavaş-yavaş silinir
  Monolit kiçilir

Addım 5: Tekrar
  Növbəti funksiya üçün eyni prosesi təkrarla

Prioritetləşdirmə:
  Hansı funksiyaları əvvəl köçürmək?
  → Ən çox dəyişən (yüksək development velocity lazım)
  → Ən çox yüklənən (independent scaling lazım)
  → Ən az dependency-si olan (asan köçürmə)
  → Əvvəl köçürülmüş komanda sahib olanlar
```

---

## Spring Boot-da Strangler Fig

```java
// ─── Strangler Facade — API Gateway ilə ─────────────────
// Spring Cloud Gateway: Traffic routing

@Configuration
public class StranglerFacadeRouteConfig {

    @Bean
    public RouteLocator stranglerRoutes(RouteLocatorBuilder builder) {
        return builder.routes()

            // ─── Köçürülmüş funksiya: Product Catalog ─────
            // /api/products → Yeni product-service
            .route("product-service-new", r -> r
                .path("/api/products/**")
                .uri("lb://product-service")  // Yeni microservice
            )

            // ─── Köçürülmüş funksiya: User Profile ────────
            // /api/users/profile → Yeni user-service
            .route("user-profile-new", r -> r
                .path("/api/users/*/profile")
                .uri("lb://user-service")
            )

            // ─── Hələ köçürülməmiş: Monolita yönləndir ────
            // /api/** → Köhnə monolit
            .route("legacy-monolith", r -> r
                .path("/api/**")
                .uri("http://monolith:8080")  // Köhnə sistem
            )

            .build();
    }
}

// ─── Feature Flag ilə tədricən keçid ──────────────────────
@Service
public class OrderService {

    private final FeatureFlagService featureFlags;
    private final LegacyOrderClient legacyClient;   // Monolit client
    private final NewOrderRepository orderRepository; // Yeni DB

    public OrderResponse getOrder(String orderId) {
        if (featureFlags.isEnabled("USE_NEW_ORDER_SERVICE")) {
            // Yeni servis
            return orderRepository.findById(orderId)
                .map(this::toResponse)
                .orElseThrow(() -> new OrderNotFoundException(orderId));
        } else {
            // Köhnə monolit
            return legacyClient.getOrder(orderId);
        }
    }
}

// Feature Flag konfigurasyonu (DB-based)
@Entity
@Table(name = "feature_flags")
public class FeatureFlag {
    @Id
    private String name;
    private boolean enabled;
    private int rolloutPercentage; // 0-100% tədricən açmaq
    private String enabledForUsers; // JSON array — test users
}

@Service
public class FeatureFlagService {

    private final FeatureFlagRepository repository;

    public boolean isEnabled(String flagName) {
        return repository.findById(flagName)
            .map(FeatureFlag::isEnabled)
            .orElse(false);
    }

    // Müəyyən % istifadəçi üçün açmaq (canary rollout)
    public boolean isEnabledForUser(String flagName, String userId) {
        return repository.findById(flagName)
            .map(flag -> {
                if (!flag.isEnabled()) return false;
                // Hash-based bucketing — eyni user hər dəfə eyni cavab alır
                int bucket = Math.abs(userId.hashCode()) % 100;
                return bucket < flag.getRolloutPercentage();
            })
            .orElse(false);
    }
}

// ─── Strangler Facade — Nginx konfiqurasiya ───────────────
/*
# nginx.conf — Nginx-based strangler facade
upstream monolith {
    server monolith-app:8080;
}

upstream product_service {
    server product-service:8081;
}

upstream user_service {
    server user-service:8082;
}

server {
    listen 80;

    # Yeni servisə yönləndir
    location /api/products/ {
        proxy_pass http://product_service;
    }

    location /api/users/profile {
        proxy_pass http://user_service;
    }

    # Hər şey digəri monolit
    location /api/ {
        proxy_pass http://monolith;
    }
}
*/
```

---

## Database migration strategiyası

```java
// ─── Dual Write — Köçürmə zamanı hər iki DB-yə yaz ──────
@Service
@Transactional
public class OrderMigrationService {

    private final LegacyOrderRepository legacyRepo; // Köhnə DB
    private final NewOrderRepository newRepo;         // Yeni DB
    private final FeatureFlagService featureFlags;

    public Order createOrder(CreateOrderRequest request) {
        // Köhnə DB-yə həmişə yaz (backward compatibility)
        LegacyOrder legacyOrder = legacyRepo.save(toLegacy(request));

        // Feature flag aktiv isə yeni DB-yə də yaz
        if (featureFlags.isEnabled("DUAL_WRITE_ORDERS")) {
            try {
                Order newOrder = newRepo.save(toNew(request, legacyOrder.getId()));
                log.debug("Dual write uğurlu: orderId={}", legacyOrder.getId());
            } catch (Exception e) {
                // Yeni DB xətası → köhnəni pozmur, sadəcə log
                log.error("Dual write xətası: {}", e.getMessage());
                // Alert göndər amma servisi dayandırma
            }
        }

        return toLegacyResponse(legacyOrder);
    }
}

// ─── Data Backfill — Köhnə data-nı yeni DB-yə köçür ─────
@Component
public class OrderDataBackfillJob {

    private final LegacyOrderRepository legacyRepo;
    private final NewOrderRepository newRepo;

    // Batch-lərlə köçür (hər gecə ya da weekend-də)
    @Scheduled(cron = "0 2 * * * *") // Hər gecə 02:00
    @Transactional(readOnly = true)
    public void backfillOrders() {
        long lastMigratedId = newRepo.findMaxLegacyId().orElse(0L);

        // Batch-lərlə köçür
        Pageable pageable = PageRequest.of(0, 1000,
            Sort.by("id").ascending());

        Page<LegacyOrder> batch;
        long processed = 0;

        do {
            batch = legacyRepo.findByIdGreaterThan(lastMigratedId, pageable);
            List<Order> toMigrate = batch.getContent().stream()
                .map(this::migrateOrder)
                .toList();

            newRepo.saveAll(toMigrate);
            processed += toMigrate.size();

            if (!batch.isEmpty()) {
                lastMigratedId = batch.getContent()
                    .get(batch.getContent().size() - 1).getId();
            }

            log.info("Backfill: {} sifariş köçürüldü", processed);
        } while (batch.hasNext());

        log.info("Backfill tamamlandı: cəmi {} sifariş", processed);
    }

    private Order migrateOrder(LegacyOrder legacy) {
        return Order.builder()
            .legacyId(legacy.getId())
            .customerId(legacy.getCustomerId())
            .status(mapStatus(legacy.getStatus()))
            .total(legacy.getAmount())
            .createdAt(legacy.getCreatedDate().toInstant())
            .build();
    }
}

// ─── Read Fallback — Yeni DB-də yoxsa köhnədən oxu ───────
@Service
public class OrderReadService {

    private final NewOrderRepository newRepo;
    private final LegacyOrderClient legacyClient;

    public OrderResponse getOrder(String orderId) {
        // Əvvəlcə yeni DB-dən cəhd et
        Optional<Order> newOrder = newRepo.findById(orderId);

        if (newOrder.isPresent()) {
            return toResponse(newOrder.get());
        }

        // Tapılmadı — köhnə sistemdən al (backfill hələ çatmayıb)
        log.debug("Yeni DB-də tapılmadı, köhnəyə fallback: {}", orderId);
        return legacyClient.getOrder(orderId);
    }
}
```

---

## Antipatterns — nələrdən qaçmaq lazım

```java
// ─── Antipattern 1: Big Bang Migration ───────────────────
// ❌ Yanlış: hamısını bir dəfəyə köçürmək
@Service
public class BadMigrationService {
    public void migrateEverything() {
        // 500,000 sifariş, 1M müştəri, 2M transaction
        // 6 aylıq iş, deploy günü kaos!
        // Geri dönüş yoxdur!
    }
}

// ✅ Doğru: inkremental
@Service
public class GoodMigrationService {
    // 1 həftə: Product service (ən sadə)
    // 2 həftə: User profile
    // 4 həftə: Order creation
    // 6 həftə: Payment history
    // ...
}

// ─── Antipattern 2: Shared DB-ni saxlamaq ────────────────
// ❌ Yanlış: yeni servis köhnə DB-yə birbaşa çatır
@Repository
public class NewOrderRepository {
    // Köhnə monolitin DB-sinə birbaşa connect!
    // DataSource url = jdbc:postgresql://monolith-db/...
    // Bu coupling-i aradan qaldırmır
}

// ✅ Doğru: API vasitəsilə kommunikasiya
@Service
public class GoodNewOrderService {
    private final LegacyOrderClient legacyClient; // HTTP API!
    // Yeni servis köhnə sistemin API-sini çağırır
}

// ─── Antipattern 3: Miqrasiya testləri yoxdur ────────────
// ❌ Yanlış: köhnə + yeni sistemin nəticəsini müqayisə etmirəm
// ✅ Doğru: Shadow Testing

@Service
public class ShadowTestingOrderService {

    private final LegacyOrderClient legacyClient;
    private final NewOrderService newOrderService;

    public OrderResponse getOrder(String orderId) {
        // Köhnə sistem — əsas cavab
        OrderResponse legacyResponse = legacyClient.getOrder(orderId);

        // Yeni sistem — shadow (istifadəçiyə göstərilmir)
        try {
            OrderResponse newResponse = newOrderService.getOrder(orderId);

            // Müqayisə et — fərq varsa log et
            if (!isEquivalent(legacyResponse, newResponse)) {
                log.warn("Shadow test fərqi aşkar edildi: orderId={}," +
                    " legacy={}, new={}", orderId, legacyResponse, newResponse);
                metrics.increment("shadow.test.mismatch");
            }
        } catch (Exception e) {
            log.error("Shadow test xətası: {}", e.getMessage());
            metrics.increment("shadow.test.error");
        }

        // Hər zaman köhnə cavabı qaytar (shadow test görünmür)
        return legacyResponse;
    }
}
```

---

## Real dünya nümunəsi

```java
// ─── E-commerce monolit → microservice miqrasiyası ───────
/*
Addım 0 (Başlanğıc vəziyyəti):
  Monolit: [Products] [Orders] [Payments] [Users] [Inventory] [Reviews]
  Bir PostgreSQL DB
  10 il köhnə Spring MVC kodu

Addım 1 — Strangler Facade qur (Sprint 1):
  Nginx/Spring Cloud Gateway → Monolita proxy

Addım 2 — Reviews Service (Sprint 2-3):
  → Ən az business logic dependency
  → MongoDB seçildi (document store reviews üçün uyğun)
  → /api/reviews/** → yeni review-service
  → Köhnə reviews kodu monolitdə qalır (feature flag)

Addım 3 — Products Service (Sprint 4-6):
  → Product catalog oxuma ağır → Elasticsearch
  → Dual write başladı
  → Data backfill: 500K məhsul gecə 02:00-da köçürüldü
  → Shadow testing 2 həftə
  → /api/products/** → yeni product-service
  → Monolit Products kodu silindi

Addım 4 — Users Service (Sprint 7-9):
  → Auth logic çıxarıldı
  → JWT token gateway-də doğrulanır
  → /api/users/** → yeni user-service

Addım 5 — Inventory Service (Sprint 10-12):
  → Redis qatlandı (real-time stock)

Addım 6 — Orders Service (Sprint 13-18):
  → Ən mürəkkəb: Saga pattern tətbiq edildi
  → Inventory, Payment, User servisləri ilə koordinasiya
  → 6 sprint — ən uzun köçürmə

Addım 7 — Payments Service (Sprint 19-22):
  → Son böyük modul
  → PCI DSS compliance tələbləri

Son vəziyyət (1 il sonra):
  [Products] [Orders] [Payments] [Users] [Inventory] [Reviews]
  Monolit sıfırlandı — yalnız boş shell qaldı, sonra silindi
*/

// ─── Migration Metrics tracking ──────────────────────────
@Component
public class MigrationMetrics {

    private final MeterRegistry meterRegistry;

    public void recordMigrationProgress(String module, double percentage) {
        meterRegistry.gauge("migration.progress",
            Tags.of("module", module),
            percentage);
    }

    public void recordShadowTestResult(String module, boolean matched) {
        meterRegistry.counter("shadow.test.result",
            "module", module,
            "result", matched ? "match" : "mismatch")
            .increment();
    }
}
```

---

## İntervyu Sualları

### 1. Strangler Fig Pattern nədir?
**Cavab:** Martin Fowler-in adlandırdığı bu pattern — köhnə monolit sistemini tədricən yeni microservice-lərlə əvəzləmə strategiyasıdır. Adı strangler fig bitkisindən gəlir — köhnə ağaca sarılır, tədricən onu əvəzləyir. Əsas ideya: monolit dayandırılmır, paralel olaraq funksiyalar bir-bir yeni servisə köçürülür. Hər köçürmə test edilir, deploy edilir, doğrulanır. Risk minimal — istənilən vaxt geri dönmək mümkündür.

### 2. Big Bang vs Strangler Fig miqrasiyası?
**Cavab:** **Big Bang** — hamısını bir dəfəyə yenidən yaz. Risk: 6-12 aylıq iş, deploy günü bilinməyən problemlər, geri dönüş demək olar ki mümkün deyil, komanda morale problemi. **Strangler Fig** — inkremental, hər sprint-də bir funksiya. Risk az: kiçik dəyişiklik, sürətli feedback, geri dönüş her zaman mümkün. Production-da sınanmış. Qazanc: köhnə sistem çalışmağa davam edir, business dayanmır.

### 3. Dual Write nədir və niyə lazımdır?
**Cavab:** Data miqrasiyası zamanı həm köhnə, həm yeni DB-yə yazma strategiyası. Məqsəd: data itkisi olmadan, sıfır downtime ilə miqrasiya. Addımlar: (1) Dual write başla — yeni data hər iki DB-yə yazılır; (2) Backfill — köhnə data batch-lərlə yeni DB-yə köçürülür; (3) Doğrula — yeni DB köhnə ilə eyni data-ya sahibdirmi? (4) Read-i yeni DB-yə keç; (5) Write-ı yalnız yeni DB-yə keç. Risk: dual write zamanı yeni DB xətası köhnəni pozmur — geri dönüş mümkündür.

### 4. Feature Flag Strangler Fig-də necə istifadə olunur?
**Cavab:** Feature flag — runtime-da hansı implementasiyanın istifadə olunacağını idarə edir. Canary rollout: əvvəl 1% trafikə (test users), sonra 10%, 50%, 100%. A/B testing: köhnə vs yeni sistemin davranışını müqayisə et. Instant rollback: problem çıxarsa flag-ı söndür, sistem anında köhnəyə qayıdır (yenidən deploy lazım deyil). Shadow testing: hər iki sistem çağırılır amma yalnız köhnənin cavabı istifadəçiyə göndərilir.

### 5. Shadow Testing nədir?
**Cavab:** Yeni sistemin əsl production trafikini istifadəçiləri bilmədən emal etməsi. Köhnə sistem əsas cavabı verir; yeni sistem eyni sorğunu parallel emal edir; nəticələr müqayisə edilir, fərqlər log-lanır. Məqsəd: yeni sistemin köhnə ilə eyni nəticə verməsini real data ilə sübut etmək — test mühiti bütün edge case-ləri əhatə edə bilmir. Mismatch aşkarlandıqda: bug-lar düzəldilir, yalnız 100% match-dən sonra real keçid edilir. Netflix, GitHub bu yanaşmanı geniş istifadə edir.

*Son yenilənmə: 2026-04-10*
