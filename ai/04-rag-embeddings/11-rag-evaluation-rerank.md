# RAG Evaluation və Reranker Qiymətləndirməsi (Lead)

> **Oxucu kütləsi:** Senior developerlər, ML engineer-lər
> **Bu faylın 06-dan fərqi:** 06 — reranking və hybrid search mexanikası. Bu fayl — **RAG pipeline-ın EVAL**: RAGAS metrikləri (faithfulness, answer relevance, context precision/recall), retrieval metrikaları (MRR, NDCG, hit@k), reranker qiymətləndirməsi, synthetic eval generation, ablation study, Laravel ground-truth pinning.

---

## 1. RAG Eval-in Üç Səviyyəsi

RAG pipeline üç müstəqil mərhələdən ibarətdir — hər biri fərqli metriklə ölçülməlidir:

```
İstifadəçi sorğusu
      ↓
[1] RETRIEVAL  ───►  MRR, NDCG, hit@k, recall@k
      ↓
[2] RERANKING  ───►  NDCG uplift, MRR uplift, latency delta
      ↓
[3] GENERATION ───►  Faithfulness, answer relevance, hallucination rate
```

**Tək bir "RAG doğru işləyir?" metriki aldadıcıdır.** Aşağıdakıların hamısı fərqli səbəblərdən sistem zəif ola bilər:

- Retrieval düzgün doc gətirmir → MRR aşağıdır → cavab hallüsinasiyadır.
- Retrieval düzgündür, amma reranker mühüm chunk-ı siyahıdan çıxarır → context precision aşağıdır.
- Retrieval+rerank yaxşıdır, amma generator verilən konteksti ignore edib təxmin verir → faithfulness aşağıdır.

Hər qatı ayrıca ölçmək diaqnoz üçün şərtdir.

---

## 2. Retrieval Metriklər

### 2.1 hit@k (Recall@k)

Top-k nəticədə ən azı bir **relevant** sənəd varmı? İkili.

```
hit@5 = 1 əgər top-5-də ən azı bir relevant sənəd varsa, əks halda 0
```

Yaxşı başlanğıc metrikdir, amma "1 relevant vardı" — "amma 9-cu yerdə yox, 2-ci yerdə" fərqini tutmur.

### 2.2 MRR (Mean Reciprocal Rank)

İlk relevant sənədin reciprocal rank-ı:

```
RR = 1 / rank_of_first_relevant
MRR = mean(RR over all queries)
```

| rank | RR |
|------|-----|
| 1 | 1.00 |
| 2 | 0.50 |
| 3 | 0.33 |
| 5 | 0.20 |
| 10 | 0.10 |

MRR-in məhdudiyyəti: yalnız **birinci** relevant-ə baxır. Sorğuda 5 relevant sənəd olsa, yerdə qalan 4-ü ignore olunur.

### 2.3 NDCG (Normalized Discounted Cumulative Gain)

Graded relevance (0-3 ballıq) və position-weighted:

```
DCG@k = Σᵢ (2^rel_i - 1) / log₂(i + 1)
NDCG@k = DCG@k / IDCG@k
```

Burada `IDCG` — ideal sıralamada DCG. NDCG ∈ [0, 1]. 1.0 — mükəmməl sıralama.

**NDCG-nin üstünlüyü**: həm relevance gradation-ını (yüksək/orta/aşağı), həm də sırası vacibliyini nəzərə alır. Semantic search evalda standart metrikdir.

### 2.4 Precision / Recall @ k

```
Precision@k = (relevant ∩ retrieved_k) / k
Recall@k = (relevant ∩ retrieved_k) / total_relevant
```

Precision — "gətirilən 5-dən neçəsi doğrudur". Recall — "doğruların neçə faizini tapdım".

### 2.5 Kod: PHP-də Metriklər

