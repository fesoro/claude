# Message Queues (Architect)

## İcmal

Message queue — servislər arası asinxron kommunikasiya üçün istifadə olunan vasitəçidir. Göndərici mesajı növbəyə qoyur, alıcı öz sürəti ilə oxuyur. Bu **decoupling** (ayırma) yaradır: göndərici alıcının mövcud olmasını gözləmir, alıcı isə göndərici sürətindən asılı olmur.

Go ekosistemi üç əsas message broker ilə işləyir: **Apache Kafka** (yüksək həcm, log-əsaslı), **RabbitMQ** (çevik routing, AMQP), **NATS** (sadə, yüksək performans, cloud-native). Architect səviyyəsində broker seçimi, consumer strategy, idempotency, dead letter queue (DLQ) və graceful shutdown dizaynı bilinməlidir.

## Niyə Vacibdir

- Sinxron HTTP call-lar zəncir xəta riskini artırır — bir servis düşəndə hamısı düşür
- Message queue ilə yük zirvəsini hamarlamaq (load leveling) mümkündür
- Retry, dead letter, consumer group — davamlılıq mexanizmləri hazırdır
- Event-driven arxitektura — microservice-lər arasında loose coupling

## Əsas Anlayışlar

**Kafka anlayışları:**
- **Topic**: mesajların kateqoriyası (məs: `orders`, `payments`)
- **Partition**: topic-in paralel hissəsi — paralel oxuma mümkündür
- **Consumer Group**: eyni group-da olanlar partition-ları bölüşür
- **Offset**: consumer-in oxuduğu yer — yenidən oxuma mümkündür
- **Retention**: Kafka mesajları silmir, müəyyən müddət saxlayır

**RabbitMQ anlayışları:**
- **Exchange**: mesajları routing rule ilə queue-lara yönləndirir
- **Queue**: mesajların saxlandığı yer
- **Binding**: exchange → queue əlaqəsi
- **Routing Key**: hansı queue-ya gedəcəyini müəyyən edir
- **Acknowledgment**: consumer mesajı aldığını təsdiqləyir

**NATS anlayışları:**
- **Subject**: topic analoji (`orders.new`, `payments.*`)
- **Queue Group**: load balancing — yalnız bir subscriber alır
- **JetStream**: persistence əlavə edir, at-least-once delivery

**Mesaj pattern-ləri:**
- Pub/Sub: bir göndərici, çox alıcı (hər biri alır)
- Queue: bir göndərici, çox worker (yalnız biri alır — load balancing)
- Request/Reply: asinxron amma cavab gözlənilən
- Fan-out: bir mesaj hər subscriber-ə ayrıca gedər

**Delivery semantics:**
- At-most-once: mesaj bir dəfə gələ bilər, itə bilər (fire-and-forget)
- At-least-once: mütləq çatır, amma iki dəfə gələ bilər (ən yaygın)
- Exactly-once: mürəkkəb, transaction lazımdır (Kafka transactions)

## Praktik Baxış

**Broker seçim kriteriyaları:**
- Kafka: >100K msg/s, audit log, event sourcing, uzunmüddətli saxlama
- RabbitMQ: mürəkkəb routing, priority queue, task queue, AMQP ekosistemi
- NATS: microservice pub/sub, low latency, sadə quraşdırma, cloud-native

**Nə vaxt message queue istifadə etmə:**
- Cavab gözlənilən (sinxron) işlər — HTTP/gRPC daha uyğundur
- Sadə, tək servisli tətbiq — overhead-ə dəyməz
- Test mühiti — komplekslik artır

**Trade-off-lar:**
- Asinxron → debuging çətin, latency artar (queue gözləmə)
- Idempotency tətbiq etmək lazımdır — at-least-once ilə ikinci gəlmə
- Consumer group scale-up asan, amma partition sayından çox consumer işə düşmür (Kafka)
- DLQ olmadan itirilən mesajlar heç vaxt görünmür

**Common mistakes:**
- Consumer-i durdurmadan yeni pod salmaq — offset duplicate problem
- DLQ-nu monitor etməmək — mesajlar gizlicə itirir
- Mesaj ölçüsünü artırmaq (MB-larla) — queue tıxanır, timeout olur
- Bütün xətaları retry etmək — poison message sonsuz döngü yaradır

