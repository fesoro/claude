# Reranking və Hibrid Axtarış: Sadə Vektor Axtarışından Kənara Çıxmaq

## Saf Vektor Axtarışının Problemi

Vektor (semantik) axtarış güclüdür, lakin sistemli uğursuzluq halları mövcuddur:

### 1. Lüğət Uyuşmazlığı (Recall Problemi)

İstifadəçi soruşur: **"Geri ödəmə siyasəti nədir?"**  
Sənəddə yazılıb: **"İstifadə edilməmiş abunəliklər üçün 30 gün ərzində pul geri qaytarılır."**

Sözlər üst-üstə düşmür, amma məna eynidir. Vektor axtarışı bunu yaxşı işləyir.

İndi tərsinə çevirin: **"Pul geri qaytarma müddəti nə qədərdir?"**  
Sənəddə yazılıb: **"Geri ödəmə siyasəti: 30 gün."**

Yenə də işləyir. Vektor axtarışı bunda yaxşıdır.

### 2. Dəqiq Uyğunluq Uğursuzluğu (Precision Problemi)

İstifadəçi soruşur: **"E-7834 xəta kodu"**  
Sənədlər: Ümumi xəta idarəetmə bələdçisi və E-7834-ü xüsusi olaraq qeyd edən sənəd.

Vektor axtarışı ümumi bələdçini daha yüksək sıralaya bilər, çünki "xəta idarəetmə" semantik cəhətdən daha zəngindir. BM25 (açar söz axtarışı) isə xüsusi sənədi birinci sıralayar, çünki orada "E-7834" dəqiq termi var.

### 3. Bi-encoder Məhdudiyyətləri

Embedding modelləri **bi-encoder**dır: sorğu və sənədi müstəqil olaraq kodlayırlar. Embedding, sorğunun nə olduğunu "bilmir" — sənədi yalnız ayrılıqda kodlayır. Cross-encoder-lər (reranking-də istifadə olunur) sorğu və sənədi birlikdə emal edir, bu da çox daha zəngin müqayisə imkanı verir.

---

## BM25: Açar Söz Axtarışının Əsası

BM25 (Best Match 25) — Elasticsearch, Solr və PostgreSQL-in tam mətn axtarışı tərəfindən istifadə edilən sənaye standartı termin-tezlik reytinq funksiyasıdır.

### Formula

```
BM25(D, Q) = Σᵢ IDF(qᵢ) × (f(qᵢ,D) × (k₁+1)) / (f(qᵢ,D) + k₁×(1 - b + b×|D|/avgDL))
```

Burada:
- `f(qᵢ, D)` = D sənədindəki i sorğu termininin tezliyi
- `|D|` = sənədin söz uzunluğu
- `avgDL` = orta sənəd uzunluğu
- `k₁` = termin tezliyi doyma parametri (standart 1.2). Terminin əlavə meydana çıxmalarının balı necə artırdığını idarə edir. Yüksək k₁: daha çox meydana çıxma = daha yüksək bal. k₁=0: ikili (termin var/yox)
- `b` = uzunluq normallaşdırma parametri (standart 0.75). b=1: tam normallaşdırma. b=0: uzunluq normallaşdırması yoxdur
- `IDF(qᵢ)` = Tərs Sənəd Tezliyi = log((N - n(qᵢ) + 0.5) / (n(qᵢ) + 0.5) + 1)

### IDF-in Məntiqi

IDF ümumi sözlərə cəza verir. "the", "is", "a" demək olar ki, hər sənəddə görünür → sıfıra yaxın IDF. "pgvector", "HNSW", "cosine" isə az sənəddə görünür → yüksək IDF. Bu, reytinqi fərqli terminlərə yönəldir.

### BM25 vs TF-IDF vs Kosinus Oxşarlığı

