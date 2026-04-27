# Çox Addımlı İş Axını Orkestrasyonu (Onboarding/KYC/Checkout) (Lead)

## Problem Təsviri

Müasir biznes prosesləri — merchant onboarding, KYC (Know Your Customer), checkout, loan application — onlarla addımdan ibarət olur. Bu addımlar müxtəlif sistemlərə (xarici API, insan nəzərdən keçirməsi, ödəniş prosessoru) bağlıdır. Belə proseslərdə əsas problemlər bunlardır:

- **Qismən tamamlanma**: İstifadəçi 3-cü addımda çıxarsa, növbəti dəfə haradan davam etməli?
- **Uğursuzluqlar**: Xarici API cavab vermirsə nə baş verir?
- **Retry məntiqi**: Hansı addımlar yenidən cəhd edilə bilər, hansılar edilə bilməz?
- **Kompensasiya**: Addım uğursuz olduqda əvvəlki addımları geri qaytarmaq lazımdırsa?
- **Timeout**: İnsan nəzərdən keçirməsi gecikirsə proses donub qalmamalıdır.
- **İdempotency**: Eyni addım iki dəfə icra edilsə sistem pozulmamalıdır.

---

## 1. State Machine Pattern — Vəziyyətlər, Keçidlər, Guard-lar, Hərəkətlər

State machine (vəziyyət maşını) bu problemi həll etmək üçün ideal pattern-dir. Hər proses müəyyən bir **vəziyyətdə** olur, müəyyən **hadisələr** onu başqa vəziyyətə keçirir, **guard-lar** keçidin mümkün olub-olmadığını yoxlayır, **hərəkətlər** isə keçid zamanı icra edilir.

```
PENDING → [identity_submitted] → IDENTITY_REVIEW
IDENTITY_REVIEW → [identity_approved] → BANK_SETUP
IDENTITY_REVIEW → [identity_rejected] → REJECTED
BANK_SETUP → [bank_verified] → LIVE_REVIEW
LIVE_REVIEW → [approved] → LIVE
LIVE_REVIEW → [rejected] → REJECTED
LIVE → (son vəziyyət)
REJECTED → (son vəziyyət)
```

**Konseptual quruluş:**

*Bu kod state machine-in əsas interfeyslərini — vəziyyət, keçid və state machine — müəyyən edir:*

```php
<?php

interface StateInterface
{
    public function getName(): string;
    public function isFinal(): bool;
}

interface TransitionInterface
{
    public function getFrom(): string;
    public function getTo(): string;
    public function getEvent(): string;
    public function getGuards(): array;   // callable[]
    public function getActions(): array;  // callable[]
}

interface StateMachineInterface
{
    public function can(object $subject, string $event): bool;
    public function apply(object $subject, string $event): void;
    public function getState(object $subject): string;
}
```

---

## 2. PHP State Machine İmplementasiyası — Tam Kod

*Bu kod guard yoxlaması, action icrasını və vəziyyət keçidini idarə edən tam state machine implementasiyasını göstərir:*

```php
<?php

namespace App\StateMachine;

use App\Exceptions\StateMachineException;
use App\Exceptions\GuardException;

class StateMachine implements StateMachineInterface
{
    /** @var TransitionDefinition[] */
    private array $transitions = [];

    /** @var string[] */
    private array $states = [];

    /** @var string[] */
    private array $finalStates = [];

    public function __construct(
        private readonly string $stateProperty = 'status'
    ) {}

    public function addState(string $name, bool $isFinal = false): static
    {
        $this->states[] = $name;
        if ($isFinal) {
            $this->finalStates[] = $name;
        }
        return $this;
    }

    public function addTransition(TransitionDefinition $transition): static
    {
        $this->transitions[] = $transition;
        return $this;
    }

    public function getState(object $subject): string
    {
        $property = $this->stateProperty;
        return $subject->$property;
    }

    public function can(object $subject, string $event): bool
    {
        $currentState = $this->getState($subject);

        foreach ($this->transitions as $transition) {
            if ($transition->getFrom() === $currentState
                && $transition->getEvent() === $event
            ) {
                // Bütün guard-ları yoxla
                foreach ($transition->getGuards() as $guard) {
                    if (!$guard($subject)) {
                        return false;
                    }
                }
                return true;
            }
        }

        return false;
    }

    public function apply(object $subject, string $event): void
    {
        $currentState = $this->getState($subject);

        foreach ($this->transitions as $transition) {
            if ($transition->getFrom() === $currentState
                && $transition->getEvent() === $event
            ) {
                // Guard yoxlaması
                foreach ($transition->getGuards() as $guard) {
                    if (!$guard($subject)) {
                        throw new GuardException(
                            "Keçid bloklandı: {$currentState} → {$event}"
                        );
                    }
                }

                // Hərəkətləri icra et
                foreach ($transition->getActions() as $action) {
                    $action($subject, $transition);
                }

                // Vəziyyəti yenilə
                $property = $this->stateProperty;
                $subject->$property = $transition->getTo();

                return;
            }
        }

        throw new StateMachineException(
            "'{$currentState}' vəziyyətindən '{$event}' hadisəsi ilə keçid tapılmadı"
        );
    }
}

class TransitionDefinition
{
    private array $guards = [];
    private array $actions = [];

    public function __construct(
        private readonly string $from,
        private readonly string $event,
        private readonly string $to,
    ) {}

    public function addGuard(callable $guard): static
    {
        $this->guards[] = $guard;
        return $this;
    }

    public function addAction(callable $action): static
    {
        $this->actions[] = $action;
        return $this;
    }

    public function getFrom(): string { return $this->from; }
    public function getEvent(): string { return $this->event; }
    public function getTo(): string { return $this->to; }
    public function getGuards(): array { return $this->guards; }
    public function getActions(): array { return $this->actions; }
}
```

---

## 3. Merchant Onboarding State Machine — Konfiqurasiyas

*Bu kod merchant onboarding üçün bütün vəziyyətləri, keçidləri, guard-ları və notification action-larını konfiqurasiya edən state machine-i göstərir:*

