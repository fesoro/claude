# Verilənlər Bazasının Doldurulması və Factory-lər

> **Seviyye:** Intermediate ⭐⭐

## Giriş

Tətbiq inkişaf edərkən test datası lazım olur — istər development mühitində, istər avtomatik testlərdə. Laravel-də bu iş üçün Seeder və Factory sistemi daxili olaraq mövcuddur. Spring-də isə rəsmi bir seeding sistemi yoxdur — əvəzinə `CommandLineRunner`, Flyway/Liquibase seed skriptləri və ya xüsusi `DataInitializer` sinifləri istifadə olunur.

---

## Spring-də istifadəsi

### CommandLineRunner ilə ilkin data yükləmə

Ən sadə yanaşma — tətbiq başlayanda data əlavə etmək:

```java
@Component
@Profile("dev") // Yalnız development mühitində işləsin
public class DataInitializer implements CommandLineRunner {

    private final UserRepository userRepository;
    private final CategoryRepository categoryRepository;
    private final ProductRepository productRepository;

    public DataInitializer(UserRepository userRepository,
                           CategoryRepository categoryRepository,
                           ProductRepository productRepository) {
        this.userRepository = userRepository;
        this.categoryRepository = categoryRepository;
        this.productRepository = productRepository;
    }

    @Override
    public void run(String... args) {
        if (userRepository.count() > 0) {
            return; // Artıq data varsa, təkrar yükləmə
        }

        // İstifadəçilər
        User admin = new User("Admin", "admin@example.com", "ADMIN");
        admin.setPassword(new BCryptPasswordEncoder().encode("password"));
        userRepository.save(admin);

        List<User> users = new ArrayList<>();
        for (int i = 1; i <= 20; i++) {
            User user = new User(
                "User " + i,
                "user" + i + "@example.com",
                "USER"
            );
            user.setPassword(new BCryptPasswordEncoder().encode("password"));
            users.add(user);
        }
        userRepository.saveAll(users);

        // Kateqoriyalar
        List<String> categoryNames = List.of("Elektronika", "Geyim", "Kitablar", "Ev əşyaları");
        List<Category> categories = categoryNames.stream()
            .map(name -> new Category(name, name.toLowerCase() + "-slug"))
            .toList();
        categoryRepository.saveAll(categories);

        // Məhsullar
        Random random = new Random();
        List<Product> products = new ArrayList<>();
        for (int i = 1; i <= 50; i++) {
            Category category = categories.get(random.nextInt(categories.size()));
            Product product = new Product(
                "Məhsul " + i,
                "Bu məhsul " + i + " üçün açıqlamadır",
                BigDecimal.valueOf(10 + random.nextDouble() * 990).setScale(2, RoundingMode.HALF_UP),
                category
            );
            product.setStock(random.nextInt(100));
            products.add(product);
        }
        productRepository.saveAll(products);

        System.out.println("Test datası yükləndi: " + users.size() + " istifadəçi, "
            + categories.size() + " kateqoriya, " + products.size() + " məhsul");
    }
}
```

### Faker kitabxanası ilə (Java Faker)

```xml
<dependency>
    <groupId>net.datafaker</groupId>
    <artifactId>datafaker</artifactId>
    <version>2.1.0</version>
    <scope>test</scope>
</dependency>
```

```java
@Component
@Profile("dev")
public class FakerDataInitializer implements CommandLineRunner {

    private final UserRepository userRepository;
    private final Faker faker = new Faker(new Locale("az")); // Azərbaycan locale yoxdursa en istifadə edin

    public FakerDataInitializer(UserRepository userRepository) {
        this.userRepository = userRepository;
    }

    @Override
    public void run(String... args) {
        if (userRepository.count() > 0) return;

        List<User> users = new ArrayList<>();
        for (int i = 0; i < 100; i++) {
            User user = new User();
            user.setName(faker.name().fullName());
            user.setEmail(faker.internet().emailAddress());
            user.setPhone(faker.phoneNumber().cellPhone());
            user.setAddress(faker.address().fullAddress());
            user.setCity(faker.address().city());
            user.setBio(faker.lorem().paragraph(3));
            user.setCreatedAt(faker.date()
                .past(365, java.util.concurrent.TimeUnit.DAYS)
                .toInstant()
                .atZone(ZoneId.systemDefault())
                .toLocalDateTime());
            users.add(user);
        }
        userRepository.saveAll(users);
    }
}
```

### Flyway ilə SQL seed skriptləri

Flyway miqrasiya aləti ilə seed datası SQL olaraq əlavə edilə bilər:

```
src/main/resources/db/migration/
├── V1__create_users_table.sql
├── V2__create_products_table.sql
└── V3__seed_initial_data.sql        <-- seed data
```

