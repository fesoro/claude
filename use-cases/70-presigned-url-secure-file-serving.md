# Presigned URLs & Secure File Serving (Middle)

## Problem Təsviri

Real ssenari: Sizin Laravel application-da user-lər invoice, kontrakt, müqavilə fayllarını yükləyir. Bu fayllar S3-də saxlanır. Authenticated user **öz fayllarını** download etməlidir, amma başqasının faylını yox.

Üç yanaşma mövcuddur, üçü də problemli:

```
1. Public S3 bucket
   └── URL paylasilmasi: hər kəs download edə bilər
   └── Brute force: Faylların URL-lərini guess edib download edə bilərsiz
   └── PROBLEM: Təhlükəsizlik faciəsi

2. Server üzərindən proxy (sinxron stream)
   └── User → Laravel → S3 → Laravel → User
   └── PROBLEM: Hər download server CPU + bandwidth tutur
   └── 1GB fayl = 1GB server bandwidth + CPU
   └── 100 concurrent download = server göl

3. AWS credentials-i client-ə vermək
   └── PROBLEM: Tam təhlükəsizlik faciəsi (full S3 access)
```

### Problem niyə yaranır?

Səbəbi məcaza-spesifikdir: S3 (və benzər object storage-lar) **iki vəziyyətə** malikdir — public (hər kəs URL-lə oxuyur) və ya private (yalnız AWS credentials-li sorğu işləyir). **Aralıqda** vəziyyət yoxdur — "yalnız bu user 15 dəqiqə ərzində oxuya bilər" kimi.

Bu problemi həll edən mexanizm **presigned URL**-dir: AWS-in kriptoqrafik imzaladığı temporal URL. URL-də `X-Amz-Signature`, `X-Amz-Expires` parametrləri var. S3 imzanı yoxlayır — etibarlı və müddət bitməyibsə, fayl qaytarılır. Server bu URL-i generate etmək üçün AWS credentials-dən istifadə edir, amma URL özündə credentials YOXDUR — yalnız imza var.

---

## Həll 1: S3 Presigned URL (Tövsiyə Olunur)

### Konsept

```
1. User authenticated request: GET /api/files/123/download-url
2. Laravel: 
   - Authorization yoxla — user bu faylın sahibidir?
   - S3 SDK-dan presigned URL generate et (15 dəq valid)
3. Response: { "download_url": "https://s3.amazonaws.com/...?X-Amz-Signature=..." }
4. User browser direkt S3-dən download edir
   ↑ Laravel server burada İŞTİRAK ETMIR
   ↑ Bandwidth, CPU yox
```

### Service İmplementasiyası

*Bu kod presigned URL generate edən servisi göstərir:*

```php
// app/Services/FileAccessService.php
namespace App\Services;

use Illuminate\Support\Facades\Storage;

class FileAccessService
{
    /**
     * S3 obyekti üçün müvəqqəti download URL generate edir.
     */
    public function generateDownloadUrl(
        string $s3Key,
        int $expiresInMinutes = 15,
        ?string $originalFilename = null,
        ?string $contentType = null
    ): string {
        $disk = Storage::disk('s3');

        $headers = [];

        if ($originalFilename) {
            // Browser-ə "Download as ..." dialog versin
            $encoded = rawurlencode($originalFilename);
            $headers['ResponseContentDisposition'] = 
                "attachment; filename=\"{$originalFilename}\"; filename*=UTF-8''{$encoded}";
        }

        if ($contentType) {
            $headers['ResponseContentType'] = $contentType;
        }

        // Laravel 9+ Storage::temporaryUrl
        return $disk->temporaryUrl(
            path: $s3Key,
            expiration: now()->addMinutes($expiresInMinutes),
            options: $headers
        );
    }

    /**
     * Upload üçün presigned URL — browser-dən birbaşa S3-yə yükləmək.
     */
    public function generateUploadUrl(
        string $s3Key,
        int $expiresInMinutes = 15,
        int $maxSizeMb = 50,
        string $contentType = 'application/octet-stream'
    ): array {
        $client = Storage::disk('s3')->getClient();
        $bucket = config('filesystems.disks.s3.bucket');

        $command = $client->getCommand('PutObject', [
            'Bucket' => $bucket,
            'Key' => $s3Key,
            'ContentType' => $contentType,
            'ContentLength' => $maxSizeMb * 1024 * 1024,
        ]);

        $request = $client->createPresignedRequest(
            $command,
            "+{$expiresInMinutes} minutes"
        );

        return [
            'upload_url' => (string) $request->getUri(),
            'method' => 'PUT',
            'headers' => [
                'Content-Type' => $contentType,
            ],
            'expires_at' => now()->addMinutes($expiresInMinutes)->toIso8601String(),
        ];
    }
}
```

