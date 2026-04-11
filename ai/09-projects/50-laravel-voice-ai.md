# Laravel-də Səs AI Pipeline-i

Tam səs söhbəti sistemi: brauzer səsi yazır → Whisper transkripsiya edir → Claude cavab verir → TTS nitqə çevirir.

---

## Arxitektura İcmalı

```
Brauzer (JavaScript)
  ├── MediaRecorder API audio hissələrini qeyd edir
  ├── Səs Aktivlik Aşkarlanması (VAD) — səssizlikdə qeydi dayandırır
  └── Real-vaxt üçün WebSocket (Laravel Reverb)
        │
        ▼  WebM/WAV audio blob
POST /voice/transcribe
        │
        ▼
TranscribeAudioJob
  ├── Groq Whisper API (sürətli, ucuz: ~0.3s gecikmə)
  └── Transkripsiya qaytarır
        │
        ▼
ProcessVoiceMessageJob
  ├── Claude API (axın rejimi)
  └── Çıxarır: cavab + action_items + should_tts
        │
        ├── Transkripsiya + cavabı WebSocket vasitəsilə yayımlayır
        └── (TTS lazımdırsa) TextToSpeechJob
                ├── OpenAI TTS (keyfiyyət) YAXUD ElevenLabs (səs klonlama)
                └── Audio URL qaytarır
```

**Xərc optimallaşdırma strategiyası:**
- Whisper: çox ucuz ($0.006/dəqiqə) — həmişə istifadə et
- Claude: mətn emalı üçün ucuz
- TTS: baha ($15/1M simvol OpenAI üçün, ElevenLabs üçün daha çox) — yalnız istifadəçi "səs rejimindədirsə" və ya açıq şəkildə tələb edərsə çağır

---

## Verilənlər Bazası Miqrasiyaları

```php
// database/migrations/2024_01_01_create_voice_tables.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voice_sessions', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->string('mode')->default('voice'); // voice, transcribe_only, text
            $table->boolean('tts_enabled')->default(true);
            $table->string('tts_voice')->default('alloy'); // OpenAI səsi və ya ElevenLabs səs ID-si
            $table->string('whisper_language')->nullable(); // null = avtomatik aşkar et
            $table->unsignedInteger('total_audio_seconds')->default(0);
            $table->unsignedInteger('total_tts_characters')->default(0);
            $table->timestamps();
        });

        Schema::create('voice_messages', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('session_id')->constrained('voice_sessions')->cascadeOnDelete();
            $table->string('role'); // user, assistant
            $table->text('transcript')->nullable(); // İstifadəçi nitq transkripsiyası və ya assistant mətni
            $table->string('audio_path')->nullable(); // Saxlanılmış audio fayl
            $table->unsignedSmallInteger('audio_duration_seconds')->nullable();
            $table->string('tts_audio_path')->nullable(); // TTS çıxış audiosu
            $table->float('transcription_confidence')->nullable(); // Whisper əminliyi
            $table->json('metadata')->nullable(); // action_items, entities, və s.
            $table->unsignedInteger('whisper_tokens')->nullable();
            $table->unsignedInteger('claude_input_tokens')->nullable();
            $table->unsignedInteger('claude_output_tokens')->nullable();
            $table->unsignedInteger('tts_characters')->nullable();
            $table->timestamps();

            $table->index(['session_id', 'created_at']);
        });
    }
};
```

---

## Modellər

```php
// app/Models/VoiceSession.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class VoiceSession extends Model
{
    protected $fillable = [
        'ulid', 'user_id', 'title', 'mode', 'tts_enabled', 'tts_voice',
        'whisper_language', 'total_audio_seconds', 'total_tts_characters',
    ];

    protected $casts = ['tts_enabled' => 'boolean'];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn($m) => $m->ulid ??= Str::ulid());
    }

    public function messages(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(VoiceMessage::class, 'session_id')->orderBy('created_at');
    }

    public function getRouteKeyName(): string { return 'ulid'; }
}
```

```php
// app/Models/VoiceMessage.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class VoiceMessage extends Model
{
    protected $fillable = [
        'ulid', 'session_id', 'role', 'transcript', 'audio_path',
        'audio_duration_seconds', 'tts_audio_path', 'transcription_confidence',
        'metadata', 'whisper_tokens', 'claude_input_tokens', 'claude_output_tokens',
        'tts_characters',
    ];

    protected $casts = ['metadata' => 'array'];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn($m) => $m->ulid ??= Str::ulid());
    }
}
```

---

## Transkripsiya Xidməti (Groq Whisper)

