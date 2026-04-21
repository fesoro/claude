<?php

declare(strict_types=1);

namespace Src\User\Domain\Repositories;

use Src\User\Domain\Entities\User;
use Src\User\Domain\ValueObjects\UserId;
use Src\User\Domain\ValueObjects\Email;

/**
 * USER REPOSITORY INTERFACE (DDD + Clean Architecture)
 * ====================================================
 * Bu interface Domain layer-in bazaya olan EHTİYACLARINI müəyyən edir.
 *
 * REPOSITORY PATTERN NƏDİR?
 * - Repository — Domain obyektlərini saxlamaq və tapmaq üçün abstraksiya-dır.
 * - Domain layer verilənlər bazası haqqında HEÇ NƏ BİLMİR.
 * - Domain yalnız "mənə bu ID ilə User tap" deyir — NECƏ tapılması onu maraqlandırmır.
 *
 * NƏYƏ İNTERFACE?
 * - Dependency Inversion Principle (SOLID-in D hərfi):
 *   Üst səviyyə (Domain) alt səviyyədən (Database) ASILI OLMAMALIDIR.
 *   Hər ikisi abstraksiyadan (interface) asılı olmalıdır.
 *
 * - Interface Domain layer-dədir (burada).
 * - Implementation Infrastructure layer-dədir (EloquentUserRepository).
 *
 * BU BİZƏ NƏ VERİR?
 * - Bazanı dəyişmək asandır: MySQL → PostgreSQL → MongoDB
 * - Test yazmaq asandır: InMemoryUserRepository ilə əvəz edə bilərsən.
 * - Domain layer heç bir framework-dən (Laravel, Eloquent) asılı deyil.
 */
interface UserRepositoryInterface
{
    /**
     * İstifadəçini ID ilə tap.
     *
     * @return User|null Tapılarsa User, tapılmazsa null qaytarır.
     */
    public function findById(UserId $userId): ?User;

    /**
     * İstifadəçini email ilə tap.
     * Login və ya "email artıq mövcuddur" yoxlaması üçün istifadə olunur.
     *
     * @return User|null Tapılarsa User, tapılmazsa null qaytarır.
     */
    public function findByEmail(Email $email): ?User;

    /**
     * İstifadəçini bazaya yaz (yeni yaratmaq və ya mövcudu yeniləmək).
     *
     * Repository pattern-də adətən save() metodu həm INSERT, həm UPDATE edir.
     * Bu "Upsert" yanaşmasıdır — əgər ID varsa UPDATE, yoxdursa INSERT.
     */
    public function save(User $user): void;
}