### Controller İmplementasiyası

*Bu kod authorization yoxlayan və download URL qaytaran controller-i göstərir:*

```php
// app/Http/Controllers/FileDownloadController.php
namespace App\Http\Controllers;

use App\Models\File;
use App\Models\FileAccessLog;
use App\Services\FileAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FileDownloadController extends Controller
{
    public function __construct(
        private FileAccessService $fileAccess
    ) {}

    public function generateDownloadUrl(Request $request, File $file): JsonResponse
    {
        // 1. Authorization — user bu faylın sahibidir?
        $this->authorize('download', $file);

        // 2. Audit log — kim, nə zaman, hansı IP
        FileAccessLog::create([
            'file_id' => $file->id,
            'user_id' => $request->user()->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'accessed_at' => now(),
        ]);

        // 3. Presigned URL generate et
        $expiresInMinutes = 15;
        $url = $this->fileAccess->generateDownloadUrl(
            s3Key: $file->storage_path,
            expiresInMinutes: $expiresInMinutes,
            originalFilename: $file->original_name,
            contentType: $file->mime_type,
        );

        return response()->json([
            'download_url' => $url,
            'expires_at' => now()->addMinutes($expiresInMinutes)->toIso8601String(),
            'file' => [
                'name' => $file->original_name,
                'size_bytes' => $file->size_bytes,
                'mime_type' => $file->mime_type,
            ],
        ]);
    }
}
```

### Policy

*Bu kod fayl sahibliyini yoxlayan policy-ni göstərir:*

```php
// app/Policies/FilePolicy.php
namespace App\Policies;

use App\Models\File;
use App\Models\User;

class FilePolicy
{
    public function download(User $user, File $file): bool
    {
        // 1. Sahibi
        if ($file->user_id === $user->id) {
            return true;
        }

        // 2. Paylaşılıb (məsələn, kontrakt 2 user arasında)
        if ($file->sharedWith()->where('user_id', $user->id)->exists()) {
            return true;
        }

        // 3. Admin
        if ($user->isAdmin()) {
            return true;
        }

        return false;
    }
}
```

---

## Həll 2: Laravel Temporary Signed Route (Server Proxy)

Bəzi hallarda server-dən proxy etmək lazımdır:

- **Watermark əlavə etmək** (download zamanı PDF-ə user adı yazmaq)
- **Detailed audit** (hər byte log etmək, throughput tracking)
- **Bandwidth limiti** (subscription-a görə download speed throttle)
- **DRM** (encryption key user-ə görə)

*Bu kod Laravel signed route ilə proxy download-u göstərir:*

```php
// routes/web.php
use App\Http\Controllers\FileProxyController;

Route::get('/files/{file}/proxy-download', [FileProxyController::class, 'download'])
    ->name('file.proxy-download')
    ->middleware(['auth', 'signed']); // signed middleware imzanı yoxlayır
```

```php
// Generate URL
$url = URL::temporarySignedRoute(
    name: 'file.proxy-download',
    expiration: now()->addHour(),
    parameters: ['file' => $file->id]
);
```

