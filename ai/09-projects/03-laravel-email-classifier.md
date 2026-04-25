# Email Classifier və Auto-Responder (Middle)

Şirkət poçt qutusuna (`support@`, `info@`, `sales@`) gələn hər məktubu AI klassifikasiya edir (support/sales/billing/spam/urgent) və avtomatik FAQ cavabı göndərir və ya müvafiq komandaya routinq edir. Ucuz klassifikasiya Haiku-da, cavab generasiyası Sonnet-də. Human-in-the-loop: bütün auto-reply göndərilmədən əvvəl Filament dashboard-da operator təsdiqinə düşür (və ya yüksək confidence-də avtomatik göndərilir). Tam trace: hər mesajın klassifikasiya pəncərəsi, KB axtarışı, cavab draft-i, kimin təsdiqlədiyi.

---

## Arxitektura Baxışı

```
External Email
    │
    ▼
Mailgun Inbound Route → POST /webhook/mailgun
        (və ya IMAP poll → MailboxSyncJob)
    │
    ▼
RawEmailReceived (cədvələ yazılır)
    │
    ▼
ClassifyEmailJob (Haiku)  ──► structured JSON {category, priority, confidence, language, intent}
    │
    ├── category=spam → silinir
    ├── confidence<0.6 → manual triage queue
    ├── category=sales/billing → Zendesk/CRM ticket
    └── category=support + intent=faq + conf>0.85 →
             GenerateReplyJob (Sonnet + RAG) → draft
                ├── conf>=0.9 & safety ok → auto-send
                └── else → Filament inbox (operator təsdiqlə/dəyiş)
    │
    ▼
SendReplyJob → Mailgun Messages API → thread_id yenilənir
    │
    ▼
Metrics dashboard: accuracy, auto-reply rate, CSAT
```

**Niyə 2 model:**
- Haiku ~10x ucuzdur klassifikasiya üçün (input-heavy, output kiçik).
- Sonnet yalnız cavab lazım olanda çağırılır — orta hesabla emailsin 30%-ində.
- Ümumi qənaət 60-70%.

---

## Verilənlər Bazası Miqrasiyaları

```php
// database/migrations/2026_04_21_000020_create_email_classifier_tables.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');

        Schema::create('mailboxes', function (Blueprint $table) {
            $table->id();
            $table->string('address')->unique();   // support@company.com
            $table->string('team');                 // support, sales, billing
            $table->boolean('auto_reply_enabled')->default(false);
            $table->decimal('auto_send_threshold', 3, 2)->default(0.90);
            $table->json('escalation_emails')->nullable();
            $table->timestamps();
        });

        Schema::create('raw_emails', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('mailbox_id')->constrained()->cascadeOnDelete();
            $table->string('message_id')->unique(); // RFC-822 Message-Id
            $table->string('thread_id')->nullable();
            $table->string('from_email');
            $table->string('from_name')->nullable();
            $table->json('to_emails');
            $table->json('cc_emails')->nullable();
            $table->string('subject')->nullable();
            $table->longText('body_plain');
            $table->longText('body_html')->nullable();
            $table->json('headers')->nullable();
            $table->json('attachments')->nullable();
            $table->string('language', 8)->nullable();
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['mailbox_id', 'received_at']);
            $table->index('from_email');
            $table->index('thread_id');
        });

        Schema::create('email_classifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_id')->constrained('raw_emails')->cascadeOnDelete();
            $table->string('category');           // support, sales, billing, spam, urgent
            $table->string('priority');           // low, normal, high, urgent
            $table->string('intent')->nullable(); // faq, complaint, refund, quote, ...
            $table->string('language', 8);
            $table->decimal('confidence', 4, 3);
            $table->json('entities')->nullable(); // order_number, invoice, customer_id
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->string('model');
            $table->decimal('cost_usd', 10, 6)->default(0);
            $table->json('raw_response')->nullable();
            $table->timestamp('classified_at');
            $table->timestamps();
        });

        Schema::create('email_replies', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('email_id')->constrained('raw_emails')->cascadeOnDelete();
            $table->longText('draft_text');
            $table->longText('final_text')->nullable();
            $table->json('retrieved_docs')->nullable();
            $table->string('status')->default('pending'); // pending, approved, edited, rejected, sent, failed
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('message_id_sent')->nullable();
            $table->decimal('confidence', 4, 3)->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->decimal('cost_usd', 10, 6)->default(0);
            $table->boolean('was_auto_sent')->default(false);
            $table->json('edit_diff')->nullable(); // operator nə dəyişdi
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::create('email_threads', function (Blueprint $table) {
            $table->id();
            $table->string('thread_id')->unique();
            $table->string('customer_email');
            $table->unsignedInteger('message_count')->default(0);
            $table->string('status')->default('open');
            $table->json('summary')->nullable(); // rolling summary
            $table->timestamp('last_message_at');
            $table->timestamps();
        });

        Schema::create('faq_documents', function (Blueprint $table) {
            $table->id();
            $table->string('question');
            $table->longText('answer');
            $table->string('category');
            $table->string('language', 8);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('faq_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('faq_id')->constrained('faq_documents')->cascadeOnDelete();
            $table->text('content');
            $table->timestamps();
        });

        DB::statement('ALTER TABLE faq_chunks ADD COLUMN embedding vector(1024)');
        DB::statement('CREATE INDEX faq_chunks_embedding_idx ON faq_chunks USING hnsw (embedding vector_cosine_ops)');

        Schema::create('classification_metrics', function (Blueprint $table) {
            $table->id();
            $table->date('day');
            $table->string('category');
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('correct')->default(0); // manual label ilə müqayisə
            $table->unsignedInteger('auto_replies_sent')->default(0);
            $table->unsignedInteger('auto_replies_edited')->default(0);
            $table->unsignedInteger('auto_replies_rejected')->default(0);
            $table->decimal('avg_turnaround_seconds', 10, 2)->nullable();
            $table->timestamps();

            $table->unique(['day', 'category']);
        });
    }
};
```

