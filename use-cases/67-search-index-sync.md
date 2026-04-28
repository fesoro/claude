# Search Index Synchronization: MySQL → Elasticsearch/Meilisearch (Senior)

## Problem Təsviri

E-commerce, SaaS və ya istənilən məhsul kataloquna malik application-da **search index sync** (axtarış indeksi sinxronizasiyası) kritik problemdir. Application-da iki ayrı datastore mövcuddur:

- **MySQL** — source of truth (həqiqətin mənbəyi), bütün CRUD əməliyyatları buradan keçir
- **Elasticsearch / Meilisearch** — axtarış engine-i, yalnız oxumaq üçün istifadə olunur

Problem: MySQL-də product yenilənir, amma axtarış engine-ində köhnə data qalır.

```
Admin: "iPhone 15" qiymətini $999 → $899 edir
       ↓
MySQL: price = 899  ✓
       ↓
Elasticsearch: price = 999  ✗  ← Axtarışda köhnə qiymət görünür!
```

### Problem Niyə Yaranır?

**1. İki ayrı datastore, avtomatik sync yoxdur.** MySQL dəyişikliyi Elasticsearch-ə özü-özünə yansımır. Developer bu bridge-i özü qurmalıdır.

**2. Bulk import əməliyyatları Eloquent observer-lərini bypass edir.** `Product::insert([...])`, `DB::table('products')->insert(...)` və ya CSV import kimi mass update-lər model event-lərini tetikləmir — minlərlə product dəyişir, search index-i xəbər tutmur.

**3. Soft delete əməliyyatları çox vaxt unudulur.** Soft deleted product axtarışda görünməyə davam edir. `SoftDeletes` trait işlədilsə belə, index-dən silinmə əlavə iş tələb edir.

**4. Deploy zamanı yarımçıq sync.** Reindex prosesi başlayır, deploy baş verir, process öldürülür — index yarımçıq qalır. Bu vəziyyətdə bəzi məhsullar index-də yoxdur.

**5. Schema dəyişikliyi zamanı downtime.** Index mapping dəyişdirildikdə (yeni field, yeni analyzer) mövcud index silinib yenidən yaradılmalıdır — bu müddətdə axtarış işləmir.

### Nəticələri

- **İstifadəçi yanlış məlumat görür** — köhnə qiymət, silinmiş məhsul axtarışda çıxır
- **Biznesdə itirim** — "out of stock" məhsul satışda görünür, sifariş gəlir, ödənilə bilmir
- **Support yükü artır** — "axtarışda tapılmır" şikayətləri
- **Etibar itkisi** — istifadəçi axtarışa inanmır, manual scroll etmək məcburiyyətindədir

---

## Həll 1: Laravel Scout (Sync in Request)

Laravel Scout ən sadə inteqrasiyadır. Model save olduqda avtomatik index-ə yazır.

*Bu kod Product model-inə Scout integration-ı əlavə edir və index-ə yazılacaq field-ləri müəyyən edir:*

```php
// app/Models/Product.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

class Product extends Model
{
    use Searchable, SoftDeletes;

    protected $fillable = [
        'name', 'description', 'price', 'stock',
        'category_id', 'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /**
     * Search engine-ə yazılacaq data strukturu.
     * N+1 problem yaranmaması üçün relation-lar əvvəlcədən eager load edilməlidir.
     */
    public function toSearchableArray(): array
    {
        // Lazy load olmaya bilər — bu method çağırılmadan əvvəl
        // with(['category', 'tags']) istifadə edin
        $this->loadMissing(['category', 'tags']);

        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'description' => $this->description,
            'price'       => (float) $this->price,
            'category'    => $this->category?->name,
            'category_id' => $this->category_id,
            'tags'        => $this->tags->pluck('name')->toArray(),
            'in_stock'    => $this->stock > 0,
            'stock'       => $this->stock,
            'is_active'   => $this->is_active,
            'updated_at'  => $this->updated_at->timestamp,
        ];
    }

    /**
     * Bu metod false qaytararsa, model search index-ə yazılmır / silinir.
     * Deaktiv və ya silinmiş məhsulları index-dən saxlamırıq.
     */
    public function shouldBeSearchable(): bool
    {
        return $this->is_active && !$this->trashed();
    }
}
```

