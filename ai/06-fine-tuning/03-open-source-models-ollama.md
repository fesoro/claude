# Ollama ilə Açıq Mənbəli LLM-ləri Yerli Olaraq İşlətmək (Middle)

## Niyə Modelləri Yerli Olaraq İşlətməli?

Təşkilatları yerli LLM yerləşdirməyə sövq edən üç əsas səbəb var:

1. **Məxfilik**: müştəri məlumatları, şəxsi məlumatlar (PII), maliyyə qeydləri, tibbi məlumatlar — heç biri infrastrukturunuzu tərk etmir
2. **Xərc**: yüksək həcmlərdə öz avadanlığınızda nəticəçıxarma (inference) API çağırışlarına nisbətən 10-100 dəfə daha ucuzdur
3. **Nəzarət**: API sürət limiti yoxdur, dayanma müddətindən asılılıq yoxdur, satıcıya bağlılıq yoxdur, hava boşluğu mühitləri

Ekosistem sürətlə yetişdi. 2025-ci ildəki yerli modellər bir çox tapşırıq üçün kommersiya API-ləri ilə həqiqətən rəqabət apara bilir.

---

## Model Mənzərəsi

### Llama 3.3 70B (Meta)

2025-ci il üçün yerli yerləşdirmənin qızıl standartı. Əksər tapşırıqlarda Claude Haiku və GPT-4o-mini ilə həqiqətən rəqabət apara bilir.

- **Parametrlər**: 70B
- **Kontekst**: 128K token
- **Güclü cəhətlər**: məntiqi düşüncə, kodlaşdırma, təlimata əməl etmə
- **Avadanlıq**: ~40GB VRAM tələb olunur (4-bit kvantlaşdırılmış)
- **Ən yaxşı istifadə**: ümumi məqsəd, ən yaxşı yerli modeli lazım olduqda

### Mistral Small 3.1 24B

Əla keyfiyyət-ölçü nisbəti. 70B modellərdən daha sürətli və ucuz işləyir.

- **Parametrlər**: 24B
- **Kontekst**: 128K token
- **Güclü cəhətlər**: Avropa dili dəstəyi, qısa cavablar
- **Avadanlıq**: ~14GB VRAM (4-bit kvantlaşdırılmış)
- **Ən yaxşı istifadə**: çoxdilli, xərcdən xəbərdar istehsal yerləşdirməsi

### Qwen 2.5 72B (Alibaba)

Xüsusilə məntiqi düşüncə və kodlaşdırma tapşırıqlarında güclüdür. Çincə, Ərəbcə və bir çox digər dilləri daxil olmaqla əla çoxdilli dəstək.

- **Parametrlər**: 72B
- **Kontekst**: 128K token
- **Güclü cəhətlər**: kodlaşdırma, riyaziyyat, çoxdillilik
- **Avadanlıq**: ~42GB VRAM (4-bit kvantlaşdırılmış)
- **Ən yaxşı istifadə**: kodlaşdırma köməkçiləri, çoxdilli tətbiqlər

### Phi-4 14B (Microsoft)

Parametr başına müstəsna imkan. Kiçik ölçüdə yüksək məntiqi keyfiyyət üçün sintetik məlumatlarla öyrədilmişdir.

- **Parametrlər**: 14B
- **Kontekst**: 16K token
- **Güclü cəhətlər**: məntiqi düşüncə, riyaziyyat, STEM
- **Avadanlıq**: ~9GB VRAM (4-bit kvantlaşdırılmış)
- **Ən yaxşı istifadə**: resurs məhdud mühitlər, STEM ağır tapşırıqlar

### DeepSeek-R1 (DeepSeek)

Gücləndirici öyrənmə ilə öyrədilmiş son texnologiya məntiqi model. Riyaziyyat/kodlaşdırma meyarlarında o1 ilə müqayisə edilə bilər.

- **Parametrlər**: 671B (MoE, ~37B aktiv parametr yükləyir)
- **Kontekst**: 128K token
- **Güclü cəhətlər**: mürəkkəb məntiqi düşüncə, riyaziyyat, uzun formalı analiz
- **Avadanlıq**: tam üçün 8×A100 80GB; distil edilmiş 7B/14B versiyalar mövcuddur
- **Ən yaxşı istifadə**: dərin çox addımlı məntiqi düşüncə tələb edən tapşırıqlar

