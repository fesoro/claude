# MCP OAuth 2.0 Autentifikasiyası (Senior)

## MCP üçün OAuth Niyə?

stdio transport, MCP server-i host-un uşaq prosesi kimi işlədildiyi lokal server-lər üçün yaxşı işləyir. Lakin MCP server-ləri uzaqdan — HTTP vasitəsilə əlçatımlı olaraq yerləşdirildikdə autentifikasiyaya ehtiyac var.

MCP 2025-03-26 spesifikasiyası HTTP+SSE transport üçün **OAuth 2.0 avtorizasiya framework-ü** təyin edir. Bu aşağıdakıları mümkün edir:

- Müxtəlif icazə əhatə dairələri olan çoxlu istifadəçilər
- Ləğv edilə bilən giriş tokenları (konfiqurasiya fayllarında paylaşılan sirlər yoxdur)
- Mövcud kimlik təminatçıları ilə inteqrasiya olunan standart OAuth axınları
- İstifadəçi/token əsasında incəlikli tool giriş nəzarəti

---

## MCP OAuth Arxitekturası

Spesifikasiya OAuth axınında üç iştirakçı təyin edir:

### MCP Client (OAuth Client)
MCP server-ə girişə ehtiyac duyan host tətbiqi (Claude Desktop, Claude Code, xüsusi client-inizdir). OAuth axınını başladır və giriş tokenını saxlayır.

### MCP Server (OAuth Resource Server + Authorization Server)
Ən sadə halda, MCP server-in özü həm resource server (tool-ları saxlayır) həm də authorization server (token verir) kimi çıxış edir. Korporativ yerləşdirmələrdə ayrıca OAuth AS istifadə edilir və MCP server-i yalnız tokenları doğrulayır.

### Resurs Sahibi
Girişi icazəyə qoyan istifadəçi. Öz etibarlı məlumatları ilə autentifikasiya edir və tələb olunan əhatə dairələrini təsdiq edir.

### Axın

```
MCP Client                 MCP Server / Auth Server       İstifadəçi (Brauzer)
     |                              |                           |
     |-- GET /.well-known/oauth-  ->|                           |
     |   authorization-server       |                           |
     |<- {authorization_endpoint,  -|                           |
     |    token_endpoint, ...}       |                           |
     |                              |                           |
     |-- İstifadəçini auth-a ------>|------------------>        |
     |   endpoint + PKCE kodu       |                           |
     |   challenge-ə yönləndir      |                           |
     |                              |    Giriş + Razılıq        |
     |                              |<--------------------------|
     |                              |  (istifadəçi əhatə       |
     |                              |   dairələrini təsdiq edir)|
     |<-- Kodla yönləndir ---------|                           |
     |   (redirect_uri vasitəsilə)  |                           |
     |                              |                           |
     |-- POST /oauth/token -------->|                           |
     |   {kod, code_verifier}       |                           |
     |<-- {access_token,           -|                           |
     |     refresh_token, ...}      |                           |
     |                              |                           |
     |-- MCP sorğusu + Bearer ----->|                           |
     |   Authorization başlığı      |                           |
     |<-- MCP cavabı --------------|                           |
```

### PKCE (Proof Key for Code Exchange — Kod Mübadiləsi üçün Sübut Açarı)

MCP spesifikasiyası bütün avtorizasiya kodu axınları üçün PKCE tələb edir. PKCE, MCP client-i public client olduqda (client sirr saxlaya bilmir) avtorizasiya kodu ələkeçirmə hücumlarının qarşısını alır.

PKCE aşağıdakıları əlavə edir:
- `code_verifier` — client tərəfindən generasiya edilmiş təsadüfi 64 baytlıq sətir
- `code_challenge` — code_verifier-in SHA-256 hash-ı, base64url-kodlu
- Server avtorizasiya zamanı challenge-i saxlayır və token mübadiləsi zamanı verifier-i doğrulayır

---

## Kəşf: `/.well-known/oauth-authorization-server`

