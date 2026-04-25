# GraphRAG: Bilik Qrafları + Vektor Axtarışı (Lead)

## Niyə Yalnız Vektor Axtarışı Kifayətsizdir

Vektor axtarışı semantik cəhətdən oxşar parçaları tapmaqda üstündür, lakin o, əsaslı olaraq **qurumlar arasındakı əlaqələri** keçməyi tələb edən suallara cavab verə bilmir.

Korporativ bilik bazasını nəzərdən keçirin:

- "Mühəndislik VP-nin birbaşa tabeliyindəkilər kimdir?"
- "Hansı məhsullar köhnəlmiş autentifikasiya xidmətindən asılıdır?"
- "Müştəri məlumatlarının işlənməsinə təsir edən bütün uyğunluq tələbləri nələrdir?"

Bu suallar **əlaqə keçidini** tələb edir, oxşarlıq uyğunlaşmasını deyil. "Mühəndislik VP-nin birbaşa tabeliyindəkilər" üçün vektor axtarışı VP-nin bioqrafiyasını və org cədvəli təsvirini qaytarar — lakin əlaqə qrafını dinamik olaraq hesablamaz.

### Qrafiklərin Vektorların Qaçırdığı Məlumatı Tutması

| Məlumat Növü | Vektor Axtarışı | Qrafik |
|-----------------|--------------|-------|
| Semantik oxşarlıq | Əla | Zəif |
| Çoxlu addımlı əlaqələr | Bacarmır | Əla |
| Yazılı əlaqələr ("A B-yə səbəb olur", "A B-nin hissəsidir") | Xeyr | Bəli |
| Tranzitiv xassələr ("Əgər A → B → C, onda A → C") | Xeyr | Bəli |
| Cəmiyyət strukturu | Qismən | Əla |
| Faktiki dəqiqlik | Aşağı | Yüksək |

---

## Bilik Qrafının Əsasları

Bilik qrafı yönlü xassəli bir qrafikdir:
- **Qovşaqlar (Qurumlar)**: Həqiqi dünya obyektləri — insanlar, şirkətlər, məhsullar, konseptlər
- **Kənarlar (Əlaqələr)**: Qurumlar arasında yazılı, yönlü əlaqələr
- **Xassələr**: Qovşaqlar və kənarlardakı atributlar

```
(Alice)--[WORKS_FOR]-->(Acme Corp)
(Alice)--[MANAGES]-->(Bob)
(Bob)--[WORKS_ON]-->(Project X)
(Project X)--[USES]-->(PostgreSQL)
(PostgreSQL)--[IS_A]-->(Database)
```

"Alice-in komandası hansı verilənlər bazaları ilə işləyir?" kimi sorğu iki addım tələb edir:
```
Alice → MANAGES → {Bob, Carol, Dave} → WORKS_ON → {Layihələr} → USES → {Verilənlər Bazaları}
```

---

## Microsoft GraphRAG Yanaşması

Microsoft Research 2024-cü ildə GraphRAG-ı yayımladı — RAG-ı icmaya əsaslı ümumiləşdirmə ilə genişləndirir. Əsas anlayış:

### İcma Xülasələri

1. **Qurum çıxarışı**: LLM bütün sənədlərdən qurumları və əlaqələri çıxarır
2. **Qrafik qurulması**: Qurumların qrafiyini qurun
3. **İcma aşkarlanması**: Əlaqəli qurumların klasterlərini tapmaq üçün Leiden alqoritmini işlədin
4. **İcma xülasələri**: Hər icma üçün LLM xülasələri yaradın
5. **Çox səviyyəli sorğular**: Müxtəlif dəqiqlik səviyyələrində sorğulayın:
   - Lokal: birbaşa qurum əldə etmə (sürətli, xüsusi)
   - Qlobal: icma xülasəsi əldə etmə (daha yavaş, geniş)

### İcma Xülasələrinin Əhəmiyyəti

Bəzi suallar "böyük mənzərəni" anlamağı tələb edir:
- "Məhsul rəylərimizdəki əsas mövzular nələrdir?"
- "Arxitekturamıza yüksək səviyyəli icmal verin"

Heç bir tək sənəd parçası bu suallara cavab vermir. İcma xülasələri bir çox sənəddəki məlumatları ardıcıl yüksək səviyyəli təsvirlərə birləşdirir.