---

## Avadanlıq Tələbləri

### Model Ölçüsünə Görə Yaddaş Tələbləri

```
Model ölçüsü | 4-bit (Q4) | 8-bit (Q8) | 16-bit (F16)
7B           |    ~5GB    |    ~9GB    |    ~14GB
13B          |    ~9GB    |    ~15GB   |    ~26GB
34B          |   ~23GB    |    ~40GB   |    ~68GB
70B          |   ~40GB    |    ~75GB   |   ~140GB
```

### Avadanlıq Tövsiyələri

| İstifadə Halı | Tövsiyə Edilən Avadanlıq | Qiymət |
|---|---|---|
| İnkişaf / prototipləmə | MacBook Pro M4 Max (128GB) | $4,000 |
| Tək istifadəçi istehsalı | 1× RTX 4090 (24GB) | $2,000 |
| Çox istifadəçi istehsalı (7B) | 2× RTX 4090 | $4,000 |
| Çox istifadəçi istehsalı (70B) | 4× A100 40GB | $40,000 |
| Müəssisə (yüksək məhsuldarlıq) | 8× H100 80GB | $300,000+ |

**Bulud seçimi**: RunPod, Lambda Labs, Vast.ai — dəyişkən iş yükləri üçün saatlıq icarə.

---

## Ollama Arxitekturası

Ollama yerli model serveridir ki:
1. Model fayllarını yükləyir və idarə edir (GGUF formatı)
2. Modelləri GPU/CPU yaddaşına kvantlaşdırma ilə yükləyir
3. OpenAI-nin API formatı ilə uyğun HTTP API-si təqdim edir
4. Model dəyişdirməsi və yaddaş idarəçiliyini həll edir

```
┌─────────────────────────────────────────────────────┐
│                    OLLAMA                           │
│                                                     │
│  ┌─────────────┐   ┌─────────────┐                 │
│  │  Model      │   │  HTTP       │                 │
│  │  Registry   │   │  Server     │◀── Tətbiqiniz   │
│  │  (yerli)    │   │  :11434     │                 │
│  └─────────────┘   └─────────────┘                 │
│                                                     │
│  ┌─────────────────────────────────┐               │
│  │         llama.cpp               │               │
│  │  (nəticəçıxarma mühərriki)      │               │
│  └────────────────┬────────────────┘               │
│                   │                                 │
│        ┌──────────┴──────────┐                     │
│        ▼                     ▼                      │
│     GPU (CUDA)           CPU (AVX2)                │
└─────────────────────────────────────────────────────┘
```

---

## Model Kvantlaşdırması

Kvantlaşdırma, çəkiləri daha aşağı dəqiqliklə saxlamaqla model ölçüsünü azaldır.

### NF4 (4-bit NormalFloat)

QLoRA və istehsal 4-bit nəticəçıxarma üçün standart. LLM çəkilərinin normal paylanması üçün optimallaşdırılmış qeyri-bərabər kvantlaşdırma şəbəkəsi istifadə edir.

### Q4_K_M (llama.cpp formatı)

Ollama-da standart 4-bit kvantlaşdırma. "K" = K-kvantlar (köhnə Q4_0-dan yaxşıdır), "M" = orta keyfiyyət.

- **Tövsiyə edilir**: əksər yerləşdirmələr üçün. Ölçü və keyfiyyətin yaxşı balansı.
- **FP16-ya nisbətən keyfiyyət**: ~1-3% meyar degradasiyası
- **Ölçü azalması**: float16-ya nisbətən ~4x

### Q8_0 (8-bit)

Daha yüksək keyfiyyət, 4-bitdən iki dəfə çox yaddaş.

- **Nə vaxt istifadə edilir**: keyfiyyət VRAM qənaətindən daha vacibdirsə
- **FP16-ya nisbətən keyfiyyət**: ~0.1% degradasiya (demək olar ki, fərq edilmir)
- **Ölçü azalması**: float16-ya nisbətən ~2x

### F16 (Tam float16)

Tam yarım dəqiqlik. Maksimum keyfiyyət, maksimum yaddaş.

- **Nə vaxt istifadə edilir**: VRAM-ınız varsa və hər bir keyfiyyət bitinə ehtiyacınız varsa
- **Ən yaxşı istifadə**: embedding yaratma, incə keyfiyyətin önəmli olduğu tapşırıqlar

