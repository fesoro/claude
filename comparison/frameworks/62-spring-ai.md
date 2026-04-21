# Spring AI vs Laravel AI — Dərin Müqayisə

## Giriş

LLM-lər (Large Language Models) indi tətbiq inkişafının adi hissəsi olub. Chatbot, RAG (Retrieval-Augmented Generation), tool calling, structured output, multi-agent iş axınları — bunların hamısı backend tərəfdə olur. Framework-lərdən isə tələb olunur: OpenAI/Anthropic/Ollama kimi provider-lərə **vahid API**, vector DB abstraction, prompt template, tool/function binding, token/latency observability.

**Spring AI** (1.x — 2024-2025-də stable) — Spring ekosistemində LLM üçün rəsmi framework. `ChatClient` fluent API, `ChatModel` + `EmbeddingModel` abstractions, `VectorStore` interface (PGVector, Redis, Pinecone, Chroma, Qdrant, Milvus), `@Tool` annotation ilə function calling, structured output `@BeanOutputConverter` ilə, `Advisor` (middleware kimi) konsepsiyası.

**Laravel**-də isə rəsmi AI framework yoxdur. Lakin zəngin paket ekosistemi var:
- `openai-php/laravel` — OpenAI üçün
- `anthropic-api-php` — Claude üçün
- `prism-php/prism` — unified provider API (OpenAI, Anthropic, Ollama, Gemini)
- `ollama-php` — lokal Ollama
- `cloudflare-laravel-workers-ai` — Cloudflare Workers AI
- Pgvector `pgvector/pgvector-php` + Laravel Scout

Bu sənəddə ikisində də PDF sənədləri üzərində RAG pipeline quracayıq: ingest → embed → pgvector → retrieve → chat with tool calling.

---

## Spring-də istifadəsi

### 1) Dependency və konfigurasiya

```xml
<!-- pom.xml -->
<dependencyManagement>
    <dependencies>
        <dependency>
            <groupId>org.springframework.ai</groupId>
            <artifactId>spring-ai-bom</artifactId>
            <version>1.0.0</version>
            <type>pom</type>
            <scope>import</scope>
        </dependency>
    </dependencies>
</dependencyManagement>

<dependencies>
    <!-- Anthropic Claude -->
    <dependency>
        <groupId>org.springframework.ai</groupId>
        <artifactId>spring-ai-starter-model-anthropic</artifactId>
    </dependency>
    <!-- OpenAI (fallback) -->
    <dependency>
        <groupId>org.springframework.ai</groupId>
        <artifactId>spring-ai-starter-model-openai</artifactId>
    </dependency>
    <!-- PGVector -->
    <dependency>
        <groupId>org.springframework.ai</groupId>
        <artifactId>spring-ai-starter-vector-store-pgvector</artifactId>
    </dependency>
    <!-- PDF reader -->
    <dependency>
        <groupId>org.springframework.ai</groupId>
        <artifactId>spring-ai-pdf-document-reader</artifactId>
    </dependency>
    <dependency>
        <groupId>org.springframework.ai</groupId>
        <artifactId>spring-ai-tika-document-reader</artifactId>
    </dependency>
</dependencies>
```

```yaml
# application.yml
spring:
  ai:
    anthropic:
      api-key: ${ANTHROPIC_API_KEY}
      chat:
        options:
          model: claude-sonnet-4-5
          temperature: 0.3
          max-tokens: 4096
    openai:
      api-key: ${OPENAI_API_KEY}
      embedding:
        options:
          model: text-embedding-3-small
    vectorstore:
      pgvector:
        index-type: HNSW
        distance-type: COSINE_DISTANCE
        dimensions: 1536
        initialize-schema: true
        schema-name: public
        table-name: doc_chunks

  datasource:
    url: jdbc:postgresql://localhost:5432/rag_db
    username: rag
    password: rag

management:
  endpoints:
    web:
      exposure:
        include: health,metrics
  observations:
    annotations:
      enabled: true
```

### 2) ChatClient — fluent API

