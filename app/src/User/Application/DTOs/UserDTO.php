<?php

declare(strict_types=1);

namespace Src\User\Application\DTOs;

use Src\User\Domain\Entities\User;

/**
 * USER DTO (Data Transfer Object)
 * ================================
 * DTO — məlumatı bir layer-dən digərinə daşımaq üçün sadə obyektdir.
 *
 * NƏYƏ DTO LAZIMDIR?
 * - Domain Entity-ni birbaşa xaricə (API, Controller) göndərmək YANLIŞ-dır.
 * - Səbəblər:
 *   1. Entity-də biznes metodları var — API-yə lazım deyil.
 *   2. Entity-nin daxili strukturu dəyişə bilər — API kontraktı pozulmamalıdır.
 *   3. Entity-də şifrə kimi həssas data ola bilər — API-yə getməməlidir.
 *
 * DTO vs ENTITY fərqi:
 * - Entity: Biznes qaydaları var, dəyişə bilər, domain layer-dədir.
 * - DTO: Yalnız data daşıyır, immutable-dir, application layer-dədir.
 *
 * READONLY CLASS (PHP 8.2):
 * - readonly class — bütün property-lər avtomatik readonly olur.
 * - Yaradıldıqdan sonra heç bir property dəyişdirilə bilməz.
 * - DTO üçün ideal-dir çünki DTO immutable olmalıdır.
 */
final readonly class UserDTO
{
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
        public string $createdAt,
    ) {
    }

    /**
     * FACTORY METHOD — Entity-dən DTO yarat.
     *
     * Bu metod Application layer-də istifadə olunur:
     * Query Handler Entity-ni bazadan alır, DTO-ya çevirir, qaytarır.
     *
     * DİQQƏT: Password DTO-ya DAXİL EDİLMİR — təhlükəsizlik qaydası.
     *
     * NÜMUNƏ:
     * $user = $repository->findById($id);
     * $dto = UserDTO::fromEntity($user);
     * return $dto; // Bu API-yə göndərilə bilər
     */
    public static function fromEntity(User $user): self
    {
        return new self(
            id: $user->userId()->value(),
            name: $user->name(),
            email: $user->email()->value(),
            createdAt: $user->createdAt()->format('Y-m-d H:i:s'),
        );
    }
}
