# Package Manager-lər və Build Sistemləri (Maven/Gradle vs Composer)

## Giriş

Asılılıq idarəçiliyi (dependency management) və build sistemi layihənin bel sütunudur. Java ekosistemində iki böyük oyunçu var: **Maven** (XML, declarative, zəngin plugin ekosistemi) və **Gradle** (Groovy/Kotlin DSL, fleksibl, incremental build). PHP-də isə de-facto standart **Composer**-dir — yalnız asılılıq menecer kimi deyil, həm də autoloading, script runner.

Bu müqayisə dependency resolution, scope-lar, SNAPSHOT versiyalar, build lifecycle, BOM, lockfile və private registry mövzularını əhatə edir.

---

## Java-da istifadəsi

### 1) Maven — `pom.xml`

```xml
<?xml version="1.0" encoding="UTF-8"?>
<project xmlns="http://maven.apache.org/POM/4.0.0">
    <modelVersion>4.0.0</modelVersion>

    <groupId>com.example</groupId>
    <artifactId>order-service</artifactId>
    <version>1.0.0-SNAPSHOT</version>
    <packaging>jar</packaging>

    <parent>
        <groupId>org.springframework.boot</groupId>
        <artifactId>spring-boot-starter-parent</artifactId>
        <version>3.2.4</version>
    </parent>

    <properties>
        <java.version>21</java.version>
        <project.build.sourceEncoding>UTF-8</project.build.sourceEncoding>
    </properties>

    <dependencies>
        <!-- compile scope (default) — kompilyasiya və runtime-da lazım -->
        <dependency>
            <groupId>org.springframework.boot</groupId>
            <artifactId>spring-boot-starter-web</artifactId>
        </dependency>

        <!-- runtime scope — yalnız runtime-da lazım -->
        <dependency>
            <groupId>org.postgresql</groupId>
            <artifactId>postgresql</artifactId>
            <scope>runtime</scope>
        </dependency>

        <!-- provided scope — runtime-da var, compile-da lazım -->
        <dependency>
            <groupId>jakarta.servlet</groupId>
            <artifactId>jakarta.servlet-api</artifactId>
            <scope>provided</scope>
        </dependency>

        <!-- test scope — yalnız testlər üçün -->
        <dependency>
            <groupId>org.springframework.boot</groupId>
            <artifactId>spring-boot-starter-test</artifactId>
            <scope>test</scope>
        </dependency>

        <!-- Versiya aralığı -->
        <dependency>
            <groupId>com.google.guava</groupId>
            <artifactId>guava</artifactId>
            <version>[32.0,33.0)</version>    <!-- 32.x, 33.0 daxil deyil -->
        </dependency>
    </dependencies>

    <dependencyManagement>
        <dependencies>
            <!-- BOM import — versiya uyğunluğunu idarə et -->
            <dependency>
                <groupId>org.springframework.cloud</groupId>
                <artifactId>spring-cloud-dependencies</artifactId>
                <version>2023.0.1</version>
                <type>pom</type>
                <scope>import</scope>
            </dependency>
        </dependencies>
    </dependencyManagement>

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

### Maven build lifecycle (phase → goal)

Maven-də **lifecycle** = phase-lər zənciri; **goal** = bir plugin-in icrası.

```
Default lifecycle:
  validate → compile → test-compile → test → package → verify → install → deploy

Clean lifecycle:
  pre-clean → clean → post-clean

Site lifecycle:
  pre-site → site → post-site → site-deploy