```java
@Configuration
public class ChatConfig {

    @Bean
    public ChatClient chatClient(ChatClient.Builder builder) {
        return builder
            .defaultSystem("""
                Sən köməkçi assistantsan. Azerbaycan dilində cavab ver.
                Qısa, dəqiq ol. Əmin deyilsənsə, "bilmirəm" de.
                """)
            .defaultOptions(AnthropicChatOptions.builder()
                .model("claude-sonnet-4-5")
                .temperature(0.3)
                .maxTokens(2048)
                .build())
            .build();
    }
}

@RestController
@RequestMapping("/chat")
public class ChatController {

    private final ChatClient chatClient;

    public ChatController(ChatClient chatClient) {
        this.chatClient = chatClient;
    }

    @PostMapping("/ask")
    public String ask(@RequestBody ChatRequest request) {
        return chatClient.prompt()
            .user(request.question())
            .call()
            .content();
    }

    // Streaming
    @PostMapping(value = "/stream", produces = MediaType.TEXT_EVENT_STREAM_VALUE)
    public Flux<String> stream(@RequestBody ChatRequest request) {
        return chatClient.prompt()
            .user(request.question())
            .stream()
            .content();
    }

    // ChatResponse — metadata üçün
    @PostMapping("/detailed")
    public ResponseEntity<Map<String, Object>> detailed(@RequestBody ChatRequest request) {
        ChatResponse response = chatClient.prompt()
            .user(request.question())
            .call()
            .chatResponse();

        return ResponseEntity.ok(Map.of(
            "content", response.getResult().getOutput().getText(),
            "inputTokens", response.getMetadata().getUsage().getPromptTokens(),
            "outputTokens", response.getMetadata().getUsage().getCompletionTokens(),
            "finishReason", response.getResult().getMetadata().getFinishReason()
        ));
    }
}

public record ChatRequest(String question) {}
```

### 3) PromptTemplate — dinamik prompt

```java
@Service
public class ProductDescriptionService {

    private final ChatClient chatClient;

    @Value("classpath:/prompts/product-description.st")
    private Resource promptTemplate;

    public String generate(String productName, String category, List<String> features) {
        PromptTemplate template = new PromptTemplate(promptTemplate);
        Map<String, Object> params = Map.of(
            "productName", productName,
            "category", category,
            "features", String.join(", ", features)
        );

        return chatClient.prompt(template.create(params))
            .call()
            .content();
    }
}
```

```
# resources/prompts/product-description.st
Sən marketinq kopirayterisən. Aşağıdakı məhsul üçün 3 cümlədən ibarət
məhsul təsviri yaz:

Məhsul: {productName}
Kateqoriya: {category}
Xüsusiyyətlər: {features}

Təsvir azərbaycan dilində olsun, sadə və B1 səviyyəsində.
```

### 4) Structured output — `@BeanOutputConverter`

LLM-dən JSON qaytarıb Java record-a bind etmək:

```java
public record ProductDescription(
    String title,
    String shortDescription,
    List<String> bulletPoints,
    String seoKeywords
) {}

@Service
public class StructuredProductService {

    private final ChatClient chatClient;

    public ProductDescription generate(String productName) {
        return chatClient.prompt()
            .user(u -> u.text("""
                Bu məhsul üçün məhsul təsviri yarat: {name}
                """).param("name", productName))
            .call()
            .entity(ProductDescription.class);    // avtomatik JSON parse
    }

    // List<T>
    public List<ProductDescription> generateBatch(List<String> names) {
        return chatClient.prompt()
            .user(u -> u.text("Bu məhsullar üçün təsvir yarat: {names}")
                       .param("names", names))
            .call()
            .entity(new ParameterizedTypeReference<List<ProductDescription>>() {});
    }
}
```

Spring AI arxa planda JSON schema yaradır və LLM-ə "response_format: json_object" ilə göndərir.

### 5) Tool/Function calling — `@Tool`

```java
@Service
public class WeatherTools {

    private final WeatherClient weatherClient;

    public WeatherTools(WeatherClient weatherClient) {
        this.weatherClient = weatherClient;
    }

    @Tool(description = "Şəhər üçün cari hava məlumatı qaytarır")
    public WeatherInfo getWeather(
            @ToolParam(description = "Şəhərin adı, məsələn Baku") String city,
            @ToolParam(description = "Ölçü vahidi: celsius və ya fahrenheit") String unit) {
        return weatherClient.fetch(city, unit);
    }

    @Tool(description = "İstifadəçinin son 30 günlük sifarişlərini qaytarır")
    public List<Order> getRecentOrders(@ToolParam(description = "User ID") Long userId) {
        return orderRepository.findByUserIdAndCreatedAtAfter(userId, LocalDateTime.now().minusDays(30));
    }
}

public record WeatherInfo(double temperature, int humidity, String condition) {}

@RestController
public class AgentController {

    private final ChatClient chatClient;
    private final WeatherTools weatherTools;

    @PostMapping("/agent")
    public String agent(@RequestBody ChatRequest request) {
        return chatClient.prompt()
            .user(request.question())
            .tools(weatherTools)              // tools-u pass et
            .call()
            .content();
    }
}
```

