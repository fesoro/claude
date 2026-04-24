# Migrasiyanlar

> **Seviyye:** Intermediate ⭐⭐

## Giris

Database migrasiyanlar verilenler bazasinin strukturunu versiya kontrolunda saxlamaga imkan verir. Komandadaki her bir developer eyni database strukturunu asanliqla yarada bilir. Spring ekosisteminde Flyway ve ya Liquibase istifade olunur, Laravel-de ise daxili migrasiya sistemi var.

## Spring-de istifadesi

Spring-in ozunde daxili migrasiya sistemi yoxdur. Bunun evezine Flyway ve ya Liquibase kimi xarici kutuphaneler inteqrasiya edilir.

### Flyway ile migrasiyanlar

#### Asililiq elave etmek (pom.xml)

```xml
<dependency>
    <groupId>org.flywaydb</groupId>
    <artifactId>flyway-core</artifactId>
</dependency>
```

#### application.yml konfiqurasiyasi

```yaml
spring:
  flyway:
    enabled: true
    locations: classpath:db/migration
    baseline-on-migrate: true
```

#### Migrasiya fayllarinin adlandirilmasi

Flyway-de fayl adi cox vacibdir. Format: `V{versiya}__{ad}.sql`

```
src/main/resources/db/migration/
    V1__create_users_table.sql
    V2__create_posts_table.sql
    V3__add_email_index_to_users.sql
    V4__create_departments_table.sql
    V5__add_department_id_to_users.sql
```

#### Migrasiya fayli numuneleri

**V1__create_users_table.sql:**
```sql
CREATE TABLE users (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_is_active ON users(is_active);
```

**V2__create_posts_table.sql:**
```sql
CREATE TABLE posts (
    id BIGSERIAL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    published_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_posts_user_id ON posts(user_id);
```

**V5__add_department_id_to_users.sql:**
```sql
ALTER TABLE users ADD COLUMN department_id BIGINT;
ALTER TABLE users ADD CONSTRAINT fk_users_department
    FOREIGN KEY (department_id) REFERENCES departments(id);
```

#### Flyway-de rollback

Flyway-in pulsuz versiyasinda rollback yoxdur. Pro versiyada `U` (undo) fayllar yazmaq olar:

```
U5__remove_department_id_from_users.sql
```

```sql
ALTER TABLE users DROP CONSTRAINT fk_users_department;
ALTER TABLE users DROP COLUMN department_id;
```

Pulsuz versiyada ise yeni migrasiya yazmaq lazimdir:

```
V6__remove_department_id_from_users.sql
```

### Liquibase ile migrasiyanlar

Liquibase XML, YAML, JSON ve ya SQL formatinda migrasiyalar destekleyir:

#### changelog-master.xml

```xml
<?xml version="1.0" encoding="UTF-8"?>
<databaseChangeLog
    xmlns="http://www.liquibase.org/xml/ns/dbchangelog"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="http://www.liquibase.org/xml/ns/dbchangelog
        http://www.liquibase.org/xml/ns/dbchangelog/dbchangelog-latest.xsd">

    <include file="db/changelog/001-create-users-table.xml"/>
    <include file="db/changelog/002-create-posts-table.xml"/>
</databaseChangeLog>
```

#### 001-create-users-table.xml

```xml
<?xml version="1.0" encoding="UTF-8"?>
<databaseChangeLog
    xmlns="http://www.liquibase.org/xml/ns/dbchangelog"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="http://www.liquibase.org/xml/ns/dbchangelog
        http://www.liquibase.org/xml/ns/dbchangelog/dbchangelog-latest.xsd">

    <changeSet id="001" author="orkhan">
        <createTable tableName="users">
            <column name="id" type="BIGINT" autoIncrement="true">
                <constraints primaryKey="true" nullable="false"/>
            </column>
            <column name="name" type="VARCHAR(100)">
                <constraints nullable="false"/>
            </column>
            <column name="email" type="VARCHAR(255)">
                <constraints nullable="false" unique="true"/>
            </column>
            <column name="created_at" type="TIMESTAMP" defaultValueComputed="CURRENT_TIMESTAMP"/>
        </createTable>

        <rollback>
            <dropTable tableName="users"/>
        </rollback>
    </changeSet>
</databaseChangeLog>
```