```

```bash
mvn clean                    # target/ sil
mvn compile                  # src/main/java/ kompilyasiya et
mvn test                     # testləri icra et
mvn package                  # JAR/WAR yarat
mvn install                  # lokal ~/.m2 repo-ya qoy
mvn deploy                   # remote repo-ya (Nexus/Artifactory) push et
mvn clean package -DskipTests
mvn dependency:tree          # asılılıq ağacı
mvn versions:display-dependency-updates   # yeni versiyaları göstər
```

### Maven scope-ları təfərrüatlı

| Scope | Compile | Test | Runtime | Qablaşdırma |
|---|---|---|---|---|
| `compile` (default) | ✓ | ✓ | ✓ | ✓ |
| `provided` | ✓ | ✓ | ✗ | ✗ |
| `runtime` | ✗ | ✓ | ✓ | ✓ |
| `test` | ✗ | ✓ | ✗ | ✗ |
| `system` | ✓ | ✓ | ✗ | ✗ |

### SNAPSHOT versiyalar

`1.0.0-SNAPSHOT` — "hələ development"; Maven hər dəfə remote repo-dan yoxlayır, yeni nüsxə varsa çəkir. Release versiyalar immutable-dir.

```xml
<version>1.0.0-SNAPSHOT</version>       <!-- dev -->
<version>1.0.0</version>                <!-- release -->
```

### 2) Gradle — `build.gradle.kts` (Kotlin DSL)

```kotlin
plugins {
    java
    id("org.springframework.boot") version "3.2.4"
    id("io.spring.dependency-management") version "1.1.4"
}

group = "com.example"
version = "1.0.0-SNAPSHOT"

java {
    sourceCompatibility = JavaVersion.VERSION_21
}

repositories {
    mavenCentral()
    maven {
        url = uri("https://nexus.company.com/repository/maven-releases/")
        credentials {
            username = System.getenv("NEXUS_USER")
            password = System.getenv("NEXUS_PASS")
        }
    }
}

dependencies {
    // implementation = compile + runtime, lakin transitive deyil
    implementation("org.springframework.boot:spring-boot-starter-web")

    // runtimeOnly
    runtimeOnly("org.postgresql:postgresql")

    // compileOnly
    compileOnly("jakarta.servlet:jakarta.servlet-api")

    // testImplementation
    testImplementation("org.springframework.boot:spring-boot-starter-test")

    // api — transitive, başqa modulun istifadəçisi də görür
    api("com.example:shared-model:1.2.0")

    // BOM import (platform)
    implementation(platform("org.springframework.cloud:spring-cloud-dependencies:2023.0.1"))
    implementation("org.springframework.cloud:spring-cloud-starter-openfeign")

    // Version range
    implementation("com.google.guava:guava:[32.0, 33.0[")
}

tasks.withType<Test> {
    useJUnitPlatform()
}

// Dependency locking
dependencyLocking {
    lockAllConfigurations()
}

// Custom task
tasks.register("hello") {
    doLast { println("Hello Gradle!") }
}
```

### Gradle configuration-ları (scope analog)

| Gradle | Maven analog |
|---|---|
| `implementation` | `compile` (transitive gizlədilir) |
| `api` | `compile` (transitive açıq) |
| `compileOnly` | `provided` |
| `runtimeOnly` | `runtime` |
| `testImplementation` | `test` |
| `testRuntimeOnly` | `test` + `runtime` |
| `annotationProcessor` | `compile` + processor |

### Gradle commands

```bash
./gradlew build              # kompilyasiya + test + JAR
./gradlew clean
./gradlew test --tests "UserServiceTest"
./gradlew dependencies       # ağac
./gradlew dependencyInsight --dependency guava
./gradlew bootRun            # Spring Boot başlat
./gradlew publish            # Nexus/Artifactory-a push
./gradlew dependencies --write-locks    # lockfile generate
```

### Gradle dependency locking

Reproducible build üçün:

```
gradle/dependency-locks/compileClasspath.lockfile
gradle/dependency-locks/runtimeClasspath.lockfile
...
```

```bash
./gradlew dependencies --write-locks
```

### Private registry — Nexus və ya Artifactory

```xml
<!-- Maven ~/.m2/settings.xml -->
<settings>
    <servers>
        <server>
            <id>nexus-releases</id>
            <username>${env.NEXUS_USER}</username>
            <password>${env.NEXUS_PASS}</password>
        </server>
    </servers>
    <mirrors>
        <mirror>
            <id>nexus</id>
            <mirrorOf>*</mirrorOf>
            <url>https://nexus.company.com/repository/maven-public/</url>
        </mirror>
    </mirrors>
