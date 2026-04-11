# Layihe Strukturu: Spring Boot vs Laravel

## Giris

Her bir framework-un oz fayl ve qovluq strukturu var. Bu struktur framework-un felsefesini, dilin xususiyyetlerini ve inkisaf tarixini eks etdirir. Spring Boot Java dunyasinin "convention over configuration" yanasmasi ile, Laravel ise PHP dunyasinin sadelesdirme felsefesi ile oz strukturlarini formalasdirmisdir.

Bu bolmede her iki framework-un layihe strukturunu, MVC pattern-ini ve konfiqurasiya yanasmalarini muqayise edeceyik.

## Spring Boot-da Layihe Strukturu

Spring Boot layihesi adindan gorunduyu kimi Java-nin standart layihe strukturuna esaslanir. Maven ve ya Gradle build sistemi istifade olunur.

### Tipik Spring Boot layihesinin strukturu

```
my-spring-app/
├── pom.xml                          # Maven asililiqlar (ve ya build.gradle)
├── src/
│   ├── main/
│   │   ├── java/
│   │   │   └── com/
│   │   │       └── example/
│   │   │           └── myapp/
│   │   │               ├── MyAppApplication.java       # Esas giris noqtesi
│   │   │               ├── controller/
│   │   │               │   ├── UserController.java
│   │   │               │   └── ProductController.java
│   │   │               ├── service/
│   │   │               │   ├── UserService.java
│   │   │               │   └── ProductService.java
│   │   │               ├── repository/
│   │   │               │   ├── UserRepository.java
│   │   │               │   └── ProductRepository.java
│   │   │               ├── model/
│   │   │               │   ├── User.java
│   │   │               │   └── Product.java
│   │   │               ├── dto/
│   │   │               │   ├── UserDTO.java
│   │   │               │   └── ProductDTO.java
│   │   │               ├── config/
│   │   │               │   ├── SecurityConfig.java
│   │   │               │   └── WebConfig.java
│   │   │               └── exception/
│   │   │                   ├── ResourceNotFoundException.java
│   │   │                   └── GlobalExceptionHandler.java
│   │   └── resources/
│   │       ├── application.properties       # Esas konfiqurasiya
│   │       ├── application-dev.properties   # Development ucun
│   │       ├── application-prod.properties  # Production ucun
│   │       ├── static/                      # CSS, JS, sekiller
│   │       └── templates/                   # Thymeleaf sablonlari
│   └── test/
│       └── java/
│           └── com/
│               └── example/
│                   └── myapp/
│                       ├── controller/
│                       │   └── UserControllerTest.java
│                       └── service/
│                           └── UserServiceTest.java
└── target/                          # Build naticeleri (Maven)
```

### Esas giris noqtesi (Application class)

```java
package com.example.myapp;

import org.springframework.boot.SpringApplication;
import org.springframework.boot.autoconfigure.SpringBootApplication;

@SpringBootApplication  // @Configuration + @EnableAutoConfiguration + @ComponentScan
public class MyAppApplication {
    public static void main(String[] args) {
        SpringApplication.run(MyAppApplication.class, args);
    }
}
```

`@SpringBootApplication` annotasiyasi uc annotasiyanin birlesmasidir:
- `@Configuration` - Bu sinif konfiqurasiya sinifidir
- `@EnableAutoConfiguration` - Spring Boot classpath-e esasen avtomatik konfiqurasiya edir
- `@ComponentScan` - Bu paketde ve alt paketlerde komponentleri axtarir

### pom.xml (Maven)

