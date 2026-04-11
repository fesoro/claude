# Search Autocomplete / Typeahead Dizaynı

## Problem

İstifadəçi axtarış sahəsinə hərflər yazdıqca real-time təkliflər göstərmək lazımdır. Hər düymə basışında server-ə sorğu göndərmək çox yavaş və bahalıdır. Milyonlarla məhsul/istifadəçi/məqalə arasında millisaniyələr ərzində nəticə qaytarmaq tələb olunur.

**Real-world ssenari:**
- Google search suggestions
- Amazon məhsul axtarışı
- Spotify mahnı/artist axtarışı
- GitHub repository axtarışı
- LinkedIn profil axtarışı

**Tələblər:**
- Latency: < 100ms
- Minlərlə paralel istifadəçi
- Typo tolerance (yazım xətasına dözümlülük)
- Relevance ranking (ən uyğun təkliflər yuxarıda)
- İstifadəçinin axtarış tarixçəsi

### Problem niyə yaranır?

Ən sadə implementation: hər hərfdə `LIKE 'query%'` DB sorğusu. "smartphone" yazanda 9 hərflə 9 ayrı query göndərilir. 1000 aktiv user = saniyədə minlərlə DB sorğusu. `LIKE '%query%'` (prefix deyil, içindəki axtarış) isə full table scan edir — index işləmir. Əlavə olaraq: debounce olmadan hər keystroke event sorğu göndərir. Bu üç faktor birlikdə DB-ni çökdürür.

---

## 1. Frontend: Debouncing

Hər düymə basışında sorğu göndərmək əvəzinə, istifadəçi yazmağı dayandırdıqdan sonra göndəririk.

*Bu kod debounce, AbortController və client-side cache ilə autocomplete işləyən JavaScript sinifini göstərir:*

```javascript
// resources/js/search-autocomplete.js

class SearchAutocomplete {
    constructor(inputSelector, resultsSelector, options = {}) {
        this.input = document.querySelector(inputSelector);
        this.resultsContainer = document.querySelector(resultsSelector);
        this.debounceDelay = options.debounceDelay || 300; // 300ms
        this.minChars = options.minChars || 2;
        this.maxResults = options.maxResults || 10;
        this.apiUrl = options.apiUrl || '/api/search/autocomplete';

        this.debounceTimer = null;
        this.abortController = null; // Əvvəlki sorğunu ləğv etmək üçün
        this.cache = new Map();      // Client-side cache

        this.init();
    }

    init() {
        this.input.addEventListener('input', (e) => this.onInput(e));
        this.input.addEventListener('keydown', (e) => this.onKeydown(e));
        document.addEventListener('click', (e) => this.onClickOutside(e));
    }

    /**
     * Debounce — istifadəçi yazmağı dayandırdıqdan sonra sorğu göndərilir.
     * Hər yeni input əvvəlki timer-i ləğv edir.
     */
    onInput(event) {
        const query = event.target.value.trim();

        // Timer-i sıfırlayırıq
        clearTimeout(this.debounceTimer);

        if (query.length < this.minChars) {
            this.hideResults();
            return;
        }

        // Client cache-dən yoxlayırıq
        if (this.cache.has(query)) {
            this.showResults(this.cache.get(query));
            return;
        }

        // Debounce: 300ms gözlə, sonra göndər
        this.debounceTimer = setTimeout(() => {
            this.fetchSuggestions(query);
        }, this.debounceDelay);
    }

    async fetchSuggestions(query) {
        // Əvvəlki sorğunu ləğv edirik (flight-da olan)
        if (this.abortController) {
            this.abortController.abort();
        }
        this.abortController = new AbortController();

        try {
            const response = await fetch(
                `${this.apiUrl}?q=${encodeURIComponent(query)}&limit=${this.maxResults}`,
                {
                    signal: this.abortController.signal,
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                }
            );

            if (!response.ok) return;

            const data = await response.json();

            // Cache-ə əlavə edirik (max 100 entry)
            if (this.cache.size > 100) {
                const firstKey = this.cache.keys().next().value;
                this.cache.delete(firstKey);
            }
            this.cache.set(query, data.suggestions);

            this.showResults(data.suggestions);
        } catch (error) {
            if (error.name !== 'AbortError') {
                console.error('Autocomplete xətası:', error);
            }
        }
    }

    showResults(suggestions) {
        if (suggestions.length === 0) {
            this.hideResults();
            return;
        }

        this.resultsContainer.innerHTML = suggestions.map((item, index) => `
            <div class="autocomplete-item ${index === 0 ? 'active' : ''}"
                 data-value="${this.escapeHtml(item.text)}"
                 data-url="${item.url || ''}">
                <span class="autocomplete-icon">${this.getIcon(item.type)}</span>
                <div class="autocomplete-content">
                    <span class="autocomplete-text">${this.highlightMatch(item.text, this.input.value)}</span>
                    ${item.subtitle ? `<span class="autocomplete-subtitle">${this.escapeHtml(item.subtitle)}</span>` : ''}
                </div>
                ${item.image ? `<img class="autocomplete-image" src="${item.image}" alt="">` : ''}
            </div>
        `).join('');

        this.resultsContainer.style.display = 'block';
        this.addItemListeners();
    }

    /**
     * Axtarış mətninə uyğun hissəni bold edir.
     * "lap" axtarışı "Laptop" sözündə "Lap" hissəsini bold edir.
     */
    highlightMatch(text, query) {
        const regex = new RegExp(`(${this.escapeRegex(query)})`, 'gi');
        return this.escapeHtml(text).replace(regex, '<strong>$1</strong>');
    }

    /**
     * Keyboard navigation (yuxarı/aşağı ox, Enter, Escape)
     */
    onKeydown(event) {
        const items = this.resultsContainer.querySelectorAll('.autocomplete-item');
        const activeItem = this.resultsContainer.querySelector('.autocomplete-item.active');
        let activeIndex = Array.from(items).indexOf(activeItem);

        switch (event.key) {
            case 'ArrowDown':
                event.preventDefault();
                activeIndex = Math.min(activeIndex + 1, items.length - 1);
                this.setActiveItem(items, activeIndex);
                break;
            case 'ArrowUp':
                event.preventDefault();
                activeIndex = Math.max(activeIndex - 1, 0);
                this.setActiveItem(items, activeIndex);
                break;
            case 'Enter':
                event.preventDefault();
                if (activeItem) {
                    this.selectItem(activeItem);
                }
                break;
            case 'Escape':
                this.hideResults();
                break;
        }
    }

    selectItem(item) {
        const value = item.dataset.value;
        const url = item.dataset.url;

        this.input.value = value;
        this.hideResults();

        if (url) {
            window.location.href = url;
        } else {
            // Form submit
            this.input.closest('form')?.submit();
        }
    }

    hideResults() {
        this.resultsContainer.style.display = 'none';
        this.resultsContainer.innerHTML = '';
    }

    setActiveItem(items, index) {
        items.forEach(item => item.classList.remove('active'));
        items[index]?.classList.add('active');
    }

    addItemListeners() {
        this.resultsContainer.querySelectorAll('.autocomplete-item').forEach(item => {
            item.addEventListener('click', () => this.selectItem(item));
        });
    }

    onClickOutside(event) {
        if (!this.input.contains(event.target) && !this.resultsContainer.contains(event.target)) {
            this.hideResults();
        }
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    escapeRegex(string) {
        return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    getIcon(type) {
        const icons = {
            product: '🛍️',
            category: '📁',
            brand: '🏷️',
            history: '🕐',
            trending: '🔥',
        };
        return icons[type] || '🔍';
    }
}

// İstifadə:
// new SearchAutocomplete('#search-input', '#search-results', {
//     debounceDelay: 300,
//     minChars: 2,
//     maxResults: 8,
//     apiUrl: '/api/search/autocomplete',
// });
```

