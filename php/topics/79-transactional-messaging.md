# Transactional Messaging

## Problem necə yaranır?

Microservice-lər arası kommunikasiyada iki ayrı sistem mövcuddur: DB və message broker. Bu iki sistemə atomik yazı mümkün deyil — aralarında distributed transaction yoxdur.

**Ssenari 1 — Message itirilməsi:**
```
DB.commit(order)     ✅
Broker.publish(event) ❌ (network error)
→ Order yaradıldı, Payment service bilmir, sifariş işlənmir
```

**Ssenari 2 — Double publish:**
```
DB.commit(order)      ✅
Broker.publish(event)  ✅
App crash ← bu nöqtədə
Restart → yenidən publish ✅
→ Payment iki dəfə çıxılır
```

Bu problemin kökü: DB commit ilə broker publish arasındakı window-dur. Bu window sıfıra endirilə bilməz — amma effektini aradan qaldırmaq olar.

---

## Outbox Pattern — Publisher tərəfi

**Fikir:** Broker-a birbaşa yazmaq əvəzinə mesajı eyni DB transaction-da `outbox_messages` cədvəlinə yaz. Ayrı relay prosesi bu cədvəli oxuyub broker-a publish edir.

```
DB Transaction {
  INSERT INTO orders (...)
  INSERT INTO outbox_messages (topic, payload)   ← eyni TX!
}
→ İkisi ya hər ikisi yazılır, ya heç biri

Relay (ayrı proses):
  SELECT pending FROM outbox_messages
  Broker.publish(message)
  UPDATE outbox_messages SET status='published'
```

**Zəmanət:** DB commit olsa message mütləq broker-a çatacaq (relay retry edir). DB rollback olsa outbox da rollback — ghost message yoxdur.

**Relay crash olarsa:** Yenidən başlayıb `pending` mesajları tapır və publish edir. Bu at-least-once delivery deməkdir — dublikat mümkündür. Buna görə consumer tərəfində idempotency lazımdır.

---

## Inbox Pattern — Consumer tərəfi

Broker at-least-once delivery verir — eyni mesajı birdən çox dəfə deliver edə bilər (network issue, retry). Consumer idempotent olmalıdır.

**Inbox:** Hər mesajın broker tərəfindən verilən unique ID-si var. Consumer bu ID-ni DB-yə yazır. Əgər artıq yazılmışsa — dublikat, skip et.

```
Consumer mesaj alır:
  INSERT INTO inbox_messages (message_id) ON CONFLICT DO NOTHING
  → Affected rows = 1: yeni mesaj, işlə
  → Affected rows = 0: dublikat, skip et
```

**Outbox + Inbox = Exactly-once processing** (delivery deyil, processing).

---

## Delivery Semantics

| Semantika | Zəmanət | Risk | İstifadə |
|-----------|---------|------|---------|
| At-most-once | Çatmaya bilər | Message itirilməsi | Analytics, metrics |
| At-least-once | Mütləq çatır | Dublikat | Email, notification |
| Exactly-once processing | Bir dəfə işlənir | Complexity | Payment, inventory |

---

## PHP İmplementasiyası

