# JMH Benchmarking (Lead)

> **Seviyye:** Lead ⭐⭐⭐⭐

## İcmal

**JMH (Java Microbenchmark Harness)** — OpenJDK komandası tərəfindən hazırlanmış, JVM-in JIT compilation, dead code elimination, constant folding kimi optimizasiyalarını nəzərə alan yeganə etibarlı Java benchmark framework-üdür.

---

## Niyə Vacibdir

```java
// ❌ Naïve benchmark — tamamilə yanlış nəticə
long start = System.nanoTime();
for (int i = 0; i < 1_000_000; i++) {
    String s = String.valueOf(i);  // JIT bunu optimizasiya edə bilər
}
long elapsed = System.nanoTime() - start;

// Problem:
// 1. JVM warmup olmayıb (JIT hələ kod optimizasiya etməyib)
// 2. JIT dead code elimination: heç nə istifadə edilmirsə, silir
// 3. Constant folding: sabit nəticəni compile-time-da hesablayır
// 4. GC pause-ları ölçmürsən

// ✅ Düzgün üsul: JMH
```

---

## Quraşdırma

```xml
<!-- pom.xml -->
<dependency>
    <groupId>org.openjdk.jmh</groupId>
    <artifactId>jmh-core</artifactId>
    <version>1.37</version>
</dependency>
<dependency>
    <groupId>org.openjdk.jmh</groupId>
    <artifactId>jmh-generator-annprocess</artifactId>
    <version>1.37</version>
    <scope>provided</scope>
</dependency>

<!-- Fat JAR üçün plugin -->
<plugin>
    <artifactId>maven-shade-plugin</artifactId>
    <configuration>
        <finalName>benchmarks</finalName>
    </configuration>
</plugin>
```

```bash
# Build və çalışdır
mvn clean package -DskipTests
java -jar target/benchmarks.jar

# Konkret benchmark
java -jar target/benchmarks.jar "StringBenchmark.concatenation"
```

---

## Əsas Annotasiyalar

```java
@BenchmarkMode(Mode.AverageTime)     // Ortalama icra vaxtı
@OutputTimeUnit(TimeUnit.MICROSECONDS)
@State(Scope.Thread)                 // Hər thread öz instance-ı işlədir
@Warmup(iterations = 3, time = 1)    // 3 warmup iteration (JIT için)
@Measurement(iterations = 5, time = 2) // 5 ölçmə iteration
@Fork(2)                             // 2 ayrı JVM prosesi ilə çalışdır
public class StringBenchmark {

    private String prefix;
    private String suffix;

    @Setup(Level.Trial)              // Hər fork-dan əvvəl bir dəfə
    public void setup() {
        prefix = "Hello, ";
        suffix = "World!";
    }

    @Benchmark
    public String concatenation() {
        return prefix + suffix;         // + operator
    }

    @Benchmark
    public String stringBuilder() {
        return new StringBuilder()
            .append(prefix)
            .append(suffix)
            .toString();
    }

    @Benchmark
    public String stringFormat() {
        return String.format("%s%s", prefix, suffix);
    }
}
```

### BenchmarkMode növləri

```java
// Throughput — saniyədə neçə əməliyyat
@BenchmarkMode(Mode.Throughput)
@OutputTimeUnit(TimeUnit.SECONDS)

// Ortalama vaxt
@BenchmarkMode(Mode.AverageTime)
@OutputTimeUnit(TimeUnit.NANOSECONDS)

// Hər əməliyyat üçün vaxt sampling
@BenchmarkMode(Mode.SampleTime)

// Hamısını bir yerdə
@BenchmarkMode({Mode.Throughput, Mode.AverageTime})
```

### State Scope

```java
// Thread — hər thread öz instance-ı
@State(Scope.Thread)
public class ThreadState {
    List<Integer> list = new ArrayList<>(); // thread-local, race yoxdur
}

// Benchmark — bütün threadlər paylaşır
@State(Scope.Benchmark)
public class SharedState {
    ConcurrentHashMap<String, Integer> map = new ConcurrentHashMap<>();
}

// Group — thread group üçün
@State(Scope.Group)
```