---

## 2. Trie Data Structure

Trie (prefix tree) autocomplete üçün ən optimal data structure-dur. Hər node bir hərf təmsil edir.

*Bu kod prefix axtarışı üçün Trie data strukturunun node sinifini göstərir:*

```php
// app/DataStructures/TrieNode.php
<?php

namespace App\DataStructures;

class TrieNode
{
    /** @var array<string, TrieNode> */
    public array $children = [];

    public bool $isEndOfWord = false;

    /** Axtarış populariteti (ranking üçün) */
    public int $weight = 0;

    /** Son tam söz (node end-of-word olanda) */
    public ?string $fullWord = null;

    /** Əlavə metadata */
    public ?array $metadata = null;
}
```

*Bu kod insert, prefix axtarışı (DFS) və silmə əməliyyatlarını dəstəkləyən Trie implementasiyasını göstərir:*

```php
// app/DataStructures/Trie.php
<?php

namespace App\DataStructures;

class Trie
{
    private TrieNode $root;

    public function __construct()
    {
        $this->root = new TrieNode();
    }

    /**
     * Sözü Trie-yə əlavə edir.
     * Zaman mürəkkəbliyi: O(m) — m = sözün uzunluğu
     */
    public function insert(string $word, int $weight = 1, ?array $metadata = null): void
    {
        $node = $this->root;
        $lowerWord = mb_strtolower($word);

        for ($i = 0; $i < mb_strlen($lowerWord); $i++) {
            $char = mb_substr($lowerWord, $i, 1);

            if (!isset($node->children[$char])) {
                $node->children[$char] = new TrieNode();
            }

            $node = $node->children[$char];
        }

        $node->isEndOfWord = true;
        $node->weight = $weight;
        $node->fullWord = $word; // Original case saxlanılır
        $node->metadata = $metadata;
    }

    /**
     * Prefix-ə uyğun bütün sözləri qaytarır.
     * Zaman mürəkkəbliyi: O(p + n) — p = prefix uzunluğu, n = nəticə sayı
     */
    public function search(string $prefix, int $limit = 10): array
    {
        $node = $this->root;
        $lowerPrefix = mb_strtolower($prefix);

        // Prefix-in sonuna qədər gedirik
        for ($i = 0; $i < mb_strlen($lowerPrefix); $i++) {
            $char = mb_substr($lowerPrefix, $i, 1);

            if (!isset($node->children[$char])) {
                return []; // Prefix tapılmadı
            }

            $node = $node->children[$char];
        }

        // Bu node-dan aşağı bütün sözləri tapırıq
        $results = [];
        $this->collectWords($node, $results);

        // Weight-ə görə sıralayırıq (ən populyar yuxarıda)
        usort($results, fn ($a, $b) => $b['weight'] - $a['weight']);

        return array_slice($results, 0, $limit);
    }

    /**
     * Node-dan aşağı bütün sözləri toplayır (DFS).
     */
    private function collectWords(TrieNode $node, array &$results): void
    {
        if ($node->isEndOfWord) {
            $results[] = [
                'text'     => $node->fullWord,
                'weight'   => $node->weight,
                'metadata' => $node->metadata,
            ];
        }

        foreach ($node->children as $child) {
            $this->collectWords($child, $results);
        }
    }

    /**
     * Sözü Trie-dən silir.
     */
    public function delete(string $word): bool
    {
        return $this->deleteHelper($this->root, mb_strtolower($word), 0);
    }

    private function deleteHelper(TrieNode $node, string $word, int $depth): bool
    {
        if ($depth === mb_strlen($word)) {
            if (!$node->isEndOfWord) {
                return false;
            }
            $node->isEndOfWord = false;
            $node->fullWord = null;
            return count($node->children) === 0;
        }

        $char = mb_substr($word, $depth, 1);
        if (!isset($node->children[$char])) {
            return false;
        }

        $shouldDeleteChild = $this->deleteHelper($node->children[$char], $word, $depth + 1);

        if ($shouldDeleteChild) {
            unset($node->children[$char]);
            return count($node->children) === 0 && !$node->isEndOfWord;
        }

        return false;
    }
}
```

### Trie-nin istifadəsi

