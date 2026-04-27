# Polyglot Persistence (Lead ⭐⭐⭐⭐)

## İcmal
Polyglot persistence — bir aplikasiyada fərqli data-nın fərqli database texnologiyaları ilə saxlanması yanaşmasıdır. "Bir database hər şey üçün" yanaşmasının əksidir. Bu mövzu Lead interview-larda sistem arxitekturası düşüncənizi yoxlamaq üçün çıxır.

## Niyə Vacibdir
Real mürəkkəb sistemlər (Uber, Netflix, Shopify) hər data tipi üçün ən uyğun storage seçir. İnterviewer bu sualla sizin "bu data üçün hansı database ən yaxşı işləyir?" sualını context-ə görə cavablaya bildiyinizi, consistency challenges-ı bildiyinizi, operational complexity-ni nəzərə alıb-almadığınızı yoxlayır.

## Əsas Anlayışlar

- **Polyglot:** Mənşəyi "çox dil bilən" — burada "çox database texnologiyası istifadə edən" mənasında
- **Right Tool for Right Job:** SQL → ACID transactions; Redis → caching + session + counter; Elasticsearch → full-text search + analytics; Cassandra → time-series + write-heavy; Neo4j → graph (social, fraud)
- **Data Boundary:** Hər database öz data-sına sahib olmalıdır — cross-database foreign key yoxdur. Ownership aydın olmalıdır
- **Eventual Consistency Challenge:** A database-dəki dəyişiklik B database-ə necə çatır? — Event-driven sync, eventual consistency qəbul edilməlidir
- **Operational Complexity:** 5 fərqli database = 5 fərqli monitoring, backup, scaling, disaster recovery, team knowledge. Bu cost real-dir
- **CQRS Pattern:** Command → normalized SQL (ACID); Query → denormalized store (Elasticsearch, Redis read model). Yazma və oxuma modelini ayırmaq
- **Event-Driven Sync:** Kafka/RabbitMQ vasitəsilə database-ləri sinxronizasiya etmək. Primary → event → secondary stores
- **CDC (Change Data Capture):** Database transaction log-dan event stream — Debezium PostgreSQL WAL-ı oxuyur, Kafka-ya yazar; consumer-lar Elasticsearch, Redis, analytics yeniləyir
- **Read Model:** Denormalized, query-optimized storage (Elasticsearch) — primary SQL-dən populate olunur. Consistency eventual-dır
- **Write Model:** Normalized, ACID-compliant SQL. "Source of truth"
- **Data Lake / Warehouse:** Bütün database-lərdən data-nı analytics üçün bir yerə toplamaq (BigQuery, Snowflake, ClickHouse)
- **Microservices + Polyglot:** Hər microservice öz database-ə sahib olur — service-in işi üçün ən uyğun database seçilir
- **Cross-Database Join:** Mümkün deyil native olaraq — application layer-də in-memory JOIN lazımdır, ya da denormalized read model
- **Graph Database use-case:** Neo4j — friend-of-friend, recommendation engine, fraud detection ring
- **Vector Database use-case:** Pinecone, pgvector — semantic search, AI embeddings, RAG (Retrieval Augmented Generation)
- **When NOT to use Polyglot:** Kiçik sistemlər, az traffic, az mürəkkəblik. PostgreSQL + Redis çox hallarda kifayət edir. Operational cost-u justify etmək lazımdır

## Praktik Baxış

**Interview-da yanaşma:**
- Real nümunə ilə başlayın: "E-commerce-də hansı data store nəyə lazımdır"
- Consistency challenge-ı izah edin: "Redis-dəki dəyişiklik Elasticsearch-ə necə çatır?"
- "Operational complexity artır, buna dəyərmi?" sualını özünüz soruşun — əvvəl PostgreSQL + Redis cəhd edin

**Follow-up suallar:**
- "İki database arasında data sync-ı necə edirsiz?" — CDC + Kafka vs dual-write vs event sourcing
- "Dual-write niyə risklidir?" — Partial failure: PostgreSQL-ə yazılır, Elasticsearch-ə yazılmır → inconsistency
- "Polyglot-un nə vaxt lazım olmadığını düşünürsünüz?" — < 10K request/saniyə, < 10M row — PostgreSQL tək-tək başa gəlir
- "CDC ilə event sourcing fərqi?" — CDC = existing DB-dən event; Event Sourcing = əvvəldən event-first dizayn
- "Saga vs 2PC — polyglot-da transaction?" — 2PC distributed locks, Saga eventual consistency + compensation