Istifadəçi "Bakıda hava necədir?" yazanda, LLM avtomatik `getWeather` tool-unu çağırır, nəticəni alır, cavab yaradır.

### 6) RAG — VectorStore

```java
@Configuration
public class VectorStoreConfig {

    @Bean
    public VectorStore vectorStore(JdbcTemplate jdbcTemplate, EmbeddingModel embeddingModel) {
        return PgVectorStore.builder(jdbcTemplate, embeddingModel)
            .dimensions(1536)
            .distanceType(PgVectorStore.PgDistanceType.COSINE_DISTANCE)
            .indexType(PgVectorStore.PgIndexType.HNSW)
            .initializeSchema(true)
            .schemaName("public")
            .vectorTableName("doc_chunks")
            .build();
    }
}
```

### 7) Document ingestion ETL

```java
@Service
public class DocumentIngestService {

    private final VectorStore vectorStore;

    public DocumentIngestService(VectorStore vectorStore) {
        this.vectorStore = vectorStore;
    }

    public void ingestPdf(Resource pdfResource, String source) {
        // 1. Read
        PagePdfDocumentReader reader = new PagePdfDocumentReader(
            pdfResource,
            PdfDocumentReaderConfig.builder()
                .withPageTopMargin(0)
                .withPageBottomMargin(0)
                .build()
        );
        List<Document> documents = reader.get();

        // 2. Split
        TextSplitter splitter = new TokenTextSplitter(800, 200, 5, 10000, true);
        List<Document> chunks = splitter.apply(documents);

        // 3. Enrich (metadata)
        chunks.forEach(d -> {
            d.getMetadata().put("source", source);
            d.getMetadata().put("ingested_at", Instant.now().toString());
        });

        // 4. Embed + Write
        vectorStore.add(chunks);

        log.info("Ingested {} chunks from {}", chunks.size(), source);
    }

    public void ingestFromUrl(String url) {
        TikaDocumentReader reader = new TikaDocumentReader(url);
        List<Document> docs = reader.get();
        TextSplitter splitter = new TokenTextSplitter();
        vectorStore.add(splitter.apply(docs));
    }
}
```

### 8) QuestionAnswerAdvisor — RAG chat

```java
@RestController
@RequestMapping("/rag")
public class RagController {

    private final ChatClient chatClient;

    public RagController(ChatClient.Builder builder, VectorStore vectorStore) {
        this.chatClient = builder
            .defaultAdvisors(
                new QuestionAnswerAdvisor(vectorStore,
                    SearchRequest.builder().topK(5).similarityThreshold(0.75).build()),
                new MessageChatMemoryAdvisor(new InMemoryChatMemory()),
                new SimpleLoggerAdvisor()
            )
            .defaultSystem("""
                Sən dəstək agentisən. Kontekstdən istifadə edərək cavab ver.
                Kontekstdə cavab yoxdursa, "Bu məlumatı bilmirəm" de.
                """)
            .build();
    }

    @PostMapping("/ask")
    public String ask(@RequestBody ChatRequest request,
                      @RequestHeader("X-Conversation-Id") String conversationId) {
        return chatClient.prompt()
            .user(request.question())
            .advisors(a -> a.param(AbstractChatMemoryAdvisor.CHAT_MEMORY_CONVERSATION_ID_KEY,
                                   conversationId))
            .call()
            .content();
    }
}
```

`QuestionAnswerAdvisor` arxa planda: query-ni embed edir, vector store-dan top-5 oxşar chunk tapır, prompt-a `{context}` kimi əlavə edir.

### 9) Advisors — middleware kimi

