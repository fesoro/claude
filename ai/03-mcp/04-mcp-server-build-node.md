# Node.js / TypeScript ilə MCP Server Qurmaq (Middle)

## Ön Tələblər

Bu bələdçi rəsmi `@modelcontextprotocol/sdk` istifadə edərək tam, deploy edilə bilən MCP server qurur. Server aşağıdakı toolları ifşa edir:
1. PostgreSQL verilənlər bazasını sorğulamaq (yalnız oxuma)
2. Sandboxed qovluqdan faylları oxumaq
3. Xarici REST API-ni çağırmaq

**Stack:** Node.js 20+, TypeScript 5, `@modelcontextprotocol/sdk`, `zod`, `pg`, `node-fetch`

---

## Layihə Qurulması

```bash
mkdir my-mcp-server && cd my-mcp-server
npm init -y
npm install @modelcontextprotocol/sdk zod pg node-fetch
npm install -D typescript @types/node @types/pg tsx
```

`tsconfig.json`:
```json
{
  "compilerOptions": {
    "target": "ES2022",
    "module": "Node16",
    "moduleResolution": "Node16",
    "outDir": "./dist",
    "rootDir": "./src",
    "strict": true,
    "esModuleInterop": true,
    "skipLibCheck": true
  },
  "include": ["src/**/*"]
}
```

`package.json` skriptləri:
```json
{
  "scripts": {
    "build": "tsc",
    "start": "node dist/index.js",
    "dev": "tsx src/index.ts",
    "inspect": "npx @modelcontextprotocol/inspector tsx src/index.ts"
  },
  "type": "module"
}
```

---

## Əsas Server Faylı

```typescript
// src/index.ts
import { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import { registerDatabaseTools } from "./tools/database.js";
import { registerFileTools } from "./tools/files.js";
import { registerApiTools } from "./tools/external-api.js";
import { registerResources } from "./resources/index.js";

const server = new McpServer({
  name: "my-production-server",
  version: "1.0.0",
});

// Bütün imkanları qeydiyyatdan keçir
registerDatabaseTools(server);
registerFileTools(server);
registerApiTools(server);
registerResources(server);

// stdio nəqli ilə başlat (Claude Desktop / Claude Code üçün)
const transport = new StdioServerTransport();

await server.connect(transport);

// Düzgün dayanma
process.on("SIGINT", async () => {
  await server.close();
  process.exit(0);
});
```

---

## Tool 1: Verilənlər Bazası Sorğusu

