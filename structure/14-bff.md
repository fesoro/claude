# Backend for Frontend (BFF) (Senior)

Hər frontend (mobile, web, third-party) üçün ayrı bir backend layer.
Ümumi API əvəzinə, hər client öz ehtiyaclarına uyğun backend-ə sahib olur.

**Əsas anlayışlar:**
- **BFF** — Konkret bir frontend üçün optimallaşdırılmış backend
- **Aggregation** — Bir neçə microservice-dən data toplayıb birləşdirir
- **Tailored Response** — Hər client-ə lazım olan formatda cavab
- **Owned by Frontend Team** — Frontend team öz BFF-ini idarə edir
- **No Shared BFF** — Bölünmüş BFF = anti-pattern (Generic BFF = API Gateway)

**Nə vaxt lazımdır:**
- Mobile tətbiq web-dən fərqli data formatı tələb edir
- Frontend team-ləri ayrıdır, backend bottleneck yaranır
- Müxtəlif client-lər üçün fərqli aggregation lazımdır
- Client-specific business logic var (mobile offline caching, web pagination vs scroll)

---

## Laravel

```
project/
│
├── bff-web/                                   # Web BFF (web team idarə edir)
│   ├── app/
│   │   ├── Http/Controllers/
│   │   │   ├── DashboardController.php        # Aggregates: user + stats + notifications
│   │   │   ├── ProductListController.php      # Web-specific pagination
│   │   │   ├── CheckoutController.php         # Full checkout flow
│   │   │   └── UserProfileController.php
│   │   ├── Services/
│   │   │   ├── DashboardAggregator.php        # Combines multiple service calls
│   │   │   └── ProductAggregator.php
│   │   ├── Transformers/                      # Web-specific response shapes
│   │   │   ├── DashboardTransformer.php
│   │   │   └── ProductTransformer.php
│   │   └── Clients/                           # HTTP clients to backend services
│   │       ├── UserServiceClient.php
│   │       ├── OrderServiceClient.php
│   │       ├── ProductServiceClient.php
│   │       └── NotificationServiceClient.php
│   ├── routes/api.php
│   └── Dockerfile
│
├── bff-mobile/                                # Mobile BFF (mobile team idarə edir)
│   ├── app/
│   │   ├── Http/Controllers/
│   │   │   ├── HomeScreenController.php       # Single call → home screen data
│   │   │   ├── ProductDetailController.php    # Compact product + reviews + related
│   │   │   ├── OrderTrackingController.php    # Real-time order status
│   │   │   └── QuickCheckoutController.php    # Simplified mobile checkout
│   │   ├── Services/
│   │   │   ├── HomeScreenAggregator.php       # Combines 5 service calls into 1
│   │   │   └── OfflineSyncService.php         # Delta sync for offline support
│   │   ├── Transformers/
│   │   │   ├── HomeScreenTransformer.php      # Compact format for mobile
│   │   │   └── ProductCardTransformer.php     # Smaller payload than web
│   │   └── Clients/
│   │       ├── UserServiceClient.php
│   │       ├── ProductServiceClient.php
│   │       └── OrderServiceClient.php
│   ├── routes/api.php
│   └── Dockerfile
│
├── bff-partner/                               # Partner API BFF (B2B)
│   ├── app/
│   │   ├── Http/Controllers/
│   │   │   ├── CatalogController.php          # Bulk product data
│   │   │   ├── OrderWebhookController.php     # Webhook-based order sync
│   │   │   └── InventoryController.php
│   │   ├── Services/
│   │   │   └── PartnerDataNormalizer.php
│   │   └── Clients/
│   │       ├── CatalogServiceClient.php
│   │       └── InventoryServiceClient.php
│   ├── routes/api.php
│   └── Dockerfile
│
├── services/                                  # Backend services (shared)
│   ├── user-service/
│   ├── order-service/
│   ├── product-service/
│   └── notification-service/
│
└── infrastructure/
    ├── api-gateway/                           # Routes to correct BFF
    │   └── nginx.conf                        # /web/* → bff-web, /mobile/* → bff-mobile
    └── docker-compose.yml
```

---

## Spring Boot (Java)