```java
// Custom advisor
public class PiiRedactionAdvisor implements CallAroundAdvisor {
    @Override
    public AdvisedResponse aroundCall(AdvisedRequest request, CallAroundAdvisorChain chain) {
        // User input-u təmizlə (PII redact)
        String cleaned = redactPii(request.userText());
        AdvisedRequest modified = AdvisedRequest.from(request).withUserText(cleaned).build();
        return chain.nextAroundCall(modified);
    }

    @Override
    public String getName() { return "PiiRedaction"; }

    @Override
    public int getOrder() { return 0; }
}

@Configuration
public class AiConfig {
    @Bean
    public ChatClient safeChatClient(ChatClient.Builder builder, VectorStore vs) {
        return builder
            .defaultAdvisors(
                new PiiRedactionAdvisor(),                // input təmizlə
                new QuestionAnswerAdvisor(vs),            // RAG
                new SafeGuardAdvisor(List.of("killing", "harmful"))   // output yoxla
            )
            .build();
    }
}
```

### 10) Observability — token/latency

```java
@Configuration
public class AiObservabilityConfig {

    @Bean
    public ObservationRegistryCustomizer<ObservationRegistry> aiObservationCustomizer() {
        return registry -> registry.observationConfig()
            .observationHandler(new ChatModelPromptContentObservationHandler());
    }
}
```

Micrometer avtomatik bu metrikləri verir: `spring.ai.chat.client.duration`, `gen_ai.client.token.usage` (input/output), `gen_ai.client.operation.duration`. Prometheus/Grafana ilə dashboard qurmaq olur.

### 11) Multi-model fallback

```java
@Service
public class ResilientChatService {

    private final ChatClient primary;      // Claude
    private final ChatClient fallback;     // OpenAI

    @Retry(name = "llm")
    @CircuitBreaker(name = "llm", fallbackMethod = "askFallback")
    public String ask(String question) {
        return primary.prompt().user(question).call().content();
    }

    public String askFallback(String question, Throwable t) {
        log.warn("Primary LLM failed, using fallback", t);
        return fallback.prompt().user(question).call().content();
    }
}
```

---

## Laravel-də istifadəsi

### 1) composer və konfigurasiya

```json
{
    "require": {
        "php": "^8.3",
        "laravel/framework": "^12.0",
        "prism-php/prism": "^0.20",
        "openai-php/laravel": "^0.10",
        "pgvector/pgvector": "^0.2",
        "smalot/pdfparser": "^2.10"
    }
}
```

```php
// config/prism.php
return [
    'providers' => [
        'anthropic' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
            'version' => '2023-06-01',
        ],
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'organization' => env('OPENAI_ORGANIZATION'),
        ],
        'ollama' => [
            'url' => env('OLLAMA_URL', 'http://localhost:11434'),
        ],
    ],
];
```

### 2) Sadə chat — Prism

```php
// app/Services/ChatService.php
namespace App\Services;

use Prism\Prism\Prism;
use Prism\Prism\Enums\Provider;

class ChatService
{
    public function ask(string $question): string
    {
        $response = Prism::text()
            ->using(Provider::Anthropic, 'claude-sonnet-4-5')
            ->withSystemPrompt('Sən köməkçi assistantsan. Azərbaycan dilində cavab ver.')
            ->withPrompt($question)
            ->withMaxTokens(2048)
            ->withTemperature(0.3)
            ->generate();

        return $response->text;
    }

    public function askWithUsage(string $question): array
    {
        $response = Prism::text()
            ->using(Provider::Anthropic, 'claude-sonnet-4-5')
            ->withPrompt($question)
            ->generate();

        return [
            'content' => $response->text,
            'inputTokens' => $response->usage->promptTokens,
            'outputTokens' => $response->usage->completionTokens,
            'finishReason' => $response->finishReason->value,
        ];
    }
}

// Controller
Route::post('/chat/ask', function (Request $request, ChatService $service) {
    return ['answer' => $service->ask($request->input('question'))];
});
```

### 3) Streaming

```php
Route::post('/chat/stream', function (Request $request) {
    return response()->stream(function () use ($request) {
        $stream = Prism::text()
            ->using(Provider::Anthropic, 'claude-sonnet-4-5')
            ->withPrompt($request->input('question'))
            ->stream();

        foreach ($stream as $chunk) {
            echo "data: " . json_encode(['text' => $chunk->text]) . "\n\n";
            ob_flush();
            flush();
        }
    }, 200, [
        'Content-Type' => 'text/event-stream',
        'Cache-Control' => 'no-cache',
        'X-Accel-Buffering' => 'no',
    ]);
});
```

