# API Testing Tools (Junior)

## İcmal

API testing tool-ları HTTP (və digər) API-ları manual və ya automated yoxlamaq üçün istifadə edilir. Əsas məqsəd: request qurmaq, response analizini etmək, authentication idarə etmək, test-ləri suite olaraq yığmaq, CI-a inteqrasiya etmək.

**Populyar tool-lar:**

| Tool | Tipi | Əsas xüsusiyyət |
|------|------|-----------------|
| **Postman** | GUI + cloud | Collection, environment, scripting, workspaces, monitoring |
| **Bruno** | GUI, open-source | Git-friendly (plain text fayllar), offline-first, cloud lock-in yoxdur |
| **Insomnia** | GUI + cloud | Yüngül, plugin, GraphQL-first |
| **HTTPie** | CLI | Rahat sintaksis, rəngli output |
| **curl** | CLI | Hər yerdə mövcuddur, script-friendly |
| **REST Client (VSCode)** | IDE extension | `.http` file-ları version control-da saxla |
| **Newman** | CLI | Postman collection-larını CI-da run et |

## Niyə Vacibdir

API testing tool olmadan development yavaşdır — hər dəfə browser-dən test etmək mümkün deyil, xüsusilə authentication, header, binary data iştirak edəndə. CI-da automated API test API contract-ının qırılmasını PR-da dərhal aşkarlayır.

## Əsas Anlayışlar

### curl — Universal Foundation

```bash
# GET request
curl https://api.example.com/orders

# Header ilə
curl -H "Authorization: Bearer eyJ..." \
     -H "Accept: application/json" \
     https://api.example.com/orders

# POST JSON
curl -X POST https://api.example.com/orders \
     -H "Content-Type: application/json" \
     -d '{"product_id":10,"quantity":2}'

# Request/response header + timing göstər
curl -v https://api.example.com/orders

# HTTP error-da fail et, CI-friendly
curl -fsSL https://api.example.com/health

# Response timing debug
curl -w "Time: %{time_total}s\nStatus: %{http_code}\n" \
     -o /dev/null -s https://api.example.com/orders

# Fayl upload (multipart)
curl -F "file=@photo.jpg" -F "caption=Hello" \
     https://api.example.com/upload
```

### HTTPie — Human-Friendly CLI

```bash
pip install httpie

# GET (default)
http GET api.example.com/orders

# JSON POST (avtomatik aşkarlayır)
http POST api.example.com/orders product_id=10 quantity:=2

# Headers
http api.example.com/orders Authorization:"Bearer eyJ..."

# Session (persistent headers/cookies)
http --session=work api.example.com/login username=ali password=123
http --session=work api.example.com/orders   # token-i yenidən istifadə edir
```

Fərq curl-dən: `:=` raw JSON dəyəri (stringify olmadan), `=` string. HTTPie avtomatik JSON response-u formatlayır.

### Postman — GUI, Collections, Scripting

```
Workspace
  └── Collection (Order API v1)
       ├── Folder: Orders
       │    ├── List Orders       GET  /orders
       │    ├── Get Order         GET  /orders/:id
       │    ├── Create Order      POST /orders
       │    └── Update Order      PUT  /orders/:id
       ├── Folder: Products

Environment
  ├── development:  baseUrl=http://localhost:8000, token=dev_xyz
  ├── staging:      baseUrl=https://staging.example.com, token=stg_abc
  └── production:   baseUrl=https://api.example.com, token=prod_pqr

Variable scope (override sırası):
  local > data > environment > collection > global
```

**Pre-request script** (request-dən əvvəl çalışır):
```javascript
// JWT müddəti bitibsə avtomatik yenilə
const token  = pm.environment.get('jwt_token');
const expiry = pm.environment.get('jwt_expiry');

if (!token || Date.now() > expiry) {
    pm.sendRequest({
        url:    pm.environment.get('baseUrl') + '/auth/refresh',
        method: 'POST',
        body: {
            mode: 'raw',
            raw: JSON.stringify({ refresh_token: pm.environment.get('refresh_token') }),
        },
        header: { 'Content-Type': 'application/json' },
    }, (err, res) => {
        if (!err) {
            const data = res.json();
            pm.environment.set('jwt_token', data.access_token);
            pm.environment.set('jwt_expiry', Date.now() + 3600 * 1000);
        }
    });
}
```