```php
// app/Http/Controllers/FileProxyController.php
namespace App\Http\Controllers;

use App\Models\File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileProxyController extends Controller
{
    public function download(Request $request, File $file): StreamedResponse
    {
        $this->authorize('download', $file);

        // Audit
        $this->logDownload($request, $file);

        // S3-dən stream et — server memory-də saxlamır
        return new StreamedResponse(
            callback: function () use ($file) {
                $stream = Storage::disk('s3')->readStream($file->storage_path);

                while (!feof($stream)) {
                    echo fread($stream, 8192); // 8KB chunks
                    flush();
                }

                fclose($stream);
            },
            status: 200,
            headers: [
                'Content-Type' => $file->mime_type,
                'Content-Length' => $file->size_bytes,
                'Content-Disposition' => "attachment; filename=\"{$file->original_name}\"",
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
            ]
        );
    }

    private function logDownload(Request $request, File $file): void
    {
        FileAccessLog::create([
            'file_id' => $file->id,
            'user_id' => $request->user()->id,
            'ip_address' => $request->ip(),
            'method' => 'proxy',
            'accessed_at' => now(),
        ]);
    }
}
```

---

## Həll 3: Download Token (DB-əsaslı)

Yüksək təhlükəsizlikli ssenarilər üçün — hər download üçün **single-use token**:

*Bu kod DB-əsaslı download token sistemini göstərir:*

```php
// Migration
Schema::create('download_tokens', function (Blueprint $table) {
    $table->id();
    $table->string('token', 64)->unique();
    $table->foreignId('file_id')->constrained();
    $table->foreignId('user_id')->constrained();
    $table->timestamp('expires_at');
    $table->timestamp('used_at')->nullable();
    $table->string('used_ip')->nullable();
    $table->timestamps();

    $table->index(['token', 'expires_at']);
});
```

```php
// app/Models/DownloadToken.php
namespace App\Models;

use App\Models\File;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class DownloadToken extends Model
{
    protected $fillable = ['token', 'file_id', 'user_id', 'expires_at', 'used_at', 'used_ip'];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    public static function generate(File $file, User $user, int $minutes = 60): self
    {
        return self::create([
            'token' => Str::random(64),
            'file_id' => $file->id,
            'user_id' => $user->id,
            'expires_at' => now()->addMinutes($minutes),
        ]);
    }

    public function isValid(): bool
    {
        return $this->used_at === null
            && $this->expires_at->isFuture();
    }

    public function consume(string $ip): bool
    {
        // Atomic update — race condition-suz
        $affected = static::where('id', $this->id)
            ->whereNull('used_at')
            ->update([
                'used_at' => now(),
                'used_ip' => $ip,
            ]);

        return $affected === 1;
    }
}
```

```php
// app/Http/Controllers/TokenizedDownloadController.php
namespace App\Http\Controllers;

use App\Models\DownloadToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TokenizedDownloadController extends Controller
{
    public function generate(Request $request, File $file)
    {
        $this->authorize('download', $file);

        $token = DownloadToken::generate(
            file: $file,
            user: $request->user(),
            minutes: 60
        );

        return response()->json([
            'download_url' => route('file.tokenized-download', $token->token),
            'expires_at' => $token->expires_at->toIso8601String(),
        ]);
    }

    public function download(Request $request, string $token)
    {
        $tokenRecord = DownloadToken::where('token', $token)->firstOrFail();

        if (!$tokenRecord->isValid()) {
            abort(410, 'Bu download link expired və ya artıq istifadə olunub.');
        }

        // Atomic consume — yalnız bir dəfə istifadə oluna bilər
        if (!$tokenRecord->consume($request->ip())) {
            abort(410, 'Token artıq istifadə olunub.');
        }

        $file = $tokenRecord->file;

        // S3-dən presigned URL-ə redirect (sürətli)
        return redirect(
            Storage::disk('s3')->temporaryUrl(
                $file->storage_path,
                now()->addMinutes(5)
            )
        );
    }
}
```

---

## Upload → Private Storage Pattern

User upload-u həmişə **PRIVATE** path-a getməlidir:

*Bu kod private path-a fayl yükləmənin tam implementasiyasını göstərir:*

