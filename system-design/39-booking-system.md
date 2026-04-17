# Booking System Design (Airbnb/Hotel)

## Nədir? (What is it?)

**Booking System** — istifadəçilərə məkan, otel otağı, uçuş, restoran və ya xidmət rezervasiyası etmə imkanı verən sistem. Airbnb, Booking.com, OpenTable, Expedia kimi platformalar belə işləyir.

**Əsas tələblər:**
- **Availability search** — tarix/yer/filtr əsasında axtarış
- **Double-booking qarşısı** — eyni unit eyni tarixdə iki nəfərə satılmamalıdır
- **Pricing engine** — dinamik qiymətlər, seasonality, discounts
- **Payment** — authorization, capture, refunds
- **Cancellation policies** — tam/qismən refund, strict/flexible
- **Reviews & ratings** — hər iki tərəf

## Əsas Konseptlər (Key Concepts)

### Inventory Management

Hər listing (otel otağı, apartment) üçün availability calendar saxlanılır.

**İki yanaşma:**

1. **Date-range based** (Airbnb-style)
```
listing_id: 123
blocked_dates: [2024-04-20, 2024-04-21, 2024-04-22]
```

2. **Interval tree**
```
Reservations:
  [2024-04-20 → 2024-04-23]  booking_id=A
  [2024-04-25 → 2024-04-28]  booking_id=B

Query: "2024-04-21 boş?"  → overlap var → booked
```

3. **Bitmap per listing**
```
Bir illik calendar = 365 bit = 46 bayt
Day X bookeddirmi? → bit X yoxla
```

### Double-Booking Prevention

**Problem:** Concurrent request-lər eyni otağı rezervasiya edə bilər.

**Həllər:**

#### 1. Pessimistic Locking

```sql
BEGIN;
SELECT * FROM listings WHERE id = 123 FOR UPDATE;
-- Yoxla availability
INSERT INTO bookings ...;
COMMIT;
```

**Dezavantaj:** Yavaş, scaling problemi, deadlock riski.

#### 2. Optimistic Concurrency Control (OCC)

```sql
UPDATE availability
SET version = version + 1, status = 'booked'
WHERE listing_id = 123 AND date = '2024-04-20' AND version = 5;

-- Affected rows = 0 → retry
```

**Üstünlük:** High throughput. **Dezavantaj:** Yüksək contention-da retry storm.

#### 3. Unique Constraint

```sql
CREATE UNIQUE INDEX idx_listing_date
ON bookings (listing_id, date)
WHERE status = 'confirmed';

-- İki paralel insert cəhd etsə, biri uğursuz olur
INSERT INTO bookings ...;  -- DUPLICATE ERROR
```

#### 4. Distributed Lock (Redis/Zookeeper)

```
SET lock:listing:123 owner_uuid EX 10 NX
→ exclusive lock 10 saniyə
→ iş bitincə DEL lock:listing:123
```

#### 5. Two-Phase Commit (TPC)

- **Phase 1 (reserve)**: 10 dəq hold et, ödəniş gözlə
- **Phase 2 (confirm)**: ödəniş uğurludursa confirm

### Saga Pattern (Distributed Transaction)

Booking payment, inventory, notification bir neçə servisdədir.

```
Create booking saga:
1. Reserve inventory (10 min hold)
2. Charge payment
3. Confirm booking
4. Send confirmation email

Compensation (fail olsa):
4' → send failure email
3' → cancel booking
2' → refund payment
1' → release inventory
```

### Pricing Engine

**Dinamik qiymət faktorları:**
- **Base price** — listing-in standart qiyməti
- **Seasonality** — yay/qış, holiday
- **Demand-supply** — tələb yüksəkdirsə artır
- **Length of stay discount** — həftəlik/aylıq endirimlər
- **Last-minute pricing** — 1-2 gün qalıb, boş
- **Early bird** — irəlidən rezervasiya
- **Cleaning fee, service fee, taxes**

```
Total = (base_price × nights × seasonal_multiplier × demand_multiplier)
      - length_of_stay_discount
      + cleaning_fee + service_fee + taxes
```

### Search & Filter

**Çətinliklər:**
- Coğrafi axtarış (10 km radiusda)
- Date availability filter
- Facet-lər (pool, wifi, parking)
- Price range
- Real-time availability

**Arxitektura:**
- **Elasticsearch** — tam mətn axtarışı + geo + aggregations
- **Pre-computed indexes** — hər gecə yenilə
- **Cache hot queries** — Redis

### Cancellation Policies