---

## GraphRAG vs Sadə RAG

| Aspekt | Sadə RAG | GraphRAG |
|--------|----------|---------|
| Sorğu növü | Xüsusi faktlar | Faktlar + əlaqələr + xülasələr |
| Qurulum mürəkkəbliyi | Aşağı | Yüksək |
| İndeksləmə xərci | Aşağı | Yüksək (qurum çıxarışı LLM çağırışları) |
| Sorğu gecikməsi | Aşağı (ms) | Daha yüksək (qrafik keçidi) |
| Faktlarda hallüsinasiya | Orta | Aşağı (strukturlu faktlar) |
| Çoxlu addımlı mühakimə | Bacarmır | Əla |
| Texniki xidmət | Asan (yenidən embed edin) | Mürəkkəb (qurumları yenidən çıxarın) |

**Qərar**: RAG ilə başlayın. Suallar əlaqə keçidini və ya qlobal ümumiləşdirməni tələb etdikdə GraphRAG əlavə edin.

---

## Laravel Tətbiqi

### 1. PostgreSQL-də Qrafik Saxlama üçün Verilənlər Bazası Sxemi

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');

        // Qurumlar (qovşaqlar)
        Schema::create('graph_entities', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Kanonik qurum adı
            $table->string('type'); // 'person', 'organization', 'product', 'concept', 'technology'
            $table->text('description')->nullable();
            $table->jsonb('properties')->default('{}');
            $table->float('importance_score')->default(0); // PageRank-ə bənzər bal
            $table->timestamps();

            $table->unique(['name', 'type']);
            $table->index('type');
            $table->index('name');
        });

        // Qurum embedding-ləri üçün vektor sütunu
        DB::statement('ALTER TABLE graph_entities ADD COLUMN embedding vector(1536)');
        DB::statement('
            CREATE INDEX graph_entities_embedding_idx
            ON graph_entities USING hnsw (embedding vector_cosine_ops)
            WITH (m = 16, ef_construction = 64)
        ');

        // Əlaqələr (kənarlar)
        Schema::create('graph_relationships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_entity_id')->constrained('graph_entities')->cascadeOnDelete();
            $table->foreignId('target_entity_id')->constrained('graph_entities')->cascadeOnDelete();
            $table->string('relationship_type'); // 'WORKS_FOR', 'DEPENDS_ON', 'PART_OF', 'CAUSES' və s.
            $table->text('description')->nullable(); // Bu əlaqənin mənbə sənədlərdə necə təsvir edildiyi
            $table->float('strength')->default(1.0); // Əlaqə çəkisi (0-1)
            $table->jsonb('properties')->default('{}');
            $table->timestamps();

            $table->index(['source_entity_id', 'relationship_type']);
            $table->index(['target_entity_id', 'relationship_type']);
            $table->index('relationship_type');
        });

        // Qurum qeydləri: hansı sənəd parçaları hansı qurumları qeyd edir
        Schema::create('entity_mentions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained('graph_entities')->cascadeOnDelete();
            $table->foreignId('chunk_id')->constrained('knowledge_chunks')->cascadeOnDelete();
            $table->text('mention_text')->nullable(); // Qurumu qeyd edən dəqiq mətn
            $table->integer('mention_count')->default(1);
            $table->timestamps();

            $table->unique(['entity_id', 'chunk_id']);
            $table->index('entity_id');
            $table->index('chunk_id');
        });

        // İcma xülasələri
        Schema::create('graph_communities', function (Blueprint $table) {
            $table->id();
            $table->integer('level')->default(0); // İyerarxiya səviyyəsi (0=ən incə, yüksək=daha geniş)
            $table->string('title');
            $table->text('summary');
            $table->jsonb('entity_ids')->default('[]'); // Bu icmaya aid qurumlar
            $table->timestamps();
        });

        DB::statement('ALTER TABLE graph_communities ADD COLUMN embedding vector(1536)');
    }

    public function down(): void
    {
        Schema::dropIfExists('graph_communities');
        Schema::dropIfExists('entity_mentions');
        Schema::dropIfExists('graph_relationships');
        Schema::dropIfExists('graph_entities');
    }
};
```

### 2. Claude istifadə edərək Qurum və Əlaqə Çıxarışı

```php
<?php