```php
<?php

namespace App\StateMachine;

use App\Models\Merchant;
use App\Services\NotificationService;
use App\Services\AuditLogService;

class MerchantOnboardingStateMachine
{
    private StateMachine $machine;

    public function __construct(
        private readonly NotificationService $notifications,
        private readonly AuditLogService $auditLog,
    ) {
        $this->machine = $this->build();
    }

    private function build(): StateMachine
    {
        $sm = new StateMachine(stateProperty: 'status');

        // Vəziyyətləri qeyd et
        $sm->addState('pending')
           ->addState('identity_review')
           ->addState('identity_rejected', isFinal: true)
           ->addState('bank_setup')
           ->addState('live_review')
           ->addState('live', isFinal: true)
           ->addState('rejected', isFinal: true)
           ->addState('suspended', isFinal: false);

        // pending → identity_review
        $sm->addTransition(
            (new TransitionDefinition('pending', 'submit_identity', 'identity_review'))
                ->addGuard(fn(Merchant $m) => $m->hasRequiredDocuments())
                ->addGuard(fn(Merchant $m) => !$m->isBlacklisted())
                ->addAction(fn(Merchant $m) => $this->notifications->sendToMerchant(
                    $m, 'identity_under_review'
                ))
                ->addAction(fn(Merchant $m) => $this->auditLog->record(
                    $m, 'identity_submitted'
                ))
        );

        // identity_review → bank_setup
        $sm->addTransition(
            (new TransitionDefinition('identity_review', 'approve_identity', 'bank_setup'))
                ->addAction(fn(Merchant $m) => $this->notifications->sendToMerchant(
                    $m, 'identity_approved'
                ))
                ->addAction(fn(Merchant $m) => $this->notifications->sendToOps(
                    $m, 'merchant_identity_approved'
                ))
        );

        // identity_review → identity_rejected
        $sm->addTransition(
            (new TransitionDefinition('identity_review', 'reject_identity', 'identity_rejected'))
                ->addAction(fn(Merchant $m) => $this->notifications->sendToMerchant(
                    $m, 'identity_rejected'
                ))
        );

        // bank_setup → live_review
        $sm->addTransition(
            (new TransitionDefinition('bank_setup', 'verify_bank', 'live_review'))
                ->addGuard(fn(Merchant $m) => $m->hasBankAccount())
                ->addAction(fn(Merchant $m) => $this->notifications->sendToOps(
                    $m, 'ready_for_live_review'
                ))
        );

        // live_review → live
        $sm->addTransition(
            (new TransitionDefinition('live_review', 'go_live', 'live'))
                ->addAction(fn(Merchant $m) => $m->activateAccount())
                ->addAction(fn(Merchant $m) => $this->notifications->sendToMerchant(
                    $m, 'account_live'
                ))
        );

        // live_review → rejected
        $sm->addTransition(
            (new TransitionDefinition('live_review', 'reject', 'rejected'))
                ->addAction(fn(Merchant $m) => $this->notifications->sendToMerchant(
                    $m, 'application_rejected'
                ))
        );

        return $sm;
    }

    public function transition(Merchant $merchant, string $event): void
    {
        $this->machine->apply($merchant, $event);
        $merchant->save();
    }

    public function can(Merchant $merchant, string $event): bool
    {
        return $this->machine->can($merchant, $event);
    }
}
```

---

## 4. Workflow State-ini DB-də Saxlamaq — Bərpa Edilə Bilənlik

İstifadəçi prosesi yarımçıq buraxsa belə sistem vəziyyəti bilməlidir. Bunun üçün workflow state-i DB-də saxlanılır.

**Migration:**

*Bu kod merchant vəziyyətini və iş axını tarixçəsini saxlayan verilənlər bazası migrasiyasını göstərir:*

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('status')->default('pending'); // state machine vəziyyəti
            $table->json('workflow_context')->nullable(); // əlavə məlumatlar
            $table->timestamp('status_changed_at')->nullable();
            $table->unsignedBigInteger('status_changed_by')->nullable();
            $table->timestamps();
        });

        Schema::create('merchant_workflow_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->string('from_state');
            $table->string('event');
            $table->string('to_state');
            $table->json('context')->nullable();
            $table->unsignedBigInteger('triggered_by')->nullable();
            $table->string('trigger_type')->default('user'); // user, system, timeout
            $table->timestamps();

            $table->index(['merchant_id', 'created_at']);
        });
    }
};
```

**Model:**

*Bu kod workflow kontekst məlumatını oxuyub yazan, guard-lar üçün lazımi metodları olan Merchant model-ini göstərir:*

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\StateMachine\MerchantOnboardingStateMachine;

class Merchant extends Model
{
    protected $fillable = [
        'name', 'email', 'status', 'workflow_context',
        'status_changed_at', 'status_changed_by',
    ];

    protected $casts = [
        'workflow_context' => 'array',
        'status_changed_at' => 'datetime',
    ];

    // Workflow context-dən məlumat oxuma
    public function getWorkflowValue(string $key, mixed $default = null): mixed
    {
        return data_get($this->workflow_context, $key, $default);
    }

    public function setWorkflowValue(string $key, mixed $value): void
    {
        $context = $this->workflow_context ?? [];
        data_set($context, $key, $value);
        $this->workflow_context = $context;
    }

    // Guard-lar üçün lazımlı metodlar
    public function hasRequiredDocuments(): bool
    {
        return $this->documents()->where('type', 'id_card')->exists()
            && $this->documents()->where('type', 'business_registration')->exists();
    }

    public function isBlacklisted(): bool
    {
        return \App\Models\Blacklist::where('email', $this->email)->exists();
    }

    public function hasBankAccount(): bool
    {
        return $this->bankAccounts()->verified()->exists();
    }

    public function activateAccount(): void
    {
        $this->update([
            'activated_at' => now(),
            'api_key' => \Illuminate\Support\Str::random(64),
        ]);
    }

    public function workflowLogs()
    {
        return $this->hasMany(MerchantWorkflowLog::class);
    }

    public function documents()
    {
        return $this->hasMany(MerchantDocument::class);
    }

    public function bankAccounts()
    {
        return $this->hasMany(MerchantBankAccount::class);
    }
}
```