```xml
<?xml version="1.0" encoding="UTF-8"?>
<project xmlns="http://maven.apache.org/POM/4.0.0"
         xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:schemaLocation="http://maven.apache.org/POM/4.0.0
         https://maven.apache.org/xsd/maven-4.0.0.xsd">
    <modelVersion>4.0.0</modelVersion>

    <parent>
        <groupId>org.springframework.boot</groupId>
        <artifactId>spring-boot-starter-parent</artifactId>
        <version>3.2.0</version>
    </parent>

    <groupId>com.example</groupId>
    <artifactId>my-spring-app</artifactId>
    <version>0.0.1-SNAPSHOT</version>
    <name>my-spring-app</name>
    <description>Numune Spring Boot layihesi</description>

    <properties>
        <java.version>21</java.version>
    </properties>

    <dependencies>
        <!-- Web uchun -->
        <dependency>
            <groupId>org.springframework.boot</groupId>
            <artifactId>spring-boot-starter-web</artifactId>
        </dependency>

        <!-- JPA / Hibernate uchun -->
        <dependency>
            <groupId>org.springframework.boot</groupId>
            <artifactId>spring-boot-starter-data-jpa</artifactId>
        </dependency>

        <!-- PostgreSQL driver -->
        <dependency>
            <groupId>org.postgresql</groupId>
            <artifactId>postgresql</artifactId>
            <scope>runtime</scope>
        </dependency>

        <!-- Lombok - boilerplate kodu azaldir -->
        <dependency>
            <groupId>org.projectlombok</groupId>
            <artifactId>lombok</artifactId>
            <optional>true</optional>
        </dependency>

        <!-- Test uchun -->
        <dependency>
            <groupId>org.springframework.boot</groupId>
            <artifactId>spring-boot-starter-test</artifactId>
            <scope>test</scope>
        </dependency>
    </dependencies>

    <build>
        <plugins>
            <plugin>
                <groupId>org.springframework.boot</groupId>
                <artifactId>spring-boot-maven-plugin</artifactId>
            </plugin>
        </plugins>
    </build>
</project>
```

### build.gradle (Gradle alternativ)

```groovy
plugins {
    id 'java'
    id 'org.springframework.boot' version '3.2.0'
    id 'io.spring.dependency-management' version '1.1.4'
}

group = 'com.example'
version = '0.0.1-SNAPSHOT'

java {
    sourceCompatibility = '21'
}

repositories {
    mavenCentral()
}

dependencies {
    implementation 'org.springframework.boot:spring-boot-starter-web'
    implementation 'org.springframework.boot:spring-boot-starter-data-jpa'
    runtimeOnly 'org.postgresql:postgresql'
    compileOnly 'org.projectlombok:lombok'
    annotationProcessor 'org.projectlombok:lombok'
    testImplementation 'org.springframework.boot:spring-boot-starter-test'
}

tasks.named('test') {
    useJUnitPlatform()
}
```

### application.properties

```properties
# Server konfiqurasiyasi
server.port=8080

# Verilener bazasi
spring.datasource.url=jdbc:postgresql://localhost:5432/mydb
spring.datasource.username=postgres
spring.datasource.password=secret
spring.datasource.driver-class-name=org.postgresql.Driver

# JPA / Hibernate
spring.jpa.hibernate.ddl-auto=update
spring.jpa.show-sql=true
spring.jpa.properties.hibernate.format_sql=true
spring.jpa.properties.hibernate.dialect=org.hibernate.dialect.PostgreSQLDialect

# Logging
logging.level.root=INFO
logging.level.com.example.myapp=DEBUG

# Aktiv profil
spring.profiles.active=dev
```

Alternativ olaraq `application.yml` formatinda da yazmaq mumkundur:

```yaml
server:
  port: 8080

spring:
  datasource:
    url: jdbc:postgresql://localhost:5432/mydb
    username: postgres
    password: secret
  jpa:
    hibernate:
      ddl-auto: update
    show-sql: true

logging:
  level:
    root: INFO
    com.example.myapp: DEBUG
```

### MVC numunesi

```java
// Model
@Entity
@Table(name = "users")
public class User {
    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    @Column(nullable = false)
    private String name;

    @Column(unique = true, nullable = false)
    private String email;

    // getter/setter-ler ve ya Lombok @Data
}

// Repository
@Repository
public interface UserRepository extends JpaRepository<User, Long> {
    Optional<User> findByEmail(String email);
}

// Service
@Service
public class UserService {
    private final UserRepository userRepository;

    public UserService(UserRepository userRepository) {
        this.userRepository = userRepository;
    }

    public List<User> getAllUsers() {
        return userRepository.findAll();
    }

    public User createUser(User user) {
        return userRepository.save(user);
    }
}

// Controller
@RestController
@RequestMapping("/api/users")
public class UserController {
    private final UserService userService;

    public UserController(UserService userService) {
        this.userService = userService;
    }

    @GetMapping
    public ResponseEntity<List<User>> getAllUsers() {
        return ResponseEntity.ok(userService.getAllUsers());
    }

    @PostMapping
    public ResponseEntity<User> createUser(@RequestBody User user) {
        User created = userService.createUser(user);
        return ResponseEntity.status(HttpStatus.CREATED).body(created);
    }
}
```

