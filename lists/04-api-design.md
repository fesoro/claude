## REST

REST prinsipləri (Stateless, Uniform Interface, Layered System, Cacheable, Client-Server, Code-on-Demand optional)
Richardson Maturity Model: Level 0 (RPC-over-HTTP), Level 1 (Resources), Level 2 (Verbs+Status), Level 3 (HATEOAS)
HTTP metodları: GET, POST, PUT, PATCH, DELETE, HEAD, OPTIONS
Safe vs Idempotent vs Cacheable method matrisi
HTTP status kodları: 2xx (uğur), 3xx (redirect), 4xx (client xəta), 5xx (server xəta)
Ən çox işlənən status kodları: 200, 201, 202, 204, 301, 302, 304, 400, 401, 403, 404, 405, 409, 410, 422, 429, 500, 502, 503, 504
Resource adlandırma: noun-based, plural, lowercase, kebab-case URL
URI dizaynı (/users/{id}/orders); nested 2 səviyyədən dərin olmamalıdır
Path param vs Query param fərqi (identifikator vs filter/sorting)

## Headers

Content-Type, Accept (content negotiation)
Accept-Language, Content-Language
Accept-Encoding (gzip, br)
Authorization: Bearer, Basic, Digest
Cache-Control, ETag, If-None-Match, Last-Modified, If-Modified-Since
Location (301/201 response)
X-Request-ID / X-Correlation-ID — tracing
X-Forwarded-For, X-Real-IP (proxy-dən)
Retry-After (429, 503)
X-RateLimit-Limit, X-RateLimit-Remaining, X-RateLimit-Reset

## Versioning

URL versioning (/v1/users)
Header versioning (Accept: application/vnd.api+json; version=1)
Query param versioning (?version=1)
Custom header (X-API-Version: 2)
Content negotiation versioning
Backwards-compatible changes (additive only — safe)
Breaking changes → yeni major version
API Deprecation strategiyası (Sunset header, warning, documentation)

## Pagination

Offset-based (?offset=20&limit=10) — sadə, dərin offset-də yavaş
Cursor-based (?cursor=abc&limit=10) — stable, scalable, real-time feed üçün
Keyset / seek pagination (?after_id=123) — sürətli, SQL-friendly
Page-based (?page=2&per_page=10)
Link header / HATEOAS pagination

## Filtering / sorting / search

Query param-lar: ?status=active&role=admin
Range filter: ?price[gte]=10&price[lte]=100
Sort: ?sort=-created_at,name (- for desc)
Search: ?q=john (full-text)
Sparse fieldsets: ?fields=id,name (JSON:API stilində)
Include/expand: ?include=author,comments

## Error response

RFC 7807 Problem Details for HTTP APIs
Standard format: type, title, status, detail, instance
Validation errors: field-level errors array
Application-specific error codes (machine-readable)
Localization: Accept-Language ilə tərcümə
Stack trace prod-da asla (debug mode-da yalnız)

## Semantic behaviors

Idempotency (GET, PUT, DELETE default idempotentdir; POST deyil)
Safe methods (GET, HEAD, OPTIONS) — side-effect yoxdur
Optimistic concurrency — If-Match (ETag)
Conditional requests — If-None-Match / If-Match
PATCH: JSON Patch (RFC 6902) vs JSON Merge Patch (RFC 7396)

## Idempotency Key (deep)

# Client
Idempotency-Key: <uuid-v4>  — header; Stripe, Adyen, Twilio pattern

# Server workflow
1. Extract Idempotency-Key from header
2. Hash key + {user_id, endpoint} → lookup in cache/DB
3a. Miss → process request, store {status, response_body, expires_at}; return response
3b. Hit + in-flight → 409 Conflict (or wait/retry)
3c. Hit + completed → return stored response (same status + body, no re-processing)
4. TTL: 24 h - 7 days (per product policy)

