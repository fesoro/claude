<?php

declare(strict_types=1);

namespace Src\User\Application\DTOs;

/**
 * REGISTER USER DTO (Input DTO)
 * ==============================
 * Bu DTO qeydiyyat üçün lazım olan məlumatları daşıyır.
 *
 * INPUT DTO vs OUTPUT DTO:
 * - Input DTO: Xaricdən gələn data (Controller → Application layer).
 *   RegisterUserDTO — qeydiyyat formasından gələn məlumatlar.
 *
 * - Output DTO: Xaricə göndərilən data (Application layer → Controller).
 *   UserDTO — API cavabı üçün istifadəçi məlumatları.
 *
 * NƏYƏ ARRAY İSTİFADƏ ETMİRİK?
 * - Array-da hansı açarların olduğu bəlli deyil → runtime xətaları.
 * - DTO-da property-lər aydındır → IDE autocomplete işləyir.
 * - DTO-ya tip verilir → yanlış data göndərmək mümkünsüz olur.
 *
 * NÜMUNƏ İSTİFADƏ:
 * $dto = new RegisterUserDTO(
 *     name: 'Orxan',
 *     email: 'orxan@example.com',
 *     password: 'MyP@ssw0rd'
 * );
 */
final readonly class RegisterUserDTO
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
    ) {
    }
}
