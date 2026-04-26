# Email System Design (Senior)

## İcmal

Email system Gmail/Outlook kimi tam mailbox platformasıdır — internetdən mail
qəbul etmək, göndərmək, milyardlarla mesajı illərlə saxlamaq, bütün mailbox-da
full-text search, spam filter, attachment, label/folder, IMAP/POP/SMTP.

Sadə dillə: Notification system (file 13) bir mesaj göndərir və unudur.
Email system istifadəçinin bütün məktub tarixçəsini saxlayır, axtarır,
threading edir, spam-dan qoruyur və SMTP protokolu üzərində digər mail
server-ləri ilə danışır.

```
Internet MX              Gmail Infrastructure              User
  sender.com ──SMTP:25──▶ ┌──────────┐ ┌──────────┐ ──▶ Web UI
                          │ SMTP     │ │ Spam /   │
                          │ Ingress  │─│ Virus    │ ──▶ IMAP
                          └────┬─────┘ └────┬─────┘
                               ▼            ▼
                          ┌──────────────────────┐ ──▶ Mobile
                          │  Mailbox Service     │
                          │  (meta+blob+index)   │
                          └──────────────────────┘
```


## Niyə Vacibdir

Email delivery SMTP, SPF/DKIM/DMARC, spam filtering, threading, search kimi bir çox mürəkkəb problemi əhatə edir. Hər SaaS məhsulu email göndərir; delivery reliability biznes üçün kritikdir. SendGrid, Mailgun — bu sistemlərin daxili işini bilmək inteqrasiya qərarlarını yaxşılaşdırır.

## Tələblər

### Functional
- Send / receive email (SMTP), IMAP / POP3 client sync
- N il (5-10 il) mailbox tarixçəsi saxla
- Full-text search: subject + body + from + to
- Attachment-lər 25MB-a qədər
- Label / folder / star / archive, threading, filter, auto-reply
- Spam və virus filter

### Non-Functional
```
Users:           1.5B (Gmail)
Emails/user/day: ~20 received + ~5 sent
Daily volume:    30B mesaj/gün qəbul
Avg size:        75 KB (body + headers)
Storage/user:    15 MB/ay, 10 il üçün ~2 GB
Total storage:   3 EB (exabytes) sırasında
Availability:    99.99% (mail itməməlidir)
Durability:      11 nine, multi-region replication
Latency:         Send < 5s görünən, receive < 30s
```

## Əsas Anlayışlar

### 1. SMTP Ingress

```
MX record: gmail.com ──▶ smtp-in.l.google.com:25

→ HELO sender.com          ← 250 Hello
→ MAIL FROM: <a@sender>    ← 250 OK
→ RCPT TO: <b@gmail.com>   ← 250 OK
→ DATA
→ ... (RFC 5322 headers + body)
→ .                        ← 250 Queued as abc123
→ QUIT
```

Ingress validate edir: SPF/DKIM, blocklist, rate limit, recipient exists.
STARTTLS ilə TLS. Sonra internal queue-ya qoyur.

### 2. Outbound SMTP

```
User sends → Mailbox API → Outbound Queue → SMTP Relay → Recipient MX

- IP reputation (dedicated/shared IP pools, warm-up)
- SPF, DKIM, DMARC ilə imzalama
- Retry: 4xx soft → exp backoff, 30 gün cap; 5xx hard → bounce
- Bounce handling: VERP (Variable Envelope Return Path)
- List-Unsubscribe header (RFC 8058)
```

### 3. Storage Strategy

```
Metadata (Bigtable/Cassandra columnar) — partition by user_id
  message_id, thread_id, labels, flags, from, to, subject, snippet

Blob Store (S3/GCS) — content-addressed SHA-256
  Body (gzip, deduplicated) + attachments (virus-scanned)

Search Index (Elasticsearch/Vespa) — per-user inverted index
```

**Dedup vacib**: mailing list 1M abunəçiyə eyni mesaj. Body content-hash ilə
1 nüsxə, 1M metadata fərqli blob_ref ilə refer edir.

### 4. Storage Tiering

```
Hot  (son 30d)   → SSD, low latency       ~5%  data
Warm (1 il)      → HDD, standart storage  ~25% data
Cold (1+ il)     → Object (Glacier)       ~70% data
```