Liquibase-in ustunluyu ondadir ki, her `changeSet`-e `rollback` bloku yazmaq mumkundur.

### Spring-de Seeding (ilkin melumat yukleme)

Spring-de hazir seeding mexanizmi yoxdur. Amma bir nece yol var:

```java
import org.springframework.boot.CommandLineRunner;
import org.springframework.stereotype.Component;

@Component
public class DataSeeder implements CommandLineRunner {

    private final UserRepository userRepository;

    public DataSeeder(UserRepository userRepository) {
        this.userRepository = userRepository;
    }

    @Override
    public void run(String... args) {
        if (userRepository.count() == 0) {
            User admin = new User();
            admin.setName("Admin");
            admin.setEmail("admin@example.com");
            userRepository.save(admin);

            User user = new User();
            user.setName("Test User");
            user.setEmail("test@example.com");
            userRepository.save(user);
        }
    }
}
```

Alternativ olaraq `data.sql` faylindan da istifade etmek olar:

```sql
-- src/main/resources/data.sql
INSERT INTO users (name, email) VALUES ('Admin', 'admin@example.com')
    ON CONFLICT (email) DO NOTHING;
```

## Laravel-de istifadesi

Laravel-in daxili migrasiya sistemi var ve cox rahatdir.

### Migrasiya yaratmaq

```bash
# Artisan emri ile migrasiya yaratmaq
php artisan make:migration create_users_table
php artisan make:migration create_posts_table
php artisan make:migration add_department_id_to_users_table
```

Bu emrler `database/migrations/` qovlugunda tarixli fayllar yaradir:

```
database/migrations/
    2024_01_15_100000_create_users_table.php
    2024_01_15_100001_create_posts_table.php
    2024_01_20_143000_add_department_id_to_users_table.php
```

### Migrasiya fayllari

**create_users_table.php:**
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();                          // BIGINT auto-increment primary key
            $table->string('name', 100);
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->timestamps();                  // created_at ve updated_at yaradir
            $table->softDeletes();                 // deleted_at sutunu elave edir

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
```

**create_posts_table.php:**
```php
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('content')->nullable();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->fullText('title');  // Full-text search index
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
```

**add_department_id_to_users_table.php:**
```php
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('department_id')
                  ->nullable()
                  ->constrained()
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropColumn('department_id');
        });
    }
};
```

### Migrasiyalari isletmek

```bash
# Butun migrasiyalari islet
php artisan migrate

# Son migrasiyani geri al
php artisan migrate:rollback

# Son N migrasiyani geri al
php artisan migrate:rollback --step=3

# Butun migrasiyalari geri al ve yeniden islet
php artisan migrate:fresh

# Migrasiya statusunu gor
php artisan migrate:status
```

### Seeder-ler

Laravel-de seeding sistemi daxili olaraq movcuddur:

```php
<?php
// database/seeders/UserSeeder.php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
        ]);

        // Factory istifade ederek 50 test user yarat
        User::factory()->count(50)->create();
    }
}
```

```php
<?php
// database/seeders/DatabaseSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            DepartmentSeeder::class,
            PostSeeder::class,
        ]);
    }
}
```

### Factory-ler

Test melumatlari yaratmaq ucun Factory pattern istifade olunur:

```php
<?php
// database/factories/UserFactory.php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'is_active' => true,
            'email_verified_at' => now(),
            'remember_token' => Str::random(10),
        ];
    }

    // State - ferqli variantlar yaratmaq
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Admin ' . $attributes['name'],
        ]);
    }
}
```

```php
<?php
// database/factories/PostFactory.php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PostFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(),
            'content' => fake()->paragraphs(3, true),
            'user_id' => User::factory(),  // Avtomatik user yaradir
            'published_at' => fake()->optional()->dateTimeBetween('-1 year', 'now'),
        ];
    }
}
```

Factory istifadesi:

```php
// 10 user yarat
User::factory()->count(10)->create();

// Inaktiv userler yarat
User::factory()->count(5)->inactive()->create();

// User ile birlikde 3 post yarat
User::factory()
    ->has(Post::factory()->count(3))
    ->create();
