# Java Date/Time API — Geniş İzah

## Mündəricat
1. [java.time API nədir?](#javatime-api-nədir)
2. [LocalDate, LocalTime, LocalDateTime](#localdate-localtime-localdatetime)
3. [ZonedDateTime və Instant](#zoneddatetime-və-instant)
4. [Period və Duration](#period-və-duration)
5. [Formatting və Parsing](#formatting-və-parsing)
6. [Spring Boot ilə inteqrasiya](#spring-boot-ilə-inteqrasiya)
7. [İntervyu Sualları](#intervyu-sualları)

---

## java.time API nədir?

Java 8-də (JSR-310) gəldi. Köhnə `java.util.Date`/`Calendar`-ın problemlərini həll edir.

```
Köhnə API problemləri:
  java.util.Date — mutable, thread-safe deyil
  java.util.Calendar — mürəkkəb API
  Timezone handling — aşkar deyil
  Null-prone — date arithmetic bug-ları

Yeni java.time API:
  Immutable — thread-safe
  Aydın semantika — LocalDate vs ZonedDateTime
  Fluent API — oxunaqlı
  ISO 8601 — standart format
```

```java
// Əsas tip xəritəsi:
// Tarix yalnız       → LocalDate     (2026-01-15)
// Vaxt yalnız        → LocalTime     (10:30:00)
// Tarix + vaxt       → LocalDateTime (2026-01-15T10:30:00)
// Timezone ilə       → ZonedDateTime (2026-01-15T10:30:00+04:00[Asia/Baku])
// UTC timestamp      → Instant       (2026-01-15T06:30:00Z)
// Tarix intervalı    → Period        (2 il, 3 ay, 15 gün)
// Vaxt intervalı     → Duration      (2 saat 30 dəq)
```

---

## LocalDate, LocalTime, LocalDateTime

```java
class LocalDateExamples {

    // ─── LocalDate — tarix ────────────────────────────
    @Test
    void localDateOperations() {
        LocalDate today = LocalDate.now();
        LocalDate specific = LocalDate.of(2026, 1, 15);
        LocalDate fromString = LocalDate.parse("2026-01-15");

        // Əməliyyatlar
        LocalDate tomorrow = today.plusDays(1);
        LocalDate nextMonth = today.plusMonths(1);
        LocalDate nextYear = today.plusYears(1);
        LocalDate lastWeek = today.minusWeeks(1);

        // Müqayisə
        boolean isBefore = specific.isBefore(today);
        boolean isAfter = specific.isAfter(today);
        boolean isEqual = specific.isEqual(LocalDate.of(2026, 1, 15));

        // Field-lər
        int year = today.getYear();           // 2026
        Month month = today.getMonth();       // JANUARY
        int monthValue = today.getMonthValue(); // 1
        int dayOfMonth = today.getDayOfMonth(); // 15
        DayOfWeek dayOfWeek = today.getDayOfWeek(); // WEDNESDAY
        int dayOfYear = today.getDayOfYear();  // 15

        // Booleans
        boolean isLeapYear = today.isLeapYear();
        int daysInMonth = today.lengthOfMonth(); // 31 (Yanvar)

        // Ay başı / sonu
        LocalDate firstDayOfMonth = today.withDayOfMonth(1);
        LocalDate lastDayOfMonth = today.with(TemporalAdjusters.lastDayOfMonth());
        LocalDate firstDayOfYear = today.with(TemporalAdjusters.firstDayOfYear());

        // Sonrakı / keçmiş gün
        LocalDate nextMonday = today.with(TemporalAdjusters.next(DayOfWeek.MONDAY));
        LocalDate lastFriday = today.with(TemporalAdjusters.previous(DayOfWeek.FRIDAY));
    }

    // ─── LocalTime — vaxt ─────────────────────────────
    @Test
    void localTimeOperations() {
        LocalTime now = LocalTime.now();
        LocalTime specific = LocalTime.of(10, 30, 0);
        LocalTime fromString = LocalTime.parse("10:30:00");

        LocalTime plusHours = specific.plusHours(2);    // 12:30:00
        LocalTime plusMinutes = specific.plusMinutes(45); // 11:15:00

        int hour = specific.getHour();     // 10
        int minute = specific.getMinute(); // 30
        int second = specific.getSecond(); // 0

        LocalTime midnight = LocalTime.MIDNIGHT;
        LocalTime noon = LocalTime.NOON;
    }

    // ─── LocalDateTime — tarix + vaxt ────────────────
    @Test
    void localDateTimeOperations() {
        LocalDateTime now = LocalDateTime.now();
        LocalDateTime specific = LocalDateTime.of(2026, 1, 15, 10, 30, 0);
        LocalDateTime fromParts = LocalDateTime.of(
            LocalDate.of(2026, 1, 15),
            LocalTime.of(10, 30)
        );

        // Ayrı-ayrı hissələrə çevirmə
        LocalDate date = now.toLocalDate();
        LocalTime time = now.toLocalTime();

        // Modifikasiya
        LocalDateTime tomorrow = now.plusDays(1);
        LocalDateTime nextHour = now.plusHours(1);
        LocalDateTime truncated = now.truncatedTo(ChronoUnit.HOURS); // saniyə sıfırlanır
    }
}
```

---

## ZonedDateTime və Instant

```java
class ZonedDateTimeExamples {

    // ─── ZoneId ───────────────────────────────────────
    @Test
    void zoneIds() {
        ZoneId baku = ZoneId.of("Asia/Baku");         // +04:00
        ZoneId utc = ZoneId.of("UTC");
        ZoneId london = ZoneId.of("Europe/London");
        ZoneId newYork = ZoneId.of("America/New_York");

        ZoneId systemZone = ZoneId.systemDefault();

        // Bütün zone ID-lər
        Set<String> allZones = ZoneId.getAvailableZoneIds();
    }

    // ─── ZonedDateTime ────────────────────────────────
    @Test
    void zonedDateTimeOperations() {
        ZonedDateTime bakuTime = ZonedDateTime.now(ZoneId.of("Asia/Baku"));
        ZonedDateTime specific = ZonedDateTime.of(
            2026, 1, 15, 10, 30, 0, 0,
            ZoneId.of("Asia/Baku")
        );

        // Zone dəyişdirmə
        ZonedDateTime londonTime = bakuTime.withZoneSameInstant(ZoneId.of("Europe/London"));
        ZonedDateTime utcTime = bakuTime.withZoneSameInstant(ZoneId.of("UTC"));

        // Vaxt fərqi hesablama
        ZonedDateTime bakuNoon = ZonedDateTime.of(2026, 1, 15, 12, 0, 0, 0, ZoneId.of("Asia/Baku"));
        ZonedDateTime nyNoon = bakuNoon.withZoneSameInstant(ZoneId.of("America/New_York"));

        System.out.println("Bakı 12:00 = New York " + nyNoon.toLocalTime()); // 04:00 (DST-siz)
    }

    // ─── Instant — UTC timestamp ──────────────────────
    @Test
    void instantOperations() {
        Instant now = Instant.now(); // UTC-də current timestamp
        Instant epoch = Instant.EPOCH; // 1970-01-01T00:00:00Z

        Instant past = Instant.parse("2026-01-01T10:00:00Z");
        Instant future = past.plusSeconds(3600); // +1 saat

        // Millisaniyə
        long millis = now.toEpochMilli();
        Instant fromMillis = Instant.ofEpochMilli(millis);

        // Müqayisə
        boolean isBefore = past.isBefore(now);
        boolean isAfter = future.isAfter(now);

        // LocalDateTime ↔ Instant çevirmə
        ZoneId zone = ZoneId.of("Asia/Baku");
        LocalDateTime localDt = LocalDateTime.of(2026, 1, 15, 10, 0);
        Instant instant = localDt.atZone(zone).toInstant();

        LocalDateTime backToLocal = instant.atZone(zone).toLocalDateTime();
    }

    // ─── Offset DateTime ──────────────────────────────
    @Test
    void offsetDateTime() {
        OffsetDateTime odt = OffsetDateTime.of(2026, 1, 15, 10, 0, 0, 0,
            ZoneOffset.of("+04:00"));

        Instant instant = odt.toInstant();
        ZonedDateTime zdt = odt.toZonedDateTime();
    }
}
```

---

## Period və Duration

```java
class PeriodAndDurationExamples {

    // ─── Period — tarix intervalı ─────────────────────
    @Test
    void periodOperations() {
        LocalDate start = LocalDate.of(2024, 1, 1);
        LocalDate end = LocalDate.of(2026, 4, 15);

        Period period = Period.between(start, end);

        System.out.println(period.getYears());  // 2
        System.out.println(period.getMonths()); // 3
        System.out.println(period.getDays());   // 14

        // Period yaratmaq
        Period twoYears = Period.ofYears(2);
        Period threeMonths = Period.ofMonths(3);
        Period twoWeeks = Period.ofWeeks(2);
        Period combined = Period.of(1, 6, 15); // 1 il 6 ay 15 gün

        // Tətbiq etmək
        LocalDate future = start.plus(combined);
        LocalDate past = end.minus(Period.ofMonths(3));

        // Müqayisə
        boolean isNegative = period.isNegative();
        boolean isZero = period.isZero();
    }

    // ─── Duration — vaxt intervalı ────────────────────
    @Test
    void durationOperations() {
        Instant start = Instant.parse("2026-01-15T10:00:00Z");
        Instant end = Instant.parse("2026-01-15T12:30:45Z");

        Duration duration = Duration.between(start, end);

        System.out.println(duration.toHours());   // 2
        System.out.println(duration.toMinutes()); // 150
        System.out.println(duration.toSeconds()); // 9045
        System.out.println(duration.toMillis());  // 9045000

        // Duration yaratmaq
        Duration oneHour = Duration.ofHours(1);
        Duration thirtyMin = Duration.ofMinutes(30);
        Duration fiveSeconds = Duration.ofSeconds(5);
        Duration parseStr = Duration.parse("PT2H30M"); // ISO 8601

        // Əməliyyatlar
        Duration total = oneHour.plus(thirtyMin);
        Duration doubled = oneHour.multipliedBy(2);
        Duration half = oneHour.dividedBy(2);

        // LocalTime fərqi
        LocalTime t1 = LocalTime.of(9, 0);
        LocalTime t2 = LocalTime.of(17, 30);
        Duration workDay = Duration.between(t1, t2);
        System.out.println(workDay.toHours()); // 8
    }

    // ─── ChronoUnit ───────────────────────────────────
    @Test
    void chronoUnitOperations() {
        LocalDate d1 = LocalDate.of(2026, 1, 1);
        LocalDate d2 = LocalDate.of(2026, 12, 31);

        long daysBetween = ChronoUnit.DAYS.between(d1, d2);      // 364
        long monthsBetween = ChronoUnit.MONTHS.between(d1, d2);  // 11
        long yearsBetween = ChronoUnit.YEARS.between(d1, d2);    // 0

        Instant i1 = Instant.parse("2026-01-01T00:00:00Z");
        Instant i2 = Instant.parse("2026-01-02T00:00:00Z");

        long hoursBetween = ChronoUnit.HOURS.between(i1, i2); // 24
        long minutesBetween = ChronoUnit.MINUTES.between(i1, i2); // 1440
    }
}
```

---

## Formatting və Parsing

```java
class FormattingExamples {

    // ─── DateTimeFormatter ────────────────────────────
    @Test
    void formattingOperations() {
        LocalDateTime dt = LocalDateTime.of(2026, 1, 15, 10, 30, 45);

        // Hazır formatlar
        String iso = dt.format(DateTimeFormatter.ISO_LOCAL_DATE_TIME);
        // "2026-01-15T10:30:45"

        String isoDate = LocalDate.now().format(DateTimeFormatter.ISO_LOCAL_DATE);
        // "2026-01-15"

        // Custom format
        DateTimeFormatter formatter = DateTimeFormatter.ofPattern("dd.MM.yyyy HH:mm");
        String formatted = dt.format(formatter);
        // "15.01.2026 10:30"

        DateTimeFormatter withLocale = DateTimeFormatter
            .ofPattern("d MMMM yyyy", new Locale("az"));
        String azerbaijani = dt.format(withLocale);
        // "15 yanvar 2026"

        // Parsing
        LocalDate parsed = LocalDate.parse("15.01.2026",
            DateTimeFormatter.ofPattern("dd.MM.yyyy"));

        LocalDateTime parsedDt = LocalDateTime.parse("2026-01-15T10:30:45");

        // Thread-safe — DateTimeFormatter immutable-dır
        // static final kimi saxlamaq olar
    }

    // ─── Praktik format-lar ───────────────────────────
    static final DateTimeFormatter DATE_FORMATTER =
        DateTimeFormatter.ofPattern("dd.MM.yyyy");

    static final DateTimeFormatter DATETIME_FORMATTER =
        DateTimeFormatter.ofPattern("dd.MM.yyyy HH:mm:ss");

    static final DateTimeFormatter API_FORMATTER =
        DateTimeFormatter.ISO_OFFSET_DATE_TIME;

    String formatOrderDate(Order order) {
        return order.getCreatedAt()              // Instant
            .atZone(ZoneId.of("Asia/Baku"))      // → ZonedDateTime
            .format(DATETIME_FORMATTER);          // → String
    }
}
```

---

## Spring Boot ilə inteqrasiya

```java
// ─── Jackson Serialization ────────────────────────────
// application.yml:
// spring:
//   jackson:
//     serialization:
//       write-dates-as-timestamps: false
//     time-zone: Asia/Baku

// Entity:
@Entity
public class Order {

    @Id
    private Long id;

    // LocalDate → "2026-01-15"
    private LocalDate orderDate;

    // Instant → "2026-01-15T06:30:00Z" (UTC)
    private Instant createdAt;

    // ZonedDateTime → "2026-01-15T10:30:00+04:00[Asia/Baku]"
    private ZonedDateTime updatedAt;
}

// ─── JPA Conversion ───────────────────────────────────
@Entity
public class Order {

    // Hibernate 6+ → avtomatik Instant, LocalDate, LocalDateTime
    @Column(name = "created_at")
    private Instant createdAt;

    @Column(name = "order_date")
    private LocalDate orderDate;

    // Köhnə Hibernate üçün:
    @Convert(converter = InstantConverter.class)
    private Instant createdAt;
}

// ─── Spring MVC — @RequestParam ───────────────────────
@GetMapping("/orders")
public List<Order> getOrders(
    @RequestParam @DateTimeFormat(iso = DateTimeFormat.ISO.DATE) LocalDate from,
    @RequestParam @DateTimeFormat(iso = DateTimeFormat.ISO.DATE) LocalDate to
) {
    // GET /orders?from=2026-01-01&to=2026-12-31
    return orderService.findByDateRange(from, to);
}

// ─── Clock injection — test üçün ──────────────────────
@Service
public class OrderService {

    private final Clock clock;
    private final OrderRepository repository;

    public OrderService(Clock clock, OrderRepository repository) {
        this.clock = clock;
        this.repository = repository;
    }

    public Order createOrder(OrderRequest request) {
        return Order.builder()
            .createdAt(Instant.now(clock)) // Clock inject — test override edilə bilər
            .status(OrderStatus.PENDING)
            .build();
    }
}

// Test:
@TestConfiguration
class TestConfig {
    @Bean
    public Clock testClock() {
        return Clock.fixed(Instant.parse("2026-01-15T10:00:00Z"), ZoneId.of("UTC"));
    }
}

// Bütün Instant.now(clock) sabit vaxt qaytarır — deterministic test!
```

---

## İntervyu Sualları

### 1. java.util.Date ilə java.time fərqi?
**Cavab:** `java.util.Date` — mutable, thread-safe deyil, timezone problematik (UTC saxlayır, toString local timezone göstərir). `java.time` — immutable (thread-safe), aydın semantika (LocalDate timezone bilmir, ZonedDateTime bilir), fluent API, ISO 8601 standart. Java 8-dən `java.time` tövsiyə edilir.

### 2. LocalDateTime vs ZonedDateTime vs Instant?
**Cavab:** `LocalDateTime` — timezone yoxdur; istifadəçinin gördüyü "local" tarix/vaxt; `2026-01-15T10:30` nə UTC, nə +04. `ZonedDateTime` — timezone daxildir; `2026-01-15T10:30+04:00[Asia/Baku]`. `Instant` — UTC UTC timestamp; machine time, database-ə saxlama üçün; timezone-dan müstəqil. DB-də `Instant` saxlamaq, UI-də `ZonedDateTime` göstərmək tövsiyə edilir.

### 3. Period vs Duration fərqi?
**Cavab:** `Period` — calendar-based; gün, ay, il; `LocalDate` ilə istifadə. `Duration` — time-based; saat, dəqiqə, saniyə, nanosaniyə; `Instant`/`LocalTime` ilə istifadə. `Period.ofMonths(1)` — fərqli aylarda fərqli saniyə sayı (28, 29, 30, 31 gün). `Duration.ofDays(1)` — həmişə 86400 saniyə. DST dəyişikliyi `Duration`-a, `Period`-a təsir etmir.

### 4. Test-lərdə zamanı necə idarə etmək lazımdır?
**Cavab:** `Instant.now()` birbaşa çağırmaq yerinə `Clock` inject etmək: `Instant.now(clock)`. Test-də `Clock.fixed(instant, zone)` ilə sabit vaxt: `@Bean Clock testClock() { return Clock.fixed(...); }`. Bu, testləri deterministik edir — istənilən tarix/vaxt simulyasiya edilə bilər. Mockito ilə `mockStatic(Instant.class)` alternativdir, amma `Clock` injection daha təmiz.

### 5. DateTimeFormatter thread-safe-dirmi?
**Cavab:** Bəli — `DateTimeFormatter` immutable və thread-safe-dir. `SimpleDateFormat` (köhnə API) thread-safe deyildi — hər thread üçün ayrı instance lazım idi. `DateTimeFormatter`-ı `static final` sahə kimi təyin etmək güvenlidir: `static final DateTimeFormatter FORMATTER = DateTimeFormatter.ofPattern("dd.MM.yyyy");`

*Son yenilənmə: 2026-04-10*
