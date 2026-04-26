# Database Testing (Middle)
## İcmal

Database testing, verilənlər bazası əməliyyatlarının düzgün işlədiyini yoxlamaq prosesidir.
CRUD (Create, Read, Update, Delete) əməliyyatları, migration-lar, relationship-lər, query-lər
və data integrity-nin test edilməsini əhatə edir.

Laravel-də database testing güclü alətlərlə dəstəklənir: Factory-lər test data yaradır,
`RefreshDatabase` trait-i hər testdən sonra database-i təmizləyir, və `assertDatabaseHas`
kimi method-lar database state-ini yoxlayır. Bu testlər integration test kateqoriyasına aiddir
çünki real database ilə işləyir.

## Niyə Vacibdir

1. **Data integrity** - Məlumatların düzgün saxlanıldığını təmin edir
2. **Query doğruluğu** - Mürəkkəb query-lərin düzgün nəticə qaytardığını yoxlayır
3. **Migration təhlükəsizliyi** - Schema dəyişikliklərinin mövcud data-nı pozmadığını təmin edir
4. **Relationship doğruluğu** - Model əlaqələrinin düzgün işlədiyini yoxlayır
5. **Performance** - N+1 query problemlərini və yavaş query-ləri tapır

## Əsas Anlayışlar

### Test Database Strategiyaları

```
1. RefreshDatabase (Laravel)
   ├── Hər testdən əvvəl migration işlədir
   ├── Transaction istifadə edir (sürətli)
   └── Ən çox istifadə olunan yanaşma

2. DatabaseTransactions (Laravel)
   ├── Hər testi transaction-a bükür
   ├── Test bitdikdə rollback edir
   └── Migration işlətmir (database mövcud olmalıdır)

3. DatabaseMigrations (Laravel)
   ├── Hər testdən əvvəl migrate:fresh işlədir
   ├── Ən yavaş yanaşma
   └── Nadir hallarda lazımdır

4. In-Memory SQLite
   ├── RAM-da işləyir, çox sürətli
   ├── Bəzi MySQL/PostgreSQL feature-ləri dəstəkləmir
   └── Sadə testlər üçün uyğundur
```

### Laravel Database Assertions

| Method | Məqsəd |
|--------|--------|
| `assertDatabaseHas($table, $data)` | Cədvəldə data var |
| `assertDatabaseMissing($table, $data)` | Cədvəldə data yoxdur |
| `assertDatabaseCount($table, $count)` | Cədvəldə sətir sayı |
| `assertSoftDeleted($table, $data)` | Soft delete edilib |
| `assertNotSoftDeleted($table, $data)` | Soft delete edilməyib |
| `assertModelExists($model)` | Model database-də var |
| `assertModelMissing($model)` | Model database-də yoxdur |

### Factory Pattern

```
Factory → Model üçün test data generatoru

UserFactory::new()
  →  User model-i üçün fake data yaradır
  →  State-lər ilə variantlar təmin edir
  →  Relationship-ləri avtomatik yaradır
```

## Praktik Baxış

### Best Practices

1. **RefreshDatabase istifadə edin** - Ən etibarlı test isolation strategiyasıdır
2. **Factory-lər istifadə edin** - Manual data yaratmaq əvəzinə factory pattern istifadə edin
3. **Yalnız lazımi data yaradın** - Hər testdə minimum data ilə işləyin
4. **State method-ları yazın** - Təkrarlanan factory konfiqurasiyalarını state-ə çıxarın
5. **Schema testləri yazın** - Migration-ların düzgün işlədiyini yoxlayın
6. **Sequence istifadə edin** - `Sequence` class ilə fərqli data variantları yaradın

### Anti-Patterns

1. **Testlər arası data paylaşmaq** - Hər test öz data-sını yaratmalıdır
2. **Production database-ə qoşulmaq** - Həmişə ayrı test database istifadə edin
3. **Hardcoded ID-lər** - Auto-increment ID-lərə etibar etməyin
4. **Böyük seeder-lərdən asılılıq** - Testlər seeder-siz işləməlidir
5. **Bütün column-ları assert etmək** - Yalnız test üçün vacib column-ları yoxlayın
6. **Database state-ini manual təmizləmək** - RefreshDatabase/DatabaseTransactions istifadə edin

## Nümunələr

### Factory Yaratmaq

```php
<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ];
    }

    // State methods
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'admin',
        ]);
    }
}
```

### Relationship Factory

```php
<?php

namespace Database\Factories;

use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PostFactory extends Factory
{
    protected $model = Post::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(),
            'body' => fake()->paragraphs(3, true),
            'status' => 'draft',
            'published_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

    public function withComments(int $count = 3): static
    {
        return $this->has(
            \App\Models\Comment::factory()->count($count),
            'comments'
        );
    }
}
```