Search index yalnız hot+warm üçün live; cold rehydrate-dən sonra.

## Data Model

```sql
CREATE TABLE messages (
    user_id       BIGINT,
    message_id    BIGINT,            -- Snowflake
    thread_id     BIGINT,
    folder_id     INT,               -- INBOX, SENT, TRASH, SPAM
    from_addr     VARCHAR(320),
    to_addrs      TEXT,              -- JSON array
    subject       VARCHAR(1000),
    snippet       VARCHAR(200),
    blob_ref      VARCHAR(64),       -- SHA-256 body blob
    attachment_refs TEXT,            -- JSON array of blob refs
    labels        TEXT,              -- JSON array
    flags         INT,               -- bitfield: READ, STARRED
    received_at   TIMESTAMP,
    PRIMARY KEY ((user_id), received_at DESC, message_id)
);

CREATE TABLE threads (
    user_id BIGINT, thread_id BIGINT,
    subject VARCHAR(1000), participants TEXT,
    message_count INT, last_message_at TIMESTAMP,
    PRIMARY KEY ((user_id), last_message_at DESC, thread_id)
);
```

## Threading

```
1. In-Reply-To header → direct parent
2. References header  → ancestor chain
3. Fallback: normalized subject + participants + 30d window

Normalize: strip "Re:", "Fwd:", whitespace, lowercase

Msg1: Subject "Project", Message-ID: <a@x>
Msg2: "Re: Project", In-Reply-To: <a@x>  → same thread
Msg3: "Re: Project", References: <a@x> <b@x>  → same thread
```

## Spam / Virus Detection

```
Incoming pipeline:
1. IP blocklist (Spamhaus, SORBS)
2. SPF / DKIM / DMARC validation
3. Bayesian filter (word frequencies)
4. ML scoring (sender reputation, links, content)
5. Virus scan (ClamAV + vendor engines)
6. User-reported spam feedback loop → retrain

Score > 0.95 → reject (5xx at SMTP)
Score > 0.7  → SPAM folder
Score < 0.3  → INBOX
```

## SPF / DKIM / DMARC

```
SPF  (DNS TXT):
  sender.com TXT "v=spf1 ip4:192.0.2.0/24 include:_spf.google.com -all"
  → hansı IP-lər bu domain adından göndərə bilər

DKIM (header signature):
  DKIM-Signature: v=1; a=rsa-sha256; d=sender.com; s=sel1; bh=...; b=<sig>
  → DNS-də public key, imzanı yoxla

DMARC (policy):
  _dmarc.sender.com TXT "v=DMARC1; p=reject; rua=mailto:dmarc@sender.com"
  → SPF+DKIM fail olanda nə et, hara report
```

## Search

```
Per-user inverted index, user-level sharding:
  shard(user_id) → ES cluster
  Fields: from, to, subject, body, labels, has_attachment, date
  Query: "from:alice project after:2025-01-01 has:attachment"

Pipeline: new mail → Kafka "new-email" → indexer → ES bulk API (~1s NRT)
Cold mail: archive-də yalnız metadata tag; açıq sorğuda rehydrate.
```

## Architecture

```
Internet MX                        Gmail Users
    │                                   ▲
    ▼                                   │
┌──────────────┐                 ┌─────────────┐
│ SMTP Ingress │                 │ Web / Mobile│
│ port 25 TLS  │                 └──────┬──────┘
└──────┬───────┘                        │
       ▼                                ▼
┌──────────────┐          ┌──────────────────────┐
│ Spam/Virus   │          │ Mailbox API Gateway  │
└──────┬───────┘          └──────────┬───────────┘
       │                             │
       ▼                             ▼
┌────────────────────────────────────────────────┐
│         Mailbox Service (stateless)            │
└─┬──────────────┬──────────────┬────────────────┘
  ▼              ▼              ▼
┌──────────┐ ┌──────────┐ ┌──────────────┐
│ Metadata │ │ Blob S3  │ │ Search ES    │
│ Bigtable │ └──────────┘ └──────────────┘
└──────────┘
     ▲
     │ (new-mail → Kafka)
     │
┌────┴──────────┐   ┌───────────────┐
│ IMAP / POP3   │   │ Outbound SMTP │──▶ Internet MX
│ (sticky sess) │   │ Relay + Queue │
└───────────────┘   └───────────────┘
```

## Nümunələr