*Trie-nin istifadəsi üçün kod nümunəsi:*
```php
// Trie yaradırıq və sözlər əlavə edirik
$trie = new Trie();

$trie->insert('iPhone 15 Pro', weight: 1000, metadata: ['type' => 'product', 'id' => 1]);
$trie->insert('iPhone 15', weight: 900, metadata: ['type' => 'product', 'id' => 2]);
$trie->insert('iPhone 14', weight: 500, metadata: ['type' => 'product', 'id' => 3]);
$trie->insert('iPad Pro', weight: 700, metadata: ['type' => 'product', 'id' => 4]);
$trie->insert('iMac', weight: 300, metadata: ['type' => 'product', 'id' => 5]);

// Axtarış
$results = $trie->search('iph', limit: 5);
// Nəticə: iPhone 15 Pro (1000), iPhone 15 (900), iPhone 14 (500)

$results = $trie->search('ip', limit: 5);
// Nəticə: iPhone 15 Pro (1000), iPhone 15 (900), iPad Pro (700), iPhone 14 (500)
```

---

## 3. Redis-based Autocomplete (Sorted Set)

Production-da Trie memory-də saxlamaq çətin ola bilər (restart-da itir). Redis Sorted Set istifadə edərək sürətli və persistent autocomplete yarada bilərik.

*Bu kod Redis Sorted Set ilə hər prefix üçün ayrı key saxlayan, score-a görə sıralayan autocomplete service-ini göstərir:*

```php
// app/Services/RedisAutocompleteService.php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;

class RedisAutocompleteService
{
    private const PREFIX = 'autocomplete:';
    private const POPULAR_PREFIX = 'autocomplete:popular:';

    /**
     * Sözü autocomplete index-ə əlavə edir.
     *
     * Redis Sorted Set istifadə edirik:
     * - Hər prefix üçün ayrı sorted set
     * - Score = popularitet/weight
     * - Member = tam söz
     */
    public function index(string $term, float $score = 1.0, string $category = 'general'): void
    {
        $normalized = mb_strtolower(trim($term));
        $key = self::PREFIX . $category;

        // Tam sözü əlavə edirik
        Redis::zadd($key, $score, $normalized . '|' . $term);

        // Hər prefix üçün əlavə edirik (sürətli axtarış üçün)
        // "iPhone" -> "i", "ip", "iph", "ipho", "iphon", "iphone"
        $prefixKey = self::PREFIX . $category . ':prefix';
        for ($i = 1; $i <= mb_strlen($normalized); $i++) {
            $prefix = mb_substr($normalized, 0, $i);
            Redis::zadd($prefixKey . ':' . $prefix, $score, $normalized . '|' . $term);
        }
    }

    /**
     * Prefix-ə uyğun təklifləri qaytarır.
     * Redis ZREVRANGEBYSCORE — ən yüksək score-dan aşağı.
     */
    public function search(string $prefix, int $limit = 10, string $category = 'general'): array
    {
        $normalized = mb_strtolower(trim($prefix));
        $prefixKey = self::PREFIX . $category . ':prefix:' . $normalized;

        // Score-a görə ən yüksəkdən aşağı sıralayırıq
        $results = Redis::zrevrange($prefixKey, 0, $limit - 1, 'WITHSCORES');

        $suggestions = [];
        foreach ($results as $member => $score) {
            // "iphone 15 pro|iPhone 15 Pro" -> ayrılır
            [$normalized, $original] = explode('|', $member, 2);
            $suggestions[] = [
                'text'  => $original,
                'score' => (float) $score,
            ];
        }

        return $suggestions;
    }

    /**
     * Toplu indexləmə — çox sayda element əlavə edir.
     * Pipeline istifadə edirik — performans üçün.
     */
    public function bulkIndex(array $items, string $category = 'general'): void
    {
        $pipeline = Redis::pipeline();

        foreach ($items as $item) {
            $term = $item['term'];
            $score = $item['score'] ?? 1.0;
            $normalized = mb_strtolower(trim($term));

            $key = self::PREFIX . $category;
            $pipeline->zadd($key, $score, $normalized . '|' . $term);

            $prefixKey = self::PREFIX . $category . ':prefix';
            for ($i = 1; $i <= mb_strlen($normalized); $i++) {
                $prefix = mb_substr($normalized, 0, $i);
                $pipeline->zadd($prefixKey . ':' . $prefix, $score, $normalized . '|' . $term);
            }
        }

        $pipeline->execute();
    }

    /**
     * Axtarış populyarlığını artırır.
     * İstifadəçi bir təklifi seçəndə çağırılır.
     */
    public function boost(string $term, string $category = 'general', float $boostAmount = 1.0): void
    {
        $normalized = mb_strtolower(trim($term));
        $member = $normalized . '|' . $term;

        $key = self::PREFIX . $category;
        Redis::zincrby($key, $boostAmount, $member);

        // Prefix key-ləri də yeniləyirik
        $prefixKey = self::PREFIX . $category . ':prefix';
        for ($i = 1; $i <= mb_strlen($normalized); $i++) {
            $prefix = mb_substr($normalized, 0, $i);
            Redis::zincrby($prefixKey . ':' . $prefix, $boostAmount, $member);
        }
    }

    /**
     * Elementi index-dən silir.
     */
    public function remove(string $term, string $category = 'general'): void
    {
        $normalized = mb_strtolower(trim($term));
        $member = $normalized . '|' . $term;

        $key = self::PREFIX . $category;
        Redis::zrem($key, $member);

        $prefixKey = self::PREFIX . $category . ':prefix';
        for ($i = 1; $i <= mb_strlen($normalized); $i++) {
            $prefix = mb_substr($normalized, 0, $i);
            Redis::zrem($prefixKey . ':' . $prefix, $member);
        }
    }

    /**
     * Populyar axtarışları saxlayır (trending).
     */
    public function recordSearch(string $query): void
    {
        $normalized = mb_strtolower(trim($query));
        $key = self::POPULAR_PREFIX . date('Y-m-d');

        Redis::zincrby($key, 1, $normalized);
        Redis::expire($key, 86400 * 7); // 7 gün saxla
    }

    /**
     * Trending axtarışları qaytarır.
     */
    public function getTrending(int $limit = 10): array
    {
        $key = self::POPULAR_PREFIX . date('Y-m-d');
        return Redis::zrevrange($key, 0, $limit - 1, 'WITHSCORES');
    }

    /**
     * Bütün index-i təmizləyir və yenidən qurur.
     */
    public function rebuildIndex(string $category = 'general'): void
    {
        // Köhnə key-ləri silirik
        $keys = Redis::keys(self::PREFIX . $category . '*');
        if (!empty($keys)) {
            Redis::del($keys);
        }
    }
}
```