namespace App\Services\Graph;

use Anthropic\Client as AnthropicClient;
use Illuminate\Support\Collection;

class EntityExtractionService
{
    private const EXTRACTION_SYSTEM_PROMPT = <<<'PROMPT'
You are a knowledge graph extraction system. Extract entities and relationships from text.

Return a JSON object with:
- "entities": array of {name, type, description} objects
  - types: person, organization, product, technology, concept, location, event, policy
- "relationships": array of {source, target, type, description} objects
  - relationship types: WORKS_FOR, MANAGES, PART_OF, DEPENDS_ON, USES, CAUSES, LOCATED_IN, CREATED_BY, GOVERNED_BY, RELATED_TO

Rules:
- Extract only entities explicitly mentioned in the text
- Use canonical names (full names, not pronouns)
- Be specific with relationship types
- Include a short description explaining each relationship in context

Return ONLY valid JSON, no other text.
PROMPT;

    public function __construct(
        private AnthropicClient $anthropic,
    ) {}

    /**
     * Mətn parçasından qurumları və əlaqələri çıxarın.
     *
     * @param string $text Sənəd parçasının mətni
     * @return array{entities: array, relationships: array}
     */
    public function extract(string $text): array
    {
        $response = $this->anthropic->messages()->create([
            'model' => 'claude-haiku-4-5', // Toplu çıxarış üçün sürətli və ucuz
            'max_tokens' => 2048,
            'system' => self::EXTRACTION_SYSTEM_PROMPT,
            'messages' => [[
                'role' => 'user',
                'content' => "Bu mətndən qurumları və əlaqələri çıxarın:\n\n{$text}",
            ]],
        ]);

        $content = $response->content[0]->text;

        // JSON cavabını parse edin
        $extracted = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            // Cavab markdown-a bükülübsə JSON-u çıxarmağa çalışın
            preg_match('/```(?:json)?\s*(\{[\s\S]+\})\s*```/', $content, $matches);
            if (isset($matches[1])) {
                $extracted = json_decode($matches[1], true);
            }
        }

        return [
            'entities' => $extracted['entities'] ?? [],
            'relationships' => $extracted['relationships'] ?? [],
        ];
    }

    /**
     * Sürət limiti ilə bir neçə parçadan toplu çıxarış.
     */
    public function extractFromChunks(array $chunks): array
    {
        $allEntities = [];
        $allRelationships = [];

        foreach ($chunks as $index => $chunk) {
            // Sürət limiti: toplu şəkildə emal edin
            if ($index > 0 && $index % 10 === 0) {
                usleep(500000); // Hər 10 sorğudan sonra 0.5 saniyə fasilə
            }

            $extracted = $this->extract($chunk['text'] ?? $chunk);
            $allEntities = array_merge($allEntities, $extracted['entities']);
            $allRelationships = array_merge($allRelationships, $extracted['relationships']);
        }

        return [
            'entities' => $this->deduplicateEntities($allEntities),
            'relationships' => $allRelationships,
        ];
    }

    /**
     * Ad+növ əsasında qurumları dublikat silin, təsvirləri birləşdirin.
     */
    private function deduplicateEntities(array $entities): array
    {
        $seen = [];

        foreach ($entities as $entity) {
            $key = strtolower($entity['name']) . '|' . strtolower($entity['type']);

            if (!isset($seen[$key])) {
                $seen[$key] = $entity;
            } else {
                // Fərqli olarsa təsvirləri birləşdirin
                if ($entity['description'] && $entity['description'] !== $seen[$key]['description']) {
                    $seen[$key]['description'] .= ' ' . $entity['description'];
                }
            }
        }

        return array_values($seen);
    }
}
```

### 3. Qrafik Saxlama Xidməti

```php
<?php

namespace App\Services\Graph;

use App\Models\GraphEntity;
use App\Models\GraphRelationship;
use App\Models\EntityMention;
use App\Services\AI\EmbeddingService;
use Illuminate\Support\Facades\DB;

class GraphStorageService
{
    public function __construct(
        private EmbeddingService $embeddingService,
    ) {}

