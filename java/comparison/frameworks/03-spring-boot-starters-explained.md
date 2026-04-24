# Spring Boot Starter-ləri — Nə Üçün və Necə

> **Seviyye:** Beginner ⭐

## Giriş

Spring Boot-un həyatı asanlaşdıran ən vacib ixtiralardan biri **"starter"** konseptidir. Starter — özündə bir neçə bir-biri ilə əlaqəli kitabxananı saxlayan, "meta-dependency" adlandırılan xüsusi Maven/Gradle paketidir.

Laravel-də hər funksiya üçün ayrı composer paketi yüklənir: `laravel/sanctum`, `laravel/passport`, `laravel/horizon`. Starter konsepti yoxdur — çünki Laravel sadələşdirməyə "default" ilə yanaşmışdır, Spring Boot isə "opinionated bundle" ilə.

Bu faylda starter-lərin nə üçün yarandığını, hansılarının mövcud olduğunu, necə istifadə etməyi və Laravel paket sistemi ilə müqayisəsini görəcəyik.

## Spring/Java-də istifadəsi

### 1. Starter nədir?

**Starter** — bir neçə transitive (dolayı) asılılıq gətirən və opinionated (öncədən seçilmiş) defaults tətbiq edən Maven/Gradle dependency-dir.

Məsələn, `spring-boot-starter-web` əlavə etdikdə, sən əslində bu kitabxanaları yükləyirsən:
- `spring-web` — Spring Web əsas sinfləri
- `spring-webmvc` — Model-View-Controller
- `spring-core`, `spring-context`, `spring-beans` — Spring Framework özək
- `tomcat-embed-core` — embedded Tomcat server
- `jackson-databind` — JSON serialization
- `validation-api` — Bean validation
- `logback-classic` — logging

Bütün bunları əllə yazmaq əvəzinə, **bir dependency**:

```xml
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-web</artifactId>
</dependency>
```

### 2. Əsas starter-lər

Ən çox istifadə olunan starter-lər:

| Starter | Nə edir? | İçində nə var? |
|---|---|---|
| `spring-boot-starter-web` | REST API, web tətbiqlər | Spring MVC, Tomcat, Jackson |
| `spring-boot-starter-data-jpa` | DB + ORM | Hibernate, JPA, Spring Data |
| `spring-boot-starter-security` | Authentication + Authorization | Spring Security |
| `spring-boot-starter-test` | Test | JUnit 5, Mockito, AssertJ, Spring Test |
| `spring-boot-starter-validation` | Bean validation | Hibernate Validator |
| `spring-boot-starter-webflux` | Reactive web | Netty, Reactor, WebFlux |
| `spring-boot-starter-actuator` | Monitoring | Health, metrics, info endpoint-lər |
| `spring-boot-starter-data-redis` | Redis | Lettuce/Jedis + Spring Data Redis |
| `spring-boot-starter-data-mongodb` | MongoDB | MongoDB driver + Spring Data |
| `spring-boot-starter-amqp` | RabbitMQ | Spring AMQP |
| `spring-boot-starter-mail` | E-poçt göndərmə | JavaMail |
| `spring-boot-starter-thymeleaf` | Server-side HTML | Thymeleaf template engine |
| `spring-boot-starter-oauth2-client` | OAuth2 client | Spring Security OAuth2 |
| `spring-boot-starter-cache` | Caching abstraction | Spring Cache + Caffeine default |

### 3. Maven-də starter əlavə etmək

`pom.xml` faylına yazırsan:

```xml
<dependencies>
    <!-- Web endpoint-lər -->
    <dependency>
        <groupId>org.springframework.boot</groupId>
        <artifactId>spring-boot-starter-web</artifactId>
    </dependency>

    <!-- DB + JPA -->
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

    <!-- Security -->
    <dependency>
        <groupId>org.springframework.boot</groupId>
        <artifactId>spring-boot-starter-security</artifactId>
    </dependency>

    <!-- Validation -->
    <dependency>
        <groupId>org.springframework.boot</groupId>
        <artifactId>spring-boot-starter-validation</artifactId>
    </dependency>

    <!-- Test (yalnız test üçün) -->
    <dependency>
        <groupId>org.springframework.boot</groupId>
        <artifactId>spring-boot-starter-test</artifactId>
        <scope>test</scope>
    </dependency>
</dependencies>
```