# Implementation (Redis-based)
SET idempotency:{key} "in-flight" NX EX 30          — claim key; NX prevents race
# ... process ...
SET idempotency:{key} {json_response} XX EX 86400   # store result

# Edge cases
Same key + different body → 422 (Stripe behaviour); or ignore silently (choose one, document it)
Key reuse after TTL → treated as new request
Network timeout (client retry with same key) → idempotent response returned
Distributed: use distributed lock (Redis SETNX) to prevent concurrent processing

# Response headers
Idempotency-Replayed: true   — signals cached response returned

## HATEOAS / discoverability

Hypermedia links (_links section)
HAL format
JSON:API spec
Link relations (self, next, prev, first, last)

## Cross-cutting concerns

CORS — preflight OPTIONS, Access-Control-Allow-*
CSRF qorunması (SameSite cookie, CSRF token)
Rate Limiting — token bucket, sliding window, fixed window
Throttling vs Quota vs Burst
Authentication: API Key, JWT, OAuth2, OIDC, Session cookie, mTLS
Authorization: RBAC, ABAC, ReBAC, policy-as-code (OPA)
Audit logging — kim, nə, nə vaxt

## Documentation

OpenAPI / Swagger (3.0, 3.1)
Redoc, Swagger UI, Stoplight
AsyncAPI — event-driven API-lər üçün
API Blueprint (köhnəlmiş)
RAML (köhnəlmiş)
Postman Collection / Insomnia export
Contract testing (Pact)

## OpenAPI 3.1 (vs 3.0)

JSON Schema full alignment — 3.0 subset-di, 3.1-də tam JSON Schema Draft 2020-12
nullable deyil artıq — type: ["string", "null"]  (3.0-da nullable: true idi)
Webhooks — openapi 3.1-də ayrı "webhooks" section (paths-dən ayrı)
$ref + sibling allowed — 3.0-da $ref yanında başqa key-lər ignore edilirdi
discriminator.mapping tam uyğun
paths optional — 3.1-də paths olmaya bilər (webhooks-only spec)
info.summary əlavə edildi
license.identifier (SPDX kodu) dəstəyi

# Minimal 3.1 spec
openapi: "3.1.0"
info:
  title: My API
  version: "1.0.0"
paths:
  /users/{id}:
    get:
      parameters:
        - name: id
          in: path
          required: true
          schema: { type: integer }
      responses:
        "200":
          description: OK
          content:
            application/json:
              schema: { $ref: "#/components/schemas/User" }
        "404":
          description: Not Found
          content:
            application/problem+json:
              schema: { $ref: "#/components/schemas/Problem" }
components:
  schemas:
    User:
      type: object
      required: [id, email]
      properties:
        id: { type: integer }
        email: { type: string, format: email }
        deleted_at: { type: ["string", "null"], format: date-time }   # 3.1 nullable
    Problem:
      type: object
      properties:
        type: { type: string, format: uri }
        title: { type: string }
        status: { type: integer }
        detail: { type: string }
  securitySchemes:
    BearerAuth:
      type: http
      scheme: bearer
      bearerFormat: JWT
security:
  - BearerAuth: []
webhooks:
  orderCreated:
    post:
      requestBody:
        content:
          application/json:
            schema: { $ref: "#/components/schemas/OrderEvent" }
      responses:
        "200":
          description: Acknowledged

## GraphQL

Query, Mutation, Subscription
Schema Definition Language (SDL)
GraphQL Schema dizaynı (types, inputs, interfaces, unions)
Resolver-lər
GraphQL N+1 problemi və DataLoader
Persisted queries (security, performance)
Schema federation (Apollo Federation, GraphQL Federation v2)
Schema stitching (köhnəlmiş)
Error extensions
Introspection (prod-da disable)
Query depth / complexity limiting
Rate limiting GraphQL-də (query cost analysis)

## gRPC / Protobuf

