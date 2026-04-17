# API Testing Tools

## Nədir? (What is it?)

API testing tool-ları HTTP (və digər) API-ları manual və ya automated yoxlamaq üçün istifadə edilir. Əsas məqsəd: request qurmaq, response analizini etmək, authentication idarə etmək, suite olaraq test-lər yığmaq, CI-a inteqrasiya etmək.

**Populyar tool-lar:**

| Tool | Tipi | Əsas xüsusiyyət |
|------|------|-----------------|
| **Postman** | GUI + cloud | Collection, environment, scripting, workspaces, monitoring |
| **Bruno** | GUI, open-source | Git-friendly (plain text files), offline-first, no cloud lock-in |
| **Insomnia** | GUI + cloud | Yüngül, plugin, GraphQL-first, Kong-a məxsus |
| **HTTPie** | CLI | Rahat sintaksis, rəngli output, Python |
| **curl** | CLI | Hər yerdə var, universal, script-friendly |
| **REST Client (VSCode)** | IDE extension | `.http` file-ları version control-da saxla |
| **k6** | CLI + load | Load testing, JavaScript script |
| **Newman** | CLI | Postman collection-larını CI-da run et |

Bu tool-lar interview-də tez-tez "API-nı necə debug edirsiniz?", "CI-da API test-i necə qurursunuz?" sualları ilə bağlıdır.

## Necə İşləyir? (How does it work?)

### curl — Universal Foundation

```bash
# GET request
curl https://api.example.com/orders

# With headers
curl -H "Authorization: Bearer eyJ..." \
     -H "Accept: application/json" \
     https://api.example.com/orders

# POST JSON
curl -X POST https://api.example.com/orders \
     -H "Content-Type: application/json" \
     -d '{"product_id":10,"quantity":2}'

# Show request/response headers + timing
curl -v https://api.example.com/orders

# Follow redirects, fail on HTTP errors (CI-friendly)
curl -fsSL https://api.example.com/health

# Save response to file
curl -o response.json https://api.example.com/orders

# Upload file (multipart)
curl -F "file=@photo.jpg" -F "caption=Hello" \
     https://api.example.com/upload

# Response time debugging
curl -w "Time: %{time_total}s\nStatus: %{http_code}\n" \
     -o /dev/null -s https://api.example.com/orders
```

### HTTPie — Human-Friendly CLI

```bash
# Install
pip install httpie     # or: brew install httpie

# GET (default)
http GET api.example.com/orders

# JSON POST (auto-detects)
http POST api.example.com/orders product_id=10 quantity:=2

# Headers
http api.example.com/orders Authorization:"Bearer eyJ..."

# File upload
http -f POST api.example.com/upload photo@./photo.jpg

# Download
http --download api.example.com/report.pdf

# Session (persistent headers/cookies)
http --session=work api.example.com/login username=ali password=123
http --session=work api.example.com/orders   # reuses token
```

Difference from curl: `:=` means raw JSON value (not stringified), `=` means string. HTTPie auto-formats JSON responses with syntax highlighting.

### Postman — GUI, Collections, Scripting

**Core concepts:**

```
Workspace
  └── Collection (Order API v1)
       ├── Folder: Orders
       │    ├── List Orders       GET  /orders
       │    ├── Get Order         GET  /orders/:id
       │    ├── Create Order      POST /orders
       │    └── Update Order      PUT  /orders/:id
       ├── Folder: Products
       └── Pre-request Script (global)
       └── Tests (global)

Environment
  ├── development:  baseUrl=http://localhost:8000, token=dev_xyz
  ├── staging:      baseUrl=https://staging.example.com, token=stg_abc
  └── production:   baseUrl=https://api.example.com, token=prod_pqr

Variable scope:
  global > collection > environment > data > local
```

**Pre-request script** (runs before each request):

```javascript
// Auto-refresh JWT if expired
const token = pm.environment.get('jwt_token');
const expiry = pm.environment.get('jwt_expiry');

if (!token || Date.now() > expiry) {
    pm.sendRequest({
        url: pm.environment.get('baseUrl') + '/auth/refresh',
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

**Test script** (runs after response):

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

// Save value for subsequent requests
const firstOrderId = pm.response.json().data[0].id;
pm.collectionVariables.set('last_order_id', firstOrderId);
```

### Bruno — Git-Friendly Alternative

Bruno bütün collection-ları **plain text `.bru` file** kimi saxlayır. Postman-dən əsas fərq: **cloud yoxdur**, hər şey local + repo-da commit edilir.

Directory structure:

```
my-api-tests/
├── bruno.json                 # collection metadata
├── environments/
│   ├── dev.bru
│   ├── staging.bru
│   └── production.bru
├── orders/
│   ├── list-orders.bru
│   ├── get-order.bru
│   └── create-order.bru
└── products/
    └── list-products.bru
```

Example `.bru` file:

```
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
    "quantity": 2,
    "notes": "Deliver tomorrow"
  }
}

vars:pre-request {
  timestamp: {{$timestamp}}
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

**Üstünlüklər:**
- Pull request-də diff görünür (Postman JSON export çətin oxunur).
- Cloud vendor lock-in yoxdur.
- Offline işləyir, sync servisindən asılı deyil.
- Shell runner var (`bru run`).

### Insomnia — Lightweight Alternative

Bənzər Postman-ə, amma:
- Daha yüngül interface
- GraphQL introspection + auto-complete
- Plugin ecosystem (grpc, cookies, chain dynamic values)
- Kong Inso — enterprise variant
- Team sync (cloud-based, Kong-dan)

### REST Client (VSCode Extension)

`.http` və ya `.rest` file yaradırsan, birbaşa editor-dan request göndərirsən. Version control-da saxlamaq asandır.

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

### Use response from previous request
GET {{baseUrl}}/orders/{{createOrder.response.body.id}}
Authorization: Bearer {{token}}
```

## Əsas Konseptlər (Key Concepts)

### Collection vs Environment vs Variables

```
Collection    = sorted group of requests (logical grouping, e.g., "Order API v1")
Folder        = subgroup within collection
Environment   = set of variables for a context (dev/staging/prod)
Variable      = placeholder like {{baseUrl}}

Variable scopes (overrides):
  Local (single request)
    > Data (from data file in Runner)
      > Environment
        > Collection
          > Global
```

### Authentication Types Supported

```
- No Auth
- Basic Auth     (Authorization: Basic base64(user:pass))
- Bearer Token   (Authorization: Bearer xxx)
- API Key        (header or query param)
- OAuth 1.0
- OAuth 2.0      (Authorization Code, Client Credentials, Implicit, Password)
- AWS Signature v4
- Digest Auth
- NTLM
- Hawk
```

### Newman — Postman in CI

Newman Postman-in CLI runner-idir. Collection + environment JSON-ları alır, CI-da run edir.

```bash
# Install
npm install -g newman newman-reporter-htmlextra

# Run collection
newman run ./api-tests.postman_collection.json \
    -e ./staging.postman_environment.json \
    --reporters cli,json,htmlextra \
    --reporter-htmlextra-export ./newman-report.html

# Fail CI if any test fails
newman run ... --bail  # stop on first failure
```

### Data-Driven Testing

CSV və ya JSON-dan data oxu, hər row üçün request göndər:

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

### Mock Servers

Postman, Bruno, Insomnia mock server yaratmaq imkanı verir — backend hazır olmadan client test edə bilər. OpenAPI spec-dən mock yaratmaq üçün **Prism** (Stoplight) daha güclüdür.

### Workflow Automation Example

```
1. Login (POST /auth)        -> save {{token}}
2. Create customer           -> save {{customer_id}}
3. Create order              -> uses {{customer_id}}, saves {{order_id}}
4. Pay order                 -> uses {{order_id}}
5. Verify order status       -> assertions
6. Cleanup: delete order     -> uses {{order_id}}

Every step uses previous step's output via variables.
Run as a sequence in Collection Runner or Newman.
```

### CI/CD Integration

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

      - name: Publish test summary
        if: always()
        uses: EnricoMi/publish-unit-test-result-action@v2
        with:
          files: results.xml
```

## PHP/Laravel ilə İstifadə

Laravel API-nızı yuxarıdakı tool-larla test edirsiniz. Bəzi konkret inteqrasiyalar:

### Laravel Route-larından Postman Collection Generasiya Etmə

```bash
composer require andreaselia/laravel-api-to-postman --dev
php artisan export:postman
# -> storage/app/postman/collection.json
```

### Scramble / L5-Swagger ilə OpenAPI → Postman Import

```bash
# Scramble üçün (bax: 38-openapi-swagger.md)
curl http://localhost:8000/docs/api.json > openapi.json

