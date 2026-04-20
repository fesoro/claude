# Ad Serving System Design (RTB)

## Nədir? (What is it?)

Ad serving system istifadəçi publisher səhifəsini açanda <100ms içində ən uyğun
reklamı seçib göstərən və impression/click/conversion hadisələrini izləyən sistemdir.
**Real-Time Bidding (RTB)** modelində hər ad slot üçün auction keçirilir: supply
side (SSP) DSP-lərə bid request göndərir, DSP-lər təkliflərini qaytarır, ən yüksək
eCPM qalib olur, creative render olunur. Sonra impression/click/conversion event-ləri
Kafka-ya axır, attribution service onları campaign-ə bağlayır, billing aggregator
advertiser-dən pul yığır.

Sadə dillə: hərrac evi. Səhifə açılır, hərraca salınır, 100ms-lik "güllə dueli"-ndə
reklamverən təklif edir, ən yaxşı təklif qalib gəlir, afişa görünür, klikə görə pul
alınır.

```
Publisher page  ──▶  SSP  ──▶  DSP1, DSP2, DSP3 ...   (bid requests, ≤100ms)
                      │
                      └◀── Winning bid ── creative URL ──▶ Browser
                      
Browser ── impression pixel ──▶ Tracker ──▶ Kafka
Browser ── click redirect   ──▶ Tracker ──▶ Kafka ──▶ target URL
Advertiser site ── conversion ──▶ Attribution ──▶ bill advertiser
```

## Tələblər (Requirements)

### Funksional (Functional)

- User+page üçün ən yaxşı reklamı seç, 100ms RTB pəncərəsində qaytar
- Impression, click və conversion event-lərini izlə
- Hər conversion-u doğru campaign-ə attribute et (lookback window daxilində)
- Advertiser-ı düzgün model (CPM/CPC/CPA) ilə billing et
- Click fraud-u aşkarla və ödənişdən çıx
- Budget pacing — gün ərzində bərabər xərclə
- Frequency capping — eyni user-ə gündə max N impression

### Qeyri-funksional (Non-functional)

```
Scale:
  100k ad request/sec (peak)
  Milyonlarla advertiser, milyardlarla user profile
  100B+ impression/gün, 1B+ click/gün

Latency:
  Bid response p99 < 100ms (OpenRTB protokol tələbi)
  Impression logging fire-and-forget
  Conversion attribution eventual (dəqiqələr)

Availability:
  Ad server 99.95%+ (aşağı bid = itirilmiş gəlir)
  Event pipeline at-least-once (dublikatı dedupe et)

Consistency:
  Budget counter eventually consistent ama pacing-i pozmamalı
  Billing strictly auditable (financial grade)
```

## Əsas Konseptlər (Key Concepts)

### Core Loop — Request-dən Conversion-a

```
1. User publisher.com açır → Browser ad tag-i SSP-yə çağırır
2. SSP OpenRTB JSON bid request paralel N DSP-ə göndərir (timeout 100ms):
     {user_id, geo, device, page_url, ad_slot, consent_flags}
3. Hər DSP: candidate generation → CTR model → eCPM = bid × pCTR ×
   1000 → budget+freq cap → ən yaxşı bid qaytarır
4. SSP auction: first-price (qalib öz bid-ini) və ya second-price
   (2-ci bid + 1 cent). Qalib creative URL qaytarır, browser render edir
5. Impression pixel: <img src="tracker/imp?bid_id=..."> → Kafka
6. Click: <a href="tracker/click?..."> → 302 redirect → advertiser URL
7. Advertiser landing-də conversion pixel / postback click_id ilə
   gəlir, attribution service son klikə bağlayır
```

### OpenRTB Protokolu

```
IAB industry standart. Vacib sahələr:
  imp      — slot size, floor price, format
  site/app — publisher domain, category
  device   — ua, ip, geo, os, connection
  user     — anonymous id (cookie/IFA)
  regs     — GDPR/CCPA consent flags

Timeout budget: SSP→DSP 80ms, DSP processing 60ms,
network RTT 20ms, ümumi < 100ms.
```

### Ad Selection Pipeline

