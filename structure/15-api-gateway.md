# API Gateway Architecture (Lead)

Microservices-ə qarşı client-lərin **tək giriş nöqtəsi**. Cross-cutting concerns-i
(auth, rate limiting, logging, routing) mərkəzləşdirir, backend service-ləri client-dən gizlədir.

**Əsas anlayışlar:**
- **Gateway** — Bütün client request-lərini qəbul edir
- **Routing** — Request-i düzgün backend service-ə yönləndirir
- **Auth/AuthZ** — JWT validation, API key check, OAuth2
- **Rate Limiting** — Client başına request limiti
- **Circuit Breaker** — Backend xəta zamanı fallback
- **Request Aggregation** — Bir client call-u → bir neçə service call
- **Protocol Translation** — HTTP/REST ↔ gRPC, GraphQL, WebSocket

**API Gateway vs BFF fərqi:**
- API Gateway — texniki, infrastructure-level, generic (bütün client-lər üçün eyni)
- BFF — business-level, client-specific (hər frontend üçün ayrı)

---

## Laravel (Custom Gateway)

```
project/
├── api-gateway/
│   ├── app/
│   │   ├── Http/
│   │   │   ├── Controllers/
│   │   │   │   └── GatewayController.php      # Bütün request-lər buraya gəlir
│   │   │   └── Middleware/
│   │   │       ├── AuthenticateApiKey.php      # API key validation
│   │   │       ├── ValidateJwtToken.php        # JWT check
│   │   │       ├── RateLimiter.php             # Per-client rate limit
│   │   │       ├── RequestLogger.php           # Audit log
│   │   │       ├── CorrelationId.php           # Request tracing
│   │   │       └── CircuitBreaker.php          # Fault tolerance
│   │   │
│   │   ├── Services/
│   │   │   ├── Router/
│   │   │   │   ├── RouteRegistry.php          # Route map: /users → user-service
│   │   │   │   └── LoadBalancer.php           # Round-robin / weighted
│   │   │   ├── Proxy/
│   │   │   │   └── ServiceProxy.php           # Forward request to backend
│   │   │   ├── Auth/
│   │   │   │   ├── JwtValidator.php
│   │   │   │   └── ApiKeyValidator.php
│   │   │   ├── RateLimit/
│   │   │   │   └── RedisRateLimiter.php
│   │   │   └── CircuitBreaker/
│   │   │       └── RedisCircuitBreaker.php
│   │   │
│   │   └── Config/
│   │       └── routes.php                     # Route definitions
│   │
│   ├── routes/api.php
│   └── Dockerfile
│
├── services/
│   ├── user-service/
│   ├── order-service/
│   └── product-service/
│
└── infrastructure/
    └── docker-compose.yml
```

---

## Spring Boot (Spring Cloud Gateway)

```
project/
├── api-gateway/                               # Spring Cloud Gateway
│   ├── src/main/java/com/example/gateway/
│   │   ├── GatewayApplication.java
│   │   │
│   │   ├── config/
│   │   │   ├── RouteConfig.java               # Route definitions (code-based)
│   │   │   ├── SecurityConfig.java
│   │   │   ├── RateLimiterConfig.java
│   │   │   └── CircuitBreakerConfig.java
│   │   │
│   │   ├── filter/                            # Global + route-level filters
│   │   │   ├── global/
│   │   │   │   ├── AuthFilter.java            # JWT/API key validation
│   │   │   │   ├── CorrelationIdFilter.java   # Add trace ID to headers
│   │   │   │   ├── RequestLoggingFilter.java
│   │   │   │   └── ResponseLoggingFilter.java
│   │   │   └── route/
│   │   │       ├── ModifyRequestFilter.java
│   │   │       └── ModifyResponseFilter.java
│   │   │
│   │   ├── fallback/
│   │   │   └── FallbackController.java        # Circuit breaker fallback responses
│   │   │
│   │   ├── ratelimit/
│   │   │   ├── CustomKeyResolver.java         # Rate limit key: user ID / IP
│   │   │   └── RateLimitProperties.java
│   │   │
│   │   └── loadbalancer/
│   │       └── CustomLoadBalancer.java
│   │
│   └── src/main/resources/
│       └── application.yml                    # YAML-based route config
│
├── service-registry/                          # Eureka / Consul
│   └── DiscoveryApplication.java
│
├── user-service/
├── order-service/
└── product-service/
```

---

## Golang (Custom Gateway)

```
project/
├── api-gateway/
│   ├── cmd/
│   │   └── main.go
│   │
│   ├── internal/
│   │   ├── router/
│   │   │   ├── router.go                      # Route registration
│   │   │   └── registry.go                    # Service URL registry
│   │   │
│   │   ├── proxy/
│   │   │   ├── reverse_proxy.go               # httputil.ReverseProxy wrapper
│   │   │   └── load_balancer.go               # Round-robin balancer
│   │   │
│   │   ├── middleware/
│   │   │   ├── auth.go                        # JWT / API key validation
│   │   │   ├── rate_limiter.go                # Token bucket (Redis)
│   │   │   ├── circuit_breaker.go             # gobreaker integration
│   │   │   ├── correlation_id.go              # Request tracing
│   │   │   ├── logging.go                     # Structured access log
│   │   │   └── cors.go
│   │   │
│   │   ├── auth/
│   │   │   ├── jwt_validator.go
│   │   │   └── api_key_validator.go
│   │   │
│   │   ├── ratelimit/
│   │   │   └── redis_limiter.go               # Per-client Redis rate limit
│   │   │
│   │   └── config/
│   │       ├── config.go
│   │       └── routes.yaml                    # Route config
│   │
│   └── go.mod
│
├── user-service/
├── order-service/
└── infrastructure/
    └── docker-compose.yml
```

---

## Route Config Nümunəsi (Spring Cloud Gateway)

```yaml
# application.yml
spring:
  cloud:
    gateway:
      routes:
        - id: user-service
          uri: lb://user-service          # Load-balanced Eureka service
          predicates:
            - Path=/api/v1/users/**
          filters:
            - StripPrefix=2              # /api/v1/users → /users
            - name: CircuitBreaker
              args:
                name: userServiceCB
                fallbackUri: forward:/fallback/users
            - name: RequestRateLimiter
              args:
                redis-rate-limiter.replenishRate: 100
                redis-rate-limiter.burstCapacity: 200

        - id: order-service
          uri: lb://order-service
          predicates:
            - Path=/api/v1/orders/**
          filters:
            - StripPrefix=2
            - name: CircuitBreaker
              args:
                name: orderServiceCB
                fallbackUri: forward:/fallback/orders
```