## Nümunələr

### Nümunə 1: Kafka ilə Go — kafka-go

```go
package main

import (
    "context"
    "encoding/json"
    "fmt"
    "log/slog"
    "time"

    "github.com/segmentio/kafka-go"
)

// go get github.com/segmentio/kafka-go

// Sifariş strukturu
type Order struct {
    ID       string  `json:"id"`
    Product  string  `json:"product"`
    Quantity int     `json:"quantity"`
    Amount   float64 `json:"amount"`
}

// ---- PRODUCER ----
type KafkaProducer struct {
    writer *kafka.Writer
    logger *slog.Logger
}

func NewKafkaProducer(brokers []string, topic string) *KafkaProducer {
    return &KafkaProducer{
        writer: &kafka.Writer{
            Addr:         kafka.TCP(brokers...),
            Topic:        topic,
            Balancer:     &kafka.LeastBytes{},    // partition seçimi
            RequiredAcks: kafka.RequireAll,        // lider + replica-lar təsdiqləsin
            Async:        false,                   // sinxron — təsdiq gözlə
            Compression:  kafka.Snappy,            // sıxışdırma
            BatchTimeout: 10 * time.Millisecond,   // batch göndərmə
        },
        logger: slog.Default(),
    }
}

func (p *KafkaProducer) SendOrder(ctx context.Context, order Order) error {
    data, err := json.Marshal(order)
    if err != nil {
        return fmt.Errorf("json marshal xətası: %w", err)
    }

    msg := kafka.Message{
        Key:   []byte(order.ID),    // eyni key → eyni partition (sıralama)
        Value: data,
        Headers: []kafka.Header{
            {Key: "content-type", Value: []byte("application/json")},
            {Key: "version", Value: []byte("1")},
        },
        Time: time.Now(),
    }

    if err := p.writer.WriteMessages(ctx, msg); err != nil {
        p.logger.Error("Kafka mesaj göndərmə xətası",
            slog.String("order_id", order.ID),
            slog.String("error", err.Error()),
        )
        return fmt.Errorf("mesaj göndərmə xətası: %w", err)
    }

    p.logger.Info("Sifariş Kafka-ya göndərildi",
        slog.String("order_id", order.ID),
        slog.String("product", order.Product),
    )
    return nil
}

func (p *KafkaProducer) Close() error {
    return p.writer.Close()
}

// ---- CONSUMER ----
type KafkaConsumer struct {
    reader  *kafka.Reader
    logger  *slog.Logger
    handler func(ctx context.Context, order Order) error
}

func NewKafkaConsumer(brokers []string, topic, groupID string, handler func(context.Context, Order) error) *KafkaConsumer {
    return &KafkaConsumer{
        reader: kafka.NewReader(kafka.ReaderConfig{
            Brokers:        brokers,
            Topic:          topic,
            GroupID:        groupID,
            MinBytes:       1,        // minimum gözlə
            MaxBytes:       10 << 20, // 10MB max
            CommitInterval: 0,        // manual commit
            StartOffset:    kafka.LastOffset, // yalnız yeni mesajlar
        }),
        logger:  slog.Default(),
        handler: handler,
    }
}

func (c *KafkaConsumer) Run(ctx context.Context) error {
    c.logger.Info("Kafka consumer başladı")

    for {
        // Context ləğv olunanda çıx
        if ctx.Err() != nil {
            c.logger.Info("Context ləğv edildi, consumer dayanır")
            return nil
        }

        msg, err := c.reader.FetchMessage(ctx)
        if err != nil {
            if ctx.Err() != nil {
                return nil
            }
            c.logger.Error("Mesaj oxuma xətası", slog.String("error", err.Error()))
            continue
        }

        c.logger.Info("Mesaj alındı",
            slog.String("topic", msg.Topic),
            slog.Int("partition", msg.Partition),
            slog.Int64("offset", msg.Offset),
        )

        // Mesajı emal et
        var order Order
        if err := json.Unmarshal(msg.Value, &order); err != nil {
            c.logger.Error("JSON parse xətası — DLQ-ya göndər",
                slog.String("error", err.Error()),
                slog.Int64("offset", msg.Offset),
            )
            // Poison message — commit et, DLQ-ya göndər
            c.reader.CommitMessages(ctx, msg)
            continue
        }

        if err := c.handler(ctx, order); err != nil {
            c.logger.Error("Mesaj emal xətası — retry",
                slog.String("order_id", order.ID),
                slog.String("error", err.Error()),
            )
            // Retry etmək üçün commit etmirik — yenidən gələcək
            // Amma sonsuz döngü riski var — DLQ strategiyası lazım
            continue
        }

        // Uğurlu emal — offset commit et
        if err := c.reader.CommitMessages(ctx, msg); err != nil {
            c.logger.Error("Offset commit xətası", slog.String("error", err.Error()))
        }
    }
}

func (c *KafkaConsumer) Close() error {
    return c.reader.Close()
}
```