# Postman-də File → Import → openapi.json
# Postman OpenAPI spec-dən tam collection qurur
```

### Laravel Feature Tests vs API Tool Tests

```php
// Laravel feature test — unit/integration level
// tests/Feature/OrderApiTest.php
namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

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
# Postman/Newman test — end-to-end, real HTTP
# runs against deployed staging environment
newman run ./postman/orders.collection.json -e ./postman/staging.env.json
```

**Fərq:**
- **Laravel feature tests:** tətbiq daxilində, sürətli, mock-lara sahib. Business logic testi.
- **Postman/Newman:** external perspective, real HTTP, infrastructure+routing+middleware cəm testi. Staging-də smoke test.

### GitHub Actions ilə Newman + Laravel

```yaml
# .github/workflows/e2e.yml
name: E2E API Tests
on:
  push:
    branches: [main]
  pull_request:

jobs:
  e2e:
    runs-on: ubuntu-latest
    services:
      mysql:
        image: mysql:8
        env:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: laravel_test
        ports: ['3306:3306']

    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'

      - name: Install deps
        run: composer install --no-interaction

      - name: Prepare env
        run: |
          cp .env.ci .env
          php artisan key:generate
          php artisan migrate --force
          php artisan db:seed --force

      - name: Start Laravel
        run: php artisan serve --port=8000 &

      - name: Wait for server
        run: |
          until curl -s http://localhost:8000/health; do sleep 1; done

      - uses: actions/setup-node@v4
        with: { node-version: 20 }

      - run: npm install -g newman

      - name: Run API tests
        run: |
          newman run tests/postman/collection.json \
            -e tests/postman/ci.env.json \
            --reporters cli,junit \
            --reporter-junit-export results.xml
```

### Laravel Artisan Command for Smoke Tests

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
            'GET /health'              => fn () => Http::get("$base/health"),
            'GET /api/v1/products'     => fn () => Http::get("$base/api/v1/products"),
            'GET /api/v1/orders'       => fn () => Http::withToken(env('SMOKE_TOKEN'))->get("$base/api/v1/orders"),
        ];

        $failed = 0;
        foreach ($checks as $label => $check) {
            $response = $check();
            if ($response->successful()) {
                $this->info("OK  $label  ({$response->status()}, {$response->handlerStats()['total_time_us']}us)");
            } else {
                $this->error("FAIL $label  ({$response->status()})");
                $failed++;
            }
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
```

## Interview Sualları (Q&A)

### 1. Postman və Bruno arasında əsas fərq nədir?

**Cavab:**
- **Postman:** cloud-first, workspace sync, paid features (runs, monitors), JSON export format çətin diff. Team collaboration güclüdür, amma vendor lock-in riski var.
- **Bruno:** open-source, plain text `.bru` file-ları git-də saxlanılır. Offline, cloud yoxdur. PR review zamanı request dəyişiklikləri aydın görünür. Developer-centric.
Hansını seç: böyük qarışıq komanda + Postman-in premium feature-ları lazımdırsa Postman. Dev-first workflow, git workflow prioritetdirsə Bruno.

### 2. Collection və Environment fərqi?

**Cavab:** **Collection** request-lərin qruplaşdırılmasıdır (məntiqi birləşmə — "Order API", "Auth", "Users"). **Environment** dəyişənlər dəstidir (baseUrl, token, apiKey) — eyni collection-u müxtəlif mühitdə (dev/staging/prod) run etməyə imkan verir. Bir collection + çox environment = eyni test-ləri bütün mühitlərdə run etmə.

### 3. Newman nədir və nə üçün lazımdır?

**Cavab:** Newman — Postman collection-larını CLI-dan run etməyə imkan verən Node.js tool-u. Postman GUI olmadan eyni test-ləri CI-da (GitHub Actions, GitLab, Jenkins) işlətməyə imkan verir. JUnit, HTML, JSON reporter dəstəyi var. Use case: hər PR-da API contract test-ləri, staging-də nightly smoke test, production health check.

### 4. Pre-request script-i nə vaxt istifadə edərsən?

**Cavab:** Request göndərilməzdən əvvəl dinamik hazırlıq lazım olduqda:
- JWT token refresh (expire olsa yenidən al)
- Timestamp və ya UUID generation
- Signature qurma (HMAC, AWS Sig v4)
- Test data yaradılması (fake email, random name)
- Previous response-dan variable çıxarma
- Database cleanup və ya setup

### 5. API test-lərini nə üçün CI-a inteqrasiya edirik?

**Cavab:** (1) Regression prevention — breaking change-lər PR-da dərhal tutulur. (2) Contract verification — API response format client expectation-lara uyğun qalır. (3) Staging quality gate — deployment-dən əvvəl smoke test. (4) Post-deployment verification — live production üçün health check. (5) Documentation accuracy — test pass olur ⇒ example-lar işləyir. Newman + GitHub Actions ilə 5-10 dəqiqəlik suite hər PR-da run olur.

