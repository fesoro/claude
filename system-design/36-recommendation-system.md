# Recommendation System Design (Senior)

## İcmal

**Recommendation System (Tövsiyə Sistemi)** — istifadəçilərə onların maraqlarına, davranışlarına və başqa istifadəçilərin davranışlarına əsasən məhsul, kontent və ya xidmət təklif edən sistemdir.

**Əsas məqsədlər:**
- **User engagement artırmaq** — Netflix 80% seçimlər recommendation-dan
- **Conversion rate artırmaq** — Amazon satışların 35%-i recommendation-dan
- **Content discovery** — uzun quyruq (long tail) kontentini çatdırmaq
- **Personalization** — hər istifadəçiyə unikal təcrübə


## Niyə Vacibdir

E-commerce, streaming, social media — hamısı recommendation ilə revenue artırır. Collaborative filtering, content-based, hybrid yanaşmalar arasındakı fərqi bilmək data-driven ürün qərarları üçün vacibdir. Matrix factorization, embedding similarity — modern ML recommendation-ın özəyidir.

## Əsas Anlayışlar

### 1. Collaborative Filtering (CF)

İstifadəçilərin davranışları əsasında oxşarlıq tapır. "Sənə bənzər istifadəçilər bunu sevdi" yanaşmasıdır.

**User-based CF:**
```
User A: [🎬 Movie1:5, 🎬 Movie2:4, 🎬 Movie3:1]
User B: [🎬 Movie1:5, 🎬 Movie2:5, 🎬 Movie4:4]
User C: [🎬 Movie1:1, 🎬 Movie3:5, 🎬 Movie5:5]

Similarity(A, B) = high (hər ikisi Movie1, Movie2 sevir)
Recommend A: Movie4 (B sevdiyindən A da sevə bilər)
```

**Item-based CF:**
```
Movie1-i sevənlər, Movie2-ni də sevir
User A Movie1-i 5 ilə qiymətləndirib
Recommend: Movie2
```

**Similarity metrics:**
- **Cosine similarity** — vector angle
- **Pearson correlation** — rating correlation
- **Jaccard index** — set overlap

### 2. Content-Based Filtering

Məhsul/kontent xüsusiyyətlərinə əsasən oxşar şeylər təklif edir.

```
Movie1: [genre: Action, director: Nolan, year: 2020]
Movie2: [genre: Action, director: Nolan, year: 2019]

User A Movie1-i sevir → Movie2 təklif et
```

**Texnikalar:**
- TF-IDF (text features)
- Word embeddings (Word2Vec)
- Feature engineering

**Üstünlüklər:**
- Cold start (new items) problemi yoxdur
- Niche items təklif edə bilər
- Explainable ("sevdiyin X filmə bənzədiyi üçün")

**Dezavantajlar:**
- Serendipity zəif (sürpriz tövsiyələr)
- Feature engineering çətin

### 3. Hybrid Approach

CF + Content-Based birləşdirir. Ən güclü sistemlər hibriddir.

**Strategiyalar:**
- **Weighted** — bal verib birləşdir
- **Switching** — kontekstə görə seç
- **Mixed** — iki listi birlikdə göstər
- **Feature combination** — feature-ları model-ə birlikdə ver

### 4. Matrix Factorization (SVD)

User-item rating matrisi low-rank matrices-ə parçalanır.

```
Rating Matrix R (m × n) ≈ U (m × k) × V^T (k × n)

k = latent factors (məs: action, romance, comedy)
U = user preferences in latent space
V = item characteristics in latent space

Prediction: R[u][i] = U[u] · V[i]
```

**Populyar alqoritmlər:**
- SVD (Singular Value Decomposition)
- SVD++ (implicit feedback ilə)
- ALS (Alternating Least Squares) — Spark MLlib
- NMF (Non-negative Matrix Factorization)

### 5. Deep Learning Approaches

- **Neural Collaborative Filtering (NCF)** — user/item embedding + neural network
- **Wide & Deep** (Google) — memorization + generalization
- **Two-Tower Model** (YouTube) — user tower + item tower, vector search
- **Transformer-based** — BERT4Rec, SASRec (session-based)

### 6. Cold Start Problemi

**Növləri:**
- **New user** — heç bir tarix yox
- **New item** — heç bir rating yox
- **New system** — hər şey yenidir