### Scout Avtomatik Sync Nə Zaman İşləyir, Nə Zaman İşləmir?

| Əməliyyat | Scout Sync |
|-----------|-----------|
| `$product->save()` | İşləyir |
| `$product->update([...])` | İşləyir |
| `$product->delete()` (soft) | İşləyir |
| `Product::create([...])` | İşləyir |
| `Product::insert([...])` | **İşləmir** |
| `DB::table('products')->update(...)` | **İşləmir** |
| CSV / seeder import | **İşləmir** |
| `Product::whereIn(...)->update(...)` | **İşləmir** |

**Əsas problem:** Scout yalnız Eloquent model event-lərinə qoşulur. Bulk əməliyyatlar bu event-ləri tetikləmir.

---

## Həll 2: Queue-Based Async Sync (Tövsiyə Edilən)

HTTP request içindən birbaşa search engine-ə yazmaq user-i gözlətdirir (Elasticsearch timeout ala bilər) və uğursuzluq halında request fail edir. Daha etibarlı yanaşma: sync-i queue-ya ötürmək.

### Scout Queue-nu Aktiv Etmək

*Bu konfiqurasiya Scout-un bütün index əməliyyatlarını queue-ya göndərməsini təmin edir:*

```php
// config/scout.php
return [
    'driver' => env('SCOUT_DRIVER', 'elasticsearch'),

    'queue' => [
        'connection' => 'redis',
        'queue'      => 'search-sync',
    ],

    // Elasticsearch driver üçün:
    'elasticsearch' => [
        'hosts' => [env('ELASTICSEARCH_HOST', 'http://localhost:9200')],
    ],

    // Meilisearch driver üçün:
    'meilisearch' => [
        'host'   => env('MEILISEARCH_HOST', 'http://localhost:7700'),
        'key'    => env('MEILISEARCH_KEY'),
    ],
];
```

### Custom Sync Job

Scout-un default queue job-u limiteddir. Custom job daha çox kontrol verir: retry, backoff, partial failure handling.

*Bu job product-ı search index-ə sync edən əsas əməliyyatı həyata keçirir:*

```php
// app/Jobs/SyncProductToSearchJob.php
namespace App\Jobs;

use App\Models\Product;
use App\Repositories\ProductSearchRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncProductToSearchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Maksimum 3 cəhd: əvvəlcə 5 saniyə, sonra 30, sonra 120 gözlər.
     * Elasticsearch müvəqqəti down olduğunda bu backoff kifayətdir.
     */
    public int $tries = 3;
    public array $backoff = [5, 30, 120];

    /**
     * Job 10 dəqiqədən çox işlərsə, timeout sayılır.
     */
    public int $timeout = 600;

    public function __construct(
        public readonly int    $productId,
        public readonly string $operation // 'upsert' | 'delete'
    ) {}

    public function handle(ProductSearchRepository $searchRepo): void
    {
        if ($this->operation === 'delete') {
            $searchRepo->delete($this->productId);
            Log::info('Product deleted from search index', ['product_id' => $this->productId]);
            return;
        }

        // Soft deleted məhsulu da yoxlayırıq (shouldBeSearchable false qaytarsa silirik)
        $product = Product::withTrashed()
            ->with(['category', 'tags'])
            ->find($this->productId);

        if (!$product || !$product->shouldBeSearchable()) {
            // Məhsul yoxdur, deaktiv edilib, və ya silinib — index-dən sil
            $searchRepo->delete($this->productId);
            Log::info('Product removed from search index (not searchable)', [
                'product_id' => $this->productId,
                'exists'     => (bool) $product,
            ]);
            return;
        }

        $searchRepo->upsert($product->toSearchableArray());

        Log::info('Product synced to search index', ['product_id' => $this->productId]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SyncProductToSearchJob failed permanently', [
            'product_id' => $this->productId,
            'operation'  => $this->operation,
            'error'      => $exception->getMessage(),
        ]);

        // Alert göndər — search drift başlayır
        // Notification::route('slack', ...)->notify(new SearchSyncFailedNotification(...));
    }
}
```