### Redis Autocomplete-i dolduran Command

*Bu kod məhsul, kateqoriya və brendləri Redis autocomplete index-inə toplu şəkildə yükləyən artıq komandanı göstərir:*

```php
// app/Console/Commands/BuildAutocompleteIndex.php
<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use App\Services\RedisAutocompleteService;
use Illuminate\Console\Command;

class BuildAutocompleteIndex extends Command
{
    protected $signature = 'autocomplete:build {--category=general}';
    protected $description = 'Autocomplete index-i Redis-də yenidən qurur';

    public function handle(RedisAutocompleteService $autocomplete): void
    {
        $category = $this->option('category');

        $this->info("Index təmizlənir: {$category}");
        $autocomplete->rebuildIndex($category);

        // Məhsulları indexləyirik
        $this->info('Məhsullar indexlənir...');
        Product::where('is_active', true)->chunk(1000, function ($products) use ($autocomplete) {
            $items = $products->map(fn ($p) => [
                'term'  => $p->name,
                'score' => $p->popularity_score ?? $p->sales_count ?? 1,
            ])->toArray();

            $autocomplete->bulkIndex($items, 'products');
        });

        // Kateqoriyaları indexləyirik
        $this->info('Kateqoriyalar indexlənir...');
        Category::all()->each(function ($cat) use ($autocomplete) {
            $autocomplete->index($cat->name, score: 500, category: 'categories');
        });

        // Brendləri indexləyirik
        $this->info('Brendlər indexlənir...');
        Brand::all()->each(function ($brand) use ($autocomplete) {
            $autocomplete->index($brand->name, score: 300, category: 'brands');
        });

        $this->info('Autocomplete index uğurla quruldu!');
    }
}
```

---

## 4. Elasticsearch-based Autocomplete

Böyük data-setlər üçün Elasticsearch ən yaxşı həlldir. Fuzzy matching, typo tolerance, relevance scoring dəstəkləyir.

*Bu kod edge_ngram analyzer, completion suggester və fuzzy matching ilə Elasticsearch autocomplete service-ini göstərir:*