| Aspekt | BM25 | TF-IDF | Kosinus Oxşarlığı |
|--------|------|--------|-------------------|
| Termin doyması | Bəli (k₁ parametri) | Xeyr (məhdudiyyətsiz) | Xeyr |
| Uzunluq normallaşdırması | Qismən (b parametri) | Yoxdur | Tam (vahid vektorlar) |
| Semantik uyğunluq | Xeyr | Xeyr | Bəli |
| Dəqiq açar söz uyğunluğu | Əla | Yaxşı | Zəif |

---

## Reciprocal Rank Fusion (RRF)

RRF — bir neçə axtarış sistemindən alınan reytinqləri birləşdirmək üçün standart alqoritmdir. Əsas çətinliyi həll edir: müxtəlif sistemlərin balları müqayisəli deyil (kosinus oxşarlığı 0.85 vs BM25 balı 12.4 — hansı daha yüksəkdir?).

### Formula

```
RRF(d) = Σ_r ∈ R  1 / (k + r(d))
```

Burada:
- `R` = axtarış sistemlərinin çoxluğu (məs., {BM25, vector_search})
- `r(d)` = r sistemindəki d sənədinin reytinqi (1-dən başlayaraq)
- `k` = hamarlaşdırma sabiti (standart 60) — çox yüksək reytinqlərin təsirini azaldır

### RRF-in İşləmə Səbəbi

RRF mütləq balları (sistemlər arasında müqayisəli deyil) reytinqlərə (hamı üçün müqayisəli) çevirir. Hamarlaşdırma sabiti k, birinci sıradakı sənədin hökmranlığını önləyir — 1-ci sıra ilə 2-ci sıra arasındakı fərq 1/(60+1) - 1/(60+2) ≈ 0.0003-dür, çox böyük deyil.

### RRF Nümunəsi

| Sənəd | BM25 Reytinqi | Vektor Reytinqi | RRF Balı |
|-------|---------------|-----------------|----------|
| Doc A | 1 | 5 | 1/61 + 1/65 = 0.0164 + 0.0154 = 0.0318 |
| Doc B | 3 | 1 | 1/63 + 1/61 = 0.0159 + 0.0164 = 0.0323 |
| Doc C | 2 | 10 | 1/62 + 1/70 = 0.0161 + 0.0143 = 0.0304 |

Doc B, BM25-də 3-cü sırada olmasına baxmayaraq qalib gəlir — hər iki sistemdə ardıcıl olaraq yuxarı nəticələrdədir.

---

## Cross-Encoder-lər və Reranking

### Bi-encoder vs Cross-encoder

**Bi-encoder** (embedding modeli):
```
Sorğu → Encoder → Sorğu Vektoru
Sənəd → Encoder → Sənəd Vektoru
Oxşarlıq = cosine(Sorğu Vektoru, Sənəd Vektoru)
```

Sorğu və sənəd müstəqil kodlanır. Sürətlidir (sənəd vektorlarını əvvəlcədən hesablamaq mümkündür). Dəqiqliyi azdır.

**Cross-encoder** (reranker):
```
[Sorğu + Sənəd] → Encoder → Uyğunluq Balı
```

Sorğu və sənəd birlikdə transformer vasitəsilə ötürülür. Diqqət mexanizmi sorğu və sənəd tokenləri arasındakı qarşılıqlı təsirləri görür. Çox daha dəqiqdir. Əvvəlcədən hesablamaq mümkün deyil — hər namizəd üçün sorğu zamanı işə salınmalıdır.

### Reranking Nümunəsi

```
Addım 1: Geniş əldə etmə (sürətli, recall-yönümlü)
  Vektor axtarışı → ən yaxşı 50-100 namizəd
  
Addım 2: Reranking (daha yavaş, precision-yönümlü)
  Cross-encoder bütün namizədləri qiymətləndirir
  Reranker balına görə ən yaxşı 5-10-u qaytarır
```