*PHP İmplementasiyası üçün kod nümunəsi:*
```php
// Outbox — transaction içindən çağırılır, mesajı DB-yə yazır
class TransactionalOutbox
{
    public function publish(string $topic, array $payload): void
    {
        if (!DB::transactionLevel()) {
            throw new \RuntimeException('Outbox yalnız aktiv TX içindən çağırılmalıdır');
        }

        DB::table('outbox_messages')->insert([
            'id'         => Str::uuid(),
            'topic'      => $topic,
            'payload'    => json_encode($payload),
            'status'     => 'pending',
            'created_at' => now(),
        ]);
    }
}

// Order service — DB write + message publish atomik
class OrderService
{
    public function place(array $data): Order
    {
        return DB::transaction(function () use ($data) {
            $order = Order::create($data);

            // Broker-a deyil, outbox-a yazılır — eyni TX-də
            app(TransactionalOutbox::class)->publish('order.placed', [
                'order_id' => $order->id,
                'user_id'  => $order->user_id,
                'total'    => $order->total,
            ]);

            return $order;
        });
        // TX commit olsa həm order, həm outbox mesajı yazılıb
        // TX fail olsa heç biri yazılmayıb
    }
}

// Relay — pending outbox mesajlarını broker-a göndərir
class OutboxRelayJob implements ShouldQueue
{
    public function handle(): void
    {
        DB::transaction(function () {
            // lockForUpdate: parallel relay-lər eyni mesajı iki dəfə publish etməsin
            $messages = DB::table('outbox_messages')
                ->where('status', 'pending')
                ->orderBy('id')
                ->limit(100)
                ->lockForUpdate()
                ->get();

            foreach ($messages as $message) {
                try {
                    app(MessageBroker::class)->publish(
                        $message->topic,
                        json_decode($message->payload, true),
                        $message->id  // idempotency key broker üçün
                    );

                    DB::table('outbox_messages')
                        ->where('id', $message->id)
                        ->update(['status' => 'published', 'published_at' => now()]);

                } catch (\Exception $e) {
                    DB::table('outbox_messages')
                        ->where('id', $message->id)
                        ->increment('retry_count', 1, ['last_error' => $e->getMessage()]);
                }
            }
        });
    }
}

// Inbox — exactly-once processing, dublikat mesajları skip edir
class PaymentEventConsumer
{
    public function handle(array $message): void
    {
        $messageId = $message['id'];

        DB::transaction(function () use ($message, $messageId) {
            // insertOrIgnore: UNIQUE constraint — eyni message_id ikinci dəfə insert olmur
            $inserted = DB::table('inbox_messages')->insertOrIgnore([
                'message_id'  => $messageId,
                'topic'       => $message['topic'],
                'received_at' => now(),
            ]);

            if (!$inserted) {
                return; // Dublikat — heç nə etmə
            }

            // İlk dəfə gəlir — iş gör
            $this->processOrderPlaced($message['payload']);
        });
        // inbox insert + business logic eyni TX-də
        // Relay crash olsa, yenidən göndərsə — inbox block edir
    }
}
```

---

## CDC ilə Outbox (Polling əvəzinə)

Relay polling (hər N saniyə SELECT) əvəzinə Debezium kimi CDC tool-u `outbox_messages` cədvəlinin MySQL binlog-unu oxuyur — real-time, polling yoxdur, latency aşağı.

---

## Anti-patterns

- **Outbox-suz broker publish:** `DB::commit()` sonra `$broker->publish()` — network error halında message itirilir.
- **Inbox-suz consumer:** At-least-once broker + idempotent olmayan consumer = double charge, double email.
- **Outbox-u transaction xaricindən çağırmaq:** DB write ilə mesaj arasında atomiklik itirilir.
- **Outbox cədvəlini heç vaxt təmizləməmək:** Milyonlarla `published` mesaj yığılır, cədvəl böyüyür.

---

## İntervyu Sualları

**1. Niyə DB commit + broker publish atomik deyil?**
İki ayrı sistem — distributed transaction yoxdur. Network error, app crash, ya da broker down olduqda biri uğurlu, digəri uğursuz ola bilər. Outbox pattern bu problemi DB-nin öz ACID zəmanəti ilə həll edir.

**2. Outbox pattern at-least-once delivery verir — bu problem deyilmi?**
Relay retry etdiyi üçün mesaj birdən çox dəfə deliver oluna bilər. Bu qəbul ediləndir — consumer tərəfində Inbox pattern ilə idempotency təmin edilsə exactly-once processing əldə edilir.

**3. Outbox vs Saga fərqi nədir?**
Outbox: DB + broker arasında atomiklik problemi. Saga: çoxlu servis arasında distributed transaction. Saga-nın compensating transaction addımları Outbox pattern istifadə edərək publish edilə bilər — ikisi tamamlayıcıdır.