**Ümumi səhvlər:**
- "Hər şey üçün ayrı database istifadə edin" demək — operational overhead real problemdir
- Operational complexity-ni qeyd etməmək
- Data consistency problemini nəzərə almamaq — "Redis-ə yazmağı unudursam nə olur?"
- Dual-write pattern-ni CDC yerinə tövsiyə etmək

**Yaxşı cavabı əla cavabdan fərqləndirən:**
- CDC + Kafka ilə sync arxitekturasını izah etmək
- "Ne vaxt polyglot lazım deyil?" sualına cavab vermək
- Real trade-off: consistency vs performance vs operational complexity
- "Biz PostgreSQL + Redis ilə başladıq, traffic artdıqca Elasticsearch əlavə etdik" kimi incremental yanaşma

## Nümunələr

### Tipik Interview Sualı
"Booking.com kimi bir platform dizayn edirsiniz. Otellərin saxlanması, axtarışı, rezervasyon, reytinq, analitika üçün hansı data store-ları seçərdiniz? Data consistency-ni necə idarə edərdiniz?"

### Güclü Cavab
Bu klassik polyglot persistence ssenarisidir. Hər domain üçün ən uyğun storage:

**Rezervasyon + ödəniş → PostgreSQL:** ACID tələbi var, transaction kritikdir — "otel alındı + ödəniş keçdi" atomik olmalıdır.

**Otel məlumatları, şəkillər → PostgreSQL + S3:** Structured data PostgreSQL-də; media faylları S3-də (CDN ilə).

**Axtarış → Elasticsearch:** Full-text search (otel adı, şəhər), filter (qiymət, reytinq), geospatial ("Bakıda 50km-lik otellər"). PostgreSQL `LIKE` query-si bu miqyasda scale olmaz.

**Real-time mövcudluq → Redis:** Otağın boş/dolu vəziyyəti — hər saniyə minlərlə sorğu gəlir, Redis microsecond latency-si ideal.

**User sessions → Redis:** TTL dəstəkli, auto-expire.

**Analitika → ClickHouse:** Booking funnel analysis, revenue reports, aggregate sorğular — OLAP workload.

**Consistency arxitekturası:** Rezervasyon PostgreSQL-ə yazılır → Debezium CDC → Kafka topic → Consumer 1: Elasticsearch-i yenilə (axtarış üçün), Consumer 2: Redis-i yenilə (availability cache), Consumer 3: ClickHouse-a yaz (analytics). Lag < 1 saniyə. Kritik vəziyyət (real-time availability check, payment) həmişə PostgreSQL-dən oxunur.