Bu iki mərhələli yanaşma sizə verir:
- **Sürət**: Yalnız 50 namizəd bahalı cross-encoder-dən keçir
- **Keyfiyyət**: Cross-encoder tam kontekstlə son uyğunluq qərarını verir

---

## Sorğu Genişləndirilməsi və HyDE

### Sorğu Genişləndirilməsi

Embedding-dən əvvəl orijinal sorğunu əlaqəli terminlərlə genişləndirin:

```
Orijinal: "Python xəta idarəetmə"
Genişləndirilmiş: "Python error handling exception try catch raise except finally"
```

Bu, sorğu vektorunun daha çox uyğun lüğəti əhatə etməsini təmin edərək recall-ı yaxşılaşdırır.

### HyDE (Hipotetik Sənəd Embedding-ləri)

Sorğunu birbaşa embedding etmək əvəzinə, sorğuya cavab verəcək hipotetik sənəd yaradın, sonra həmin sənədi embed edin.

**Niyə işləyir**: "Python-da yaddaş sızıntılarına nə səbəb olur?" sorğusu qısa və seyrəkdir. Zibil toplanması, istinad dövrləri və del operatorları haqqında hipotetik cavab paraqrafı, yaddaş idarəetməsi haqqında faktiki Python sənədlərinin embedding fəzasına çox daha yaxındır.

**Mübadilə**: Hər sorğu üçün bir LLM çağırışı əlavə edir (gecikmə + xərc). Mürəkkəb sorğular üçün dəyər, sadə faktiki axtarışlar üçün isə deyil.

---

## Laravel Tətbiqi

### 1. PostgreSQL ilə BM25 Tam Mətn Axtarışı

```php
<?php

namespace App\Services\RAG;

use App\Models\KnowledgeChunk;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class FullTextSearchService
{
    /**
     * PostgreSQL-in ts_rank_cd funksiyasından istifadə edərək BM25-stilli tam mətn axtarışı.
     * PostgreSQL BM25-ə oxşar reytinq alqoritmindən istifadə edir.
     *
     * @param string $query Axtarış sorğusu
     * @param int $limit Nəticə sayı
     * @param float $minRank Minimum reytinq balı
     */
    public function search(
        string $query,
        int $limit = 50,
        float $minRank = 0.01,
    ): Collection {
        // Sorğunu tsquery formatına çevirin
        // plainto_tsquery təbii dili işləyir (operatorlar tələb olunmur)
        // to_tsquery AND/OR/NOT operatorlarına icazə verir
        $tsQuery = $this->buildTsQuery($query);

        return KnowledgeChunk::query()
            ->selectRaw(<<<SQL
                knowledge_chunks.*,
                ts_rank_cd(
                    to_tsvector('english', content),
                    to_tsquery('english', ?),
                    32  -- normallaşdırma: reytinqi sənəd uzunluğuna bölün
                ) as bm25_score
            SQL, [$tsQuery])
            ->join('knowledge_documents', 'knowledge_documents.id', '=', 'knowledge_chunks.document_id')
            ->where('knowledge_documents.status', 'indexed')
            ->whereRaw(
                "to_tsvector('english', knowledge_chunks.content) @@ to_tsquery('english', ?)",
                [$tsQuery]
            )
            ->having('bm25_score', '>=', $minRank)
            ->orderByDesc('bm25_score')
            ->limit($limit)
            ->get();
    }

    /**
     * Təbii dil sorğusunu PostgreSQL tsquery-yə çevirin.
     * Fraza sorğularını və boolean operatorlarını idarə edir.
     */
    private function buildTsQuery(string $query): string
    {
        // Xüsusi simvolları silin
        $cleaned = preg_replace('/[^a-zA-Z0-9\s\-]/', '', $query);

        // Tokenizə edin və kökə endirin
        $words = array_filter(
            explode(' ', strtolower(trim($cleaned))),
            fn($word) => strlen($word) > 2 && !in_array($word, $this->getStopWords())
        );

        if (empty($words)) {
            return plainto_tsquery($query); // ehtiyat variant
        }

        // Hibrid axtarışda maksimum recall üçün OR ilə birləşdirin
        // Dəqiqlik yönümlü FTS üçün & (AND) istifadə edin
        return implode(' | ', array_map(
            fn($word) => $word . ':*', // Prefiks uyğunluğu: "refund" "refunds"-a uyğun gəlir
            $words
        ));
    }

    private function getStopWords(): array
    {
        return ['the', 'is', 'at', 'which', 'on', 'a', 'an', 'and', 'or', 'but', 'in', 'with', 'to', 'for'];
    }
}
```

