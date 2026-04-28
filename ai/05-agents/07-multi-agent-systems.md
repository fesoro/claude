# Çox Agentli Sistemlər (Lead)

## Niyə Bir Neçə Agent?

Tək agent fundamental məhdudiyyətlərlə üzləşir: kontekst pəncərəsinin məhdudiyyətləri, ardıcıl emal, generalist olmanın yükü. Çox agentli sistemlər mürəkkəb tapşırıqları ixtisaslaşmış, paralel vahidlərə bölür — yüksək performanslı insan komandasının işlədiyi kimi.

Üç əsas üstünlük:
1. **Paralelləşdirmə**: müstəqil alt tapşırıqlar eyni anda icra edilir, ümumi vaxt azalır
2. **İxtisaslaşma**: bir sahə üçün optimize edilmiş hər agent generalisdən daha yaxşı nəticə verir
3. **Miqyas**: bir kontekst pəncərəsi üçün çox böyük problemlər bir çoxuna paylanır

---

## Orkestratorçu-Alt Agent Patterni

İstehsalat çox agentli sistemlərinin dominant patterni. Bir orkestratorçu LLM ixtisaslaşmış alt agentlər komandası idarə edir.

```
                    ┌─────────────────┐
                    │  ORKESTRATORÇU  │
                    │                 │
                    │ - Məqsəd alır   │
                    │ - Addım planlar │
                    │ - Tapşırır      │
                    │ - Birləşdirir   │
                    └────────┬────────┘
                             │ tapşırıqları həvalə edir
              ┌──────────────┼──────────────┐
              │              │              │
              ▼              ▼              ▼
     ┌──────────────┐ ┌──────────────┐ ┌──────────────┐
     │  ALT AGENT A │ │  ALT AGENT B │ │  ALT AGENT C │
     │              │ │              │ │              │
     │  Veb Axtarış │ │  Xülasə      │ │  Fakt Yoxla  │
     │  Mütəxəssisi │ │  Mütəxəssisi │ │  Mütəxəssisi │
     └──────────────┘ └──────────────┘ └──────────────┘
```

**Orkestratorçunun məsuliyyətləri**:
- Məqsədi alt tapşırıqlara bölmək
- Hər alt tapşırığı hansı agentin idarə edəcəyini müəyyən etmək
- Asılılıqları idarə etmək (agent B-nin agent A-nın çıxışına ehtiyacı var)
- Uğursuzluqları idarə etmək (yenidən cəhd et, yenidən tap, ya da dayandır)
- Nəticələri ardıcıl son çıxışda birləşdirmək

**Alt agentin məsuliyyətləri**:
- Bir xüsusi tapşırıq növünü icra etmək
- Nəticələri ya da xətaları orkestratorçuya bildirmək
- Öz məhdudiyyətlərini bilmək və qeyri-müəyyənliyi bildirmək

---

## Ünsiyyət Patternləri

### 1. Paylaşılan Yaddaş (Lövhə Patterni)

Bütün agentlər paylaşılan vəziyyət anbarından (Redis, verilənlər bazası) oxuyur və ora yazır. Orkestratorçu koordinasiya edir, amma agentlər başqalarının istifadə etdiyi kəşfləri də yayımlaya bilər.

```
┌─────────────┐     ┌─────────────────────────────┐
│  Agent A    │────▶│       PAYLAŞILAN YADDAŞ      │
└─────────────┘     │  (Redis / Verilənlər Bazası) │
                    │                              │
┌─────────────┐     │  context:session_123 {       │
│  Agent B    │◀───▶│    research_results: [...],  │
└─────────────┘     │    fact_checks: [...],       │
                    │    current_step: "summary"   │
┌─────────────┐     │  }                           │
│  Agent C    │────▶│                              │
└─────────────┘     └─────────────────────────────┘
```