### 4) Prompt template — Blade ilə

```blade
{{-- resources/views/prompts/product-description.blade.php --}}
Sən marketinq kopirayterisən. Aşağıdakı məhsul üçün 3 cümləli təsvir yaz:

Məhsul: {{ $productName }}
Kateqoriya: {{ $category }}
Xüsusiyyətlər: {{ implode(', ', $features) }}

Təsvir azərbaycan dilində, sadə B1 səviyyəsində.
```

```php
public function generateDescription(string $name, string $cat, array $features): string
{
    $prompt = view('prompts.product-description', [
        'productName' => $name,
        'category' => $cat,
        'features' => $features,
    ])->render();

    return Prism::text()
        ->using(Provider::Anthropic, 'claude-sonnet-4-5')
        ->withPrompt($prompt)
        ->generate()
        ->text;
}
```

### 5) Structured output — Schema

```php
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;
use Prism\Prism\Schema\ArraySchema;

class ProductDescriptionService
{
    public function generate(string $productName): array
    {
        $schema = new ObjectSchema(
            name: 'product_description',
            description: 'Məhsul təsviri strukturu',
            properties: [
                new StringSchema('title', 'Qısa başlıq'),
                new StringSchema('shortDescription', '1 cümləli təsvir'),
                new ArraySchema(
                    name: 'bulletPoints',
                    description: 'Xüsusiyyətlər siyahısı',
                    items: new StringSchema('point', 'Bir xüsusiyyət')
                ),
                new StringSchema('seoKeywords', 'Vergüllə ayrılmış SEO açar sözləri'),
            ],
            requiredFields: ['title', 'shortDescription', 'bulletPoints']
        );

        $response = Prism::structured()
            ->using(Provider::Anthropic, 'claude-sonnet-4-5')
            ->withSchema($schema)
            ->withPrompt("Bu məhsul üçün təsvir: {$productName}")
            ->generate();

        return $response->structured;
    }
}
```

### 6) Tool/Function calling

```php
use Prism\Prism\Tool;

class WeatherTool extends Tool
{
    public function __construct(private WeatherClient $client)
    {
        $this
            ->as('get_weather')
            ->for('Şəhər üçün cari hava məlumatı qaytarır')
            ->withStringParameter('city', 'Şəhərin adı, məsələn Baku')
            ->withStringParameter('unit', 'celsius və ya fahrenheit')
            ->using(fn (string $city, string $unit) => $this->handle($city, $unit));
    }

    public function handle(string $city, string $unit): string
    {
        $info = $this->client->fetch($city, $unit);
        return json_encode([
            'temperature' => $info->temperature,
            'condition' => $info->condition,
            'humidity' => $info->humidity,
        ]);
    }
}

class OrderLookupTool extends Tool
{
    public function __construct()
    {
        $this
            ->as('get_recent_orders')
            ->for('İstifadəçinin son 30 günlük sifarişlərini qaytarır')
            ->withNumberParameter('userId', 'İstifadəçi ID')
            ->using(fn (int $userId) => $this->handle($userId));
    }

    public function handle(int $userId): string
    {
        $orders = \App\Models\Order::where('user_id', $userId)
            ->where('created_at', '>=', now()->subDays(30))
            ->get()
            ->map(fn ($o) => ['id' => $o->id, 'total' => $o->total, 'status' => $o->status]);
        return $orders->toJson();
    }
}

// Istifadə
Route::post('/agent', function (Request $request, WeatherTool $weather, OrderLookupTool $orders) {
    $response = Prism::text()
        ->using(Provider::Anthropic, 'claude-sonnet-4-5')
        ->withSystemPrompt('Sən köməkçi agentsan. Lazım olsa tool çağır.')
        ->withPrompt($request->input('question'))
        ->withTools([$weather, $orders])
        ->withMaxSteps(5)    // multi-step tool calling
        ->generate();

    return ['answer' => $response->text, 'steps' => $response->steps];
});
```

### 7) Embedding və pgvector

