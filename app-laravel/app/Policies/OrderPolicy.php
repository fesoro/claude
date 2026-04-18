<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Src\Order\Infrastructure\Models\OrderModel;

/**
 * ORDER POLICY — SİFARİŞ ÜZRƏ AVTORİZASİYA QAYDALARI
 * ======================================================
 *
 * POLICY NEDİR VƏ NƏYƏ LAZIMDIR?
 * --------------------------------
 * Policy — Laravel-in avtorizasiya sistemidir. Sual budur:
 * "Bu istifadəçi BU KONKRET resursu görə/dəyişə bilərmi?"
 *
 * Məsələn: "User #5 Order #123-ü ləğv edə bilərmi?"
 * Policy cavab verir: "Bəli, çünki Order #123 User #5-ə məxsusdur."
 *
 * POLICY vs MIDDLEWARE FƏRQİ (ÇOX VACİBDİR!):
 * ---------------------------------------------
 * Middleware = MARŞRUT səviyyəsində yoxlama (route-level)
 *   → "Bu route-a GİRİŞ var mı?" (məsələn: auth middleware — login olubmu?)
 *   → Request controller-ə çatmamışdan ƏVVƏL işləyir
 *   → Konkret resursu bilmir, ümumi qaydadır
 *   → Misal: Route::middleware('auth') → giriş etmədən heç bir endpoint işləmir
 *
 * Policy = RESURS səviyyəsində yoxlama (resource-level)
 *   → "Bu KONKRET resursa İCZASI var mı?" (məsələn: bu sifariş səninkidir?)
 *   → Controller daxilində, konkret model ilə işləyir
 *   → Hər modelin öz Policy-si olur
 *   → Misal: $this->authorize('cancel', $order) → bu sifarişi SƏN ləğv edə bilərsən?
 *
 * POLICY vs GATE FƏRQİ:
 * ----------------------
 * Gate = ÜMUMİ hərəkətlər üçün (model-ə bağlı deyil)
 *   → Gate::define('is-admin', fn(User $user) => $user->is_active)
 *   → "Bu user admin-dir?" — konkret resurs yoxdur
 *
 * Policy = MODEL-ə BAĞLI hərəkətlər üçün
 *   → "Bu user BU SİFARİŞİ görə bilər?" — konkret Order modeli var
 *   → Hər model üçün ayrı Policy sinfi yazırsan
 *
 * LARAVEL POLICY-Nİ NECƏ TAPIR (Auto-Discovery):
 * ------------------------------------------------
 * Laravel 11-də Policy avtomatik tapılır əgər:
 *   1. Policy sinfi app/Policies/ qovluğundadırsa
 *   2. Adı ModelAdı + "Policy" formatındadırsa (User → UserPolicy)
 *
 * Bizim layihədə Model-lər DDD strukturunda olduğu üçün (src/Order/Infrastructure/Models/)
 * avtomatik tapılmır. Ona görə əl ilə AppServiceProvider-da qeydiyyat edirik:
 *   Gate::policy(OrderModel::class, OrderPolicy::class)
 *
 * CONTROLLER-DƏ NECƏ İSTİFADƏ OLUNUR:
 * -------------------------------------
 * Controller-də $this->authorize('view', $order) çağıranda:
 *   1. Laravel Order modelinə uyğun Policy-ni tapır (OrderPolicy)
 *   2. OrderPolicy::view($user, $order) metodunu çağırır
 *   3. true qaytarsa → davam edir
 *   4. false qaytarsa → 403 Forbidden xətası atır (AuthorizationException)
 *
 * before() METODU — SUPER ADMİN BYPASS:
 * --------------------------------------
 * before() bütün digər yoxlamalardan ƏVVƏL işləyir.
 * Əgər true qaytarsa → digər metodlar HEÇVƏQT çağırılmır (tam icazə).
 * Əgər null qaytarsa → normal yoxlama davam edir.
 * Əgər false qaytarsa → birbaşa rədd edilir.
 * Bu pattern "super admin hər şeyi edə bilər" üçün idealdır.
 */