    /**
     * Çıxarılmış qurumları qrafikdə saxlayın.
     * Ad+növ əsasında qurumlar yaradır və ya yeniləyir (upsert).
     *
     * @param array $entities EntityExtractionService::extract()-dan alınan qurumlar
     * @return array Qurum adı → qurum ID xəritəsi
     */
    public function storeEntities(array $entities): array
    {
        $entityMap = [];

        foreach ($entities as $entityData) {
            $entity = GraphEntity::firstOrCreate(
                [
                    'name' => $entityData['name'],
                    'type' => $entityData['type'],
                ],
                [
                    'description' => $entityData['description'] ?? null,
                    'properties' => $entityData['properties'] ?? [],
                ]
            );

            // Daha yaxşı/uzun olarsa təsviri yeniləyin
            if (
                !empty($entityData['description']) &&
                strlen($entityData['description']) > strlen($entity->description ?? '')
            ) {
                $entity->update(['description' => $entityData['description']]);
            }

            $entityMap[$entityData['name']] = $entity->id;
        }

        return $entityMap;
    }

    /**
     * Qurumlar arasındakı əlaqələri saxlayın.
     *
     * @param array $relationships Çıxarılmış əlaqələr
     * @param array $entityMap storeEntities()-dən alınan Ad → ID xəritəsi
     */
    public function storeRelationships(array $relationships, array $entityMap): void
    {
        foreach ($relationships as $rel) {
            $sourceId = $entityMap[$rel['source']] ?? null;
            $targetId = $entityMap[$rel['target']] ?? null;

            if (!$sourceId || !$targetId) {
                continue; // Qurumlar tapılmadıqda atlayın
            }

            GraphRelationship::updateOrCreate(
                [
                    'source_entity_id' => $sourceId,
                    'target_entity_id' => $targetId,
                    'relationship_type' => $rel['type'],
                ],
                [
                    'description' => $rel['description'] ?? null,
                    'strength' => $rel['strength'] ?? 1.0,
                ]
            );
        }
    }

    /**
     * Qurum qeydlərini saxlayın (hansı parçalar hansı qurumları qeyd edir).
     */
    public function storeMentions(array $entities, array $entityMap, int $chunkId): void
    {
        foreach ($entities as $entity) {
            $entityId = $entityMap[$entity['name']] ?? null;
            if (!$entityId) {
                continue;
            }

            EntityMention::updateOrCreate(
                ['entity_id' => $entityId, 'chunk_id' => $chunkId],
                ['mention_count' => DB::raw('mention_count + 1')]
            );
        }
    }

    /**
     * Qurum embedding-lərini yaradın və saxlayın.
     * Embed edin: "QurumdaNövü: QurumdanAdı. Təsvir."
     */
    public function generateEntityEmbeddings(): void
    {
        $entities = GraphEntity::whereNull('embedding')->get();

        $texts = $entities->map(fn($e) =>
            "{$e->type}: {$e->name}. " . ($e->description ?? '')
        )->toArray();

        if (empty($texts)) {
            return;
        }

        $embeddings = $this->embeddingService->embedBatch($texts);

        foreach ($entities as $index => $entity) {
            $vectorString = '[' . implode(',', $embeddings[$index]) . ']';
            DB::statement(
                'UPDATE graph_entities SET embedding = ?::vector WHERE id = ?',
                [$vectorString, $entity->id]
            );
        }
    }

    /**
     * Dərəcə mərkəziliyi vasitəsilə əhəmiyyət ballarını hesablayın və saxlayın.
     * Daha çox bağlı qurumlar daha vacibdir.
     */
    public function calculateImportanceScores(): void
    {
        DB::statement(<<<SQL
            UPDATE graph_entities ge
            SET importance_score = (
                SELECT COUNT(*) 
                FROM graph_relationships gr
                WHERE gr.source_entity_id = ge.id 
                   OR gr.target_entity_id = ge.id
            )::float / GREATEST(1, (SELECT MAX(cnt) FROM (
                SELECT COUNT(*) as cnt
                FROM graph_relationships
                GROUP BY source_entity_id
            ) counts))
        SQL);
    }
}
```

### 4. Kontekst Əldə Etmə üçün Qrafik Keçidi

```php
<?php

namespace App\Services\Graph;

