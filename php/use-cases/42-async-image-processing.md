# Async Image Processing Pipeline

## Problem necə yaranır?

İstifadəçi şəkil yükləyir, server sinxron olaraq işləyir: resize (3 variant), watermark, S3 upload. Bu proses 3-10 saniyə çəkir. HTTP request bu müddətdə açıq qalır — timeout riski, server resursu bloklanır, digər request-lər gözləyir.

Daha ciddi problem: 100 istifadəçi eyni anda şəkil yükləyərsə 100 PHP worker bloklana bilər — server capacity tükənir.

---

## Pipeline

```
Upload endpoint (202 ms):
  File validate → S3 original → DB (status=processing) → Queue job

Queue worker (arxa planda):
  S3-dən original al → Resize (3 variant) → Watermark → S3 upload → DB update → Notify user
```

---

## İmplementasiya

*Bu kod şəkli S3-ə yükləyib queue-ya göndərən upload endpoint-ni, asinxron emal job-unu və Intervention Image ilə resize/watermark prosessorunu göstərir:*

```php
// Upload — dərhal qaytarır, işi queue-ya göndərir
class ImageController extends Controller
{
    public function upload(Request $request): JsonResponse
    {
        $request->validate(['image' => 'required|image|max:10240']);

        // Orijinal faylı S3-ə yüklə (fast — sadəcə transfer)
        $path = $request->file('image')->store('originals', 's3');

        $image = Image::create([
            'user_id'       => $request->user()->id,
            'original_path' => $path,
            'status'        => 'processing',
        ]);

        ProcessImageJob::dispatch($image->id);

        // 202 Accepted: işlənir, hazır deyil
        return response()->json(['id' => $image->id, 'status' => 'processing'], 202);
    }

    // Client polling edə bilər — status yoxlamaq üçün
    public function status(int $id): JsonResponse
    {
        $image = Image::findOrFail($id);

        return response()->json([
            'status' => $image->status, // processing | ready | failed
            'urls'   => $image->status === 'ready' ? [
                'thumbnail' => $image->thumbnail_url,
                'medium'    => $image->medium_url,
                'large'     => $image->large_url,
            ] : null,
        ]);
    }
}

// Queue job — 120s timeout, 3 retry
class ProcessImageJob implements ShouldQueue
{
    public int $tries   = 3;
    public int $timeout = 120; // Böyük şəkillər üçün

    public function __construct(private int $imageId) {}

    public function handle(ImageProcessor $processor): void
    {
        $image    = Image::findOrFail($this->imageId);

        // Idempotency: artıq işlənibsə skip et
        if ($image->status === 'ready') return;

        $original = Storage::disk('s3')->get($image->original_path);

        try {
            // 3 variant yaradılır: thumbnail, medium, large
            $variants = $processor->process($original, [
                'thumbnail' => ['width' => 150,  'height' => 150,  'fit' => 'crop'],
                'medium'    => ['width' => 800,  'height' => 600,  'fit' => 'contain'],
                'large'     => ['width' => 1920, 'height' => 1080, 'fit' => 'contain'],
            ]);

            $paths = [];
            foreach ($variants as $size => $data) {
                $path         = "processed/{$image->id}/{$size}.webp";
                Storage::disk('s3')->put($path, $data, 'public');
                $paths[$size] = Storage::disk('s3')->url($path);
            }

            $image->update([
                'status'        => 'ready',
                'thumbnail_url' => $paths['thumbnail'],
                'medium_url'    => $paths['medium'],
                'large_url'     => $paths['large'],
                'processed_at'  => now(),
            ]);

            // WebSocket ilə real-time bildiriş
            $image->user->notify(new ImageReadyNotification($image));
            broadcast(new ImageProcessed($image))->toOthers();

        } catch (\Exception $e) {
            $image->update(['status' => 'failed', 'error' => $e->getMessage()]);
            throw $e; // queue retry üçün yenidən at
        }
    }

    // Bütün retry-lar tükəndikdən sonra çağırılır
    public function failed(\Throwable $e): void
    {
        Image::where('id', $this->imageId)->update(['status' => 'failed']);
        // User-ə bildiriş, ops-a alert
    }
}

// Processor — Intervention Image ilə resize + watermark
class ImageProcessor
{
    public function process(string $original, array $variants): array
    {
        $results = [];

        foreach ($variants as $name => $config) {
            $img = \Intervention\Image\Facades\Image::make($original);

            match($config['fit']) {
                'crop'    => $img->fit($config['width'], $config['height']),
                'contain' => $img->resize($config['width'], $config['height'], function ($c) {
                                $c->aspectRatio(); // Nisbəti qoruyur
                                $c->upsize();      // Kiçik şəkli böyütmür
                             }),
                default   => $img->resize($config['width'], $config['height']),
            };

            // Watermark əlavə et (aşağı sağ künc, 10px margin)
            $img->insert(\Intervention\Image\Facades\Image::make(
                storage_path('app/watermark.png')
            ), 'bottom-right', 10, 10);

            // WebP formatı: JPEG-dən 25-35% kiçik, keyfiyyət eyni
            $results[$name] = $img->encode('webp', 85)->getEncoded();
        }

        return $results;
    }
}
```

