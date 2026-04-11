# Elasticsearch, Search və Data Processing

## 1. Full-Text Search — Elasticsearch ilə Laravel

```php
// Laravel Scout + Elasticsearch driver
// composer require laravel/scout
// composer require babenkoivan/elastic-scout-driver

class Product extends Model {
    use Searchable;

    public function toSearchableArray(): array {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'category' => $this->category->name,
            'price' => $this->price,
            'tags' => $this->tags->pluck('name')->toArray(),
            'created_at' => $this->created_at->toISOString(),
        ];
    }

    // Hansı index-ə yazılsın
    public function searchableAs(): string {
        return 'products_index';
    }
}

// Axtarış
$products = Product::search('iphone case')
    ->where('category', 'electronics')
    ->orderBy('price', 'asc')
    ->paginate(20);

// Bulk index
php artisan scout:import "App\Models\Product"
```

**Meilisearch (alternativ — daha sadə):**
```php
// composer require meilisearch/meilisearch-php
// .env: SCOUT_DRIVER=meilisearch

$results = Product::search('telefon')
    ->where('price', '<=', 1000)
    ->get();
```

---

## 2. Elasticsearch birbaşa istifadə (Scout olmadan)

```php
// composer require elasticsearch/elasticsearch

$client = ClientBuilder::create()
    ->setHosts(['localhost:9200'])
    ->build();

// Index yaratma (mapping)
$client->indices()->create([
    'index' => 'products',
    'body' => [
        'mappings' => [
            'properties' => [
                'name' => ['type' => 'text', 'analyzer' => 'standard'],
                'description' => ['type' => 'text'],
                'price' => ['type' => 'float'],
                'category' => ['type' => 'keyword'],  // exact match
                'tags' => ['type' => 'keyword'],
                'created_at' => ['type' => 'date'],
                'location' => ['type' => 'geo_point'], // GIS
            ],
        ],
    ],
]);

// Sənəd əlavə et
$client->index([
    'index' => 'products',
    'id' => $product->id,
    'body' => $product->toSearchableArray(),
]);

// Axtarış — complex query
$results = $client->search([
    'index' => 'products',
    'body' => [
        'query' => [
            'bool' => [
                'must' => [
                    ['multi_match' => [
                        'query' => 'wireless headphones',
                        'fields' => ['name^3', 'description'], // name 3x daha vacib
                    ]],
                ],
                'filter' => [
                    ['range' => ['price' => ['gte' => 10, 'lte' => 200]]],
                    ['term' => ['category' => 'electronics']],
                ],
            ],
        ],
        'sort' => [
            ['_score' => 'desc'],
            ['price' => 'asc'],
        ],
        'aggs' => [
            'categories' => ['terms' => ['field' => 'category']],
            'avg_price' => ['avg' => ['field' => 'price']],
            'price_ranges' => [
                'range' => [
                    'field' => 'price',
                    'ranges' => [
                        ['to' => 50],
                        ['from' => 50, 'to' => 100],
                        ['from' => 100],
                    ],
                ],
            ],
        ],
        'highlight' => [
            'fields' => ['name' => new stdClass(), 'description' => new stdClass()],
        ],
        'from' => 0,
        'size' => 20,
    ],
]);
```

---

## 3. Data Import / Export — Böyük datalarla işləmək

```php
// CSV Import — chunk ilə
class ImportUsersCommand extends Command {
    protected $signature = 'import:users {file}';

    public function handle(): void {
        $path = $this->argument('file');
        $bar = $this->output->createProgressBar();

        $this->readCsv($path)
            ->chunk(1000)
            ->each(function (LazyCollection $chunk) use ($bar) {
                $records = $chunk->map(fn ($row) => [
                    'name' => $row[0],
                    'email' => $row[1],
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->toArray();

                User::insert($records); // Bulk insert
                $bar->advance($chunk->count());
            });

        $bar->finish();
    }

    private function readCsv(string $path): LazyCollection {
        return LazyCollection::make(function () use ($path) {
            $handle = fopen($path, 'r');
            fgetcsv($handle); // header skip
            while ($row = fgetcsv($handle)) {
                yield $row;
            }
            fclose($handle);
        });
    }
}

// Excel Export (Laravel Excel)
class OrdersExport implements FromQuery, WithHeadings, WithMapping, WithChunkReading {
    public function query(): Builder {
        return Order::query()->with('user');
    }

    public function headings(): array {
        return ['Order ID', 'Customer', 'Total', 'Status', 'Date'];
    }

    public function map($order): array {
        return [
            $order->id,
            $order->user->name,
            $order->total,
            $order->status,
            $order->created_at->format('Y-m-d'),
        ];
    }

    public function chunkSize(): int {
        return 1000;
    }
}

// Queue-da export
(new OrdersExport)->queue('orders.xlsx', 's3');
```

---

## 4. Laravel-də Scheduled Reports

```php
// Report generator service
class ReportService {
    public function dailySalesReport(Carbon $date): array {
        return [
            'date' => $date->toDateString(),
            'total_orders' => Order::whereDate('created_at', $date)->count(),
            'revenue' => Order::whereDate('created_at', $date)->sum('total'),
            'avg_order_value' => Order::whereDate('created_at', $date)->avg('total'),
            'top_products' => DB::table('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->join('products', 'products.id', '=', 'order_items.product_id')
                ->whereDate('orders.created_at', $date)
                ->select('products.name', DB::raw('SUM(order_items.quantity) as sold'))
                ->groupBy('products.name')
                ->orderByDesc('sold')
                ->limit(10)
                ->get(),
        ];
    }
}

// Schedule
Schedule::job(new GenerateDailyReport)->dailyAt('06:00');
```