### 6. curl və HTTPie — hansı nə vaxt?

**Cavab:**
- **curl:** hər yerdə var (container, minimal Docker, CI runner). Script-lərdə universal. Bütün edge case dəstəkləyir (HTTP/3, mTLS, custom cipher).
- **HTTPie:** human-friendly, JSON default, rəngli output. Exploratory testing, debug session üçün sürətli.
Praktikada: interactive session-da HTTPie, automation/CI-da curl. `curl -fsSL` CI-da standart (`-f` HTTP error-da fail et, `-s` silent, `-L` redirect follow).

### 7. Mock server nə vaxt lazımdır?

**Cavab:**
- Backend hələ yazılmayıb, frontend paralel işləməlidir.
- Third-party API (Stripe, PayPal) sandbox-unun lazım olmadığı halda offline development.
- Flaky external service-i isolate etmək testlərdə.
- Load testing-də real DB-ni qorumaq.
Postman mock server, Bruno Cat-like mock, Insomnia, və Stoplight Prism (OpenAPI-dən generate) seçimlərdir. Spec-first yanaşmada Prism ən güclüdür.

### 8. Data-driven testing və test coverage arasında əlaqə?

**Cavab:** Data-driven test-də CSV/JSON-dan test case-lər oxunur — eyni request template, müxtəlif input. Coverage artır çünki edge case-lər (boş string, çox uzun, xüsusi simvol, negative rəqəm) ayrı-ayrı yazılmadan təkrar olunur. Newman `--iteration-data` ilə dəstəkləyir. Nümunə: login test — 50 fərqli username/password kombinasiyası, bir template.

### 9. Postman-dən OpenAPI-yə və əksinə keçid necə mümkündür?

**Cavab:**
- **OpenAPI → Postman:** File → Import → openapi.yaml. Postman endpoint-ləri collection kimi qurur. `example` field-ləri body-yə keçir.
- **Postman → OpenAPI:** Native export yoxdur, amma üçüncü tərəf tool-lar var (`apimatic-sdk-transformer`, `postman-to-openapi` npm paketi).
Bu sənəddə daha yaxşı yanaşma: **OpenAPI single source** — tool-ları import üçün istifadə et, manual sync-dən qaç.

### 10. API testing-də təhlükəsizlik testləri necə yerinə yetirilir?

**Cavab:**
- **Authentication bypass:** token olmadan request, başqa user-in resource-u.
- **Authorization:** rol əsaslı giriş (admin-only endpoint-ə adi user).
- **Input fuzzing:** SQL injection, XSS, command injection payload-ları.
- **Rate limiting:** limit aşıldıqda 429 qaytarılmalıdır.
- **CORS:** düzgün origin check.
- **Postman + OWASP ZAP kombinasiyası:** Postman real workflow simulyasiya edir, ZAP trafic-i passive scan edir.
Bu tip test-lər üçün dedicated tool-lar daha yaxşıdır: **OWASP ZAP**, **Burp Suite**, **Nuclei**.

## Best Practices

1. **Collection-ları git-də saxla** — Bruno ideal, Postman export JSON-u da repo-da. Test history və review mümkün olsun.
2. **Environment-dən həssas dəyərləri xaric et** — token, API key, password-ları Postman secret (current value) və ya CI secret-lərində saxla; initial value-də yalnız placeholder.
3. **Global pre-request script yaz** — JWT refresh, timestamp, trace ID generation bütün request-lər üçün mərkəzləşdir.
4. **Test assertion yaz, təkcə request yox** — hər endpoint üçün status, struktur və business rule assertion-ları. "200 gəldi" kifayət deyil.
5. **Newman-i CI-da işlət** — hər PR-da smoke test, nightly full regression. JUnit report ilə GitHub PR-da status göstər.
6. **Environment separasiyasına riayət et** — production environment-də data-mutation test işlətmə; dev/staging üçün ayrı collection folder.
7. **Mock server paralel qur** — backend hazır olmadan frontend çalışa bilsin. Postman mock və ya Stoplight Prism.
8. **Chain request-lər üçün variable istifadə et** — response-dan ID çıxar, növbəti request-də istifadə et. Workflow simulyasiyası.
9. **Hybrid test strategiya qur** — Laravel feature test (fast, mocked), Postman/Newman (end-to-end, real HTTP), OWASP ZAP (security). Hər biri fərqli layer-ə baxır.
10. **Documentation-la inteqrasiya et** — Postman və OpenAPI-nin sync saxlanması. Developer portal-da "Run in Postman" button-u — external consumer-lər üçün asanlıq.
