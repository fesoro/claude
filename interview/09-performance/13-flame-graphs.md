# Flame Graphs and CPU Profiling (Lead ⭐⭐⭐⭐)

## İcmal

Flame graph — CPU zamanının funksiyalar arasında bölüşdürülməsini vizuallaşdıran bir diaqram növüdür. Brendan Gregg tərəfindən ixtira edilmiş bu format, stacked call stack-ləri horizontal şəkildə göstərir: alt — caller, üst — callee; en — neçə faiz CPU vaxtı. Bir baxışda "CPU vaxtının 40%-i hansı funksiyaya gedir?" sualının cavabını tapmaq mümkündür.

## Niyə Vacibdir

Load test zamanı CPU 100%-ə çıxır — problem hardadır? Log-da görmürsünüz, APM-də ən yavaş endpoint görürsünüz, amma niyə — bilmirsiniz. Flame graph açırsınız: geniş, düz "plateau" görürsünüz — bu "hot function"dur. Onu tapıb optimallaşdırırsınız. Brendan Gregg-in dediyi kimi: "Flame graphs are the best tool I've seen for CPU profiling." Senior developer bilir, Lead developer produksiyada istifadə edir.

## Əsas Anlayışlar

- **Flame graph oxuma:**
  - **X oxu** — zamanı deyil, sorğu sırasını göstərir (alphabetical sort)
  - **Y oxu** — call stack dərinliyi (aşağı → yuxarı: caller → callee)
  - **En** — funksiya CPU vaxtının neçə faizini alır (geniş = çox)
  - **Rəng** — adətən random (fərqli modullara görə)
  - **Düz, geniş sütun (plateau)** → hot function → optimizasiya hədəfi

- **Flame graph növləri:**
  - **CPU flame graph** — CPU üzərindəki funksiyalar
  - **Memory allocation graph** — yaddaş ayırma profili
  - **Off-CPU flame graph** — I/O gözləmə (blocked)
  - **Diff flame graph** — iki profil arasındakı fərq

- **PHP profiling alətləri:**
  - **Xdebug** — `xdebug.mode=profile` → KCachegrind
  - **Blackfire.io** — timeline + call graph, production-safe
  - **Tideways** — flame graph native
  - **SPX (Simple PHP Profiler)** — overhead az, web UI
  - **phpspy** — sampling profiler (Rust), overhead çox az

- **System-level profiling:**
  - **perf** (Linux) — kernel + userspace sampling
  - **FlameGraph** (Brendan Gregg tools) — SVG generasiyası
  - **async-profiler** (Java) — JVM flame graph, JIT aware
  - `pprof` (Go) — built-in, interactive web UI
  - **py-spy** (Python) — sampling, low overhead

- **Call graph vs Flame graph:**
  - Call graph — kim kimi çağırır (tree)
  - Flame graph — hər funksiya nə qədər CPU alır (area)
  - Flame graph daha sürətli hotspot tapır

## Praktik Baxış

**PHP Xdebug → KCachegrind:**

```bash
# php.ini
xdebug.mode = profile
xdebug.output_dir = /tmp/xdebug
xdebug.profiler_output_name = callgrind.out.%p

# URL trigger:
# curl "http://app.test/api/orders?XDEBUG_PROFILE=1"

# KCachegrind ilə aç (Ubuntu):
sudo apt-get install kcachegrind
kcachegrind /tmp/xdebug/callgrind.out.12345
```

**Blackfire flame graph:**

```bash
# CLI profiling
blackfire run php artisan process:orders

# HTTP profiling
blackfire curl https://app.test/api/orders

# Blackfire web UI-da:
# Timeline tab → span-ları gör
# Call Graph tab → treemap / flame
# Comparisons tab → before/after

# PHP SDK
$probe = \BlackfireIo\PhpSdk\Probe::singleton();
$probe->enable();
$result = $this->heavyComputation();
$probe->disable();
// Blackfire UI-da flame görünür
```

**SPX (Simple PHP Profiler) — overhead az:**

