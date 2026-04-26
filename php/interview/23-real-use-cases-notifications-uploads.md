# Real Use Cases (Senior)

## 1. Multi-Channel Notification sistemi (preferences ilə)

```php
// notification_preferences table:
// user_id | channel | type           | enabled
// 1       | email   | order_update   | true
// 1       | sms     | order_update   | false
// 1       | push    | promotion      | true
// 1       | email   | promotion      | false

class NotificationPreferenceService {
    public function getChannels(User $user, string $type): array {
        $preferences = $user->notificationPreferences()
            ->where('type', $type)
            ->where('enabled', true)
            ->pluck('channel')
            ->toArray();

        // Default: email həmişə açıq (user söndürməyibsə)
        if (empty($preferences) && !$user->notificationPreferences()->where('type', $type)->exists()) {
            return ['mail', 'database'];
        }

        return array_map(fn ($ch) => match($ch) {
            'email' => 'mail',
            'sms' => 'vonage',
            'push' => 'fcm',
            'database' => 'database',
            default => $ch,
        }, $preferences);
    }
}

class SmartNotification extends Notification implements ShouldQueue {
    use Queueable;

    public function __construct(
        private string $type,
        private array $data,
    ) {}

    public function via(object $notifiable): array {
        return app(NotificationPreferenceService::class)
            ->getChannels($notifiable, $this->type);
    }

    public function toMail(object $notifiable): MailMessage {
        return (new MailMessage)
            ->subject($this->data['subject'])
            ->markdown('notifications.generic', $this->data);
    }

    public function toVonage(object $notifiable): VonageMessage {
        return (new VonageMessage)->content($this->data['sms_text']);
    }

    public function toFcm(object $notifiable): FcmMessage {
        return FcmMessage::create()
            ->setNotification(\NotificationChannels\Fcm\Resources\Notification::create()
                ->setTitle($this->data['subject'])
                ->setBody($this->data['push_text']));
    }

    public function toDatabase(object $notifiable): array {
        return [
            'type' => $this->type,
            'title' => $this->data['subject'],
            'body' => $this->data['body'] ?? '',
            'action_url' => $this->data['action_url'] ?? null,
        ];
    }
}

// Notification preferences API
class NotificationPreferenceController extends Controller {
    public function index(Request $request): JsonResponse {
        $prefs = $request->user()->notificationPreferences()
            ->get()
            ->groupBy('type')
            ->map(fn ($group) => $group->pluck('enabled', 'channel'));

        return response()->json($prefs);
    }

    public function update(Request $request): JsonResponse {
        $request->validate([
            'preferences' => 'required|array',
            'preferences.*.type' => 'required|string',
            'preferences.*.channel' => 'required|in:email,sms,push,database',
            'preferences.*.enabled' => 'required|boolean',
        ]);

        foreach ($request->preferences as $pref) {
            $request->user()->notificationPreferences()->updateOrCreate(
                ['type' => $pref['type'], 'channel' => $pref['channel']],
                ['enabled' => $pref['enabled']],
            );
        }

        return response()->json(['message' => 'Tənzimləmələr yeniləndi.']);
    }
}
```

---

## 2. File Upload — Image Processing Pipeline

