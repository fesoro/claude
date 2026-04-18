<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Search;

use Illuminate\Support\Collection;
use Src\Product\Infrastructure\Models\ProductModel;
use Src\Order\Infrastructure\Models\OrderModel;
use Src\User\Infrastructure\Models\UserModel;

/**
 * GLOBAL SEARCH SERVICE (Full-Text Search)
 * ==========================================
 * Bütün entity-lərdə (User, Product, Order) eyni anda axtarış edir.
 *
 * LARAVEL SCOUT NƏDİR?
 * Laravel Scout — full-text search üçün driver-based axtarış sistemidir.
 * Elasticsearch, Algolia, Meilisearch, və ya database driver ilə işləyir.
 *
 * SCOUT ARXİTEKTURASI:
 * Model::search('laptop') → Scout Driver → Search Engine → Nəticələr
 *
 * DRIVER-LƏR:
 * 1. Database (default): SQL LIKE ilə axtarış. Sadə, amma yavaş.
 * 2. Meilisearch: Açıq mənbəli, sürətli, typo-tolerant. Tövsiyə olunur.
 * 3. Algolia: SaaS, çox sürətli, amma pullu.
 * 4. Elasticsearch: Ən güclü, mürəkkəb quraşdırma. Böyük layihələr üçün.
 *
 * SCOUT İSTİFADƏSİ (Model-də):
 * 1. Model-ə `use Searchable;` trait əlavə et
 * 2. `toSearchableArray()` metodu ilə indekslənəcək sahələri təyin et
 * 3. `Model::search('axtarış')->get()` ilə axtar
 *
 * BU SERVİSİN ROLU:
 * Scout hər model-i ayrı axtarır. Bu service bütün model-lərdə
 * eyni anda axtarış edib nəticələri birləşdirir (unified search).
 *
 * NÜMUNƏ:
 * GET /api/search?q=laptop
 * → Products: [Laptop Pro, Gaming Laptop]
 * → Orders: [Order #123 (contains Laptop)]
 * → Users: []
 *
 * FULL-TEXT SEARCH vs LIKE:
 * LIKE '%laptop%' → yavaş (full table scan), typo yoxdur
 * Full-text → sürətli (index istifadə edir), typo dəstəkləyir ("lapto" → "laptop")
 *
 * BU LAYİHƏDƏ: Database driver istifadə edirik (sadəlik üçün).
 * Production-da Meilisearch və ya Elasticsearch tövsiyə olunur.
 */
class SearchService
{
    /**
     * Bütün entity-lərdə axtarış et.
     *
     * @param string $query Axtarış sözü
     * @param int $limit Hər entity-dən maksimum nəticə sayı
     * @return array Qruplaşdırılmış nəticələr
     */
    public function searchAll(string $query, int $limit = 5): array
    {
        $results = [];

        // Products-da axtar
        $results['products'] = $this->searchProducts($query, $limit);

        // Orders-da axtar
        $results['orders'] = $this->searchOrders($query, $limit);

        // Users-da axtar
        $results['users'] = $this->searchUsers($query, $limit);

        // Ümumi nəticə sayı
        $results['total'] = collect($results)
            ->except('total')
            ->sum(fn($items) => count($items));

        return $results;
    }

    /**
     * Məhsullarda axtarış.
     * Ad üzrə LIKE axtarışı.
     */
    public function searchProducts(string $query, int $limit = 10): array
    {
        return ProductModel::where('name', 'LIKE', "%{$query}%")
            ->limit($limit)
            ->get()
            ->map(fn($p) => [
                'id' => $p->id,
                'type' => 'product',
                'title' => $p->name,
                'subtitle' => "{$p->price} {$p->currency}",
                'url' => "/api/products/{$p->id}",
            ])
            ->toArray();
    }

    /**
     * Sifarişlərdə axtarış.
     * ID və ya status üzrə axtarış.
     */
    public function searchOrders(string $query, int $limit = 10): array
    {
        return OrderModel::where('id', 'LIKE', "%{$query}%")
            ->orWhere('status', 'LIKE', "%{$query}%")
            ->limit($limit)
            ->get()
            ->map(fn($o) => [
                'id' => $o->id,
                'type' => 'order',
                'title' => "Sifariş #{$o->id}",
                'subtitle' => "Status: {$o->status}, Məbləğ: {$o->total_amount} {$o->currency}",
                'url' => "/api/orders/{$o->id}",
            ])
            ->toArray();
    }

    /**
     * İstifadəçilərdə axtarış.
     * Ad və ya email üzrə axtarış.
     */
    public function searchUsers(string $query, int $limit = 10): array
    {
        return UserModel::where('name', 'LIKE', "%{$query}%")
            ->orWhere('email', 'LIKE', "%{$query}%")
            ->limit($limit)
            ->get()
            ->map(fn($u) => [
                'id' => $u->id,
                'type' => 'user',
                'title' => $u->name,
                'subtitle' => $u->email,
                'url' => "/api/users/{$u->id}",
            ])
            ->toArray();
    }
}