### Nümunə 2: RabbitMQ ilə Go

```go
package main

import (
    "context"
    "encoding/json"
    "fmt"
    "log/slog"
    "time"

    amqp "github.com/rabbitmq/amqp091-go"
)

// go get github.com/rabbitmq/amqp091-go

type RabbitMQClient struct {
    conn    *amqp.Connection
    channel *amqp.Channel
    logger  *slog.Logger
}

func NewRabbitMQClient(url string) (*RabbitMQClient, error) {
    conn, err := amqp.Dial(url)
    if err != nil {
        return nil, fmt.Errorf("RabbitMQ bağlantı xətası: %w", err)
    }

    ch, err := conn.Channel()
    if err != nil {
        conn.Close()
        return nil, fmt.Errorf("kanal açma xətası: %w", err)
    }

    // Prefetch — bir consumer-ın eyni anda max neçə mesaj alacağı
    ch.Qos(10, 0, false)

    return &RabbitMQClient{
        conn:    conn,
        channel: ch,
        logger:  slog.Default(),
    }, nil
}

// DLQ ilə queue yaratmaq
func (r *RabbitMQClient) SetupQueues() error {
    // 1. DLQ (Dead Letter Queue) yaradın
    _, err := r.channel.QueueDeclare(
        "orders.dlq",
        true,  // durable
        false, // auto-delete
        false, // exclusive
        false, // no-wait
        nil,
    )
    if err != nil {
        return fmt.Errorf("DLQ yaratma xətası: %w", err)
    }

    // 2. Əsas queue — DLQ ilə əlaqəli
    args := amqp.Table{
        "x-dead-letter-exchange":    "",           // default exchange
        "x-dead-letter-routing-key": "orders.dlq", // DLQ-ya göndər
        "x-message-ttl":             int32(300000), // 5 dəqiqə TTL
        "x-max-retries":             int32(3),      // max retry sayı
    }

    _, err = r.channel.QueueDeclare(
        "orders",
        true,  // durable — server restart-dan salamat
        false,
        false,
        false,
        args,
    )
    return err
}

// Mesaj göndərmək
func (r *RabbitMQClient) PublishOrder(ctx context.Context, order Order) error {
    data, err := json.Marshal(order)
    if err != nil {
        return err
    }

    return r.channel.PublishWithContext(ctx,
        "",       // exchange (default)
        "orders", // routing key = queue adı
        false,    // mandatory
        false,    // immediate
        amqp.Publishing{
            DeliveryMode:  amqp.Persistent, // disk-ə yazılır — restart-a tab gətirir
            ContentType:   "application/json",
            Body:          data,
            MessageId:     order.ID,         // idempotency üçün
            Timestamp:     time.Now(),
            CorrelationId: generateCorrelationID(),
        },
    )
}

// Mesaj almaq
func (r *RabbitMQClient) ConsumeOrders(ctx context.Context, handler func(Order) error) error {
    msgs, err := r.channel.Consume(
        "orders",
        "",    // consumer tag (boş = avtomatik)
        false, // auto-ack: FALSE — manual acknowledge
        false, // exclusive
        false, // no-local
        false, // no-wait
        nil,
    )
    if err != nil {
        return fmt.Errorf("consume başlatma xətası: %w", err)
    }

    for {
        select {
        case <-ctx.Done():
            r.logger.Info("Consumer dayanır")
            return nil

        case msg, ok := <-msgs:
            if !ok {
                return fmt.Errorf("mesaj kanalı bağlandı")
            }

            var order Order
            if err := json.Unmarshal(msg.Body, &order); err != nil {
                r.logger.Error("Parse xətası — ACK et, DLQ-ya gedəcək")
                // Poison message — ACK et (DLQ args ilə avtomatik oraya gedər)
                msg.Ack(false)
                continue
            }

            if err := handler(order); err != nil {
                r.logger.Error("Emal xətası — NACK",
                    slog.String("order_id", order.ID),
                    slog.String("error", err.Error()),
                )
                // requeue=false → DLQ-ya göndər (x-dead-letter-routing-key)
                msg.Nack(false, false)
                continue
            }

            // Uğurlu — ACK et
            msg.Ack(false)
        }
    }
}

func (r *RabbitMQClient) Close() {
    r.channel.Close()
    r.conn.Close()
}

func generateCorrelationID() string {
    return fmt.Sprintf("corr-%d", time.Now().UnixNano())
}
```