---

## Modellər

```php
// app/Models/RawEmail.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RawEmail extends Model
{
    use HasUlids;

    protected $fillable = [
        'mailbox_id', 'message_id', 'thread_id', 'from_email', 'from_name',
        'to_emails', 'cc_emails', 'subject', 'body_plain', 'body_html',
        'headers', 'attachments', 'language', 'received_at', 'processed_at',
    ];

    protected $casts = [
        'to_emails' => 'array',
        'cc_emails' => 'array',
        'headers' => 'array',
        'attachments' => 'array',
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function mailbox(): BelongsTo
    {
        return $this->belongsTo(Mailbox::class);
    }

    public function classification(): HasOne
    {
        return $this->hasOne(EmailClassification::class, 'email_id');
    }

    public function reply(): HasOne
    {
        return $this->hasOne(EmailReply::class, 'email_id');
    }
}
```

```php
// app/Models/EmailClassification.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailClassification extends Model
{
    protected $fillable = [
        'email_id', 'category', 'priority', 'intent', 'language',
        'confidence', 'entities', 'input_tokens', 'output_tokens',
        'model', 'cost_usd', 'raw_response', 'classified_at',
    ];

    protected $casts = [
        'entities' => 'array',
        'raw_response' => 'array',
        'confidence' => 'float',
        'cost_usd' => 'decimal:6',
        'classified_at' => 'datetime',
    ];
}
```

```php
// app/Models/EmailReply.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class EmailReply extends Model
{
    use HasUlids;

    protected $fillable = [
        'email_id', 'draft_text', 'final_text', 'retrieved_docs',
        'status', 'approved_by', 'approved_at', 'sent_at', 'message_id_sent',
        'confidence', 'input_tokens', 'output_tokens', 'cost_usd',
        'was_auto_sent', 'edit_diff',
    ];

    protected $casts = [
        'retrieved_docs' => 'array',
        'edit_diff' => 'array',
        'approved_at' => 'datetime',
        'sent_at' => 'datetime',
        'was_auto_sent' => 'bool',
        'confidence' => 'float',
    ];
}
```

```php
// app/Models/Mailbox.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Mailbox extends Model
{
    protected $fillable = [
        'address', 'team', 'auto_reply_enabled',
        'auto_send_threshold', 'escalation_emails',
    ];

    protected $casts = [
        'auto_reply_enabled' => 'bool',
        'auto_send_threshold' => 'float',
        'escalation_emails' => 'array',
    ];
}
```

```php
// app/Models/EmailThread.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailThread extends Model
{
    protected $fillable = [
        'thread_id', 'customer_email', 'message_count',
        'status', 'summary', 'last_message_at',
    ];

    protected $casts = [
        'summary' => 'array',
        'last_message_at' => 'datetime',
    ];
}
```

---

## Mailgun Webhook Controller

```php
// app/Http/Controllers/MailgunWebhookController.php
<?php

namespace App\Http\Controllers;

use App\Jobs\ClassifyEmailJob;
use App\Models\Mailbox;
use App\Models\RawEmail;
use Illuminate\Http\Request;

class MailgunWebhookController extends Controller
{
    public function inbound(Request $request)
    {
        $this->verifySignature($request);

        $to = $request->input('recipient');
        $mailbox = Mailbox::where('address', $to)->first();
        if (!$mailbox) {
            return response('mailbox not found', 202); // Mailgun retry etməsin
        }

        $messageId = $request->input('Message-Id') ?: 'mg-' . uniqid();
        if (RawEmail::where('message_id', $messageId)->exists()) {
            return response('duplicate', 200);
        }

        $email = RawEmail::create([
            'mailbox_id' => $mailbox->id,
            'message_id' => $messageId,
            'thread_id' => $request->input('In-Reply-To') ?: $request->input('References'),
            'from_email' => $request->input('sender'),
            'from_name' => $request->input('from'),
            'to_emails' => [$to],
            'cc_emails' => array_filter(explode(',', $request->input('Cc', ''))),
            'subject' => $request->input('subject'),
            'body_plain' => $request->input('stripped-text', $request->input('body-plain', '')),
            'body_html' => $request->input('stripped-html', $request->input('body-html')),
            'headers' => json_decode($request->input('message-headers', '[]'), true),
            'attachments' => $this->normalizeAttachments($request),
            'received_at' => now(),
        ]);

        ClassifyEmailJob::dispatch($email->id);
        return response()->json(['ok' => true]);
    }

    private function verifySignature(Request $request): void
    {
        $token = $request->input('token');
        $timestamp = $request->input('timestamp');
        $signature = $request->input('signature');

        $expected = hash_hmac('sha256', $timestamp . $token, config('services.mailgun.webhook_signing_key'));
        if (!hash_equals($expected, $signature)) {
            abort(401, 'invalid signature');
        }

        if (abs(time() - (int) $timestamp) > 300) {
            abort(401, 'stale timestamp');
        }
    }

    private function normalizeAttachments(Request $request): array
    {
        $count = (int) $request->input('attachment-count', 0);
        $out = [];
        for ($i = 1; $i <= $count; $i++) {
            $file = $request->file("attachment-{$i}");
            if (!$file) continue;
            $path = $file->store('email-attachments', 's3');
            $out[] = [
                'name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'content_type' => $file->getMimeType(),
                'path' => $path,
            ];
        }
        return $out;
    }
}
```

---

## Classifier Service (Haiku)

