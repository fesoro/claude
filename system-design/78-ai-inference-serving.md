# AI Inference Serving at Scale

## Nədir? (What is it?)

**AI inference serving** — train olunmuş ML modellərini (LLM chat, image classification, recommendation, fraud scoring) production trafikdə aşağı latency, yüksək throughput və cost-efficient təqdim edən sistemdir. Model bir dəfə train olunur, amma serving gündə milyonlarla dəfə çağırılır — serving layer ML product-ın iqtisadiyyatını müəyyən edir.

Əsas çağırışlar: GPU bahalıdır ($2-8/saat A100, H100 daha çox), model yaddaşa böyük yer tutur (Llama-70B ≈ 140 GB FP16), batch açılanda throughput 10-50× artır amma latency də artır.

## Requirements (Tələblər)

**Functional:** prediction endpoint (REST/gRPC/streaming), multi-model hosting (eyni cluster-də 10-100 model), versioning və A/B, input validation və tokenization.

**Non-functional:**
- **Latency**: kiçik classifier p99 < 100ms, orta model < 500ms, LLM first-token < 1s, tam cavab 5-15s
- **Throughput**: 10k+ req/sec per region; LLM üçün tokens/sec daha vacib
- **Availability**: 99.95%+
- **Cost**: GPU utilization > 60%, idle GPU = yanmış pul
- **Scalability**: traffic 10× artanda autoscale (GPU scale-up yavaşdır — dəqiqələr)

## Arxitektura (Architecture)

```
               ┌──────────────────────┐
               │     Client / App     │
               │  (Laravel, Web, iOS) │
               └──────────┬───────────┘
                          │ HTTPS / gRPC
                          ▼
               ┌──────────────────────┐
               │     API Gateway      │
               │ (auth, rate limit)   │
               └──────────┬───────────┘
                          ▼
               ┌──────────────────────┐
               │   Inference Router   │
               │  (model-aware LB,    │
               │   sticky for KV-cache│
               │   semantic cache)    │
               └──────────┬───────────┘
                ┌─────────┴─────────┐
                ▼                   ▼
       ┌────────────────┐   ┌────────────────┐
       │ Dynamic Batcher│   │ Dynamic Batcher│
       │ (bs=32, wait=10│   │ (bs=32, wait=10│
       └───────┬────────┘   └───────┬────────┘
               ▼                    ▼
       ┌────────────────┐   ┌────────────────┐
       │  GPU Worker 1  │   │  GPU Worker 2  │
       │ Triton / vLLM  │   │ Triton / vLLM  │
       │ A100 80GB      │   │ A100 80GB      │
       │ - model_v1     │   │ - model_v1     │
       │ - model_v2 (AB)│   │ - embedding    │
       └────────────────┘   └────────────────┘
               ▲                    ▲
       ┌───────┴────────────────────┴───────┐
       │ Model Registry (S3) + warm GPU mem │
       └────────────────────────────────────┘

  Metrics (QPS, p99, GPU%, tokens/s) → Autoscaler
```

## Serving Frameworks

- **TF Serving** (TensorFlow), **TorchServe** (PyTorch), **ONNX Runtime** — framework-specific
- **Triton Inference Server (NVIDIA)** — multi-framework (TF, PyTorch, ONNX, TensorRT), de facto standart
- **LLM-optimized**: **vLLM** (PagedAttention, continuous batching), **TGI** (HuggingFace), **TensorRT-LLM** (H100/FP8), **SGLang**
- **K8s orchestration**: **KServe**, **Seldon Core**, **Ray Serve**

## Dynamic Batching

GPU parallel compute üçün dizayn olunub — 1 request və 32 request təqribən eyni vaxtda işləyir. Batcher gələn request-ləri qısa pəncərədə toplayır, tək GPU call göndərir, cavabları ayırır.

```
t=0ms:  req_A gəlir, batcher wait...
t=3ms:  req_B gəlir
t=7ms:  req_C gəlir
t=10ms: max_wait çatdı → batch=[A,B,C] → GPU (40ms)
t=50ms: response split → A, B, C cavabları
```

Parametrlər: `max_batch_size` (8-64), `max_wait_ms` (5-50). Throughput 10-30× artır, amma queue latency də artır. Latency-sensitive path-da `wait_ms` aşağı.