```typescript
// src/tools/database.ts
import { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { z } from "zod";
import pg from "pg";

const { Pool } = pg;

// Bağlantı hovuzu — bir dəfə yaradılır, tool çağırışları arasında paylaşılır
const pool = new Pool({
  connectionString: process.env.DATABASE_URL,
  max: 5,
  idleTimeoutMillis: 30_000,
  connectionTimeoutMillis: 5_000,
});

// İcazə verilmiş cədvəllər (allowlist ixtiyari cədvəl girişinin qarşısını alır)
const ALLOWED_TABLES = new Set([
  "users",
  "products",
  "orders",
  "categories",
  "inventory",
]);

// Sadə SQL injection qarşısının alınması — yalnız SELECT ifadələrinə icazə ver
function validateReadOnlyQuery(sql: string): void {
  const normalized = sql.trim().toUpperCase();

  if (!normalized.startsWith("SELECT")) {
    throw new Error("Yalnız SELECT sorğularına icazə verilir");
  }

  const forbidden = ["INSERT", "UPDATE", "DELETE", "DROP", "TRUNCATE", "ALTER", "CREATE", "EXEC", "--", ";"];
  for (const keyword of forbidden) {
    if (normalized.includes(keyword)) {
      throw new Error(`Qadağan olunmuş SQL açar söz: ${keyword}`);
    }
  }
}

export function registerDatabaseTools(server: McpServer): void {
  // Tool: mövcud cədvəlləri siyahıla
  server.tool(
    "list_tables",
    "Sütun sxemləri ilə birlikdə bütün sorğulanabilir verilənlər bazası cədvəllərini siyahıla",
    {},
    async () => {
      const client = await pool.connect();
      try {
        const result = await client.query(`
          SELECT
            t.table_name,
            array_agg(
              c.column_name || ' ' || c.data_type
              ORDER BY c.ordinal_position
            ) AS columns
          FROM information_schema.tables t
          JOIN information_schema.columns c
            ON c.table_name = t.table_name
            AND c.table_schema = t.table_schema
          WHERE t.table_schema = 'public'
            AND t.table_name = ANY($1)
          GROUP BY t.table_name
          ORDER BY t.table_name
        `, [Array.from(ALLOWED_TABLES)]);

        const tables = result.rows.map((row) => ({
          table: row.table_name,
          columns: row.columns,
        }));

        return {
          content: [
            {
              type: "text" as const,
              text: JSON.stringify(tables, null, 2),
            },
          ],
        };
      } finally {
        client.release();
      }
    }
  );

  // Tool: SELECT sorğusu icra et
  server.tool(
    "query_database",
    "Verilənlər bazasına qarşı yalnız oxuma SELECT sorğusu icra et. Nəticələri JSON kimi qaytarır.",
    {
      sql: z.string().min(1).describe("İcra ediləcək SELECT SQL sorğusu"),
      limit: z
        .number()
        .int()
        .min(1)
        .max(1000)
        .default(100)
        .describe("Qaytarılacaq maks sətir sayı (standart: 100, maks: 1000)"),
    },
    async ({ sql, limit }) => {
      try {
        validateReadOnlyQuery(sql);
      } catch (err) {
        return {
          content: [{ type: "text" as const, text: `Sorğu rədd edildi: ${(err as Error).message}` }],
          isError: true,
        };
      }

      // LIMIT yoxdursa əlavə et
      const limitedSql = sql.trimEnd().replace(/;?\s*$/, "") + ` LIMIT ${limit}`;

      const client = await pool.connect();
      try {
        const start  = Date.now();
        const result = await client.query(limitedSql);
        const ms     = Date.now() - start;

        return {
          content: [
            {
              type: "text" as const,
              text: JSON.stringify({
                rows:      result.rows,
                rowCount:  result.rowCount,
                executionMs: ms,
                fields:    result.fields.map((f) => ({ name: f.name, type: f.dataTypeID })),
              }, null, 2),
            },
          ],
        };
      } catch (err) {
        return {
          content: [
            {
              type: "text" as const,
              text: `Sorğu xətası: ${(err as Error).message}`,
            },
          ],
          isError: true,
        };
      } finally {
        client.release();
      }
    }
  );

  // Tool: cədvəl üçün sətir sayını al
  server.tool(
    "count_rows",
    "Cədvəl üçün təxmini sətir sayını al",
    {
      table: z
        .string()
        .refine((t) => ALLOWED_TABLES.has(t), {
          message: "Cədvəl icazə verilmiş siyahıda deyil",
        })
        .describe("Cədvəl adı"),
    },
    async ({ table }) => {
      const client = await pool.connect();
      try {
        // Sürətli təxmini say üçün pg_stat istifadə et
        const result = await client.query(
          "SELECT reltuples::bigint AS approximate_count FROM pg_class WHERE relname = $1",
          [table]
        );

        return {
          content: [
            {
              type: "text" as const,
              text: `'${table}' cədvəlinin təxminən ${result.rows[0]?.approximate_count ?? 0} sətri var.`,
            },
          ],
        };
      } finally {
        client.release();
      }
    }
  );
}
```

---

## Tool 2: Fayl Sistemi Girişi