```php
// app/Services/Voice/TranscriptionService.php
<?php

namespace App\Services\Voice;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sürətli transkripsiya üçün Groq Whisper API.
 * Groq, Whisper-large-v3-ü aşağı gecikmə ilə ~10x real vaxt sürətilə işlədir.
 * OpenAI formatı ilə uyğundur — sadəcə əsas URL-i dəyişdirin.
 *
 * Qiymət: $0.111/saat audio = $0.00185/dəqiqə
 *
 * Alternativ: OpenAI Whisper $0.006/dəqiqədə (3x ucuz, lakin daha yüksək gecikmə)
 */
class TranscriptionService
{
    private string $apiKey;
    private string $baseUrl;
    private string $model;

    public function __construct()
    {
        $useGroq = config('voice.transcription_provider') === 'groq';

        $this->apiKey = $useGroq
            ? config('services.groq.api_key')
            : config('services.openai.api_key');

        $this->baseUrl = $useGroq
            ? 'https://api.groq.com/openai/v1'
            : 'https://api.openai.com/v1';

        $this->model = $useGroq
            ? 'whisper-large-v3'
            : 'whisper-1';
    }

    /**
     * Audio faylını transkripsiya et.
     *
     * @param string $filePath Audio faylın yerli yolu (MP3, WAV, WebM, M4A, FLAC)
     * @param string|null $language BCP-47 dil kodu (null = avtomatik aşkar et)
     * @return array{text: string, duration: float, language: string, segments: array}
     */
    public function transcribe(string $filePath, ?string $language = null): array
    {
        $params = [
            'model' => $this->model,
            'response_format' => 'verbose_json', // Zaman damgaları və əminlik al
            'temperature' => 0,
        ];

        if ($language) {
            $params['language'] = $language;
        }

        // Groq üçün zaman damgası dəqiqliyi əlavə et
        if (str_contains($this->baseUrl, 'groq')) {
            $params['timestamp_granularities[]'] = 'segment';
        }

        $response = Http::withToken($this->apiKey)
            ->baseUrl($this->baseUrl)
            ->timeout(60)
            ->attach('file', file_get_contents($filePath), basename($filePath))
            ->post('/audio/transcriptions', $params)
            ->throw()
            ->json();

        return [
            'text' => trim($response['text'] ?? ''),
            'duration' => $response['duration'] ?? 0,
            'language' => $response['language'] ?? 'unknown',
            'segments' => $response['segments'] ?? [],
            'avg_confidence' => $this->computeConfidence($response['segments'] ?? []),
        ];
    }

    /**
     * Brauzer WebM blob-unu transkripsiya et.
     * WebM (MediaRecorder-in istehsal etdiyi format) birbaşa dəstəklənir.
     */
    public function transcribeWebm(string $webmPath, ?string $language = null): array
    {
        // FFmpeg lazım olduqda WebM-i MP3-ə çevirə bilər, lakin Whisper WebM-i birbaşa qəbul edir
        return $this->transcribe($webmPath, $language);
    }

    private function computeConfidence(array $segments): float
    {
        if (empty($segments)) return 0.0;

        $avgLogProb = collect($segments)
            ->filter(fn($s) => isset($s['avg_logprob']))
            ->avg('avg_logprob');

        if ($avgLogProb === null) return 0.8; // Mövcud deyilsə standart dəyər

        // Logaritmik ehtimalı əminliyə çevir (təxmini çevrilmə)
        // -0.2 avg_logprob yüksək əminlikdir, -1.0 aşağı əminlik
        return max(0, min(1, ($avgLogProb + 1) / 0.8));
    }
}
```

---

## Mətn-Nitq (Text-to-Speech) Xidməti

```php
// app/Services/Voice/TextToSpeechService.php
<?php

namespace App\Services\Voice;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * OpenAI TTS API (standart) və ya ElevenLabs (səs klonlama üçün) istifadə edən TTS.
 *
 * OpenAI TTS qiyməti: $15/1M simvol (~$0.015 hər 1000 simvol üçün)
 * 100 sözdən ibarət tipik söhbət cavabı ≈ 500 simvol ≈ $0.0075
 *
 * ElevenLabs: Daha yaxşı keyfiyyət, lakin 2-5x baha.
 * ElevenLabs-ı yalnız premium istifadəçilər və ya xüsusi səs klonlama halları üçün istifadə edin.
 */
class TextToSpeechService
{
    // OpenAI səsləri: alloy, echo, fable, onyx, nova, shimmer
    private array $openAiVoices = ['alloy', 'echo', 'fable', 'onyx', 'nova', 'shimmer'];

    /**
     * Mətni nitq audiosuna çevir.
     *
     * @return string Yaradılmış audio faylın saxlama yolu
     */
    public function synthesize(string $text, string $voice = 'alloy', string $format = 'mp3'): string
    {
        // Səs ID formatına görə provayderi seç
        $isElevenLabsVoice = !in_array($voice, $this->openAiVoices);

        if ($isElevenLabsVoice) {
            return $this->synthesizeWithElevenLabs($text, $voice);
        }

        return $this->synthesizeWithOpenAI($text, $voice, $format);
    }

    private function synthesizeWithOpenAI(string $text, string $voice, string $format): string
    {
        // Mətn uzunluğunu məhdudlaşdır (OpenAI maksimumu sorğu başına 4096 simvoldur)
        $text = mb_substr($text, 0, 4096);

        $response = Http::withToken(config('services.openai.api_key'))
            ->withHeaders(['Accept' => 'audio/mpeg'])
            ->timeout(30)
            ->post('https://api.openai.com/v1/audio/speech', [
                'model' => 'tts-1', // tts-1-hd daha yüksək keyfiyyətlidir, lakin 2x baha
                'input' => $text,
                'voice' => $voice,
                'response_format' => $format,
                'speed' => 1.0, // 0.25-dən 4.0-a qədər
            ])
            ->throw();

        $path = 'tts/' . uniqid('tts_', true) . '.' . $format;
        Storage::put($path, $response->body());

        return $path;
    }

    private function synthesizeWithElevenLabs(string $text, string $voiceId): string
    {
        $response = Http::withHeaders([
            'xi-api-key' => config('services.elevenlabs.api_key'),
            'Accept' => 'audio/mpeg',
            'Content-Type' => 'application/json',
        ])
        ->timeout(30)
        ->post("https://api.elevenlabs.io/v1/text-to-speech/{$voiceId}/stream", [
            'text' => $text,
            'model_id' => 'eleven_turbo_v2', // Aşağı gecikmə üçün sürətli model
            'voice_settings' => [
                'stability' => 0.5,
                'similarity_boost' => 0.8,
            ],
        ])
        ->throw();

        $path = 'tts/' . uniqid('tts_', true) . '.mp3';
        Storage::put($path, $response->body());

        return $path;
    }

    /**
     * Bu cavab üçün TTS yaratmağın mənalı olub-olmadığını qərar ver.
     * Çox qısa cavablar, kod blokları və ya cədvəl ağırlıqlı məzmun üçün TTS-dən yayınır.
     */
    public function shouldGenerateTts(string $text): bool
    {
        // Çox qısa cavablar üçün TTS-dən yayın
        if (strlen($text) < 10) return false;

        // Cavab əsasən koddan ibarətdirsə yayın
        $codeBlocks = substr_count($text, '```');
        if ($codeBlocks >= 2) return false; // Ən azı bir kod bloku var

        // Cavabda çox markdown cədvəl sintaksisi varsa yayın
        if (substr_count($text, '|') > 10) return false;

        return true;
    }

    /**
     * TTS üçün mətni təmizlə: markdown, kod blokları, URL-ləri sil.
     */
    public function cleanForTts(string $text): string
    {
        // Kod bloklarını tamamilə sil
        $text = preg_replace('/```[\s\S]*?```/m', '[code block]', $text);
        $text = preg_replace('/`[^`]+`/', '', $text); // Sətirdaxili kod

        // Markdown formatlamasını sil
        $text = preg_replace('/\*\*([^*]+)\*\*/', '$1', $text); // Qalın
        $text = preg_replace('/\*([^*]+)\*/', '$1', $text);      // Kursiv
        $text = preg_replace('/#{1,6}\s+/', '', $text);          // Başlıqlar
        $text = preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $text); // Keçidlər

        // URL-ləri sil
        $text = preg_replace('/https?:\/\/[^\s]+/', 'a link', $text);

        // Çoxlu yeni sətirləri daralt
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text);
    }
}
```

---

## Səs Emal Xidməti (Claude İnteqrasiyası)

```php
// app/Services/Voice/VoiceProcessingService.php
<?php