**Üstünlüklər**: agentlər ayrışmış; istənilən agent tam konteksti görə bilir; yeni agent əlavə etmək asandır.
**Çatışmazlıqlar**: yazma konfliktləri; köhnəlmiş oxumalar; səbəbiyyət zəncirini izlemek çətindir.

### 2. Mesaj Ötürmə

Agentlər strukturlaşdırılmış mesajlar vasitəsilə ünsiyyət qurur (növbələr, hadisə avtobusu). Hər agent müəyyən mesaj növlərini abunəlik edir və nəticələri yayımlayır.

```
Agent A ──▶ Növbə ──▶ Agent B ──▶ Növbə ──▶ Agent C
            │                    │
          "X üçün                "budur
           axtar"                 xülasə"
```

**Üstünlüklər**: ayrışmış, asinxron, Laravel jobs üçün təbii uyğunluq.
**Çatışmazlıqlar**: sıralama mürəkkəbliyi, mesaj zəncirlərinin sazlanması çətindir.

### 3. Birbaşa Orkestratorluq

Orkestratorçu alt agentləri birbaşa çağırır (funksiya çağırışları, API çağırışları) və nəticəni gözləyir. Sinxron və ardıcıl.

**Üstünlüklər**: sadə, proqnozlaşdırıla bilən, sazlaması asandır.
**Çatışmazlıqlar**: ardıcıl — paralel çağırışları açıq şəkildə göndərməsəniz paralelizm yoxdur.

---

## Swarm vs Hiyerarxik Agentlər

### Hiyerarxik (Orkestratorçu-Alt Agent)

Yuxarıda tək koordinator. Açıq komanda zənciri. Aşağıdakı hallarda yaxşı işləyir:
- Tapşırıqların aydın dekompozisiyası var
- Generalist planlayıcı ixtisasları nümayəndəlik üçün kifayət qədər anlaya bilir
- Vahid son çıxışa ehtiyac var

### Swarm Memarlığı

Agentlər bərabər hüquqlulardır. İstənilən agent istənilənə ötürə bilər. Sabit hiyerarxiya yoxdur.

```
Agent A ←──────────▶ Agent B
   │                    │
   │                    │
   ▼                    ▼
Agent C ←──────────▶ Agent D
```

Hər agent öz sahəsinin xaricinə çıxanda fərq edir və daha uyğun agentə ötürə bilir. OpenAI bu patterni Swarm kitabxanası ilə populyarlaşdırdı.

**Yaxşı işlədyi yer**: müştəri xidməti (hesab, texniki, satış arasında keçid), əvvəlcədən strukturu bilinməyən açıq uçlu tapşırıqlar.

**Risklər**: dövrlər, qeyri-müəyyən mülkiyyət, "isti kartof" — agentlər həll olunmayan problemləri bir-birinə ötürür.

---

## Çox Agentli Sistemlərdə Xəta Yayılması

İstehsalatda çox agentli sistemlər bu nöqtədə sizi tutacaq. Xətalar dalğa kimi yayılır.

```
Orkestratorçu
    │
    ├─ Agent A (uğur ✓) → nəticə: data_set_A
    ├─ Agent B (UĞURSUZ ✗) → nəticə: null / xəta
    └─ Agent C (uğur ✓) → nəticə: data_set_C
                │
                ▼
    Birləşdirici alır:
      - data_set_A ✓
      - null / xəta ✗  ← Bununla necə davranmaq?
      - data_set_C ✓
```

**Strategiyalar**:

1. **Sürətli uğursuzlaşma**: istənilən alt agent uğursuz olarsa, bütün işi dayandır. Sadə, mühafizəkar.
2. **Qismən nəticələr**: uğurlu olanları birləşdir, uğursuz olanları qeyd et. "Ən yaxşı cəhd" tapşırıqları üçün ən yaxşı.
3. **Geridönüşlə yenidən cəhd**: N dəfə yenidən cəhd etməzdən əvvəl uğursuz agentləri yenidən çalıştır.
4. **Ehtiyat agentlər**: mütəxəssis uğursuz olarsa, ümumi məqsədli agentə keç.
5. **Kompensasiya**: agent A uğurlu olub yan effektlər yaradandan sonra agent B uğursuz olarsa, A-nın dəyişikliklərini geri al.

