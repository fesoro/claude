# Anti-Corruption Layer (ACL) (Senior ⭐⭐⭐)

## İcmal

Anti-Corruption Layer — xarici və ya legacy sistemin domain modelinin öz sisteminizə sirayət etməsini önləyən translation (çevirmə) qatı. Xarici sistemin öz field adları, statusları, konseptləri olur; ACL bunları sizin ubiquitous language-ınıza çevirir. Domain-iniz xarici sistemdən xəbərsiz qalır, onun modelindən çirklənmir (corrupted).

## Niyə Vacibdir

Legacy ERP-dən `tbl_cust_ord_hdr` cədvəli, `CUST_NO`, `AMT_TOT_NET` field-ləri gəlir. Bu adları bütün kod bazasında istifadə etsəniz, ERP dəyişdikdə ya da yeni sistemə keçdikdə hər yeri düzəltmək lazım olur. ACL bu xarici modeli daxili `Order`, `customerId`, `totalAmount` modelinizə çevirir — xarici sistem dəyişsə yalnız ACL dəyişir.

## Əsas Anlayışlar

- **ACL (Anti-Corruption Layer)**: xarici → daxili model translation qatı; DDD-də bounded context-lər arası inteqrasiya nöqtəsidir
- **Translation (çevirmə)**: field adları, status kodları, data formatları, konseptlər arası mapping
- **Domain model qorunması**: daxili domain xarici terminologiyadan xəbərsiz qalır
- **Sidecar Pattern**: eyni K8s pod-da əsas app ilə yan yana çalışan köməkçi container; logging, tracing, mTLS
- **Ambassador Pattern**: eyni pod-da outbound traffic proxy-si; retry, circuit break, auth injection
- **API Gateway vs ACL**: Gateway xaricdəki giriş nöqtəsidir; ACL bounded context daxilindəki inteqrasiya nöqtəsidir

## Praktik Baxış

- **Real istifadə**: legacy ERP inteqrasiyası, third-party payment gateway, köhnə SOAP API → REST, microservice-lər arası bounded context inteqrasiyası
- **Trade-off-lar**: domain-i xarici sistemdən qoruyur; xarici sistem dəyişsə yalnız ACL dəyişir; lakin əlavə qat — test etmək lazım; mapping məntiqi mürəkkəbləşərsə maintainability çətin
- **İstifadə etməmək**: xarici sistem sizin ubiquitous language-ınıza çox yaxındırsa (eyni konseptlər); sadə pass-through inteqrasiya üçün
- **Common mistakes**: ACL-i çox "thin" yazmaq (yalnız field rename); ACL-ə business logic yerləşdirmək; hər iki tərəf dəyişdikdə ACL-i güncəlləməyi unutmaq

## Anti-Pattern Nə Zaman Olur?

**ACL-in domain concept leak etməsi:**
ACL `status: "PENDING_REVIEW"` kimi xarici kodu domain-ə sızdırırsa — ACL yarımçıqdır. ACL həm data çevirməli, həm xarici konseptləri domain konseptlərinə mapping etməlidir: `PaymentStatus::CONFIRMED` domain modelinizin dili ilə.

**Hər iki tərəf dəyişdikdə ACL maintainance yükü:**
Xarici sistem API-si tez-tez dəyişirsə, ACL həmişə güncəllənməlidir. Bu xərc bəzən shared model istifadəsini justify edə bilər — tərəflər çox yaxın domain-ə malikdirsə. Lakin domain fərqlidirsə, ACL maintainance yükünü qəbul et.

**ACL-ə business logic yerləşdirmək:**
`LegacyOrderACL.calculateDiscount()` — bu domain service-ə aiddir. ACL yalnız translate edir, hesablamır. Business logic ACL-ə girdikcə test etmək çətinləşir, domain-in məsuliyyəti bulanır.

**ACL olmadan xarici modeli domain-ə daxil etmək:**
Ödəniş provayderinin `txn_status: 3` kimi dəyərlərini bütün kod bazasında istifadə etmək — provayder API dəyişəndə hər yeri düzəltmək lazım olur. ACL ilə xarici modeli öz ubiquitous language-ınıza çevirin.

## Nümunələr

### Ümumi Nümunə

Legacy ERP: `tbl_cust_ord_hdr` cədvəli, `CUST_NO`, `ORD_DT`, `AMT_TOT_NET`, `ORD_STS_CD: 'N'/'P'/'S'/'C'` field-ləri. Sizin domain: `Order`, `customerId`, `createdAt`, `totalAmount`, `OrderStatus::Pending`. ACL bu ikisi arasında translator-dır — domain ERP-dən xəbərsizdir.

### PHP/Laravel Nümunəsi