### ProductSearchRepository

*Bu repository Elasticsearch/Meilisearch ilə əlaqəni mərkəzləşdirir, iş məntiqi job-lardan izolə olunur:*

```php
// app/Repositories/ProductSearchRepository.php
namespace App\Repositories;

use Elastic\Elasticsearch\Client;
use Illuminate\Support\Facades\Log;

class ProductSearchRepository
{
    public function __construct(private Client $elasticsearch) {}

    /**
     * Məhsulu index-ə əlavə et və ya yenilə.
     */
    public function upsert(array $document): void
    {
        $this->elasticsearch->index([
            'index' => 'products',
            'id'    => $document['id'],
            'body'  => $document,
        ]);
    }

    /**
     * Məhsulu index-dən sil.
     */
    public function delete(int $productId): void
    {
        try {
            $this->elasticsearch->delete([
                'index' => 'products',
                'id'    => $productId,
            ]);
        } catch (\Elastic\Elasticsearch\Exception\ClientResponseException $e) {
            // 404 — məhsul artıq index-də yoxdur, problem deyil
            if ($e->getCode() === 404) {
                return;
            }
            throw $e;
        }
    }

    /**
     * Çoxlu məhsulu bir sorğu ilə index-ə əlavə et (bulk import üçün).
     */
    public function bulkUpsert(string $index, array $documents): void
    {
        $params = ['body' => []];

        foreach ($documents as $doc) {
            $params['body'][] = [
                'index' => ['_index' => $index, '_id' => $doc['id']],
            ];
            $params['body'][] = $doc;
        }

        $response = $this->elasticsearch->bulk($params);

        if ($response['errors']) {
            $errors = collect($response['items'])
                ->filter(fn($item) => isset($item['index']['error']))
                ->count();

            Log::warning("Bulk upsert: {$errors} document(s) failed", [
                'index' => $index,
            ]);
        }
    }

    /**
     * Yeni index yaratmaq (alias swap üçün istifadə olunur).
     */
    public function createIndex(string $index): void
    {
        $this->elasticsearch->indices()->create([
            'index' => $index,
            'body'  => [
                'settings' => [
                    'number_of_shards'   => 1,
                    'number_of_replicas' => 1,
                ],
                'mappings' => [
                    'properties' => [
                        'id'          => ['type' => 'integer'],
                        'name'        => ['type' => 'text', 'analyzer' => 'standard'],
                        'description' => ['type' => 'text'],
                        'price'       => ['type' => 'double'],
                        'category'    => ['type' => 'keyword'],
                        'category_id' => ['type' => 'integer'],
                        'tags'        => ['type' => 'keyword'],
                        'in_stock'    => ['type' => 'boolean'],
                        'stock'       => ['type' => 'integer'],
                        'is_active'   => ['type' => 'boolean'],
                        'updated_at'  => ['type' => 'long'],
                    ],
                ],
            ],
        ]);
    }

    /**
     * Alias-ı köhnə index-dən yeni index-ə atomik şəkildə dəyişir.
     */
    public function swapAlias(string $alias, string $newIndex): void
    {
        // Mövcud alias-ın bağlı olduğu index-ləri tap
        $currentIndices = [];
        try {
            $aliases = $this->elasticsearch->indices()->getAlias(['name' => $alias]);
            $currentIndices = array_keys($aliases->asArray());
        } catch (\Throwable) {
            // Alias mövcud deyil — ilk dəfə yaradılır
        }

        $actions = [];

        foreach ($currentIndices as $oldIndex) {
            $actions[] = ['remove' => ['index' => $oldIndex, 'alias' => $alias]];
        }

        $actions[] = ['add' => ['index' => $newIndex, 'alias' => $alias]];

        // Atomik swap — axtarış heç bir an kəsilmir
        $this->elasticsearch->indices()->updateAliases([
            'body' => ['actions' => $actions],
        ]);
    }
}
```