---

## Çox Agentli Sistemin Həddən Artıq Olduğu Hallar

Çox agentli sistemlər cəlbedici, amma bahalı və mürəkkəbdir. Əvvəlcə bu sualları verin:

| Sual | Çox agentli əsaslandırılırmı? |
|---|---|
| Bu bir LLM çağırışında edilə bilərmi? | Xeyr |
| Bu sadə bir zəncirlə edilə bilərmi? | Xeyr |
| Tapşırıq paralel işə təbii olaraq parçalanırmı? | Bəli |
| Alt tapşırıqlar həqiqətən fərqli ixtisaslar tələb edirmi? | Bəli |
| Mürəkkəblik 2-5x daha yüksək gecikməni + xərci əsaslandırırmı? | Bəlkə |
| Komandanız paylanmış LLM uğursuzluqlarını sazlamağa hazırdırmı? | Mütləq bəli olmalıdır |

**Çox agentli istifadə etməyin**:
- Geri alma ilə sadə sual-cavab
- Tək sahəli tapşırıqlar (hətta mürəkkəb olanlar)
- Ciddi gecikm tələbləri olan tapşırıqlar (< 2 saniyə)
- Prototiplər və MVP-lər (sadə başlayın, lazım olanda mürəkkəblik əlavə edin)

---

## Laravel İmplementasiyası

### 1. OrchestratorAgent

```php
<?php

namespace App\AI\Agents;

use App\AI\Agents\Subagents\WebSearchAgent;
use App\AI\Agents\Subagents\SummarizationAgent;
use App\AI\Agents\Subagents\FactCheckAgent;
use App\AI\Memory\SharedAgentContext;
use App\Jobs\RunSubagentJob;
use Illuminate\Support\Facades\Bus;

class OrchestratorAgent
{
    public function __construct(
        private readonly SharedAgentContext $context,
        private readonly \Anthropic\Client $claude,
    ) {}

    public function run(string $goal, string $sessionId): array
    {
        // Paylaşılan konteksti ilk dəfə yükləyin
        $this->context->initialize($sessionId, [
            'goal' => $goal,
            'status' => 'planning',
            'started_at' => now()->toISOString(),
        ]);

        // Addım 1: İşi planlaşdırın
        $plan = $this->plan($goal, $sessionId);
        $this->context->set($sessionId, 'plan', $plan);

        // Addım 2: Paralel alt agent işlərini göndərin
        $results = $this->executeParallel($plan, $sessionId);

        // Addım 3: Nəticələri birləşdirin
        return $this->synthesize($goal, $results, $sessionId);
    }

    private function plan(string $goal, string $sessionId): array
    {
        $response = $this->claude->messages()->create([
            'model' => 'claude-opus-4-5',
            'max_tokens' => 1024,
            'system' => 'You are a research orchestrator. Break down the research goal into parallel subtasks. Return JSON only.',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => "Goal: {$goal}\n\nReturn a JSON array of subtasks, each with: type (web_search|summarize|fact_check), query, priority (high|medium|low).",
                ],
            ],
        ]);

        $content = $response->content[0]->text;

        // Markdown kod bloklarını sil (varsa)
        $json = preg_replace('/^```json\s*|\s*```$/m', '', trim($content));

        return json_decode($json, true) ?? [];
    }

    private function executeParallel(array $plan, string $sessionId): array
    {
        // Paralel göndərmə üçün növə görə qruplaşdırın
        $jobs = collect($plan)->map(fn($task) => new RunSubagentJob(
            sessionId: $sessionId,
            taskId: uniqid('task_'),
            type: $task['type'],
            query: $task['query'],
        ));

        // Laravel Bus batch — bütün işlər paralel icra edilir (növbə işçiləri)
        $batch = Bus::batch($jobs->all())
            ->name("research-{$sessionId}")
            ->allowFailures()
            ->dispatch();

        // Toplu iş tamamlanana qədər gözləyin (vaxt aşımı ilə)
        $timeout = 120; // saniyə
        $elapsed = 0;
        while (!$batch->fresh()->finished() && $elapsed < $timeout) {
            sleep(2);
            $elapsed += 2;
        }

        if ($elapsed >= $timeout) {
            $this->context->set($sessionId, 'warning', 'Some subagents timed out');
        }

        // Paylaşılan kontekstdən nəticələri toplayın
        return $this->context->getAll($sessionId, prefix: 'result:');
    }

    private function synthesize(string $goal, array $results, string $sessionId): array
    {
        $resultsText = collect($results)
            ->map(fn($r, $key) => "## {$key}\n{$r}")
            ->join("\n\n");

        $response = $this->claude->messages()->create([
            'model' => 'claude-opus-4-5',
            'max_tokens' => 4096,
            'system' => 'You are a research synthesizer. Combine findings into a coherent, well-structured report.',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => "Original goal: {$goal}\n\nFindings from subagents:\n{$resultsText}\n\nSynthesize these into a comprehensive research report.",
                ],
            ],
        ]);

        $report = $response->content[0]->text;

        $this->context->set($sessionId, 'final_report', $report);
        $this->context->set($sessionId, 'status', 'complete');

        return [
            'goal' => $goal,
            'session_id' => $sessionId,
            'report' => $report,
            'sub_results' => $results,
            'completed_at' => now()->toISOString(),
        ];
    }
}
```