### 2. RRF ilə Hibrid Axtarış

```php
<?php

namespace App\Services\RAG;

use App\Services\AI\EmbeddingService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class HybridSearchService
{
    private const RRF_K = 60; // Standart RRF hamarlaşdırma sabiti

    public function __construct(
        private EmbeddingService $embeddingService,
        private FullTextSearchService $fullTextService,
    ) {}

    /**
     * Hibrid axtarış: RRF vasitəsilə BM25 + vektor axtarışını birləşdirin.
     *
     * @param string $query İstifadəçi sorğusu
     * @param int $topK Qaytarılacaq son nəticə sayı
     * @param int $candidateK Hər metoddan alınacaq namizəd sayı
     * @param float $vectorWeight Vektor reytinqi üçün çəki (0-1)
     * @param float $bm25Weight BM25 reytinqi üçün çəki (0-1)
     */
    public function search(
        string $query,
        int $topK = 10,
        int $candidateK = 50,
        float $vectorWeight = 0.7,
        float $bm25Weight = 0.3,
    ): Collection {
        // Hər iki axtarışı paralel işlədin (həqiqi paralellik üçün asinxron tələb olunur)
        $vectorResults = $this->vectorSearch($query, $candidateK);
        $bm25Results = $this->fullTextService->search($query, $candidateK);

        // RRF vasitəsilə birləşdirin
        return $this->reciprocalRankFusion(
            $vectorResults,
            $bm25Results,
            $topK,
            $vectorWeight,
            $bm25Weight,
        );
    }

    /**
     * Saf vektor axtarışı, sıralanmış nəticələr qaytarır.
     */
    private function vectorSearch(string $query, int $limit): Collection
    {
        $queryVector = $this->embeddingService->embed($query);
        $vectorString = '[' . implode(',', $queryVector) . ']';

        return DB::table('knowledge_chunks')
            ->selectRaw(<<<SQL
                knowledge_chunks.id,
                knowledge_chunks.content,
                knowledge_chunks.metadata,
                knowledge_chunks.document_id,
                knowledge_chunks.chunk_index,
                1 - (embedding <=> ?) as vector_score
            SQL, [$vectorString])
            ->join('knowledge_documents', 'knowledge_documents.id', '=', 'knowledge_chunks.document_id')
            ->where('knowledge_documents.status', 'indexed')
            ->whereNotNull('knowledge_chunks.embedding')
            ->orderByRaw('embedding <=> ?', [$vectorString])
            ->limit($limit)
            ->get();
    }

    /**
     * Reciprocal Rank Fusion tətbiqi.
     */
    private function reciprocalRankFusion(
        Collection $vectorResults,
        Collection $bm25Results,
        int $topK,
        float $vectorWeight,
        float $bm25Weight,
    ): Collection {
        $scores = [];

        // Vektor axtarışından bal
        foreach ($vectorResults as $rank => $result) {
            $id = $result->id;
            $scores[$id] = ($scores[$id] ?? 0) + $vectorWeight / (self::RRF_K + $rank + 1);

            // Nəticə məlumatlarını saxlayın
            if (!isset($resultData[$id])) {
                $resultData[$id] = [
                    'id' => $id,
                    'content' => $result->content,
                    'metadata' => is_string($result->metadata) ? json_decode($result->metadata, true) : $result->metadata,
                    'document_id' => $result->document_id,
                    'vector_score' => $result->vector_score,
                    'bm25_score' => 0,
                    'vector_rank' => $rank + 1,
                    'bm25_rank' => null,
                ];
            }
        }

        // BM25 axtarışından bal
        foreach ($bm25Results as $rank => $result) {
            $id = $result->id;
            $scores[$id] = ($scores[$id] ?? 0) + $bm25Weight / (self::RRF_K + $rank + 1);

            if (!isset($resultData[$id])) {
                $resultData[$id] = [
                    'id' => $id,
                    'content' => $result->content,
                    'metadata' => is_string($result->metadata) ? json_decode($result->metadata, true) : $result->metadata,
                    'document_id' => $result->document_id,
                    'vector_score' => 0,
                    'bm25_score' => $result->bm25_score,
                    'vector_rank' => null,
                    'bm25_rank' => $rank + 1,
                ];
            } else {
                $resultData[$id]['bm25_score'] = $result->bm25_score;
                $resultData[$id]['bm25_rank'] = $rank + 1;
            }
        }

        // Birləşdirilmiş RRF balına görə sıralayın
        arsort($scores);

        return collect(array_slice(array_keys($scores), 0, $topK))
            ->map(fn($id) => array_merge($resultData[$id], [
                'rrf_score' => round($scores[$id], 6),
            ]));
    }
}
```

