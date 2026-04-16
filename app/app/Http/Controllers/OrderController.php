<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\CancelOrderRequest;
use App\Http\Requests\CreateOrderRequest;
use App\Http\Requests\UpdateOrderStatusRequest;
use App\Http\Resources\ApiResponse;
use App\Http\Resources\OrderCollection;
use App\Http\Resources\OrderResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\Shared\Application\Bus\CommandBus;
use Src\Shared\Application\Bus\QueryBus;
use Src\Order\Application\Commands\CreateOrder\CreateOrderCommand;
use Src\Order\Application\Commands\CancelOrder\CancelOrderCommand;
use Src\Order\Application\Commands\UpdateOrderStatus\UpdateOrderStatusCommand;
use Src\Order\Application\Queries\GetOrder\GetOrderQuery;
use Src\Order\Application\Queries\ListOrders\ListOrdersQuery;
use Src\Order\Infrastructure\Models\OrderModel;

/**
 * ORDER CONTROLLER (CQRS nümunəsi)
 * ==================================
 * Bu controller-də CQRS ən aydın şəkildə görünür:
 *
 * YAZMA (Command) əməliyyatları:
 *   store()       → CreateOrderCommand  → CommandBus → CreateOrderHandler
 *   cancel()      → CancelOrderCommand  → CommandBus → CancelOrderHandler
 *   updateStatus()→ UpdateOrderStatusCommand → CommandBus → UpdateOrderStatusHandler
 *
 * OXUMA (Query) əməliyyatları:
 *   show()        → GetOrderQuery       → QueryBus → GetOrderHandler
 *   listByUser()  → ListOrdersQuery     → QueryBus → ListOrdersHandler
 *
 * Command və Query tamamilə fərqli yollardan keçir:
 * - Command: Middleware pipeline → Transaction → Domain Event → Outbox
 * - Query: Birbaşa DB-dən oxu → DTO qaytar
 */
class OrderController extends Controller
{
    public function __construct(
        private CommandBus $commandBus,
        private QueryBus $queryBus,
    ) {}

    /**
     * POST /api/orders
     * Yeni sifariş yarat.
     *
     * Request body:
     * {
     *   "user_id": "uuid",
     *   "items": [
     *     { "product_id": "uuid", "quantity": 2, "price": 29.99 }
     *   ],
     *   "address": { "street": "...", "city": "...", "zip": "...", "country": "..." }
     * }
     *
     * ARXITEKTURA AXINI:
     * 1. Controller → CreateOrderCommand yaradır
     * 2. CommandBus → Middleware pipeline-dan keçirir (Log → Validate → Transaction)
     * 3. CreateOrderHandler → OrderFactory ilə Order Aggregate yaradır
     * 4. Order::create() → OrderCreatedEvent qeydə alır
     * 5. Repository → DB-yə saxlayır
     * 6. Outbox → Integration Event-i outbox_messages cədvəlinə yazır
     * 7. OutboxPublisher (cron job) → RabbitMQ-ya göndərir
     * 8. Payment modulu → RabbitMQ-dan oxuyub ödənişi başladır
     */
    public function store(CreateOrderRequest $request): JsonResponse
    {
        $command = new CreateOrderCommand(
            userId: $request->input('user_id'),
            items: $request->input('items', []),
            address: $request->input('address', []),
        );

        $orderId = $this->commandBus->dispatch($command);

        return ApiResponse::success(
            data: ['order_id' => $orderId],
            message: 'Sifariş uğurla yaradıldı',
            code: 201
        );
    }