Diqqət: versiya nömrəsi yazılmır! Bu, `spring-boot-starter-parent`-in işidir.

### 4. Gradle-də starter əlavə etmək

`build.gradle`:

```groovy
dependencies {
    implementation 'org.springframework.boot:spring-boot-starter-web'
    implementation 'org.springframework.boot:spring-boot-starter-data-jpa'
    implementation 'org.springframework.boot:spring-boot-starter-security'
    implementation 'org.springframework.boot:spring-boot-starter-validation'

    runtimeOnly 'org.postgresql:postgresql'

    testImplementation 'org.springframework.boot:spring-boot-starter-test'
}
```

### 5. BOM və `spring-boot-starter-parent`

**BOM = Bill of Materials** — bir cədvəl fayl, hər kitabxana üçün hansı versiyanın istifadə olunacağını göstərir.

Niyə lazımdır? Çünki `spring-boot-starter-web` içində Jackson 2.15.3 var, amma sən başqa kitabxana əlavə edirsən ki, o Jackson 2.14 istəyir. Versiya konflikti olur. BOM bu konfliktləri həll edir.

```xml
<parent>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-parent</artifactId>
    <version>3.3.0</version>
    <relativePath/>
</parent>
```

`spring-boot-starter-parent` özü `spring-boot-dependencies`-dən irəli gəlir. O, **yüzlərlə kitabxananın** versiyalarını təyin edir ki, hamısı uyğun olsun.

Əgər parent istifadə etmək istəmirsənsə, BOM-u dependency management kimi import edə bilərsən:

```xml
<dependencyManagement>
    <dependencies>
        <dependency>
            <groupId>org.springframework.boot</groupId>
            <artifactId>spring-boot-dependencies</artifactId>
            <version>3.3.0</version>
            <type>pom</type>
            <scope>import</scope>
        </dependency>
    </dependencies>
</dependencyManagement>
```

### 6. Starter necə işləyir? (arxa plan)

`spring-boot-starter-web` Maven Central-da var. Onun `pom.xml`-inə baxsaq:

```xml
<dependencies>
    <dependency>
        <groupId>org.springframework.boot</groupId>
        <artifactId>spring-boot-starter</artifactId>
    </dependency>
    <dependency>
        <groupId>org.springframework.boot</groupId>
        <artifactId>spring-boot-starter-json</artifactId>
    </dependency>
    <dependency>
        <groupId>org.springframework.boot</groupId>
        <artifactId>spring-boot-starter-tomcat</artifactId>
    </dependency>
    <dependency>
        <groupId>org.springframework</groupId>
        <artifactId>spring-web</artifactId>
    </dependency>
    <dependency>
        <groupId>org.springframework</groupId>
        <artifactId>spring-webmvc</artifactId>
    </dependency>
</dependencies>
```

Sən `spring-boot-starter-web` əlavə edəndə, Maven/Gradle bu transitive dependency-ləri avtomatik həll edir.

### 7. Dependency tree-ni görmək

Layihədə hansı kitabxanaların yükləndiyini görmək üçün:

**Maven:**
```bash
./mvnw dependency:tree
```

Nəticə (qısa):
```
[INFO] com.example:demo:jar:0.0.1-SNAPSHOT
[INFO] +- org.springframework.boot:spring-boot-starter-web:jar:3.3.0
[INFO] |  +- org.springframework.boot:spring-boot-starter:jar:3.3.0
[INFO] |  +- org.springframework.boot:spring-boot-starter-json:jar:3.3.0
[INFO] |  |  +- com.fasterxml.jackson.core:jackson-databind:jar:2.17.1
[INFO] |  +- org.springframework.boot:spring-boot-starter-tomcat:jar:3.3.0
[INFO] |  |  +- org.apache.tomcat.embed:tomcat-embed-core:jar:10.1.24
...
```

**Gradle:**
```bash
./gradlew dependencies
```

### 8. Version konflikt həll etmək

Bəzən iki kitabxana eyni dependency-nin fərqli versiyalarını istəyir. Spring Boot parent bunu idarə edir, amma override etmək olar:

```xml
<properties>
    <jackson.version>2.17.2</jackson.version>
</properties>
```

Və ya konkret asılılıqda:

```xml
<dependency>
    <groupId>com.fasterxml.jackson.core</groupId>
    <artifactId>jackson-databind</artifactId>
    <version>2.17.2</version>  <!-- BOM-dakı versiyanı override edir -->
</dependency>
```

Exclude etmək:

```xml
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-web</artifactId>
    <exclusions>
        <exclusion>
            <groupId>org.springframework.boot</groupId>
            <artifactId>spring-boot-starter-tomcat</artifactId>
        </exclusion>
    </exclusions>
</dependency>

<!-- Tomcat əvəzinə Jetty -->
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-jetty</artifactId>
</dependency>
```

### 9. Custom starter (qısa)

Öz starter-ini yaratmaq olar (advanced mövzu):

```
my-starter/
├── pom.xml
└── src/main/java/com/example/mystarter/
    ├── MyStarterAutoConfiguration.java
    └── MyStarterProperties.java

src/main/resources/META-INF/spring/
└── org.springframework.boot.autoconfigure.AutoConfiguration.imports
```

İçində:
```
com.example.mystarter.MyStarterAutoConfiguration
```

`MyStarterAutoConfiguration.java`:
```java
@AutoConfiguration
@ConditionalOnClass(MyService.class)
@EnableConfigurationProperties(MyStarterProperties.class)
public class MyStarterAutoConfiguration {

    @Bean
    @ConditionalOnMissingBean
    public MyService myService(MyStarterProperties props) {
        return new MyService(props.getApiKey());
    }
}
```

Bu starter-i istifadə edən tətbiq `my-starter` dependency əlavə etsə, `MyService` bean-i avtomatik yaradılır.

## Laravel/PHP-də istifadəsi

Laravel-də **"starter" konsepti yoxdur**. Hər feature ayrı composer paketi ilə gəlir.

### 1. Composer ilə paket əlavə etmək

```bash
composer require laravel/sanctum
composer require laravel/horizon
composer require laravel/telescope --dev
```

`composer.json`:

```json
{
    "require": {
        "php": "^8.2",
        "laravel/framework": "^11.0",
        "laravel/sanctum": "^4.0",
        "laravel/passport": "^12.0",
        "laravel/horizon": "^5.0",
        "spatie/laravel-permission": "^6.0",
        "stripe/stripe-php": "^15.0"
    },
    "require-dev": {
        "laravel/telescope": "^5.0",
        "phpunit/phpunit": "^11.0",
        "fakerphp/faker": "^1.23"
    }
}
```

### 2. Laravel paket auto-discovery

Laravel paketləri `composer.json` içində özlərini qeyd edir:

```json
{
    "name": "laravel/sanctum",
    "extra": {
        "laravel": {
            "providers": [
                "Laravel\\Sanctum\\SanctumServiceProvider"
            ]
        }
    }
}
```

`composer install`-dan sonra Laravel bu provider-i avtomatik qeydiyyatdan keçirir.

### 3. Laravel-də feature aktivləşdirmə

```bash
# Sanctum üçün
composer require laravel/sanctum
php artisan install:api

# Horizon üçün
composer require laravel/horizon
php artisan horizon:install

# Passport üçün
composer require laravel/passport
php artisan passport:install
```

Hər paket öz setup əmrini gətirir — migration, config publish və s.

### 4. Tipik Laravel composer.json müqayisəli baxış

```json
{
    "require": {
        "php": "^8.2",

        "laravel/framework": "^11.0",      // core (Spring-də spring-boot-starter)
        "laravel/sanctum": "^4.0",          // auth (Spring-də spring-boot-starter-security)

        "doctrine/dbal": "^4.0",            // DB (Spring-də data-jpa)
        "predis/predis": "^2.2",            // Redis (Spring-də data-redis)

        "guzzlehttp/guzzle": "^7.8",        // HTTP client (Spring-də RestClient daxili)
        "league/flysystem-aws-s3-v3": "^3.0", // S3
        "pusher/pusher-php-server": "^7.2"  // Broadcasting
    }
}
```

### 5. Laravel-in "starter" oxşarları

Hərçənd "starter" yoxdur, Laravel-də bəzi birləşdirici paketlər var:

- **laravel/breeze** — sadə auth scaffolding
- **laravel/jetstream** — təkmilləşdirilmiş auth + team management
- **laravel/sail** — Docker development mühiti

