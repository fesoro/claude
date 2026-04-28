# Fine-Tuned Modeli Produksiyada Serve Etmək: vLLM, GGUF, Ollama (Senior)

> **Kim üçündür:** Fine-tuning prosesini başa çatdırmış developerlər. Fine-tuned modeli produksiyaya necə çıxarmaq sualına cavab.
>
> **Əhatə dairəsi:** vLLM, GGUF/llama.cpp, Ollama, BentoML, HuggingFace TGI. Benchmark, Docker deploy, Laravel PHP client inteqrasiyası.

---

## 1. Serving Variantları Baxışı

```
Fine-tuned model (HuggingFace Safetensors/GGUF)
           │
    ┌──────┼──────────────────────┐
    ▼      ▼                      ▼
  vLLM   Ollama           llama.cpp
  (GPU   (dev-friendly,   (CPU/GPU hybrid,
  high   easy setup)      edge/embedded)
  throughput)
    │      │                      │
    └──────┴──────────────────────┘
                   │
           OpenAI-compatible API
                   │
           PHP Laravel client
```

| | vLLM | Ollama | llama.cpp | TGI |
|--|------|--------|-----------|-----|
| **GPU tələbi** | Mütləq | İxtiyari | İxtiyari | Mütləq |
| **Throughput** | Çox yüksək | Orta | Aşağı | Yüksək |
| **Setup** | Orta | Çox asan | Çətin | Orta |
| **LoRA adapter** | Dəstəklənir | Dəstəklənir | Bəli | Dəstəklənir |
| **OpenAI API** | Tam uyğun | Tam uyğun | Qismən | Tam uyğun |
| **İstifadə** | Produksiya, yüksək yük | Dev, kiçik produksiya | Edge, offline | Produksiya |

---

## 2. vLLM: Produksiya Üçün Ən Yaxşı Seçim

### 2.1 Niyə vLLM?

**PagedAttention** — KV cache-i GPU yaddaşında virtual memory kimi idarə edir. Nəticədə:
- 24x daha yüksək throughput (orijinal Hugging Face Transformers-ə nisbətən)
- Concurrent requests daha effektiv idarə olunur
- Uzun sequence-lər üçün yaddaş waste azalır

```
Ənənəvi KV Cache:
  Request 1: [Token 1..2048] → 2048 slot ayrılır (8GB)
  Request 2: [Token 1..500]  → 2048 slot ayrılır (8GB)
  Waste: Request 2 üçün 1548 slot boşdur

vLLM PagedAttention:
  Request 1: [Block 1][Block 2]...[Block 8] → yalnız lazım olan blocks
  Request 2: [Block 1][Block 2]             → yalnız 2 block
  Waste: minimal
```

### 2.2 vLLM ilə Fine-Tuned Model Serve Etmək

```bash
# Installation
pip install vllm

# SFT model (merged LoRA)
python -m vllm.entrypoints.openai.api_server \
    --model ./my-fine-tuned-model \
    --host 0.0.0.0 \
    --port 8000 \
    --dtype bfloat16 \
    --max-model-len 8192 \
    --tensor-parallel-size 1  # GPU sayı

# LoRA adapter ayrı saxlanıbsa (merge etmədən)
python -m vllm.entrypoints.openai.api_server \
    --model meta-llama/Llama-3.3-70B-Instruct \
    --enable-lora \
    --lora-modules my-adapter=./lora-adapter-path \
    --host 0.0.0.0 \
    --port 8000
```

### 2.3 vLLM Konfiqurasiya Parametrləri

```python
# vllm_config.py
from vllm import LLM, SamplingParams

llm = LLM(
    model="./my-fine-tuned-model",
    
    # Yaddaş idarəsi
    gpu_memory_utilization=0.90,   # GPU-nun 90%-ni istifadə et
    max_model_len=8192,            # Maksimum context window
    swap_space=4,                  # CPU RAM swap (GB) — OOM-dan qorunmaq
    
    # Throughput
    max_num_batched_tokens=32768,  # Bir batch-da maksimum token
    max_num_seqs=256,              # Parallel sequence sayı
    
    # Dəqiqlik
    dtype="bfloat16",
    
    # LoRA (merge etmədən)
    enable_lora=True,
    max_lora_rank=64,
)
```

### 2.4 Docker ilə Deploy