use App\Models\GraphEntity;
use App\Models\GraphRelationship;
use App\Services\AI\EmbeddingService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GraphRetrievalService
{
    public function __construct(
        private EmbeddingService $embeddingService,
    ) {}

    /**
     * Sorğuya ən oxşar qurumları tapın.
     * Qrafik keçidi üçün giriş nöqtəsi.
     */
    public function findRelevantEntities(
        string $query,
        int $limit = 5,
        float $threshold = 0.70,
    ): Collection {
        $queryVector = $this->embeddingService->embed($query);
        $vectorString = '[' . implode(',', $queryVector) . ']';

        return GraphEntity::query()
            ->selectRaw('*, 1 - (embedding <=> ?) as similarity', [$vectorString])
            ->whereNotNull('embedding')
            ->whereRaw('1 - (embedding <=> ?) >= ?', [$vectorString, $threshold])
            ->orderByRaw('embedding <=> ?', [$vectorString])
            ->limit($limit)
            ->get();
    }

    /**
     * Qurumin yaxınlığını alın: bütün birbaşa qonşular və onların əlaqələri.
     *
     * @param int $entityId
     * @param int $maxHops Maksimum keçid dərinliyi
     * @param int $maxNeighbors Hər qurum üçün maksimum qonşu sayı
     */
    public function getEntityNeighborhood(
        int $entityId,
        int $maxHops = 2,
        int $maxNeighbors = 10,
    ): array {
        $visited = [$entityId];
        $neighborhood = [];
        $frontier = [$entityId];

        for ($hop = 0; $hop < $maxHops; $hop++) {
            $nextFrontier = [];

            foreach ($frontier as $currentId) {
                $relationships = GraphRelationship::where(function ($q) use ($currentId) {
                    $q->where('source_entity_id', $currentId)
                      ->orWhere('target_entity_id', $currentId);
                })
                ->with(['sourceEntity', 'targetEntity'])
                ->orderByDesc('strength')
                ->limit($maxNeighbors)
                ->get();

                foreach ($relationships as $rel) {
                    $neighborhood[] = [
                        'source' => $rel->sourceEntity->name,
                        'source_type' => $rel->sourceEntity->type,
                        'relationship' => $rel->relationship_type,
                        'target' => $rel->targetEntity->name,
                        'target_type' => $rel->targetEntity->type,
                        'description' => $rel->description,
                        'hop' => $hop + 1,
                    ];

                    // Ziyarət edilməmiş qonşuları növbəti sərhədə əlavə edin
                    $neighborId = $rel->source_entity_id === $currentId
                        ? $rel->target_entity_id
                        : $rel->source_entity_id;

                    if (!in_array($neighborId, $visited)) {
                        $visited[] = $neighborId;
                        $nextFrontier[] = $neighborId;
                    }
                }
            }

            $frontier = $nextFrontier;
            if (empty($frontier)) {
                break;
            }
        }

        return $neighborhood;
    }

    /**
     * Xüsusi qurumları qeyd edən bütün sənəd parçalarını alın.
     * Bu, qrafik əldə etməni mətn parçalarına yenidən bağlayır.
     */
    public function getChunksForEntities(array $entityIds, int $limit = 10): Collection
    {
        return DB::table('knowledge_chunks')
            ->join('entity_mentions', 'knowledge_chunks.id', '=', 'entity_mentions.chunk_id')
            ->join('graph_entities', 'graph_entities.id', '=', 'entity_mentions.entity_id')
            ->whereIn('entity_mentions.entity_id', $entityIds)
            ->select('knowledge_chunks.*', DB::raw('COUNT(DISTINCT entity_mentions.entity_id) as entity_overlap'))
            ->groupBy('knowledge_chunks.id')
            ->orderByDesc('entity_overlap')
            ->limit($limit)
            ->get();
    }

    /**
     * Qrafik kontekstini LLM prompt-a daxil etmək üçün mətn kimi formatlayın.
     */
    public function formatGraphContext(array $neighborhood): string
    {
        if (empty($neighborhood)) {
            return '';
        }

        $lines = ["Bilik qrafı əlaqələri:"];

        // Keçid məsafəsinə görə qruplaşdırın
        $byHop = collect($neighborhood)->groupBy('hop');

        foreach ($byHop as $hop => $relationships) {
            $lines[] = "\nBirbaşa əlaqələr (addım {$hop}):";
            foreach ($relationships as $rel) {
                $description = $rel['description'] ? " ({$rel['description']})" : '';
                $lines[] = "  [{$rel['source_type']}] {$rel['source']} --[{$rel['relationship']}]--> [{$rel['target_type']}] {$rel['target']}{$description}";
            }
        }

        return implode("\n", $lines);
    }
}
```

### 5. Birləşdirilmiş Qrafik + Vektor RAG Xidməti

```php
<?php

