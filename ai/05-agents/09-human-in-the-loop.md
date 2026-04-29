# Human-in-the-Loop Agent-lar: Approval Gates, Async Handoff və Escalation (Lead)

> **Oxucu:** Senior PHP/Laravel tərtibatçılar, production agent sistemlərində safety quran arxitektlər
> **Ön şərtlər:** Agent dövrü (24), multi-agent (25), memory (27), Claude Agent SDK (31), tool dizayn (32), reasoning (33), orchestration (34), Laravel queues, Filament, Slack webhook
> **Diff vs 24-34:** 24-34 agent-in avtonom işləməsi haqqındadır. Bu fayl isə **agent-i dayandırıb insan qərarını gözləyən** pattern-ləri detallı açır — approval gate-lər, async resume, escalation, state persistence. Production-da agent nə qədər güclü olsa da, pul, e-poçt, DB write, contract imza kimi kritik action-lar insan gözündən keçməlidir. Bu fayl həmin infrastrukturu qurur.
> **Tarix:** 2026-04-24

---

## Mündəricat

1. Niyə HITL — agent-ə tam etibar etmə
2. Approval gate pattern-ləri
3. Synchronous vs asynchronous approval
4. Tool-level approval
5. Threshold-based HITL (confidence, cost, impact)
6. Escalation pattern-ləri
7. State machine — paused agent-in anatomy-si
8. Paused agent-i resume etmək — tam state serialization
9. Timeout handling — approval gəlmədi
10. UI pattern-ləri — Slack, email, web diff view
11. Audit trail — kim, nə vaxt, niyə
12. Laravel implementasiya — `AgentSession` model + pending_action
13. Queue job ilə resume
14. Slack approval flow
15. Filament admin panel — pending approvals inbox
16. Safety — signed URL, replay protection, expiration
17. HITL NƏ vaxt istifadə olunmamalı
18. Metrikalar — approval rate, time-to-approve, accuracy
19. UX — reviewer fatigue və prioritization
20. Legal/compliance — SOC2, HIPAA, PCI, KYC

---

## 1. Niyə HITL

Agent istənilən tool-u çağıra bilir. Bəs `delete_production_database` tool-unu da? `transfer_funds` tool-unu da? `send_mass_email` tool-unu da?

Production-da bu cür aksiyalar üçün agent tək qərarını etməməlidir. Səbəblər:

- **Yüksək dəyərli aksiyalar** — pul transferi, refund, invoice silmə.
- **Geri qaytarılmayan aksiyalar** — müştəriyə göndərilən e-poçt, DELETE statement.
- **Compliance** — SOX, HIPAA, PCI, GDPR. Bəzi aksiyalar audit trail + insan imzası tələb edir.
- **Model hallucination** — agent "Bu müştəri refund tələb etdi" düşünür, amma əslində müştəri başqa şey yazmışdı.
- **Social engineering** — attacker agent-i aldada bilir (prompt injection → "refund mənə 10k$").

HITL = Human-in-the-Loop = **agent dayanır, insan görür, insan təsdiqləyir (ya rədd edir), agent davam edir**.

### Risk matrisi

```
                    Geri qaytarıla bilən                    Geri qaytarıla bilməz
                    ──────────────────────────              ──────────────────────
 Aşağı təsir  │     Agent özü icra edir              │    Agent özü + audit log      │
              │     (search, read, compute)          │    (draft email to self)      │
              ├──────────────────────────────────────┼───────────────────────────────┤
 Orta təsir   │     Confirm-before-act (sync HITL)   │    Async approval             │
              │     (update note)                    │    (contract draft)           │
              ├──────────────────────────────────────┼───────────────────────────────┤
 Yüksək təsir │     Async approval                   │    Multi-approver + audit     │
              │     (refund under $X)                │    (money transfer, DELETE)   │
              └──────────────────────────────────────┴───────────────────────────────┘
```

---

## 2. Approval gate pattern-ləri

Approval gate = agent-in müəyyən nöqtəsində insan qərarını gözləməsi.

### Klassik axın

```
┌──────────────────────────────────────────────────────────────┐
│  User: "Bu müştəriyə 500 AZN refund et"                      │
└──────────────────────────────────────────────────────────────┘
                       │
                       ▼
┌──────────────────────────────────────────────────────────────┐
│  Agent düşünür: tool_use = process_refund(amount=500)         │
│  Sistem gate-i tetikleyir: approval_required                  │
└──────────────────────────────────────────────────────────────┘
                       │
                       ▼
┌──────────────────────────────────────────────────────────────┐
│  Agent state PAUSED olur                                     │
│  Slack mesajı gedir: "Refund 500 AZN — təsdiqləyin/rədd"     │
│  Session DB-də saxlanır                                      │
└──────────────────────────────────────────────────────────────┘
                       │
                 (insan gözləyir)
                       │
                       ▼
┌──────────────────────────────────────────────────────────────┐
│  Manager "Təsdiqlə" basır                                    │
│  Webhook → Laravel endpoint                                  │
│  Queue job tetiklenir: ResumeAgentJob                        │
└──────────────────────────────────────────────────────────────┘
                       │
                       ▼
┌──────────────────────────────────────────────────────────────┐
│  Agent DB-dən state-i oxuyur                                 │
│  tool_result = process_refund() icra olunur                  │
│  Agent davam edir                                            │
└──────────────────────────────────────────────────────────────┘
```

