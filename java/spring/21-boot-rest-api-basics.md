# 21 — REST API Əsasları (HTTP, Metodlar, Status Kodları)

> **Seviyye:** Middle ⭐⭐


## Mündəricat
1. [REST nədir?](#nədir)
2. [REST prinsipləri (6 qayda)](#prinsiplər)
3. [HTTP protokolu qısaca](#http)
4. [HTTP metodları — GET/POST/PUT/PATCH/DELETE](#metodlar)
5. [URI dizaynı (resurs adlandırma)](#uri)
6. [API versioning](#versioning)
7. [HTTP status kodları](#status)
8. [HTTP başlıqları](#başlıqlar)
9. [Query param vs Path param vs Body](#param)
10. [İlk Spring Boot REST Controller](#controller)
11. [ResponseEntity və düzgün status qaytarmaq](#responseentity)
12. [ProblemDetail ilə xəta cavabları (RFC 7807)](#problemdetail)
13. [Pagination, sort, filter](#pagination)
14. [API-ni test etmək (curl/Postman)](#test)
15. [Ümumi Səhvlər](#səhvlər)
16. [İntervyu Sualları](#intervyu)

---

## 1. REST nədir? {#nədir}

**REST** — **RE**presentational **S**tate **T**ransfer — 2000-ci ildə Roy Fielding-in doktorluq dissertasiyasında təqdim etdiyi memarlıq üslubudur.

Qısaca: **klient-server arasında HTTP üzərindən resurs-yönümlü, state-siz əlaqə qurmaq yolu**. Hər resurs (məs., "istifadəçi", "sifariş", "məhsul") unikal URL-ə malikdir, onun üzərində HTTP metodları (GET, POST, PUT, DELETE) əməliyyat aparır.

Real dünyada: REST API bir restorandakı menyuya bənzəyir. Menyu (URI) hansı yeməklərin (resurs) mövcud olduğunu göstərir. Sifariş (HTTP metod) — GET (xəbər öyrənmək üçün), POST (yeni sifariş), PUT (dəyişdirmək), DELETE (ləğv etmək).

### REST API vs SOAP və RPC:

| | REST | SOAP | gRPC |
|---|---|---|---|
| Protokol | HTTP | HTTP/SMTP | HTTP/2 |
| Format | JSON (adətən) | XML | Protobuf |
| Sxem | OpenAPI/Swagger | WSDL | .proto |
| Performans | orta | yavaş | çox sürətli |
| Brauzer-dostu | ✓ | ✗ | ✗ |
| Streaming | SSE/WS ilə | ✗ | daxili |

---

## 2. REST prinsipləri (6 qayda) {#prinsiplər}

### 1. Client-Server (klient-server ayrılığı)
Frontend və backend müstəqil inkişaf edir. Klient UI-ya fokuslanır, server məntiqə.

### 2. Stateless (state-siz)
Server heç bir klient state-i saxlamır. Hər sorğu öz-özünə tamdır — lazımlı bütün məlumat (authentication token, parametrlər) sorğu içindədir. Scalability-nin əsasıdır: hər server eyni sorğunu cavablandıra bilər.

### 3. Cacheable (keş edilə bilər)
Cavablar keş oluna bilir. HTTP `Cache-Control`, `ETag`, `Last-Modified` başlıqları bu məqsədə xidmət edir.

### 4. Uniform Interface (vahid interfeys)
Hər şey eyni qaydalarla: resurs URI ilə identifikasiya, HTTP metodu ilə əməliyyat, statuskodla nəticə.

### 5. Layered System (laylı sistem)
Klient bilmir arada kimin — load balancer, CDN, API Gateway, cache layer — olduğunu. Hər lay öz funksiyasını yerinə yetirir.

### 6. Code-on-Demand (isteğe bağlı)
Server bəzən klient tərəfə icra ediləcək kod (JS) göndərə bilər. REST-də çox nadir istifadə olunur.

---

## 3. HTTP protokolu qısaca {#http}

HTTP sorğu/cavab paradiqmasıdır.

### Sorğu strukturu:

```
POST /api/users HTTP/1.1              ← request line (metod, URI, versiya)
Host: api.example.com                  ← header-lər
Content-Type: application/json
Authorization: Bearer eyJ...
Content-Length: 54

{"name":"Ali","email":"ali@example.com"}   ← body (JSON)
```

### Cavab strukturu:

```
HTTP/1.1 201 Created                   ← status line (versiya, kod, mesaj)
Content-Type: application/json          ← header-lər
Location: /api/users/42
Content-Length: 87

{"id":42,"name":"Ali","email":"ali@example.com","createdAt":"2026-04-24T10:30"}
```

---

## 4. HTTP metodları — GET/POST/PUT/PATCH/DELETE {#metodlar}

REST-də 5 əsas metod istifadə olunur:

| Metod | Məqsəd | Safe | Idempotent | Body | Cache |
|---|---|---|---|---|---|
| **GET** | Resurs oxu | ✓ | ✓ | yox | ✓ |
| **POST** | Yeni resurs yarat | ✗ | ✗ | ✓ | yalnız `Cache-Control` ilə |
| **PUT** | Resursu tamamilə əvəz et | ✗ | ✓ | ✓ | ✗ |
| **PATCH** | Resursu qismən yenilə | ✗ | ✗* | ✓ | ✗ |
| **DELETE** | Resursu sil | ✗ | ✓ | bəzən | ✗ |
| **HEAD** | GET kimi, amma body yox | ✓ | ✓ | yox | ✓ |
| **OPTIONS** | Hansı metodlar qəbul olunur | ✓ | ✓ | yox | ✗ |

*PATCH idempotent ola bilər, amma çox vaxt yox.

### Terminlər:
- **Safe**: server state-ini dəyişmir
- **Idempotent**: sorğunu 10 dəfə göndərmək nəticə baxımından 1 dəfə göndərməklə eynidir

### Niyə fərq vacibdir?

GET idempotentdir, ona görə `/api/users/DELETE` kimi "crutch" URL-lər qadağandır — brauzer keş-dan GET-i təkrarlaya bilər.

---

## 5. URI dizaynı (resurs adlandırma) {#uri}

Resurs URL-ləri proqramçılıq arxitekturasında vizit kartıdır. Yaxşı dizayn:

### ✅ Doğru:

```
GET    /api/v1/users                  — bütün istifadəçilər
GET    /api/v1/users/42               — id=42 istifadəçi
POST   /api/v1/users                  — yeni istifadəçi yarat
PUT    /api/v1/users/42               — 42-ni tamamilə əvəz et
PATCH  /api/v1/users/42               — 42-nin bəzi sahələrini yenilə
DELETE /api/v1/users/42               — 42-ni sil

GET    /api/v1/users/42/orders        — 42-nin sifarişləri (nested)
POST   /api/v1/orders/100/cancel      — 100 nömrəli sifarişi ləğv et (action endpoint)
```

### ❌ Səhv:

```
GET /api/getUser?id=42              — metod URL-də yox, URL resurs olmalı
GET /api/user                       — tək (resurs kolleksiyadır, cəm istifadə et)
POST /api/deleteUser/42             — DELETE metod var, POST deyil
GET /api/users/42/getOrders         — "get" fellinə ehtiyac yox
```

### Qızıl qaydalar:

1. **İsim, fel yox** — `/orders`, `/processOrder` yox
2. **Cəm** — `/users`, `/user` yox
3. **Kiçik hərflər** — `/userProfiles` yox, `/user-profiles`
4. **Hierarchy** — `/users/42/orders/100`
5. **Versioning** — `/api/v1/...`
6. **Heç vaxt fayl adı olmasın** — `/api/users.php` yox

---

## 6. API versioning {#versioning}

API dəyişdikdə (kəsici dəyişiklik) mövcud klientləri sındırmamaq üçün versiya lazımdır.

### 1. URI versioning (ən yayılmış):

```
GET /api/v1/users
GET /api/v2/users
```

Asan, şəffafdır. Spring-də konfiqurasiya sadədir.

### 2. Header versioning:

```
GET /api/users
Accept: application/vnd.example.v2+json
```

URI təmiz qalır, amma brauzerdən test etmək çətin olur.

### 3. Query param:

```
GET /api/users?version=2
```

Cachability probleməverir, az tövsiyə olunur.

Əksər komanda URI versioning seçir — sadə, debug edilə bilən.

---

## 7. HTTP status kodları {#status}

Status kodu 3 rəqəmlidir, iki hissədən:

| Kateqoriya | Məna |
|---|---|
| **1xx** | Informational — nadir |
| **2xx** | Success — uğurlu |
| **3xx** | Redirection — başqa yerə yönləndir |
| **4xx** | Client error — klient səhvidir |
| **5xx** | Server error — server səhvidir |

### Ən vacib kodlar:

| Kod | Ad | Nə vaxt |
|---|---|---|
| **200 OK** | Uğurlu GET, PUT, PATCH | "Bütün dəyişənlər olduğu kimi, hər şey yaxşıdır" |
| **201 Created** | POST uğurla yeni resurs yaratdı | `Location:` header yeni URL-ə göstərir |
| **202 Accepted** | Sorğu qəbul olundu, işlənir (async) | Job/batch bildirişi |
| **204 No Content** | Uğurlu, amma body yoxdur | DELETE, PUT response bəzən |
| **301 Moved Permanently** | Resurs daimi başqa yerə köçdü | SEO redirect |
| **302 Found** | Müvəqqəti redirect | Login redirect |
| **304 Not Modified** | Keş-daki versiya cari | ETag/If-None-Match ilə |
| **400 Bad Request** | Sorğu səhvdir (format, sintaksis) | JSON parse xətası, validation fail |
| **401 Unauthorized** | Kimlik təsdiq olunmayıb | Token yoxdur/yanlışdır |
| **403 Forbidden** | Kimlik var, amma icazə yoxdur | Role kifayət etmir |
| **404 Not Found** | Resurs mövcud deyil | `/users/999999` |
| **405 Method Not Allowed** | URL var, amma bu metod dəstəklənmir | POST-a GET göndərdin |
| **409 Conflict** | Konflikt | Duplicate email, version conflict |
| **410 Gone** | Əvvəllər var idi, artıq yoxdur | Deaktiv endpoint |
| **415 Unsupported Media Type** | Content-Type dəstəklənmir | XML göndərdin, JSON gözlənir |
| **422 Unprocessable Entity** | Format düzgün, amma məzmunda problem | Validation xətası (REST Laravel-də) |
| **429 Too Many Requests** | Rate limit aşıldı | API-yə bombardman |
| **500 Internal Server Error** | Server-də gözlənilməz xəta | NPE, DB crash |
| **502 Bad Gateway** | Upstream server xətası | Gateway downstream-dən pis cavab aldı |
| **503 Service Unavailable** | Müvəqqəti çatışmazlıq | Deploy vaxtı, maintenance |
| **504 Gateway Timeout** | Upstream cavab vermir | Downstream yavaşdır |

### 401 vs 403 fərqi:

- **401** — "Kim olduğunu bilmirəm" (auth token yoxdur, yanlışdır)
- **403** — "Kim olduğunu bilirəm, amma bu sənə qadağandır" (token var, rol çatmır)

### 422 vs 400:

- **400** — sorğu pozuq (JSON parse olunmur, məcburi field yoxdur)
- **422** — sorğu düzgün formatdadır, amma validation keçmir (email format yanlışdır)

---

## 8. HTTP başlıqları {#başlıqlar}

Başlıqlar metadata daşıyır.

### Sorğu başlıqları:

| Header | Təsvir | Nümunə |
|---|---|---|
| `Accept` | Klient hansı formatı gözləyir | `application/json` |
| `Content-Type` | Body-nin formatı | `application/json` |
| `Authorization` | Kimlik məlumatı | `Bearer eyJ0eXAi...` |
| `If-None-Match` | Keş üçün ETag | `"abc123"` |
| `User-Agent` | Klient proqramı | `Mozilla/5.0...` |

### Cavab başlıqları:

| Header | Təsvir |
|---|---|
| `Content-Type` | Body formatı |
| `Content-Length` | Body ölçüsü |
| `Location` | Yeni yaranan resursun URL-i (201-də) |
| `Cache-Control` | Keş direktivləri (`max-age=3600`) |
| `ETag` | Resursun versiya tokeni |
| `Last-Modified` | Son dəyişiklik tarixi |
| `Access-Control-Allow-Origin` | CORS — hansı origin-lər icazəlidir |

---

## 9. Query param vs Path param vs Body {#param}

Spring Boot-da hər birinin annotasiyası var:

### Path Param (`@PathVariable`):

```java
@GetMapping("/users/{id}")
public User getUser(@PathVariable Long id) { ... }
// GET /users/42
```

Resursun identifikasiyası üçün.

### Query Param (`@RequestParam`):

```java
@GetMapping("/users")
public List<User> search(
    @RequestParam(required = false) String name,
    @RequestParam(defaultValue = "10") int limit) { ... }
// GET /users?name=Ali&limit=20
```

Filter, pagination, optional parametrlər üçün.

### Body (`@RequestBody`):

```java
@PostMapping("/users")
public User create(@RequestBody @Valid CreateUserDto dto) { ... }
// POST /users + JSON body
```

Yeni/dəyişdirilən resursun məzmunu üçün.

### Nə vaxt hansı?

| Məlumat növü | Harada |
|---|---|
| Resursun identifikasiyası (`/users/42`) | Path |
| Filter, sort, pagination | Query |
| Məxfi token, auth | Header |
| Yeni resursun məzmunu, complex obyekt | Body |

**Səhv:** GET sorğusunda body göndərmək — bəzi proxy-lər atır.

---

## 10. İlk Spring Boot REST Controller {#controller}

Sadə User CRUD endpoint-ləri:

```java
package com.example.api;

import org.springframework.http.HttpStatus;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.*;

import java.net.URI;
import java.util.*;
import java.util.concurrent.atomic.AtomicLong;

@RestController
@RequestMapping("/api/v1/users")
public class UserController {

    private final Map<Long, User> users = new HashMap<>();
    private final AtomicLong idGen = new AtomicLong();

    // GET /api/v1/users — bütün istifadəçilər
    @GetMapping
    public List<User> list() {
        return new ArrayList<>(users.values());
    }

    // GET /api/v1/users/{id} — bir istifadəçi
    @GetMapping("/{id}")
    public ResponseEntity<User> getById(@PathVariable Long id) {
        User u = users.get(id);
        if (u == null) {
            return ResponseEntity.notFound().build();  // 404
        }
        return ResponseEntity.ok(u);                   // 200
    }

    // POST /api/v1/users — yeni istifadəçi yarat
    @PostMapping
    public ResponseEntity<User> create(@RequestBody User input) {
        long id = idGen.incrementAndGet();
        User u = new User(id, input.name(), input.email());
        users.put(id, u);

        URI location = URI.create("/api/v1/users/" + id);
        return ResponseEntity.created(location).body(u);  // 201 + Location
    }

    // PUT /api/v1/users/{id} — tamamilə əvəz et
    @PutMapping("/{id}")
    public ResponseEntity<User> replace(@PathVariable Long id, @RequestBody User input) {
        if (!users.containsKey(id)) {
            return ResponseEntity.notFound().build();
        }
        User updated = new User(id, input.name(), input.email());
        users.put(id, updated);
        return ResponseEntity.ok(updated);
    }

    // DELETE /api/v1/users/{id}
    @DeleteMapping("/{id}")
    public ResponseEntity<Void> delete(@PathVariable Long id) {
        if (users.remove(id) == null) {
            return ResponseEntity.notFound().build();
        }
        return ResponseEntity.noContent().build();  // 204
    }

    public record User(Long id, String name, String email) {}
}
```

---

## 11. ResponseEntity və düzgün status qaytarmaq {#responseentity}

`ResponseEntity<T>` status, header və body-ni dəqiq idarə etmək üçün.

```java
// 200 OK + body
return ResponseEntity.ok(user);

// 201 Created + Location header
return ResponseEntity
    .created(URI.create("/users/" + id))
    .body(user);

// 204 No Content (DELETE üçün)
return ResponseEntity.noContent().build();

// 404 Not Found (body yoxdur)
return ResponseEntity.notFound().build();

// 400 Bad Request + xəta body
return ResponseEntity
    .badRequest()
    .body(Map.of("error", "Email formatı səhvdir"));

// Custom header
return ResponseEntity
    .ok()
    .header("X-Total-Count", "42")
    .body(users);

// 429 Too Many Requests + Retry-After
return ResponseEntity
    .status(HttpStatus.TOO_MANY_REQUESTS)
    .header(HttpHeaders.RETRY_AFTER, "30")
    .build();
```

### `@RestController` vs `@Controller`

- **`@RestController`** = `@Controller` + `@ResponseBody`. Return dəyəri birbaşa JSON/XML-ə serialize olur.
- **`@Controller`** — ənənəvi MVC, View (HTML template) qaytarır. `@ResponseBody` lazım gələrsə metodda yazılır.

REST API üçün həmişə `@RestController`.

---

## 12. ProblemDetail ilə xəta cavabları (RFC 7807) {#problemdetail}

RFC 7807 — "Problem Details for HTTP APIs" — xəta cavablarını standartlaşdırmaq üçün. Spring Boot 3.x-də yerləşdirilib.

### Standart xəta cavabı:

```json
{
  "type": "https://api.example.com/errors/user-not-found",
  "title": "User not found",
  "status": 404,
  "detail": "İstifadəçi id=99 tapılmadı",
  "instance": "/api/v1/users/99"
}
```

### Spring-də istifadə:

```java
@RestControllerAdvice
public class GlobalExceptionHandler {

    @ExceptionHandler(UserNotFoundException.class)
    public ProblemDetail handleNotFound(UserNotFoundException ex) {
        ProblemDetail p = ProblemDetail.forStatusAndDetail(
            HttpStatus.NOT_FOUND, ex.getMessage());
        p.setTitle("User not found");
        p.setType(URI.create("https://api.example.com/errors/user-not-found"));
        p.setProperty("userId", ex.getUserId());
        return p;
    }

    @ExceptionHandler(MethodArgumentNotValidException.class)
    public ProblemDetail handleValidation(MethodArgumentNotValidException ex) {
        List<String> errors = ex.getBindingResult().getFieldErrors().stream()
            .map(e -> e.getField() + ": " + e.getDefaultMessage())
            .toList();

        ProblemDetail p = ProblemDetail.forStatusAndDetail(
            HttpStatus.BAD_REQUEST, "Validation failed");
        p.setProperty("errors", errors);
        return p;
    }
}
```

`application.properties`-də aktiv et:

```properties
spring.mvc.problemdetails.enabled=true
```

---

## 13. Pagination, sort, filter {#pagination}

### Spring Data Pageable ilə:

```java
@GetMapping("/users")
public Page<User> list(
    @RequestParam(defaultValue = "0") int page,
    @RequestParam(defaultValue = "20") int size,
    @RequestParam(defaultValue = "id,asc") String sort,
    @RequestParam(required = false) String name) {

    Pageable pageable = PageRequest.of(page, size, Sort.by(sort.split(",")[0]));
    return userRepository.findByNameContaining(name, pageable);
}
```

Çağırış:

```
GET /users?page=0&size=10&sort=name,desc&name=Ali
```

Cavab `Page<User>`:

```json
{
  "content": [ {...}, {...} ],
  "pageable": { "pageNumber": 0, "pageSize": 10 },
  "totalElements": 42,
  "totalPages": 5
}
```

### Linki pagination (HATEOAS):

```
Link: <http://api/users?page=1>; rel="next",
      <http://api/users?page=4>; rel="last"
```

---

## 14. API-ni test etmək (curl/Postman) {#test}

### curl:

```bash
# GET
curl http://localhost:8080/api/v1/users

# POST
curl -X POST http://localhost:8080/api/v1/users \
  -H "Content-Type: application/json" \
  -d '{"name":"Ali","email":"ali@ex.com"}'

# PUT
curl -X PUT http://localhost:8080/api/v1/users/1 \
  -H "Content-Type: application/json" \
  -d '{"name":"Ali Yenilənmiş","email":"ali@ex.com"}'

# DELETE
curl -X DELETE http://localhost:8080/api/v1/users/1

# Auth header
curl http://localhost:8080/api/v1/users \
  -H "Authorization: Bearer eyJ0..."

# Verbose (header-ləri gör)
curl -v http://localhost:8080/api/v1/users/1

# Status code yalnız
curl -o /dev/null -s -w "%{http_code}\n" http://localhost:8080/api/v1/users/99
# → 404
```

### HTTPie (daha dost):

```bash
http GET localhost:8080/api/v1/users
http POST localhost:8080/api/v1/users name=Ali email=ali@ex.com
```

### IntelliJ HTTP Client (Ultimate):

`requests.http` faylı yarat:

```http
### Bütün istifadəçilər
GET http://localhost:8080/api/v1/users
Accept: application/json

### Yeni istifadəçi
POST http://localhost:8080/api/v1/users
Content-Type: application/json

{
  "name": "Ali",
  "email": "ali@ex.com"
}
```

Hər `###` bloku ▶ düyməsi ilə işlədilir.

### Postman / Insomnia:

Kolleksiyalarda endpoint-lər saxla, environment-lər yarat (dev/staging/prod), test-lər yaz.

---

## 15. Ümumi Səhvlər {#səhvlər}

### 1. GET-lə mutasiya etmək

```java
@GetMapping("/users/{id}/delete")  // ❌
public void delete(@PathVariable Long id) { ... }
```

GET idempotent olmalıdır. Brauzer keş edə, prefetch edə bilər.

**Düzəliş:** `DELETE /users/{id}`.

### 2. Yanlış status kodları

```java
// DELETE uğurla bitdi, amma ...
return ResponseEntity.ok().build();  // 200 qaytarır (body yoxdur)
```

**Düzəliş:** `ResponseEntity.noContent().build()` — 204.

```java
// POST uğurla yarandı, amma ...
return ResponseEntity.ok(user);  // 200 qaytarır
```

**Düzəliş:** `ResponseEntity.created(URI...).body(user)` — 201 + Location.

### 3. Daxili istisnaları body-də göstərmək

```java
return ResponseEntity.status(500).body(ex.getStackTrace());  // ❌
```

Təhlükəlidir — daxili struktur görünür. ProblemDetail və generic mesaj qaytar.

### 4. Resurs adları fel ilə

```
/api/getUser  ❌
/api/users    ✓

/api/createOrder  ❌
POST /api/orders  ✓
```

### 5. State saxlamaq server-də

Stateless olmaq REST-in prinsipidir. Session yerinə JWT/stateless auth.

### 6. Body-də sensitivə məlumat GET-də

```
GET /users/login?password=123   ❌
```

URL log-lara düşür, brauzer history-də qalır. POST body-də auth göndər.

### 7. 200-də "success: false" qaytarmaq

```json
HTTP 200 OK
{"success": false, "error": "Not found"}
```

Monitoring sistemləri 200-i uğurlu sayır. **404** qaytar, JSON-da detail ver.

### 8. Plural adları unudmaq

```
/api/user   ❌
/api/users  ✓
```

### 9. Versioning-i unutmaq

İlk versiya `/api/users` — sonra kəsici dəyişiklik lazım olanda problem.

**Düzəliş:** Hələ birinci versiyadan `/api/v1/users` qoy.

### 10. CORS qaydalarını unutmaq

Brauzer başqa domain-dən API-ya çatanda CORS xətası. `@CrossOrigin` və ya `WebMvcConfigurer.addCorsMappings()` istifadə et. Təhlükəsizliyi unutma — `*` production-da məsləhət deyil.

---

## 16. İntervyu Sualları {#intervyu}

**S1: REST nədir və qısaca 6 prinsipi sadala.**

REST — resurs-yönümlü, state-siz HTTP API memarlığı. 6 prinsip: (1) Client-Server ayrılığı, (2) Stateless, (3) Cacheable, (4) Uniform Interface, (5) Layered System, (6) Code-on-Demand (optional).

**S2: PUT və PATCH arasında fərq nədir?**

PUT — resursun tamamını əvəz edir (idempotent). PATCH — resursun bəzi sahələrini yeniləyir (ümumiyyətlə idempotent deyil). PUT-da bütün field-ləri göndərməlisən, PATCH-da yalnız dəyişənləri.

**S3: 401 və 403 arasında fərq?**

401 Unauthorized — "kimlik təsdiq olunmayıb" (token yoxdur/yanlış). 403 Forbidden — "kimlik var, amma bu icazə yoxdur" (rol kifayət etmir).

**S4: POST uğurla yaratdı — hansı status kodu və hansı header?**

201 Created + `Location:` header yeni resursun URL-i ilə. Cavab body-də yaradılan obyekt.

**S5: Idempotent metod nədir və hansıları?**

Eyni sorğunu N dəfə göndərməklə nəticə 1 dəfə göndərməklə eynidir. GET, PUT, DELETE, HEAD, OPTIONS idempotentdir. POST və PATCH ümumiyyətlə idempotent deyil.

**S6: GET sorğusunda body göndərmək olar?**

Texniki olaraq RFC açıq buraxıb, amma **praktik olaraq yox** — bəzi proxy, CDN, cache body-ni atır. Çox məlumat göndərmək lazımdırsa POST istifadə et və ya query param-da paylaş.

**S7: `@RestController` və `@Controller` fərqi?**

`@RestController = @Controller + @ResponseBody`. REST API üçün həmişə `@RestController`. `@Controller` HTML view qaytaran ənənəvi MVC üçündür.

**S8: API versioning yolları hansılardır?**

(1) URI: `/api/v1/users` — ən yayılmış; (2) Accept header: `Accept: application/vnd.example.v2+json`; (3) Query param: `?version=2` — az tövsiyə olunur (cachability problemi).

**S9: HATEOAS nədir?**

Hypermedia as the Engine of Application State. Cavab body-də klientin növbəti edə biləcəyi əməliyyatların linkləri də olur. REST-in "maksimal səviyyəsidir" (Richardson Level 3). Praktikada az istifadə olunur.

**S10: Stateless-in əhəmiyyəti nədir?**

Server klient state-i saxlamır — hər sorğu özü-özünə tamdır. Bu scalability verir: load balancer istənilən instance-a yönləndirə bilər, session yapışqanlığı (sticky) lazım deyil. Auth üçün JWT kimi self-contained token-lar istifadə olunur.

**S11: 429 Too Many Requests ilə necə davranmaq olar?**

Response-da `Retry-After` header ver (saniyələr və ya tarix). Klient belə cavabda sorğunu dayandırmalıdır. Server tərəfdən Bucket4j, Resilience4j ratelimiter ilə qorumaq olar.

**S12: RESTful olmaq üçün minimum nə tələb olunur?**

Richardson Maturity Model (RMM):
- L0: tək POST endpoint, RPC üslubu (REST deyil)
- L1: resurs URL-ləri
- L2: HTTP metodları + status kodları (əksər API-lər buradadır)
- L3: HATEOAS (hipermedya linkləri)

Minimum L2 olmalıdır ki, "RESTful" adlansın.

**S13: ProblemDetail (RFC 7807) nədir və niyə vacibdir?**

Xəta cavablarını standartlaşdıran RFC. `type`, `title`, `status`, `detail`, `instance` sahələri var. Klientlər xəta strukturunu proqnozlaşdıra bilir, her API özü uydurur yaradan səhv vəziyyət yaranmır. Spring Boot 3.x-də `ProblemDetail` class-ı daxildir.