```php
// app/Services/ElasticsearchAutocompleteService.php
<?php

namespace App\Services;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;

class ElasticsearchAutocompleteService
{
    private Client $client;
    private string $index = 'autocomplete';

    public function __construct()
    {
        $this->client = ClientBuilder::create()
            ->setHosts([config('services.elasticsearch.host', 'localhost:9200')])
            ->build();
    }

    /**
     * Index yaradır — xüsusi analyzer-lərlə.
     * edge_ngram: "iPhone" -> "i", "ip", "iph", "ipho", "iphon", "iphone"
     * Bu prefix axtarışını çox sürətli edir.
     */
    public function createIndex(): void
    {
        $params = [
            'index' => $this->index,
            'body'  => [
                'settings' => [
                    'number_of_shards'   => 1,
                    'number_of_replicas' => 0,
                    'analysis' => [
                        'filter' => [
                            'autocomplete_filter' => [
                                'type'     => 'edge_ngram',
                                'min_gram' => 1,
                                'max_gram' => 20,
                            ],
                        ],
                        'analyzer' => [
                            // Indexləmə zamanı istifadə olunur
                            'autocomplete_analyzer' => [
                                'type'      => 'custom',
                                'tokenizer' => 'standard',
                                'filter'    => ['lowercase', 'autocomplete_filter'],
                            ],
                            // Axtarış zamanı istifadə olunur
                            'autocomplete_search_analyzer' => [
                                'type'      => 'custom',
                                'tokenizer' => 'standard',
                                'filter'    => ['lowercase'],
                            ],
                        ],
                    ],
                ],
                'mappings' => [
                    'properties' => [
                        'name' => [
                            'type'            => 'text',
                            'analyzer'        => 'autocomplete_analyzer',
                            'search_analyzer' => 'autocomplete_search_analyzer',
                        ],
                        'name_suggest' => [
                            'type' => 'completion',  // Elasticsearch-in xüsusi suggest type-ı
                            'contexts' => [
                                [
                                    'name' => 'category',
                                    'type' => 'category',
                                ],
                            ],
                        ],
                        'category'   => ['type' => 'keyword'],
                        'popularity' => ['type' => 'integer'],
                        'image_url'  => ['type' => 'keyword'],
                        'url'        => ['type' => 'keyword'],
                    ],
                ],
            ],
        ];

        $this->client->indices()->create($params);
    }

    /**
     * Sənəd əlavə edir / yeniləyir.
     */
    public function indexDocument(string $id, array $data): void
    {
        $this->client->index([
            'index' => $this->index,
            'id'    => $id,
            'body'  => [
                'name'         => $data['name'],
                'name_suggest' => [
                    'input'    => $this->generateInputs($data['name']),
                    'weight'   => $data['popularity'] ?? 1,
                    'contexts' => [
                        'category' => [$data['category'] ?? 'general'],
                    ],
                ],
                'category'   => $data['category'] ?? 'general',
                'popularity' => $data['popularity'] ?? 1,
                'image_url'  => $data['image_url'] ?? null,
                'url'        => $data['url'] ?? null,
            ],
        ]);
    }

    /**
     * Autocomplete axtarışı — Completion Suggester istifadə edir.
     * Bu Elasticsearch-in ən sürətli axtarış üsuludur (FST-based).
     */
    public function suggest(string $prefix, int $limit = 10, ?string $category = null): array
    {
        $suggestBody = [
            'prefix'     => $prefix,
            'completion' => [
                'field'           => 'name_suggest',
                'size'            => $limit,
                'skip_duplicates' => true,
                'fuzzy'           => [
                    'fuzziness' => 'AUTO',  // Typo tolerance
                ],
            ],
        ];

        // Kateqoriya filter
        if ($category) {
            $suggestBody['completion']['contexts'] = [
                'category' => [$category],
            ];
        }

        $response = $this->client->search([
            'index' => $this->index,
            'body'  => [
                'suggest' => [
                    'autocomplete' => $suggestBody,
                ],
            ],
        ]);

        $suggestions = [];
        foreach ($response['suggest']['autocomplete'][0]['options'] as $option) {
            $suggestions[] = [
                'text'       => $option['_source']['name'],
                'category'   => $option['_source']['category'],
                'popularity' => $option['_source']['popularity'],
                'image_url'  => $option['_source']['image_url'],
                'url'        => $option['_source']['url'],
                'score'      => $option['_score'],
            ];
        }

        return $suggestions;
    }

    /**
     * Multi-match axtarış — daha mürəkkəb, amma daha güclü.
     * Fuzzy matching, boosting, highlighting dəstəkləyir.
     */
    public function searchWithRelevance(string $query, int $limit = 10): array
    {
        $response = $this->client->search([
            'index' => $this->index,
            'body'  => [
                'size'  => $limit,
                'query' => [
                    'function_score' => [
                        'query' => [
                            'bool' => [
                                'should' => [
                                    // Prefix match (ən yüksək boost)
                                    [
                                        'match_phrase_prefix' => [
                                            'name' => [
                                                'query' => $query,
                                                'boost' => 3,
                                            ],
                                        ],
                                    ],
                                    // Fuzzy match (typo tolerance)
                                    [
                                        'match' => [
                                            'name' => [
                                                'query'     => $query,
                                                'fuzziness' => 'AUTO',
                                                'boost'     => 1,
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        // Populyarlığa görə boost
                        'functions' => [
                            [
                                'field_value_factor' => [
                                    'field'    => 'popularity',
                                    'modifier' => 'log1p',
                                    'factor'   => 0.5,
                                ],
                            ],
                        ],
                        'score_mode' => 'sum',
                        'boost_mode' => 'multiply',
                    ],
                ],
                'highlight' => [
                    'fields' => [
                        'name' => [
                            'pre_tags'  => ['<strong>'],
                            'post_tags' => ['</strong>'],
                        ],
                    ],
                ],
            ],
        ]);

        return array_map(fn ($hit) => [
            'text'       => $hit['_source']['name'],
            'highlighted'=> $hit['highlight']['name'][0] ?? $hit['_source']['name'],
            'category'   => $hit['_source']['category'],
            'score'      => $hit['_score'],
            'url'        => $hit['_source']['url'],
        ], $response['hits']['hits']);
    }

    /**
     * Input variantları generasiya edir.
     * "iPhone 15 Pro" -> ["iPhone 15 Pro", "15 Pro", "Pro", "iPhone", "15"]
     */
    private function generateInputs(string $text): array
    {
        $words = explode(' ', $text);
        $inputs = [$text]; // Tam söz

        // Hər sözlə başlayan variantlar
        for ($i = 1; $i < count($words); $i++) {
            $inputs[] = implode(' ', array_slice($words, $i));
        }

        return $inputs;
    }

    /**
     * Toplu indexləmə (bulk API).
     */
    public function bulkIndex(array $documents): void
    {
        $params = ['body' => []];

        foreach ($documents as $doc) {
            $params['body'][] = [
                'index' => [
                    '_index' => $this->index,
                    '_id'    => $doc['id'],
                ],
            ];
            $params['body'][] = [
                'name'         => $doc['name'],
                'name_suggest' => [
                    'input'  => $this->generateInputs($doc['name']),
                    'weight' => $doc['popularity'] ?? 1,
                ],
                'category'   => $doc['category'] ?? 'general',
                'popularity' => $doc['popularity'] ?? 1,
                'image_url'  => $doc['image_url'] ?? null,
                'url'        => $doc['url'] ?? null,
            ];
        }

        if (!empty($params['body'])) {
            $this->client->bulk($params);
        }
    }
}
```

---

## 5. Laravel Controller (Unified Search API)

*Bu kod Redis cache, Redis autocomplete və Elasticsearch-i birləşdirən vahid axtarış controller-ini göstərir:*