## Laravel-de Layihe Strukturu

Laravel layihesi Composer vasitesile yaradilir ve PHP ekosisteminin konvensiyalarina uygun strukturlasdirilir.

### Tipik Laravel layihesinin strukturu

```
my-laravel-app/
├── app/
│   ├── Console/
│   │   └── Commands/                # Artisan emrleri
│   ├── Exceptions/
│   │   └── Handler.php              # Qlobal xeta idare etme
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── UserController.php
│   │   │   └── ProductController.php
│   │   ├── Middleware/
│   │   │   ├── Authenticate.php
│   │   │   └── VerifyCsrfToken.php
│   │   └── Requests/
│   │       ├── StoreUserRequest.php
│   │       └── UpdateUserRequest.php
│   ├── Models/
│   │   ├── User.php
│   │   └── Product.php
│   ├── Providers/
│   │   ├── AppServiceProvider.php
│   │   ├── AuthServiceProvider.php
│   │   └── RouteServiceProvider.php
│   └── Services/                    # Oz yaratdigimiz qovluq
│       ├── UserService.php
│       └── ProductService.php
├── bootstrap/
│   └── app.php                      # Framework-u yukleyen fayl
├── config/
│   ├── app.php                      # Umumi konfiqurasiya
│   ├── database.php                 # DB konfiqurasiyasi
│   ├── auth.php                     # Autentifikasiya
│   ├── cache.php                    # Kes konfiqurasiyasi
│   └── mail.php                     # E-poct konfiqurasiyasi
├── database/
│   ├── factories/                   # Model factory-ler
│   │   └── UserFactory.php
│   ├── migrations/                  # DB migrasiylari
│   │   └── 2024_01_01_create_users_table.php
│   └── seeders/                     # Test datalari
│       └── DatabaseSeeder.php
├── public/
│   ├── index.php                    # Giris noqtesi
│   ├── css/
│   └── js/
├── resources/
│   ├── views/                       # Blade sablonlari
│   │   ├── layouts/
│   │   │   └── app.blade.php
│   │   └── users/
│   │       ├── index.blade.php
│   │       └── show.blade.php
│   ├── css/
│   └── js/
├── routes/
│   ├── web.php                      # Web marsrutlari
│   ├── api.php                      # API marsrutlari
│   ├── console.php                  # Console marsrutlari
│   └── channels.php                 # Broadcast kanallari
├── storage/
│   ├── app/                         # Tetbiq fayllari
│   ├── framework/                   # Framework kesleri
│   └── logs/                        # Log fayllari
├── tests/
│   ├── Feature/
│   │   └── UserTest.php
│   └── Unit/
│       └── UserServiceTest.php
├── .env                             # Muhit deyisenleri
├── .env.example                     # Numune muhit fayli
├── composer.json                    # PHP asililiqlar
├── artisan                          # CLI emrleri
└── package.json                     # Frontend asililiqlar
```

### .env fayli

```env
APP_NAME=MyLaravelApp
APP_ENV=local
APP_KEY=base64:xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=mydb
DB_USERNAME=postgres
DB_PASSWORD=secret

CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=file

MAIL_MAILER=smtp
MAIL_HOST=mailhog
MAIL_PORT=1025
```

### composer.json