---

## 3. Synchronous vs asynchronous approval

### Synchronous

User HTTP request göndərir, agent işləyir, approval lazım olur, **browser modal** çıxır, user basır, agent davam edir.

```
Browser               Backend             Agent
   │                     │                  │
   │───user prompt──────▶│──────────────────▶│ (işləyir)
   │                     │                  │
   │                     │◄──approval req───│
   │◄─modal open─────────│                  │
   │                     │                  │
   │──user clicks OK────▶│──approval: yes──▶│
   │                     │                  │ (davam)
   │◄──final response────│◄─────────────────│
```

Yalnız user özü approver-dirsə işləyir (self-service confirm). "`delete_my_post` edirsən? Bəli/Xeyr".

### Asynchronous

Approver **user deyil** — başqa insan (manager, compliance officer). Browser bağlana bilər, approval saatlar sonra gələ bilər.

```
User           Backend         Agent        Manager
  │              │                │           │
  │─prompt─────▶│────────────────▶│           │
  │             │                 │           │
  │             │◄──approval_req──│           │
  │◄─"biz üçün  │────────slack────────────────▶│
  │ Yoxladıq"───│                 │           │
                │                 │           │
                │                 │           │ (1 saat sonra)
                │                 │           │
                │◄─webhook approve──────────── │
                │                 │           │
                │──resume────────▶│           │
                │                 │ (davam)   │
                │◄─result─────────│           │
                ▼                             ▼
            email user                    audit log
```

Production-da **90% hallar async-dir**. User bir şey istəyir, agent "yoxlanır, yaxın zamanda xəbər verərik" cavabı qaytarır, sonra email/push göndərilir.

---

## 4. Tool-level approval

Hər tool üçün `requires_approval` flag-i.

```php
class ProcessRefundTool extends Tool
{
    public bool $requiresApproval = true;
    public string $approvalReason = 'Refund process changes customer balance';

    public function execute(array $input): mixed
    {
        // ...
    }
}
```

Agent loop daxilində:

```php
foreach ($toolCalls as $call) {
    $tool = $this->registry->get($call->name);
    
    if ($tool->requiresApproval) {
        // Agent-i pause et
        $this->requestApproval($session, $call);
        throw new AgentPaused($session->id);  // loop-u dayandır
    }
    
    $result = $tool->execute($call->input);
    // ...
}
```

### Tool approval matrix nümunəsi

| Tool | Requires approval | Səbəb |
|------|-------------------|-------|
| `search_customer` | Xeyr | Read-only |
| `get_invoice` | Xeyr | Read-only |
| `update_note` | Xeyr | Aşağı təsir, reversible |
| `send_email_to_customer` | Bəli (async) | Geri qaytarıla bilməz |
| `process_refund` | Bəli (async, 2 approver amount > 1k) | Pul, compliance |
| `delete_user` | Bəli (async, legal approve) | GDPR, geri qaytarıla bilməz |
| `run_sql` | Şərtlə (oxu OK, yaz — bəli) | Data integrity |

---

## 5. Threshold-based HITL

Hər tool "həmişə approval" demir. Bəzən şərt əsasında:

### Confidence threshold

Agent model confidence skoru aşağı olanda escalate et.

```php
if ($agent->confidence < 0.7) {
    $this->escalateToHuman($session, reason: 'low_confidence');
}
```

Müşahidə: Claude `extended_thinking` və `stop_reason` verir, amma direct confidence skoru vermir. Workaround: model-ə "confidence 0-1 arası qaytar" deyirsən, və ya log-perplexity proxy-dən istifadə edirsən.

### Cost threshold

```php
if ($action->estimatedCost > 1000_00 /* cents */) {
    $this->requireApproval($session, $action);
}
```

Bank API-lərdə klassikdir: < $100 avtomatik, $100-$1000 manager, > $1000 CFO.

### Impact threshold

Refund miqdarı, e-poçt alıcı sayı, DB row sayı.

```php
match (true) {
    $action->affects_rows > 1000 => $this->requireApproval($session, $action, level: 'senior'),
    $action->affects_rows > 100  => $this->requireApproval($session, $action, level: 'manager'),
    default => $action->execute(),
};
```

### Rate limit threshold

Agent son saatda çox hərəkət edibsə, dayandır.

```php
$recentActions = $session->actions()->where('created_at', '>', now()->subHour())->count();
if ($recentActions > 20) {
    $this->pauseAndNotify($session, 'rate_limit');
}
```

---

## 6. Escalation pattern-ləri

Escalation = agent tam olmadıqda insanın müdaxiləsini tələb etmək.

### Trigger-lər

1. **3 failed attempts** — Reflexion 3 dəfə uğursuz olsa.
2. **New topic** — router "bu playbook-a aid deyil" deyir.
3. **User explicit** — "Həqiqi insan istəyirəm".
4. **Timeout** — agent 5 dəqiqə içində dayanmırsa.
5. **Compliance trigger** — user "sikayət", "məhkəmə", "polis" kimi sözlər yazırsa.

### Escalation target

```
Agent failure
      │
      ▼
L1: Senior AI agent (yeni pattern)
      │
   uğursuz
      ▼
L2: Human operator (live chat)
      │
   yoxdursa (gecədir və s.)
      ▼
L3: Ticket queue + email notification
```