```bash
# Docker
docker run -e SPX_ENABLED=1 -e SPX_AUTO_START=0 ...

# .env
SPX_ENABLED=1
SPX_HTTP_ENABLED=1
SPX_HTTP_IP_WHITELIST=127.0.0.1

# URL trigger:
curl "http://app.test/api/orders?SPX_ENABLED=1&SPX_REPORT=full"
# Web UI: http://app.test/?SPX_UI_URI=/
```

**phpspy — sampling profiler (production-safe):**

```bash
# Build phpspy
git clone https://github.com/adsr/phpspy
cd phpspy && make

# PHP prosesini profile et (process ID)
PID=$(ps aux | grep php-fpm | head -1 | awk '{print $2}')
./phpspy -p $PID -d 10 | ./utils/flamegraph.pl > flamegraph.svg

# Çıxış: SVG flame graph → browser-də aç
```

**Linux perf + flame graph (system-level):**

```bash
# perf install
sudo apt-get install linux-perf

# PHP prosesini profile et (30 saniyə)
PID=$(pgrep -f "php-fpm: worker")
sudo perf record -g -p $PID -o perf.data -- sleep 30

# Stack-ları çıxar
sudo perf script -i perf.data > out.perf

# FlameGraph toolları (Brendan Gregg)
git clone https://github.com/brendangregg/FlameGraph
./FlameGraph/stackcollapse-perf.pl out.perf | ./FlameGraph/flamegraph.pl > flamegraph.svg

# Brauzerdə aç: xdg-open flamegraph.svg
# Tıklayaraq zoom, axtarış mümkündür
```

**Go pprof:**

```go
// main.go
import (
    "net/http"
    _ "net/http/pprof"  // /debug/pprof endpoint
)

func main() {
    // pprof server (ayrı portda)
    go func() {
        http.ListenAndServe("localhost:6060", nil)
    }()
    // ...
}
```

```bash
# CPU profiling (30 saniyə)
go tool pprof http://localhost:6060/debug/pprof/profile?seconds=30

# Interactive terminal
(pprof) top 10          # ən çox CPU alan funksiyalar
(pprof) web             # SVG flame graph açır (graphviz lazım)
(pprof) list functionName  # source code ilə annotated

# Web UI
go tool pprof -http=:8080 profile.pb.gz
# http://localhost:8080/ui/flamegraph
```

**Java async-profiler:**

```bash
# async-profiler download
wget https://github.com/async-profiler/async-profiler/releases/download/v3.0/async-profiler-3.0-linux-x64.tar.gz
tar -xvf async-profiler-3.0-linux-x64.tar.gz

# JVM profile et
PID=$(pgrep -f "java.*myapp.jar")
./asprof -d 30 -f flamegraph.html $PID

# Brauzerdə aç
# Filterlə: sadəcə application kod görün
```

**Flame graph oxuma nümunəsi:**

```
# Bu nədir?

      ┌─────────────────────────────────────────────────────────┐  ← top (leaf)
      │         serialize()                                     │
      ├───────────┬─────────────────────────────────────────────┤
      │ json_encode│         array_map()                        │
      ├───────────┴────────────┬────────────────────────────────┤
      │   OrderResource::toArray()     │ other stuff            │
      ├────────────────────────────────┴────────────────────────┤
      │              OrderController::index()                   │  ← bottom (root)
      └─────────────────────────────────────────────────────────┘

Oxu:
- OrderController::index() CPU-nun 100%-ni alır (bir request üçün)
- Bunun 70%-i OrderResource::toArray()-a gedir
- Bunun böyük hissəsi array_map() → serialize()
- "array_map" çox geniş → hot function

Nəticə: Resource transform aşırı serialization edib.
Həll: Daha az field, lazy relation, collection caching.
```

**Diff flame graph (before/after):**

```bash
# Optimallaşdırma öncəsi
blackfire --reference 1 run php bench.php

# Kod dəyişikliyindən sonra
blackfire --reference 1 --samples 5 run php bench.php

# Blackfire UI-da: "Comparison" tab
# Qırmızı → yeni yavaşlamalar
# Yaşıl → optimallaşmalar
# Göy → dəyişmir
```

