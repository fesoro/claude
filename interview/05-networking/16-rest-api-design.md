# REST API Design Principles (Senior ⭐⭐⭐)

## İcmal

REST (Representational State Transfer) API dizaynı — HTTP protokolunun üzərindən resurs əsaslı, stateless kommunikasiya qurmaq üçün standartlaşdırılmış yanaşmadır. Yaxşı dizayn olunmuş REST API istifadəsi asan, genişləndirilə bilən, versioning-ə uyğun və komandalar arasında tutarlıdır.

Senior developer olaraq müsahibədə bu sualı eşidəcəksiniz: "Yaxşı REST API nə deməkdir?" — bu cavab sizin arxitektura düşüncə tərzinizi ortaya qoyur.

---

## Niyə Vacibdir

- Pis dizayn olunmuş API breaking changes tələb edir — bu isə bütün client-ları sındırır
- API contract-ı team-lər arasında kommunikasiyanın əsasıdır (mobile, frontend, third-party)
- Tutarsız naming/status code convention-lar debugging-i çətinləşdirir
- Production API-lər illər boyu yaşayır — ilk dizayn qərarları uzun müddət izini qoyur

---

## Əsas Anlayışlar

### 1. Resource Naming Conventions

**Əsas qayda:** URL-lər resursu (isim) təmsil edir, əməliyyatı (feil) yox.

```
# Düzgün — isim, cəm, iyerarxik
GET  /users
GET  /users/42
GET  /users/42/orders
GET  /users/42/orders/7
POST /users/42/orders

# Yanlış — feil URL-də
GET  /getUsers
POST /createUser
POST /deleteUser/42
GET  /user/42/getOrders
```

**Qaydalar:**
- Həmişə **cəm** (plural) istifadə et: `/users`, `/orders`, `/products`
- **kebab-case** istifadə et: `/payment-methods`, `/api-keys` (camelCase yox)
- Alt resurslar üçün iyerarxik yol: `/users/{id}/posts/{postId}/comments`
- İyerarxiyanı 3 səviyyədən dərindən saxlama — daha dərinsə, müstəqil endpoint düşün

### 2. HTTP Methods — Nə Zaman Hansını İstifadə Et

| Method | İstifadə | Idempotent | Safe |
|--------|----------|-----------|------|
| GET | Resurs oxu | Bəli | Bəli |
| POST | Yeni resurs yarat | Xeyr | Xeyr |
| PUT | Resursu tam əvəzlə | Bəli | Xeyr |
| PATCH | Resursu qismən yenilə | Bəli* | Xeyr |
| DELETE | Resursu sil | Bəli | Xeyr |

> *PATCH idempotent olmaya da bilər — tətbiqə görə dəyişir. Məs: `PATCH /counter {increment: 1}` idempotent deyil.

**PUT vs PATCH fərqi:**
```json
// PUT — tam obyekti göndər
PUT /users/42
{
  "name": "Əli",
  "email": "ali@example.com",
  "role": "admin"
}

// PATCH — yalnız dəyişən sahəni göndər
PATCH /users/42
{
  "email": "ali.new@example.com"
}
```

### 3. HTTP Status Codes — Hərtərəfli Bələdçi

#### 2xx — Uğurlu Cavablar

| Kod | Ad | Nə Zaman |
|-----|----|---------|
| 200 | OK | GET, PUT, PATCH uğurlu olduqda |
| 201 | Created | POST ilə resurs yaradıldıqda — `Location` header əlavə et |
| 202 | Accepted | Sorğu qəbul edildi, amma async icra olunacaq (job queue) |
| 204 | No Content | DELETE uğurlu, body yoxdur; PATCH cavab vermədikdə |

#### 3xx — Yönləndirilmə

| Kod | Ad | Nə Zaman |
|-----|----|---------|
| 301 | Moved Permanently | URL dəyişdi, client bookmark-ı yeniləsin |
| 302 | Found | Müvəqqəti yönləndirmə |
| 304 | Not Modified | ETag/If-None-Match uyğun gəldi — body göndərmə, cache istifadə et |

#### 4xx — Client Xətaları