### Observer ilə Tetikleme

*ProductObserver model dəyişikliklərini tutur və sync job-unu queue-ya göndərir:*

```php
// app/Observers/ProductObserver.php
namespace App\Observers;

use App\Jobs\SyncProductToSearchJob;
use App\Models\Product;

class ProductObserver
{
    /**
     * Create və update üçün — 2 saniyəlik debounce ilə.
     * Bir product eyni saniyədə 5 dəfə save olunursa (pipeline),
     * yalnız son sync qalib gəlir. Queue-da duplicate iş azalır.
     */
    public function saved(Product $product): void
    {
        SyncProductToSearchJob::dispatch($product->id, 'upsert')
            ->onQueue('search-sync')
            ->delay(now()->addSeconds(2));
    }

    /**
     * Hard delete üçün.
     */
    public function deleted(Product $product): void
    {
        // Soft delete-dirsə, saved() artıq tetiklənib —
        // shouldBeSearchable() false qaytaracaq, job özü siler.
        // Hard delete üçün explicit silirik.
        if (!$product->isForceDeleting()) {
            return;
        }

        SyncProductToSearchJob::dispatch($product->id, 'delete')
            ->onQueue('search-sync');
    }

    /**
     * Restore olduqda yenidən index-ə əlavə et.
     */
    public function restored(Product $product): void
    {
        SyncProductToSearchJob::dispatch($product->id, 'upsert')
            ->onQueue('search-sync');
    }
}
```

*Observer-i model ilə qeydiyyatdan keçirmək:*

```php
// app/Providers/AppServiceProvider.php
use App\Models\Product;
use App\Observers\ProductObserver;

public function boot(): void
{
    Product::observe(ProductObserver::class);
}
```

### Queue Worker Konfiqurasiyası

*Bu konfiqurasiya `search-sync` queue üçün ayrıca worker prosesi qurur:*

```php
// Supervisor konfiqurasiyası (production)
// /etc/supervisor/conf.d/search-sync-worker.conf

/*
[program:search-sync-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/artisan queue:work redis --queue=search-sync --tries=3 --timeout=600
autostart=true
autorestart=true
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/search-sync-worker.log
*/
```

---

## Həll 3: Incremental Sync (Scheduled)

Queue-based sync observer-dən asılıdır. Bulk import, birbaşa DB əməliyyatları, və ya observer xəttinin hər hansı nöqtəsindəki uğursuzluq sync-i buraxdıra bilər. Incremental sync bu "düşmüş" hadisələri tutur.

*Bu command son N dəqiqədə dəyişən bütün məhsulları tapıb sync-ə göndərir:*