```json
{
    "name": "example/my-laravel-app",
    "type": "project",
    "description": "Numune Laravel layihesi",
    "require": {
        "php": "^8.2",
        "laravel/framework": "^11.0",
        "laravel/sanctum": "^4.0",
        "laravel/tinker": "^2.9"
    },
    "require-dev": {
        "fakerphp/faker": "^1.23",
        "laravel/pint": "^1.13",
        "laravel/sail": "^1.26",
        "mockery/mockery": "^1.6",
        "nunomaduro/collision": "^8.0",
        "phpunit/phpunit": "^11.0"
    },
    "autoload": {
        "psr-4": {
            "App\\": "app/",
            "Database\\Factories\\": "database/factories/",
            "Database\\Seeders\\": "database/seeders/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Tests\\": "tests/"
        }
    },
    "scripts": {
        "post-autoload-dump": [
            "Illuminate\\Foundation\\ComposerScripts::postAutoloadDump",
            "@php artisan package:discover --ansi"
        ]
    }
}
```

### config/database.php (konfiqurasiya numunesi)

```php
<?php

return [
    'default' => env('DB_CONNECTION', 'pgsql'),

    'connections' => [
        'pgsql' => [
            'driver' => 'pgsql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'schema' => 'public',
        ],
    ],

    'migrations' => 'migrations',
];
```

### MVC numunesi

```php
// Model - app/Models/User.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class User extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'email'];

    protected $hidden = ['password'];

    public function posts()
    {
        return $this->hasMany(Post::class);
    }
}

// Migration - database/migrations/2024_01_01_create_users_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};

// Service - app/Services/UserService.php
namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class UserService
{
    public function getAllUsers(): Collection
    {
        return User::all();
    }

    public function createUser(array $data): User
    {
        return User::create($data);
    }
}

// Controller - app/Http/Controllers/UserController.php
namespace App\Http\Controllers;

use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(
        private readonly UserService $userService
    ) {}

    public function index(): JsonResponse
    {
        $users = $this->userService->getAllUsers();
        return response()->json($users);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
        ]);

        $user = $this->userService->createUser($validated);
        return response()->json($user, 201);
    }
}

// Routes - routes/api.php
use App\Http\Controllers\UserController;

Route::apiResource('users', UserController::class);
```

## Esas Ferqler