MCP client-ləri MCP server-in domenindən bu well-known URL-i çəkərək OAuth endpoint-lərini kəşf edir:

```json
{
  "issuer": "https://mcp.sizintətbiqiniz.com",
  "authorization_endpoint": "https://mcp.sizintətbiqiniz.com/oauth/authorize",
  "token_endpoint": "https://mcp.sizintətbiqiniz.com/oauth/token",
  "registration_endpoint": "https://mcp.sizintətbiqiniz.com/oauth/clients",
  "scopes_supported": ["mcp:tools:read", "mcp:tools:write", "mcp:resources:read"],
  "response_types_supported": ["code"],
  "grant_types_supported": ["authorization_code", "refresh_token"],
  "code_challenge_methods_supported": ["S256"],
  "token_endpoint_auth_methods_supported": ["none", "client_secret_basic"]
}
```

---

## Laravel İmplementasiyası

OAuth server kimi **Laravel Passport** istifadə edirik. Passport qutudan çıxan PKCE dəstəyi ilə OAuth 2.0-ı tətbiq edir.

### 1. Laravel Passport-u Quraşdırın və Konfiqurasiya Edin

```bash
composer require laravel/passport
php artisan install:api --passport
php artisan passport:keys
```

### 2. MCP Server Kəşf Endpoint-i

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mcp;

use Illuminate\Http\JsonResponse;

final class OAuthDiscoveryController
{
    public function __invoke(): JsonResponse
    {
        $base = config('app.url');

        return response()->json([
            'issuer'                               => $base,
            'authorization_endpoint'               => "{$base}/oauth/authorize",
            'token_endpoint'                       => "{$base}/oauth/token",
            'registration_endpoint'                => "{$base}/mcp/oauth/register",
            'scopes_supported'                     => [
                'mcp:tools:read',
                'mcp:tools:write',
                'mcp:resources:read',
                'mcp:resources:write',
                'mcp:prompts:read',
            ],
            'response_types_supported'             => ['code'],
            'grant_types_supported'                => ['authorization_code', 'refresh_token'],
            'code_challenge_methods_supported'     => ['S256'],
            'token_endpoint_auth_methods_supported' => ['none'],
        ]);
    }
}
```

Marşrutu qeydiyyata alın (autentifikasiya edilməmiş olmalıdır):

```php
// routes/web.php
Route::get('/.well-known/oauth-authorization-server', App\Http\Controllers\Mcp\OAuthDiscoveryController::class);
```

### 3. Dinamik Client Qeydiyyatı

Dinamik qeydiyyatı dəstəkləyən MCP client-ləri OAuth axınına başlamazdan əvvəl özlərini qeyd edə bilərlər:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mcp;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Passport\ClientRepository;

final class ClientRegistrationController
{
    public function __construct(
        private readonly ClientRepository $clients,
    ) {}

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'client_name'    => ['required', 'string', 'max:255'],
            'redirect_uris'  => ['required', 'array', 'min:1'],
            'redirect_uris.*' => ['url'],
            'grant_types'    => ['sometimes', 'array'],
            'scope'          => ['sometimes', 'string'],
        ]);

        // Public Passport client yarat (PKCE axınları üçün sirr yoxdur)
        $client = $this->clients->createPublicAuthCodeClient(
            userId:      null, // Xüsusi bir istifadəçi ilə əlaqəli deyil
            name:        $validated['client_name'],
            redirect:    implode(',', $validated['redirect_uris']),
            provider:    null,
            personalAccessClient: false,
        );

        return response()->json([
            'client_id'              => $client->id,
            'client_name'            => $client->name,
            'redirect_uris'          => $validated['redirect_uris'],
            'grant_types'            => ['authorization_code', 'refresh_token'],
            'token_endpoint_auth_method' => 'none',
        ], 201);
    }
}
```

```php
// routes/api.php
Route::post('/mcp/oauth/register', App\Http\Controllers\Mcp\ClientRegistrationController::class);
```