**Həllər:**
- Onboarding questions (sevdiyin janrları seç)
- Popularity-based fallback
- Demographic-based
- Content-based filtering (new item üçün)
- Explore/exploit (multi-armed bandit)

### 7. Popularity Bias

Populyar items həmişə rec olunur, long tail itir. Həll: diversity, novelty metrics daxil et.

## Arxitektura

```
┌─────────────────────────────────────────────────────────┐
│                    OFFLINE TRAINING                      │
│                                                          │
│  ┌──────────┐    ┌──────────┐    ┌──────────┐           │
│  │   Data   │───►│ Feature  │───►│  Model   │           │
│  │   Lake   │    │  Store   │    │ Training │           │
│  └──────────┘    └──────────┘    └────┬─────┘           │
│                                        │                  │
│                                        ▼                  │
│                                  ┌──────────┐            │
│                                  │  Model   │            │
│                                  │ Registry │            │
│                                  └────┬─────┘            │
└───────────────────────────────────────┼──────────────────┘
                                        │
                                        ▼
┌───────────────────────────────────────────────────────────┐
│                    ONLINE SERVING                          │
│                                                            │
│  User Request                                              │
│       │                                                    │
│       ▼                                                    │
│  ┌──────────────┐   ┌──────────────┐   ┌──────────────┐  │
│  │   Candidate  │──►│   Ranking    │──►│  Filtering   │  │
│  │  Generation  │   │   (ML Model) │   │ (business)   │  │
│  │  (1000s)     │   │   (100s)     │   │  (10s)       │  │
│  └──────────────┘   └──────────────┘   └──────┬───────┘  │
│                                                 │           │
│                                                 ▼           │
│                                        ┌──────────────┐   │
│                                        │  Response    │   │
│                                        └──────────────┘   │
└────────────────────────────────────────────────────────────┘
```

**Two-stage design (YouTube, Netflix):**
1. **Candidate generation** — milyonlardan minlərə azalt (sürətli, retrieval)
2. **Ranking** — minlərdən 10-lara ranklay (dəqiq, model-heavy)
3. **Re-ranking** — diversity, business rules (promoted, fresh content)

## Nümunələr

### Item-based Collaborative Filtering

```php
<?php

namespace App\Services\Recommendation;

class ItemBasedCF
{
    /**
     * Cosine similarity between two items based on user ratings
     */
    public function cosineSimilarity(array $item1Ratings, array $item2Ratings): float
    {
        $commonUsers = array_intersect_key($item1Ratings, $item2Ratings);
        if (count($commonUsers) < 2) return 0.0;

        $dotProduct = 0;
        $norm1 = 0;
        $norm2 = 0;

        foreach ($commonUsers as $userId => $_) {
            $r1 = $item1Ratings[$userId];
            $r2 = $item2Ratings[$userId];
            $dotProduct += $r1 * $r2;
            $norm1 += $r1 ** 2;
            $norm2 += $r2 ** 2;
        }

        if ($norm1 == 0 || $norm2 == 0) return 0.0;
        return $dotProduct / (sqrt($norm1) * sqrt($norm2));
    }

    /**
     * Predict rating for user-item pair
     */
    public function predict(int $userId, int $itemId, array $itemSimilarities, array $userRatings): float
    {
        $weightedSum = 0;
        $simSum = 0;

        foreach ($userRatings as $ratedItemId => $rating) {
            if (!isset($itemSimilarities[$itemId][$ratedItemId])) continue;
            $sim = $itemSimilarities[$itemId][$ratedItemId];
            if ($sim <= 0) continue;

            $weightedSum += $sim * $rating;
            $simSum += abs($sim);
        }

        return $simSum > 0 ? $weightedSum / $simSum : 0.0;
    }

    /**
     * Top-N recommendations for user
     */
    public function recommend(int $userId, int $topN = 10): array
    {
        $userRatings = $this->getUserRatings($userId);
        $candidateItems = $this->getCandidateItems($userId);
        $similarities = $this->loadItemSimilarities();

        $predictions = [];
        foreach ($candidateItems as $itemId) {
            $predictions[$itemId] = $this->predict($itemId, $itemId, $similarities, $userRatings);
        }

        arsort($predictions);
        return array_slice($predictions, 0, $topN, true);
    }

    private function getUserRatings(int $userId): array
    {
        return \DB::table('ratings')
            ->where('user_id', $userId)
            ->pluck('rating', 'item_id')
            ->toArray();
    }

    private function getCandidateItems(int $userId): array
    {
        // User görməyən itemler
        return \DB::table('items')
            ->whereNotIn('id', function ($q) use ($userId) {
                $q->select('item_id')->from('ratings')->where('user_id', $userId);
            })
            ->limit(1000)
            ->pluck('id')
            ->toArray();
    }

    private function loadItemSimilarities(): array
    {
        // Pre-computed, Redis/DB-də saxlanılır
        return cache()->remember('item_similarities', 3600, function () {
            return \DB::table('item_similarities')
                ->get()
                ->groupBy('item_a')
                ->map(fn($rows) => $rows->pluck('similarity', 'item_b')->toArray())
                ->toArray();
        });
    }
}
```

