<?php

declare(strict_types=1);

namespace Src\Order\Domain\Enums;

/**
 * SİFARİŞ STATUSU ENUM
 * =====================
 *
 * PHP 8.1 ENUM NƏDİR?
 * ====================
 * Enum (enumeration) — məhdud sayda sabit dəyərləri təmsil edən xüsusi tipdir.
 * PHP 8.1-dən əvvəl biz class constant istifadə edirdik:
 *
 *   class OrderStatus {
 *       const PENDING = 'pending';
 *       const PAID = 'paid';
 *   }
 *
 * Bu yanaşmanın PROBLEMLƏRİ:
 * 1. Tip yoxlaması yoxdur — funksiyaya istənilən string göndərmək olur:
 *    processOrder('yanlış_status') — xəta vermir, amma məntiqi səhvdir.
 * 2. Avtotamamlama zəifdir — IDE bilmir ki, hansı dəyərlər mövcuddur.
 * 3. == müqayisəsi etibarsızdır — 'pending' == 0 true qaytarır (PHP type juggling).
 *
 * Enum ilə bu problemlər həll olunur:
 *   function processOrder(OrderStatusEnum $status) — yalnız enum dəyəri qəbul edir.
 *   OrderStatusEnum::PENDING === OrderStatusEnum::PENDING — həmişə düzgün müqayisə.
 *
 * BACKED ENUM (Dəyərlə dəstəklənmiş enum):
 * ===========================================
 * "Backed" enum hər case-ə skalar dəyər (string və ya int) bağlayır.
 * Bu dəyər verilənlər bazasında, JSON-da, API cavablarında istifadə olunur.
 *
 *   enum Status: string {       ← ": string" — backed enum-dur, hər case-in string dəyəri var
 *       case PENDING = 'pending';  ← 'pending' DB-yə yazılacaq dəyərdir
 *   }
 *
 * PURE ENUM vs BACKED ENUM:
 * - Pure enum: case PENDING;            — dəyəri yoxdur, yalnız tip kimi istifadə olunur
 * - Backed enum: case PENDING = 'pending'; — string/int dəyəri var, DB-yə yazıla bilər
 *
 * from() və tryFrom() METODLARI:
 * ================================
 * Bu metodlar yalnız BACKED enum-larda mövcuddur:
 *
 *   OrderStatusEnum::from('pending')     → OrderStatusEnum::PENDING qaytarır
 *   OrderStatusEnum::from('yanlış')      → ValueError ATIR (exception)
 *   OrderStatusEnum::tryFrom('yanlış')   → null qaytarır (exception atmır)
 *
 * from() — dəyərin mövcud olduğuna əminsənsə istifadə et (DB-dən oxuyanda).
 * tryFrom() — istifadəçi input-unda istifadə et, null yoxlaması ilə birlikdə.
 *
 * ENUM-DA METOD YAZMAQ:
 * =====================
 * Enum-lar class kimi metod saxlaya bilər — bu onları çox güclü edir.
 * Aşağıda label() metodu hər status üçün Azərbaycan dilində ad qaytarır.
 * canTransitionTo() metodu isə statuslar arası keçidin mümkün olub-olmadığını yoxlayır.
 *
 * ENUM match() İFADƏSİNDƏ:
 * =========================
 * PHP 8.0-da gələn match() ifadəsi enum-larla mükəmməl işləyir:
 *
 *   match($status) {
 *       OrderStatusEnum::PENDING => 'Gözləyir',
 *       OrderStatusEnum::PAID => 'Ödənilib',
 *   };
 *
 * switch-dən fərqi: match() bütün case-lərin örtülməsini tələb edir,
 * əks halda UnhandledMatchError atır — beləliklə yeni status əlavə edəndə
 * unudulmuş yerləri kompilyator tapır.
 *
 * ENUM-LAR NƏYƏ STRING CONSTANT-LARDAN YAXŞIDIR?
 * ================================================
 * 1. Tip təhlükəsizliyi — funksiya parametri olaraq istifadə oluna bilər
 * 2. IDE dəstəyi — avtotamamlama, refactoring, "Find Usages"
 * 3. Validasiya — from() etibarsız dəyərdə exception atır
 * 4. Metod — enum-a biznes məntiqi əlavə etmək olur (label, canTransitionTo)
 * 5. Interface — enum interface implement edə bilər
 * 6. cases() — bütün mümkün dəyərlərin siyahısını almaq olur
 */