```php
// app/Http/Controllers/SearchController.php
<?php

namespace App\Http\Controllers;

use App\Services\RedisAutocompleteService;
use App\Services\ElasticsearchAutocompleteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SearchController extends Controller
{
    public function __construct(
        private RedisAutocompleteService $redisAutocomplete,
        private ElasticsearchAutocompleteService $esAutocomplete
    ) {}

    /**
     * Autocomplete endpoint — ən sürətli cavab verməlidir.
     */
    public function autocomplete(Request $request): JsonResponse
    {
        $query = $request->query('q', '');
        $limit = min((int) $request->query('limit', 8), 20);
        $category = $request->query('category');

        if (mb_strlen($query) < 2) {
            return response()->json(['suggestions' => []]);
        }

        // Cache layer — eyni axtarışlar üçün
        $cacheKey = "autocomplete:" . md5("{$query}:{$limit}:{$category}");

        $suggestions = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($query, $limit, $category) {
            return $this->getSuggestions($query, $limit, $category);
        });

        // Axtarışı qeyd edirik (trending üçün)
        $this->redisAutocomplete->recordSearch($query);

        return response()->json([
            'suggestions' => $suggestions,
            'query'       => $query,
        ]);
    }

    /**
     * Suggestion-ları alır — əvvəl Redis, sonra Elasticsearch.
     */
    private function getSuggestions(string $query, int $limit, ?string $category): array
    {
        $suggestions = [];

        // 1. Redis-dən sürətli nəticələr (< 1ms)
        $redisSuggestions = $this->redisAutocomplete->search($query, $limit, $category ?? 'products');

        foreach ($redisSuggestions as $item) {
            $suggestions[] = [
                'text'   => $item['text'],
                'type'   => 'product',
                'score'  => $item['score'],
                'source' => 'redis',
            ];
        }

        // 2. Əgər Redis-dən kifayət qədər nəticə gəlməyibsə, Elasticsearch-dən alırıq
        if (count($suggestions) < $limit) {
            $remaining = $limit - count($suggestions);
            $esSuggestions = $this->esAutocomplete->suggest($query, $remaining, $category);

            foreach ($esSuggestions as $item) {
                // Dublikatları yoxlayırıq
                $exists = collect($suggestions)->contains('text', $item['text']);
                if (!$exists) {
                    $suggestions[] = [
                        'text'      => $item['text'],
                        'type'      => $item['category'] ?? 'product',
                        'score'     => $item['score'],
                        'image_url' => $item['image_url'] ?? null,
                        'url'       => $item['url'] ?? null,
                        'source'    => 'elasticsearch',
                    ];
                }
            }
        }

        // 3. Trending axtarışlar əlavə edirik (əgər yer varsa)
        if (count($suggestions) < $limit && mb_strlen($query) <= 3) {
            $trending = $this->redisAutocomplete->getTrending(3);
            foreach ($trending as $term => $count) {
                if (count($suggestions) >= $limit) break;
                if (str_starts_with($term, mb_strtolower($query))) {
                    $suggestions[] = [
                        'text'  => $term,
                        'type'  => 'trending',
                        'score' => (float) $count,
                    ];
                }
            }
        }

        return $suggestions;
    }

    /**
     * Tam axtarış (autocomplete deyil, Enter basıldıqdan sonra).
     * Daha ətraflı nəticələr qaytarır.
     */
    public function fullSearch(Request $request): JsonResponse
    {
        $query = $request->query('q', '');
        $page = (int) $request->query('page', 1);
        $perPage = min((int) $request->query('per_page', 20), 50);

        if (empty($query)) {
            return response()->json(['results' => [], 'total' => 0]);
        }

        $results = $this->esAutocomplete->searchWithRelevance($query, $perPage);

        return response()->json([
            'results' => $results,
            'query'   => $query,
            'total'   => count($results),
        ]);
    }
}
```

### Routes

*Routes üçün kod nümunəsi:*
```php
// routes/api.php
Route::prefix('search')->group(function () {
    Route::get('/autocomplete', [SearchController::class, 'autocomplete']);
    Route::get('/full', [SearchController::class, 'fullSearch']);
});
```

---

## 6. Caching Strategiyası

*6. Caching Strategiyası üçün kod nümunəsi:*
```php
// app/Services/SearchCacheService.php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class SearchCacheService
{
    /**
     * Multi-layer caching strategiyası:
     *
     * Layer 1: Application Cache (in-memory, request scope)
     * Layer 2: Redis Cache (5 dəqiqə TTL)
     * Layer 3: Redis Autocomplete Index (persistent)
     * Layer 4: Elasticsearch (persistent, full-text)
     */

    private array $requestCache = []; // Layer 1

    public function get(string $query, int $limit): ?array
    {
        $key = $this->cacheKey($query, $limit);

        // Layer 1: Request scope cache
        if (isset($this->requestCache[$key])) {
            return $this->requestCache[$key];
        }

        // Layer 2: Redis cache
        $cached = Cache::get($key);
        if ($cached !== null) {
            $this->requestCache[$key] = $cached;
            return $cached;
        }

        return null;
    }

    public function set(string $query, int $limit, array $results): void
    {
        $key = $this->cacheKey($query, $limit);

        // Layer 1
        $this->requestCache[$key] = $results;

        // Layer 2: Qısa TTL (populyar axtarışlar tez-tez dəyişə bilər)
        $ttl = $this->calculateTTL($query);
        Cache::put($key, $results, $ttl);
    }

    /**
     * TTL-i axtarışın uzunluğuna görə təyin edirik.
     * Qısa prefix-lər daha çox dəyişir, ona görə TTL qısadır.
     */
    private function calculateTTL(string $query): int
    {
        $length = mb_strlen($query);

        return match (true) {
            $length <= 2 => 60,         // 1 dəqiqə (çox ümumi)
            $length <= 4 => 300,        // 5 dəqiqə
            $length <= 8 => 900,        // 15 dəqiqə
            default      => 1800,       // 30 dəqiqə (çox spesifik)
        };
    }

    /**
     * Məhsul dəyişəndə əlaqəli cache-ləri təmizləyir.
     */
    public function invalidateForProduct(string $productName): void
    {
        // Prefix-ləri generasiya edirik
        $normalized = mb_strtolower($productName);
        for ($i = 2; $i <= mb_strlen($normalized); $i++) {
            $prefix = mb_substr($normalized, 0, $i);
            $pattern = "autocomplete:{$prefix}:*";
            // Cache tag istifadə edə bilərik (Redis, Memcached)
            Cache::forget($this->cacheKey($prefix, 8));
            Cache::forget($this->cacheKey($prefix, 10));
            Cache::forget($this->cacheKey($prefix, 20));
        }
    }

    private function cacheKey(string $query, int $limit): string
    {
        return "autocomplete:" . md5(mb_strtolower($query) . ":{$limit}");
    }
}
```

---

## 7. Search History (İstifadəçinin Axtarış Tarixçəsi)