```php
// app/Console/Commands/IncrementalSearchSyncCommand.php
namespace App\Console\Commands;

use App\Jobs\SyncProductToSearchJob;
use App\Models\Product;
use Illuminate\Console\Command;

class IncrementalSearchSyncCommand extends Command
{
    protected $signature = 'search:sync-incremental
                            {--minutes=5 : Son neçə dəqiqənin dəyişiklikləri sync edilsin}
                            {--dry-run : Həqiqətən sync etmədən sayı göstər}';

    protected $description = 'Son N dəqiqədə dəyişən məhsulları search index-ə sync edir';

    public function handle(): int
    {
        $minutes = (int) $this->option('minutes');
        $since   = now()->subMinutes($minutes);
        $dryRun  = $this->option('dry-run');

        $this->info("Son {$minutes} dəqiqənin dəyişiklikləri axtarılır...");

        $count = 0;

        Product::withTrashed()
            ->where(function ($query) use ($since) {
                $query->where('updated_at', '>=', $since)
                      ->orWhere('deleted_at', '>=', $since);
            })
            ->select(['id', 'deleted_at']) // Yalnız lazım olan field-lər
            ->chunk(500, function ($products) use ($dryRun, &$count) {
                foreach ($products as $product) {
                    $operation = $product->trashed() ? 'delete' : 'upsert';

                    if (!$dryRun) {
                        SyncProductToSearchJob::dispatch($product->id, $operation)
                            ->onQueue('search-sync');
                    }

                    $count++;
                }
            });

        $action = $dryRun ? 'tapıldı (dry-run)' : 'sync-ə göndərildi';
        $this->info("{$count} məhsul {$action}.");

        return Command::SUCCESS;
    }
}
```

*Bu command hər 5 dəqiqədə avtomatik işləmək üçün schedule-a əlavə edilir:*

```php
// bootstrap/app.php (Laravel 11) və ya app/Console/Kernel.php
use App\Console\Commands\IncrementalSearchSyncCommand;
use Illuminate\Support\Facades\Schedule;

Schedule::command(IncrementalSearchSyncCommand::class, ['--minutes=6'])
    ->everyFiveMinutes()
    ->onOneServer() // Bir neçə server varsa yalnız birində işləsin
    ->withoutOverlapping(10) // Əvvəlki run bitməyibsə, skip et
    ->runInBackground();
```

---

## Həll 4: Full Reindex Without Downtime (Alias Swap)

Index mapping dəyişdikdə (yeni field, yeni analyzer) və ya ilk deployment-da full reindex lazımdır. Mövcud index-i silib yenidən yaratmaq axtarışın müvəqqəti işləməməsinə səbəb olur. Alias swap bu problemi həll edir.

### Alias Swap Konsepti

```
Cari vəziyyət:
  products (alias) → products_v1 (real index)

Addımlar:
  1. products_v2 yarat
  2. products_v2-yə bütün məhsulları yaz
  3. Atomik swap: products alias → products_v2
  4. products_v1-i sil (isteğe bağlı)

Nəticə:
  products (alias) → products_v2 (real index)

Axtarış prosesinin heç bir anında kəsilməsi yoxdur.
```

*Bu command yeni index yaradır, bütün məhsulları yazır, alias-ı swap edir:*

```php
// app/Console/Commands/ReindexProductsCommand.php
namespace App\Console\Commands;

use App\Models\Product;
use App\Repositories\ProductSearchRepository;
use Illuminate\Console\Command;

class ReindexProductsCommand extends Command
{
    protected $signature = 'search:reindex
                            {--alias=products : Index alias adı}
                            {--chunk=1000 : Hər dəfə neçə məhsul işlənsin}';

    protected $description = 'Bütün məhsulları yeni index-ə yazır, downtime olmadan alias swap edir';

    public function handle(ProductSearchRepository $repo): int
    {
        $alias     = $this->option('alias');
        $chunkSize = (int) $this->option('chunk');

        // Versioned index adı: products_1714300000
        $newIndex = $alias . '_' . now()->timestamp;

        $this->info("Yeni index yaradılır: {$newIndex}");
        $repo->createIndex($newIndex);

        $total = Product::active()->count();
        $this->info("Cəmi {$total} məhsul yazılacaq.");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $written = 0;
        $failed  = 0;

        Product::active()
            ->with(['category', 'tags'])
            ->chunk($chunkSize, function ($products) use ($repo, $newIndex, $bar, &$written, &$failed) {
                try {
                    $documents = $products
                        ->map(fn($p) => $p->toSearchableArray())
                        ->all();

                    $repo->bulkUpsert($newIndex, $documents);
                    $written += count($documents);
                } catch (\Throwable $e) {
                    $failed += $products->count();
                    $this->newLine();
                    $this->error("Chunk xətası: {$e->getMessage()}");
                }

                $bar->advance($products->count());
            });

        $bar->finish();
        $this->newLine();

        if ($failed > 0) {
            $this->warn("{$failed} məhsul yazıla bilmədi. Alias swap edilmir.");
            return Command::FAILURE;
        }

        $this->info("Alias swap edilir: {$alias} → {$newIndex}");
        $repo->swapAlias($alias, $newIndex);

        $this->info("Reindex tamamlandı. {$written} məhsul yazıldı.");
        $this->line("Köhnə index əl ilə silinə bilər: {$alias}_<köhnə timestamp>");

        return Command::SUCCESS;
    }
}
```