```php
// app/Http/Controllers/FileUploadController.php
namespace App\Http\Controllers;

use App\Models\File;
use App\Http\Resources\FileResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileUploadController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:51200|mimes:pdf,docx,xlsx,jpg,png',
        ]);

        $uploadedFile = $request->file('file');

        // Private path — user_id-yə görə qruplaşdır, UUID adı ver
        // Format: users/{user_id}/documents/{YYYY/MM}/{uuid}.{ext}
        $userId = $request->user()->id;
        $year = now()->format('Y');
        $month = now()->format('m');
        $uuid = (string) Str::uuid();
        $ext = $uploadedFile->getClientOriginalExtension();

        $path = "users/{$userId}/documents/{$year}/{$month}/{$uuid}.{$ext}";

        // S3-yə private upload (visibility: private — public deyil)
        Storage::disk('s3')->put(
            path: $path,
            contents: file_get_contents($uploadedFile->getRealPath()),
            options: [
                'visibility' => 'private', // Critical!
                'ContentType' => $uploadedFile->getMimeType(),
            ]
        );

        // DB-də metadata saxla
        $file = File::create([
            'user_id' => $userId,
            'storage_path' => $path,
            'original_name' => $uploadedFile->getClientOriginalName(),
            'mime_type' => $uploadedFile->getMimeType(),
            'size_bytes' => $uploadedFile->getSize(),
        ]);

        return response()->json(FileResource::make($file), 201);
    }
}
```

---

## Trade-offs

| Yanaşma | Server yükü | Control | CDN uyğunluğu | Setup complexity | Nə zaman |
|---------|------------|---------|----------------|------------------|----------|
| **S3 Presigned URL** | Sıfır (URL-i client istifadə edir) | Aşağı (URL paylaşıla bilər) | OK (CloudFront signed URL) | Aşağı | 95% halda — default seçim |
| **Laravel Proxy (StreamedResponse)** | Yüksək (bandwidth, CPU) | Tam (watermark, throttle) | Çətin | Orta | DRM, watermark, audit per-byte |
| **Download Token (DB)** | Aşağı | Tam (single-use) | OK | Yüksək | Çox sensitive (medical, legal) |
| **CloudFront Signed URL** | Sıfır | Orta | Mükəmməl | Yüksək | Global users, large files |

---

## Anti-patternlər

**1. S3 bucket-i public etmək**
"Easy fix" deyə bucket-i public edirsiniz — hər kəs hər faylı download edə bilər. URL-lər guess edilə bilər (`/uploads/invoice_123.pdf`). **Real incident:** Boyuk şirkətlər bucket misconfig səbəbindən millyonlarla user dataı leaks edib. Solution: bucket həmişə private, presigned URL istifadə et.

**2. Faylın real S3 path-ini URL-də açmaq**
`GET /api/download?path=users/123/secret-file.pdf` — user URL-i dəyişib `users/456/...` path-ı istəyə bilər (path traversal). Solution: faylın **DB id-sini** istifadə et (`/api/files/789/download`), policy-də sahibliyi yoxla, S3 path-ı server-də əldə et.

**3. Presigned URL expiry-ni çox uzun etmək (24 saat, 7 gün)**
Uzun URL = uzun risk. URL log-larda, Slack-da, email-də paylaşıla bilər. Standart: **15 dəqiqə**. User download başlayıb yarıda dayandırsa, yenidən request etsin. Müstəsna: çox böyük fayllar (multi-GB) — 1-2 saat OK.

**4. File download-dan əvvəl authorization yoxlamamaq**
Generate URL endpoint-i sadəcə `Storage::temporaryUrl()` qaytarır — **policy çağırmır**. User başqasının file_id-sini guess edib URL əldə edə bilər. Solution: `$this->authorize('download', $file)` mütləq olmalıdır.

**5. File path-ini user-controllable etmək**
Upload zamanı `$path = $request->input('path')` — **path traversal**. User `../../etc/passwd` göndərə bilər. Solution: server tərəfində UUID generate et, original filename DB-də saxla. User real path-ı heç vaxt görməsin.

**6. Download-ları log etməmək (compliance)**
Medical/legal fayllar üçün **audit** məcburidir — kim, nə zaman, hansı IP-dən download etdi. Compliance audit zamanı sübut edə bilməlisiniz. `file_access_logs` cədvəli yaradın və hər download-u qeyd edin.