### Content-Based Filtering (TF-IDF)

```php
class ContentBasedRecommender
{
    /**
     * Film description-lardan TF-IDF vektor
     */
    public function buildTfIdfVector(string $text, array $corpus): array
    {
        $terms = $this->tokenize($text);
        $termFreq = array_count_values($terms);
        $totalDocs = count($corpus);

        $vector = [];
        foreach ($termFreq as $term => $tf) {
            $docsWithTerm = 0;
            foreach ($corpus as $doc) {
                if (stripos($doc, $term) !== false) $docsWithTerm++;
            }
            $idf = log($totalDocs / max(1, $docsWithTerm));
            $vector[$term] = $tf * $idf;
        }

        return $vector;
    }

    public function cosineSimilarity(array $v1, array $v2): float
    {
        $dot = 0;
        $norm1 = 0;
        $norm2 = 0;

        foreach ($v1 as $term => $w) {
            if (isset($v2[$term])) $dot += $w * $v2[$term];
            $norm1 += $w ** 2;
        }
        foreach ($v2 as $w) $norm2 += $w ** 2;

        return ($norm1 && $norm2) ? $dot / (sqrt($norm1) * sqrt($norm2)) : 0;
    }

    private function tokenize(string $text): array
    {
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9\s]/', ' ', $text);
        $stopwords = ['the', 'a', 'an', 'and', 'or', 'is', 'are'];
        $words = array_filter(explode(' ', $text), fn($w) => $w && !in_array($w, $stopwords));
        return array_values($words);
    }
}
```

### Laravel: Real-time Recommendation API

```php
// routes/api.php
Route::middleware('auth:sanctum')->get('/recommendations', [RecommendationController::class, 'index']);

// app/Http/Controllers/RecommendationController.php
class RecommendationController extends Controller
{
    public function __construct(private RecommendationService $service) {}

    public function index(Request $request)
    {
        $userId = $request->user()->id;
        $context = [
            'device' => $request->header('User-Agent'),
            'location' => $request->ip(),
            'time_of_day' => now()->hour,
        ];

        $recommendations = $this->service->getRecommendations($userId, $context);

        return response()->json([
            'items' => $recommendations,
            'model_version' => config('recommendation.version'),
        ]);
    }
}

// app/Services/RecommendationService.php
class RecommendationService
{
    public function getRecommendations(int $userId, array $context): array
    {
        // 1. Cache check
        $cacheKey = "rec:{$userId}:" . md5(serialize($context));
        if ($cached = Cache::get($cacheKey)) return $cached;

        // 2. Candidate generation (fast)
        $candidates = $this->generateCandidates($userId);

        // 3. Ranking (ML model)
        $ranked = $this->rankCandidates($userId, $candidates, $context);

        // 4. Filtering (business rules)
        $filtered = $this->applyBusinessRules($userId, $ranked);

        // 5. Diversity re-ranking
        $final = $this->diversify($filtered, 20);

        Cache::put($cacheKey, $final, 300);
        return $final;
    }

    private function generateCandidates(int $userId): array
    {
        return array_merge(
            $this->collaborativeCandidates($userId, 500),
            $this->contentBasedCandidates($userId, 500),
            $this->popularItems(100),
            $this->trendingItems(100),
        );
    }

    private function rankCandidates(int $userId, array $candidates, array $context): array
    {
        // External ML service (Python/TensorFlow Serving)
        $response = Http::post('http://ml-service:8501/v1/models/ranker:predict', [
            'user_id' => $userId,
            'item_ids' => array_unique(array_column($candidates, 'id')),
            'context' => $context,
        ]);

        return $response->json('predictions');
    }

    private function applyBusinessRules(int $userId, array $items): array
    {
        return array_filter($items, function ($item) use ($userId) {
            if (in_array($item['id'], $this->userBlocklist($userId))) return false;
            if ($item['stock'] === 0) return false;
            if ($item['age_restricted'] && !$this->userIsAdult($userId)) return false;
            return true;
        });
    }

    private function diversify(array $items, int $limit): array
    {
        // MMR (Maximal Marginal Relevance) — diversity + relevance
        $selected = [];
        $lambda = 0.7;

        while (count($selected) < $limit && $items) {
            $bestScore = -INF;
            $bestIdx = 0;
            foreach ($items as $idx => $item) {
                $diversity = $this->minDistanceTo($item, $selected);
                $score = $lambda * $item['score'] + (1 - $lambda) * $diversity;
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestIdx = $idx;
                }
            }
            $selected[] = $items[$bestIdx];
            unset($items[$bestIdx]);
        }

        return $selected;
    }

    private function minDistanceTo(array $item, array $selected): float
    {
        if (empty($selected)) return 1.0;
        $minSim = INF;
        foreach ($selected as $s) {
            $sim = $this->itemSimilarity($item, $s);
            if ($sim < $minSim) $minSim = $sim;
        }
        return 1 - $minSim;
    }
}
```