```php
// Upload → Validate → Resize → Optimize → Store → Create record

class ImageUploadService {
    public function upload(UploadedFile $file, string $directory, array $sizes = []): Media {
        // 1. Validate
        $this->validate($file);

        // 2. Generate unique name
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $basePath = "{$directory}/{$filename}";

        // 3. Original-ı yüklə
        $originalPath = $file->storeAs($directory, $filename, 'public');

        // 4. Media record yarat
        $media = Media::create([
            'original_name' => $file->getClientOriginalName(),
            'path' => $originalPath,
            'disk' => 'public',
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'dimensions' => $this->getDimensions($file),
        ]);

        // 5. Resize jobs — async
        if (!empty($sizes)) {
            ProcessImageVariants::dispatch($media, $sizes);
        }

        return $media;
    }

    private function validate(UploadedFile $file): void {
        $maxSize = 10 * 1024 * 1024; // 10MB
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

        if ($file->getSize() > $maxSize) {
            throw new FileTooLargeException($file->getSize(), $maxSize);
        }

        if (!in_array($file->getMimeType(), $allowedMimes)) {
            throw new InvalidMimeTypeException($file->getMimeType());
        }

        // Malware scan (ClamAV ilə)
        // $this->scanForMalware($file);
    }

    private function getDimensions(UploadedFile $file): ?array {
        $size = getimagesize($file->getPathname());
        return $size ? ['width' => $size[0], 'height' => $size[1]] : null;
    }
}

class ProcessImageVariants implements ShouldQueue {
    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(
        private Media $media,
        private array $sizes,
    ) {}

    public function handle(): void {
        $manager = new ImageManager(new Driver());
        $originalPath = Storage::disk($this->media->disk)->path($this->media->path);

        foreach ($this->sizes as $name => $dimensions) {
            $image = $manager->read($originalPath);

            // Resize (aspect ratio saxla)
            $image->scaleDown(
                width: $dimensions['width'],
                height: $dimensions['height'],
            );

            // WebP formatına çevir (daha kiçik)
            $variantFilename = pathinfo($this->media->path, PATHINFO_FILENAME);
            $variantPath = dirname($this->media->path) . "/{$variantFilename}_{$name}.webp";

            Storage::disk($this->media->disk)->put(
                $variantPath,
                $image->toWebp(quality: 80)->toString()
            );

            $this->media->variants()->create([
                'name' => $name,
                'path' => $variantPath,
                'width' => $dimensions['width'],
                'height' => $dimensions['height'],
            ]);
        }

        $this->media->update(['processed_at' => now()]);
    }
}

// İstifadə
class ProductImageController extends Controller {
    public function store(Request $request, Product $product): JsonResponse {
        $request->validate([
            'images' => 'required|array|max:10',
            'images.*' => 'required|image|max:10240',
        ]);

        $sizes = [
            'thumbnail' => ['width' => 150, 'height' => 150],
            'medium' => ['width' => 600, 'height' => 600],
            'large' => ['width' => 1200, 'height' => 1200],
        ];

        $uploadService = app(ImageUploadService::class);

        $media = collect($request->file('images'))->map(
            fn ($file) => $uploadService->upload($file, "products/{$product->id}", $sizes)
        );

        $product->images()->attach($media->pluck('id'));

        return MediaResource::collection($media)->response()->setStatusCode(201);
    }
}

// Presigned URL — böyük fayllar üçün direct S3 upload
class PresignedUploadController extends Controller {
    public function create(Request $request): JsonResponse {
        $request->validate([
            'filename' => 'required|string',
            'content_type' => 'required|string|in:image/jpeg,image/png,video/mp4',
        ]);

        $key = 'uploads/' . Str::uuid() . '/' . $request->filename;

        $command = Storage::disk('s3')->getClient()->getCommand('PutObject', [
            'Bucket' => config('filesystems.disks.s3.bucket'),
            'Key' => $key,
            'ContentType' => $request->content_type,
        ]);

        $url = (string) Storage::disk('s3')->getClient()
            ->createPresignedRequest($command, '+30 minutes')
            ->getUri();

        return response()->json([
            'upload_url' => $url,
            'key' => $key,
        ]);
    }
}
```

---

## 3. Real-time Chat sistemi (Laravel Reverb / Pusher)