</settings>

<!-- pom.xml -->
<distributionManagement>
    <repository>
        <id>nexus-releases</id>
        <url>https://nexus.company.com/repository/maven-releases/</url>
    </repository>
    <snapshotRepository>
        <id>nexus-snapshots</id>
        <url>https://nexus.company.com/repository/maven-snapshots/</url>
    </snapshotRepository>
</distributionManagement>
```

---

## PHP-də istifadəsi (Composer)

### `composer.json`

```json
{
    "name": "example/order-service",
    "description": "Order service",
    "type": "project",
    "license": "MIT",
    "require": {
        "php": "^8.3",
        "ext-pdo": "*",
        "ext-redis": "*",
        "laravel/framework": "^11.0",
        "guzzlehttp/guzzle": "^7.8",
        "league/flysystem-aws-s3-v3": "^3.0",
        "lcobucci/jwt": "^5.0 || ^4.0"
    },
    "require-dev": {
        "pestphp/pest": "^2.34",
        "laravel/pint": "^1.13",
        "phpstan/phpstan": "^1.10",
        "mockery/mockery": "^1.6"
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
        ],
        "test": "pest --parallel",
        "analyse": "phpstan analyse --memory-limit=2G",
        "format": "pint"
    },
    "config": {
        "optimize-autoloader": true,
        "preferred-install": "dist",
        "sort-packages": true,
        "allow-plugins": {
            "pestphp/pest-plugin": true,
            "php-http/discovery": true
        }
    },
    "minimum-stability": "stable",
    "prefer-stable": true,
    "repositories": [
        {
            "type": "composer",
            "url": "https://repo.packagist.com/company/"
        },
        {
            "type": "vcs",
            "url": "git@github.com:company/private-sdk.git"
        }
    ]
}
```

### Versiya qaydaları

```
^1.2.3    >=1.2.3, <2.0.0      (major qoruyur)  — ən çox istifadə olunan
~1.2.3    >=1.2.3, <1.3.0      (minor qoruyur)
1.2.*     >=1.2.0, <1.3.0
>=1.2     >=1.2
*         istənilən
dev-main  main branch-dən ən son commit
1.2.x-dev tag olmamış development versiya
```

### Dev / stable flag

```json
"require": {
    "laravel/framework": "^11.0@dev"     // dev-stability qəbul et
}
```

### Lockfile — `composer.lock`

```bash
composer install         # composer.lock əsasında dəqiq versiyalar
composer update          # composer.json-a baxır, lock yeniləyir
composer update vendor/package   # yalnız bir paketi yenilə
composer update --with-all-dependencies vendor/package   # sorğulanan paket + transitive
```

**Qayda:** `composer.lock` versioning-ə əlavə edilir — bütün developer-lər və prod eyni versiyalar ilə işləyir.

### Autoloading

Composer autoload 4 formatı dəstəkləyir:

```json
"autoload": {
    "psr-4": { "App\\": "app/" },
    "psr-0": { "Legacy_": "legacy/" },
    "classmap": ["includes/"],
    "files": ["helpers.php"]
}
```

```bash
composer dump-autoload --optimize    # prod üçün (opcache-friendly classmap)
composer dump-autoload --classmap-authoritative   # maximum perf
```

### Commands

```bash
composer install            # lock əsasında install
composer update             # versiyaları yenilə
composer require vendor/pkg # əlavə et
composer remove vendor/pkg
composer outdated           # köhnə paketlər
composer show               # installed paketlər
composer show --tree        # asılılıq ağacı
composer why vendor/pkg     # niyə bu paket install olunub?
composer why-not vendor/pkg 2.0   # 2.0 niyə install ola bilmir?
composer validate           # composer.json sintaksisi yoxla
composer audit              # təhlükəsizlik audit
composer run test           # script-ləri icra et
```

### Scripts — build lifecycle əvəzedicisi

Composer-də Maven/Gradle kimi "phase" yoxdur — sadəcə `scripts` bloku var:

```json
"scripts": {
    "pre-install-cmd": [...],
    "post-install-cmd": [...],
    "pre-update-cmd": [...],
    "post-update-cmd": [...],
    "post-autoload-dump": [...],
    "pre-package-install": [...],
    "post-package-install": [...],

    // custom script-lər
    "test": "pest",
    "build": [
        "@composer dump-autoload --optimize",
        "@php artisan config:cache",
        "@php artisan route:cache"
    ]
}
```

### Platform requirements

```json
"require": {
    "php": "^8.3",
    "ext-pdo": "*",
    "ext-gd": "*"
},
"config": {
    "platform": {
        "php": "8.3.0"     // minimum PHP versiyası simulyasiyası
    }
}
```

### Private registry — Private Packagist, Satis

```json
"repositories": [
    {
        "type": "composer",
        "url": "https://repo.packagist.com/company/"
    }
]
```

```bash
# auth — ~/.composer/auth.json
{
    "http-basic": {
        "repo.packagist.com": {
            "username": "token",
            "password": "${COMPOSER_AUTH_TOKEN}"
        }
    }
}
```

**Satis** — öz Composer mirror-un. **Private Packagist** — SaaS mirror. **Nexus** — Composer repository tipi də dəstəkləyir.

### Monorepo — Composer paths

```json
"repositories": [
    {
        "type": "path",
        "url": "packages/*",
        "options": { "symlink": true }
    }
]
```

### Plugin sistemi

Composer plugin-lər install/update lifecycle-ına qoşulur:

```json
"config": {
    "allow-plugins": {
        "pestphp/pest-plugin": true,
        "php-http/discovery": true,
        "composer/package-versions-deprecated": true
    }
}
```

---

## Əsas fərqlər

| Xüsusiyyət | Maven | Gradle | Composer |
|---|---|---|---|
| Fayl | `pom.xml` (XML) | `build.gradle[.kts]` (Groovy/Kotlin) | `composer.json` (JSON) |
| DSL | Deklarativ XML | Imperative + deklarativ | Deklarativ JSON |
| Build lifecycle | Phase → goal | Task graph | Yoxdur (sadə scripts) |
| Scope-lar | compile, provided, runtime, test, system | implementation, api, runtimeOnly, compileOnly, test* | require, require-dev (yalnız 2) |
| Lockfile | Yoxdur (dependency management ilə pin) | `gradle/dependency-locks/*.lockfile` | `composer.lock` (default) |
| Transitive dep | Default açıq | `api` açıq, `implementation` gizli | Həmişə transitive |
| BOM/Platform | `<dependencyManagement>` | `platform(...)` | Yoxdur (həyati deyil) |
| SNAPSHOT | `-SNAPSHOT` suffix | Eyni Maven ilə | `dev-branch`, `*-dev` |
| Version range | `[1.0,2.0)` | `[1.0, 2.0[` | `^1.0`, `~1.0`, `>=1.0 <2.0` |
| Central repo | Maven Central | Maven Central + JCenter (bağlanıb) | Packagist.org |
| Private repo | Nexus, Artifactory | Eyni | Private Packagist, Satis, Nexus Composer |
| Build speed | Stabil | Incremental, daemon | Sürətli (install), yavaş (update) |
| IDE dəstəyi | IntelliJ, Eclipse | IntelliJ, Android Studio | PhpStorm |
| Plugin ecosystem | Yüzlərlə | Yüzlərlə | Az, lakin kifayət |
| Autoload | Əl ilə classpath | Əl ilə | PSR-4, PSR-0, classmap, files |
| Script | `<executions>` | `tasks.register` | `scripts` array |
| Paket format | JAR, WAR | Eyni | Git repo (zip) |

---

## Niyə belə fərqlər var?

**Maven-in "convention over configuration" fəlsəfəsi.** Maven 2002-də çıxdı — o vaxt Ant ilə hər layihə üçün build script yazmaq standart idi. Maven "bir pom.xml ilə hər şey işləsin" gətirdi: standart qovluq strukturu (`src/main/java`), standart phase-lər, standart plugin-lər. Bu uğurlu oldu, amma XML verbose-dur.

**Gradle-in flexibility yanaşması.** Gradle 2007-də çıxdı — Groovy DSL ilə imperative kod yazmaq imkanı verdi. Task graph concept-i — hər task input/output bildirir, yalnız dəyişən task-lar yenidən işlənir (incremental build). Android komandaları bu sürəti sevdi, Gradle Android-in rəsmi build tool-u oldu.

**Composer-in sadəliyi.** Composer 2012-də çıxdı — artıq NPM, Bundler, pip kimi müasir package manager-lər var idi. Composer onlardan dərs aldı: JSON faylı, lockfile, semver, ayrı dev-require. Phase-lər və lifecycle-lar yoxdur — çünki PHP-də "compile" yoxdur, sadəcə autoload və script-lər. Bu sadə model PHP üçün kifayət etdi.

**Autoload problemi.** Java-da class-path JVM-lə idarə olunur — kompilyator import-ları bilir. PHP-də isə runtime-da hər class üçün `require_once` lazım idi. PSR-4 + Composer autoload-u bu problemi həll etdi — namespace-ə əsasən class faylı tapılır.

**Build vs runtime.** Java: compile → JAR/WAR → deploy. Composer: install → autoload generate → deploy (source kod). Bu səbəbdən Composer-də "build" yox, "install + optimize" var.

**Scope azlığı (Composer).** Composer yalnız `require` + `require-dev` verir. Maven 5 scope, Gradle 7+ configuration verir. Səbəb: PHP runtime-da compile/runtime ayrımı yoxdur — hər şey eyni vaxtda runtime-dır. Test-only paketlər üçün `require-dev` kifayətdir.

**Lockfile fərqi.** NPM, Composer, Bundler "lockfile-first" mühitdən gəlir. Maven tarixən lockfile istifadə etməyib — `<dependencyManagement>` ilə versiyaları pin etmək tövsiyə olunub. Gradle 5+ `dependency-locks` əlavə etdi, amma opt-in.

---

## Hansı sistemdə var, hansında yoxdur?

**Yalnız Maven-də:**
- `<parent>` POM inheritance (Spring Boot parent)
- `<dependencyManagement>` — versiya override-sız bulkde tərif
- Aggregator POM (`<modules>`) — multi-module proyekt
- Effective POM (`mvn help:effective-pom`)
- Profile-lar (`<profiles>`) — dev/prod üçün fərqli konfiq
- Enforcer plugin — convention-lərə məcbur etmək

**Yalnız Gradle-də:**
- Incremental build (yalnız dəyişiklik olan task-ları işlət)
- Build cache — nəticələri remote cache-ləmək
- Gradle Daemon — JVM startup-ı əvvəlcədən
- Kotlin DSL — type-safe build script-lər
- Composite build (`includeBuild`) — multi-repo əlaqələndirmə
- `implementation` vs `api` — transitive görünürlüyü idarə etmək

**Yalnız Composer-də:**
- Avtomatik PSR-4 autoload — heç kod yazmadan class-lar qoşulur
- `scripts` ilə sadə composer command-lar (`composer test`)
- `type: "path"` — monorepo symlink
- `minimum-stability` + `prefer-stable`
- Platform requirement-lər (`ext-*`, `php: ^8.3`)
- `composer audit` — CVE yoxlaması daxili
- `prefer-dist` vs `prefer-source` — zip vs git clone
- Plugin-lər install/update hook-larına qoşulur
