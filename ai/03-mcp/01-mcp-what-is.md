# Model Context Protocol (MCP) — Tam Dərinlikli Araşdırma (Junior)

## MCP-nin Həll Etdiyi Problem

MCP-dən əvvəl xarici sistemlərə qoşulması tələb olunan hər AI tətbiqi öz inteqrasiya qatını sıfırdan qururdu. Kodlama köməkçisi inşa edən komanda GitHub inteqrasiyasını sıfırdan həyata keçirirdi. Müştəri xidməti botu inşa edən başqa komanda fərqli CRM inteqrasiyasını sıfırdan həyata keçirirdi. AI IDE plagini bazarında hər vendor — Cursor, Windsurf, GitHub Copilot, JetBrains AI — öz fayl sistemi toollarını, öz terminal toollarını, öz veb axtarış toollarını quruyordu. Nəticədə böyük miqdarda dublikat və parçalanma yarandı:

- 50 AI toolu hər biri öz "fayl oxu" imkanını qururdu
- İnteqrasiyalar arasında heç bir qarşılıqlı işləmə yoxdu
- Təhlükəsizlik qeyri-ardıcıl idarə olunurdu (və ya ümumiyyətlə idarə olunmurdu)
- Tool imkanı sürüşməsi — eyni "verilənlər bazası sorğusu" toolu hər məhsulda fərqli davranırdı

**MCP universal adapter qatıdır** — hər AI tətbiqinin eyni protokol istifadə edərək hər hansı məlumat mənbəyinə və ya toola qoşulmasına imkan verən açıq standart. Verilənlər bazanız üçün bir dəfə MCP server qurun, hər MCP uyumlu AI istemcisi (Claude Desktop, Claude Code, IDE-lər, xüsusi agentlər) ondan istifadə edə bilər.

Analoji: MCP, AI toolları üçün HTTP-nin veb serverlər üçün nə olduğunu edir. HTTP-dən əvvəl hər istemci-server protokolu xüsusi idi. HTTP-dən sonra hər brauzer hər veb serverlə danışa bildi.

---

## Arxitektura

MCP üç rol müəyyən edir:

### Host
AI modelini ehtiva edən və ya idarə edən tətbiq. Nümunələr: Claude Desktop, Claude Code CLI, VS Code with Copilot, xüsusi Laravel tətbiqi. Host aşağıdakılara cavabdehdir:
- MCP istemcilərinin həyat dövrünü idarə etmək
- Hansı MCP serverlərinə qoşulacağına qərar vermək
- Tool nəticələrini LLM-ə təqdim etmək
- Təhlükəsizlik siyasətlərini tətbiq etmək

### İstemci
Bir MCP serveri ilə **1:1 bağlantı** saxlayan host daxilindəki komponent. Host eyni anda fərqli serverlərə qoşulmuş çoxlu istemciləri paralel işlədə bilər. İstemci:
- MCP JSON-RPC protokolunu danışır
- Bağlantı nəqlini idarə edir
- Hostdan gələn sorğuları düzgün serverə yönləndirir

### Server
MCP protokolu üzərindən **imkanları** (toollar, resurslar, promptlar) ifşa edən proqram. Server adətən kiçik, fokuslanmış proqramdır. Nümunələr: fayl sistemi serveri, verilənlər bazası serveri, Slack serveri, xüsusi biznes məntiqi serveri. Server:
- Başlatma zamanı imkanlarını elan edir
- Tool çağırışlarını, resurs oxumalarını və prompt sorğularını idarə edir
- Bildirişlər və tərəqqi yeniləmələri göndərə bilər

```
┌─────────────────────────────────────────────────────────┐
│                         HOST                            │
│  (Claude Desktop / Claude Code / Laravel tətbiqiniz)    │
│                                                         │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐             │
│  │ MCP      │  │ MCP      │  │ MCP      │             │
│  │ İstemci 1│  │ İstemci 2│  │ İstemci 3│             │
│  └────┬─────┘  └────┬─────┘  └────┬─────┘             │
└───────┼─────────────┼─────────────┼────────────────────┘
        │             │             │
   stdio/HTTP    stdio/HTTP    stdio/HTTP
        │             │             │
   ┌────▼─────┐  ┌────▼─────┐  ┌────▼──────┐
   │ MCP      │  │ MCP      │  │ MCP       │
   │ Server   │  │ Server   │  │ Server    │
   │ (fs)     │  │ (git)    │  │ (DB-niz)  │
   └──────────┘  └──────────┘  └───────────┘
```