```dockerfile
# Dockerfile.vllm
FROM nvcr.io/nvidia/cuda:12.1-devel-ubuntu22.04

RUN pip install vllm

WORKDIR /app
COPY ./my-fine-tuned-model /app/model

CMD ["python", "-m", "vllm.entrypoints.openai.api_server", \
     "--model", "/app/model", \
     "--host", "0.0.0.0", \
     "--port", "8000", \
     "--dtype", "bfloat16"]
```

```yaml
# docker-compose.vllm.yml
services:
  vllm:
    build:
      context: .
      dockerfile: Dockerfile.vllm
    ports:
      - "8000:8000"
    deploy:
      resources:
        reservations:
          devices:
            - driver: nvidia
              count: 1
              capabilities: [gpu]
    environment:
      - CUDA_VISIBLE_DEVICES=0
    volumes:
      - ./model:/app/model:ro  # Read-only model mount
    restart: unless-stopped
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:8000/health"]
      interval: 30s
      timeout: 10s
      retries: 3
```

---

## 3. GGUF / llama.cpp: CPU + Kiçik GPU Yerləşdirmə

### 3.1 GGUF Nədir?

Modeli kvantlaşdırılmış formata çevirmək — 16-bit model 4-bit olur, ölçü 4x azalır.

```
Llama 3.3 70B:
  bf16: ~140GB → 2×A100 80GB lazımdır
  Q4_K_M: ~40GB → 1×A100 80GB (ya da high-end desktop GPU)
  Q2_K:   ~25GB → RTX 4090 ilə işləyir (keyfiyyət aşağı)
```

### 3.2 LoRA-dan GGUF-a

```bash
# 1. LoRA-nı base model ilə merge et
python -c "
from peft import AutoPeftModelForCausalLM
import torch

model = AutoPeftModelForCausalLM.from_pretrained(
    './lora-adapter',
    torch_dtype=torch.bfloat16,
    device_map='auto',
)
merged = model.merge_and_unload()
merged.save_pretrained('./merged-model')
"

# 2. Safetensors → GGUF çevir
git clone https://github.com/ggerganov/llama.cpp
cd llama.cpp
python convert-hf-to-gguf.py ../merged-model --outfile ../model.gguf

# 3. Kvantlaşdır (Q4_K_M — yaxşı keyfiyyət/ölçü balansı)
./quantize ../model.gguf ../model-q4km.gguf Q4_K_M
```

### 3.3 Ollama ilə GGUF Deploy

```bash
# Modelfile yarat
cat > Modelfile << 'EOF'
FROM ./model-q4km.gguf

PARAMETER temperature 0.3
PARAMETER num_ctx 8192
PARAMETER num_predict 1000

SYSTEM """
Sən senior PHP/Laravel developer-lərinə kömək edən texniki assistentsan.
Azərbaycan dilindədir, texniki terminlər ingilis dilindədir.
"""
EOF

# Ollama-ya əlavə et
ollama create my-laravel-assistant -f Modelfile

# Test et
ollama run my-laravel-assistant "Laravel queue-u necə konfiqurasiya etmək olar?"

# API kimi serve et (OpenAI uyğun)
ollama serve  # localhost:11434
```

---

## 4. Laravel PHP Client

vLLM/Ollama hər ikisi OpenAI-uyğun API verir. Eyni PHP client istifadə oluna bilər.

### 4.1 Universal AI Client

```php
<?php
// app/Services/AI/LocalModelClient.php

namespace App\Services\AI;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class LocalModelClient
{
    private PendingRequest $http;

    public function __construct()
    {
        $baseUrl = config('ai.local_model_url', 'http://localhost:8000');

        $this->http = Http::baseUrl($baseUrl)
            ->timeout(120)
            ->withHeaders(['Content-Type' => 'application/json']);
    }

    public function chat(
        string $userMessage,
        string $systemPrompt = '',
        float  $temperature  = 0.3,
        int    $maxTokens    = 1000,
        string $model        = 'my-laravel-assistant',
    ): string {
        $messages = [];

        if ($systemPrompt) {
            $messages[] = ['role' => 'system', 'content' => $systemPrompt];
        }

        $messages[] = ['role' => 'user', 'content' => $userMessage];

        $response = $this->http->post('/v1/chat/completions', [
            'model'       => $model,
            'messages'    => $messages,
            'temperature' => $temperature,
            'max_tokens'  => $maxTokens,
            'stream'      => false,
        ]);

        if ($response->failed()) {
            throw new \RuntimeException(
                "Local model error: {$response->status()} - {$response->body()}"
            );
        }

        return $response->json('choices.0.message.content', '');
    }

    public function streamChat(
        string   $userMessage,
        callable $onChunk,
        string   $systemPrompt = '',
        float    $temperature  = 0.3,
    ): void {
        $messages = [];

        if ($systemPrompt) {
            $messages[] = ['role' => 'system', 'content' => $systemPrompt];
        }

        $messages[] = ['role' => 'user', 'content' => $userMessage];

        $this->http->withOptions(['stream' => true])
            ->post('/v1/chat/completions', [
                'model'       => config('ai.local_model_name'),
                'messages'    => $messages,
                'temperature' => $temperature,
                'stream'      => true,
            ])
            ->throw()
            ->toPsrResponse()
            ->getBody()
            ->read(function ($chunk) use ($onChunk) {
                foreach (explode("\n", $chunk) as $line) {
                    if (str_starts_with($line, 'data: ')) {
                        $data = json_decode(substr($line, 6), true);
                        if ($data && isset($data['choices'][0]['delta']['content'])) {
                            $onChunk($data['choices'][0]['delta']['content']);
                        }
                    }
                }
            });
    }
}
```

