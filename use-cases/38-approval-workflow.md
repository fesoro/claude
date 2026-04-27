# Approval Workflow (Senior)

## Problem necə yaranır?

1. **Concurrent approve:** İki manager eyni anda eyni step-i approve etməyə çalışır → ikisi də `status=pending` görür → ikisi də approve edir → workflow iki dəfə irəliləyir, skip edilmiş step-lər olur.
2. **State inconsistency:** Workflow status-u ayrı, step status-u ayrı saxlanırsa sinxronizasiya pozulur.
3. **Escalation yoxluğu:** Manager cavab vermirsə request sonsuza qədər pending qalır — biznes bloklanır.
4. **Authorization bypass:** Hər approver bütün step-ləri approve edə bilir — yalnız öz step-ini etməlidir.

---

## Workflow Modeli

```
Sequential:  Manager → Finance → CFO (ardıcıl)
Parallel:    Legal || Compliance → CEO (hamısı approve etməlidir)
Conditional: amount > $10,000 → CFO step əlavə edilir

PENDING → IN_REVIEW → APPROVED
                    → REJECTED   (istənilən step reject edə bilər)
                    → ESCALATED  (deadline keçdi)
```

---

## İmplementasiya

*Bu kod iş axını təsdiq/rədd əməliyyatlarını, paralel addım idarəsini, eskalasiya job-unu və audit trail-i göstərir:*

```php
class WorkflowService
{
    public function approve(int $stepId, int $approverId, ?string $comment = null): void
    {
        DB::transaction(function () use ($stepId, $approverId, $comment) {
            // lockForUpdate: concurrent approve race condition önlənir
            // İki manager eyni anda approve edərsə biri gözləyər
            $step = ApprovalStep::lockForUpdate()->findOrFail($stepId);

            if ($step->status !== 'pending') {
                throw new \DomainException('Bu addım artıq cavablandırılıb');
            }

            // Authorization: yalnız assigned approver approve edə bilər
            if ($step->approver_id !== $approverId) {
                throw new UnauthorizedApprovalException();
            }

            $step->update([
                'status'      => 'approved',
                'approved_at' => now(),
                'comment'     => $comment,
            ]);

            // Audit trail — hər qərar dəyişməz şəkildə qeyd edilir
            AuditLog::create([
                'workflow_id'  => $step->workflow_id,
                'step_id'      => $step->id,
                'actor_id'     => $approverId,
                'action'       => 'approved',
                'comment'      => $comment,
                'ip_address'   => request()->ip(),
                'performed_at' => now(),
            ]);

            // Parallel step-lər: eyni group-dakı hamısı approve etməlidir
            if ($step->step_type === 'parallel') {
                $pendingInGroup = ApprovalStep::where('workflow_id', $step->workflow_id)
                    ->where('step_group', $step->step_group)
                    ->where('status', 'pending')
                    ->exists();

                if ($pendingInGroup) return; // Qrup tamamlanmayıb, gözlə
            }

            // Sequential step ya da parallel qrup tamamlandı → növbəti step-ə keç
            $this->advance($step->workflow);
        });
    }

    public function reject(int $stepId, int $approverId, string $reason): void
    {
        DB::transaction(function () use ($stepId, $approverId, $reason) {
            $step = ApprovalStep::lockForUpdate()->findOrFail($stepId);

            if ($step->approver_id !== $approverId) {
                throw new UnauthorizedApprovalException();
            }

            $step->update(['status' => 'rejected', 'comment' => $reason]);

            // Bir reject → bütün workflow reject olur
            $step->workflow->update(['status' => 'rejected', 'rejected_at' => now()]);
            $step->workflow->submitter->notify(
                new WorkflowRejectedNotification($step->workflow, $reason)
            );
        });
    }

    private function advance(ApprovalWorkflow $workflow): void
    {
        $nextConfig = $this->getNextStepConfig($workflow);

        if (!$nextConfig) {
            // Bütün step-lər tamamlandı
            $workflow->update(['status' => 'approved', 'approved_at' => now()]);
            $workflow->submitter->notify(new WorkflowApprovedNotification($workflow));
            event(new WorkflowApproved($workflow));
            return;
        }

        // Növbəti step-ləri yarat + approver-ları xəbərdar et
        foreach ($nextConfig['approvers'] as $approverId) {
            ApprovalStep::create([
                'workflow_id' => $workflow->id,
                'approver_id' => $approverId,
                'step_order'  => $nextConfig['order'],
                'step_group'  => $nextConfig['group'] ?? null,
                'step_type'   => $nextConfig['type'],
                'status'      => 'pending',
                'due_at'      => now()->addHours($nextConfig['deadline_hours'] ?? 48),
            ]);

            User::find($approverId)?->notify(new ApprovalRequestedNotification($workflow));
        }
    }
}

// Escalation job — deadline keçmiş step-ləri tapıb manager-a köçürür
class EscalateOverdueApprovalsJob implements ShouldQueue
{
    public function handle(): void
    {
        ApprovalStep::where('status', 'pending')
            ->where('due_at', '<', now())
            ->chunk(50, function ($steps) {
                foreach ($steps as $step) {
                    DB::transaction(function () use ($step) {
                        $step->update(['status' => 'escalated']);

                        $escalateTo = $step->workflow->config['escalation_approver_id'] ?? null;
                        if (!$escalateTo) return;

                        // Escalation approver-a yeni step — 24 saat deadline
                        ApprovalStep::create([
                            'workflow_id'            => $step->workflow_id,
                            'approver_id'            => $escalateTo,
                            'step_order'             => $step->step_order,
                            'step_type'              => 'sequential',
                            'status'                 => 'pending',
                            'due_at'                 => now()->addHours(24),
                            'escalated_from_step_id' => $step->id,
                        ]);

                        User::find($escalateTo)?->notify(
                            new EscalationNotification($step->workflow)
                        );
                    });
                }
            });
    }
}
```

