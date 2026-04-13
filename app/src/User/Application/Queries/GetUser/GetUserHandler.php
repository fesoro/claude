<?php

declare(strict_types=1);

namespace Src\User\Application\Queries\GetUser;

use Src\Shared\Application\Bus\Query;
use Src\Shared\Application\Bus\QueryHandler;
use Src\Shared\Domain\Exceptions\DomainException;
use Src\User\Application\DTOs\UserDTO;
use Src\User\Domain\Repositories\UserRepositoryInterface;
use Src\User\Domain\ValueObjects\UserId;

/**
 * GET USER HANDLER (CQRS Pattern)
 * ================================
 * Bu Handler GetUserQuery-ni qəbul edib istifadəçi məlumatlarını qaytarır.
 *
 * QUERY HANDLER-İN ROLU:
 * - Datanı YALNIZ oxuyur, heç vaxt dəyişmir.
 * - Domain Entity-ni Repository-dən alır.
 * - Entity-ni DTO-ya çevirir (domain obyekti xaricə çıxmamalıdır).
 * - DTO qaytarır.
 *
 * NƏYƏ ENTİTY DEYİL, DTO QAYTARIRIR?
 * - Entity-nin biznes metodları var — API-yə lazım deyil.
 * - Entity-nin daxili strukturu dəyişə bilər — API kontraktı pozulmamalıdır.
 * - Entity-də həssas data ola bilər (şifrə) — API-yə getməməlidir.
 * - DTO yalnız lazım olan dataları daşıyır (şifrə yoxdur!).
 */
final class GetUserHandler implements QueryHandler
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
    ) {
    }

    /**
     * İstifadəçini tap və DTO olaraq qaytar.
     *
     * @throws DomainException İstifadəçi tapılmadıqda
     */
    public function handle(Query $query): UserDTO
    {
        /** @var GetUserQuery $query */

        /**
         * String ID-ni UserId Value Object-ə çevir.
         * Əgər UUID formatı yanlışdırsa, UserId::fromString() DomainException atacaq.
         */
        $userId = UserId::fromString($query->userId);

        /**
         * Repository-dən istifadəçini axtar.
         */
        $user = $this->userRepository->findById($userId);

        if ($user === null) {
            throw new DomainException(
                "İstifadəçi tapılmadı: {$query->userId}"
            );
        }

        /**
         * Entity-ni DTO-ya çevir və qaytar.
         * UserDTO::fromEntity() yalnız lazım olan sahələri götürür (şifrəsiz!).
         */
        return UserDTO::fromEntity($user);
    }
}
