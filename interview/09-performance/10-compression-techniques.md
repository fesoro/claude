# Compression Techniques (Senior ⭐⭐⭐)

## İcmal

Compression — data-nın daha az bit ilə ifadə olunması prosesidir. HTTP cavablarında, verilənlər bazasında, fayl saxlamada, log sistemlərində, mesaj brokerlarında — compression hər yerdə mövcuddur. Backend developer üçün "sıxış" sadəcə bir config sətri deyil: hangi algoritm, hansı content, hansı trade-off — bunları bilmək vacibdir.

## Niyə Vacibdir

Gzip aktiv edilmiş bir API cavabı 10x kiçilə bilər — bandwidth azalır, network latency azalır, CDN cache effektivliyi artır. Böyük bir log faylını Zstandard ilə sıxmaq həm diskdə yer qazandırır, həm də S3-ə upload vaxtını azaldır. Database-də TOAST ilə böyük text sütunları avtomatik sıxılır. Bu mexanizmləri anlamaq real deployment qərarlarına təsir edir.

## Əsas Anlayışlar

- **Compression növləri:**
  - **Lossless** — orijinal data tam bərpa olunur (HTTP, file, DB)
  - **Lossy** — bir hissə itirilir, amma qəbuledilən keyfiyyət (JPEG, MP3, video codec)

- **Ən çox istifadə olunan alqoritmlər:**
  - **Gzip (DEFLATE)** — HTTP default, yaxşı ratio, orta sürət
  - **Brotli** — Google, gzip-dən 20-26% yaxşı, HTTP/HTTPS
  - **Zstandard (zstd)** — sürətli, yaxşı ratio, real-time üçün (Kafka, Zabbix)
  - **LZ4** — çox sürətli, az ratio (cache, real-time log)
  - **Snappy** — Google, sürətli, orta ratio (Hadoop, Cassandra)
  - **LZMA/XZ** — çox yaxşı ratio, yavaş (arxiv, distribution)

- **Compression context-ləri:**
  - **HTTP response compression** — Nginx/Apache Gzip/Brotli
  - **Database compression** — PostgreSQL TOAST, InnoDB page compression
  - **File/backup compression** — gzip, zstd
  - **In-transit** — TLS + content compression
  - **At-rest** — S3 storage class, disk-level
  - **Message queue** — Kafka producer compression
  - **Cache** — Redis LZF (built-in, avtomatik)

- **Ratio vs Speed trade-off:**

  | Alqoritm | Ratio | Speed | CPU |
  |---|---|---|---|
  | Gzip-9 | Çox yaxşı | Yavaş | Yüksək |
  | Gzip-6 | Yaxşı | Orta | Orta |
  | Brotli-11 | Ən yaxşı | Yavaş | Çox yüksək |
  | Brotli-4 | Yaxşı | Sürətli | Orta |
  | Zstd-3 | Yaxşı | Çox sürətli | Az |
  | LZ4 | Orta | Ən sürətli | Ən az |

- **Compressible vs not:**
  - Yaxşı: JSON, XML, HTML, CSS, JS, plaintext, log
  - Pis: JPEG, PNG (artıq sıxılıb), PDF (çox halda), ZIP/gz, binary

## Praktik Baxış

**Nginx Gzip + Brotli:**

```nginx
# /etc/nginx/conf.d/compression.conf

# Gzip
gzip on;
gzip_comp_level 6;          # 1-9, 6 balans nöqtəsidir
gzip_min_length 1024;       # 1KB-dan kiçik sıxma (overhead > fayda)
gzip_proxied any;
gzip_types
    text/plain
    text/css
    text/javascript
    application/javascript
    application/json
    application/xml
    image/svg+xml;
gzip_vary on;               # Vary: Accept-Encoding header əlavə et

# Brotli (ngx_brotli module lazımdır)
brotli on;
brotli_comp_level 4;        # 0-11
brotli_types
    text/plain
    text/css
    application/json
    application/javascript;
```

**PHP-də response compression (API):**

```php
// Laravel middleware
class CompressResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Nginx-də compression varsa, burada etmə
        if (config('app.nginx_handles_compression')) {
            return $response;
        }

        $acceptEncoding = $request->header('Accept-Encoding', '');
        $content = $response->getContent();

        // Brotli (PHP brotli extension)
        if (str_contains($acceptEncoding, 'br') && function_exists('brotli_compress')) {
            $compressed = brotli_compress($content, 4, BROTLI_TEXT);
            if ($compressed !== false) {
                return $response
                    ->setContent($compressed)
                    ->header('Content-Encoding', 'br')
                    ->header('Content-Length', strlen($compressed));
            }
        }

        // Gzip fallback
        if (str_contains($acceptEncoding, 'gzip')) {
            $compressed = gzencode($content, 6);
            return $response
                ->setContent($compressed)
                ->header('Content-Encoding', 'gzip')
                ->header('Content-Length', strlen($compressed));
        }

        return $response;
    }
}
```