---

## S3 Pre-signed URL (Birbaşa Upload)

Server üzərindən upload etmək əvəzinə client birbaşa S3-ə yükləyir:

*Bu kod server üzərindən yox, client-in birbaşa S3-ə yükləməsi üçün müvəqqəti imzalı URL yaradan endpoint-i göstərir:*

```php
class UploadController extends Controller
{
    public function presign(Request $request): JsonResponse
    {
        $key = 'originals/' . Str::uuid() . '.' . $request->extension;

        // 5 dəqiqəlik pre-signed URL yaradılır
        $url = Storage::disk('s3')->temporaryUploadUrl($key, now()->addMinutes(5), [
            'ContentType' => $request->content_type,
        ]);

        $image = Image::create([
            'user_id'       => $request->user()->id,
            'original_path' => $key,
            'status'        => 'pending_upload',
        ]);

        return response()->json(['upload_url' => $url, 'image_id' => $image->id]);
        // Client: PUT $upload_url ilə birbaşa S3-ə yükləyir
        // Upload tamamlandıqdan sonra: POST /images/{id}/process
    }
}
```

Server upload traffic-ini daşımır — bandwidth cost azalır, latency azalır.

---

## CDN Entegrasyonu

Processed variantları CDN (CloudFront) üzərindən serve etmək:

*Bu kod emal edilmiş şəkil URL-lərini CDN (CloudFront) üzərindən serve etmək üçün konfiqurasiya edilməsini göstərir:*

```php
// S3 URL əvəzinə CloudFront URL qaytar
// CloudFront → S3 origin — edge caching, global distribution
$cdnBase = config('cdn.cloudfront_url'); // https://d1234.cloudfront.net

$image->update([
    'thumbnail_url' => "{$cdnBase}/processed/{$image->id}/thumbnail.webp",
    'medium_url'    => "{$cdnBase}/processed/{$image->id}/medium.webp",
    'large_url'     => "{$cdnBase}/processed/{$image->id}/large.webp",
]);

// Cache-Control header S3 object-ə — CDN neçə müddət cache-ləyəcəyini bilir
Storage::disk('s3')->put($path, $data, [
    'visibility'   => 'public',
    'CacheControl' => 'max-age=31536000', // 1 il — şəkil dəyişmir
]);
```

---

## Job Chaining — Ardıcıl Processing

Şəkil processing-dən sonra avtomatik başqa job:

*Bu kod şəkil emalından sonra virus skan, moderasiya və bildiriş job-larını ardıcıl icra edən job chain-i göstərir:*

```php
// Chain: process → virus scan → moderate → notify
ProcessImageJob::withChain([
    new VirusScanJob($image->id),
    new ModerationJob($image->id),   // AI content moderation
    new NotifyUserJob($image->id),
])->dispatch($image->id);
```

---

## Anti-patterns

- **Sinxron processing:** HTTP handler-da resize → timeout, server capacity israfı.
- **Timeout-suz job:** Böyük şəkil job-u sonsuz işləyə bilər, worker bloklayır.
- **Retry-da orijinal faylı yenidən S3-dən almamaq:** İlk cəhddə S3 yüklənmiş ola bilər — idempotent et, processed variantları sil, sıfırdan başla.
- **WebP-yə keçməmək:** JPEG istifadəsi — WebP eyni keyfiyyətdə 30% kiçikdir, bandwidth azalır.

---

## İntervyu Sualları

**1. Niyə image processing asinxrondur?**
Sinxron: resize+watermark+S3 upload 3-10s → HTTP timeout, server PHP worker bloklayır, concurrent capacity azalır. Asinxron: 202 Accepted dərhal qaytar, işi queue-ya göndər. Worker pool ayrıca — web worker-larını azad edir.

**2. Job retry-da idempotency necə təmin edilir?**
Retry zamanı işin bir hissəsi artıq tamamlanmış ola bilər (S3-ə yazılmış amma DB yenilənməyib). Həll: status yoxla — artıq `ready` isə skip et. S3-dəki processed faylları sil, sıfırdan başla. Job-u idempotent dizayn et.