### Outbound Service (dedup + queue)

```php
class OutboundMailService
{
    public function __construct(
        private MessageRepository $messages,
        private BlobStore $blobs
    ) {}

    public function queue(
        int $userId, string $from, array $to,
        string $subject, string $body, array $attachments
    ): OutgoingMessage {
        foreach ($attachments as $file) {
            if ($file->getSize() > 25 * 1024 * 1024) {
                throw new AttachmentTooLargeException('Max 25MB');
            }
            if (!app(VirusScanner::class)->scan($file->path())) {
                throw new InfectedAttachmentException();
            }
        }

        // Content-addressed dedup
        $bodyHash = hash('sha256', $body);
        $this->blobs->putIfAbsent($bodyHash, $body);

        $attachmentRefs = collect($attachments)->map(function ($file) {
            $hash = hash_file('sha256', $file->path());
            $this->blobs->putIfAbsent($hash, file_get_contents($file->path()));
            return ['hash' => $hash, 'name' => $file->getClientOriginalName()];
        })->toArray();

        $message = $this->messages->create([
            'id' => Snowflake::generate(),
            'user_id' => $userId,
            'folder_id' => Folder::SENT,
            'from_addr' => $from,
            'to_addrs' => json_encode($to),
            'subject' => $subject,
            'snippet' => Str::limit(strip_tags($body), 200),
            'blob_ref' => $bodyHash,
            'attachment_refs' => json_encode($attachmentRefs),
            'received_at' => now(),
        ]);

        // Horizon queue for outbound SMTP relay
        SendOutboundMail::dispatch($message)
            ->onQueue('smtp-outbound')
            ->delay(now()->addSeconds(2)); // undo-send window

        return $message;
    }
}
```

### Inbound Processing Job

```php
class ProcessInboundMail implements ShouldQueue
{
    public function handle(
        MessageParser $parser, SpamScorer $spam,
        ThreadResolver $threads, SearchIndexer $indexer
    ): void {
        $parsed = $parser->parse(Storage::get($this->rawRef));
        $this->verifySpfDkimDmarc($parsed);

        $score = $spam->score($parsed);
        $folder = $score > 0.7 ? Folder::SPAM : Folder::INBOX;

        $recipient = User::whereEmail($parsed->to[0])->firstOrFail();
        $threadId = $threads->resolve($recipient->id, $parsed);

        $message = Message::create([
            'id' => Snowflake::generate(),
            'user_id' => $recipient->id,
            'thread_id' => $threadId,
            'folder_id' => $folder,
            'from_addr' => $parsed->from,
            'subject' => $parsed->subject,
            'snippet' => Str::limit($parsed->textBody, 200),
            'blob_ref' => $this->storeBlobs($parsed),
            'received_at' => $parsed->date,
        ]);

        $indexer->index($message);  // Meilisearch/Scout
        event(new NewMailArrived($recipient->id, $message));
    }
}
```

### IMAP IDLE / Push Sync

```php
class NewMailArrived implements ShouldBroadcast
{
    public function __construct(public int $userId, public Message $message) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("mailbox.{$this->userId}");
    }
}
// Reverb/Pusher → WebSocket to mobile/web clients
```

## Edge Cases

```
1. Mail loops: Auto-Submitted header; don't reply if != "no"
2. Bounce storm: suppression list + VERP (per-recipient tracking)
3. Large attachment > 25MB: Drive link injection, chunked transfer
4. Forwarding loop A→B→A: Received header hop count, max 100
5. Unicode domain IDN (xn--...): normalize before lookup/dedup
6. PGP/S-MIME: ciphertext as-is, search limited to headers
```

## Compliance

```
GDPR:      delete cascade metadata + blob refcount--, GC when 0
HIPAA:     BAA, encryption rest+transit, audit log every access
SOX:       finance mail 7 years, WORM storage, immutable
Retention: 30d Trash/Spam auto-delete
```

## Real-World Nümunələr

1. **Gmail** — 1.5B users, Bigtable + Colossus, Vespa search
2. **Outlook/Exchange** — M365, ESE DB, DAG replication
3. **ProtonMail** — E2E encrypted, client-side search, Swiss DCs
4. **FastMail** — Cyrus IMAP, JMAP pioneer
5. **AWS SES** — API-only sending, no mailbox

