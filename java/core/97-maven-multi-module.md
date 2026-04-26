# 97 — Maven Multi-Module Projects — Böyük Layihə Strukturu

> **Seviyye:** Senior ⭐⭐⭐

## Mündəricat
1. [Niyə Multi-Module?](#niyə-multi-module)
2. [Əsas struktur](#əsas-struktur)
3. [Parent POM](#parent-pom)
4. [Module POM-ları](#module-pom-ları)
5. [Module-lar arası dependency](#module-lar-arası-dependency)
6. [Real layihə nümunəsi](#real-layihə-nümunəsi)
7. [Build və run](#build-və-run)
8. [Gradle ilə müqayisə](#gradle-ilə-müqayisə)
9. [İntervyu Sualları](#intervyu-sualları)

---

## Niyə Multi-Module?

Böyük layihələrdə hamı eyni Maven modulunda işlərsə — build uzun çəkir, dependency idarəetməsi çətin olur, sınırlar pozulur.

```
Single module problemi:
  └── src/main/java/com/example/
      ├── controller/     ← API layer
      ├── service/        ← Business logic
      ├── repository/     ← DB access
      ├── domain/         ← Entities
      └── util/           ← Helpers

  Problem:
  - Controller birbaşa Repository-ni çağıra bilər (encapsulation yoxdur)
  - Bütün layihə bir build — kiçik dəyişiklik = hamı yenidən build
  - Test etmək çətin — hər şey birdir

Multi-module:
  - Hər modul ayrı JAR
  - Dependency-lər açıq müəyyən edilir
  - İstədiyiniz modulu ayrıca build/test edə bilərsiniz
```

---

## Əsas struktur

```
my-app/
├── pom.xml                    ← Parent POM (bütün modulları birləşdirir)
├── my-app-domain/             ← Entities, Value Objects
│   ├── pom.xml
│   └── src/main/java/
├── my-app-repository/         ← JPA, DB operations
│   ├── pom.xml
│   └── src/main/java/
├── my-app-service/            ← Business logic
│   ├── pom.xml
│   └── src/main/java/
├── my-app-api/                ← REST Controllers, DTOs
│   ├── pom.xml
│   └── src/main/java/
└── my-app-web/                ← Spring Boot entry point, main class
    ├── pom.xml
    └── src/main/java/
```

---

## Parent POM

```xml
<!-- my-app/pom.xml -->
<?xml version="1.0" encoding="UTF-8"?>
<project xmlns="http://maven.apache.org/POM/4.0.0"
         xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:schemaLocation="http://maven.apache.org/POM/4.0.0
         http://maven.apache.org/xsd/maven-4.0.0.xsd">

    <modelVersion>4.0.0</modelVersion>

    <!-- Parent: Spring Boot BOM (Bill of Materials) -->
    <parent>
        <groupId>org.springframework.boot</groupId>
        <artifactId>spring-boot-starter-parent</artifactId>
        <version>3.2.0</version>
        <relativePath/>
    </parent>

    <!-- Bu layihənin koordinatları: -->
    <groupId>com.example</groupId>
    <artifactId>my-app</artifactId>
    <version>1.0.0-SNAPSHOT</version>
    <packaging>pom</packaging>  <!-- ← pom, jar deyil! -->

    <!-- Modullar: -->
    <modules>
        <module>my-app-domain</module>
        <module>my-app-repository</module>
        <module>my-app-service</module>
        <module>my-app-api</module>
        <module>my-app-web</module>
    </modules>

    <!-- Bütün modullar üçün ortaq properties: -->
    <properties>
        <java.version>21</java.version>
        <mapstruct.version>1.5.5.Final</mapstruct.version>
        <lombok.version>1.18.30</lombok.version>
    </properties>

    <!-- dependencyManagement: versiyaları mərkəzləşdirər, amma avtomatik əlavə etmir -->
    <dependencyManagement>
        <dependencies>
            <!-- Öz modullarına ref: -->
            <dependency>
                <groupId>com.example</groupId>
                <artifactId>my-app-domain</artifactId>
                <version>${project.version}</version>
            </dependency>
            <dependency>
                <groupId>com.example</groupId>
                <artifactId>my-app-repository</artifactId>
                <version>${project.version}</version>
            </dependency>
            <dependency>
                <groupId>com.example</groupId>
                <artifactId>my-app-service</artifactId>
                <version>${project.version}</version>
            </dependency>
            <dependency>
                <groupId>com.example</groupId>
                <artifactId>my-app-api</artifactId>
                <version>${project.version}</version>
            </dependency>

            <!-- 3rd party versiyalar: -->
            <dependency>
                <groupId>org.mapstruct</groupId>
                <artifactId>mapstruct</artifactId>
                <version>${mapstruct.version}</version>
            </dependency>
        </dependencies>
    </dependencyManagement>

    <!-- Bütün modullara əlavə olunan dependency-lər: -->
    <dependencies>
        <dependency>
            <groupId>org.projectlombok</groupId>
            <artifactId>lombok</artifactId>
            <optional>true</optional>
        </dependency>
        <dependency>
            <groupId>org.springframework.boot</groupId>
            <artifactId>spring-boot-starter-test</artifactId>
            <scope>test</scope>
        </dependency>
    </dependencies>
</project>
```

---

## Module POM-ları

### Domain module:

```xml
<!-- my-app-domain/pom.xml -->
<project>
    <modelVersion>4.0.0</modelVersion>

    <parent>
        <groupId>com.example</groupId>
        <artifactId>my-app</artifactId>
        <version>1.0.0-SNAPSHOT</version>
    </parent>

    <artifactId>my-app-domain</artifactId>
    <packaging>jar</packaging>

    <!-- Domain heç bir Spring/DB dependency istəmir! -->
    <dependencies>
        <dependency>
            <groupId>jakarta.persistence</groupId>
            <artifactId>jakarta.persistence-api</artifactId>
        </dependency>
        <dependency>
            <groupId>jakarta.validation</groupId>
            <artifactId>jakarta.validation-api</artifactId>
        </dependency>
    </dependencies>
</project>
```

```java
// my-app-domain/src/main/java/com/example/domain/User.java
@Entity
@Table(name = "users")
@Getter @Setter @NoArgsConstructor
public class User {
    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;
    private String name;
    private String email;
}
```

### Repository module:

```xml
<!-- my-app-repository/pom.xml -->
<project>
    <parent>
        <groupId>com.example</groupId>
        <artifactId>my-app</artifactId>
        <version>1.0.0-SNAPSHOT</version>
    </parent>

    <artifactId>my-app-repository</artifactId>

    <dependencies>
        <!-- Öz domain moduluna dependency: -->
        <dependency>
            <groupId>com.example</groupId>
            <artifactId>my-app-domain</artifactId>
        </dependency>

        <!-- Spring Data JPA: -->
        <dependency>
            <groupId>org.springframework.boot</groupId>
            <artifactId>spring-boot-starter-data-jpa</artifactId>
        </dependency>
    </dependencies>
</project>
```

### Service module:

```xml
<!-- my-app-service/pom.xml -->
<project>
    <parent>
        <groupId>com.example</groupId>
        <artifactId>my-app</artifactId>
        <version>1.0.0-SNAPSHOT</version>
    </parent>

    <artifactId>my-app-service</artifactId>

    <dependencies>
        <dependency>
            <groupId>com.example</groupId>
            <artifactId>my-app-domain</artifactId>
        </dependency>
        <dependency>
            <groupId>com.example</groupId>
            <artifactId>my-app-repository</artifactId>
        </dependency>
    </dependencies>
</project>
```

### Web module — entry point:

```xml
<!-- my-app-web/pom.xml -->
<project>
    <parent>
        <groupId>com.example</groupId>
        <artifactId>my-app</artifactId>
        <version>1.0.0-SNAPSHOT</version>
    </parent>

    <artifactId>my-app-web</artifactId>

    <dependencies>
        <dependency>
            <groupId>com.example</groupId>
            <artifactId>my-app-api</artifactId>
        </dependency>
        <dependency>
            <groupId>com.example</groupId>
            <artifactId>my-app-service</artifactId>
        </dependency>
        <dependency>
            <groupId>org.springframework.boot</groupId>
            <artifactId>spring-boot-starter-web</artifactId>
        </dependency>
        <dependency>
            <groupId>org.springframework.boot</groupId>
            <artifactId>spring-boot-starter-data-jpa</artifactId>
        </dependency>
    </dependencies>

    <!-- Yalnız web modulunda spring-boot-maven-plugin: -->
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

---

## Module-lar arası dependency

```
Düzgün dependency istiqaməti (one-way):
  web → api → service → repository → domain

  domain:      heç kimə dependency yoxdur
  repository:  domain-ə dependency
  service:     domain + repository-ə dependency
  api:         service + domain-ə dependency (DTO üçün)
  web:         api + service-ə dependency

Yanlış:
  domain → service (cycle yaranır!)
  repository → api (layer pozuntusu)
```

```java
// Service modulda:
@Service
@RequiredArgsConstructor
public class UserService {
    private final UserRepository userRepo; // repository moduldan
    private final User user;               // domain moduldan — OK
}

// API modulda — Service interface istifadə etmək daha yaxşıdır:
// api modulu service implementation bilmirdi
@RestController
public class UserController {
    private final UserService userService; // service moduldan → OK
}

// Domain modulda service-ə istinad YANLIŞ:
@Entity
public class User {
    // Buraya @Autowired UserService userService; yazmaq OLMAZ
    // Domain layer heç bir Spring component tanımır
}
```

---

## Real layihə nümunəsi

### E-commerce layihəsi:

```
ecommerce/
├── pom.xml (parent)
├── ecommerce-domain/
│   └── src/main/java/com/shop/domain/
│       ├── User.java
│       ├── Product.java
│       ├── Order.java
│       └── OrderItem.java
│
├── ecommerce-repository/
│   └── src/main/java/com/shop/repository/
│       ├── UserRepository.java
│       ├── ProductRepository.java
│       └── OrderRepository.java
│
├── ecommerce-service/
│   └── src/main/java/com/shop/service/
│       ├── UserService.java
│       ├── ProductService.java
│       ├── OrderService.java
│       └── PaymentService.java
│
├── ecommerce-api/
│   └── src/main/java/com/shop/api/
│       ├── dto/
│       │   ├── CreateOrderRequest.java
│       │   └── OrderResponse.java
│       ├── controller/
│       │   ├── UserController.java
│       │   └── OrderController.java
│       └── mapper/
│           └── OrderMapper.java
│
└── ecommerce-web/
    └── src/main/java/com/shop/
        ├── Application.java   ← @SpringBootApplication
        └── resources/
            └── application.yml
```

### application.yml yalnız web modulunda:

```yaml
# ecommerce-web/src/main/resources/application.yml
spring:
  datasource:
    url: jdbc:postgresql://localhost:5432/ecommerce
  jpa:
    hibernate:
      ddl-auto: validate
server:
  port: 8080
```

---

## Build və run

```bash
# Root-dan bütün layihəni build et:
cd ecommerce/
mvn clean install

# Yalnız bir modulu build et:
mvn install -pl ecommerce-service -am
# -pl: project list (hansı modul)
# -am: also make (bu modulun dependency-lərini də build et)

# Test olmadan:
mvn install -DskipTests

# Web modulu run (Spring Boot):
cd ecommerce-web/
mvn spring-boot:run

# JAR-la run:
java -jar ecommerce-web/target/ecommerce-web-1.0.0-SNAPSHOT.jar

# Müəyyən profilllə:
java -jar ecommerce-web/target/*.jar --spring.profiles.active=prod
```

---

## Gradle ilə müqayisə

```groovy
// settings.gradle (Gradle multi-module):
rootProject.name = 'my-app'
include 'my-app-domain'
include 'my-app-repository'
include 'my-app-service'
include 'my-app-api'
include 'my-app-web'

// build.gradle (root):
plugins {
    id 'org.springframework.boot' version '3.2.0' apply false
    id 'io.spring.dependency-management' version '1.1.4' apply false
}

subprojects {
    apply plugin: 'java'
    apply plugin: 'io.spring.dependency-management'

    dependencyManagement {
        imports {
            mavenBom "org.springframework.boot:spring-boot-dependencies:3.2.0"
        }
    }
}

// my-app-service/build.gradle:
dependencies {
    implementation project(':my-app-domain')
    implementation project(':my-app-repository')
}
```

| Xüsusiyyət | Maven | Gradle |
|-----------|-------|--------|
| Konfiqurasiya | XML, verbose | Groovy/Kotlin, qısa |
| Build cache | Zəif | Güclü |
| Incremental build | Yox (plugin lazımdır) | Var |
| Convention | Daha çox | Daha az |
| CI/CD dəstəyi | Geniş | Geniş |

---

## İntervyu Sualları

**S: Multi-module layihənin üstünlükləri nədir?**
C: Separation of concerns — layer-lər arasında sınırlar compile-time-da məcbur edilir. Incremental build — yalnız dəyişən modul build olunur. Reuse — domain modulu başqa layihə tərəfindən istifadə edilə bilər. Testability — hər modulun ayrıca unit testi.

**S: `<dependencyManagement>` vs `<dependencies>` fərqi?**
C: `<dependencyManagement>` — versiyaları mərkəzləşdirir, amma modullara avtomatik əlavə etmir. `<dependencies>` — bütün modullara əlavə olunur. Versiya yalnız `<dependencyManagement>`-da müəyyən edilir, child modulda yalnız `<dependency>` (version olmadan) yazılır.

**S: Hangi modulda @SpringBootApplication olur?**
C: Adətən ən son (entry point) modulda — bizim nümunədə `web` modulu. Bu modul bütün digər modulları dependency kimi alır. Spring Boot maven plugin da yalnız bu modulun `pom.xml`-ında olur.

**S: Modul A modulunda modul B-nin dependency-si olduğu müəyyən edilmişdir. Modul A-nı build edərkən B-yi də build etmək üçün nə etmək lazımdır?**
C: `mvn install -pl module-a -am` — `-am` (also make) flag-i. Bu `module-a`-nın bütün dependency modullarını əvvəlcə build edir.
