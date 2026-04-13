<?php

declare(strict_types=1);

namespace Src\User\Application\Commands\RegisterUser;

use Src\Shared\Application\Bus\Command;
use Src\User\Application\DTOs\RegisterUserDTO;

/**
 * REGISTER USER COMMAND (CQRS Pattern)
 * =====================================
 * Bu Command sistemə "Yeni istifadəçi yarat!" əmrini verir.
 *
 * COMMAND NƏDİR?
 * - Command — niyyəti (intent) ifadə edən obyektdir.
 * - "Bunu ET!" mənasındadır — imperativ (əmr) formasında adlandırılır.
 * - RegisterUser = "İstifadəçini qeydiyyatdan keçir!"
 *
 * COMMAND XÜSUSİYYƏTLƏRİ:
 * 1. Immutable — yaradıldıqdan sonra dəyişmir.
 * 2. Data daşıyır — əməliyyat üçün lazım olan bütün məlumatlar buradadır.
 * 3. Handler-ə yönləndirilir — CommandBus bunu RegisterUserHandler-ə göndərir.
 *
 * AXIN:
 * Controller → RegisterUserCommand yaradır
 *   → CommandBus.dispatch(command)
 *   → RegisterUserHandler.handle(command)
 *   → User yaradılır, bazaya yazılır
 *
 * COMMAND vs DTO FƏRQI:
 * - DTO: Sadəcə data daşıyır, heç bir davranışı yoxdur.
 * - Command: Niyyəti ifadə edir, CommandBus tərəfindən Handler-ə yönləndirilir.
 * - Command daxilində DTO ola bilər (bu nümunədə olduğu kimi).
 */
final readonly class RegisterUserCommand implements Command
{
    public function __construct(
        /**
         * Qeydiyyat üçün lazım olan bütün məlumatları RegisterUserDTO daşıyır.
         * Command DTO-nu "wrap" edir — yəni DTO-nun üstündən niyyət əlavə edir.
         */
        public RegisterUserDTO $dto,
    ) {
    }
}