## Praktik Tapşırıqlar

**S1: 1.5B user üçün metadata-nı necə partition edirsən?**
C: user_id ilə shard (consistent hashing). Hər mailbox eyni shard-da —
query single-shard olur. Global cross-user query nadir, fan-out ilə həll.
Power user-ləri ayrıca shard-a köçürmək olar.

**S2: Eyni mesaj 1M abunəçiyə gəlir — storage necə optimize edirsən?**
C: Body SHA-256 content-addressed. Blob store-a 1 nüsxə, 1M metadata fərqli
blob_ref ilə. Refcount (və ya orphan GC). Nəticə: 75KB × 1 = 75KB body,
+ 1M × 500B metadata = 500MB. 75GB əvəzinə yalnız 500MB + 75KB.

**S3: Bütün mailbox-da full-text search necə işləyir?**
C: Per-user inverted index, user-level sharding. Yeni mail → Kafka event →
indexer consumer → Elasticsearch bulk (NRT ~1s). Query yalnız user-in
shard-ına. Cold mail metadata-only; full-text üçün rehydrate lazım.

**S4: SPF, DKIM, DMARC fərqi nədir?**
C: SPF — DNS-də "bu IP-lər domain adından göndərə bilər"; envelope üçün.
DKIM — mesajı private key ilə imzalayır, alıcı DNS public key ilə yoxlayır;
body integrity. DMARC — SPF/DKIM fail olanda policy (reject/quarantine) +
report. Üçü birlikdə spoofing-i blok edir, deliverability üçün kritik.

**S5: Outbound IP reputation necə idarə olunur?**
C: Dedicated IP pool enterprise üçün, shared free tier üçün. Yeni IP warm-up:
gün 1-də 50, sonra 2x. Volume birdən yüksəlsə spam sayılır. Bounce > 5% →
IP çıxar. DMARC report + feedback loop (AOL/Yahoo FBL) signal.

**S6: Threading necə qurulur?**
C: In-Reply-To → parent; References → tam zəncir. Ən yaxın parent-in
thread-ı; yoxsa References-dən birinin. Fallback: normalized subject (Re:/Fwd:
strip) + participants + 30d window. thread_id mesaja verilir, UI group edir.

**S7: IMAP server stateful — horizontal scale necə?**
C: IMAP session uzun müddətlidir (IDLE üçün), state saxlayır (selected folder,
uidvalidity). Sticky session eyni user-i eyni server-ə yönəldir. Server fail
→ client reconnect, state IMAP protokolundan bərpa. Alternativ: JMAP
(stateless HTTP/JSON) modern client-lər üçün.

**S8: Spam filter user-dən necə öyrənir?**
C: "Report spam" → training set-ə spam, "Not spam" → ham. Nightly batch
ML model retrain (Bayesian + gradient boost). Per-user model + global model
birləşir. Blocklist-lər external feed (Spamhaus). Sender reputation IP +
domain + DKIM identity səviyyəsində.

## Praktik Baxış

1. **Content-addressed storage** — body/attachment SHA-256 ilə dedup
2. **User-level sharding** — mailbox single shard-da, cross-user minimal
3. **Storage tiering** — hot/warm/cold, köhnə mail ucuz storage-a
4. **SPF + DKIM + DMARC** — hər üçü, deliverability üçün vacib
5. **IP warming** — yeni outbound IP tədricən (50 → 100 → 200 → ...)
6. **Bounce suppression** — hard bounce ünvanına bir daha göndərmə
7. **Virus scan before delivery** — ClamAV + vendor, never skip
8. **Async indexing** — Kafka + indexer, delivery-ni bloklamasın
9. **List-Unsubscribe** — RFC 8058 one-click, spam complaint azaldır
10. **Rate limit outbound** — per-user + per-domain, abuse prevention
11. **Retry with backoff** — 4xx exp backoff, 30d cap, sonra bounce
12. **Archive compliance** — WORM storage finance/medical, immutable


## Əlaqəli Mövzular

- [Notification System](13-notification-system.md) — email notification channel
- [Message Queues](05-message-queues.md) — email delivery queue
- [Idempotency](28-idempotency.md) — email deduplication
- [Webhook Delivery](82-webhook-delivery-system.md) — email event webhook-ları
- [Push Notification](79-push-notification-backend.md) — digər notification kanalı