namespace App\Services\Voice;

use App\Models\VoiceSession;
use App\Models\VoiceMessage;
use Anthropic\Laravel\Facades\Anthropic;

class VoiceProcessingService
{
    private string $systemPrompt = <<<'PROMPT'
    You are a helpful voice assistant. The user is speaking to you, and your response will be
    converted to speech. Therefore:

    - Keep responses concise (2-4 sentences for simple questions, more for complex ones)
    - Avoid markdown formatting — no **bold**, no bullet points using -, no code blocks
    - Use natural spoken language: "the first option is..., the second is..." not "1. ..., 2. ..."
    - If you need to give a list, say "First... Second... And finally..."
    - Don't say "Certainly!" or "Absolutely!" — just answer the question

    At the end of your response, add a JSON block on a new line (this won't be read aloud):
    [METADATA]{"action_items": [], "entities": [], "should_tts": true}[/METADATA]

    should_tts should be false if your response contains code, a table, or something that
    doesn't make sense as speech.
    PROMPT;

    public function process(
        VoiceSession $session,
        string $transcript,
    ): array {
        // Söhbət tarixçəsini qur
        $history = $session->messages()
            ->whereNotNull('transcript')
            ->orderBy('created_at')
            ->take(20) // Kontekst üçün son 20 növbə
            ->get()
            ->map(fn($msg) => [
                'role' => $msg->role,
                'content' => $msg->transcript,
            ])
            ->toArray();

        // Cari istifadəçi mesajını əlavə et
        $history[] = ['role' => 'user', 'content' => $transcript];

        $response = Anthropic::messages()->create([
            'model' => 'claude-haiku-4-5', // Səs üçün sürətli model — aşağı gecikmə
            'max_tokens' => 1024,
            'system' => $this->systemPrompt,
            'messages' => $history,
        ]);

        $rawText = $response->content[0]->text ?? '';

        // Metadata blokunu parse et
        $metadata = [];
        $cleanText = $rawText;

        if (preg_match('/\[METADATA\](.*?)\[\/METADATA\]/s', $rawText, $matches)) {
            $cleanText = trim(str_replace($matches[0], '', $rawText));
            $metadata = json_decode($matches[1], true) ?? [];
        }

        return [
            'response_text' => $cleanText,
            'metadata' => $metadata,
            'should_tts' => $metadata['should_tts'] ?? true,
            'action_items' => $metadata['action_items'] ?? [],
            'input_tokens' => $response->usage->inputTokens,
            'output_tokens' => $response->usage->outputTokens,
        ];
    }
}
```

---

## HTTP Controller (İdarəedici)

```php
// app/Http/Controllers/VoiceController.php
<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessVoiceAudio;
use App\Models\VoiceSession;
use App\Models\VoiceMessage;
use App\Services\Voice\TranscriptionService;
use App\Services\Voice\VoiceProcessingService;
use App\Services\Voice\TextToSpeechService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class VoiceController extends Controller
{
    public function __construct(
        private readonly TranscriptionService $transcription,
        private readonly VoiceProcessingService $processing,
        private readonly TextToSpeechService $tts,
    ) {}

    /**
     * Yeni səs sessiyası yarat.
     */
    public function createSession(Request $request)
    {
        $request->validate([
            'mode' => ['in:voice,transcribe_only'],
            'tts_enabled' => ['boolean'],
            'tts_voice' => ['in:alloy,echo,fable,onyx,nova,shimmer'],
            'language' => ['nullable', 'string', 'size:2'], // BCP-47 2 hərfli kod
        ]);

        $session = auth()->user()->voiceSessions()->create([
            'mode' => $request->input('mode', 'voice'),
            'tts_enabled' => $request->boolean('tts_enabled', true),
            'tts_voice' => $request->input('tts_voice', 'alloy'),
            'whisper_language' => $request->input('language'),
        ]);

        return response()->json([
            'session_id' => $session->ulid,
            'mode' => $session->mode,
            'tts_enabled' => $session->tts_enabled,
        ]);
    }

    /**
     * Səs mesajını emal et.
     *
     * Bu endpoint brauzerdən audio qəbul edir və:
     * 1. Audio faylı saxlayır
     * 2. Transkripsiya edir (sinxron — Groq ilə kifayət qədər sürətlidir)
     * 3. Claude ilə emal edir
     * 4. İxtiyari olaraq TTS yaradır
     *
     * Ən aşağı gecikmə üçün transkripsiya dərhal qaytarılır,
     * Claude + TTS isə asinxron emal olunur.
     */
    public function processAudio(Request $request, VoiceSession $session)
    {
        $this->authorize('update', $session);

        $request->validate([
            'audio' => ['required', 'file', 'max:25600'], // Maksimum 25MB
            'duration' => ['nullable', 'numeric', 'max:300'], // Maksimum 5 dəqiqə
        ]);

        $file = $request->file('audio');
        $audioPath = $file->store("voice/{$session->id}", 'private');

        // Addım 1: Dərhal transkripsiya et (Groq sinxron istifadə üçün kifayət qədər sürətlidir)
        $transcription = $this->transcription->transcribe(
            Storage::path($audioPath),
            $session->whisper_language,
        );

        if (empty($transcription['text'])) {
            return response()->json(['error' => 'Audionu transkripsiya etmək mümkün olmadı'], 422);
        }

        // Addım 2: İstifadəçi mesajını saxla
        $userMessage = $session->messages()->create([
            'role' => 'user',
            'transcript' => $transcription['text'],
            'audio_path' => $audioPath,
            'audio_duration_seconds' => (int) ($request->input('duration') ?? $transcription['duration']),
            'transcription_confidence' => $transcription['avg_confidence'],
        ]);

        $session->increment('total_audio_seconds', $userMessage->audio_duration_seconds);

        // Addım 3: Yalnız transkripsiya rejimi üçün burada qaytar
        if ($session->mode === 'transcribe_only') {
            return response()->json([
                'message_id' => $userMessage->ulid,
                'transcript' => $transcription['text'],
                'confidence' => $transcription['avg_confidence'],
            ]);
        }

        // Addım 4: Claude ilə emal et
        $result = $this->processing->process($session, $transcription['text']);

        // Addım 5: Assistant mesajını saxla
        $assistantMessage = $session->messages()->create([
            'role' => 'assistant',
            'transcript' => $result['response_text'],
            'metadata' => [
                'action_items' => $result['action_items'],
                'should_tts' => $result['should_tts'],
            ],
            'claude_input_tokens' => $result['input_tokens'],
            'claude_output_tokens' => $result['output_tokens'],
        ]);

        $response = [
            'user_message_id' => $userMessage->ulid,
            'transcript' => $transcription['text'],
            'response_text' => $result['response_text'],
            'response_message_id' => $assistantMessage->ulid,
            'action_items' => $result['action_items'],
            'audio_url' => null,
        ];

        // Addım 6: Lazım olduqda TTS yarat
        if ($session->tts_enabled && $result['should_tts']) {
            try {
                $cleanText = $this->tts->cleanForTts($result['response_text']);
                $ttsPath = $this->tts->synthesize($cleanText, $session->tts_voice);

                $assistantMessage->update([
                    'tts_audio_path' => $ttsPath,
                    'tts_characters' => strlen($cleanText),
                ]);

                $session->increment('total_tts_characters', strlen($cleanText));

                $response['audio_url'] = Storage::temporaryUrl($ttsPath, now()->addHours(1));
            } catch (\Exception $e) {
                \Log::warning('TTS yaratma uğursuz oldu', ['error' => $e->getMessage()]);
                // Audio olmadan cavab qaytar — frontend mətn ehtiyat variantını göstərəcək
            }
        }

        return response()->json($response);
    }

    /**
     * TTS audio faylı üçün müvəqqəti URL al.
     * Frontend tərəfindən əvvəllər yaradılmış audionu oxutmaq üçün istifadə olunur.
     */
    public function getAudio(VoiceMessage $message)
    {
        $this->authorize('view', $message->session);

        if (!$message->tts_audio_path) {
            abort(404);
        }

        return redirect(Storage::temporaryUrl($message->tts_audio_path, now()->addHours(1)));
    }

    /**
     * Sessiya tarixçəsini al.
     */
    public function getSession(VoiceSession $session)
    {
        $this->authorize('view', $session);

        return response()->json([
            'session_id' => $session->ulid,
            'mode' => $session->mode,
            'messages' => $session->messages()->get()->map(fn($m) => [
                'id' => $m->ulid,
                'role' => $m->role,
                'transcript' => $m->transcript,
                'audio_url' => $m->tts_audio_path
                    ? Storage::temporaryUrl($m->tts_audio_path, now()->addHour())
                    : null,
                'created_at' => $m->created_at->toIso8601String(),
            ]),
        ]);
    }
}
```

---

## Real-Vaxt üçün Reverb WebSocket

```php
// app/Events/VoiceTranscriptReady.php
<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class VoiceTranscriptReady implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public readonly string $sessionId,
        public readonly string $transcript,
        public readonly string $responseText,
        public readonly ?string $audioUrl = null,
        public readonly array $actionItems = [],
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("voice-session.{$this->sessionId}")];
    }

    public function broadcastAs(): string
    {
        return 'transcript.ready';
    }
}
```

```php
// app/Http/Controllers/VoiceRealtimeController.php
<?php