```typescript
// src/tools/files.ts
import { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { z } from "zod";
import { readFile, readdir, stat } from "fs/promises";
import { resolve, relative, join } from "path";

// Sandbox: bütün fayl girişi bu qovluqla məhdudlaşdırılır
const SANDBOX_DIR = resolve(process.env.FILES_SANDBOX ?? "/tmp/mcp-sandbox");

/**
 * Yolu həll edir və sandbox daxilindəyini yoxlayır.
 * Qovluq keçid hücumlarının (../../etc/passwd) qarşısını alır.
 */
function safePath(inputPath: string): string {
  const resolved = resolve(join(SANDBOX_DIR, inputPath));

  if (!resolved.startsWith(SANDBOX_DIR + "/") && resolved !== SANDBOX_DIR) {
    throw new Error(
      `Qovluq keçidi aşkar edildi: '${inputPath}' sandbox xaricini göstərir`
    );
  }

  return resolved;
}

export function registerFileTools(server: McpServer): void {
  server.tool(
    "list_files",
    "Sandbox-da faylları və qovluqları siyahıla. Nisbi yol və ya kök üçün '.' verin.",
    {
      path: z.string().default(".").describe("Sandbox daxilindəki nisbi yol"),
      recursive: z.boolean().default(false).describe("Rekursiv siyahıla"),
    },
    async ({ path, recursive }) => {
      let resolved: string;
      try {
        resolved = safePath(path);
      } catch (err) {
        return {
          content: [{ type: "text" as const, text: (err as Error).message }],
          isError: true,
        };
      }

      async function listDir(dir: string, depth = 0): Promise<string[]> {
        const entries = await readdir(dir, { withFileTypes: true });
        const lines: string[] = [];

        for (const entry of entries) {
          const indent  = "  ".repeat(depth);
          const prefix  = entry.isDirectory() ? "📁 " : "📄 ";
          const relPath = relative(SANDBOX_DIR, join(dir, entry.name));
          lines.push(`${indent}${prefix}${relPath}`);

          if (recursive && entry.isDirectory() && depth < 3) {
            lines.push(...await listDir(join(dir, entry.name), depth + 1));
          }
        }

        return lines;
      }

      try {
        const lines = await listDir(resolved);
        return {
          content: [{ type: "text" as const, text: lines.join("\n") || "(boş qovluq)" }],
        };
      } catch (err) {
        return {
          content: [{ type: "text" as const, text: `Qovluq siyahılanamadı: ${(err as Error).message}` }],
          isError: true,
        };
      }
    }
  );

  server.tool(
    "read_file",
    "Sandbox-da faylın məzmununu oxu",
    {
      path: z.string().describe("Sandbox daxilindəki fayla nisbi yol"),
      encoding: z
        .enum(["utf8", "base64"])
        .default("utf8")
        .describe("Faylı UTF-8 mətn və ya base64 kimi qaytarın (ikili fayllar üçün)"),
    },
    async ({ path, encoding }) => {
      let resolved: string;
      try {
        resolved = safePath(path);
      } catch (err) {
        return {
          content: [{ type: "text" as const, text: (err as Error).message }],
          isError: true,
        };
      }

      try {
        const info = await stat(resolved);

        if (info.isDirectory()) {
          return {
            content: [{ type: "text" as const, text: "Yol qovluqdur. Əvəzinə list_files istifadə edin." }],
            isError: true,
          };
        }

        // Fayl ölçüsünü 1 MB ilə məhdudlaşdır
        if (info.size > 1_048_576) {
          return {
            content: [
              {
                type: "text" as const,
                text: `Fayl çox böyükdür: ${info.size} bayt (maks 1 MB). Bölməyi və ya ümumiləşdirməyi düşünün.`,
              },
            ],
            isError: true,
          };
        }

        const content = await readFile(resolved, encoding === "base64" ? undefined : "utf8");
        const text    = encoding === "base64"
          ? (content as Buffer).toString("base64")
          : (content as string);

        return {
          content: [{ type: "text" as const, text }],
        };
      } catch (err) {
        return {
          content: [{ type: "text" as const, text: `Fayl oxuna bilmədi: ${(err as Error).message}` }],
          isError: true,
        };
      }
    }
  );
}
```

---

## Tool 3: Xarici API Çağırışı