---

## Nəql Qatları

MCP iki nəql dəstəkləyir:

### stdio (Standart Giriş/Çıxış)
Host serveri **uşaq proses** kimi işə salır və stdin/stdout vasitəsilə ünsiyyət qurur. JSON-RPC mesajları yeni sətir ilə ayrılır.

- Ən sadə həyata keçirilmə və deployment
- Claude Desktop və Claude Code-da standart olaraq istifadə olunur
- Server yerli işləyir — şəbəkə, auth lazım deyil
- Proses həyat dövrü hostla bağlıdır

### HTTP + SSE (Server-Sent Events)
Server HTTP xidməti kimi işləyir. İstemci sorğular üçün adi HTTP POST vasitəsilə, server tərəfindən başladılan mesajlar üçün SSE endpointi vasitəsilə qoşulur.

- Uzaq serverləre imkan verir
- Bir serveri çoxlu hostların paylaşmasına imkan verir
- Server fərqli maşında olmalı olduqda tələb olunur
- Autentifikasiya üçün OAuth 2.0 əlavə edilə bilər (17-ci fayla bax)

2025-03-26 spesifikasiya yeniləməsi **Streamable HTTP** təqdim etdi — həm sorğuları, həm streamləri idarə edən tək endpoint, HTTP nəqlini sadələşdirir.

---

## İmkan Tipləri

MCP serverləri dörd tip imkan ifşa edə bilər:

### 1. Toollar
AI-ın hərəkət etmək və ya data əldə etmək üçün çağıra biləcəyi funksiyalar. Toollar Claude-un imkanlarını genişləndirmənin əsas mexanizmidir.

```json
{
  "name": "query_database",
  "description": "İstehsal verilənlər bazasında yalnız oxuma SQL sorğusu icra et",
  "inputSchema": {
    "type": "object",
    "properties": {
      "sql": { "type": "string", "description": "İcra ediləcək SQL SELECT sorğusu" },
      "limit": { "type": "integer", "description": "Maks qaytarılacaq sətir", "default": 100 }
    },
    "required": ["sql"]
  }
}
```

Claude bir tool çağıranda MCP istemcisinə `tools/call` göndərir, istemci onu serverə ötürür. Server hərəkəti icra edir və nəticə qaytarır. Claude nəticəni cavabına daxil edir.

### 2. Resurslar
Oxuna bilən URI-ünvanlı data. Resurslar serverin data modelini təmsil edir — siyahıya salına bilən və gətirilə bilən şeylər.

```json
{
  "uri": "db://users/42",
  "name": "İstifadəçi #42",
  "mimeType": "application/json",
  "description": "Profil və üstünlüklər olan istifadəçi qeydi"
}
```

Resurslar `resources/read` ilə oxunur. Toollardan fərqli olaraq, onlar konseptual olaraq hərəkətlər (fellər) deyil, data (ismlər)dir. Fayl resursdur. Verilənlər bazası sətri resursdur. Sorğu işlətmək tooldu.

### 3. Promptlar
İstifadəçilərin və hostların ada görə çağıra biləcəyi əvvəlcədən müəyyən edilmiş prompt şablonları. Promptlar arqument qəbul edə bilər və strukturlaşdırılmış mesaj ardıcıllığı qaytara bilər.

```json
{
  "name": "code_review",
  "description": "Kodu xətalara, təhlükəsizlik problemlərinə və stila görə nəzərdən keçir",
  "arguments": [
    { "name": "language", "description": "Proqramlaşdırma dili", "required": true },
    { "name": "code", "description": "Nəzərdən keçiriləcək kod", "required": true }
  ]
}
```

Promptlar serverlərə sahə spesifik düşüncə nümunələrini kodlaşdırmağa imkan verir. Verilənlər bazası serveri optimal sorğu analizi üçün söhbəti strukturlaşdıran `explain_query` prompta sahib ola bilər.