### Laravel implementasiya

```php
class EscalationHandler
{
    public function handle(AgentSession $session, string $reason): void
    {
        match ($reason) {
            'failed_3_attempts' => $this->toSeniorAgent($session),
            'new_topic'         => $this->toHumanOperator($session),
            'user_request'      => $this->toHumanOperator($session, priority: 'high'),
            'compliance_trigger'=> $this->toComplianceQueue($session),
            default             => $this->toTicketQueue($session),
        };

        event(new AgentEscalated($session, $reason));
    }
}
```

---

## 7. State machine — paused agent-in anatomy-si

Agent session-ı 6 state-li FSM-dir:

```
                    ┌─────────┐
                    │ PENDING │  (yaradıldı, hələ start olmayıb)
                    └────┬────┘
                         │ start
                         ▼
      ┌─────────────▶┌──────────┐
      │              │  ACTIVE  │  (agent işləyir)
      │              └────┬─────┘
      │                   │
      │      ┌────────────┼──────────────┐
      │      │            │              │
      │   approval      done          error
      │   required        │              │
      │      │            ▼              ▼
      │      ▼         ┌──────┐      ┌───────┐
      │ ┌──────────┐   │ DONE │      │ FAILED│
      │ │AWAITING_ │   └──────┘      └───────┘
      │ │APPROVAL  │       │              │
      │ └────┬─────┘       └─── terminal ─┘
      │      │
      │      │ approved        rejected         timeout
      │      ├─────────────┬────────────┬────────────┐
      │      │             │            │            │
      └──────┘         ┌────▼───┐  ┌────▼──────┐     │
                       │REJECTED│  │ ESCALATED │     │
                       └────────┘  └───────────┘     │
                                                     │
                                                     ▼
                                                 ┌────────┐
                                                 │ EXPIRED│
                                                 └────────┘
```

### State-lər

| State | Mənası |
|-------|--------|
| PENDING | Session yaradıldı, agent hələ başlamayıb |
| ACTIVE | Agent loop-u icra edir |
| AWAITING_APPROVAL | Bir tool call üçün insan qərarını gözləyir |
| DONE | Uğurla bitdi |
| REJECTED | Approver rədd etdi, session dayandı |
| ESCALATED | İnsan operatora ötürüldü |
| FAILED | Texniki xəta |
| EXPIRED | Timeout, approval gəlmədi |

### Laravel enum

```php
enum AgentSessionStatus: string
{
    case Pending          = 'pending';
    case Active           = 'active';
    case AwaitingApproval = 'awaiting_approval';
    case Done             = 'done';
    case Rejected         = 'rejected';
    case Escalated        = 'escalated';
    case Failed           = 'failed';
    case Expired          = 'expired';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Done, self::Rejected, self::Escalated, self::Failed, self::Expired]);
    }
}
```

---

## 8. Paused agent-i resume etmək

Bu HITL-in ən çətin texniki tərəfidir. Agent-i "dondurmaq" və sonra davam etdirmək üçün **tam state-i serialize etmək** lazımdır.

### Serialize olunası şeylər

1. **Message history** — hər user/assistant/tool mesajı.
2. **Tool use state** — pending tool call ID, tool input.
3. **Extended thinking blokları** — Claude 4.5-də thinking-i saxlamaq lazımdır, yoxsa model "amneziya" olur.
4. **Intermediate outputs** — agent-in ara nəticələri (blackboard).
5. **Iteration sayı** — max-a çatmamaq üçün.
6. **Tool registry identifierləri** — amma tool kod-u deyil (kod dəyişə bilər).
7. **System prompt hash** — dəyişibsə qeyd et.
8. **Model və parametrlər** — model, temperature, max_tokens.

### JSON snapshot nümunəsi

```json
{
  "version": 2,
  "created_at": "2026-04-24T10:00:00Z",
  "model": "claude-sonnet-4-6",
  "system_prompt_hash": "sha256:abc123...",
  "iteration": 4,
  "max_iterations": 10,
  "messages": [
    {"role": "user", "content": [{"type": "text", "text": "500 AZN refund et"}]},
    {"role": "assistant", "content": [
      {"type": "thinking", "thinking": "Müştərinin invoice-ını yoxlamalıyam..."},
      {"type": "tool_use", "id": "toolu_1", "name": "lookup_invoice", "input": {"id": "INV-123"}}
    ]},
    {"role": "user", "content": [{"type": "tool_result", "tool_use_id": "toolu_1", "content": "..."}]},
    {"role": "assistant", "content": [
      {"type": "text", "text": "İnvoice tapıldı. 500 AZN refund edirəm."},
      {"type": "tool_use", "id": "toolu_2", "name": "process_refund", "input": {"amount": 500, "invoice_id": "INV-123"}}
    ]}
  ],
  "pending_tool_call": {
    "id": "toolu_2",
    "name": "process_refund",
    "input": {"amount": 500, "invoice_id": "INV-123"},
    "approval_status": "awaiting"
  },
  "blackboard": {
    "customer_id": 42,
    "original_invoice_total": 500
  }
}
```

### System prompt hash

System prompt dəyişibsə, agent-i resume etmək təhlükəlidir — məntiq dəyişib, agent davranışı fərqli olacaq. Hash save edib resume zamanı müqayisə et:

```php
if (hash('sha256', $currentPrompt) !== $session->system_prompt_hash) {
    throw new SessionPromptMismatch('System prompt dəyişib, session expire olundu');
}
```

---

## 9. Timeout handling

Approval 24 saat gəlmədi. Nə etmək?

### Strategiya seçimləri

1. **Auto-reject** — session rədd olunur.
2. **Auto-escalate** — başqa approver-ə keçir (manager → director).
3. **Auto-notify-and-expire** — istifadəçiyə "timeout" mesajı, session expire.
4. **Auto-approve (yalnız aşağı risk)** — aşağı-impactlı tool-larda.

### Implementasiya — scheduled job

```php
namespace App\Console\Commands;

class ExpireStaleApprovals extends Command
{
    protected $signature = 'agents:expire-approvals';
    
    public function handle(): void
    {
        $expired = AgentSession::query()
            ->where('status', AgentSessionStatus::AwaitingApproval)
            ->where('awaiting_since', '<', now()->subHours(24))
            ->get();
        
        foreach ($expired as $session) {
            $this->expireSession($session);
        }
    }
    
    protected function expireSession(AgentSession $session): void
    {
        $session->update(['status' => AgentSessionStatus::Expired]);
        event(new AgentSessionExpired($session));
        // istifadəçiyə notification
        Notification::send($session->user, new SessionExpiredNotification($session));
    }
}
```

Schedule:

```php
$schedule->command('agents:expire-approvals')->hourly();
```

---

## 10. UI pattern-ləri

### Slack button approval

```json
{
  "blocks": [
    {"type": "section", "text": {"type": "mrkdwn", "text": "*Refund təsdiqi*\nMüştəri: John Doe\nMiqdar: 500 AZN\nSəbəb: Duplicate charge"}},
    {"type": "section", "text": {"type": "mrkdwn", "text": "*Agent reasoning:*\nMüştəri invoice #INV-123 üçün iki dəfə ödəyib..."}},
    {"type": "actions", "elements": [
      {"type": "button", "text": {"type": "plain_text", "text": "Təsdiqlə"}, "style": "primary", "value": "approve_xxx"},
      {"type": "button", "text": {"type": "plain_text", "text": "Rədd et"}, "style": "danger", "value": "reject_xxx"}
    ]}
  ]
}
```

Slack `interactions_endpoint_url` POST edir, Laravel handle edir.

### Email one-click approve

Gmail `List-Unsubscribe`-ə oxşar bir-kliklə:

```
Subject: [Approval] Refund 500 AZN for INV-123

Agent 500 AZN refund etmək istəyir.

Reasoning: ...
Invoice details: ...

[Təsdiqlə](https://app.example.com/approve/abc?sig=xxx)
[Rədd et](https://app.example.com/reject/abc?sig=xxx)
[Detallara bax](https://app.example.com/session/abc)
```

Mühüm: link signed olmalıdır (signed URL, aşağıda).

### Web diff view

DB update-i təsdiqləmək üçün "before/after" göstər:

```
Before:
  customer.plan = "basic"
  customer.balance = 5000

After:
  customer.plan = "premium"
  customer.balance = 5000 - 2000 = 3000

[Təsdiqlə] [Rədd et] [Dəyiş]
```

Filament panel-də resource view-da render et.

---

## 11. Audit trail

Hər approval aksiyası qeydə alınmalıdır. SOC2, HIPAA, PCI-DSS hamısı bunu tələb edir.

### audit_logs cədvəli

```sql
CREATE TABLE agent_approval_audit (
    id BIGSERIAL PRIMARY KEY,
    session_id UUID NOT NULL,
    tool_name VARCHAR(128),
    tool_input JSONB,
    decision VARCHAR(16),          -- approved, rejected, expired, escalated
    decided_by_user_id BIGINT,     -- nullable (system decisions)
    decided_by_email VARCHAR(255),
    decided_at TIMESTAMPTZ,
    decision_reason TEXT,          -- optional manual note
    approval_method VARCHAR(32),   -- slack, email, web, api
    ip_address INET,
    user_agent TEXT,
    INDEX (session_id),
    INDEX (decided_by_user_id, decided_at)
);
```

### Eloquent model

```php
class AgentApprovalAudit extends Model
{
    protected $casts = [
        'tool_input' => 'array',
        'decided_at' => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(AgentSession::class);
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }
}
```

### Immutability

Audit log update və delete olunmamalı. Trigger ilə qoruyun:

```sql
CREATE OR REPLACE FUNCTION prevent_audit_modification()
RETURNS TRIGGER AS $$
BEGIN
    RAISE EXCEPTION 'audit logs dəyişdirilə bilməz';
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER audit_immutable
BEFORE UPDATE OR DELETE ON agent_approval_audit
FOR EACH ROW EXECUTE FUNCTION prevent_audit_modification();
```

---

## 12. Laravel implementasiya — `AgentSession` model

### Migration