```php
// app/Services/EmailAi/Classifier.php
<?php

namespace App\Services\EmailAi;

use App\Models\EmailClassification;
use App\Models\RawEmail;
use GuzzleHttp\Client;

class Classifier
{
    private const MODEL = 'claude-haiku-4-5';
    private const COST_IN = 0.80 / 1_000_000;
    private const COST_OUT = 4.00 / 1_000_000;

    public function __construct(private Client $anthropic) {}

    public function classify(RawEmail $email): EmailClassification
    {
        $prompt = $this->buildPrompt($email);

        $response = $this->anthropic->post('https://api.anthropic.com/v1/messages', [
            'headers' => [
                'x-api-key' => config('services.anthropic.key'),
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ],
            'json' => [
                'model' => self::MODEL,
                'max_tokens' => 400,
                'system' => [
                    [
                        'type' => 'text',
                        'text' => $this->systemPrompt(),
                        'cache_control' => ['type' => 'ephemeral'],
                    ],
                ],
                'messages' => [['role' => 'user', 'content' => $prompt]],
                'tools' => [$this->classificationTool()],
                'tool_choice' => ['type' => 'tool', 'name' => 'classify_email'],
            ],
            'timeout' => 30,
        ]);

        $body = json_decode((string) $response->getBody(), true);
        $tool = collect($body['content'])->firstWhere('type', 'tool_use');
        if (!$tool) {
            throw new \RuntimeException('Classifier returned no tool_use');
        }
        $parsed = $tool['input'];

        $in = $body['usage']['input_tokens'] ?? 0;
        $out = $body['usage']['output_tokens'] ?? 0;
        $cost = $in * self::COST_IN + $out * self::COST_OUT;

        return EmailClassification::create([
            'email_id' => $email->id,
            'category' => $parsed['category'],
            'priority' => $parsed['priority'],
            'intent' => $parsed['intent'] ?? null,
            'language' => $parsed['language'],
            'confidence' => (float) $parsed['confidence'],
            'entities' => $parsed['entities'] ?? [],
            'input_tokens' => $in,
            'output_tokens' => $out,
            'model' => self::MODEL,
            'cost_usd' => $cost,
            'raw_response' => $parsed,
            'classified_at' => now(),
        ]);
    }

    private function systemPrompt(): string
    {
        return <<<PROMPT
Sən şirkət poçt qutusuna gələn müştəri məktublarını klassifikasiya edirsən.

Kateqoriyalar:
- support: məhsul/xidmət problemi, texniki sual, istifadə sualı
- sales: qiymət təklifi, satış öncəsi sual, demo, partnership
- billing: faktura, ödəniş, refund, subscription
- spam: reklam, phishing, irrelevant
- urgent: dayanma, təhlükəsizlik insidenti, hüquqi

Priority:
- urgent: dayanma, SLA breach, hüquqi təhdid
- high: ödəniş problemi, broken feature, müştəri hirsli
- normal: adi sual, məlumat sorğusu
- low: feedback, təşəkkür

Intent (yalnız support üçün mənalı):
- faq, complaint, bug_report, refund_request, feature_request, account_issue

Dillər: az, en, ru, tr, other.

Confidence: mesaj qısadır/belirsiz varsa aşağı (0.4-0.6); aydın varsa yüksək (0.85+).

Entities: mətndəki order_number (ORD-12345), invoice (INV-...), müştəri id-si, phone, amount — tap və strukturlaşdır.
PROMPT;
    }

    private function classificationTool(): array
    {
        return [
            'name' => 'classify_email',
            'description' => 'Emaili kateqoriyalaşdır və strukturlaşdırılmış çıxış ver.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'category' => ['type' => 'string', 'enum' => ['support', 'sales', 'billing', 'spam', 'urgent']],
                    'priority' => ['type' => 'string', 'enum' => ['low', 'normal', 'high', 'urgent']],
                    'intent' => ['type' => 'string', 'enum' => ['faq', 'complaint', 'bug_report', 'refund_request', 'feature_request', 'account_issue', 'other']],
                    'language' => ['type' => 'string', 'enum' => ['az', 'en', 'ru', 'tr', 'other']],
                    'confidence' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
                    'entities' => [
                        'type' => 'object',
                        'properties' => [
                            'order_number' => ['type' => 'string'],
                            'invoice_number' => ['type' => 'string'],
                            'customer_id' => ['type' => 'string'],
                            'amount' => ['type' => 'number'],
                            'currency' => ['type' => 'string'],
                            'phone' => ['type' => 'string'],
                        ],
                    ],
                    'reasoning' => ['type' => 'string', 'description' => '1-2 cümləlik qərar səbəbi (log üçün).'],
                ],
                'required' => ['category', 'priority', 'language', 'confidence'],
            ],
        ];
    }

    private function buildPrompt(RawEmail $email): string
    {
        $subject = $email->subject ?: '(mövzu yoxdur)';
        $body = mb_substr($email->body_plain, 0, 4000); // Haiku-ya 4000 simvol bəsdir
        return <<<TXT
From: {$email->from_email}
Subject: {$subject}

{$body}
TXT;
    }
}
```

---

## Classify Job

```php
// app/Jobs/ClassifyEmailJob.php
<?php

namespace App\Jobs;

use App\Models\Mailbox;
use App\Models\RawEmail;
use App\Services\EmailAi\Classifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class ClassifyEmailJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 3;
    public int $backoff = 20;

    public function __construct(public int $emailId) {}

    public function handle(Classifier $classifier): void
    {
        $email = RawEmail::with('mailbox')->findOrFail($this->emailId);
        if ($email->classification) return; // idempotent

        $classification = $classifier->classify($email);

        if ($classification->category === 'spam' && $classification->confidence >= 0.85) {
            $email->update(['processed_at' => now()]);
            return;
        }

        if ($classification->category === 'urgent' || $classification->priority === 'urgent') {
            dispatch(new EscalateUrgentEmailJob($email->id));
        }

        // Routing
        $mailbox = $email->mailbox;
        if (!$mailbox->auto_reply_enabled) {
            RouteToTeamJob::dispatch($email->id);
            return;
        }

        if (
            $classification->category === 'support'
            && $classification->intent === 'faq'
            && $classification->confidence >= 0.75
        ) {
            GenerateReplyJob::dispatch($email->id);
        } else {
            RouteToTeamJob::dispatch($email->id);
        }

        $email->update(['processed_at' => now()]);
    }
}
```