### 3. Cohere Rerank API

```php
<?php

namespace App\Services\RAG;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class RerankingService
{
    private const COHERE_RERANK_MODEL = 'rerank-english-v3.0';
    private const MAX_CHUNKS_PER_REQUEST = 1000;

    /**
     * Cohere-in cross-encoder-i vasitəsilə namizədləri yenidən sıralayın.
     *
     * @param string $query Orijinal istifadəçi sorğusu
     * @param Collection $candidates İlkin əldə etmədən alınan parçalar
     * @param int $topN Yenidən sıralamadan sonra qaytarılacaq say
     */
    public function rerank(
        string $query,
        Collection $candidates,
        int $topN = 5,
    ): Collection {
        if ($candidates->isEmpty()) {
            return $candidates;
        }

        $documents = $candidates->pluck('content')->toArray();

        $response = Http::withToken(config('services.cohere.key'))
            ->timeout(30)
            ->post('https://api.cohere.ai/v2/rerank', [
                'model' => self::COHERE_RERANK_MODEL,
                'query' => $query,
                'documents' => $documents,
                'top_n' => $topN,
                'return_documents' => false, // Sənədlər artıq bizde var
            ]);

        if ($response->failed()) {
            // Zərif deqradasiya: reranking uğursuz olarsa orijinal sıranı qaytarın
            \Log::warning('Cohere reranking uğursuz oldu, orijinal sıra istifadə edilir', [
                'error' => $response->body(),
            ]);
            return $candidates->take($topN);
        }

        $results = $response->json('results');
        $candidatesArray = $candidates->values()->all();

        return collect($results)->map(fn($result) => array_merge(
            $candidatesArray[$result['index']],
            [
                'rerank_score' => $result['relevance_score'],
                'original_index' => $result['index'],
            ]
        ));
    }

    /**
     * Öz hostinqinizdə BGE-Reranker (məs., FastAPI + HuggingFace)
     * Öz infrastrukturunuzda yerləşdirmək üçün Cohere-ə alternativ.
     */
    public function rerankBGE(
        string $query,
        Collection $candidates,
        int $topN = 5,
    ): Collection {
        $pairs = $candidates->map(fn($c) => [$query, $c['content']])->toArray();

        $response = Http::post(config('services.bge_reranker.url') . '/rerank', [
            'pairs' => $pairs,
        ]);

        if ($response->failed()) {
            return $candidates->take($topN);
        }

        $scores = $response->json('scores');

        return $candidates
            ->map(fn($chunk, $idx) => array_merge($chunk, [
                'rerank_score' => $scores[$idx],
            ]))
            ->sortByDesc('rerank_score')
            ->take($topN)
            ->values();
    }
}
```