**Airbnb tərzi:**
- **Flexible** — 24 saat əvvəl cancel → tam refund
- **Moderate** — 5 gün əvvəl → tam; sonra 50%
- **Strict** — 7 gün əvvəl → 50%; sonra 0%
- **Super Strict** — əksərən non-refundable

## Arxitektura

```
┌──────────────────────────────────────────────────────────┐
│                        CLIENT                             │
│            Web / Mobile App / Host App                    │
└──────────────────────┬───────────────────────────────────┘
                       │
              ┌────────▼────────┐
              │   API Gateway   │
              └────────┬────────┘
                       │
     ┌─────────────────┼─────────────────┬─────────────┐
     ▼                 ▼                 ▼             ▼
┌─────────┐     ┌──────────┐      ┌──────────┐  ┌──────────┐
│ Search  │     │ Booking  │      │ Payment  │  │ Review   │
│ Service │     │ Service  │      │ Service  │  │ Service  │
└────┬────┘     └─────┬────┘      └─────┬────┘  └─────┬────┘
     │                │                  │             │
     ▼                ▼                  ▼             ▼
┌─────────┐    ┌──────────┐       ┌──────────┐  ┌──────────┐
│  ES     │    │ Postgres │       │  Stripe  │  │  MySQL   │
│ (index) │    │(bookings)│       └──────────┘  └──────────┘
└─────────┘    └──────────┘
                    │
                    ▼
              ┌──────────┐
              │  Redis   │
              │ (locks,  │
              │  cache)  │
              └──────────┘

Kafka: booking.created, booking.cancelled, payment.* events
Notification Service → email/SMS/push
```

## PHP/Laravel ilə Tətbiq

### Database Schema

```php
// Migrations
Schema::create('listings', function (Blueprint $t) {
    $t->id();
    $t->foreignId('host_id');
    $t->string('title');
    $t->string('city');
    $t->decimal('lat', 10, 7);
    $t->decimal('lng', 10, 7);
    $t->decimal('base_price', 10, 2);
    $t->json('amenities');
    $t->integer('max_guests');
    $t->timestamps();
    $t->index(['city', 'base_price']);
});

Schema::create('availability', function (Blueprint $t) {
    $t->id();
    $t->foreignId('listing_id');
    $t->date('date');
    $t->enum('status', ['available', 'held', 'booked', 'blocked']);
    $t->decimal('price', 10, 2)->nullable(); // dinamik qiymət
    $t->integer('version')->default(0);       // OCC
    $t->string('hold_token', 64)->nullable();
    $t->timestamp('hold_expires_at')->nullable();
    $t->unique(['listing_id', 'date']);
});

Schema::create('bookings', function (Blueprint $t) {
    $t->id();
    $t->foreignId('listing_id');
    $t->foreignId('guest_id');
    $t->date('check_in');
    $t->date('check_out');
    $t->integer('guests');
    $t->decimal('total_price', 10, 2);
    $t->string('currency', 3);
    $t->enum('status', ['pending', 'confirmed', 'cancelled', 'completed']);
    $t->string('cancellation_policy');
    $t->json('price_breakdown');
    $t->timestamps();
    $t->index(['listing_id', 'check_in', 'check_out']);
});
```

### Availability Service

```php
<?php

namespace App\Services\Booking;

use Illuminate\Support\Facades\DB;
use App\Models\Availability;
use Carbon\CarbonPeriod;

class AvailabilityService
{
    public function isAvailable(int $listingId, string $checkIn, string $checkOut): bool
    {
        $dates = CarbonPeriod::create($checkIn, $checkOut)->excludeEndDate();

        $taken = Availability::where('listing_id', $listingId)
            ->whereIn('date', collect($dates)->map->toDateString())
            ->whereIn('status', ['booked', 'held', 'blocked'])
            ->where(function ($q) {
                $q->where('status', '!=', 'held')
                  ->orWhere('hold_expires_at', '>', now());
            })
            ->exists();

        return !$taken;
    }

    /**
     * Optimistic hold — 10 dəq.
     */
    public function hold(int $listingId, string $checkIn, string $checkOut): string
    {
        $holdToken = bin2hex(random_bytes(16));
        $dates = CarbonPeriod::create($checkIn, $checkOut)->excludeEndDate();

        return DB::transaction(function () use ($listingId, $dates, $holdToken) {
            foreach ($dates as $date) {
                // Unique constraint (listing_id, date) + status check
                $row = Availability::firstOrNew([
                    'listing_id' => $listingId,
                    'date' => $date->toDateString(),
                ]);

                if ($row->status === 'booked') {
                    throw new \RuntimeException("Date {$date->toDateString()} already booked");
                }
                if ($row->status === 'held' && $row->hold_expires_at > now()) {
                    throw new \RuntimeException("Date {$date->toDateString()} on hold");
                }

                $row->fill([
                    'status' => 'held',
                    'hold_token' => $holdToken,
                    'hold_expires_at' => now()->addMinutes(10),
                ]);
                $row->save();
            }
            return $holdToken;
        });
    }

    public function confirm(string $holdToken): void
    {
        Availability::where('hold_token', $holdToken)
            ->where('status', 'held')
            ->where('hold_expires_at', '>', now())
            ->update(['status' => 'booked', 'hold_token' => null, 'hold_expires_at' => null]);
    }

    public function release(string $holdToken): void
    {
        Availability::where('hold_token', $holdToken)
            ->where('status', 'held')
            ->update(['status' => 'available', 'hold_token' => null, 'hold_expires_at' => null]);
    }

    /**
     * Scheduled: expired holds təmizlə
     */
    public function cleanupExpiredHolds(): int
    {
        return Availability::where('status', 'held')
            ->where('hold_expires_at', '<=', now())
            ->update(['status' => 'available', 'hold_token' => null]);
    }
}
```