### 2. Redis Vasitəsilə Paylaşılan Kontekst

```php
<?php

namespace App\AI\Memory;

use Illuminate\Support\Facades\Redis;

class SharedAgentContext
{
    private const TTL = 3600; // 1 saat

    public function initialize(string $sessionId, array $data): void
    {
        $key = $this->baseKey($sessionId);
        Redis::hmset($key, array_map('json_encode', $data));
        Redis::expire($key, self::TTL);
    }

    public function set(string $sessionId, string $field, mixed $value): void
    {
        Redis::hset($this->baseKey($sessionId), $field, json_encode($value));
        // Hər yazmada TTL-i yeniləyin
        Redis::expire($this->baseKey($sessionId), self::TTL);
    }

    public function get(string $sessionId, string $field): mixed
    {
        $value = Redis::hget($this->baseKey($sessionId), $field);
        return $value ? json_decode($value, true) : null;
    }

    public function getAll(string $sessionId, string $prefix = ''): array
    {
        $all = Redis::hgetall($this->baseKey($sessionId));

        return collect($all)
            ->filter(fn($v, $k) => str_starts_with($k, $prefix))
            ->mapWithKeys(fn($v, $k) => [
                str_replace($prefix, '', $k) => json_decode($v, true),
            ])
            ->toArray();
    }

    public function append(string $sessionId, string $field, mixed $item): void
    {
        $existing = $this->get($sessionId, $field) ?? [];
        $existing[] = $item;
        $this->set($sessionId, $field, $existing);
    }

    public function delete(string $sessionId): void
    {
        Redis::del($this->baseKey($sessionId));
    }

    private function baseKey(string $sessionId): string
    {
        return "agent:context:{$sessionId}";
    }
}
```

### 3. RunSubagentJob