```
project/
│
├── bff-web/                                   # Web BFF
│   ├── src/main/java/com/example/bff/web/
│   │   ├── WebBffApplication.java
│   │   ├── controller/
│   │   │   ├── DashboardController.java
│   │   │   ├── ProductController.java
│   │   │   └── CheckoutController.java
│   │   ├── service/
│   │   │   ├── DashboardAggregationService.java
│   │   │   └── ProductAggregationService.java
│   │   ├── dto/                               # Web-specific response DTOs
│   │   │   ├── DashboardResponse.java
│   │   │   └── ProductPageResponse.java
│   │   ├── client/                            # Feign or WebClient
│   │   │   ├── UserServiceClient.java
│   │   │   ├── OrderServiceClient.java
│   │   │   ├── ProductServiceClient.java
│   │   │   └── NotificationServiceClient.java
│   │   └── config/
│   │       └── WebClientConfig.java
│   └── src/main/resources/
│       └── application.yml                    # service URLs
│
├── bff-mobile/                                # Mobile BFF
│   ├── src/main/java/com/example/bff/mobile/
│   │   ├── MobileBffApplication.java
│   │   ├── controller/
│   │   │   ├── HomeScreenController.java
│   │   │   ├── ProductDetailController.java
│   │   │   └── OrderTrackingController.java
│   │   ├── service/
│   │   │   ├── HomeScreenAggregator.java      # Parallel calls via CompletableFuture
│   │   │   └── OfflineSyncService.java
│   │   ├── dto/
│   │   │   ├── HomeScreenResponse.java        # Compact: smaller images, less fields
│   │   │   └── ProductCardDto.java
│   │   └── client/
│   │       ├── UserServiceClient.java
│   │       └── ProductServiceClient.java
│   └── src/main/resources/
│       └── application.yml
│
├── bff-partner/                               # B2B Partner BFF
│   ├── src/main/java/com/example/bff/partner/
│   │   ├── PartnerBffApplication.java
│   │   ├── controller/
│   │   │   ├── CatalogController.java
│   │   │   └── OrderSyncController.java
│   │   ├── service/
│   │   └── client/
│   └── src/main/resources/
│       └── application.yml
│
└── infrastructure/
    ├── docker-compose.yml
    └── kubernetes/
        ├── bff-web-deployment.yaml
        ├── bff-mobile-deployment.yaml
        └── bff-partner-deployment.yaml
```

---

## Golang

```
project/
├── bff-web/
│   ├── cmd/
│   │   └── main.go
│   ├── internal/
│   │   ├── handler/
│   │   │   ├── dashboard.go
│   │   │   ├── product.go
│   │   │   └── checkout.go
│   │   ├── aggregator/
│   │   │   ├── dashboard_aggregator.go        # Concurrent service calls
│   │   │   └── product_aggregator.go
│   │   ├── dto/
│   │   │   ├── dashboard_response.go
│   │   │   └── product_response.go
│   │   ├── client/
│   │   │   ├── user_client.go
│   │   │   ├── order_client.go
│   │   │   └── product_client.go
│   │   └── config/
│   └── go.mod
│
├── bff-mobile/
│   ├── cmd/
│   │   └── main.go
│   ├── internal/
│   │   ├── handler/
│   │   │   ├── home_screen.go
│   │   │   ├── product_detail.go
│   │   │   └── order_tracking.go
│   │   ├── aggregator/
│   │   │   └── home_screen_aggregator.go      # errgroup concurrent calls
│   │   ├── dto/
│   │   │   ├── home_screen_response.go        # Compact mobile format
│   │   │   └── product_card.go
│   │   └── client/
│   │       ├── user_client.go
│   │       └── product_client.go
│   └── go.mod
│
└── infrastructure/
    └── docker-compose.yml
```

---

## Aggregation Nümunəsi (Golang Mobile BFF)

```go
// Mobile home screen: 1 API call → 5 backend call

func (a *HomeScreenAggregator) Aggregate(ctx context.Context, userID string) (*HomeScreenResponse, error) {
    var (
        user          *UserData
        orders        []RecentOrder
        recommendations []Product
        notifications int
        err           error
    )

    g, ctx := errgroup.WithContext(ctx)

    g.Go(func() error { user, err = a.userClient.Get(ctx, userID); return err })
    g.Go(func() error { orders, err = a.orderClient.RecentOrders(ctx, userID, 3); return err })
    g.Go(func() error { recommendations, err = a.productClient.Recommended(ctx, userID, 6); return err })
    g.Go(func() error { notifications, err = a.notifClient.UnreadCount(ctx, userID); return err })

    if err := g.Wait(); err != nil {
        return nil, err
    }

    return &HomeScreenResponse{
        User:            toCompactUser(user),           // Mobile-specific DTO
        RecentOrders:    toOrderCards(orders),
        Recommendations: toProductCards(recommendations),
        NotifCount:      notifications,
    }, nil
}
```