```php
<?php
// app/Services/AI/Evals/RetrievalMetrics.php

namespace App\Services\AI\Evals;

class RetrievalMetrics
{
    /**
     * @param array $retrieved retrieved chunk ID-ləri sırası üzrə
     * @param array $relevant  [chunk_id => grade(0-3)] ground truth
     */
    public function hitAtK(array $retrieved, array $relevant, int $k): float
    {
        $topK = array_slice($retrieved, 0, $k);
        foreach ($topK as $id) {
            if (isset($relevant[$id]) && $relevant[$id] > 0) {
                return 1.0;
            }
        }
        return 0.0;
    }

    public function mrr(array $retrieved, array $relevant): float
    {
        foreach ($retrieved as $i => $id) {
            if (isset($relevant[$id]) && $relevant[$id] > 0) {
                return 1 / ($i + 1);
            }
        }
        return 0.0;
    }

    public function ndcgAtK(array $retrieved, array $relevant, int $k): float
    {
        $topK = array_slice($retrieved, 0, $k);

        // DCG
        $dcg = 0.0;
        foreach ($topK as $i => $id) {
            $rel = $relevant[$id] ?? 0;
            $dcg += (pow(2, $rel) - 1) / log($i + 2, 2);
        }

        // IDCG
        $grades = array_values($relevant);
        rsort($grades);
        $idealTopK = array_slice($grades, 0, $k);
        $idcg = 0.0;
        foreach ($idealTopK as $i => $rel) {
            $idcg += (pow(2, $rel) - 1) / log($i + 2, 2);
        }

        return $idcg > 0 ? $dcg / $idcg : 0.0;
    }
}
```

---

## 3. Generation Metriklər — RAGAS

RAGAS (Retrieval-Augmented Generation Assessment) — RAG-a xüsusi açıq eval framework. Python library, amma metrikaları hər dildə implement etmək olar.

### 3.1 Faithfulness (Sadiqlik)

Cavab yalnız verilən kontekstdən çıxır, hallüsinasiya yox. Ölçü metodu: cavabı atomik iddialara ayır, hər iddianın kontekst tərəfindən dəstəkləndiyini yoxla.

```
Faithfulness = (number of supported claims) / (total claims)
```

### 3.2 Answer Relevance (Cavab Relevantlığı)

Cavab istifadəçi sorğusuna cavab verirmi? (Kontekstdən asılı olmayaraq.)

Ölçü: LLM cavabdan 3 potensial sorğu yaradır, bu sorğuların original sorğu ilə embedding cosine similarity-ı.

### 3.3 Context Precision

Retrieved context-lərdən neçəsi həqiqətən istifadə olundu / relevant idi?

```
Context Precision = (relevant chunks in top-k) / k
```

Çox shum və noise-ı aşkar edir.

### 3.4 Context Recall

Ground-truth cavabındakı fakt-ların neçə faizi retrieved context-də var?

```
Context Recall = (facts in context ∩ facts in ground truth) / facts in ground truth
```

Missing context-i aşkar edir.

### 3.5 Context Relevance

Retrieved chunk-ların neçə faizi sorğuya uyğundur? (graded, not binary)

### Laravel RAGAS Implementation Skeleton

