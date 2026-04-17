# File Storage

## Nədir? (What is it?)

File storage sistemi istifadəçi fayllarını (şəkillər, videolar, sənədlər) etibarlı
şəkildə saxlamaq, idarə etmək və çatdırmaq üçün arxitekturadır. Modern tətbiqlər
cloud object storage (S3) istifadə edir - burada fayllar key-value pairs kimi saxlanır.

Sadə dillə: bulud anbar xidməti kimi düşünün - fayllarınızı göndərirsiniz, onlar
təhlükəsiz saxlanır, istənilən vaxt əldə edə bilirsiniz.

```
┌──────────┐     ┌──────────┐     ┌───────────────┐
│  Client  │────▶│   API    │────▶│ Object Storage│
│          │     │  Server  │     │    (S3)       │
│ Upload   │     │          │     │               │
│ file     │     │ Process  │     │ bucket/       │
│          │     │ + Store  │     │   key/file    │
└──────────┘     └──────────┘     └───────────────┘
```

## Əsas Konseptlər (Key Concepts)

### Object Storage vs File System vs Block Storage

```
File System (EXT4, NFS):
  - Hierarchical (folders/files)
  - POSIX compliant
  - Good for OS, applications
  - Limited scalability

Block Storage (EBS):
  - Raw storage blocks
  - Formatted with filesystem
  - Good for databases
  - Attached to single instance

Object Storage (S3):
  - Flat namespace (bucket + key)
  - HTTP API access
  - Unlimited scalability
  - Best for unstructured data (images, videos, backups)
  - 99.999999999% durability (S3)
```

### Pre-signed URLs

Server tərəfindən imzalanmış, müvəqqəti URL - client birbaşa S3-ə yükləyir:

```
Traditional Upload:          Pre-signed URL Upload:
Client → Server → S3         Client → Server (get URL)
(server bottleneck)          Client → S3 directly (fast)

┌────────┐  1. Request URL  ┌────────┐
│ Client │ ───────────────▶ │ Server │
│        │                  │        │
│        │  2. Pre-signed   │ Generate│
│        │ ◀─────────────── │  URL   │
│        │     URL          └────────┘
│        │
│        │  3. Upload directly to S3
│        │ ───────────────────────────▶ ┌────┐
│        │                              │ S3 │
│        │  4. Success                  │    │
│        │ ◀─────────────────────────── │    │
└────────┘                              └────┘
```

### Chunked Upload

Böyük faylları kiçik parçalara böləb yükləmək:

```
100MB File:
  Chunk 1: 0-10MB    ✓ uploaded
  Chunk 2: 10-20MB   ✓ uploaded
  Chunk 3: 20-30MB   ✗ failed → retry only this chunk
  Chunk 4: 30-40MB   ✓ uploaded
  ...
  Chunk 10: 90-100MB ✓ uploaded

  → Complete multipart upload
```

### Image Processing Pipeline

```
Original Upload (5MB JPEG)
        │
        ▼
┌──────────────────┐
│  Image Processor │
│                  │
│  ├─ Validate     │  (type, size, dimensions)
│  ├─ Strip EXIF   │  (privacy - GPS data etc)
│  ├─ Generate     │
│  │  thumbnails:  │
│  │  ├─ 150x150   │  (avatar)
│  │  ├─ 400x300   │  (card)
│  │  └─ 1200x900  │  (detail)
│  ├─ Optimize     │  (compress, WebP convert)
│  └─ CDN push     │
└──────────────────┘
```

## Arxitektura (Architecture)

### Complete File Storage System

```
┌────────────┐
│   Client   │
└─────┬──────┘
      │
┌─────┴──────────────────────────────────────┐
│              API Gateway                    │
└─────┬──────────────────────────────────────┘
      │
┌─────┴──────┐     ┌────────────┐
│  Upload    │────▶│  File      │
│  Service   │     │  Metadata  │
│            │     │  DB        │
│ Pre-signed │     │ (Postgres) │
│ URL gen    │     └────────────┘
└─────┬──────┘
      │
┌─────┴──────┐     ┌────────────┐
│    S3      │────▶│  Processing│
│  Bucket    │     │  Queue     │
│            │ S3  │            │
│ /originals │event│ Thumbnails │
│ /processed │     │ Optimize   │
└────────────┘     └─────┬──────┘
                         │
                   ┌─────┴──────┐
                   │    CDN     │
                   │(CloudFront)│
                   └────────────┘
```

## PHP/Laravel ilə Tətbiq (Implementation with PHP/Laravel)

### Laravel Storage Facade

```php
// config/filesystems.php
'disks' => [
    'local' => [
        'driver' => 'local',
        'root' => storage_path('app'),
    ],
    's3' => [
        'driver' => 's3',
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'eu-central-1'),
        'bucket' => env('AWS_BUCKET'),
        'url' => env('AWS_URL'),
    ],
    's3-public' => [
        'driver' => 's3',
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION'),
        'bucket' => env('AWS_PUBLIC_BUCKET'),
        'visibility' => 'public',
    ],
],
```