namespace App\Services\Graph;

use App\Services\RAG\RetrievalService;
use App\Services\RAG\PromptAugmentationService;
use Anthropic\Client as AnthropicClient;
use Illuminate\Support\Collection;

class GraphRAGService
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
You are a knowledgeable assistant with access to both a document knowledge base and a knowledge graph.
The knowledge graph shows explicit relationships between entities.
The documents provide detailed textual context.

Answer questions based on the provided context. When relationship information from the graph is relevant, use it to provide precise, fact-based answers. Cite sources using [Source N] notation.

If the information is not in the provided context, say so clearly.
PROMPT;

    public function __construct(
        private GraphRetrievalService $graphRetrieval,
        private RetrievalService $vectorRetrieval,
        private EntityExtractionService $entityExtractor,
        private PromptAugmentationService $augmentation,
        private AnthropicClient $anthropic,
    ) {}

    /**
     * GraphRAG sorğusu: qrafik keçidini + vektor əldə etməni birləşdirir.
     *
     * @param string $query İstifadəçi sualı
     * @param bool $includeGraph Qrafik kontekstinin istifadə olunub-olunmayacağı
     */
    public function query(string $query, bool $includeGraph = true): array
    {
        // 1. Vektor əldə etmə (mətn səviyyəsindəki kontekst)
        $vectorChunks = $this->vectorRetrieval->retrieve($query, topK: 5);

        $graphContext = '';
        $entityContext = [];

        if ($includeGraph) {
            // 2. Sorğudan uyğun qurumları tapın
            $relevantEntities = $this->graphRetrieval->findRelevantEntities($query, limit: 3);

            if ($relevantEntities->isNotEmpty()) {
                // 3. Hər uyğun qurum üçün qrafiki keçin
                $allRelationships = [];
                foreach ($relevantEntities as $entity) {
                    $neighborhood = $this->graphRetrieval->getEntityNeighborhood(
                        entityId: $entity->id,
                        maxHops: 2,
                        maxNeighbors: 8,
                    );
                    $allRelationships = array_merge($allRelationships, $neighborhood);
                }

                // 4. Uyğun qurumları qeyd edən mətn parçalarını alın
                $entityIds = $relevantEntities->pluck('id')->toArray();
                $entityChunks = $this->graphRetrieval->getChunksForEntities($entityIds, limit: 3);

                // 5. Qrafik kontekstini formatlayın
                $graphContext = $this->graphRetrieval->formatGraphContext($allRelationships);

                $entityContext = $relevantEntities->map(fn($e) => [
                    'name' => $e->name,
                    'type' => $e->type,
                    'description' => $e->description,
                ])->toArray();
            }
        }

        // 6. Vektor və qrafik kontekstini birləşdirən genişləndirilmiş prompt qurun
        $prompt = $this->buildGraphAugmentedPrompt(
            query: $query,
            vectorChunks: $vectorChunks,
            graphContext: $graphContext,
        );

        // 7. Cavab yaradın
        $response = $this->anthropic->messages()->create([
            'model' => 'claude-opus-4-5',
            'max_tokens' => 1024,
            'system' => self::SYSTEM_PROMPT,
            'messages' => $prompt['messages'],
        ]);

        $answer = $response->content[0]->text;
        $citations = $this->augmentation->extractCitations($answer, $vectorChunks);

        return [
            'answer' => $answer,
            'citations' => $citations,
            'entities_used' => $entityContext,
            'graph_relationships_found' => !empty($allRelationships ?? []),
        ];
    }

    private function buildGraphAugmentedPrompt(
        string $query,
        Collection $vectorChunks,
        string $graphContext,
    ): array {
        $documentContext = $vectorChunks->map(function ($chunk, $index) {
            $source = $chunk['metadata']['document_title'] ?? 'Naməlum';
            return "[Mənbə " . ($index + 1) . "] ({$source})\n{$chunk['content']}";
        })->implode("\n\n");

        $contextBlocks = [];

        if ($graphContext) {
            $contextBlocks[] = "=== BİLİK QRAFİKİ ===\n{$graphContext}";
        }

        if ($documentContext) {
            $contextBlocks[] = "=== SƏNƏDLƏr ===\n{$documentContext}";
        }

        $fullContext = implode("\n\n", $contextBlocks);

        return [
            'messages' => [[
                'role' => 'user',
                'content' => "{$fullContext}\n\n---\n\nSual: {$query}",
            ]],
        ];
    }
}
```

### 6. Qrafik + Vektor üçün Tam Mənimsəmə Pipeline-ı

```php
<?php