### 4. Sampling
MCP serverlərinə host LLM-dən mətn generasiyasını tələb etməyə imkan verir — əslində server Claude-dan alt tapşırığı tamamlamasını xahiş edir. Bu, serverlərin öz API açarları olmadan agentik davranışları (düşüncə zəncirləri, rekursiv tool istifadəsi) həyata keçirməsinə imkan verir.

Sampling daha az tətbiq edilir, lakin çox agentli arxitekturalar üçün güclüdür.

---

## MCP Protokolu: JSON-RPC 2.0

MCP mesaj formatı olaraq JSON-RPC 2.0 istifadə edir. Hər mesaj ya **sorğu** (cavab gözlənilir), **bildiriş** (göndər-unut), ya da **cavab**dır.

### Sorğu
```json
{
  "jsonrpc": "2.0",
  "id": "req-1",
  "method": "tools/call",
  "params": {
    "name": "query_database",
    "arguments": { "sql": "SELECT count(*) FROM users" }
  }
}
```

### Cavab
```json
{
  "jsonrpc": "2.0",
  "id": "req-1",
  "result": {
    "content": [
      { "type": "text", "text": "[{\"count(*)\": 4231}]" }
    ],
    "isError": false
  }
}
```

### Xəta Cavabı
```json
{
  "jsonrpc": "2.0",
  "id": "req-1",
  "error": {
    "code": -32603,
    "message": "Daxili xəta",
    "data": { "details": "Verilənlər bazası bağlantısı rədd edildi" }
  }
}
```

Standart JSON-RPC xəta kodları:

| Kod | Məna |
|---|---|
| -32700 | Parse xətası |
| -32600 | Yanlış sorğu |
| -32601 | Metod tapılmadı |
| -32602 | Yanlış parametrlər |
| -32603 | Daxili xəta |

---

## Tam Bağlantı Ardıcıllığı (ASCII Ardıcıllıq Diaqramı)

```
Host/İstemci                   MCP Server
     |                              |
     |--- initialize sorğusu ------>|
     |    {protocolVersion,         |
     |     capabilities,            |
     |     clientInfo}              |
     |                              |
     |<-- initialize cavabı --------|
     |    {protocolVersion,         |
     |     capabilities: {          |
     |       tools: {},             |
     |       resources: {},         |
     |       prompts: {}            |
     |     },                       |
     |     serverInfo}              |
     |                              |
     |--- initialized (bildiriş) -->|
     |    (cavab gözlənilmir)       |
     |                              |
     |--- tools/list sorğusu ------>|
     |                              |
     |<-- tools/list cavabı --------|
     |    {tools: [...]}            |
     |                              |
     |--- resources/list sorğusu -->|
     |                              |
     |<-- resources/list cavabı ----|
     |    {resources: [...]}        |
     |                              |
     |   [... vaxt keçir ...]       |
     |                              |
     |--- tools/call sorğusu ------>|
     |    {name, arguments}         |
     |                              |
     |<-- tools/call cavabı --------|
     |    {content, isError}        |
     |                              |
     |--- resources/read sorğusu -->|
     |    {uri}                     |
     |                              |
     |<-- resources/read cavabı ----|
     |    {contents: [...]}         |
     |                              |
     |   [bağlantı bitir]           |
```

---

## MCP vs Birbaşa Tool İstifadəsi

Claude toollara iki yanaşmanı dəstəkləyir. Hansını nə vaxt istifadə edəcəyini bilmək arxitektlər üçün kritikdir.

### Birbaşa Tool İstifadəsi (Anthropic API `tools` parametri)

```json
{
  "tools": [
    {
      "name": "query_db",
      "description": "...",
      "input_schema": { ... }
    }
  ]
}
```

Tətbiqiniz toolları birbaşa API çağırışında müəyyən edir. Claude tool çağırdıqda, `tool_use` məzmun bloku alırsınız, funksiyanı kodunuzda icra edirsiniz və `tool_result` qaytarırsınız. Hər şey tətbiqiniz daxilindədir.

**Ən uyğun:**
- Tətbiqinizin məntiqi ilə sıx bağlı toollar
- Sadə, yaxşı müəyyən edilmiş tool dəstləri
- Claude API-ni artıq birbaşa idarə edən tətbiqlər
- Tool icrasının tam nəzarəti lazım olduqda

### MCP Toolları