**Workflow Service — vəziyyəti DB-yə yazma:**

*Bu kod keçidi DB transaction-ında icra edib log yazan və istifadəçinin davam etməli olduğu URL-i qaytaran workflow servisini göstərir:*

```php
<?php

namespace App\Services;

use App\Models\Merchant;
use App\Models\MerchantWorkflowLog;
use App\StateMachine\MerchantOnboardingStateMachine;
use Illuminate\Support\Facades\DB;

class MerchantWorkflowService
{
    public function __construct(
        private readonly MerchantOnboardingStateMachine $stateMachine,
    ) {}

    public function transition(
        Merchant $merchant,
        string $event,
        array $context = [],
        ?int $triggeredBy = null,
        string $triggerType = 'user',
    ): void {
        $fromState = $merchant->status;

        DB::transaction(function () use (
            $merchant, $event, $context,
            $fromState, $triggeredBy, $triggerType
        ) {
            $this->stateMachine->transition($merchant, $event);

            MerchantWorkflowLog::create([
                'merchant_id'  => $merchant->id,
                'from_state'   => $fromState,
                'event'        => $event,
                'to_state'     => $merchant->status,
                'context'      => $context,
                'triggered_by' => $triggeredBy,
                'trigger_type' => $triggerType,
            ]);

            $merchant->update([
                'status_changed_at' => now(),
                'status_changed_by' => $triggeredBy,
            ]);
        });
    }

    public function resume(Merchant $merchant): string
    {
        // İstifadəçinin haradan davam etməli olduğunu qaytarır
        return match ($merchant->status) {
            'pending'          => route('onboarding.identity'),
            'identity_review'  => route('onboarding.waiting', 'identity'),
            'bank_setup'       => route('onboarding.bank'),
            'live_review'      => route('onboarding.waiting', 'live'),
            'live'             => route('merchant.dashboard'),
            'rejected'         => route('onboarding.rejected'),
            default            => route('onboarding.start'),
        };
    }
}
```

---

## 5. Addım Uğursuz Olduqda Kompensasiya Hərəkətləri (Saga Pattern)

Distributed sistemlərdə əgər 3-cü addım uğursuz olursa, 1-ci və 2-ci addımların effektlərini geri qaytarmaq lazım ola bilər. Bu **Saga Pattern** ilə həll edilir.

*Bu kod hər addım uğursuz olduqda əvvəlki addımları tərsinə icra edən kompensasiyalı Saga pattern-ni göstərir:*

```php
<?php

namespace App\Services\Saga;

use App\Models\Merchant;
use Illuminate\Support\Facades\Log;

class MerchantOnboardingSaga
{
    private array $completedSteps = [];

    private array $compensations = [];

    public function execute(Merchant $merchant): void
    {
        try {
            $this->step(
                name: 'create_payment_profile',
                execute: fn() => $this->createPaymentProfile($merchant),
                compensate: fn() => $this->deletePaymentProfile($merchant),
            );

            $this->step(
                name: 'register_in_fraud_system',
                execute: fn() => $this->registerInFraudSystem($merchant),
                compensate: fn() => $this->deregisterFromFraudSystem($merchant),
            );

            $this->step(
                name: 'create_ledger_account',
                execute: fn() => $this->createLedgerAccount($merchant),
                compensate: fn() => $this->closeLedgerAccount($merchant),
            );

            $this->step(
                name: 'notify_crm',
                execute: fn() => $this->notifyCRM($merchant),
                compensate: fn() => $this->revertCRMEntry($merchant),
            );
        } catch (\Throwable $e) {
            Log::error('Saga uğursuz oldu, kompensasiya başlayır', [
                'merchant_id'     => $merchant->id,
                'failed_at_step'  => end($this->completedSteps),
                'error'           => $e->getMessage(),
            ]);

            $this->compensate();
            throw $e;
        }
    }

    private function step(string $name, callable $execute, callable $compensate): void
    {
        $execute();
        $this->completedSteps[] = $name;
        $this->compensations[$name] = $compensate;
    }

    private function compensate(): void
    {
        // Tamamlanan addımları tərsinə icra et
        foreach (array_reverse($this->completedSteps) as $stepName) {
            try {
                ($this->compensations[$stepName])();
                Log::info("Kompensasiya uğurlu: {$stepName}");
            } catch (\Throwable $e) {
                // Kompensasiya uğursuz olsa belə davam et, manual müdaxilə lazımdır
                Log::critical("Kompensasiya uğursuz: {$stepName}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function createPaymentProfile(Merchant $merchant): void
    {
        $profileId = app(PaymentGateway::class)->createMerchant($merchant);
        $merchant->setWorkflowValue('payment_profile_id', $profileId);
        $merchant->save();
    }

    private function deletePaymentProfile(Merchant $merchant): void
    {
        $profileId = $merchant->getWorkflowValue('payment_profile_id');
        if ($profileId) {
            app(PaymentGateway::class)->deleteMerchant($profileId);
        }
    }

    // ... digər addım metodları
}
```

---

## 6. Hər Addımda İdempotency

Eyni addım iki dəfə icra edilsə (şəbəkə xətası, retry) sistem pozulmamalıdır.