### 4. HyDE Tətbiqi

```php
<?php

namespace App\Services\RAG;

use App\Services\AI\EmbeddingService;
use Anthropic\Client as AnthropicClient;

class HyDEService
{
    public function __construct(
        private AnthropicClient $anthropic,
        private EmbeddingService $embeddingService,
    ) {}

    /**
     * Sorğu üçün hipotetik sənəd yaradın, sonra onu embed edin.
     * Bu, qısa sorğular ilə uzun sənədlər arasındakı semantik uçurumu bağlayır.
     *
     * @param string $query İstifadəçinin sualı
     * @return array Hipotetik sənədin embedding-i
     */
    public function generateHypotheticalEmbedding(string $query): array
    {
        $hypotheticalDoc = $this->generateHypotheticalDocument($query);
        return $this->embeddingService->embed($hypotheticalDoc);
    }

    /**
     * Sorğu genişləndirilməsi üçün: həm orijinal sorğu embedding-ini
     * həm də HyDE embedding-ini qaytarın, sonra ortalamasını alın.
     */
    public function getExpandedQueryEmbedding(string $query): array
    {
        $queryEmbedding = $this->embeddingService->embed($query);
        $hydeEmbedding = $this->generateHypotheticalEmbedding($query);

        // İki embedding-in ortalamasını alın
        return array_map(
            fn($q, $h) => ($q + $h) / 2,
            $queryEmbedding,
            $hydeEmbedding
        );
    }

    private function generateHypotheticalDocument(string $query): string
    {
        $response = $this->anthropic->messages()->create([
            'model' => 'claude-haiku-4-5', // Bu tapşırıq üçün sürətli və ucuz
            'max_tokens' => 256,
            'messages' => [[
                'role' => 'user',
                'content' => <<<PROMPT
                Bu suala mükəmməl cavab olacaq qısa 2-3 paraqraflıq sənəd yazın.
                Sanki sənədləşmə və ya bilik bazası məqaləsi yazırsınız kimi yazın.
                Ton olaraq konkret və faktiki olun. "Bu sənəd izah edir" deməyin — birbaşa məzmunu yazın.
                
                Sual: {$query}
                PROMPT,
            ]],
        ]);

        return $response->content[0]->text;
    }
}
```

### 5. Bütün Addımlarla Tam Pipeline