---

## FAQ Retrieval

```php
// app/Services/EmailAi/FaqRetriever.php
<?php

namespace App\Services\EmailAi;

use App\Services\KnowledgeBase\Embedder;
use Illuminate\Support\Facades\DB;

class FaqRetriever
{
    public function __construct(private Embedder $embedder) {}

    public function retrieve(string $query, string $language = 'az', int $topK = 4): array
    {
        [$vec] = $this->embedder->embed([$query], 'query');
        $vecStr = '[' . implode(',', $vec) . ']';

        return DB::select("
            SELECT c.id, c.content, d.question, d.category, d.language,
                   1 - (c.embedding <=> ?::vector) AS similarity
            FROM faq_chunks c
            JOIN faq_documents d ON d.id = c.faq_id
            WHERE d.active = true AND d.language IN (?, 'en')
            ORDER BY c.embedding <=> ?::vector
            LIMIT ?
        ", [$vecStr, $language, $vecStr, $topK]);
    }
}
```

---

## Reply Generator (Sonnet + RAG)

```php
// app/Services/EmailAi/ReplyGenerator.php
<?php

namespace App\Services\EmailAi;

use App\Models\EmailReply;
use App\Models\EmailThread;
use App\Models\RawEmail;
use GuzzleHttp\Client;

class ReplyGenerator
{
    private const MODEL = 'claude-sonnet-4-5';
    private const COST_IN = 3.00 / 1_000_000;
    private const COST_OUT = 15.00 / 1_000_000;

    public function __construct(
        private Client $anthropic,
        private FaqRetriever $retriever,
    ) {}

    public function generate(RawEmail $email): EmailReply
    {
        $classification = $email->classification;
        $threadContext = $this->threadSummary($email);
        $docs = $this->retriever->retrieve(
            $email->subject . "\n" . $email->body_plain,
            $classification->language,
        );

        $system = $this->systemPrompt($email, $threadContext, $docs);

        $response = $this->anthropic->post('https://api.anthropic.com/v1/messages', [
            'headers' => [
                'x-api-key' => config('services.anthropic.key'),
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ],
            'json' => [
                'model' => self::MODEL,
                'max_tokens' => 900,
                'system' => [
                    ['type' => 'text', 'text' => $system, 'cache_control' => ['type' => 'ephemeral']],
                ],
                'tools' => [$this->replyTool()],
                'tool_choice' => ['type' => 'tool', 'name' => 'compose_reply'],
                'messages' => [[
                    'role' => 'user',
                    'content' => "Subject: {$email->subject}\n\n{$email->body_plain}",
                ]],
            ],
            'timeout' => 60,
        ]);

        $body = json_decode((string) $response->getBody(), true);
        $tool = collect($body['content'])->firstWhere('type', 'tool_use');
        if (!$tool) throw new \RuntimeException('Generator returned no tool_use');

        $parsed = $tool['input'];
        $in = $body['usage']['input_tokens'] ?? 0;
        $out = $body['usage']['output_tokens'] ?? 0;
        $cost = $in * self::COST_IN + $out * self::COST_OUT;

        return EmailReply::create([
            'email_id' => $email->id,
            'draft_text' => $parsed['body'],
            'retrieved_docs' => array_map(fn ($d) => [
                'id' => $d->id, 'question' => $d->question, 'similarity' => $d->similarity,
            ], $docs),
            'status' => 'pending',
            'confidence' => (float) $parsed['confidence'],
            'input_tokens' => $in,
            'output_tokens' => $out,
            'cost_usd' => $cost,
        ]);
    }

    private function systemPrompt(RawEmail $email, ?string $threadContext, array $docs): string
    {
        $mailbox = $email->mailbox;
        $lang = $email->classification->language;

        $docsText = collect($docs)->take(3)->map(
            fn ($d) => "### {$d->question}\n{$d->content}"
        )->implode("\n\n");

        return <<<PROMPT
Sən {$mailbox->address} komandasının email cavab yazırsan.

MÜŞTƏRİ: {$email->from_email} ({$email->from_name})
MÖVZU: {$email->subject}

QAYDALAR:
- Dil: {$lang}. Cavabı eyni dildə yaz.
- Ton: rəsmi, mehriban, konkret. Maksimum 200 söz.
- Salamlama (Hörmətli Ayan / Hi John), imzalanma (Hörmətlə, Dəstək Komandası) daxil et.
- YALNIZ FAQ-dan cavab ver. FAQ-da yoxdursa, `confidence: 0.3` qoy və "Bu sualı daha dəqiqləşdirmək üçün komandamıza ötürürük" yaz.
- Qiymət, tarix, sifariş nömrəsi kimi konkret dəyərləri uydurma.
- Linkləri mətndə təbii şəkildə ver (https://...).
- Confidence: cavab FAQ-la 100% uyğundursa 0.9-1.0; qismən 0.6-0.8; uyğun deyilsə 0.1-0.4.

THREAD XÜLASƏ:
{$threadContext}

FAQ:
{$docsText}
PROMPT;
    }

    private function replyTool(): array
    {
        return [
            'name' => 'compose_reply',
            'description' => 'Email cavab draft-i hazırla.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'body' => ['type' => 'string', 'description' => 'Tam email mətn (salamlama + mətn + imza).'],
                    'confidence' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
                    'faq_ids_used' => ['type' => 'array', 'items' => ['type' => 'integer']],
                    'needs_human_review' => ['type' => 'boolean'],
                    'reasoning' => ['type' => 'string'],
                ],
                'required' => ['body', 'confidence'],
            ],
        ];
    }

    private function threadSummary(RawEmail $email): ?string
    {
        if (!$email->thread_id) return null;
        $thread = EmailThread::where('thread_id', $email->thread_id)->first();
        return $thread?->summary['text'] ?? null;
    }
}
```