```typescript
// src/tools/external-api.ts
import { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { z } from "zod";

// İcazə verilmiş API endpointləri — LLM-in ixtiyari URL-ləri çağırmasına heç vaxt icazə verməyin
const ALLOWED_APIS: Record<string, { url: string; description: string }> = {
  weather: {
    url: "https://api.open-meteo.com/v1/forecast",
    description: "Hava proqnozu datasİ",
  },
  exchange_rates: {
    url: "https://api.exchangerate-api.com/v4/latest",
    description: "Valyuta məzənnələri",
  },
};

export function registerApiTools(server: McpServer): void {
  server.tool(
    "call_external_api",
    "Təsdiq edilmiş xarici API çağır. JSON cavabını qaytarır.",
    {
      api: z
        .enum(["weather", "exchange_rates"])
        .describe("Hansı API-ni çağırmaq"),
      params: z
        .record(z.string())
        .default({})
        .describe("Sorğuya əlavə ediləcək sorğu parametrləri"),
    },
    async ({ api, params }) => {
      const apiConfig = ALLOWED_APIS[api];
      if (!apiConfig) {
        return {
          content: [{ type: "text" as const, text: `Naməlum API: ${api}` }],
          isError: true,
        };
      }

      const url = new URL(apiConfig.url);
      for (const [key, value] of Object.entries(params)) {
        url.searchParams.set(key, value);
      }

      try {
        const response = await fetch(url.toString(), {
          headers: { "User-Agent": "MCP-Server/1.0" },
          signal: AbortSignal.timeout(10_000),
        });

        if (!response.ok) {
          return {
            content: [
              {
                type: "text" as const,
                text: `API HTTP ${response.status} qaytardı: ${await response.text()}`,
              },
            ],
            isError: true,
          };
        }

        const data = await response.json();

        return {
          content: [
            {
              type: "text" as const,
              text: JSON.stringify(data, null, 2),
            },
          ],
        };
      } catch (err) {
        return {
          content: [
            {
              type: "text" as const,
              text: `API çağırışı uğursuz oldu: ${(err as Error).message}`,
            },
          ],
          isError: true,
        };
      }
    }
  );
}
```

---

## Resurslar

```typescript
// src/resources/index.ts
import { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { readFile } from "fs/promises";
import { join } from "path";
import pg from "pg";

const { Pool } = pg;
const pool = new Pool({ connectionString: process.env.DATABASE_URL });

export function registerResources(server: McpServer): void {
  // Statik resurs: API sənədləşməsi
  server.resource(
    "api-docs",
    "docs://api-reference",
    { mimeType: "text/markdown", description: "API istinad sənədləşməsi" },
    async () => ({
      contents: [
        {
          uri: "docs://api-reference",
          mimeType: "text/markdown",
          text: await readFile(join(process.cwd(), "docs/api.md"), "utf8").catch(
            () => "# API Sənədləşməsi\n\nSənədləşmə faylı tapılmadı."
          ),
        },
      ],
    })
  );

  // Dinamik resurs: verilənlər bazası sxemi
  server.resource(
    "db-schema",
    "db://schema",
    { mimeType: "application/json", description: "Cari verilənlər bazası sxemi" },
    async () => {
      const client = await pool.connect();
      try {
        const result = await client.query(`
          SELECT table_name, column_name, data_type, is_nullable
          FROM information_schema.columns
          WHERE table_schema = 'public'
          ORDER BY table_name, ordinal_position
        `);

        return {
          contents: [
            {
              uri: "db://schema",
              mimeType: "application/json",
              text: JSON.stringify(result.rows, null, 2),
            },
          ],
        };
      } finally {
        client.release();
      }
    }
  );
}
```

---

## Xəta İdarəetmə Nümunələri

MCP toolları idarəsiz istisnalar atmaz. Bütün xətalar `isError: true` ilə strukturlaşdırılmış cavablar kimi qaytarılmalıdır. Bu, Claude-un nəyin səhv olduğunu başa düşməsinə və potensial olaraq fərqli parametrlərlə yenidən cəhd etməsinə imkan verir.