```sql
-- V3__seed_initial_data.sql
INSERT INTO categories (name, slug) VALUES
    ('Elektronika', 'elektronika'),
    ('Geyim', 'geyim'),
    ('Kitablar', 'kitablar');

INSERT INTO users (name, email, password, role) VALUES
    ('Admin', 'admin@example.com', '$2a$10$...hashed...', 'ADMIN'),
    ('Demo User', 'demo@example.com', '$2a$10$...hashed...', 'USER');

INSERT INTO products (name, price, category_id, stock) VALUES
    ('Laptop', 1500.00, 1, 25),
    ('Telefon', 800.00, 1, 50),
    ('T-shirt', 25.00, 2, 200);
```

Problem: Flyway miqrasiyaları bir dəfə işləyir və geri qaytarılmır. Seed datanı ayrı profilə qoymaq daha yaxşıdır:

```
src/main/resources/
├── db/migration/          # Əsas miqrasiyalar (həmişə)
│   ├── V1__create_users.sql
│   └── V2__create_products.sql
└── db/seed/               # Yalnız dev mühitində
    └── R__seed_data.sql   # R__ prefiksi = Repeatable migration
```

```yaml
# application-dev.yml
spring:
  flyway:
    locations:
      - classpath:db/migration
      - classpath:db/seed
```

### Test-lərdə istifadə — Test Builder pattern

Spring-də factory sistemi olmadığı üçün adətən Builder pattern istifadə olunur:

```java
// Test builder sinfi
public class UserTestBuilder {

    private String name = "Default User";
    private String email = "default@test.com";
    private String role = "USER";
    private String password = "password";
    private boolean active = true;

    public static UserTestBuilder aUser() {
        return new UserTestBuilder();
    }

    public UserTestBuilder withName(String name) {
        this.name = name;
        return this;
    }

    public UserTestBuilder withEmail(String email) {
        this.email = email;
        return this;
    }

    public UserTestBuilder asAdmin() {
        this.role = "ADMIN";
        return this;
    }

    public UserTestBuilder inactive() {
        this.active = false;
        return this;
    }

    public User build() {
        User user = new User();
        user.setName(name);
        user.setEmail(email);
        user.setRole(role);
        user.setPassword(password);
        user.setActive(active);
        return user;
    }

    public User buildAndSave(UserRepository repository) {
        return repository.save(build());
    }
}

// Test-də istifadə
@SpringBootTest
class UserServiceTest {

    @Autowired
    private UserRepository userRepository;

    @Test
    void shouldFindActiveAdmins() {
        UserTestBuilder.aUser().withName("Admin 1").asAdmin().buildAndSave(userRepository);
        UserTestBuilder.aUser().withName("Admin 2").asAdmin().inactive().buildAndSave(userRepository);
        UserTestBuilder.aUser().withName("Regular").buildAndSave(userRepository);

        List<User> activeAdmins = userRepository.findByRoleAndActiveTrue("ADMIN");
        assertThat(activeAdmins).hasSize(1);
        assertThat(activeAdmins.get(0).getName()).isEqualTo("Admin 1");
    }
}
```

---

## Laravel-də istifadəsi

### Seeder-lər

Seeder yaratmaq:

```bash
php artisan make:seeder CategorySeeder
php artisan make:seeder UserSeeder
php artisan make:seeder ProductSeeder
```

```php
// database/seeders/CategorySeeder.php
class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Elektronika', 'slug' => 'elektronika', 'icon' => 'laptop'],
            ['name' => 'Geyim', 'slug' => 'geyim', 'icon' => 'shirt'],
            ['name' => 'Kitablar', 'slug' => 'kitablar', 'icon' => 'book'],
            ['name' => 'Ev əşyaları', 'slug' => 'ev-esyalari', 'icon' => 'home'],
        ];

        foreach ($categories as $category) {
            Category::create($category);
        }
    }
}
```

```php
// database/seeders/UserSeeder.php
class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Admin istifadəçi — həmişə eyni
        User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'role' => 'admin',
        ]);

        // 50 adi istifadəçi
        User::factory(50)->create();

        // 5 aktiv olmayan istifadəçi
        User::factory(5)->inactive()->create();
    }
}
```

```php
// database/seeders/DatabaseSeeder.php — Əsas seeder
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Sıra vacibdir — əvvəlcə kateqoriyalar, sonra məhsullar
        $this->call([
            CategorySeeder::class,
            UserSeeder::class,
            ProductSeeder::class,
            OrderSeeder::class,
        ]);
    }
}
```

Seeder-i işlətmək:

```bash
# Bütün seeder-lər
php artisan db:seed

# Müəyyən seeder
php artisan db:seed --class=UserSeeder

# Miqrasiya + seed birlikdə
php artisan migrate:fresh --seed
```