*Bu kod eyni idempotency açarı ilə ikinci dəfə çağırıldıqda əvvəlki nəticəni qaytaran idempotent addım icraçısını göstərir:*

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class IdempotentStepExecutor
{
    /**
     * Addımı yalnız bir dəfə icra et.
     * Eyni idempotency key ilə çağırılsa əvvəlki nəticəni qaytarır.
     */
    public function execute(
        string $idempotencyKey,
        callable $operation,
        int $lockTtlSeconds = 300,
    ): mixed {
        // DB-də artıq icra edilib-edilmədiyini yoxla
        $existing = DB::table('idempotency_records')
            ->where('key', $idempotencyKey)
            ->first();

        if ($existing) {
            return json_decode($existing->result, true);
        }

        // Eyni anda iki proses eyni əməliyyatı icra etməsin
        $lock = Cache::lock("idempotency:{$idempotencyKey}", $lockTtlSeconds);

        return $lock->block(10, function () use ($idempotencyKey, $operation) {
            // Lock əldə edildikdən sonra yenidən yoxla (double-check)
            $existing = DB::table('idempotency_records')
                ->where('key', $idempotencyKey)
                ->first();

            if ($existing) {
                return json_decode($existing->result, true);
            }

            $result = $operation();

            DB::table('idempotency_records')->insert([
                'key'        => $idempotencyKey,
                'result'     => json_encode($result),
                'created_at' => now(),
                'expires_at' => now()->addDays(7),
            ]);

            return $result;
        });
    }
}

// İstifadə nümunəsi:
class BankVerificationService
{
    public function __construct(
        private readonly IdempotentStepExecutor $idempotent,
        private readonly ExternalBankApi $bankApi,
    ) {}

    public function verifyBankAccount(int $merchantId, string $iban): array
    {
        $key = "bank_verification:{$merchantId}:{$iban}";

        return $this->idempotent->execute($key, function () use ($iban) {
            return $this->bankApi->verify($iban); // Xarici API yalnız bir dəfə çağırılır
        });
    }
}
```

---

## 7. Timeout İdarəetməsi

Bəzi addımların vaxt limiti var. Məsələn, identity review 48 saatdan çox getsə avtomatik olaraq eskalasiya edilməlidir.

*Bu kod müəyyən müddətdə tamamlanmamış workflow addımlarını aşkarlayıb eskalasiya edən artisan command-ını göstərir:*

```php
<?php

namespace App\Console\Commands;

use App\Models\Merchant;
use App\Services\MerchantWorkflowService;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class CheckWorkflowTimeouts extends Command
{
    protected $signature   = 'workflow:check-timeouts';
    protected $description = 'Vaxtı keçmiş workflow addımlarını yoxla';

    public function handle(
        MerchantWorkflowService $workflowService,
        NotificationService $notifications,
    ): void {
        // Identity review 48 saatdan çoxdur gözləyən merchantlar
        $stuckInIdentityReview = Merchant::where('status', 'identity_review')
            ->where('status_changed_at', '<', now()->subHours(48))
            ->get();

        foreach ($stuckInIdentityReview as $merchant) {
            $this->warn("Merchant #{$merchant->id} 48 saatdır identity review-dadır");

            // Ops komandaya bildiriş göndər
            $notifications->sendToOps($merchant, 'identity_review_timeout', [
                'stuck_for_hours' => $merchant->status_changed_at->diffInHours(now()),
            ]);

            // Merchant-a xatırlatma göndər (lazım olsa)
            $notifications->sendToMerchant($merchant, 'review_in_progress_reminder');

            $merchant->setWorkflowValue('identity_review_reminder_sent_at', now()->toIso8601String());
            $merchant->save();
        }

        // Bank setup 7 gündən çoxdur gözləyən merchantlar — avtomatik ləğv
        $stuckInBankSetup = Merchant::where('status', 'bank_setup')
            ->where('status_changed_at', '<', now()->subDays(7))
            ->get();

        foreach ($stuckInBankSetup as $merchant) {
            $this->error("Merchant #{$merchant->id} bank setup timeout — reject edilir");

            $workflowService->transition(
                merchant: $merchant,
                event: 'reject',
                context: ['reason' => 'bank_setup_timeout'],
                triggerType: 'timeout',
            );
        }

        $this->info('Timeout yoxlaması tamamlandı');
    }
}
```

**Kernel.php-də planlaşdırma:**

*Bu kod timeout yoxlama command-ını hər saat işlədən scheduler konfiqurasiyasını göstərir:*

```php
// app/Console/Kernel.php
$schedule->command('workflow:check-timeouts')->hourly();
```

**Timeout-u DB səviyyəsində qeyd etmək:**

*Bu kod addım üçün deadline sahəsini migration-da əlavə etməyi və addım başladıqda deadline qoymağı göstərir:*

```php
// Migration-da
$table->timestamp('step_deadline')->nullable();
$table->boolean('deadline_notified')->default(false);

// Addım başladıqda deadline qoy
$merchant->update([
    'step_deadline' => now()->addHours(48),
]);
```

---

## 8. Human-in-the-Loop Addımları

Bəzi addımlar insan qərarı tələb edir (KYC nəzərdən keçirmə, fraud yoxlaması). Bu addımlar sistemi bloklamamalıdır.

*Bu kod admin paneldən insan tərəfindən merchant kimlik yoxlamasını təsdiqləyən və rədd edən controller-ı göstərir:*

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Models\Merchant;
use App\Services\MerchantWorkflowService;
use Illuminate\Http\Request;

class MerchantReviewController
{
    public function __construct(
        private readonly MerchantWorkflowService $workflowService,
    ) {}

    /**
     * Admin paneldən merchant kimlik yoxlamasını təsdiqləyir.
     */
    public function approveIdentity(Request $request, Merchant $merchant)
    {
        $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if (!$this->workflowService->can($merchant, 'approve_identity')) {
            return back()->with('error', 'Bu merchant hal-hazırda bu əməliyyat üçün uyğun deyil');
        }

        $this->workflowService->transition(
            merchant: $merchant,
            event: 'approve_identity',
            context: [
                'reviewer_notes' => $request->notes,
                'reviewed_at'    => now()->toIso8601String(),
            ],
            triggeredBy: $request->user()->id,
            triggerType: 'user',
        );

        return redirect()
            ->route('admin.merchants.show', $merchant)
            ->with('success', 'Kimlik doğrulandı');
    }

    public function rejectIdentity(Request $request, Merchant $merchant)
    {
        $request->validate([
            'reason' => ['required', 'string'],
        ]);

        $this->workflowService->transition(
            merchant: $merchant,
            event: 'reject_identity',
            context: ['reason' => $request->reason],
            triggeredBy: $request->user()->id,
            triggerType: 'user',
        );

        return redirect()
            ->route('admin.merchants.show', $merchant)
            ->with('success', 'Kimlik rədd edildi');
    }

    /**
     * Nəzərdən keçirmə gözləyən merchantların siyahısı
     */
    public function reviewQueue()
    {
        $pending = Merchant::whereIn('status', ['identity_review', 'live_review'])
            ->with(['documents', 'workflowLogs'])
            ->orderBy('status_changed_at')
            ->paginate(20);

        return view('admin.merchants.review-queue', compact('pending'));
    }
}
```