**7. Large file-ı server memory-ə tam yükləmək**
`Storage::download($path)` kiçik fayl üçün OK, amma 5GB fayl üçün **OOM**-a aparır. Solution: `StreamedResponse` ilə chunk-chunk stream et (`fread($stream, 8192)`). Daha yaxşı: presigned URL ilə tamamən bypass et — server toxunmur.

---

## Interview Sualları və Cavablar

**S: Presigned URL nədir, necə işləyir?**
C: Presigned URL — AWS-in **HMAC-SHA256 imzası** olan temporal URL-dir. Server presigned URL generate edir: AWS access key + secret + bucket + key + expiration timestamp + HTTP method-u imzalayır. URL-də `X-Amz-Signature`, `X-Amz-Expires`, `X-Amz-Date` parametrləri var. S3 sorğunu qəbul edəndə imzanı yenidən hesablayır və müqayisə edir — match olarsa və müddət bitməyibsə, fayl qaytarılır. URL-də **AWS credentials yoxdur** — yalnız imza var. Bu yanaşma server-i bandwidth-dən azad edir, eyni zamanda full credential leak olmur.

**S: Presigned URL expiry strategiyası — nə qədər olmalıdır?**
C: Asılıdır: (1) **Standart download** — 15 dəqiqə (user click edir, browser download başlayır, kifayət edir). (2) **Background process** download (məs: scheduled job archive download) — 1 saat. (3) **Large file** (multi-GB) — 2-3 saat (slow connection-da kifayət olsun). (4) **Email-də göndərilən URL** — 24-48 saat, amma daha yaxşı: email-də Laravel signed route, click → fresh presigned URL. **Heç vaxt > 7 gün** — log-larda paylaşıla bilər. **Heç vaxt < 1 dəqiqə** — slow connection-larda fail.

**S: Proxy download vs direct presigned URL — hansını seçərdiniz?**
C: 95% hallarda **presigned URL** — server-ı bandwidth-dən azad edir, scaling problemi yox. **Proxy** yalnız bu hallarda: (1) **Watermark** — PDF-ə user adı dinamik yazmaq. (2) **DRM** — fayl encrypted, decrypt key user-ə görə. (3) **Bandwidth throttling** — free user 100KB/s, premium 10MB/s. (4) **Per-byte audit** — banking/medical compliance. Proxy maintain etmək üçün CDN konfiqurasiyası, large file streaming, OOM prevention lazımdır — sadə deyil.

**S: CloudFront signed cookies vs signed URLs?**
C: **Signed URL** — bir specific resource üçün. **Signed cookie** — bir CloudFront distribution daxilində bütün resource-lara icazə verir. Use case fərqi: video streaming HLS — manifest.m3u8 + 100+ segment fayl. Hər segment üçün signed URL impractical (URL list çox böyük). Solution: signed cookie set et browser-də (10-cu segmentdə nə zaman expire olur), bütün segment request-lər avtomatik authenticated. Documentation streaming-də signed URL daha sadə.

**S: Multipart upload large files üçün necə implement edirsiniz?**
C: Browser-dən birbaşa S3-yə multipart upload — large files (> 100MB) üçün essential: (1) **Initiate** — server generate edir `CreateMultipartUpload` presigned URL → upload_id qaytarır. (2) **Upload parts** — browser hər 5MB chunk-i ayrıca presigned URL-lə upload edir (`UploadPart`). Concurrency mümkün — 5 chunk paralel upload. (3) **Complete** — bütün chunk-lar uğurla upload olduqdan sonra `CompleteMultipartUpload` presigned URL ilə bitir. **Üstünlüklər:** resume mümkün (network kəsilsə son chunk-dan davam), parallel upload (sürətli), browser-dən birbaşa S3 (server toxunmur). AWS SDK JS-də `@aws-sdk/lib-storage` `Upload` class-ı bunu avtomatik idarə edir.

---

## Əlaqəli Mövzular

- [04-file-upload-processing.md](04-file-upload-processing.md) — File upload pipeline
- [42-async-image-processing.md](42-async-image-processing.md) — Image processing pipeline
- [10-audit-logging.md](10-audit-logging.md) — File access audit
- [16-audit-and-gdpr-compliance.md](16-audit-and-gdpr-compliance.md) — GDPR compliance for files
