# REST Best Practices & Richardson Maturity Model (Junior)

## Mündəricat
1. [Richardson Maturity Model](#richardson-maturity-model)
2. [Resource Naming](#resource-naming)
3. [HTTP Methods və Idempotency](#http-methods-və-idempotency)
4. [Status Codes](#status-codes)
5. [Pagination Strategiyaları](#pagination-strategiyaları)
6. [HATEOAS](#hateoas)
7. [PHP İmplementasiyası](#php-implementasiyası)
8. [İntervyu Sualları](#intervyu-sualları)

---

## Richardson Maturity Model

```
Level 0 — The Swamp of POX:
  HTTP transport kimi istifadə edilir.
  POST /api  {"action": "getUser", "id": 1}
  POST /api  {"action": "createOrder", ...}
  → HTTP semantics yoxdur

Level 1 — Resources:
  Resurslar var, amma hər şey POST.
  POST /users/1      → user al
  POST /orders/new   → sifariş yarat
  → Resurs var, HTTP methods yoxdur

Level 2 — HTTP Verbs:
  HTTP methods düzgün istifadə edilir.
  GET    /users/1    → user al
  POST   /users      → user yarat
  PUT    /users/1    → update
  DELETE /users/1    → sil
  → Əksər API-lar bu leveldir

Level 3 — Hypermedia Controls (HATEOAS):
  Response-da növbəti mümkün actions verilir.
  GET /orders/1 →
  {
    "id": 1,
    "status": "pending",
    "_links": {
      "confirm": {"href": "/orders/1/confirm", "method": "POST"},
      "cancel":  {"href": "/orders/1/cancel",  "method": "POST"},
      "customer":{"href": "/customers/42",     "method": "GET"}
    }
  }
  → Client hardcode URL bilməir, serverdən öyrənir
```

---

## Resource Naming

```
Qaydalar:
  ✓ İsim (noun), feil (verb) yox
  ✓ Cəm (plural)
  ✓ Kiçik hərf, tire ilə
  ✓ Hierarxiya resursu əks etdirməlidir

✅ Düzgün:
  GET    /users
  GET    /users/42
  GET    /users/42/orders
  GET    /users/42/orders/7
  POST   /users
  PUT    /users/42
  PATCH  /users/42
  DELETE /users/42

❌ Yanlış:
  GET  /getUser           → feil
  POST /user/create       → feil + tək
  GET  /Users/42          → böyük hərf
  GET  /user_orders       → hierarxiya yoxdur

Əməliyyatlar (verbs qaçınılmaz olduqda):
  POST /orders/42/confirm   → OK (state dəyişikliyi)
  POST /users/42/deactivate → OK
  POST /payments/42/refund  → OK
```

---

## HTTP Methods və Idempotency

```
┌──────────┬───────────┬─────────────┬─────────────────────────────────┐
│ Method   │ Safe?     │ Idempotent? │ İstifadə                        │
├──────────┼───────────┼─────────────┼─────────────────────────────────┤
│ GET      │ ✅ Yes    │ ✅ Yes      │ Resurs oxu                      │
│ HEAD     │ ✅ Yes    │ ✅ Yes      │ Metadata (body yox)             │
│ OPTIONS  │ ✅ Yes    │ ✅ Yes      │ Mövcud metodları öyrən          │
│ POST     │ ❌ No     │ ❌ No       │ Yarat, ya da action             │
│ PUT      │ ❌ No     │ ✅ Yes      │ Tam resurs əvəzlə               │
│ PATCH    │ ❌ No     │ ❌ No*      │ Qismən update                   │
│ DELETE   │ ❌ No     │ ✅ Yes      │ Sil                             │
└──────────┴───────────┴─────────────┴─────────────────────────────────┘

Safe: DB-ni dəyişdirmir
Idempotent: Təkrar eyni nəticə

PUT vs PATCH:
  PUT:   Tam resurs göndər, əvəzlə
         Göndərilməyən sahələr default/null olur!
  PATCH: Yalnız dəyişəcəkləri göndər
         RFC 6902 (JSON Patch) format da var
```

---

## Status Codes

```
2xx — Uğurlu:
  200 OK              → GET, PUT, PATCH uğurlu
  201 Created         → POST ilə yaradıldı (Location header əlavə et)
  204 No Content      → DELETE uğurlu, body yoxdur
  202 Accepted        → Async əməliyyat qəbul edildi (hələ tamamlanmadı)

3xx — Yönləndir:
  301 Moved Permanently → URL dəyişdi, SEO-friendly
  302 Found             → Müvəqqəti redirect
  304 Not Modified      → Cache hələ keçərlidir (ETags ilə)

4xx — Client xətası:
  400 Bad Request       → Invalid input, validation error
  401 Unauthorized      → Auth lazımdır (token yoxdur/etibarsızdır)
  403 Forbidden         → Auth var, amma icazə yoxdur
  404 Not Found         → Resurs mövcud deyil
  409 Conflict          → State conflict (duplicate, version mismatch)
  410 Gone              → Həmişəlik silindi (404-dan fərqli)
  422 Unprocessable     → Validation xətası (Laravel default)
  429 Too Many Requests → Rate limit keçildi

5xx — Server xətası:
  500 Internal Error    → Unhandled exception
  502 Bad Gateway       → Upstream server xətası
  503 Service Unavailable → Maintenance/overload
  504 Gateway Timeout   → Upstream timeout
```

---

## Pagination Strategiyaları

```
Offset Pagination:
  GET /posts?page=3&per_page=20
  SQL: SELECT * FROM posts LIMIT 20 OFFSET 40

  ✓ Sadə, istənilən səhifəyə atlamaq olar
  ✗ Böyük offset-lərdə yavaş (OFFSET 10000 → 10000 sətir oxunur)
  ✗ Canlı datayla "phantom read" / "skip" problemi

Cursor Pagination:
  GET /posts?after=cursor_xyz&limit=20
  cursor = base64(last_item_id veya timestamp)
  SQL: SELECT * FROM posts WHERE id > :last_id LIMIT 20

  ✓ Böyük dataset-lərdə sürətli (index istifadə edir)
  ✓ Canlı datayla sabit nəticə
  ✗ Spesifik səhifəyə atlamaq olmur
  ✗ Client-ə cursor saxlamaq lazımdır
  
  İstifadə: Social media feed, infinite scroll

Keyset Pagination:
  Cursor-a oxşar, amma birden çox sütun üzrə.
  WHERE (created_at, id) < (:last_created, :last_id)
  ORDER BY created_at DESC, id DESC
```

---

## HATEOAS

```
Hypermedia As The Engine Of Application State.

Client API-nın URL strukturunu hardcode etmir.
Entry point-dən başlayıb link-ləri izləyir.

HAL (Hypertext Application Language) formatı:
{
  "id": 42,
  "status": "pending",
  "total": 150.00,
  "_links": {
    "self":    {"href": "/orders/42"},
    "confirm": {"href": "/orders/42/confirm", "method": "POST"},
    "cancel":  {"href": "/orders/42/cancel",  "method": "DELETE"},
    "customer":{"href": "/customers/7"}
  },
  "_embedded": {
    "items": [
      {"productId": 1, "qty": 2, "_links": {"product": "/products/1"}}
    ]
  }
}

Praktikada: Tam HATEOAS az API tətbiq edir. Level 2 (HTTP Verbs) daha geniş yayılıb.
```

---

## PHP İmplementasiyası

```php
<?php
// Cursor Pagination
class CursorPaginator
{
    public function paginate(
        QueryBuilder $qb,
        ?string $cursor,
        int $limit = 20
    ): PaginatedResult {
        if ($cursor) {
            $decoded = json_decode(base64_decode($cursor), true);
            $qb->where('id < :last_id')
               ->setParameter('last_id', $decoded['id']);
        }

        $qb->orderBy('id', 'DESC')->setMaxResults($limit + 1);
        $items = $qb->getQuery()->getResult();

        $hasMore = count($items) > $limit;
        if ($hasMore) {
            array_pop($items);
        }

        $nextCursor = null;
        if ($hasMore && !empty($items)) {
            $last = end($items);
            $nextCursor = base64_encode(json_encode(['id' => $last->getId()]));
        }

        return new PaginatedResult($items, $nextCursor, $hasMore);
    }
}
```

```php
<?php
// HATEOAS Response builder
class HateoasResponse
{
    private array $links = [];
    private array $embedded = [];

    public function __construct(private array $data) {}

    public function addLink(string $rel, string $href, string $method = 'GET'): self
    {
        $this->links[$rel] = ['href' => $href, 'method' => $method];
        return $this;
    }

    public function toArray(): array
    {
        return array_merge($this->data, [
            '_links'    => $this->links,
            '_embedded' => $this->embedded ?: null,
        ]);
    }
}

// Controller
class OrderController
{
    public function show(int $id): JsonResponse
    {
        $order = $this->orderRepo->find($id);

        $response = (new HateoasResponse($order->toArray()))
            ->addLink('self', "/orders/{$id}")
            ->addLink('customer', "/customers/{$order->getCustomerId()}");

        if ($order->isPending()) {
            $response->addLink('confirm', "/orders/{$id}/confirm", 'POST');
            $response->addLink('cancel', "/orders/{$id}/cancel", 'DELETE');
        }

        return new JsonResponse($response->toArray());
    }
}
```

---

## İntervyu Sualları

- Richardson Maturity Model-in Level 2 vs Level 3 fərqi nədir?
- PUT vs PATCH — tam fərqi nədir? PATCH idempotent deyil niyə?
- 401 vs 403 — hər birini nə vaxt qaytararsınız?
- Offset pagination böyük dataset-lərdə niyə yavaşlayır?
- Cursor pagination-da "jump to page 5" mümkün deyilsə bu niyə qəbul edilir?
- `202 Accepted` nə vaxt istifadə edilir? Async əməliyyat statusunu client necə öyrənir?
- HATEOAS-ın praktik faydası nədir? Niyə geniş istifadə edilmir?