| Kod | Ad | Nə Zaman |
|-----|----|---------|
| 400 | Bad Request | Validation xətası, malformed JSON, məntiqi xəta |
| 401 | Unauthorized | Authentication yoxdur və ya etibarsızdır |
| 403 | Forbidden | Authentication var, amma icazə yoxdur |
| 404 | Not Found | Resurs tapılmadı |
| 405 | Method Not Allowed | Bu endpoint bu HTTP methodunu dəstəkləmir |
| 409 | Conflict | State konflikti (məs: email artıq mövcuddur, optimistic lock conflict) |
| 410 | Gone | Resurs əvvəl var idi, indi yoxdur (404-dan fərqli olaraq keçicilik yox, qətilik) |
| 422 | Unprocessable Entity | Syntax düzgündür, amma semantic xəta var (business rule pozulması) |
| 429 | Too Many Requests | Rate limit keçildi — `Retry-After` header əlavə et |

> **400 vs 422 fərqi:** 400 — malformed request (JSON parse edilmir). 422 — format düzgündür, amma məzmun qəbul edilmir (məs: tarix keçmişdədir).

#### 5xx — Server Xətaları

| Kod | Ad | Nə Zaman |
|-----|----|---------|
| 500 | Internal Server Error | Gözlənilməz server xətası |
| 502 | Bad Gateway | Upstream servis cavab vermir |
| 503 | Service Unavailable | Servis müvəqqəti deaktivdir (maintenance, overload) |
| 504 | Gateway Timeout | Upstream servis timeout verdi |

### 4. Request/Response Envelope Design

Tutarlı structure seç və onu bütün API-də saxla:

```json
// Tək resurs cavabı
{
  "data": {
    "id": 42,
    "name": "Əli Həsənov",
    "email": "ali@example.com",
    "created_at": "2026-01-15T10:30:00Z"
  }
}

// Kolleksiya cavabı
{
  "data": [...],
  "meta": {
    "total": 150,
    "page": 1,
    "per_page": 20
  }
}

// Xəta cavabı
{
  "error": {
    "code": "VALIDATION_FAILED",
    "message": "Validation failed",
    "details": [...]
  }
}
```

### 5. RFC 7807 Problem Details Standartı

Xəta response-ları üçün standart format — `Content-Type: application/problem+json`:

```json
{
  "type": "https://api.example.com/errors/validation-failed",
  "title": "Validation Failed",
  "status": 422,
  "detail": "The email field must be a valid email address.",
  "instance": "/users/register",
  "errors": [
    {
      "field": "email",
      "message": "Invalid email format",
      "value": "not-an-email"
    },
    {
      "field": "password",
      "message": "Must be at least 8 characters",
      "value": null
    }
  ]
}
```

**Sahələr:**
- `type` — xəta növünü izah edən URI (documentation link)
- `title` — insan oxunaqlı qısa izah (status code-a görə dəyişmir)
- `status` — HTTP status kodu (integer)
- `detail` — bu konkret halda daha ətraflı izah
- `instance` — xətanın baş verdiyi URI

### 6. Pagination — Cavabda Necə Qaytarmaq

#### Offset-based Pagination

```json
// Request: GET /posts?page=2&per_page=20
{
  "data": [...],
  "meta": {
    "current_page": 2,
    "per_page": 20,
    "total": 543,
    "total_pages": 28,
    "has_more": true
  },
  "links": {
    "first": "/posts?page=1&per_page=20",
    "prev": "/posts?page=1&per_page=20",
    "next": "/posts?page=3&per_page=20",
    "last": "/posts?page=28&per_page=20"
  }
}
```

#### Cursor-based Pagination (böyük dataset-lər üçün)

```json
// Request: GET /posts?cursor=eyJpZCI6MTAwfQ&limit=20
{
  "data": [...],
  "meta": {
    "limit": 20,
    "has_more": true,
    "next_cursor": "eyJpZCI6MTIwfQ",
    "prev_cursor": "eyJpZCI6MTAxfQ"
  }
}
```

> Cursor adətən `base64(JSON{id, created_at})` şəklindədir. Client-a opaque string kimi göstər.

### 7. Filtering və Sorting

```
# Filtering
GET /products?category=electronics&price_min=100&price_max=500&in_stock=true

# Sorting (prefix ilə yön göstər)
GET /products?sort=price          # ascending
GET /products?sort=-price         # descending (minus prefix)
GET /products?sort=-created_at,name  # çoxlu sahə

# Field selection (sparse fieldsets)
GET /users?fields=id,name,email

# Search
GET /products?q=iphone&category=phones
```

