# 01 — İlk Spring Boot Tətbiqi (Spring Initializr ilə)

> **Seviyye:** Intermediate ⭐⭐


## Mündəricat
1. [Spring Framework vs Spring Boot](#spring-vs-boot)
2. [Spring Initializr — start.spring.io](#initializr)
3. [Maven vs Gradle seçimi](#maven-gradle)
4. [Dependencies (bağlılıqlar) seçimi](#dependencies)
5. [Layihənin strukturu (skelet)](#struktur)
6. [IntelliJ IDEA-da açmaq](#intellij)
7. [@SpringBootApplication — əsas sinif sətir-sətir](#main-class)
8. [Tətbiqi işə salmaq (run)](#run)
9. [İlk @RestController — "Salam Dünya"](#salam-dunya)
10. [DevTools və canlı yenilənmə (live reload)](#devtools)
11. [Executable JAR paketləmə](#jar)
12. [Embedded Tomcat nə deməkdir?](#embedded)
13. [Tipik ilk layihə xətaları](#errors)
14. [Tam nümunə: kiçik TODO REST API](#todo-api)
15. [Ümumi Səhvlər](#umumi-sehvler)
16. [İntervyu Sualları](#intervyu)

---

## 1. Spring Framework vs Spring Boot {#spring-vs-boot}

Çox yeni başlayanlar bu iki ifadəni qarışdırır. Sadə dillə:

- **Spring Framework** — böyük, güclü, lakin konfiqurasiyası mürəkkəb kitabxana topludur.
- **Spring Boot** — Spring Framework üzərində qurulmuş, konfiqurasiyaları avtomatlaşdıran "wrapper"dir.

### Spring Framework (klassik) ilə nə etmək lazım idi?

```xml
<!-- web.xml, servlet context, dispatcher servlet, view resolver... -->
<!-- 100+ sətrlik XML konfiqurasiya -->
<bean id="dataSource" class="...">
    <property name="url" value="..."/>
    <property name="username" value="..."/>
    <!-- ... -->
</bean>
<bean id="sessionFactory" class="...">
    <!-- ... -->
</bean>
<!-- Tomcat-ı əl ilə quraşdır, war fayl yarat, deploy et -->
```

### Spring Boot ilə indi:

```java
@SpringBootApplication
public class MyApp {
    public static void main(String[] args) {
        SpringApplication.run(MyApp.class, args);
    }
}
```

Və `application.properties`-də bir neçə sətir — vəssalam!

### Müqayisə cədvəli:

| Xüsusiyyət | Spring Framework | Spring Boot |
|---|---|---|
| XML konfiqurasiya | Zəruri | Demək olar yoxdur |
| Server | Xarici (Tomcat/Jetty) əl ilə | Embedded (JAR içində) |
| Dependency idarəsi | Əl ilə versiyalar | Starter parent vasitəsilə |
| Auto-configuration | Yoxdur | Var (classpath-ə görə) |
| Production-ready features | Əlavə konfiqurasiya | Actuator hazır |
| Hello World zamanı | 30 dəqiqə | 3 dəqiqə |

### Analogiya

Spring Framework — mebel mağazasından bütün hissələri ayrı-ayrı alıb özün yığmağa bənzəyir.
Spring Boot — IKEA-nın hazır komplektidir: hissələr qutudadır, təlimat var, alətlər daxildir.

---

## 2. Spring Initializr — start.spring.io {#initializr}

**Spring Initializr** — rəsmi Spring komandasının təmin etdiyi layihə yaradıcısıdır.
URL: [https://start.spring.io](https://start.spring.io)

### Sayta daxil olduqda seçəcəyin sahələr:

```
┌─────────────────────────────────────────────────────┐
│ Project:     ( ) Maven    (•) Gradle - Groovy       │
│ Language:    (•) Java     ( ) Kotlin   ( ) Groovy   │
│ Spring Boot: (•) 3.3.5    ( ) 3.2.11   ( ) SNAPSHOT │
│                                                     │
│ Project Metadata:                                   │
│   Group:        com.example                         │
│   Artifact:     demo                                │
│   Name:         demo                                │
│   Description:  Demo project for Spring Boot        │
│   Package name: com.example.demo                    │
│   Packaging:    (•) Jar    ( ) War                  │
│   Java:         ( ) 17    (•) 21    ( ) 23          │
│                                                     │
│ Dependencies:  [+ ADD DEPENDENCIES]                 │
│   • Spring Web                                      │
│   • Lombok                                          │
│   • Spring Boot DevTools                            │
└─────────────────────────────────────────────────────┘
        [ GENERATE ]    [ EXPLORE ]    [ SHARE ]
```

### Hər sahənin mənası:

| Sahə | Nə seçmək lazımdır? |
|---|---|
| **Project** | Maven (daha çox istifadə olunur, yeni başlayanlar üçün) və ya Gradle |
| **Language** | Java |
| **Spring Boot** | Ən son **stabil** versiya (3.3.x, 3.4.x) — SNAPSHOT və ya M (milestone) versiyaları istehsalat üçün seçmə |
| **Group** | Şirkətin reverse domain-i — `com.example`, `az.mycompany` |
| **Artifact** | Layihə adı — `todo-api` |
| **Packaging** | JAR (müasir) — WAR yalnız köhnə servlet serverlərinə deploy üçün |
| **Java** | **21** (LTS) və ya 17 (LTS). Spring Boot 3.x üçün minimum Java 17 lazımdır |

### Addımlar:

1. Sahələri doldur.
2. **ADD DEPENDENCIES** düyməsinə bas və lazımi bağlılıqları seç.
3. **GENERATE** düyməsinə bas — `demo.zip` faylı yüklənir.
4. Faylı çıxart və IDE-də aç.

---

## 3. Maven vs Gradle seçimi {#maven-gradle}

Bu, layihənin **build sistemidir** — kodu kompilyasiya edən, dependency-ləri yükləyən, JAR paketləyən alət.

### Sürətli müqayisə:

| Meyar | Maven | Gradle |
|---|---|---|
| Konfiqurasiya faylı | `pom.xml` (XML) | `build.gradle` (Groovy) və ya `build.gradle.kts` (Kotlin) |
| Sintaksis | Deklarativ, uzun | Qısa, koda bənzər |
| Sürət | Orta | Daha sürətli (incremental build) |
| Öyrənmə əyrisi | Daha sadə | Bir az çətin |
| Icma | Çox böyük | Böyük (Android-də standartdır) |
| Spring Boot dəstəyi | Mükəmməl | Mükəmməl |

### Maven `pom.xml` nümunəsi:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<project xmlns="http://maven.apache.org/POM/4.0.0">
    <modelVersion>4.0.0</modelVersion>

    <parent>
        <groupId>org.springframework.boot</groupId>
        <artifactId>spring-boot-starter-parent</artifactId>
        <version>3.3.5</version>
    </parent>

    <groupId>com.example</groupId>
    <artifactId>demo</artifactId>
    <version>0.0.1-SNAPSHOT</version>

    <properties>
        <java.version>21</java.version>
    </properties>

    <dependencies>
        <dependency>
            <groupId>org.springframework.boot</groupId>
            <artifactId>spring-boot-starter-web</artifactId>
        </dependency>

        <dependency>
            <groupId>org.projectlombok</groupId>
            <artifactId>lombok</artifactId>
            <optional>true</optional>
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

### Gradle `build.gradle` (Groovy) nümunəsi:

```groovy
plugins {
    id 'java'
    id 'org.springframework.boot' version '3.3.5'
    id 'io.spring.dependency-management' version '1.1.6'
}

group = 'com.example'
version = '0.0.1-SNAPSHOT'

java {
    toolchain {
        languageVersion = JavaLanguageVersion.of(21)
    }
}

repositories {
    mavenCentral()
}

dependencies {
    implementation 'org.springframework.boot:spring-boot-starter-web'

    compileOnly 'org.projectlombok:lombok'
    annotationProcessor 'org.projectlombok:lombok'
}
```

**Tövsiyə:** ilk tətbiqiniz üçün **Maven** seçin — sənədlər daha çoxdur, səhv olsa yardım tapmaq asandır.

---

## 4. Dependencies (bağlılıqlar) seçimi {#dependencies}

İlk layihəniz üçün ən çox istifadə edilən starter-lər:

| Starter | Nə üçün? |
|---|---|
| **Spring Web** | REST API qurmaq üçün (ən vacib) — Tomcat + Spring MVC daxildir |
| **Spring Boot DevTools** | Faylı dəyişdikdə tətbiq avtomatik yenidən başlayır |
| **Lombok** | Getter/setter/constructor yazmamaq üçün — `@Data`, `@Getter` annotasiyaları |
| **Spring Data JPA** | Verilənlər bazası ilə işləmək üçün (ORM) |
| **H2 Database** | İn-memory test DB — quraşdırma tələb etmir |
| **Validation** | DTO-larda `@NotNull`, `@Email` kimi yoxlamalar |
| **Spring Boot Actuator** | Health check, metrics endpoint-ləri |

### İlk layihə üçün tövsiyə:

```
Spring Web          (məcburi — REST üçün)
Spring Boot DevTools (tövsiyə — live reload)
Lombok              (tövsiyə — boilerplate azaldır)
Validation          (tövsiyə — input yoxlama)
```

Hələ ki **JPA və H2 seçmə** — sadə controller-dən başlayaq.

---

## 5. Layihənin strukturu (skelet) {#struktur}

`demo.zip` faylını çıxartdıqdan sonra belə struktur görəcəksən:

```
demo/
├── .mvn/                          # Maven wrapper — mvn quraşdırmasız işləmək
│   └── wrapper/
├── mvnw                           # Unix/Mac üçün Maven wrapper skripti
├── mvnw.cmd                       # Windows üçün Maven wrapper skripti
├── pom.xml                        # Maven konfiqurasiya faylı
├── .gitignore                     # Git-in nəzərə almayacağı fayllar
├── HELP.md                        # Avtomatik yaradılan yardım faylı
└── src/
    ├── main/
    │   ├── java/
    │   │   └── com/example/demo/
    │   │       └── DemoApplication.java   # Əsas sinif (giriş nöqtəsi)
    │   └── resources/
    │       ├── application.properties     # Konfiqurasiya faylı
    │       ├── static/                    # CSS/JS/şəkillər
    │       └── templates/                 # Thymeleaf HTML (əgər istifadə olunursa)
    └── test/
        └── java/
            └── com/example/demo/
                └── DemoApplicationTests.java  # İlk test faylı
```

### Hər qovluğun məqsədi:

| Yol | Nə var? |
|---|---|
| `src/main/java/` | Bütün Java kodunu buraya yaz |
| `src/main/resources/` | Konfiqurasiya, şablon, statik resurslar |
| `src/main/resources/application.properties` | Port, DB URL, logging — tətbiqin ayarları |
| `src/main/resources/static/` | Brauzerdən birbaşa əlçatan fayllar (CSS, JS) |
| `src/main/resources/templates/` | Server-side HTML şablonları (Thymeleaf) |
| `src/test/java/` | JUnit test sinifləri |
| `pom.xml` | Bağlılıqlar və build konfiqurasiyası |
| `mvnw` / `mvnw.cmd` | Sisteminizdə Maven olmasa da işləyir |

---

## 6. IntelliJ IDEA-da açmaq {#intellij}

### Addım-addım:

1. **IntelliJ IDEA**-ni aç (Community versiyası pulsuzdur və kifayət edir).
2. **File → Open** seçərək `demo` qovluğunu seç (ZIP-dən çıxartdığın).
3. IntelliJ `pom.xml`-i tanıyıb "Maven Project" olaraq açacaq.
4. Sağ altda "Loading Maven dependencies..." yazısını görəcəksən — bu Maven-in bütün JAR faylları internetdən yükləməsidir.
5. Yükləmə bitdikdə sol tərəfdə layihə strukturu görünəcək.

### Vacib qeydlər:

- **İlk açılışda** dependency yükləməsi 2-5 dəqiqə çəkə bilər.
- IntelliJ `Trust Project` soruşa bilər — **Trust** de.
- Sağ üst küncdə JDK versiyasını yoxla: **File → Project Structure → Project SDK** → Java 21 seçilmişdir.

### Faydalı qısayollar (IntelliJ):

| Qısayol (Linux/Win) | Qısayol (Mac) | Nə edir? |
|---|---|---|
| `Shift+Shift` | `⇧⇧` | Hər şeyi axtar |
| `Ctrl+N` | `⌘O` | Sinif axtar |
| `Ctrl+Shift+F10` | `⌃⇧R` | Cari faylı işə sal |
| `Ctrl+Space` | `⌃Space` | Auto-complete |
| `Alt+Enter` | `⌥⏎` | Quick fix təklifləri |

---

## 7. @SpringBootApplication — əsas sinif sətir-sətir {#main-class}

Layihə açıldıqdan sonra `DemoApplication.java`-nı aç:

```java
package com.example.demo;                                           // 1

import org.springframework.boot.SpringApplication;                  // 2
import org.springframework.boot.autoconfigure.SpringBootApplication; // 3

@SpringBootApplication                                              // 4
public class DemoApplication {                                      // 5

    public static void main(String[] args) {                        // 6
        SpringApplication.run(DemoApplication.class, args);         // 7
    }
}
```

### Sətir-sətir izah:

**Sətir 1 — `package com.example.demo;`**
Bu sinfin yerləşdiyi paketdir. Spring Boot `@ComponentScan` ilə **bu paketi və onun alt paketlərini** avtomatik skan edir. Yəni `@Service`, `@Controller` kimi komponentlər bu paketin daxilində olmalıdır.

**Sətir 4 — `@SpringBootApplication`**
Bu "meta-annotasiya" 3 annotasiyanın birləşməsidir:

```java
@SpringBootApplication = @SpringBootConfiguration  // bu sinif konfiqurasiya mənbəyidir
                      + @EnableAutoConfiguration   // classpath-ə görə avtomatik konfiqurasiya
                      + @ComponentScan             // @Component, @Service, @Controller tap
```

**Sətir 6 — `public static void main(String[] args)`**
Standart Java giriş nöqtəsi (entry point). JVM tətbiqi bu metoddan başladır.

**Sətir 7 — `SpringApplication.run(...)`**
Bu sətir bütün sehri həyata keçirir:

1. Application context (bean konteyner) yaradır.
2. Classpath-i skan edir.
3. `@ComponentScan`-a əsasən bean-lar aşkar edir.
4. Auto-configuration tətbiq edir.
5. Embedded Tomcat-ı işə salır (əgər `spring-web` varsa).
6. Port 8080-də HTTP sorğuları gözləyir.

---

## 8. Tətbiqi işə salmaq (run) {#run}

Üç yolla tətbiqi başlada bilərsən.

### Üsul 1 — IntelliJ Run düyməsi

`DemoApplication` faylını aç, `main` metodun yanındakı **yaşıl ox** işarəsinə klik et → **Run 'DemoApplication.main()'**.

### Üsul 2 — Maven (command line)

```bash
# Layihə qovluğunda:
./mvnw spring-boot:run

# Windows-da:
mvnw.cmd spring-boot:run

# Sistemdə Maven quraşdırılıbsa:
mvn spring-boot:run
```

### Üsul 3 — Gradle (command line)

```bash
./gradlew bootRun

# Windows-da:
gradlew.bat bootRun
```

### Uğurlu başlanğıc çıxışı belədir:

```
  .   ____          _            __ _ _
 /\\ / ___'_ __ _ _(_)_ __  __ _ \ \ \ \
( ( )\___ | '_ | '_| | '_ \/ _` | \ \ \ \
 \\/  ___)| |_)| | | | | || (_| |  ) ) ) )
  '  |____| .__|_| |_|_| |_\__, | / / / /
 =========|_|==============|___/=/_/_/_/
 :: Spring Boot ::        (v3.3.5)

2026-04-24 10:30:15.123  INFO --- Starting DemoApplication using Java 21
2026-04-24 10:30:16.456  INFO --- Tomcat initialized with port 8080 (http)
2026-04-24 10:30:16.789  INFO --- Tomcat started on port 8080 (http) with context path '/'
2026-04-24 10:30:16.890  INFO --- Started DemoApplication in 1.823 seconds
```

### "Tomcat started on port 8080" nə deməkdir?

- **Tomcat** — Java üçün ən məşhur HTTP server.
- **Port 8080** — şəbəkə portu. Brauzerdə `http://localhost:8080` ünvanı ilə daxil olmaq olar.
- **Embedded** — Tomcat ayrıca quraşdırılmır; JAR-ın içindədir.

Brauzerdə `http://localhost:8080` aç — hələ ki **Whitelabel Error Page** görəcəksən. Bu normaldır — heç bir endpoint yazmamışıq.

---

## 9. İlk @RestController — "Salam Dünya" {#salam-dunya}

Gəlin ilk endpoint-i yazaq.

### Addım 1 — yeni sinif yarat

`src/main/java/com/example/demo/` içində yeni fayl: **`HelloController.java`**.

```java
package com.example.demo;

import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.RestController;

@RestController
public class HelloController {

    @GetMapping("/hello")
    public String sayHello() {
        return "Salam Dünya!";
    }
}
```

### Sətir-sətir:

- `@RestController` — bu sinif REST endpoint-lər ehtiva edir; qaytarılan dəyər birbaşa HTTP body-yə yazılır.
- `@GetMapping("/hello")` — `GET /hello` sorğusu gələndə bu metod çağırılır.
- Metod `String` qaytarır — Spring bunu response body-yə yazır.

### Yoxlamaq:

1. Tətbiqi restart et (DevTools varsa avtomatik).
2. Brauzerdə: `http://localhost:8080/hello`
3. Ekranda: **Salam Dünya!**

### JSON qaytaran endpoint:

```java
@RestController
public class GreetingController {

    @GetMapping("/greeting")
    public Greeting greet() {
        // Jackson avtomatik JSON-a çevirir
        return new Greeting("Salam", "Əli");
    }

    // Record — Java 16+ qısa DTO sintaksisi
    record Greeting(String message, String name) {}
}
```

Nəticə:
```json
{
  "message": "Salam",
  "name": "Əli"
}
```

---

## 10. DevTools və canlı yenilənmə (live reload) {#devtools}

**Spring Boot DevTools** inkişaf zamanı faylları dəyişdikdə tətbiqi **avtomatik yenidən başladır**.

### pom.xml-də:

```xml
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-devtools</artifactId>
    <scope>runtime</scope>
    <optional>true</optional>
</dependency>
```

### Necə işləyir?

- DevTools iki sinif yükləyicisi (classloader) istifadə edir: `base` (nadir dəyişən) və `restart` (tez-tez dəyişən kod).
- Kodu dəyişdikdə yalnız `restart` classloader yenidən yüklənir → **tam restart-dan 3-5 dəfə sürətli**.

### IntelliJ-də aktivləşdirmək:

1. **Settings → Build, Execution, Deployment → Compiler** → `Build project automatically` seçimini yandır.
2. **Settings → Advanced Settings** → `Allow auto-make to start even if developed application is currently running` seçimini yandır.

Bu iki seçim olmadan DevTools işləməz!

### Dəyişiklik ssenarisi:

```
1. HelloController.java-da String-i dəyişirsən: "Salam Dünya!" → "Salam Baku!"
2. Faylı yadda saxla (Ctrl+S)
3. 1-2 saniyə içində loglarda: "Restarting Spring-managed services..."
4. Brauzerdə səhifəni yenilə — yeni mətn!
```

---

## 11. Executable JAR paketləmə {#jar}

İnkişaf bitdikdə tətbiqi **tək bir JAR fayl** kimi paketləyirik — serverə göndəririk və işə salırıq.

### Paket əmri:

```bash
# Maven:
./mvnw clean package

# Gradle:
./gradlew bootJar
```

Nəticə: `target/demo-0.0.1-SNAPSHOT.jar` (Maven) və ya `build/libs/demo-0.0.1-SNAPSHOT.jar` (Gradle).

### JAR-ı işə sal:

```bash
java -jar target/demo-0.0.1-SNAPSHOT.jar
```

### Fat JAR (uber JAR) nədir?

Spring Boot-un JAR-ı **fat JAR**-dır — tətbiqin koduna əlavə olaraq bütün bağlılıqları (Tomcat, Jackson, Hibernate, ...) daxilindədir.

```
demo-0.0.1-SNAPSHOT.jar (35 MB)
├── BOOT-INF/
│   ├── classes/       # Sənin kodun
│   ├── lib/           # Bütün bağlılıqlar
│   │   ├── spring-core-6.1.x.jar
│   │   ├── tomcat-embed-core-10.1.x.jar
│   │   └── ...
│   └── classpath.idx
├── META-INF/
│   └── MANIFEST.MF    # Main-Class qeyd olunub
└── org/               # Spring Boot loader
```

### Fərqli port ilə işə sal:

```bash
java -jar demo.jar --server.port=9090
```

### Profil ilə:

```bash
java -jar demo.jar --spring.profiles.active=prod
```

---

## 12. Embedded Tomcat nə deməkdir? {#embedded}

**Klassik (köhnə) yanaşma:**

```
1. Apache Tomcat-ı serverə quraşdır
2. Tətbiqini WAR fayl kimi paketlə
3. WAR faylı Tomcat-ın webapps/ qovluğuna at
4. Tomcat-ı yenidən başlat
```

**Spring Boot yanaşması (embedded):**

```
1. JAR fayl paketlə (Tomcat içindədir)
2. java -jar myapp.jar
3. Hazır!
```

### Üstünlüklər:

- **Konteyner-friendly** — Docker üçün ideal (bir JAR = bir konteyner).
- **Versiya nəzarəti** — hər tətbiq öz Tomcat versiyasını gətirir.
- **Sadəlik** — server quraşdırmaq lazım deyil.
- **Microservice-lərə uyğundur** — hər xidmət öz server-i ilə işləyir.

### Tomcat əvəzinə başqa server?

```xml
<!-- Tomcat-ı söndür, Jetty istifadə et -->
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

Alternativlər: Undertow, Netty (WebFlux üçün).

---

## 13. Tipik ilk layihə xətaları {#errors}

### Xəta 1: Port 8080 is already in use

```
Web server failed to start. Port 8080 was already in use.
```

**Səbəb:** Başqa proses artıq 8080 portunu istifadə edir (bəlkə əvvəlki Spring Boot instansı).

**Həll:**
```bash
# Linux/Mac — portu istifadə edən prosesi tap:
lsof -i :8080
kill -9 <PID>

# Windows:
netstat -ano | findstr :8080
taskkill /PID <PID> /F

# Və ya fərqli port seç:
# application.properties:
server.port=8081
```

### Xəta 2: Unsupported class file major version

```
Unsupported class file major version 65
```

**Səbəb:** JAR Java 21 (major 65) ilə compile olunub, lakin sən Java 17 ilə işə salırsan.

**Həll:**
```bash
java -version   # cari versiyanı yoxla
# JAVA_HOME dəyişkənini dəyişdir və ya uyğun JDK quraşdır
```

### Xəta 3: "Whitelabel Error Page"

Brauzerdə:
```
Whitelabel Error Page
This application has no explicit mapping for /error
```

**Səbəb:** Bu URL üçün controller yoxdur.

**Həll:** `http://localhost:8080/hello` (mövcud endpoint) aç və ya yeni `@GetMapping` əlavə et.

### Xəta 4: Could not resolve dependencies

```
Could not resolve dependencies for project com.example:demo:jar:0.0.1-SNAPSHOT
```

**Səbəb:** İnternetsiz və ya Maven repository-yə çıxış yoxdur.

**Həll:**
- İnternet bağlantısını yoxla.
- Korporativ şəbəkədəsənsə, proxy ayarla (`~/.m2/settings.xml`).

### Xəta 5: Lombok işləmir (getter/setter "cannot find symbol")

**Səbəb:** IntelliJ-də **Annotation Processing** söndürülüb.

**Həll:**
**Settings → Build, Execution, Deployment → Compiler → Annotation Processors** → `Enable annotation processing` seçimini yandır.

### Xəta 6: Main class yoxdur

```
no main manifest attribute, in target/demo.jar
```

**Səbəb:** `spring-boot-maven-plugin` `pom.xml`-də yoxdur və ya JAR paketlənməyib.

**Həll:** `pom.xml`-də plugin-in olduğundan əmin ol:
```xml
<plugin>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-maven-plugin</artifactId>
</plugin>
```

---

## 14. Tam nümunə: kiçik TODO REST API {#todo-api}

Gəlin 4 endpoint-li memory-based TODO API yaradaq.

### Addım 1 — Model (Todo sinifi)

```java
// src/main/java/com/example/demo/Todo.java
package com.example.demo;

public class Todo {
    private Long id;
    private String title;
    private boolean done;

    // Default konstruktor (Jackson üçün lazımdır)
    public Todo() {}

    public Todo(Long id, String title, boolean done) {
        this.id = id;
        this.title = title;
        this.done = done;
    }

    // Getter/setter-lər
    public Long getId() { return id; }
    public void setId(Long id) { this.id = id; }

    public String getTitle() { return title; }
    public void setTitle(String title) { this.title = title; }

    public boolean isDone() { return done; }
    public void setDone(boolean done) { this.done = done; }
}
```

### Addım 2 — Controller (4 endpoint)

```java
// src/main/java/com/example/demo/TodoController.java
package com.example.demo;

import org.springframework.http.HttpStatus;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.*;

import java.util.ArrayList;
import java.util.List;
import java.util.Optional;
import java.util.concurrent.atomic.AtomicLong;

@RestController
@RequestMapping("/api/todos")
public class TodoController {

    // Sadə memory storage (istehsal üçün DB lazımdır!)
    private final List<Todo> todos = new ArrayList<>();
    private final AtomicLong idCounter = new AtomicLong(1);

    // 1) Bütün todo-ları al
    // GET /api/todos
    @GetMapping
    public List<Todo> getAll() {
        return todos;
    }

    // 2) ID-yə görə tək todo al
    // GET /api/todos/1
    @GetMapping("/{id}")
    public ResponseEntity<Todo> getOne(@PathVariable Long id) {
        Optional<Todo> found = todos.stream()
            .filter(t -> t.getId().equals(id))
            .findFirst();

        return found
            .map(ResponseEntity::ok)                     // 200 tap
            .orElse(ResponseEntity.notFound().build());  // 404 yoxdur
    }

    // 3) Yeni todo əlavə et
    // POST /api/todos
    @PostMapping
    @ResponseStatus(HttpStatus.CREATED)  // 201 qaytar
    public Todo create(@RequestBody Todo input) {
        input.setId(idCounter.getAndIncrement());
        todos.add(input);
        return input;
    }

    // 4) Todo-nu sil
    // DELETE /api/todos/1
    @DeleteMapping("/{id}")
    @ResponseStatus(HttpStatus.NO_CONTENT)  // 204
    public void delete(@PathVariable Long id) {
        todos.removeIf(t -> t.getId().equals(id));
    }
}
```

### Addım 3 — Test et (curl ilə)

```bash
# 1) Yeni todo yarat
curl -X POST http://localhost:8080/api/todos \
  -H "Content-Type: application/json" \
  -d '{"title":"Spring Boot öyrən","done":false}'
# Cavab: {"id":1,"title":"Spring Boot öyrən","done":false}

# 2) Bütün todo-ları al
curl http://localhost:8080/api/todos
# Cavab: [{"id":1,"title":"Spring Boot öyrən","done":false}]

# 3) Tək todo al
curl http://localhost:8080/api/todos/1

# 4) Mövcud olmayan ID
curl -i http://localhost:8080/api/todos/999
# HTTP/1.1 404 Not Found

# 5) Sil
curl -X DELETE http://localhost:8080/api/todos/1
```

**Təbriklər!** İlk Spring Boot REST API-n hazırdır.

---

## 15. Ümumi Səhvlər {#umumi-sehvler}

### Səhv 1: Main sinfi başqa paketdə yaratmaq

```java
// YANLIŞ — controller paketdən kənardadır
com.example.demo.DemoApplication       // main sinif
com.acme.controllers.UserController    // SCAN EDİLMƏYƏCƏK!

// DOĞRU — controller main sinfin paketindədir və ya alt paketində
com.example.demo.DemoApplication
com.example.demo.controller.UserController
```

### Səhv 2: `@Controller` istifadə edib REST gözləmək

```java
// YANLIŞ
@Controller
@GetMapping("/hello")
public String hello() { return "Salam"; }  // "Salam" view adı kimi axtarılır!

// DOĞRU
@RestController
@GetMapping("/hello")
public String hello() { return "Salam"; }  // Mətn birbaşa body-yə yazılır
```

### Səhv 3: Lombok istifadə edib annotation processing yandırmamaq

Kodda `@Data` var, IDE error verir: `cannot find method getName()`.
Həll: Annotation processing yandır.

### Səhv 4: İstehsalat üçün DevTools saxlamaq

DevTools yalnız inkişaf üçündür — production JAR-da əlavə resurs yeyir. `scope=runtime` və `optional=true` sayəsində avtomatik olaraq production JAR-a daxil edilmir.

### Səhv 5: `SNAPSHOT` versiyalar istifadə etmək

```
Spring Boot: 3.5.0-SNAPSHOT   — YANLIŞ (inkişafdadır, dəyişə bilər)
Spring Boot: 3.3.5            — DOĞRU (stabil)
```

---

## 16. İntervyu Sualları {#intervyu}

**S: Spring Framework və Spring Boot arasında fərq nədir?**
C: Spring Framework — əsas Dependency Injection və web kitabxanasıdır, konfiqurasiyası mürəkkəbdir. Spring Boot Spring üzərində qurulub və "convention over configuration" prinsipi ilə avtomatik konfiqurasiya, embedded server, starter dependency-ləri təqdim edir. Spring Boot Spring-i əvəz etmir — onun "wrapper"ıdır.

**S: `@SpringBootApplication` annotasiyası nədir?**
C: Üç annotasiyanın birləşməsidir: `@SpringBootConfiguration` (konfiqurasiya sinfi), `@EnableAutoConfiguration` (classpath-ə görə avtomatik bean yaratma) və `@ComponentScan` (`@Component`, `@Service`, `@Controller` sinifləri tap). Adətən əsas sinfin üzərində istifadə edilir.

**S: Embedded server nədir və nə üçün yaxşıdır?**
C: Embedded server (Tomcat/Jetty/Undertow) tətbiqin JAR-ının içindədir. Üstünlükləri: ayrıca server quraşdırmaq lazım deyil; Docker konteynerləri üçün idealdır; hər tətbiq öz versiyasını gətirir; `java -jar` əmri ilə başlayır. Microservice arxitekturası üçün standartdır.

**S: Spring Initializr nədir?**
C: `start.spring.io` ünvanında rəsmi Spring layihə generatorudur. Layihə metadata-sını və istədiyin starter-ləri seçirsən, o da hazır ZIP fayl yaradır — `pom.xml`/`build.gradle`, əsas sinif və düzgün struktur ilə.

**S: Spring Boot starter nədir?**
C: Starter müəyyən bir xüsusiyyət üçün qabaqcadan konfiqurasiya edilmiş bağlılıqlar toplusudur. Məsələn, `spring-boot-starter-web` Spring MVC, Tomcat, Jackson və digər web üçün lazım olan kitabxanaları bir araya gətirir. Beləliklə, versiyaları əl ilə uyğunlaşdırmağa ehtiyac qalmır.

**S: Niyə `server.port=8080`-i dəyişməli ola bilərsən?**
C: Əgər 8080 başqa tətbiq tərəfindən istifadə olunursa; mikroservislər bir maşında işləyirsə (hər biri fərqli port); production-da xüsusi port qaydası varsa. Dəyişmək üçün `application.properties`-də `server.port=9090` və ya command line-da `--server.port=9090`.

**S: Fat JAR nədir?**
C: Fat JAR (uber JAR) — tətbiq kodunu və bütün asılılıqları bir JAR faylına paketləyən yanaşmadır. Spring Boot Maven/Gradle plugin bunu avtomatik edir. Nəticədə `java -jar app.jar` əmri ilə classpath qurmaq lazım olmadan tətbiqi işə sala bilərik.

**S: DevTools necə işləyir?**
C: DevTools iki classloader istifadə edir: `base` (dəyişməyən kitabxanalar) və `restart` (tətbiq kodu). Kod dəyişdikdə yalnız `restart` classloader yenidən yüklənir — tam restart-dan 3-5 dəfə sürətli. Həm də LiveReload serveri qoşur (port 35729), brauzer uzantısı ilə səhifəni avtomatik yenilə bilər.

**S: `mvn spring-boot:run` ilə `java -jar` arasında fərq nədir?**
C: `mvn spring-boot:run` inkişaf mühiti üçündür — Maven layihəni kompilyasiya edib işə salır, hər dəyişiklikdə yenidən kompilyasiya lazımdır. `java -jar` — paketlənmiş (executable) JAR-ı birbaşa icra edir, production üçündür; daha sürətli başlayır, Maven asılılığı yoxdur.

**S: Niyə `@ComponentScan` default olaraq yalnız əsas paketi skan edir?**
C: `@SpringBootApplication` daxilindəki `@ComponentScan` parametrsiz çağırıldıqda, annotasiyanın yerləşdiyi paketi "root" kimi götürür və bu paketin özünü + bütün alt paketlərini skan edir. Bu, tətbiqin strukturunu standartlaşdırır — əsas sinif `com.example.demo`-da olarsa, bütün `@Component`-lər də həmin paketin altında yerləşməlidir.