```typescript
// src/utils/tool-error.ts

export type ToolResult = {
  content: Array<{ type: "text"; text: string }>;
  isError?: boolean;
};

/**
 * Tool handler-i bütün xətaları tutmaq və onları
 * serveri çökdürmək əvəzinə strukturlaşdırılmış xəta
 * cavabları kimi qaytarmaq üçün bürüyür.
 */
export function withErrorHandling<TInput>(
  handler: (input: TInput) => Promise<ToolResult>
): (input: TInput) => Promise<ToolResult> {
  return async (input: TInput): Promise<ToolResult> => {
    try {
      return await handler(input);
    } catch (err) {
      const message = err instanceof Error ? err.message : String(err);
      const stack   = err instanceof Error ? err.stack : undefined;

      // stderr-ə log et (stdio MCP mesajlarına mane olmaz)
      console.error(`Tool xətası: ${message}`, stack);

      return {
        content: [{ type: "text", text: `Xəta: ${message}` }],
        isError: true,
      };
    }
  };
}
```

---

## MCP Inspector ilə Test

MCP Inspector serveri test etmək üçün veb UI verən rəsmi sazlama alatidir:

```bash
npm run inspect
# http://localhost:5173 ünvanı açır
```

Inspector göstərir:
- Bütün elan edilmiş toollar, resurslar və promptlar
- Hər toolu test parametrləri ilə çağırmaq üçün forma
- Göndərilən və alınan xam JSON-RPC mesajları
- Server logları

### `@modelcontextprotocol/sdk` ilə Avtomatik Testlər

```typescript
// src/__tests__/database-tools.test.ts
import { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { Client } from "@modelcontextprotocol/sdk/client/index.js";
import { InMemoryTransport } from "@modelcontextprotocol/sdk/inMemory.js";
import { registerDatabaseTools } from "../tools/database.js";
import { describe, it, expect, beforeAll, afterAll } from "vitest";

describe("verilənlər bazası toolları", () => {
  let server: McpServer;
  let client: Client;

  beforeAll(async () => {
    server = new McpServer({ name: "test", version: "1.0.0" });
    registerDatabaseTools(server);

    client = new Client({ name: "test-client", version: "1.0.0" });

    const [clientTransport, serverTransport] = InMemoryTransport.createLinkedPair();
    await server.connect(serverTransport);
    await client.connect(clientTransport);
  });

  afterAll(async () => {
    await client.close();
    await server.close();
  });

  it("mövcud toolları siyahılayır", async () => {
    const { tools } = await client.listTools();
    const names = tools.map((t) => t.name);
    expect(names).toContain("query_database");
    expect(names).toContain("list_tables");
  });

  it("SELECT olmayan sorğuları rədd edir", async () => {
    const result = await client.callTool({
      name: "query_database",
      arguments: { sql: "DELETE FROM users" },
    });

    expect(result.isError).toBe(true);
    expect((result.content[0] as { text: string }).text).toContain("rədd edildi");
  });

  it("etibarlı SELECT sorğularını icra edir", async () => {
    const result = await client.callTool({
      name: "query_database",
      arguments: { sql: "SELECT 1 AS value", limit: 10 },
    });

    expect(result.isError).toBeFalsy();
    const data = JSON.parse((result.content[0] as { text: string }).text);
    expect(data.rows[0].value).toBe(1);
  });
});
```

---

## Deployment

### Claude Desktop Konfiqurasiyası

Build etdikdən (`npm run build`) sonra `claude_desktop_config.json`-da qeydiyyatdan keçirin:

```json
{
  "mcpServers": {
    "my-production-server": {
      "command": "node",
      "args": ["/mütləq/yol/my-mcp-server/dist/index.js"],
      "env": {
        "DATABASE_URL": "postgresql://user:pass@localhost/mydb",
        "FILES_SANDBOX": "/home/user/workspace"
      }
    }
  }
}
```