Xarici MCP serverinde müəyyən edilmiş toollar. Host (Claude Desktop, Claude Code, tətbiqiniz) onları MCP protokolu vasitəsilə kəşf edir və çağırır.

**Ən uyğun:**
- Çoxlu AI tətbiqləri paylaşmalı olan toollar
- Öz deployment həyat dövrünə ehtiyacı olan toollar (verilənlər bazası serverləri, fayl serverləri)
- Təşkilatınız üçün yenidən istifadə edilə bilən tool kitabxanaları qurmaq
- Mövcud MCP serverlərini istifadə etmək istədikdə (fayl sistemi, GitHub və s.)
- Toolların mürəkkəb vəziyyəti varsa və ya davamlı bağlantı tələb edirsə

### Qərar Matrisi

| Amil | Birbaşa Toollar | MCP Toolları |
|---|---|---|
| Həyata keçirilmə sadəliyi | Sadədir (bir sistem) | Daha mürəkkəb (ayrı server) |
| Yenidən istifadə | Aşağı (tətbiqə xüsusi) | Yüksək (hər MCP istemcisi) |
| Deployment müstəqilliyi | Xeyr | Bəli |
| Mövcud ekosistem | Sıfırdan qurun | 100+ mövcud server |
| Gecikmə | Aşağı (proses daxilindədir) | Yüksək (IPC/şəbəkə) |
| Təhlükəsizlik sərhədi | Yoxdur | Proses izolyasiyası |
| Auth/icazələr | Tətbiq səviyyəsindədir | Server səviyyəsindədir |

---

## MCP Ekosistemi

Anthropic spesifikasiyanı 2024-cü ilin noyabrında yayımladıqdan bəri açıq mənbəli MCP server ekosistemi sürətlə böyüdü.

### Rəsmi Anthropic Serverləri
- **filesystem** — fayl oxuma/yazma, qovluq siyahısı, fayl axtarışı
- **git** — repozitoriya əməliyyatları, diff, blame, log
- **github** — issues, pull requests, kod axtarışı
- **postgres** — yalnız oxuma verilənlər bazası sorğuları
- **sqlite** — SQLite əməliyyatları
- **brave-search** — veb axtarışı
- **puppeteer** — brauzer avtomatlaşdırması

### Cəmiyyət Serverləri
- **everything** — bütün imkan tipləri ilə demo server
- **aws-kb-retrieval** — AWS Bedrock Knowledge Bases
- **docker** — konteyner idarəetməsi
- **kubernetes** — klaster əməliyyatları
- **jira** — layihə idarəetməsi
- **notion** — iş sahəsi girişi
- **slack** — mesajlaşma

### MCP Serverləri Tapmaq
- Rəsmi siyahı: https://github.com/modelcontextprotocol/servers
- Cəmiyyət: https://mcp.so (cəmiyyət reyestri)
- npm: `@modelcontextprotocol` axtarın

---

## Təhlükəsizlik Modeli

MCP aydın təhlükəsizlik sərhədi tətbiq edir. Server ayrı prosesdir — kompromis edilmiş server hostun yaddaşına və ya digər serverlərə birbaşa daxil ola bilməz. Lakin bəzi vacib məsələlər var:

### Serverlərin Nəyə Çıxış Əldə Edə Biləcəyi
Yalnız açıqca verdiyin şeylərə. Fayl sistemi serveri yalnız konfiqurasiya etdiyin qovluqlara toxunur. Verilənlər bazası serveri yalnız təqdim etdiyin bağlantı məlumatlarından istifadə edir.

### Prompt Injection Riski
Resursdən qaytarılan zərərli sənəd Claude-u manipulyasiya etməyə çalışan təlimatlar ehtiva edə bilər. Azaldma tədbirləri:
- Məzmunu resurs datasına qaytarmazdan əvvəl doğrulayın və sanitasiya edin
- Məzmunu etibarsız kimi işarələmək üçün `annotations` sahəsini istifadə edin
- `roots`-u tətbiq edin — serverin nəyə daxil olacağına dair server elan edilmiş sərhədlər

### İstifadəçi Razılığı
MCP spesifikasiyası hostların serverlərə qoşulmadan əvvəl və həssas data göndərməzdən əvvəl istifadəçi razılığı almasını tələb edir. Claude Desktop yeni server bağlantı tələb etdikdə icazə dialoqu göstərir.