### 4.2 Config və Service Provider

```php
<?php
// config/ai.php
return [
    'provider'          => env('AI_PROVIDER', 'claude'),  // claude | local
    'local_model_url'   => env('LOCAL_MODEL_URL', 'http://localhost:8000'),
    'local_model_name'  => env('LOCAL_MODEL_NAME', 'my-laravel-assistant'),
    'claude_api_key'    => env('ANTHROPIC_API_KEY'),
];
```

```php
<?php
// app/Providers/AIServiceProvider.php
use App\Services\AI\LocalModelClient;
use App\Services\AI\ClaudeService;

$this->app->bind('ai.chat', function () {
    return config('ai.provider') === 'local'
        ? new LocalModelClient()
        : new ClaudeService();
});
```

---

## 5. Benchmark: Tokens/Second

Real ölçüm aparmaq — model-i produksiyaya çıxarmadan əvvəl benchmark mütləqdir.

```python
# benchmark/throughput_test.py
import asyncio
import time
import httpx

async def single_request(client: httpx.AsyncClient, prompt: str) -> dict:
    start = time.perf_counter()
    
    response = await client.post(
        "/v1/chat/completions",
        json={
            "model": "my-model",
            "messages": [{"role": "user", "content": prompt}],
            "max_tokens": 200,
            "temperature": 0.0,
        },
        timeout=60,
    )
    
    elapsed = time.perf_counter() - start
    data     = response.json()
    tokens   = data["usage"]["completion_tokens"]
    
    return {
        "latency_ms":   elapsed * 1000,
        "tokens":       tokens,
        "tokens_per_s": tokens / elapsed,
    }

async def benchmark(
    url: str,
    prompts: list[str],
    concurrent: int = 10,
) -> dict:
    async with httpx.AsyncClient(base_url=url) as client:
        tasks   = [single_request(client, p) for p in prompts[:concurrent]]
        results = await asyncio.gather(*tasks)
    
    latencies = [r["latency_ms"] for r in results]
    tps       = [r["tokens_per_s"] for r in results]
    
    return {
        "p50_latency_ms":  sorted(latencies)[len(latencies) // 2],
        "p99_latency_ms":  sorted(latencies)[int(len(latencies) * 0.99)],
        "avg_tokens_per_s": sum(tps) / len(tps),
        "concurrent_reqs":  concurrent,
    }

# Test prompts (realistic)
PROMPTS = [
    "Laravel Queue worker-i necə restart etmək olar?",
    "N+1 problemi nədir və eager loading necə işləyir?",
    "Redis cache-i Laravel-də necə konfiqurasiya edirsiniz?",
] * 20  # 60 test request

# asyncio.run(benchmark("http://localhost:8000", PROMPTS, concurrent=10))
```

**Gözlənilən nəticələr (A100 40GB üzərindəki 70B model):**

| Concurrent | p50 latency | p99 latency | Tokens/s |
|-----------|-------------|-------------|---------|
| 1         | 1.2s        | 1.5s        | 120     |
| 10        | 2.1s        | 4.8s        | 890     |
| 50        | 6.3s        | 12s         | 1,800   |

---

## 6. LoRA Adapter Swap (Multi-Adapter Serving)

Fərqli task-lər üçün fərqli adapter-lər — base model bir dəfə yüklənir.