**Xarici sistemdən webhook ilə dönən cavab (avtomatik human-in-loop):**

```php
<?php

namespace App\Http\Controllers\Webhooks;

use App\Models\Merchant;
use App\Services\MerchantWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class KycWebhookController
{
    public function __construct(
        private readonly MerchantWorkflowService $workflowService,
    ) {}

    public function handle(Request $request)
    {
        // Webhook imzasını yoxla
        $this->verifySignature($request);

        $payload = $request->validated([
            'merchant_id' => ['required', 'integer'],
            'status'      => ['required', 'in:approved,rejected'],
            'reason'      => ['nullable', 'string'],
        ]);

        $merchant = Merchant::findOrFail($payload['merchant_id']);

        $event = $payload['status'] === 'approved'
            ? 'approve_identity'
            : 'reject_identity';

        if ($this->workflowService->can($merchant, $event)) {
            $this->workflowService->transition(
                merchant: $merchant,
                event: $event,
                context: ['kyc_response' => $payload],
                triggerType: 'system',
            );
        } else {
            Log::warning('KYC webhook: merchant uyğun vəziyyətdə deyil', [
                'merchant_id'    => $merchant->id,
                'current_status' => $merchant->status,
                'event'          => $event,
            ]);
        }

        return response()->json(['received' => true]);
    }

    private function verifySignature(Request $request): void
    {
        $signature = $request->header('X-KYC-Signature');
        $expected  = hash_hmac('sha256', $request->getContent(), config('services.kyc.secret'));

        if (!hash_equals($expected, (string) $signature)) {
            abort(401, 'Etibarsız webhook imzası');
        }
    }
}
```

---

## 9. Tərəqqi Vizuallaşdırması

İstifadəçi prosesinin hansı mərhələdə olduğunu görməlidir.

*İstifadəçi prosesinin hansı mərhələdə olduğunu görməlidir üçün kod nümunəsi:*
```php
<?php

namespace App\Services;

use App\Models\Merchant;

class OnboardingProgressService
{
    /**
     * Onboarding prosesinin tam strukturunu qaytarır.
     * Hər addım üçün: tamamlandı mı, aktivdir mi, gözləyir mi.
     */
    public function getProgress(Merchant $merchant): array
    {
        $steps = $this->defineSteps();
        $currentStateOrder = $this->getStateOrder($merchant->status);

        return array_map(function (array $step) use ($currentStateOrder, $merchant) {
            $stepOrder = $step['order'];

            return array_merge($step, [
                'status' => match (true) {
                    $stepOrder < $currentStateOrder  => 'completed',
                    $stepOrder === $currentStateOrder => 'active',
                    default                           => 'pending',
                },
                'completed_at' => $stepOrder < $currentStateOrder
                    ? $this->getStepCompletionTime($merchant, $step['state'])
                    : null,
            ]);
        }, $steps);
    }

    public function getCompletionPercentage(Merchant $merchant): int
    {
        $steps = $this->defineSteps();
        $currentOrder = $this->getStateOrder($merchant->status);
        $totalSteps = count($steps);

        return (int) round(($currentOrder / $totalSteps) * 100);
    }

    private function defineSteps(): array
    {
        return [
            ['order' => 0, 'state' => 'pending',          'label' => 'Hesab Yaradıldı',       'icon' => 'user'],
            ['order' => 1, 'state' => 'identity_review',  'label' => 'Kimlik Yoxlanılır',     'icon' => 'id-card'],
            ['order' => 2, 'state' => 'bank_setup',       'label' => 'Bank Hesabı',           'icon' => 'bank'],
            ['order' => 3, 'state' => 'live_review',      'label' => 'Son Nəzərdən Keçirmə', 'icon' => 'check-circle'],
            ['order' => 4, 'state' => 'live',             'label' => 'Aktivdir',              'icon' => 'rocket'],
        ];
    }

    private function getStateOrder(string $state): int
    {
        return match ($state) {
            'pending'         => 0,
            'identity_review' => 1,
            'bank_setup'      => 2,
            'live_review'     => 3,
            'live'            => 4,
            default           => 0,
        };
    }

    private function getStepCompletionTime(Merchant $merchant, string $state): ?string
    {
        $log = $merchant->workflowLogs()
            ->where('from_state', $state)
            ->orderBy('created_at')
            ->first();

        return $log?->created_at->toIso8601String();
    }
}

// API Resource
class OnboardingProgressResource extends \Illuminate\Http\Resources\Json\JsonResource
{
    public function toArray($request): array
    {
        $progressService = app(OnboardingProgressService::class);

        return [
            'current_status' => $this->resource->status,
            'percentage'     => $progressService->getCompletionPercentage($this->resource),
            'steps'          => $progressService->getProgress($this->resource),
            'resume_url'     => app(MerchantWorkflowService::class)->resume($this->resource),
        ];
    }
}
```

---

## 10. Hər Addımda Bildirişlər

Hər keçiddə müxtəlif kanallarda bildiriş göndərilir.