namespace App\Http\Controllers;

use App\Events\VoiceTranscriptReady;
use App\Models\VoiceSession;
use App\Services\Voice\TranscriptionService;
use App\Services\Voice\VoiceProcessingService;
use App\Services\Voice\TextToSpeechService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * WebSocket-optimallaşdırılmış səs emalı.
 * VoiceController ilə eynidir, lakin HTTP cavabları qaytarmaq əvəzinə
 * Reverb vasitəsilə hadisələri yayımlayır — real-vaxt UX üçün daha yaxşıdır.
 */
class VoiceRealtimeController extends Controller
{
    public function __construct(
        private readonly TranscriptionService $transcription,
        private readonly VoiceProcessingService $processing,
        private readonly TextToSpeechService $tts,
    ) {}

    public function processAudioWebSocket(Request $request, VoiceSession $session)
    {
        $this->authorize('update', $session);

        $request->validate([
            'audio' => ['required', 'file', 'max:25600'],
        ]);

        $file = $request->file('audio');
        $audioPath = $file->store("voice/{$session->id}", 'private');

        // Transkripsiya et
        $transcription = $this->transcription->transcribe(Storage::path($audioPath));

        if (empty($transcription['text'])) {
            return response()->json(['error' => 'Nitq aşkar edilmədi'], 422);
        }

        // İstifadəçi mesajını saxla
        $session->messages()->create([
            'role' => 'user',
            'transcript' => $transcription['text'],
            'audio_path' => $audioPath,
        ]);

        // "Transkripsiya qəbul edildi" dərhal yayımla
        // Bu, Claude düşünərkən UI-nin transkripsiyanu göstərməsinə imkan verir
        broadcast(new VoiceTranscriptReady(
            sessionId: $session->ulid,
            transcript: $transcription['text'],
            responseText: '...',
        ));

        // Claude ilə emal et
        $result = $this->processing->process($session, $transcription['text']);

        // Lazım olduqda TTS yarat
        $audioUrl = null;
        if ($session->tts_enabled && $result['should_tts']) {
            $cleanText = $this->tts->cleanForTts($result['response_text']);
            $ttsPath = $this->tts->synthesize($cleanText, $session->tts_voice);
            $audioUrl = Storage::temporaryUrl($ttsPath, now()->addHours(1));

            $session->messages()->create([
                'role' => 'assistant',
                'transcript' => $result['response_text'],
                'tts_audio_path' => $ttsPath,
            ]);
        }

        // Tam cavabı yayımla
        broadcast(new VoiceTranscriptReady(
            sessionId: $session->ulid,
            transcript: $transcription['text'],
            responseText: $result['response_text'],
            audioUrl: $audioUrl,
            actionItems: $result['action_items'],
        ));

        return response()->json(['queued' => true]);
    }
}
```

---

## Marşrutlar (Routes)

```php
// routes/api.php
use App\Http\Controllers\VoiceController;

