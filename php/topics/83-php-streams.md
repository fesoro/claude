# PHP Streams

## Mündəricat
1. [Stream nədir?](#stream-nədir)
2. [Built-in Stream Wrappers](#built-in-stream-wrappers)
3. [Stream Filters](#stream-filters)
4. [Stream Context](#stream-context)
5. [Custom Stream Wrapper](#custom-stream-wrapper)
6. [PHP İmplementasiyası](#php-implementasiyası)
7. [İntervyu Sualları](#intervyu-sualları)

---

## Stream nədir?

```
Stream — ardıcıl oxuna/yazıla bilən məlumat mənbəyi.
Mənbədən asılı olmayaraq eyni API ilə işləmək mümkündür.

File, network, memory, process — hamısı stream-dir.

┌─────────────────────────────────────────────────┐
│              Stream Abstraction                 │
│                                                 │
│  fread() / fwrite() / fgets() / stream_copy()  │
│                    │                            │
│    ┌───────────────┼───────────────┐            │
│    ▼               ▼               ▼            │
│  file://         http://         php://         │
│  (disk)         (network)        (memory)       │
└─────────────────────────────────────────────────┘

$f1 = fopen('file.txt', 'r');           // file stream
$f2 = fopen('http://example.com', 'r'); // HTTP stream
$f3 = fopen('php://memory', 'r+');      // memory stream
// Hamısı eyni fread/fwrite API ilə işləyir
```

---

## Built-in Stream Wrappers

```
php://stdin   → standart giriş
php://stdout  → standart çıxış
php://stderr  → standart xəta çıxışı
php://memory  → RAM-da müvəqqəti buffer
php://temp    → RAM-da başlar, böyüdükdə fayla keçir (2MB default)
php://input   → HTTP request body (raw)
php://output  → output buffer-ə yaz

file://       → yerli fayl sistemi (default wrapper)
http://       → HTTP GET sorğusu
https://      → HTTPS GET sorğusu
ftp://        → FTP
compress.gz://→ gzip sıxılmış fayllar
compress.bz2//→ bzip2 sıxılmış fayllar

php://temp vs php://memory:
  memory → həmişə RAM-da
  temp   → 2MB-ya qədər RAM, sonra /tmp faylına yazır
  Böyük upload-lar üçün temp daha təhlükəsizdir
```

---

## Stream Filters

```
Filter-lər stream məlumatını oxu/yaz zamanı transform edir.
Zəncir şəklində birləşdirilə bilər.

Stream → Filter1 → Filter2 → Filter3 → Destination

Mövcud filterlər:
  string.toupper     → böyük hərflərə çevir
  string.tolower     → kiçik hərflərə çevir
  string.rot13       → ROT13 encode
  string.base64-encode → base64 encode
  string.base64-decode → base64 decode
  zlib.deflate       → gzip compress
  zlib.inflate       → gzip decompress
  convert.iconv.*    → encoding çevirmə

Filterlər stream oxuyarkən (read filter) və
ya yazarkən (write filter) tətbiq oluna bilər.
```

---

## Stream Context

```
Context — stream əməliyyatı üçün metadata/options.

HTTP stream context:
  method, headers, content, timeout, proxy

SSL context:
  verify_peer, verify_peer_name, cafile, local_cert

FTP context:
  overwrite, resume_pos, proxy

┌──────────────────┐
│  stream_context  │
│  ┌────────────┐  │
│  │  HTTP opts │  │
│  │  method    │  │
│  │  headers   │  │
│  │  body      │  │
│  └────────────┘  │
└────────┬─────────┘
         │
         ▼
    fopen(url, 'r', false, $context)
```

---

## Custom Stream Wrapper

```
Öz protokolunuzu yarada bilərsiniz.
stream_wrapper_register('myproto', MyWrapper::class)

Sonra:
  fopen('myproto://path/to/resource', 'r')

Tətbiq sahələri:
  - Test doubles (filesystem mock)
  - Encrypted file storage
  - Remote storage (S3, GCS) yerli fayl kimi
  - Database-backed file system
  - In-memory virtual filesystem (unit test-lərdə)

Implement edilməli metodlar:
  stream_open()    → açma
  stream_read()    → oxuma
  stream_write()   → yazma
  stream_eof()     → sona çatıb?
  stream_close()   → bağlama
  stream_stat()    → fayl məlumatı
  url_stat()       → static stat
```

---

## PHP İmplementasiyası

```php
<?php
// php://temp — böyük response buffer-i
function processLargeResponse(): string
{
    $buffer = fopen('php://temp', 'r+');

    // Böyük data hissə-hissə yaz
    for ($i = 0; $i < 10000; $i++) {
        fwrite($buffer, "Row {$i}: " . str_repeat('data', 10) . "\n");
    }

    rewind($buffer);
    $content = stream_get_contents($buffer);
    fclose($buffer);

    return $content;
}

// php://input — raw request body oxumaq (middleware-lərdə)
function getRawBody(): string
{
    return file_get_contents('php://input');
}
```

```php
<?php
// Stream filter — fayl yazarkən base64 encode
$fp = fopen('encoded_output.txt', 'w');
stream_filter_append($fp, 'convert.base64-encode');
fwrite($fp, 'Hello, World!');
fclose($fp);
// Fayl: SGVsbG8sIFdvcmxkIQ==

// Stream filter zənciri — şifrələ + compress + base64
$input  = fopen('large_data.json', 'r');
$output = fopen('output.gz.b64', 'w');

stream_filter_append($output, 'zlib.deflate', STREAM_FILTER_WRITE);
stream_filter_append($output, 'convert.base64-encode', STREAM_FILTER_WRITE);

stream_copy_to_stream($input, $output);
fclose($input);
fclose($output);
```

```php
<?php
// HTTP stream context — file_get_contents ilə POST sorğusu
function httpPost(string $url, array $data): string
{
    $context = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/json\r\n",
            'content' => json_encode($data),
            'timeout' => 10,
            'ignore_errors' => true, // 4xx/5xx-də də body oxu
        ],
        'ssl' => [
            'verify_peer' => true,
        ],
    ]);

    return file_get_contents($url, false, $context);
}
```

```php
<?php
// Custom Stream Wrapper — in-memory virtual filesystem (test-lər üçün)
class MemoryStreamWrapper
{
    private static array $files = [];
    private string $path;
    private int $position = 0;

    public function stream_open(string $path, string $mode): bool
    {
        $this->path = str_replace('memory://', '', $path);
        if (!isset(self::$files[$this->path])) {
            self::$files[$this->path] = '';
        }
        $this->position = 0;
        return true;
    }

    public function stream_read(int $count): string
    {
        $data = substr(self::$files[$this->path], $this->position, $count);
        $this->position += strlen($data);
        return $data;
    }

    public function stream_write(string $data): int
    {
        $left  = substr(self::$files[$this->path], 0, $this->position);
        $right = substr(self::$files[$this->path], $this->position + strlen($data));
        self::$files[$this->path] = $left . $data . $right;
        $this->position += strlen($data);
        return strlen($data);
    }

    public function stream_eof(): bool
    {
        return $this->position >= strlen(self::$files[$this->path]);
    }

    public function stream_close(): void {}

    public function stream_stat(): array
    {
        return ['size' => strlen(self::$files[$this->path] ?? '')];
    }

    public static function reset(): void
    {
        self::$files = [];
    }
}

// Qeydiyyat
stream_wrapper_register('memory', MemoryStreamWrapper::class);

// İstifadə (test-lərdə)
$fp = fopen('memory://config.json', 'w');
fwrite($fp, json_encode(['key' => 'value']));
fclose($fp);

$fp = fopen('memory://config.json', 'r');
echo fread($fp, 1024); // {"key":"value"}
fclose($fp);
```

---

## İntervyu Sualları

- `php://temp` vs `php://memory` — nə vaxt hansını seçərsiniz?
- Stream filter-ləri zəncir şəklində birləşdirmənin praktik faydası nədir?
- Custom stream wrapper hansı metodları mütləq implement etməlidir?
- Unit test-lərdə custom stream wrapper-dən necə istifadə edə bilərsiniz?
- `stream_context_create` HTTP sorğularında nə üçün lazımdır?
- `file_get_contents` vs `fopen` + `stream_copy_to_stream` — böyük fayllarda fərq nədir?