### Claude Code CLI Konfiqurasiyası

`.claude/settings.json` (layihə səviyyəsindədir) və ya `~/.claude/settings.json` (qlobaldir):

```json
{
  "mcpServers": {
    "my-production-server": {
      "command": "node",
      "args": ["/mütləq/yol/dist/index.js"],
      "env": {
        "DATABASE_URL": "postgresql://..."
      }
    }
  }
}
```

### HTTP Transport Server (Bulud Deployment üçün)

stdio yalnız lokal process-lər üçündür. Remote server-lərə (Docker, cloud) `StreamableHTTPServerTransport` lazımdır:

```typescript
// src/index-http.ts
import { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { StreamableHTTPServerTransport } from "@modelcontextprotocol/sdk/server/streamableHttp.js";
import { registerDatabaseTools } from "./tools/database.js";
import { registerFileTools } from "./tools/files.js";
import { registerApiTools } from "./tools/external-api.js";
import { registerResources } from "./resources/index.js";
import { createServer } from "node:http";

const PORT = parseInt(process.env.PORT ?? "3000", 10);

async function createMcpServer(): Promise<McpServer> {
  const server = new McpServer({
    name: "my-production-server",
    version: "1.0.0",
  });

  registerDatabaseTools(server);
  registerFileTools(server);
  registerApiTools(server);
  registerResources(server);

  return server;
}

// Hər HTTP bağlantısı üçün ayrı transport instance — stateless dizayn
const httpServer = createServer(async (req, res) => {
  // Yalnız /mcp endpoint-ini idarə et
  if (!req.url?.startsWith("/mcp")) {
    res.writeHead(404).end("Not found");
    return;
  }

  // Bearer token autentifikasiyası
  const authHeader = req.headers["authorization"];
  const expectedToken = process.env.MCP_AUTH_TOKEN;

  if (expectedToken && authHeader !== `Bearer ${expectedToken}`) {
    res.writeHead(401).end(JSON.stringify({ error: "Unauthorized" }));
    return;
  }

  const server = await createMcpServer();
  const transport = new StreamableHTTPServerTransport({
    sessionIdGenerator: undefined, // Stateless: hər request müstəqil
  });

  res.on("close", () => {
    transport.close();
    server.close();
  });

  await server.connect(transport);
  await transport.handleRequest(req, res, req.body);
});

httpServer.listen(PORT, () => {
  console.log(`MCP HTTP server ${PORT} portunda işləyir`);
});

process.on("SIGTERM", () => {
  httpServer.close(() => process.exit(0));
});
```

### Docker Deployment

```dockerfile
# Dockerfile
FROM node:20-alpine AS builder
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY tsconfig.json ./
COPY src/ ./src/
RUN npm run build

FROM node:20-alpine
WORKDIR /app
COPY package*.json ./
RUN npm ci --omit=dev && npm cache clean --force
COPY --from=builder /app/dist ./dist/
EXPOSE 3000
ENV NODE_ENV=production
HEALTHCHECK --interval=30s --timeout=10s \
  CMD wget -qO- http://localhost:3000/health || exit 1
USER node
CMD ["node", "dist/index-http.js"]
```

```yaml
# docker-compose.yml
version: "3.9"

services:
  mcp-server:
    build: .
    restart: unless-stopped
    ports:
      - "3000:3000"
    environment:
      DATABASE_URL: postgresql://user:pass@postgres:5432/mydb
      FILES_SANDBOX: /data/sandbox
      MCP_AUTH_TOKEN: ${MCP_AUTH_TOKEN}
      NODE_ENV: production
    volumes:
      - sandbox_data:/data/sandbox:ro
    depends_on:
      postgres:
        condition: service_healthy
    networks:
      - internal

  postgres:
    image: postgres:16-alpine
    environment:
      POSTGRES_DB: mydb
      POSTGRES_USER: user
      POSTGRES_PASSWORD: pass
    volumes:
      - pg_data:/var/lib/postgresql/data
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U user"]
      interval: 5s
      timeout: 5s
      retries: 5
    networks:
      - internal

volumes:
  pg_data:
  sandbox_data:

networks:
  internal:
```