---

## Həll 5: Consistency Check (Drift Aşkarlama)

Heç bir sistem 100% mükəmməl deyil. Uzun müddət işlədikdə MySQL ilə search index arasında drift (uyğunsuzluq) yaranır. Bu job uyğunsuzluğu aşkarlayıb alert göndərir.

*Bu job MySQL-dəki aktiv məhsul sayını Elasticsearch-dəki count ilə müqayisə edir, böyük fərq varsa alert göndərir:*

```php
// app/Jobs/SearchIndexConsistencyCheckJob.php
namespace App\Jobs;

use App\Models\Product;
use App\Repositories\ProductSearchRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class SearchIndexConsistencyCheckJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * MySQL ilə Elasticsearch arasındakı fərq bu faizi keçərsə, alert göndər.
     * Məs: 0.02 = %2 fərq.
     */
    private const DRIFT_THRESHOLD = 0.02;

    public function handle(ProductSearchRepository $searchRepo): void
    {
        $mysqlCount = Product::active()->count();
        $esCount    = $searchRepo->count();

        $diff        = abs($mysqlCount - $esCount);
        $driftRatio  = $mysqlCount > 0 ? $diff / $mysqlCount : 0;

        Log::info('Search index consistency check', [
            'mysql_count' => $mysqlCount,
            'es_count'    => $esCount,
            'diff'        => $diff,
            'drift_ratio' => round($driftRatio * 100, 2) . '%',
        ]);

        if ($driftRatio > self::DRIFT_THRESHOLD) {
            Log::warning('Search index drift detected', [
                'mysql_count' => $mysqlCount,
                'es_count'    => $esCount,
                'diff'        => $diff,
            ]);

            // Slack / PagerDuty alert
            // Notification::route('slack', config('services.slack.ops_channel'))
            //     ->notify(new SearchDriftAlertNotification($mysqlCount, $esCount, $diff));

            // Drift böyükdürsə, incremental sync tetiklə
            if ($driftRatio > 0.10) {
                \Illuminate\Support\Facades\Artisan::call('search:sync-incremental', [
                    '--minutes' => 60,
                ]);
            }
        }
    }
}
```

*Consistency check-i cədvələ əlavə etmək:*

```php
// bootstrap/app.php
Schedule::job(new SearchIndexConsistencyCheckJob())
    ->hourly()
    ->onOneServer();
```

---

## Trade-offs

| Yanaşma | Latency | Etibarlılıq | Mürəkkəblik | Nə Zaman İstifadə Et |
|---------|---------|-------------|-------------|----------------------|
| **Scout (sync in request)** | Dəqiq (real-time) | Aşağı — HTTP request fail olarsa sync olmur | Aşağı | Development, kiçik trafik |
| **Queue-based async** | Bir neçə saniyə gecikə bilər | Yüksək — retry, backoff, dead letter queue | Orta | Tövsiyə edilən — production standartı |
| **Incremental sync** | 1–10 dəqiqə | Yüksək — missed event-ləri tutur | Aşağı | Queue-based sync ilə birlikdə, fallback kimi |
| **Alias swap (full reindex)** | Bir dəfəlik əməliyyat | Çox yüksək — downtime yoxdur | Yüksək | Schema dəyişikliyi, initial setup |
| **CDC (Change Data Capture)** | Millisaniyə | Ən yüksək — binlog oxuyur | Çox yüksək | Yüksək yük, real-time tələbi olan sistemlər |