**File compression (backup, export):**

```php
// Zstandard ilə sıxma (pecl-zstd)
class FileCompressor
{
    public function compressWithZstd(string $inputPath, string $outputPath, int $level = 3): void
    {
        $input = file_get_contents($inputPath);
        $compressed = zstd_compress($input, $level);
        file_put_contents($outputPath . '.zst', $compressed);

        $ratio = strlen($compressed) / strlen($input) * 100;
        Log::info("Compressed: {$ratio}% of original");
    }

    public function compressWithGzip(string $inputPath, string $outputPath): void
    {
        $gz = gzopen($outputPath . '.gz', 'wb9');
        $input = fopen($inputPath, 'rb');

        while (!feof($input)) {
            gzwrite($gz, fread($input, 65536)); // 64KB chunks
        }

        fclose($input);
        gzclose($gz);
    }

    // Streaming compression (böyük fayl, az memory)
    public function streamCompress(string $inputPath, string $outputPath): void
    {
        $inputStream = fopen($inputPath, 'rb');
        $outputStream = gzopen($outputPath . '.gz', 'wb6');

        stream_copy_to_stream($inputStream, $outputStream);

        fclose($inputStream);
        gzclose($outputStream);
    }
}
```

**Database TOAST (PostgreSQL):**

```sql
-- TOAST: The Oversized-Attribute Storage Technique
-- Sütun > 2KB olduqda avtomatik sıxılır

-- Compression method seçmək (PostgreSQL 14+)
CREATE TABLE articles (
    id SERIAL PRIMARY KEY,
    content TEXT COMPRESSION lz4,      -- sürətli, az ratio
    metadata JSONB COMPRESSION pglz    -- default, yaxşı ratio
);

-- Mövcud sütunun compression method-unu dəyiş
ALTER TABLE articles
ALTER COLUMN content SET COMPRESSION zstd;  -- PG 16+

-- TOAST storage statistics
SELECT relname, pg_size_pretty(pg_total_relation_size(oid)) as total,
       pg_size_pretty(pg_relation_size(oid)) as table,
       pg_size_pretty(pg_total_relation_size(oid) - pg_relation_size(oid)) as indexes_toast
FROM pg_class
WHERE relname = 'articles';
```

**Kafka producer compression:**

```php
// php-rdkafka ilə
$conf = new RdKafka\Conf();
$conf->set('compression.type', 'zstd');      // lz4, snappy, gzip, zstd
$conf->set('compression.level', '3');        // zstd 1-22

$producer = new RdKafka\Producer($conf);
// Hər message batch sıxılmış göndərilir
```

**Redis built-in compression:**

```php
// Redis LZF avtomatik: 20+ byte değerlər sıxılır
// Explicit application-level compression
class CompressedRedisCache
{
    public function set(string $key, mixed $value, int $ttl): void
    {
        $serialized = serialize($value);

        // 1KB-dan böyüksə sıx
        if (strlen($serialized) > 1024) {
            $compressed = lz4_compress($serialized);  // pecl-lz4
            Redis::setex('c:' . $key, $ttl, $compressed);
        } else {
            Redis::setex($key, $ttl, $serialized);
        }
    }

    public function get(string $key): mixed
    {
        // Compressed key-i yoxla
        $compressed = Redis::get('c:' . $key);
        if ($compressed !== null) {
            return unserialize(lz4_uncompress($compressed));
        }

        $raw = Redis::get($key);
        return $raw !== null ? unserialize($raw) : null;
    }
}
```

**S3 upload kompressiyası:**

```php
// Laravel Storage S3 ilə sıxılmış upload
use Illuminate\Support\Facades\Storage;

class ReportUploader
{
    public function upload(string $reportPath): string
    {
        $content = file_get_contents($reportPath);
        $compressed = gzencode($content, 9);

        $s3Key = 'reports/' . date('Y/m/d') . '/' . basename($reportPath) . '.gz';

        Storage::disk('s3')->put($s3Key, $compressed, [
            'ContentType' => 'application/gzip',
            'ContentEncoding' => 'gzip',
            'StorageClass' => 'STANDARD_IA', // 30 günlük report → Infrequent Access
        ]);

        return $s3Key;
    }
}
```