```php
// Migration
Schema::create('doc_chunks', function (Blueprint $table) {
    $table->id();
    $table->text('content');
    $table->jsonb('metadata');
    $table->vector('embedding', 1536);
    $table->timestamps();
});

DB::statement('CREATE INDEX doc_chunks_embedding_idx ON doc_chunks USING hnsw (embedding vector_cosine_ops)');

// Embedding servisi
namespace App\Services;

use Prism\Prism\Prism;
use Prism\Prism\Enums\Provider;

class EmbeddingService
{
    public function embed(string $text): array
    {
        $response = Prism::embeddings()
            ->using(Provider::OpenAI, 'text-embedding-3-small')
            ->fromInput($text)
            ->generate();

        return $response->embeddings[0]->embedding;
    }

    public function embedBatch(array $texts): array
    {
        $response = Prism::embeddings()
            ->using(Provider::OpenAI, 'text-embedding-3-small')
            ->fromArray($texts)
            ->generate();

        return array_map(fn ($e) => $e->embedding, $response->embeddings);
    }
}
```

### 8) Document ingestion

```php
// app/Services/DocumentIngestService.php
namespace App\Services;

use App\Models\DocChunk;
use Smalot\PdfParser\Parser as PdfParser;

class DocumentIngestService
{
    public function __construct(private EmbeddingService $embeddings) {}

    public function ingestPdf(string $pdfPath, string $source): int
    {
        // 1. Read
        $parser = new PdfParser();
        $pdf = $parser->parseFile($pdfPath);
        $text = $pdf->getText();

        // 2. Split (chunk_size=800, overlap=200)
        $chunks = $this->splitText($text, 800, 200);

        // 3. Embed batch
        $embeddings = $this->embeddings->embedBatch($chunks);

        // 4. Write
        $rows = [];
        foreach ($chunks as $i => $chunk) {
            $rows[] = [
                'content' => $chunk,
                'metadata' => json_encode(['source' => $source, 'chunk' => $i]),
                'embedding' => DB::raw("'[" . implode(',', $embeddings[$i]) . "]'"),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DocChunk::insert($rows);

        return count($chunks);
    }

    private function splitText(string $text, int $chunkSize, int $overlap): array
    {
        $words = explode(' ', $text);
        $chunks = [];
        $step = $chunkSize - $overlap;
        for ($i = 0; $i < count($words); $i += $step) {
            $slice = array_slice($words, $i, $chunkSize);
            $chunks[] = implode(' ', $slice);
        }
        return $chunks;
    }
}
```

### 9) RAG — similarity search + chat

```php
// app/Services/RagService.php
namespace App\Services;

use App\Models\DocChunk;
use Prism\Prism\Prism;
use Prism\Prism\Enums\Provider;
use Prism\Prism\ValueObjects\Messages\SystemMessage;
use Prism\Prism\ValueObjects\Messages\UserMessage;

class RagService
{
    public function __construct(private EmbeddingService $embeddings) {}

    public function ask(string $question, int $topK = 5): array
    {
        // 1. Query embed
        $queryEmbedding = $this->embeddings->embed($question);
        $vectorStr = '[' . implode(',', $queryEmbedding) . ']';

        // 2. Similarity search (pgvector)
        $chunks = DocChunk::selectRaw("content, metadata,
                                        embedding <=> ?::vector AS distance")
            ->addBinding($vectorStr, 'select')
            ->whereRaw("embedding <=> ?::vector < 0.25", [$vectorStr])
            ->orderByRaw("embedding <=> ?::vector", [$vectorStr])
            ->limit($topK)
            ->get();

        // 3. Build context
        $context = $chunks->pluck('content')->implode("\n\n---\n\n");

        // 4. Chat with context
        $prompt = "Kontekst:\n{$context}\n\nSual: {$question}\n\nYalnız kontekstə əsaslan. Cavab yoxdursa, 'Bilmirəm' de.";

        $response = Prism::text()
            ->using(Provider::Anthropic, 'claude-sonnet-4-5')
            ->withSystemPrompt('Sən sənəd əsaslı köməkçi assistantsan.')
            ->withPrompt($prompt)
            ->generate();

        return [
            'answer' => $response->text,
            'sources' => $chunks->pluck('metadata'),
            'usage' => [
                'input' => $response->usage->promptTokens,
                'output' => $response->usage->completionTokens,
            ],
        ];
    }
}
```

### 10) Conversation memory