### Pricing Engine

```php
class PricingService
{
    public function calculate(Listing $listing, string $checkIn, string $checkOut, int $guests): array
    {
        $nights = Carbon::parse($checkIn)->diffInDays($checkOut);
        $dates = CarbonPeriod::create($checkIn, $checkOut)->excludeEndDate();

        $subtotal = 0;
        $dailyPrices = [];
        foreach ($dates as $date) {
            $price = $this->dailyPrice($listing, $date);
            $dailyPrices[] = ['date' => $date->toDateString(), 'price' => $price];
            $subtotal += $price;
        }

        // Length of stay discounts
        $discount = 0;
        if ($nights >= 28) $discount = $subtotal * 0.15;       // monthly
        elseif ($nights >= 7) $discount = $subtotal * 0.08;    // weekly

        $cleaningFee = $listing->cleaning_fee ?? 50;
        $serviceFee = ($subtotal - $discount) * 0.14;  // Airbnb ~14%
        $taxes = ($subtotal - $discount + $cleaningFee) * 0.12;

        $total = $subtotal - $discount + $cleaningFee + $serviceFee + $taxes;

        return [
            'subtotal' => round($subtotal, 2),
            'discount' => round($discount, 2),
            'cleaning_fee' => $cleaningFee,
            'service_fee' => round($serviceFee, 2),
            'taxes' => round($taxes, 2),
            'total' => round($total, 2),
            'currency' => $listing->currency,
            'nights' => $nights,
            'daily_breakdown' => $dailyPrices,
        ];
    }

    private function dailyPrice(Listing $listing, Carbon $date): float
    {
        $base = $listing->base_price;

        // Seasonal multiplier
        $month = $date->month;
        $seasonal = match (true) {
            in_array($month, [6, 7, 8]) => 1.3,        // summer
            in_array($month, [12, 1]) => 1.2,          // winter holidays
            default => 1.0,
        };

        // Weekend surcharge
        $weekend = $date->isWeekend() ? 1.15 : 1.0;

        // Demand multiplier (Redis cache-dən)
        $demand = (float) (Redis::get("demand:{$listing->city}:{$date->toDateString()}") ?: 1.0);

        return round($base * $seasonal * $weekend * $demand, 2);
    }
}
```

### Booking Service (Saga)