```

## Esas ferqler

| Xususiyyet | Spring (Flyway/Liquibase) | Laravel |
|---|---|---|
| **Migrasiya sistemi** | Xarici kutupxane (Flyway ve ya Liquibase) | Daxili sistem |
| **Migrasiya dili** | SQL (Flyway), XML/YAML/JSON (Liquibase) | PHP (Schema Builder) |
| **Fayl adlandirmasi** | `V1__name.sql` (Flyway) | `2024_01_15_100000_name.php` (tarix bazali) |
| **Rollback** | Flyway Pro-da var, pulsuzda yoxdur. Liquibase-de var | Her migrasiyanin `down()` metodu var |
| **Schema Builder** | Yoxdur (raw SQL yazirsan) | `Blueprint` sinfi ile PHP-de schema yaradirsan |
| **Seeding** | Manual implementasiya (`CommandLineRunner`) | Daxili Seeder sistemi |
| **Factory** | Yoxdur (test ucun manual yaratmaq lazim) | Daxili Factory sistemi |
| **CLI emrleri** | Yoxdur (avtomatik isleyir application start-da) | `php artisan migrate`, `migrate:rollback`, `migrate:fresh` |
| **Database-agnostik** | Liquibase-de var, Flyway-de SQL yazirsan | Schema Builder database-agnostikdir |

## Niye bele ferqler var?

### PHP-de SQL yazmamaq isteyirik

Laravel Schema Builder-in esas meqsedi developerlerin SQL yazmadan database strukturunu idare etmesidir. PHP kodu yazaraq her hansi verilenbazasinda (MySQL, PostgreSQL, SQLite) eyni migrasiya isleyir. Bu xususen inkisaf muhitinde SQLite, production-da PostgreSQL istifade etmek isteyen komandalar ucun faydalidir.

Spring/Flyway-de ise birbaşa SQL yazirsan. Bu daha cox kontrol verir ve database-specific xususiyyetlerden (meselen, PostgreSQL-in `JSONB` tipi) asanliqla istifade etmeye imkan yaradir. Java ekosisteminde verilenbazasi ile isleyenler SQL-i yaxsi bilir ve bu normal qebul edilir.

### Convention felsefesi

Laravel-de `php artisan make:migration create_users_table` yazdiqda framework cedvel adini (`users`) avtomatik bilir ve `Schema::create('users', ...)` kodu hazir gelir. Her sey convention ile isleyir.

Flyway-de ise her seyi ozun yazirsan - cedvel adi, sutunlar, indeksler, her sey senin SQL-indedir.

### Test melumatlari meselesi

Laravel Factory sistemi test driven development ucun cox elaveridir. `User::factory()->count(100)->create()` yazmaqla 100 realistik test melumati yarada bilersiniz. Spring ekosisteminde bu qeder sadelesilmis bir melumat yaratma sistemi yoxdur - adeten test siniflerinde melumat manual yaradilir ve ya TestContainers kimi aletlerle islenilir.

## Hansi framework-de var, hansinda yoxdur?

### Yalniz Laravel-de:
- **Schema Builder** - PHP kodu ile database-agnostik schema yaratmaq
- **Daxili seeder sistemi** - `php artisan db:seed`
- **Factory sistemi** - `User::factory()` ile test melumatlari
- **`migrate:fresh`** - Butun cedvelleri silir ve sifirdan yaradir
- **`migrate:status`** - CLI-da migrasiya statusunu gostermek
- **`down()` metodu** - Her migrasiyada rollback hazir olur

### Yalniz Spring-de (ve ya daha asandir):
- **XML/YAML formatinda migrasiya** (Liquibase) - SQL bilmeyen komanda uzvleri ucun
- **Checksum yoxlamasi** (Flyway) - Movcud migrasiya faylinin deyisdirilmediyin yoxlayir
- **Avtomatik isletme** - Application baslayanda migrasiyanlar avtomatik isleyir, elave emr lazim deyil
- **Repeatable migrations** (Flyway) - `R__` prefiksi ile her defe isleyen migrasiyanlar (view, function yaratmaq ucun)
- **ChangeSet rollback** (Liquibase) - Her changeset ucun avtomatik ve ya manual rollback