### 4. MCP Endpoint-ləri üçün Token Doğrulama Middleware-i

```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * MCP HTTP endpoint-lərindəki Bearer tokenləri doğrulayır.
 *
 * Yoxlayır:
 * 1. Bearer token mövcuddur
 * 2. Token etibarlıdır (Passport vasitəsilə)
 * 3. Tokenin tələb olunan MCP əhatə dairəsi var
 */
final class ValidateMcpToken
{
    public function handle(Request $request, Closure $next, string ...$requiredScopes): Response
    {
        $token = $request->bearerToken();

        if ($token === null) {
            return response()->json([
                'error'             => 'unauthorized',
                'error_description' => 'Bearer token tələb olunur',
            ], 401, [
                'WWW-Authenticate' => 'Bearer realm="mcp", scope="' . implode(' ', $requiredScopes) . '"',
            ]);
        }

        // Passport-un token mühafizəsini istifadə edin
        $user = auth('api')->user();

        if ($user === null) {
            return response()->json([
                'error'             => 'invalid_token',
                'error_description' => 'Token etibarsızdır və ya müddəti bitib',
            ], 401);
        }

        // Tələb olunan əhatə dairələrini yoxlayın
        foreach ($requiredScopes as $scope) {
            if (! $user->tokenCan($scope)) {
                return response()->json([
                    'error'             => 'insufficient_scope',
                    'error_description' => "Token tələb olunan əhatə dairəsindən məhrumdur: {$scope}",
                    'scope'             => $scope,
                ], 403);
            }
        }

        return $next($request);
    }
}
```

`bootstrap/app.php`-də qeyd edin:
```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'mcp.token' => ValidateMcpToken::class,
    ]);
})
```

### 5. HTTP+SSE MCP Server Endpoint-ləri

HTTP transport aşağıdakıları istifadə edir:
- `POST /mcp` — client-dən server-ə sorğular üçün
- `GET /mcp/sse` — server-dən client-ə mesajlar üçün SSE axını (bildirişlər)

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mcp;

use App\Mcp\McpServer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * MCP server-i üçün HTTP transport endpoint-i.
 *
 * POST /mcp — JSON-RPC sorğularını idarə edir
 * GET  /mcp/sse — server bildirişləri üçün SSE axını
 */
final class McpHttpController
{
    public function __construct(
        private readonly McpServer $server,
    ) {}

    /**
     * HTTP vasitəsilə tək JSON-RPC sorğusunu idarə edin.
     */
    public function handle(Request $request): JsonResponse
    {
        $body = $request->json()->all();

        if (empty($body) || ! isset($body['jsonrpc'])) {
            return response()->json([
                'jsonrpc' => '2.0',
                'id'      => null,
                'error'   => ['code' => -32700, 'message' => 'Parse xətası'],
            ], 400);
        }

        // Uyğun idarəediciyə yönləndir
        $response = $this->server->handleHttpRequest($body, $request->user());

        if ($response === null) {
            // Bildiriş — cavab gövdəsi yoxdur
            return response()->json(null, 204);
        }

        return response()->json($response);
    }