## Praktik Tapşırıqlar

### Əsas Database Test Nümunələri

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Post;
use App\Models\Comment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostDatabaseTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_creates_a_post_in_database(): void
    {
        $user = User::factory()->create();

        $post = Post::create([
            'user_id' => $user->id,
            'title' => 'Database Testing',
            'body' => 'Learning database testing with Laravel.',
            'status' => 'draft',
        ]);

        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'title' => 'Database Testing',
            'user_id' => $user->id,
        ]);

        $this->assertModelExists($post);
    }

    /** @test */
    public function it_soft_deletes_a_post(): void
    {
        $post = Post::factory()->create();

        $post->delete();

        $this->assertSoftDeleted('posts', ['id' => $post->id]);
        $this->assertDatabaseHas('posts', ['id' => $post->id]);
        $this->assertDatabaseCount('posts', 1); // hələ də database-dədir
    }

    /** @test */
    public function it_permanently_deletes_a_post(): void
    {
        $post = Post::factory()->create();

        $post->forceDelete();

        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
        $this->assertModelMissing($post);
        $this->assertDatabaseCount('posts', 0);
    }

    /** @test */
    public function factory_creates_related_models(): void
    {
        $user = User::factory()
            ->has(Post::factory()->count(3))
            ->create();

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('posts', 3);
        $this->assertCount(3, $user->posts);
    }

    /** @test */
    public function it_uses_factory_states(): void
    {
        $activeUser = User::factory()->create();
        $inactiveUser = User::factory()->inactive()->create();
        $admin = User::factory()->admin()->create();

        $this->assertTrue($activeUser->is_active);
        $this->assertFalse($inactiveUser->is_active);
        $this->assertEquals('admin', $admin->role);
    }
}
```

### Relationship Testing

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Post;
use App\Models\Comment;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RelationshipTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function user_has_many_posts(): void
    {
        $user = User::factory()
            ->has(Post::factory()->count(3))
            ->create();

        $this->assertCount(3, $user->posts);
        $this->assertInstanceOf(Post::class, $user->posts->first());
    }

    /** @test */
    public function post_belongs_to_user(): void
    {
        $post = Post::factory()->create();

        $this->assertInstanceOf(User::class, $post->user);
        $this->assertEquals($post->user_id, $post->user->id);
    }

    /** @test */
    public function post_has_many_comments(): void
    {
        $post = Post::factory()
            ->has(Comment::factory()->count(5))
            ->create();

        $this->assertCount(5, $post->comments);
    }

    /** @test */
    public function post_belongs_to_many_tags(): void
    {
        $post = Post::factory()->create();
        $tags = Tag::factory()->count(3)->create();

        $post->tags()->attach($tags);

        $this->assertCount(3, $post->tags);
        $this->assertDatabaseCount('post_tag', 3);
    }

    /** @test */
    public function cascade_delete_removes_related_records(): void
    {
        $user = User::factory()
            ->has(Post::factory()->count(2))
            ->create();

        $postIds = $user->posts->pluck('id');
        $user->delete();

        foreach ($postIds as $postId) {
            $this->assertDatabaseMissing('posts', ['id' => $postId]);
        }
    }
}
```

### Query və Scope Testing

```php
<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostQueryTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function published_scope_returns_only_published_posts(): void
    {
        Post::factory()->count(3)->published()->create();
        Post::factory()->count(2)->create(); // draft

        $published = Post::published()->get();

        $this->assertCount(3, $published);
        $published->each(fn ($post) =>
            $this->assertEquals('published', $post->status)
        );
    }

    /** @test */
    public function search_scope_filters_by_title(): void
    {
        Post::factory()->create(['title' => 'Laravel Testing Guide']);
        Post::factory()->create(['title' => 'Vue.js Tutorial']);
        Post::factory()->create(['title' => 'Advanced Laravel Patterns']);

        $results = Post::search('Laravel')->get();

        $this->assertCount(2, $results);
    }

    /** @test */
    public function it_eager_loads_relationships_without_n_plus_one(): void
    {
        User::factory()
            ->has(Post::factory()->count(3))
            ->count(5)
            ->create();

        // N+1 problemi olmadan
        $queryCount = 0;
        \DB::listen(function () use (&$queryCount) {
            $queryCount++;
        });

        $users = User::with('posts')->get();
        $users->each(fn ($user) => $user->posts->count());

        // 2 query olmalıdır: 1 users, 1 posts
        $this->assertEquals(2, $queryCount);
    }
}
```