## Continuous Batching (LLM üçün)

Klassik batching LLM-də işləmir — hər request fərqli sayda token generate edir, batch ən uzununu gözləyir. **Continuous batching** (iteration-level scheduling) hər token addımından sonra bitəni çıxarır, yeni gələni əlavə edir. vLLM breakthrough-u — GPU utilization 30% → 90%, throughput 10-20× artım.

## KV Cache və PagedAttention

Transformer hər yeni token üçün əvvəlki Key/Value matris-lərini cache-ləyir (Llama-70B, 2048 token ≈ 1.6 GB/request). Naive allocation fragmentasiya yaradır — boşluqlar qalır. **PagedAttention (vLLM)** — OS virtual memory kimi cache-i fixed-size block-lara bölür, page table ilə idarə edir. Fragmentation < 4%, eyni GPU-da 2-4× çox concurrent request.

**Prefix caching:** eyni system prompt-lu request-lər KV cache-i paylaşır. 1000-token system prompt = 1000 forward pass qənaət hər request.

## Speculative Decoding

LLM inference bandwidth-bound-dur (compute əslində boşdur). Kiçik draft model (7B) N=4 token təklif edir, böyük model (70B) hamısını tək forward pass-da verify edir. Doğrudursa 4 token 1 addımda hazır, səhv olan ilk tokendən sonrası atılır. 2-3× speedup, output eynidir (math guarantee).

## Quantization

Model parametrlərini aşağı precision-a endirmək:

| Format | Memory | Speedup | Drop |
|--------|--------|---------|------|
| FP32   | 1×     | 1×      | baseline |
| FP16/BF16 | 0.5× | 2×    | ~0%  |
| INT8   | 0.25×  | 2-4×    | 0.5-2% |
| INT4   | 0.125× | 3-5×    | 1-5% |
| FP8 (H100) | 0.25× | 2-4× | ~0.5% |

Llama-70B FP16 = 140 GB (2 A100), INT4 = 35 GB (1 A100), cost 2× aşağı. Texnikalar: GPTQ, AWQ, SmoothQuant, QLoRA.

## GPU Sharing (Multi-tenancy)

Bir GPU-da bir model boşa gedir. Variantlar:
- **MIG (Multi-Instance GPU)** — A100/H100 fiziki GPU-nu 7 slice-a bölür, hardware isolation
- **MPS (Multi-Process Service)** — paralel CUDA kernel-lər, yumşaq isolation
- **Time-slicing** — K8s device plugin, növbə ilə pay
- **Multi-model Triton** — bir server N model, CUDA stream paralel

Production kritik — MIG, dev/staging — MPS. GPU utilization 20% → 70%.

## Routing və Load Balancing

Klassik least-connections chat LLM üçün zəifdir — KV cache locality pozulur. **Sticky routing** (conversation_id hash) və **prefix-aware routing** (system prompt hash → cache sahibi worker) cache hit 50-70%. Power-of-two-choices + GPU memory pressure feedback.

## Model Versioning və Deployment

- **Canary**: v2 1% → 10% → 100%, accuracy + latency monitor
- **Shadow**: v2 request alır, cavabı user-ə çatmır, offline v1 ilə compare
- **Blue-green**: instant rollback, 2× GPU cost
- **A/B**: user bucketing, business KPI (CTR, revenue)

Bax Fayl 72 — deployment strategies.

## Autoscaling

GPU scale-up yavaşdır: node boot 2-5 dəq + model endirmə (Llama-70B 140 GB) 3-10 dəq + CUDA warm-up 30-60 san = **5-15 dəq**. "Traffic artdı, pod aç" faydasızdır.

- **Pre-warmed pool** — 20% buffer
- **Predictive scaling** — trafik profilinə görə (saat 9:00 peak)
- **Queue + 429 Retry-After** — qəfil burst
- **Two-tier fallback** — burst kiçik modelə redirect

## Cost Optimizations

- **Batch inference** (non-latency-critical, nightly) — ayrı pool, 90%+ GPU util, 5-10× ucuz
- **Spot / preemptible GPUs** — 60-90% ucuz, interrupt riski, stateless serving-ə uyğun
- **Distillation** — 70B teacher → 7B student, 10× ucuz, 90% keyfiyyət
- **Right-sizing** — task-specific fine-tune böyük model-i əvəz edə bilər
- **Request coalescing** — eyni anda identik request-lər tək inference