### Kvantlaşdırma Seçimi

```
Mövcud VRAM    →  Tövsiyə edilən kvant
< 8GB          →  Q4_K_S (ən kiçik)
8-16GB         →  Q4_K_M (ən yaxşı 4-bit)
16-32GB        →  Q8_0 (demək olar ki mükəmməl)
> 32GB         →  F16 (və ya Q8_0 yaxşıdır)
```

---

## Laravel Tətbiqi

### 1. OllamaClient Xidməti

```php
<?php

namespace App\AI\Clients;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\LazyCollection;

class OllamaClient
{
    private PendingRequest $http;

    public function __construct(
        private readonly string $baseUrl = 'http://localhost:11434',
        private readonly int $timeout = 120,
    ) {
        $this->http = Http::baseUrl($this->baseUrl)
            ->timeout($this->timeout)
            ->acceptJson();
    }

    /**
     * Chat tamamlama (axınsız)
     */
    public function chat(
        string $model,
        array  $messages,
        array  $options = [],
    ): OllamaResponse {
        $response = $this->http->post('/api/chat', [
            'model'    => $model,
            'messages' => $messages,
            'stream'   => false,
            'options'  => array_merge([
                'temperature' => 0.7,
                'num_ctx'     => 4096,
            ], $options),
        ]);

        $data = $response->json();

        return new OllamaResponse(
            content:      $data['message']['content'] ?? '',
            model:        $data['model'],
            promptTokens: $data['prompt_eval_count'] ?? 0,
            completionTokens: $data['eval_count'] ?? 0,
            totalDuration: $data['total_duration'] ?? 0, // nanosaniyə
        );
    }

    /**
     * Axınlı chat — mətn parçaları qaytarır
     */
    public function stream(
        string $model,
        array  $messages,
        array  $options = [],
    ): \Generator {
        $response = $this->http
            ->withOptions(['stream' => true])
            ->post('/api/chat', [
                'model'    => $model,
                'messages' => $messages,
                'stream'   => true,
                'options'  => $options,
            ]);

        $body = $response->toPsrResponse()->getBody();

        while (!$body->eof()) {
            $line = $this->readLine($body);
            if (empty($line)) continue;

            $data = json_decode($line, true);
            if (!$data) continue;

            $content = $data['message']['content'] ?? '';
            if ($content !== '') {
                yield $content;
            }

            if ($data['done'] ?? false) {
                break;
            }
        }
    }

    /**
     * Embedding-lər yaradır
     */
    public function embed(string $model, string $text): array
    {
        $response = $this->http->post('/api/embeddings', [
            'model'  => $model,
            'prompt' => $text,
        ]);

        return $response->json('embedding', []);
    }

    /**
     * Mövcud modelləri siyahıya alır
     */
    public function listModels(): array
    {
        return $this->http->get('/api/tags')->json('models', []);
    }

    /**
     * Ollama reyestrindən model çəkir
     */
    public function pullModel(string $model): void
    {
        $this->http
            ->timeout(3600) // Modellər böyük ola bilər
            ->post('/api/pull', ['name' => $model]);
    }

    /**
     * Ollama-nın işlədiyini yoxlayır
     */
    public function isAvailable(): bool
    {
        try {
            return $this->http->timeout(3)->get('/api/tags')->ok();
        } catch (\Throwable) {
            return false;
        }
    }

    private function readLine($body): string
    {
        $line = '';
        while (!$body->eof()) {
            $char = $body->read(1);
            if ($char === "\n") break;
            $line .= $char;
        }
        return $line;
    }
}

final class OllamaResponse
{
    public function __construct(
        public readonly string $content,
        public readonly string $model,
        public readonly int    $promptTokens,
        public readonly int    $completionTokens,
        public readonly int    $totalDuration,    // nanosaniyə
    ) {}

    public function durationMs(): float
    {
        return $this->totalDuration / 1_000_000;
    }

    public function tokensPerSecond(): float
    {
        if ($this->totalDuration === 0) return 0;
        return $this->completionTokens / ($this->totalDuration / 1_000_000_000);
    }
}
```

### 2. Vahid LLM İnterfeysi (Əvəzedici)