Amma bunlar Spring starter-lərindən fərqlidir — onlar kod scaffold edir, dependency yükləmir.

## Əsas fərqlər

| Xüsusiyyət | Spring Boot Starter | Laravel Composer Package |
|---|---|---|
| **Konsept adı** | Starter (meta-dependency) | Package (sadə dependency) |
| **Versiya idarəsi** | BOM (`spring-boot-dependencies`) | `composer.json` əllə |
| **Opinionated default** | Bəli (auto-config ilə) | Qismən (service provider ilə) |
| **Transitive dependency** | Çox (bir starter → 10-15 paket) | Az (adətən 1-3 paket) |
| **Qeydiyyat** | Auto-config + `@EnableAutoConfiguration` | Package auto-discovery |
| **Aktivləşdirmə** | Dependency əlavə et → işləyir | Dependency əlavə et + setup əmri |
| **Versiya konflikti** | BOM həll edir | Composer resolver əllə |
| **Setup əmri** | Çox vaxt lazım deyil | Adətən `php artisan xxx:install` |
| **Classpath əsaslı aktivləşmə** | `@ConditionalOnClass` | Yoxdur |

## Niyə belə fərqlər var?

### Spring-in yanaşması

1. **Tarixi yük**: Spring Boot-dan əvvəl Java dünyası **ağır** idi. Adi bir web tətbiqi üçün 20-30 dependency əllə yazmaq, XML konfiqurasiyası, web.xml, applicationContext.xml... Bu cəhənnəm idi. Spring Boot bu ağrını starter-lərlə həll etdi.

2. **Enterprise-lar üçün**: Java çoxvaxt böyük şirkətlərdə istifadə olunur. Bir layihədə 100+ dependency olur. Starter-lər bu dependency-ləri kateqoriyalaşdırıb idarə etməyi asanlaşdırır.

3. **Type safety + version safety**: Java compiler versiya uyğunsuzluqlarını tapır. BOM bu yoxlamanı mərkəzi yerdə edir.

4. **Opinionated defaults**: "Biz nə üçün yenə düşünək ki? 90% hal üçün ən yaxşı konfiqurasiyanı başa düşmüşük. Sən yalnız fərqli istədiyini yaz."

### Laravel-in yanaşması

1. **Monolithic framework**: Laravel özü bir çox şeyi daxil edir (ORM, auth, cache, queue, mail). Əlavə paket az-az lazım olur.

2. **Developer experience**: Hər paketin öz CLI əmri var (`install`, `publish`). Bu, start-up-dakı "sadələşdirilmiş" yanaşmadır.

3. **PHP ekosistemi**: Composer-in özü Maven qədər güclü deyil, amma daha sadədir. Laravel bu sadəliyi saxlayır.

4. **Tarixi fərq**: Laravel 2011-də yarandı, Spring Boot 2014-də. Laravel PHP-nin "sadə" yanaşmasını götürdü, Spring Boot isə Java-nın "ağır" tarixini "yüngülləşdirdi".

## Ümumi səhvlər (Beginner traps)

### Səhv 1: Versiya yazmaq

```xml
<!-- YANLIŞ -->
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-web</artifactId>
    <version>3.3.0</version>  <!-- bu lazım deyil -->
</dependency>
```

`spring-boot-starter-parent` versiya idarə edir. Əllə versiya yazmaq BOM-u override edir və konfliktlər yarada bilər.

### Səhv 2: Düzgün scope istifadə etməmək

```xml
<!-- YANLIŞ - test kitabxanası production-a da gedir -->
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-test</artifactId>
</dependency>

<!-- DÜZGÜN -->
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-test</artifactId>
    <scope>test</scope>
</dependency>
```

### Səhv 3: DB driver-i unutmaq

```xml
<!-- Yalnız bu kifayət DEYIL! -->
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-data-jpa</artifactId>
</dependency>
```

JPA starter ORM verir, amma konkret DB driver-ini özün əlavə etməlisən:

```xml
<dependency>
    <groupId>org.postgresql</groupId>
    <artifactId>postgresql</artifactId>
    <scope>runtime</scope>
</dependency>
```

### Səhv 4: İki web starter