```php
// Cache ilə sadə memory
class ChatMemory
{
    public function append(string $conversationId, string $role, string $content): void
    {
        $key = "chat:{$conversationId}";
        $history = Cache::get($key, []);
        $history[] = ['role' => $role, 'content' => $content, 'at' => now()->toIso8601String()];
        $history = array_slice($history, -20);    // son 20 mesaj
        Cache::put($key, $history, now()->addHour());
    }

    public function get(string $conversationId): array
    {
        return Cache::get("chat:{$conversationId}", []);
    }
}

// RagService-də istifadə
$messages = array_map(
    fn ($m) => $m['role'] === 'user'
        ? new UserMessage($m['content'])
        : new \Prism\Prism\ValueObjects\Messages\AssistantMessage($m['content']),
    $memory->get($conversationId)
);
$messages[] = new UserMessage($question);

$response = Prism::text()
    ->using(Provider::Anthropic, 'claude-sonnet-4-5')
    ->withMessages($messages)
    ->generate();
```

### 11) Observability

Laravel-də Prism event dispatch edir — bunu log-a yazmaq olur:

```php
// app/Providers/AppServiceProvider.php
use Prism\Prism\Events\RequestStarted;
use Prism\Prism\Events\RequestFinished;

Event::listen(RequestFinished::class, function (RequestFinished $event) {
    Log::channel('ai')->info('LLM call', [
        'provider' => $event->provider,
        'model' => $event->model,
        'input_tokens' => $event->usage?->promptTokens,
        'output_tokens' => $event->usage?->completionTokens,
        'duration_ms' => $event->durationMs,
    ]);

    // Metrics — Prometheus
    app(\Prometheus\CollectorRegistry::class)
        ->getOrRegisterCounter('laravel', 'llm_tokens_total', 'Token usage', ['model', 'type'])
        ->incBy($event->usage->promptTokens, [$event->model, 'input']);
});
```

---

## Əsas fərqlər

| Xüsusiyyət | Spring AI | Laravel (Prism/openai-php) |
|---|---|---|
| Framework tipi | Rəsmi Spring framework | 3rd party paket (Prism) |
| Provider abstraction | `ChatModel`, `EmbeddingModel` | `Prism::text()` fluent |
| Fluent API | `ChatClient.prompt()...call()` | `Prism::text()...generate()` |
| Structured output | `@BeanOutputConverter` → POJO | `ObjectSchema` → array |
| Tool calling | `@Tool` annotation method | `Tool` class extend |
| Prompt template | `PromptTemplate` + `.st` faylı | Blade view |
| Conversation memory | `ChatMemory` advisor built-in | Manual Cache |
| VectorStore abstraction | `VectorStore` interface (6+ provider) | Eloquent + pgvector (manual) |
| Document readers | PDF/Tika/JSON built-in | `smalot/pdfparser` 3rd party |
| Text splitter | `TokenTextSplitter` built-in | Manual split |
| RAG advisor | `QuestionAnswerAdvisor` 1 sətir | Manual similarity search + prompt |
| Safety guard | `SafeGuardAdvisor` | Manual |
| Middleware (advisor) | `CallAroundAdvisor` interface | Middleware / event listener |
| Streaming | `Flux<String>` reactive | `response()->stream()` |
| Observability | Micrometer built-in (token, latency) | Event listener manual |
| Multi-provider fallback | `@CircuitBreaker` + multiple beans | Manual try/catch |

---

## Niyə belə fərqlər var?

**Spring ekosistemində "framework-first" fəlsəfə.** Spring AI 2024-də rəsmi olaraq GA oldu. Spring tərəfindən dəstəklənir — provider-lərdən asılı olmayaraq vahid API vermək məqsədidir. `VectorStore`, `ChatModel`, `EmbeddingModel` abstractions 10+ provider üçün eynidir — kodu dəyişmədən Anthropic-dan OpenAI-a keçmək mümkündür.

**Laravel "paket ekosistemi" fəlsəfəsi.** Laravel core-a AI built-in gətirmir — community paketləri istifadə olunur. Bu həm üstünlük (çeviklik), həm mənfi (qeyri-standart). Prism Laravel dünyasında ən çox "framework kimi" hiss olunan AI paketidir, amma hələ 1.0-a çatmayıb.

**Advisor vs Middleware.** Spring AI-nin `Advisor` konseptsiyası HTTP middleware-ə bənzəyir amma LLM request cycle-a aiddir — PII redaction, RAG context injection, safety guard hamısı advisor ola bilər. Laravel-də bunu manual yazmaq lazımdır (event listener və ya service decorator).