```php
<?php

namespace App\Jobs;

use App\AI\Agents\Subagents\WebSearchAgent;
use App\AI\Agents\Subagents\SummarizationAgent;
use App\AI\Agents\Subagents\FactCheckAgent;
use App\AI\Memory\SharedAgentContext;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;

class RunSubagentJob implements ShouldQueue
{
    use Batchable, Queueable, InteractsWithQueue;

    public int $tries = 3;
    public int $timeout = 60;

    public function __construct(
        public readonly string $sessionId,
        public readonly string $taskId,
        public readonly string $type,
        public readonly string $query,
    ) {}

    public function handle(SharedAgentContext $context): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        try {
            $agent = match ($this->type) {
                'web_search' => app(WebSearchAgent::class),
                'summarize'  => app(SummarizationAgent::class),
                'fact_check' => app(FactCheckAgent::class),
                default      => throw new \InvalidArgumentException("Unknown agent type: {$this->type}"),
            };

            $result = $agent->run($this->query, $this->sessionId);

            // Nəticəni prefix'li açar ilə paylaşılan kontekstdə saxlayın
            $context->set(
                $this->sessionId,
                "result:{$this->type}:{$this->taskId}",
                $result,
            );

            // Tamamlanmış tapşırıqları izləyin
            $context->append($this->sessionId, 'completed_tasks', [
                'task_id' => $this->taskId,
                'type' => $this->type,
                'completed_at' => now()->toISOString(),
            ]);
        } catch (\Throwable $e) {
            // Uğursuzluğu qeydə alın, amma yenidən atmayın — batch allowFailures() istifadə edir
            $context->append($this->sessionId, 'failed_tasks', [
                'task_id' => $this->taskId,
                'type' => $this->type,
                'error' => $e->getMessage(),
                'failed_at' => now()->toISOString(),
            ]);

            // İşin yenidən cəhd məntiqi üçün yenidən atın
            throw $e;
        }
    }
}
```

### 4. İxtisaslaşmış Alt Agentlər

```php
<?php

namespace App\AI\Agents\Subagents;

use App\AI\Memory\SharedAgentContext;

class WebSearchAgent
{
    public function __construct(
        private readonly \Anthropic\Client $claude,
        private readonly SharedAgentContext $context,
    ) {}

    public function run(string $query, string $sessionId): string
    {
        $response = $this->claude->messages()->create([
            'model' => 'claude-haiku-4-5', // Axtarış tapşırıqları üçün sürətli, ucuz model
            'max_tokens' => 2048,
            'tools' => [
                [
                    'name' => 'web_search',
                    'description' => 'Search the web for current information',
                    'input_schema' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => ['type' => 'string', 'description' => 'Search query'],
                        ],
                        'required' => ['query'],
                    ],
                ],
            ],
            'messages' => [
                [
                    'role' => 'user',
                    'content' => "Search for information about: {$query}. Return a concise summary of the most relevant findings.",
                ],
            ],
        ]);

        // Real implementasiyada alət çağırışlarını burada idarə edin
        // Bu nümunə üçün axtarış nəticəsini simulyasiya edirik
        return $response->content[0]->text ?? 'No results found';
    }
}

class SummarizationAgent
{
    public function __construct(
        private readonly \Anthropic\Client $claude,
    ) {}

    public function run(string $content, string $sessionId): string
    {
        $response = $this->claude->messages()->create([
            'model' => 'claude-haiku-4-5',
            'max_tokens' => 1024,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => "Summarize the following into 3-5 key bullet points:\n\n{$content}",
                ],
            ],
        ]);

        return $response->content[0]->text;
    }
}

class FactCheckAgent
{
    public function __construct(
        private readonly \Anthropic\Client $claude,
    ) {}

    public function run(string $claim, string $sessionId): string
    {
        $response = $this->claude->messages()->create([
            'model' => 'claude-sonnet-4-5', // Fakt yoxlama üçün daha yaxşı mühakimə
            'max_tokens' => 1024,
            'system' => 'You are a rigorous fact-checker. Evaluate claims for accuracy. Be specific about confidence levels.',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => "Fact-check this claim: {$claim}\n\nProvide: verdict (TRUE/FALSE/PARTIALLY TRUE/UNVERIFIABLE), confidence (0-100%), and reasoning.",
                ],
            ],
        ]);

        return $response->content[0]->text;
    }
}
```

### 5. Nəticə Birləşdirmə və API Endpoint