## Semantic Caching

Exact cache LLM-də zəifdir ("What is Docker?" vs "Explain Docker" eyni cavab, fərqli hash).

1. Query embedding hesabla (kiçik model, 5ms, 384-dim)
2. Vector DB (Pinecone, Milvus, Redis VSS) similarity top-1
3. Similarity > 0.95 → cached response
4. Miss → LLM, sonra cache-lə

FAQ/support chatbot hit rate 30-50%, cost 30-50% aşağı. Risky domain-də (medical, legal) disable. Bax Fayl 69.

## Serving Modes

- **Online synchronous** — user-facing, p99 SLA, blocking
- **Streaming (SSE)** — token-by-token LLM, first-token < 1s UX kritik
- **Batch inference** — nightly, GPU tam dolu, p99 latency yoxdur (SageMaker Batch Transform)
- **Serverless (per-invocation)** — cold start var, ucuz az trafik üçün

## Monitoring

**Core:** latency histogram (p50/p95/p99), QPS per model, GPU util (< 30% waste, > 85% throttle), GPU memory, queue depth, batch size histogram.

**LLM-specific:** tokens/sec per replica, time-to-first-token (TTFT), inter-token latency, KV cache utilization, rejection rate.

**Quality (offline):** model drift, hallucination rate, confidence distribution.

## Model Loading və Cold Start

**Warm boot:** model GPU memory-də, ilk request milliseconds-da hazır.

**Cold start (5-15 dəq):** S3 download + GPU transfer + CUDA graph compile + warm-up request.

Həllər:
- **Pre-load at startup**, hazır saxla
- **LRU unload** — 100 model var, GPU 10-una yer, 90 gün passiv evict
- **Lazy load** — nadir model ilk request-də
- **Model sharding** — tensor parallelism 2+ GPU-ya, paralel yükləmə

## Data Model

```sql
CREATE TABLE models (
  id              BIGSERIAL PRIMARY KEY,
  name            TEXT NOT NULL,
  version         TEXT NOT NULL,
  framework       TEXT,  -- pytorch/tensorflow/onnx/tensorrt
  quantization    TEXT,  -- fp16/int8/int4
  gpu_memory_mb   INT,
  artifact_uri    TEXT,  -- s3://...
  status          TEXT,  -- staging/production/archived
  UNIQUE(name, version)
);

CREATE TABLE endpoints (
  id              BIGSERIAL PRIMARY KEY,
  name            TEXT UNIQUE,
  model_id        BIGINT REFERENCES models(id),
  replica_count   INT,
  gpu_type        TEXT,  -- a100_80gb/h100/t4
  max_batch_size  INT,
  max_wait_ms     INT,
  autoscale_min   INT,
  autoscale_max   INT
);

CREATE TABLE inference_logs (
  id              BIGSERIAL PRIMARY KEY,
  endpoint_id     BIGINT,
  request_id      UUID,
  input_tokens    INT,
  output_tokens   INT,
  latency_ms      INT,
  queue_ms        INT,
  gpu_ms          INT,
  created_at      TIMESTAMPTZ
) PARTITION BY RANGE (created_at);
```

Logs həcmli (milyard/gün) — partition + TTL 30 gün, sampling 10% detallı.

## Laravel Inference Client

