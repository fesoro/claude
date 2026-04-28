# AI Product UX Patterns — İstifadəçi Təcrübəsinin Yeni Qrammatikası (Senior)

> Hədəf auditoriyası: AI məhsullarını real istifadəçilərə gətirən senior developer-lər, product designer-lər və product manager-lər. "Niyə chat-im boş hiss verir?", "İstifadəçi niyə ilk cəhddən sonra gedir?" suallarına cavab axtaranlar.

> Tarix: 2026-04-21. Referans modellər: `claude-sonnet-4-5`, `claude-haiku-4-5`. Referans məhsullar: Linear, Notion, Cursor, Claude Code, Perplexity, Intercom Fin, GitHub Copilot.

---

## Mündəricat

1. [AI UX-in Yeni Qrammatikası](#new-grammar-of-ai-ux)
2. [Streaming — Gözləmənin Psixologiyası](#streaming-psychology)
3. [Typing Indicators və Natural Feel](#typing-indicators)
4. [Cancel Button — Must-have, Nice-to-have Yox](#cancel-button)
5. [Retry və Regenerate](#retry-regenerate)
6. [Feedback — Thumbs Up/Down-dan Sonrası](#feedback-loops)
7. [Confidence Indicators](#confidence-indicators)
8. [Citations və Source Attribution](#citations)
9. [Graceful Degradation](#graceful-degradation)
10. [Undo AI Output](#undo-ai-output)
11. ["AI Wrote This" Etiketləri və Disclosure](#ai-disclosure)
12. [Progressive Disclosure](#progressive-disclosure)
13. [Multi-turn Refinement və Continuity](#multi-turn-continuity)
14. [Input Hints və Prompt Scaffolding](#input-hints)
15. [Error Messages — Recovery-focused](#error-messages)
16. [Keyboard Shortcuts və Power User](#keyboard-shortcuts)
17. [A/B Test İdeyaları](#ab-test-ideas)
18. [Anti-patterns — Etmə](#anti-patterns)
19. [Real Məhsul Nümunələri](#real-examples)
20. [Cheat Sheet və Qərar Ağacları](#cheat-sheet)

---

## AI UX-in Yeni Qrammatikası

Ənənəvi UI-də "klik-cavab" modeli var idi — düymə basırsan, server cavab verir, UI yenilənir. AI-da 3 yeni dimension əlavə olundu:

1. **Zaman** — cavab anlıq deyil, axır. Gözləmə var, stream var, yarımçıq cavab var.
2. **Qeyri-müəyyənlik** — model yanlış ola bilər, confidence gərəkdir, user güvən qurmalıdır.
3. **Agency paylaşımı** — user + AI birlikdə output yaradır. Kim müəllifdir? Kim məsuliyyətlidir?

Bu 3 dimension hər UX qərarını dəyişdirir. Ənənəvi pattern-lər ya işləmir, ya da yenidən düşünülməlidir.

### Köhnə vs Yeni UX Prinsipləri

| Köhnə SaaS                    | AI Product                         |
|-------------------------------|-------------------------------------|
| Loading spinner               | Stream + typing indicator          |
| "Submit" button               | "Send" + "Cancel" + "Regenerate"   |
| Single source of truth         | Model + user drafts, versiyalar    |
| Error: "Try again"            | Error: "Try with less context" + fallback |
| Predictable latency           | Variable 2-30s, user signalization gərəkdir |
| Binary success/failure         | Quality spectrum + confidence      |

### Fundamental UX Qanunu: Expectation Setting

İstifadəçi AI-a nə vaxt güvənəcəyini, nə vaxt şübhələnəcəyini bilmir. UX-in əsas işi: **gözləmələri tənzimləmək**.

- Əgər model 95% dəqiqdirsə, UX "əminlik" verir
- Əgər 70% dəqiqdirsə, UX "təsdiq lazımdır" sinyal verir
- Əgər 50% dəqiqdirsə, UX "brainstorming" kimi framework-lər (bir-neçə variant göstər)

---

## Streaming — Gözləmənin Psixologiyası

### Niyə Streaming?

Bir nümunə götürək. User prompt göndərir, cavab 8 saniyə çəkir.

**Scenario A (no streaming)**:
- 0s: "Loading..." spinner
- 8s: bütün cavab birdəfəyə görünür
- User hissi: uzun, ölü, çıxmaq istəyi

**Scenario B (streaming)**:
- 0s: ilk token 400ms sonra gəlir
- 0.4s - 8s: sözlər axır, user oxumağa başlayır
- User hissi: canlı, dinamik, diqqət cəlb edilib

### Ölçülmüş Effekt

Anthropic-in 2024 A/B test nəticəsi (publik olmayan, amma Discord-da bəhs olunan):
- Streaming ilə session length +35%
- Abandon rate -28%
- "Response feels fast" rating +52% (əslində eyni total time)

Bu **Peak-End Rule**-un təzahürüdür: insanlar təcrübəni **peak** və **end** anlarına görə xatırlayır. Streaming-də "peak" yoxdur (gərgin gözləmə yoxdur), "end" yumşaqdır.

### Laravel-də Streaming Implementasiya

```php
// app/Http/Controllers/ChatController.php
public function stream(Request $request): StreamedResponse
{
    $message = $request->input('message');
    $history = $request->input('history', []);

    return response()->stream(function () use ($message, $history) {
        $client = new Anthropic\Client(config('services.anthropic.key'));
        $stream = $client->messages()->createStream([
            'model' => 'claude-sonnet-4-5',
            'max_tokens' => 2048,
            'messages' => [...$history, ['role' => 'user', 'content' => $message]],
        ]);

        foreach ($stream as $event) {
            if ($event->type === 'content_block_delta') {
                $chunk = $event->delta->text ?? '';
                echo "data: " . json_encode(['chunk' => $chunk]) . "\n\n";
                ob_flush();
                flush();
            }
        }

        echo "data: [DONE]\n\n";
    }, 200, [
        'Content-Type'      => 'text/event-stream',
        'Cache-Control'     => 'no-cache',
        'X-Accel-Buffering' => 'no',
    ]);
}
```

### Streaming UX Detalları

1. **İlk token latency** < 500ms hədəf. Üstündə user "loading" hiss edir.
2. **Smooth rendering** — hər 50ms-də buffer flush et, char-by-char əvəzinə word-by-word.
3. **Autoscroll** — user aşağı skroll edərkən saxla, amma "scroll to bottom" düyməsi göstər.
4. **Markdown-ı yarımçıq render et** — `**bol` yazılanda, hələ partial bold göstər.

### Anti-pattern: Fake Streaming

Bəzi məhsullar full cavabı bir-başa alıb, sonra "stream kimi" göstərir. Bu **tex etik deyil** — sürət illüziyası verirsən, amma user real sürət qazanmır.

**Qayda**: əgər backend full cavab alırsa, UI-da **anında göstər**, fake stream-ləmə.

---

## Typing Indicators və Natural Feel

Typing indicator (üç nöqtə, sinyal) user-ə "sistem işləyir" deyir.

### Variantlar

1. **Sadə 3-nöqtə** (iMessage stili): `. . .`
2. **Brand-ed loader**: Claude.ai-də "thinking" kimi incə dalğalanma
3. **Fase-aware**:
   - "Düşünürəm..." (initial)
   - "Mənbələri yoxlayıram..." (tool use)
   - "Cavabı yazıram..." (generation)

### Phase-aware Typing (Claude Code və Cursor-da)

Cursor terminal-da bu mərhələləri göstərir:
```
⠋ Reading file src/App.tsx...
⠙ Planning changes...
⠹ Applying edit...
✓ Done (2.1s)
```

Bu user-ə **mental model** verir: AI nə edir, gözlə.

### Linear AI-nin Approach-u

Linear-də "Ask AI" açanda:
- Typing indicator yerinə **pulse effect** var (box-shadow yumşaq dalğa)
- Text "Thinking" sonra "Drafting" kimi dəyişir
- Çox minimal, Linear-in enterprise həsi ilə uyğun

### Typing Indicator Göstərməyin Qaydaları

- **Göstər**: əgər initial latency > 200ms
- **Göstərmə**: əgər cache hit-dir və < 100ms cavab gələcək
- **Phase-aware göstər**: multi-step operation (agent, RAG) üçün

---

## Cancel Button — Must-have, Nice-to-have Yox

### Niyə Kritikdir?

User prompt göndərir, 2 saniyə sonra səhv olduğunu başa düşür. Əgər cancel yoxdursa:
- 20 saniyə gözləmək məcburiyyətindədir
- Frustration artır
- Token-lər hədər gedir
- User sonrakı dəfə ya prompt-u mükəmməl formulə etməyə çalışır (paralysis), ya da AI istifadəsindən çəkinir

### Claude Code-da Cancel

Claude Code-da `ESC` düyməsi dərhal iti kəsir:
```
> Write 1000 tests for the codebase
  ⠋ Analyzing 47 files...
  [ESC pressed]
  ✗ Cancelled. Partial analysis available.
```

Bu yalnız UX-də deyil, **biznes mənada** vacibdir — token qənaəti.

### PHP-də Server-Side Cancel

```php
// app/Services/AI/StreamingCancellable.php
class StreamingCancellable
{
    public function stream(string $prompt, string $sessionId): \Generator
    {
        $cancelKey = "ai:cancel:{$sessionId}";

        $stream = $this->client->messages()->createStream([...]);

        foreach ($stream as $event) {
            // Hər chunk-da cancel flag yoxla
            if (Redis::get($cancelKey)) {
                Redis::del($cancelKey);
                $stream->close(); // upstream SSE bağla
                yield ['type' => 'cancelled'];
                return;
            }
            yield $event;
        }
    }
}

// Controller
public function cancel(Request $request)
{
    Redis::setex("ai:cancel:{$request->session_id}", 60, '1');
    return response()->json(['cancelled' => true]);
}
```

### Cancel Button UX Prinsipləri

1. **Görünən olsun** — streaming anında həmişə display
2. **Keyboard shortcut** — `ESC` və ya `Cmd+.`
3. **Visual feedback** — basılanda "Cancelling..." göstər, 200ms sonra kəs
4. **Partial content göstər** — əgər 50% cavab gəldisə, onu saxla və "Cancelled here" göstər
5. **Retry təklifi** — "Cancelled. Retry with different prompt?"

### Anti-pattern: Greyed-out Cancel

Bəzi məhsullarda cancel düyməsi "disabled" görünür. Bu user-i çıxılmaz hiss etdirir. Həmişə **açıq və aktiv** saxla.

---

## Retry və Regenerate

"Regenerate" həyat qurtarandır. AI yanlış cavab verəndə, user ikinci şans istəyir.

### ChatGPT/Claude.ai-nin Yanaşması

Hər cavabın altında:
- Regenerate (eyni prompt, yeni seed)
- Edit prompt (prompt-u düzəlt və yenidən göndər)
- Copy
- Thumbs up/down

### Regenerate Options

```
[Regenerate ▼]
├── Try again (same prompt, different seed)
├── Use different model (Haiku / Sonnet / Opus)
├── Make it shorter
├── Make it more detailed
└── More formal tone
```

Bu, user-ə AI ilə **discourse** gücünü verir. "Istədiyim deyil" → bir klik → fərqli cavab.

### Implementation Qeydləri

```php
// Regenerate endpoint
public function regenerate(Request $request)
{
    $messageId = $request->input('message_id');
    $hint = $request->input('hint'); // "shorter" | "more_formal" | null

    $message = Message::findOrFail($messageId);
    $history = $message->conversation->messages()
        ->where('created_at', '<', $message->created_at)
        ->get();

    $prompt = $history->last()->content;

    if ($hint === 'shorter') {
        $prompt .= "\n\nKeep the answer under 100 words.";
    } elseif ($hint === 'more_formal') {
        $prompt .= "\n\nRespond in formal, professional tone.";
    }

    // Model-ə yeni seed ilə göndər (temperature ↑)
    return $this->aiService->stream($prompt, [
        'temperature' => 1.0, // diversity
    ]);
}
```

### Cursor-da Regenerate

Cursor-da kod AI yazanda:
- "Accept" / "Reject" / "Regenerate"
- Regenerate-da fərqli model də təklif edir
- History — son 5 generasiyadan hansını istəyirsən seç

### UX Qaydaları

1. **Regenerate ilk 10 saniyədə elə asan** olsun (big button), sonra secondary yerə keç
2. **Regenerate limitsiz**, ancaq 5-dən sonra cost warning göstər
3. **Previous draft-u itirmə** — versiya tarixçəsi saxla

---

## Feedback — Thumbs Up/Down-dan Sonrası

Thumbs up/down toplanması asandır. Amma "niyə?" mühümdür.

### 3-Qat Feedback Modeli

**Qat 1 — Passive signal**:
- User cavabı kopyaladı? Positive signal.
- User cavabı silib reword etdi? Negative signal.
- User 30s sonra yenə prompt yazdı? Probably satisfied.

**Qat 2 — Active binary**:
- Thumbs up: "Yaxşıdır"
- Thumbs down: "Bəyənmədim"

**Qat 3 — Qualitative**:
- Thumbs down-dan sonra dropdown: "Yanlış idi", "Tam cavab vermədi", "Format problemi", "Çox uzun idi"
- Optional text input

### Linear AI-nin Feedback Mechanics

Linear-də AI cavab verəndən sonra:
1. Düymələr: "Helpful" / "Not helpful"
2. Not helpful basanda 4 chip: "Off-topic", "Wrong data", "Too vague", "Other"
3. "Other"-də text input

### Feedback Nə İşə Yarayır?

Feedback-siz sen sadəcə ümid edirsən. Feedback-lə:

1. **Model selection** — konkret task-da hansı model daha yaxşı (Sonnet vs Haiku)
2. **Prompt iteration** — feedback negative-də prompt-un hansı hissəsi problemdir
3. **Retrieval qualityy** — citations thumbs-down çox alır? RAG-i fix et
4. **Training signal** — future fine-tuning üçün dataset

### Feedback Loop Misalı (Laravel)

```php
// app/Models/AiFeedback.php
class AiFeedback extends Model
{
    protected $fillable = [
        'message_id',
        'user_id',
        'rating',       // 1 = up, 0 = down
        'category',     // 'wrong', 'vague', 'format', etc.
        'comment',
        'context_tokens',
        'output_tokens',
        'model',
        'latency_ms',
    ];
}

// Feedback controller
public function submit(Request $request)
{
    $feedback = AiFeedback::create([
        'message_id' => $request->message_id,
        'user_id'    => auth()->id(),
        'rating'     => $request->rating,
        'category'   => $request->category,
        'comment'    => $request->comment,
        'model'      => $request->model,
        'latency_ms' => $request->latency,
    ]);

    // Low rating → alert trigger
    if ($request->rating === 0 && $request->category === 'wrong') {
        dispatch(new FlagForReview($feedback));
    }

    return response()->json(['ok' => true]);
}
```

### Weekly Feedback Report

Har həftə email-də team-ə:
```
Weekly AI Feedback:
  Total responses: 12,450
  Thumbs up: 78%
  Thumbs down: 12%  (10% no feedback)

  Top negative categories:
    - Wrong data: 45% (RAG issue?)
    - Too vague: 28% (prompt needs specificity)
    - Format: 15%

  Model breakdown:
    Sonnet: 82% up
    Haiku: 71% up
```

---

## Confidence Indicators

Bəzən AI cavab verir, amma bilir ki, cavab zəifdir. Bunu user-ə çatdırmaq — güvən qurmanın mərkəzi faktorudur.

### Confidence Göstərmək Yolları

1. **Explicit**: "I'm not sure, but..." (prompt engineering)
2. **Visual**: rəng gradientə görə (yaşıl = yüksək, sarı = orta)
3. **Probabilistic**: "85% sure"
4. **Semantic**: "Based on general knowledge" vs "Based on your document"

### Claude Code və Cursor-da Confidence

Cursor-da edit təklif olunanda:
- Yüksək confidence: yaşıl highlight, "Apply" default
- Orta: sarı highlight, "Review" tövsiyə
- Aşağı: boz, "Suggestion only, verify"

### PHP İmplementasiya Fikri

```php
// System prompt-da modelə confidence ifadə etməyi öyrət
$systemPrompt = <<<PROMPT
Answer the user's question based on the provided context.

Before your answer, output a confidence score:
<confidence>0.0-1.0</confidence>

- 0.9+: context has direct, explicit answer
- 0.7-0.9: context has related info, mild inference needed
- 0.5-0.7: context has partial info, significant inference
- <0.5: context insufficient, say so explicitly
PROMPT;

// Response parsing
preg_match('/<confidence>([\d.]+)<\/confidence>/', $response, $m);
$confidence = (float) ($m[1] ?? 0.5);

// UI render
if ($confidence < 0.5) {
    echo '<div class="warning">I am not confident about this answer.</div>';
}
```

### Confidence Indicator Pitfall-ları

1. **Bəzi modellər fake-confidence verir** — həmişə 0.95 yazır. Validasiya lazımdır.
2. **User "confidence"-in mənasını anlamır** — "85% nə deməkdir?" Başa düşməyə kömək et.
3. **Low confidence demək AI-ın istifadəsini azalda bilər**. Amma güvən uzunmüddətli qalib olur.

---

## Citations və Source Attribution

AI cavab verdi, user soruşur: "Bunu haradan bildin?"

### Perplexity-nin Gold Standard

Perplexity hər iddia üçün inline citation verir:
```
Istanbul Turkey-nin ən böyük şəhəridir[1]. Əhalisi 15.8 milyondan çoxdur[2].

Sources:
[1] Wikipedia — Istanbul
[2] TurkStat 2024 Population Report
```

Bu user-ə:
- Faktı yoxlamaq imkanı
- Güvən və nüfuz
- Hallucination-ı tez aşkarlama

### Citation Implementation Patterns

**Pattern A — Inline [1][2]**:
```
The CAP theorem states consistency, availability, and
partition tolerance cannot all be guaranteed[1].
```

**Pattern B — Hover cards**:
```
The CAP theorem states [consistency, availability, and
partition tolerance] cannot all be guaranteed.
  ↑ hover → source card popups
```

**Pattern C — Side panel**:
Cavab solda, sağda "Sources used" paneli.

### RAG-lı Sistemdə Citation

```php
// RAG pipeline
$docs = $this->retriever->search($query, limit: 5);

$contextWithIds = collect($docs)
    ->map(fn($d, $i) => "[{$i}] {$d->title}\n{$d->content}")
    ->join("\n\n");

$systemPrompt = <<<PROMPT
Answer based on these documents. Cite sources using [0], [1] etc
immediately after claims. If info is not in sources, say so.

Documents:
{$contextWithIds}
PROMPT;

// Cavabı parse edib citation-ları real link-lərə çevir
$html = preg_replace_callback(
    '/\[(\d+)\]/',
    fn($m) => '<a href="' . $docs[$m[1]]->url . '">[' . $m[1] . ']</a>',
    $response
);
```

### Citation UX Prinsipləri

1. **Inline >> end of text** — user aktif oxuyarkən görür
2. **Link tap edə bilmək lazımdır** — mənbə görmək asan olsun
3. **Qeyri-sadiqlik dərhal aşkar** — əgər [1] dedikdə mənbədə o məlumat yoxdursa, user itibarı itirir
4. **"No source" hal da ifadə olunsun** — "This is my general knowledge, not from your docs"

---

## Graceful Degradation

AI xidməti cütür: API down, rate limit, token overflow, slow response. UX bu halları göz önünə alsın.

### Degradation Səviyyələri

**Level 0 — Everything works**: Sonnet + full features

**Level 1 — Slow model response** (>10s): typing indicator güclü, "Still thinking" message

**Level 2 — API rate limit**: fallback Haiku-ya, banner "Using faster model due to high demand"

**Level 3 — API down**: fallback-to-rules (basic template answers), "AI unavailable, showing basic response"

**Level 4 — Total failure**: feature hide və ya "We'll email you when ready"

### Laravel-də Circuit Breaker

```php
// app/Services/AI/AiCircuitBreaker.php
class AiCircuitBreaker
{
    public function call(callable $fn, callable $fallback)
    {
        $state = Cache::get('ai:circuit:state', 'closed');

        if ($state === 'open') {
            return $fallback();
        }

        try {
            $result = $fn();
            Cache::forget('ai:circuit:failures');
            return $result;
        } catch (\Throwable $e) {
            $failures = Cache::increment('ai:circuit:failures');
            if ($failures > 5) {
                Cache::put('ai:circuit:state', 'open', now()->addMinutes(2));
            }
            Log::warning('AI call failed', ['error' => $e->getMessage()]);
            return $fallback();
        }
    }
}

// İstifadə
$result = $circuitBreaker->call(
    fn() => $this->claudeService->chat($prompt),
    fn() => $this->templateService->basicAnswer($prompt),
);
```

### Notion-un Graceful Degradation

Notion AI down olanda:
- Banner: "AI temporarily unavailable. Try again in a moment."
- "AI" düyməsi görünür amma basılanda toast: "Retrying..."
- User UI-ni tərk etmir, məhsul çökmür

### UX Qaydaları

1. **Silent fallback etmə** — user bilməlidir
2. **Retry asan olsun** — 1 klik
3. **ETA ver mümkün olduqda** — "Back in 5 minutes"
4. **Başqa feature bloklama** — AI down olmağı bütün Notion-u öldürməsin

---

## Undo AI Output

AI yazdı — user istəmir. Undo imkanı olmalıdır.

### Cursor-un Approach-u

AI kod yazanda:
- Diff view — əvvəl/sonra göstər
- "Reject" düyməsi — tamamilə geri qaytar
- "Accept partial" — hissə-hissə qəbul
- `Cmd+Z` — standart undo-da da işləyir

### Notion AI-nin Approach-u

AI paragrafı yazanda:
- Yeni paragraf "with AI" marker-lı görünür
- `Cmd+Z` undo (native Notion undo)
- "Discard AI content" düyməsi panel-də

### Linear-in Approach-u

Linear-də AI bir issue generate edəndə:
- Draft mode-da görünür
- "Create issue" basılana qədər save olmur
- User free şəkildə edit edib atabilər

### UX Prinsipi

**AI output committed deyildir, draft-dır.** User "accept" etməsə, yox olmalıdır. Bu AI-nin insan təsdiqinə ehtiyac duyduğunu vurğulayır.

### Implementation Fikri

```php
// AI-nin output-u "pending" state-də saxlanır
class AiDraft extends Model
{
    protected $fillable = [
        'user_id',
        'feature',
        'content',
        'state', // pending | accepted | rejected
        'ai_metadata', // model, tokens, latency
    ];
}

// Accept
$draft->update(['state' => 'accepted']);
// → Real saxlanmaya push et (document, issue, etc.)

// Reject
$draft->update(['state' => 'rejected']);
// → Feedback qeydə al (niyə reject edildi?)
```

---

## "AI Wrote This" Etiketləri və Disclosure

Etika və user güvəni baxımından, AI output-u AI tərəfindən yaradıldığını göstərmək lazımdır.

### Different Contexts, Different Labels

**B2C chat (Claude.ai, ChatGPT)**: hər cavab AI-dəndir, explicit label lazım deyil.

**Mixed content (Notion, Linear)**: user-in yazdığı vs AI yazdığı qarışır — label vacib.

**Professional output (code review, PRs)**: "Co-authored by AI" və ya bot signature.

### Visual Design Options

**Option A — Icon + label**:
```
[AI icon] AI-generated • 2 min ago
```

**Option B — Subtle tint**:
AI-yazdığı paragraf fərqli arxaplan rəngində (light purple tint).

**Option C — Attribution line**:
```
---
Suggested by Claude Sonnet 4.5 • Verified by @alex
```

### Legal Considerations (2026)

Bəzi yurisdiksiyalar AI disclosure-u qanunlaşdırıb:
- EU AI Act — "synthetic content" etiketlənməlidir
- California AI Transparency Act — public consumption-da disclosure
- Çin PIPL — generative content-də AI mənşəyi

**Qayda**: məhsulun global qurularkən disclosure default olsun.

### Cursor-un Signature Pattern-i

Git commit-də:
```
feat: add user export API

Implements CSV export with filter support.

Co-authored-by: Claude <noreply@anthropic.com>
```

Bu həm disclosure, həm də history-də transparency verir.

---

## Progressive Disclosure

AI feature-lər kompleks ola bilər. Her şeyi bir-başa göstərmək istifadəçini qorxudur.

### Progressive Disclosure Prinsipi

Beginner user → sadə interface
Power user → advanced seçimlər

### Claude.ai-də Nümunə

**Basic interface**:
- Text input
- Send button
- History

**Advanced (açılanda)**:
- Model selector
- Temperature slider
- System prompt
- Tools toggle
- Extended thinking

Default user heç vaxt bu setting-ləri açmır. Power user-lər sevinir.

### Linear AI "Ask"-də

Sadə sual kimi başlayır:
```
[Input: Ask about anything...]
```

Kompleks sual yazanda avtomatik context-e genişlənir:
```
[Input with expanded context]
Scope: [x] This project  [ ] All projects
Time: [x] Last 30 days  [ ] All time
```

### Dizayn Qaydası

1. **Default 80% use-case-ə xidmət etsin** — advanced gizli
2. **Advanced 1 klik uzaqlıqda olsun** — "More options"
3. **Advanced seçimləri remember et** — user bir dəfə açıbsa, növbəti sessiyada açıq qalsın

---

## Multi-turn Refinement və Continuity

AI cavab verdi — amma user hələ də razı deyil. Növbəti mesajda daha precise istəyir.

### Multi-turn Refinement Nümunələri

```
User: Write a tweet about Laravel 12 new features.
AI:   [draft 1]
User: Make it more technical, less marketing-y.
AI:   [draft 2]
User: Add a code snippet.
AI:   [draft 3]
```

Bu **iterative refinement**-dir. UX multi-turn davamlılığı dəstəkləməlidir.

### Context Continuity Challenges

1. **Token window çəki** — hər turn context böyüdüyü üçün cost artır
2. **Model "unutma"** — 50-ci turn-də model ilk turn-ü zəif xatırlayır
3. **Context pollution** — köhnə instruction-lar yeni ilə qarışır

### Solutions

**Solution A — Sliding window**: son 10 turn saxla, köhnələri xülaisə et.

**Solution B — Summary rollup**: hər 5 turn-dən bir AI özü "conversation so far" xülasə etsin.

**Solution C — Explicit state**: user-in intention-unu struktur şəklində track et (e.g., "user wants tweet, technical style, ≤280 chars").

### Implementation (Laravel)

```php
// app/Services/AI/ConversationManager.php
class ConversationManager
{
    private const MAX_TURNS = 20;
    private const SUMMARY_AFTER = 10;

    public function getContext(int $conversationId): array
    {
        $conversation = Conversation::with('messages')->find($conversationId);
        $messages = $conversation->messages;

        if ($messages->count() > self::SUMMARY_AFTER && !$conversation->summary) {
            $conversation->summary = $this->summarize(
                $messages->slice(0, -5)
            );
            $conversation->save();
        }

        $context = [];
        if ($conversation->summary) {
            $context[] = [
                'role' => 'system',
                'content' => "Conversation so far: {$conversation->summary}"
            ];
        }

        // Son 5 turn həmişə
        foreach ($messages->slice(-5) as $msg) {
            $context[] = ['role' => $msg->role, 'content' => $msg->content];
        }

        return $context;
    }
}
```

### UX Indicators

- **Progress**: "Turn 8 of this chat"
- **Summary available**: "View summary" button
- **Start fresh**: "New conversation" clearly visible

---

## Input Hints və Prompt Scaffolding

Boş input sahəsi user-in blokajıdır. "Nə yazmalıyam?"

### Placeholder-lər Nümunə Ilə

```
Placeholder: "Summarize this article in 3 bullet points, for a PM audience"
```

İstifadəçi özü fikir alır ki, AI-dan nəyi necə istəmək olar.

### Example Prompts (Claude.ai, ChatGPT)

Boş state-də:
```
Try asking:
  "Explain quantum computing like I'm 5"
  "Review this code for bugs"
  "Plan a 3-day trip to Tokyo"
```

Bu **prompt scaffolding**-dir. User öyrənir AI-a necə xitab etmək lazımdır.

### Cursor-da Command Palette

Cursor `Cmd+K`-da:
```
> _
  Suggestions:
    /fix    Fix errors in this file
    /explain Explain this code
    /test   Write tests for selection
    /docs   Add documentation
```

User nə yazacağını bilmir → komanda ilə başla.

### Linear AI-nin Slash Commands

```
/ai create issue from this meeting note
/ai summarize this epic
/ai find duplicates
```

Structured prompts, discovery asan.

### UX Prinsipləri

1. **Example 3-5 tane** — çox olsa overwhelm
2. **Dynamic** — user-in kontekstinə görə (code file → code example-ları)
3. **Seçiləndə input-ə push et, edit imkanı ver** — hazır yox, **template** kimi davran

---

## Error Messages — Recovery-focused

Standart error: "Something went wrong." — faydasız.

### AI-də Typical Errors

1. **Rate limit**: "Too many requests"
2. **Context too long**: "Input exceeds token limit"
3. **Invalid input**: "Cannot process this image format"
4. **Model overloaded**: "Service unavailable"
5. **Guardrail triggered**: "Can't help with this"
6. **Network timeout**: "No response"

### Recovery-Focused Error Messages

**Bad**: "Error 429. Try again."

**Good**:
```
You are sending requests quickly. Wait 30 seconds
before trying again. [Try again in 0:28]
```

**Bad**: "Context too long."

**Good**:
```
Your document is too long for me to process at once.
Try one of these:
  → Summarize sections individually
  → Focus on a specific chapter
  → Use the "smart split" option

[Shorten input] [Split automatically]
```

### Claude Code-un Error UX-i

```
! Rate limit reached (Claude)
  Your usage: 85% of daily limit
  Resets: in 3h 22min
  Alternatives:
    • Use --model haiku for this task (cheaper)
    • Use /compact to reduce context
```

Informative, actionable, kömək edir.

### Guardrail Errors

Model refuse edəndə:
```
! I can't help with that specific request.

Reason: Involves personal data of another user.

Alternatives you might try:
  → Ask about anonymous aggregate data
  → Request summary instead of raw data
```

Refuse edildikdə **niyə** və **alternativ** ver. "Can't help" özü bitməsin.

---

## Keyboard Shortcuts və Power User

Power user-lər keyboard üzərindədir. AI məhsulda shortcut olmaması = professional dəstək yoxluğu.

### Universal AI Shortcuts

```
Cmd+Enter       Send message
Cmd+K           Open AI command palette
Cmd+/           Toggle AI panel
Esc             Cancel generation
Up arrow        Edit last prompt
Cmd+Shift+C     Copy response
Cmd+R           Regenerate
Cmd+Shift+N     New chat
```

### Cursor-da Shortcuts (Ən Yaxşı)

```
Cmd+K           Inline edit (AI)
Cmd+L           Open chat
Cmd+I           Composer (multi-file AI)
Cmd+Enter       Apply suggestion
Cmd+Shift+Enter Apply and jump to next diff
Tab             Accept autocomplete
Esc             Reject suggestion
```

### Claude Code-da Shortcuts

```
Tab             Accept
Esc             Cancel
/compact        Reduce context
/model          Switch model
!               Run shell command
@               Reference file
```

### UX Prinsipləri

1. **Standart OS pattern-lərə uyğun** — Cmd+Enter send, Esc cancel
2. **Shortcut label göstər** — tooltip-də "Send (Cmd+Enter)"
3. **Discoverable** — `?` düyməsi shortcut listi aç
4. **Customizable** — power user rebind istəyə bilər

---

## A/B Test İdeyaları

AI UX kompleks olduğu üçün A/B test ölçülsün.

### Test 1 — Typing Indicator Type

- A: 3-nöqtə klassik
- B: "Thinking... reading docs... writing..."
- Metric: response abandon rate

### Test 2 — Stream Speed

- A: char-by-char (real speed)
- B: word-by-word buffer
- Metric: perceived quality rating

### Test 3 — Feedback Chip

- A: tək thumbs up/down
- B: thumbs + category dropdown
- Metric: feedback completion rate

### Test 4 — Error Message Style

- A: "Something went wrong"
- B: Actionable error with alternatives
- Metric: retry conversion

### Test 5 — Prompt Examples

- A: statik 3 example
- B: dynamic context-aware examples
- Metric: first-prompt length (daha uzun = daha çox fikir)

### Test 6 — Model Naming

- A: "Fast" vs "Smart"
- B: "Haiku" vs "Sonnet"
- C: "Default" vs "Pro"
- Metric: upgrade rate, model mix

### Test 7 — Confidence Display

- A: heç bir göstərici
- B: explicit "confidence: 0.85"
- C: visual only (rəng)
- Metric: fact-check click rate, trust survey

### Metrics Taxonomy

AI UX-də ölçülən metrics:

| Metric                   | Təsvir                              |
|--------------------------|-------------------------------------|
| Time to first token       | UI responsiveness                   |
| Abandon rate              | Stream dayandırılır                 |
| Regenerate rate           | User ilk cavabı bəyənmir            |
| Thumbs rate               | Quality proxy                       |
| Multi-turn rate           | Engagement depth                     |
| Session length             | Product stickiness                  |
| Recovery rate              | Error-dan sonra yenidən try edir mi |

---

## Anti-patterns — Etmə

### Anti-pattern 1 — Hidden AI

"AI-dır, deyək lazım deyil" — etika və güvən pozur. Həmişə disclosure et.

### Anti-pattern 2 — Can't Cancel

Stream başlayanda cancel yoxdursa, user tələyə salınıb. Token-lər yanır, frustration artır.

### Anti-pattern 3 — Overwhelming Options

"Model 12 seçimi, 5 temperature, 3 top-p, system prompt" — user paralyze olur. Default-lar yaxşı olsun.

### Anti-pattern 4 — Blocking UI

AI cavab verəndə bütün UI blok olsun — user başqa iş görə bilmir. Async və non-blocking olsun.

### Anti-pattern 5 — Vague Errors

"Error occurred." → user bilmir nə edəcək. Həmişə **recovery path** ver.

### Anti-pattern 6 — Infinite Loading

20 saniyə "Thinking..." sonra heç bir dəyişiklik. Timeout qoy, ETA göstər, fallback ver.

### Anti-pattern 7 — Missing Retry

Model yanlış cavab verdi, user yenidən prompt yazmalıdır. Regenerate düyməsi olmalıdır.

### Anti-pattern 8 — AI Everything

Hər düyməyə AI qoymaq **AI fatigue** yaradır. Yalnız dəyər olan yerdə AI təklif et.

### Anti-pattern 9 — No History

AI chat-də history olmasa, user köhnə cavabı tapa bilmir. Search-able history qur.

### Anti-pattern 10 — Ignoring Feedback

User thumbs-down basır, heç bir şey dəyişmir. Feedback-i closed loop-lu et.

---

## Real Məhsul Nümunələri

### Linear AI

- **Strong**: enterprise tune, progressive disclosure, slash commands
- **UX signature**: minimal, fast, keyboard-first
- **Lesson**: AI enterprise-da **confidence**-i artırmalıdır, sürpriz deyil

### Notion AI

- **Strong**: inline AI (her yerdə), undo/drafts, AI blocks
- **UX signature**: AI document-in bir hissəsi, natural inteqrasiya
- **Lesson**: AI qeyri-məcburi olsun, user istəyəndə çağırsın

### Cursor

- **Strong**: diff view, accept/reject, multi-file composer
- **UX signature**: developer-centric, terminal-feel
- **Lesson**: code-da AI **precision tool** kimi, not assistant

### Claude Code

- **Strong**: phase-aware typing, slash commands, terminal-native
- **UX signature**: minimal chrome, keyboard-only mode
- **Lesson**: power user UI əlavə visual chrome-a ehtiyac duymur

### Perplexity

- **Strong**: citations (gold standard), follow-up suggestions
- **UX signature**: search-first, sources prominent
- **Lesson**: information retrieval-da **trust = sources**

### Intercom Fin

- **Strong**: resolution tracking, handoff to human
- **UX signature**: professional, outcome-oriented
- **Lesson**: support AI-də **clarity of responsibility** (AI vs human) vacibdir

---

## Cheat Sheet və Qərar Ağacları

### "Streaming qurum?" Ağacı

```
Initial latency > 1s?
├── BƏLİ → Streaming lazım
│         └── Chunks in-order gəlir? → SSE
│         └── Out-of-order → WebSocket
└── XEYR → Direct response ok
          └── Ancaq >500ms üçün typing indicator göstər
```

### "Cancel lazımdırmı?" Ağacı

```
Streaming var?
├── BƏLİ → Cancel MUST-HAVE
└── XEYR → Response > 2s?
          ├── BƏLİ → Cancel nice-to-have
          └── XEYR → Cancel lazım deyil
```

### "Feedback necə toplayım?" Ağacı

```
Feature B2B-dir?
├── BƏLİ → Binary + category + optional comment
└── XEYR (B2C) → Binary yüksük sürtünməli
                 └── Long-form ayrı survey-də topla
```

### "Error messaging necə yazım?" 4-addım

1. Nə baş verdi? (concrete)
2. Niyə baş verdi? (user-understandable reason)
3. Nə edə bilər? (actionable alternatives)
4. Tək klik çözüm? (button/link)

### Keyboard Shortcut Minimum Set

```
Cmd+Enter  — Send
Esc        — Cancel
Up arrow   — Edit last prompt
Cmd+K      — AI command palette
```

### UX Review Checklist (25 item)

```
Streaming
  [ ] Initial token < 500ms
  [ ] Word-level smooth render
  [ ] Autoscroll + "jump to bottom"

Cancellation
  [ ] Cancel button həmişə görünür
  [ ] Esc shortcut
  [ ] Partial content qorunur

Feedback
  [ ] Thumbs up/down
  [ ] Negative → category dropdown
  [ ] Feedback → internal alert

Confidence
  [ ] Low-confidence halda user-ə deyilir
  [ ] Citations varsa göstərilir
  [ ] "General knowledge" vs "Your docs" ayrı

Error
  [ ] Every error actionable
  [ ] Rate limit ETA göstərir
  [ ] Fallback avtomatik

Continuity
  [ ] History axtarıla bilir
  [ ] Long chat summarize olunur
  [ ] "New chat" asan

Input
  [ ] Placeholder real example
  [ ] Slash commands / examples
  [ ] Keyboard shortcut-lar

Disclosure
  [ ] AI content etiketli
  [ ] Model versiya görünür (settings-də)
  [ ] Data usage policy link
```

---

## Son Söz — AI UX = Güvən Mühəndisliyi

AI UX dizaynı **feature dizaynı deyil**, **güvən mühəndisliyi**-dir. Hər pattern (streaming, feedback, citations, error) bir sualın cavabıdır: "User AI-a necə güvənəcək?"

Senior developer kimi sən backend-i bilirsən. Amma AI product-da UX sənə backend qədər təsir göstərir. Feature keyfiyyəti 9/10, UX keyfiyyəti 4/10 olan məhsul **ölür**. Feature 7/10, UX 9/10 olan məhsul **yaşayır və qazanır**.

Bu sənəddəki pattern-ləri öz app-ında bir-bir tətbiq et. Hər həftə bir pattern. 3 ay sonra AI product-un həm daha etik, həm də daha sevilən olacaq.

## Praktik Tapşırıqlar

### 1. Streaming + Progressive Disclosure
Mövcud AI feature-ınızda streaming tətbiq edin. SSE (Server-Sent Events) ilə tokenləri real-vaxtda göndərin. Loading skeleton ilk cavab gələnə qədər göstərin. İlk token gəldikdən sonra skeleton yox olsun, cavab genişlənsin. TTFT (Time to First Token) ölçün. User feedback toplayın: streaming ilə əvvəlki tam-cavab yanaşmasını müqayisə edin.

### 2. Feedback Loop UI
Hər AI cavabının altına thumbs up/down əlavə edin. Down seçildikdə optional dropdown: `"Yanlış məlumat" | "Uyğunsuz ton" | "Çox uzun" | "Başqa"`. Bu məlumatı `ai_feedback` cədvəlinə yazın. Dashboard qurun: hansı cavab tipi daha çox rədd edilir? Bu pattern-ləri prompt improvement-ə yönləndirilə bilər.

### 3. Error UX Audit
Mövcud AI feature-ınızın error vəziyyətlərini sənədləşdirin: API timeout, rate limit, content policy block, boş cavab. Hər hal üçün hazırda user nə görür? Yaxşılaşdırın: timeout → `"Cavab gecikir, yenidən cəhd edilir..."`, rate limit → `"Çox aktiv, 30 saniyə gözləyin"`. Texniki error mesajları istifadəçiyə göstərilməsin.

## Əlaqəli Mövzular

- [AI MVP Playbook](./01-ai-mvp-playbook.md)
- [Measuring AI Success](./05-measuring-ai-success.md)
- [Streaming Responses](../02-claude-api/05-streaming-responses.md)
- [Safety Guardrails](../08-production/08-safety-guardrails.md)
- [Responsible AI for Product](./06-responsible-ai-for-product.md)
