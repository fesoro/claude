# System Design: File Upload & Storage System (Senior)

## Mündəricat
1. [Tələblər](#tələblər)
2. [Yüksək Səviyyəli Dizayn](#yüksək-səviyyəli-dizayn)
3. [Komponent Dizaynı](#komponent-dizaynı)
4. [PHP İmplementasiyası](#php-implementasiyası)
5. [İntervyu Sualları](#intervyu-sualları)

---

## Tələblər

```
Funksional:
  Fayl yükləmək (upload): şəkil, video, sənəd
  Fayl endirmək (download): CDN vasitəsilə
  Fayl silmək: soft delete + hard delete
  Versioning: fayl versiyaları saxlamaq
  Thumbnail: şəkillər üçün avtomatik kiçiltmə

Qeyri-funksional:
  Böyük fayllar: 5GB-a qədər
  Yüksək mövcudluq: 99.9%
  Durability: 99.999999999% (11 nines) — S3 kimi
  Aşağı gecikmə download: CDN ilə

Hesablamalar:
  1M upload/gün, ortalama 1MB → 1TB/gün
  5 il: ~1.8PB storage
  Replication factor 3 → 5.4PB raw storage
```

---

## Yüksək Səviyyəli Dizayn

```
Upload axını (presigned URL):
  
  Client ──POST /upload/init──► API Server
         ◄── presigned URL ─────
  Client ──PUT (direct to S3)──► Object Storage (S3)
         ◄── upload complete ────
  Client ──POST /upload/confirm─► API Server
                                  → DB metadata yaz
                                  → Processing queue-ya yaz

  Niyə presigned URL?
    Fayl API server-dən keçmir → bandwidth azalır
    S3 horizontal scale edir
    API server load azalır

Download axını:
  Client ──GET /files/abc──► API Server
         ◄── 302 CDN URL ───
  Client ──GET cdn.example.com/abc──► CDN (edge)
    → CDN hit: birbaşa qaytar
    → CDN miss: S3-dən al, cache et

Processing Pipeline:
  Upload complete → SQS/Kafka message
  Image Worker: resize, thumbnail, optimize
  Video Worker: transcode (FFmpeg)
  Virus Scanner: ClamAV
  → Processing complete → metadata yenilə
```

---

## Komponent Dizaynı

```
Object Storage:
  AWS S3, GCS, Azure Blob, MinIO (self-hosted)
  Flat namespace (bucket/key)
  Versioning built-in
  Lifecycle policy: köhnə fayllar Glacier-ə taşı

Metadata DB (MySQL/PostgreSQL):
  files:
    id, user_id, original_name, storage_key, size,
    mime_type, status, created_at, deleted_at
  
  file_versions:
    file_id, version, storage_key, created_at

CDN:
  CloudFront, Cloudflare, Fastly
  Edge location-da cache
  TTL: public fayllar (uzun), private fayllar (qısa/signed URL)

Virus Scanning:
  Upload-dan sonra async scan
  Infected → fayl bloklanır, user notify edilir
  Clean → fayl activated

Deduplication:
  SHA-256 hash hesabla
  Eyni hash mövcuddursa → yeni storage yox, reference əlavə et
  Storage istehlakını azaldır

Chunked Upload (böyük fayllar):
  5GB fayl bir sorğuda yüklənmir
  Multipart upload: 5MB-lıq parçalar
  Paralel yükləmə: daha sürətli
  Resume on failure: yarıda kəsilsə davam et
```

---

## PHP İmplementasiyası

```php
<?php
// Presigned URL yaratmaq (AWS S3)
use Aws\S3\S3Client;

class FileUploadService
{
    private S3Client $s3;
    private string   $bucket;

    public function __construct(string $bucket, string $region = 'eu-west-1')
    {
        $this->s3     = new S3Client(['version' => 'latest', 'region' => $region]);
        $this->bucket = $bucket;
    }

    public function initiateUpload(
        string $userId,
        string $originalName,
        string $mimeType,
        int    $fileSize,
    ): UploadInitResult {
        $this->validateMimeType($mimeType);
        $this->validateFileSize($fileSize);

        $storageKey = $this->generateStorageKey($userId, $originalName);

        // Presigned PUT URL (10 dəqiqə etibarlı)
        $presignedUrl = $this->s3->createPresignedRequest(
            $this->s3->getCommand('PutObject', [
                'Bucket'       => $this->bucket,
                'Key'          => $storageKey,
                'ContentType'  => $mimeType,
                'ContentLength'=> $fileSize,
                'Metadata'     => ['uploaded-by' => $userId],
            ]),
            '+10 minutes',
        )->getUri();

        // DB-ə pending status ilə yaz
        $fileId = $this->fileRepository->createPending(
            userId:      $userId,
            storageKey:  $storageKey,
            name:        $originalName,
            mimeType:    $mimeType,
            size:        $fileSize,
        );

        return new UploadInitResult(
            fileId:       $fileId,
            presignedUrl: (string) $presignedUrl,
            storageKey:   $storageKey,
        );
    }

    public function confirmUpload(string $fileId, string $userId): FileMetadata
    {
        $file = $this->fileRepository->findByIdAndUser($fileId, $userId)
            ?? throw new FileNotFoundException($fileId);

        // S3-dən yoxla — fayl həqiqətən yüklənibmi?
        if (!$this->s3->doesObjectExist($this->bucket, $file->getStorageKey())) {
            throw new UploadNotCompletedException($fileId);
        }

        $file->markUploaded();
        $this->fileRepository->save($file);

        // Async processing (thumbnail, virus scan)
        $this->processingQueue->publish(new FileUploadedEvent($fileId));

        return $file->toMetadata();
    }

    private function generateStorageKey(string $userId, string $originalName): string
    {
        $date = date('Y/m/d');
        $uuid = bin2hex(random_bytes(16));
        $ext  = pathinfo($originalName, PATHINFO_EXTENSION);
        return "uploads/{$userId}/{$date}/{$uuid}.{$ext}";
    }

    private function validateMimeType(string $mimeType): void
    {
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf', 'video/mp4'];
        if (!in_array($mimeType, $allowed)) {
            throw new UnsupportedFileTypeException($mimeType);
        }
    }

    private function validateFileSize(int $bytes): void
    {
        $maxBytes = 5 * 1024 * 1024 * 1024; // 5GB
        if ($bytes > $maxBytes) {
            throw new FileTooLargeException("Maksimum fayl ölçüsü 5GB-dır");
        }
    }
}
```

```php
<?php
// Download — signed CDN URL (private files)
class FileDownloadService
{
    public function getDownloadUrl(string $fileId, string $userId): string
    {
        $file = $this->fileRepository->findByIdAndUser($fileId, $userId)
            ?? throw new FileNotFoundException($fileId);

        if ($file->isPublic()) {
            // Public → CDN URL birbaşa
            return "https://cdn.example.com/{$file->getStorageKey()}";
        }

        // Private → Signed CloudFront URL (1 saat etibarlı)
        return $this->cloudFront->createSignedUrl(
            url:     "https://cdn.example.com/{$file->getStorageKey()}",
            expires: time() + 3600,
        );
    }
}
```

```php
<?php
// Image Processing Worker
class ImageProcessingWorker
{
    public function process(FileUploadedEvent $event): void
    {
        $file = $this->fileRepository->findById($event->fileId);

        if (!str_starts_with($file->getMimeType(), 'image/')) {
            return; // Şəkil deyilsə keç
        }

        $originalKey = $file->getStorageKey();

        // Şəkili S3-dən yüklə
        $imageData = $this->s3->getObject([
            'Bucket' => $this->bucket,
            'Key'    => $originalKey,
        ])->get('Body');

        $image = imagecreatefromstring((string) $imageData);

        // Thumbnail yarat (200x200)
        $thumb = $this->createThumbnail($image, 200, 200);

        // Thumbnail-ı S3-ə yüklə
        $thumbKey = str_replace('uploads/', 'thumbnails/', $originalKey);
        $this->s3->putObject([
            'Bucket'      => $this->bucket,
            'Key'         => $thumbKey,
            'Body'        => $thumb,
            'ContentType' => 'image/webp',
        ]);

        // Metadata yenilə
        $file->setThumbnailKey($thumbKey);
        $file->markProcessed();
        $this->fileRepository->save($file);
    }

    private function createThumbnail($image, int $width, int $height): string
    {
        $thumb = imagecreatetruecolor($width, $height);
        imagecopyresampled($thumb, $image, 0, 0, 0, 0,
            $width, $height, imagesx($image), imagesy($image));

        ob_start();
        imagewebp($thumb, null, 85);
        return ob_get_clean();
    }
}
```

---

## İntervyu Sualları

- Presigned URL yanaşmasının API-dən birbaşa yükləməyə üstünlüyü nədir?
- 5GB fayl üçün chunked/multipart upload necə işləyir?
- Virus scan etmək üçün axının hansı yerində yer almalıdır?
- Deduplication üçün content hash nə zaman hesablanmalıdır?
- Private fayllar üçün CDN signed URL niyə lazımdır?
- Fayl silinəndə storage, DB, CDN cache sinxronizasiyasını necə idarə edərdiniz?
