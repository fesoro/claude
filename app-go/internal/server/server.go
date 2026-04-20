// Package server — application composition root
//
// Laravel: bootstrap/app.php + AppServiceProvider
// Spring: @SpringBootApplication + @ComponentScan + @Configuration
// Go: explicit manual wiring — 1 funksiya, hər şey görünür
//
// Bu Go-nun fəlsəfəsidir: implicit "magic" əvəzinə explicit kod.
package server

import (
	"context"
	"fmt"
	"log/slog"

	"github.com/gin-gonic/gin"
	"github.com/google/uuid"
	notifApp "github.com/orkhan/ecommerce/internal/notification/application"
	notifChannel "github.com/orkhan/ecommerce/internal/notification/infrastructure/channel"
	notifWeb "github.com/orkhan/ecommerce/internal/notification/infrastructure/web"
	orderApp "github.com/orkhan/ecommerce/internal/order/application"
	orderPersistence "github.com/orkhan/ecommerce/internal/order/infrastructure/persistence"
	orderWeb "github.com/orkhan/ecommerce/internal/order/infrastructure/web"
	paymentApp "github.com/orkhan/ecommerce/internal/payment/application"
	paymentDomain "github.com/orkhan/ecommerce/internal/payment/domain"
	paymentGateway "github.com/orkhan/ecommerce/internal/payment/infrastructure/gateway"
	paymentPersistence "github.com/orkhan/ecommerce/internal/payment/infrastructure/persistence"
	paymentWeb "github.com/orkhan/ecommerce/internal/payment/infrastructure/web"
	productApp "github.com/orkhan/ecommerce/internal/product/application"
	productPersistence "github.com/orkhan/ecommerce/internal/product/infrastructure/persistence"
	productWeb "github.com/orkhan/ecommerce/internal/product/infrastructure/web"
	"github.com/orkhan/ecommerce/internal/admin"
	"github.com/orkhan/ecommerce/internal/config"
	"github.com/orkhan/ecommerce/internal/health"
	"github.com/orkhan/ecommerce/internal/middleware"
	"github.com/orkhan/ecommerce/internal/search"
	"github.com/orkhan/ecommerce/internal/shared/application/bus"
	sharedMW "github.com/orkhan/ecommerce/internal/shared/application/middleware"
	"github.com/orkhan/ecommerce/internal/shared/infrastructure/api"
	"github.com/orkhan/ecommerce/internal/shared/infrastructure/audit"
	"github.com/orkhan/ecommerce/internal/shared/infrastructure/auth"
	"github.com/orkhan/ecommerce/internal/shared/infrastructure/cache"
	"github.com/orkhan/ecommerce/internal/shared/infrastructure/database"
	"github.com/orkhan/ecommerce/internal/shared/infrastructure/featureflags"
	"github.com/orkhan/ecommerce/internal/shared/infrastructure/locking"
	"github.com/orkhan/ecommerce/internal/shared/infrastructure/messaging"
	userApp "github.com/orkhan/ecommerce/internal/user/application"
	userPersistence "github.com/orkhan/ecommerce/internal/user/infrastructure/persistence"
	userWeb "github.com/orkhan/ecommerce/internal/user/infrastructure/web"
	webhookCtrl "github.com/orkhan/ecommerce/internal/webhook"
	amqp "github.com/rabbitmq/amqp091-go"
	"github.com/redis/go-redis/v9"
)

type Application struct {
	cfg      *config.Config
	router   *gin.Engine
	dbs      *database.Databases
	redis    *redis.Client
	rabbit   *amqp.Connection
	cleanups []func()
}

