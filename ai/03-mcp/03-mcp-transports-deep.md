# MCP Transports Deep Dive — stdio vs SSE vs Streamable HTTP (Senior)

## Məzmun

1. [Transport Niyə Vacibdir?](#transport-nı̇yə-vacı̇bdı̇r)
2. [Üç Transport-un Qısa Xülasəsi](#üç-transport-un-qısa-xülasəsi)
3. [stdio Transport — Dərinlikli](#stdio-transport--dərinlikli)
4. [HTTP + SSE Transport — Niyə Köhnəlmişdir (Deprecated)](#http--sse-transport--niyə-köhnəlmişdir-deprecated)
5. [Streamable HTTP — Yeni Standart](#streamable-http--yeni-standart)
6. [Connection Lifecycle — Üçü də Yan-yana](#connection-lifecycle--üçü-də-yan-yana)
7. [Message Framing — Baytların Arxasında](#message-framing--baytların-arxasında)
8. [Reconnection və Multiplexing](#reconnection-və-multiplexing)
9. [Hansını Nə Vaxt Seçmək](#hansını-nə-vaxt-seçmək)
10. [Laravel ilə Streamable HTTP Server](#laravel-ı̇lə-streamable-http-server)
11. [Packet-Level Walkthrough: initialize + tools/call](#packet-level-walkthrough-initialize--toolscall)
12. [Ümumi Transport Xətaları](#ümumi-transport-xətaları)

---

## Transport Niyə Vacibdir?

MCP protokol olaraq JSON-RPC 2.0 üzərində qurulub — bu, ardıcıl mesajları necə kodladığımızı təyin edir. Amma bu mesajları "necə daşıyırıq?" sualı transport qatına aiddir. Transport seçimi aşağıdakıları birbaşa təyin edir:

- **Server harada işləyir?** Lokal proses, yoxsa uzaq server?
- **Auth necə işləyir?** Proses sərhədi, yoxsa OAuth?
- **Bir server neçə istemciyə xidmət edir?** 1:1, yoxsa çox istemci?
- **Debug necə gedir?** Loglar hara yazılır, mesajları necə capture edirsən?
- **Reconnect nə vaxt baş verir?** Heç vaxt, proses restart-da, şəbəkə kəsintisində?

Yanlış transport seçmək — məsələn, bulud serverini stdio kimi dizayn etmək — sonradan yenidən yazmağa məcbur edir. Bu fayl hər üçünü detalda izah edir ki, bir dəfə düzgün seçə biləsən.

---

## Üç Transport-un Qısa Xülasəsi

```
┌───────────────┬──────────────────────┬─────────────────┬──────────────────────┐
│ Transport     │ İstifadə Yeri        │ Status (2026)   │ Auth                 │
├───────────────┼──────────────────────┼─────────────────┼──────────────────────┤
│ stdio         │ Lokal dev toolları   │ Cari / stabil   │ Yoxdur (proses)      │
│ HTTP + SSE    │ Uzaq serverlər       │ Deprecated      │ OAuth 2.0            │
│ Streamable    │ Uzaq serverlər       │ Cari / stabil   │ OAuth 2.0 (Bearer)   │
│ HTTP          │ (bulud, multi-user)  │ (yeni standart) │                      │
└───────────────┴──────────────────────┴─────────────────┴──────────────────────┘
```

2025-03-26 spesifikasiyasında SSE transport `Streamable HTTP` ilə əvəz olundu. 2025-11 spesifikasiyasında (2026-cı il etibarilə cari) SSE tamamilə köhnəlmiş hesab olunur — yeni client-lər dəstəkləməyə bilər. Lakin hələ də "field-də" çalışan köhnə MCP serverləri var, buna görə hər üçünü bilmək lazımdır.

---

## stdio Transport — Dərinlikli

### Necə İşləyir?

stdio transport `sadə və güclü` bir ideadır: **host serveri öz uşaq prosesi (child process) kimi fork edir**. Ünsiyyət üç standart POSIX axını ilə gedir:

```
┌───────────────┐                        ┌──────────────────┐
│   Host        │                        │   MCP Server     │
│  (Claude      │                        │   (Laravel       │
│   Desktop)    │                        │    artisan cmd)  │
│               │   spawn child process   │                  │
│               ├────────────────────────>│                  │
│               │                        │                  │
│ stdin (write) ├────── JSON-RPC ───────>│ stdin (read)     │
│ stdout (read) │<───── JSON-RPC ────────┤ stdout (write)   │
│ stderr (read) │<───── loglar ──────────┤ stderr (write)   │
│               │                        │                  │
│               │   exit / SIGTERM       │                  │
│               ├────────────────────────>│                  │
└───────────────┘                        └──────────────────┘
```

Host, server prosesini `posix_spawn(3)` və ya `fork(2) + execve(2)` ilə başladır. Sonra üç pipe yaradılır:
- Host `stdin`-ə yazır → server `stdin`-dən oxuyur
- Server `stdout`-a yazır → host `stdout`-dan oxuyur
- Server `stderr`-ə yazır → host `stderr`-dən oxuyur (loglar, debug)

**Kritik qayda:** `stdout` protokol kanalıdır. Oraya yazılan hər şey JSON-RPC mesajı olmalıdır. Əgər bir `echo`, `var_dump`, PHP warning və ya Laravel log cərəyanı stdout-a düşsə, host bütün mesaj axınını parse edə bilməyəcək və bağlantı pozulacaq. Bu, PHP developer-lər üçün ən çox rast gəlinən tələdir.

### Mesaj Framing — stdio üçün

stdio `line-delimited JSON` (LDJSON, həmçinin NDJSON adlanır) istifadə edir. Hər JSON-RPC mesajı:
1. Bir sətirdə olmalıdır (daxildə `\n` olmamalıdır)
2. `\n` (LF, 0x0A) ilə bitməlidir
3. UTF-8 kodlanmış olmalıdır

```
{"jsonrpc":"2.0","id":1,"method":"initialize","params":{...}}\n
{"jsonrpc":"2.0","id":2,"method":"tools/list","params":{}}\n
```

Host oxuyucu tərəfi `fgets()` və ya `readline`-ə bənzər bir mexanizmlə hər sətri alır, parse edir, istemciyə yönləndirir.

### Proses Həyat Dövrü

```
[Host işə düşür]
    │
    ▼
[Host konfiqurasiya oxuyur: claude_desktop_config.json]
    │
    ▼
[Host: spawn("php", ["artisan","mcp:serve"], env={...})]
    │
    ▼
[Server prosesi başladı, PID=12345]
    │
    ▼
[Host → server stdin: initialize request]
[Host ← server stdout: initialize response]
    │
    ▼
[Host → server stdin: initialized notification]
    │
    ▼
[...normal iş — tools/list, tools/call, ...]
    │
    ▼
[Host dayandırır: stdin-i bağlayır, sonra SIGTERM (15 san gözləyir), sonra SIGKILL]
    │
    ▼
[Server prosesi öldürüldü]
```

Server **həmişə** host tərəfindən idarə olunur. Server özü ayrı-ayrı yaşaya bilməz. Bu, stdio-nun ən böyük məhdudiyyətidir: **horizontal scale yoxdur**.

### stdio-nun Üstünlükləri

1. **Sıfır network setup** — firewall, DNS, TLS, CORS yoxdur
2. **Sıfır auth** — proses eyni istifadəçi altında işləyir, OS-dan gələn izolyasiya
3. **Aşağı latency** — pipe-lar IPC üçün çox sürətlidir (mikrosaniyələr)
4. **Sadə deployment** — bir binary/script, konfiqurasiyada qeyd et, işlədir
5. **Hər dildə asan** — stdin/stdout hər yerdədir

### stdio-nun Çətinlikləri — Debug Ağrısı

Bu, real PHP/Laravel layihələrində ən acı təcrübədir:

**Problem 1: stdout çirklənir.** Bir `dd()`, `var_dump`, PHP deprecation notice, Laravel `Log::info('...')` default channel-də varsa və channel stdout-a yazırsa, protokol ölür. Səssiz şəkildə. Host "Server disconnected" deyir və sən saatlarla axtarırsan.

```php
// YANLIŞ — stdio serverini dərhal öldürür
echo "Starting server...\n"; // stdout!
var_dump($request); // stdout!
Log::info('got request', ['r' => $req]); // Laravel default channel-ə getsə stdout-a düşə bilər

// DÜZGÜN
fwrite(STDERR, "Starting server...\n"); // stderr
Log::channel('stderr')->info('got request'); // konkret channel
```

**Problem 2: Output buffering.** PHP-nin `output_buffering` php.ini-də ON-dursa, stdout-a yazdıqların bufferə düşür və `fflush` edənə qədər host görmür. Kod:

```php
// Əmrin başında
while (ob_get_level() > 0) {
    ob_end_clean();
}
ini_set('output_buffering', 'off');
ini_set('implicit_flush', '1');
ob_implicit_flush(true);
```

**Problem 3: Proses çökəndə host heç nə göstərmir.** Əgər server fatal error verirsə, Claude Desktop sadəcə "failed" deyir. Logları görmək üçün:

- macOS: `~/Library/Logs/Claude/mcp-server-<name>.log`
- Linux: `~/.config/Claude/logs/mcp-server-<name>.log`
- Windows: `%APPDATA%\Claude\logs\mcp-server-<name>.log`

Bu fayllar stderr-i capture edir. Buna görə `fwrite(STDERR, ...)` və `error_log()` istifadə etmək lazımdır.

**Problem 4: Environment variables.** stdio serveri host-un mühit dəyişənlərini **miras almır**. Hər şeyi `claude_desktop_config.json`-un `env` blokunda açıqca vermək lazımdır:

```json
{
  "mcpServers": {
    "laravel-mcp": {
      "command": "php",
      "args": ["/var/www/app/artisan", "mcp:serve"],
      "env": {
        "APP_ENV": "local",
        "DB_HOST": "127.0.0.1",
        "DB_DATABASE": "my_app",
        "PATH": "/usr/local/bin:/usr/bin:/bin"
      }
    }
  }
}
```

`PATH`-i də qeyd edin — əks halda `composer`, `node`, `mysqldump` kimi alətlər tapılmaya bilər.

### stdio — Nümunə Wire Trace

```
[host→server stdin]
{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-03-26","capabilities":{},"clientInfo":{"name":"claude-desktop","version":"0.10.4"}}}

[server→host stdout]
{"jsonrpc":"2.0","id":1,"result":{"protocolVersion":"2025-03-26","capabilities":{"tools":{"listChanged":false}},"serverInfo":{"name":"laravel-mcp","version":"1.0.0"}}}

[host→server stdin]
{"jsonrpc":"2.0","method":"notifications/initialized"}

[server→host stderr]
[MCP] handshake completed, client=claude-desktop/0.10.4

[host→server stdin]
{"jsonrpc":"2.0","id":2,"method":"tools/list","params":{}}

[server→host stdout]
{"jsonrpc":"2.0","id":2,"result":{"tools":[{"name":"list_models","description":"...","inputSchema":{...}}]}}
```

---

## HTTP + SSE Transport — Niyə Köhnəlmişdir (Deprecated)

### Necə İşləyirdi?

İlk uzaq transport iki ayrı endpoint istifadə edirdi:

```
┌──────────┐                                 ┌────────────┐
│  Client  │                                 │   Server   │
│          │                                 │            │
│          │── POST /mcp/sse ───────────────>│            │
│          │   (client-dən server-ə)         │            │
│          │                                 │            │
│          │── GET  /mcp/sse ───────────────>│            │
│          │   (keep-alive, server-dən       │            │
│          │    client-ə push)               │            │
│          │                                 │            │
│          │<═══ event: message ════════════ │            │
│          │     data: {jsonrpc:...}         │            │
│          │<═══ event: message ════════════ │            │
└──────────┘                                 └────────────┘
```

İki endpoint problemi:
1. **POST** — JSON-RPC sorğularını göndərmək üçün (client → server)
2. **GET** — SSE (Server-Sent Events) açıq bağlantısı, server push üçün (server → client)

Client POST edir, server dərhal `202 Accepted` qaytarır. Sonra server cavabı GET SSE axınına push edir. Client cavabı `id` ilə uyğunlaşdırır.

### Niyə Köhnəldi?

SSE transport bir neçə ciddi problem yaratdı:

1. **İki endpoint = iki auth.** Hər ikisində Bearer token, hər ikisində rate limit, hər ikisində OAuth scope yoxlamaq lazımdır.
2. **Session tracking.** Server hansı GET-in hansı POST-a aid olduğunu bilməlidir. Bu session ID-lər ilə idarə olunurdu — gizli state, çətin scale.
3. **Load balancer problemi.** POST bir pod-a, GET isə başqa pod-a düşəndə cavab tapılmır. Sticky session lazım idi.
4. **Reconnect mürəkkəbliyi.** SSE bağlantısı kəsiləndə client GET-i yenidən açmalı və server hansı mesajların təkrar göndəriləcəyini bilməli idi (`Last-Event-ID` başlığı).
5. **Firewall/proxy problemləri.** Bəzi korporativ proxy-lər uzunmüddətli GET bağlantılarını 30 saniyədən sonra bağlayır, SSE-ni effektiv şəkildə sındırır.
6. **Bidirectional değil.** Server client-ə push edə bilirdi, ancaq client → server yalnız POST idi. Streaming upload mümkün deyildi.

### SSE Deprecation Timeline

- **2025-03-26:** `Streamable HTTP` təqdim olundu, SSE hələ də dəstəklənir ("legacy" kimi).
- **2025-11:** Spesifikasiya SSE-ni `deprecated` elan etdi.
- **2026-Q2+:** Yeni Claude Desktop və Claude Code versiyaları SSE-ni artıq dəstəkləməyə bilər.

Əgər köhnə SSE serverinə sahibsənsə — migrate et. Növbəti bölmə niyə və necə göstərir.

---

## Streamable HTTP — Yeni Standart

### Necə İşləyir?

Streamable HTTP **tək endpoint** dizaynıdır: `POST /mcp`. Hər POST JSON-RPC sorğusudur. Server cavabı **ya birbaşa JSON cavabı** kimi qaytara bilər (sadə hal), **ya da SSE streamə** upgrade edə bilər (server push, streaming, notification üçün).

```
┌──────────┐                                 ┌────────────┐
│  Client  │                                 │   Server   │
│          │                                 │            │
│          │── POST /mcp ───────────────────>│            │
│          │   Content-Type: application/json│            │
│          │   Accept: application/json,     │            │
│          │           text/event-stream     │            │
│          │   Body: {jsonrpc:2.0, ...}      │            │
│          │                                 │            │
│          │   Server qərar verir:           │            │
│          │                                 │            │
│   YOL 1: │<──── 200 OK ────────────────────│  (sadə)    │
│          │     Content-Type: application/  │            │
│          │                    json         │            │
│          │     Body: {jsonrpc, id, result} │            │
│          │                                 │            │
│   YOL 2: │<──── 200 OK ────────────────────│  (stream)  │
│          │     Content-Type: text/event-   │            │
│          │                    stream       │            │
│          │     event: message              │            │
│          │     data: {progress: 0.5}       │            │
│          │                                 │            │
│          │     event: message              │            │
│          │     data: {jsonrpc, id, result} │            │
└──────────┘                                 └────────────┘
```

Server-in iki cavab rejimi var:
- **Immediate JSON** — sadə cavab, kiçik, tez
- **SSE stream** — uzun proseslər, progress updates, çoxlu hadisələr

Client hər ikisini qəbul etməlidir. `Accept: application/json, text/event-stream` başlığı göndərilir.

### Üstünlüklər

1. **Tək endpoint, tək auth.** Bütün trafik `POST /mcp`-dən keçir. Bearer token, rate limit, scope, bir yerdə.
2. **Stateless əsas.** Hər POST müstəqildir. Server state-i saxlamaq istəmirsə məcbur deyil.
3. **Sticky session yoxdur.** Hər POST istənilən pod-a düşə bilər (bəzi hallarda session ID istifadə etmək mümkün, amma məcburi deyil).
4. **Streaming when needed.** Uzun tool-lar (məs. 30 saniyə çəkən data analysis) progress notification göndərə bilər.
5. **Standart HTTP.** CDN, load balancer, WAF, OpenTelemetry middleware — hər şey işləyir.
6. **Bidirectional via POST-per-message.** Client push üçün GET bağlantısı lazım deyil — server initiated mesaj üçün ayrı bir session endpoint-i istifadə olunur.

### Server Initiated Messages (Notifications, Sampling)

Server client-ə mesaj göndərmək istədikdə (notification və ya sampling request):
- Server **client-in açdığı SSE axınında** push edir (əgər stream rejimində cavab varsa)
- Və ya ayrı **`GET /mcp`** uzun-canlı SSE bağlantısı açıla bilər (opsional)

Spesifikasiyada session ID `Mcp-Session-Id` başlığı ilə ötürülür. Server session yaradanda ilk cavabda bu başlığı qaytarır, client sonrakı bütün sorğularda onu daxil edir.

### Wire Nümunəsi — Sadə Hal

```http
POST /mcp HTTP/1.1
Host: mcp.company.com
Authorization: Bearer eyJhbGc...
Content-Type: application/json
Accept: application/json, text/event-stream

{"jsonrpc":"2.0","id":42,"method":"tools/call","params":{"name":"get_customer","arguments":{"id":123}}}
```

```http
HTTP/1.1 200 OK
Content-Type: application/json
Mcp-Session-Id: 7f3a2b1e-9c4d-4a6b-8e1f-2d5c7a9b3e0a

{"jsonrpc":"2.0","id":42,"result":{"content":[{"type":"text","text":"{\"id\":123,\"name\":\"Alice\"}"}],"isError":false}}
```

### Wire Nümunəsi — Streaming Hal

```http
POST /mcp HTTP/1.1
Host: mcp.company.com
Authorization: Bearer eyJhbGc...
Content-Type: application/json
Accept: application/json, text/event-stream

{"jsonrpc":"2.0","id":43,"method":"tools/call","params":{"name":"analyze_full_database","arguments":{}}}
```

```http
HTTP/1.1 200 OK
Content-Type: text/event-stream
Cache-Control: no-cache
Mcp-Session-Id: 7f3a2b1e-9c4d-4a6b-8e1f-2d5c7a9b3e0a

event: message
data: {"jsonrpc":"2.0","method":"notifications/progress","params":{"progressToken":"t1","progress":0.25,"message":"Scanning users..."}}

event: message
data: {"jsonrpc":"2.0","method":"notifications/progress","params":{"progressToken":"t1","progress":0.75,"message":"Indexing orders..."}}

event: message
data: {"jsonrpc":"2.0","id":43,"result":{"content":[{"type":"text","text":"Analysis complete."}],"isError":false}}

```

SSE event-lərinin formatı:
- Hər event `event:` (optional) və `data:` xəttləri ilə başlayır
- Boş xətt (`\n\n`) event-i bitirir
- Hər `data:` sətri JSON mesajıdır (bizim halda JSON-RPC)

---

## Connection Lifecycle — Üçü də Yan-yana

```
                stdio                 SSE (deprecated)        Streamable HTTP
                ─────                 ────────────────        ───────────────
Connect         spawn(server)         GET /mcp/sse            (none — every
                                      POST /mcp/messages       POST is "connect")

Handshake       initialize over       initialize over          initialize in
                stdin/stdout          POST+SSE                 POST body

Auth            None (OS level)       OAuth Bearer             OAuth Bearer

Lifetime        Until host exits      Until GET closes         Per-request (or
                                                               Mcp-Session-Id TTL)

Server→client   Always available      SSE channel              Response SSE
push                                                           stream (or session
                                                               GET)

Reconnect       Restart process       Last-Event-ID            Resume via
                                      header                   session-id (if
                                                               server keeps state)

Multiplex       1:1 only              N clients per server     N clients per
                                                               server, per pod
```

---

## Message Framing — Baytların Arxasında

### stdio: LDJSON (Line-Delimited JSON)

```
{"jsonrpc":"2.0","id":1,"method":"ping"}\n
```

Bir mesaj = bir sətir = `\n`-ə qədər. Server oxumaq üçün `fgets()` (PHP) və ya `readline` (Node) istifadə edir.

### HTTP + SSE: Mixed

- Client → server: normal JSON body `POST /mcp/messages`
- Server → client: SSE event-lər `GET /mcp/sse` axınında

### Streamable HTTP: Content-Type-əsaslı

- Client → server: `application/json` POST body
- Server → client: ya `application/json` cavabı (tək mesaj), ya da `text/event-stream` (çox mesaj)

```
SSE frame formatı (Streamable HTTP-də):

event: message\n
data: {"jsonrpc":"2.0","id":1,"result":{...}}\n
\n
```

SSE-də `\n\n` (iki yeni sətir) event-in sonunu göstərir. `data:` multiline ola bilər amma JSON-RPC üçün bir xətt kifayət edir.

---

## Reconnection və Multiplexing

### stdio Reconnection

Yoxdur. Server proses ölürsə host yeni proses başladır — bu "reconnect" deyil, "restart"-dır. State itir. Əgər server uzun tool prosesi icra edirdisə, iş itir.

Mitigation: Server idempotent olmalı, long-running iş state-i xarici yerdə (DB, Redis) saxlamalı.

### SSE Reconnection (Legacy)

SSE client `Last-Event-ID` başlığı ilə yenidən qoşulur:

```http
GET /mcp/sse HTTP/1.1
Last-Event-ID: 42
```

Server ID 42-dən sonrakı mesajları yenidən göndərir. Server bu mesajları buffer-ləməlidir — real həyatda çox serverlər bunu zəif edir.

### Streamable HTTP Reconnection

Session-based. Client `Mcp-Session-Id` başlığını hər POST-a daxil edir. Server session state-i (pending responses, subscriptions) Redis kimi paylaşılan store-da saxlayır. Şəbəkə kəsildikdə client yeni POST açır, eyni session ID-ni göndərir, server resume edir.

Session olmadan: hər sorğu müstəqildir, reconnection problemi yoxdur.

### Multiplexing

- **stdio:** 1 host, 1 server, 1 istemci. Bir başqa host eyni serveri istifadə etmək istəsə öz uşaq prosesini yaradır.
- **HTTP (SSE və ya Streamable):** 1 server → N istemciyə xidmət. Auth, rate limiting per-user. Pod scale horizontal. Bu — bulud hostunq üçün yeganə variantdır.

---

## Hansını Nə Vaxt Seçmək

### stdio seçin əgər:

- Server istifadəçinin öz maşınında işləyir
- Developer tool və ya CLI-yə qoşulur (Claude Code, Cursor, local Claude Desktop)
- Filesystem, local git, local Docker kimi şeylərə çıxış lazımdır
- Deployment sadə olsun — `pip install`, `composer install`, istifadəçi konfiqurasiya edir
- Auth lazım deyil (istifadəçi artıq öz maşınının sahibidir)

**Konkret misallar:** `@modelcontextprotocol/server-filesystem`, `mcp-server-git`, şirkət-daxili Laravel tətbiqinə lokal qoşulmaq üçün `php artisan mcp:serve`.

### Streamable HTTP seçin əgər:

- Server uzaqdadır (bulud, korporativ datacenter)
- Çoxlu istifadəçi eyni serverə qoşulur
- Auth/authorization lazımdır (OAuth, multi-tenant)
- Horizontal scale lazımdır
- Mövcud HTTP infrastrukturun istifadə etmək istəyirsən (CDN, WAF, observability)
- Server öz deployment lifecycle-ı ilə yaşayır (kubernetes pod, ECS task)

**Konkret misallar:** Şirkətin daxili Laravel MCP serveri (customer/order/ticket API), SaaS provayderin MCP endpoint-i (məs. Linear, Notion MCP serverləri), multi-tenant AI agent backend-i.

### SSE seçin əgər:

- **Seçməyin.** Deprecated. Yalnız köhnə istemci dəstəkləyirsə, o zaman keçid üçün (həm SSE, həm Streamable HTTP paralel saxla).

### Qərar Matrisi

| Amil | stdio | SSE | Streamable HTTP |
|---|---|---|---|
| Lokal dev tool | Mükəmməl | Həddən artıq | Həddən artıq |
| Multi-user server | İmkansız | İşləyir | Mükəmməl |
| Auth tələb olunur | Yox | OAuth | OAuth |
| Horizontal scale | Yox | Zəif | Güclü |
| Firewall-friendly | N/A | Bəzən yox | Bəli |
| Debug asanlığı | Ağrılı (stderr) | Mürəkkəb | HTTP tool-ları |
| Spec Status (2026) | Stabil | Deprecated | Stabil |
| Claude Desktop | Bəli | Köhnə versiyalar | Bəli (0.10+) |
| Claude Code | Bəli | Bəli | Bəli |
| Cursor | Bəli | Bəli | Bəli |

---

## Laravel ilə Streamable HTTP Server

Bu, şirkətinin üçün real bir pattern-dir — Laravel route-u MCP endpoint-i olaraq aç, OAuth ilə qoru, Streamable HTTP ilə.

### 1. Route

```php
// routes/api.php
use App\Http\Controllers\Mcp\McpHttpController;

Route::middleware(['auth:api', 'mcp.token:mcp:tools:read'])
    ->post('/mcp', [McpHttpController::class, 'handle']);
```

### 2. Controller — Immediate vs Stream

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mcp;

use App\Mcp\McpServer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class McpHttpController
{
    public function __construct(
        private readonly McpServer $server,
    ) {}

    public function handle(Request $request): JsonResponse|StreamedResponse
    {
        $body = $request->json()->all();

        if (! isset($body['jsonrpc']) || $body['jsonrpc'] !== '2.0') {
            return response()->json([
                'jsonrpc' => '2.0',
                'id'      => $body['id'] ?? null,
                'error'   => ['code' => -32600, 'message' => 'Yanlış JSON-RPC versiyası'],
            ], 400);
        }

        $method = $body['method'] ?? '';

        // Uzun icra edilən tool-lar üçün streaming
        if ($this->isStreamingMethod($method, $body['params'] ?? [])) {
            return $this->streamResponse($body, $request);
        }

        // Standart synchronous cavab
        $result = $this->server->handle($body, $request->user());

        // Bildiriş (id yoxdur) — 202 Accepted, bədən yoxdur
        if (! isset($body['id'])) {
            return response()->json(null, 202);
        }

        return response()->json($result);
    }

    private function isStreamingMethod(string $method, array $params): bool
    {
        // Uzun-run tool-lar streaming qaytarır
        if ($method !== 'tools/call') {
            return false;
        }

        $longRunning = ['analyze_database', 'generate_report', 'crawl_site'];
        return in_array($params['name'] ?? '', $longRunning, strict: true);
    }

    private function streamResponse(array $body, Request $request): StreamedResponse
    {
        return response()->stream(function () use ($body, $request) {
            $id = $body['id'] ?? null;
            $user = $request->user();

            // Progress updates göndərmək üçün generator
            foreach ($this->server->handleStreaming($body, $user) as $event) {
                echo "event: message\n";
                echo 'data: ' . json_encode($event, JSON_UNESCAPED_UNICODE) . "\n\n";
                ob_flush();
                flush();

                if (connection_aborted()) {
                    break;
                }
            }
        }, 200, [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache',
            'X-Accel-Buffering' => 'no', // Nginx buffering off
            'Mcp-Session-Id'    => $request->header('Mcp-Session-Id', (string) \Str::uuid()),
        ]);
    }
}
```

### 3. Server Streaming Handler

```php
<?php

declare(strict_types=1);

namespace App\Mcp;

use Generator;
use Illuminate\Foundation\Auth\User;

final class McpServer
{
    /**
     * Streaming execution — yield edir progress event-lər, sonra final nəticə.
     *
     * @return Generator<int, array<string, mixed>>
     */
    public function handleStreaming(array $message, ?User $user): Generator
    {
        $id = $message['id'] ?? null;
        $params = $message['params'] ?? [];
        $toolName = $params['name'] ?? '';

        $progressToken = 'prog-' . bin2hex(random_bytes(8));

        // 25% mərhələ
        yield [
            'jsonrpc' => '2.0',
            'method'  => 'notifications/progress',
            'params'  => [
                'progressToken' => $progressToken,
                'progress'      => 0.25,
                'total'         => 1.0,
                'message'       => 'Data yüklənir...',
            ],
        ];

        // Real iş parçası 1
        $step1 = $this->doStep1($params);

        yield [
            'jsonrpc' => '2.0',
            'method'  => 'notifications/progress',
            'params'  => [
                'progressToken' => $progressToken,
                'progress'      => 0.5,
                'total'         => 1.0,
                'message'       => 'Analiz edilir...',
            ],
        ];

        // Real iş parçası 2
        $step2 = $this->doStep2($step1);

        yield [
            'jsonrpc' => '2.0',
            'method'  => 'notifications/progress',
            'params'  => [
                'progressToken' => $progressToken,
                'progress'      => 0.9,
                'total'         => 1.0,
                'message'       => 'Nəticə hazırlanır...',
            ],
        ];

        // Final cavab
        yield [
            'jsonrpc' => '2.0',
            'id'      => $id,
            'result'  => [
                'content' => [['type' => 'text', 'text' => json_encode($step2)]],
                'isError' => false,
            ],
        ];
    }

    private function doStep1(array $params): array { /* ... */ return []; }
    private function doStep2(array $input): array { /* ... */ return []; }

    public function handle(array $message, ?User $user): ?array
    {
        // Standart synchronous handler — əvvəlki faylda göstərildi
        return ['jsonrpc' => '2.0', 'id' => $message['id'] ?? null, 'result' => []];
    }
}
```

### 4. Nginx Konfiqurasiyası — SSE üçün Kritik

```nginx
server {
    listen 443 ssl http2;
    server_name mcp.company.com;

    location /api/mcp {
        proxy_pass http://laravel-upstream;

        # SSE üçün kritik — buffering-i söndür
        proxy_buffering off;
        proxy_cache off;
        proxy_set_header X-Accel-Buffering "no";

        # Uzun streaming üçün timeout-lar yüksək
        proxy_read_timeout 3600s;
        proxy_send_timeout 3600s;

        # Əsas başlıqlar
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;

        # HTTP/1.1 məcburi SSE üçün
        proxy_http_version 1.1;
        proxy_set_header Connection "";
    }
}
```

### 5. PHP-FPM Konfiqurasiyası

SSE uzun-müddət açıq qalır. PHP-FPM child-ı bu müddətdə bloklanır. Həll:
- `pm.max_children`-i kifayət qədər yüksək et
- Və ya daha yaxşı: **Laravel Octane (Swoole/RoadRunner/FrankenPHP)** istifadə et — coroutine-lərlə çoxlu stream eyni vaxtda, bir prosesdə

```bash
# FrankenPHP ilə Octane
composer require laravel/octane
php artisan octane:install --server=frankenphp
php artisan octane:start --host=0.0.0.0 --port=8000 --workers=4 --max-requests=1000
```

---

## Packet-Level Walkthrough: initialize + tools/call

İki tərəf: `Claude Desktop` (client) və `Laravel MCP Server` (Streamable HTTP). Session yaradılmır, sadə halı izləyirik.

### Paket 1: initialize

**Client → Server:**

```
POST /api/mcp HTTP/1.1
Host: mcp.acme.com
User-Agent: Claude-Desktop/0.11.2
Authorization: Bearer eyJhbGciOiJSUzI1NiI...
Content-Type: application/json
Accept: application/json, text/event-stream
Content-Length: 234

{
  "jsonrpc": "2.0",
  "id": 1,
  "method": "initialize",
  "params": {
    "protocolVersion": "2025-11-01",
    "capabilities": {
      "roots": {"listChanged": true},
      "sampling": {}
    },
    "clientInfo": {
      "name": "Claude Desktop",
      "version": "0.11.2"
    }
  }
}
```

**Server → Client:**

```
HTTP/1.1 200 OK
Content-Type: application/json
Mcp-Session-Id: 7f3a2b1e-9c4d-4a6b-8e1f-2d5c7a9b3e0a
Content-Length: 312

{
  "jsonrpc": "2.0",
  "id": 1,
  "result": {
    "protocolVersion": "2025-11-01",
    "capabilities": {
      "tools": {"listChanged": false},
      "resources": {"subscribe": false, "listChanged": false},
      "prompts": {"listChanged": false}
    },
    "serverInfo": {
      "name": "acme-laravel-mcp",
      "version": "2.1.0"
    }
  }
}
```

Qeyd: server `Mcp-Session-Id` başlığı yaratdı. Client bundan sonrakı POST-larda bunu daxil etməlidir.

### Paket 2: initialized notification

```
POST /api/mcp HTTP/1.1
Host: mcp.acme.com
Authorization: Bearer eyJhbGciOiJSUzI1NiI...
Content-Type: application/json
Accept: application/json, text/event-stream
Mcp-Session-Id: 7f3a2b1e-9c4d-4a6b-8e1f-2d5c7a9b3e0a
Content-Length: 62

{"jsonrpc":"2.0","method":"notifications/initialized"}
```

```
HTTP/1.1 202 Accepted
Content-Length: 0
```

Bildiriş olduğundan server cavab qaytarmır.

### Paket 3: tools/list

```
POST /api/mcp HTTP/1.1
...başlıqlar...
Mcp-Session-Id: 7f3a2b1e-9c4d-4a6b-8e1f-2d5c7a9b3e0a

{"jsonrpc":"2.0","id":2,"method":"tools/list"}
```

```
HTTP/1.1 200 OK
Content-Type: application/json

{
  "jsonrpc": "2.0",
  "id": 2,
  "result": {
    "tools": [
      {
        "name": "search_customer",
        "description": "CRM-də adla, email ilə və ya telefon nömrəsi ilə müştəri axtarır",
        "inputSchema": {
          "type": "object",
          "properties": {
            "query": {"type": "string", "description": "Axtarış sorğusu"},
            "limit": {"type": "integer", "default": 10}
          },
          "required": ["query"]
        }
      }
    ]
  }
}
```

### Paket 4: tools/call — Streaming Response

```
POST /api/mcp HTTP/1.1
...başlıqlar...
Mcp-Session-Id: 7f3a2b1e-9c4d-4a6b-8e1f-2d5c7a9b3e0a
Content-Length: 130

{
  "jsonrpc": "2.0",
  "id": 3,
  "method": "tools/call",
  "params": {
    "name": "analyze_customer_churn",
    "arguments": {"period": "last_quarter"}
  }
}
```

```
HTTP/1.1 200 OK
Content-Type: text/event-stream
Cache-Control: no-cache
X-Accel-Buffering: no
Transfer-Encoding: chunked

event: message
data: {"jsonrpc":"2.0","method":"notifications/progress","params":{"progressToken":"t-abc","progress":0.2,"total":1.0,"message":"Ödəniş tarixçəsi yüklənir..."}}

event: message
data: {"jsonrpc":"2.0","method":"notifications/progress","params":{"progressToken":"t-abc","progress":0.6,"total":1.0,"message":"Model işlədilir..."}}

event: message
data: {"jsonrpc":"2.0","id":3,"result":{"content":[{"type":"text","text":"Son kvartalda 23 müştəri churn riskindədir..."}],"isError":false}}

```

Axırıncı `\n\n` (iki yeni sətir) event-i bitirir. Client `id: 3` ilə cavabı görüb, orijinal sorğu ilə uyğunlaşdırır.

### Paket 5: Session sonu

Client session-ı bağlamaq istəyirsə:

```
DELETE /api/mcp HTTP/1.1
Host: mcp.acme.com
Authorization: Bearer eyJhbGciOiJSUzI1NiI...
Mcp-Session-Id: 7f3a2b1e-9c4d-4a6b-8e1f-2d5c7a9b3e0a
```

```
HTTP/1.1 204 No Content
```

Server session state-ini təmizləyir.

---

## Ümumi Transport Xətaları

### 1. stdio — "Server disconnected after 3 seconds"

Demək olar ki, həmişə stdout çirklənməsi. Yoxla:
- `echo`, `print`, `var_dump`, `dd`, `dump` protokol kodunun heç bir yerində
- `composer dump-autoload` output-u (bəzən autoload diaqnostika yazır)
- PHP warning/notice-ları (`display_errors=0` təyin et, log-ları stderr-ə yönləndir)
- Laravel `Log` default channel-i stderr-ə qur

### 2. Streamable HTTP — SSE axını qırılır

- Nginx `proxy_buffering on` (default) — söndür
- PHP `output_buffering` — söndür
- `flush()` + `ob_flush()` çağırılmır — həmişə çağır
- PHP-FPM timeout qısa — `request_terminate_timeout` artır

### 3. Streamable HTTP — 401 Unauthorized, düzgün token var

- `Authorization: Bearer` yazılışına bax (böyük B)
- Token scope-u çatışmır (401 əvəzinə 403 insufficient_scope ola bilər)
- Token müddəti bitib (saat sinxronizasiyası?)
- CORS preflight OPTIONS-dur, POST deyil — `Authorization` başlığı OPTIONS-da göndərilmir

### 4. stdio — "php: command not found"

`PATH` mühit dəyişəni pass olunmayıb. Konfiqurasiyaya `"PATH": "/usr/local/bin:/usr/bin:/bin"` əlavə et və ya `command` sahəsində `php` əvəzinə mütləq yol: `"/usr/local/bin/php"`.

### 5. SSE — "Response timeout after 30s"

Corporate proxy uzun bağlantıları bağlayır. Həll: Streamable HTTP-yə keç, hər sorğu ayrı POST.

### 6. Session ID unudulur

Client hər POST-da `Mcp-Session-Id` başlığını daxil etməyə bilər. Sənin tərəfdən sadə qərar: session-less rejim gör — hər POST müstəqil, state Redis-də tool-un öz məntiqi ilə.

### 7. Connection reset, heç bir log yoxdur

- Firewall SSE-ni keep-alive düşünərək öldürür — heartbeat event-lər göndər (15 sən-də bir `event: ping`)
- Load balancer idle timeout-u SSE-dən qısa — LB-də artır

---

## Xülasə

- **stdio** lokal, sadə, 1:1, auth-suz. Dev tool üçün mükəmməl.
- **SSE** köhnə, iki endpoint, çətin. İstifadə etmə.
- **Streamable HTTP** bulud üçün standart. Tək endpoint, JSON və ya SSE cavab, OAuth.
- Hər transport üçün PHP-də kritik məsələlər: **stdout təmiz saxlanmalı**, **output buffering söndürülməli**, **stderr-ə log edilməli**.
- Şirkətin üçün MCP server = **Streamable HTTP + OAuth Bearer + Laravel Octane**. Bu 22-ci faylda tam göstərilir.
- Laravel Passport OAuth və `08-mcp-oauth-auth.md`-dəki scope middleware-ı Streamable HTTP-lə birbaşa inteqrasiya olur.

---

## Praktik Tapşırıqlar

### Tapşırıq 1: stdio vs HTTP Latency Müqayisəsi

Eyni MCP server-i stdio və Streamable HTTP kimi implement et. 100 tool call üçün latency ölç. stdio-da process start overhead nə qədərdir? HTTP-də ilk bağlantı vs sonrakı bağlantılar fərqlənirmi?

### Tapşırıq 2: Output Buffering Testi

PHP stdio server-ə `echo "test";` satırı əlavə et (stdout-a birbaşa). Claude Desktop-da bu server-i çağır. Nə baş verir? `ob_end_clean()` + `ob_implicit_flush(true)` əlavə et. Fərqi müşahidə et — bu, stdout çirklənməsinin nə qədər mühim olduğunu sübut edir.

### Tapşırıq 3: Streamable HTTP Session-less Konfiqurasiya

`StreamableHTTPServerTransport({sessionIdGenerator: undefined})` ilə stateless server qur. Eyni `file_id`-i iki ardıcıl sorğuda server-ə göndər. Server session state saxlamır — bu, horizontal scaling-i necə asanlaşdırır?

---

## Əlaqəli Mövzular

- `04-mcp-server-build-node.md` — HTTP transport Node.js implementasiyası
- `05-mcp-server-build-php.md` — PHP stdio transport
- `08-mcp-oauth-auth.md` — Streamable HTTP + OAuth inteqrasiyası
- `07-mcp-clients-compared.md` — Client-lər hansı transport-u dəstəkləyir
