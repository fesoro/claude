# HTTP Endpoint Nümunələri

Bu sənəd 3 stack üçün də (Laravel, Spring, Go) **eyni** endpoint-ləri test etmək üçün curl nümunələrini ehtiva edir. Bütün 3 server eyni `localhost:8080` portunda işləyir.

## 1. Auth Flow

### Register
```bash
curl -X POST http://localhost:8080/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Test User",
    "email": "test@example.com",
    "password": "password123"
  }'
```

**Cavab** (3 stack-də də eyni format):
```json
{
  "success": true,
  "message": "Qeydiyyat uğurla tamamlandı",
  "data": {
    "user_id": "550e8400-e29b-41d4-a716-446655440000",
    "token": "eyJhbGciOiJIUzI1NiIs..."
  }
}
```

### Login
```bash
curl -X POST http://localhost:8080/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email": "test@example.com", "password": "password123"}'
```

### Forgot Password
```bash
curl -X POST http://localhost:8080/api/auth/forgot-password \
  -H "Content-Type: application/json" \
  -d '{"email": "test@example.com"}'
```

### Reset Password
```bash
curl -X POST http://localhost:8080/api/auth/reset-password \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com",
    "token": "TOKEN_FROM_EMAIL",
    "password": "newpassword456"
  }'
```

### Get Current User (auth)
```bash
TOKEN="eyJhbGciOiJIUzI1NiIs..."
curl http://localhost:8080/api/auth/me \
  -H "Authorization: Bearer $TOKEN"
```

## 2. 2FA Flow

### Enable 2FA (auth)
```bash
curl -X POST http://localhost:8080/api/auth/2fa/enable \
  -H "Authorization: Bearer $TOKEN"
```

Cavabda `secret` və `qr_code_url` qaytarır. QR-i Google Authenticator-da scan et.

### Confirm 2FA (auth)
```bash
curl -X POST http://localhost:8080/api/auth/2fa/confirm \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"secret": "JBSWY3DPEHPK3PXP", "code": "123456"}'
```

### Verify 2FA (login zamanı)
```bash
curl -X POST http://localhost:8080/api/auth/2fa/verify \
  -H "Content-Type: application/json" \
  -d '{"user_id": "...", "code": "123456"}'
```

## 3. Products

### List (public)
```bash
curl 'http://localhost:8080/api/products?page=0&size=15'
```

### Create (auth)
```bash
curl -X POST http://localhost:8080/api/products \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Laptop Dell XPS",
    "description": "13-inch ultrabook",
    "price_amount": 350000,
    "currency": "AZN",
    "stock_quantity": 25
  }'
```

> `price_amount` qəpiklə (350000 = 3500 AZN). 3 stack-də də eyni.

### Update Stock (auth)
```bash
curl -X PATCH http://localhost:8080/api/products/PRODUCT_ID/stock \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"amount": 5, "type": "decrease"}'
```

### Upload Image (auth)
```bash
curl -X POST http://localhost:8080/api/products/PRODUCT_ID/images \
  -H "Authorization: Bearer $TOKEN" \
  -F "file=@/path/to/image.jpg"
```

## 4. Orders

### Create Order (auth)
```bash
curl -X POST http://localhost:8080/api/orders \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -H "X-Idempotency-Key: $(uuidgen)" \
  -d '{
    "user_id": "USER_UUID",
    "currency": "AZN",
    "items": [
      {
        "product_id": "PRODUCT_UUID",
        "product_name": "Laptop Dell XPS",
        "unit_price_amount": 350000,
        "unit_price_currency": "AZN",
        "quantity": 1
      }
    ],
    "address": {
      "street": "Nizami küçəsi 10",
      "city": "Bakı",
      "zip": "AZ1000",
      "country": "Azerbaijan"
    }
  }'
```

> `X-Idempotency-Key` təkrar göndərilsə yenisini yaratmır (Redis 24h cache).

### Get Order (auth)
```bash
curl http://localhost:8080/api/orders/ORDER_ID \
  -H "Authorization: Bearer $TOKEN"
```

### List User Orders (auth)
```bash
curl http://localhost:8080/api/orders/user/USER_ID \
  -H "Authorization: Bearer $TOKEN"
```

### Cancel Order (auth)
```bash
curl -X POST http://localhost:8080/api/orders/ORDER_ID/cancel \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"reason": "Müştəri imtina etdi"}'
```

### Update Status (auth)
```bash
curl -X PATCH http://localhost:8080/api/orders/ORDER_ID/status \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"target": "CONFIRMED"}'
```

> Status keçid qaydası: `PENDING → CONFIRMED → PAID → SHIPPED → DELIVERED`. Yanlış keçid 422 verir.

## 5. Payments

### Process Payment (auth)
```bash
curl -X POST http://localhost:8080/api/payments/process \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "order_id": "ORDER_UUID",
    "user_id": "USER_UUID",
    "amount": 350000,
    "currency": "AZN",
    "method": "CREDIT_CARD"
  }'
```

> `method` enum: `CREDIT_CARD | PAYPAL | BANK_TRANSFER | STRIPE`. Strategy pattern uyğun gateway seçir.

### Get Payment (auth)
```bash
curl http://localhost:8080/api/payments/PAYMENT_ID \
  -H "Authorization: Bearer $TOKEN"
```

## 6. Webhooks

### Create Webhook (auth)
```bash
curl -X POST http://localhost:8080/api/webhooks \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "url": "https://your-domain.com/webhook-receiver",
    "events": ["order.created", "payment.completed"]
  }'
```

> Webhook payload-ı HMAC-SHA256 ilə imzalanır. `X-Webhook-Signature: sha256=<hex>` header-i yoxlayın.

## 7. Notification Preferences

### Update Preference (auth)
```bash
curl -X PUT http://localhost:8080/api/notifications/preferences/order.created \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"email": true, "sms": false, "push": true}'
```

## 8. Health Check

```bash
curl http://localhost:8080/api/health        # full check
curl http://localhost:8080/api/health/live   # liveness
curl http://localhost:8080/api/health/ready  # readiness
```

## 9. Search

```bash
curl 'http://localhost:8080/api/search?q=laptop'
```

## 10. Admin (auth + admin role)

```bash
curl http://localhost:8080/api/admin/failed-jobs \
  -H "Authorization: Bearer $TOKEN"
```

## Standart Cavab Format (3 stack-də eyni)

### Uğurlu
```json
{ "success": true, "data": { ... }, "message": "..." }
```

### Validation xətası (HTTP 400)
```json
{
  "success": false,
  "message": "Validasiya xətası",
  "errors": { "email": "düzgün formatda olmalıdır" }
}
```

### Domain xətası (HTTP 422)
```json
{ "success": false, "message": "Bu email artıq qeydiyyatdadır" }
```

### Not Found (HTTP 404)
```json
{ "success": false, "message": "Order tapılmadı: id=..." }
```

## Header-lər

| Header | Niyə |
|---|---|
| `Authorization: Bearer <jwt>` | Auth tələb edən endpoint-lərdə |
| `X-Idempotency-Key: <uuid>` | Təkrar request-ləri 24h-də bir dəfə işləyir |
| `X-Correlation-ID: <uuid>` | Request tracing (yoxsa avtomatik generate) |
| `X-Tenant-ID: <uuid>` | Multi-tenancy üçün |
| `X-API-Version: v1` | API versiyalaşdırma (default `v2`) |

## RabbitMQ Management UI

Servisi qaldırdıqdan sonra: `http://localhost:15672` (login: `guest` / `guest`)

## Mailpit UI (test email-ləri görmək üçün)

`http://localhost:8025`