| Xususiyyet | Spring Boot | Laravel |
|---|---|---|
| **Dil** | Java (compile olunan) | PHP (interpret olunan) |
| **Giris noqtesi** | `main()` metodu | `public/index.php` |
| **Asililiq idaresi** | Maven (`pom.xml`) ve ya Gradle (`build.gradle`) | Composer (`composer.json`) |
| **Konfiqurasiya** | `application.properties` / `application.yml` | `.env` + `config/*.php` |
| **Muhit profilleri** | `application-{profile}.properties` | `.env` fayli + `APP_ENV` |
| **Routing** | Controller annotasiyalarinda | Ayri `routes/` fayllarinda |
| **View sablon** | Thymeleaf, Freemarker | Blade |
| **CLI aleyi** | Spring CLI (az istifade olunur) | Artisan (cox guclii) |
| **DB migrasiylari** | Flyway / Liquibase (ucuncu teref) | Daxili migrasiya sistemi |
| **Qovluq strukturu** | Java paket konvensiyasi (`com.example.app`) | PSR-4 autoloading (`App\`) |
| **Build prosesi** | Compile + package (JAR/WAR) | Birbase deploy (compile yoxdur) |
| **Test qovluqlari** | `src/test/java/` | `tests/Feature/` ve `tests/Unit/` |

## Niye Bele Ferqler Var?

### Java-nin tesiri Spring-e

Spring Boot-un strukturu Java dilinin xususiyyetlerinden qaynaqlanir:

1. **Paket sistemi**: Java-da her sinif bir paketde olmalidir. `com.example.myapp` kimi tersine domen adi konvensiyasi Java dunyasinin standartdir. Bu, ayri-ayri sinif fayllari ucun mentiqi qruplasdirma yaradir.

2. **Compile prosesi**: Java compile olunan dildir, ona gore `src/main/java` (menbeler) ve `target/` (netice) ayriligi var. Kod yazilir, compile olunur, JAR/WAR faylina paketlenir.

3. **Maven/Gradle medeniyyeti**: Java ekosistemi onillikledir Maven-e esaslanir. `pom.xml`-in XML formati agir gorunse de, o, ciddiyyeti ve aciqligi ifade edir - her asililik daqiq versiya ile gosterilir.

4. **Konfiqurasiya yanasmasi**: `application.properties` Spring-in oz formatidir. Profil sistemi (`application-dev.properties`) production ve development muhitlerini asanliqla ayirmaga imkan verir.

### PHP-nin tesiri Laravel-e

Laravel-in strukturu PHP-nin tarixini ve imkanlarini eks etdirir:

1. **Fayllarla birbasa islemek**: PHP ilkin olaraq her sorgu ucun fayldan oxunan dildir. `public/index.php` giris noqtesidir, cunku web server birbasa PHP fayllarini isleyir.

2. **Compile yoxdur**: PHP interpret olunan dildir, ona gore build qovluqlari lazim deyil. Kod yazilir ve deyisiklik birbase gorunur (development-de).

3. **`.env` fayli**: Laravel `.env` faylini PHP-nin muhit deyisenleri ile islemek ucun istifade edir. Bu yanasmani Ruby dunyasindan (dotenv) goturub. Konfiqurasiya koddan kenar saxlanir - bu 12-Factor App prinsipine uygunudur.

4. **Artisan CLI**: `php artisan` emrleri ile controller, model, migrasiya yaratmaq mumkundur. Bu, Laravel-in "developer experience" felsefesinin tezahuurudur - hec bir fayli elle yaratmaga ehtiyac yoxdur.

5. **Routes ayri faylda**: Laravel marsrutlari ayri `routes/` qovlugunda saxlayir, cunku PHP-de annotasiya sistemi Java qeder gucluu deyil. Elave olaraq, marsrutlarin bir yerde olmasi API-nin umumi menzeresini gormeyey asanlasdirur.

## Hansi Framework-de Var, Hansinda Yoxdur?

### Yalniz Laravel-de olan xususiyyetler

- **Artisan code generation**: `php artisan make:controller`, `make:model`, `make:migration` emrleri ile sifirdan fayl yaradilir. Spring-de bele daxili generator yoxdur (Spring Initializr yalniz layihenin ozunu yaradir).

- **Daxili migrasiya sistemi**: Laravel-de migrasiyalar framework-un ozu ile gelir. Spring-de Flyway ve ya Liquibase elave kitabxana kimi qosulur.

- **Tinker (REPL)**: `php artisan tinker` ile interaktiv olaraq modelleri, servisleri sinaqdan kecirmek olur. Spring-de buna benzer daxili aley yoxdur.

- **Route fayllari (`web.php`, `api.php`)**: Marsrutlarin merkezi bir yerde toplanmasi.

### Yalniz Spring Boot-da olan xususiyyetler

- **Avtomatik konfiqurasiya (Auto-Configuration)**: Spring Boot classpath-deki kitabxanalara esasen ozunu avtomatik konfiqurasiya edir. Meselen, `spring-boot-starter-data-jpa` elave etseniz, JPA avtomatik qurulur.

- **Profil sistemi**: `application-dev.properties`, `application-prod.properties` kimi ferqli muhitler ucun ayri konfiqurasiya fayllari. Laravel-de bu `.env` fayli ile hell olunur, amma Spring-in yanasmasi daha strukturlu ola bilir.

- **Starter Dependencies**: `spring-boot-starter-web` kimi "starter" paketler bir nece kitabxanani bir yerde toplayir. Bu, asililiq idaresini asanlasdirir.

- **Embedded Server**: Spring Boot oz daxilinde Tomcat/Jetty serveri dasiyir. Laravel-de ise `php artisan serve` yalniz development ucundur, production-da Nginx/Apache lazimdir.

- **Multi-module layiheler**: Maven/Gradle ile boyuk layiheler modullar seklinde bolunur. Composer-de bele daxili modul sistemi yoxdur.

### Her ikisinde olan, amma ferqli isleyen xususiyyetler

- **Muhit deyisenleri**: Spring `application.properties`-de, Laravel `.env`-de saxlayir
- **Test**: Spring JUnit + Mockito, Laravel PHPUnit + Mockery
- **Static assets**: Spring `src/main/resources/static/`, Laravel `public/` + `resources/`
- **View templates**: Spring Thymeleaf, Laravel Blade
