<?php

declare(strict_types=1);

namespace Src\Shared\Domain;

use Src\Shared\Domain\Exceptions\DomainException;

/**
 * DOMAIN POLICY PATTERN — Domain Səviyyəsində Avtorizasiya
 * ===========================================================
 *
 * PROBLEMİ ANLAYAQ:
 * =================
 * Laravel Policy (app/Policies/) HTTP səviyyəsində işləyir:
 *   - Controller-da `$this->authorize('cancel', $order)` yazırsan.
 *   - Request → Middleware → Controller → Policy → Domain.
 *
 * AMMA bəzən avtorizasiya qaydası BİZNES QAYDASI-dır:
 *   - "Yalnız sifarişin sahibi ləğv edə bilər" — bu BİZNES qaydasıdır.
 *   - "Ödənilmiş sifariş ləğv edilə bilməz" — bu BİZNES qaydasıdır.
 *   - "Admin istənilən sifarişi ləğv edə bilər" — bu da BİZNES qaydasıdır.
 *
 * Bu qaydalar domainə aiddir — Laravel Policy-ə deyil. Nəyə?
 *   1. Domain test-lərində Policy yoxlanılmalıdır (controller yoxdur).
 *   2. Console command-dan sifariş ləğv edəndə Policy çalışmır (HTTP yoxdur).
 *   3. Queue job-dan sifariş ləğv edəndə Policy çalışmır.
 *   4. Fərqli framework-a keçsən, Laravel Policy-ləri getməyəcək, amma domain qaydaları qalacaq.
 *
 * HƏLLİ — DOMAIN POLİCY:
 * ========================
 * Avtorizasiya qaydalarını domain layer-ə köçürürük.
 * Laravel Policy infrastructure layer-ində qalır (HTTP üçün), amma
 * əsl biznes qaydası Domain Policy-dədir.
 *
 * İKİ QATLI AVTORİZASİYA:
 * ========================
 *   HTTP Request
 *     → Laravel Policy (infrastructure): "istifadəçi authenticated-dirmi?"
 *       → Domain Policy (domain): "bu istifadəçi BU əməliyyatı edə bilərmi?"
 *         → Domain Logic: əməliyyatı icra et
 *
 * Laravel Policy: "Kim sən?" (authentication + basic authorization)
 * Domain Policy: "Sən BUNU edə bilərsənmi?" (business rule authorization)
 *
 * ANALOGİYA:
 * ==========
 * Xəstəxana:
 * - Mühafizəçi (Laravel Policy): "Sənin giriş kartın varmı?" — binaya giriş icazəsi.
 * - Həkim (Domain Policy): "Sən bu əməliyyatı edə bilərsənmi? Lisenziya, ixtisas, təcrübə var?"
 *
 * Mühafizəçi həkimlik qaydalarını bilmir — yalnız giriş icazəsini yoxlayır.
 * Həkim isə peşə qaydalarını bilir — hansı əməliyyatın edilə biləcəyini müəyyən edir.
 *
 * SPECİFİCATİON PATTERN İLƏ ƏLAQƏ:
 * ===================================
 * Domain Policy əslində Specification pattern-in avtorizasiya versiyasıdır.
 * Specification: "Bu obyekt ŞƏRTİ ödəyirmi?" (data qaydası)
 * Domain Policy: "Bu AKTOR bu ƏMƏLİYYATI edə bilərmi?" (icazə qaydası)
 *
 * İkisi də composable-dır (and, or, not ilə birləşdirilə bilər).
 * İkisi də domain layer-dədir.
 * İkisi də asanlıqla test olunur.
 *
 * İSTİFADƏ NÜMUNƏSİ:
 * ===================
 * ```php
 * // Application Service-də:
 * $policy = new OrderCancellationPolicy();
 * $policy->authorize($currentUser, $order); // DomainException atır əgər icazəsi yoxdursa
 *
 * // Və ya yoxlama:
 * if ($policy->isAllowed($currentUser, $order)) {
 *     $order->cancel($reason);
 * }
 * ```
 */
abstract class DomainPolicy
{
    /**
     * İcazə varmı yoxla — boolean qaytarır.
     * Controller-da if/else yoxlamaq üçün istifadə olunur.
     *
     * @param mixed $actor Əməliyyatı icra edən (istifadəçi, sistem, admin)
     * @param mixed $subject Əməliyyatın hədəfi (sifariş, məhsul, ödəniş)
     */
    abstract public function isAllowed(mixed $actor, mixed $subject): bool;

    /**
     * İcazə tələb et — icazə yoxdursa DomainException atır.
     * "Fail fast" prinsipi — xəta dərhal bildirilir.
     *
     * @throws PolicyViolationException İcazə yoxdursa
     */
    public function authorize(mixed $actor, mixed $subject): void
    {
        if (!$this->isAllowed($actor, $subject)) {
            throw new PolicyViolationException(
                policyName: static::class,
                reason: $this->denialReason($actor, $subject),
            );
        }
    }

    /**
     * İcazə yoxdursa səbəbi izah et.
     * Override edərək hər policy üçün fərqli izah yaz.
     */
    protected function denialReason(mixed $actor, mixed $subject): string
    {
        return 'Bu əməliyyat üçün icazəniz yoxdur.';
    }
}

/**
 * POLİCY VİOLATİON EXCEPTİON — İcazə Pozuntusu Xətası
 * =======================================================
 * Domain Policy qaydası pozulanda atılır.
 *
 * DomainException-dan fərqi:
 * - DomainException: biznes qaydası pozuldu (məs: stok yoxdur)
 * - PolicyViolationException: icazə qaydası pozuldu (məs: sən bunu edə bilməzsən)
 *
 * HTTP cavabı: 403 Forbidden
 */
final class PolicyViolationException extends DomainException
{
    public function __construct(
        public readonly string $policyName,
        string $reason,
    ) {
        parent::__construct($reason);
    }
}