*Hər keçiddə müxtəlif kanallarda bildiriş göndərilir üçün kod nümunəsi:*
```php
<?php

namespace App\Notifications;

use App\Models\Merchant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MerchantWorkflowNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $event,
        private readonly array $context = [],
    ) {}

    public function via(Merchant $notifiable): array
    {
        return match ($this->event) {
            'identity_approved'       => ['mail', 'database'],
            'identity_rejected'       => ['mail', 'database', 'slack'],
            'account_live'            => ['mail', 'database', 'slack'],
            'review_in_progress_reminder' => ['mail'],
            default                   => ['database'],
        };
    }

    public function toMail(Merchant $notifiable): MailMessage
    {
        $templates = [
            'identity_under_review' => [
                'subject' => 'Kimlik sənədləriniz nəzərdən keçirilir',
                'lines'   => [
                    'Kimlik sənədlərinizi aldıq.',
                    '24-48 saat ərzində nəticə bildiriləcəkdir.',
                ],
                'action'  => null,
            ],
            'identity_approved' => [
                'subject' => 'Kimliğiniz təsdiqləndi — Növbəti addım',
                'lines'   => [
                    'Kimlik doğrulaması uğurla tamamlandı.',
                    'İndi bank hesabınızı əlavə edə bilərsiniz.',
                ],
                'action'  => ['Bank Hesabı Əlavə Et', route('onboarding.bank')],
            ],
            'account_live' => [
                'subject' => 'Hesabınız aktivdir! 🎉',
                'lines'   => [
                    'Təbrik edirik! Merchant hesabınız artıq aktivdir.',
                    'İndi ödənişləri qəbul etməyə başlaya bilərsiniz.',
                ],
                'action'  => ['Dashboarda keç', route('merchant.dashboard')],
            ],
        ];

        $template = $templates[$this->event] ?? [
            'subject' => 'Hesab vəziyyətiniz yeniləndi',
            'lines'   => ['Hesabınızın vəziyyəti dəyişdi.'],
            'action'  => null,
        ];

        $mail = (new MailMessage)
            ->subject($template['subject']);

        foreach ($template['lines'] as $line) {
            $mail->line($line);
        }

        if ($template['action']) {
            $mail->action(...$template['action']);
        }

        return $mail;
    }

    public function toArray(Merchant $notifiable): array
    {
        return [
            'event'   => $this->event,
            'context' => $this->context,
        ];
    }
}
```

**Bildirişi state machine action-ından göndər:**

```php
// StateMachine konfiqurasiyasında:
->addAction(function (Merchant $m) {
    $m->notify(new MerchantWorkflowNotification('account_live'));
})
```

---

## 11. Real Ssenari: Yeni Merchant Onboarding — Tam Axın

```
1. Merchant qeydiyyat formasını doldurur
        ↓
2. Hesab yaradılır (status: pending)
        ↓
3. Merchant sənədlərini yükləyir → submit_identity hadisəsi
        ↓ Guard: sənədlər mövcuddur? Qara siyahıda deyil?
4. Ops komanda nəzərdən keçirir (status: identity_review)
   — HUMAN-IN-THE-LOOP — 48 saat timeout
        ↓ approve_identity hadisəsi (admin tərəfindən)
5. Merchant bank hesabını əlavə edir (status: bank_setup)
   — Xarici bank API ilə doğrulama
        ↓ verify_bank hadisəsi
6. Ops son nəzərdən keçirməni edir (status: live_review)
        ↓ go_live hadisəsi
7. Merchant hesabı aktivdir (status: live)
   — Saga: payment profile + fraud sistemi + ledger hesabı yaradılır
   — Bildiriş göndərilir
```

**Controller — tam axın:**

```php
<?php

namespace App\Http\Controllers\Onboarding;

use App\Models\Merchant;
use App\Services\MerchantWorkflowService;
use App\Services\OnboardingProgressService;
use Illuminate\Http\Request;

class OnboardingController
{
    public function __construct(
        private readonly MerchantWorkflowService $workflowService,
        private readonly OnboardingProgressService $progressService,
    ) {}

    /**
     * Merchant qeydiyyatdan keçdikdən sonra onboarding-i başladır.
     */
    public function start(Request $request)
    {
        $merchant = $request->user()->merchant;

        $resumeUrl = $this->workflowService->resume($merchant);

        // Merchant artıq bir addımdadırsa oradan davam etsin
        return redirect($resumeUrl);
    }

    /**
     * Sənəd yükləmə addımı.
     */
    public function submitIdentity(Request $request)
    {
        $merchant = $request->user()->merchant;

        $request->validate([
            'id_card'               => ['required', 'file', 'mimes:pdf,jpg,png', 'max:5120'],
            'business_registration' => ['required', 'file', 'mimes:pdf,jpg,png', 'max:5120'],
        ]);

        // Sənədləri saxla
        foreach (['id_card', 'business_registration'] as $type) {
            $path = $request->file($type)->store("merchants/{$merchant->id}/documents");
            $merchant->documents()->updateOrCreate(
                ['type' => $type],
                ['path' => $path, 'uploaded_at' => now()],
            );
        }

        if (!$this->workflowService->can($merchant, 'submit_identity')) {
            return back()->with('error', 'Sənədlər tələblərə uyğun deyil');
        }

        $this->workflowService->transition(
            merchant: $merchant,
            event: 'submit_identity',
            triggeredBy: $request->user()->id,
        );

        return redirect()
            ->route('onboarding.waiting', 'identity')
            ->with('success', 'Sənədlər göndərildi, nəzərdən keçirilir');
    }

    /**
     * Bank hesabı əlavə etmə addımı.
     */
    public function submitBankAccount(Request $request)
    {
        $merchant = $request->user()->merchant;

        $request->validate([
            'iban'         => ['required', 'string'],
            'account_name' => ['required', 'string'],
        ]);

        // İdempotent xarici API çağırışı
        $result = app(BankVerificationService::class)->verifyBankAccount(
            $merchant->id,
            $request->iban,
        );

        if (!$result['verified']) {
            return back()->with('error', 'IBAN doğrulaması uğursuz oldu: ' . $result['reason']);
        }

        $merchant->bankAccounts()->create([
            'iban'         => $request->iban,
            'account_name' => $request->account_name,
            'verified'     => true,
            'verified_at'  => now(),
        ]);

        $this->workflowService->transition(
            merchant: $merchant,
            event: 'verify_bank',
            context: ['iban_last4' => substr($request->iban, -4)],
            triggeredBy: $request->user()->id,
        );

        return redirect()
            ->route('onboarding.waiting', 'live')
            ->with('success', 'Bank hesabı doğrulandı');
    }

    /**
     * Cari tərəqqi.
     */
    public function progress(Request $request)
    {
        $merchant = $request->user()->merchant;

        return response()->json([
            'status'     => $merchant->status,
            'percentage' => $this->progressService->getCompletionPercentage($merchant),
            'steps'      => $this->progressService->getProgress($merchant),
        ]);
    }
}
```

