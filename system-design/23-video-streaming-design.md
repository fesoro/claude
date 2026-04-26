# Video Streaming Design (Middle)

## İcmal

Video streaming sistemi video contentini istifadəçilərə real-time çatdıran arxitekturadır.
Upload, transcoding (format çevirmə), adaptive bitrate streaming, və CDN delivery əhatə
edir. YouTube, Netflix, Twitch kimi platformalar video streaming istifadə edir.

Sadə dillə: TV yayımı kimi - amma internet üzərindən, istifadəçi istədiyi vaxt
istədiyi videonu izləyə bilir (on-demand) və ya canlı yayım (live) izləyir.

```
Upload → Transcode → Store → CDN → Stream to User

Original Video (4K, 10GB)
    │
    ▼
┌──────────────┐
│  Transcoder  │──▶ 1080p (5GB)
│              │──▶ 720p  (2GB)
│              │──▶ 480p  (1GB)
│              │──▶ 360p  (0.5GB)
└──────────────┘
    │
    ▼
┌──────────────┐     ┌──────┐     ┌──────┐
│   Storage    │────▶│ CDN  │────▶│ User │
│   (S3)      │     │      │     │      │
└──────────────┘     └──────┘     └──────┘
```


## Niyə Vacibdir

Video ən böyük bandwidth istehlakçısıdır; HLS/DASH adaptive bitrate istifadəçinin şəbəkəsinə uyğun keyfiyyət seçir. CDN olmadan qlobal video streaming mümkün deyil. YouTube/Netflix-in arxitekturası — transcoding pipeline, chunked storage, adaptive player — texniki dərinliyin klassik nümunəsidir.

## Əsas Anlayışlar

### HLS vs DASH

**HLS (HTTP Live Streaming - Apple):**
```
Original video → split into small segments (2-10 seconds each)
                → create playlist file (.m3u8)

master.m3u8:
  #EXTM3U
  #EXT-X-STREAM-INF:BANDWIDTH=5000000,RESOLUTION=1920x1080
  1080p/playlist.m3u8
  #EXT-X-STREAM-INF:BANDWIDTH=2500000,RESOLUTION=1280x720
  720p/playlist.m3u8
  #EXT-X-STREAM-INF:BANDWIDTH=1000000,RESOLUTION=854x480
  480p/playlist.m3u8

1080p/playlist.m3u8:
  #EXTM3U
  #EXT-X-TARGETDURATION:6
  #EXTINF:6.0,
  segment_001.ts
  #EXTINF:6.0,
  segment_002.ts
  #EXTINF:6.0,
  segment_003.ts
```

**DASH (Dynamic Adaptive Streaming over HTTP):**
```
Similar concept, uses .mpd (Media Presentation Description)
XML-based manifest file
More flexible, open standard
Used by YouTube, Netflix
```

### Adaptive Bitrate Streaming (ABR)

```
User's bandwidth changes during playback:

Time 0-30s:  Fast WiFi   → 1080p (5 Mbps)
Time 30-60s: Slow 3G     → 480p  (1 Mbps)  ← auto switch
Time 60-90s: Back to WiFi → 720p  (2.5 Mbps)
Time 90s+:   Stable WiFi → 1080p (5 Mbps)

Client measures:
  - Download speed of each segment
  - Buffer level
  - Decides next segment quality

No rebuffering, smooth experience
```

### Transcoding Pipeline

```
┌──────────────────────────────────────────────────┐
│               Transcoding Pipeline                │
│                                                   │
│  Input: original.mp4 (4K, H.264, 10GB)          │
│                                                   │
│  Step 1: Validate & Extract metadata             │
│  Step 2: Split into chunks (for parallel encode) │
│  Step 3: Transcode each chunk to each quality:   │
│          ├─ 2160p (4K)  H.265  15 Mbps          │
│          ├─ 1080p       H.264  5 Mbps            │
│          ├─ 720p        H.264  2.5 Mbps          │
│          ├─ 480p        H.264  1 Mbps            │
│          └─ 360p        H.264  0.5 Mbps          │
│  Step 4: Generate HLS/DASH segments              │
│  Step 5: Generate thumbnails (every 10 seconds)  │
│  Step 6: Extract/generate subtitles              │
│  Step 7: Upload to storage + CDN                 │
│                                                   │
│  Time: ~30min for a 1-hour video                 │
└──────────────────────────────────────────────────┘
```

