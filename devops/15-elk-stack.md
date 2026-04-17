# ELK Stack (Elasticsearch, Logstash, Kibana)

## Nədir? (What is it?)

ELK Stack mərkəzləşdirilmiş log idarəetmə platformasıdır. Elasticsearch - axtarış və analitika mühərriki (data saxlama, sorğulama), Logstash - log toplama və emal pipeline (parse, transform), Kibana - vizualizasiya interfeysi (dashboard, axtarış). Filebeat - yüngül log göndərici agent. Bütün serverlədəki logları bir yerdə toplamaq, axtarmaq və analiz etmək üçün istifadə olunur.

## Əsas Konseptlər (Key Concepts)

### Arxitektura

```
Serverlər                Pipeline              Storage & UI
┌──────────┐                                 ┌───────────────┐
│ Server 1 │──Filebeat──┐                    │ Elasticsearch │
│ (nginx)  │            │    ┌──────────┐    │  (index,      │
├──────────┤            ├───>│ Logstash │───>│   search,     │
│ Server 2 │──Filebeat──┤    │ (parse,  │    │   store)      │
│ (laravel)│            │    │  filter) │    └───────┬───────┘
├──────────┤            │    └──────────┘            │
│ Server 3 │──Filebeat──┘                    ┌───────┴───────┐
│ (mysql)  │                                 │    Kibana     │
└──────────┘                                 │  (dashboard,  │
                                             │   visualize)  │
                                             └───────────────┘
```

### Docker Compose ilə Quraşdırma

```yaml
# docker-compose.yml
version: '3.8'
services:
  elasticsearch:
    image: docker.elastic.co/elasticsearch/elasticsearch:8.12.0
    environment:
      - discovery.type=single-node
      - xpack.security.enabled=false
      - "ES_JAVA_OPTS=-Xms1g -Xmx1g"
    ports:
      - "9200:9200"
    volumes:
      - es_data:/usr/share/elasticsearch/data

  logstash:
    image: docker.elastic.co/logstash/logstash:8.12.0
    ports:
      - "5044:5044"    # Beats input
      - "5000:5000"    # TCP input
    volumes:
      - ./logstash/pipeline:/usr/share/logstash/pipeline
    depends_on:
      - elasticsearch

  kibana:
    image: docker.elastic.co/kibana/kibana:8.12.0
    ports:
      - "5601:5601"
    environment:
      - ELASTICSEARCH_HOSTS=http://elasticsearch:9200
    depends_on:
      - elasticsearch

  filebeat:
    image: docker.elastic.co/beats/filebeat:8.12.0
    volumes:
      - ./filebeat.yml:/usr/share/filebeat/filebeat.yml:ro
      - /var/log:/var/log:ro
      - /var/www/laravel/storage/logs:/app/logs:ro
    depends_on:
      - logstash

volumes:
  es_data:
```

### Elasticsearch Əsasları

```bash
# Cluster health
curl http://localhost:9200/_cluster/health?pretty

# Index-ləri göstər
curl http://localhost:9200/_cat/indices?v

# Index yaratmaq
curl -X PUT "localhost:9200/laravel-logs-2024.01" -H 'Content-Type: application/json' -d'
{
  "settings": {
    "number_of_shards": 3,
    "number_of_replicas": 1
  },
  "mappings": {
    "properties": {
      "@timestamp": { "type": "date" },
      "level": { "type": "keyword" },
      "message": { "type": "text" },
      "channel": { "type": "keyword" },
      "context": { "type": "object" },
      "server": { "type": "keyword" }
    }
  }
}'

# Document əlavə etmək
curl -X POST "localhost:9200/laravel-logs-2024.01/_doc" -H 'Content-Type: application/json' -d'
{
  "@timestamp": "2024-01-15T10:30:00Z",
  "level": "error",
  "message": "SQLSTATE Connection refused",
  "channel": "production",
  "server": "web1"
}'

# Axtarış
curl -X GET "localhost:9200/laravel-logs-*/_search?pretty" -H 'Content-Type: application/json' -d'
{
  "query": {
    "bool": {
      "must": [
        { "match": { "level": "error" } },
        { "range": { "@timestamp": { "gte": "now-1h" } } }
      ]
    }
  },
  "sort": [{ "@timestamp": "desc" }],
  "size": 20
}'

# Aggregation
curl -X GET "localhost:9200/laravel-logs-*/_search?pretty" -H 'Content-Type: application/json' -d'
{
  "size": 0,
  "aggs": {
    "errors_by_level": {
      "terms": { "field": "level" }
    },
    "errors_over_time": {
      "date_histogram": {
        "field": "@timestamp",
        "calendar_interval": "hour"
      }
    }
  }
}'

# Index lifecycle management (ILM)
# Köhnə index-ləri avtomatik silmək
curl -X PUT "localhost:9200/_ilm/policy/laravel-logs-policy" -H 'Content-Type: application/json' -d'
{
  "policy": {
    "phases": {
      "hot": { "actions": { "rollover": { "max_size": "5gb", "max_age": "1d" } } },
      "warm": { "min_age": "7d", "actions": { "shrink": { "number_of_shards": 1 } } },
      "delete": { "min_age": "30d", "actions": { "delete": {} } }
    }
  }
}'
```