### Seeder Testing

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeederTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function role_seeder_creates_default_roles(): void
    {
        $this->seed(RoleSeeder::class);

        $this->assertDatabaseHas('roles', ['name' => 'admin']);
        $this->assertDatabaseHas('roles', ['name' => 'editor']);
        $this->assertDatabaseHas('roles', ['name' => 'user']);
        $this->assertDatabaseCount('roles', 3);
    }

    /** @test */
    public function seeder_is_idempotent(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(RoleSeeder::class);

        // İki dəfə işlətdikdə duplicate yaratmamalıdır
        $this->assertDatabaseCount('roles', 3);
    }
}
```

### Migration Testing

```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MigrationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function posts_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('posts'));
        $this->assertTrue(Schema::hasColumns('posts', [
            'id', 'user_id', 'title', 'body', 'status',
            'published_at', 'created_at', 'updated_at', 'deleted_at',
        ]));
    }

    /** @test */
    public function posts_table_has_foreign_key_to_users(): void
    {
        // Foreign key constraint test
        $this->expectException(\Illuminate\Database\QueryException::class);

        \DB::table('posts')->insert([
            'user_id' => 99999, // mövcud olmayan user
            'title' => 'Test',
            'body' => 'Body',
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
```

## Ətraflı Qeydlər

### 1. RefreshDatabase və DatabaseTransactions arasındakı fərq nədir?
**Cavab:** `RefreshDatabase` hər test suite başlanğıcında `migrate:fresh` işlədir, sonra hər testi transaction-a bükür. `DatabaseTransactions` migration işlətmir, sadəcə hər testi transaction-a bükür və sonra rollback edir. RefreshDatabase daha etibarlıdır çünki hər dəfə təmiz schema ilə başlayır.

### 2. Factory state nədir və nə üçün istifadə olunur?
**Cavab:** Factory state, factory-nin default dəyərlərini override edən named method-dur. Məsələn `User::factory()->admin()->create()` admin state-ini tətbiq edir. Bu, fərqli ssenarilar üçün data yaratmağı asanlaşdırır. `$this->state(fn (array $attributes) => ['role' => 'admin'])` şəklində təyin edilir.

### 3. assertDatabaseHas nə yoxlayır?
**Cavab:** `assertDatabaseHas('table_name', ['column' => 'value'])` verilən cədvəldə verilən şərtlərə uyğun ən az bir sətirin mövcud olduğunu yoxlayır. Bütün key-value pair-lər AND şərti ilə yoxlanır. Yalnız göstərilən column-lar yoxlanır, digər column-lar nəzərə alınmır.

### 4. N+1 query problemini testlərdə necə taparsınız?
**Cavab:** Laravel-də `DB::listen()` ilə query sayını izləyirik. `laravel-query-detector` package-i N+1 query-ləri avtomatik tapır. Testdə gözlənilən query sayını assert edə bilərik. `preventLazyLoading()` ilə lazy loading-i qadağan edib exception atacaq şəkildə konfiqurasiya edə bilərik.

### 5. In-memory SQLite ilə test etmənin üstünlükləri və çatışmazlıqları nələrdir?
**Cavab:** Üstünlükləri: çox sürətlidir, disk I/O yoxdur, hər test təmiz database ilə başlayır. Çatışmazlıqları: MySQL/PostgreSQL-ə xas feature-lər (JSON column, full-text search, specific data types) dəstəklənmir, production database-dən fərqli davranış göstərə bilər.

### 6. Seeder-in idempotent olması nə deməkdir?
**Cavab:** Idempotent seeder neçə dəfə işlədilsə də eyni nəticəni verir. Məsələn `firstOrCreate` istifadə edən seeder iki dəfə işlədildikdə duplicate yaratmaz. Bu testing üçün vacibdir çünki testlər seeder-i təkrar işlədə bilər.

### 7. Factory-lərdə relationship necə yaradılır?
**Cavab:** Üç yol var: 1) `User::factory()->has(Post::factory()->count(3))->create()` - has method ilə, 2) `Post::factory()->for(User::factory())->create()` - for method ilə parent, 3) Factory definition-da `'user_id' => User::factory()` - avtomatik. Birinci yol ən oxunaqlıdır.

### 8. Transaction rollback testing nə üçün istifadə olunur?
**Cavab:** Hər testi database transaction-a bükmək testlər arası izolyasiya təmin edir. Test bitdikdə transaction rollback edilir, database əvvəlki vəziyyətinə qayıdır. Bu testləri sürətləndirir (DELETE/TRUNCATE-dən sürətli) və testlər bir-birini təsir etmir.

## Əlaqəli Mövzular

- [Integration Testing (Junior)](03-integration-testing.md)
- [Database Testing (Middle)](10-database-testing.md)
- [Test Data Management (Senior)](33-test-data-management.md)
- [Concurrency & Race Condition Testing (Senior)](35-concurrency-race-testing.md)
- [Testing Anti-Patterns (Senior)](27-testing-anti-patterns.md)