```php
<?php

// Anti-Corruption Layer — Legacy ERP → Domain translation
class LegacyOrderACL
{
    // Xarici model → Domain model
    public function toDomain(array $legacyOrder): Order
    {
        return new Order(
            id:         $legacyOrder['ORD_ID'],
            customerId: $legacyOrder['CUST_NO'],
            // Cents-ə çevir — xarici sistem decimal istifadə edir
            totalCents: (int) round($legacyOrder['AMT_TOT_NET'] * 100),
            status:     $this->mapStatus($legacyOrder['ORD_STS_CD']),
            createdAt:  \Carbon\Carbon::createFromFormat('Ymd', $legacyOrder['ORD_DT']),
        );
    }

    // Domain model → Xarici model
    public function fromDomain(Order $order): array
    {
        return [
            'ORD_ID'      => $order->id,
            'CUST_NO'     => $order->customerId,
            'AMT_TOT_NET' => $order->totalCents / 100,
            'ORD_STS_CD'  => $this->reverseMapStatus($order->status),
            'ORD_DT'      => $order->createdAt->format('Ymd'),
        ];
    }

    // Status mapping — xarici kodlar → domain enum
    private function mapStatus(string $legacyCode): OrderStatus
    {
        return match ($legacyCode) {
            'N' => OrderStatus::Pending,
            'P' => OrderStatus::Paid,
            'S' => OrderStatus::Shipped,
            'C' => OrderStatus::Cancelled,
            default => throw new \DomainException("Naməlum status kodu: {$legacyCode}"),
        };
    }

    private function reverseMapStatus(OrderStatus $status): string
    {
        return match ($status) {
            OrderStatus::Pending   => 'N',
            OrderStatus::Paid      => 'P',
            OrderStatus::Shipped   => 'S',
            OrderStatus::Cancelled => 'C',
        };
    }
}

// Repository — ACL vasitəsilə işləyir
class LegacyOrderRepository implements OrderRepository
{
    public function __construct(
        private LegacyOrderClient $legacyClient,
        private LegacyOrderACL    $acl,
    ) {}

    public function findById(string $id): ?Order
    {
        $raw = $this->legacyClient->getOrder($id);
        return $raw ? $this->acl->toDomain($raw) : null;
    }

    public function save(Order $order): void
    {
        $legacyData = $this->acl->fromDomain($order);
        $this->legacyClient->saveOrder($legacyData);
    }
}
```

```php
<?php

// Ambassador Pattern — outbound proxy; retry, CB, auth mərkəzləşir
class PaymentGatewayAmbassador
{
    public function __construct(
        private CircuitBreaker $circuitBreaker,
        private RetryPolicy    $retry,
    ) {}

    public function charge(array $data): array
    {
        return $this->retry->execute(function () use ($data) {
            return $this->circuitBreaker->call(function () use ($data) {
                return \Http::withHeaders([
                    'Authorization'      => 'Bearer ' . $this->getAccessToken(),
                    'X-Idempotency-Key'  => $data['idempotency_key'],
                ])
                ->timeout(10)
                ->post(config('payment.gateway_url') . '/charges', $data)
                ->throw()
                ->json();
            });
        });
    }

    // Token caching — hər sorğuda auth call etmə
    private function getAccessToken(): string
    {
        return \Cache::remember('payment:access_token', 3500, fn() =>
            \Http::post(config('payment.token_url'), [
                'client_id'     => config('payment.client_id'),
                'client_secret' => config('payment.client_secret'),
            ])->json('access_token')
        );
    }
}
```

```php
<?php

// Sidecar Pattern — K8s pod-da köməkçi container (PHP kodu deyil, konfiqurasiya)
// docker-compose.yml nümunəsi (development üçün):
//
// services:
//   app:
//     image: php:8.3-fpm
//     # App kodu dəyişmir — cross-cutting concerns sidecar-da
//
//   logging-sidecar:
//     image: fluent/fluentd:latest
//     volumes:
//       - ./storage/logs:/logs  # App log-larını oxuyur
//     # Logs → centralized logging system (Loki, Datadog)
//
//   otel-sidecar:
//     image: otel/opentelemetry-collector:latest
//     # Tracing data toplar, Jaeger/Tempo-ya göndərir
```

## Praktik Tapşırıqlar

1. Üçüncü tərəf payment API inteqrasiyası yazın: provayderın öz field adları var (`txn_id`, `txn_status`, `amt`); ACL ilə domain modelinizə çevirin; provayder API-si dəyişsə yalnız ACL dəyişsin; test: domain heç bir xarici terminologiya bilmir
2. `LegacyOrderACL.toDomain()` + `fromDomain()` yazın: tam round-trip test; `toDomain(fromDomain(order))` orijinal order-ı qaytarmalıdır
3. Ambassador pattern tətbiq edin: `PaymentGatewayAmbassador` — retry (3 dəfə, exponential backoff), circuit breaker, auth token caching; test: gateway timeout verəndə retry; gateway down olduqda CB açılır
4. Köhnə SOAP API üçün ACL yazın: XML response-u PHP array-ə çevirin, sonra domain modelinizə; unit test: SOAP XML fixture-dan domain object-i düzgün qurmaq

## Əlaqəli Mövzular

- [Strangler Fig Pattern](06-strangler-fig-pattern.md) — miqrasiya zamanı ACL köprü kimi işləyir
- [BFF Pattern](09-bff-pattern.md) — BFF-də downstream legacy model-ləri ACL ilə translate edilir
- [DDD Bounded Context](../ddd/06-ddd-bounded-context.md) — ACL bounded context-lər arası inteqrasiya nöqtəsidir
- [Repository Pattern](../laravel/01-repository-pattern.md) — ACL repository içərisindəki translation qatıdır
- [Circuit Breaker](16-circuit-breaker.md) — Ambassador pattern-də CB xarici servis qorunması