### Nümunə 3: Idempotency — təkrar mesaj qorunması

```go
package main

import (
    "context"
    "fmt"
    "sync"
    "time"
)

// Idempotency yoxlayıcı — production-da Redis istifadə edin
type IdempotencyChecker struct {
    mu      sync.Mutex
    cache   map[string]time.Time
    ttl     time.Duration
}

func NewIdempotencyChecker(ttl time.Duration) *IdempotencyChecker {
    return &IdempotencyChecker{
        cache: make(map[string]time.Time),
        ttl:   ttl,
    }
}

func (ic *IdempotencyChecker) IsProcessed(messageID string) bool {
    ic.mu.Lock()
    defer ic.mu.Unlock()

    t, exists := ic.cache[messageID]
    if !exists {
        return false
    }

    // TTL bitibsə — köhnə qeyd, yenidən emal oluna bilər
    if time.Since(t) > ic.ttl {
        delete(ic.cache, messageID)
        return false
    }

    return true
}

func (ic *IdempotencyChecker) MarkProcessed(messageID string) {
    ic.mu.Lock()
    defer ic.mu.Unlock()
    ic.cache[messageID] = time.Now()
}

// Redis ilə idempotency (production üçün)
type RedisIdempotency struct {
    // redis *redis.Client
    ttl time.Duration
}

func (r *RedisIdempotency) IsProcessed(ctx context.Context, messageID string) (bool, error) {
    // SET message_id 1 NX EX <ttl_seconds>
    // NX = yalnız mövcud deyilsə set et
    // Əgər set olunubsa — yeni (işlənməyib)
    // Əgər set olunmayıbsa — köhnə (artıq işlənib)

    // result, err := r.redis.SetNX(ctx, "idempotency:"+messageID, 1, r.ttl).Result()
    // return !result, err

    return false, nil // mock
}

// Handler-də istifadə
type OrderProcessor struct {
    idempotency *IdempotencyChecker
}

func (p *OrderProcessor) ProcessOrder(ctx context.Context, order Order) error {
    // Artıq işlənibmi?
    if p.idempotency.IsProcessed(order.ID) {
        // Bu normal — at-least-once delivery
        return nil
    }

    // Əsas emal
    if err := p.doProcessOrder(ctx, order); err != nil {
        return fmt.Errorf("sifariş emalı xətası: %w", err)
    }

    // İşləndi kimi qeyd et
    p.idempotency.MarkProcessed(order.ID)
    return nil
}

func (p *OrderProcessor) doProcessOrder(ctx context.Context, order Order) error {
    // Real business logic...
    return nil
}

type Order struct {
    ID       string
    Product  string
    Quantity int
    Amount   float64
}
```

### Nümunə 4: Dead Letter Queue — retry ilə DLQ pattern