### Video Storage Estimation

```
1 hour video at multiple qualities:
  4K:    ~7 GB
  1080p: ~4 GB
  720p:  ~2 GB
  480p:  ~1 GB
  360p:  ~0.5 GB
  Total: ~14.5 GB per video

YouTube: 500 hours uploaded per minute
  = 500 × 14.5 GB = 7.25 TB per minute
  = 10.44 PB per day
```

## Arxitektura

### Video Platform Architecture

```
┌─────────────────────────────────────────────────────┐
│                  Upload Flow                         │
│                                                      │
│  ┌────────┐    ┌──────────┐    ┌──────────────┐    │
│  │ Client │───▶│  Upload  │───▶│  Object      │    │
│  │        │    │  Service │    │  Storage (S3) │    │
│  └────────┘    └────┬─────┘    └──────────────┘    │
│                     │                                │
│              ┌──────┴──────┐                        │
│              │  Transcode  │                        │
│              │   Queue     │                        │
│              └──────┬──────┘                        │
│                     │                                │
│              ┌──────┴──────┐    ┌──────────────┐    │
│              │ Transcoding │───▶│  Processed   │    │
│              │  Workers    │    │  Storage (S3)│    │
│              └─────────────┘    └──────┬───────┘    │
│                                        │             │
│                                 ┌──────┴───────┐    │
│                                 │    CDN        │    │
│                                 │ (CloudFront)  │    │
│                                 └──────┬───────┘    │
│                                        │             │
│                                 ┌──────┴───────┐    │
│                                 │   Viewers    │    │
│                                 └──────────────┘    │
└─────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────┐
│                  Watch Flow                          │
│                                                      │
│  Client ──▶ API (get video metadata + playlist URL)  │
│        ──▶ CDN (download .m3u8 manifest)            │
│        ──▶ CDN (download video segments one by one) │
│                                                      │
│  Buffer: [seg1][seg2][seg3]...                      │
│  Playback: ▶━━━━━━━━━━━━━━━━━━━━━━━━━               │
└─────────────────────────────────────────────────────┘
```

## Nümunələr

### Video Upload Service

```php
class VideoUploadService
{
    public function initiateUpload(int $userId, array $metadata): array
    {
        $video = Video::create([
            'user_id' => $userId,
            'title' => $metadata['title'],
            'description' => $metadata['description'],
            'status' => 'uploading',
            'original_filename' => $metadata['filename'],
        ]);

        // Generate pre-signed URL for direct S3 upload
        $path = "uploads/raw/{$video->id}/{$metadata['filename']}";
        $client = Storage::disk('s3')->getClient();
        $command = $client->getCommand('PutObject', [
            'Bucket' => config('filesystems.disks.s3.bucket'),
            'Key' => $path,
            'ContentType' => $metadata['content_type'],
        ]);

        $presignedUrl = (string) $client
            ->createPresignedRequest($command, '+2 hours')
            ->getUri();

        return [
            'video_id' => $video->id,
            'upload_url' => $presignedUrl,
        ];
    }

    public function confirmUpload(int $videoId): void
    {
        $video = Video::findOrFail($videoId);

        // Verify file exists in S3
        $path = "uploads/raw/{$video->id}/{$video->original_filename}";
        if (!Storage::disk('s3')->exists($path)) {
            throw new FileNotFoundException('Upload not found');
        }

        $video->update([
            'status' => 'processing',
            'raw_path' => $path,
            'file_size' => Storage::disk('s3')->size($path),
        ]);

        // Dispatch transcoding job
        TranscodeVideo::dispatch($video)->onQueue('transcoding');
    }
}
```

### Transcoding Job