namespace App\Services\Graph;

use App\Models\KnowledgeDocument;
use App\Services\RAG\DocumentIngestionPipeline;
use App\Services\RAG\ChunkingService;
use Illuminate\Support\Facades\DB;

class GraphIngestionPipeline
{
    public function __construct(
        private DocumentIngestionPipeline $ragPipeline,
        private EntityExtractionService $entityExtractor,
        private GraphStorageService $graphStorage,
        private ChunkingService $chunkingService,
    ) {}

    /**
     * Tam mənimsəmə: həm vektor parçaları, həm də qrafik qurumları yaradır.
     */
    public function ingest(
        string $title,
        string $content,
        array $metadata = [],
    ): array {
        return DB::transaction(function () use ($title, $content, $metadata) {
            // 1. Standart RAG mənimsəməsi (parçalar + embedding-lər)
            $document = $this->ragPipeline->ingest($title, $content, 'text', $metadata);

            // 2. Qurum çıxarışı üçün məzmunu parçalara bölün
            $chunks = $this->chunkingService->chunk($content, [
                'strategy' => 'recursive',
                'chunk_size' => 512,
            ]);

            // 3. Bütün parçalardan qurumları və əlaqələri çıxarın
            $extracted = $this->entityExtractor->extractFromChunks(
                array_column($chunks, 'text')
            );

            // 4. Qurumları saxlayın
            $entityMap = $this->graphStorage->storeEntities($extracted['entities']);

            // 5. Əlaqələri saxlayın
            $this->graphStorage->storeRelationships($extracted['relationships'], $entityMap);

            // 6. Qurum qeydlərini saxlayın (qurumları parçalarla əlaqələndirin)
            $chunkModels = $document->chunks()->orderBy('chunk_index')->get();
            foreach ($chunks as $index => $chunk) {
                if (isset($chunkModels[$index])) {
                    $this->graphStorage->storeMentions(
                        $extracted['entities'],
                        $entityMap,
                        $chunkModels[$index]->id
                    );
                }
            }

            // 7. Qurum embedding-lərini yaradın (istehsalda asinxron)
            $this->graphStorage->generateEntityEmbeddings();

            return [
                'document_id' => $document->id,
                'entities_extracted' => count($extracted['entities']),
                'relationships_extracted' => count($extracted['relationships']),
            ];
        });
    }
}
```

---

## Memarın Tövsiyələri

### Nə Zaman Bilik Qrafiki Qurmaq Lazımdır

GraphRAG-ın xərci əhəmiyyətlidir:
- Qurum çıxarışı: indeksləmə zamanı parça başına 1 LLM çağırışı (embedding-dən 10-100 dəfə bahalı)
- Daha mürəkkəb əldə etmə məntiqi
- Sənədlər yeniləndikdə qrafik texniki xidməti

**Bilik qrafiki qurduğunuzda**:
- İstifadəçilər əlaqə sualları verirlər ("X üçün kim məsuldur?", "Y-dən nə asılıdır?")
- Strukturlaşdırılmış, qurum zəngin məzmununuz var (org cədvəlləri, texniki arxitektura sənədləri, tənzimləyici uyğunluq)
- Çoxlu addımlı mühakimə tələb olunur ("A xidmətinə dəyişikliklərindən təsirlənən bütün xidmətləri tapın")

**Bilik qrafiki qurmadığınızda**:
- Məzmun əsasən az adlı varlıqları olan düz mətndən ibarətdir
- Suallar əsasən sənədlərden faktiki axtarışlardır
- Büdcə məhduddur

### Qurum Həlli: Çətin Problem

Qurum həlli (dublikat silmə) bilik qrafiki quruluşunun ən çətin hissəsidir:
- "PostgreSQL", "Postgres", "postgres", "pg" → eyni qurum
- "John Smith", "J. Smith", "Dr. Smith" → eyni şəxs?
- "OpenAI API", "OpenAI", "GPT API" → eyni mi yoxsa fərqli mi?

**Yanaşmalar**:
1. **Kanonik ad normallaşdırması**: Kanonik formaları təyin edin və ləqəbləri xəritələyin
2. **Embedding-ə əsaslanan dublikat silmə**: Qurum embedding-lərini klasterləyin, klasterləri birləşdirin
3. **LLM-ə əsaslanan həll**: Claude-dan iki qurum adının eyni quruma istinad edib-etmədiyini soruşun

```php
// Nümunə: embedding-ə əsaslanan qurum dublikat silmə
public function findDuplicateEntities(float $threshold = 0.95): array
{
    $vectorString = '[' . implode(',', $embedding) . ']';

    // Çox yüksək embedding oxşarlığı olan qurum cütlərini tapın
    return DB::select(<<<SQL
        SELECT a.id as entity_a_id, a.name as entity_a, 
               b.id as entity_b_id, b.name as entity_b,
               1 - (a.embedding <=> b.embedding) as similarity
        FROM graph_entities a
        JOIN graph_entities b ON a.id < b.id  -- Dublikatların qarşısını alın
        WHERE a.type = b.type  -- Yalnız eyni növ qurumları müqayisə edin
          AND 1 - (a.embedding <=> b.embedding) >= ?
        ORDER BY similarity DESC
    SQL, [$threshold]);
}
```

### Qrafik İndeks Strategiyaları

Miqyasda PostgreSQL-ə əsaslanan qrafik saxlama üçün:

```sql
-- Sürətli qonşu axtarışı (ən geniş yayılmış qrafik əməliyyatı)
CREATE INDEX rel_source_type_idx ON graph_relationships (source_entity_id, relationship_type);
CREATE INDEX rel_target_type_idx ON graph_relationships (target_entity_id, relationship_type);