enum OrderStatusEnum: string
{
    /**
     * Sifariş yaradılıb, hələ təsdiqlənməyib.
     * Bu ilkin statusdur — hər yeni sifariş PENDING ilə başlayır.
     */
    case PENDING = 'pending';

    /**
     * Sifariş təsdiqlənib, ödəniş gözlənilir.
     */
    case CONFIRMED = 'confirmed';

    /**
     * Ödəniş uğurla tamamlanıb.
     */
    case PAID = 'paid';

    /**
     * Sifariş göndərilib (kargoya verilib).
     */
    case SHIPPED = 'shipped';

    /**
     * Sifariş müştəriyə çatdırılıb.
     * Bu son uğurlu statusdur.
     */
    case DELIVERED = 'delivered';

    /**
     * Sifariş ləğv edilib.
     * Ləğv edilmiş sifarişin statusu daha dəyişə bilməz (terminal status).
     */
    case CANCELLED = 'cancelled';

    /**
     * Status keçidinin mümkün olub-olmadığını yoxlayır.
     *
     * Sifariş status maşını (State Machine):
     *   PENDING → CONFIRMED, CANCELLED
     *   CONFIRMED → PAID, CANCELLED
     *   PAID → SHIPPED, CANCELLED
     *   SHIPPED → DELIVERED
     *   DELIVERED → (heç yerə — son status)
     *   CANCELLED → (heç yerə — terminal status)
     *
     * Bu metod Domain Layer-də biznes qaydalarını tətbiq edir:
     * - Göndərilmiş sifarişi ləğv etmək olmaz
     * - Çatdırılmış sifariş geri qaytarıla bilməz
     * - Status yalnız irəliyə doğru dəyişə bilər (PAID → PENDING olmaz)
     *
     * @param OrderStatusEnum $newStatus - keçid edilmək istənilən yeni status
     * @return bool - keçid mümkündürsə true, əks halda false
     */
    public function canTransitionTo(OrderStatusEnum $newStatus): bool
    {
        /**
         * match() ifadəsi — hər cari status üçün icazə verilən keçidləri qaytarır.
         * default => [] — siyahıda olmayan statuslar heç yerə keçə bilməz.
         */
        $allowedTransitions = match ($this) {
            self::PENDING   => [self::CONFIRMED, self::CANCELLED],
            self::CONFIRMED => [self::PAID, self::CANCELLED],
            self::PAID      => [self::SHIPPED, self::CANCELLED],
            self::SHIPPED   => [self::DELIVERED],
            self::DELIVERED => [],   // Son status — keçid yoxdur
            self::CANCELLED => [],   // Terminal status — keçid yoxdur
        };

        /**
         * in_array() — yeni status icazə verilən siyahıda varmı?
         * strict: true — tip yoxlaması ilə müqayisə (=== istifadə edir)
         */
        return in_array($newStatus, $allowedTransitions, strict: true);
    }

    /**
     * Statusun Azərbaycan dilində etiketini qaytarır.
     *
     * Bu metod UI səviyyəsində istifadə olunur — istifadəçiyə
     * 'pending' əvəzinə 'Gözləmədə' göstərmək üçün.
     *
     * match() burada switch-in qısa və təhlükəsiz alternativini təqdim edir.
     * Bütün case-lər örtülməlidir — yeni case əlavə etsən, burda da əlavə etməlisən.
     *
     * @return string - Azərbaycan dilində status adı
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING   => 'Gözləmədə',
            self::CONFIRMED => 'Təsdiqlənib',
            self::PAID      => 'Ödənilib',
            self::SHIPPED   => 'Göndərilib',
            self::DELIVERED => 'Çatdırılıb',
            self::CANCELLED => 'Ləğv edilib',
        };
    }
}