### Offline Training Pipeline (Laravel Queue)

```php
// app/Console/Commands/TrainRecommendationModel.php
class TrainRecommendationModel extends Command
{
    protected $signature = 'rec:train';

    public function handle()
    {
        // 1. Data export
        $this->info('Exporting ratings...');
        $this->exportRatings(storage_path('ml/ratings.csv'));

        // 2. Training (Python script via shell)
        $this->info('Training model...');
        Process::run('python3 /ml/train.py --data ratings.csv --output model.pkl');

        // 3. Compute item similarities
        $this->info('Computing item similarities...');
        Process::run('python3 /ml/similarity.py --model model.pkl --output similarities.json');

        // 4. Load into Redis
        $similarities = json_decode(file_get_contents('/ml/similarities.json'), true);
        foreach ($similarities as $itemA => $sims) {
            Redis::hmset("sim:{$itemA}", $sims);
        }

        // 5. Version marker
        Cache::forever('rec:model_version', now()->timestamp);
        $this->info('Training complete.');
    }
}

// Scheduler
// app/Console/Kernel.php
$schedule->command('rec:train')->dailyAt('02:00');
```

## Real-World Nümunələr

- **Netflix** — personalized homepage, 80% watch time from recommendations, deep learning + CF
- **YouTube** — two-tower model + candidate generation + ranking, 70% watch time
- **Amazon** — item-to-item CF (2003 paper founder), "bundu alanlar bunu da aldı"
- **Spotify** — Discover Weekly, collaborative filtering + NLP (playlist text) + audio features
- **TikTok** — real-time user feedback, deep learning, very fast loop
- **LinkedIn** — "People you may know", graph-based + collaborative filtering
- **Pinterest** — PinSage (graph neural network)
- **Instagram Explore** — two-stage, billions of candidates

## Praktik Tapşırıqlar

**1. Collaborative filtering vs content-based — fərqi və nə vaxt hansı?**
- **CF**: istifadəçi davranışına əsaslanır, serendipity yüksək, cold start problemi var
- **Content-based**: item xüsusiyyətlərinə, explainable, new item üçün yaxşı
- Hibrid daha güclüdür — Netflix, Spotify hibrid istifadə edir

**2. Cold start problemini necə həll edirsən?**
- **New user**: onboarding suallarla maraqları topla, demographic, popularity fallback
- **New item**: content-based (features), metadata-dan başla
- **Explore/exploit** (bandits): yeni kontenti müvəqqəti boost et
- Transfer learning — başqa domain-dən model

**3. Matrix factorization intuitively nə edir?**
User-item rating matrisi user və item "preference vector"-larına parçalayır. Hər user və item k-dimensional latent space-də embedding alır. Rating ≈ user_vec · item_vec (dot product). Latent factor-lar gizli xüsusiyyətlərdir (janr, mood və s.).