```php
<?php
// app/Services/AI/Evals/Ragas.php

namespace App\Services\AI\Evals;

use Anthropic\Anthropic;

class Ragas
{
    public function __construct(private Anthropic $client) {}

    public function faithfulness(string $question, string $answer, array $contexts): float
    {
        // Step 1: cavabdan atomik iddialar çıxar
        $claims = $this->extractClaims($question, $answer);

        if (empty($claims)) return 1.0;

        // Step 2: hər iddianı kontekstdə axtar
        $ctx = implode("\n---\n", $contexts);
        $supported = 0;
        foreach ($claims as $claim) {
            if ($this->isSupported($claim, $ctx)) {
                $supported++;
            }
        }

        return $supported / count($claims);
    }

    public function answerRelevance(string $question, string $answer): float
    {
        // 3 potensial sual yarat
        $response = $this->client->messages()->create([
            'model' => 'claude-haiku-4-5-20251001',
            'max_tokens' => 300,
            'messages' => [[
                'role' => 'user',
                'content' => "Given this answer, generate 3 plausible questions that would lead to it. Return as JSON array.\n\nAnswer: {$answer}",
            ]],
        ]);

        $generated = json_decode($response->content[0]->text, true);

        // Embedding similarity
        $origEmb = $this->embed($question);
        $sims = [];
        foreach ($generated as $q) {
            $qEmb = $this->embed($q);
            $sims[] = $this->cosine($origEmb, $qEmb);
        }

        return array_sum($sims) / count($sims);
    }

    public function contextPrecision(string $question, array $contexts, string $groundTruth): float
    {
        $relevant = 0;
        foreach ($contexts as $i => $ctx) {
            // LLM-judge: bu ctx ground truth cavabına kömək edirmi?
            $useful = $this->isUseful($question, $ctx, $groundTruth);
            if ($useful) $relevant++;
        }
        return count($contexts) > 0 ? $relevant / count($contexts) : 0.0;
    }

    private function extractClaims(string $question, string $answer): array
    {
        $response = $this->client->messages()->create([
            'model' => 'claude-haiku-4-5-20251001',
            'max_tokens' => 512,
            'messages' => [[
                'role' => 'user',
                'content' => "Extract atomic factual claims from this answer as a JSON array of strings.\n\nQuestion: {$question}\nAnswer: {$answer}",
            ]],
        ]);
        return json_decode($response->content[0]->text, true) ?? [];
    }

    private function isSupported(string $claim, string $context): bool
    {
        $response = $this->client->messages()->create([
            'model' => 'claude-haiku-4-5-20251001',
            'max_tokens' => 50,
            'messages' => [[
                'role' => 'user',
                'content' => "Is this claim supported by the context? Answer only YES or NO.\n\nClaim: {$claim}\nContext: {$context}",
            ]],
        ]);
        return str_starts_with(trim($response->content[0]->text), 'YES');
    }

    private function isUseful(string $question, string $context, string $groundTruth): bool
    {
        $response = $this->client->messages()->create([
            'model' => 'claude-haiku-4-5-20251001',
            'max_tokens' => 50,
            'messages' => [[
                'role' => 'user',
                'content' => "Is this context useful for answering the question with the given ground truth? Answer YES or NO.\n\nQ: {$question}\nCtx: {$context}\nGT: {$groundTruth}",
            ]],
        ]);
        return str_starts_with(trim($response->content[0]->text), 'YES');
    }

    private function embed(string $text): array { /* Voyage / Anthropic Embedding API */ return []; }
    private function cosine(array $a, array $b): float { /* ... */ return 0.0; }
}
```

---

## 4. Synthetic Eval Generation

Manual dataset yaratmaq bahalıdır. LLM ilə sintetik dataset generasiya edin:

```php
<?php
// app/Services/AI/Evals/SyntheticEvalGenerator.php

namespace App\Services\AI\Evals;

use Anthropic\Anthropic;
use App\Models\Document;

class SyntheticEvalGenerator
{
    public function __construct(private Anthropic $client) {}

    /**
     * Document-dən 3-5 sual/cavab cütü yarat.
     */
    public function generateQAPairs(Document $doc): array
    {
        $response = $this->client->messages()->create([
            'model' => 'claude-sonnet-4-6',
            'max_tokens' => 1500,
            'system' => 'Generate high-quality QA pairs for RAG evaluation. Include: (1) specific-fact questions, (2) multi-hop questions requiring synthesis, (3) questions that cannot be answered from this doc (for negative tests). Return JSON array.',
            'messages' => [[
                'role' => 'user',
                'content' => "Document:\n{$doc->content}\n\nGenerate 5 QA pairs. JSON schema: [{\"question\": \"\", \"answer\": \"\", \"type\": \"specific|multihop|negative\", \"supporting_chunks\": [...]}]",
            ]],
        ]);

        $pairs = json_decode($response->content[0]->text, true) ?? [];

        // Negative case-lər kontrol üçün — doc-da cavab yoxdursa retriever aşağı ranklamalıdır
        return array_map(fn ($p) => [...$p, 'source_doc_id' => $doc->id], $pairs);
    }
}
```

### Synthetic Dataset-in Limitləri

1. **LLM bias** — generasiya edən model öz güclü tərəflərini ölçəcək, zəif tərəflərini deyil.
2. **Distribution shift** — sintetik sual real istifadəçi sualından fərqlənir (daha formal, daha ardıcıl).
3. **Negative case-lərin qüsurlu olması** — "cavablanmaz" sual generasiya çətin.

Mitigation: **70% sintetik + 30% manual** seed dataset.

---

## 5. Reranker Evaluation

Reranker faydalıdırmı? Onu **uplift** olaraq ölçün: metrics_with_reranker − metrics_without_reranker.

