# Backpressure (Lead ⭐⭐⭐⭐)

## İcmal
Backpressure, sistemin öz daxilindəki yükün artdığını producer-ə siqnal verməsi mexanizmidir. Consumer, producer-dən daha yavaş iş görürsə — buffer dolar, consumer yükləndiyini producer-ə bildirir, producer sürətini azaldır. Əks halda — buffer tükənir, sistem crash olur. Reactive sistemlər, streaming, message queue-lar backpressure-u fərqli şəkildə idarə edir.

## Niyə Vacibdir
Twitter, Netflix, Uber kimi şirkətlər milyonlarla event-i real-time emal edirlər. Backpressure olmadan spike zamanı sistemin crash olması qaçılmazdır. Lead mühəndis backpressure-u yalnız "queue var, buffer var" kimi deyil — pull vs push modeli, bounded queue, load shedding, rate limiting ilə əlaqəsini izah edə bilir.

## Əsas Anlayışlar

### 1. Backpressure Problemi
```
Push model (backpressure yoxdur):
  Producer: 10K msg/sec göndərir
  Consumer: 1K msg/sec emal edir
  Buffer: 9K msg/sec artar
  
  T=0: Buffer 0 msg
  T=1s: Buffer 9K msg
  T=10s: Buffer 90K msg
  T=100s: Buffer 900K msg → memory dolur → OOM → CRASH

Çözüm yolları:
  1. Producer-i yavaşlat (backpressure)
  2. Consumer-i artır (scale)
  3. Load shedding (bəzi request-ləri at)
  4. Bounded buffer (overflow olduqda reject)
```

### 2. Pull vs Push Model

**Push model:**
```
Producer → Consumer (producer sürəti ilə)
Consumer: Gələn hər şeyi qəbul etməlidir
Backpressure: Çətin (producer-i nə zaman yavaşlat?)

Nümunə:
  WebSocket stream: Server client-ə push edir
  HTTP/1.1: Server response yazır, client oxumasa buffer dolar
```

**Pull model:**
```
Consumer → Producer: "Hazıram, 100 event ver"
Producer: Tələb olunan qədər göndərir
Consumer sürəti sistemi avtomatik idarə edir

Nümunə:
  Kafka consumer: poll(100ms) → max 100 message
  Consumer işlədikdə sonrakı poll edir
  Backpressure: Automatic (consumer poll etmirsə delivery olmur)
```

### 3. Kafka Backpressure
```
Kafka pull-based model:

Consumer:
  while True:
    records = consumer.poll(max_records=100, timeout_ms=1000)
    for record in records:
      process(record)    // işlənir
    consumer.commitSync()  // offset advance

Backpressure:
  Consumer yavaş işlədikdə → poll daha az gəlir
  Kafka broker: messages saxlayır (retention period)
  Consumer lag artır (monitor edilir!)
  
Consumer lag alert:
  alert: consumer_lag > 10,000 → Consumer yetişmir
  Action: Consumer sayını artır (horizontal scale)
          Ya da processing optimize et

Max.poll.records tuning:
  Çox yüksək: Consumer per-poll çox işlə → poll interval uzanır
  Çox aşağı: Overhead artır, throughput düşür
  Optimal: İşlənmə zamanına görə ayarla
```

### 4. gRPC Streaming Backpressure
```
gRPC server streaming:
  Server → Client (flow control via HTTP/2 WINDOW_UPDATE)

HTTP/2 flow control:
  Initial window: 65,535 bytes (default)
  Client: Window dolduqda WINDOW_UPDATE göndərmir
  Server: Window 0 olduqda gözləyir (backpressure!)
  Client: Data emal etdikdə WINDOW_UPDATE göndərir
  Server: Göndərməyə davam edir

gRPC-nin built-in backpressure:
  Application level müdaxilə lazım deyil
  HTTP/2 avtomatik idarə edir
```

### 5. Reactive Streams (Project Reactor, RxJava)
```java
// Project Reactor: backpressure with operators

Flux.fromIterable(largeDataset)
    .onBackpressureBuffer(1000)  // bounded buffer
    .flatMap(item -> processAsync(item), 
             16)  // max 16 concurrent
    .subscribe(
        result -> handleResult(result),
        error -> handleError(error)
    );

// Backpressure strategies:
// onBackpressureBuffer(N): N-ə qədər buffer, sonra error
// onBackpressureDrop(): Buffer dolu → yeni item-ları at
// onBackpressureLatest(): Buffer dolu → yalnız son item saxla
// onBackpressureError(): Buffer dolu → error signal
```