### File Upload Controller

```php
class FileUploadController extends Controller
{
    public function __construct(
        private FileService $fileService
    ) {}

    // Traditional upload through server
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:10240'], // 10MB
            'type' => ['required', 'in:avatar,document,image'],
        ]);

        $result = $this->fileService->upload(
            file: $request->file('file'),
            type: $request->input('type'),
            userId: auth()->id()
        );

        return response()->json($result, 201);
    }

    // Pre-signed URL for direct S3 upload
    public function getUploadUrl(Request $request): JsonResponse
    {
        $request->validate([
            'filename' => ['required', 'string'],
            'content_type' => ['required', 'string'],
            'size' => ['required', 'integer', 'max:104857600'], // 100MB
        ]);

        $result = $this->fileService->generateUploadUrl(
            filename: $request->input('filename'),
            contentType: $request->input('content_type'),
            size: $request->input('size'),
            userId: auth()->id()
        );

        return response()->json($result);
    }

    // Confirm upload completed (after pre-signed URL upload)
    public function confirmUpload(Request $request, string $fileId): JsonResponse
    {
        $file = $this->fileService->confirmUpload($fileId, auth()->id());

        return response()->json(new FileResource($file));
    }
}
```

### File Service

```php
class FileService
{
    private array $allowedTypes = [
        'avatar' => ['image/jpeg', 'image/png', 'image/webp'],
        'document' => ['application/pdf', 'application/msword'],
        'image' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
    ];

    private array $maxSizes = [
        'avatar' => 5 * 1024 * 1024,    // 5MB
        'document' => 20 * 1024 * 1024,  // 20MB
        'image' => 10 * 1024 * 1024,     // 10MB
    ];

    public function upload(UploadedFile $file, string $type, int $userId): array
    {
        $this->validateFile($file, $type);

        $path = $this->generatePath($type, $userId, $file->getClientOriginalExtension());

        // Upload to S3
        Storage::disk('s3')->put($path, file_get_contents($file), [
            'ContentType' => $file->getMimeType(),
        ]);

        // Save metadata
        $fileRecord = FileUpload::create([
            'user_id' => $userId,
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'disk' => 's3',
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'type' => $type,
        ]);

        // Dispatch processing job
        if ($type === 'image' || $type === 'avatar') {
            ProcessImage::dispatch($fileRecord);
        }

        return [
            'id' => $fileRecord->id,
            'url' => $this->getUrl($fileRecord),
        ];
    }

    public function generateUploadUrl(
        string $filename, string $contentType, int $size, int $userId
    ): array {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $path = $this->generatePath('image', $userId, $extension);

        // Create pending file record
        $fileRecord = FileUpload::create([
            'user_id' => $userId,
            'original_name' => $filename,
            'path' => $path,
            'disk' => 's3',
            'mime_type' => $contentType,
            'size' => $size,
            'status' => 'pending',
        ]);

        // Generate pre-signed URL
        $client = Storage::disk('s3')->getClient();
        $command = $client->getCommand('PutObject', [
            'Bucket' => config('filesystems.disks.s3.bucket'),
            'Key' => $path,
            'ContentType' => $contentType,
            'ContentLength' => $size,
        ]);

        $presignedUrl = (string) $client
            ->createPresignedRequest($command, '+15 minutes')
            ->getUri();

        return [
            'file_id' => $fileRecord->id,
            'upload_url' => $presignedUrl,
            'expires_in' => 900,
        ];
    }

    public function getTemporaryUrl(FileUpload $file, int $minutes = 60): string
    {
        return Storage::disk($file->disk)
            ->temporaryUrl($file->path, now()->addMinutes($minutes));
    }

    private function generatePath(string $type, int $userId, string $ext): string
    {
        $date = now()->format('Y/m/d');
        $hash = Str::random(32);
        return "{$type}/{$date}/{$userId}/{$hash}.{$ext}";
    }

    private function validateFile(UploadedFile $file, string $type): void
    {
        if (!in_array($file->getMimeType(), $this->allowedTypes[$type] ?? [])) {
            throw ValidationException::withMessages([
                'file' => "Invalid file type for {$type}",
            ]);
        }

        if ($file->getSize() > ($this->maxSizes[$type] ?? 0)) {
            throw ValidationException::withMessages([
                'file' => "File too large for {$type}",
            ]);
        }
    }
}
```

### Image Processing Job

```php
class ProcessImage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(private FileUpload $file) {}

    public function handle(): void
    {
        $original = Storage::disk('s3')->get($this->file->path);
        $image = Image::make($original);

        // Strip EXIF data for privacy
        $image->orientate();

        $variants = [
            'thumb' => [150, 150],
            'medium' => [400, 300],
            'large' => [1200, 900],
        ];

        $paths = [];
        foreach ($variants as $name => [$width, $height]) {
            $resized = clone $image;
            $resized->fit($width, $height);

            $variantPath = $this->getVariantPath($name);
            Storage::disk('s3')->put($variantPath, $resized->encode('webp', 80), [
                'ContentType' => 'image/webp',
            ]);

            $paths[$name] = $variantPath;
        }

        $this->file->update([
            'variants' => $paths,
            'status' => 'processed',
        ]);
    }

    private function getVariantPath(string $variant): string
    {
        $info = pathinfo($this->file->path);
        return "{$info['dirname']}/{$info['filename']}_{$variant}.webp";
    }
}
```