```php
// Messages table: id, conversation_id, user_id, body, type, metadata, read_at, created_at
// Conversations table: id, type (direct/group), name, created_at
// conversation_user: conversation_id, user_id, last_read_at, is_muted

class ChatService {
    public function sendMessage(SendMessageDTO $dto): Message {
        $conversation = Conversation::findOrFail($dto->conversationId);

        // Yoxla — bu user bu conversation-ın üzvüdür?
        if (!$conversation->users()->where('user_id', $dto->userId)->exists()) {
            throw new ForbiddenException('Bu söhbətdə deyilsiniz.');
        }

        $message = DB::transaction(function () use ($conversation, $dto) {
            $message = $conversation->messages()->create([
                'user_id' => $dto->userId,
                'body' => $dto->body,
                'type' => $dto->type, // text, image, file, system
                'metadata' => $dto->metadata, // file url, image dimensions, etc.
            ]);

            // Son mesajı conversation-da saxla (denormalized)
            $conversation->update([
                'last_message_id' => $message->id,
                'last_message_at' => now(),
            ]);

            return $message;
        });

        // Real-time broadcast
        MessageSent::dispatch($message->load('user'));

        // Push notification (mute olmayanlara)
        $recipients = $conversation->users()
            ->where('user_id', '!=', $dto->userId)
            ->wherePivot('is_muted', false)
            ->get();

        Notification::send($recipients, new NewMessageNotification($message));

        return $message;
    }

    public function markAsRead(int $conversationId, int $userId): void {
        $conversation = Conversation::findOrFail($conversationId);

        $conversation->users()->updateExistingPivot($userId, [
            'last_read_at' => now(),
        ]);

        // Unread count broadcast
        $user = User::find($userId);
        $unreadCount = $this->getUnreadCount($user);
        UnreadCountUpdated::dispatch($user, $unreadCount);
    }

    public function getConversations(User $user): Collection {
        return $user->conversations()
            ->with([
                'lastMessage.user:id,name,avatar',
                'users:id,name,avatar',
            ])
            ->withCount(['messages as unread_count' => function ($query) use ($user) {
                $query->where('created_at', '>', function ($sub) use ($user) {
                    $sub->select('last_read_at')
                        ->from('conversation_user')
                        ->whereColumn('conversation_id', 'conversations.id')
                        ->where('user_id', $user->id);
                })->where('user_id', '!=', $user->id);
            }])
            ->orderByDesc('last_message_at')
            ->get();
    }

    private function getUnreadCount(User $user): int {
        return DB::table('messages')
            ->join('conversation_user', 'messages.conversation_id', '=', 'conversation_user.conversation_id')
            ->where('conversation_user.user_id', $user->id)
            ->where('messages.user_id', '!=', $user->id)
            ->whereRaw('messages.created_at > conversation_user.last_read_at')
            ->count();
    }
}

// Broadcasting events
class MessageSent implements ShouldBroadcast {
    use InteractsWithSockets, SerializesModels;

    public function __construct(public Message $message) {}

    public function broadcastOn(): array {
        return [new PresenceChannel('conversation.' . $this->message->conversation_id)];
    }

    public function broadcastWith(): array {
        return [
            'id' => $this->message->id,
            'body' => $this->message->body,
            'type' => $this->message->type,
            'user' => [
                'id' => $this->message->user->id,
                'name' => $this->message->user->name,
                'avatar' => $this->message->user->avatar_url,
            ],
            'created_at' => $this->message->created_at->toISOString(),
        ];
    }
}

// Typing indicator
class UserTyping implements ShouldBroadcast {
    use InteractsWithSockets;

    public function __construct(
        public int $conversationId,
        public int $userId,
        public string $userName,
    ) {}

    public function broadcastOn(): array {
        return [new PresenceChannel('conversation.' . $this->conversationId)];
    }
}
```

---

## 4. Export sistemi — Böyük data, async, download link