---

## 12. Laravel İmplementasiyası: Job-lar, Event-lər, State Machine

**Workflow Event-ləri:**

```php
<?php

namespace App\Events;

use App\Models\Merchant;

class MerchantWorkflowTransitioned
{
    public function __construct(
        public readonly Merchant $merchant,
        public readonly string $fromState,
        public readonly string $event,
        public readonly string $toState,
        public readonly array $context = [],
    ) {}
}
```

**Event Listener — go_live zamanı Saga işlət:**

```php
<?php

namespace App\Listeners;

use App\Events\MerchantWorkflowTransitioned;
use App\Services\Saga\MerchantOnboardingSaga;
use Illuminate\Contracts\Queue\ShouldQueue;

class HandleMerchantGoLive implements ShouldQueue
{
    public string $queue = 'onboarding';

    public function __construct(
        private readonly MerchantOnboardingSaga $saga,
    ) {}

    public function handle(MerchantWorkflowTransitioned $event): void
    {
        if ($event->toState !== 'live') {
            return;
        }

        $this->saga->execute($event->merchant);
    }

    public function failed(MerchantWorkflowTransitioned $event, \Throwable $exception): void
    {
        // Saga uğursuz oldu — manual müdaxilə tələb edir
        app(AlertingService::class)->critical('go_live_saga_failed', [
            'merchant_id' => $event->merchant->id,
            'error'       => $exception->getMessage(),
        ]);
    }
}
```

**EventServiceProvider:**

```php
protected $listen = [
    MerchantWorkflowTransitioned::class => [
        HandleMerchantGoLive::class,
        SendWorkflowNotifications::class,
        UpdateAnalyticsDashboard::class,
    ],
];
```

**Async Job ilə KYC göndərmə:**

```php
<?php

namespace App\Jobs;

use App\Models\Merchant;
use App\Services\KycService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SubmitMerchantKyc implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60; // saniyə, exponential backoff

    public function __construct(
        private readonly Merchant $merchant,
    ) {}

    public function handle(KycService $kycService): void
    {
        $kycService->submit($this->merchant);
    }

    public function retryUntil(): \DateTime
    {
        return now()->addHours(24);
    }

    public function failed(\Throwable $exception): void
    {
        app(AlertingService::class)->error('kyc_submission_failed', [
            'merchant_id' => $this->merchant->id,
            'error'       => $exception->getMessage(),
        ]);
    }
}
```

**Service Provider-də State Machine qeydiyyatı:**

```php
<?php

namespace App\Providers;

use App\StateMachine\MerchantOnboardingStateMachine;
use Illuminate\Support\ServiceProvider;

class StateMachineServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MerchantOnboardingStateMachine::class);
    }
}
```

---

## İntervyu Sualları

**S: State machine pattern niyə if/else yığınından yaxşıdır?**
C: `if status == 'pending' && documents_uploaded` kimi şərtlər zamanla artır, bir-birinə dolaşır, hansı keçidin mümkün olduğu başa düşülmür. State machine: mövcud vəziyyətlər, mümkün keçidlər, guard şərtlər açıq şəkildə müəyyən edilir. Etibarsız keçid edilə bilməz — `can()` yoxlaması mütləq müsbət olmalıdır. Yeni state əlavə etmək codebase-i minimal şəkildə dəyişdirir.

**S: Saga pattern nədir, nə vaxt lazımdır?**
C: Distributed tranzaksiya əvəzinə hər addım yerli tranzaksiya kimi icra edilir. Addım uğursuz olduqda əvvəlki addımların effektlərini geri qaytaran "compensating action"-lar çağırılır. Lazımdır: bir neçə xarici sistem var (payment gateway, fraud sistem, ledger), bunların hamısını bir DB tranzaksiyasında birləşdirmək mümkün deyil. Mühüm məqam: compensation da uğursuz ola bilər — bu halda manual intervention lazımdır, critical alert göndər.

**S: İdempotency niyə vacibdir, necə implement edilir?**
C: Network xətası, retry, worker restart — eyni addım iki dəfə çağırıla bilər. İdempotency: eyni əməliyyatın ikinci dəfə çağırılması birinci dəfəki ilə eyni nəticəni verməlidir, side effect olmamalıdır. İmplementasiya: `idempotency_records` cədvəlinə unique key + result saxla. İkinci çağırışda key varsa, əvvəlki nəticəni qaytar, xarici API-ni yenidən çağırma. Distributed lock + double-check pattern race condition-ı önləyir.

**S: Human-in-the-loop addımları sistemi necə idarə etməlidir?**
C: Bu addımlar qeyri-müəyyən müddət gözlətir — sistem bloklanmamalıdır. Proses `identity_review` vəziyyətinə keçir, admin panel-də gözlər. Scheduled job (hər saat) 48 saatdan çox gözləyənləri aşkar edir, ops-a eskalasiya bildirişi göndərir. Admin approve/reject edir → state machine keçidi baş verir. KYC kimi xarici sistemlər webhook ilə cavab göndərə bilər → avtomatik keçid.

**S: Workflow state-ini DB-də saxlamaq niyə vacibdir?**
C: Server restart, pod eviction, deployment — bu hadisələrdə in-memory state itirilir. DB-də saxladıqda: istifadəçi brauzeri bağlayıb bir gün sonra açır, sistem `resume()` ilə hansı addımda olduğunu bilir, oradan davam edir. Audit trail əlavə faydadır: kim, nə vaxt, hansı keçidi etdi — compliance üçün lazımdır.

