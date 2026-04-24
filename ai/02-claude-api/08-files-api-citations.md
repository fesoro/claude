# Claude Files API və Citations: Document Upload, Reuse və Attributed Answers

> Hədəf auditoriyası: Laravel production-da document-based workflow-lar (HR chatbot, legal review, customer support KB) quran senior developerlər. Bu sənəd Files API-in lifecycle idarəsini, Citations feature-u ilə verifiable cavabların necə veriliyini və hər ikisinin RAG ilə münasibətini əhatə edir. Vision üçün bax 06-vision-pdf-support.md; RAG folder-i: 04-rag.

---

## Mündəricat

1. [Files API — Nə Üçün Lazımdır](#why-files-api)
2. [Files API vs Inline Base64 — Qərar Nöqtələri](#files-vs-inline)
3. [API Endpoint-ləri: Upload, List, Get, Delete](#endpoints)
4. [File Lifecycle və TTL](#lifecycle)
5. [Quota və Limit-lər](#quotas)
6. [Prompt Caching ilə Sinergiya](#caching-synergy)
7. [Citations — Attributed Answers](#citations)
8. [Citations Response Format](#citations-format)
9. [Citations-ı Request-də Aktivləşdirmək](#enabling-citations)
10. [Laravel Service: File Lifecycle Manager](#file-manager)
11. [Tam Nümunə: HR Document Assistant](#hr-assistant)
12. [Citation-grounded RAG Pattern](#grounded-rag)
13. [Tenant Isolation və Security](#tenant-isolation)
14. [Compliance Use Cases](#compliance)
15. [Gotchas və Hallucination Reality Check](#gotchas)
16. [Anti-Pattern-lər](#anti-patterns)
17. [Qərar Çərçivəsi](#decision-framework)

---

## Files API — Nə Üçün Lazımdır

Files API (2024-də çıxdı) Anthropic-in platformasına dosya upload edib, sonra onu **file_id** ilə istinad etməyə imkan verir. Base64 inline-dan fərqli:

```
INLINE BASE64:
 Hər request-də 5 MB fayl göndərilir
 10 request → 50 MB şəbəkə yükü
 Hər dəfə yenidən tokenizə olunur
 Cache etmək çətin

FILES API:
 Bir dəfə upload (POST /v1/files)
 Hər request-də yalnız file_id (~30 bayt)
 Tokenizasiya avtomatik cache-lənir (prompt caching ilə)
 Lifecycle idarə olunur
```

### Əsas Use-Case-lər

1. **Multi-turn conversation same file**: istifadəçi bir sənədi yükləyir, çoxlu sual verir
2. **Knowledge base**: sabit sənədlər toplusu, bütün user-lər üçün paylaşılan
3. **Large PDF**: böyük faylları base64 göndərmək rateli
4. **Multi-document analysis**: bir request-də 5-10 fayl
5. **Reusable context**: support playbook, onboarding docs

---

## Files API vs Inline Base64 — Qərar Nöqtələri

| Faktor | Inline Base64 | Files API |
|---|---|---|
| Setup kompleksikliyi | Sadə | Orta |
| Network cost | Hər req-də tam fayl | Yalnız file_id |
| Reuse | Yox | Bəli |
| Cache synergy | Məhdud | Excellent |
| Storage management | Ayrı infra | Anthropic |
| TTL | N/A | 30+ gün |
| Max ölçü | 5 MB image / 32 MB PDF | 500 MB/fayl |
| Tenant isolation | Developer responsibility | Developer responsibility |

### Qərar Qaydası

```
Fayl bir dəfə istifadə olunur + <5 MB   → Inline base64
Fayl çoxlu dəfə istifadə olunur         → Files API
Fayl >32 MB                             → Files API (məcburi)
Multi-tenant, hər tenant öz fayllar      → Files API + metadata
Prompt caching aktiv                     → Files API (optimal)
Prototyping / development                → Inline base64
```

---

## API Endpoint-ləri: Upload, List, Get, Delete

### 1. Upload

```
POST /v1/files
Content-Type: multipart/form-data
anthropic-version: 2023-06-01
anthropic-beta: files-api-2025-04-14
x-api-key: ...

file: [binary]
```

Response:

```json
{
  "id": "file_011CpyNf3aALDr...",
  "type": "file",
  "filename": "contract.pdf",
  "mime_type": "application/pdf",
  "size_bytes": 2458112,
  "created_at": "2026-04-24T14:32:10Z",
  "downloadable": true
}
```

### 2. List

```
GET /v1/files?limit=100&before_id=file_xxx
```

Response:

```json
{
  "data": [
    { "id": "file_...", "filename": "...", ... },
    ...
  ],
  "has_more": false,
  "first_id": "file_...",
  "last_id": "file_..."
}
```

### 3. Get Metadata

```
GET /v1/files/{file_id}
```

### 4. Download

```
GET /v1/files/{file_id}/content
```

Fayl binary-sini geri qaytarır. Audit və debug üçün faydalıdır.

### 5. Delete

```
DELETE /v1/files/{file_id}
```

Response:

```json
{
  "id": "file_011CpyNf3aALDr...",
  "deleted": true
}
```

### Messages API-də İstifadə

```json
{
  "model": "claude-sonnet-4-6",
  "max_tokens": 2000,
  "messages": [
    {
      "role": "user",
      "content": [
        {
          "type": "document",
          "source": {
            "type": "file",
            "file_id": "file_011CpyNf3aALDr..."
          }
        },
        {
          "type": "text",
          "text": "Bu müqavilənin əsas risklərini sadala."
        }
      ]
    }
  ]
}
```

---

## File Lifecycle və TTL

Files default olaraq saxlanır, amma developer tərəfindən idarə olunmalıdır.

### Retention

- Upload-dan sonra fayl qalır (avtomatik silinmir)
- Workspace quota-sı var — maksimum limiti çatanda yeni upload rədd edilir
- Manual delete lazımdır

### Lifecycle Best Practice

```
1. Upload - fayl user tərəfindən yüklənir
2. Use    - 1-30 gün aktiv istifadə
3. Archive - köhnə faylları own storage-ə köçür
4. Delete - Anthropic-də sil (quota azad et)
```

### Scheduled Cleanup Job

```php
// app/Console/Commands/CleanupOldAnthropicFiles.php
class CleanupOldAnthropicFiles extends Command
{
    protected $signature = 'anthropic:cleanup-files {--days=30}';

    public function handle(FileManager $manager): void
    {
        $cutoff = now()->subDays($this->option('days'));

        UploadedFile::where('last_used_at', '<', $cutoff)
            ->whereNotNull('anthropic_file_id')
            ->chunkById(100, function ($files) use ($manager) {
                foreach ($files as $file) {
                    $manager->delete($file->anthropic_file_id);
                    $file->update(['anthropic_file_id' => null]);
                }
            });
    }
}
```

Schedule:

```php
// app/Console/Kernel.php
$schedule->command('anthropic:cleanup-files')->daily();
```

---

## Quota və Limit-lər

2026 tipik limit-lər (rəsmi sənədi yoxla):

```
Fayl ölçüsü:           500 MB / fayl (ümumiyyət)
                       32 MB  / PDF (vision processing)
                       5 MB   / image (inline əlaqədə)
Workspace total:       100 GB / workspace
File count:            10,000 / workspace
Upload rate:           60 / minute
Download rate:         100 / minute
```

### Limit aşılırsa

`413 Payload Too Large` — fayl ölçüsü aşdı
`429 Too Many Requests` — upload rate  
`507 Insufficient Storage` — workspace quota doldu

### Quota Monitoring

```php
// Periodically list files və ölçüləri topla
$totalSize = 0;
$client->files()->list(['limit' => 100])->each(function ($file) use (&$totalSize) {
    $totalSize += $file->size_bytes;
});

Log::info("Anthropic workspace usage: " . number_format($totalSize / 1024 / 1024 / 1024, 2) . " GB");

if ($totalSize > 0.8 * 100 * 1024 * 1024 * 1024) {
    // 80% threshold alert
    Alert::send('Anthropic storage 80%+');
}
```

---

## Prompt Caching ilə Sinergiya

Files API-in ən güclü tərəfi — prompt caching ilə kombinasiya. Böyük sənəd bir dəfə tokenizə olunur, sonrakı request-lərdə KV cache-dən istifadə olunur.

### Nümunə

```php
$response = $claude->messages()->create([
    'model' => 'claude-sonnet-4-6',
    'max_tokens' => 1500,
    'system' => 'You are a contract review assistant.',
    'messages' => [
        [
            'role' => 'user',
            'content' => [
                [
                    'type' => 'document',
                    'source' => ['type' => 'file', 'file_id' => $fileId],
                    'cache_control' => ['type' => 'ephemeral'],
                ],
                [
                    'type' => 'text',
                    'text' => 'What is the termination clause?',
                ],
            ],
        ],
    ],
]);
```

### Cost Dinamikası

50-səhifəlik PDF (~70k token):

```
Birinci request:
  Cache write: 70k × $3.75/M = $0.26
  Cavab: 500 tok × $15/M = $0.0075
  Total: ~$0.27

İkinci request (eyni file, 5 dəq içində):
  Cache read: 70k × $0.30/M = $0.021
  Cavab: 500 tok × $15/M = $0.0075
  Total: ~$0.029

10 sual müqayisə:
 Caching ilə:     $0.27 + 9 × $0.029 = $0.53
 Caching-siz:     10 × $0.21 = $2.10
 Qənaət: 75%
```

HR / legal / support chatbot-larda yüksəkdir.

---

## Citations — Attributed Answers

Citations (2025-də GA oldu) — Claude-in hər cavab hissəsini **qaynaq sənəddəki konkret span-a** istinadla qaytarmasına imkan verir.

### Nə Verir?

```
Klassik cavab:
 "Müqavilə 30 günlük notice tələb edir."
 → Hardan? Manual yoxlamaq lazımdır.

Citation ilə:
 "Müqavilə 30 günlük notice tələb edir [1].
  Notice müddəti təcili xitam üçün qısaldıla bilər [2]."

 [1] Page 5, line 23-27: "...shall provide 30 days' written notice..."
 [2] Page 7, line 10-12: "...expedited termination may reduce notice..."
```

### Niyə Vacibdir?

1. **Auditability**: qeyri-dövlət / maliyyə / tibbi domen-lərdə hər iddia source-a bağlı olmalıdır
2. **Trust**: istifadəçi cavaba inanmaq üçün qaynağı yoxlaya bilər
3. **Debugging**: model yanlış cavab verirsə, hansı span-ı səhv şərh etdi görürsən
4. **Compliance**: GDPR "data subject right to explanation" — qərar qaynağı izlənə bilər

### Citation ≠ Hallucination Cure

Bu çox vacib: citation olması cavabın **doğru** olduğunu göstərmir. Citation:
- Cavabın hansı span-a **əsaslandığını** göstərir
- Amma model hələ də span-ı **yanlış şərh edə** bilər
- Amma model **qaynaq olmadan** cümlə qaya bilər (lazımi işarələmə yoxdursa)

Yenə də insan verifikasiyası lazımdır.

---

## Citations Response Format

Citations aktiv olduqda, response-də `content` blokları `citations` array-ı ehtiva edir:

```json
{
  "content": [
    {
      "type": "text",
      "text": "Müqavilə 30 günlük notice tələb edir.",
      "citations": [
        {
          "type": "page_location",
          "document_index": 0,
          "document_title": "contract.pdf",
          "start_page_number": 5,
          "end_page_number": 5,
          "cited_text": "Party shall provide 30 days' written notice prior to termination of this Agreement."
        }
      ]
    },
    {
      "type": "text",
      "text": "Notice müddəti təcili xitam üçün qısaldıla bilər.",
      "citations": [
        {
          "type": "page_location",
          "document_index": 0,
          "start_page_number": 7,
          "end_page_number": 7,
          "cited_text": "In cases of material breach, expedited termination may reduce notice period to 7 days."
        }
      ]
    }
  ]
}
```

### Citation Növləri

- `page_location` — PDF sənədlərdə (page number range)
- `char_location` — text sənədlərdə (character offset)
- `content_block_location` — content block indeksləri (inline sənədlər)

### Parsing Pattern

```php
$citationMap = [];
$answerText = '';

foreach ($response->content as $block) {
    if ($block->type !== 'text') continue;

    $answerText .= $block->text;

    if (!empty($block->citations ?? [])) {
        foreach ($block->citations as $cite) {
            $citationMap[] = [
                'text_span' => $block->text,
                'doc_index' => $cite->document_index,
                'page_start' => $cite->start_page_number ?? null,
                'page_end' => $cite->end_page_number ?? null,
                'cited_text' => $cite->cited_text,
            ];
        }
    }
}
```

---

## Citations-ı Request-də Aktivləşdirmək

Document blocks-una `citations: { enabled: true }` əlavə et:

```json
{
  "model": "claude-sonnet-4-6",
  "max_tokens": 2000,
  "messages": [
    {
      "role": "user",
      "content": [
        {
          "type": "document",
          "source": {
            "type": "file",
            "file_id": "file_..."
          },
          "citations": { "enabled": true },
          "title": "HR Policy 2026"
        },
        {
          "type": "document",
          "source": {
            "type": "file",
            "file_id": "file_..."
          },
          "citations": { "enabled": true },
          "title": "Code of Conduct"
        },
        {
          "type": "text",
          "text": "Remote work qaydası nədir? Hər iddianız üçün cite edin."
        }
      ]
    }
  ]
}
```

### Custom Document Format

Base64 text sənədlər üçün:

```json
{
  "type": "document",
  "source": {
    "type": "text",
    "media_type": "text/plain",
    "data": "Bu şirkət siyasətidir..."
  },
  "citations": { "enabled": true },
  "title": "Internal Policy"
}
```

### Content Blocks (inline document chunks)

```json
{
  "type": "document",
  "source": {
    "type": "content",
    "content": [
      { "type": "text", "text": "Chunk 1..." },
      { "type": "text", "text": "Chunk 2..." }
    ]
  },
  "citations": { "enabled": true },
  "title": "Retrieved chunks"
}
```

Bu RAG scenarioları üçün ideal — retrieved chunk-ları birbaşa content kimi göndərirsən.

---

## Laravel Service: File Lifecycle Manager

Tam production-ready wrapper.

### 1. Local DB Model

```php
Schema::create('anthropic_files', function (Blueprint $t) {
    $t->id();
    $t->uuid('uuid')->unique();
    $t->foreignId('user_id')->constrained();
    $t->foreignId('tenant_id')->nullable()->constrained();
    $t->string('anthropic_file_id', 100);
    $t->string('filename');
    $t->string('mime_type', 100);
    $t->unsignedBigInteger('size_bytes');
    $t->string('file_hash', 64)->index(); // sha256 for dedup
    $t->timestamp('last_used_at')->nullable();
    $t->timestamp('expires_at')->nullable();
    $t->jsonb('metadata')->nullable();
    $t->timestamps();

    $t->index(['user_id', 'file_hash']);
});
```

### 2. File Manager Service

```php
<?php

namespace App\Services\Anthropic;

use Anthropic\Anthropic;
use App\Models\AnthropicFile;
use Illuminate\Http\UploadedFile as LaravelUploadedFile;
use Illuminate\Support\Facades\Log;

class FileManager
{
    public function __construct(private Anthropic $client) {}

    /**
     * Upload ilə deduplication.
     * Eyni content (hash-match) varsa, mövcud file_id qaytarır.
     */
    public function upload(
        LaravelUploadedFile $file,
        int $userId,
        ?int $tenantId = null,
    ): AnthropicFile {
        $contents = file_get_contents($file->getRealPath());
        $hash = hash('sha256', $contents);

        // Dedup check
        $existing = AnthropicFile::where('user_id', $userId)
            ->where('file_hash', $hash)
            ->where('expires_at', '>', now())
            ->first();

        if ($existing) {
            $existing->update(['last_used_at' => now()]);
            return $existing;
        }

        // Upload to Anthropic
        $response = $this->client->files()->upload([
            'file' => $contents,
            'filename' => $file->getClientOriginalName(),
        ]);

        return AnthropicFile::create([
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'anthropic_file_id' => $response->id,
            'filename' => $response->filename,
            'mime_type' => $response->mime_type,
            'size_bytes' => $response->size_bytes,
            'file_hash' => $hash,
            'last_used_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);
    }

    public function delete(string $anthropicFileId): bool
    {
        try {
            $this->client->files()->delete($anthropicFileId);
            return true;
        } catch (\Throwable $e) {
            Log::warning("Failed to delete Anthropic file {$anthropicFileId}: {$e->getMessage()}");
            return false;
        }
    }

    public function markUsed(AnthropicFile $file): void
    {
        $file->update(['last_used_at' => now()]);
    }

    public function list(int $userId, ?int $tenantId = null): array
    {
        return AnthropicFile::where('user_id', $userId)
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where('expires_at', '>', now())
            ->orderByDesc('last_used_at')
            ->get()
            ->toArray();
    }

    /**
     * Multi-document request üçün file_id array-i hazırla.
     */
    public function toDocumentBlocks(array $fileIds, bool $citations = true): array
    {
        return collect($fileIds)->map(fn($id) => [
            'type' => 'document',
            'source' => ['type' => 'file', 'file_id' => $id],
            'citations' => ['enabled' => $citations],
        ])->toArray();
    }
}
```

### 3. Controller

```php
<?php

namespace App\Http\Controllers;

use App\Services\Anthropic\FileManager;
use Illuminate\Http\Request;

class AnthropicFileController
{
    public function __construct(private FileManager $manager) {}

    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:32768', // 32MB
        ]);

        $file = $this->manager->upload(
            file: $request->file('file'),
            userId: $request->user()->id,
            tenantId: $request->user()->tenant_id,
        );

        return response()->json([
            'id' => $file->uuid,
            'filename' => $file->filename,
            'size' => $file->size_bytes,
            'expires_at' => $file->expires_at,
        ]);
    }

    public function destroy(string $uuid, Request $request)
    {
        $file = AnthropicFile::where('uuid', $uuid)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $this->manager->delete($file->anthropic_file_id);
        $file->delete();

        return response()->noContent();
    }
}
```

---

## Tam Nümunə: HR Document Assistant

Use case: şirkətin HR siyasətini yüklə, employee-lər sual verə bilsin.

### 1. Admin Upload

```php
// Admin bir dəfə yükləyir
Route::post('/admin/hr-docs', function (Request $r, FileManager $m) {
    foreach ($r->file('files') as $file) {
        $anthropicFile = $m->upload(
            file: $file,
            userId: auth()->id(),
            tenantId: auth()->user()->tenant_id,
        );

        HRDocument::create([
            'tenant_id' => auth()->user()->tenant_id,
            'anthropic_file_id' => $anthropicFile->id,
            'title' => $file->getClientOriginalName(),
            'active' => true,
        ]);
    }

    return response()->json(['uploaded' => count($r->file('files'))]);
});
```

### 2. Q&A Service

```php
<?php

namespace App\Services;

use Anthropic\Anthropic;
use App\Models\HRDocument;

class HRAssistant
{
    public function __construct(private Anthropic $claude) {}

    public function ask(string $question, int $tenantId): array
    {
        $docs = HRDocument::where('tenant_id', $tenantId)
            ->where('active', true)
            ->with('anthropicFile')
            ->get();

        if ($docs->isEmpty()) {
            return [
                'answer' => 'HR sənədləri hələ yüklənməyib.',
                'citations' => [],
            ];
        }

        $content = [];
        foreach ($docs as $i => $doc) {
            $content[] = [
                'type' => 'document',
                'source' => [
                    'type' => 'file',
                    'file_id' => $doc->anthropicFile->anthropic_file_id,
                ],
                'title' => $doc->title,
                'citations' => ['enabled' => true],
                'cache_control' => ['type' => 'ephemeral'],
            ];
        }

        $content[] = [
            'type' => 'text',
            'text' => $question,
        ];

        $response = $this->claude->messages()->create([
            'model' => 'claude-sonnet-4-6',
            'max_tokens' => 2000,
            'temperature' => 0.2,
            'system' => <<<SYS
Siz şirkətin HR köməkçisisiniz. Yalnız verilmiş HR sənədlərinə
əsasən cavab verirsiniz. Hər iddianız üçün qaynaq sənədə istinad
edin. Sənəddə cavab yoxdursa, "Bu mövzu HR sənədlərimdə yoxdur,
HR departamenti ilə əlaqə saxlayın" deyin.
SYS,
            'messages' => [
                ['role' => 'user', 'content' => $content],
            ],
        ]);

        return $this->formatResponse($response, $docs);
    }

    private function formatResponse($response, $docs): array
    {
        $answer = '';
        $citations = [];

        foreach ($response->content as $block) {
            if ($block->type !== 'text') continue;
            $answer .= $block->text;

            foreach (($block->citations ?? []) as $cite) {
                $docIdx = $cite->document_index ?? 0;
                $citations[] = [
                    'document_title' => $docs[$docIdx]->title ?? 'Unknown',
                    'page_start' => $cite->start_page_number ?? null,
                    'page_end' => $cite->end_page_number ?? null,
                    'cited_text' => $cite->cited_text ?? '',
                    'span_in_answer' => $block->text,
                ];
            }
        }

        return [
            'answer' => $answer,
            'citations' => $citations,
        ];
    }
}
```

### 3. Route

```php
Route::post('/hr/ask', function (Request $r, HRAssistant $hr) {
    $result = $hr->ask($r->input('question'), $r->user()->tenant_id);

    // Log for audit
    HRQuery::create([
        'user_id' => $r->user()->id,
        'question' => $r->input('question'),
        'answer' => $result['answer'],
        'citations' => $result['citations'],
    ]);

    return response()->json($result);
});
```

### 4. UI Rendering

```vue
<template>
  <div>
    <div v-html="formatAnswer(result.answer, result.citations)"></div>
    
    <div class="citations">
      <h4>Qaynaqlar:</h4>
      <div v-for="(c, i) in result.citations" :key="i">
        [{{ i+1 }}] {{ c.document_title }}, 
        page {{ c.page_start }}{{ c.page_start !== c.page_end ? '-'+c.page_end : '' }}
        <blockquote>{{ c.cited_text }}</blockquote>
      </div>
    </div>
  </div>
</template>
```

---

## Citation-grounded RAG Pattern

Files API + Citations RAG-da ən güclü pattern-dir. Arxitektura:

```
┌──────────┐   ┌──────────────┐   ┌──────────────┐
│ User Q   │──▶│ Embed query  │──▶│ Vector DB    │
└──────────┘   └──────────────┘   └──────┬───────┘
                                         │ top-K chunks
                                         ▼
                                  ┌──────────────┐
                                  │ Build docs[] │
                                  │  with        │
                                  │  citations   │
                                  └──────┬───────┘
                                         ▼
                                  ┌──────────────┐
                                  │ Claude API   │
                                  │ + citations  │
                                  └──────┬───────┘
                                         ▼
                                  ┌──────────────┐
                                  │ Parsed answer│
                                  │ + refs       │
                                  └──────────────┘
```

### Implementation

```php
public function answer(string $question, int $topK = 5): array
{
    // 1. Retrieve
    $queryVec = $this->embeddings->embedQuery($question);
    $chunks = Document::nearestNeighbors('embedding', $queryVec, 'cosine')
        ->limit($topK)
        ->get();

    // 2. Build documents array
    $content = [];
    foreach ($chunks as $chunk) {
        $content[] = [
            'type' => 'document',
            'source' => [
                'type' => 'content',
                'content' => [
                    ['type' => 'text', 'text' => $chunk->content],
                ],
            ],
            'title' => $chunk->title,
            'citations' => ['enabled' => true],
        ];
    }

    $content[] = ['type' => 'text', 'text' => $question];

    // 3. Call Claude
    $response = $this->claude->messages()->create([
        'model' => 'claude-sonnet-4-6',
        'max_tokens' => 1500,
        'messages' => [['role' => 'user', 'content' => $content]],
    ]);

    return $this->formatCitedResponse($response, $chunks);
}
```

Bu yanaşmanın üstünlüyü: model **yalnız provided chunks**-a əsaslanır və **hər iddianı span-a bağlayır**. Faithfulness hallucination kəskin azalır.

Ətraflı: 04-rag/22-reranking-models.md və 04-rag folder-i.

---

## Tenant Isolation və Security

Multi-tenant SaaS-da file isolation kritikdir — bir müştərinin faylı başqasına getməməlidir.

### Application-level Izolasiya

Anthropic-in Files API-i **workspace-wide**-dir. Tenant isolation application layer-də etməlisiniz:

```php
// Həmişə user_id + tenant_id ilə scope et
AnthropicFile::where('user_id', $user->id)
    ->where('tenant_id', $user->tenant_id)
    ->where('anthropic_file_id', $fileId)
    ->firstOrFail(); // Yoxdursa 404 — başqa tenant-in file-ıdır

// Request-də istifadə etməzdən əvvəl authorization yoxla
```

### Metadata Tagging

```php
// Upload zamanı metadata yaz
AnthropicFile::create([
    'anthropic_file_id' => $response->id,
    'user_id' => $userId,
    'tenant_id' => $tenantId,
    'metadata' => [
        'confidentiality' => 'internal',
        'department' => 'HR',
        'access_level' => 'employee',
    ],
]);
```

### Separate Workspaces (enterprise)

Anthropic-in Enterprise tier-ində hər tenant üçün ayrı workspace yarat — strong isolation.

```
Tenant A → workspace_a (API key A) → file_ids unique to A
Tenant B → workspace_b (API key B) → file_ids unique to B
```

Bu, hard isolation verir — bir workspace-dən digərinə file access mümkün deyil.

### Encryption at Rest

Anthropic fayl-ları şifrələyir. Amma əlavə layer lazımdırsa:

1. Upload-dan əvvəl **öz key**-inlə encrypt et
2. Claude-ə decrypted göndər
3. Amma bu prompt caching-i pozur və cost artır

Tipik olaraq Anthropic-in default şifrələməsi kifayətdir.

### Audit Logging

Hər file access log olunmalıdır:

```php
AnthropicFileAccess::create([
    'user_id' => auth()->id(),
    'tenant_id' => auth()->user()->tenant_id,
    'file_id' => $file->id,
    'action' => 'used_in_query',
    'query_id' => $queryLog->id,
    'ip_address' => request()->ip(),
    'timestamp' => now(),
]);
```

---

## Compliance Use Cases

### 1. HR Queries (attributable)

Employee "Mənim xəstəlik günləri limitim nədir?" — cavabın HR manual-da hansı səhifədə olduğu göstərilməlidir. Citation bunu təmin edir.

### 2. Legal Review

Hüquqi müqavilə analizində hər risk iddiasının "page X, clause Y"-ya bağlanması zəruridir. Auditor-lar bunu tələb edir.

### 3. Financial Reports

SEC / maliyyə təftişində cavabların qaynağı sübut edilməlidir. Citations bunu sadələşdirir.

### 4. Medical Documentation

Müəyyən klinik suallarda (uygun provayder + enterprise tier ilə), citation qaynaq verilənlər bazasına yönəlməlidir.

### 5. Regulatory Compliance (GDPR "right to explanation")

İstifadəçi "niyə bu qərar verildi" sorğusu edəndə cavabı qaynağa bağlayan mexanizm lazımdır.

---

## Gotchas və Hallucination Reality Check

### 1. Citation Olsa Da, Model Yanlış Şərh Edə Bilər

```
Source: "The contract is valid for 24 months."
Model cavabı (citation ilə): "Müqavilə 2 il 6 ay keçərlidir."
Citation: [source span: "valid for 24 months"]

Hiss olsa da yanlışdır — citation var, amma yanlış şərh olunub.
```

Yanlış şərh citation-sız olan versiyadan **daha inanılandır** — istifadəçi "citation var, deməli düzgündür" fikrini qəbul edə bilər.

**Mitigation**: kritik cavablarda manual review + confidence rating.

### 2. Model Citation Etmədən də Cavab Verə Bilər

"Hər iddia üçün cite et" deməyinizə baxmayaraq, bəzən model cite etmədən cavab hissəsi verir. Post-processing:

```php
if (empty($block->citations)) {
    $flag = 'uncited_claim';
    // Bu hissəni "unverified" işarələ
}
```

### 3. Cited_text Exact Match Olmaya Bilər

Citation-da `cited_text` sənəddən paraphrase edilmiş ola bilər (OCR pozulması və ya whitespace). Fuzzy match lazımdır:

```php
$found = str_contains($document, $cite->cited_text);
if (!$found) {
    // Try fuzzy match
    similar_text($document, $cite->cited_text, $percent);
    if ($percent < 80) {
        Log::warning('Citation text not found in source', ['cite' => $cite]);
    }
}
```

### 4. Page Numbers Approximate

PDF-də page numbers Anthropic-in parsing-inə bağlıdır. Bəzən "page 5" əsl page 4 olur (cover vs. başlanğıc). UI-da göstərərkən bunu nəzərə al.

### 5. Document Index Ordering

`document_index` 0-indexed və request-də documents-ın sırasına uyğundur. Sıralamanı dəyişsən, indekslər dəyişir — cache-dən gələn citations reconstructed olmaya bilər.

### 6. Cost Implication

Citation processing additional computation edir — latency bir az artır (5-15%). Amma token cost-u dəyişmir.

### 7. Rate Limit

Files API ayrı rate limit-dir — bəzən Messages API limit-inə yox, Files limit-inə çatırsan. Monitoring et.

---

## Anti-Pattern-lər

### 1. File-ları Delete Etməmək

Upload, delete-siz → storage quota dolur, yeni upload-lar rədd olur. Scheduled cleanup job yerləşdir.

### 2. Eyni Faylı Dublikat Upload Etmək

User eyni faylı 10 dəfə yükləyirsə, 10 file_id yaranır. Hash-based deduplication et.

### 3. Citations-ı Yalnız Görüntüləmək (Validate Etməmək)

Citation-ı göstərmək kifayət deyil — sənəddə həqiqətən olduğunu yoxla (fuzzy match). Yoxsa istifadəçi tam uydurulmuş citation görə bilər.

### 4. Tenant Isolation-ı İgnore Etmək

"Workspace bir tenant üçün" deyərək file_id-ləri public kimi işlətmək → başqa user-in fayllarına access ola bilər.

### 5. File ID-ləri URL-də Göstərmək

`GET /files/file_ABC123` → leak. Öz uuid-ni istifadə et, DB-də anthropic_file_id saxla.

### 6. Citations-suz Compliance Use-case

Regulated domen-də (HR, legal, medical) citations olmadan production-a getmə. Auditor bunu tələb edəcək.

### 7. Expiry Tracking-siz Upload

Anthropic file-ı nə qədər saxladığını track etməyən app → surprise quota dolmalar. DB-də `expires_at` saxla.

### 8. Page Numbers-ı Hard-Code Kimi Trust Etmək

"Citation page 5" — UI-da "sənəddə page 5-ə get" deyib PDF viewer open etmə; bəzən off-by-one. Orijinal cited_text-i highlight et.

### 9. Citation Count = Quality Düşüncəsi

"10 citation var = yaxşı cavab" — yanlış. Model çoxlu irrelevant citation ata bilər. Quality-ni cavab + citation birlikdə evaluate et.

### 10. File-ı Local-da Silmək, Amma Anthropic-də Saxlamaq

User "sil" düyməsini basır, local DB-dən silinir, Anthropic file qalır. Dual delete zəruridir.

---

## Qərar Çərçivəsi

### Files API vs Inline?

```
File böyükdür (>5 MB)?                    → Files API məcburi
File çoxlu request-də istifadə olunacaq?   → Files API
Multi-tenant?                             → Files API (dedup + metadata)
Bir dəfə işlənəcək + kiçikdir?            → Inline base64
Production RAG?                           → Files API + caching
Prototyping?                              → Inline
```

### Citations Aktivləşdirmək?

| Use Case | Citations |
|---|---|
| HR Q&A | Məcburi |
| Legal document review | Məcburi |
| Financial / audit | Məcburi |
| Internal KB search | Tövsiyə |
| Customer support (policy) | Tövsiyə |
| Creative writing | Lazım deyil |
| Code generation | Lazım deyil |
| Summarization | Opsional |

### Cache Strategy

```
File həmişə dəyişmir (policy doc) → cache_control ephemeral
Yalnız user-specific file        → cache etmə (qısa TTL faydasız)
Multi-user shared KB             → cache (90% read hit)
```

---

## Xülasə

- Files API — upload one, use many. Inline base64-dən çox üstündür reuse scenario-larında
- Lifecycle: upload, use, archive, delete — cleanup job vacibdir
- Prompt caching ilə kombinasiya 75%+ cost qənaət edir (HR / KB use-case-lərdə)
- Citations — hər cavab iddiası source span-ə bağlanır (page_location, char_location)
- Citations compliance (HR, legal, audit) üçün zəruri feature-dur
- Citation olması cavabın doğru olduğunu bilvasitə təsdiq ETMIR — insan verifikasiyası lazımdır
- Laravel-də FileManager service: dedup (hash), TTL tracking, tenant isolation, cleanup
- HR/legal/support chatbot-lar — Files API + Citations + prompt caching tipik production stack
- RAG-da citations content-based document blocks ilə birləşdirilir — faithfulness kəskin artır
- Anti-pattern-lər: deletion-siz upload, dedup-suz, tenant isolation olmadan, citation-ı blind trust etmək

---

*Növbəti: [18 — Computer Use](./12-computer-use.md)*