### Logstash Pipeline

```ruby
# logstash/pipeline/laravel.conf

input {
  beats {
    port => 5044
  }
  tcp {
    port => 5000
    codec => json_lines
  }
}

filter {
  # Laravel log parsing
  if [fields][type] == "laravel" {
    # Laravel log format: [2024-01-15 10:30:00] production.ERROR: Message
    grok {
      match => {
        "message" => "\[%{TIMESTAMP_ISO8601:timestamp}\] %{DATA:channel}\.%{LOGLEVEL:level}: %{GREEDYDATA:log_message}"
      }
    }

    date {
      match => ["timestamp", "yyyy-MM-dd HH:mm:ss"]
      target => "@timestamp"
    }

    # Level-i lowercase et
    mutate {
      lowercase => ["level"]
      remove_field => ["timestamp"]
    }

    # Stack trace-i birləşdir (multiline)
    multiline {
      pattern => "^\["
      negate => true
      what => "previous"
    }

    # Sensitive data-nı gizlə
    mutate {
      gsub => [
        "log_message", "password=\S+", "password=***",
        "log_message", "token=\S+", "token=***"
      ]
    }
  }

  # Nginx access log parsing
  if [fields][type] == "nginx-access" {
    grok {
      match => {
        "message" => '%{IPORHOST:remote_ip} - %{DATA:user} \[%{HTTPDATE:timestamp}\] "%{WORD:method} %{DATA:url} HTTP/%{NUMBER:http_version}" %{NUMBER:status} %{NUMBER:bytes} "%{DATA:referrer}" "%{DATA:user_agent}"'
      }
    }

    date {
      match => ["timestamp", "dd/MMM/yyyy:HH:mm:ss Z"]
      target => "@timestamp"
    }

    geoip {
      source => "remote_ip"
      target => "geoip"
    }

    useragent {
      source => "user_agent"
      target => "ua"
    }

    mutate {
      convert => {
        "status" => "integer"
        "bytes" => "integer"
      }
    }
  }
}

output {
  if [fields][type] == "laravel" {
    elasticsearch {
      hosts => ["elasticsearch:9200"]
      index => "laravel-logs-%{+YYYY.MM.dd}"
    }
  } else if [fields][type] == "nginx-access" {
    elasticsearch {
      hosts => ["elasticsearch:9200"]
      index => "nginx-access-%{+YYYY.MM.dd}"
    }
  } else {
    elasticsearch {
      hosts => ["elasticsearch:9200"]
      index => "general-%{+YYYY.MM.dd}"
    }
  }
}
```

### Filebeat Konfiqurasiyası

```yaml
# filebeat.yml
filebeat.inputs:
  # Laravel logs
  - type: log
    enabled: true
    paths:
      - /app/logs/laravel.log
    fields:
      type: laravel
      app: myapp
      env: production
    multiline.pattern: '^\['
    multiline.negate: true
    multiline.match: after
    multiline.max_lines: 50

  # Nginx access logs
  - type: log
    enabled: true
    paths:
      - /var/log/nginx/access.log
    fields:
      type: nginx-access

  # Nginx error logs
  - type: log
    enabled: true
    paths:
      - /var/log/nginx/error.log
    fields:
      type: nginx-error

  # PHP-FPM logs
  - type: log
    enabled: true
    paths:
      - /var/log/php8.3-fpm.log
    fields:
      type: php-fpm

  # MySQL slow query log
  - type: log
    enabled: true
    paths:
      - /var/log/mysql/slow.log
    fields:
      type: mysql-slow
    multiline.pattern: '^# Time:'
    multiline.negate: true
    multiline.match: after

output.logstash:
  hosts: ["logstash:5044"]

# və ya birbaşa Elasticsearch-ə
# output.elasticsearch:
#   hosts: ["elasticsearch:9200"]
#   index: "filebeat-%{+yyyy.MM.dd}"

processors:
  - add_host_metadata: ~
  - add_cloud_metadata: ~
```

