<?php

declare(strict_types=1);

namespace Src\User\Application\Queries\GetUser;

use Src\Shared\Application\Bus\Query;

/**
 * GET USER QUERY (CQRS Pattern)
 * ==============================
 * Bu Query sistemdən "Bu ID ilə istifadəçini tap!" sorğusunu göndərir.
 *
 * QUERY vs COMMAND FƏRQI:
 * - Command: "İstifadəçini yarat!" → datanı DƏYİŞİR, heç nə qaytarmır.
 * - Query: "İstifadəçini göstər!" → datanı OXUYUR, DTO qaytarır.
 *
 * QUERY XÜSUSİYYƏTLƏRİ:
 * 1. Side-effect free — heç bir data dəyişmir.
 * 2. Həmişə nəticə qaytarır (DTO, array, scalar və s.).
 * 3. Adı sual/sorğu formasındadır: GetUser, ListUsers, FindUserByEmail.
 *
 * AXIN:
 * Controller → GetUserQuery yaradır
 *   → QueryBus.ask(query)
 *   → GetUserHandler.handle(query)
 *   → UserDTO qaytarılır
 */
final readonly class GetUserQuery implements Query
{
    public function __construct(
        /**
         * Axtarılan istifadəçinin ID-si.
         * String olaraq qəbul edirik — Value Object yaratmaq Handler-in işidir.
         */
        public string $userId,
    ) {
    }
}