```php
<?php

namespace App\AI\Contracts;

interface LlmClientInterface
{
    public function complete(
        array  $messages,
        string $systemPrompt = '',
        array  $options = [],
    ): LlmResponse;

    public function stream(
        array  $messages,
        string $systemPrompt = '',
        array  $options = [],
    ): \Generator;
}

final class LlmResponse
{
    public function __construct(
        public readonly string $content,
        public readonly string $model,
        public readonly int    $inputTokens,
        public readonly int    $outputTokens,
        public readonly float  $costUsd,
    ) {}
}
```

```php
<?php

namespace App\AI\Clients;

use App\AI\Contracts\LlmClientInterface;
use App\AI\Contracts\LlmResponse;

class OllamaLlmClient implements LlmClientInterface
{
    public function __construct(
        private readonly OllamaClient $ollama,
        private readonly string $model = 'llama3.3:70b-instruct-q4_K_M',
    ) {}

    public function complete(
        array  $messages,
        string $systemPrompt = '',
        array  $options = [],
    ): LlmResponse {
        $formattedMessages = $this->formatMessages($messages, $systemPrompt);

        $response = $this->ollama->chat(
            model:    $this->model,
            messages: $formattedMessages,
            options:  $options,
        );

        return new LlmResponse(
            content:      $response->content,
            model:        $response->model,
            inputTokens:  $response->promptTokens,
            outputTokens: $response->completionTokens,
            costUsd:      0.0, // Pulsuz (yerli)
        );
    }

    public function stream(
        array  $messages,
        string $systemPrompt = '',
        array  $options = [],
    ): \Generator {
        $formattedMessages = $this->formatMessages($messages, $systemPrompt);
        yield from $this->ollama->stream($this->model, $formattedMessages, $options);
    }

    private function formatMessages(array $messages, string $systemPrompt): array
    {
        $result = [];

        if (!empty($systemPrompt)) {
            $result[] = ['role' => 'system', 'content' => $systemPrompt];
        }

        foreach ($messages as $msg) {
            $result[] = [
                'role'    => $msg['role'],
                'content' => is_string($msg['content'])
                    ? $msg['content']
                    : $this->extractTextContent($msg['content']),
            ];
        }

        return $result;
    }

    private function extractTextContent(array $content): string
    {
        return collect($content)
            ->where('type', 'text')
            ->pluck('text')
            ->join("\n");
    }
}
```

```php
<?php

namespace App\AI\Clients;

use App\AI\Contracts\LlmClientInterface;
use App\AI\Contracts\LlmResponse;
use Anthropic\Client;

class AnthropicLlmClient implements LlmClientInterface
{
    private const MODEL_COSTS = [
        'claude-haiku-4-5'  => ['input' => 0.80,  'output' => 4.00],
        'claude-sonnet-4-5' => ['input' => 3.00,  'output' => 15.00],
        'claude-opus-4-5'   => ['input' => 15.00, 'output' => 75.00],
    ];

    public function __construct(
        private readonly Client $claude,
        private readonly string $model = 'claude-haiku-4-5',
    ) {}

    public function complete(
        array  $messages,
        string $systemPrompt = '',
        array  $options = [],
    ): LlmResponse {
        $params = array_filter([
            'model'      => $this->model,
            'max_tokens' => $options['max_tokens'] ?? 2048,
            'system'     => $systemPrompt ?: null,
            'messages'   => $messages,
        ]);

        $response = $this->claude->messages()->create($params);

        $inputTokens  = $response->usage->inputTokens;
        $outputTokens = $response->usage->outputTokens;
        $costs        = self::MODEL_COSTS[$this->model] ?? ['input' => 0, 'output' => 0];

        return new LlmResponse(
            content:      $response->content[0]->text,
            model:        $this->model,
            inputTokens:  $inputTokens,
            outputTokens: $outputTokens,
            costUsd:      ($inputTokens / 1_000_000 * $costs['input'])
                        + ($outputTokens / 1_000_000 * $costs['output']),
        );
    }

    public function stream(
        array  $messages,
        string $systemPrompt = '',
        array  $options = [],
    ): \Generator {
        $params = array_filter([
            'model'      => $this->model,
            'max_tokens' => $options['max_tokens'] ?? 2048,
            'system'     => $systemPrompt ?: null,
            'messages'   => $messages,
        ]);

        $stream = $this->claude->messages()->stream($params);

        foreach ($stream as $event) {
            if ($event->type === 'content_block_delta') {
                yield $event->delta->text ?? '';
            }
        }
    }
}
```