Route::middleware(['auth:sanctum'])->prefix('voice')->name('voice.')->group(function () {
    Route::post('/sessions', [VoiceController::class, 'createSession'])->name('sessions.create');
    Route::get('/sessions/{session}', [VoiceController::class, 'getSession'])->name('sessions.show');
    Route::post('/sessions/{session}/audio', [VoiceController::class, 'processAudio'])
        ->middleware('throttle:30,1')
        ->name('sessions.audio');
    Route::get('/messages/{message}/audio', [VoiceController::class, 'getAudio'])->name('messages.audio');
});

// routes/channels.php (WebSocket autentifikasiyası üçün)
Broadcast::channel('voice-session.{sessionId}', function ($user, $sessionId) {
    return \App\Models\VoiceSession::where('ulid', $sessionId)
        ->where('user_id', $user->id)
        ->exists();
});
```

---

## Frontend JavaScript

```javascript
// resources/js/voice-recorder.js
// Tam səs qeydiyyatı, VAD (Səs Aktivlik Aşkarlanması) və oxutma tətbiqi

class VoiceRecorder {
    constructor(options = {}) {
        this.sessionId = options.sessionId;
        this.apiBaseUrl = options.apiBaseUrl || '/api/voice';
        this.onTranscript = options.onTranscript || (() => {});
        this.onResponse = options.onResponse || (() => {});
        this.onStatusChange = options.onStatusChange || (() => {});
        this.onError = options.onError || (() => {});

        // VAD parametrləri
        this.silenceThreshold = options.silenceThreshold || 0.01; // RMS amplitudası
        this.silenceTimeout = options.silenceTimeout || 1500;      // Dayanmadan əvvəl ms-lə səssizlik
        this.minRecordingTime = options.minRecordingTime || 500;   // 500ms-dən qısa kliplər göndərilmir

        this.mediaRecorder = null;
        this.audioStream = null;
        this.audioChunks = [];
        this.isRecording = false;
        this.silenceTimer = null;
        this.recordingStartTime = null;
        this.analyser = null;
        this.audioContext = null;
        this.csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

        // Real-vaxt yeniləmələr üçün WebSocket bağlantısı
        this.channel = null;
        if (window.Echo && this.sessionId) {
            this.setupWebSocket();
        }
    }