**Trade-offs:**
- Yüksək compression level → CPU artır, latency artır
- Real-time API → Brotli-4 / Gzip-6 (balans)
- Static asset → Brotli-11 (offline sıxmaq mümkündür)
- Kiçik cavablar → compression overhead fayda vermir
- Binary data → compression yardımı olmur (artıq sıxılıb)
- Double compression → nə ratio nə performance artır

**Common mistakes:**
- Nginx-dən əvvəl PHP-də sıxmaq (double compression)
- Gzip min_length qoymamaq (50 byte-lıq JSON-u sıxmaq)
- Already-compressed fayl (JPEG) üzərinə gzip tətbiq etmək
- `Vary: Accept-Encoding` header-i unutmaq (CDN caching problem)

## Nümunələr

### Real Ssenari: API bandwidth azaltmaq

```
Problem: Mobile client 2MB JSON response alır, yavaş network.

Analiz:
- Response: order history list, 500 record
- Fields çoxu client istifadə etmir

Həll 1 (projection): Yalnız lazım olan fields qaytarıldı → 2MB → 400KB
Həll 2 (compression): Gzip aktivləşdirildi → 400KB → 45KB
Həll 3 (pagination): 500 → 50 per page → 45KB → 5KB

Nəticə: 2MB → 5KB (400x azalma)
Bandwidth azaldı, CDN hit ratio artdı, mobile UX yaxşılaşdı.
```

### Kod Nümunəsi

```php
<?php

// Compression benchmark utility
class CompressionBenchmark
{
    public function benchmark(string $data): array
    {
        $results = [];

        // Gzip levels
        foreach ([1, 6, 9] as $level) {
            $start = hrtime(true);
            $compressed = gzencode($data, $level);
            $time = (hrtime(true) - $start) / 1e6;

            $results["gzip-{$level}"] = [
                'original_kb' => round(strlen($data) / 1024, 2),
                'compressed_kb' => round(strlen($compressed) / 1024, 2),
                'ratio' => round(strlen($compressed) / strlen($data) * 100, 1) . '%',
                'time_ms' => round($time, 3),
            ];
        }

        // LZ4 (pecl-lz4)
        if (function_exists('lz4_compress')) {
            $start = hrtime(true);
            $compressed = lz4_compress($data);
            $time = (hrtime(true) - $start) / 1e6;

            $results['lz4'] = [
                'original_kb' => round(strlen($data) / 1024, 2),
                'compressed_kb' => round(strlen($compressed) / 1024, 2),
                'ratio' => round(strlen($compressed) / strlen($data) * 100, 1) . '%',
                'time_ms' => round($time, 3),
            ];
        }

        return $results;
    }
}

// İstifadə:
$bench = new CompressionBenchmark();
$data = json_encode(Order::limit(1000)->get()); // ~500KB JSON
$results = $bench->benchmark($data);
// gzip-6: ratio=8.2%, time=12ms
// lz4:    ratio=31.5%, time=1.2ms
```

## Praktik Tapşırıqlar

1. **Nginx compression aktiv et:** Local Nginx konfiqurasiyasına Gzip əlavə et, Chrome DevTools Network tab-da `Content-Encoding: gzip` görən anda response size müqayisə et.

2. **Benchmark:** `CompressionBenchmark` class-ı ilə real bir JSON response üzərində gzip-1, gzip-6, gzip-9 müqayisə et.

3. **API middleware:** `Accept-Encoding` header-ə baxan, Brotli/Gzip seçən, compression ratio-nu response header-ə yazan middleware yaz.

4. **S3 compress upload:** Laravel Storage ilə gzip sıxılmış fayl upload et, `ContentEncoding: gzip` ilə, S3-dən manual decompress test et.

5. **PostgreSQL TOAST:** `articles` cədvəlindəki `content` sütunu üçün `pg_stat_user_tables` ilə `n_live_tup` vs `pg_total_relation_size` müqayisə et.

## Əlaqəli Mövzular

- `09-async-batch-processing.md` — Böyük fayl/data sıxma
- `11-apm-tools.md` — Bandwidth metrikalarını izlə
- `03-caching-layers.md` — Sıxılmış cache
- `14-api-performance.md` — API-level compression strategies
- `02-query-optimization.md` — DB-də TOAST ilə böyük data