```go
package main

import (
    "context"
    "fmt"
    "log/slog"
    "math"
    "math/rand"
    "time"
)

type RetryableProcessor struct {
    maxRetries int
    handler    func(ctx context.Context, msg Message) error
    dlqSender  func(ctx context.Context, msg Message, reason string) error
    logger     *slog.Logger
}

type Message struct {
    ID          string
    Body        []byte
    RetryCount  int
    OriginalErr string
}

func (p *RetryableProcessor) Process(ctx context.Context, msg Message) error {
    var lastErr error

    for attempt := 0; attempt <= p.maxRetries; attempt++ {
        if attempt > 0 {
            // Exponential backoff + jitter
            backoff := time.Duration(math.Pow(2, float64(attempt))) * time.Second
            jitter := time.Duration(rand.Intn(1000)) * time.Millisecond
            wait := backoff + jitter

            p.logger.Info("Retry gözlənir",
                slog.String("message_id", msg.ID),
                slog.Int("attempt", attempt),
                slog.Duration("wait", wait),
            )

            select {
            case <-ctx.Done():
                return ctx.Err()
            case <-time.After(wait):
            }
        }

        err := p.handler(ctx, msg)
        if err == nil {
            if attempt > 0 {
                p.logger.Info("Retry uğurlu",
                    slog.String("message_id", msg.ID),
                    slog.Int("attempt", attempt),
                )
            }
            return nil
        }

        lastErr = err
        p.logger.Warn("Emal xətası",
            slog.String("message_id", msg.ID),
            slog.Int("attempt", attempt),
            slog.String("error", err.Error()),
        )

        // Bərpa olunmaz xəta — retry etmə, DLQ-ya göndər
        if isNonRetryable(err) {
            break
        }
    }

    // Bütün retry-lar uğursuz — DLQ-ya göndər
    p.logger.Error("Bütün retry-lar uğursuz — DLQ-ya göndərilir",
        slog.String("message_id", msg.ID),
        slog.String("last_error", lastErr.Error()),
    )

    msg.RetryCount = p.maxRetries
    msg.OriginalErr = lastErr.Error()

    if err := p.dlqSender(ctx, msg, lastErr.Error()); err != nil {
        return fmt.Errorf("DLQ göndərmə xətası: %w (original: %w)", err, lastErr)
    }

    return nil
}

// Bərpa olunmaz xəta növləri
func isNonRetryable(err error) bool {
    switch err.(type) {
    case *ValidationError:
        return true // Format xətası — retry kömək etməz
    case *AuthorizationError:
        return true // İcazə yoxdur — retry kömək etməz
    }
    return false
}

type ValidationError struct{ Msg string }
type AuthorizationError struct{ Msg string }

func (e *ValidationError) Error() string    { return "validation: " + e.Msg }
func (e *AuthorizationError) Error() string { return "authorization: " + e.Msg }
```

### Nümunə 5: Graceful consumer shutdown

```go
package main

import (
    "context"
    "log/slog"
    "os"
    "os/signal"
    "sync"
    "syscall"
    "time"
)

type Consumer struct {
    logger    *slog.Logger
    wg        sync.WaitGroup
    semaphore chan struct{} // maksimal paralel emal
}

func NewConsumer(maxParallel int) *Consumer {
    return &Consumer{
        logger:    slog.Default(),
        semaphore: make(chan struct{}, maxParallel),
    }
}

func (c *Consumer) Run(ctx context.Context, messages <-chan Message) {
    c.logger.Info("Consumer başladı")

    for {
        select {
        case <-ctx.Done():
            c.logger.Info("Context ləğv edildi, yeni mesaj almır...")
            // Mövcud işlər bitənə qədər gözlə
            c.wg.Wait()
            c.logger.Info("Bütün mesajlar emal olundu, consumer dayandı")
            return

        case msg, ok := <-messages:
            if !ok {
                c.wg.Wait()
                return
            }

            // Semaphore ilə paralel emalı məhdudlaşdır
            c.semaphore <- struct{}{}
            c.wg.Add(1)

            go func(m Message) {
                defer func() {
                    <-c.semaphore
                    c.wg.Done()
                }()
                c.processMessage(ctx, m)
            }(msg)
        }
    }
}

func (c *Consumer) processMessage(ctx context.Context, msg Message) {
    c.logger.Info("Mesaj emal edilir", slog.String("id", msg.ID))

    // Real emal...
    time.Sleep(100 * time.Millisecond) // simulyasiya

    c.logger.Info("Mesaj tamamlandı", slog.String("id", msg.ID))
}

func main() {
    ctx, cancel := signal.NotifyContext(context.Background(), os.Interrupt, syscall.SIGTERM)
    defer cancel()

    consumer := NewConsumer(10)
    messages := make(chan Message, 100)

    // Simulyasiya: mesaj göndər
    go func() {
        for i := 0; i < 50; i++ {
            select {
            case <-ctx.Done():
                return
            case messages <- Message{ID: fmt.Sprintf("msg-%d", i)}:
            }
        }
        close(messages)
    }()

    consumer.Run(ctx, messages)
    slog.Info("Proqram bitti")
}

type Message struct {
    ID   string
    Body []byte
}

import "fmt"
```