### CDC Haqqında Qısa Qeyd

**CDC (Change Data Capture)** — MySQL binary log-u oxuyaraq hər dəyişikliyi tutmaq. Debezium + Kafka stack-i ilə qurulur. Bulk insert-lər, birbaşa DB əməliyyatları da tutulur. Latency millisaniyə səviyyəsindədir. Ancaq infrastruktur mürəkkəbliyi yüksəkdir: Kafka, Debezium connector, schema registry lazımdır. 5+ milyon məhsulu olan sistemlər üçün nəzərə alın.

---

## Anti-patternlər

**1. Sync-i HTTP request içindən etmək**

```php
// Pis yanaşma ❌
public function update(Request $request, Product $product): JsonResponse
{
    $product->update($request->validated());
    $this->elasticsearchClient->index([...]); // User gözləyir + timeout riski
    return response()->json($product);
}
```

Elasticsearch müvəqqəti down olduqda bütün product update-ləri fail edir. Həmçinin Elasticsearch-in latency-si user-in cavab gözləmə müddətini artırır. Həmişə queue istifadə edin.

**2. Bulk insert/update-i observer-dən keçirməmək**

```php
// Pis yanaşma ❌
Product::insert($products); // Observer işləmir!

// Düzgün yanaşma ✓
foreach ($products as $productData) {
    $product = Product::create($productData); // Observer işləyir
    // amma çox yavaş...
}

// Daha yaxşı yanaşma ✓
Product::insert($products);
// Sonra ayrıca sync tetikləmək:
$insertedIds = Product::whereIn('sku', array_column($products, 'sku'))->pluck('id');
$insertedIds->each(fn($id) => SyncProductToSearchJob::dispatch($id, 'upsert'));
```

**3. Delete əməliyyatlarını unutmaq**

Ən çox buraxılan xəta: soft delete olunmuş məhsul axtarışda görünməyə davam edir. Observer-də `deleted` və `restored` metodlarını mütləq implement edin. `forceDelete` üçün ayrıca `deleted` observer metodunda hard delete yoxlaması əlavə edin.

**4. Reindex zamanı downtime etmək**

```bash
# Pis yanaşma ❌
curl -X DELETE http://localhost:9200/products  # Axtarış işləmir!
php artisan search:reindex                     # 10 dəqiqə sürsə, 10 dəqiqə downtime

# Düzgün yanaşma ✓
php artisan search:reindex  # Alias swap ilə — downtime sıfır
```

**5. Index schema dəyişikliyi zamanı alias swap etməmək**

Mövcud index-in mapping-ini birbaşa dəyişmək olmur (Elasticsearch bunu qadağan edir). Yeni field əlavə etmək üçün yeni index + alias swap məcburidir. Bu prosesi standart deployment pipeline-a daxil edin.

**6. Sync failure-larını monitoring etməmək**

Queue job fail olduqda dead letter queue-ya düşür. Əgər monitoring yoxdursa, yüzlərlə product sync olunmadan qalır, heç kim bilmir. `job.failed` event-inə qoşulun, Slack/PagerDuty alert qurun, drift threshold monitoring əlavə edin.

**7. `toSearchableArray()` metodunda N+1 yaratmaq**

```php
// Pis yanaşma ❌
public function toSearchableArray(): array
{
    return [
        'category' => $this->category->name,  // Hər product üçün ayrı query!
        'tags'     => $this->tags->pluck('name')->toArray(), // Yenə ayrı query!
    ];
}

// Düzgün yanaşma ✓
// Job-da:
$product = Product::with(['category', 'tags'])->find($this->productId);
// Reindex-də:
Product::active()->with(['category', 'tags'])->chunk(1000, ...);
```

