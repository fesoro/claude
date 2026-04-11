# System Design: Audit Log System

## Mündəricat
1. [Tələblər](#tələblər)
2. [Yüksək Səviyyəli Dizayn](#yüksək-səviyyəli-dizayn)
3. [Komponent Dizaynı](#komponent-dizaynı)
4. [PHP İmplementasiyası](#php-implementasiyası)
5. [İntervyu Sualları](#intervyu-sualları)

---

## Tələblər

```
Funksional:
  Hər dəyişikliyi qeyd et: kim, nə etdi, nə vaxt, nədən nəyə
  Axtarış: istifadəçiyə görə, resursa görə, vaxt aralığına görə
  Ixrac: compliance üçün CSV/PDF ixracı
  Tamperproof: log-lar dəyişdirilə bilməz

Qeyri-funksional:
  Write-heavy: hər əməliyyat = 1+ audit record
  Uzun müddətli saxlama: 7 il (GDPR, PCI DSS)
  Axtarış: < 1 saniyə
  Immutable: yazılan log silinə/dəyişdirilə bilməz

Compliance tələbləri:
  GDPR: şəxsi datanın işlənməsi izlənilir
  PCI DSS: ödəniş sistemlərindəki dəyişikliklər
  HIPAA: tibbi qeydlərə giriş
  SOX: maliyyə məlumatları
```

---

## Yüksək Səviyyəli Dizayn

```
┌───────────────┐   event   ┌──────────────────┐
│  Application  │──────────►│  Audit Service   │
└───────────────┘           └────────┬─────────┘
                                     │
                            ┌────────▼─────────┐
                            │   Message Queue  │
                            │ (Kafka/async)    │
                            └────────┬─────────┘
                                     │
              ┌──────────────────────┼──────────────────────┐
              │                      │                      │
    ┌─────────▼──────┐   ┌───────────▼───────┐  ┌──────────▼──────┐
    │  Primary DB    │   │  Search Index     │  │  Cold Storage   │
    │  (PostgreSQL)  │   │ (Elasticsearch)   │  │  (S3 / Glacier) │
    │  recent logs   │   │  searchable       │  │  7-year archive │
    └────────────────┘   └───────────────────┘  └─────────────────┘

Immutability:
  Audit DB-ə yalnız INSERT (heç vaxt UPDATE/DELETE)
  DB user-i yalnız INSERT icazəsi
  Kriptografik chaining (Merkle tree / hash chain)
```

---

## Komponent Dizaynı

```
Audit Record strukturu:
  id:           UUID
  event_type:   'order.created', 'user.password_changed'
  actor_id:     Kim etdi (user_id, service_name)
  actor_ip:     IP ünvanı
  resource_type: 'Order', 'User'
  resource_id:  'order-123'
  action:       'create', 'update', 'delete', 'read'
  before:       JSON — dəyişiklikdən əvvəlki vəziyyət
  after:        JSON — dəyişiklikdən sonrakı vəziyyət
  metadata:     correlation_id, request_id, user_agent
  occurred_at:  Timestamp (microseconds)
  hash:         SHA-256(prev_hash + record_data) — tamperproof

Hash Chaining:
  Record 1: hash = SHA256(genesis + data1)
  Record 2: hash = SHA256(record1_hash + data2)
  Record 3: hash = SHA256(record2_hash + data3)
  
  Bir record dəyişirilsə → bütün sonrakı hash-lər pozulur
  Tamper detection mümkün

Data Retention:
  Hot (0-90 gün): PostgreSQL — sürətli axtarış
  Warm (90 gün-2 il): Elasticsearch — sürətli axtarış
  Cold (2-7 il): S3 Glacier — ucuz, yavaş oxuma
  
  Lifecycle policy avtomatik köçürür

PII (Şəxsi Məlumat):
  Audit log-da şifrələnmiş saxla
  Encryption key ayrıca Key Management Service-də
  GDPR "right to be forgotten": log silinmir amma PII şifrələnir
```

---

## PHP İmplementasiyası

```php
<?php
// Audit Event DTO
namespace App\Audit\Domain;

class AuditEvent
{
    public readonly string $id;
    public readonly string $hash;

    public function __construct(
        public readonly string  $eventType,
        public readonly string  $actorId,
        public readonly string  $actorIp,
        public readonly string  $resourceType,
        public readonly string  $resourceId,
        public readonly string  $action,
        public readonly ?array  $before,
        public readonly ?array  $after,
        public readonly array   $metadata,
        public readonly string  $occurredAt,
        private readonly string $previousHash,
    ) {
        $this->id   = bin2hex(random_bytes(16));
        $this->hash = $this->computeHash();
    }

    private function computeHash(): string
    {
        $data = implode('|', [
            $this->previousHash,
            $this->id,
            $this->eventType,
            $this->actorId,
            $this->resourceType,
            $this->resourceId,
            $this->occurredAt,
            json_encode($this->before),
            json_encode($this->after),
        ]);

        return hash('sha256', $data);
    }
}
```

```php
<?php
// Audit Logger — application seviyyəsindən audit qeydiyyat
class AuditLogger
{
    public function __construct(
        private AuditEventRepository $repository,
        private MessageQueue         $queue,
        private RequestContext       $context,
    ) {}

    public function log(
        string  $eventType,
        string  $resourceType,
        string  $resourceId,
        string  $action,
        ?array  $before = null,
        ?array  $after  = null,
        array   $extra  = [],
    ): void {
        $prevHash = $this->repository->getLastHash($resourceType, $resourceId)
            ?? '0000000000000000'; // Genesis hash

        $event = new AuditEvent(
            eventType:    $eventType,
            actorId:      $this->context->getUserId(),
            actorIp:      $this->context->getClientIp(),
            resourceType: $resourceType,
            resourceId:   $resourceId,
            action:       $action,
            before:       $before,
            after:        $after,
            metadata:     array_merge([
                'correlation_id' => $this->context->getCorrelationId(),
                'request_id'     => $this->context->getRequestId(),
                'user_agent'     => $this->context->getUserAgent(),
            ], $extra),
            occurredAt:   (new \DateTimeImmutable())->format('Y-m-d H:i:s.u'),
            previousHash: $prevHash,
        );

        // Async — main flow-u yavaşlatmır
        $this->queue->publish($event);
    }
}
```

```php
<?php
// Doctrine Subscriber — avtomatik audit
use Doctrine\ORM\Events;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Event\LifecycleEventArgs;

class AuditSubscriber
{
    public function __construct(private AuditLogger $logger) {}

    public function getSubscribedEvents(): array
    {
        return [Events::postPersist, Events::preUpdate, Events::preRemove];
    }

    public function postPersist(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof AuditableInterface) return;

        $this->logger->log(
            eventType:    get_class($entity) . '.created',
            resourceType: $entity->getAuditResourceType(),
            resourceId:   (string) $entity->getId(),
            action:       'create',
            after:        $entity->toAuditArray(),
        );
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof AuditableInterface) return;

        $changes = $args->getEntityChangeSet();
        $before  = [];
        $after   = [];

        foreach ($changes as $field => [$old, $new]) {
            $before[$field] = $old;
            $after[$field]  = $new;
        }

        $this->logger->log(
            eventType:    get_class($entity) . '.updated',
            resourceType: $entity->getAuditResourceType(),
            resourceId:   (string) $entity->getId(),
            action:       'update',
            before:       $before,
            after:        $after,
        );
    }
}
```

```php
<?php
// Tamper detection
class AuditIntegrityChecker
{
    public function verify(string $resourceType, string $resourceId): bool
    {
        $events = $this->repository->findChronological($resourceType, $resourceId);

        $prevHash = '0000000000000000';

        foreach ($events as $event) {
            $expectedHash = $this->computeExpectedHash($event, $prevHash);

            if ($event->getHash() !== $expectedHash) {
                $this->logger->critical("Audit log tampered!", [
                    'event_id'      => $event->getId(),
                    'expected_hash' => $expectedHash,
                    'actual_hash'   => $event->getHash(),
                ]);
                return false;
            }

            $prevHash = $event->getHash();
        }

        return true;
    }
}
```

---

## İntervyu Sualları

- Audit log-ların immutability-sini necə təmin edərdiniz?
- Hash chaining nədir? Tamper detection üçün necə işləyir?
- 7 illik audit data-sını saxlamaq üçün storage strategiyası?
- Audit logging main application performance-ına necə təsir etməməlidir?
- GDPR "right to erasure" audit log ilə necə balanslaşdırılır?
- Audit log-un özü audit edilə bilərmi?