```php
Schema::create('agent_sessions', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignId('user_id')->nullable()->constrained();
    $table->string('agent_name');
    $table->string('status');  // AgentSessionStatus
    $table->string('model');
    $table->string('system_prompt_hash', 64);
    $table->jsonb('state');              // tam snapshot
    $table->jsonb('pending_action')->nullable();
    $table->timestampTz('awaiting_since')->nullable();
    $table->string('pending_approver_email')->nullable();
    $table->timestampTz('approved_at')->nullable();
    $table->foreignId('approved_by_user_id')->nullable()->constrained('users');
    $table->integer('iteration')->default(0);
    $table->integer('total_cost_cents')->default(0);
    $table->timestamps();
    
    $table->index(['status', 'awaiting_since']);
    $table->index(['user_id', 'status']);
});
```

### Model

```php
<?php

namespace App\Models;

use App\Enums\AgentSessionStatus;
use Illuminate\Database\Eloquent\Model;

class AgentSession extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    protected $casts = [
        'state'           => 'array',
        'pending_action'  => 'array',
        'status'          => AgentSessionStatus::class,
        'awaiting_since'  => 'datetime',
        'approved_at'     => 'datetime',
    ];

    public function requestApproval(array $toolCall, ?string $approverEmail = null): void
    {
        $this->update([
            'status'                 => AgentSessionStatus::AwaitingApproval,
            'pending_action'         => $toolCall,
            'awaiting_since'         => now(),
            'pending_approver_email' => $approverEmail,
        ]);

        event(new AgentApprovalRequested($this));
    }

    public function approve(User $approver, ?string $note = null): void
    {
        DB::transaction(function () use ($approver, $note) {
            $pending = $this->pending_action;

            $this->update([
                'status'              => AgentSessionStatus::Active,
                'approved_at'         => now(),
                'approved_by_user_id' => $approver->id,
            ]);

            AgentApprovalAudit::create([
                'session_id'          => $this->id,
                'tool_name'           => $pending['name'] ?? null,
                'tool_input'          => $pending['input'] ?? [],
                'decision'            => 'approved',
                'decided_by_user_id'  => $approver->id,
                'decided_by_email'    => $approver->email,
                'decided_at'          => now(),
                'decision_reason'     => $note,
                'approval_method'     => request()->header('X-Approval-Method', 'web'),
                'ip_address'          => request()->ip(),
                'user_agent'          => request()->userAgent(),
            ]);

            dispatch(new ResumeAgentJob($this->id));
        });
    }

    public function reject(User $approver, string $reason): void
    {
        $pending = $this->pending_action;
        $this->update(['status' => AgentSessionStatus::Rejected]);

        AgentApprovalAudit::create([
            'session_id'         => $this->id,
            'tool_name'          => $pending['name'] ?? null,
            'tool_input'         => $pending['input'] ?? [],
            'decision'           => 'rejected',
            'decided_by_user_id' => $approver->id,
            'decided_by_email'   => $approver->email,
            'decided_at'         => now(),
            'decision_reason'    => $reason,
        ]);

        event(new AgentApprovalRejected($this, $reason));
    }
}
```

---

## 13. Queue job ilə resume

Approval təsdiqləndi → agent loop davam etməli. Queue job-da:

```php
<?php

namespace App\Jobs;

use App\Models\AgentSession;
use App\Services\Agents\ResumableAgent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ResumeAgentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries = 1;  // resume yenidən cəhd etmir

    public function __construct(public string $sessionId) {}

    public function handle(ResumableAgent $agent): void
    {
        $session = AgentSession::findOrFail($this->sessionId);

        if ($session->status->value !== 'active') {
            // başqa job-da resume olunubsa
            return;
        }

        // Pending tool call-ı icra et
        $pending = $session->pending_action;
        $toolResult = $agent->executeTool($pending['name'], $pending['input']);

        // Message history-yə tool_result əlavə et
        $state = $session->state;
        $state['messages'][] = [
            'role' => 'user',
            'content' => [[
                'type' => 'tool_result',
                'tool_use_id' => $pending['id'],
                'content' => json_encode($toolResult, JSON_UNESCAPED_UNICODE),
            ]],
        ];
        $session->update(['state' => $state, 'pending_action' => null]);

        // Loop-u davam etdir
        $agent->continueFromState($session);
    }
}
```

### ResumableAgent xidməti

```php
class ResumableAgent
{
    public function continueFromState(AgentSession $session): void
    {
        while ($session->iteration++ < $this->maxIterations) {
            $response = $this->client->messages([
                'model' => $session->model,
                'max_tokens' => 4096,
                'tools' => $this->tools->toSchema(),
                'messages' => $session->state['messages'],
                'system' => $this->systemPrompt(),
            ]);

            $session->state = [...$session->state, 'messages' => [...$session->state['messages'], [
                'role' => 'assistant', 'content' => $response['content'],
            ]]];

            if ($response['stop_reason'] === 'end_turn') {
                $session->update(['status' => AgentSessionStatus::Done, 'state' => $session->state]);
                event(new AgentSessionCompleted($session));
                return;
            }

            if ($response['stop_reason'] === 'tool_use') {
                foreach ($response['content'] as $block) {
                    if ($block['type'] !== 'tool_use') continue;
                    
                    $tool = $this->tools->get($block['name']);
                    if ($tool->requiresApproval) {
                        $session->requestApproval([
                            'id' => $block['id'],
                            'name' => $block['name'],
                            'input' => $block['input'],
                        ]);
                        return;  // pause
                    }
                    
                    // Aksiyonu direct icra et
                    // ... (continues as ReAct loop)
                }
            }
        }
    }
}
```

