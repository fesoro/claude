<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * PRODUCT POLICY — MƏHSUL ÜZRƏ AVTORİZASİYA QAYDALARI
 * ======================================================
 *
 * Bu Policy məhsul resursları üzrə icazələri idarə edir.
 *
 * XÜSUSI HAL — viewAny() METODU:
 * --------------------------------
 * viewAny() metodunda User parametri nullable-dır (?User).
 * Bu o deməkdir ki, hətta login olmamış (guest) istifadəçilər də
 * məhsulları görə bilər. Bu e-commerce saytları üçün normaldır —
 * kataloqu hər kəs görə bilər.
 *
 * Laravel avtomatik olaraq:
 * - Auth olunmuş user varsa → User obyektini ötürür
 * - Auth olunmamışsa → null ötürür
 * - ?User type hint buna icazə verir
 *
 * Əgər User nullable olmasaydı, Laravel guest istifadəçiləri
 * avtomatik rədd edərdi (403) — heç metodu çağırmadan.
 */
class ProductPolicy
{
    /**
     * viewAny — Hər kəs məhsulları görə bilərmi?
     *
     * QAYDA: Bəli, hətta qeydiyyatsız istifadəçilər də.
     *
     * ?User — NULL OLA BİLƏN USER:
     * Bu Laravel Policy-nin xüsusi xüsusiyyətidir:
     * - ?User yazanda guest (login olmamış) istifadəçilər də yoxlanılır
     * - User yazanda (? olmadan) guest avtomatik rədd olunur
     *
     * E-commerce kontekstində:
     * - Məhsul kataloqu hər kəsə açıqdır (SEO, marketing)
     * - Sifariş vermək üçün isə login lazımdır (OrderPolicy-də User nullable deyil)
     */
    public function viewAny(?User $user): bool
    {
        // Hər kəs — hətta guest istifadəçilər — məhsulları görə bilər
        return true;
    }

    /**
     * create — İstifadəçi yeni məhsul yarada bilərmi?
     *
     * QAYDA: Yalnız admin/aktiv istifadəçilər məhsul yarada bilər.
     * is_active sahəsi admin proxy kimi istifadə olunur.
     *
     * MODEL-SİZ METOD:
     * Məhsul hələ yaradılmayıb, ona görə Product parametri yoxdur.
     * Yalnız istifadəçinin İCAZƏSİ yoxlanılır.
     *
     * Controller-dən:
     *   $this->authorize('create', ProductModel::class);
     *   ↑ CLASS adı ötürülür, instance yox!
     */
    public function create(User $user): bool
    {
        // Yalnız admin (is_active) istifadəçilər məhsul yarada bilər
        // Adi müştərilər məhsul yarada bilməz — yalnız sifariş verə bilər
        return (bool) $user->is_active;
    }

    /**
     * updateStock — İstifadəçi stoku yeniləyə bilərmi?
     *
     * QAYDA: Yalnız admin istifadəçilər stoku dəyişə bilər.
     *
     * NƏYƏ AYRI METOD?
     * create() və updateStock() fərqli icazə ola bilər gələcəkdə:
     * - Bəlkə "editor" rolu məhsul yarada bilər amma stoku dəyişə bilməz
     * - Bəlkə "warehouse_manager" stoku dəyişə bilər amma məhsul yarada bilməz
     * Policy bu cür incə avtorizasiya imkanı verir.
     */
    public function updateStock(User $user): bool
    {
        // Yalnız admin istifadəçilər stoku dəyişə bilər
        return (bool) $user->is_active;
    }
}
