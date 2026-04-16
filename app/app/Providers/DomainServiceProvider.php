<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Src\Shared\Application\Bus\CommandBus;
use Src\Shared\Application\Bus\QueryBus;
use Src\Shared\Application\Middleware\IdempotencyMiddleware;
use Src\Shared\Application\Middleware\LoggingMiddleware;
use Src\Shared\Application\Middleware\RetryOnConcurrencyMiddleware;
use Src\Shared\Application\Middleware\TransactionMiddleware;
use Src\Shared\Application\Middleware\ValidationMiddleware;
use Src\Shared\Infrastructure\Bus\EventDispatcher;
use Src\Shared\Infrastructure\Bus\SimpleCommandBus;
use Src\Shared\Infrastructure\Bus\SimpleQueryBus;
use Src\Shared\Infrastructure\EventSourcing\EloquentEventStore;
use Src\Shared\Infrastructure\EventSourcing\EventStore;
use Src\Shared\Infrastructure\EventSourcing\EventUpcaster;
use Src\Shared\Infrastructure\EventSourcing\SnapshotStore;
use Src\Shared\Infrastructure\Locking\DistributedLock;
use Src\Shared\Infrastructure\Logging\StructuredLogger;
use Src\Shared\Infrastructure\Messaging\DeadLetterQueue;
use Src\Shared\Infrastructure\Messaging\IdempotentConsumer;
use Src\Shared\Infrastructure\Messaging\InboxStore;
use Src\Shared\Infrastructure\Messaging\RabbitMQConsumer;
use Src\Shared\Infrastructure\Messaging\RabbitMQPublisher;
use Src\Shared\Infrastructure\Persistence\ReadReplicaConnection;
use Src\Shared\Infrastructure\Persistence\UnitOfWork;
use Src\Shared\Infrastructure\EventSourcing\ConcreteEventUpcaster;
use Src\Shared\Infrastructure\EventSourcing\PersistentProcessManager;
use Src\Shared\Infrastructure\Api\BackendForFrontend;
use Src\Shared\Infrastructure\EventSourcing\EventStoreSubscription;
use Src\Shared\Infrastructure\Messaging\OutboxRelay;
use Src\Shared\Infrastructure\Resilience\BulkheadPattern;

/**
 * DOMAIN SERVICE PROVIDER (Composition Root)
 * ============================================
 * Bu provider bütün DDD arxitekturasının "composition root"-udur.
 *
 * COMPOSITION ROOT NƏDİR?
 * - Bütün dependency-lərin bir-birinə bağlandığı tək nöqtə.
 * - Interface → Implementation mapping burada edilir.
 * - Laravel-də bu ServiceProvider vasitəsilə həyata keçirilir.
 *
 * DEPENDENCY INJECTION (DI) PRİNSİPİ:
 * - Kod interface-ə bağlıdır, konkret class-a deyil.
 * - CommandBus interface → SimpleCommandBus implementation
 * - Bu sayədə implementation-ı dəyişmək çox asandır.
 *
 * MIDDLEWARE PIPELINE SİRASI:
 * Command → Logging → Validation → Transaction → Handler
 * Bu sıra vacibdir:
 * 1. Logging: Əvvəlcə log et (hətta validation xətası olsa belə loqda görünsün)
 * 2. Validation: Data düzgündürmü yoxla
 * 3. Transaction: Hər şeyi bir transaction-da icra et
 */
class DomainServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // RabbitMQ Publisher — Integration Event-ləri göndərmək üçün
        $this->app->singleton(RabbitMQPublisher::class, function () {
            $config = config('rabbitmq');

            return new RabbitMQPublisher(
                host: $config['host'],
                port: $config['port'],
                user: $config['user'],
                password: $config['password'],
                exchange: $config['exchange'],
            );
        });

        // RabbitMQ Consumer
        $this->app->singleton(RabbitMQConsumer::class, function () {
            $config = config('rabbitmq');

            return new RabbitMQConsumer(
                host: $config['host'],
                port: $config['port'],
                user: $config['user'],
                password: $config['password'],
                exchange: $config['exchange'],
            );
        });

        // Event Dispatcher — domain event-ləri dinləyicilərə çatdırır
        $this->app->singleton(EventDispatcher::class, function ($app) {
            return new EventDispatcher(
                rabbitMQPublisher: $app->make(RabbitMQPublisher::class),
            );
        });

        /**
         * Command Bus — middleware pipeline ilə
         *
         * MIDDLEWARE PIPELINE SİRASI (YENİLƏNMİŞ):
         * Command → Logging → Idempotency → Validation → Transaction → Handler
         *
         * 1. Logging: Əvvəlcə log et (dublikat cəhdi də loqda görünsün)
         * 2. Idempotency: Dublikat command-ı bloklat (handler-ə çatmasın)
         * 3. Validation: Data düzgündürmü yoxla
         * 4. Transaction: Hər şeyi bir transaction-da icra et
         *
         * Idempotency Logging-dən SONRA yerləşdirilir çünki:
         * - Dublikat cəhdini log-da görmək lazımdır (monitoring üçün).
         * - Əgər əvvəl olsa, dublikat cəhdi log-da görünməzdi.
         */
        $this->app->singleton(CommandBus::class, function ($app) {
            $bus = new SimpleCommandBus($app);

            /**
             * MIDDLEWARE PIPELINE SİRASI:
             * Command → Logging → Idempotency → Validation → Transaction → Retry → Handler
             *
             * RetryOnConcurrency Transaction-dan SONRA yerləşir çünki:
             * - ConcurrencyException transaction daxilində baş verir.
             * - Retry olduqda bütün transaction yenidən başlamalıdır.
             * - Transaction middleware retry-ın XARİCİNDƏ olmalıdır ki, hər retry təmiz transaction ilə başlasın.
             */
            $bus->addMiddleware(new LoggingMiddleware());
            $bus->addMiddleware(new IdempotencyMiddleware());
            $bus->addMiddleware(new ValidationMiddleware());
            $bus->addMiddleware(new TransactionMiddleware());
            $bus->addMiddleware(new RetryOnConcurrencyMiddleware(maxRetries: 3));

            return $bus;
        });

        // Query Bus — middleware olmadan (oxuma əməliyyatına lazım deyil)
        $this->app->singleton(QueryBus::class, function ($app) {
            return new SimpleQueryBus($app);
        });

        // =====================================================================
        // EVENT SOURCİNG SERVİSLƏRİ
        // =====================================================================

        // Event Store — event-lərin saxlanma yeri (interface → implementation)
        $this->app->singleton(EventStore::class, function () {
            return new EloquentEventStore();
        });

        // Snapshot Store — aggregate vəziyyətini müəyyən aralıqlarla saxlayır
        // Event replay performansını artırır (100 event əvəzinə snapshot + son event-lər)
        $this->app->singleton(SnapshotStore::class, function () {
            return new SnapshotStore();
        });

        /**
         * Event Upcaster — event schema versiyalaması.
         * Köhnə event-ləri oxuyanda yeni formata çevirir.
         *
         * NÜMUNƏ: OrderCreatedEvent v1-dən v2-yə yüksəltmə.
         * v1-də 'currency' sahəsi yox idi, v2-də əlavə olundu.
         * Upcaster köhnə v1 event-ini oxuyanda avtomatik 'currency' => 'AZN' əlavə edir.
         */
        /**
         * Event Upcaster — event schema versiyalaması.
         * ConcreteEventUpcaster bütün versiya migration-larını qeydiyyatdan keçirir.
         * Əvvəl burada commented-out nümunə var idi — indi real migration-lar aktiv.
         */
        $this->app->singleton(EventUpcaster::class, function () {
            $upcaster = new EventUpcaster();

            // Bütün real event versiya migration-larını qeydiyyatdan keçir
            ConcreteEventUpcaster::register($upcaster);

            return $upcaster;
        });

        // =====================================================================
        // İNFRASTRUKTUR SERVİSLƏRİ
        // =====================================================================

        // Structured Logger — JSON formatında strukturlaşdırılmış log
        // ELK/Kibana/Datadog ilə inteqrasiya üçün
        $this->app->singleton(StructuredLogger::class, function () {
            return new StructuredLogger();
        });

        // Distributed Lock — Redis əsaslı paylanmış kilidləmə
        // Race condition-ları önləyir (məs: eyni sifarişə eyni anda iki ödəniş)
        $this->app->singleton(DistributedLock::class, function () {
            return new DistributedLock();
        });

        // Dead Letter Queue — uğursuz mesajların idarəsi
        $this->app->singleton(DeadLetterQueue::class, function () {
            return new DeadLetterQueue();
        });

        // Idempotent Consumer — dublikat mesaj emalının qarşısını alır
        // At-least-once delivery + idempotent consumer = effectively exactly-once
        $this->app->singleton(IdempotentConsumer::class, function () {
            return new IdempotentConsumer();
        });

        // Unit of Work — birdən çox aggregate-in atomik persist-i + event dispatch
        $this->app->singleton(UnitOfWork::class, function ($app) {
            return new UnitOfWork(
                eventDispatcher: $app->make(EventDispatcher::class),
            );
        });

        // =====================================================================
        // YENİ SENİOR-LEVEL SERVİSLƏR
        // =====================================================================

        // Read Replica Connection — yazma master-ə, oxuma replica-ya
        // CQRS-i infrastructure səviyyəsində tamamlayır
        $this->app->singleton(ReadReplicaConnection::class, function () {
            return new ReadReplicaConnection(
                writeConnection: 'mysql',
                readConnection: env('DB_READ_CONNECTION', 'mysql'),
            );
        });

        // Tagged Cache Service — tag əsaslı cache idarəetmə
        // CachedProductRepository decorator-unda istifadə olunur
        $this->app->singleton(\Src\Shared\Infrastructure\Cache\TaggedCacheService::class, function () {
            return new \Src\Shared\Infrastructure\Cache\TaggedCacheService();
        });

        // Backend For Frontend — klient tipinə görə API response formatlaması
        $this->app->singleton(BackendForFrontend::class, function () {
            return new BackendForFrontend();
        });

        // Inbox Store — gələn mesajları etibarlı emal etmə (Outbox-ın əksi)
        $this->app->singleton(InboxStore::class, function () {
            return new InboxStore();
        });

        // Persistent Process Manager — DB-yə persist olunan saga state
        // Server restart olsa belə proses davam edə bilər
        $this->app->singleton(PersistentProcessManager::class, function () {
            return new PersistentProcessManager();
        });

        // =====================================================================
        // YENİ ARXİTEKTURAL PATTERN-LƏR
        // =====================================================================

        /**
         * Event Store Subscription — Catch-up Subscription pattern.
         * Projector-lar event store-u poll edib read model-ləri yeniləyir.
         * Kafka consumer group-a bənzəyir — checkpoint saxlayır, geridə qalmağı izləyir.
         */
        $this->app->singleton(EventStoreSubscription::class, function () {
            return new EventStoreSubscription(
                connection: 'sqlite',
                batchSize: 100,
            );
        });

        /**
         * Outbox Relay — Transactional Outbox pattern-in transport hissəsi.
         * outbox_messages cədvəlini poll edib RabbitMQ-ya göndərir.
         * Dual-write problemi həll edir: DB + message broker atomikliyi.
         */
        $this->app->singleton(OutboxRelay::class, function ($app) {
            return new OutboxRelay(
                publisher: $app->make(RabbitMQPublisher::class),
            );
        });

        /**
         * Bulkhead Pattern — Payment Gateway üçün resurs izolyasiyası.
         * Max 20 eyni vaxtlı payment request — digər xidmətlərə təsir etmir.
         * Circuit Breaker ilə birlikdə kaskad uğursuzluğun qarşısını alır.
         */
        $this->app->singleton('bulkhead.payment', function () {
            return new BulkheadPattern(
                name: 'payment-gateway',
                maxConcurrent: 20,
                maxWaitSeconds: 10,
            );
        });

        $this->app->singleton('bulkhead.notification', function () {
            return new BulkheadPattern(
                name: 'notification-service',
                maxConcurrent: 50,
                maxWaitSeconds: 5,
            );
        });
    }

    /**
     * BOUNDED CONTEXT PROVİDER-LƏRİNİN QEYDİYYATI
     * ================================================
     * Hər bounded context-in öz ServiceProvider-i var.
     * Onlar burada yüklənir ki, hər moduldakı interface→implementation
     * binding-lər aktivləşsin.
     *
     * NƏYƏ BOOT()-DA?
     * register() yalnız binding təyin edir, boot() isə digər provider-ləri yükləyir.
     * Laravel garantiya edir ki, bütün register() tamamlandan SONRA boot() çağırılır.
     * Beləliklə DomainServiceProvider-ın öz binding-ləri (CommandBus, EventDispatcher)
     * artıq hazırdır və bounded context provider-ləri onlara istinad edə bilər.
     *
     * SERVİCE PROVİDER YÜKLƏNMƏ SIRASI:
     * 1. DomainServiceProvider::register() → CommandBus, QueryBus, EventDispatcher hazır
     * 2. DomainServiceProvider::boot() → bounded context provider-ləri yüklənir
     * 3. OrderServiceProvider::register() → OrderRepository binding
     * 4. PaymentServiceProvider::register() → PaymentRepository, CircuitBreaker binding
     * 5. ... digər provider-lər
     */
    public function boot(): void
    {
        $this->app->register(\Src\Order\Infrastructure\Providers\OrderServiceProvider::class);
        $this->app->register(\Src\Payment\Infrastructure\Providers\PaymentServiceProvider::class);
        $this->app->register(\Src\Product\Infrastructure\Providers\ProductServiceProvider::class);
        $this->app->register(\Src\User\Infrastructure\Providers\UserServiceProvider::class);
        $this->app->register(\Src\Notification\Infrastructure\Providers\NotificationServiceProvider::class);
    }
}