### 8. Idempotency — Idempotency-Key Header Pattern

POST endpoint-ləri default olaraq idempotent deyil. Network retry-larda duplikat resurs yaranmasının qarşısını almaq üçün:

```
POST /payments
Idempotency-Key: 550e8400-e29b-41d4-a716-446655440000
Content-Type: application/json

{
  "amount": 9900,
  "currency": "AZN",
  "card_id": "card_abc123"
}
```

**Server logic:**
1. `Idempotency-Key` header-ini yoxla
2. Bu key ilə əvvəl sorğu gəlibsə — eyni cavabı qaytar (yeni əməliyyat etmə)
3. Gəlməyibsə — əməliyyatı icra et və key-i saxla (TTL: 24-72 saat)

### 9. Content Negotiation

```
# Client istədiyi formatı bildirir
GET /reports/2026-01
Accept: application/json

GET /reports/2026-01
Accept: application/pdf

GET /reports/2026-01
Accept: text/csv

# Server cavab formatını bildirir
Content-Type: application/json; charset=utf-8

# Versiya negotiation (media type versioning)
Accept: application/vnd.api.v2+json
```

### 10. HATEOAS

Hypermedia As The Engine Of Application State — response içində mövcud əməliyyatların link-lərini qaytarırsan:

```json
{
  "data": {
    "id": 42,
    "status": "pending",
    "total": 15000
  },
  "links": {
    "self":    "/orders/42",
    "confirm": "/orders/42/confirm",
    "cancel":  "/orders/42/cancel",
    "items":   "/orders/42/items"
  }
}
```

**Müsbət cəhətlər:**
- Client API endpoint-lərini hardcode etmək məcburiyyətində deyil
- Server-side URL dəyişsə client avtomatik uyğunlaşır
- "Maşın tərəfindən oxunaqlı" API sənədləşməsi

**Mənfi cəhətlər:**
- Praktikada nadir istifadə olunur — client-lar link-ləri nadir izləyir
- Response size-ı artırır
- Real dünyada əksər REST API-lər Level 2 Richardson Maturity Model-dadır (HATEOAS-sız)

**Nə zaman istifadə et:** Workflow-driven API-lər (state machine), public API-lər (üçüncü tərəflər üçün), HAL/JSON:API standard tətbiq edəndə.

**Nə zaman istifadə etmə:** Mobile/frontend tərəf bilir hara getməli, documentation var, performance kritikdir.

### 11. API Consistency

Böyük komandada tutarlılığı saxlamaq üçün:

- **API Design Guide (style guide)** sənədi yaz — naming, errors, pagination formatı
- **OpenAPI/Swagger spec-first** yanaşma — əvvəlcə spec yaz, sonra kod
- **Linting tools** istifadə et: Spectral, Stoplight
- **Code review checklist** API dəyişiklikləri üçün
- **API Gateway** — mərkəzi validation, rate limiting, logging

### 12. Versioning (Xülasə)

Ətraflı bax: `08-api-versioning.md`

Qısa: URL path versioning (`/v1/users`) ən çox istifadə olunan, ən aydın yanaşmadır. Header versioning (`Api-Version: 2`) daha "RESTful"-dur amma browser-da test etmək çətindir.

---

## Praktik Baxış

### Trade-off-lar

| Qərar | Option A | Option B |
|-------|----------|----------|
| Pagination | Offset (asan) | Cursor (sürətli, böyük data) |
| Error format | Custom | RFC 7807 (standart) |
| Versioning | URL path | Header/Media type |
| Envelope | Data wrapper | Flat response |
| Filtering | Query params | POST body (POST /search) |

### Nə Zaman Qayda Pozulur

- `POST /search` — GET URL limitini keçən mürəkkəb filterlər üçün məqbuldur
- `POST /logout` — resurs yox, əməliyyat, amma qəbul edilmişdir
- `POST /orders/42/cancel` — "cancel" feil amma state transition kimi düşünülür

### Common Mistakes

- Bütün xətalar üçün 200 qaytarmaq (`{"success": false}` body-si ilə)
- 404 əvəzinə hər kəsə 403 qaytarmaq (resource existence-i gizlətmək istəyirsən — bəzən məqbuldur, amma tutarlı ol)
- Silmə əməliyyatında body qaytarmaq (204 + boş body kifayətdir)
- `GET /users?action=delete` — method semantikasını pozmaq