### Uzaq Serverler üçün OAuth
MCP serverləri uzaqda (HTTP nəqli) olduqda, 2025-03-26 spesifikasiyası autentifikasiya üçün OAuth 2.0 axını müəyyən edir. Tam həyata keçirilmə üçün `08-mcp-oauth-auth.md` faylına baxın.

---

## Protokol Versiyalaşdırması

MCP tarix əsaslı versiyalaşdırma istifadə edir: `2024-11-05` (ilkin), `2025-03-26` (2025-ci ilin aprel ayı etibarilə cari).

`initialize` zamanı istemci dəstəklənən protokol versiyasını göndərir. Server istifadə edəcəyi versiya ilə cavab verir. Uyğunsuzluq varsa bağlantı rədd edilə bilər və ya server aşağı versiyaya enə bilər.

```json
{
  "method": "initialize",
  "params": {
    "protocolVersion": "2025-03-26",
    "capabilities": { ... },
    "clientInfo": { "name": "my-client", "version": "1.0.0" }
  }
}
```

---

## Praktik Tapşırıqlar

### Tapşırıq 1: Birinci MCP Serverini Quraşdırmaq

**Məqsəd:** Mövcud MCP serverini Claude Desktop-a qoşub real tool çağırışı görmək.

```bash
# 1. MCP filesystem serverini quraşdırın
npm install -g @modelcontextprotocol/server-filesystem

# 2. Claude Desktop konfiqurasiyasını açın
# macOS: ~/Library/Application Support/Claude/claude_desktop_config.json
# Windows: %APPDATA%\Claude\claude_desktop_config.json

# 3. Serveri əlavə edin:
```

```json
{
  "mcpServers": {
    "filesystem": {
      "command": "npx",
      "args": ["-y", "@modelcontextprotocol/server-filesystem", "/home/username/projects"]
    }
  }
}
```

4. Claude Desktop-u yenidən başladın
5. Claude-a: "Layihə qovluğumda hansı fayllar var?" soruşun
6. Claude `list_directory` toolunu çağırır — JSON-RPC mesajını izləyin

**Nəticə:** İlk real tool çağırışını müşahidə etdiniz.

### Tapşırıq 2: MCP vs Birbaşa Tool Müqayisəsi

**Aşağıdakı iki implementasiyanı müqayisə edin:**

```php
// Birbaşa tool (API-də)
$response = $anthropic->messages->create([
    'tools' => [
        ['name' => 'get_weather', 'description' => '...', 'input_schema' => [...]]
    ],
    // ...
]);

// MCP tool (xarici server)
// weather-mcp-server ayrıca proqram kimi işləyir
// Claude Desktop onu kəşf edir, tool çağırışları protokol üzərindən gedir
```

**Suallar:**
- Hansı ssenari üçün birbaşa tool daha uyğundur?
- Hansı ssenari üçün MCP daha uyğundur?
- Hər birinin latensiyası necə fərqlənir?

### Tapşırıq 3: JSON-RPC Mesajlarını Debug Etmək

MCP Inspector ilə tool çağırışını real vaxtda izləyin:

```bash
# MCP Inspector quraşdırmaq
npm install -g @modelcontextprotocol/inspector

# Serveri Inspector ilə başladın
mcp-inspector npx @modelcontextprotocol/server-filesystem /tmp

# localhost:5173-ü açın
# "Tools" → "list_directory" → çağırın
# JSON-RPC sorğu/cavabını görün
```

**İzlənəcəklər:**
- `initialize` handshake
- `tools/list` cavabı
- `tools/call` sorğusu və cavabı
- Xəta halında `error` formatı

---

## Əlaqəli Mövzular

- [02-mcp-resources-tools-prompts.md](02-mcp-resources-tools-prompts.md) — MCP primitivləri dərindən
- [03-mcp-transports-deep.md](03-mcp-transports-deep.md) — stdio vs HTTP nəqli
- [04-mcp-server-build-node.md](04-mcp-server-build-node.md) — Öz MCP serverini qurmaq (Node.js)
- [05-mcp-server-build-php.md](05-mcp-server-build-php.md) — PHP-də MCP server
- [09-mcp-security-patterns.md](09-mcp-security-patterns.md) — Təhlükəsizlik modeli
```