```
Bid Request (user_id, geo, device, page, slot)
   │
   ▼
Candidate Generation       (Reverse index intersect)
   geo=AZ     → {c1,c5,c12}
   device=mob → {c1,c3,c12}
   interest   → {c5,c12}
   → {c12}                 ~10M campaigns → ~1000
   │
   ▼
Ranking (ML CTR)
   pCTR = model.predict(features)
   eCPM = bid × pCTR × 1000
   sort desc, take top 50
   │
   ▼
Budget + Freq + Brand safety filter
   budget remaining? pacing OK? user < cap? blocklist?
   │
   ▼
Winner → SSP (bid_amount, creative_url)
```

## Arxitektura (Architecture)

### Yüksək Səviyyə (High Level)

```
Publisher page
     │
     ▼
  SSP/Ad Exchange ─────────────────────────┐
     │                                      │
     ├──▶ DSP1  Bidder (stateless, 100ms)  │
     ├──▶ DSP2                              │
     └──▶ DSP3                              │
              │                             │
              ├──▶ Targeting Index (Redis)  │
              ├──▶ CTR Model (TF-Serving)   │
              ├──▶ Budget Counter (Redis)   │
              └──▶ Feature Store (user)     │
                                            │
                    winner creative ◀───────┘
                         │
                         ▼
                    User browser
                         │
            ┌────────────┼──────────────┐
            ▼            ▼              ▼
       impression      click      conversion (from advertiser)
            │            │              │
            └────────────┴──────────────┘
                         │
                         ▼
                       Kafka
                         │
              ┌──────────┴──────────┐
              ▼                     ▼
      Stream Processor         Batch (Spark)
      (Kafka Streams)           - daily attribution
       - count impressions      - reports
       - decrement budget       - billing files
       - frequency cap update   - fraud scrubbing
              │
              ▼
      Aggregated counters → Billing service → SQL invoices
```

### Storage Seçimləri (Storage Choices)

```
Campaign / Creative / Budget:   PostgreSQL (ACID, joins, audit)
Targeting index:                Redis sets/bitmaps (O(1) intersect)
                                ElasticSearch (rich bool queries)
User profile:                   DynamoDB/Cassandra + Feature Store
Event log:                      Kafka (partition by user_id)
Aggregates (reports):           ClickHouse / Druid (sub-sec rollup)
```

### Attribution Models

```
Last-click  — son klik 100% (default, sadə, transparent)
First-click — ilk klik 100% (discovery campaign üçün)
Linear      — N toxunmadan hər biri 1/N
Time-decay  — credit_i = 2^(-Δt / half_life), yaxın = çox
Position    — U-shaped: ilk 40%, son 40%, orta 20%
Data-driven — ML/Shapley values (Google Ads, Meta)
```

### Click Fraud Detection

```
Sync (bid-də rədd):
  IP blocklist, UA blacklist, obvious pattern (100 click/1s)

Async scrubber (Kafka-dan post-click):
  Eyni IP çox click / window
  Impression-siz click (mümkünsüz flow)
  Headless browser (mouse/scroll yoxdur)
  μs-precision timing (bot)
  Datacenter ASN (residential deyil)
  Click farm — geo cluster + fingerprint

Confirmed fraud → invalid=true → advertiser billing-dən çıxır.
Industry: raw click-lərin 10-25%-i invalid olur.
```

### Budget Pacing

```
Asap   — budget bitənə qədər sürətlə xərclə (yeni launch)
Even   — günə bərabər yay (prime-time slot itirməmək)

target = daily_budget × (seconds_elapsed / 86400)
ratio  = actual / target
  ratio > 1.1 → participation_rate × 0.9  (yavaşla)
  ratio < 0.9 → participation_rate × 1.1  (aqressiv)
```

## PHP/Laravel ilə Tətbiq (Implementation with PHP/Laravel)

### Click Tracking Endpoint

Click endpoint lightweight olmalıdır — DB-yə yazmır, Kafka-ya enqueue edir, dərhal
302 redirect qaytarır (p99 < 20ms).