---

## 14. Slack approval flow

### Notification send

```php
namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\SlackMessage;

class AgentApprovalRequiredNotification extends Notification
{
    public function __construct(public AgentSession $session) {}

    public function via($notifiable): array
    {
        return ['slack'];
    }

    public function toSlack($notifiable): SlackMessage
    {
        $pending = $this->session->pending_action;
        $signedApprove = URL::temporarySignedRoute('agents.approve', now()->addHours(24), ['session' => $this->session->id]);
        $signedReject  = URL::temporarySignedRoute('agents.reject', now()->addHours(24), ['session' => $this->session->id]);

        return (new SlackMessage)
            ->content("Agent approval lazımdır")
            ->attachment(function ($attachment) use ($pending, $signedApprove, $signedReject) {
                $attachment
                    ->title("Tool: {$pending['name']}")
                    ->fields([
                        'Input' => json_encode($pending['input'], JSON_PRETTY_PRINT),
                        'Session' => $this->session->id,
                    ])
                    ->action('Təsdiqlə', $signedApprove, 'primary')
                    ->action('Rədd et', $signedReject, 'danger');
            });
    }
}
```

### Web endpoint

```php
Route::get('/agents/approve/{session}', function (AgentSession $session, Request $request) {
    abort_unless($request->hasValidSignature(), 403);
    abort_unless($session->status === AgentSessionStatus::AwaitingApproval, 409, 'Artıq qərar verilib');
    
    // Approver-i müəyyən et
    $approver = auth()->user() ?? User::firstWhere('email', $session->pending_approver_email);
    abort_unless($approver, 403);
    
    $session->approve($approver);
    return view('agents.approved', ['session' => $session]);
})->name('agents.approve');
```

---

## 15. Filament admin panel — pending approvals

Admin inbox bütün awaiting_approval session-lar üçün.

### Resource skeleton

```php
namespace App\Filament\Resources;

use App\Filament\Resources\AgentSessionResource\Pages;
use App\Models\AgentSession;
use App\Enums\AgentSessionStatus;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AgentSessionResource extends Resource
{
    protected static ?string $model = AgentSession::class;
    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationLabel = 'Agent approval-ları';

    public static function table(Table $table): Table
    {
        return $table
            ->query(AgentSession::where('status', AgentSessionStatus::AwaitingApproval))
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('Session')->searchable(),
                Tables\Columns\TextColumn::make('user.email')->label('İstifadəçi'),
                Tables\Columns\TextColumn::make('pending_action.name')->label('Tool'),
                Tables\Columns\TextColumn::make('awaiting_since')->label('Gözləyir')->since(),
            ])
            ->actions([
                Tables\Actions\Action::make('view')->label('Detal')->url(fn ($r) => route('filament.admin.resources.agent-sessions.view', $r)),
                Tables\Actions\Action::make('approve')
                    ->label('Təsdiqlə')
                    ->color('success')
                    ->requiresConfirmation()
                    ->form([
                        \Filament\Forms\Components\Textarea::make('note')->label('Qeyd (optional)'),
                    ])
                    ->action(fn ($record, array $data) => $record->approve(auth()->user(), $data['note'] ?? null)),
                Tables\Actions\Action::make('reject')
                    ->label('Rədd et')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->form([
                        \Filament\Forms\Components\Textarea::make('reason')->label('Səbəb')->required(),
                    ])
                    ->action(fn ($record, array $data) => $record->reject(auth()->user(), $data['reason'])),
            ])
            ->poll('5s');
    }
}
```

### Detail page — diff view

Custom Livewire component tool input-unu "proposed change" formasında göstərir. Məsələn, DB update-i üçün:

```
┌─ Pending: update_customer
├─ Customer: #42 (John Doe)
├─ Proposed changes:
│    plan:    basic  →  premium   (DƏYİŞİR)
│    balance: 5000   →  3000      (DƏYİŞİR)
│    email:   x@y.az (dəyişmir)
└─ Agent reasoning: ...
```

---

## 16. Safety patterns

### Signed URL

Slack button-u email link-i dəyişdirilə bilər. Laravel `URL::temporarySignedRoute` istifadə et, cryptographic signature əlavə olur:

```php
$url = URL::temporarySignedRoute('agents.approve', now()->addHours(24), ['session' => $sessionId]);
// → https://app.example.com/agents/approve/abc?signature=xxx&expires=yyy
```

Server endpoint:

```php
if (!$request->hasValidSignature()) {
    abort(403, 'Invalid or expired signature');
}
```

### Replay protection

Bir dəfə approve olunan session yenidən approve olunmamalı. `status` dəyişikliyi enforcement-dir:

```php
if ($session->status !== AgentSessionStatus::AwaitingApproval) {
    abort(409, 'Session artıq resolved olunub');
}
```

Alternative: idempotency key (approval request ID) track et.

### Expiration

Link 24 saat sonra etibarsızdır (signed URL-də `expires` field). Əlavə check:

```php
if ($session->awaiting_since->diffInHours(now()) > 24) {
    abort(410, 'Approval window expired');
}
```

### Approver authorization

Approver user-in əslində approve edə biləcəyini yoxla:

```php
if (!$approver->can('approve-refund', $session->pending_action)) {
    abort(403, 'Bu approver refund təsdiqləyə bilməz');
}
```

Policy-də:

```php
class AgentActionPolicy
{
    public function approveRefund(User $user, array $action): bool
    {
        $amount = $action['input']['amount'] ?? 0;
        
        return match (true) {
            $amount <= 100_00   => $user->hasRole('support_l1'),
            $amount <= 1000_00  => $user->hasRole('support_manager'),
            $amount <= 10000_00 => $user->hasRole('finance_manager'),
            default             => $user->hasRole('cfo'),
        };
    }
}
```

### Two-person rule

High-value actions üçün 2 approver tələb olunsun. Schema-ya `approvers` array-i əlavə et:

```php
public function approve(User $approver, ?string $note = null): void
{
    $approvers = $this->approvers ?? [];
    if (in_array($approver->id, array_column($approvers, 'user_id'))) {
        throw new AlreadyApprovedException();
    }

    $approvers[] = ['user_id' => $approver->id, 'at' => now()->toIso8601String(), 'note' => $note];
    $this->update(['approvers' => $approvers]);

    if (count($approvers) >= $this->required_approvers) {
        // bütün approve-lar toplandı — resume et
        dispatch(new ResumeAgentJob($this->id));
    }
}
```

---

## 17. HITL NƏ vaxt istifadə olunmamalı

HITL "həmişə yaxşı"dır düşüncəsi səhvdir. Dezavantajları:

1. **Latency artışı** — 1 saniyəlik agent 10 dəqiqəyə çevrilir.
2. **Reviewer fatigue** — 50 approval request gündə — yoxlayan "Approve all" basır, sistem mənasızlaşır.
3. **UX pisləşməsi** — user "niyə bu qədər uzun çəkir?" deyir.
4. **Cost** — insan vaxtı LLM-dən bahalıdır.

### HITL lazım deyil:

- **Read-only tool-lar** — search, list, get.
- **Reversible actions** — draft yazma, note, label.
- **Təhlükəsiz sandbox** — dev env-də DB write OK.
- **Yüksək-confidence + aşağı-təsir** — FAQ cavabı.

### HITL mütləq lazımdır:

- Pul hərəkəti.
- Müştəriyə göndərilən iletişim.
- Geri qaytarıla bilməz DB dəyişiklikləri.
- Compliance scope (PII, PHI, PCI).
- Third-party API-lər (contract-binding).

---

## 18. Metrikalar

### Əsas KPI-lər

| Metrika | Hədəf | Niyə vacib |
|---------|-------|------------|
| Approval rate | > 90% | Aşağıdırsa, agent pis qərar verir |
| Rejection rate | < 5% | Yüksəkdirsə, prompt düzəlt |
| Time to approve (p50) | < 10 dəq | UX-i müəyyən edir |
| Time to approve (p95) | < 2 saat | SLA üçün |
| Expired rate | < 1% | Reviewer bandwidth problemi |
| Auto-approve rate | 60-80% | Aşağı-risk tool-lar üçün |

### Prometheus metrics

```php
Prometheus::counter('agent_approval_requests_total')
    ->inc(['tool' => $toolName, 'agent' => $agentName]);

Prometheus::histogram('agent_approval_duration_seconds')
    ->observe($session->awaiting_since->diffInSeconds(now()), ['tool' => $toolName]);

Prometheus::counter('agent_approval_outcomes_total')
    ->inc(['tool' => $toolName, 'outcome' => 'approved']);
```

### Dashboard queries

- "Son 7 gün reject rate-i" — per tool.
- "p95 time-to-approve" — per approver role.
- "Expired sessions" — per day.
- "Approval queue depth" — real-time.

---

## 19. UX — reviewer fatigue və prioritization

### Problem

Bir reviewer gündə 100 approval görür. 30-cudan sonra diqqət dağılır, 50-cidən sonra robot kimi təsdiqləyir. Bu "approval fatigue"-dir, HIPAA və clinical pharma sənayelərində çox öyrənilib.

### Mitigation

**1. Auto-filtering — "obvious"-ları LLM judge özü təsdiqləsin.**

Ön filter agent (Haiku-based) "həqiqətən reviewer-ə lazımdırmı?" sualını verir:

```
Əgər:
- Action amount < $100 AND
- User history clean AND
- Tool = standard refund AND
- Invoice age < 30 days
Onda: auto-approve (amma audit log-la)
```

Qalanlar insana çatır. Bu reviewer load-unu 80%-ə qədər azalda bilər.

**2. Batching**

Hər dəqiqədə bir Slack ping deyil, saatda bir digest:

```
Son 1 saat: 15 approval gözləyir
- 5 refund (total $340)
- 3 email send
- 2 data export
- 5 other

[Inbox-a bax]
```

**3. Prioritization UI**

```
HIGH PRIORITY (expire in 2h):
  - Refund $500, customer complaining  [Approve] [Reject]

NORMAL:
  - Refund $50, standard                [Approve] [Reject]
  - Email send to 3 customers           [Approve] [Reject]

LOW:
  - Label update (batch of 20)          [Approve All]
```

**4. Keyboard shortcuts**

`J`/`K` — next/prev. `A` — approve. `R` — reject. Filament hotkey plugin-i.

**5. Diff view quality**