```php
<?php

namespace App\Http\Controllers;

use App\AI\Agents\OrchestratorAgent;
use App\AI\Memory\SharedAgentContext;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ResearchController extends Controller
{
    public function __construct(
        private readonly OrchestratorAgent $orchestrator,
        private readonly SharedAgentContext $context,
    ) {}

    public function research(Request $request): JsonResponse
    {
        $request->validate([
            'goal' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        $sessionId = uniqid('research_', more_entropy: true);

        // Asinxron üçün: orkestratorçunu iş kimi göndərin
        // Sinxron üçün (demo): birbaşa çalıştırın
        $result = $this->orchestrator->run(
            goal: $request->string('goal'),
            sessionId: $sessionId,
        );

        return response()->json([
            'session_id' => $sessionId,
            'result' => $result,
        ]);
    }

    public function status(string $sessionId): JsonResponse
    {
        $status = $this->context->get($sessionId, 'status');
        $completed = $this->context->get($sessionId, 'completed_tasks') ?? [];
        $failed = $this->context->get($sessionId, 'failed_tasks') ?? [];

        return response()->json([
            'session_id' => $sessionId,
            'status' => $status,
            'completed_tasks' => count($completed),
            'failed_tasks' => count($failed),
            'plan' => $this->context->get($sessionId, 'plan'),
        ]);
    }
}
```

### 6. Service Provider Qeydiyyatı

```php
<?php

namespace App\Providers;

use App\AI\Agents\OrchestratorAgent;
use App\AI\Memory\SharedAgentContext;
use Illuminate\Support\ServiceProvider;

class AgentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SharedAgentContext::class);

        $this->app->bind(OrchestratorAgent::class, function ($app) {
            return new OrchestratorAgent(
                context: $app->make(SharedAgentContext::class),
                claude: $app->make(\Anthropic\Client::class),
            );
        });
    }
}
```

---

## Real Dünya Nümunəsi: Tədqiqat Agenti

Tam axın: *"Avropa Birliyində AI tənzimlənməsinin cari vəziyyətini araşdırın"*

```
1. Orkestratorçu məqsədi alır
   └─ Planı yaratmaq üçün Claude'u çağırır:
      [
        { type: "web_search", query: "EU AI Act 2025 latest updates" },
        { type: "web_search", query: "EU AI regulation enforcement timeline" },
        { type: "web_search", query: "EU AI Act prohibited practices list" },
        { type: "fact_check", query: "EU AI Act came into force August 2024" },
        { type: "summarize", query: "key obligations for AI providers under EU AI Act" }
      ]

2. Bütün 5 iş eyni anda növbəyə göndərilir
   └─ Növbə işçiləri paralel olaraq götürür
   └─ Nəticələr tamamlandıqca Redis-ə yazılır

3. Orkestratorçu toplu iş statusunu sorğulayır
   └─ ~8 saniyə sonra 4/5 iş tamamlanır
   └─ 1 iş uğursuz oldu (vaxt aşımı) → failed_task kimi qeydə alındı

4. Birləşdirici 4 nəticə dəsti alır
   └─ Bütün nəticələrlə Claude Opus-u çağırır
   └─ Strukturlaşdırılmış hesabat qaytarır

5. Ümumi vaxt: ~12 saniyə
   (ardıcıl olsaydı ~40 saniyə olardı)
```

---

## Çox Agentli Üçün Növbə Konfiqurasiyası

```php
// config/queue.php - agent işçiləri üçün ayrılmış növbələr
'connections' => [
    'redis' => [
        'driver' => 'redis',
        'queues' => [
            'agent-orchestrator',  // Yüksək prioritet — orkestratorçular
            'agent-subagent',      // Normal prioritet — alt agentlər
            'agent-synthesis',     // Normal prioritet — son sintez
        ],
    ],
],
```

```bash
# Supervisor konfiqurasiyası — paralel olaraq bir neçə alt agent işçisi çalıştır
[program:agent-subagent-worker]
command=php artisan queue:work redis --queue=agent-subagent --sleep=1 --tries=3
numprocs=8  # 8 paralel alt agent işçisi
autostart=true
autorestart=true
```