**S: Guard şərtlər nə vaxt state machine-ə qoyulmalı, nə vaxt controller-a?**
C: Biznes qaydaları state machine-ə məxsusdur: "sənədlər yüklənibmi?", "qara siyahıda deyilmi?". Bu qaydalar controller-a qoyularsa hər entry point-də (API, webhook, CLI) təkrarlana bilər, unudula bilər. Controller yalnız `can()` yoxlayır, keçid əməliyyatını `transition()` metoduna verir. Bir qayda dəyişirsə yalnız state machine-də dəyişdirilir.

**S: Onboarding prosesini A/B test etmək lazım olsa necə implement edilər?**
C: State machine konfiqurasiyasını variant-a görə fərqləndirmək olar. Məsələn, variant A-da "bank setup" addımı onboarding-in əvvəlindədir, variant B-də sonunda. FeatureFlagService ilə merchant-ə variant təyin et, `MerchantOnboardingStateMachine`-i variant parametri ilə konfiqurasiya et. Hər keçidin analytics event-i göndərməsini təmin et — variant-a görə completion rate-i müqayisə et.

---

## Əsas Nəticələr (Key Takeaways)

1. **State machine pattern** mürəkkəb çox addımlı prosesləri idarə etmək üçün ən münasib yanaşmadır — vəziyyətlər açıq şəkildə müəyyən edilir, etibarsız keçidlər mümkün olmur.

2. **Workflow state-ini DB-də saxlamaq** bərpa edilə bilənliyi (resumability) təmin edir — sistem çöksə, istifadəçi yenidən qoşulsa, proses kəsildiyi yerdən davam edir.

3. **Saga pattern** distributed kompensasiyası üçün lazımdır — əgər bir addım uğursuz olursa, əvvəlki addımların yan effektlərini geri qaytarmaq mümkün olur.

4. **İdempotency** retry-safe sistemlər üçün vacibdir — eyni əməliyyatın iki dəfə icra edilməsi eyni nəticə verməlidir, yan effekt olmamalıdır.

5. **Timeout idarəetməsi** insan addımlarını dondurmaqdan qoruyur — scheduled job ilə müntəzəm yoxlanılır, eskalasiya avtomatik baş verir.

6. **Human-in-the-loop addımları** sistemi bloklamır — bu addımlar üçün aydın timeout, eskalasiya yolu və admin interfeysi olmalıdır.

7. **Event-driven arxitektura** addımları bir-birindən ayırır — `MerchantWorkflowTransitioned` eventi dinləyiciləri tetikləyir, hər dinləyici öz işini müstəqil görür.

8. **Guard-lar** yalnız məntiqli keçidlərə icazə verir — bu controller-ları sadə saxlayır, biznes qaydaları state machine-də mərkəzləşdirilir.

9. **Tərəqqi vizuallaşdırması** istifadəçi təcrübəsini yaxşılaşdırır — istifadəçi hara getdiyi barədə məlumatlıdır, prosesi tərk etmə ehtimalı azalır.

10. **Audit log** hər keçidi qeyd edir — kim, nə zaman, hansı kontekstdə keçid etdi — bu həm debug, həm uyğunluq (compliance) üçün vacibdir.

---

## Anti-patternlər

**1. Onboarding addımlarını tək böyük sinxron əməliyyatda icra etmək**
E-poçt göndərmək, xarici API-lərə sorğu atmaq, hesab yaratmaq kimi bütün addımları tək HTTP request-ində sırayla çalışdırmaq — istənilən addımda xəta baş verəndə bütün proses yarımçıq qalır, rollback çətindir. Hər addımı ayrı job-a çevir, state machine ilə idarə et.

**2. Workflow vəziyyətini yaddaşda (in-memory) saxlamaq**
Onboarding prosesinin hansı addımda olduğunu server yaddaşında tutmaq — server restart olduqda, ya da başqa server request-i aldıqda vəziyyət itirilir, istifadəçi əvvəldən başlamalı olur. Hər keçidi DB-yə yaz, proses hər zaman kəsildiyi yerdən davam edə bilsin.

**3. İnsan tərəfindən gözlənilən addımlara timeout qoymamaq**
Manual review, sənəd yükləmə kimi human-in-the-loop addımlara limit qurmamaq — admin nəzərdən keçirməyi unudur, istifadəçi günlərlə "pending" vəziyyətdə qalır. Scheduled job ilə vaxtı keçmiş addımları aşkar et, eskalasiya bildirişi göndər.

**4. Saga compensation-larını planlaşdırmamaq**
Onboarding-in ortasında xəta baş verəndə əvvəlki addımların yan effektlərini (yaradılmış hesab, göndərilmiş e-poçt, debet edilmiş ödəniş) geri almaq üçün heç bir mexanizm qurmamaq — sistemdə "yarım hazır" qeydlər toplanır. Hər addım üçün compensation əməliyyatı müəyyənləşdir, xəta halında tətbiq et.

**5. Bütün onboarding addımlarını tək state-ə sıxışdırmaq**
`status: 'pending'` kimi yalnız bir vəziyyət saxlayıb bütün addımları boolean flag-lərlə izləmək — hansı addımın tamamlandığı, hansının gözlədiyini görmək çətinləşir, etibarsız keçidlərin qarşısını almaq mümkün olmur. Ayrıca state machine tətbiq et, hər keçid üçün guard şərtlər müəyyənləşdir.

**6. Onboarding keçidlərini audit log olmadan etmək**
Kim, nə zaman, hansı addımı keçdi — bu məlumatları saxlamamaq — uyğunluq (compliance) tələblərini ödəmək mümkün olmur, debug zamanı istifadəçinin hara çatdığını izləmək çətinləşir. Hər state keçidini `workflow_audit_logs` cədvəlinə timestamp, user, kontekst ilə birlikdə yaz.