    // ==================
    // Quraşdırma və Sökmə
    // ==================

    async setup() {
        try {
            this.audioStream = await navigator.mediaDevices.getUserMedia({
                audio: {
                    channelCount: 1,
                    sampleRate: 16000, // 16kHz Whisper üçün optimal
                    echoCancellation: true,
                    noiseSuppression: true,
                    autoGainControl: true,
                },
            });

            // Səs Aktivlik Aşkarlanması üçün analyser qur
            this.audioContext = new AudioContext({ sampleRate: 16000 });
            const source = this.audioContext.createMediaStreamSource(this.audioStream);
            this.analyser = this.audioContext.createAnalyser();
            this.analyser.fftSize = 512;
            this.analyser.smoothingTimeConstant = 0.8;
            source.connect(this.analyser);

            this.onStatusChange('ready');
            return true;
        } catch (err) {
            this.onError('Mikrofona giriş rədd edildi: ' + err.message);
            return false;
        }
    }

    setupWebSocket() {
        this.channel = window.Echo.private(`voice-session.${this.sessionId}`)
            .listen('.transcript.ready', (data) => {
                if (data.responseText && data.responseText !== '...') {
                    this.onResponse({
                        text: data.responseText,
                        audioUrl: data.audioUrl,
                        actionItems: data.actionItems || [],
                    });

                    // Audio cavabı avtomatik oxut
                    if (data.audioUrl) {
                        this.playAudio(data.audioUrl);
                    }
                } else if (data.transcript) {
                    this.onTranscript(data.transcript);
                }
            });
    }

    destroy() {
        this.stopRecording();
        if (this.audioStream) {
            this.audioStream.getTracks().forEach(t => t.stop());
        }
        if (this.audioContext) {
            this.audioContext.close();
        }
        if (this.channel) {
            this.channel.stopListening('.transcript.ready');
        }
    }

    // ==================
    // Qeydiyyat
    // ==================

    async startRecording() {
        if (this.isRecording) return;
        if (!this.audioStream) await this.setup();

        this.audioChunks = [];
        this.recordingStartTime = Date.now();

        // Əvvəlcə WebM/Opus sınayın (ən yaxşı keyfiyyət/ölçü nisbəti), sonra digərlərinə keçin
        const mimeType = [
            'audio/webm;codecs=opus',
            'audio/webm',
            'audio/ogg;codecs=opus',
            'audio/mp4',
        ].find(type => MediaRecorder.isTypeSupported(type)) || '';

        this.mediaRecorder = new MediaRecorder(this.audioStream, {
            mimeType,
            audioBitsPerSecond: 16000, // Nitq üçün 16kbps kifayətdir
        });

        this.mediaRecorder.ondataavailable = (e) => {
            if (e.data.size > 0) this.audioChunks.push(e.data);
        };

        this.mediaRecorder.onstop = () => this.handleRecordingStop();

        this.mediaRecorder.start(250); // Hər 250ms-də hissələri topla
        this.isRecording = true;
        this.onStatusChange('recording');

        // VAD monitorinqini başlat
        this.startVAD();
    }

    stopRecording() {
        if (!this.isRecording) return;

        clearInterval(this.vadInterval);
        clearTimeout(this.silenceTimer);
        this.isRecording = false;

        if (this.mediaRecorder?.state !== 'inactive') {
            this.mediaRecorder.stop();
        }
    }

    // ==================
    // Səs Aktivlik Aşkarlanması (VAD)
    // ==================

    startVAD() {
        const bufferLength = this.analyser.fftSize;
        const dataArray = new Float32Array(bufferLength);
        let lastSpeechTime = Date.now();
        let hasSpeech = false;

        this.vadInterval = setInterval(() => {
            this.analyser.getFloatTimeDomainData(dataArray);
            const rms = Math.sqrt(dataArray.reduce((sum, x) => sum + x * x, 0) / bufferLength);

            if (rms > this.silenceThreshold) {
                lastSpeechTime = Date.now();
                hasSpeech = true;
                clearTimeout(this.silenceTimer);
                this.onStatusChange('speaking');
            } else if (hasSpeech) {
                // Nitq aşkar edildi, lakin indi səssizdir
                const silenceDuration = Date.now() - lastSpeechTime;

                if (silenceDuration > this.silenceTimeout) {
                    // Uzun səssizlikdən sonra qeydi dayandır
                    this.onStatusChange('processing');
                    this.stopRecording();
                } else {
                    this.onStatusChange('listening');
                }
            }
        }, 100); // Hər 100ms-də yoxla
    }

    // ==================
    // Emal
    // ==================

    async handleRecordingStop() {
        clearInterval(this.vadInterval);

        const duration = Date.now() - this.recordingStartTime;
        if (duration < this.minRecordingTime || this.audioChunks.length === 0) {
            this.onStatusChange('ready');
            return;
        }

        const blob = new Blob(this.audioChunks, {
            type: this.mediaRecorder.mimeType || 'audio/webm',
        });

        // Çox kiçikdirsə (< 5KB), yəqin ki sadəcə arxa fon səsidir
        if (blob.size < 5000) {
            this.onStatusChange('ready');
            return;
        }

        this.onStatusChange('processing');

        try {
            const result = await this.sendAudio(blob, duration / 1000);

            if (result.transcript) {
                this.onTranscript(result.transcript);
            }

            if (result.response_text) {
                this.onResponse({
                    text: result.response_text,
                    audioUrl: result.audio_url,
                    actionItems: result.action_items || [],
                });
            }

            // Birbaşa qaytarılırsa audionu avtomatik oxut (WebSocket olmayan yol)
            if (result.audio_url) {
                await this.playAudio(result.audio_url);
            }

        } catch (err) {
            this.onError('Emal uğursuz oldu: ' + err.message);
        } finally {
            this.onStatusChange('ready');
        }
    }