### 6. Bounded Queue (Thread Pool)
```
Thread pool executor:
  Core threads: 10
  Max threads: 50
  Queue: LinkedBlockingQueue(1000)  // bounded!
  
  Gelen task:
  1. Core thread available → run immediately
  2. Core full → add to queue
  3. Queue full + threads < max → create new thread
  4. Queue full + threads = max → REJECT (backpressure!)

Rejection policy:
  AbortPolicy:       Exception throw (default)
  CallerRunsPolicy:  Calling thread-də run (producer slows down!)
  DiscardPolicy:     Task-ı at (no exception)
  DiscardOldestPolicy: Queue-dakı ən köhnəni at, yenisini əlavə et

CallerRunsPolicy = backpressure mechanism:
  Queue dolu → Calling thread task-ı özü edir
  → Calling thread meşğul → daha az task submit edir
  → Natural rate limiting
```

### 7. Load Shedding
```
Backpressure-un ən aggressive forması:
Sistem həddini keçdikdə → bəzi request-ləri reject et

Strategies:

1. Rate limiting (already processed separately)
   → 429 Too Many Requests

2. Shed lowest priority requests:
   Priority queue: VIP users first, free users last
   Free user request + queue full → 503 Service Unavailable

3. Probabilistic shedding:
   Queue 80% dolu → 20% chance reject
   Queue 90% dolu → 50% chance reject
   Queue 100% dolu → 100% reject
   
4. Timeout-based shedding:
   Request queue-da > 2 saniyə → discard (client timed out anyway)
   
5. Circuit breaker:
   Downstream failing → reject early (fast fail)
   → Circuit breaker backpressure form-udur
```

### 8. Database Connection Pool Backpressure
```
PgBouncer (PostgreSQL connection pooler):
  pool_size: 100 (max DB connections)
  max_client_conn: 10,000

  1001-ci request gəlir:
  Option 1: wait (queue with timeout)
  Option 2: error immediately (fail fast)

Laravel connection pool:
  DB_POOL_SIZE=100
  DB_POOL_TIMEOUT=5000  // 5s wait

  5 saniyə connection alınamazsa:
  → PDOException: Could not get a database connection

Application-level backpressure:
  Queue size monitoring:
    IF pg_pool_utilization > 90%
    THEN request_rate limit to current_capacity × 0.8
```

### 9. Nginx/Load Balancer Backpressure
```nginx
# Backend-lər yavaş işlədikdə

upstream backend {
    server backend1:8080;
    server backend2:8080;
    
    # Connection timeout
    keepalive_timeout 60s;
    keepalive_requests 100;
}

# Limit simultaneous connections to backend
server {
    location /api/ {
        proxy_pass http://backend;
        
        # Queue size (max pending requests to backend)
        proxy_connect_timeout 5s;
        proxy_read_timeout 30s;
        proxy_send_timeout 30s;
        
        # If backend unresponsive → 503
    }
}
```

**NGINX-dəki backpressure:**
```
Backend connections dolu →
Client: 503 Service Unavailable (fail fast)
Client: Retry-After header ilə exponential backoff
```

### 10. Monitoring Backpressure
```
Key metrics:

Queue depth:
  - Kafka consumer lag (max acceptable: SLO × processing time)
  - Thread pool queue size
  - DB connection pool wait count

Rejection rate:
  - HTTP 429 rate (rate limiting)
  - HTTP 503 rate (load shedding)
  - Thread pool rejection count

Processing latency vs throughput:
  - If latency up + throughput plateau → backpressure forming
  
Buffer utilization:
  - Kafka partition offset lag
  - RabbitMQ queue depth
  - Redis stream XLEN (pending entries)

Alerting:
  Kafka lag > 100K → consumer behind, scale up
  Thread pool queue > 80% → reduce ingestion rate
  503 rate > 5% → load shedding active, investigate
```

## Praktik Baxış

### Interview-da Necə Yanaşmaq
1. "Consumer-lar producer-dən yavaş işlədikdə nə olur?" sualını özünüzə soruşun
2. Queue-un sonsuz olmadığını qeyd et (bounded queue)
3. Pull-based model-in niyə daha natural backpressure verdiyini izah et
4. Load shedding-i qeyd et (backpressure-un son həddi)
5. Consumer lag monitoring-i izah et