```php
<?php
// app/Console/Commands/EvaluateReranker.php

namespace App\Console\Commands;

use App\Models\EvalQuery;
use App\Services\AI\Evals\RetrievalMetrics;
use App\Services\AI\Retrieval\VectorSearch;
use App\Services\AI\Retrieval\Reranker;
use Illuminate\Console\Command;

class EvaluateReranker extends Command
{
    protected $signature = 'ai:eval-reranker {--top-k=20} {--rerank-to=5}';

    public function handle(
        VectorSearch $search,
        Reranker $reranker,
        RetrievalMetrics $metrics,
    ): int {
        $queries = EvalQuery::with('relevantChunks')->get();

        $withoutReranker = [];
        $withReranker = [];

        foreach ($queries as $q) {
            $relevant = $q->relevantChunks->pluck('grade', 'chunk_id')->toArray();

            // Baseline: sadəcə vector search
            $vecResults = $search->search($q->text, limit: $this->option('top-k'));
            $vecIds = array_map(fn ($r) => $r->chunk_id, $vecResults);

            $withoutReranker[] = $metrics->ndcgAtK($vecIds, $relevant, k: 5);

            // With reranker
            $reranked = $reranker->rerank($q->text, $vecResults);
            $rerankedTop = array_slice($reranked, 0, $this->option('rerank-to'));
            $rerankedIds = array_map(fn ($r) => $r->chunk_id, $rerankedTop);

            $withReranker[] = $metrics->ndcgAtK($rerankedIds, $relevant, k: 5);
        }

        $meanWithout = array_sum($withoutReranker) / count($withoutReranker);
        $meanWith = array_sum($withReranker) / count($withReranker);
        $uplift = (($meanWith - $meanWithout) / $meanWithout) * 100;

        $this->table(
            ['Setup', 'NDCG@5'],
            [
                ['Vector only', sprintf('%.3f', $meanWithout)],
                ['Vector + Reranker', sprintf('%.3f', $meanWith)],
                ['Uplift', sprintf('%+.1f%%', $uplift)],
            ]
        );

        return 0;
    }
}
```

### Tipik Uplift Gözləntiləri

| Reranker | Tipik NDCG@5 Uplift | Latency əlavəsi |
|----------|---------------------|-----------------|
| Cohere Rerank 3 | +15-25% | +80-150 ms |
| Voyage Rerank 2 | +12-20% | +60-120 ms |
| Jina Rerank | +10-18% | +50-100 ms |
| Open cross-encoder (bge-reranker-v2) | +8-15% | +30-80 ms self-hosted |

Uplift baseline-a çox asılıdır. Baseline retrieval yaxşı olarsa, reranker uplift-i azalır.

---

## 6. End-to-End RAG Eval Pipeline

```php
<?php
// app/Console/Commands/RagEndToEndEval.php

namespace App\Console\Commands;

use App\Models\EvalQuery;
use App\Services\AI\Evals\Ragas;
use App\Services\AI\Evals\RetrievalMetrics;
use App\Services\AI\RagPipeline;
use Illuminate\Console\Command;

class RagEndToEndEval extends Command
{
    protected $signature = 'ai:eval-rag {--set=regression}';

    public function handle(
        RagPipeline $rag,
        Ragas $ragas,
        RetrievalMetrics $rmetrics,
    ): int {
        $queries = EvalQuery::where('eval_set', $this->option('set'))->get();

        $results = [];

        foreach ($queries as $q) {
            $trace = $rag->run($q->text);

            // Retrieval
            $retrievedIds = collect($trace->retrieved)->pluck('id')->toArray();
            $relevantGraded = $q->relevantChunks->pluck('grade', 'chunk_id')->toArray();

            // Generation
            $faith = $ragas->faithfulness($q->text, $trace->answer, $trace->contextTexts);
            $relev = $ragas->answerRelevance($q->text, $trace->answer);
            $ctxPrec = $ragas->contextPrecision($q->text, $trace->contextTexts, $q->ground_truth);

            $results[] = [
                'query' => substr($q->text, 0, 40),
                'hit@5' => $rmetrics->hitAtK($retrievedIds, $relevantGraded, 5),
                'mrr' => $rmetrics->mrr($retrievedIds, $relevantGraded),
                'ndcg@5' => $rmetrics->ndcgAtK($retrievedIds, $relevantGraded, 5),
                'faith' => $faith,
                'relev' => $relev,
                'ctx_prec' => $ctxPrec,
            ];
        }

        $this->printAggregated($results);
        $this->saveToFile($results);

        return 0;
    }
}
```

---

## 7. Ablation Studies: Chunk Size, Embedding Model, Reranker

Tək komponentin hansı həqiqətən kömək etdiyini bilmək üçün **ablation** — bir dəyişəni müxtəlif dəyərlərdə yoxla, qalanlarını sabit saxla.