**VectorStore built-in.** Spring AI 6+ vector DB üçün driver verir. Laravel-də yalnız pgvector üçün paket var — Pinecone/Weaviate/Qdrant üçün manual HTTP client yazmaq lazımdır.

**Java type safety.** `@BeanOutputConverter` JSON schema-nı avtomatik yaradır və POJO-ya parse edir. Laravel-də `ObjectSchema` əl ilə yazılır — PHP-nin type sisteminin zəif olmasına görə runtime validation lazımdır.

**Observability.** Spring Boot Actuator + Micrometer LLM metrics-i rəsmi olaraq dəstəkləyir (`gen_ai.*` semantic convention). Laravel-də event listener yazmaq lazımdır — OpenTelemetry inteqrasiyası manual.

---

## Hansı framework-də var, hansında yoxdur?

**Yalnız Spring AI-da:**
- `ChatClient` fluent API rəsmi
- `Advisor` interface (RAG, memory, safety, logging hamısı eyni abstraction-da)
- `QuestionAnswerAdvisor` — RAG 3 sətirlə
- `VectorStore` abstraction (PGVector, Redis, Pinecone, Chroma, Qdrant, Milvus, Azure, Neo4j)
- `TokenTextSplitter`, `ContentTextSplitter` — built-in
- PDF/Tika/JSON/Markdown document reader-lər
- `@Tool` annotation reflection-əsaslı function calling
- `@BeanOutputConverter` — JSON schema avtomatik
- Micrometer `gen_ai.*` metrics built-in
- `ChatMemory` + `MessageChatMemoryAdvisor`
- Multi-provider bean konfigurasiyası eyni interface ilə

**Yalnız Laravel-də (və ya daha asan):**
- Blade view ilə prompt template (developer-friendly)
- Paket ekosisteminin çevikliyi (openai-php, prism, ollama-php seçimi)
- Artisan command ilə sadə ingestion CLI
- Eloquent + pgvector (Laravel model kimi işləmək)

---

## Best Practices

**Spring AI üçün:**
- `ChatClient.Builder` defaultlar istifadə et — hər endpoint üçün təkrar yazma
- `Advisor` chain qur — PII redaction → RAG → safety guard
- `@BeanOutputConverter` ilə structured output — parse error-u azal
- `QuestionAnswerAdvisor` `similarityThreshold` parametrini tənzimlə
- Token usage-i Micrometer ilə izlə, budget limit qoy
- Fallback provider bean qur — Circuit Breaker ilə
- VectorStore `initializeSchema` yalnız dev-də true

**Laravel üçün:**
- Prism istifadə et — vendor lock-in azal
- Prompt-ları Blade view-da saxla — version control altında
- Structured output üçün Zod-bənzər schema validation əlavə et
- Embedding hesabını cache et (eyni mətn üçün)
- Pgvector HNSW index yaratmağı unutma (`vector_cosine_ops`)
- Token usage-i log-a yaz, Grafana/Loki-də dashboard
- Rate limit qoy (`throttle` middleware) — LLM xərci yüksəkdir

---

## Yekun

Spring AI enterprise-grade LLM framework-dur — `ChatClient`, `Advisor`, `VectorStore`, `@Tool` kimi konseptlər provider-dən asılı olmayaraq vahid abstraction verir. RAG, tool calling, structured output, observability — hamısı 1.x-də stable. Spring ekosistemində bu rəqibsizdir.

Laravel-də rəsmi AI framework yoxdur, amma Prism (və onu tamamlayan openai-php/laravel, ollama-php, pgvector-php) yaxşı alətlər topludur. Tool calling, streaming, structured output, embedding hamısı mümkündür. Vector store abstraction zəifdir — əsasən pgvector istifadə olunur. Advisor kimi first-class middleware konsepsiyası yoxdur, manual event listener yazılır.

Qısa qayda: **Java enterprise RAG/AI agent arxitekturasında Spring AI avtomatik seçimdir. Laravel-də AI özəlliyi kiçik-orta ölçüdə kifayət qədər yaxşı işləyir — amma advisor chain, multi-vendor vector store, deklarativ tool calling kimi inkişaflı xüsusiyyətlərdə Spring AI daha irəlidədir.**