### Factory-lər — Test data generasiyasının əsası

Factory yaratmaq:

```bash
php artisan make:factory UserFactory
php artisan make:factory ProductFactory
php artisan make:factory OrderFactory
```

```php
// database/factories/UserFactory.php
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make('password'), // Hər factory üçün eyni şifrə
            'phone' => fake()->phoneNumber(),
            'avatar' => fake()->imageUrl(200, 200, 'people'),
            'bio' => fake()->paragraph(),
            'role' => 'user',
            'is_active' => true,
            'last_login_at' => fake()->dateTimeBetween('-30 days', 'now'),
            'remember_token' => Str::random(10),
        ];
    }

    // State-lər — factory-nin variantları
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'admin',
            'email' => fake()->unique()->safeEmail(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
            'last_login_at' => null,
        ]);
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function withAvatar(): static
    {
        return $this->state(fn (array $attributes) => [
            'avatar' => fake()->imageUrl(400, 400, 'people'),
        ]);
    }
}
```

```php
// database/factories/ProductFactory.php
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'slug' => fake()->unique()->slug(),
            'description' => fake()->paragraphs(3, true),
            'price' => fake()->randomFloat(2, 5, 2000),
            'compare_price' => null,
            'sku' => fake()->unique()->bothify('???-#####'),
            'stock' => fake()->numberBetween(0, 500),
            'category_id' => Category::factory(),  // Avtomatik əlaqəli category yaradır
            'is_published' => true,
            'published_at' => now(),
        ];
    }

    public function onSale(): static
    {
        return $this->state(function (array $attributes) {
            $originalPrice = $attributes['price'];
            return [
                'compare_price' => $originalPrice,
                'price' => round($originalPrice * 0.8, 2), // 20% endirim
            ];
        });
    }

    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock' => 0,
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_published' => false,
            'published_at' => null,
        ]);
    }
}
```

```php
// database/factories/OrderFactory.php
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'order_number' => 'ORD-' . fake()->unique()->numerify('######'),
            'status' => fake()->randomElement(['pending', 'processing', 'shipped', 'delivered']),
            'subtotal' => fake()->randomFloat(2, 20, 5000),
            'tax' => fn (array $attributes) => round($attributes['subtotal'] * 0.18, 2),
            'total' => fn (array $attributes) => $attributes['subtotal'] + round($attributes['subtotal'] * 0.18, 2),
            'shipping_address' => fake()->address(),
            'notes' => fake()->optional(0.3)->sentence(), // 30% ehtimalla not
        ];
    }

    public function delivered(): static
    {
        return $this->state(fn () => [
            'status' => 'delivered',
            'delivered_at' => fake()->dateTimeBetween('-7 days', 'now'),
        ]);
    }

    // Əlaqəli məhsullarla birlikdə
    public function withItems(int $count = 3): static
    {
        return $this->afterCreating(function (Order $order) use ($count) {
            OrderItem::factory($count)->create([
                'order_id' => $order->id,
            ]);
        });
    }
}
```

### Factory istifadə nümunələri

```php
// Tək obyekt
$user = User::factory()->create();

// Çoxlu obyekt
$users = User::factory(10)->create();

// Yaddaşda yaratmaq (DB-yə yazmadan) — test üçün
$user = User::factory()->make();

// State-ləri birləşdirmək
$user = User::factory()
    ->admin()
    ->unverified()
    ->create();

// Xüsusi dəyərlər
$user = User::factory()->create([
    'name' => 'Orxan',
    'email' => 'orxan@test.com',
]);

// Əlaqəli data
$user = User::factory()
    ->has(Order::factory(3)->withItems(2))  // 3 sifariş, hər birində 2 məhsul
    ->has(Post::factory(5))                  // 5 post
    ->create();

// Və ya for() ilə
$posts = Post::factory(10)
    ->for(User::factory()->admin())  // Hamısı eyni admin üçün
    ->for(Category::factory(['name' => 'Tech']))
    ->create();
```

### Sequence — Ardıcıl dəyərlər

```php
$users = User::factory(4)
    ->state(new Sequence(
        ['role' => 'admin'],
        ['role' => 'editor'],
        ['role' => 'author'],
        ['role' => 'user'],
    ))
    ->create();

// Daha mürəkkəb sequence
$products = Product::factory(6)
    ->sequence(fn (Sequence $sequence) => [
        'name' => 'Məhsul ' . ($sequence->index + 1),
        'sort_order' => $sequence->index,
    ])
    ->create();
```

### Mürəkkəb Seeder nümunəsi