---

## Dead Code Elimination qarşısını almaq

```java
@State(Scope.Thread)
public class HashBenchmark {

    private final String key = "benchmark-key-12345";

    // ❌ Yanlış: JIT nəticəni əzləşdirib metodu silə bilər
    @Benchmark
    public void computeHashWrong() {
        key.hashCode(); // heç nə return etmirik → JIT ignore edə bilər
    }

    // ✅ Doğru: nəticəni return et
    @Benchmark
    public int computeHashReturn() {
        return key.hashCode(); // JMH özü consume edir
    }

    // ✅ Doğru: Blackhole istifadə et — birdən çox nəticə üçün
    @Benchmark
    public void computeMultiple(Blackhole bh) {
        bh.consume(key.hashCode());
        bh.consume(key.length());
        bh.consume(key.isEmpty());
    }
}
```

---

## Parametrli Benchmark

```java
@State(Scope.Thread)
public class CollectionBenchmark {

    @Param({"10", "100", "1000", "10000"}) // Hər dəyər üçün ayrıca run
    private int size;

    private List<Integer> arrayList;
    private List<Integer> linkedList;

    @Setup
    public void setup() {
        arrayList = new ArrayList<>();
        linkedList = new LinkedList<>();
        for (int i = 0; i < size; i++) {
            arrayList.add(i);
            linkedList.add(i);
        }
    }

    @Benchmark
    public int iterateArrayList() {
        int sum = 0;
        for (int val : arrayList) sum += val;
        return sum;
    }

    @Benchmark
    public int iterateLinkedList() {
        int sum = 0;
        for (int val : linkedList) sum += val;
        return sum;
    }

    @Benchmark
    public int randomAccessArrayList() {
        return arrayList.get(size / 2);
    }

    @Benchmark
    public int randomAccessLinkedList() {
        return linkedList.get(size / 2); // O(n)!
    }
}
```

---

## Real Benchmark Nümunəsi: Serialization

```java
@BenchmarkMode(Mode.Throughput)
@OutputTimeUnit(TimeUnit.MILLISECONDS)
@State(Scope.Benchmark)
@Warmup(iterations = 5, time = 1)
@Measurement(iterations = 10, time = 1)
@Fork(1)
public class SerializationBenchmark {

    private ObjectMapper jackson;
    private Gson gson;
    private Order testOrder;

    @Setup(Level.Trial)
    public void setup() throws Exception {
        jackson = new ObjectMapper();
        gson = new Gson();
        testOrder = createTestOrder();
    }

    @Benchmark
    public String jacksonSerialize() throws Exception {
        return jackson.writeValueAsString(testOrder);
    }

    @Benchmark
    public Order jacksonDeserialize(Blackhole bh) throws Exception {
        String json = jackson.writeValueAsString(testOrder);
        return jackson.readValue(json, Order.class);
    }

    @Benchmark
    public String gsonSerialize() {
        return gson.toJson(testOrder);
    }

    @Benchmark
    public Order gsonDeserialize() {
        String json = gson.toJson(testOrder);
        return gson.fromJson(json, Order.class);
    }

    private Order createTestOrder() {
        return Order.builder()
            .id(UUID.randomUUID())
            .userId(1L)
            .items(List.of(new OrderItem(1L, 2, BigDecimal.valueOf(29.99))))
            .status(OrderStatus.PENDING)
            .createdAt(LocalDateTime.now())
            .build();
    }
}
```

---

## Benchmark Nəticəsi