### Ümumi Namizəd Səhvləri
- "Queue əlavə etdik, problem həll oldu" demək
- Bounded queue vs unbounded queue fərqini bilməmək
- Pull vs push modelini müzakirə etməmək
- Load shedding strategiyasını bilməmək
- Consumer lag monitoring-i qeyd etməmək

### Senior vs Architect Fərqi
**Senior**: Kafka consumer lag monitor edir, bounded queue konfigurasiya edir, load shedding implement edir.

**Architect**: Backpressure propagation (end-to-end: from database to client), adaptive load shedding (sistemin sağlamlığına görə dinamik threshold), graceful degradation strategy (VIP vs free user priority), backpressure-un SLO ilə uyğunluğu, cost of backpressure (rejected requests = lost revenue), multi-tier backpressure (CDN → Gateway → Service → Database her layerdə).

## Nümunələr

### Tipik Interview Sualı
"Design the ingestion pipeline for IoT sensor data: 1M devices send metrics every 10 seconds. Processing is variable (50ms to 2s per record)."

### Güclü Cavab
```
IoT metrics ingestion backpressure:

Scale:
  1M devices × 1 event/10s = 100K events/sec
  Processing: 50ms-2s (variable, avg ~300ms)
  Single consumer: 1000ms / 300ms avg = 3.3 msg/sec per thread
  Required consumers: 100K / 3.3 = 30K threads (impossible!)
  
  Kafka partition: 100 partitions × 1K msg/sec/partition = 100K/sec
  Consumer threads: 100 (1 per partition)
  Per consumer: 1000 msg/sec / 100 = 1000 msg/sec per consumer
  Processing: 1000 msg × 300ms avg = ... TOO MUCH

Solution: Tiered processing

Tier 1: Fast ingest (Kafka, no processing)
  IoT devices → API Gateway → Kafka (100 partitions)
  Ingest: Just validate + write to Kafka (< 1ms per message)
  Backpressure: Kafka limits connection, Gateway rate limits

Tier 2: Stream processing (Kafka Streams / Flink)
  Pull-based consumers
  max.poll.records = 100
  Processing: Parallel per partition
  
  If processing is slow:
    Consumer lag increases
    Alert: lag > 50K → add consumer pods (Kubernetes KEDA autoscaling)
    KEDA: Scale consumers based on Kafka consumer lag metric

Tier 3: Storage (InfluxDB / TimescaleDB)
  Batch write: Every 1 second, bulk insert
  Backpressure: DB write slow → batch queue fills up
  DB backpressure propagates to stream processor (slow drain)
  Stream processor: Consumer lag increases → KEDA adds pods

Load Shedding:
  Burst handling: 100K/sec → 200K/sec spike
  Kafka partition replication lag > 30s → shed 50% of incoming
  Priority: Critical device alerts > routine metrics
  Routine metrics: probabilistic drop during overload

Pull model advantages (Kafka):
  Consumer controls pace
  No out-of-memory crash
  Lag = visible metric (can alert and scale)
  Replay: if consumer crashed, messages waiting

Bounded buffers at each tier:
  API Gateway: max 10K concurrent connections (reject beyond)
  Kafka: disk-based (essentially unbounded, but SSD limit)
  Consumer: in-memory batch: 1000 messages (small, bounded)
  DB write buffer: 10MB (bounded), flush every 1s or when full
```

### Backpressure Flow
```
[IoT Devices] ──push──► [API Gateway: rate limit 100K/sec]
                                │
                          [Kafka: 100 partitions]
                                │ pull (100 msg at a time)
                         [Consumer Pool: 100 threads]
                         [KEDA: autoscale on lag metric]
                                │
                          [InfluxDB: batch write]
                                │ slow writes
                         [Write buffer: bounded 10MB]
                                │ buffer full?
                         [Drop older metrics (low priority)]
```

## Praktik Tapşırıqlar
- Kafka consumer lag monitoring Grafana dashboard qurun
- Thread pool CallerRunsPolicy backpressure test edin
- KEDA: Kafka consumer lag-based autoscaling qurun
- Bounded queue overflow simulation: Java BlockingQueue
- Nginx rate limiting ilə load shedding test edin

## Əlaqəli Mövzular
- [08-message-queues.md](08-message-queues.md) — Kafka consumer groups, lag
- [09-rate-limiting.md](09-rate-limiting.md) — Rate limiting as backpressure
- [16-circuit-breaker.md](16-circuit-breaker.md) — Circuit breaker as backpressure
- [04-load-balancing.md](04-load-balancing.md) — LB-level backpressure
- [03-scalability-fundamentals.md](03-scalability-fundamentals.md) — Scale triggers
