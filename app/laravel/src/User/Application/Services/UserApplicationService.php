<?php

declare(strict_types=1);

namespace Src\User\Application\Services;

use Src\Shared\Application\Bus\CommandBus;
use Src\Shared\Application\Bus\QueryBus;
use Src\User\Application\Commands\RegisterUser\RegisterUserCommand;
use Src\User\Application\DTOs\RegisterUserDTO;
use Src\User\Application\DTOs\UserDTO;
use Src\User\Application\Queries\GetUser\GetUserQuery;

/**
 * USER APPLICATION SERVICE (Facade Pattern)
 * ==========================================
 * Bu servis Controller-lər üçün sadə interfeys təqdim edir.
 *
 * APPLICATION SERVICE NƏDİR?
 * - Application Service — use case-ləri (istifadə hallarını) idarə edən servisdir.
 * - Controller birbaşa Command/Query Bus ilə işləmək əvəzinə,
 *   bu servis vasitəsilə işləyir — daha sadə və təmiz olur.
 *
 * FACADE PATTERN:
 * - Mürəkkəb sistemi (CommandBus, QueryBus, Commands, Queries) gizlədir.
 * - Controller yalnız "registerUser(dto)" çağırır — arxada nə baş verdiyini bilmir.
 *
 * AXIN:
 * Controller → UserApplicationService → CommandBus/QueryBus → Handler
 *
 * ALTERNATİV YANAŞMA:
 * - Bəzi layihələrdə Application Service istifadə olunmur.
 * - Controller birbaşa CommandBus/QueryBus ilə işləyir.
 * - Hər iki yanaşma düzgündür — layihənin mürəkkəbliyindən asılıdır.
 *
 * BU SERVİSİN QAYDASI:
 * - Biznes logikası BURADA OLMAMALIDIR!
 * - Yalnız Command/Query yaradıb Bus-a göndərir.
 * - Biznes qaydaları Domain layer-dədir (Entity, Value Object, Domain Service).
 */
final class UserApplicationService
{
    public function __construct(
        private readonly CommandBus $commandBus,
        private readonly QueryBus $queryBus,
    ) {
    }

    /**
     * Yeni istifadəçi qeydiyyatdan keçir.
     *
     * AXIN:
     * 1. RegisterUserDTO-dan RegisterUserCommand yaradılır
     * 2. CommandBus command-ı RegisterUserHandler-ə göndərir
     * 3. Handler User yaradır, bazaya yazır, event qeyd edir
     * 4. Yaradılan User-in ID-si qaytarılır
     *
     * @return string Yaradılan istifadəçinin UUID-si
     */
    public function registerUser(RegisterUserDTO $dto): string
    {
        $command = new RegisterUserCommand($dto);

        /** @var string $userId */
        $userId = $this->commandBus->dispatch($command);

        return $userId;
    }

    /**
     * İstifadəçini ID ilə tap və məlumatlarını qaytar.
     *
     * AXIN:
     * 1. GetUserQuery yaradılır
     * 2. QueryBus query-ni GetUserHandler-ə göndərir
     * 3. Handler User-i bazadan tapır, DTO-ya çevirir
     * 4. UserDTO qaytarılır
     */
    public function getUser(string $userId): UserDTO
    {
        $query = new GetUserQuery($userId);

        /** @var UserDTO $userDto */
        $userDto = $this->queryBus->ask($query);

        return $userDto;
    }
}