```bash
# Production deploy
MCP_AUTH_TOKEN=$(openssl rand -hex 32) docker compose up -d
```

### PM2 ilə Production (Docker olmadan)

```yaml
# ecosystem.config.cjs
module.exports = {
  apps: [{
    name: "mcp-server",
    script: "dist/index-http.js",
    instances: 2,           // CPU-ya görə ayarlayın
    exec_mode: "cluster",
    env: {
      NODE_ENV: "production",
      PORT: 3000,
    },
    error_file: "logs/error.log",
    out_file: "logs/out.log",
    log_date_format: "YYYY-MM-DD HH:mm:ss Z",
    max_memory_restart: "512M",
  }]
};
```

```bash
npm run build
pm2 start ecosystem.config.cjs
pm2 save
pm2 startup  # sistem başlanğıcında avtomatik başlat
```

### Nginx Reverse Proxy

```nginx
upstream mcp_backend {
    server 127.0.0.1:3000;
    server 127.0.0.1:3001;   # PM2 cluster instance 2
    keepalive 32;
}

server {
    listen 443 ssl http2;
    server_name mcp.acme.az;

    ssl_certificate     /etc/letsencrypt/live/mcp.acme.az/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/mcp.acme.az/privkey.pem;

    # MCP SSE bağlantıları uzun ömürlüdür — timeout artır
    proxy_read_timeout  300s;
    proxy_send_timeout  300s;

    location /mcp {
        proxy_pass         http://mcp_backend;
        proxy_http_version 1.1;
        proxy_set_header   Connection "";
        proxy_set_header   Host $host;
        proxy_set_header   X-Real-IP $remote_addr;

        # SSE üçün buffering söndür
        proxy_buffering    off;
        proxy_cache        off;
    }
}
```

---

## Praktik Tapşırıqlar

### Tapşırıq 1: Lokal Server + Inspector

1. Bu faylda göstərilən server-i qurun (layihə quraşdırması)
2. Öz PostgreSQL test DB-ni qoşun
3. `npm run inspect` ilə MCP Inspector açın
4. `query_database("SELECT * FROM users LIMIT 5")` tool-unu Inspector-da test edin
5. İnvalid SQL ilə (`DELETE FROM users`) rədd cavabı aldığınızı doğrulayın

### Tapşırıq 2: HTTP Transport + Claude Desktop

1. `npm run build && node dist/index-http.js` ilə HTTP server başladın
2. `claude_desktop_config.json`-a **streamable-http** transport ilə əlavə edin:
   ```json
   { "transport": "streamable-http", "url": "http://localhost:3000/mcp" }
   ```
3. Claude Desktop-da "List all tables" yazın — MCP server-dən cavab gəlməlidir
4. stdio transport ilə latency fərqini müşahidə edin

### Tapşırıq 3: Docker Production Deploy

1. `Dockerfile` + `docker-compose.yml` yazın (bu faylı izləyin)
2. `docker compose up -d` ilə servisi başladın
3. `MCP_AUTH_TOKEN` olmadan `curl http://localhost:3000/mcp` ilə 401 aldığınızı yoxlayın
4. `docker logs` çıxışında server log-larını izləyin

---

## Əlaqəli Mövzular

- `01-mcp-what-is.md` — MCP protokolunun əsasları (bu fayldan əvvəl oxuyun)
- `02-mcp-resources-prompts.md` — Resources + Prompts primitiv-ləri server-ə necə əlavə olunur
- `08-mcp-oauth-auth.md` — HTTP server-ə OAuth 2.1 əlavə etmək (enterprise deployment)
- `10-mcp-testing-debugging.md` — MCP Inspector və avtomatik testlər
- `11-mcp-for-company-laravel.md` — PHP Laravel-də MCP server qurmaq