    /**
     * Server-dən client-ə bildirişlər üçün SSE endpoint-i.
     */
    public function sse(Request $request): StreamedResponse
    {
        $userId = $request->user()->id;

        return response()->stream(function () use ($userId) {
            // İlkin "qoşuldu" hadisəsini göndər
            echo "event: connected\n";
            echo "data: {\"type\":\"connected\"}\n\n";
            flush();

            // Əlaqəni açıq saxla və növbəyə alınmış bildirişləri ötür
            $lastCheck = time();
            while (! connection_aborted()) {
                // Hər 15 saniyədə bir heartbeat
                if (time() - $lastCheck >= 15) {
                    echo ": heartbeat\n\n";
                    flush();
                    $lastCheck = time();
                }

                // Gözləyən bildirişləri yoxlayın (digər proseslər tərəfindən cache/DB-də saxlanılır)
                $notifications = $this->server->flushNotifications($userId);
                foreach ($notifications as $notification) {
                    echo "event: notification\n";
                    echo 'data: ' . json_encode($notification) . "\n\n";
                    flush();
                }

                usleep(500_000); // 500ms yoxlama intervalı
            }
        }, 200, [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
```

```php
// routes/api.php
Route::middleware(['auth:api', 'mcp.token:mcp:tools:read'])->group(function () {
    Route::post('/mcp',     [McpHttpController::class, 'handle']);
    Route::get('/mcp/sse',  [McpHttpController::class, 'sse']);
});
```

### 6. Əhatə Dairəsinə Uyğun Tool İdarəedicisi

OAuth ilə tool-lar tokenin əhatə dairələrinə əsasən şərti olaraq mövcud ola bilər:

```php
<?php

declare(strict_types=1);

namespace App\Mcp\Handlers;

use App\Mcp\McpException;
use Illuminate\Foundation\Auth\User;

final class ScopeAwareToolsHandler
{
    /** @var array<string, string[]> toolAdı => tələb olunan əhatə dairələri */
    private const TOOL_SCOPES = [
        'list_routes'  => ['mcp:tools:read'],
        'run_query'    => ['mcp:tools:read', 'mcp:resources:read'],
        'write_file'   => ['mcp:tools:write'],
        'delete_record' => ['mcp:tools:write'],
        'read_logs'    => ['mcp:resources:read'],
    ];

    public function list(array $params, ?User $user = null): array
    {
        $allTools = $this->getAllToolDefinitions();

        if ($user === null) {
            return ['tools' => $allTools];
        }

        // İstifadəçinin token əhatə dairələrinə əsasən tool-ları filtr edin
        $accessible = array_filter($allTools, function (array $tool) use ($user) {
            $required = self::TOOL_SCOPES[$tool['name']] ?? [];
            foreach ($required as $scope) {
                if (! $user->tokenCan($scope)) {
                    return false;
                }
            }
            return true;
        });

        return ['tools' => array_values($accessible)];
    }

    public function call(array $params, ?User $user = null): array
    {
        $name = $params['name'] ?? throw new McpException('Tool adı çatışmır', -32602);

        // İcra etməzdən əvvəl əhatə dairələrini yoxlayın
        if ($user !== null) {
            $required = self::TOOL_SCOPES[$name] ?? [];
            foreach ($required as $scope) {
                if (! $user->tokenCan($scope)) {
                    throw new McpException(
                        "Kifayətsiz əhatə dairəsi: {$name} tool-u üçün {$scope} tələb olunur",
                        -32000, // Tətbiq tərəfindən təyin edilmiş xəta
                    );
                }
            }
        }

        // Tool implementasiyasına yönləndir
        return match ($name) {
            'list_routes' => $this->listRoutes($params['arguments'] ?? []),
            'run_query'   => $this->runQuery($params['arguments'] ?? []),
            default       => throw new McpException("Naməlum tool: {$name}", -32601),
        };
    }

    private function getAllToolDefinitions(): array
    {
        return [
            [
                'name'        => 'list_routes',
                'description' => 'Laravel marşrutlarını siyahıla',
                'inputSchema' => ['type' => 'object', 'properties' => new \stdClass()],
            ],
            [
                'name'        => 'run_query',
                'description' => 'Yalnız oxuma verilənlər bazası sorğusu icra et',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'sql' => ['type' => 'string'],
                    ],
                    'required'   => ['sql'],
                ],
            ],
            [
                'name'        => 'write_file',
                'description' => 'Fayla məzmun yaz',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'path'    => ['type' => 'string'],
                        'content' => ['type' => 'string'],
                    ],
                    'required'   => ['path', 'content'],
                ],
            ],
        ];
    }

    private function listRoutes(array $args): array
    {
        // İmplementasiya...
        return ['content' => [['type' => 'text', 'text' => '[]']], 'isError' => false];
    }