class OrderPolicy
{
    /**
     * before() — Bütün policy yoxlamalarından ƏVVƏL işləyir.
     *
     * NECƏ İŞLƏYİR:
     * - Hər authorize() çağırışında ən əvvəl bu metod çağırılır
     * - true qaytarsa → digər metodlar (view, cancel, vs.) HEÇVƏQT çağırılmır
     * - null qaytarsa → normal yoxlama davam edir (aşağıdakı metodlara keçir)
     *
     * NƏYƏ NULL QAYTARIRIR, FALSE DEYİL?
     * - null = "mən qərar vermirəm, normal yoxlamaya davam et"
     * - false = "birbaşa rədd et, heç bir metodu çağırma"
     * - true = "birbaşa icazə ver, heç bir metodu çağırma"
     *
     * Biz burada: admin (is_active) istifadəçilərə tam icazə veririk.
     * Adi istifadəçilər üçün null qaytarırıq — onlar üçün hər metod ayrıca yoxlanır.
     */
    public function before(User $user, string $ability): ?bool
    {
        // Admin istifadəçilər bütün sifariş əməliyyatlarını edə bilər
        // is_active sahəsi admin proxy kimi istifadə olunur
        if ($user->is_active) {
            return true;
        }

        // null = "qərar yoxdur, normal policy metoduna keç"
        return null;
    }

    /**
     * view — İstifadəçi sifarişi görə bilərmi?
     *
     * QAYDA: İstifadəçi yalnız ÖZ sifarişlərini görə bilər.
     *
     * Controller-dən belə çağırılır:
     *   $this->authorize('view', $order);
     *
     * Laravel avtomatik olaraq:
     *   1. Cari autentifikasiya olunmuş user-i tapır (Auth::user())
     *   2. OrderPolicy::view($user, $order) çağırır
     *   3. false qaytarsa → 403 Forbidden cavabı göndərir
     */
    public function view(User $user, OrderModel $order): bool
    {
        // user_id müqayisəsi — sifariş bu istifadəçiyə məxsusdurmu?
        return $user->id === $order->user_id;
    }

    /**
     * cancel — İstifadəçi sifarişi ləğv edə bilərmi?
     *
     * QAYDALAR (hər ikisi ödənilməlidir):
     *   1. Sifariş istifadəçiyə məxsus olmalıdır (öz sifarişi)
     *   2. Sifariş statusu "pending" və ya "confirmed" olmalıdır
     *
     * NƏYƏ STATUS YOXLAMASI BURADA?
     * Bu, Policy-nin gücünü göstərir — yalnız "kimindir?" deyil,
     * həm də business rule yoxlaması edə bilər.
     * "Göndərilmiş sifarişi ləğv etmək olmaz" — bu biznes qaydadır.
     *
     * Controller-dən: $this->authorize('cancel', $order);
     */
    public function cancel(User $user, OrderModel $order): bool
    {
        // Əvvəlcə mülkiyyət yoxlaması — sifariş səninkidirmi?
        $isOwner = $user->id === $order->user_id;

        // Sonra status yoxlaması — ləğv edilə bilən statusdadırmı?
        // Yalnız pending və confirmed statuslu sifarişlər ləğv oluna bilər
        // shipped, delivered, paid statuslu sifarişlər ləğv edilə bilməz
        $isCancellable = in_array($order->status, ['pending', 'confirmed']);

        // Hər iki şərt ödənilməlidir
        return $isOwner && $isCancellable;
    }

    /**
     * updateStatus — Sifariş statusunu yeniləyə bilərmi?
     *
     * QAYDA: Yalnız admin istifadəçilər status dəyişə bilər.
     * is_active sahəsi admin proxy kimi istifadə olunur.
     *
     * QEYD: Əslində before() metodu admin-ləri avtomatik buraxır,
     * ona görə bu metod yalnız ADİ istifadəçilər üçün çağırılır.
     * Adi istifadəçi heçvəqt status dəyişə bilməz — həmişə false.
     *
     * Controller-dən: $this->authorize('updateStatus', $order);
     */
    public function updateStatus(User $user, OrderModel $order): bool
    {
        // before() artıq admin-ləri buraxıb
        // Buraya yalnız adi istifadəçilər çatır
        // Adi istifadəçi status dəyişə bilməz
        return false;
    }
}