## Praktik Tapşırıqlar

**Tapşırıq 1 — Kafka ilə sifariş sistemi:**
1. Docker Compose ilə Kafka qurun (Redpanda daha sadədir: `docker run -p 9092:9092 redpandadata/redpanda`)
2. Producer: REST endpoint-dən sifariş qəbul et → Kafka-ya göndər
3. Consumer: Kafka-dan oxu → veritabanına yaz
4. Consumer Group ilə 3 instance çalışdırın — partition paylanmasını görün

**Tapşırıq 2 — RabbitMQ DLQ:**
1. Docker ilə RabbitMQ qurun: `docker run -p 5672:5672 -p 15672:15672 rabbitmq:3-management`
2. Main queue + DLQ qurun (x-dead-letter-exchange)
3. Xətalı mesajlar DLQ-ya getsin
4. Management UI (`localhost:15672`) ilə izləyin

**Tapşırıq 3 — Idempotent consumer:**
1. Redis ilə idempotency checker yazın
2. Eyni mesajı iki dəfə göndərin
3. Yalnız bir dəfə işlənməsini təsdiqlə

**Tapşırıq 4 — Graceful shutdown:**
1. Consumer-ı SIGTERM aldıqda:
   - Yeni mesaj alma
   - Mövcud mesajları bitir
   - Bağlantıları bağla
2. `kill -SIGTERM <pid>` ilə test edin

**Tapşırıq 5 — Laravel Queue-dan Kafka-ya miqrasiya:**
Konseptual tapşırıq — PHP/Laravel Queue Job-larını Go Kafka consumer-a necə köçürərdiniz? Aşağıdakiları nəzərə alın:
- Job serialization formatı
- Failed jobs DLQ əvəzinə
- Queue workers → Consumer Group
- Horizon → Prometheus metrics

## Ətraflı Qeydlər

**Kafka vs RabbitMQ seçimi:**
- Kafka: əvvəlki mesajları yenidən oxumaq lazımdır? Audit log? >1M msg/s? → Kafka
- RabbitMQ: routing mürəkkəbdir? Priority queue? Task queue? → RabbitMQ
- NATS: sadə pub/sub, microservice-lər arası? → NATS

**Exactly-once Kafka-da:**
- Producer: `enable.idempotence=true` + transaction API
- Consumer: Kafka transaction + DB-yə atomic commit
- Praktikada: at-least-once + idempotent consumer daha sadə

**Message ölçüsü:**
- Kafka default max: 1MB
- Böyük payload → blob store-da saxla (S3/GCS), Kafka-da yalnız reference ID göndər
- Bu pattern: "Claim Check" pattern adlanır

## PHP ilə Müqayisə

PHP/Laravel-dən gələnlər üçün: Laravel Horizon → Kafka/RabbitMQ analoji. Laravel Queue Job-ları sadə task queue-dur: Redis/SQS backend, Horizon ilə monitoring. Go-da Kafka consumer group eyni iş yükünü partition-lar vasitəsilə N instance arasında paylaşır — Laravel Queue-da bu Queue Workers ilə əldə edilir, amma coordination Laravel tərəfindən idarə edilmir. DLQ (Dead Letter Queue) Laravel-də `failed_jobs` cədvəlidir; Kafka/RabbitMQ-da əlavə konfiqurasiya lazımdır, amma daha çevik. Idempotent consumer Go-da Redis ilə idarə olunur; Laravel-də `uniqueId()` metodu eyni effekti verir.

## Əlaqəli Mövzular

- [73-microservices.md](73-microservices.md) — Microservice arxitekturasında mesajlaşma
- [74-clean-architecture.md](74-clean-architecture.md) — Message handler-in Clean Arch-da yeri
- [28-context.md](28-context.md) — Context ilə graceful shutdown
- [53-graceful-shutdown.md](53-graceful-shutdown.md) — Graceful shutdown pattern