-- Bir növdəki bütün qurumları tapın
CREATE INDEX entity_type_idx ON graph_entities (type);

-- Ada görə qurum tapın (dəqiq uyğunluq)
CREATE INDEX entity_name_lower_idx ON graph_entities (lower(name));

-- Qurum qeydi axtarışı üçün
CREATE INDEX mention_entity_idx ON entity_mentions (entity_id);
CREATE INDEX mention_chunk_idx ON entity_mentions (chunk_id);
```

### Qrafik Yeniləmələrinin İdarə Edilməsi

Mənbə sənədlər dəyişdikdə:
1. Təsirlənmiş parçalar üçün qurum qeydlərini silin
2. Yenilənmiş parçalardan qurumları yenidən çıxarın
3. Dublikat silməni yenidən işlədin
4. Əhəmiyyət ballarını yenidən hesablayın

Bu baha başa gəlir — real vaxt çıxarışı əvəzinə, pik olmayan saatlarda toplu yeniləmələri nəzərə alın.

### Neo4j Olmadan İcma Aşkarlanması

PostgreSQL-də icma aşkarlanması üçün (Leiden alqoritminin daha sadə versiyası):

```sql
-- Rekursiv CTE vasitəsilə sadə bağlı komponent aşkarlanması
WITH RECURSIVE components AS (
    -- Başlanğıc: hər qurum öz komponentidir
    SELECT id, id AS component_id FROM graph_entities
    
    UNION
    
    -- Yayılma: əlaqələrlə qoşulun
    SELECT r.target_entity_id, c.component_id
    FROM components c
    JOIN graph_relationships r ON c.id = r.source_entity_id
    WHERE r.strength > 0.5
)
SELECT component_id, ARRAY_AGG(id) as entity_ids, COUNT(*) as size
FROM (
    SELECT id, MIN(component_id) AS component_id
    FROM components
    GROUP BY id
) resolved
GROUP BY component_id
ORDER BY size DESC;
```

Bu, icma xülasəsi yaratmaq üçün güclü şəkildə bağlı qurumların icmalarını müəyyənləşdirir.