```php
Route::get('/click', [ClickTrackerController::class, 'track']);

class ClickTrackerController extends Controller
{
    public function __construct(
        private readonly ClickSigner $signer,
        private readonly EventPublisher $events,
    ) {}

    public function track(Request $request)
    {
        // 1) Signed URL verify — tampering click_id/bid_id qarşısını al
        if (!$this->signer->verify($request->query('p'), $request->query('s'))) {
            abort(400, 'Invalid click signature');
        }
        $data = $this->signer->decode($request->query('p'));

        // 2) TTL — köhnə / replay link-ləri rədd et
        if (now()->timestamp - $data['issued_at'] > 3600) {
            abort(410, 'Expired');
        }

        // 3) click_id — attribution üçün join key
        $clickId = (string) Str::uuid();

        // 4) Kafka-ya enqueue (fire-and-forget, DB-yə YAZMA)
        $this->events->publish('ad.clicks', [
            'click_id'    => $clickId,
            'bid_id'      => $data['bid_id'],
            'campaign_id' => $data['campaign_id'],
            'creative_id' => $data['creative_id'],
            'user_id'     => $data['user_id'],
            'ip'          => $request->ip(),
            'ua'          => $request->userAgent(),
            'ts'          => now()->timestamp,
        ]);

        // 5) click_id-ni advertiser URL-nə əlavə et + 1st party cookie
        $sep    = str_contains($data['target_url'], '?') ? '&' : '?';
        $target = $data['target_url'].$sep.http_build_query(['click_id' => $clickId]);
        cookie()->queue('last_click_id', $clickId, 60 * 24 * 7);

        return redirect()->away($target, 302);
    }
}
```

### Attribution Service (Last-click + Lookback)

```php
class AttributionService
{
    private const LOOKBACK_DAYS = 7;

    public function attribute(array $conversion): ?Attribution
    {
        // 1) Determinist: click_id varsa, birbaşa bağla
        $click = !empty($conversion['click_id'])
            ? $this->clicks->findById($conversion['click_id'])
            : null;

        // 2) Fallback: cookie / user_id + lookback window
        $click ??= $this->clicks->lastByUserWithin(
            $conversion['user_id'], now()->subDays(self::LOOKBACK_DAYS),
        );
        if (!$click) return null; // organic, campaign-ə attribute olmur

        return $this->conversions->store([
            'conversion_id' => (string) Str::uuid(),
            'click_id'      => $click->id,
            'campaign_id'   => $click->campaign_id,
            'revenue'       => $conversion['revenue'],
            'model'         => 'last_click',
            'credit'        => 1.0,
        ]);
    }
}
```

### Pacing Job (hər 5 dəqiqəbir)

```php
class AdjustPacingJob implements ShouldQueue
{
    public function handle(CampaignRepository $repo, BudgetCounter $counter): void
    {
        foreach ($repo->activeCampaigns() as $c) {
            $target = $c->daily_budget * (now()->diffInSeconds(now()->startOfDay()) / 86400);
            $ratio  = $target > 0 ? $counter->spentToday($c->id) / $target : 1.0;

            $c->update(['participation_rate' => match (true) {
                $ratio > 1.15 => $c->participation_rate * 0.85,
                $ratio < 0.85 => min($c->participation_rate * 1.15, 1.0),
                default       => $c->participation_rate,
            }]);
        }
    }
}
```

## Data Model

```
campaigns(id, advertiser_id, name, daily_budget, total_budget,
          start_at, end_at, bid_model, participation_rate, status)

ads(id, campaign_id, name, status)

creatives(id, ad_id, type, html, image_url, width, height, landing_url)

targetings(id, ad_id, geo, device, age_min, age_max, interests_json, keywords_json)

users(id, anon_id, last_seen_at, consent_flags)

bids(id, bid_id, user_id, campaign_id, creative_id,
     bid_price, clearing_price, won, ts)

impressions(id, bid_id, user_id, campaign_id, creative_id, ts)

clicks(id, click_id, bid_id, user_id, campaign_id, creative_id,
       ip, ua, ts, invalid_flag)

conversions(id, click_id, user_id, campaign_id, order_id,
            revenue, model, credit, ts)
```

## Interview Q&A

**S1: Niyə bid response 100ms hard limit-dir?**
OpenRTB spec-i belə qoyur — publisher page render-i blok etmək olmaz. Geç DSP
auction-dan atılır (timeout = itkin gəlir). DSP özünə 60-80ms verir, qalanı
network + SSP overhead-dir.

