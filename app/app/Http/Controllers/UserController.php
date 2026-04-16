<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\RegisterUserRequest;
use App\Http\Resources\ApiResponse;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\Shared\Application\Bus\CommandBus;
use Src\Shared\Application\Bus\QueryBus;
use Src\User\Application\Commands\RegisterUser\RegisterUserCommand;
use Src\User\Application\DTOs\RegisterUserDTO;
use Src\User\Application\Queries\GetUser\GetUserQuery;

/**
 * USER CONTROLLER
 * ===============
 * Controller — HTTP sorğusunu qəbul edib, müvafiq Command və ya Query-ə çevirir.
 *
 * CONTROLLER-İN ROLU (Thin Controller):
 * - HTTP request-dən data-nı çıxar
 * - Command/Query yarat
 * - Bus vasitəsilə göndər
 * - HTTP response qaytar
 *
 * Controller-də BİZNES LOGİKASI OLMAMALIDIR!
 * Biznes logikası → Domain Layer (Entity, Service)
 * Koordinasiya → Application Layer (Handler)
 * HTTP → Controller (Presentation Layer)
 */
class UserController extends Controller
{
    public function __construct(
        private CommandBus $commandBus,
        private QueryBus $queryBus,
    ) {}

    /**
     * POST /api/users/register
     * Yeni istifadəçi qeydiyyatı.
     *
     * Request body: { "name": "...", "email": "...", "password": "..." }
     *
     * AXIN:
     * HTTP Request → RegisterUserCommand → CommandBus
     *   → [LoggingMiddleware] → [ValidationMiddleware] → [TransactionMiddleware]
     *   → RegisterUserHandler → User::create() → UserRegisteredEvent
     *   → HTTP Response (201 Created)
     */
    /**
     * RegisterUserRequest type-hint ilə qəbul edilir.
     * Laravel avtomatik olaraq:
     * 1. authorize() → icazə yoxlayır (qeydiyyat üçün həmişə true)
     * 2. rules() → validasiya qaydalarını icra edir
     * 3. Uğursuzdursa → 422 cavabı qaytarır (controller-ə çatmır)
     * 4. Uğurludursa → $request->validated() ilə təmiz data əldə edirik
     *
     * ƏVVƏL: register(Request $request) — validasiya controller-də idi
     * SONRA: register(RegisterUserRequest $request) — validasiya ayrı sinifdə
     */
    public function register(RegisterUserRequest $request): JsonResponse
    {
        $command = new RegisterUserCommand(
            dto: new RegisterUserDTO(
                name: $request->input('name'),
                email: $request->input('email'),
                password: $request->input('password'),
            ),
        );

        $userId = $this->commandBus->dispatch($command);

        /**
         * ApiResponse::success() — standartlaşdırılmış cavab formatı.
         * Bütün endpoint-lərdən eyni strukturda JSON qaytarırıq.
         * 201 = HTTP Created (yeni resurs yaradıldı).
         */
        return ApiResponse::success(
            data: ['user_id' => $userId],
            message: 'İstifadəçi uğurla qeydiyyatdan keçdi',
            code: 201
        );
    }

    /**
     * GET /api/users/{id}
     * İstifadəçi məlumatlarını al.
     *
     * AXIN:
     * HTTP Request → GetUserQuery → QueryBus → GetUserHandler → UserDTO → HTTP Response
     *
     * Qeyd: Query heç vaxt datanı dəyişmir, yalnız oxuyur.
     */
    public function show(string $id): JsonResponse
    {
        $query = new GetUserQuery(userId: $id);
        $userDTO = $this->queryBus->ask($query);

        if ($userDTO === null) {
            return ApiResponse::error('İstifadəçi tapılmadı', code: 404);
        }

        /**
         * UserResource ilə DTO-nu API formatına çeviririk.
         * DTO birbaşa qaytarmaq əvəzinə, Resource vasitəsilə formatlayırıq.
         * Beləliklə API cavabı həmişə eyni strukturda olur.
         */
        return ApiResponse::success(
            data: new UserResource($userDTO),
            message: 'İstifadəçi tapıldı'
        );
    }
}