**Trade-offs:**
- Sampling profiler (phpspy, perf) — az overhead, az dəqiqlik
- Tracing profiler (Xdebug) — çox overhead (~10x), çox dəqiq
- Production: sampling; development: tracing
- Flame graph → hotspot tapır, amma root cause söyləmir
- JIT PHP 8+ ilə flame graph fərqli görünə bilər (inline optimization)

**Common mistakes:**
- Development-da profiling edib production-a tətbiq etmək (data volume fərqlidir)
- Yalnız bir sınıfın overhead-ini görmək (hot call üzərinə zoom etməmək)
- Flame graph-ı production-da Xdebug ilə toplamaq (tətbiqi 10x yavaşladır)
- Bütün kod haqqında qərar vermək (top 3 hotspot = 80% gain)

## Nümunələr

### Real Ssenari: Sifarişlər API niyə 2x yavaşladı?

```
Deployment sonrası: P99 180ms → 380ms.
APM göstərir: /api/orders endpointi yavaşdı.
Trace: PHP execution 290ms, DB 40ms.

Flame graph analiz (Blackfire):
- OrderResource::toArray() → 55% CPU
- İçinə zoom: hər item üçün Category::find() (N+1!)
- Yeni deployment-da $order->items->each fn() əlavə edilmişdi

Həll:
- with('items.category') əlavə edildi
- OrderResource-da $this->whenLoaded() istifadə edildi

Nəticə: P99 380ms → 120ms (öncəkindən də yaxşı)
```

### Kod Nümunəsi

```php
<?php

// Blackfire SDK ilə custom profiling annotation
use Blackfire\Client as BlackfireClient;

class HeavyReportGenerator
{
    public function generate(int $reportId): array
    {
        // Blackfire probe ilə bölmə profiling
        $blackfire = new BlackfireClient();
        $probe = $blackfire->createProbe();

        $probe->addMarker('start:data_fetch');
        $data = $this->fetchData($reportId);

        $probe->addMarker('start:transform');
        $transformed = $this->transform($data);

        $probe->addMarker('start:aggregate');
        $aggregated = $this->aggregate($transformed);

        $blackfire->endProbe($probe);

        // Flame graph-da aydın görünəcək: hər marker öz "plateau"suna sahib

        return $aggregated;
    }

    private function fetchData(int $id): array
    {
        // DB query...
        return Report::with('items.product.category')
            ->findOrFail($id)
            ->toArray();
    }

    private function transform(array $data): array
    {
        // CPU-intensive transformation
        return array_map(fn($item) => $this->formatItem($item), $data['items']);
    }

    private function aggregate(array $items): array
    {
        // Grouping, summing...
        return collect($items)
            ->groupBy('category')
            ->map(fn($group) => $group->sum('total'))
            ->toArray();
    }
}
```

## Praktik Tapşırıqlar

1. **Xdebug flame graph:** Xdebug profiling mode aktivləşdir, N+1 olan bir endpoint-i profile et, KCachegrind-də "Self" zamanına görə sırala, top 3 funksiyanı tap.

2. **Blackfire:** Blackfire CLI qur, `blackfire run php artisan db:seed --class=OrderSeeder` icra et, timeline-də ən uzun span-ı tap.

3. **Go pprof:** Sadə Go HTTP server yaz, `/debug/pprof` aktiv et, benchmark çalışdır, `go tool pprof -http=:8080` ilə flame graph aç.

4. **Diff comparison:** Bir PHP funksiyasını iki fərqli implementation ilə yaz, Blackfire comparison ilə flame graph fərqini izlə.

5. **phpspy:** phpspy build et, PHP-FPM worker-ini 30 saniyə profile et, SVG flame graph yarat, ən geniş "plateau"-nu izah et.

## Əlaqəli Mövzular

- `01-performance-profiling.md` — Profiling əsasları
- `06-memory-leak-detection.md` — Memory allocation flame graph
- `07-garbage-collection.md` — GC pause-larını flame graph-da görmək
- `11-apm-tools.md` — APM + flame graph birlikdə
- `12-load-testing.md` — Load altında CPU flame graph