**S2: First-price vs second-price auction fərqi?**
Second-price (Vickrey): qalib 2-ci ən yüksək + 1 cent ödəyir, bidder truthful olur.
First-price: qalib öz bid-ini tam ödəyir, shading lazım olur. 2019-dan sonra
industry first-price-a keçdi çünki exchange-lər 2-ci qiyməti soft floor-larla
manipulyasiya edirdi, bidder etibarı itdi.

**S3: Bid pipeline-da DB-ni necə ayırırsan?**
Hot path-da heç bir OLTP query olmamalıdır. Targeting index, budget counter, user
features, CTR model — hamısı in-memory / Redis / feature store-da precompute.
Campaign update CDC ilə push olunur. DB yalnız admin write üçündür.

**S4: Budget overspend-i necə qarşısını alırsan?**
Distributed counter problem. Redis atomic DECRBY hər bid-də budget-i aşağı salır,
overshoot millisecond pəncərəsindədir. Alternativ: hər bidder-ə budget shard.
Praktikada Redis hybrid + 3-5% safety margin.

**S5: Click fraud-u necə aşkarlayırsan?**
İki qat: sync blocklist (məlum bot IP/ASN) + async scrubber Kafka-dan click stream
oxuyur, features (IP repeat, no preceding impression, headless signal, timing)
ML model-ə verir. `invalid=true` bill-ə getmir. Industry-də 10-25% click invalid.

**S6: Attribution window nə olmalıdır?**
Business-dən asılı — e-commerce 1-7 gün, SaaS B2B 30-90 gün. Uzun window = çox
conversion amma çox noise. Tipik default: view 1 gün, click 7-30 gün. Advertiser
per-campaign seçir.

**S7: Cookie-siz dünyada (Privacy Sandbox, iOS ATT) necə?**
Contextual targeting (page content-ə görə), 1st party data (publisher login),
privacy-preserving API-lər (Topics API, Protected Audience), clean rooms (hashed
identifier match). Identity accuracy 80%→40%-ə düşür, data-driven attribution
vacibləşir.

**S8: Frequency cap-i necə tətbiq edirsən?**
Redis-də `freq:{user}:{campaign}:{date}` incr + TTL 24h. Bid time GET < 1ms.
Distributed mühitdə hafif overshoot OK. Alternativ: Bloom filter (yaddaşa
qənaət, false positive dözümlüyü tələb edir).

## Best Practices

- **Hot path-dan DB-ni at** — bid time-da yalnız in-memory / Redis / feature store
- **Precompute everything** — targeting reverse index, freq counters, budget remaining
- **Fire-and-forget tracking** — impression/click endpoint Kafka-ya enqueue, DB yox
- **Signed redirect URL** — click URL tamper-proof (HMAC + TTL + nonce)
- **At-least-once + dedupe** — Kafka duplicate atır, aggregator idempotent olmalı
- **Budget safety margin** — distributed counter overshoot edir, 3-5% buffer saxla
- **Attribution model configurable** — advertiser per-campaign seçə bilməlidir
- **Event schema versioning** — OpenRTB + internal event Avro/Proto registry
- **GDPR/CCPA consent hər bid-də** — consent yoxdursa, contextual bid (user data yox)
- **Brand safety blocklist** — page category (adult, violence) advertiser seçiminə görə
- **Chaos testing** — DSP timeout, Redis down, Kafka lag ssenariləri
- **Observability** — win rate, fill rate, pCTR calibration, pace, fraud rate

## Cross-references

- [Recommendation System](36-recommendation-system.md) — candidate generation + ranking paterni eynidir
- [Stream Processing](54-stream-processing.md) — Kafka Streams impression aggregation
- [Rate Limiting](06-rate-limiting.md) — frequency capping Redis counter nümunəsi
- [Idempotency](28-idempotency.md) — click_id ilə conversion dedupe
- [Back-of-envelope](31-back-of-envelope-estimation.md) — 100k rps capacity planning
- [Chaos Engineering](56-chaos-engineering.md) — DSP timeout / SSP failover testləri
- [Backpressure](57-backpressure-load-shedding.md) — bid peak-lərdə load shedding