```php
class BookingService
{
    public function __construct(
        private AvailabilityService $availability,
        private PricingService $pricing,
        private PaymentService $payment,
    ) {}

    public function create(int $userId, array $data): Booking
    {
        $listing = Listing::findOrFail($data['listing_id']);

        // Step 1: Hold inventory (10 min)
        try {
            $holdToken = $this->availability->hold(
                $listing->id, $data['check_in'], $data['check_out']
            );
        } catch (\RuntimeException $e) {
            throw new BookingException('Dates no longer available', 409);
        }

        // Step 2: Price
        $priceBreakdown = $this->pricing->calculate(
            $listing, $data['check_in'], $data['check_out'], $data['guests']
        );

        // Step 3: Create pending booking
        $booking = Booking::create([
            'listing_id' => $listing->id,
            'guest_id' => $userId,
            'check_in' => $data['check_in'],
            'check_out' => $data['check_out'],
            'guests' => $data['guests'],
            'total_price' => $priceBreakdown['total'],
            'currency' => $priceBreakdown['currency'],
            'status' => 'pending',
            'cancellation_policy' => $listing->cancellation_policy,
            'price_breakdown' => $priceBreakdown,
        ]);

        // Step 4: Payment
        try {
            $charge = $this->payment->authorize(
                userId: $userId,
                amount: $priceBreakdown['total'],
                currency: $priceBreakdown['currency'],
                idempotencyKey: "booking:{$booking->id}",
            );
        } catch (PaymentException $e) {
            $this->availability->release($holdToken);
            $booking->update(['status' => 'cancelled']);
            throw $e;
        }

        // Step 5: Confirm
        DB::transaction(function () use ($booking, $holdToken, $charge) {
            $this->availability->confirm($holdToken);
            $booking->update([
                'status' => 'confirmed',
                'payment_intent_id' => $charge->id,
            ]);
        });

        // Step 6: Async notifications
        event(new \App\Events\BookingConfirmed($booking));

        return $booking;
    }

    public function cancel(Booking $booking, User $user): void
    {
        if ($booking->guest_id !== $user->id) {
            throw new UnauthorizedException();
        }

        $refundAmount = $this->refundAmount($booking);

        DB::transaction(function () use ($booking, $refundAmount) {
            // Release availability
            Availability::where('listing_id', $booking->listing_id)
                ->whereBetween('date', [$booking->check_in, $booking->check_out])
                ->update(['status' => 'available']);

            if ($refundAmount > 0) {
                $this->payment->refund($booking->payment_intent_id, $refundAmount);
            }

            $booking->update(['status' => 'cancelled']);
        });

        event(new \App\Events\BookingCancelled($booking));
    }

    private function refundAmount(Booking $booking): float
    {
        $daysUntil = Carbon::now()->diffInDays(Carbon::parse($booking->check_in), false);

        return match ($booking->cancellation_policy) {
            'flexible' => $daysUntil >= 1 ? $booking->total_price : 0,
            'moderate' => match (true) {
                $daysUntil >= 5 => $booking->total_price,
                $daysUntil >= 1 => $booking->total_price * 0.5,
                default => 0,
            },
            'strict' => match (true) {
                $daysUntil >= 7 => $booking->total_price * 0.5,
                default => 0,
            },
            default => 0,
        };
    }
}
```

### Search Service (Elasticsearch)

```php
class SearchService
{
    public function search(array $filters): array
    {
        $query = [
            'bool' => [
                'must' => [
                    ['match' => ['city' => $filters['city']]],
                ],
                'filter' => [
                    ['range' => ['price' => ['gte' => $filters['min_price'] ?? 0, 'lte' => $filters['max_price'] ?? 10000]]],
                    ['range' => ['max_guests' => ['gte' => $filters['guests']]]],
                ],
            ],
        ];

        if (!empty($filters['lat'], $filters['lng'])) {
            $query['bool']['filter'][] = [
                'geo_distance' => [
                    'distance' => ($filters['radius_km'] ?? 10) . 'km',
                    'location' => ['lat' => $filters['lat'], 'lon' => $filters['lng']],
                ],
            ];
        }

        if (!empty($filters['amenities'])) {
            foreach ($filters['amenities'] as $amenity) {
                $query['bool']['filter'][] = ['term' => ['amenities' => $amenity]];
            }
        }

        $raw = Elasticsearch::search([
            'index' => 'listings',
            'body' => [
                'query' => $query,
                'size' => 50,
                'sort' => [['_score' => 'desc'], ['rating' => 'desc']],
            ],
        ]);

        $hits = $raw['hits']['hits'] ?? [];
        $listingIds = array_column(array_column($hits, '_source'), 'id');

        // Post-filter availability (ES-də availability keep çətindir)
        return $this->filterAvailable($listingIds, $filters['check_in'], $filters['check_out']);
    }

    private function filterAvailable(array $ids, string $checkIn, string $checkOut): array
    {
        $taken = Availability::whereIn('listing_id', $ids)
            ->whereBetween('date', [$checkIn, $checkOut])
            ->whereIn('status', ['booked', 'held'])
            ->pluck('listing_id')
            ->unique();

        return array_values(array_diff($ids, $taken->toArray()));
    }
}
```

## Real-World Nümunələr

- **Airbnb** — MySQL shards, Airlock anti-discrimination, dynamic pricing (Smart Pricing)
- **Booking.com** — machine learning pricing, A/B testing intensiv
- **Expedia** — multi-brand (Hotels.com, Trivago), GDS (Global Distribution System) inteqrasiyası
- **OpenTable** — restaurant reservations, real-time availability
- **Resy** — high-end restaurants, ticketed reservations
- **KAYAK** — meta-search, birdən çox provider-dan aqreqasiya
- **Hotel PMS (Property Management System)** — Opera, Oracle Hospitality