---

## Conditional Steps

Amount-a görə dinamik step əlavə edilə bilər:

*Bu kod şərti qaydaya əsasən (məsələn, məbləğ > $10,000 olduqda CFO addımı əlavə edən) dinamik növbəti addımı tapan metodu göstərir:*

```php
private function getNextStepConfig(ApprovalWorkflow $workflow): ?array
{
    $config    = $workflow->config;
    $lastOrder = ApprovalStep::where('workflow_id', $workflow->id)
        ->where('status', 'approved')
        ->max('step_order') ?? 0;

    $next = collect($config['steps'])
        ->where('order', '>', $lastOrder)
        ->first();

    if (!$next) return null;

    // Conditional: amount > 10000 → CFO step əlavə edilir
    if ($next['condition'] ?? false) {
        $entity = $workflow->entity;
        if (!$this->evaluateCondition($next['condition'], $entity)) {
            return $this->getNextAfter($config, $next['order']);
        }
    }

    return $next;
}
```

---

## Delegation (Vəkil Təyini)

Approver tətilə gedərsə başqa birinə müvəqqəti ötürə bilər. `approve()` metodunda `resolveApprover()` çağırılır:

*Bu kod təsdiq səlahiyyətinin başqa şəxsə müvəqqəti ötürülməsini (delegation) və audit üçün əsl approver-ın tapılmasını göstərir:*

```php
class ApprovalDelegationService
{
    public function delegate(int $fromApproverId, int $toApproverId, \DateTimeInterface $until): void
    {
        ApprovalDelegation::updateOrCreate(
            ['from_approver_id' => $fromApproverId],
            ['to_approver_id' => $toApproverId, 'valid_until' => $until]
        );
    }

    // Approve zamanı: əsl approver delegasiya etmişsə onu tap
    public function resolveApprover(int $approverId): int
    {
        $delegation = ApprovalDelegation::where('from_approver_id', $approverId)
            ->where('valid_until', '>', now())
            ->first();

        return $delegation?->to_approver_id ?? $approverId;
    }
}
```

**Vacib:** Delegation audit log-a da yazılmalı, kim adından kim approve etdi görsənməlidir.

---

## Anti-patterns

- **lockForUpdate olmadan concurrent approve:** Race condition — workflow iki dəfə irəliləyir, step-lər skip olunur.
- **Authorization yoxlamadan approve etmək:** Hər user hər step-i approve edə bilər.
- **Deadline olmayan step-lər:** Approval sonsuza qədər pending qalır, biznes bloklanır.
- **Workflow state-ni step state-lərdən ayrı idarə etmək:** Desync riski — step approved amma workflow hələ pending.

---

## İntervyu Sualları

**1. Parallel approval necə işləyir?**
Eyni `step_group`-dakı bütün approver-lar eyni anda xəbərdar edilir. Hər biri ayrı-ayrılıqda approve edir. Sonuncu approve etdikdə `pending` qalmadığı yoxlanır → workflow irəliləyir. Bir reject → bütün workflow reject.