### Kod Nümunəsi
```php
// Laravel-də polyglot persistence
class BookingService
{
    public function __construct(
        private readonly DatabaseManager  $db,        // PostgreSQL
        private readonly ElasticsearchClient $elastic, // Elasticsearch
        private readonly Redis            $redis,     // Redis
        private readonly EventBus         $events,    // Kafka/RabbitMQ
    ) {}

    /**
     * Rezervasyon yarat — Write Model (PostgreSQL, ACID)
     */
    public function createBooking(CreateBookingDTO $dto): Booking
    {
        return DB::transaction(function () use ($dto) {
            // Pessimistic lock — concurrent booking-i önlə
            $room = Room::where('hotel_id', $dto->hotelId)
                ->lockForUpdate()
                ->firstOrFail();

            if (!$room->isAvailableFor($dto->checkIn, $dto->checkOut)) {
                throw new RoomNotAvailableException("Room {$room->id} is not available.");
            }

            $booking = Booking::create([
                'room_id'    => $room->id,
                'user_id'    => $dto->userId,
                'check_in'   => $dto->checkIn,
                'check_out'  => $dto->checkOut,
                'total_price' => $room->calculatePrice($dto->checkIn, $dto->checkOut),
                'status'     => 'confirmed',
            ]);

            $room->update(['status' => 'booked']);

            // Event publish — CDC ya da manual
            // CDC (Debezium) avtomatik WAL-dan oxuyacaq
            // Manual üçün:
            // $this->events->publish('booking.created', new BookingCreated($booking));

            return $booking;
        });
    }

    /**
     * Otel axtarışı — Read Model (Elasticsearch)
     */
    public function searchHotels(SearchHotelDTO $dto): array
    {
        return $this->elastic->search([
            'index' => 'hotels',
            'body'  => [
                'query' => [
                    'bool' => [
                        'must'   => [
                            ['match' => ['city' => $dto->city]],
                        ],
                        'filter' => [
                            ['range' => ['price_per_night' => [
                                'gte' => $dto->minPrice,
                                'lte' => $dto->maxPrice,
                            ]]],
                            ['range' => ['rating' => ['gte' => $dto->minRating]]],
                            $dto->coords ? ['geo_distance' => [
                                'distance' => "{$dto->radiusKm}km",
                                'location' => $dto->coords,
                            ]] : null,
                        ],
                    ],
                ],
                'sort' => [['rating' => 'desc'], ['price_per_night' => 'asc']],
                'from' => $dto->offset,
                'size' => $dto->limit,
            ],
        ]);
    }

    /**
     * Real-time mövcudluq — Redis (ultra-fast)
     */
    public function getRoomAvailability(int $hotelId, string $date): ?int
    {
        $cacheKey = "availability:{$hotelId}:{$date}";

        // Redis-dən oxu
        $cached = $this->redis->get($cacheKey);
        if ($cached !== null) {
            return (int) $cached;
        }

        // Cache miss — PostgreSQL-dən oxu
        $available = Room::where('hotel_id', $hotelId)
            ->where('status', 'available')
            ->whereDoesntHave('bookings', fn($q) =>
                $q->where('check_in', '<=', $date)
                  ->where('check_out', '>', $date)
            )
            ->count();

        // 5 dəqiqə cache et
        $this->redis->setex($cacheKey, 300, $available);
        return $available;
    }
}
```

```php
// CDC + Event-driven sync
// Kafka Consumer: PostgreSQL bookings → Elasticsearch
class BookingElasticSyncer implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function handle(): void
    {
        // Kafka message-dan gəlir (Debezium CDC format):
        // {"op": "c", "after": {"id": 1, "hotel_id": 5, ...}}
    }
}

// Event Listener — manual event-driven sync
class SyncBookingToSearch
{
    public function __construct(
        private readonly ElasticsearchClient $elastic,
        private readonly Redis $redis,
    ) {}

    public function handle(BookingCreated $event): void
    {
        $booking = $event->booking;

        // Elasticsearch-i yenilə
        $this->elastic->update([
            'index' => 'hotels',
            'id'    => $booking->hotel_id,
            'body'  => [
                'doc' => [
                    'available_rooms' => $booking->hotel->available_rooms_count,
                    'last_booking_at' => now()->toISOString(),
                ],
            ],
        ]);

        // Redis availability cache-i invalidate et
        $date     = $booking->check_in->format('Y-m-d');
        $cacheKey = "availability:{$booking->hotel_id}:{$date}";
        $this->redis->del($cacheKey);
    }
}
```

```yaml
# CDC arxitekturası (Debezium + Kafka + Consumers)
# debezium-postgres-connector.json
{
  "name": "booking-postgres-connector",
  "config": {
    "connector.class": "io.debezium.connector.postgresql.PostgresConnector",
    "database.hostname": "postgres",
    "database.port": "5432",
    "database.user": "replication_user",
    "database.password": "${DB_PASSWORD}",
    "database.dbname": "bookingdb",
    "table.include.list": "public.bookings,public.hotels,public.rooms",
    "plugin.name": "pgoutput",
    "slot.name": "debezium_booking_slot",
    "topic.prefix": "booking",
    "transforms": "unwrap",
    "transforms.unwrap.type": "io.debezium.transforms.ExtractNewRecordState",
    "transforms.unwrap.drop.tombstones": "false"
  }
}

# Kafka topics yaranır:
# booking.public.bookings   — booking create/update/delete events
# booking.public.hotels     — hotel events
# booking.public.rooms      — room events

# Consumer groups:
# elastic-indexer   → Elasticsearch-i yenilə
# redis-invalidator → Redis cache invalidate et
# analytics-writer  → ClickHouse-a yaz
# notification-svc  → user-ə email/push göndər
```