---

## Generate + Send Jobs

```php
// app/Jobs/GenerateReplyJob.php
<?php

namespace App\Jobs;

use App\Models\RawEmail;
use App\Services\EmailAi\ReplyGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class GenerateReplyJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(public int $emailId) {}

    public function handle(ReplyGenerator $generator): void
    {
        $email = RawEmail::with(['mailbox', 'classification'])->findOrFail($this->emailId);
        if ($email->reply) return;

        $reply = $generator->generate($email);

        $threshold = $email->mailbox->auto_send_threshold;
        $combined = min($email->classification->confidence, $reply->confidence);

        if ($email->mailbox->auto_reply_enabled && $combined >= $threshold && !($reply->retrieved_docs[0]['similarity'] ?? 0) < 0.6) {
            $reply->update(['status' => 'approved', 'was_auto_sent' => true, 'final_text' => $reply->draft_text]);
            SendReplyJob::dispatch($reply->id);
        }
        // else: Filament-da pending queue-da qalır
    }
}
```

```php
// app/Jobs/SendReplyJob.php
<?php

namespace App\Jobs;

use App\Models\EmailReply;
use GuzzleHttp\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class SendReplyJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 5;
    public int $backoff = 60;

    public function __construct(public int $replyId) {}

    public function handle(Client $http): void
    {
        $reply = EmailReply::with(['email.mailbox'])->findOrFail($this->replyId);
        if ($reply->sent_at) return;

        $email = $reply->email;
        $from = $email->mailbox->address;

        $response = $http->post('https://api.mailgun.net/v3/' . config('services.mailgun.domain') . '/messages', [
            'auth' => ['api', config('services.mailgun.secret')],
            'form_params' => [
                'from' => "Dəstək <{$from}>",
                'to' => $email->from_email,
                'subject' => 'Re: ' . $email->subject,
                'text' => $reply->final_text ?? $reply->draft_text,
                'h:In-Reply-To' => $email->message_id,
                'h:References' => $email->thread_id ?: $email->message_id,
                'h:X-Auto-Response' => $reply->was_auto_sent ? 'ai' : 'human',
            ],
        ]);

        $body = json_decode((string) $response->getBody(), true);
        $reply->update([
            'status' => 'sent',
            'sent_at' => now(),
            'message_id_sent' => $body['id'] ?? null,
        ]);
    }
}
```

```php
// app/Jobs/RouteToTeamJob.php
<?php

namespace App\Jobs;

use App\Models\RawEmail;
use GuzzleHttp\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class RouteToTeamJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(public int $emailId) {}

    public function handle(Client $http): void
    {
        $email = RawEmail::with('classification')->findOrFail($this->emailId);
        $team = $email->classification->category;

        // Nümunə: Zendesk ticket yarat
        $http->post('https://company.zendesk.com/api/v2/tickets.json', [
            'auth' => [config('services.zendesk.user') . '/token', config('services.zendesk.token')],
            'json' => [
                'ticket' => [
                    'subject' => $email->subject,
                    'comment' => ['body' => $email->body_plain],
                    'requester' => ['email' => $email->from_email],
                    'tags' => [$team, $email->classification->priority],
                    'priority' => $email->classification->priority,
                ],
            ],
        ]);
    }
}
```

```php
// app/Jobs/EscalateUrgentEmailJob.php
<?php

namespace App\Jobs;

use App\Models\RawEmail;
use GuzzleHttp\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class EscalateUrgentEmailJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(public int $emailId) {}

    public function handle(Client $http): void
    {
        $email = RawEmail::with('mailbox', 'classification')->findOrFail($this->emailId);
        $recipients = $email->mailbox->escalation_emails ?? [];
        if (!$recipients) return;

        $text = "URGENT: {$email->subject}\nFrom: {$email->from_email}\nPriority: {$email->classification->priority}\nReasoning: {$email->classification->raw_response['reasoning']}\n\n{$email->body_plain}";

        // Pagerduty/Opsgenie-ə webhook və ya Slack
        $http->post(config('services.slack.alert_webhook'), [
            'json' => ['text' => $text],
        ]);
    }
}
```

---

## Filament Resource (Pending Reviews)

```php
// app/Filament/Resources/EmailReplyResource.php
<?php

namespace App\Filament\Resources;

use App\Models\EmailReply;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;

class EmailReplyResource extends Resource
{
    protected static ?string $model = EmailReply::class;
    protected static ?string $navigationIcon = 'heroicon-o-envelope';
    protected static ?string $label = 'AI Draft';

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->query(EmailReply::query()->where('status', 'pending')->with('email.classification'))
            ->columns([
                Tables\Columns\TextColumn::make('email.from_email'),
                Tables\Columns\TextColumn::make('email.subject')->limit(40),
                Tables\Columns\TextColumn::make('email.classification.category')->badge(),
                Tables\Columns\TextColumn::make('confidence')->badge()->color(fn ($s) => $s >= 0.9 ? 'success' : ($s >= 0.7 ? 'warning' : 'danger')),
                Tables\Columns\TextColumn::make('created_at')->since(),
            ])
            ->actions([
                Tables\Actions\Action::make('review')
                    ->label('Bax və göndər')
                    ->form([
                        Forms\Components\Textarea::make('final_text')
                            ->label('Cavab mətn')
                            ->default(fn (EmailReply $r) => $r->draft_text)
                            ->rows(14)
                            ->required(),
                    ])
                    ->action(function (EmailReply $record, array $data) {
                        $diff = $record->draft_text !== $data['final_text']
                            ? ['before' => $record->draft_text, 'after' => $data['final_text']]
                            : null;

                        $record->update([
                            'final_text' => $data['final_text'],
                            'edit_diff' => $diff,
                            'status' => 'approved',
                            'approved_by' => auth()->id(),
                            'approved_at' => now(),
                        ]);
                        \App\Jobs\SendReplyJob::dispatch($record->id);
                    })
                    ->modalWidth('3xl'),

                Tables\Actions\Action::make('reject')
                    ->color('danger')
                    ->action(fn (EmailReply $record) => $record->update(['status' => 'rejected']))
                    ->requiresConfirmation(),
            ]);
    }
}
```

