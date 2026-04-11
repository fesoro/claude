# System Design: Booking / Reservation System

## Mündəricat
1. [Tələblər](#tələblər)
2. [Kritik Problem: Double Booking](#kritik-problem-double-booking)
3. [Yüksək Səviyyəli Dizayn](#yüksək-səviyyəli-dizayn)
4. [PHP İmplementasiyası](#php-implementasiyası)
5. [İntervyu Sualları](#intervyu-sualları)

---

## Tələblər

```
Funksional (Otel Rezervasiyası nümunəsi):
  Mövcud otaqları axtarmaq
  Otaq rezerv etmək
  Rezervasiyanı ləğv etmək
  Mövcudluq real-time göstərmək

Qeyri-funksional:
  Consistency: eyni otaq iki şəxsə verilə bilməz (double booking yoxdur)
  Yüksək mövcudluq: 99.9%
  Aşağı gecikmə: axtarış < 100ms
  Transaksiyalar: ödəniş + rezervasiya atomik olmalıdır
  
Hesablamalar:
  100,000 otel × 100 otaq = 10M otaq
  Peak: 50,000 rezervasiya/dəqiqə
```

---

## Kritik Problem: Double Booking

```
Ssenario:
  User A: İstanbul oteli 1-ci May → görür, mövcuddur
  User B: İstanbul oteli 1-ci May → görür, mövcuddur
  
  User A: Rezerv et (başlayır)
  User B: Rezerv et (başlayır)
  
  Hər ikisi: mövcuddur → rezerv edir
  → DOUBLE BOOKING! İki nəfər eyni otağı aldı.

Həll 1 — Optimistic Locking:
  Otaq məlumatına "version" sütunu əlavə et.
  Rezerv etmədən əvvəl version oxu.
  UPDATE rooms SET version=version+1
  WHERE id=? AND version=? AND status='available'
  → 0 row affected = başqası artıq aldı → retry/conflict

Həll 2 — Pessimistic Locking:
  SELECT ... FOR UPDATE (DB-level row lock)
  Sorğu zamanı digər transaksiya bloklanır
  Throughput aşağı düşür

Həll 3 — Redis Distributed Lock:
  SETNX room:123:2026-05-01 user-A-uuid 30s
  Lock → rezervasiya axını → release
  Sürətli, amma DB ilə atomik deyil (2PC lazım)

Tövsiyə: Optimistic Locking + Retry
  Yüksək throughput + correctness
```

---

## Yüksək Səviyyəli Dizayn

```
Axtarış:
  User → Search API → Availability Service
                       ← Elasticsearch / Read DB
  Elasticsearch: sürətli fulltext + filter (şəhər, tarix, qiymət)
  Read replica: availability sorğuları üçün

Rezervasiya:
  User → Reserve → Booking Service
                   → Availability Check (pessimistic/optimistic lock)
                   → Payment Service
                   → Booking confirmed → Event
                   → Notification Service ← Event

Availability Index:
  Mövcudluq dəyişəndə → Search index yenilə (async)
  Eventual consistency: kiçik gecikmə qəbul edilir

┌─────────┐   ┌──────────────┐   ┌──────────────┐
│ Client  │──►│ Search API   │──►│Elasticsearch │
└─────────┘   └──────────────┘   └──────────────┘
     │
     │ reserve
     ▼
┌──────────────┐   ┌───────────────┐   ┌──────────────┐
│ Booking API  │──►│ Availability  │──►│   MySQL DB   │
└──────────────┘   │   Service     │   │(write master)│
                   └───────────────┘   └──────────────┘
                         │
                   ┌─────▼──────┐
                   │  Payment   │
                   │  Service   │
                   └────────────┘
```

---

## PHP İmplementasiyası

```php
<?php
// Optimistic Locking ilə Rezervasiya
class BookingService
{
    private const MAX_RETRIES = 3;

    public function __construct(
        private RoomRepository    $rooms,
        private BookingRepository $bookings,
        private PaymentService    $payments,
        private EventBus          $eventBus,
        private \PDO              $db,
    ) {}

    public function reserve(ReserveRoomCommand $cmd): Booking
    {
        for ($attempt = 0; $attempt < self::MAX_RETRIES; $attempt++) {
            try {
                return $this->attemptReservation($cmd);
            } catch (OptimisticLockException $e) {
                if ($attempt === self::MAX_RETRIES - 1) {
                    throw new RoomNoLongerAvailableException(
                        "Otaq artıq mövcud deyil. Zəhmət olmasa başqa tarix seçin."
                    );
                }
                // Qısa gözləmə sonra yenidən cəhd
                usleep(random_int(10_000, 100_000)); // 10-100ms
            }
        }
    }

    private function attemptReservation(ReserveRoomCommand $cmd): Booking
    {
        $this->db->beginTransaction();

        try {
            // Otağı oxu + version saxla
            $room = $this->rooms->findWithLock($cmd->roomId);

            if (!$room->isAvailable($cmd->checkIn, $cmd->checkOut)) {
                $this->db->rollBack();
                throw new RoomNotAvailableException();
            }

            // Optimistic lock: versiyonu yoxla + yenilə
            $updated = $this->rooms->reserveWithVersionCheck(
                roomId:   $cmd->roomId,
                checkIn:  $cmd->checkIn,
                checkOut: $cmd->checkOut,
                version:  $room->getVersion(),
            );

            if ($updated === 0) {
                $this->db->rollBack();
                throw new OptimisticLockException("Version konflikti");
            }

            // Booking yarat
            $booking = Booking::create(
                roomId:   $cmd->roomId,
                userId:   $cmd->userId,
                checkIn:  $cmd->checkIn,
                checkOut: $cmd->checkOut,
                total:    $room->calculateTotal($cmd->checkIn, $cmd->checkOut),
            );
            $this->bookings->save($booking);

            $this->db->commit();

            // Event (ödəniş, bildiriş async)
            $this->eventBus->publish(new BookingCreatedEvent($booking->getId()));

            return $booking;

        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
```

```php
<?php
// Repository — optimistic lock SQL
class MySQLRoomRepository implements RoomRepository
{
    public function reserveWithVersionCheck(
        string    $roomId,
        \DateTimeImmutable $checkIn,
        \DateTimeImmutable $checkOut,
        int       $version,
    ): int {
        $stmt = $this->db->prepare(
            "UPDATE room_availability
             SET status = 'reserved',
                 version = version + 1
             WHERE room_id = ?
               AND date BETWEEN ? AND ?
               AND status = 'available'
               AND version = ?"
        );

        $stmt->execute([
            $roomId,
            $checkIn->format('Y-m-d'),
            $checkOut->format('Y-m-d'),
            $version,
        ]);

        return $stmt->rowCount(); // 0 = conflict, >0 = uğurlu
    }
}
```

```php
<?php
// Availability search — read replica + cache
class AvailabilitySearchService
{
    private const CACHE_TTL = 60; // 1 dəqiqə (çox tez-tez dəyişir)

    public function search(SearchCommand $cmd): array
    {
        $cacheKey = "availability:{$cmd->city}:{$cmd->checkIn}:{$cmd->checkOut}:{$cmd->guests}";

        // Cache-dən yoxla
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        // Elasticsearch-dan sürətli axtarış
        $results = $this->elasticsearch->search([
            'index' => 'rooms',
            'body'  => [
                'query' => [
                    'bool' => [
                        'must' => [
                            ['term'  => ['city'        => $cmd->city]],
                            ['range' => ['capacity'    => ['gte' => $cmd->guests]]],
                            ['range' => ['price_night' => ['lte' => $cmd->maxPrice]]],
                        ],
                        'filter' => [
                            ['term' => ['available_dates' => $cmd->checkIn]],
                        ],
                    ],
                ],
                'sort' => [['price_night' => 'asc']],
                'size' => 20,
            ],
        ]);

        $rooms = $this->mapResults($results);
        $this->cache->set($cacheKey, $rooms, $this->CACHE_TTL);

        return $rooms;
    }
}
```

---

## İntervyu Sualları

- Double booking problemini necə həll edərdiniz?
- Optimistic locking vs Pessimistic locking — hər birinin üstünlüyü?
- Rezervasiya + ödəniş atomik olmalıdır — bunu necə tətbiq edərdiniz?
- Mövcudluq axtarışı üçün niyə ayrı read storesi lazımdır?
- Ləğvetmə siyasəti (cancellation) sistemə necə təsir edir?
- Flash sale (hamı eyni anda reserve edir) vəziyyəti necə idarə edilir?