*7. Search History (İstifadəçinin Axtarış Tarixçəsi) üçün kod nümunəsi:*
```php
// app/Services/SearchHistoryService.php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;

class SearchHistoryService
{
    private const PREFIX = 'search_history:';
    private const MAX_HISTORY = 20;

    /**
     * İstifadəçinin axtarışını qeyd edir.
     */
    public function record(int $userId, string $query): void
    {
        $key = self::PREFIX . $userId;
        $normalized = trim($query);

        if (empty($normalized)) return;

        // Əgər artıq varsa, silirik (dublikat olmasın)
        Redis::lrem($key, 0, $normalized);

        // Siyahının əvvəlinə əlavə edirik (ən son axtarış birinci)
        Redis::lpush($key, $normalized);

        // Max uzunluğu saxlayırıq
        Redis::ltrim($key, 0, self::MAX_HISTORY - 1);

        // 30 gün sonra avtomatik silinir
        Redis::expire($key, 86400 * 30);
    }

    /**
     * İstifadəçinin axtarış tarixçəsini qaytarır.
     * Prefix-ə uyğun olanları filtrləyir (autocomplete üçün).
     */
    public function getHistory(int $userId, ?string $prefix = null, int $limit = 5): array
    {
        $key = self::PREFIX . $userId;
        $history = Redis::lrange($key, 0, self::MAX_HISTORY - 1);

        if ($prefix) {
            $normalizedPrefix = mb_strtolower(trim($prefix));
            $history = array_filter($history, function ($item) use ($normalizedPrefix) {
                return str_starts_with(mb_strtolower($item), $normalizedPrefix);
            });
        }

        return array_slice(array_values($history), 0, $limit);
    }

    /**
     * Tək bir axtarışı tarixçədən silir.
     */
    public function remove(int $userId, string $query): void
    {
        $key = self::PREFIX . $userId;
        Redis::lrem($key, 0, trim($query));
    }

    /**
     * Bütün tarixçəni silir.
     */
    public function clear(int $userId): void
    {
        Redis::del(self::PREFIX . $userId);
    }
}
```

---

## 8. Performance Optimization

### Product Model-ə Observer

*Product Model-ə Observer üçün kod nümunəsi:*
```php
// app/Observers/ProductObserver.php
<?php

namespace App\Observers;

use App\Models\Product;
use App\Services\RedisAutocompleteService;
use App\Services\SearchCacheService;

class ProductObserver
{
    public function __construct(
        private RedisAutocompleteService $autocomplete,
        private SearchCacheService $cacheService
    ) {}

    /**
     * Yeni məhsul yaradıldıqda autocomplete index-ə əlavə edir.
     */
    public function created(Product $product): void
    {
        if ($product->is_active) {
            $this->autocomplete->index(
                $product->name,
                score: $product->popularity_score ?? 1,
                category: 'products'
            );
        }
    }

    /**
     * Məhsul yeniləndikdə index-i yeniləyir.
     */
    public function updated(Product $product): void
    {
        // Köhnə adı silirik
        if ($product->isDirty('name')) {
            $this->autocomplete->remove($product->getOriginal('name'), 'products');
        }

        // Yenisini əlavə edirik
        if ($product->is_active) {
            $this->autocomplete->index(
                $product->name,
                score: $product->popularity_score ?? 1,
                category: 'products'
            );
        } else {
            $this->autocomplete->remove($product->name, 'products');
        }

        // Cache-i təmizləyirik
        $this->cacheService->invalidateForProduct($product->name);
        if ($product->isDirty('name')) {
            $this->cacheService->invalidateForProduct($product->getOriginal('name'));
        }
    }

    /**
     * Məhsul silinəndə index-dən çıxarır.
     */
    public function deleted(Product $product): void
    {
        $this->autocomplete->remove($product->name, 'products');
        $this->cacheService->invalidateForProduct($product->name);
    }
}
```

### Rate Limiting (Autocomplete üçün)

*Rate Limiting (Autocomplete üçün) üçün kod nümunəsi:*
```php
// app/Http/Middleware/AutocompleteRateLimiter.php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class AutocompleteRateLimiter
{
    /**
     * Autocomplete üçün xüsusi rate limiter.
     * Normal API rate limit-dən ayrıdır çünki autocomplete çox tez-tez çağırılır.
     */
    public function handle(Request $request, Closure $next)
    {
        $key = 'autocomplete:' . ($request->user()?->id ?? $request->ip());

        // Saniyədə 10 sorğu (debounce ilə belə kifayət etməlidir)
        if (RateLimiter::tooManyAttempts($key, 10)) {
            return response()->json([
                'error' => 'Çox sayda sorğu göndərdiniz',
                'suggestions' => [],
            ], 429);
        }

        RateLimiter::hit($key, 1); // 1 saniyə window

        return $next($request);
    }
}
```

---

## 9. Test Nümunələri

*9. Test Nümunələri üçün kod nümunəsi:*
```php
// tests/Feature/SearchAutocompleteTest.php
<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Services\RedisAutocompleteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class SearchAutocompleteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Redis::flushall();
    }

    public function test_redis_autocomplete_returns_results(): void
    {
        $service = app(RedisAutocompleteService::class);

        $service->index('iPhone 15 Pro', score: 100, category: 'products');
        $service->index('iPhone 15', score: 90, category: 'products');
        $service->index('iPad Pro', score: 80, category: 'products');

        $results = $service->search('iph', limit: 5, category: 'products');

        $this->assertCount(2, $results);
        $this->assertEquals('iPhone 15 Pro', $results[0]['text']); // Daha yüksək score
        $this->assertEquals('iPhone 15', $results[1]['text']);
    }

    public function test_autocomplete_api_endpoint(): void
    {
        $service = app(RedisAutocompleteService::class);
        $service->index('Samsung Galaxy S24', score: 50, category: 'products');
        $service->index('Samsung Galaxy S23', score: 40, category: 'products');

        $response = $this->getJson('/api/search/autocomplete?q=sam&limit=5');

        $response->assertOk();
        $response->assertJsonStructure([
            'suggestions' => [['text', 'type', 'score']],
            'query',
        ]);
    }

    public function test_boost_increases_score(): void
    {
        $service = app(RedisAutocompleteService::class);

        $service->index('Product A', score: 10, category: 'products');
        $service->index('Product B', score: 10, category: 'products');

        // Product B-ni boost edirik
        $service->boost('Product B', 'products', boostAmount: 50);

        $results = $service->search('prod', limit: 5, category: 'products');

        // Product B birinci olmalıdır
        $this->assertEquals('Product B', $results[0]['text']);
    }

    public function test_trie_search(): void
    {
        $trie = new \App\DataStructures\Trie();

        $trie->insert('apple', weight: 100);
        $trie->insert('application', weight: 50);
        $trie->insert('banana', weight: 80);

        $results = $trie->search('app');
        $this->assertCount(2, $results);
        $this->assertEquals('apple', $results[0]['text']); // Daha yüksək weight

        $results = $trie->search('ban');
        $this->assertCount(1, $results);
        $this->assertEquals('banana', $results[0]['text']);

        $results = $trie->search('xyz');
        $this->assertCount(0, $results);
    }

    public function test_search_history(): void
    {
        $historyService = app(\App\Services\SearchHistoryService::class);

        $historyService->record(1, 'iPhone');
        $historyService->record(1, 'Samsung');
        $historyService->record(1, 'iPad');

        $history = $historyService->getHistory(1);
        $this->assertCount(3, $history);
        $this->assertEquals('iPad', $history[0]); // Son axtarış birinci

        // Prefix ilə filter
        $filtered = $historyService->getHistory(1, prefix: 'i');
        $this->assertCount(2, $filtered); // iPhone, iPad
    }

    public function test_debounce_rate_limiting(): void
    {
        $service = app(RedisAutocompleteService::class);
        $service->index('Test Product', score: 10, category: 'products');

        // 10 sorğu göndəririk (limit daxilində)
        for ($i = 0; $i < 10; $i++) {
            $this->getJson('/api/search/autocomplete?q=test')->assertOk();
        }

        // 11-ci sorğu rate limit-ə düşməlidir
        $this->getJson('/api/search/autocomplete?q=test')->assertStatus(429);
    }
}
```