    async sendAudio(blob, durationSeconds) {
        const formData = new FormData();
        formData.append('audio', blob, 'recording.webm');
        formData.append('duration', durationSeconds.toFixed(2));

        const response = await fetch(
            `${this.apiBaseUrl}/sessions/${this.sessionId}/audio`,
            {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': this.csrfToken,
                    'Accept': 'application/json',
                },
                body: formData,
            }
        );

        if (!response.ok) {
            const error = await response.json();
            throw new Error(error.error || 'Yükləmə uğursuz oldu');
        }

        return response.json();
    }

    // ==================
    // Oxutma
    // ==================

    async playAudio(url) {
        return new Promise((resolve, reject) => {
            const audio = new Audio(url);
            audio.onended = resolve;
            audio.onerror = reject;
            audio.play().catch(reject);
        });
    }
}

// ==================
// UI üçün Alpine.js / vanilla JS komponenti
// ==================

function voiceChat(sessionId) {
    return {
        recorder: null,
        status: 'idle', // idle, ready, recording, speaking, listening, processing
        messages: [],
        isThinking: false,
        autoRecord: false, // Assistant danışdıqdan sonra avtomatik qeydiyyatı başlat

        init() {
            this.recorder = new VoiceRecorder({
                sessionId,
                onTranscript: (text) => {
                    this.addMessage('user', text);
                },
                onResponse: (response) => {
                    this.isThinking = false;
                    this.addMessage('assistant', response.text, response.audioUrl);
                    if (response.actionItems.length > 0) {
                        this.showActionItems(response.actionItems);
                    }
                },
                onStatusChange: (s) => {
                    this.status = s;
                    if (s === 'processing') this.isThinking = true;
                },
                onError: (msg) => {
                    this.isThinking = false;
                    console.error(msg);
                    this.addMessage('error', msg);
                },
            });
        },

        async setupMicrophone() {
            const ok = await this.recorder.setup();
            if (ok) this.status = 'ready';
        },

        toggleRecording() {
            if (this.status === 'recording' || this.status === 'speaking') {
                this.recorder.stopRecording();
            } else if (this.status === 'ready') {
                this.recorder.startRecording();
            }
        },

        addMessage(role, text, audioUrl = null) {
            this.messages.push({
                id: Date.now(),
                role,
                text,
                audioUrl,
                time: new Date().toLocaleTimeString(),
            });
            this.$nextTick(() => {
                const el = document.getElementById('messages');
                if (el) el.scrollTop = el.scrollHeight;
            });
        },

        showActionItems(items) {
            // Hadisə göndərə, modal göstərə və s. edə bilər
            console.log('Fəaliyyət elementləri:', items);
        },

        getStatusLabel() {
            return {
                idle: 'Başlamaq üçün klikləyin',
                ready: 'Hazır — danışmaq üçün basın',
                recording: 'Dinlənilir...',
                speaking: 'Danışılır...',
                listening: 'Gözlənilir...',
                processing: 'Düşünülür...',
            }[this.status] || this.status;
        },

        getStatusColor() {
            return {
                recording: 'bg-red-500',
                speaking: 'bg-red-400 animate-pulse',
                processing: 'bg-yellow-500 animate-pulse',
                ready: 'bg-green-500',
            }[this.status] || 'bg-gray-400';
        },

        destroy() {
            this.recorder?.destroy();
        },
    };
}
```

---

## Səs UI (Blade + Alpine.js)

```blade
{{-- resources/views/voice/session.blade.php --}}
<x-app-layout>
    <div class="max-w-2xl mx-auto px-4 py-8 h-screen flex flex-col"
         x-data="voiceChat('{{ $session->ulid }}')"
         x-init="init()"
         x-on:beforeunload.window="destroy()">

        {{-- Başlıq --}}
        <div class="flex items-center justify-between mb-4">
            <h1 class="text-xl font-semibold text-gray-900">Səs Assistenti</h1>
            <div class="flex items-center gap-2 text-sm">
                <span :class="getStatusColor()" class="w-2 h-2 rounded-full"></span>
                <span x-text="getStatusLabel()" class="text-gray-500"></span>
            </div>
        </div>

        {{-- Mesajlar --}}
        <div id="messages"
             class="flex-1 overflow-y-auto space-y-4 mb-4"
             x-ref="messages">

            <template x-if="messages.length === 0">
                <div class="text-center text-gray-400 mt-20">
                    <p class="text-4xl mb-3">🎤</p>
                    <p>Danışmağa başlamaq üçün aşağıdakı düyməni basın</p>
                </div>
            </template>

            <template x-for="message in messages" :key="message.id">
                <div :class="message.role === 'user' ? 'flex justify-end' : 'flex justify-start'">
                    <div :class="message.role === 'user'
                        ? 'bg-blue-600 text-white rounded-2xl rounded-tr-sm'
                        : message.role === 'error'
                        ? 'bg-red-50 border border-red-200 text-red-800 rounded-2xl'
                        : 'bg-white border border-gray-200 shadow-sm rounded-2xl rounded-tl-sm'"
                         class="max-w-xs px-4 py-3">

                        <p x-text="message.text" class="text-sm leading-relaxed"></p>

                        {{-- Assistant mesajları üçün audio oxutma düyməsi --}}
                        <template x-if="message.audioUrl">
                            <button
                                @click="recorder.playAudio(message.audioUrl)"
                                class="mt-2 text-xs flex items-center gap-1 opacity-70 hover:opacity-100">
                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M8 5v14l11-7z"/>
                                </svg>
                                Oxut
                            </button>
                        </template>

                        <div class="mt-1 text-xs opacity-50" x-text="message.time"></div>
                    </div>
                </div>
            </template>

            {{-- Düşünmə göstəricisi --}}
            <template x-if="isThinking">
                <div class="flex justify-start">
                    <div class="bg-white border rounded-2xl rounded-tl-sm px-4 py-3 shadow-sm">
                        <div class="flex items-center gap-1">
                            <span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce"></span>
                            <span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay:0.15s"></span>
                            <span class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay:0.3s"></span>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        {{-- Mikrofon düyməsi --}}
        <div class="flex flex-col items-center gap-4 pb-4">
            {{-- Quraşdırma düyməsi (ilk dəfə) --}}
            <template x-if="status === 'idle'">
                <button
                    @click="setupMicrophone()"
                    class="bg-blue-600 text-white rounded-full px-6 py-3 font-medium hover:bg-blue-700">
                    Mikrofonu Aktiv Et
                </button>
            </template>

            {{-- Qeydiyyat düyməsi --}}
            <template x-if="status !== 'idle'">
                <button
                    @click="toggleRecording()"
                    :disabled="status === 'processing'"
                    class="w-20 h-20 rounded-full flex items-center justify-center transition-all"
                    :class="{
                        'bg-red-500 hover:bg-red-600 scale-110': status === 'recording' || status === 'speaking',
                        'bg-gray-200 cursor-not-allowed': status === 'processing',
                        'bg-blue-500 hover:bg-blue-600': status === 'ready' || status === 'listening',
                    }">
                    <template x-if="status === 'recording' || status === 'speaking'">
                        <svg class="w-8 h-8 text-white" fill="currentColor" viewBox="0 0 24 24">
                            <rect x="6" y="4" width="4" height="16"/>
                            <rect x="14" y="4" width="4" height="16"/>
                        </svg>
                    </template>
                    <template x-if="status !== 'recording' && status !== 'speaking'">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/>
                        </svg>
                    </template>
                </button>
            </template>

            <p class="text-xs text-gray-400" x-text="getStatusLabel()"></p>
        </div>
    </div>