```php
<?php

namespace App\Services\RAG;

use App\Models\RagQuery;
use Anthropic\Client as AnthropicClient;
use Illuminate\Support\Facades\Log;

class AdvancedRAGPipeline
{
    public function __construct(
        private HybridSearchService $hybridSearch,
        private RerankingService $reranker,
        private HyDEService $hyde,
        private PromptAugmentationService $augmentation,
        private AnthropicClient $anthropic,
    ) {}

    /**
     * Tam təkmilləşdirilmiş RAG pipeline:
     * HyDE → Hibrid Axtarış → Reranking → Genişləndirilmə → Nəsil
     *
     * @param string $query
     * @param array $config Pipeline konfiqurasiyası
     */
    public function run(string $query, array $config = []): array
    {
        $startTime = microtime(true);
        $tracing = [];

        // Addım 1: Sorğu yaxşılaşdırması
        $useHyde = $config['use_hyde'] ?? false;
        if ($useHyde) {
            $enhancedQuery = $this->hyde->generateHypotheticalEmbedding($query);
            $tracing['hyde_used'] = true;
        }

        // Addım 2: Hibrid əldə etmə (BM25 + vektor)
        $candidateCount = $config['candidate_k'] ?? 50;
        $candidates = $this->hybridSearch->search(
            query: $query,
            topK: $candidateCount,
            candidateK: $candidateCount * 2,
            vectorWeight: $config['vector_weight'] ?? 0.7,
            bm25Weight: $config['bm25_weight'] ?? 0.3,
        );

        $tracing['candidates_retrieved'] = $candidates->count();

        if ($candidates->isEmpty()) {
            return $this->buildNoContextResponse($query);
        }

        // Addım 3: Reranking
        $useReranking = $config['use_reranking'] ?? true;
        $finalTopK = $config['top_k'] ?? 5;

        if ($useReranking && $candidates->count() > $finalTopK) {
            $rerankedCandidates = $this->reranker->rerank(
                query: $query,
                candidates: $candidates,
                topN: $finalTopK,
            );
            $tracing['reranking_applied'] = true;
        } else {
            $rerankedCandidates = $candidates->take($finalTopK);
            $tracing['reranking_applied'] = false;
        }

        $tracing['final_chunks'] = $rerankedCandidates->count();

        // Addım 4: Prompt genişləndirilməsi
        $prompt = $this->augmentation->buildPrompt($query, $rerankedCandidates);

        // Addım 5: Nəsil
        $response = $this->anthropic->messages()->create([
            'model' => $config['model'] ?? 'claude-opus-4-5',
            'max_tokens' => $config['max_tokens'] ?? 1024,
            'system' => $prompt['system'],
            'messages' => $prompt['messages'],
        ]);

        $answer = $response->content[0]->text;
        $citations = $this->augmentation->extractCitations($answer, $rerankedCandidates);

        $latencyMs = (int)((microtime(true) - $startTime) * 1000);
        $tracing['latency_ms'] = $latencyMs;

        // Analitika üçün qeyd edin
        RagQuery::create([
            'query' => $query,
            'answer' => $answer,
            'retrieved_chunks' => $rerankedCandidates->toArray(),
            'citations' => $citations,
            'latency_ms' => $latencyMs,
        ]);

        return [
            'answer' => $answer,
            'citations' => $citations,
            'tracing' => $tracing,
            'latency_ms' => $latencyMs,
        ];
    }

    private function buildNoContextResponse(string $query): array
    {
        return [
            'answer' => 'Sualınız üçün bilik bazasında uyğun məlumat tapa bilmədim.',
            'citations' => [],
            'tracing' => ['candidates_retrieved' => 0],
            'latency_ms' => 0,
        ];
    }
}
```

### 6. Tam Mətn Axtarışı İndeksi Miqrasiyası

```php
<?php
// BM25 axtarışının effektiv işləməsi üçün tam mətn axtarışı üçün GIN indeksi əlavə edin

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Sürətli tam mətn axtarışı üçün GIN (Ümumiləşdirilmiş Tərs İndeks)
        // tsvector_update_trigger məzmun dəyişdikdə axtarış vektorunu avtomatik yeniləyir
        DB::statement(<<<SQL
            ALTER TABLE knowledge_chunks
            ADD COLUMN IF NOT EXISTS content_tsv tsvector
            GENERATED ALWAYS AS (to_tsvector('english', content)) STORED
        SQL);

        DB::statement(<<<SQL
            CREATE INDEX IF NOT EXISTS knowledge_chunks_content_tsv_idx
            ON knowledge_chunks
            USING gin(content_tsv)
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE knowledge_chunks DROP COLUMN IF EXISTS content_tsv');
    }
};
```

---

## Memarın Tövsiyələri

### Hansı Əldə Etmə Metodunu Nə Zaman İstifadə Etməli