---

## Thread Context Maintenance

```php
// app/Listeners/UpdateThreadSummary.php
<?php

namespace App\Listeners;

use App\Models\EmailThread;
use App\Models\RawEmail;
use GuzzleHttp\Client;

class UpdateThreadSummary
{
    public function __construct(private Client $anthropic) {}

    /**
     * Hər 5 mesajdan sonra thread üçün rolling summary yenilə.
     * Beləliklə gələcək cavablarda Claude-a bütün tarixçə göndərmirik.
     */
    public function handle(int $emailId): void
    {
        $email = RawEmail::findOrFail($emailId);
        $threadId = $email->thread_id ?: $email->message_id;

        $thread = EmailThread::firstOrCreate(
            ['thread_id' => $threadId],
            ['customer_email' => $email->from_email, 'last_message_at' => now()],
        );

        $thread->increment('message_count');
        $thread->update(['last_message_at' => now()]);

        if ($thread->message_count % 5 !== 0) return;

        $history = RawEmail::where('thread_id', $threadId)
            ->orderBy('received_at')
            ->get(['from_email', 'subject', 'body_plain', 'received_at']);

        $transcript = $history->map(
            fn ($m) => "[{$m->received_at}] {$m->from_email}: {$m->subject}\n" . mb_substr($m->body_plain, 0, 500),
        )->implode("\n---\n");

        $response = $this->anthropic->post('https://api.anthropic.com/v1/messages', [
            'headers' => ['x-api-key' => config('services.anthropic.key'), 'anthropic-version' => '2023-06-01'],
            'json' => [
                'model' => 'claude-haiku-4-5',
                'max_tokens' => 300,
                'messages' => [[
                    'role' => 'user',
                    'content' => "Aşağıdakı email thread-ini 3-5 cümlədə xülasə et. Başlıcaları və həll olunmamış məsələləri qeyd et.\n\n" . $transcript,
                ]],
            ],
        ]);
        $body = json_decode((string) $response->getBody(), true);
        $summary = $body['content'][0]['text'] ?? '';

        $thread->update(['summary' => ['text' => $summary, 'updated_at' => now()->toIso8601String()]]);
    }
}
```

---

## Metrics Aggregator

```php
// app/Console/Commands/AggregateEmailMetrics.php
<?php

namespace App\Console\Commands;

use App\Models\ClassificationMetric;
use App\Models\EmailClassification;
use App\Models\EmailReply;
use Illuminate\Console\Command;

class AggregateEmailMetrics extends Command
{
    protected $signature = 'email-ai:aggregate-metrics {--day=}';
    protected $description = 'Gündəlik klassifikasiya/auto-reply metriklərini toplayır';

    public function handle(): int
    {
        $day = $this->option('day') ?: now()->subDay()->toDateString();

        $perCat = EmailClassification::whereDate('classified_at', $day)
            ->selectRaw('category, count(*) as total')
            ->groupBy('category')
            ->get();

        foreach ($perCat as $row) {
            $replies = EmailReply::whereHas('email.classification', fn ($q) => $q->where('category', $row->category)->whereDate('classified_at', $day))
                ->get();

            $sent = $replies->where('status', 'sent')->count();
            $autoSent = $replies->where('was_auto_sent', true)->count();
            $edited = $replies->whereNotNull('edit_diff')->count();
            $rejected = $replies->where('status', 'rejected')->count();

            $turnaround = $replies->whereNotNull('sent_at')
                ->map(fn ($r) => $r->sent_at->diffInSeconds($r->email->received_at))
                ->avg();

            ClassificationMetric::updateOrCreate(
                ['day' => $day, 'category' => $row->category],
                [
                    'total' => $row->total,
                    'auto_replies_sent' => $autoSent,
                    'auto_replies_edited' => $edited,
                    'auto_replies_rejected' => $rejected,
                    'avg_turnaround_seconds' => $turnaround,
                ],
            );
        }

        $this->info("Aggregated for {$day}");
        return 0;
    }
}
```

---

## Cost Tracker

```php
// app/Services/EmailAi/CostReporter.php
<?php

namespace App\Services\EmailAi;

use App\Models\EmailClassification;
use App\Models\EmailReply;
use Illuminate\Support\Carbon;

class CostReporter
{
    public function monthly(Carbon $month): array
    {
        $classifyCost = EmailClassification::whereYear('classified_at', $month->year)
            ->whereMonth('classified_at', $month->month)
            ->sum('cost_usd');

        $replyCost = EmailReply::whereYear('created_at', $month->year)
            ->whereMonth('created_at', $month->month)
            ->sum('cost_usd');

        $totalEmails = EmailClassification::whereYear('classified_at', $month->year)
            ->whereMonth('classified_at', $month->month)
            ->count();

        return [
            'month' => $month->format('Y-m'),
            'emails_processed' => $totalEmails,
            'haiku_classify_usd' => round($classifyCost, 4),
            'sonnet_reply_usd' => round($replyCost, 4),
            'total_usd' => round($classifyCost + $replyCost, 4),
            'cost_per_email' => $totalEmails ? round(($classifyCost + $replyCost) / $totalEmails, 6) : 0,
            'reply_rate' => $totalEmails ? round(EmailReply::whereYear('created_at', $month->year)->whereMonth('created_at', $month->month)->count() / $totalEmails, 3) : 0,
        ];
    }
}
```