```php
class TranscodeVideo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600; // 1 hour
    public int $tries = 2;

    private array $profiles = [
        '1080p' => ['width' => 1920, 'height' => 1080, 'bitrate' => '5000k'],
        '720p'  => ['width' => 1280, 'height' => 720,  'bitrate' => '2500k'],
        '480p'  => ['width' => 854,  'height' => 480,  'bitrate' => '1000k'],
        '360p'  => ['width' => 640,  'height' => 360,  'bitrate' => '500k'],
    ];

    public function __construct(private Video $video) {}

    public function handle(): void
    {
        $inputPath = Storage::disk('s3')->temporaryUrl($this->video->raw_path, now()->addHour());

        $variants = [];
        foreach ($this->profiles as $quality => $profile) {
            $outputDir = "videos/{$this->video->id}/{$quality}";

            // FFmpeg transcode + HLS segment
            $this->transcode($inputPath, $outputDir, $profile, $quality);

            $variants[$quality] = [
                'path' => $outputDir,
                'width' => $profile['width'],
                'height' => $profile['height'],
                'bitrate' => $profile['bitrate'],
            ];
        }

        // Generate master playlist
        $masterPlaylist = $this->generateMasterPlaylist($variants);
        Storage::disk('s3')->put(
            "videos/{$this->video->id}/master.m3u8",
            $masterPlaylist
        );

        // Generate thumbnails
        $this->generateThumbnails($inputPath);

        // Update video status
        $this->video->update([
            'status' => 'ready',
            'variants' => $variants,
            'playlist_url' => "videos/{$this->video->id}/master.m3u8",
            'duration' => $this->getVideoDuration($inputPath),
        ]);

        // Notify user
        $this->video->user->notify(new VideoProcessed($this->video));
    }

    private function transcode(string $input, string $outputDir, array $profile, string $quality): void
    {
        $localOutput = storage_path("app/temp/{$this->video->id}/{$quality}");
        File::ensureDirectoryExists($localOutput);

        // FFmpeg command for HLS output
        $command = sprintf(
            'ffmpeg -i "%s" -vf "scale=%d:%d" -c:v libx264 -b:v %s ' .
            '-c:a aac -b:a 128k -hls_time 6 -hls_list_size 0 ' .
            '-hls_segment_filename "%s/segment_%%03d.ts" "%s/playlist.m3u8"',
            $input,
            $profile['width'], $profile['height'],
            $profile['bitrate'],
            $localOutput, $localOutput
        );

        Process::run($command)->throw();

        // Upload segments to S3
        foreach (File::files($localOutput) as $file) {
            Storage::disk('s3')->put(
                "{$outputDir}/{$file->getFilename()}",
                file_get_contents($file->getRealPath())
            );
        }

        // Cleanup local files
        File::deleteDirectory(storage_path("app/temp/{$this->video->id}"));
    }

    private function generateMasterPlaylist(array $variants): string
    {
        $playlist = "#EXTM3U\n";
        foreach ($variants as $quality => $info) {
            $playlist .= "#EXT-X-STREAM-INF:BANDWIDTH={$this->bitrateToInt($info['bitrate'])}," .
                         "RESOLUTION={$info['width']}x{$info['height']}\n";
            $playlist .= "{$quality}/playlist.m3u8\n";
        }
        return $playlist;
    }

    private function generateThumbnails(string $inputPath): void
    {
        $interval = 10; // every 10 seconds
        $outputDir = "videos/{$this->video->id}/thumbnails";

        $command = sprintf(
            'ffmpeg -i "%s" -vf "fps=1/%d,scale=320:-1" -q:v 5 "%s/thumb_%%04d.jpg"',
            $inputPath, $interval,
            storage_path("app/temp/thumbs/{$this->video->id}")
        );

        Process::run($command);

        // Upload thumbnails
        $thumbDir = storage_path("app/temp/thumbs/{$this->video->id}");
        foreach (File::files($thumbDir) as $file) {
            Storage::disk('s3')->put(
                "{$outputDir}/{$file->getFilename()}",
                file_get_contents($file->getRealPath())
            );
        }
    }

    private function bitrateToInt(string $bitrate): int
    {
        return (int) str_replace('k', '000', $bitrate);
    }
}
```

### Video Streaming Controller