## Praktiki Nümunələr (Practical Examples)

### Kibana KQL (Kibana Query Language)

```
# Əsas axtarış
level: error                                    # Error loglar
message: "Connection refused"                   # Mətn axtarışı
level: error AND channel: production            # AND şərti
level: (error OR critical)                      # OR şərti
NOT level: debug                                # NOT şərti
status >= 500                                   # Rəqəm müqayisəsi
url: /api/*                                     # Wildcard

# Laravel specific
level: error AND message: "SQLSTATE*"           # Database errorları
level: error AND message: "TokenMismatchException"  # CSRF errorları
channel: production AND level: critical          # Critical loglar
```

### Log analizi üçün Elasticsearch Aggregation

```bash
# Son 24 saatda ən çox error verən endpoint-lər
curl -X GET "localhost:9200/nginx-access-*/_search" -H 'Content-Type: application/json' -d'
{
  "size": 0,
  "query": {
    "bool": {
      "must": [
        { "range": { "status": { "gte": 500 } } },
        { "range": { "@timestamp": { "gte": "now-24h" } } }
      ]
    }
  },
  "aggs": {
    "top_error_urls": {
      "terms": {
        "field": "url.keyword",
        "size": 10,
        "order": { "_count": "desc" }
      }
    }
  }
}'

# Error trend (saatlıq)
curl -X GET "localhost:9200/laravel-logs-*/_search" -H 'Content-Type: application/json' -d'
{
  "size": 0,
  "query": {
    "bool": {
      "must": [
        { "match": { "level": "error" } },
        { "range": { "@timestamp": { "gte": "now-24h" } } }
      ]
    }
  },
  "aggs": {
    "errors_over_time": {
      "date_histogram": {
        "field": "@timestamp",
        "fixed_interval": "1h"
      }
    }
  }
}'
```

## PHP/Laravel ilə İstifadə

### Laravel Log-larını ELK-ə Göndərmək

```php
<?php
// composer require monolog/monolog

// config/logging.php
'channels' => [
    'stack' => [
        'driver' => 'stack',
        'channels' => ['daily', 'logstash'],
    ],

    'logstash' => [
        'driver' => 'monolog',
        'handler' => \Monolog\Handler\SocketHandler::class,
        'handler_with' => [
            'connectionString' => 'tcp://' . env('LOGSTASH_HOST', 'localhost') . ':5000',
        ],
        'formatter' => \Monolog\Formatter\LogstashFormatter::class,
        'formatter_with' => [
            'applicationName' => env('APP_NAME', 'laravel'),
        ],
    ],

    // Alternativ: JSON format ilə fayla yaz, Filebeat göndərsin
    'json' => [
        'driver' => 'monolog',
        'handler' => \Monolog\Handler\StreamHandler::class,
        'handler_with' => [
            'stream' => storage_path('logs/laravel-json.log'),
        ],
        'formatter' => \Monolog\Formatter\JsonFormatter::class,
    ],
],
```

### Custom Log Context

```php
<?php
// Strukturlaşdırılmış logging - ELK-də axtarış asanlaşır
Log::info('Order created', [
    'order_id' => $order->id,
    'user_id' => $order->user_id,
    'amount' => $order->total,
    'payment_method' => $order->payment_method,
]);

Log::error('Payment failed', [
    'order_id' => $order->id,
    'error_code' => $exception->getCode(),
    'error_message' => $exception->getMessage(),
    'gateway' => 'stripe',
]);

// Global context (hər logda olacaq)
Log::shareContext([
    'server' => gethostname(),
    'request_id' => request()->header('X-Request-Id'),
    'user_id' => auth()->id(),
]);

// Exception handler-da
// app/Exceptions/Handler.php
public function report(Throwable $e)
{
    if ($this->shouldReport($e)) {
        Log::error($e->getMessage(), [
            'exception' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
            'url' => request()->fullUrl(),
            'method' => request()->method(),
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    parent::report($e);
}
```