```bash
# vLLM multi-adapter
python -m vllm.entrypoints.openai.api_server \
    --model meta-llama/Llama-3.3-70B-Instruct \
    --enable-lora \
    --lora-modules \
        code-review=./adapters/code-review \
        customer-support=./adapters/customer-support \
        sql-assistant=./adapters/sql-assistant \
    --max-loras 3
```

```php
<?php
// Laravel-dən adapter seçimi
public function chat(string $message, string $taskType): string
{
    $model = match ($taskType) {
        'code-review'      => 'code-review',
        'customer-support' => 'customer-support',
        'sql'              => 'sql-assistant',
        default            => 'meta-llama/Llama-3.3-70B-Instruct',
    };

    return $this->localModelClient->chat(
        userMessage: $message,
        model: $model,
    );
}
```

---

## 7. Produksiya Checklist

Fine-tuned modeli serve etməzdən əvvəl:

```
Infrastructure:
  ☐ GPU server konfiqurasiyası (CUDA version uyğunluğu)
  ☐ Docker/NVIDIA Container Toolkit qurulub
  ☐ Model fayl path-ları doğrulanıb (HF Hub vs local)
  ☐ Health check endpoint işləyir

Performance:
  ☐ Benchmark: concurrent load altında latency ölçülüb
  ☐ Max concurrent requests müəyyən edilib (OOM olmamaq üçün)
  ☐ Memory profiling aparılıb (gpu-memory-utilization tuned)
  ☐ Throughput SLA müəyyən edilib (məs: p99 < 10s)

Keyfiyyət:
  ☐ Eval test set üzərindən skor alınıb
  ☐ Base model ilə müqayisə aparılıb
  ☐ Edge case-lər test edilib
  ☐ Alignment yoxlanılıb (harmful output?)

Monitoring:
  ☐ Token/request metrics
  ☐ Latency alertlər
  ☐ Error rate monitoring
  ☐ GPU utilization tracking

Rollback:
  ☐ Köhnə model/adapter version saxlanılır
  ☐ Feature flag ilə model switch mümkündür
  ☐ Fallback provider mövcuddur (Claude API)
```

---

## 8. Anti-Pattern-lər

### Model Faylını Git-ə Commit Etmək

```
# YANLIŞ: Model faylları çox böyükdür
git add ./model-weights/  # 40GB → git unusable olur

# DOĞRU: Model ayrı artifact store-da
- HuggingFace Hub (private)
- AWS S3 + DVC
- MLflow Artifact Store
```

### Kvantizasiya Keyfiyyət Testi Olmadan

```
Q4_K_M: Çox vaxt fine-tuned model üçün kabul edilə bilər
Q2_K:   Aggressiv — task keyfiyyəti 10-15% düşə bilər
        MHAZIRDA TEST ET, sonra deploy et
```

### CPU-da vLLM

```
vLLM GPU olmadan işləmir. CPU inference üçün llama.cpp istifadə edin.
```

---

## Praktik Tapşırıqlar

### Tapşırıq 1: vLLM OpenAI-Compatible Endpoint

Fine-tuned Llama modelini vLLM ilə serve et: `vllm serve ./model --host 0.0.0.0 --port 8000`. Laravel-də `OllamaService` interfeysinə uyğun `VllmService` yaz: `POST /v1/chat/completions`. Claude API-si ilə eyni interface-dən istifadə etdiyini test et.

### Tapşırıq 2: Throughput Benchmark

vLLM vs llama.cpp benchmark-ı keçir. 50 concurrent request göndər. Hər konfiqurasiya üçün: requests/second, p95 latency, GPU utilization ölç. vLLM-in continuous batching-i latency-ni nə qədər azaldır?

### Tapşırıq 3: LoRA Adapter Hot-Swap

Eyni base model üzərindən 2 fərqli LoRA adapter-i (customer-support, code-assistant) dinamik yüklə. vLLM-in `--enable-lora` flag-i ilə hər sorğu üçün `lora_request` parametrini göndər. Her iki adapter-in doğru işlədiyini, base model-in dəyişmədiyini yoxla.

---

## Əlaqəli Mövzular

- [05-create-custom-model-finetune.md](05-create-custom-model-finetune.md) — Model fine-tuning
- [04-lora-qlora-peft.md](04-lora-qlora-peft.md) — LoRA adapter hazırlamaq
- [03-open-source-models-ollama.md](03-open-source-models-ollama.md) — Ollama giriş
- [../08-production/05-latency-optimization.md](../08-production/05-latency-optimization.md) — Produksiya latency