---

## Memarlıq Mülahizələri

### Kontekst İzolasiyası

Hər tədqiqat sessiyası öz Redis ad sahəsini alır (`agent:context:{sessionId}`). Bu, eyni vaxtda çalışan sessiyalar arasında çapraz çirklənməni önləyir və təmizliyi sadələşdirir: tamamlandıqda ad sahəsini silin.

### İdempotentlik

İşlər idempotent olmalıdır — əgər iş iki dəfə çalışırsa (uğursuzluq + yenidən cəhd səbəbindən), ikinci dəfə eyni nəticəni yan effektlər olmadan verməlidir. `taskId`-ni nəticə açarı kimi istifadə etmək dublikat nəticələrin üzərinə yazılmasını, təkrarlanmamasını təmin edir.

### Xərc İdarəetməsi

Çox agentli sistemlər LLM xərclərini çoxaldır. Tədqiqat nümunəsi üçün:
- 3 veb axtarış çağırışı (Haiku) ≈ hərəsi $0.003
- 1 fakt yoxlama çağırışı (Sonnet) ≈ $0.015
- 1 sintez çağırışı (Opus) ≈ $0.075
- Tədqiqat tapşırığı başına ümumi ≈ $0.10

Miqyasda (gündə 1,000 tədqiqat tapşırığı), bu gündəlik $100-dir. Xərc izləməni ilk gündən quruyun.

### Circuit Breaker Patterni

Xarici API aşdıqda kaskadlı uğursuzluqlardan qorunun:

```php
use Illuminate\Support\Facades\Cache;

class SubagentCircuitBreaker
{
    public function isOpen(string $agentType): bool
    {
        $failures = Cache::get("circuit:{$agentType}:failures", 0);
        return $failures >= 5; // 5 ardıcıl uğursuzluqdan sonra açıq
    }

    public function recordFailure(string $agentType): void
    {
        Cache::increment("circuit:{$agentType}:failures");
        Cache::put("circuit:{$agentType}:failures",
            Cache::get("circuit:{$agentType}:failures"),
            ttl: 60 // 60 saniyə sonra avtomatik sıfırlanır
        );
    }

    public function recordSuccess(string $agentType): void
    {
        Cache::forget("circuit:{$agentType}:failures");
    }
}
```

---

## Praktik Tapşırıqlar

### Tapşırıq 1: Orchestrator + Worker Pattern

`ResearchOrchestrator` implement et: 1 orchestrator + 3 worker agent (search, summarize, format). Orchestrator hər worker-ə tapşırığı dağıdır, nəticələri toplayır. 5 research tapşırığı üçün çalışdır. Tək agent vs multi-agent-in keyfiyyət + cost müqayisəsini apar.

### Tapşırıq 2: Circuit Breaker Test

`AgentCircuitBreaker`-i test et: worker agent 3 dəfə ardıcıl fail olsun (mock exception at). Circuit `open` vəziyyətinə keçirmi? `open` halda orchestrator-un alternativ worker ya da fallback response-a keçdiyini yoxla.

### Tapşırıq 3: Shared State via Database

`agent_shared_state` cədvəli üçün: orchestrator bir agent-in nəticəsini yazır, digər agent həmin nəticəni oxuyub işləyir. Race condition testi: iki agent eyni anda `state`-i güncəlləməyə çalışır. `SELECT FOR UPDATE` ilə lock tətbiq et, problemi həll et.

---

## Əlaqəli Mövzular

- `08-agent-orchestration-patterns.md` — Supervisor, Swarm, Blackboard, Pipeline pattern-ləri
- `05-build-custom-agent-laravel.md` — Tək agent — multi-agent-in building block-u
- `09-human-in-the-loop.md` — Multi-agent sistemdə HITL checkpoint
- `13-agent-security.md` — Agent-lərarası trust boundary-lər