---

## Nümunələr

### Tipik Interview Sualı

> "REST API dizayn prinsiplerinizi izah edin. Ödəniş sistemi üçün bir neçə endpoint dizayn edin."

---

### Güclü Cavab

"REST API-ı resource-centric şəkildə dizayn edirəm. Əsas prinsiplərim:

**URL dizaynı** — URL-lər isim olmalıdır, feil yox. Cəm formada, hierarchical. Məsələn: `/payments`, `/payments/{id}/refunds`. Feil əməliyyatlar HTTP methodları ilə ifadə olunur.

**Status code-lar** — semantic məna daşımalıdır. 201 yaradıldıqda, 202 async sorğuda, 409 conflict-də, 422 business rule pozulmasında. Xətalarda 200 heç vaxt qaytarmıram.

**Error format** — komandada RFC 7807 standartını tətbiq edirəm: `type`, `title`, `status`, `detail` sahələri. Hər client eyni structure gözləyir.

**Pagination** — böyük dataset-lərdə cursor-based istifadə edirəm, offset-in performance problemi var. Response-da `next_cursor`, `has_more` qaytarıram.

**Idempotency** — POST endpoint-lərə `Idempotency-Key` header dəstəyi əlavə edirəm, xüsusilə ödəniş kimi kritik əməliyyatlarda.

Komanda consistency-si üçün OpenAPI spec-first yanaşma istifadə edirəm."

---

### Kod/Nümunə (JSON nümunələri)

#### Yaxşı vs Pis URL Dizaynı

```
# Yaxşı
GET    /v1/users
GET    /v1/users/42
POST   /v1/users
PUT    /v1/users/42
DELETE /v1/users/42
GET    /v1/users/42/orders
POST   /v1/orders/7/refund    # state transition — məqbuldur

# Pis
GET    /v1/getUsers
POST   /v1/createUser
GET    /v1/user/42/getOrders
POST   /v1/deleteUser?id=42
GET    /v1/users/42/ordersOfUser
```

#### RFC 7807 Xəta Response

```json
HTTP/1.1 422 Unprocessable Entity
Content-Type: application/problem+json

{
  "type": "https://api.payment.az/errors/insufficient-funds",
  "title": "Insufficient Funds",
  "status": 422,
  "detail": "Account balance (₼45.00) is less than the requested amount (₼150.00).",
  "instance": "/v1/payments",
  "account_id": "acc_789",
  "available_balance": 4500,
  "requested_amount": 15000
}
```

#### Paginated Response (cursor-based)

```json
HTTP/1.1 200 OK

{
  "data": [
    {
      "id": "ord_101",
      "total": 4500,
      "status": "completed",
      "created_at": "2026-04-20T09:15:00Z"
    },
    {
      "id": "ord_102",
      "total": 12000,
      "status": "pending",
      "created_at": "2026-04-21T14:30:00Z"
    }
  ],
  "meta": {
    "limit": 20,
    "has_more": true,
    "next_cursor": "eyJpZCI6Im9yZF8xMDIiLCJjcmVhdGVkX2F0IjoiMjAyNi0wNC0yMVQxNDozMDowMFoifQ==",
    "prev_cursor": null
  }
}

// Növbəti səhifə
GET /v1/orders?cursor=eyJpZCI6Im9yZF8xMDIiLCJjcmVhdGVkX2F0IjoiMjAyNi0wNC0yMVQxNDozMDowMFoifQ==&limit=20
```

#### 201 Created + Location Header

```
HTTP/1.1 201 Created
Location: /v1/users/42
Content-Type: application/json

{
  "data": {
    "id": 42,
    "name": "Fərid Məmmədov",
    "email": "farid@example.com",
    "created_at": "2026-04-26T08:00:00Z"
  }
}
```

#### HATEOAS Response Nümunəsi

