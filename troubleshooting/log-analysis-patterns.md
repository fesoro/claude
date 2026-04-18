# Log Analysis Patterns

## Problem (nə görürsən)
Nə isə sınıb. Log-lar sənin əsas dəlilindir. Amma scale-də log-lar düşmən mətn divarına bənzəyir: saatda 5GB, qarışıq formatlar, təkrarlanan sətirlər, stack trace-lər araya qarışıb. Hər sual üçün bütün ELK stack-i dartmadan tez gəzməyin işləyən alət toplusu lazımdır.

Log-ların doğru alət olduğuna dair simptomlar:
- Error rate spike-ə gəldi, hansı exception tipləri olduğunu görmək lazımdır
- Konkret istifadəçi bug bildirir, request izini görmək lazımdır
- Servislər arasında hadisələri korrelyasiya etmək lazımdır
- Bir şeyin nə qədər tez baş verdiyini saymaq lazımdır

## Sürətli triage (ilk 5 dəqiqə)

### Əvvəlcə tail və filter

```bash
# Live tail with filter
tail -f storage/logs/laravel.log | grep -i "error\|exception\|fatal"

# Last 200 lines
tail -n 200 storage/logs/laravel.log

# Errors only in last 1000 lines
tail -n 1000 storage/logs/laravel.log | grep -i "ERROR"
```

### Səhvlərin nə vaxt başladığını tap

```bash
# First ERROR in today's log
grep "ERROR" storage/logs/laravel-$(date +%Y-%m-%d).log | head -1

# First ERROR after a specific time
awk '/2026-04-17 14:30/,0' storage/logs/laravel.log | grep ERROR | head
```

## Diaqnoz

### Strukturlu log-lar (JSON) vs strukturlu olmayan

**Strukturlu olmayan** (default Laravel):
```
[2026-04-17 14:35:12] production.ERROR: Call to undefined method in /app/Services/X.php:42
```

**Strukturlu** (JSON, prod üçün tövsiyə olunur):
```json
{"ts":"2026-04-17T14:35:12Z","level":"ERROR","service":"api","trace_id":"abc123","msg":"Call to undefined method","file":"/app/Services/X.php","line":42,"user_id":4321}
```

Strukturlu log-lar kəsib aparmaq üçün sonsuz asandır. `jq` əsas alətin olur.

### Laravel JSON logging

`config/logging.php`-də:

```php
'channels' => [
    'stderr' => [
        'driver' => 'monolog',
        'handler' => StreamHandler::class,
        'formatter' => \Monolog\Formatter\JsonFormatter::class,
        'with' => ['stream' => 'php://stderr'],
    ],
],
```

### Pattern kitabı: grep

```bash
# Fatal errors and parse errors (PHP)
grep -iE "fatal|parse error|out of memory|allowed memory size" storage/logs/laravel.log

# Specific exception class
grep "QueryException" storage/logs/laravel.log

# Count errors by type (rough)
grep -oE "[A-Z][a-zA-Z]+Exception" storage/logs/laravel.log | sort | uniq -c | sort -rn

# All requests for a user
grep "user_id=4321" storage/logs/laravel.log

# Errors in last hour (log format dependent)
awk -v d="$(date -d '1 hour ago' '+%Y-%m-%d %H')" '$0 >= d' storage/logs/laravel.log | grep ERROR
```

### Pattern kitabı: jq

```bash
# Pretty-print a JSON log
jq . storage/logs/laravel.json

# Only ERROR level
jq 'select(.level=="ERROR")' storage/logs/laravel.json

# Error messages only
jq -r 'select(.level=="ERROR") | .msg' storage/logs/laravel.json

# Group errors by message
jq -r 'select(.level=="ERROR") | .msg' storage/logs/laravel.json | sort | uniq -c | sort -rn

# Errors for a specific user
jq 'select(.user_id == 4321)' storage/logs/laravel.json

# Errors in last hour (UTC)
jq --arg since "$(date -u -d '1 hour ago' -Iseconds)" 'select(.ts > $since and .level=="ERROR")' storage/logs/laravel.json
```

### Zaman ərzində səhvləri saymaq

```bash
# Errors per minute
grep ERROR storage/logs/laravel.log \
  | awk '{print substr($2,1,5)}' \
  | sort | uniq -c

# Errors per hour from JSON logs
jq -r 'select(.level=="ERROR") | .ts[0:13]' storage/logs/laravel.json \
  | sort | uniq -c
```

### Correlation ID-lər

Hər request trace/correlation ID logla. Sonra:

```bash
# Find a failing request
grep "status=500" storage/logs/laravel.log | head -1
# [2026-04-17 14:35:12] trace_id=abc123 ...

# Full request trail
grep "abc123" storage/logs/laravel.log

# Trail across services (SSH to each log source or use centralized)
grep "abc123" /var/log/{api,auth,payments}/*.log
```

### Log səviyyələri — hər birinin mənası

| Level | Nə vaxt |
|-------|------|
| DEBUG | Yalnız dev. Dəqiq state. Adətən prod-da söndürülüb. |
| INFO | Normal hadisələr: istifadəçi daxil oldu, job tamamlandı. |
| NOTICE | Qeyri-adi amma idarə olunub. |
| WARNING | Şübhəli bir şey, sistem hələ qaydasındadır. |
| ERROR | Bir request/job uğursuz oldu, istifadəçi təsirləndi. |
| CRITICAL | Servis komponenti sıradan çıxdı, bərpa lazımdır. |
| ALERT | Dərhal hərəkət tələb olunur. |
| EMERGENCY | Sistem istifadəyə yararsızdır. |