Protocol Buffers (.proto)
Unary vs Server Streaming vs Client Streaming vs Bidirectional Streaming
gRPC-Web (browser-dən)
gRPC-Gateway (REST ilə proxy)
Status codes (OK, CANCELLED, UNKNOWN, INVALID_ARGUMENT, ...)
Deadlines / timeouts
Interceptors (unary/stream)
Schema evolution (reserved fields, optional, required-less)
Buf CLI (modern Proto ecosystem)

## Real-time / streaming APIs

Server-Sent Events (SSE) — tək yönlü stream
WebSocket — bidirectional
Long polling (köhnəlmiş alternative)
Webhook vs polling tradeoff
MQTT — IoT real-time
AMQP — RabbitMQ

## Webhook design (deep)

# Signing (HMAC-SHA256 — GitHub/Stripe/Shopify pattern)
secret = random 32-byte hex (per-subscription)
payload = raw request body bytes (do NOT parse before verify)
signature = HMAC-SHA256(secret, payload)
header: X-Webhook-Signature: sha256=<hex>  OR  X-Hub-Signature-256: sha256=<hex>

# Verification (PHP)
$expected = 'sha256=' . hash_hmac('sha256', $rawBody, $secret);
if (!hash_equals($expected, $header)) { return 401; }
# hash_equals prevents timing attacks

# Verification (Go)
mac := hmac.New(sha256.New, []byte(secret))
mac.Write(body)
expected := "sha256=" + hex.EncodeToString(mac.Sum(nil))
if !hmac.Equal([]byte(expected), []byte(header)) { ... }

# Replay attack defense
X-Webhook-Timestamp: 1714123456  — unix seconds
if abs(time.now() - timestamp) > 300 { return 401 }  — 5 min window
Include timestamp in signature: HMAC(secret, timestamp + "." + body)

# Retry strategy (sender side)
1st retry: 5s
2nd retry: 30s
3rd-5th:   exponential (1min, 5min, 30min)
Max:       24h; give up → dead letter / alert
Respect Retry-After header if present

# Delivery guarantees
At-least-once by default (retry on non-2xx or timeout)
Receiver must be idempotent (use event_id for deduplication)

# Payload design
{
  "event_id": "evt_abc123",          — unique per event (idempotency key)
  "event_type": "order.created",
  "created_at": "2024-04-27T10:00:00Z",
  "api_version": "2024-04-01",       — version pin (breaking changes don't break old receivers)
  "data": { ... }
}

# Fan-out
One domain event → multiple webhook subscriptions
Use queue (Kafka/SQS) between domain event and webhook dispatcher
Dispatcher: pull from queue → POST → ack or requeue

# Security extras
Allowlist sender IPs (optional; breaks CDN-based delivery)
Rotate secrets without downtime: send old+new sig during rotation window
mTLS for high-security M2M webhooks

## Resilience

Idempotency Key header pattern
Retry-After
Circuit breaker statusu header-də (X-Circuit-Breaker: open)
Graceful degradation
Fallback responses
Bulkhead (resource partitioning per client)

## Performance

HTTP caching (Cache-Control: max-age, s-maxage, public, private, no-cache)
CDN caching
Conditional GET (304 Not Modified)
Compression (gzip, Brotli)
HTTP/2 (multiplexing, server push)
HTTP/3 / QUIC (UDP-based)
Partial responses (Range header, 206)
Batch API — çoxsayı resource-u tək request-də

## API Gateway / patterns

API Gateway pattern (Kong, AWS API Gateway, KrakenD, Tyk)
BFF (Backend for Frontend) — Netflix, SoundCloud
Aggregation gateway
Protocol translation (REST → gRPC)
Anti-corruption layer at gateway
Service mesh vs gateway

## Security

OWASP API Top 10 (BOLA, broken auth, excessive data exposure, mass assignment, ...)
Input validation / whitelisting
Output encoding
TLS 1.2+ məcburi
HSTS header
Secret rotation
JWT best practices (short-lived, refresh tokens, don't store sensitive data)
mTLS (mutual TLS) — service-to-service
Mass assignment qorunması (allowlist)