Çoxu reviewer "nə dəyişir?" sualına cavab axtarır. Yaxşı diff view-un olması approval time-ını 50%+ azaldır.

---

## 20. Legal/compliance

### SOC2

SOC2 Type II audit-də:

- **Access control** — approver roles + policy-lər sənədləşib.
- **Audit logging** — dəyişdirilə bilməyən log (immutable).
- **Separation of duties** — agent və approver ayrı rol.
- **Retention** — audit log 7 il saxla (finance), 6 il (general).

### HIPAA

PHI (Protected Health Information) daxil olan agent-lərdə:

- **Patient data-ya access** — log-la.
- **Clinical decision tool-ları** — həkim approval məcburidir.
- **Breach notification** — unauthorized action varsa 60 gün içində report.

### PCI-DSS

Card data ilə işləyən agent-lərdə:

- **Stored PAN** — hash-lənmiş, məcburi.
- **Transaction approval** — $X-dən yuxarı hər transaction-da manual review.
- **Quarterly access review** — kim approve edə bilir?

### GDPR

Avropa istifadəçiləri üçün:

- **Right to explanation** — user "niyə bu qərar?" soruşa bilər. Agent reasoning log-lanmalı.
- **Right to human review** — user "insan baxsın" tələb edə bilər. Agent auto-approve-dan çıxarmalı.
- **Data subject actions** — user data export/delete — həmişə HITL.

### KYC / AML

Bank sektoru:

- **High-value transfer** — 2 approver.
- **Suspicious activity** — avtomatik "compliance queue"-ya escalate.
- **Sanctions check** — hər transfer OFAC list-ə qarşı yoxlama.

### Compliance checklist

```
[ ] Bütün approval-lar immutable audit log-da
[ ] Approver roles RBAC ilə enforce olunur
[ ] Signed URL-lər istifadə olunur
[ ] Timeout + auto-expire var
[ ] PII/PHI log-larda redacted
[ ] Retention policy yazılıb
[ ] "Right to human review" endpoint mövcuddur
[ ] Incident response runbook var
```

---

## Praktik Tapşırıqlar

### Tapşırıq 1: State Machine + Async Approval

`agent_sessions` cədvəli üçün state machine implement et: `PENDING → ACTIVE → AWAITING_APPROVAL → DONE / REJECTED / EXPIRED`. `ApproveAgentActionJob` queue job-u yaz ki, approval webhook gəldikdə session-ı serialize edilmiş state-dən bərpa edib agent loop-u davam etdirsin. Zaman aşımı: 24 saat sonra EXPIRED statusuna keçsin, user-ə notification göndərsin.

### Tapşırıq 2: Reviewer Fatigue Dashboard

`agent_approvals` cədvəlini Filament resource ilə admin paneldə göstər. Ölç: (a) ortalama gözləmə müddəti, (b) approval rate (approved / total), (c) hər reviewer-in həftəlik yük. Threshold-u aşmış (məs. gündə 30+ approval) reviewer-ləri highlight et. Bu məlumat hansı aksiyaların auto-approve siyahısına əlavə olunacağını bildirir.

### Tapşırıq 3: Signed URL + Replay Protection

Approval linkini HMAC-SHA256 signed URL kimi generate et: `token = HMAC(secret, "approve:{session_id}:{expires_at}")`. Webhook controller-da: token-i doğrula, `expires_at` keçibsə rədd et, `approved_tokens` cədvəlindən token-in əvvəllər istifadə olunub-olunmadığını yoxla (replay attack qorunması). Validation failure halında audit log-a yaz.

---

## Xülasə

- HITL agent-lərə tam etibar edilmədiyi production-da mandatory-dir.
- **Tool-level + threshold-based** pattern-ləri kombinasiyada istifadə et.
- **State machine** ilə session-ları modellə: PENDING → ACTIVE → AWAITING_APPROVAL → DONE/REJECTED/EXPIRED.
- **Full state serialization** — message history + thinking block-ları + pending tool call.
- **Queue job** ilə resume — agent loop-u sync deyil, background-da.
- **Slack/Email/Web** — 3 UI kanalı, user-ə ən rahatını ver.
- **Signed URL + expiration + replay protection** — security fundamentals.
- **Reviewer fatigue**-dən qorun — auto-filter, batching, prioritization.
- **Audit trail** dəyişdirilə bilməz olmalı — SOC2, HIPAA, PCI, GDPR.
- HITL-ı read-only tool-lara qoyma. Yalnız yüksək-təsir aksiyalara.

Bir cümlədə: HITL agent-in mühəndisliyə əkizidir. Agent nə qədər "ağıllı" olsa da, yüksək risk nöqtələrində insan son sözü olmalıdır — həm texniki, həm legal, həm etik səbəblərə görə.

---

## Əlaqəli Mövzular

- `08-agent-orchestration-patterns.md` — Supervisor pattern içindəki HITL checkpoint
- `13-agent-security.md` — Approval gate-i bypass edən privilege escalation hücumları
- `11-agent-evaluation-evals.md` — HITL qərarlarını eval dataset-inə daxil etmə
- `../02-claude-api/06-claude-agent-sdk-deep.md` — SDK-nın built-in permission sistemi
- `../08-production/04-audit-trail-logging.md` — Dəyişdirilməz audit log pattern-i