    private function runQuery(array $args): array
    {
        // İmplementasiya...
        return ['content' => [['type' => 'text', 'text' => '[]']], 'isError' => false];
    }
}
```

---

## Claude Desktop-u OAuth Tokenlarından İstifadə Etmək üçün Konfiqurasiya Etmək

Claude Desktop (0.10+ versiyasından etibarən) uzaq MCP server-ləri üçün OAuth dəstəkləyir. Konfiqurasiya:

```json
{
  "mcpServers": {
    "my-remote-server": {
      "url": "https://mcp.sizintətbiqiniz.com/mcp",
      "transport": "http"
    }
  }
}
```

Claude Desktop HTTP MCP server-inə qoşulduqda:
1. Server-in domenindən `/.well-known/oauth-authorization-server` çəkir.
2. PKCE ilə OAuth avtorizasiya kodu axınını başladır.
3. İstifadəçinin autentifikasiya etməsi üçün brauzer pəncərəsi açılır.
4. Razılıqdan sonra Claude Desktop tokenları saxlayır və sonrakı sorğularda istifadə edir.
5. Tokenlar refresh token istifadə edərək avtomatik yenilənir.

İstifadəçi icazə dialoqu görür:
```
"my-remote-server" aşağıdakıları etmək istəyir:
- Tool-ları və resursları oxu (mcp:tools:read, mcp:resources:read)

[İcazə Ver] [Rədd Et]
```

---

## MCP üçün Passport Konfiqurasiyası

```php
// app/Providers/AppServiceProvider.php
use Laravel\Passport\Passport;

public function boot(): void
{
    Passport::tokensCan([
        'mcp:tools:read'       => 'Yalnız oxuma MCP tool-larını oxu və icra et',
        'mcp:tools:write'      => 'Yazma MCP tool-larını icra et',
        'mcp:resources:read'   => 'MCP resurslarını oxu',
        'mcp:resources:write'  => 'MCP resurslarına yaz',
        'mcp:prompts:read'     => 'MCP prompt şablonlarını oxu',
    ]);

    // Tokenlar 8 saat etibarlıdır — təhlükəsizlik üçün kifayət qədər qısa
    Passport::tokensExpireIn(now()->addHours(8));

    // Refresh tokenlar 30 gün etibarlıdır
    Passport::refreshTokensExpireIn(now()->addDays(30));

    // Public client-lərə icazə ver (client sirr yoxdur, PKCE üçün)
    Passport::enableImplicitGrant();
}
```

---

## Təhlükəsizlik Mülahizələri

### Claude Desktop-da Token Saxlanması
Tokenlar OS açar zəncirinə (macOS Keychain, Windows Credential Manager, Linux Secret Service) saxlanılır. Açıq mətn konfiqurasiya fayllarında saxlanılmır.

### Əhatə Dairəsi Minimizasiyası
Yalnız ehtiyacınız olan əhatə dairələrini tələb edin. Yalnız oxuma MCP client-i yalnız `mcp:tools:read` və `mcp:resources:read` tələb etməlidir. Bu ən az imtiyaz prinsipinə uyğundur.

### PKCE Məcburidir
Heç vaxt PKCE olmadan MCP OAuth axını tətbiq etməyin. Public client-lər (desktop tətbiqləri, CLI tool-ları) client sirr saxlaya bilmir, buna görə PKCE olmadan kod ələkeçirmə hücumları real bir riskdir.

### Refresh Token Rotasiyası
Refresh tokenları fırlatmaq üçün Passport-u konfiqurasiya edin:
```php
Passport::revokeOtherTokens(); // Yeniləmədə köhnə tokenları ləğv et
```

### Audit Loglaması
Hər tool çağırışını əlaqəli istifadəçi və token ilə qeydə alın:
```php
// Tool idarəedicinizda
Log::info('MCP tool icra edildi', [
    'tool'    => $toolName,
    'user_id' => $user?->id,
    'token_id' => $request->user()?->token()?->id,
    'scopes'  => $request->user()?->token()?->scopes,
]);
```