```php
namespace App\Services;

use Illuminate\Support\Facades\{Http, Cache, Log};

class InferenceClient
{
    private string $triton;
    private string $vllm;

    public function __construct()
    {
        $this->triton = config('inference.triton_url'); // http://triton:8000
        $this->vllm   = config('inference.vllm_url');
    }

    /** Triton HTTP (dynamic batching server-side). */
    public function classify(array $features, string $model = 'fraud_v3'): array
    {
        $key = 'infer:'.$model.':'.md5(json_encode($features));

        return Cache::remember($key, 60, function () use ($features, $model) {
            $res = Http::timeout(1.0)->retry(2, 100, throw: false)
                ->post("{$this->triton}/v2/models/{$model}/infer", [
                    'inputs' => [[
                        'name'     => 'INPUT__0',
                        'shape'    => [1, count($features)],
                        'datatype' => 'FP32',
                        'data'     => $features,
                    ]],
                ]);

            if ($res->failed()) {
                Log::error('inference.failed', ['model' => $model, 'status' => $res->status()]);
                throw new \RuntimeException('Inference failed');
            }

            return ['score' => $res->json('outputs.0.data.0'), 'model' => $model];
        });
    }

    /** LLM streaming via vLLM (OpenAI-compatible). */
    public function streamChat(string $prompt, string $model = 'llama-3-8b'): \Generator
    {
        $res = Http::withOptions(['stream' => true])->timeout(30)
            ->post("{$this->vllm}/v1/chat/completions", [
                'model'      => $model,
                'messages'   => [['role' => 'user', 'content' => $prompt]],
                'stream'     => true,
                'max_tokens' => 1024,
            ]);

        $body = $res->toPsrResponse()->getBody();
        while (! $body->eof()) {
            $line = $this->readLine($body);
            if (! str_starts_with($line, 'data: ')) continue;
            $json = substr($line, 6);
            if (trim($json) === '[DONE]') break;
            $delta = json_decode($json, true)['choices'][0]['delta']['content'] ?? '';
            if ($delta !== '') yield $delta;
        }
    }

    private function readLine($s): string
    {
        $line = '';
        while (! $s->eof() && ($c = $s->read(1)) !== "\n") $line .= $c;
        return $line;
    }
}
```

SSE controller-də:

```php
public function chat(Request $r, InferenceClient $c)
{
    return response()->stream(function () use ($r, $c) {
        foreach ($c->streamChat($r->input('prompt')) as $token) {
            echo "data: ".json_encode(['token' => $token])."\n\n";
            ob_flush(); flush();
        }
        echo "data: [DONE]\n\n";
    }, 200, [
        'Content-Type'      => 'text/event-stream',
        'X-Accel-Buffering' => 'no', // Nginx buffering off
    ]);
}
```

## Real Sistemlər

- **OpenAI** — custom C++/CUDA stack, continuous batching, speculative decoding, H100 clusters
- **Anthropic Claude** — long context (200k+) üçün aggressive KV cache reuse
- **HuggingFace Inference Endpoints** — TGI managed, 100k+ model catalog
- **AWS SageMaker / GCP Vertex AI** — endpoint, serverless, batch transform
- **NVIDIA NIM** — pre-packaged optimized microservices
- **Cloudflare Workers AI** — edge, kiçik modellər, global low latency

## Trade-offs

- **Throughput vs latency**: böyük batch → p99 pisləşir
- **Quality vs cost**: Llama-70B vs 8B — 10× cost, 10-15% quality; 8B fine-tune çox halda kifayət
- **Managed vs self-host**: Bedrock/OpenAI API ($/token, 0 ops) vs GPU lease + SRE; ~1M req/gün break-even
- **Precision**: INT4 bəzi task-da 5% drop — benchmark (MMLU, domain eval)

## Interview Sualları (Interview Q&A)

**S1: Dynamic batching nədir və niyə lazımdır?**
C: GPU parallel compute üçün dizayn olunub — 1 vs 32 request təqribən eyni vaxtda bitir. Batcher gələn request-ləri qısa pəncərədə (5-50ms) toplayır, tək GPU call göndərir, cavabları ayırır. Throughput 10-30× artır, amma queue latency 5-50ms əlavə olunur. Latency-sensitive path-da `max_wait_ms` aşağı tutulur.

**S2: Continuous batching niyə LLM üçün breakthrough oldu?**
C: Klassik batching batch-in hamısının eyni anda bitməsini gözləyir. LLM-də hər request fərqli token sayı generate edir — batch ən uzununu gözləyir, qısalar bitdikdən sonra GPU boş qalır. Continuous batching hər token addımından sonra bitəni çıxarır, yeni request əlavə edir. vLLM ilə GPU utilization 30% → 90%, throughput 10-20× artım.

**S3: KV cache və PagedAttention nədir?**
C: Transformer hər yeni token üçün əvvəlki Key/Value matris-lərini yenidən hesablamamaq üçün cache-ləyir (Llama-70B 2048 token ≈ 1.6 GB/request). Naive allocation fragmentation yaradır. PagedAttention OS virtual memory kimi fixed-size block-lara bölür, page table ilə idarə edir. Fragmentation < 4%, eyni GPU-da 2-4× çox concurrent request. Prefix cache system prompt-u paylaşır.