---

## 10. Arxitektura Xülasəsi

```
İstifadəçi Input → Debounce (300ms) → API Request
                                          │
                                    ┌─────▼─────┐
                                    │  Rate      │
                                    │  Limiter   │
                                    └─────┬─────┘
                                          │
                                    ┌─────▼─────┐
                                    │  Cache     │
                                    │  Layer     │──── HIT → Response
                                    └─────┬─────┘
                                          │ MISS
                                    ┌─────▼──────┐
                                    │  Redis     │
                                    │  Sorted    │──── Nəticə var → Response
                                    │  Set       │
                                    └─────┬──────┘
                                          │ Kifayət deyil
                                    ┌─────▼──────┐
                                    │  Elastic   │
                                    │  search    │──── Fuzzy match → Response
                                    └────────────┘
```

---

## Interview Sualları və Cavablar

**S: Debounce nədir və niyə lazımdır?**
C: Debounce istifadəçi yazmağı dayandırdıqdan sonra müəyyən müddət (məs. 300ms) gözləyib sorğu göndərmə texnikasıdır. Əgər debounce olmazsa, "iPhone" yazmaq 6 ayrı sorğu göndərər (i, ip, iph, ipho, iphon, iphone). Debounce ilə yalnız 1-2 sorğu göndərilir.

**S: Trie vs Redis Sorted Set — hansını seçərdiniz?**
C: Kiçik data-set üçün (< 100K) Trie yaxşıdır — application memory-də saxlanılır, ən sürətlidir. Böyük data-set üçün (> 100K) Redis Sorted Set daha yaxşıdır — persistent-dir, horizontal scale olur, cluster dəstəkləyir. Ən yaxşı seçim Elasticsearch-dir — fuzzy matching, typo tolerance, relevance scoring hamısını dəstəkləyir.

**S: Autocomplete üçün < 100ms latency necə təmin olunur?**
C: Multi-layer caching (request cache → Redis cache → Redis index → Elasticsearch), edge_ngram analyzer (pre-computed prefix-lər), completion suggester (FST-based, O(prefix_length)), CDN/edge computing (coğrafi yaxınlıq).

**S: Typo tolerance necə işləyir?**
C: Elasticsearch-in fuzzy matching-i Levenshtein distance istifadə edir. `fuzziness: AUTO` 1-2 hərfli dəyişikliklərə icazə verir. Məsələn, "iphne" yazsan "iPhone" nəticəsini alırsan. Redis-də bunu etmək çətindir — ona görə Elasticsearch lazımdır.

**S: Axtarış nəticələrinin relevance-ını necə yaxşılaşdırardınız?**
C: Bir neçə faktor birləşdirilir: text match score (nə qədər uyğundur), popularity/sales score (nə qədər populyardır), recency (nə qədər yenidir), user personalization (istifadəçinin tarixçəsinə əsasən), click-through rate (keçmişdə nə qədər kliklənib). Function score query ilə bu faktorlar çəkilənmiş şəkildə birləşdirilir.

---

## Anti-patterns

**1. Debounce olmadan hər keystroke-da sorğu**
Hər hərfdə 1 HTTP request → 10 hərflik "smartphone" 10 request göndərir. Client-side 300ms debounce + server-side request cancel (AbortController) lazımdır.

**2. LIKE '%query%' ilə autocomplete**
`WHERE name LIKE '%iph%'` — prefix olmayan axtarış full table scan edir. FULLTEXT index, Elasticsearch completion suggester, yaxud Redis Sorted Set daha effektivdir.

**3. Hər sözü ayrıca cache-ləməmək**
"iphone 13" yazarkən "i", "ip", "iph"... hər prefix ayrıca DB sorğusu. Redis prefix cache ilə bir dəfə hesabla, TTL ilə saxla.

**4. Axtarışda PII log-lamaq**
Hər axtarış query-sini log-lamaq → istifadəçinin sağlamlığı, şəxsi məlumatı log-larda qalır. Anonymize et, aggregated statistics saxla.

**5. Elasticsearch-i real-time sync ilə istifadə etmək**
Hər DB yazımında dərhal ES-ə update — yüksək write yükündə ES bottleneck olur. Queue-based async indexing (observer → job → ES) daha scalable-dır.

**6. Autocomplete-də authorization yoxlamamaq**
`/api/search/autocomplete?q=admin` sorğusuna bütün user-lər, admin data-sı da daxil olmaqla, cavab alır. Category/permission filter-i autocomplete-ə də tətbiq edin.

**7. Throttling olmadan autocomplete**
Bot/crawler hər saniyə 100 sorğu göndərir — Redis Sorted Set sorğuları ilə server yükü artır. Autocomplete endpoint-inə `throttle:30,1` (dəqiqədə 30 sorğu) tətbiq edin.