### 7.1 Chunk Size Ablation

| Chunk size | Overlap | NDCG@5 | Faithfulness | Latency (p50) |
|------------|---------|--------|--------------|---------------|
| 256 tok | 20% | 0.71 | 0.82 | 180 ms |
| 512 tok | 20% | 0.78 | 0.85 | 190 ms |
| 1024 tok | 20% | 0.74 | 0.79 | 210 ms |
| 2048 tok | 20% | 0.68 | 0.73 | 260 ms |

Bu tipik pattern: kiçik chunk precision-da qalib, böyük chunk recall-də qalib, orta-512 ən yaxşı kompromis. **Öz dataset-inizdə yoxlayın** — heç bir tək dəyər hər domain üçün optimaldır.

### 7.2 Embedding Model Ablation

| Model | Dimensions | NDCG@5 | Cost/1M tok | Latency |
|-------|-----------|--------|-------------|---------|
| voyage-3 | 1024 | 0.82 | $0.12 | 25 ms |
| voyage-3-large | 2048 | 0.85 | $0.18 | 45 ms |
| OpenAI text-embedding-3-large | 3072 | 0.79 | $0.13 | 40 ms |
| BGE-M3 (self-host) | 1024 | 0.76 | ≈$0 | 15 ms |

### 7.3 Reranker Ablation

| Reranker | NDCG@5 | Uplift | Latency | Cost/1K pairs |
|----------|--------|--------|---------|---------------|
| None | 0.78 | — | 0 ms | $0 |
| Cohere 3 | 0.92 | +18% | +110 ms | $2.00 |
| Voyage 2 | 0.89 | +14% | +80 ms | $0.50 |
| bge-reranker (self) | 0.86 | +10% | +40 ms | ≈$0 |

---

## 8. Ground-Truth Pinning (Laravel)

Dataset stable olmalıdır — `Document` dəyişirsə, eval nəticəsi dəyişməməlidir.

```php
<?php
// database/migrations/create_eval_queries_table.php

Schema::create('eval_queries', function (Blueprint $table) {
    $table->id();
    $table->string('eval_set');
    $table->text('text');                        // sorğu
    $table->text('ground_truth');                // reference answer
    $table->timestamps();
});

Schema::create('eval_relevant_chunks', function (Blueprint $table) {
    $table->id();
    $table->foreignId('eval_query_id')->constrained()->cascadeOnDelete();
    $table->string('chunk_id');
    $table->text('chunk_content_snapshot');      // chunk content-in o an snapshoot
    $table->unsignedTinyInteger('grade');        // 0=not relevant, 1=partial, 2=relevant, 3=perfect
    $table->string('pinned_version');            // 'v1.2-2026-04'
    $table->timestamps();
    $table->index(['eval_query_id', 'grade']);
});
```

Chunk content snapshot saxlamaq vacibdir — production-da chunk yenilənsə də, eval history stabil qalmalıdır.

### Pinning Command

```php
<?php
// app/Console/Commands/PinGroundTruth.php

class PinGroundTruth extends Command
{
    protected $signature = 'ai:pin-ground-truth {version}';

    public function handle(): int
    {
        $version = $this->argument('version');

        EvalRelevantChunk::whereNull('pinned_version')
            ->chunkById(100, function ($chunks) use ($version) {
                foreach ($chunks as $rel) {
                    $currentChunk = Chunk::find($rel->chunk_id);
                    $rel->chunk_content_snapshot = $currentChunk?->content ?? '[MISSING]';
                    $rel->pinned_version = $version;
                    $rel->save();
                }
            });

        $this->info("Pinned ground truth to version {$version}");
        return 0;
    }
}
```

---

## 9. A/B Test: RAG Pipeline Variant

Production-da iki pipeline variant-ı paralel işə sal:

```php
<?php
// app/Services/AI/RagAbRouter.php

namespace App\Services\AI;

class RagAbRouter
{
    public function __construct(
        private RagPipelineV1 $v1,
        private RagPipelineV2 $v2,
        private FeatureFlags $flags,
    ) {}

    public function run(string $query, int $userId): RagTrace
    {
        $variant = $this->flags->assign('rag-v2-rollout', $userId);

        $trace = match ($variant) {
            'v1' => $this->v1->run($query),
            'v2' => $this->v2->run($query),
        };

        $trace->variant = $variant;

        // Background-da evals işlə
        dispatch(new ScoreRagTrace($trace));

        return $trace;
    }
}
```