## Interview Sualları

### S1: ELK Stack-in hər komponentinin rolu nədir?
**C:** **Elasticsearch**: distributed axtarış və analitika mühərriki - logları saxlayır, indeksləyir, axtarır, aggregation edir. **Logstash**: data pipeline - logları qəbul edir (input), parse/transform edir (filter), göndərir (output). **Kibana**: vizualizasiya - dashboard, axtarış UI, alert. **Filebeat**: yüngül agent - serverlədəki log fayllarını oxuyur və Logstash/Elasticsearch-ə göndərir. Beats resource istifadəsi Logstash-dan çox azdır.

### S2: Elasticsearch-də index, shard və replica nədir?
**C:** **Index**: logları saxlayan verilənlər bazası (məsələn: laravel-logs-2024.01). **Shard**: index-in parçası - data bir neçə shard-a bölünür, paralel axtarış üçün. Primary shard - əsas data. **Replica**: primary shard-ın kopyası - yüksək əlçatanlıq və oxuma performansı üçün. Bir node çökdükdə replica data-nı qoruyur. Default: 1 primary, 1 replica.

### S3: Logstash ilə Filebeat arasında fərq nədir?
**C:** Filebeat Go-da yazılıb, yüngüldir (10-20MB RAM), yalnız log fayllarını oxuyub göndərir. Logstash JVM-də işləyir, ağırdır (500MB+ RAM), amma güclü filter/transform imkanları var (grok, mutate, geoip). Ən yaxşı arxitektura: Filebeat serverlədə log toplayır -> Logstash mərkəzi serverdə parse edir -> Elasticsearch-ə yazır. Sadə hallarda Filebeat birbaşa Elasticsearch-ə göndərə bilər.

### S4: Elasticsearch cluster scaling necə edilir?
**C:** Horizontal scaling: yeni node əlavə et, shard-lar avtomatik paylanır. Shard sayı index yaradılarkən təyin olunur (sonra dəyişmir). Replica sayı runtime-da artırıla bilər. Hot-warm-cold arxitektura: yeni data SSD (hot), köhnə data HDD (warm), arxiv (cold). ILM policy ilə index lifecycle idarə olunur. Data node, master node, coordinating node rolları var.

### S5: Laravel loglarını ELK-ə necə göndərirsiniz?
**C:** İki yanaşma: 1) Filebeat ilə - Laravel log faylını (storage/logs/laravel.log) Filebeat oxuyur və Logstash-a göndərir. Multiline pattern lazımdır (stack trace üçün). 2) Birbaşa - Monolog SocketHandler və ya GelfHandler ilə logları TCP üzərindən Logstash-a göndərmək. JSON formatter istifadə etmək strukturlaşdırılmış logging üçün vacibdir. Context əlavə etmək (user_id, request_id) axtarışı asanlaşdırır.

### S6: Log volume çox olduqda performansı necə artırırsınız?
**C:** 1) Index lifecycle management (ILM) - köhnə logları sil/arxivlə, 2) Lazımsız logları filter et (debug level production-da olmasın), 3) Shard sayını düzgün seç (shard başına 20-50GB), 4) Hot-warm-cold arxitektura, 5) Logstash pipeline-da lazy filtering, 6) Bulk indexing, 7) Replica sayını azalt (disk space), 8) Index template-lərdə mapping optimize et.

## Best Practices

1. **Strukturlaşdırılmış logging** - JSON format, kontekst əlavə edin
2. **Index naming convention** - `app-logs-YYYY.MM.dd` formatı
3. **ILM policy** konfiqurasiya edin - Köhnə logları avtomatik silin
4. **Log level-i düzgün istifadə edin** - Production-da DEBUG olmasın
5. **Sensitive data-nı maskalayın** - Password, token, PII data
6. **Multiline log** konfiqurasiyası - Stack trace-lər üçün
7. **Request ID** əlavə edin - Distributed tracing üçün
8. **Alert** qurun - Error spike olduqda xəbərdar olun
9. **Disk space** monitorinq edin - Elasticsearch çox disk istifadə edir
10. **Kibana saved searches** yaradın - Tez-tez istifadə olunan axtarışları saxlayın