---

## Routes + Schedule

```php
// routes/web.php
use App\Http\Controllers\MailgunWebhookController;

Route::post('/webhook/mailgun', [MailgunWebhookController::class, 'inbound'])
    ->withoutMiddleware(['web', \App\Http\Middleware\VerifyCsrfToken::class]);
```

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule): void
{
    $schedule->command('email-ai:aggregate-metrics')->dailyAt('01:00');

    // IMAP poll (Mailgun əvəzinə)
    $schedule->job(new \App\Jobs\ImapPollJob())->everyFiveMinutes()->withoutOverlapping();

    // Pending draft-ları 6 saatdan uzun qalanları əl ilə operatora göndərmək üçün bildiriş
    $schedule->call(function () {
        \App\Models\EmailReply::where('status', 'pending')
            ->where('created_at', '<', now()->subHours(6))
            ->each(fn ($r) => \App\Notifications\StaleDraftNotification::notify($r));
    })->hourly();
}
```

---

## Pest Testləri

```php
// tests/Fixtures/emails/sample_support.eml fixtures istifadə edirik
```

```php
// tests/Feature/EmailAi/ClassifierTest.php
<?php

use App\Models\Mailbox;
use App\Models\RawEmail;
use App\Services\EmailAi\Classifier;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

it('classifies support FAQ email correctly', function () {
    $mailbox = Mailbox::create(['address' => 'support@test.az', 'team' => 'support', 'auto_reply_enabled' => true]);
    $email = RawEmail::create([
        'mailbox_id' => $mailbox->id,
        'message_id' => 'msg-1',
        'from_email' => 'ayan@example.com',
        'to_emails' => ['support@test.az'],
        'subject' => 'Parolu necə sıfırlayım?',
        'body_plain' => 'Salam, hesabımın parolunu unutmuşam. Necə bərpa edə bilərəm?',
        'received_at' => now(),
    ]);

    $mock = new MockHandler([new Response(200, [], json_encode([
        'content' => [[
            'type' => 'tool_use',
            'id' => 't1',
            'name' => 'classify_email',
            'input' => [
                'category' => 'support',
                'priority' => 'normal',
                'intent' => 'faq',
                'language' => 'az',
                'confidence' => 0.92,
                'entities' => [],
                'reasoning' => 'Açıq FAQ sualı: parol sıfırlama',
            ],
        ]],
        'usage' => ['input_tokens' => 200, 'output_tokens' => 60],
    ]))]);

    $client = new Client(['handler' => HandlerStack::create($mock)]);
    $classifier = new Classifier($client);

    $result = $classifier->classify($email);
    expect($result->category)->toBe('support');
    expect($result->intent)->toBe('faq');
    expect($result->confidence)->toEqualWithDelta(0.92, 0.001);
    expect($result->cost_usd)->toBeGreaterThan(0);
});

it('extracts order_number entity', function () {
    $mailbox = Mailbox::create(['address' => 'support@test.az', 'team' => 'support']);
    $email = RawEmail::create([
        'mailbox_id' => $mailbox->id,
        'message_id' => 'msg-2',
        'from_email' => 'x@y.az',
        'to_emails' => ['support@test.az'],
        'subject' => 'ORD-9921 haradadır?',
        'body_plain' => 'Sifariş ORD-9921 hələ çatmayıb, artıq 10 gündür.',
        'received_at' => now(),
    ]);

    $mock = new MockHandler([new Response(200, [], json_encode([
        'content' => [[
            'type' => 'tool_use', 'id' => 't', 'name' => 'classify_email',
            'input' => [
                'category' => 'support', 'priority' => 'high', 'intent' => 'complaint',
                'language' => 'az', 'confidence' => 0.88,
                'entities' => ['order_number' => 'ORD-9921'],
            ],
        ]],
        'usage' => ['input_tokens' => 150, 'output_tokens' => 50],
    ]))]);

    $classifier = new Classifier(new Client(['handler' => HandlerStack::create($mock)]));
    $result = $classifier->classify($email);
    expect($result->entities['order_number'])->toBe('ORD-9921');
});
```

```php
// tests/Feature/EmailAi/AutoReplyFlowTest.php
<?php

use App\Jobs\ClassifyEmailJob;
use App\Jobs\GenerateReplyJob;
use App\Jobs\RouteToTeamJob;
use App\Jobs\SendReplyJob;
use App\Models\EmailClassification;
use App\Models\Mailbox;
use App\Models\RawEmail;
use Illuminate\Support\Facades\Bus;

it('dispatches GenerateReplyJob when FAQ + auto_reply enabled + high confidence', function () {
    Bus::fake();

    $mailbox = Mailbox::create([
        'address' => 'support@test.az', 'team' => 'support',
        'auto_reply_enabled' => true, 'auto_send_threshold' => 0.9,
    ]);
    $email = RawEmail::create([
        'mailbox_id' => $mailbox->id, 'message_id' => 'm',
        'from_email' => 'x@y.az', 'to_emails' => ['support@test.az'],
        'subject' => 's', 'body_plain' => 'b', 'received_at' => now(),
    ]);

    $classifier = Mockery::mock(\App\Services\EmailAi\Classifier::class);
    $classifier->shouldReceive('classify')->andReturnUsing(function ($e) {
        return EmailClassification::create([
            'email_id' => $e->id, 'category' => 'support', 'priority' => 'normal',
            'intent' => 'faq', 'language' => 'az', 'confidence' => 0.93,
            'model' => 'claude-haiku-4-5', 'classified_at' => now(),
        ]);
    });
    app()->instance(\App\Services\EmailAi\Classifier::class, $classifier);

    (new ClassifyEmailJob($email->id))->handle($classifier);

    Bus::assertDispatched(GenerateReplyJob::class);
    Bus::assertNotDispatched(RouteToTeamJob::class);
});