```php
class VideoController extends Controller
{
    public function show(int $id): JsonResponse
    {
        $video = Video::with('user')->findOrFail($id);

        if ($video->status !== 'ready') {
            return response()->json(['message' => 'Video is still processing'], 202);
        }

        // Increment view count asynchronously
        IncrementViewCount::dispatch($video->id);

        // Generate signed CDN URL for playlist
        $playlistUrl = $this->getSignedUrl($video->playlist_url);

        return response()->json([
            'id' => $video->id,
            'title' => $video->title,
            'description' => $video->description,
            'duration' => $video->duration,
            'playlist_url' => $playlistUrl,
            'thumbnail' => $this->getSignedUrl($video->thumbnail_path),
            'views' => $video->view_count,
            'author' => new UserResource($video->user),
            'created_at' => $video->created_at->toIso8601String(),
        ]);
    }

    private function getSignedUrl(string $path): string
    {
        return Storage::disk('s3')->temporaryUrl($path, now()->addHours(4));
    }
}
```

## Real-World Nümunələr

1. **YouTube** - 500 hours/min upload, 1B hours/day watched, DASH streaming
2. **Netflix** - Per-title encoding, custom ABR, 15% global internet traffic
3. **Twitch** - Live streaming, RTMP ingest, HLS delivery, < 5s latency
4. **TikTok** - Short-form video, aggressive transcoding, global CDN
5. **Disney+** - Premium content, DRM protection, 4K HDR

## Praktik Tapşırıqlar

**S1: HLS vs DASH fərqi nədir?**
C: HLS Apple tərəfindən yaradılıb, .m3u8 playlist, .ts segments, geniş browser support.
DASH open standard, .mpd manifest, .mp4 segments, daha flexible. YouTube/Netflix DASH
istifadə edir. Əksər platform hər ikisini dəstəkləyir.

**S2: Adaptive bitrate streaming necə işləyir?**
C: Video hər quality üçün kiçik segment-lərə bölünür (2-10s). Client hər segment-i
yükləyib bandwidth-ı ölçür. Bandwidth yaxşıdırsa yüksək quality, pisdirsə aşağı
quality segment yüklənir. Seamless keçid olur.

**S3: Video transcoding niyə lazımdır?**
C: Fərqli cihazlar fərqli codec, resolution, bitrate dəstəkləyir. Bir video
bir neçə format/quality-yə çevrilir. Bandwidth-a uyğun quality seçimi mümkün
olur. Storage çox olsa da user experience yaxşılaşır.

**S4: Live streaming vs on-demand fərqi nədir?**
C: Live - video real-time encode və segment olunur, latency kritikdir (1-30s).
On-demand - video əvvəlcədən transcode olunub, latency problem deyil.
Live üçün RTMP ingest + HLS/DASH delivery, daha complex infrastructure.

**S5: CDN video streaming-də necə rol oynayır?**
C: Video segments CDN edge server-lərə cache olunur. İstifadəçi ən yaxın edge-dən
yükləyir - latency azalır, origin server-ə yük düşmür. Popular videos hot cache-də
qalır. Multi-CDN strategiyası ilə reliability artır.

**S6: Video processing cost-u necə optimize olunur?**
C: Spot/preemptible instances ilə transcoding (70% qənaət). Per-title encoding
(content complexity-ə görə bitrate). Lazy transcoding (istifadə olunmayan
quality-ləri sonradan encode). Parallel processing ilə sürət artırma.

## Praktik Baxış

1. **Chunked Upload** - Böyük video faylları parçalayıb yükləyin
2. **Async Transcoding** - Queue-da background processing
3. **ABR Streaming** - HLS/DASH ilə adaptive quality
4. **CDN Delivery** - Bütün video content CDN-dən serve edin
5. **Thumbnail Generation** - Preview üçün thumbnails yaradın
6. **DRM** - Premium content üçün Digital Rights Management
7. **Monitoring** - Buffering ratio, startup time, quality switches track edin
8. **Cost Optimization** - Spot instances, S3 storage classes
9. **Content Moderation** - Upload olunan videoları auto-review edin
10. **Resumable Upload** - Upload kəsildikdə davam etmək imkanı


## Əlaqəli Mövzular

- [CDN](04-cdn.md) — video chunk-larını edge-dən serv etmək
- [File Storage](15-file-storage.md) — video origin saxlaması
- [Live Streaming](58-live-streaming-design.md) — real-time video ingest və delivery
- [Video Conferencing](80-video-conferencing-design.md) — iki tərəfli video
- [Stream Processing](54-stream-processing.md) — video analitika pipeline