</x-app-layout>
```

---

## Konfiqurasiya

```php
// config/voice.php
<?php

return [
    // Transkripsiya provayderi: 'groq' (sürətli, tövsiyə olunur) yaxud 'openai' (ucuz)
    'transcription_provider' => env('VOICE_TRANSCRIPTION_PROVIDER', 'groq'),

    // TTS provayderi: 'openai' (standart) yaxud 'elevenlabs' (daha yaxşı keyfiyyət)
    'tts_provider' => env('VOICE_TTS_PROVIDER', 'openai'),

    // OpenAI TTS üçün standart səs
    'default_voice' => env('VOICE_DEFAULT_VOICE', 'alloy'),

    // Maksimum audio fayl ölçüsü MB-da
    'max_audio_size_mb' => 25,

    // Saniyələrlə maksimum audio müddəti
    'max_audio_duration' => 300,

    // Yalnız cavab bu qədər simvoldan qısadırsa TTS yarat (simvol sayı)
    // Uzun cavablar TTS üçün çox bahadır
    'tts_max_chars' => 2000,

    // Səssizlik aşkarlanması həddi (millisaniyə)
    'vad_silence_timeout_ms' => 1500,
];
```

---

## İstehsalat Mülahizələri

### Gecikmə Büdcəsi

Səs söhbətinin cavabdeh hiss edilməsi üçün ümumi gediş-qayıdış müddəti 3 saniyədən az olmalıdır:

| Addım | Hədəf | Qeydlər |
|-------|-------|---------|
| Audio yükləmə | 200ms | Fayl ölçüsündən asılıdır |
| Whisper (Groq) | 300ms | ~10x real vaxt |
| Claude (Haiku) | 500ms | İlk token |
| TTS (OpenAI) | 500ms | 50 sözlük cavab üçün |
| Audio yükləmə | 200ms | |
| **Cəmi** | **~1.7s** | Sürətli şəbəkə ilə əldə edilə bilər |

Səs üçün `claude-haiku-4-5` istifadə edin — söhbət tapşırıqları üçün qəbul edilə bilən keyfiyyətlə Sonnet-dən 5x daha sürətlidir.

### Audio Saxlama

TTS faylları qısa ömürlü olmalıdır — onları həmişəlik saxlamağın heç bir mənası yoxdur:

```php
// AppServiceProvider-də və ya planlaşdırılmış komandada:
// 24 saatdan köhnə TTS fayllarını sil
Storage::allFiles('tts')
    ->filter(fn($f) => Storage::lastModified($f) < now()->subDay()->timestamp)
    ->each(fn($f) => Storage::delete($f));
```

### Xərc Nəzarəti

- **Groq Whisper**: 30 saniyəlik klip üçün ~$0.002
- **claude-haiku-4-5**: növbə başına ~$0.001
- **OpenAI TTS**: cavab başına ~$0.008 (100 söz)
- **Növbə başına cəmi**: ~$0.011