### 3. Model Seçim Strategiyası

```php
<?php

namespace App\AI;

use App\AI\Clients\AnthropicLlmClient;
use App\AI\Clients\OllamaLlmClient;
use App\AI\Contracts\LlmClientInterface;

class ModelSelector
{
    private const TASK_ROUTING = [
        // Sadə, yüksək həcmli tapşırıqlar → yerli model
        'classification'   => 'local',
        'summarization'    => 'local',
        'extraction'       => 'local',
        'translation'      => 'local',
        'formatting'       => 'local',

        // Mürəkkəb məntiqi düşüncə → bulud modeli
        'analysis'         => 'cloud',
        'planning'         => 'cloud',
        'code_generation'  => 'cloud',
        'fact_checking'    => 'cloud',

        // Standart
        'default'          => 'local',
    ];

    public function __construct(
        private readonly OllamaLlmClient    $localClient,
        private readonly AnthropicLlmClient $cloudClient,
        private readonly bool $forceLocal = false,
        private readonly bool $forceCloud = false,
    ) {}

    public function forTask(string $taskType): LlmClientInterface
    {
        if ($this->forceLocal) return $this->localClient;
        if ($this->forceCloud) return $this->cloudClient;

        // Ollama-nın mövcudluğunu yoxlayır
        if (!$this->localClient->isAvailable()) {
            return $this->cloudClient; // Buluda geri düşmə
        }

        $routing = self::TASK_ROUTING[$taskType] ?? self::TASK_ROUTING['default'];

        return $routing === 'local' ? $this->localClient : $this->cloudClient;
    }

    /**
     * Məzmun həssaslığına görə yönləndir.
     * Yüksək həssaslıq → həmişə yerli (məlumatlar infrastrukturu tərk etmir)
     */
    public function forSensitivity(string $sensitivity): LlmClientInterface
    {
        return match($sensitivity) {
            'high'   => $this->localClient, // PII, maliyyə, tibbi
            'medium' => $this->isLocalAvailable() ? $this->localClient : $this->cloudClient,
            'low'    => $this->cloudClient, // İctimai məlumat, ümumi sorğular
            default  => $this->cloudClient,
        };
    }

    private function isLocalAvailable(): bool
    {
        try {
            return $this->localClient->isAvailable();
        } catch (\Throwable) {
            return false;
        }
    }
}
```

### 4. Ollama ilə Axınlı Cavablar (SSE Controller)

```php
<?php

namespace App\Http\Controllers;

use App\AI\Clients\OllamaClient;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LocalChatController extends Controller
{
    public function __construct(
        private readonly OllamaClient $ollama,
    ) {}

    public function chat(Request $request): StreamedResponse
    {
        $request->validate([
            'message'  => ['required', 'string', 'max:10000'],
            'model'    => ['string', 'in:llama3.3:70b,mistral:7b,phi4:14b'],
            'history'  => ['array'],
        ]);

        $model    = $request->string('model', 'llama3.3:70b-instruct-q4_K_M');
        $messages = $request->array('history', []);
        $messages[] = ['role' => 'user', 'content' => $request->string('message')];

        return response()->stream(function () use ($model, $messages) {
            while (ob_get_level() > 0) ob_end_flush();

            foreach ($this->ollama->stream($model, $messages) as $chunk) {
                echo "data: " . json_encode(['content' => $chunk]) . "\n\n";
                flush();
            }

            echo "data: " . json_encode(['done' => true]) . "\n\n";
            flush();
        }, 200, [
            'Content-Type'     => 'text/event-stream',
            'Cache-Control'    => 'no-cache',
            'X-Accel-Buffering'=> 'no',
        ]);
    }

    public function models(): \Illuminate\Http\JsonResponse
    {
        if (!$this->ollama->isAvailable()) {
            return response()->json(['error' => 'Ollama işləmir'], 503);
        }

        return response()->json([
            'models' => $this->ollama->listModels(),
        ]);
    }
}
```

### Xidmət Provayderi