### Chunked Upload

```php
class ChunkedUploadController extends Controller
{
    // Step 1: Initialize multipart upload
    public function initiate(Request $request): JsonResponse
    {
        $request->validate([
            'filename' => 'required|string',
            'total_size' => 'required|integer',
            'total_chunks' => 'required|integer',
        ]);

        $uploadId = Str::uuid();
        $path = "uploads/chunked/{$uploadId}/{$request->filename}";

        Cache::put("chunked_upload:{$uploadId}", [
            'path' => $path,
            'total_chunks' => $request->total_chunks,
            'uploaded_chunks' => [],
            'user_id' => auth()->id(),
        ], now()->addHours(24));

        return response()->json(['upload_id' => $uploadId]);
    }

    // Step 2: Upload each chunk
    public function uploadChunk(Request $request, string $uploadId): JsonResponse
    {
        $meta = Cache::get("chunked_upload:{$uploadId}");
        abort_unless($meta, 404);

        $chunkNumber = $request->input('chunk_number');
        $chunkPath = "chunks/{$uploadId}/{$chunkNumber}";

        Storage::disk('local')->put($chunkPath, $request->file('chunk')->get());

        $meta['uploaded_chunks'][] = $chunkNumber;
        Cache::put("chunked_upload:{$uploadId}", $meta, now()->addHours(24));

        return response()->json([
            'chunk' => $chunkNumber,
            'uploaded' => count($meta['uploaded_chunks']),
            'total' => $meta['total_chunks'],
        ]);
    }

    // Step 3: Assemble chunks
    public function complete(string $uploadId): JsonResponse
    {
        $meta = Cache::get("chunked_upload:{$uploadId}");

        // Assemble file
        AssembleChunkedUpload::dispatch($uploadId, $meta);

        return response()->json(['status' => 'processing']);
    }
}
```

## Real-World Nümunələr

1. **Dropbox** - File sync, chunked upload, deduplication
2. **Instagram** - Image upload, multiple size variants, CDN delivery
3. **YouTube** - Chunked video upload, transcoding pipeline
4. **Google Drive** - Multi-format storage, real-time collaboration
5. **Imgur** - Image hosting, on-the-fly resizing

## Interview Sualları

**S1: Pre-signed URL nədir və niyə istifadə olunur?**
C: Server tərəfindən imzalanmış, müvəqqəti URL-dir. Client birbaşa S3-ə yükləyir,
server bottleneck olmur. Bandwidth, CPU, memory qənaət olunur. Expiration time ilə
təhlükəsizlik təmin olunur.

**S2: Böyük fayl upload necə idarə olunur?**
C: Multipart/chunked upload - fayl kiçik parçalara bölünür (5-10MB), hər parça
ayrıca yüklənir, uğursuz parça retry olunur. S3 multipart upload API dəstəkləyir.
Progress tracking mümkündür.

**S3: Image processing niyə async olmalıdır?**
C: Image resize, format conversion CPU-intensive əməliyyatlardır. Sync etsək request
timeout ola bilər, user gözləyir. Queue-da background job ilə emal edib, hazır
olanda notification göndərmək daha yaxşıdır.

**S4: File deduplication necə edilir?**
C: Upload zamanı file content-in hash-ini (SHA256) hesablayın. Eyni hash artıq
varsa, yeni fayl yükləmək əvəzinə mövcud fayla reference yaradın. Storage
qənaət olunur.

**S5: Faylları necə təhlükəsiz saxlamaq olar?**
C: Private bucket (public access yox), pre-signed URL ilə müvəqqəti access,
server-side encryption (SSE-S3, SSE-KMS), access logs, IAM policies.
Sensitive fayllar üçün client-side encryption də mümkündür.

**S6: CDN ilə file delivery necə optimize olunur?**
C: Statik faylları CDN edge location-lara cache edin. S3-ı origin kimi istifadə edin.
Cache headers (Cache-Control, ETag) ilə cache policy təyin edin. Purge/invalidation
strategiyası olsun. Custom domain + SSL.

## Best Practices

1. **Pre-signed URLs** - Server üzərindən traffic keçirməyin
2. **Virus Scanning** - Upload olunan faylları scan edin
3. **File Type Validation** - MIME type + magic bytes yoxlayın
4. **Size Limits** - Hər file type üçün max size təyin edin
5. **Unique Filenames** - UUID/hash istifadə edin, collision-dan qaçının
6. **CDN** - Statik fayllar CDN-dən serve edin
7. **Lifecycle Policies** - Köhnə faylları avtomatik archive/delete edin
8. **Backup** - Cross-region replication aktiv edin
9. **Async Processing** - Image/video processing queue-da edin
10. **Access Control** - Hər fayla düzgün permission təyin edin