```php
// exports table: id, user_id, type, status, file_path, filters, error, started_at, completed_at

class ExportService {
    public function request(User $user, string $type, array $filters = []): Export {
        // Eyni anda 1-dən çox export olmasın
        $pending = Export::where('user_id', $user->id)
            ->whereIn('status', ['pending', 'processing'])
            ->first();

        if ($pending) {
            throw new ExportInProgressException($pending);
        }

        $export = Export::create([
            'user_id' => $user->id,
            'type' => $type,
            'status' => 'pending',
            'filters' => $filters,
        ]);

        ProcessExport::dispatch($export);

        return $export;
    }
}

class ProcessExport implements ShouldQueue {
    public int $timeout = 600; // 10 dəqiqə
    public int $tries = 2;

    public function __construct(private Export $export) {}

    public function handle(): void {
        $this->export->update(['status' => 'processing', 'started_at' => now()]);

        try {
            $exporter = match($this->export->type) {
                'orders' => app(OrderExporter::class),
                'users' => app(UserExporter::class),
                'products' => app(ProductExporter::class),
                default => throw new \InvalidArgumentException("Unknown export: {$this->export->type}"),
            };

            $filePath = $exporter->export($this->export->filters);

            $this->export->update([
                'status' => 'completed',
                'file_path' => $filePath,
                'completed_at' => now(),
            ]);

            // Bildiriş göndər
            $this->export->user->notify(new ExportReadyNotification($this->export));

        } catch (\Throwable $e) {
            $this->export->update([
                'status' => 'failed',
                'error' => $e->getMessage(),
                'completed_at' => now(),
            ]);
            throw $e;
        }
    }
}

class OrderExporter {
    public function export(array $filters): string {
        $filename = 'exports/orders_' . now()->format('Ymd_His') . '.csv';
        $handle = fopen(Storage::disk('local')->path($filename), 'w');

        // Header
        fputcsv($handle, ['Order #', 'Customer', 'Email', 'Total', 'Status', 'Date']);

        // Data — chunk ilə
        Order::query()
            ->with('user:id,name,email')
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['from'] ?? null, fn ($q, $d) => $q->where('created_at', '>=', $d))
            ->when($filters['to'] ?? null, fn ($q, $d) => $q->where('created_at', '<=', $d))
            ->chunkById(1000, function ($orders) use ($handle) {
                foreach ($orders as $order) {
                    fputcsv($handle, [
                        $order->order_number,
                        $order->user->name,
                        $order->user->email,
                        $order->total,
                        $order->status->value,
                        $order->created_at->toDateTimeString(),
                    ]);
                }
            });

        fclose($handle);
        return $filename;
    }
}

// Download endpoint (temporary signed URL)
class ExportController extends Controller {
    public function download(Export $export): Response {
        $this->authorize('download', $export);

        if ($export->status !== 'completed') {
            abort(404, 'Export hələ hazır deyil.');
        }

        return Storage::disk('local')->download(
            $export->file_path,
            "export_{$export->type}_{$export->created_at->format('Ymd')}.csv"
        );
    }

    // Signed URL (email-dən klik üçün)
    public function signedDownload(Export $export): JsonResponse {
        $url = URL::temporarySignedRoute(
            'exports.download',
            now()->addHours(24),
            ['export' => $export->id]
        );

        return response()->json(['download_url' => $url]);
    }
}
```

---

## 5. Booking / Reservation sistemi (race condition-suz)