**S4: GPU-ları necə paylaşırıq çoxlu model arasında?**
C: Bir model bir GPU-nu doldurmur. Variantlar: (1) MIG — A100/H100 fiziki bölgü, hardware isolation; (2) MPS — paralel CUDA kernel, yumşaq isolation; (3) K8s time-slicing; (4) Multi-model Triton (CUDA stream paralel). Kritik path MIG, dev MPS. Utilization 20% → 70%, GPU cost 3× aşağı.

**S5: LLM serving-də sticky routing niyə vacibdir?**
C: User söhbətin davamını göndərir — system prompt + previous messages KV cache-də worker X-də hazırdır. Least-connections başqa worker-ə yönəltsə cache yenidən qurulmalıdır (saniyələr GPU israfı). Həll: conversation_id hash sticky, və ya prefix-aware routing (system prompt hash → cache sahibi). Cache hit 50-70% production-da.

**S6: GPU autoscaling niyə çətindir?**
C: Node boot 2-5 dəq + model endirmə 3-10 dəq + CUDA warm-up 30-60 san = 5-15 dəq total. "Pod aç" burst-ə çatmır, user 503 görür. Həll: (1) 20% pre-warmed buffer; (2) predictive scaling trafik profilinə görə; (3) queue + 429 Retry-After; (4) two-tier — burst-də kiçik modelə fallback. CPU service "HPA target 70%" burada işləmir.

**S7: Semantic cache necə işləyir, nə qədər qənaət?**
C: Query embedding-ini hesablayıb (5ms) vector DB-də cosine similarity top-1 axtarıram. > 0.95 → cached cavab. FAQ chatbot-da 30-50% hit, hər hit tam LLM inference qənaəti ($0.001-0.01). Risk: oxşar-amma-fərqli niyyətli sual-a yanlış cavab — threshold düzgün tune, medical/legal domain disable.

**S8: Speculative decoding necə LLM-i sürətləndirir?**
C: LLM inference memory-bandwidth-bound-dur — compute boşdur. Kiçik draft model (7B) N=4 token təklif edir, böyük (70B) hamısını tək forward pass-da verify edir. Hamısı doğrudursa 4 token 1 addımda hazır. Səhv olan ilk token-dən sonrası atılır. 2-3× speedup, output mathematically eynidir. OpenAI, Anthropic istifadə edir.

## Best Practices

- **Dynamic batching default aç** — Triton config-də, throughput 10× pulsuz
- **LLM üçün vLLM / TGI** — naive TorchServe LLM-də 5-10× aşağı throughput
- **Prefix KV cache reuse** — system prompt-ları eyni saxla
- **Quantization benchmark et** — INT4 bəzi task-da 5% drop, kor-koranə tətbiq etmə
- **GPU utilization monitor et** — < 30% resize down, > 85% scale up
- **Sticky routing chat üçün** — conversation_id hash, cache locality
- **Streaming SSE ilə** — first-token < 1s UX kritik
- **Pre-warmed pool** — cold start 5-15 dəq → buffer
- **Batch vs online ayır** — offline report-lar ayrı pool-da, online latency qoru
- **Model versioning strict** — v1/v2 hot-swap, rollback rahat
- **Canary + shadow deploy** — quality regression production-da tutulsun
- **Cost alert per endpoint** — GPU saatı budget, threshold alert
- **Semantic cache FAQ-da** — hit 30-50%, cost dramatic aşağı
- **Request coalescing** — identik eyni anda tək inference
- **Graceful degradation** — LLM down → kiçik model / cached fallback
- **Input validation before GPU** — max_tokens cap, DoS qarşısı
- **Rate limit per user / per model** — bahalı modelə kvota
- **Cross-reference**:
  - Fayl 69 — vector database (semantic cache, embeddings)
  - Fayl 70 — feature store (classical ML features)
  - Fayl 72 — deployment strategies (canary, shadow, blue-green)
  - Fayl 16 — logging/monitoring (GPU, latency, tokens/sec)
  - Fayl 57 — backpressure / load shedding (queue full, 429)
  - Fayl 03 — caching strategies (semantic cache üst səviyyə)