func NewApplication(cfg *config.Config, logger *slog.Logger) (*Application, error) {
	app := &Application{cfg: cfg}

	// === 1. Infrastructure connections ===
	dbs, err := database.Open(cfg.Database)
	if err != nil {
		return nil, fmt.Errorf("database: %w", err)
	}
	app.dbs = dbs
	if err := dbs.MigrateAll("migrations"); err != nil {
		return nil, fmt.Errorf("migrate: %w", err)
	}

	rdb := redis.NewClient(&redis.Options{
		Addr: fmt.Sprintf("%s:%d", cfg.Redis.Host, cfg.Redis.Port),
		DB:   cfg.Redis.DB,
	})
	app.redis = rdb

	rabbitConn, err := amqp.Dial(cfg.RabbitMQ.URL)
	if err != nil {
		return nil, fmt.Errorf("rabbitmq: %w", err)
	}
	app.rabbit = rabbitConn

	publisher, err := messaging.NewPublisher(rabbitConn)
	if err != nil {
		return nil, fmt.Errorf("rabbitmq publisher: %w", err)
	}

	// === 2. Shared infrastructure services ===
	jwtService := auth.NewJWTService(cfg.JWT.Secret, cfg.JWT.TTL)
	twoFactor := auth.NewTwoFactorService()
	cacheSvc := cache.New(rdb)
	lockSvc := locking.New(rdb)
	auditSvc := audit.New(dbs.User)
	featureFlag := featureflags.New(cfg.Features)

	// Per-context outbox — hər DB-də öz outbox_messages cədvəli
	// Bu multi-DB transactional outbox problemini həll edir
	userOutbox := messaging.NewOutboxRepository(dbs.User)
	productOutbox := messaging.NewOutboxRepository(dbs.Product)
	orderOutbox := messaging.NewOutboxRepository(dbs.Order)
	paymentOutbox := messaging.NewOutboxRepository(dbs.Payment)

	// 4 ayrı outbox publisher (hər biri öz DB-dən oxuyur, RabbitMQ-yə göndərir)
	for _, outbox := range []*messaging.OutboxRepository{userOutbox, productOutbox, orderOutbox, paymentOutbox} {
		pub := messaging.NewOutboxPublisher(outbox, publisher,
			cfg.Outbox.PublishInterval, cfg.Outbox.BatchSize)
		go pub.Start(context.Background())
	}

	// EventDispatcher → routing key-ə görə uyğun outbox-a yazır
	eventDispatcher := NewEventDispatcher(map[string]*messaging.OutboxRepository{
		"user":    userOutbox,
		"product": productOutbox,
		"order":   orderOutbox,
		"payment": paymentOutbox,
	})

	// === 3. CQRS bus + middleware pipeline ===
	cmdBus := bus.NewBus(
		sharedMW.NewLoggingMiddleware(),
		sharedMW.NewIdempotencyMiddleware(rdb),
		sharedMW.NewValidationMiddleware(),
		sharedMW.NewTransactionMiddleware(dbs.User),
		sharedMW.NewRetryOnConcurrencyMiddleware(),
	)
	queryBus := bus.NewQueryBus()

	// === 4. Repository implementations ===
	userRepo := userPersistence.NewRepository(dbs.User)
	productRepoBase := productPersistence.NewRepository(dbs.Product)
	productRepo := productPersistence.NewCachedRepository(productRepoBase, cacheSvc, cfg.Cache.ProductTTL)
	orderRepo := orderPersistence.NewRepository(dbs.Order)
	paymentRepo := paymentPersistence.NewRepository(dbs.Payment)

	// === 5. Notification (email channel + listener-lər) ===
	emailChannel, err := notifChannel.NewEmailChannel(cfg.Mail.Host, cfg.Mail.Port, cfg.Mail.From)
	if err != nil {
		slog.Warn("email channel init failed (mailpit yoxdursa normal-dır)", "err", err)
	}
	notifListeners := notifApp.NewListeners(emailChannel)
	// RabbitMQ subscriber-ləri notification üçün qeyd et
	if err := notifChannel.SubscribeAll(context.Background(), rabbitConn, notifListeners); err != nil {
		slog.Warn("notification subscriber başlaya bilmədi", "err", err)
	}

	// === 6. Application handlers — Bus-a register et ===
	// QEYD: Go generics `_` type parametr kimi qəbul etmir — həqiqi tipləri yazmalıyıq
	bus.Register[userApp.RegisterUserCommand, uuid.UUID](cmdBus,
		userApp.NewRegisterUserHandler(userRepo, eventDispatcher))
	bus.RegisterQuery[userApp.GetUserQuery, userApp.UserDTO](queryBus,
		userApp.NewGetUserHandler(userRepo))

	bus.Register[productApp.CreateProductCommand, uuid.UUID](cmdBus,
		productApp.NewCreateProductHandler(productRepo, eventDispatcher))
	bus.Register[productApp.UpdateStockCommand, struct{}](cmdBus,
		productApp.NewUpdateStockHandler(productRepo, eventDispatcher))
	bus.RegisterQuery[productApp.GetProductQuery, productApp.ProductDTO](queryBus,
		productApp.NewGetProductHandler(productRepo))
	bus.RegisterQuery[productApp.ListProductsQuery, []productApp.ProductDTO](queryBus,
		productApp.NewListProductsHandler(productRepo))

	bus.Register[orderApp.CreateOrderCommand, uuid.UUID](cmdBus,
		orderApp.NewCreateOrderHandler(orderRepo, eventDispatcher))
	bus.Register[orderApp.CancelOrderCommand, struct{}](cmdBus,
		orderApp.NewCancelOrderHandler(orderRepo, eventDispatcher))
	bus.Register[orderApp.UpdateOrderStatusCommand, struct{}](cmdBus,
		orderApp.NewUpdateOrderStatusHandler(orderRepo, eventDispatcher))
	bus.RegisterQuery[orderApp.GetOrderQuery, orderApp.OrderDTO](queryBus,
		orderApp.NewGetOrderHandler(orderRepo))
	bus.RegisterQuery[orderApp.ListOrdersQuery, []orderApp.OrderDTO](queryBus,
		orderApp.NewListOrdersHandler(orderRepo))

	// Payment + Strategy + ACL
	paymentStrategy := paymentDomain.NewStrategyResolver(
		paymentGateway.NewCreditCard(uint32(cfg.CircuitBreaker.FailureThreshold), 30),
		paymentGateway.NewPayPal(),
		paymentGateway.NewBankTransfer(),
	)
	paymentACL := paymentApp.NewGatewayACL(paymentStrategy)
	bus.Register[paymentApp.ProcessPaymentCommand, paymentApp.PaymentResult](cmdBus,
		paymentApp.NewProcessPaymentHandler(paymentRepo, paymentACL, eventDispatcher, lockSvc))
	bus.RegisterQuery[paymentApp.GetPaymentQuery, paymentApp.PaymentDTO](queryBus,
		paymentApp.NewGetPaymentHandler(paymentRepo))

	// Password reset service
	passwordReset := userApp.NewPasswordResetService(userRepo, dbs.User, emailChannel)

	// === 7. Outbox publisher-lər hər context üçün artıq yuxarıda göndərilib (goroutine) ===

	// === 8. HTTP layer ===
	app.router = setupRouter(auditSvc, featureFlag)

	// Controllers
	authCtrl := userWeb.NewAuthController(cmdBus, queryBus, userRepo, jwtService, passwordReset)
	twoFactorCtrl := userWeb.NewTwoFactorController(userRepo, twoFactor, jwtService)
	productCtrl := productWeb.NewProductController(cmdBus, queryBus)
	productImageCtrl := productWeb.NewProductImageController(dbs.Product)
	orderCtrl := orderWeb.NewOrderController(cmdBus, queryBus)
	paymentCtrl := paymentWeb.NewPaymentController(cmdBus, queryBus)
	notifPrefCtrl := notifWeb.NewPreferenceController(dbs.User)
	whCtrl := webhookCtrl.New(dbs.User)
	healthCtrl := health.New(dbs, rdb, cfg.RabbitMQ.URL)
	searchCtrl := search.New()
	adminCtrl := admin.New(messaging.NewDLQRepository(dbs.Order))

	// === 9. Route registration ===
	apiGroup := app.router.Group("/api")
	authMW := middleware.JWTAuth(jwtService)
	authedGroup := apiGroup.Group("")
	authedGroup.Use(authMW)

	authCtrl.RegisterRoutes(apiGroup, authMW)
	twoFactorCtrl.RegisterRoutes(apiGroup, authedGroup)
	productCtrl.RegisterRoutes(apiGroup, authedGroup)
	productImageCtrl.RegisterRoutes(apiGroup, authedGroup)
	orderCtrl.RegisterRoutes(authedGroup)
	paymentCtrl.RegisterRoutes(authedGroup)
	notifPrefCtrl.RegisterRoutes(authedGroup)
	whCtrl.RegisterRoutes(authedGroup)
	healthCtrl.RegisterRoutes(apiGroup)
	searchCtrl.RegisterRoutes(apiGroup)
	adminCtrl.RegisterRoutes(authedGroup)

	app.cleanups = append(app.cleanups,
		func() { dbs.Close() },
		func() { _ = rdb.Close() },
		func() { _ = rabbitConn.Close() },
		func() { _ = publisher.Close() },
	)

	return app, nil
}

func setupRouter(auditSvc *audit.Service, ff *featureflags.FeatureFlag) *gin.Engine {
	gin.SetMode(gin.ReleaseMode)
	r := gin.New()

	// Recovery + 7 custom middleware
	r.Use(gin.Recovery())
	r.Use(middleware.CorrelationID())
	r.Use(middleware.Idempotency())
	r.Use(middleware.Tenant())
	r.Use(middleware.APIVersion())
	r.Use(middleware.Audit(auditSvc))
	r.Use(middleware.FeatureFlag(ff))
	r.Use(middleware.ForceJSON())
	r.Use(api.ErrorHandler())

	return r
}

func (a *Application) Router() *gin.Engine { return a.router }

func (a *Application) Close() {
	for _, fn := range a.cleanups {
		fn()
	}
}