Monolog/PSR-3 bu hamısını təmin edir. Təcrübədə əksər komandalar DEBUG/INFO/WARN/ERROR istifadə edir.

## Fix (qanaxmanı dayandır)

Log analizi nadir hallarda problemi birbaşa həll edir — ona işarə edir. Fix kod/config/infra-da yaşayır. Amma yaxşı log analizi sənə deyir:

- Hansı exception / error tipi dominantdır
- Hansı endpoint / route təsirlənir
- Nə vaxt başladı
- Hansı istifadəçi / müştərilər təsirlənir
- Bu yeni pattern-dir, yoxsa məlum olan

## Əsas səbəbin analizi

Incident sonrası log review-u:
- Log-larda yetərincə kontekst vardımı? (user_id, trace_id, request_id)
- Sındırılan şeyi logladıq, yoxsa təxmin etdik?
- Log həcmi bizi yavaşlatdımı?
- Təmizlənməsi lazım olan PII loglamışıqmı təsadüfən?

## Qarşısının alınması

- Həmişə trace_id / correlation_id logla
- Həmişə user_id logla (PII siyasəti tələb edirsə hash-lanmış)
- Komanda boyu uyğun log səviyyəsi konvensiyaları
- Sirlər, token-lər, parollar loglama (bəllidir), həmçinin request payload-larına diqqət et
- Log saxlama siyasəti qoy (30-90 gün hot, S3 cold)
- Yüksək həcmli INFO log-ları sample et (10% saxla, ERROR+ 100% saxla)

## PHP/Laravel üçün qeydlər

### Laravel log yerləri

```
storage/logs/laravel.log         # default, single file
storage/logs/laravel-2026-04-17.log  # if daily driver
```

`config/logging.php`-də konfiqurasiya et:
```php
'default' => env('LOG_CHANNEL', 'stack'),
'channels' => [
    'daily' => [
        'driver' => 'daily',
        'path' => storage_path('logs/laravel.log'),
        'level' => env('LOG_LEVEL', 'debug'),
        'days' => 14,
    ],
],
```

### Bütün log-lara kontekst əlavə et

```php
// AppServiceProvider::boot()
Log::shareContext([
    'trace_id' => request()?->header('X-Trace-Id') ?? Str::uuid()->toString(),
    'user_id' => auth()->id(),
    'request_id' => Str::uuid()->toString(),
]);
```

### Log başına xüsusi kontekst

```php
Log::channel('orders')->error('Order failed', [
    'order_id' => $order->id,
    'amount' => $order->total,
    'gateway_response' => $response->body(),
]);
```

### Grep-in scale etməyəcəyi vaxt

Log həcmin > 1GB/gün-dürsə və ya bir neçə servisin varsa, bunlara investisiya et:
- **ELK / OpenSearch** — tam mətn axtarışı, Kibana dashboard-ları
- **Grafana Loki** — ucuz, log-as-labels modeli, Prometheus ilə əla
- **Datadog Logs** — bahalı, mükəmməl UX, APM inteqrasiyası
- **Splunk** — enterprise, bahalı, güclü query dili
- **CloudWatch Logs Insights** — AWS native, sərfəli

Query dili nümunələri:

Loki LogQL:
```
{app="api"} |= "ERROR" | json | status_code = 500
```

Datadog:
```
service:api status:error @http.status_code:500
```

CloudWatch Insights:
```
fields @timestamp, @message
| filter @message like /ERROR/
| stats count() by bin(5m)
```

## Yadda saxlanacaq komandalar

```bash
# Live tail with color
tail -f storage/logs/laravel.log | grep --color -iE "error|exception"

# Just today's errors count
grep -c ERROR storage/logs/laravel-$(date +%F).log

# Top 10 exception types
grep -oE "[A-Z][a-zA-Z]+Exception" storage/logs/laravel.log | sort | uniq -c | sort -rn | head

# Requests per status code (nginx)
awk '{print $9}' /var/log/nginx/access.log | sort | uniq -c | sort -rn

# Slow requests (> 1s) from nginx
awk '$NF > 1.0' /var/log/nginx/access.log

# JSON errors in last hour
jq --arg s "$(date -u -d '1 hour ago' -Iseconds)" \
  'select(.ts > $s and .level=="ERROR")' \
  storage/logs/laravel.json
```

## Interview sualı

"Log-lardan istifadə edərək production problemi necə araşdırırsan?"

Güclü cavab:
- "Sıx zaman pəncərəsi ilə başlayıram — alert ətrafında 5 dəqiqə. ERROR və yuxarı səviyyə üzrə filter edirəm."
- "Əvvəlcə dominant exception tiplərinə baxıram: `grep -oE 'Exception$' | sort | uniq -c`. Adətən bir-iki tip dominant olur."
- "Bir fail olan request-i servislər arasında izləmək üçün correlation ID istifadə edirəm."
- "JSON log-larda `jq` ilə istifadəçi, status kod, trace ID üzrə kəsirəm."
- "Həcm grep-lə bitməzsə, strukturlu query-lərlə Loki və ya Datadog istifadə edirəm."
- "Log-ları oxuma məşqi kimi yox, axtarış aləti kimi görürəm. Cavabı axtarıram, scroll etmirəm."

Bonus: sıx log kəsimlə saat əvəzinə dəqiqələrdə root cause-a çatdığın konkret incident göstər.