    /**
     * GET /api/orders/{id}
     * Sifariş detallarını al (Query — yalnız oxuma).
     */
    public function show(string $id): JsonResponse
    {
        /**
         * POLICY İSTİFADƏSİ — authorize() METODU:
         * =========================================
         * $this->authorize('view', $order) çağıranda:
         *   1. Laravel $order-in sinfinə baxır (OrderModel)
         *   2. Gate::policy() qeydiyyatından OrderPolicy-ni tapır
         *   3. OrderPolicy::before($user, 'view') çağırır (admin bypass)
         *   4. null qaytarsa → OrderPolicy::view($user, $order) çağırır
         *   5. false qaytarsa → AuthorizationException atır (403 cavab)
         *
         * ƏVVƏLCƏ model-i tapmalıyıq ki, Policy-yə ötürək.
         * Policy-nin Model lazımdır çünki "bu sifariş səninkidirmi?" yoxlayır.
         */
        $order = OrderModel::findOrFail($id);

        // authorize() — Controller bazasında olan yardımçı metoddur
        // İlk parametr: Policy metod adı ('view')
        // İkinci parametr: Model instance (OrderPolicy::view($user, $order))
        $this->authorize('view', $order);

        $query = new GetOrderQuery(orderId: $id);
        $result = $this->queryBus->ask($query);

        if ($result === null) {
            return ApiResponse::error('Sifariş tapılmadı', code: 404);
        }

        /**
         * OrderResource — sifarişi API formatına çevirir.
         * whenLoaded() sayəsində items və address yalnız eager load edildikdə göstərilir.
         */
        return ApiResponse::success(
            data: new OrderResource($result),
            message: 'Sifariş tapıldı'
        );
    }

    /**
     * GET /api/orders/user/{userId}
     * İstifadəçinin bütün sifarişlərini al (Query).
     */
    public function listByUser(string $userId): JsonResponse
    {
        $query = new ListOrdersQuery(userId: $userId);
        $orders = $this->queryBus->ask($query);

        /**
         * OrderCollection — sifariş siyahısını formatlamaq üçün ResourceCollection.
         * Hər sifariş OrderResource ilə formatlanır, pagination avtomatik əlavə olunur.
         */
        return ApiResponse::paginated(new OrderCollection($orders));
    }

    /**
     * POST /api/orders/{id}/cancel
     * Sifarişi ləğv et.
     *
     * SPECIFICATION PATTERN burada işləyir:
     * CancelOrderHandler → OrderCanBeCancelledSpec yoxlayır.
     * Yalnız PENDING və ya CONFIRMED statusda ləğv edilə bilər.
     */
    public function cancel(string $id, CancelOrderRequest $request): JsonResponse
    {
        /**
         * POLICY — Ləğvetmə icazəsi yoxlanılır.
         *
         * OrderPolicy::cancel() iki şeyi yoxlayır:
         *   1. Sifariş bu istifadəçiyə məxsusdurmu?
         *   2. Status pending/confirmed-dırmi?
         * Hər ikisi ödənilməsə → 403 Forbidden.
         */
        $order = OrderModel::findOrFail($id);
        $this->authorize('cancel', $order);

        $command = new CancelOrderCommand(orderId: $id);
        $this->commandBus->dispatch($command);

        return ApiResponse::success(message: 'Sifariş ləğv edildi');
    }

    /**
     * PATCH /api/orders/{id}/status
     * Sifariş statusunu yenilə.
     *
     * Request body: { "status": "shipped" }
     *
     * ORDER STATUS STATE MACHINE:
     * PENDING → CONFIRMED → PAID → SHIPPED → DELIVERED
     *                ↓                          ↑
     *            CANCELLED (yalnız PENDING/CONFIRMED-dən)
     */
    public function updateStatus(string $id, UpdateOrderStatusRequest $request): JsonResponse
    {
        /**
         * POLICY — Yalnız admin status dəyişə bilər.
         *
         * OrderPolicy::before() admin-ləri avtomatik buraxır (true qaytarır).
         * Adi istifadəçilər OrderPolicy::updateStatus()-a düşür → false qaytarır.
         * Nəticə: yalnız admin bu endpoint-i istifadə edə bilər.
         */
        $order = OrderModel::findOrFail($id);
        $this->authorize('updateStatus', $order);

        $command = new UpdateOrderStatusCommand(
            orderId: $id,
            newStatus: $request->input('status'),
        );

        $this->commandBus->dispatch($command);

        return ApiResponse::success(message: 'Sifariş statusu yeniləndi');
    }
}