```php
// Misal: Həkim görüşü, otel otağı, restoran masası

class BookingService {
    public function reserve(ReserveDTO $dto): Booking {
        // Atomic lock — eyni slot üçün eyni anda yalnız 1 request
        $lockKey = "booking:{$dto->resourceId}:{$dto->date}:{$dto->timeSlot}";

        return Cache::lock($lockKey, 10)->block(5, function () use ($dto) {
            // Double check — slot boşdur?
            $existing = Booking::where('resource_id', $dto->resourceId)
                ->where('date', $dto->date)
                ->where('time_slot', $dto->timeSlot)
                ->whereNotIn('status', ['cancelled'])
                ->first();

            if ($existing) {
                throw new SlotAlreadyBookedException($dto->timeSlot);
            }

            return DB::transaction(function () use ($dto) {
                $booking = Booking::create([
                    'user_id' => $dto->userId,
                    'resource_id' => $dto->resourceId,
                    'date' => $dto->date,
                    'time_slot' => $dto->timeSlot,
                    'status' => 'confirmed',
                    'notes' => $dto->notes,
                ]);

                BookingConfirmed::dispatch($booking);

                return $booking;
            });
        });
    }

    public function getAvailableSlots(int $resourceId, Carbon $date): array {
        $resource = Resource::findOrFail($resourceId);
        $dayOfWeek = $date->dayOfWeek;

        // Resource-un iş saatları
        $schedule = $resource->schedules()
            ->where('day_of_week', $dayOfWeek)
            ->first();

        if (!$schedule) {
            return []; // Bu gün işləmir
        }

        // Bütün mümkün slot-lar
        $allSlots = $this->generateSlots(
            $schedule->start_time,
            $schedule->end_time,
            $resource->slot_duration_minutes,
        );

        // Tutulmuş slot-lar
        $bookedSlots = Booking::where('resource_id', $resourceId)
            ->where('date', $date)
            ->whereNotIn('status', ['cancelled'])
            ->pluck('time_slot')
            ->toArray();

        // Keçmiş slot-ları sil (bu gün üçün)
        $now = now();
        if ($date->isToday()) {
            $allSlots = array_filter($allSlots, fn ($slot) =>
                Carbon::parse($date->format('Y-m-d') . ' ' . $slot)->gt($now)
            );
        }

        return array_values(array_filter($allSlots, fn ($slot) => !in_array($slot, $bookedSlots)));
    }

    private function generateSlots(string $start, string $end, int $duration): array {
        $slots = [];
        $current = Carbon::parse($start);
        $endTime = Carbon::parse($end);

        while ($current->lt($endTime)) {
            $slots[] = $current->format('H:i');
            $current->addMinutes($duration);
        }

        return $slots;
    }

    // Auto-cancel — 15 dəqiqə ödəniş olmazsa ləğv et
    public function autoCancelExpiredBookings(): int {
        $expired = Booking::where('status', 'pending_payment')
            ->where('created_at', '<', now()->subMinutes(15))
            ->get();

        foreach ($expired as $booking) {
            $booking->update(['status' => 'cancelled', 'cancel_reason' => 'Payment timeout']);
            BookingCancelled::dispatch($booking);
        }

        return $expired->count();
    }
}
```

---

## 6. Leaderboard / Ranking sistemi (Redis Sorted Set)

```php
class LeaderboardService {
    private string $key;

    public function __construct(string $name = 'global') {
        $this->key = "leaderboard:{$name}";
    }

    public function addScore(int $userId, float $score): void {
        Redis::zincrby($this->key, $score, "user:{$userId}");
    }

    public function setScore(int $userId, float $score): void {
        Redis::zadd($this->key, $score, "user:{$userId}");
    }

    public function getRank(int $userId): ?int {
        $rank = Redis::zrevrank($this->key, "user:{$userId}");
        return $rank !== null ? $rank + 1 : null; // 0-based → 1-based
    }

    public function getScore(int $userId): float {
        return (float) Redis::zscore($this->key, "user:{$userId}");
    }

    public function getTopN(int $n = 10): array {
        $results = Redis::zrevrange($this->key, 0, $n - 1, ['WITHSCORES' => true]);

        $leaderboard = [];
        $rank = 1;
        foreach ($results as $member => $score) {
            $userId = (int) str_replace('user:', '', $member);
            $leaderboard[] = [
                'rank' => $rank++,
                'user_id' => $userId,
                'score' => (float) $score,
            ];
        }

        // User məlumatlarını əlavə et
        $userIds = array_column($leaderboard, 'user_id');
        $users = User::whereIn('id', $userIds)->get()->keyBy('id');

        return array_map(function ($entry) use ($users) {
            $user = $users[$entry['user_id']] ?? null;
            $entry['name'] = $user?->name;
            $entry['avatar'] = $user?->avatar_url;
            return $entry;
        }, $leaderboard);
    }

    // Aylıq leaderboard — ayın sonunda sıfırla
    public function resetMonthly(): void {
        $archiveKey = $this->key . ':' . now()->subMonth()->format('Y-m');
        Redis::rename($this->key, $archiveKey);
        Redis::expire($archiveKey, 86400 * 90); // 3 ay saxla
    }
}
```