---

## Interview Sualları və Cavablar

**S: MySQL-dəki product dəyişikliyi search index-ə niyə avtomatik yansımır?**

C: Elasticsearch və MySQL iki müstəqil datastore-dur. MySQL-in internal event mexanizmi Elasticsearch-dən xəbərsizdir. Developer bu sync körpüsünü özü qurmalıdır: ya Eloquent observer + queue job, ya scheduled incremental sync, ya da CDC (Change Data Capture) ilə binlog oxumaq. Scout bu iş üçün bir wrapper verdir, amma bulk əməliyyatları üçün əlavə mexanizm tələb edir.

**S: CDC (Change Data Capture) nədir, nə zaman lazımdır?**

C: CDC — MySQL binary log-unu oxuyaraq hər insert/update/delete əməliyyatını event kimi capture etmək. Debezium + Kafka stack-i ən geniş yayılmış yanaşmadır. Eloquent observer-dən fərqli olaraq bulk insert, birbaşa DB əməliyyatları, hətta başqa application-ların dəyişikliklərini də tutur. Millisaniyə latency ilə işləyir. Ancaq infrastruktur mürəkkəbdir. Böyük kataloqun (1M+ məhsul), yüksək yazma tezliyinin, və ya cross-service sync tələbinin olduğu sistemlər üçün nəzərə alınmalıdır.

**S: Alias swap nədir, niyə lazımdır?**

C: Elasticsearch-də mövcud index-in mapping-ini dəyişmək olmur (yeni field tipi əlavə etmək, analyzer dəyişdirmək). Bunun üçün yeni index yaradılır, bütün datalar yazılır, sonra alias (virtual ad) atomik şəkildə köhnə index-dən yeni index-ə dəyişdirilir. Bu əməliyyat millisaniyələr çəkir, axtarış prosesinin heç bir anında kəsilmir. Alternativ — index silmək, yenidən yaratmaq — deployment zamanı axtarışı tamamilə dayandırır.

**S: Search index-dəki drift-i necə aşkarlarsınız?**

C: Əsas metrik: MySQL-dəki aktiv məhsul sayı ilə Elasticsearch-dəki document sayını müqayisə etmək. Fərq müəyyən threshold-u keçərsə (məs: %2), alert göndərilir. Daha dərin yoxlama üçün: son X saatda dəyişən product ID-lərini MySQL-dən çəkmək, eyni ID-ləri Elasticsearch-dən çəkmək, `updated_at` timestamp-lərini müqayisə etmək. Bu yoxlamanı saatda bir scheduled job ilə avtomatik etmək lazımdır.

**S: Elasticsearch ilə Meilisearch arasında fərq nədir, hansını seçmək lazımdır?**

C: **Elasticsearch** — enterprise miqyaslı, çox güclü aggregation, geo-search, full-text search imkanları. Konfiqurasiya mürəkkəbdir, resurs isteği yüksəkdir, Scout driver-i mövcuddur. Böyük kataloqun, mürəkkəb filtrlərin, analitikanın olduğu sistemlər üçün. **Meilisearch** — developer experience-ə fokuslanmış, quraşdırması asan, out-of-the-box typo tolerance, faset axtarışı, Scout driver mövcuddur. Orta ölçülü kataloqun (1-5M məhsul), sadə search UI-nin, tez deployment-ın lazım olduğu layihələr üçün. Seçim meyarı: komandanın infrastruktur kapasitesi, axtarış mürəkkəbliyi, shard/replica ehtiyacı.

---

## Əlaqəli Mövzular

- `02-double-charge-prevention.md` — Idempotency pattern, queue-based iş məntiqi
- `17-bulk-data-import.md` — Bulk import zamanı observer bypass problemi
- `31-event-sourcing.md` — Append-only log, event replay ilə reindex
- `58-queue-workers-horizon.md` — Laravel Horizon ilə queue monitoring