Sonra dashboard-da v1 vs v2 NDCG, faithfulness, latency, cost müqayisəsi.

---

## 10. Continuous Eval: Production Trafikdən Öyrənmə

Eval dataset daimi böyüyməlidir:

```php
<?php
// app/Listeners/CaptureLowConfidenceTrace.php

namespace App\Listeners;

use App\Events\RagQueryCompleted;
use App\Models\EvalQuery;

class CaptureLowConfidenceTrace
{
    public function handle(RagQueryCompleted $event): void
    {
        $trace = $event->trace;

        // İstifadəçi thumbs-down bildirsə
        if ($event->userFeedback === 'thumbs_down') {
            $this->promote($trace, tag: 'user-feedback-negative');
        }

        // Retrieval score aşağı olsa
        if ($trace->topScore < 0.6) {
            $this->promote($trace, tag: 'low-retrieval-score');
        }

        // Faithfulness aşağı olsa (async scored)
        if ($trace->faithfulness && $trace->faithfulness < 0.7) {
            $this->promote($trace, tag: 'low-faithfulness');
        }
    }

    private function promote($trace, string $tag): void
    {
        EvalQuery::updateOrCreate(
            ['source_trace_id' => $trace->id],
            [
                'eval_set' => 'production-capture',
                'text' => $trace->query,
                'ground_truth' => null, // human review lazımdır
                'tags' => [$tag],
            ]
        );
    }
}
```

Human review queue-ya gedir — editor ground truth markup edir.

---

## 11. Eval Cost Budget

| Komponent | Nümunə dataset (200 sorğu) | Qiymət |
|-----------|---------------------------|--------|
| Retrieval (yalnız vector query) | 200 × ~200 ms | ~$0 (öz DB) |
| Reranker | 200 × 20 chunks × Cohere | $0.8 |
| Generation | 200 × ~3K tok × Sonnet | $6.0 |
| RAGAS faithfulness (judge) | 200 × ~5 claims × 2 LLM calls × Haiku | $2.0 |
| RAGAS relevance (judge + embed) | 200 × 3 gen + embeddings | $1.5 |
| **Total per run** | | **~$10** |

PR-də slice 50 sample ilə $2.5/PR. Nightly full run $10/gecə.

---

## 12. Dashboards və Alerting

### Metric-lər

- **Retrieval health**: MRR, NDCG@5, hit@5 — production sample 1%.
- **Generation quality**: faithfulness p50, answer relevance p50.
- **Latency breakdown**: retrieval / rerank / generation (hər biri p50, p95).
- **Cost breakdown**: embedding / rerank / generation.
- **Negative feedback rate**: thumbs-down per 1000 query.

### Alert-lər

- MRR 7-gün baseline-dan 10%+ aşağı → Slack alert
- Faithfulness < 0.7 gündəlik orta → high-priority investigate
- Top-k empty rate > 2% → retrieval index corruption
- Rerank p99 > 500 ms → backend issue

---

## 13. Müsahibə Xülasəsi

- **RAG-da üç qat eval**: retrieval (MRR/NDCG/hit@k), reranker (uplift), generation (faithfulness/relevance).
- **NDCG** ən nuanslı retrieval metrikdir — graded relevance + position weighting.
- **MRR** yalnız ilk relevant nəticəni ölçür — sadə, amma partial.
- **RAGAS metrikləri**: faithfulness (halüsinasiya yox), answer relevance (soruğa uyğun), context precision (noise aşağı), context recall (fakt tamlığı).
- **Reranker uplift** həqiqi faydanı ölçür: tipik NDCG@5 +10-25%, latency +40-150 ms.
- **Synthetic eval generation**: sürətli dataset, amma LLM bias; 70/30 sintetik/manual balans.
- **Ablation**: chunk size (512 tok tipik), embedding model (voyage-3-large, BGE-M3), reranker (Cohere > Voyage > self-host bge).
- **Ground-truth pinning**: chunk content snapshot + versioning — eval tarixdə stable.
- **Continuous capture**: thumbs-down + low-confidence + low-faithfulness → human review queue.
- **Cost budget**: ~$10/200-sample tam run, slice CI-də $2-3.
- **Alerting**: MRR deklin, faithfulness trend, empty retrieval, rerank latency spike.