**Test script** (response-dan sonra çalışır):
```javascript
pm.test('Status 200', () => {
    pm.response.to.have.status(200);
});

pm.test('Response has orders array', () => {
    const body = pm.response.json();
    pm.expect(body).to.have.property('data');
    pm.expect(body.data).to.be.an('array');
});

pm.test('Response time under 500ms', () => {
    pm.expect(pm.response.responseTime).to.be.below(500);
});

// Növbəti request üçün dəyəri saxla
const firstOrderId = pm.response.json().data[0].id;
pm.collectionVariables.set('last_order_id', firstOrderId);
```

### Bruno — Git-Friendly Alternative

Bruno bütün collection-ları **plain text `.bru` file** kimi saxlayır. Postman-dən əsas fərq: **cloud yoxdur**, hər şey local + repoda commit edilir.

```
my-api-tests/
├── bruno.json
├── environments/
│   ├── dev.bru
│   └── staging.bru
├── orders/
│   ├── list-orders.bru
│   └── create-order.bru
```

```
# create-order.bru
meta {
  name: Create Order
  type: http
  seq: 3
}

post {
  url: {{baseUrl}}/orders
  body: json
  auth: bearer
}

auth:bearer {
  token: {{jwt_token}}
}

body:json {
  {
    "product_id": 10,
    "quantity": 2
  }
}

tests {
  test("status 201", () => {
    expect(res.status).to.equal(201);
  });

  test("has order ID", () => {
    expect(res.body.id).to.be.a('number');
    bru.setVar('created_order_id', res.body.id);
  });
}
```

**Üstünlüklər:** PR-də diff görünür, cloud vendor lock-in yoxdur, offline işləyir.

### REST Client (VSCode Extension)

`.http` faylı yaradırsan, birbaşa editor-dan request göndərirsən:

```http
### Variables
@baseUrl = http://localhost:8000/api
@token = eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...

### List orders
GET {{baseUrl}}/orders
Authorization: Bearer {{token}}

### Create order
# @name createOrder
POST {{baseUrl}}/orders
Authorization: Bearer {{token}}
Content-Type: application/json

{
  "product_id": 10,
  "quantity": 2
}

### Əvvəlki request-in response-undan istifadə et
GET {{baseUrl}}/orders/{{createOrder.response.body.id}}
Authorization: Bearer {{token}}
```

### Newman — Postman CI-da

```bash
npm install -g newman newman-reporter-htmlextra

newman run ./api-tests.postman_collection.json \
    -e ./staging.postman_environment.json \
    --reporters cli,json,htmlextra \
    --reporter-htmlextra-export ./newman-report.html

# İlk uğursuzluqda dayanır
newman run ... --bail
```

### Data-Driven Testing

```csv
# test-data.csv
product_id,quantity,expected_status
10,2,201
10,0,422
99999,1,404
```

```bash
newman run collection.json -d test-data.csv --iteration-data
```

### Workflow Automation

```
1. Login (POST /auth)        → {{token}} saxla
2. Customer yarat            → {{customer_id}} saxla
3. Order yarat               → {{customer_id}} istifadə et, {{order_id}} saxla
4. Ödəmə et                  → {{order_id}} istifadə et
5. Order statusunu yoxla     → assertion-lar
6. Təmizlə: order sil        → {{order_id}} istifadə et

Hər addım əvvəlki addımın output-unu variable vasitəsilə istifadə edir.
```

## Praktik Baxış

- **curl hər yerdə mövcuddur:** Container, minimal Docker, CI runner. Script-lərdə universal. `curl -fsSL` CI-da standartdır.
- **HTTPie interaktiv session-da:** Debug, exploratory testing üçün sürətli. Production-da curl.
- **Bruno team üçün:** Git workflow, PR review-da request dəyişiklikləri aydın görünür.
- **Collection = Postman JSON problemi:** Postman-in JSON export faylı diff-lərdə oxunmaz. Bruno həll edir.
- **Newman CI-da məcburidir:** GUI olmadan collection run etmək, JUnit report, CI integration.

### Anti-patterns

- Token-ları collection-in "initial value"-unda saxlamaq — repoda açıq qalır, CI secret-ləri istifadə et
- Test assertion olmadan yalnız request — "200 gəldi" kifayət deyil, structure, business rule yoxla
- Production environment-də data-mutation test — dev/staging üçün ayrı collection folder
- Workflow-u manuel run etmək — Newman ilə CI-da avtomatlaşdır

## Nümunələr

### Ümumi Nümunə

```
Test chain nümunəsi:

Request 1 — POST /auth/login
  Body: {email, password}
  Test script: pm.environment.set('token', body.access_token)

Request 2 — POST /orders
  Header: Authorization: Bearer {{token}}
  Test script: pm.collectionVariables.set('order_id', body.id)

Request 3 — GET /orders/{{order_id}}
  Assertion: status = body.status === 'pending'

Request 4 — DELETE /orders/{{order_id}} (cleanup)

Newman ilə CI-da run olunanda tam workflow test edilir.
```