**4. İki-tower arxitekturası necə işləyir?**
User tower user features-ini embedding-ə çevirir. Item tower item features-ini. Training-də dot product relevancy predict edir. Inference-də item embeddings-i ANN index-ə (Faiss, ScaNN) yazılır, user embedding ilə nearest neighbor search edilir. Milyardlarla item arasında millisecond-larla candidate generation.

**5. Offline vs online training?**
- **Offline (batch)**: günlük/həftəlik, böyük data, heavy computation
- **Online (streaming)**: real-time user feedback, session-based, quick adaptation
- **Hybrid**: offline global model + online personalization

**6. A/B test recommendation system üçün necə aparılır?**
- Users tədüfi group-lara bölünür (control vs treatment)
- Metrik: CTR, watch time, session length, revenue
- Statistical significance (p-value < 0.05, Bayesian)
- Novelty effect üçün uzun müddət test et
- Counterfactual — bias-ları əvəz et (IPS weighting)

**7. Popularity bias necə həll edilir?**
- Diversity metrics (MMR, Determinantal Point Processes)
- Re-ranking — populyar item-ləri penalize et
- Long-tail boost
- Inverse propensity scoring — qorunmayan item-lərə ağırlıq ver
- Fairness constraints

**8. Implicit feedback (klik, view) vs explicit (rating)?**
- **Explicit**: daha dəqiq, amma az (1% users rating verir)
- **Implicit**: çox, amma noisy (click ≠ like)
- **BPR (Bayesian Personalized Ranking)**: implicit üçün populyar
- **SVD++**: hər ikisini birləşdirir
- Weight müxtəlif feedback-lərə (watch > click > impression)

**9. Real-time personalization necə işləyir?**
- Session features (son 5 klik, son axtarış)
- Streaming (Kafka + Flink) ilə user embedding update
- Online feature store (Redis, Feast)
- Low-latency (<100ms) model serving (TensorFlow Serving, ONNX)
- Two-tower user tower session-based yenilənir

**10. Recommendation system-ı necə evalüasiya edirsən?**
**Offline metrics:**
- Precision@K, Recall@K, NDCG@K, MAP
- RMSE, MAE (rating prediction)
- Coverage, diversity, novelty, serendipity

**Online metrics:**
- CTR, conversion rate
- Watch time, session length
- Retention, DAU/MAU
- Revenue per user

**11. Scalability — milyardlarla user/item?**
- **Two-stage** (retrieval + ranking)
- **ANN (Approximate Nearest Neighbor)**: Faiss, ScaNN, HNSW
- **Sharding**: user range-lər üzrə
- **Caching**: popular users üçün pre-computed rec
- **CDN** for static lists
- **Lambda architecture**: batch + streaming

## Praktik Baxış

1. **Başla sadə modelələrdən** — popularity, simple CF-dən başla, deep learning sonra
2. **Hybrid seç** — CF + content + context ən yaxşı nəticə
3. **Cold start-a xüsusi strategiya** — production-a çıxmamış hazırla
4. **Two-stage arxitektura** — retrieval + ranking ayrı-ayrı optimize
5. **Diversity/novelty** daxil et — populer bias-dan qoru
6. **Business rules filter** — stock, region, age-restricted
7. **A/B test** hər model dəyişikliyini
8. **Cache aggressively** — rec hesabat bahalıdır, 5-15 dəq cache OK
9. **Real-time feedback loop** — user klik/view anında model-ə qaytar
10. **Explain rec-lərini** — "çünki X sevmisən" — trust artır
11. **Feature store** istifadə et — offline/online consistency
12. **Monitoring**: CTR drift, coverage, latency, model staleness
13. **Ethical considerations** — filter bubble, echo chamber, bias auditing
14. **Fallback strategy** — ML service fail olsa popular/trending göstər


## Əlaqəli Mövzular

- [Feed System](22-feed-system-design.md) — personalized content sıralaması
- [Vector Database](69-vector-database-design.md) — embedding-based similarity axtarışı
- [Feature Store](70-feature-store-design.md) — recommendation feature-ləri
- [Social Graph](61-social-graph-design.md) — social signal-lar
- [Caching](03-caching-strategies.md) — precomputed recommendation cache