## Interview Sualları

**1. Double-booking necə qarşısı alınır?**
- Unique constraint (listing_id, date)
- Optimistic concurrency (version column)
- Distributed lock (Redis SETNX)
- Two-phase booking (hold → confirm)
- Airbnb: hold 10 dəq, ödəniş tamamlanmasa release

**2. Pessimistic vs Optimistic locking?**
- **Pessimistic** (`SELECT ... FOR UPDATE`): sadə, amma scaling pis, contention-da yavaş
- **Optimistic** (version column): throughput yüksək, amma retry lazım
- Booking üçün optimistic + unique constraint tipik — low contention

**3. Search + real-time availability necə birləşir?**
- Elasticsearch-də listing metadata index
- Availability PostgreSQL-də (source of truth)
- İki mərhələ: ES-dən candidate ID-lər → DB-də availability filter
- Alternative: ES-də availability cache (5-10 dəq stale OK)
- Pre-computed blocked dates summary

**4. Cancellation policy business logic-i necə həll olunur?**
- Enum/string field booking-də
- Per-listing customization
- Cancellation zamanı daysUntil hesablayır
- Strategy pattern: her policy öz class-ı
- Edge cases: host cancel = guest tam refund

**5. Pricing nə dərəcədə dinamikdir?**
- **Static**: base price per night
- **Seasonal**: yay/qış/holiday
- **Day-of-week**: həftə sonu surcharge
- **Demand-based**: ML model (Airbnb Smart Pricing)
- **Competitor-aware**: rəqibdən gözləmə
- Real-time calculation cache aggressive

**6. Payment saga fail olsa ne baş verir?**
- Compensation-lar işə salınır
- Availability release
- Booking status = cancelled
- Idempotency key retry-ları qoruyur
- Failed payment log user-ə error mesajı

**7. Host və guest hər ikisi eyni anda rezervasiya dəyişdirsə?**
- Event sourcing — bütün dəyişikliklər audit
- Conflict resolution — son yazan qalib, amma notification
- Host manual approval (Airbnb "Instant Book" olmayan listings)
- Optimistic UI + server validation

**8. Channel manager (birdən çox platforma sync) necə işləyir?**
- Otel Booking.com + Expedia + Airbnb-də listed
- Central inventory sistem
- Hər biri API ilə real-time update
- Webhook/polling hibrid
- Race condition — bir platformada satılıbsa digərlərində dərhal remove

**9. Multi-currency və localization?**
- Listings `base_currency` saxlayır
- Exchange rate cache (hər saat)
- User preferred currency → display conversion
- Booking zamanı actual charge original currency-dədir
- Timezone-lar: UTC saxla, user timezone-da göstər

**10. Review sistemi necə gaming-dən qorunur?**
- Yalnız completed booking-dən sonra review
- Two-way blind (hər ikisi yazsın sonra göstərsin) — Airbnb yanaşması
- 14 gün deadline
- ML fake review detection
- Appeal system

**11. Calendar sync (iCal, Google Calendar) necə işləyir?**
- iCal feed URL export
- Import iCal (Google Cal-dən blocked dates)
- Hər 30 dəq sync
- Two-way sync mürəkkəb — event mapping

## Best Practices

1. **Hold + confirm pattern** istifadə et — ödəniş prosesində inventory bloklu olsun
2. **Unique constraint** database səviyyəsində — safety net
3. **Idempotency keys** bütün payment/booking API-lərdə
4. **Saga pattern** distributed transaction-lar üçün
5. **Eventual consistency** search index-də (ES) — 5-10 sec lag OK
6. **Cache hot listings** — çox axtarılan şəhər/tarixlər Redis-də
7. **Dynamic pricing ayrı service-də** — ML team iterate edə bilsin
8. **Audit log** — hər booking action immutable
9. **Soft delete bookings** — analytics, dispute resolution
10. **Webhook-larla eventlər** — partners üçün (channel manager)
11. **Rate limit search** — scraping qarşısı
12. **Fraud detection** — yeni account + bahalı booking + yeni card
13. **Retention policy** — personal data GDPR (booking 7 il saxla audit üçün)
14. **Timezone-lar** — bütün system UTC, display user-timezone
15. **A/B test** — search ranking, pricing, UX
16. **Overbooking hesablamaları** (hotel) — historical no-show rate
17. **Load test** peak seasons üçün — yay, Black Friday