| Sorğu Növü | Ən Yaxşı Metod | Səbəb |
|-----------|---------------|--------|
| Xüsusi identifikatorlar (xəta kodları, ID-lər, adlar) | BM25 | Dəqiq uyğunluq tələb olunur |
| Konseptual suallar ("X necə işləyir?") | Vektor | Semantik anlayış |
| Qarışıq ("hansı xəta X problemə səbəb olur?") | Hibrid + Rerank | Hər iki növ siqnal |
| Qısa sorğular (1-3 söz) | HyDE + Vektor | Qısa sorğuların embedding-i zəifdir |
| Uzun sorğular (paraqraflar) | Vektor | Tam semantik niyyəti tutur |
| Boolean sorğular ("A VƏ B, amma C deyil") | BM25 | Vektor inkarı yaxşı idarə edə bilmir |

### Reranking Gecikmə Büdcəsi

Reranking ən bahalı addımdır. Tipik gecikməler:

| Yanaşma | Gecikmə | Xərc |
|---------|---------|------|
| Reranking yoxdur | 0ms | Pulsuz |
| Cohere Rerank (50 sənəd) | 200-500ms | ~$0.001 |
| BGE-Reranker (öz serveriniz, GPU) | 50-200ms | İnfrastruktur xərci |
| GPT-4 reranker kimi | 2-5s | ~$0.01 |

**Qərar**: Yalnız əldə etmə keyfiyyəti açıq şəkildə kifayətsiz olduqda və gecikmə büdcəsi icazə verdikdə reranking istifadə edin. Reranking əlavə etməzdən əvvəl hibrid axtarışla başlayın (çox vaxt kifayət edir).

### RRF Parametrlərinin Tənzimlənməsi

RRF sabiti k=60 yaxşı təsdiqlənmiş standartdır, lakin onu tənzimləyə bilərsiniz:
- **Aşağı k (məs., 10)**: Daha yüksək variasiya. Hər sistemdə ən yüksək sıralanan sənədlər daha çox kredit alır. Bir sistem aydın şəkildə daha yaxşı olduqda yaxşıdır.
- **Yüksək k (məs., 100)**: Daha az variasiya. Daha vahid bal paylanması. Hər iki sistem bərabər töhfə verdikdə daha yaxşıdır.

### Vektor-BM25 Nisbəti

70/30 vektor-BM25 çəki bölgüsü başlanğıc nöqtəsidir. Məlumat dəstinizi profilləyin:
- Çoxlu xüsusi terminləri olan texniki sənədlər → BM25 çəkisini artırın
- Söhbət şəklindəki bilik bazası → vektor çəkisini artırın
- Qarışıq → 60/40-dan başlayın, dəqiqliyi/recall-ı ölçün, tənzimləyin

### Əlavə İnfrastruktur Olmadan PostgreSQL Hibrid Axtarışı

Saf PostgreSQL hibrid axtarışı üçün (Elasticsearch olmadan):

```sql
-- CTE istifadə edərək tək sorğuda hibrid axtarış
WITH vector_results AS (
    SELECT id, 
           ROW_NUMBER() OVER (ORDER BY embedding <=> '[...]'::vector) as vector_rank
    FROM knowledge_chunks
    WHERE embedding IS NOT NULL
    ORDER BY embedding <=> '[...]'::vector
    LIMIT 50
),
bm25_results AS (
    SELECT id,
           ROW_NUMBER() OVER (ORDER BY ts_rank_cd(content_tsv, query) DESC) as bm25_rank
    FROM knowledge_chunks,
         to_tsquery('english', 'your & query & terms') query
    WHERE content_tsv @@ query
    LIMIT 50
),
rrf_scores AS (
    SELECT 
        COALESCE(v.id, b.id) as id,
        COALESCE(1.0 / (60 + v.vector_rank), 0) * 0.7 +
        COALESCE(1.0 / (60 + b.bm25_rank), 0) * 0.3 as rrf_score
    FROM vector_results v
    FULL OUTER JOIN bm25_results b ON v.id = b.id
)
SELECT kc.*, rrf_scores.rrf_score
FROM knowledge_chunks kc
JOIN rrf_scores ON kc.id = rrf_scores.id
ORDER BY rrf_scores.rrf_score DESC
LIMIT 10;
```