**2. Concurrent approve race condition necə həll edilir?**
`lockForUpdate()` + DB transaction. İki approver eyni anda approve edərsə biri gözləyər. Lock alanı `status=pending` yoxlayır — ikincisi artıq `approved` görür → DomainException.

**3. Escalation nə zaman lazımdır?**
Manager cavab vermirsə request pending qalır. `due_at` keçmiş step-ləri scheduled job tapır, `escalated` edir, manager-in rəhbərinə yeni step yaradır. Audit trail: orijinal step `escalated`, yeni step `escalated_from_step_id` ilə link.

**4. Workflow konfiqurasiyasını hard-code etmək niyə yanlışdır?**
"Manager → Finance → CFO" ardıcıllığını PHP `if/else`-də kodlamaq yeni step əlavə etmək üçün deploy tələb edir. Konfiqurasiya DB-də JSON kimi saxlanmalı, mühərrik dinamik oxumalıdır. Beləcə biznes qaydaları dəyişdikdə deploy olmadan yenilənir.

**5. Delegation nə vaxt lazımdır, necə audit edilir?**
Approver məzuniyyətdə olduqda başqa birinə ötürür. Audit log-da `delegated_by: original_approver_id, acted_by: delegate_id` saxlanmalıdır. Delegation müddəti bitdikdə avtomatik deaktiv olur. Özünə delegation (self-delegation) bloklanmalıdır.

**6. Submission edən şəxsin öz request-ini approve etməsi niyə bloklanmalıdır?**
"Segregation of duties" — maliyyə fırıldaqçılığının klassik sxemi: kim göndərir, o approve edə bilməz. `approver_id !== workflow->submitter_id` yoxlaması məcburidir. Bütün step-lər üçün, delegation zamanı da yoxlanmalıdır.

---

## Anti-patternlər

**1. Workflow məntiqini application code-da hard-code etmək**
"Manager approve edir, sonra CFO" sırasını PHP `if/else` bloklarında kodlamaq — yeni step əlavə etmək üçün kod dəyişikliyi və deploy lazımdır. Workflow konfiqurasiyası DB-də (`workflow_steps` cədvəlində) saxlanmalı, mühərrik dinamik olaraq addımları oxumalıdır.

**2. Approve edən şəxsin özünü authorize etməsinə icazə vermək**
Submit edən istifadəçinin öz request-ini approve edə bilməsini yoxlamamaq — maliyyə fırıldaqçılığının klassik sxemi. Hər step-də `approver_id != submitter_id` yoxlaması məcburidir, ideally ayrı rol/permission sistemi ilə.

**3. Step tamamlandıqda bütün workflow-u yenidən evaluate etmək**
Hər approve/reject-də bütün workflow-u scratch-dan hesablamaq — mürəkkəb parallel step-lərdə race condition, performans problemi. Hər step state dəyişikliyi yalnız növbəti addımı tetikləməlidir, `lockForUpdate` ilə qorunmuş atomic transition.

**4. Rejection zamanı səbəb saxlamamaq**
Step reject edildikdə yalnız status `rejected` etmək, şərh tələb etməmək — submitter nəyi düzəltməli olduğunu bilmir, eyni xəta ilə yenidən göndərir. `rejection_reason` məcburi sahə olmalı, audit log-da qorunmalıdır.

**5. Approval bildirişlərini sinxron HTTP call ilə göndərmək**
Email/Slack notification-ları `approve()` metodu içindəki sinxron HTTP call ilə göndərmək — notification servisi yavaş ya da down olarsa approval işlənmir, istifadəçi xəta alır. Bildirişlər queue job-u ilə asinxron göndərilməlidir.

**6. Eskalasiya olmadan deadline-sız step-lər saxlamaq**
Approval step-lərinə `due_at` qoymamaq — manager cavab vermirsə request həftələrlə pending qalır, biznes prosesləri bloklanır. Hər step-in son tarixi olmalı, deadline keçdikdə scheduled job rəhbərə eskalasiya etməlidir.

**7. Delegation zamanı audit trail saxlamamaq**
Approver başqasına ötürdükdə audit log-a yalnız son approver-ı yazmaq — kim adından kim qərar verdi bəlli deyil. Delegation: `delegated_by`, `acted_by`, `delegation_valid_until` audit log-da qeyd edilməlidir.