**3. Pre-signed URL nədir, niyə istifadə edilir?**
Server müvəqqəti imzalı S3 URL yaradır, client birbaşa S3-ə yükləyir. Server upload traffic-ini daşımır. Böyük fayllar üçün xüsusilə faydalı — server memory/bandwidth israfı yoxdur.

**4. Processing status-u client-ə necə çatdırılır?**
İki yanaşma: (1) **Polling** — client `/images/{id}/status` endpoint-ini hər N saniyədə sorğulayır, sadə lakin həddindən artıq HTTP request yarada bilər; (2) **WebSocket/SSE** — server işin tamamlandığını push edir, daha real-time, Laravel Reverb/Pusher ilə. Production-da polling + WebSocket hybrid: ilk 10s-də polling, sonra WebSocket.

**5. Şəkil formatları arasında seçim necə edilir?**
WebP: JPEG-dən ~30% kiçik, bütün müasir brauzer dəstəkləyir. AVIF: WebP-dən ~20% kiçik, lakin encoding daha yavaş. JPEG: universal fallback. Tövsiyə: WebP əsas format, AVIF experimental. Encoding keyfiyyəti 80-85 — görsel fərq az, fayl kiçik.

**6. S3-ə upload zamanı xəta baş verərsə?**
Job `throw $e` ilə exception-u yenidən atar → Laravel queue retry mexanizmi işə düşür (`$tries = 3`). Exponential backoff: `backoff()` metodunu override edib `[10, 30, 60]` saniyə gözlətmək. Bütün retry-lar tükəndikdə `failed()` metodu çağırılır — user-ə bildiriş, ops alert.

---

## Anti-patternlər

**1. Upload endpoint-ində sinxron image processing etmək**
HTTP request handler-da şəkli resize, watermark, S3 upload ardıcıllığını sinxron icra etmək — əməliyyat 5-15s çəkir, PHP worker bloklanır, istifadəçi timeout alır. Upload yalnız orijinal faylı S3-ə qoymalı, processing ayrı queue worker-a tapşırılmalıdır.

**2. Eyni worker pool-unda həm web, həm image processing job-larını işlətmək**
Image processing job-larını web sorğuları ilə eyni queue-da işlətmək — böyük şəkil job-ları worker-ları uzun müddət tutur, web request-ləri növbədə gözləyir. Image processing üçün ayrı `image-processing` queue və dedicated worker pool olmalıdır.

**3. Job retry-ında processed variantları yenidən yaratmamaq**
Retry zamanı S3-ə artıq yazılmış thumbnail-lərin üzərindən keçməyib yalnız DB-ni yeniləməyə çalışmaq — yarımçıq state: bəzi variantlar var, bəziləri yox. Job idempotent olmalı, hər retry-da S3-dəki processed faylları silib sıfırdan yaratmalıdır.

**4. Image job-larına timeout qoymamaq**
Worker-ların həddindən böyük (500MB+) şəkilləri time limit olmadan emal etməsinə icazə vermək — job sonsuz işləyir, worker bloklanır, növbədəki job-lar işlənmir. Hər job üçün maksimum icra müddəti (timeout) təyin edilməli, aşıldıqda job `failed` işarələnməlidir.

**5. Orijinal şəkli S3-dən sildikdən sonra yenidən process etmək imkanını aradan qaldırmaq**
Image processing tamamlandıqda orijinal faylı S3-dən silmək — gələcəkdə yeni format (AVIF) əlavə etmək ya da variant xarab olduqda yenidən process etmək mümkün olmur. Orijinal fayl daim `originals/` bucket-da saxlanmalı, yalnız processed variantlar silinə bilər.

**6. Pre-signed URL-i server üzərindən proxying ilə əvəz etmək**
Client-in S3-ə birbaşa yükləmək əvəzinə bütün upload trafikini Laravel serverdən keçirmək — böyük fayllar server memory-ni tükədir, bandwidth xərci iki dəfə artır (client→server, server→S3). Pre-signed URL client-ə verilməli, fayl birbaşa S3-ə yüklənməlidir.

**7. S3-dəki şəkilləri CDN-siz birbaşa serve etmək**
`https://s3.amazonaws.com/bucket/image.webp` URL-ini birbaşa client-ə vermək — hər request S3 origin-ə gedir, latency yüksək (region məsafəsi), S3 API xərci artır. CloudFront və ya digər CDN qabaqda olmalı, şəkillər edge node-lardan serve edilməlidir.