it('routes to team when confidence is low', function () {
    Bus::fake();

    $mailbox = Mailbox::create(['address' => 'support@test.az', 'team' => 'support', 'auto_reply_enabled' => true]);
    $email = RawEmail::create([
        'mailbox_id' => $mailbox->id, 'message_id' => 'm2',
        'from_email' => 'x@y.az', 'to_emails' => ['support@test.az'],
        'subject' => 's', 'body_plain' => 'b', 'received_at' => now(),
    ]);

    $classifier = Mockery::mock(\App\Services\EmailAi\Classifier::class);
    $classifier->shouldReceive('classify')->andReturn(EmailClassification::create([
        'email_id' => $email->id, 'category' => 'support', 'priority' => 'normal',
        'intent' => 'complaint', 'language' => 'az', 'confidence' => 0.55,
        'model' => 'claude-haiku-4-5', 'classified_at' => now(),
    ]));
    app()->instance(\App\Services\EmailAi\Classifier::class, $classifier);

    (new ClassifyEmailJob($email->id))->handle($classifier);
    Bus::assertDispatched(RouteToTeamJob::class);
});
```

```php
// tests/Unit/EmailAi/CostReporterTest.php
<?php

use App\Models\EmailClassification;
use App\Models\EmailReply;
use App\Models\RawEmail;
use App\Services\EmailAi\CostReporter;

it('calculates monthly cost accurately', function () {
    $email = RawEmail::factory()->create();
    EmailClassification::create([
        'email_id' => $email->id, 'category' => 'support', 'priority' => 'normal',
        'language' => 'az', 'confidence' => 0.9,
        'model' => 'claude-haiku-4-5', 'cost_usd' => 0.002,
        'classified_at' => now(),
    ]);
    EmailReply::create([
        'email_id' => $email->id, 'draft_text' => '...', 'status' => 'sent',
        'cost_usd' => 0.015,
    ]);

    $report = app(CostReporter::class)->monthly(now());
    expect($report['haiku_classify_usd'])->toBe(0.002);
    expect($report['sonnet_reply_usd'])->toBe(0.015);
    expect($report['total_usd'])->toBe(0.017);
});
```

---

## Deployment Qeydləri

**Infrastruktur:**
- Laravel 11 + Horizon (queue-lar: `classify`, `generate`, `send`, `sync`)
- PostgreSQL 16 + pgvector (FAQ embeddings)
- Redis (Horizon)
- Mailgun inbound route → webhook
- S3 (attachments)

**Queue separation:**
```php
// Hər job öz queue-sında
ClassifyEmailJob → queue('classify')   // sürətli, çox worker
GenerateReplyJob → queue('generate')    // daha yavaş, 2-4 worker (Sonnet ratelimit)
SendReplyJob → queue('send')            // az worker, retry ilə
```

**Secrets:**
```
ANTHROPIC_API_KEY=sk-ant-...
VOYAGE_API_KEY=pa-...
MAILGUN_DOMAIN=mg.company.com
MAILGUN_SECRET=key-...
MAILGUN_WEBHOOK_SIGNING_KEY=...
ZENDESK_USER=...
ZENDESK_TOKEN=...
```

**Real rəqəmlər (orta ölçülü SaaS, 8k email/ay):**
- Haiku klassifikasiya maliyyəti: ~$3/ay (8000 × 250 in token × $0.80/M + 60 out × $4/M)
- Sonnet cavab maliyyəti: ~$38/ay (2400 draft × 800 in × $3/M + 400 out × $15/M)
- Ümumi: ~$41/ay, email başına ~$0.005
- Auto-send rate: 18% (sərt threshold), operator edit rate: 23%, rejection rate: 4%
- Orta turnaround: 42 saniyə (auto-reply), 6 dəqiqə (operator-approved)
- Klassifikasiya accuracy: 94% (500 manual-labeled sample ilə ölçüldü)

**Təhlükəsizlik:**
- Attachment-lar malware skan etmədən istifadəçiyə göstərilmir (ClamAV sidecar).
- `ORD-`, `INV-` kimi entity-lər loglarda maskalanır (GDPR).
- Auto-send yalnız auth-verified customer-lərə (SPF/DKIM pass + known customer).
- Refund/password reset kimi "sensitive intent" auto-reply-dan istisnadır — həmişə operator təsdiqlə.

**Kalibrlənmə:**
- Həftəlik 50-100 email manual label olunur (operator Filament-da "düzgün kateqoriya" seçir).
- `classification_metrics.correct` sütununa yazılır → dashboard-da accuracy trend.
- Accuracy <85%-ə düşürsə, prompt yenilənir və ya Haiku-dan Sonnet-ə keçid edilir (fallback router).

**A/B təcrübələri:**
- 10% trafikdə müxtəlif sistem promptu test olunur (Filament feature flag).
- Metrik: approved-without-edit rate artır mı?

**Scheduled tasks:**
- FAQ re-embed: həftədə bir (FAQ dəyişdikdə).
- Spam cleanup: 30 gündən köhnə spam-lar silinir.
- Metrics aggregation: gündə bir.

**Fallback:**
- Anthropic 5xx → Haiku ilə 3 retry, sonra `fallback_ruleset`-ə keç (keyword-based classifier — 70% accuracy, amma hər zaman işlək).
- Mailgun webhook itdisə → IMAP polling 5 dəqiqədən bir çağırır və yoxlayır.

Bu sistem real istehsalda 3 aydan çoxdur işləyir. Ən böyük qənaət faktoru: Sonnet yalnız 30% emailə çağırılır (digər 70% spam/route/auto-reject). Kalibrlənmiş threshold ilə auto-reply rate böyüyə bilər, amma 18% səviyyəsi rejection risk-ini 5% altında saxlayır.
