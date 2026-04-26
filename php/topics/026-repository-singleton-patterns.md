# Repository Pattern və Singleton Pattern (Dərin İzah) (Middle)

## Mündəricat

### Repository Pattern
1. [Repository Pattern Nədir](#repository-pattern-nədir)
2. [Repository vs Active Record](#repository-vs-active-record)
3. [Interface-based Repository](#interface-based-repository)
4. [Base/Abstract Repository](#baseabstract-repository)
5. [Criteria/Specification Pattern](#criteriaspecification-pattern)
6. [Repository ilə Caching](#repository-ilə-caching)
7. [Laravel-də Tam Implementation](#laraveldə-tam-implementation)
8. [Real-World Nümunələr](#real-world-repository-nümunələri)
9. [Repository Unit Test](#repository-unit-test)
10. [Repository Mənfi Cəhətləri](#repository-mənfi-cəhətləri)
11. [Query Object Pattern](#query-object-pattern)

### Singleton Pattern
12. [Singleton Pattern Nədir](#singleton-pattern-nədir)
13. [PHP-də Singleton Implementation](#phpd-singleton-implementation)
14. [Thread Safety](#thread-safety)
15. [Laravel-də Singleton](#laraveldə-singleton)
16. [Singleton Anti-pattern Olaraq](#singleton-anti-pattern-olaraq)
17. [Singleton vs Dependency Injection](#singleton-vs-dependency-injection)
18. [Nə Vaxt İstifadə Etməli](#nə-vaxt-istifadə-etməli)
19. [Testing ilə Problemlər](#testing-ilə-problemlər)
20. [Real-World Singleton Nümunələri](#real-world-singleton-nümunələri)
21. [İntervyu Sualları](#intervyu-sualları)

---

# REPOSITORY PATTERN

## Repository Pattern Nədir

Repository Pattern — data access logic-i business logic-dən ayıran bir design pattern-dir. Repository, data source (database, API, file system) ilə business layer arasında vasitəçi (intermediary) rolunu oynayır. Domain object-lərinə daxil olmaq üçün collection-like interface təqdim edir.

**Əsas prinsip:** Business logic data-nın haradan və necə gəldiyini bilmir və bilməməlidir.

```
┌──────────────┐     ┌──────────────┐     ┌──────────────┐
│  Controller   │────▶│  Repository  │────▶│   Database   │
│  / Service    │◀────│  (Interface) │◀────│   / API      │
└──────────────┘     └──────────────┘     └──────────────┘
     Business             Data Access          Data Source
     Logic                Logic
```

**Repository-nin üstünlükləri:**
1. **Separation of Concerns** — data access logic ayrı layer-da
2. **Testability** — repository-ni mock/stub etmək asandır
3. **Flexibility** — data source dəyişmək asandır (DB -> API, MySQL -> MongoDB)
4. **DRY** — eyni sorğuları təkrar yazmamaq
5. **Single Responsibility** — hər class bir iş görür

*5. **Single Responsibility** — hər class bir iş görür üçün kod nümunəsi:*
```php
<?php

// Repository OLMADAN (controller-da database logic):
class UserController
{
    public function index(Request $request): JsonResponse
    {
        // ❌ Database logic birbaşa controller-da
        $users = User::query()
            ->when($request->search, fn($q, $s) => $q->where('name', 'LIKE', "%$s%"))
            ->when($request->role, fn($q, $r) => $q->where('role', $r))
            ->where('active', true)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($users);
    }
}

// Repository İLƏ:
class UserController
{
    public function __construct(
        private readonly UserRepositoryInterface $repository
    ) {}

    public function index(Request $request): JsonResponse
    {
        // ✅ Controller yalnız business logic bilir
        $users = $this->repository->getActiveUsers(
            search: $request->search,
            role: $request->role,
            perPage: 20
        );

        return response()->json($users);
    }
}
```

---

## Repository vs Active Record

Laravel Eloquent Active Record pattern istifadə edir. Repository ilə Active Record-un fərqlərinə baxaq:

### Active Record (Eloquent)

*Active Record (Eloquent) üçün kod nümunəsi:*
```php
<?php

// Active Record — model özü data access-i idarə edir
class User extends Model
{
    protected $fillable = ['name', 'email', 'role', 'active'];

    // Query scopes — bir növ built-in repository method-ları
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function scopeByRole(Builder $query, string $role): Builder
    {
        return $query->where('role', $role);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if ($term) {
            return $query->where(function ($q) use ($term) {
                $q->where('name', 'LIKE', "%$term%")
                  ->orWhere('email', 'LIKE', "%$term%");
            });
        }
        return $query;
    }
}

// Active Record istifadəsi — model birbaşa controller-da
$users = User::active()->byRole('admin')->search('Orxan')->paginate(20);
$user = User::findOrFail($id);
$user->update(['name' => 'Yeni Ad']);
$user->delete();
```

### Repository

*Repository üçün kod nümunəsi:*
```php
<?php

// Repository — ayrı class data access-i idarə edir
interface UserRepositoryInterface
{
    public function findById(int $id): ?User;
    public function findByEmail(string $email): ?User;
    public function getActiveUsers(?string $search = null, ?string $role = null, int $perPage = 15): LengthAwarePaginator;
    public function create(array $data): User;
    public function update(int $id, array $data): User;
    public function delete(int $id): bool;
}

class EloquentUserRepository implements UserRepositoryInterface
{
    public function __construct(
        private readonly User $model
    ) {}

    public function findById(int $id): ?User
    {
        return $this->model->find($id);
    }

    public function findByEmail(string $email): ?User
    {
        return $this->model->where('email', $email)->first();
    }

    public function getActiveUsers(?string $search = null, ?string $role = null, int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->query()
            ->where('active', true)
            ->when($search, fn($q, $s) => $q->where('name', 'LIKE', "%$s%"))
            ->when($role, fn($q, $r) => $q->where('role', $r))
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function create(array $data): User
    {
        return $this->model->create($data);
    }

    public function update(int $id, array $data): User
    {
        $user = $this->findById($id);
        $user->update($data);
        return $user->fresh();
    }

    public function delete(int $id): bool
    {
        return $this->model->destroy($id) > 0;
    }
}
```

### Müqayisə Cədvəli

| Xüsusiyyət | Active Record | Repository |
|---|---|---|
| Sadəlik | Çox sadə, az kod | Daha çox kod (boilerplate) |
| Test | Mock etmək çətindir | Asanlıqla mock olunur |
| Flexibility | ORM-ə bağlıdır | Implementation dəyişdirilə bilər |
| SOLID | SRP pozulur (model həm domain, həm data access) | SRP-yə uyğundur |
| Performance | Direct query, çox sürətli | Extra layer, minimal overhead |
| Kiçik layihələr | İdeal | Over-engineering ola bilər |
| Böyük layihələr | Çətin idarə olunur | Məsləhətdir |

---

## Interface-based Repository

Interface istifadəsi Repository pattern-in ən vacib hissəsidir. Bu, Dependency Inversion Principle (DIP) tətbiq edir.

*Interface istifadəsi Repository pattern-in ən vacib hissəsidir. Bu, De üçün kod nümunəsi:*
```php
<?php

namespace App\Contracts\Repositories;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface UserRepositoryInterface
{
    // CRUD əməliyyatları
    public function findById(int $id): ?User;
    public function findOrFail(int $id): User;
    public function findByEmail(string $email): ?User;
    public function create(array $data): User;
    public function update(int $id, array $data): User;
    public function delete(int $id): bool;

    // Siyahı əməliyyatları
    public function all(): Collection;
    public function paginate(int $perPage = 15, array $columns = ['*']): LengthAwarePaginator;

    // Business-specific əməliyyatlar
    public function getActiveUsers(): Collection;
    public function getUsersByRole(string $role): Collection;
    public function searchUsers(string $term): Collection;
    public function getRecentlyRegistered(int $days = 30): Collection;
}

interface OrderRepositoryInterface
{
    public function findById(int $id): ?Order;
    public function create(array $data): Order;
    public function getOrdersByUser(int $userId): Collection;
    public function getOrdersByStatus(string $status): Collection;
    public function getPendingOrders(): Collection;
    public function getTotalRevenue(\DateTimeInterface $from, \DateTimeInterface $to): float;
    public function getTopSellingProducts(int $limit = 10): Collection;
}

interface ProductRepositoryInterface
{
    public function findById(int $id): ?Product;
    public function findBySku(string $sku): ?Product;
    public function create(array $data): Product;
    public function update(int $id, array $data): Product;
    public function getAvailableProducts(): Collection;
    public function getProductsByCategory(int $categoryId): Collection;
    public function searchProducts(string $query, array $filters = []): LengthAwarePaginator;
    public function getLowStockProducts(int $threshold = 10): Collection;
}
```

---

## Base/Abstract Repository

Ortaq CRUD əməliyyatlarını təkrar yazmamaq üçün base repository yaradılır:

*Ortaq CRUD əməliyyatlarını təkrar yazmamaq üçün base repository yaradı üçün kod nümunəsi:*
```php
<?php

namespace App\Repositories;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface BaseRepositoryInterface
{
    public function findById(int $id): ?Model;
    public function findOrFail(int $id): Model;
    public function all(array $columns = ['*']): Collection;
    public function paginate(int $perPage = 15, array $columns = ['*']): LengthAwarePaginator;
    public function create(array $data): Model;
    public function update(int $id, array $data): Model;
    public function delete(int $id): bool;
    public function findWhere(array $conditions): Collection;
    public function findWhereFirst(array $conditions): ?Model;
    public function count(array $conditions = []): int;
    public function with(array $relations): self;
    public function orderBy(string $column, string $direction = 'asc'): self;
}

abstract class BaseRepository implements BaseRepositoryInterface
{
    protected Builder $query;
    protected array $eagerLoad = [];
    protected array $orderBys = [];

    public function __construct(
        protected readonly Model $model
    ) {
        $this->resetQuery();
    }

    protected function resetQuery(): void
    {
        $this->query = $this->model->newQuery();
        $this->eagerLoad = [];
        $this->orderBys = [];
    }

    protected function applyConditions(): Builder
    {
        $query = $this->query;

        if (!empty($this->eagerLoad)) {
            $query = $query->with($this->eagerLoad);
        }

        foreach ($this->orderBys as [$column, $direction]) {
            $query = $query->orderBy($column, $direction);
        }

        return $query;
    }

    public function findById(int $id): ?Model
    {
        $result = $this->applyConditions()->find($id);
        $this->resetQuery();
        return $result;
    }

    public function findOrFail(int $id): Model
    {
        $result = $this->applyConditions()->findOrFail($id);
        $this->resetQuery();
        return $result;
    }

    public function all(array $columns = ['*']): Collection
    {
        $result = $this->applyConditions()->get($columns);
        $this->resetQuery();
        return $result;
    }

    public function paginate(int $perPage = 15, array $columns = ['*']): LengthAwarePaginator
    {
        $result = $this->applyConditions()->paginate($perPage, $columns);
        $this->resetQuery();
        return $result;
    }

    public function create(array $data): Model
    {
        $model = $this->model->create($data);
        $this->resetQuery();
        return $model;
    }

    public function update(int $id, array $data): Model
    {
        $model = $this->findOrFail($id);
        $model->update($data);
        return $model->fresh();
    }

    public function delete(int $id): bool
    {
        $model = $this->findOrFail($id);
        return $model->delete();
    }

    public function findWhere(array $conditions): Collection
    {
        $query = $this->applyConditions();
        foreach ($conditions as $field => $value) {
            if (is_array($value)) {
                $query->whereIn($field, $value);
            } else {
                $query->where($field, $value);
            }
        }
        $result = $query->get();
        $this->resetQuery();
        return $result;
    }

    public function findWhereFirst(array $conditions): ?Model
    {
        $query = $this->applyConditions();
        foreach ($conditions as $field => $value) {
            $query->where($field, $value);
        }
        $result = $query->first();
        $this->resetQuery();
        return $result;
    }

    public function count(array $conditions = []): int
    {
        $query = $this->applyConditions();
        foreach ($conditions as $field => $value) {
            $query->where($field, $value);
        }
        $count = $query->count();
        $this->resetQuery();
        return $count;
    }

    public function with(array $relations): self
    {
        $this->eagerLoad = array_merge($this->eagerLoad, $relations);
        return $this;
    }

    public function orderBy(string $column, string $direction = 'asc'): self
    {
        $this->orderBys[] = [$column, $direction];
        return $this;
    }
}

// === Concrete Repository-lər ===

class EloquentUserRepository extends BaseRepository implements UserRepositoryInterface
{
    public function __construct(User $model)
    {
        parent::__construct($model);
    }

    public function findByEmail(string $email): ?User
    {
        return $this->findWhereFirst(['email' => $email]);
    }

    public function getActiveUsers(): Collection
    {
        return $this->findWhere(['active' => true]);
    }

    public function getUsersByRole(string $role): Collection
    {
        return $this->findWhere(['role' => $role]);
    }

    public function searchUsers(string $term): Collection
    {
        $result = $this->model->query()
            ->where(function ($query) use ($term) {
                $query->where('name', 'LIKE', "%$term%")
                      ->orWhere('email', 'LIKE', "%$term%");
            })
            ->get();
        return $result;
    }

    public function getRecentlyRegistered(int $days = 30): Collection
    {
        return $this->model->query()
            ->where('created_at', '>=', now()->subDays($days))
            ->orderByDesc('created_at')
            ->get();
    }

    public function getActiveUsersWithOrders(): Collection
    {
        return $this->with(['orders'])->findWhere(['active' => true]);
    }
}

class EloquentOrderRepository extends BaseRepository implements OrderRepositoryInterface
{
    public function __construct(Order $model)
    {
        parent::__construct($model);
    }

    public function getOrdersByUser(int $userId): Collection
    {
        return $this->with(['items.product'])
            ->orderBy('created_at', 'desc')
            ->findWhere(['user_id' => $userId]);
    }

    public function getOrdersByStatus(string $status): Collection
    {
        return $this->findWhere(['status' => $status]);
    }

    public function getPendingOrders(): Collection
    {
        return $this->getOrdersByStatus('pending');
    }

    public function getTotalRevenue(\DateTimeInterface $from, \DateTimeInterface $to): float
    {
        return $this->model->query()
            ->where('status', 'completed')
            ->whereBetween('created_at', [$from, $to])
            ->sum('total');
    }

    public function getTopSellingProducts(int $limit = 10): Collection
    {
        return $this->model->query()
            ->join('order_items', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.status', 'completed')
            ->select('order_items.product_id')
            ->selectRaw('SUM(order_items.quantity) as total_sold')
            ->selectRaw('SUM(order_items.quantity * order_items.price) as total_revenue')
            ->groupBy('order_items.product_id')
            ->orderByDesc('total_sold')
            ->limit($limit)
            ->with('product')
            ->get();
    }
}
```

---

## Criteria/Specification Pattern

Mürəkkəb query-ləri modular etmək üçün Criteria (və ya Specification) pattern istifadə olunur:

*Mürəkkəb query-ləri modular etmək üçün Criteria (və ya Specification)  üçün kod nümunəsi:*
```php
<?php

// === Criteria Interface ===

interface CriteriaInterface
{
    public function apply(Builder $query): Builder;
}

// === Concrete Criteria-lar ===

class ActiveUsersCriteria implements CriteriaInterface
{
    public function apply(Builder $query): Builder
    {
        return $query->where('active', true);
    }
}

class ByRoleCriteria implements CriteriaInterface
{
    public function __construct(
        private readonly string $role
    ) {}

    public function apply(Builder $query): Builder
    {
        return $query->where('role', $this->role);
    }
}

class SearchCriteria implements CriteriaInterface
{
    public function __construct(
        private readonly string $term,
        private readonly array $searchableColumns = ['name', 'email']
    ) {}

    public function apply(Builder $query): Builder
    {
        return $query->where(function ($q) {
            foreach ($this->searchableColumns as $column) {
                $q->orWhere($column, 'LIKE', "%{$this->term}%");
            }
        });
    }
}

class DateRangeCriteria implements CriteriaInterface
{
    public function __construct(
        private readonly string $column,
        private readonly ?\DateTimeInterface $from = null,
        private readonly ?\DateTimeInterface $to = null
    ) {}

    public function apply(Builder $query): Builder
    {
        if ($this->from && $this->to) {
            return $query->whereBetween($this->column, [$this->from, $this->to]);
        }
        if ($this->from) {
            return $query->where($this->column, '>=', $this->from);
        }
        if ($this->to) {
            return $query->where($this->column, '<=', $this->to);
        }
        return $query;
    }
}

class OrderByCriteria implements CriteriaInterface
{
    public function __construct(
        private readonly string $column,
        private readonly string $direction = 'asc'
    ) {}

    public function apply(Builder $query): Builder
    {
        return $query->orderBy($this->column, $this->direction);
    }
}

class WithRelationsCriteria implements CriteriaInterface
{
    public function __construct(
        private readonly array $relations
    ) {}

    public function apply(Builder $query): Builder
    {
        return $query->with($this->relations);
    }
}

class HasMinimumOrdersCriteria implements CriteriaInterface
{
    public function __construct(
        private readonly int $minimumOrders
    ) {}

    public function apply(Builder $query): Builder
    {
        return $query->has('orders', '>=', $this->minimumOrders);
    }
}

// === Repository ilə Criteria inteqrasiyası ===

interface CriteriaRepositoryInterface
{
    public function pushCriteria(CriteriaInterface $criteria): self;
    public function resetCriteria(): self;
    public function getByCriteria(CriteriaInterface $criteria): Collection;
}

abstract class CriteriaRepository extends BaseRepository implements CriteriaRepositoryInterface
{
    protected array $criteria = [];

    public function pushCriteria(CriteriaInterface $criteria): self
    {
        $this->criteria[] = $criteria;
        return $this;
    }

    public function resetCriteria(): self
    {
        $this->criteria = [];
        return $this;
    }

    public function getByCriteria(CriteriaInterface $criteria): Collection
    {
        return $this->pushCriteria($criteria)->all();
    }

    protected function applyCriteria(): Builder
    {
        $query = $this->model->newQuery();

        foreach ($this->criteria as $criteria) {
            $query = $criteria->apply($query);
        }

        $this->criteria = [];
        return $query;
    }

    // Override base methods to use criteria
    public function all(array $columns = ['*']): Collection
    {
        if (empty($this->criteria)) {
            return parent::all($columns);
        }
        $result = $this->applyCriteria()->get($columns);
        return $result;
    }

    public function paginate(int $perPage = 15, array $columns = ['*']): LengthAwarePaginator
    {
        if (empty($this->criteria)) {
            return parent::paginate($perPage, $columns);
        }
        $result = $this->applyCriteria()->paginate($perPage, $columns);
        return $result;
    }
}

// === İstifadə ===

class UserController
{
    public function __construct(
        private readonly EloquentUserRepository $repository
    ) {}

    public function index(Request $request): JsonResponse
    {
        $repo = $this->repository;

        // Criteria-ları dinamik əlavə et
        $repo->pushCriteria(new ActiveUsersCriteria());

        if ($request->has('role')) {
            $repo->pushCriteria(new ByRoleCriteria($request->role));
        }

        if ($request->has('search')) {
            $repo->pushCriteria(new SearchCriteria($request->search));
        }

        if ($request->has('from') || $request->has('to')) {
            $repo->pushCriteria(new DateRangeCriteria(
                'created_at',
                $request->date('from'),
                $request->date('to')
            ));
        }

        $repo->pushCriteria(new OrderByCriteria(
            $request->input('sort_by', 'created_at'),
            $request->input('sort_dir', 'desc')
        ));

        $repo->pushCriteria(new WithRelationsCriteria(['profile', 'roles']));

        return response()->json($repo->paginate($request->input('per_page', 20)));
    }
}
```

---

## Repository ilə Caching

Repository pattern caching tətbiq etmək üçün idealdır — Decorator pattern ilə:

*Repository pattern caching tətbiq etmək üçün idealdır — Decorator patt üçün kod nümunəsi:*
```php
<?php

class CachedUserRepository implements UserRepositoryInterface
{
    private const CACHE_TTL = 3600; // 1 saat
    private const CACHE_PREFIX = 'users:';

    public function __construct(
        private readonly UserRepositoryInterface $repository, // Decorated repository
        private readonly \Illuminate\Contracts\Cache\Repository $cache
    ) {}

    public function findById(int $id): ?User
    {
        $key = self::CACHE_PREFIX . "id:$id";

        return $this->cache->remember($key, self::CACHE_TTL, function () use ($id) {
            return $this->repository->findById($id);
        });
    }

    public function findByEmail(string $email): ?User
    {
        $key = self::CACHE_PREFIX . "email:" . md5($email);

        return $this->cache->remember($key, self::CACHE_TTL, function () use ($email) {
            return $this->repository->findByEmail($email);
        });
    }

    public function getActiveUsers(): Collection
    {
        $key = self::CACHE_PREFIX . "active";

        return $this->cache->remember($key, self::CACHE_TTL, function () {
            return $this->repository->getActiveUsers();
        });
    }

    public function create(array $data): User
    {
        $user = $this->repository->create($data);
        $this->clearCache();
        return $user;
    }

    public function update(int $id, array $data): User
    {
        $user = $this->repository->update($id, $data);
        $this->clearUserCache($id);
        $this->clearUserCache(null, $user->email);
        return $user;
    }

    public function delete(int $id): bool
    {
        $user = $this->findById($id);
        $result = $this->repository->delete($id);
        if ($result && $user) {
            $this->clearUserCache($id);
            $this->clearUserCache(null, $user->email);
        }
        return $result;
    }

    // Cache temizleme
    private function clearUserCache(?int $id = null, ?string $email = null): void
    {
        if ($id) {
            $this->cache->forget(self::CACHE_PREFIX . "id:$id");
        }
        if ($email) {
            $this->cache->forget(self::CACHE_PREFIX . "email:" . md5($email));
        }
        // List cache-ləri təmizlə
        $this->cache->forget(self::CACHE_PREFIX . "active");
    }

    private function clearCache(): void
    {
        // Bütün user cache-ləri təmizlə (tag istifadə etsək daha yaxşıdır)
        // Cache::tags(['users'])->flush();
    }

    // Digər method-ları delegate et
    public function all(): Collection
    {
        return $this->repository->all();
    }

    public function paginate(int $perPage = 15, array $columns = ['*']): LengthAwarePaginator
    {
        return $this->repository->paginate($perPage, $columns);
    }

    public function getUsersByRole(string $role): Collection
    {
        $key = self::CACHE_PREFIX . "role:$role";
        return $this->cache->remember($key, self::CACHE_TTL, function () use ($role) {
            return $this->repository->getUsersByRole($role);
        });
    }

    public function searchUsers(string $term): Collection
    {
        // Search nəticələrini cache etmək adətən məsləhət deyil
        return $this->repository->searchUsers($term);
    }

    public function getRecentlyRegistered(int $days = 30): Collection
    {
        return $this->repository->getRecentlyRegistered($days);
    }
}

// Cache Tags ilə daha yaxşı versiya:
class TaggedCachedUserRepository implements UserRepositoryInterface
{
    private const TTL = 3600;

    public function __construct(
        private readonly UserRepositoryInterface $repository,
        private readonly \Illuminate\Contracts\Cache\Repository $cache
    ) {}

    private function cacheStore(): \Illuminate\Cache\TaggedCache
    {
        return $this->cache->tags(['users']);
    }

    public function findById(int $id): ?User
    {
        return $this->cacheStore()->remember(
            "user:$id",
            self::TTL,
            fn() => $this->repository->findById($id)
        );
    }

    public function create(array $data): User
    {
        $user = $this->repository->create($data);
        $this->cacheStore()->flush(); // Bütün user cache-ləri silir
        return $user;
    }

    public function update(int $id, array $data): User
    {
        $user = $this->repository->update($id, $data);
        $this->cacheStore()->flush();
        return $user;
    }

    // ... digər method-lar
}

// === Service Provider-dən binding ===
class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Base Eloquent repository
        $this->app->bind(
            UserRepositoryInterface::class,
            EloquentUserRepository::class
        );

        // Cached versiya (Decorator)
        $this->app->extend(UserRepositoryInterface::class, function ($repository, $app) {
            return new CachedUserRepository(
                $repository,
                $app->make(\Illuminate\Contracts\Cache\Repository::class)
            );
        });

        // Və ya environment-ə görə:
        if (app()->environment('production')) {
            $this->app->extend(UserRepositoryInterface::class, function ($repository, $app) {
                return new CachedUserRepository($repository, $app['cache.store']);
            });
        }
    }
}
```

---

## Laravel-də Tam Implementation

Addım-addım tam repository sistemi quraq:

### Addım 1: Interface-lər

*Addım 1: Interface-lər üçün kod nümunəsi:*
```php
<?php

// app/Contracts/Repositories/UserRepositoryInterface.php
namespace App\Contracts\Repositories;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface UserRepositoryInterface
{
    public function findById(int $id): ?User;
    public function findOrFail(int $id): User;
    public function findByEmail(string $email): ?User;
    public function all(): Collection;
    public function paginate(int $perPage = 15): LengthAwarePaginator;
    public function create(array $data): User;
    public function update(int $id, array $data): User;
    public function delete(int $id): bool;
    public function getActiveUsers(int $perPage = 15): LengthAwarePaginator;
    public function getUsersByRole(string $role): Collection;
    public function search(string $term, int $perPage = 15): LengthAwarePaginator;
}
```

### Addım 2: Eloquent Implementation

*Addım 2: Eloquent Implementation üçün kod nümunəsi:*
```php
<?php

// app/Repositories/EloquentUserRepository.php
namespace App\Repositories;

use App\Contracts\Repositories\UserRepositoryInterface;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class EloquentUserRepository implements UserRepositoryInterface
{
    public function __construct(
        private readonly User $model
    ) {}

    public function findById(int $id): ?User
    {
        return $this->model->find($id);
    }

    public function findOrFail(int $id): User
    {
        return $this->model->findOrFail($id);
    }

    public function findByEmail(string $email): ?User
    {
        return $this->model->where('email', $email)->first();
    }

    public function all(): Collection
    {
        return $this->model->all();
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->latest()->paginate($perPage);
    }

    public function create(array $data): User
    {
        return $this->model->create($data);
    }

    public function update(int $id, array $data): User
    {
        $user = $this->findOrFail($id);
        $user->update($data);
        return $user->fresh();
    }

    public function delete(int $id): bool
    {
        return $this->model->destroy($id) > 0;
    }

    public function getActiveUsers(int $perPage = 15): LengthAwarePaginator
    {
        return $this->model
            ->where('active', true)
            ->latest()
            ->paginate($perPage);
    }

    public function getUsersByRole(string $role): Collection
    {
        return $this->model->where('role', $role)->get();
    }

    public function search(string $term, int $perPage = 15): LengthAwarePaginator
    {
        return $this->model
            ->where(function ($query) use ($term) {
                $query->where('name', 'LIKE', "%{$term}%")
                      ->orWhere('email', 'LIKE', "%{$term}%");
            })
            ->latest()
            ->paginate($perPage);
    }
}
```

### Addım 3: Service Provider

*Addım 3: Service Provider üçün kod nümunəsi:*
```php
<?php

// app/Providers/RepositoryServiceProvider.php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Contracts\Repositories\UserRepositoryInterface;
use App\Contracts\Repositories\OrderRepositoryInterface;
use App\Contracts\Repositories\ProductRepositoryInterface;
use App\Repositories\EloquentUserRepository;
use App\Repositories\EloquentOrderRepository;
use App\Repositories\EloquentProductRepository;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Repository binding-lərin siyahısı.
     * Yeni repository əlavə etmək üçün buraya əlavə edin.
     */
    private array $repositories = [
        UserRepositoryInterface::class => EloquentUserRepository::class,
        OrderRepositoryInterface::class => EloquentOrderRepository::class,
        ProductRepositoryInterface::class => EloquentProductRepository::class,
    ];

    public function register(): void
    {
        foreach ($this->repositories as $interface => $implementation) {
            $this->app->bind($interface, $implementation);
        }
    }
}

// config/app.php-ə əlavə et:
// 'providers' => [
//     ...
//     App\Providers\RepositoryServiceProvider::class,
// ],
```

### Addım 4: Controller-da İstifadə

*Addım 4: Controller-da İstifadə üçün kod nümunəsi:*
```php
<?php

// app/Http/Controllers/UserController.php
namespace App\Http\Controllers;

use App\Contracts\Repositories\UserRepositoryInterface;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Http\Resources\UserCollection;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    public function __construct(
        private readonly UserRepositoryInterface $users
    ) {}

    public function index(): UserCollection
    {
        $users = $this->users->paginate(20);
        return new UserCollection($users);
    }

    public function show(int $id): UserResource
    {
        $user = $this->users->findOrFail($id);
        return new UserResource($user);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = $this->users->create($request->validated());
        return (new UserResource($user))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateUserRequest $request, int $id): UserResource
    {
        $user = $this->users->update($id, $request->validated());
        return new UserResource($user);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->users->delete($id);
        return response()->json(null, 204);
    }

    public function search(string $term): UserCollection
    {
        return new UserCollection($this->users->search($term));
    }
}
```

### Addım 5: Service Layer ilə birlikdə

*Addım 5: Service Layer ilə birlikdə üçün kod nümunəsi:*
```php
<?php

// app/Services/UserService.php
namespace App\Services;

use App\Contracts\Repositories\UserRepositoryInterface;
use App\Models\User;
use App\Events\UserCreated;
use App\Events\UserUpdated;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly NotificationService $notifications
    ) {}

    public function createUser(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $data['password'] = Hash::make($data['password']);

            $user = $this->users->create($data);

            // Event fire et
            event(new UserCreated($user));

            // Welcome email göndər
            $this->notifications->sendWelcomeEmail($user);

            return $user;
        });
    }

    public function updateUser(int $id, array $data): User
    {
        return DB::transaction(function () use ($id, $data) {
            if (isset($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            }

            $user = $this->users->update($id, $data);

            event(new UserUpdated($user));

            return $user;
        });
    }

    public function deactivateUser(int $id): User
    {
        $user = $this->users->update($id, ['active' => false]);

        $this->notifications->sendDeactivationNotice($user);

        return $user;
    }
}
```

---

## Real-World Repository Nümunələri

### ProductRepository

*ProductRepository üçün kod nümunəsi:*
```php
<?php

interface ProductRepositoryInterface
{
    public function findById(int $id): ?Product;
    public function findBySku(string $sku): ?Product;
    public function create(array $data): Product;
    public function update(int $id, array $data): Product;
    public function getAvailableProducts(int $perPage = 20): LengthAwarePaginator;
    public function getProductsByCategory(int $categoryId, int $perPage = 20): LengthAwarePaginator;
    public function searchProducts(string $query, array $filters = [], int $perPage = 20): LengthAwarePaginator;
    public function getLowStockProducts(int $threshold = 10): Collection;
    public function getBestSellers(int $limit = 10): Collection;
    public function getRelatedProducts(Product $product, int $limit = 4): Collection;
}

class EloquentProductRepository implements ProductRepositoryInterface
{
    public function __construct(
        private readonly Product $model
    ) {}

    public function findById(int $id): ?Product
    {
        return $this->model
            ->with(['category', 'images', 'variants'])
            ->find($id);
    }

    public function findBySku(string $sku): ?Product
    {
        return $this->model->where('sku', $sku)->first();
    }

    public function create(array $data): Product
    {
        return DB::transaction(function () use ($data) {
            $product = $this->model->create([
                'name' => $data['name'],
                'sku' => $data['sku'],
                'price' => $data['price'],
                'description' => $data['description'] ?? null,
                'category_id' => $data['category_id'],
                'stock' => $data['stock'] ?? 0,
                'slug' => Str::slug($data['name']),
            ]);

            if (isset($data['images'])) {
                foreach ($data['images'] as $image) {
                    $product->images()->create($image);
                }
            }

            if (isset($data['variants'])) {
                foreach ($data['variants'] as $variant) {
                    $product->variants()->create($variant);
                }
            }

            return $product->load(['category', 'images', 'variants']);
        });
    }

    public function update(int $id, array $data): Product
    {
        $product = $this->model->findOrFail($id);
        $product->update($data);
        return $product->fresh(['category', 'images', 'variants']);
    }

    public function getAvailableProducts(int $perPage = 20): LengthAwarePaginator
    {
        return $this->model
            ->where('stock', '>', 0)
            ->where('active', true)
            ->with(['category', 'images'])
            ->latest()
            ->paginate($perPage);
    }

    public function getProductsByCategory(int $categoryId, int $perPage = 20): LengthAwarePaginator
    {
        return $this->model
            ->where('category_id', $categoryId)
            ->where('active', true)
            ->with(['images'])
            ->paginate($perPage);
    }

    public function searchProducts(string $query, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $builder = $this->model->query()
            ->where('active', true)
            ->where(function ($q) use ($query) {
                $q->where('name', 'LIKE', "%$query%")
                  ->orWhere('description', 'LIKE', "%$query%")
                  ->orWhere('sku', 'LIKE', "%$query%");
            });

        // Filterləri tətbiq et
        if (isset($filters['min_price'])) {
            $builder->where('price', '>=', $filters['min_price']);
        }
        if (isset($filters['max_price'])) {
            $builder->where('price', '<=', $filters['max_price']);
        }
        if (isset($filters['category_id'])) {
            $builder->where('category_id', $filters['category_id']);
        }
        if (isset($filters['in_stock']) && $filters['in_stock']) {
            $builder->where('stock', '>', 0);
        }

        // Sıralama
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';
        $builder->orderBy($sortBy, $sortDir);

        return $builder->with(['category', 'images'])->paginate($perPage);
    }

    public function getLowStockProducts(int $threshold = 10): Collection
    {
        return $this->model
            ->where('stock', '>', 0)
            ->where('stock', '<=', $threshold)
            ->where('active', true)
            ->orderBy('stock')
            ->get();
    }

    public function getBestSellers(int $limit = 10): Collection
    {
        return $this->model
            ->withCount(['orderItems as total_sold' => function ($query) {
                $query->whereHas('order', fn($q) => $q->where('status', 'completed'));
            }])
            ->orderByDesc('total_sold')
            ->limit($limit)
            ->get();
    }

    public function getRelatedProducts(Product $product, int $limit = 4): Collection
    {
        return $this->model
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->where('active', true)
            ->inRandomOrder()
            ->limit($limit)
            ->get();
    }
}
```

---

## Repository Unit Test

*Repository Unit Test üçün kod nümunəsi:*
```php
<?php

namespace Tests\Unit\Repositories;

use Tests\TestCase;
use App\Models\User;
use App\Repositories\EloquentUserRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

class EloquentUserRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private EloquentUserRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new EloquentUserRepository(new User());
    }

    public function test_can_create_user(): void
    {
        $data = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ];

        $user = $this->repository->create($data);

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('Test User', $user->name);
        $this->assertEquals('test@example.com', $user->email);
        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
    }

    public function test_can_find_user_by_id(): void
    {
        $user = User::factory()->create();

        $found = $this->repository->findById($user->id);

        $this->assertNotNull($found);
        $this->assertEquals($user->id, $found->id);
    }

    public function test_returns_null_for_nonexistent_user(): void
    {
        $found = $this->repository->findById(99999);

        $this->assertNull($found);
    }

    public function test_can_find_user_by_email(): void
    {
        $user = User::factory()->create(['email' => 'unique@test.com']);

        $found = $this->repository->findByEmail('unique@test.com');

        $this->assertNotNull($found);
        $this->assertEquals($user->id, $found->id);
    }

    public function test_can_update_user(): void
    {
        $user = User::factory()->create(['name' => 'Old Name']);

        $updated = $this->repository->update($user->id, ['name' => 'New Name']);

        $this->assertEquals('New Name', $updated->name);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'New Name']);
    }

    public function test_can_delete_user(): void
    {
        $user = User::factory()->create();

        $result = $this->repository->delete($user->id);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_get_active_users(): void
    {
        User::factory()->count(3)->create(['active' => true]);
        User::factory()->count(2)->create(['active' => false]);

        $activeUsers = $this->repository->getActiveUsers(10);

        $this->assertEquals(3, $activeUsers->total());
    }

    public function test_search_users(): void
    {
        User::factory()->create(['name' => 'Orxan Əliyev']);
        User::factory()->create(['name' => 'Aynur Həsənova']);
        User::factory()->create(['email' => 'orxan@test.com']);

        $results = $this->repository->search('orxan');

        $this->assertEquals(2, $results->total());
    }

    public function test_get_users_by_role(): void
    {
        User::factory()->count(3)->create(['role' => 'admin']);
        User::factory()->count(5)->create(['role' => 'user']);

        $admins = $this->repository->getUsersByRole('admin');

        $this->assertCount(3, $admins);
    }
}

// === Mock Repository ilə Service Test ===

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\UserService;
use App\Contracts\Repositories\UserRepositoryInterface;
use App\Models\User;
use Mockery;

class UserServiceTest extends TestCase
{
    public function test_create_user_hashes_password(): void
    {
        $mockRepo = Mockery::mock(UserRepositoryInterface::class);
        $mockNotifications = Mockery::mock(NotificationService::class);

        $mockRepo->shouldReceive('create')
            ->once()
            ->withArgs(function ($data) {
                // Password hash olunub?
                return $data['name'] === 'Test'
                    && $data['email'] === 'test@test.com'
                    && password_verify('secret', $data['password']);
            })
            ->andReturn(new User([
                'id' => 1,
                'name' => 'Test',
                'email' => 'test@test.com',
            ]));

        $mockNotifications->shouldReceive('sendWelcomeEmail')->once();

        $service = new UserService($mockRepo, $mockNotifications);

        $user = $service->createUser([
            'name' => 'Test',
            'email' => 'test@test.com',
            'password' => 'secret',
        ]);

        $this->assertEquals('Test', $user->name);
    }
}
```

---

## Repository Mənfi Cəhətləri

Repository pattern həmişə yaxşı seçim deyil. Mənfi cəhətləri:

*Repository pattern həmişə yaxşı seçim deyil. Mənfi cəhətləri üçün kod nümunəsi:*
```php
<?php

// 1. Boilerplate kod artır
// Hər entity üçün Interface + Implementation + Binding lazımdır

// 2. Eloquent-in gücünü daraltır
// Repository method-ları bütün Eloquent feature-ları əhatə etmir:
$users = User::query()
    ->whereHas('posts', function ($q) {
        $q->where('published', true)
           ->whereYear('published_at', 2024);
    })
    ->withCount('posts')
    ->withAvg('orders', 'total')
    ->having('posts_count', '>', 5)
    ->get();

// Bu mürəkkəb sorğunu repository method-una çevirmək çətindir

// 3. "Leaky Abstraction" problemi
// Eloquent Model-i qaytaran repository aslında abstraction-ı pozur
interface UserRepositoryInterface
{
    public function findById(int $id): ?User; // <-- Eloquent Model qaytarır!
    // Əsl abstraction: DTO qaytarmalıdır
}

// 4. Over-engineering risk
// Kiçik layihələrdə lazımsız mürəkkəblik yaradır
```

**Alternativlər:**

```php
<?php

// 1. Eloquent Scopes (Repository olmadan)
class User extends Model
{
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'LIKE', "%$term%")
              ->orWhere('email', 'LIKE', "%$term%");
        });
    }
}

// 2. Action Classes (Single Responsibility)
class CreateUser
{
    public function execute(array $data): User
    {
        $data['password'] = Hash::make($data['password']);
        $user = User::create($data);
        event(new UserCreated($user));
        return $user;
    }
}

class SearchUsers
{
    public function execute(string $term, int $perPage = 20): LengthAwarePaginator
    {
        return User::search($term)->active()->paginate($perPage);
    }
}

// 3. Query Object Pattern (aşağıda ətraflı)
```

---

## Query Object Pattern

Repository-yə alternativ olaraq Query Object pattern mürəkkəb sorğuları kapsullaşdırır:

*Repository-yə alternativ olaraq Query Object pattern mürəkkəb sorğular üçün kod nümunəsi:*
```php
<?php

class ActiveUsersWithOrdersQuery
{
    public function __construct(
        private readonly ?string $role = null,
        private readonly ?int $minOrders = null,
        private readonly ?string $search = null,
        private readonly int $perPage = 20
    ) {}

    public function execute(): LengthAwarePaginator
    {
        $query = User::query()
            ->where('active', true)
            ->withCount('orders');

        if ($this->role) {
            $query->where('role', $this->role);
        }

        if ($this->minOrders) {
            $query->having('orders_count', '>=', $this->minOrders);
        }

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'LIKE', "%{$this->search}%")
                  ->orWhere('email', 'LIKE', "%{$this->search}%");
            });
        }

        return $query->latest()->paginate($this->perPage);
    }
}

class TopCustomersQuery
{
    public function __construct(
        private readonly \DateTimeInterface $from,
        private readonly \DateTimeInterface $to,
        private readonly int $limit = 10
    ) {}

    public function execute(): Collection
    {
        return User::query()
            ->withSum(['orders' => function ($query) {
                $query->where('status', 'completed')
                      ->whereBetween('created_at', [$this->from, $this->to]);
            }], 'total')
            ->orderByDesc('orders_sum_total')
            ->limit($this->limit)
            ->get();
    }
}

class MonthlyRevenueQuery
{
    public function __construct(
        private readonly int $year,
        private readonly ?int $month = null
    ) {}

    public function execute(): Collection
    {
        $query = Order::query()
            ->where('status', 'completed')
            ->whereYear('created_at', $this->year);

        if ($this->month) {
            $query->whereMonth('created_at', $this->month);
        }

        return $query
            ->selectRaw('MONTH(created_at) as month')
            ->selectRaw('SUM(total) as revenue')
            ->selectRaw('COUNT(*) as order_count')
            ->groupByRaw('MONTH(created_at)')
            ->orderBy('month')
            ->get();
    }
}

// İstifadə
class ReportController
{
    public function topCustomers(Request $request): JsonResponse
    {
        $query = new TopCustomersQuery(
            from: $request->date('from'),
            to: $request->date('to'),
            limit: $request->integer('limit', 10)
        );

        return response()->json($query->execute());
    }

    public function monthlyRevenue(int $year): JsonResponse
    {
        $query = new MonthlyRevenueQuery($year);
        return response()->json($query->execute());
    }
}
```

---

# SINGLETON PATTERN

## Singleton Pattern Nədir

Singleton Pattern — bir class-ın yalnız bir instance-ının olmasını təmin edən və bu instance-a global daxil olmağı mümkün edən creational design pattern-dir.

**Əsas xüsusiyyətləri:**
1. Private constructor — xaricdən `new` ilə yaradıla bilməz
2. Private clone — kopyalana bilməz
3. Static method — yeganə instance-ı qaytarır
4. Lazy initialization — ilk dəfə lazım olduqda yaradılır

*4. Lazy initialization — ilk dəfə lazım olduqda yaradılır üçün kod nümunəsi:*
```php
<?php

// Nə zaman Singleton lazımdır:
// 1. Database connection — yalnız bir connection lazımdır
// 2. Logger — bütün application eyni logger istifadə etməlidir
// 3. Configuration — config yalnız bir dəfə oxunmalıdır
// 4. Cache manager — connection paylaşılmalıdır
// 5. Thread pool / Connection pool

// Nə zaman Singleton lazım DEYİL:
// 1. Stateless service-lər
// 2. Data Transfer Object-lər
// 3. Entity/Value Object-lər
// 4. İstənilən class ki, state dəyişir request-lər arasında
```

---

## PHP-də Singleton Implementation

*PHP-də Singleton Implementation üçün kod nümunəsi:*
```php
<?php

// === Classic Singleton ===

class DatabaseConnection
{
    private static ?self $instance = null;
    private \PDO $pdo;

    /**
     * Private constructor — xaricdən new edilə bilməz
     */
    private function __construct(
        string $dsn,
        string $username,
        string $password
    ) {
        $this->pdo = new \PDO($dsn, $username, $password, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    /**
     * Clone etməyi bloklayır
     */
    private function __clone(): void {}

    /**
     * Unserialize etməyi bloklayır
     */
    public function __wakeup(): void
    {
        throw new \RuntimeException("Cannot unserialize a singleton.");
    }

    /**
     * Yeganə instance-ı qaytarır (lazy initialization)
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self(
                'mysql:host=localhost;dbname=myapp;charset=utf8mb4',
                'root',
                'secret'
            );
        }

        return self::$instance;
    }

    public function query(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function execute(string $sql, array $params = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /**
     * Test üçün instance-ı sıfırlama (yalnız test mühitində!)
     */
    public static function resetInstance(): void
    {
        self::$instance = null;
    }
}

// İstifadə
$db = DatabaseConnection::getInstance();
$users = $db->query("SELECT * FROM users WHERE active = ?", [true]);

// Hər yerdə eyni instance
$db2 = DatabaseConnection::getInstance();
var_dump($db === $db2); // true — eyni object

// Bunlar mümkün deyil:
// $db = new DatabaseConnection(...); // Fatal error: private constructor
// $db2 = clone $db; // Fatal error: private __clone
```

### Singleton Variasiyaları

*Singleton Variasiyaları üçün kod nümunəsi:*
```php
<?php

// === Registry Pattern (Multi-Singleton) ===

class ConnectionRegistry
{
    private static array $instances = [];

    private function __construct() {}
    private function __clone(): void {}

    public static function getConnection(string $name = 'default'): \PDO
    {
        if (!isset(self::$instances[$name])) {
            $config = self::getConfig($name);
            self::$instances[$name] = new \PDO(
                $config['dsn'],
                $config['username'],
                $config['password']
            );
        }

        return self::$instances[$name];
    }

    private static function getConfig(string $name): array
    {
        $configs = [
            'default' => [
                'dsn' => 'mysql:host=localhost;dbname=myapp',
                'username' => 'root',
                'password' => 'secret',
            ],
            'analytics' => [
                'dsn' => 'mysql:host=analytics-server;dbname=analytics',
                'username' => 'reader',
                'password' => 'readonly',
            ],
        ];

        if (!isset($configs[$name])) {
            throw new \InvalidArgumentException("Unknown connection: $name");
        }

        return $configs[$name];
    }
}

// İstifadə
$defaultDb = ConnectionRegistry::getConnection(); // default
$analyticsDb = ConnectionRegistry::getConnection('analytics');

// === Enum Singleton (PHP 8.1+) ===
// PHP-də enum instance-ları naturally singleton-dur

enum AppConfig
{
    case Instance;

    private const CONFIG_PATH = '/etc/app/config.json';

    public function get(string $key, mixed $default = null): mixed
    {
        static $config = null;

        if ($config === null) {
            $config = json_decode(file_get_contents(self::CONFIG_PATH), true);
        }

        return $config[$key] ?? $default;
    }
}

// İstifadə
$dbHost = AppConfig::Instance->get('database.host', 'localhost');
```

---

## Thread Safety

PHP-nin standard request-response modelində thread safety nadir problem yaradır, amma paralel execution mühitlərində (Swoole, RoadRunner, Octane) əhəmiyyətli olur:

*PHP-nin standard request-response modelində thread safety nadir proble üçün kod nümunəsi:*
```php
<?php

// PHP-nin standard modeli:
// Hər request ayrı process-dir, state paylaşılmır
// Ona görə classic PHP-də Singleton thread-safe-dir

// AMMA Laravel Octane (Swoole/RoadRunner) ilə:
// Eyni process çoxlu request-lər emal edir
// Singleton instance request-lər arasında paylaşılır!

// PROBLEM:
class RequestCounter
{
    private static ?self $instance = null;
    private int $count = 0; // Bu state request-lər arasında qalır!

    private function __construct() {}

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function increment(): void
    {
        $this->count++; // Race condition risk!
    }

    public function getCount(): int
    {
        return $this->count;
    }
}

// Octane-da:
// Request 1: counter = 1
// Request 2: counter = 2 (1-dən başlamır!)
// Request 3: counter = 3
// STATE LEAK!

// HƏLL: Laravel Octane üçün scoped singleton istifadə edin
$this->app->scoped(RequestCounter::class);

// Və ya request bitdikdə state təmizləyin:
// Octane flush callback
Octane::on('request-handled', function () {
    RequestCounter::resetInstance();
});
```

---

## Laravel-də Singleton

Laravel-in Service Container-i Singleton pattern-i daha yaxşı şəkildə tətbiq edir:

*Laravel-in Service Container-i Singleton pattern-i daha yaxşı şəkildə  üçün kod nümunəsi:*
```php
<?php

// === Laravel Container Singleton Binding ===

// 1. singleton() metodu
$this->app->singleton(CacheManager::class, function ($app) {
    return new CacheManager($app);
});

// 2. instance() metodu (artıq yaradılmış object)
$config = new AppConfig(require 'config.php');
$this->app->instance(AppConfig::class, $config);

// 3. scoped() metodu (request-scoped singleton)
$this->app->scoped(CartService::class, function ($app) {
    return new CartService($app['session']);
});

// === Laravel-in Öz Singleton-ları ===

// Framework-un core singleton-ları:
// (Illuminate\Foundation\Application::registerBaseBindings)

// 'app' -> Application instance
// 'config' -> Repository (Config)
// 'db' -> DatabaseManager
// 'cache' -> CacheManager
// 'log' -> LogManager
// 'router' -> Router
// 'events' -> Dispatcher
// 'auth' -> AuthManager
// 'hash' -> HashManager
// 'mail.manager' -> MailManager
// 'queue' -> QueueManager
// 'session' -> SessionManager
// 'validator' -> ValidationFactory
// 'view' -> ViewFactory

// Hamısı singleton olaraq register olunub:
// vendor/laravel/framework/src/Illuminate/Foundation/Application.php

class Application extends Container
{
    public function registerCoreContainerAliases(): void
    {
        foreach ([
            'app' => [self::class, Container::class, ...],
            'cache' => [\Illuminate\Cache\CacheManager::class, ...],
            'config' => [\Illuminate\Config\Repository::class, ...],
            'db' => [\Illuminate\Database\DatabaseManager::class, ...],
            // ...
        ] as $key => $aliases) {
            foreach ($aliases as $alias) {
                $this->alias($key, $alias);
            }
        }
    }
}

// === Container Singleton vs Classic Singleton fərqi ===

// Classic Singleton:
class Logger
{
    private static ?self $instance = null;
    private function __construct() {}
    public static function getInstance(): self { /* ... */ }
}
// Problemlər: test çətin, global state, tight coupling

// Container Singleton:
$this->app->singleton(LoggerInterface::class, FileLogger::class);
// Üstünlüklər: test asan, swap oluna bilər, interface-ə bağlı

// Test zamanı:
$this->app->singleton(LoggerInterface::class, NullLogger::class);
// və ya
$this->app->instance(LoggerInterface::class, $mockLogger);
```

---

## Singleton Anti-pattern Olaraq

Singleton çox vaxt anti-pattern hesab olunur. Səbəblər:

*Singleton çox vaxt anti-pattern hesab olunur. Səbəblər üçün kod nümunəsi:*
```php
<?php

// === Problem 1: Global State ===

class UserPreferences
{
    private static ?self $instance = null;
    private array $preferences = [];

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function set(string $key, mixed $value): void
    {
        $this->preferences[$key] = $value;
    }

    public function get(string $key): mixed
    {
        return $this->preferences[$key] ?? null;
    }
}

// Problem: Global state — hər yerdən dəyişdirilə bilər, izləmək çətindir
UserPreferences::getInstance()->set('theme', 'dark');
// Başqa faylda:
$theme = UserPreferences::getInstance()->get('theme'); // 'dark' — haradan gəldi?

// === Problem 2: Tight Coupling ===

class OrderService
{
    public function createOrder(array $data): void
    {
        // ❌ Tight coupling — Logger-ə birbaşa bağlıdır
        Logger::getInstance()->log("Creating order");
        DatabaseConnection::getInstance()->execute("INSERT INTO orders...");
    }
}

// OrderService-i test etmək üçün Logger və Database-i mock etmək mümkün deyil!

// ✅ ƏVƏZ: Dependency Injection
class OrderService
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly DatabaseConnection $db
    ) {}

    public function createOrder(array $data): void
    {
        $this->logger->log("Creating order");
        $this->db->execute("INSERT INTO orders...");
    }
}

// === Problem 3: Single Responsibility Principle pozulması ===

// Singleton class həm iş görür, həm öz lifecycle-ını idarə edir
class CacheManager
{
    private static ?self $instance = null;

    // Lifecycle management — bu SRP-ni pozur!
    private function __construct() {}
    private function __clone(): void {}
    public static function getInstance(): self { /* ... */ }

    // Əsl işi
    public function get(string $key): mixed { /* ... */ }
    public function set(string $key, mixed $value): void { /* ... */ }
}

// === Problem 4: Test Çətinliyi ===

class OrderServiceTest extends TestCase
{
    public function test_create_order(): void
    {
        // ❌ Singleton-u mock etmək olmur!
        // Logger::getInstance() həmişə real Logger qaytarır
        // Database::getInstance() həmişə real DB-yə qoşulur

        $service = new OrderService();
        $service->createOrder($data); // Real DB-yə yazır!
    }
}

// ✅ DI ilə test:
class OrderServiceTest extends TestCase
{
    public function test_create_order(): void
    {
        $mockLogger = $this->createMock(LoggerInterface::class);
        $mockDb = $this->createMock(DatabaseConnection::class);

        $mockDb->expects($this->once())
               ->method('execute')
               ->with($this->stringContains('INSERT'));

        $service = new OrderService($mockLogger, $mockDb);
        $service->createOrder($data); // Mock DB-yə yazır, real deyil
    }
}

// === Problem 5: Hidden Dependencies ===

class UserController
{
    public function show(int $id): array
    {
        // Bu method-un hansı dependency-ləri var?
        // Signature-dan görünmür!
        $user = Database::getInstance()->query("SELECT * FROM users WHERE id = ?", [$id]);
        Logger::getInstance()->log("User viewed: $id");
        Cache::getInstance()->set("user:$id", $user);

        return $user;
    }
}

// ✅ DI ilə dependency-lər aydın görünür:
class UserController
{
    public function __construct(
        private readonly DatabaseConnection $db,    // Görünür!
        private readonly LoggerInterface $logger,    // Görünür!
        private readonly CacheInterface $cache       // Görünür!
    ) {}
}
```

---

## Singleton vs Dependency Injection

*Singleton vs Dependency Injection üçün kod nümunəsi:*
```php
<?php

// === Singleton yanaşması ===

class PaymentService
{
    public function processPayment(float $amount): bool
    {
        $gateway = PaymentGateway::getInstance();     // Hidden dependency
        $logger = Logger::getInstance();               // Hidden dependency
        $cache = Cache::getInstance();                 // Hidden dependency

        $logger->log("Processing payment: $amount");

        if ($cache->has('payment_locked')) {
            return false;
        }

        return $gateway->charge($amount);
    }
}

// Test:
// MÜMKÜN DEYİL mock etmək (reflection ilə hack etmədən)

// === Dependency Injection yanaşması ===

class PaymentService
{
    public function __construct(
        private readonly PaymentGatewayInterface $gateway,  // Explicit dependency
        private readonly LoggerInterface $logger,            // Explicit dependency
        private readonly CacheInterface $cache               // Explicit dependency
    ) {}

    public function processPayment(float $amount): bool
    {
        $this->logger->log("Processing payment: $amount");

        if ($this->cache->has('payment_locked')) {
            return false;
        }

        return $this->gateway->charge($amount);
    }
}

// Test:
class PaymentServiceTest extends TestCase
{
    public function test_payment_processed_successfully(): void
    {
        $gateway = $this->createMock(PaymentGatewayInterface::class);
        $gateway->method('charge')->willReturn(true);

        $logger = $this->createMock(LoggerInterface::class);
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('has')->willReturn(false);

        $service = new PaymentService($gateway, $logger, $cache);

        $this->assertTrue($service->processPayment(99.99));
    }

    public function test_payment_blocked_when_locked(): void
    {
        $gateway = $this->createMock(PaymentGatewayInterface::class);
        $gateway->expects($this->never())->method('charge');

        $logger = $this->createMock(LoggerInterface::class);
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('has')->with('payment_locked')->willReturn(true);

        $service = new PaymentService($gateway, $logger, $cache);

        $this->assertFalse($service->processPayment(99.99));
    }
}

// Müqayisə cədvəli:
// | Xüsusiyyət     | Singleton          | DI (Container)     |
// |----------------|--------------------|--------------------|
// | Testability    | Çox çətin          | Çox asan           |
// | Coupling       | Tight              | Loose              |
// | Flexibility    | Dəyişmək çətin     | Asanlıqla dəyişir  |
// | Dependencies   | Gizli              | Aydın              |
// | SOLID          | SRP, DIP pozulur   | SOLID-ə uyğun      |
// | Performance    | Minimal overhead   | Minimal overhead   |
// | Lifecycle      | Class öz idarə edir| Container idarə edir|
```

---

## Nə Vaxt İstifadə Etməli

*Nə Vaxt İstifadə Etməli üçün kod nümunəsi:*
```php
<?php

// Singleton istifadə etmək məqsədəuyğun olan hallar:

// 1. Database Connection Pool (ayrılmış resurs)
$this->app->singleton('db', function ($app) {
    return new DatabaseManager($app, $app['db.factory']);
});

// 2. Configuration (bir dəfə oxunan, dəyişməyən məlumat)
$this->app->singleton('config', function () {
    return new Repository($items);
});

// 3. Cache Manager (connection paylaşılmalıdır)
$this->app->singleton('cache', function ($app) {
    return new CacheManager($app);
});

// 4. Event Dispatcher (application-wide tək instance)
$this->app->singleton('events', function ($app) {
    return new Dispatcher($app);
});

// 5. Logger (tək log destination)
$this->app->singleton('log', function ($app) {
    return new LogManager($app);
});

// Singleton İSTİFADƏ ETMƏMƏLİ olan hallar:

// ❌ Request-specific state saxlayan service-lər
$this->app->singleton(CartService::class); // YOX! scoped() istifadə edin
$this->app->scoped(CartService::class);    // BƏLİ! hər request üçün ayrı

// ❌ User-specific service-lər
$this->app->singleton(UserPreferences::class); // YOX! hər user üçün fərqlidir

// ❌ Mutable state saxlayan service-lər (Octane-da problem)
$this->app->singleton(RequestContext::class); // YOX! state leak
$this->app->scoped(RequestContext::class);    // BƏLİ!

// Qərar ağacı:
// 1. State saxlayırmı? -> Bəli: scoped() və ya bind()
// 2. Baha resurs istifadə edirsə? (DB, Redis connection) -> singleton()
// 3. Configuration-like oxuma edirsə? -> singleton()
// 4. Request-specific məlumat var? -> scoped()
// 5. Hər istifadədə yeni instance lazımdırsa? -> bind()
```

---

## Testing ilə Problemlər

*Testing ilə Problemlər üçün kod nümunəsi:*
```php
<?php

// === Classic Singleton Testing Problemi ===

class Logger
{
    private static ?self $instance = null;
    private array $logs = [];

    private function __construct() {}

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function log(string $message): void
    {
        $this->logs[] = [
            'message' => $message,
            'time' => date('Y-m-d H:i:s'),
        ];
        // Real dünyada fayla və ya database-ə yazır
    }

    public function getLogs(): array
    {
        return $this->logs;
    }
}

// Test problemi:
class LoggerTest extends TestCase
{
    public function test_log_message(): void
    {
        $logger = Logger::getInstance();
        $logger->log("Test message");

        $this->assertCount(1, $logger->getLogs());
        // ✅ Bu test keçir
    }

    public function test_another_log(): void
    {
        $logger = Logger::getInstance();
        $logger->log("Another message");

        // ❌ FAIL! getLogs() 2 qaytarır (əvvəlki test-dən qalan "Test message" + "Another message")
        // Çünki singleton instance test-lər arasında paylaşılır!
        $this->assertCount(1, $logger->getLogs());
    }
}

// === Workaround: resetInstance() metodu ===

class Logger
{
    // ... əvvəlki kod ...

    public static function resetInstance(): void
    {
        self::$instance = null;
    }
}

class LoggerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Logger::resetInstance(); // Hər test əvvəli sıfırla
    }
}
// Bu işləyir, amma anti-pattern-dir — test üçün production kodu dəyişdirik

// === Ən yaxşı həll: Container singleton ===

// Service Provider:
$this->app->singleton(LoggerInterface::class, FileLogger::class);

// Test:
class LoggerTest extends TestCase
{
    public function test_log_message(): void
    {
        // Hər test üçün təzə instance
        $logger = new FileLogger();
        $logger->log("Test message");

        $this->assertCount(1, $logger->getLogs());
    }

    // Və ya mock:
    public function test_service_uses_logger(): void
    {
        $mockLogger = $this->createMock(LoggerInterface::class);
        $mockLogger->expects($this->once())
                   ->method('log')
                   ->with('Order created');

        $this->app->instance(LoggerInterface::class, $mockLogger);

        $service = app(OrderService::class);
        $service->createOrder($data);
    }
}

// === Reflection ilə Singleton Test Hack ===
// Son çarə olaraq, mövcud singleton-ı reflection ilə sıfırlamaq:

function resetSingleton(string $className): void
{
    $ref = new ReflectionClass($className);
    $instanceProp = $ref->getProperty('instance');
    $instanceProp->setAccessible(true);
    $instanceProp->setValue(null, null);
}

// Test-də:
resetSingleton(Logger::class);
```

---

## Real-World Singleton Nümunələri

*Real-World Singleton Nümunələri üçün kod nümunəsi:*
```php
<?php

// === 1. Application Configuration ===

class AppConfig
{
    private static ?self $instance = null;
    private array $config;

    private function __construct()
    {
        // Config bir dəfə oxunur
        $this->config = [
            ...require '/config/app.php',
            ...require '/config/database.php',
            ...require '/config/cache.php',
        ];
    }

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }
}

// Laravel əvəzi:
$this->app->singleton('config', fn() => new Repository($items));
config('app.name'); // Singleton instance-dan oxuyur

// === 2. Event Manager ===

class EventManager
{
    private static ?self $instance = null;
    private array $listeners = [];

    private function __construct() {}

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    public function on(string $event, callable $listener): void
    {
        $this->listeners[$event][] = $listener;
    }

    public function emit(string $event, mixed ...$args): void
    {
        foreach ($this->listeners[$event] ?? [] as $listener) {
            $listener(...$args);
        }
    }
}

// Laravel əvəzi:
$this->app->singleton('events', fn($app) => new Dispatcher($app));
Event::listen(OrderCreated::class, SendConfirmation::class);

// === 3. Service Locator (Anti-pattern, amma real dünyada istifadə olunur) ===

class ServiceLocator
{
    private static ?self $instance = null;
    private array $services = [];

    private function __construct() {}

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    public function register(string $name, object $service): void
    {
        $this->services[$name] = $service;
    }

    public function get(string $name): object
    {
        if (!isset($this->services[$name])) {
            throw new \RuntimeException("Service not found: $name");
        }
        return $this->services[$name];
    }
}

// Laravel əvəzi: Service Container (daha yaxşı, DI dəstəyi var)
// app()->bind(...), app()->make(...)
```

---

## İntervyu Sualları

### Sual 1: Repository Pattern nədir?
**Cavab:** Repository Pattern data access logic-i business logic-dən ayıran design pattern-dir. Database/API sorğuları repository class-ında kapsullaşdırılır, business layer interface vasitəsilə repository ilə ünsiyyət qurur. Üstünlükləri: testability (mock edilə bilər), flexibility (data source dəyişdirilə bilər), SRP-yə uyğunluq, DRY. Laravel-də interface yaradılır, Eloquent-based implementation yazılır, Service Provider-dən bind olunur.

### Sual 2: Repository pattern-i Eloquent ilə istifadə etmək lazımdırmı?
**Cavab:** Mübahisəlidir. Eloquent özü Active Record pattern-dir və query builder, scopes, relationships ilə güclü abstraction təqdim edir. Kiçik/orta layihələrdə repository əlavə boilerplate yaradır. Böyük layihələrdə, xüsusilə çoxlu data source-lar (DB + API + Cache) olduqda, testability vacib olduqda, implementation dəyişə biləcəkdə faydalıdır. Alternativlər: Query Object pattern, Action classes, Eloquent Scopes.

### Sual 3: Repository-də Eloquent Model qaytarmaq doğrudurmu?
**Cavab:** Bu "leaky abstraction" problemidir. İdeal halda repository DTO qaytarmalıdır ki, business layer Eloquent-dən tam müstəqil olsun. Praktikada isə Eloquent Model qaytarmaq qəbul olunur, çünki DTO yaratmaq əlavə mapping layer tələb edir. Kompromis: interface-in return type-ında Eloquent Model istifadə olunsa, implementation Eloquent-ə bağlı qalır. Əsl abstraction lazımdırsa, DTO qaytarın.

### Sual 4: Criteria/Specification pattern nədir?
**Cavab:** Mürəkkəb query şərtlərini ayrı class-lara çıxarmaq üçün istifadə olunur. Hər Criteria bir filter/şərt təmsil edir (ActiveUsersCriteria, SearchCriteria, DateRangeCriteria). Repository-yə push edilir, query-yə tətbiq olunur. Üstünlükləri: reusable (eyni criteria fərqli repository-lərdə), composable (bir neçə criteria chain olunur), SRP (hər criteria bir iş görür), test edilə bilən.

### Sual 5: Singleton Pattern nədir? Necə implement olunur?
**Cavab:** Singleton — bir class-ın yalnız bir instance-ının olmasını təmin edən pattern. PHP-də: private constructor (new-i bloklamaq), private __clone (clone-u bloklamaq), static getInstance() metodu (lazy initialization), private __wakeup (unserialize-i bloklamaq). Laravel-də container singleton binding daha yaxşıdır: `$this->app->singleton(...)` — test edilə bilən, swap oluna bilən, interface-ə bağlana bilən.

### Sual 6: Singleton niyə anti-pattern hesab olunur?
**Cavab:** 5 əsas səbəb: 1) Global state — hər yerdən dəyişdirilə bilər, debug çətin, 2) Tight coupling — static çağırış ilə class-a birbaşa bağlanır, 3) SRP pozulur — həm iş görür, həm lifecycle idarə edir, 4) Test çətin — mock etmək mümkün deyil (static method), state test-lər arasında paylaşılır, 5) Hidden dependencies — method signature-dan dependency-lər görünmür. Həll: Service Container singleton binding + Dependency Injection.

### Sual 7: bind(), singleton() və scoped() arasındakı fərq?
**Cavab:** `bind()` — hər resolve-da yeni instance, stateless service-lər üçün. `singleton()` — ilk resolve-dan sonra həmişə eyni instance, shared resources üçün (DB, Cache). `scoped()` — hər request/lifecycle üçün ayrı instance, request-specific state saxlayan service-lər üçün. Octane/Queue worker-da singleton request-lər arasında paylaşılır (state leak riski), scoped isə hər request-də yenidən yaradılır.

### Sual 8: Repository pattern-in unit test üstünlüyü nədir?
**Cavab:** Repository interface-i mock etmək olduqca asandır. Service layer-ı test edərkən database-ə ehtiyac yoxdur — mock repository istifadə olunur. Məsələn: `$mockRepo->shouldReceive('findById')->with(1)->andReturn($fakeUser)`. Bu, test-i sürətli edir (I/O yoxdur), izolə edir (xarici asılılıq yoxdur), deterministik edir (database state-dən asılı deyil).

### Sual 9: Cached Repository necə implement olunur?
**Cavab:** Decorator pattern ilə. CachedRepository class-ı repository interface-ini implement edir, constructor-da real repository-ni və cache service-i alır. Read method-lar əvvəl cache yoxlayır, yoxdursa repository-dən oxuyub cache-ə yazır. Write method-lar (create/update/delete) repository-yə yazır, sonra əlaqəli cache-ləri təmizləyir. Service Provider-dən `extend()` ilə decorator chain qurulur. Cache tags istifadəsi invalidation-ı asanlaşdırır.

### Sual 10: Singleton-ı Octane-da istifadə etmək təhlükəsidirmi?
**Cavab:** Xeyr, singleton-lar Octane-da state leak yarada bilər. Octane-da eyni process çoxlu request-lər emal edir, singleton instance request-lər arasında paylaşılır. Request-specific state saxlayan singleton-lar (cart, user preferences, request context) data qarışmasına səbəb olur. Həll: `scoped()` binding istifadə edin — hər request üçün ayrı instance yaradılır. Database connection kimi stateless singleton-lar isə təhlükəsizdir.

---

## Anti-patternlər

**1. Repository-də Bütün Eloquent Metodlarını Expose Etmək**
Repository interface-inə `query()`, `newQuery()`, `getModel()` kimi Eloquent-specific metodlar əlavə etmək — abstraction pozulur, business layer Eloquent-ə bağlanır, interface dəyişdiriləndə bütün implementation-lar sinir. Repository interface yalnız domain dilindəki metodları (findActiveByEmail, findOverdueOrders) ehtiva etməlidir.

**2. Repository-ni Transaction-sız Yazma**
Bir Repository metodunda bir neçə əlaqəli yazma əməliyyatını (create order + create order_items + deduct inventory) `DB::transaction()` olmadan etmək — qismən yazılmış data qalır, sistem inconsistent vəziyyətə düşür. Repository write metodları atomik olmalıdır; lazım gəldikdə `DB::transaction()` daxilindən çağırılmalı, ya da Unit of Work pattern istifadə edilməlidir.

**3. Singleton-da Mutable State Saxlamaq**
Singleton service-in property-sinə (`$this->currentUser`, `$this->requestData`) hər request-də yeni dəyər yazmaq — singleton request-lər arasında yaşadığından sonrakı request əvvəlki request-in data-sını görür (xüsusilə Octane/queue işçilərində). Singleton-lar stateless olmalıdır; request-specific data constructor injection, method parameter, ya da `scoped()` binding ilə idarə olunmalıdır.

**4. Classic Singleton-ı Unit Test-lərdə Reset Etməmək**
PHP-nin eyni prosesindən çalışan test suite-lərdə Singleton-ın static instance-ı test-lər arasında qalır — bir testin yaratdığı state digər testi kirlədir, test sırası əhəmiyyət kəsb edir. Service Container `singleton()` binding istifadə edin; `$this->app->forgetInstance()` ilə test-lər arasında reset edin.

**5. Repository Olmayan Yerdə Repository İstifadə Etmək**
Sadə reporting sorğuları, analytics view-ları üçün Repository yaratmaq — 20 JOIN-lu, 15 parametrli sorğunu Repository interface-inə yerləşdirmək method signature-ı mürəkkəbləşdirir. Reporting/analytics üçün ayrıca Query Object, ReadModel, ya da `DB::select()` ilə raw SQL daha uyğundur.

**6. Lazy Initialization Olmayan Singleton**
Singleton-ı ilk istifadədə deyil, class define edilən anda başlatmaq — ağır resurs (DB connection, 3rd party SDK) hətta lazım olmasa belə yüklənir. Lazy initialization (ilk `getInstance()` çağırışında yaratmaq) ya da Service Container-in lazy binding mexanizmi istifadə edilməlidir.