```php
<?php

namespace App\Providers;

use App\AI\Clients\AnthropicLlmClient;
use App\AI\Clients\OllamaClient;
use App\AI\Clients\OllamaLlmClient;
use App\AI\ModelSelector;
use Illuminate\Support\ServiceProvider;

class LlmServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(OllamaClient::class, function () {
            return new OllamaClient(
                baseUrl: config('ai.ollama.url', 'http://localhost:11434'),
                timeout: config('ai.ollama.timeout', 120),
            );
        });

        $this->app->singleton(OllamaLlmClient::class, function ($app) {
            return new OllamaLlmClient(
                ollama: $app->make(OllamaClient::class),
                model: config('ai.ollama.default_model', 'llama3.3:70b-instruct-q4_K_M'),
            );
        });

        $this->app->singleton(ModelSelector::class, function ($app) {
            return new ModelSelector(
                localClient: $app->make(OllamaLlmClient::class),
                cloudClient: $app->make(AnthropicLlmClient::class),
                forceLocal:  config('ai.force_local', false),
                forceCloud:  config('ai.force_cloud', false),
            );
        });
    }
}
```

---

## Docker Compose ilə İstehsal Yerləşdirməsi

```yaml
# docker-compose.yml

version: '3.8'

services:
  ollama:
    image: ollama/ollama:latest
    ports:
      - "11434:11434"
    volumes:
      - ollama_data:/root/.ollama
    deploy:
      resources:
        reservations:
          devices:
            - driver: nvidia
              count: all
              capabilities: [gpu]
    restart: unless-stopped
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:11434/api/tags"]
      interval: 30s
      timeout: 10s
      retries: 3

  app:
    build: .
    depends_on:
      ollama:
        condition: service_healthy
    environment:
      AI_OLLAMA_URL: http://ollama:11434
      AI_OLLAMA_DEFAULT_MODEL: llama3.3:70b-instruct-q4_K_M

volumes:
  ollama_data:
```

```bash
# İlk yerləşdirmədə modelləri çəkin
docker exec ollama ollama pull llama3.3:70b-instruct-q4_K_M
docker exec ollama ollama pull nomic-embed-text  # Embedding-lər üçün
```

---

## Performans Meyarları (Təxmini, Q4_K_M)

| Model | Avadanlıq | Token/san | Gecikmə (ilk token) |
|---|---|---|---|
| Llama 3.1 8B | RTX 4090 | 85 t/s | ~200ms |
| Llama 3.3 70B | 4×RTX 4090 | 18 t/s | ~800ms |
| Llama 3.3 70B | A100 80GB | 35 t/s | ~400ms |
| Phi-4 14B | RTX 4090 | 55 t/s | ~300ms |
| Mistral 24B | RTX 4090 | 30 t/s | ~400ms |
| M4 Max 128GB | Apple Silicon | 45 t/s | ~250ms |

Müqayisə üçün: Claude Haiku API adətən 300-500ms-də ilk tokenləri qaytarır, sonra ~60-100 t/s çıxış verir.

---

## Praktik Tapşırıqlar

### Tapşırıq 1: Ollama + Laravel Inteqrasiyası

Ollama-nı qur, `llama3.2:3b` modelini yüklə. Laravel `OllamaService` yaz: `POST /api/generate` endpoint-inə HTTP request göndər, response-u stream et. `ClaudeService` ilə eyni interface tətbiq et (`message(array $messages): string`). Featurenin API dəyişmədən Ollama-ya keçdiyini test et.

### Tapşırıq 2: Privacy-First Workflow

PII ehtiva edən mətn üzərindən iki emalı müqayisə et: (a) Claude API — data Anthropic serverlərinə gedir, (b) Ollama lokal — data heç yerə getmir. Hər iki halda keyfiyyəti, latency-ni ölç. GDPR tələbi olan data için Ollama-nın deployment modelini sənədləşdir.

### Tapşırıq 3: Model Routing Hibrid

`ModelRouter` implement et: sorğu həssas PII data ehtiva edirsə → Ollama, adi sorğu isə → Claude Haiku. PII detection üçün basit regex ya da NER istifadə et. Cost tracking: lokal inference-in ayda nə qədər Claude API cost-unu əvəz etdiyini hesabla.

---

## Əlaqəli Mövzular

- `09-vllm-model-serving.md` — Production-grade model serving
- `../01-fundamentals/09-llm-provider-comparison.md` — Lokal vs cloud provider müqayisəsi
- `../08-production/15-multi-provider-failover.md` — Ollama fallback kimi multi-provider
- `04-lora-qlora-peft.md` — Yerli modeli fine-tune etmək