```php
class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $categories = Category::all();

        $categories->each(function (Category $category) {
            // Hər kateqoriya üçün 10-20 məhsul
            $count = rand(10, 20);

            Product::factory($count)
                ->for($category)
                ->create();

            // Hər kateqoriyada 2-3 endirimli məhsul
            Product::factory(rand(2, 3))
                ->for($category)
                ->onSale()
                ->create();

            // Hər kateqoriyada 1-2 stokda olmayan
            Product::factory(rand(1, 2))
                ->for($category)
                ->outOfStock()
                ->create();
        });

        $this->command->info(Product::count() . ' məhsul yaradıldı');
    }
}
```

### Test-lərdə Factory istifadəsi

```php
class OrderServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_place_order(): void
    {
        $user = User::factory()->create();
        $products = Product::factory(3)->create(['stock' => 10]);

        $orderData = [
            'items' => $products->map(fn ($p) => [
                'product_id' => $p->id,
                'quantity' => 2,
            ])->toArray(),
            'shipping_address' => '123 Test Street',
        ];

        $order = app(OrderService::class)->placeOrder($user, $orderData);

        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'status' => 'pending',
        ]);
        $this->assertEquals(3, $order->items()->count());
        $products->each(fn ($p) => $this->assertEquals(8, $p->fresh()->stock));
    }

    public function test_cannot_order_out_of_stock_product(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->outOfStock()->create();

        $this->expectException(OutOfStockException::class);

        app(OrderService::class)->placeOrder($user, [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);
    }
}
```

---

## Əsas fərqlər

| Xüsusiyyət | Spring | Laravel |
|---|---|---|
| Seeder sistemi | Yoxdur (CommandLineRunner, Flyway ilə əl ilə) | Daxili Seeder sinifləri |
| Factory sistemi | Yoxdur (Builder pattern əl ilə yazılır) | Daxili Factory sinifləri |
| Fake data | DataFaker (ayrıca dependency) | Faker daxili inteqrasiya (`fake()`) |
| State-lər | Builder metodu olaraq əl ilə | `state()` metodu ilə deklarativ |
| Sequence | Yoxdur | `Sequence` sinfi ilə daxili |
| Əlaqəli data | Əl ilə yaradılır | `has()`, `for()` ilə avtomatik |
| DB sıfırlama | `@Sql` annotasiyası, `@Transactional` | `RefreshDatabase` trait |
| Əmr ilə seed | Yoxdur | `php artisan db:seed` |
| Miqrasiya + seed | Ayrı-ayrı | `migrate:fresh --seed` bir əmrdə |

---

## Niyə belə fərqlər var?

**Laravel ActiveRecord pattern istifadə edir.** Hər model öz cədvəli ilə birbaşa bağlıdır — `User::create()`, `User::factory()` kimi əməliyyatlar modelin özündə mümkündür. Bu, factory və seeder sistemini çox sadə edir.

**Spring Repository pattern istifadə edir.** Entity sinfi sadəcə data daşıyıcısıdır, verilənlər bazası əməliyyatları Repository interface vasitəsilə aparılır. Bu, daha çox ayrılma (separation) verir, amma factory sistemi yaratmağı çətinləşdirir — çünki obyekt yaratmaq və onu saxlamaq ayrı addımlardır.

**PHP-nin qısamüddətli həyat dövrü** seeder sistemini zəruri edir. Hər `php artisan migrate:fresh --seed` əmri verilənlər bazasını sıfırdan qurur. Java tətbiqləri isə uzunmüddətli işlədiyi üçün, test datası adətən yalnız test-lərdə və ya development profilində lazım olur.

**Laravel-in "Convention over Configuration" fəlsəfəsi** factory və seeder-ləri standartlaşdırıb. Spring-də isə hər komanda öz yanaşmasını seçir — bəziləri Flyway seed, bəziləri CommandLineRunner, bəziləri Testcontainers istifadə edir.

---

## Hansı framework-də var, hansında yoxdur?

**Yalnız Laravel-də:**
- `Factory` sinfi ilə deklarativ test data generasiyası
- `state()` ilə factory variantları
- `Sequence` ilə ardıcıl data
- `has()` / `for()` ilə əlaqəli data avtomatik yaratma
- `fake()` helper ilə Faker inteqrasiyası
- `RefreshDatabase` trait ilə test-lərdə avtomatik DB sıfırlama
- `php artisan db:seed` əmri

**Yalnız Spring-də:**
- Flyway/Liquibase ilə versiyalanmış seed skriptləri (SQL əsaslı)
- `@Sql` annotasiyası ilə test-ə xüsusi SQL faylı qoşmaq
- `@Transactional` ilə test sonrası avtomatik rollback
- Testcontainers ilə real verilənlər bazası konteynerlərində test
- `@Profile("dev")` ilə mühitə görə fərqli data yükləmə