```
Benchmark                                Mode  Cnt     Score    Error  Units
SerializationBenchmark.jacksonSerialize  thrpt   10  2847.234 ± 45.12  ops/ms
SerializationBenchmark.gsonSerialize     thrpt   10  1923.451 ± 32.78  ops/ms
SerializationBenchmark.jacksonDeserializ thrpt   10  1456.321 ± 28.90  ops/ms
SerializationBenchmark.gsonDeserialize   thrpt   10   987.654 ± 19.45  ops/ms

# Score: daha yüksək = daha sürətli (Throughput mode-da)
# Error: ± standart deviation — daha kiçik = daha stabil
```

---

## Praktik Baxış

**Nə zaman JMH:**
- String concatenation vs StringBuilder vs formatted — real fərq nədir?
- HashMap vs ConcurrentHashMap — read-heavy workload-da
- Stream vs for loop — böyük kolleksiyada
- JSON serializer seçimi (Jackson vs Gson vs Jsonb)
- Cache implementation müqayisəsi

**Ümumi tələlər:**
```java
// ❌ Constant folding — JIT nəticəni compile-time-da hesablayır
@Benchmark
public int wrongBenchmark() {
    int a = 10;
    int b = 20;
    return a + b; // JIT → return 30; metodun içini boşaldır
}

// ✅ State-dən götür
@State(Scope.Thread)
public static class NumberState {
    int a = 10;
    int b = 20;
}

@Benchmark
public int correctBenchmark(NumberState state) {
    return state.a + state.b;
}
```

**Warmup vacibdir:**
```
İlk iteration-lar yavaş olur:
  Iteration 1:  3245 ns/op  ← JIT hələ kompile etməyib (interpreted)
  Iteration 2:  1876 ns/op  ← C1 compiler işə düşdü
  Iteration 3:   234 ns/op  ← C2 (full optimization) — bu dəyər daha realdır
  Iteration 4:   231 ns/op
  Iteration 5:   229 ns/op  ← stable
```

**Fork sayı:**
- `@Fork(1)` — sürətli amma JVM state-ə həssas
- `@Fork(2)` — hər fork ayrı JVM prosesiyə, daha etibarlı
- `@Fork(0)` — yalnız IDE-dən çalışdıranda (production üçün deyil)

---

## İntervyu Sualları

### 1. Niyə `System.nanoTime()` ilə benchmark etmək olmaz?
**Cavab:** JVM warmup yoxdur — ilk iteration-lar interpreted mode-da çalışır, C1/C2 JIT hələ işə düşməyib. JIT dead code elimination: heç yerdə istifadə edilməyən hesablamanı kompilyator silir. Constant folding: sabit dəyərli ifadəni compile-time-da hesablayır. GC pause-ları ölçüldən çıxarılmır. JMH bütün bu problemi həll edir.

### 2. Blackhole nədir, niyə lazımdır?
**Cavab:** JIT compiler "bu nəticə heç yerdə istifadə edilmir" görüb kodu tamamilə silə bilər — dead code elimination. `Blackhole.consume()` nəticəni "istehlak edir": JIT silə bilmir, amma real hesablama da baş vermir. Birdən çox nəticəni ölçmək lazım olduqda — `return` yalnız bir dəyər qaytara bilər — `Blackhole` həldir.

### 3. @Fork nədir?
**Cavab:** Hər fork ayrı JVM prosesini başladır. Bu vacibdir çünki: (1) öncəki benchmark-ın JIT compilation nəticəsi (code cache) sıfırlanır, (2) JVM startup parametrləri eyni olur, (3) GC state-i sıfırlanır. `@Fork(1)` minimum, `@Fork(3)` daha etibarlı. `@Fork(0)` — ayrı JVM prosesi olmadan, IDE-dən debug etmək üçün.

### 4. @State(Scope.Benchmark) vs @State(Scope.Thread)?
**Cavab:** `Scope.Thread` — hər thread öz instance-ını işlədir, thread-local data, race condition yoxdur. `Scope.Benchmark` — bütün thread-lər paylaşır, concurrent access benchmark-ları üçün (ConcurrentHashMap vs synchronized HashMap müqayisəsi). `Scope.Thread` default — ən çox istifadə edilən.

*Son yenilənmə: 2026-04-27*