### Kod Nümunəsi

**Laravel API-dan Postman Collection Generasiyası:**
```bash
composer require andreaselia/laravel-api-to-postman --dev
php artisan export:postman
# -> storage/app/postman/collection.json
```

**OpenAPI → Postman Import:**
```bash
# Scramble spec-ini al
curl http://localhost:8000/docs/api.json > openapi.json
# Postman → File → Import → openapi.json
# Postman OpenAPI spec-dən tam collection qurur
```

**Laravel Feature Tests vs API Tool Tests:**
```php
// Laravel feature test — unit/integration level
// tests/Feature/OrderApiTest.php
class OrderApiTest extends TestCase
{
    public function test_authenticated_user_can_create_order(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/orders', [
                'product_id' => 10,
                'quantity'   => 2,
            ]);

        $response
            ->assertCreated()
            ->assertJsonStructure(['id', 'total', 'status']);

        $this->assertDatabaseHas('orders', [
            'user_id'    => $user->id,
            'product_id' => 10,
        ]);
    }
}
```

```bash
# Postman/Newman — end-to-end, real HTTP
newman run ./postman/orders.collection.json -e ./postman/staging.env.json
```

**Fərq:**
- **Laravel feature tests:** Tətbiq daxilində, sürətli, mock-lara sahib. Business logic testi.
- **Postman/Newman:** External perspective, real HTTP, infrastructure+routing+middleware tam testi. Staging-də smoke test.

**GitHub Actions CI:**
```yaml
# .github/workflows/api-tests.yml
name: API Tests
on: [push, pull_request]

jobs:
  api-test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
        with: { node-version: 20 }

      - name: Start app
        run: docker-compose up -d && sleep 15

      - name: Install Newman
        run: npm install -g newman newman-reporter-htmlextra

      - name: Run API tests
        run: |
          newman run ./tests/api.postman_collection.json \
            -e ./tests/ci.postman_environment.json \
            --reporters cli,junit,htmlextra \
            --reporter-junit-export results.xml \
            --reporter-htmlextra-export report.html

      - name: Upload test report
        if: always()
        uses: actions/upload-artifact@v4
        with:
          name: api-test-report
          path: report.html
```

**Laravel Artisan Smoke Test Command:**
```php
// app/Console/Commands/SmokeTest.php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SmokeTest extends Command
{
    protected $signature = 'api:smoke {baseUrl}';

    public function handle(): int
    {
        $base = $this->argument('baseUrl');

        $checks = [
            'GET /health'          => fn () => Http::get("$base/health"),
            'GET /api/v1/products' => fn () => Http::get("$base/api/v1/products"),
            'GET /api/v1/orders'   => fn () => Http::withToken(env('SMOKE_TOKEN'))->get("$base/api/v1/orders"),
        ];

        $failed = 0;
        foreach ($checks as $label => $check) {
            $response = $check();
            if ($response->successful()) {
                $this->info("OK  $label  ({$response->status()})");
            } else {
                $this->error("FAIL $label  ({$response->status()})");
                $failed++;
            }
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
```

## Praktik Tapşırıqlar

1. **curl ilə ilk test:** `curl -v https://api.example.com/health` — response header-lərini oxu, timing-i anla.

2. **Postman collection yarat:** Mövcud API üçün List/Create/Get/Delete CRUD collection qur, environment-ə `baseUrl` və `token` əlavə et.

3. **Pre-request JWT refresh:** Postman-də pre-request script yaz — JWT expire olubsa login endpoint-ə sorğu göndərib yeni token al.

4. **Newman CI-ya inteqrasiya:** GitHub Actions workflow yaz, `newman run` ilə collection-ı CI-da run et, JUnit report artifact kimi saxla.

5. **Bruno-ya keç:** Postman collection-ını Bruno-ya import et, `.bru` fayllarını git repoya əlavə et, PR-da diff-i yoxla.

6. **Data-driven test:** CSV faylı yarat (valid/invalid/edge case input-lar), Newman `--iteration-data` ilə run et.

## Əlaqəli Mövzular

- [REST API](08-rest-api.md)
- [OpenAPI & Swagger](38-openapi-swagger.md)
- [API Security](17-api-security.md)
- [OAuth2](14-oauth2.md)
- [Network Troubleshooting](30-network-troubleshooting.md)
