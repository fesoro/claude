# MCP Client-lər Müqayisə — Claude Desktop vs Code vs Cursor vs ChatGPT vs Xüsusi Agent

> Bir MCP server yazdın. İndi hansı istemci ona qoşulacaq? Hər birinin transport dəstəyi, autentifikasiya modeli, primitives dəstəyi (tools/resources/prompts), debugging UX, quraşdırma zəhməti fərqlidir.

---

## Mündəricat

1. [Məqsəd: Hansını Hansı Use-case Üçün?](#goal)
2. [Claude Desktop](#claude-desktop)
3. [Claude Code](#claude-code)
4. [Cursor](#cursor)
5. [ChatGPT / OpenAI](#chatgpt)
6. [Zed, Windsurf, Continue](#other-ides)
7. [Xüsusi Agent (Python/TS/PHP)](#custom)
8. [Capability Matrix](#matrix)
9. [Tövsiyə](#recommendation)

---

## Məqsəd <a name="goal"></a>

Hər istemci AI ilə fərqli iş prosesinə uyğundur:

| İstifadəçi tipi | İş Konteksti | Tövsiyə |
|-----------------|--------------|---------|
| Support manager | Gün boyu chat-lə ticket, customer data | **Claude Desktop** |
| Backend developer | Terminal-də CI, deploy, debug | **Claude Code** |
| Frontend/full-stack developer | IDE-də kod yazarkən | **Cursor** |
| Product / operations | Slack/Web chat | **Xüsusi agent (Laravel)** |
| ML / data engineer | Jupyter, scripts | **Python SDK + custom client** |

---

## Claude Desktop <a name="claude-desktop"></a>

**Rolu**: gündəlik AI assistant istifadəçilər üçün, GUI chat tətbiqi (macOS/Windows).

### Transport Dəstəyi

| Transport | Status | Qeyd |
|-----------|--------|------|
| stdio | ✅ | Lokal tool-lar üçün ən yayılmış |
| streamable HTTP | ✅ | Cloud server-lərə qoşulma (2025-dən) |
| SSE (köhnə) | ⚠️ | Deprecated, yeni server-lərdə istifadə etmə |

### Config Faylı

- **macOS**: `~/Library/Application Support/Claude/claude_desktop_config.json`
- **Windows**: `%APPDATA%\Claude\claude_desktop_config.json`
- **Linux**: `~/.config/Claude/claude_desktop_config.json`

**Nümunə**:

```json
{
  "mcpServers": {
    "filesystem": {
      "command": "npx",
      "args": ["-y", "@modelcontextprotocol/server-filesystem", "/Users/me/Projects"]
    },
    "acme-company": {
      "transport": "streamable-http",
      "url": "https://api.acme.az/mcp",
      "headers": { "Authorization": "Bearer ${ACME_MCP_TOKEN}" }
    }
  }
}
```

### Primitives Dəstəyi

| Primitive | Dəstək | UX |
|-----------|--------|-----|
| **Tools** | Tam | Chat zamanı avtomatik çağırılır; tool call göstərilir, nəticə inline |
| **Resources** | Tam | `@mention` ilə resource-u kontekstə əlavə edirsən (paperclip icon) |
| **Prompts** | Tam | `/` yazanda slash menyuda görünür |

### Güclü Tərəfləri

- **GUI simplicity** — qeyri-texnik istifadəçilər üçün
- **Resource @mention** — fayl/resource seçmə intuitiv
- **Multiple servers** — eyni anda 10+ MCP server
- **Desktop notifications** — uzun sürən tool-lardan bildiriş

### Zəif Tərəfləri

- **Sandboxing məhduddur** — tool-lar tam istifadəçi səlahiyyəti ilə işləyir
- **Headless scenario yoxdur** — avtomatlaşdırma üçün uyğun deyil
- **Log görünüşü zəif** — debugging üçün `mcp.log` file-i əllə açmaq lazım
- **Stdio-lu server-lərdə config file restart tələb edir**

### Debugging

```bash
# macOS
tail -f ~/Library/Logs/Claude/mcp*.log

# Server-specific log
tail -f ~/Library/Logs/Claude/mcp-server-acme-company.log
```

Config səhv olsa Claude Desktop başlamır və ya tool-lar görünmür. Inspector ilə ayrıca test et (bax: `10-mcp-testing-debugging.md`).

---

## Claude Code <a name="claude-code"></a>

**Rolu**: terminal-based AI coding agent — developer-in SSH-da, CI-da, git hook-larında işlətdiyi alət.

### Transport Dəstəyi

| Transport | Status |
|-----------|--------|
| stdio | ✅ |
| streamable HTTP | ✅ |

### Config

```bash
# Project-level (.mcp.json in repo root) — commit etmək olar
claude mcp add filesystem --transport stdio \
  -- npx -y @modelcontextprotocol/server-filesystem /path

# User-level (ən çox istifadə)
claude mcp add acme-company --transport http \
  --url https://api.acme.az/mcp \
  --header "Authorization: Bearer $TOKEN"

# List
claude mcp list

# Remove
claude mcp remove acme-company
```

`.mcp.json` format (project-level):

```json
{
  "mcpServers": {
    "db": {
      "command": "node",
      "args": ["./scripts/mcp-db-server.js"],
      "env": { "DATABASE_URL": "${env:DATABASE_URL}" }
    }
  }
}
```

### Primitives + Hooks + Skills

Claude Code MCP-ə əlavə olaraq öz sistemini qoşur:

| Mexanizm | Mənbə | Təyinat |
|----------|-------|---------|
| MCP tools | Server-dən | LLM avtomatik çağırır |
| MCP resources | Server-dən | `@` mention |
| MCP prompts | Server-dən | `/` slash menu |
| **Skills** | Lokal yaxud global `.claude/skills/` | Multi-turn skills — xüsusi context paketi |
| **Hooks** | `settings.json` | Pre/post tool-use event-lərinə reaksiya |
| **Slash commands** | `.claude/commands/` | Custom slash shortcut-lar |

### Güclü Tərəfləri

- **Skills + hooks** MCP ilə birlikdə çox güclü dəyişənlik
- **Git hook-larında işləyir** — pre-commit AI review
- **CI/CD-də işləyir** — GitHub Actions-da Claude Code istifadə edən workflow-lar
- **Project-level config** — komanda ilə paylaş
- **Background agents** — uzun task-lar üçün

### Zəif Tərəfləri

- **Terminal-focused** — qeyri-texnik istifadəçi üçün çətin
- **No resource preview** — resource-lar text-dir, rich media GUI yoxdur
- **Permission dialog CLI-də** — mouse-click yoxdur, klaviatura ilə təsdiq

### Debugging

```bash
claude --debug  # verbose output
tail -f ~/.claude/logs/latest.log
```

---

## Cursor <a name="cursor"></a>

**Rolu**: IDE (VS Code fork) — kod yazarkən AI inline yardım.

### Transport Dəstəyi

| Transport | Status |
|-----------|--------|
| stdio | ✅ |
| HTTP / SSE | ✅ (sürətlə dəyişir, docs yoxla) |

### Config

Cursor üç səviyyəli config-i dəstəkləyir:

1. **User-level** — `~/.cursor/mcp.json`
2. **Project-level** — `.cursor/mcp.json` (repo root)
3. **Workspace** — workspace settings UI-dan

```json
{
  "mcpServers": {
    "acme-company": {
      "url": "https://api.acme.az/mcp",
      "headers": { "Authorization": "Bearer ${env:ACME_MCP_TOKEN}" }
    },
    "local-db": {
      "command": "node",
      "args": ["./mcp-db.js"]
    }
  }
}
```

### Primitives Dəstəyi

| Primitive | Dəstək |
|-----------|--------|
| Tools | ✅ Tam |
| Resources | ⚠️ Məhdud (2026-04 tarixinə) |
| Prompts | ⚠️ Slash command integration sürətlə təkmilləşir |

### Güclü Tərəfləri

- **IDE context** — LLM açıq file-ları, cursor pozisiyasını görür
- **Inline tool call UI** — tool nəticələri file sidebar-da görünə bilər
- **Apply patch workflow** — LLM kod patch təklif edir, bir kliklə qəbul et
- **Multi-model** — Claude, GPT, Gemini arasında keç

### Zəif Tərəfləri

- **MCP implementation dəyişkəndir** — Cursor update-lərlə MCP behavior-u dəyişə bilər
- **Telemetry concerns** — lokal MCP server-lərin logları Cursor cloud-una göndərilə bilər (enterprise plan bunu deaktiv edir)

---

## ChatGPT / OpenAI <a name="chatgpt"></a>

### 2026-04 Vəziyyəti

OpenAI MCP-ni **Custom Connectors** adı altında dəstəkləyir (2025 sonlarından). ChatGPT Desktop və ChatGPT Enterprise hər ikisi:

| Transport | Status |
|-----------|--------|
| stdio | ⚠️ Enterprise only |
| streamable HTTP | ✅ |
| OAuth 2.1 | ✅ — server OAuth endpoint açırsa, ChatGPT avtomatik token flow edir |

### Config

UI üzərindən: **Settings → Connectors → Add Custom Connector → MCP** → URL daxil et → OAuth login.

### Güclü Tərəfləri

- **GPT-4/5 arxasında** — böyük istifadəçi bazası
- **OAuth UI** — developer tap etmədən login edir
- **Plugins legacy-dən keçiş** — köhnə plugin yazanlar MCP-yə asanlıqla port edir

### Zəif Tərəfləri

- **Stdio dəstəyi zəif** — cloud HTTP server-lər lazımdır
- **Resource-lar üçün UX zəifdir** — ChatGPT-də `@mention` Claude Desktop-dakı kimi hamar deyil
- **Enterprise-only bəzi xüsusiyyətlər**

---

## Zed, Windsurf, Continue <a name="other-ides"></a>

### Zed (Rust-lu editor)

- MCP dəstəyi 2025-dən başlayıb
- stdio + HTTP
- `~/.config/zed/settings.json` → `"experimental.context_servers"`
- Güclü performans, amma MCP UX Cursor səviyyəsində deyil

### Windsurf (Codeium)

- Cursor alternativi, MCP dəstəyi var
- Enterprise fokusu — private cloud deploy

### Continue.dev (VS Code extension)

- Open-source, MCP dəstəkləyir
- Plugin olaraq VS Code/JetBrains-ə daxil olur
- Custom agent yaratmaq üçün ən açıq platform

---

## Xüsusi Agent (Python/TS/PHP) <a name="custom"></a>

Öz məhsulun varsa — Laravel web app, Slack bot, mobile app — LLM-i MCP server-ə qoşub öz agent-ini qur.

### PHP/Laravel-da MCP Client (Saloon + Custom)

```php
<?php
// app/Services/McpClient.php

namespace App\Services;

use Saloon\Http\Connector;
use Saloon\Http\Request;

class McpClient extends Connector
{
    public function __construct(private string $baseUrl, private string $token) {}

    public function resolveBaseUrl(): string { return $this->baseUrl; }

    protected function defaultHeaders(): array
    {
        return [
            'Authorization' => "Bearer {$this->token}",
            'Content-Type' => 'application/json',
            'Accept' => 'application/json, text/event-stream',
        ];
    }

    public function listTools(): array
    {
        return $this->send(new JsonRpcRequest('tools/list'))->json();
    }

    public function callTool(string $name, array $args): array
    {
        return $this->send(new JsonRpcRequest('tools/call', [
            'name' => $name,
            'arguments' => $args,
        ]))->json();
    }
}

class JsonRpcRequest extends Request
{
    protected Method $method = Method::POST;
    protected string $endpoint = '/mcp';

    public function __construct(private string $rpcMethod, private array $params = []) {}

    protected function defaultBody(): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => (string) Str::uuid(),
            'method' => $this->rpcMethod,
            'params' => $this->params,
        ];
    }
}
```

İstifadə:

```php
$mcp = new McpClient('https://api.acme.az/mcp', $token);
$tools = $mcp->listTools();

// Agent loop içində
$result = $mcp->callTool('search_customer', ['query' => 'farid@acme.az']);
```

### TypeScript MCP SDK

```ts
import { Client } from '@modelcontextprotocol/sdk/client/index.js';
import { StreamableHTTPClientTransport } from '@modelcontextprotocol/sdk/client/streamableHttp.js';

const client = new Client({ name: 'my-agent', version: '1.0' });
const transport = new StreamableHTTPClientTransport(new URL('https://api.acme.az/mcp'), {
  requestInit: { headers: { Authorization: `Bearer ${token}` } },
});
await client.connect(transport);

const tools = await client.listTools();
const result = await client.callTool({ name: 'search_customer', arguments: { query: '...' } });
```

### Python

```python
from mcp import Client
from mcp.client.streamable_http import streamablehttp_client

async with streamablehttp_client("https://api.acme.az/mcp",
                                  headers={"Authorization": f"Bearer {token}"}) as (read, write, _):
    async with Client(read, write) as client:
        await client.initialize()
        result = await client.call_tool("search_customer", {"query": "..."})
```

---

## Capability Matrix <a name="matrix"></a>

| Feature | Claude Desktop | Claude Code | Cursor | ChatGPT | Zed | Custom |
|---------|:-:|:-:|:-:|:-:|:-:|:-:|
| stdio transport | ✅ | ✅ | ✅ | ⚠️ | ✅ | ✅ |
| streamable HTTP | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| OAuth 2.1 flow | ⚠️ | ⚠️ | ⚠️ | ✅ | ❌ | ✅ (əllə) |
| Tools | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Resources (read) | ✅ | ✅ | ⚠️ | ⚠️ | ⚠️ | ✅ |
| Resource subscribe | ✅ | ✅ | ❌ | ❌ | ❌ | ✅ |
| Prompts (slash) | ✅ | ✅ | ⚠️ | ❌ | ❌ | ✅ |
| Multi-server | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| GUI config | ✅ | ❌ | ✅ | ✅ | ⚠️ | N/A |
| CLI config | ❌ | ✅ | ✅ | ❌ | ✅ | ✅ |
| Per-project config | ⚠️ | ✅ | ✅ | ❌ | ✅ | ✅ |
| Debugging logs | ⚠️ | ✅ | ⚠️ | ❌ | ⚠️ | ✅ |
| Headless / CI | ❌ | ✅ | ❌ | ❌ | ❌ | ✅ |
| Agent mode | ❌ | ✅ | ⚠️ | ⚠️ | ❌ | ✅ |

**Legend**: ✅ = tam dəstək, ⚠️ = qismən/dəyişkən, ❌ = yoxdur

---

## Tövsiyə <a name="recommendation"></a>

### Şirkətin üçün deploy strategiyası

```
┌─────────────────────────────────────────────────────────────┐
│  Şirkətin MCP Server-i (Laravel, streamable HTTP, OAuth)    │
└─────┬───────────────────────────────────────────────────────┘
      │
      ├──► Support Manageri  → Claude Desktop (GUI, gündəlik chat)
      ├──► Backend Dev       → Claude Code (terminal, CI integration)
      ├──► Full-stack Dev    → Cursor (IDE kodlama)
      ├──► Product/Ops       → Xüsusi Slack bot (öz Laravel agent)
      └──► Enterprise users  → ChatGPT (OAuth, corporate SSO)
```

### Tövsiyələr

1. **İçəri tool-lar üçün** (şirkətin öz komandası) → **streamable HTTP + OAuth 2.1**
   - Bir server, bir endpoint — hər istemci ona qoşulur
   - Token/OAuth flow istifadəçi kimliyini həll edir
   - stdio istifadə etmə (hər istifadəçi öz maşınında process işlədə bilmir)

2. **Developer maşınında lokal tool-lar üçün** → **stdio**
   - Fayl sistemi, local git, docker-compose idarəetməsi
   - Fast iteration, sandbox maşında
   - Config file-də `command` ilə

3. **Public distribusiya üçün** (open-source tool) → **npm package + stdio template**
   - Hər istifadəçi `npx @acme/mcp-tool` ilə işə salsın
   - OAuth + cloud tələb etmə — entry barrier aşağı

4. **Qeyri-texnik istifadəçi üçün** → **Claude Desktop**
   - Installation minimal
   - @mention + slash menu intuitiv

5. **Developer workflow üçün** → **Claude Code + Cursor paralel**
   - Code-da terminal işləri, CI, hooks
   - Cursor-da inline kod yazmaq

6. **Test/dev zamanı** → **MCP Inspector** (bax `10-mcp-testing-debugging.md`)
   - Hər istemci özünü fərqli aparır; Inspector protokol səviyyəsində yoxlayır

### Anti-patterns

- ❌ **Hər istifadəçiyə stdio server göndərmək** — update, versiya idarəetməsi pain
- ❌ **ChatGPT-yə stdio server qoşmağa çalışmaq** — HTTP ilə get
- ❌ **Prompt-ları tool kimi etmək** — `/customer_summary` prompt olsun, tool yox
- ❌ **Resource subscribe-a güvənmək hər istemci** üçün — Claude Desktop dəstəkləyir, Cursor hələ yox

---

## Xülasə

Hər istemci fərqli rol üçündür. Server-in isə **bir dəfə** qurulur və hamı ona qoşulur. Transport seçimi (stdio vs streamable HTTP) əksər hallarda deployment modelini (lokal vs cloud) müəyyən edir. OAuth dəstəyi server-də varsa, ChatGPT/enterprise users avtomatik onboard olur.

**Senior PHP dev üçün praktik yol**:
1. Laravel MCP server-i streamable HTTP + Sanctum token ilə qur (bax `11-mcp-for-company-laravel.md`)
2. Özün `Claude Code` ilə sına (terminal)
3. Komanda üçün `Claude Desktop` config paylaş
4. Enterprise müştərilər üçün OAuth əlavə et (bax `08-mcp-oauth-auth.md`)
5. Xüsusi daxili agent (Slack bot) lazım olanda — PHP-də MCP client yaz