```json
HTTP/1.1 200 OK

{
  "data": {
    "id": "ord_55",
    "status": "pending_payment",
    "total": 25000,
    "currency": "AZN"
  },
  "links": {
    "self":     { "href": "/v1/orders/ord_55", "method": "GET" },
    "pay":      { "href": "/v1/orders/ord_55/pay", "method": "POST" },
    "cancel":   { "href": "/v1/orders/ord_55/cancel", "method": "POST" },
    "items":    { "href": "/v1/orders/ord_55/items", "method": "GET" }
  }
}

// Status "paid" olandan sonra:
{
  "data": {
    "id": "ord_55",
    "status": "paid"
  },
  "links": {
    "self":    { "href": "/v1/orders/ord_55", "method": "GET" },
    "refund":  { "href": "/v1/orders/ord_55/refund", "method": "POST" },
    "items":   { "href": "/v1/orders/ord_55/items", "method": "GET" }
  }
}
// Qeyd: "pay" və "cancel" link-ləri artıq yoxdur — state dəyişdi
```

---

### Anti-Pattern Nümunəsi

```json
// YANLISH — 200 OK ilə xəta qaytarmaq
HTTP/1.1 200 OK
{
  "success": false,
  "error": "User not found",
  "code": 404
}

// DÜZGÜN
HTTP/1.1 404 Not Found
{
  "type": "https://api.example.com/errors/not-found",
  "title": "Resource Not Found",
  "status": 404,
  "detail": "User with ID 42 was not found."
}
```

```json
// YANLISH — feil URL-də, inconsistent naming
POST /api/createNewUserAccount
POST /api/user_deletion?userId=42
GET  /api/getUserOrderList?userId=42

// DÜZGÜN
POST   /v1/users
DELETE /v1/users/42
GET    /v1/users/42/orders
```

```json
// YANLISH — validation xətasında 500 qaytarmaq
HTTP/1.1 500 Internal Server Error
{
  "message": "Unhandled exception"
}

// DÜZGÜN — validation 400 və ya 422-dir
HTTP/1.1 400 Bad Request
{
  "type": "https://api.example.com/errors/validation",
  "title": "Validation Failed",
  "status": 400,
  "errors": [
    { "field": "email", "message": "Required field is missing" }
  ]
}
```

---

## Praktik Tapşırıqlar

### Tapşırıq 1 — URL Dizayn

Aşağıdakı resurslar üçün CRUD endpoint-lərini dizayn et:
- Blog (posts, comments, tags, authors)
- E-commerce (products, categories, orders, order-items, refunds)

Nüans: `orders/{id}/status` endpoint-i PUT mi, PATCH mi olmalıdır? Niyə?

### Tapşırıq 2 — Status Code Audit

Mövcud bir API-nı götür (öz proyektin) və status code-ları yoxla:
- Hər validation xəta hansı kod qaytarır?
- Delete əməliyyatı nə qaytarır?
- Async əməliyyatlar 202 istifadə edirmi?

### Tapşırıq 3 — RFC 7807 Error Handler

PHP/Laravel-də middleware yaz ki, bütün xətalari RFC 7807 formatında qaytarsın:
```php
class ProblemDetailsHandler
{
    public function handle(Request $request, Throwable $e): JsonResponse
    {
        // ValidationException → 422
        // ModelNotFoundException → 404
        // AuthenticationException → 401
        // AuthorizationException → 403
        // Throwable → 500
    }
}
```

### Tapşırıq 4 — Pagination Seçimi

Aşağıdakı ssenarilər üçün offset vs cursor seçimini əsaslandır:
1. Admin panel — istifadəçi siyahısı (ümumi say görünür, spesifik səhifəyə keçid lazımdır)
2. Social media feed — sonsuz scroll
3. Report — 1M+ sətirlik data export

### Tapşırıq 5 — Idempotency Implementation

`/v1/payments` endpoint-i üçün idempotency mexanizmi dizayn et:
- Key-i harada saxlayacaqsan?
- TTL nə qədər olacaq?
- Eyni key ilə request gedəndə response necə qaytarılacaq?

---

## Əlaqəli Mövzular

- [05-rest-graphql-grpc.md](05-rest-graphql-grpc.md) — REST vs alternativlər
- [08-api-versioning.md](08-api-versioning.md) — API versioning strategiyaları
- [09-http-caching.md](09-http-caching.md) — ETag, Cache-Control
- [11-oauth-jwt.md](11-oauth-jwt.md) — Authentication/Authorization
- [12-webhook-design.md](12-webhook-design.md) — Webhook dizayn