```xml
<!-- Hər ikisini əlavə etmə! -->
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-web</artifactId>
</dependency>
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-webflux</artifactId>
</dependency>
```

`spring-boot-starter-web` (MVC) və `spring-boot-starter-webflux` (Reactive) konflikt edir. Bir dənəsini seç.

### Səhv 5: Jetty əvəzinə Tomcat saxlamaq

Jetty istifadə etmək istəyirsənsə, əvvəlcə Tomcat-i exclude etməlisən:

```xml
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-web</artifactId>
    <exclusions>
        <exclusion>
            <groupId>org.springframework.boot</groupId>
            <artifactId>spring-boot-starter-tomcat</artifactId>
        </exclusion>
    </exclusions>
</dependency>
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-jetty</artifactId>
</dependency>
```

## Hansı framework-də var, hansında yoxdur?

### Yalnız Spring Boot-da

- **Starter meta-dependency**: bir paketdə 10-15 transitive dependency
- **BOM (Bill of Materials)**: mərkəzi versiya idarəsi
- **Auto-configuration + `@ConditionalOn*`**: classpath-ə görə bean-lər
- **Dependency tree**: `mvn dependency:tree` ilə analiz
- **Custom starter yaratmaq**: öz meta-dependency paketlərin

### Yalnız Laravel-də

- **Package auto-discovery**: `composer.json` extra.laravel
- **Artisan install əmrləri**: hər paketin öz setup-ı
- **Starter kit-lər (Breeze/Jetstream)**: kod scaffold edən paketlər
- **Composer `--dev` flag**: dev dependency-lər

### İkisində də var, fərqli

- **Dependency management**: Spring Maven/Gradle, Laravel Composer
- **Version qaydaları**: Spring BOM, Laravel semver (`^11.0`)
- **Transitive resolution**: Hər ikisi edir, amma Spring BOM ilə konflikti aradan qaldırır

## Mini müsahibə sualları

**Sual 1:** Spring Boot starter-i nədir və niyə yaradıldı?

*Cavab:* Starter — bir neçə bir-biri ilə əlaqəli kitabxananı saxlayan meta-dependency paketidir. Yaradılma səbəbi: Spring Boot-dan əvvəl Java web tətbiqi qurmaq üçün 20-30 dependency əllə yazmaq, versiya konfliklərini həll etmək, XML konfiqurasiya yazmaq lazım idi. Starter-lər bu prosesi "bir dependency əlavə et, işə başla" səviyyəsinə endirdi. Məsələn, `spring-boot-starter-web` tək dependency-dir, amma özündə Spring MVC, Tomcat, Jackson və digər 10+ kitabxana gətirir — hamısı uyğun versiyalarda.

**Sual 2:** `spring-boot-starter-parent` nəyə lazımdır? BOM nədir?

*Cavab:* `spring-boot-starter-parent` layihənin `<parent>` olaraq istifadə olunur və bütün kitabxana versiyalarını idarə edir. Özü də `spring-boot-dependencies` BOM (Bill of Materials)-ından gəlir. BOM bir cədvəl fayl-dır — yüzlərlə kitabxananın hansı versiyalarının uyğun olduğunu göstərir. Bu sayədə sən dependency əlavə edərkən versiya yazmırsan — parent avtomatik düzgün versiyanı seçir. Version konflikti olarsa, BOM həll edir. Parent istifadə etmək istəməsən, BOM-u `dependencyManagement` bölməsində `import` scope ilə əlavə edə bilərsən.

**Sual 3:** Sən `spring-boot-starter-data-jpa` əlavə etmisən, amma tətbiq "DataSource not found" xətası verir. Problem nədədir?

*Cavab:* `spring-boot-starter-data-jpa` JPA/Hibernate abstraksiyasını gətirir, amma konkret DB driver-i daxil etmir. Səbəb — hər layihə fərqli DB istifadə edə bilər (PostgreSQL, MySQL, Oracle, H2). Həll: əlavə olaraq driver dependency-si yaz, məsələn PostgreSQL üçün `org.postgresql:postgresql` və `application.properties`-də `spring.datasource.url`, `username`, `password` təyin et. Alternativ: yalnız test üçün `com.h2database:h2` əlavə etmək olar — Spring Boot H2-ni avtomatik in-memory DB kimi quraşdırır.