```python
# Polyglot data access map — Python pseudo-code
class DataAccessLayer:
    """
    Polyglot persistence data access layer.
    Hər metod hansı DB-dən oxuyub niyə yazır — aydın olmalıdır.
    """

    def create_booking(self, dto):
        """PostgreSQL — ACID transaction tələb olunur"""
        return self.postgres.transaction(lambda: self._do_booking(dto))

    def search_hotels(self, query, filters, geo):
        """Elasticsearch — full-text + geo + faceted search"""
        return self.elastic.search(index='hotels', query=query)

    def get_realtime_availability(self, hotel_id, date):
        """Redis — sub-millisecond latency, soft state"""
        return self.redis.get(f"avail:{hotel_id}:{date}")
              or self._load_and_cache(hotel_id, date)

    def get_booking_analytics(self, date_range, group_by):
        """ClickHouse — OLAP, aggregate queries"""
        return self.clickhouse.query(
            "SELECT toDate(created_at), count(), sum(total_price) "
            "FROM bookings WHERE created_at BETWEEN %(start)s AND %(end)s "
            "GROUP BY 1 ORDER BY 1",
            params=date_range
        )

    def get_hotel_recommendations(self, user_id):
        """Neo4j — collaborative filtering via graph traversal"""
        return self.neo4j.run(
            "MATCH (u:User {id: $userId})-[:BOOKED]->(h:Hotel)"
            "<-[:BOOKED]-(similar:User)-[:BOOKED]->(rec:Hotel) "
            "WHERE NOT (u)-[:BOOKED]->(rec) "
            "RETURN rec, count(*) AS score ORDER BY score DESC LIMIT 10",
            userId=user_id
        )
```

### İkinci Nümunə — When NOT to Polyglot

```
Antipattern: Hər şey üçün ayrı database

users        → PostgreSQL
sessions     → Redis       ← OK
search       → Elasticsearch ← OK əgər geniş search var
files        → S3           ← OK həmişə
analytics    → ClickHouse   ← OK yalnız yüksək volume-da
recommendations → Neo4j     ← OK yalnız həqiqətən graph problem-dirsə
feature flags → ???         ← PostgreSQL kifayət edir!
notifications → ???         ← PostgreSQL + queue kifayət edir!
config       → ???          ← PostgreSQL kifayət edir!

Qayda: Hər yeni database üçün özün soruş:
1. Bu problem PostgreSQL + Redis ilə həll olunurmu?
2. Bu database-in operational cost-u (backup, monitoring, team knowledge)
   əldə etdiyim faydadan azdırmı?
3. Team-in bu DB-ni production-da idarə etmək təcrübəsi varmı?

Əgər 3 sualın cavabı "yes/maybe" isə — simpler database seç.
```

## Praktik Tapşırıqlar

- E-commerce layihəniz üçün data store xəritəsi çəkin: hər entity üçün hansı DB seçərdiniz, niyə
- Redis + PostgreSQL dual-write ssenariyasında consistency bug-unu reproduce edin: PostgreSQL-ə yazın, Redis-i update etməyi "unudun", inconsistency-ni müşahidə edin; sonra CDC ilə düzəldin
- Debezium + Kafka + Elasticsearch Docker Compose stack-ı qurun: PostgreSQL-ə yazın, Elasticsearch-dən axtarın, lag-ı ölçün
- "Polyglot lazım deyil" kriteriyaları: 5 konkret ssenario siyahılayın — hansı hallarda PostgreSQL + Redis kifayət edir
- Eventual consistency-nin user experience-a təsirini araşdırın: Elasticsearch 1 saniyəlik lag — user nə görür?

## Əlaqəli Mövzular
- `01-sql-vs-nosql.md` — Hansı data store seçmək — polyglot-un əsas qərarı
- `10-database-replication.md` — Replication vs polyglot sync — fərqli problemlər
- `18-time-series-databases.md` — Analytics üçün xüsusi DB — polyglot-un bir komponenti
- `19-graph-databases.md` — Relationship-heavy data üçün — polyglot-un bir komponenti
- `20-document-stores.md` — Document store polyglot stack-ında nə vaxt