**4. Kafka-da exactly-once necə işləyir?**
`enable.idempotence=true`: producer eyni mesajı birdən çox göndərsə broker dublikatı atar. Transactional producer: `beginTransaction` → publish → `commitTransaction`. Consumer tərəfində Kafka transaction + DB write atomik edilə bilər (offset commit + DB write eyni TX).

**5. `FOR UPDATE SKIP LOCKED` relay-də niyə istifadə edilir?**
Parallel relay instance-lar eyni pending mesajı eyni anda pick etsə double publish riski var. `SKIP LOCKED`: başqa transaction-ın lock-ladığı sətirləri keçir. Hər relay instance ayrı sətirləri işləyir — race condition yoxdur, throughput artır. PostgreSQL 9.5+, MySQL 8.0+ dəstəkləyir.

**6. Outbox cədvəlinin sxeması necə olmalıdır?**
Minimum sütunlar: `id` (UUID, PK), `topic` (varchar), `payload` (json), `status` (pending/published/failed), `created_at`, `published_at`, `retry_count`, `last_error`. Index: `(status, created_at)` — relay bu index-lə pending mesajları sürətlə tapır.

**7. Transactional outbox vs Two-Phase Commit (2PC) fərqi nədir?**
2PC: distributed transaction coordinator — hər iki sistem (DB + broker) eyni anda commit/rollback. Amma coordinator SPOF, blocking protocol, broker-ların çoxu 2PC dəstəkləmir. Outbox: 2PC-yə ehtiyac yoxdur — DB-nin ACID-ini istifadə edib, broker-a async publish edir. Daha simple, daha reliable.

---

## Anti-patternlər

**1. Outbox olmadan DB commit + broker publish etmək**
`DB::commit()` uğurlu, sonra `$broker->publish()` çağırılır — network xətası, app crash, broker downtime halında mesaj itirilir, DB-ə yazılan data broker-ə çatmır. Outbox Pattern tətbiq edin: mesajı DB əməliyyatı ilə eyni transaction-da `outbox` cədvəlinə yazın; relay prosesi ayrıca publish etsin.

**2. Outbox relay-ini transaction xaricindən çağırmaq**
Relay `outbox` cədvəlindən mesajları alır, broker-ə göndərir, sonra DB-dən silir — broker publish uğurlu, DB delete uğursuz olarsa eyni mesaj bir daha publish edilir. Relay idempotent olsun: broker publish + DB status update atomik bir əməliyyat ya da `processed` flag-la idarə edilsin.

**3. Inbox Pattern olmadan at-least-once delivery istifadə etmək**
Broker eyni mesajı iki dəfə çatdırır (retry, requeue) — consumer idempotent deyil, iki dəfə ödəniş alınır, iki dəfə email göndərilir. Inbox Pattern tətbiq edin: `inbox` cədvəlinə `message_id` unikal yazın, artıq işlənibsə skip edin; unikal constraint ilə race condition önlənir.

**4. Outbox cədvəlini heç vaxt təmizləməmək**
`published` statuslu milyonlarla sətir yığılır — cədvəl böyüyür, index-lər şişir, relay sorğuları yavaşlayır. Müntəzəm cleanup prosesi qurun: `published` + müəyyən müddət keçmiş (məs: 7 gün) sətirləri silin; chunk-larla silin ki, lock problemi yaranmasın.

**5. Relay polling intervalını çox uzun qoymaq**
Relay hər 60 saniyədə bir `outbox`-u yoxlayır — event-driven sistemi 60 saniyə gecikmə ilə işləyir. Polling intervalını qısaldın (1-5s); daha yaxşısı: DB notification (PostgreSQL `LISTEN/NOTIFY`) ya da CDC ilə relay-i push-based edin ki, yeni sətir yazılanda dərhal trigger olsun.

**6. Outbox relay-ini single instance olaraq işlətmək**
Relay prosesi tək instance-dır — process çöksə outbox sətirləri yığılır, mesajlar gecikmə ilə çatdırılır. Relay-i HA rejimində qurun: çox instance işləsin, lakin eyni sətiri iki dəfə publish etməmək üçün `FOR UPDATE SKIP LOCKED` ilə row-level lock istifadə edin.
