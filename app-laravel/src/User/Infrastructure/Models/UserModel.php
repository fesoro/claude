<?php

declare(strict_types=1);

namespace Src\User\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * ELOQUENT MODEL (Infrastructure Layer)
 * ======================================
 * Eloquent Model — Laravel-in ORM (Object-Relational Mapping) sistemidir.
 *
 * DDD-DƏ MODEL-İN YERİ:
 * Model Domain Layer-də DEYİL, Infrastructure Layer-dədir!
 * Çünki Model database-ə bağlıdır — bu texniki detal-dır, biznes qaydası deyil.
 *
 * AXIN:
 * Controller → Repository Interface (Domain) → EloquentUserRepository (Infrastructure)
 *   → UserModel (Infrastructure) → Database
 *
 * Repository daxilində:
 *   YAZMA: Domain Entity → UserModel-ə çevir → DB-yə yaz
 *   OXUMA: DB-dən oxu → UserModel → Domain Entity-yə çevir → qaytar
 *
 * BELƏLİKLƏ:
 * Domain Layer heç vaxt Eloquent Model-dən xəbərdar deyil.
 * Sabah Eloquent-i başqa ORM ilə əvəz etsən, Domain Layer dəyişmir.
 *
 * MODEL vs ENTITY FƏRQI:
 * - Model: DB cədvəlini təmsil edir (texniki)
 * - Entity: Biznes obyektini təmsil edir (domain)
 * - Model-də: $fillable, $casts, relations
 * - Entity-də: biznes metodları, domain events
 *
 * ═══════════════════════════════════════════════════════════════════
 * NƏYƏ Model YOX, Authenticatable EXTEND EDİRİK?
 * ═══════════════════════════════════════════════════════════════════
 *
 * Authenticatable sinfi Model-dən extend edir VƏ əlavə olaraq
 * autentifikasiya üçün lazım olan interface-ləri implementasiya edir:
 * - AuthenticatableContract — getAuthIdentifier(), getAuthPassword()
 * - AuthorizableContract — can(), cannot()
 * - CanResetPasswordContract — getEmailForPasswordReset()
 *
 * Yəni Authenticatable = Model + Auth funksionallığı.
 * Sanctum, Guard, Login/Logout — hamısı bu interface-lərdən asılıdır.
 * Əgər sadə Model istifadə etsək, Auth::attempt(), $request->user()
 * və Sanctum token sistemi İŞLƏMƏYƏCƏK.
 *
 * ═══════════════════════════════════════════════════════════════════
 * TRAİT-LƏR:
 * ═══════════════════════════════════════════════════════════════════
 *
 * HasApiTokens (Sanctum):
 *   - createToken() → Yeni API token yaradır.
 *   - tokens() → İstifadəçinin bütün token-lərini qaytarır.
 *   - currentAccessToken() → Hazırda istifadə olunan token-i qaytarır.
 *   - Bu trait olmasa, Sanctum token sistemi bu model ilə işləməyəcək.
 *
 * Notifiable:
 *   - notify() → İstifadəçiyə bildiriş göndərmək üçün (email, SMS və s.).
 *   - notifications() → İstifadəçinin bildirişlərini qaytarır.
 *   - Gələcəkdə sifariş təsdiqi, şifrə sıfırlama kimi bildirişlər üçün lazımdır.
 *
 * HasUuids:
 *   - UUID əsaslı primary key istifadə edir (auto-increment əvəzinə).
 *   - DDD-də bounded context-lər arası ID paylaşmaq üçün UUID daha uyğundur.
 */
class UserModel extends Authenticatable
{
    use HasApiTokens, HasUuids, Notifiable;

    /**
     * Bu model User bounded context-inin ayrı verilənlər bazasına qoşulur.
     * Hər bounded context-in öz DB-si var — bu DDD-nin "Database per Bounded Context"
     * prinsipidir. Beləliklə, digər context-lər (Order, Payment) bu DB-yə
     * birbaşa sorğu göndərə bilməz — yalnız domain event-lər vasitəsilə əlaqə qurulur.
     */
    protected $connection = 'user_db';

    /**
     * Cədvəl adı.
     * Laravel default olaraq class adından cəm şəklini istifadə edir (users).
     * Biz domain_users adlı cədvəl istifadə edirik.
     */
    protected $table = 'domain_users';

    /**
     * $fillable — mass assignment-dən qorunan sahələr.
     *
     * MASS ASSIGNMENT NƏDİR?
     * UserModel::create($request->all()) yazdıqda, request-dəki BÜTÜN
     * sahələr DB-yə yazıla bilər — bu təhlükəsizlik boşluğudur.
     * $fillable yalnız icazə verilən sahələri müəyyən edir.
     *
     * Məsələn: Hacker request-ə "is_admin: true" əlavə etsə,
     * $fillable-da is_admin yoxdursa, nəzərə alınmayacaq.
     */
    protected $fillable = [
        'id',
        'name',
        'email',
        'password',
        'is_active',
    ];

    /**
     * $hidden — JSON-a çevrildikdə gizlədilən sahələr.
     * Password heç vaxt API response-da görünməməlidir.
     */
    protected $hidden = [
        'password',
    ];

    /**
     * $casts — sahələrin PHP tiplərinə avtomatik çevrilməsi.
     *
     * NƏYƏ LAZIMDIR?
     * DB-dən gələn data həmişə string-dir.
     * Cast sayəsində: $user->is_active → true (boolean), '1' (string) deyil.
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'password' => 'hashed', // Laravel avtomatik bcrypt hash edir
        ];
    }

    /*
     * CROSS-CONTEXT RELATION SİLİNDİ: orders()
     *
     * DDD-də bounded context-lər arası Eloquent relation QADAĞANDIR.
     * User və Order fərqli verilənlər bazalarındadır (user_db vs order_db),
     * ona görə SQL JOIN mümkün deyil. Hətta eyni DB-də olsalar belə,
     * DDD prinsiplərinə görə bir context digərinin daxili strukturunu bilməməlidir.
     *
     * Əvəzinə: User-in sifarişlərini öyrənmək üçün Order context-inin
     * API-si və ya Query Service istifadə olunmalıdır.
     */
}
