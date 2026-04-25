# 60 — Gradle Əsasları

> **Seviyye:** Intermediate ⭐⭐


## Mündəricat
1. [Gradle nədir?](#nedir)
2. [Gradle vs Maven — qısa müqayisə](#vs-maven)
3. [Gradle-ın quraşdırılması](#qurasdirma)
4. [Groovy DSL vs Kotlin DSL](#dsl)
5. [build.gradle anatomiyası](#build-gradle)
6. [Dependency configurations](#config)
7. [Task-lar və lifecycle](#task)
8. [Gradle Wrapper](#wrapper)
9. [settings.gradle və plugins bloku](#settings)
10. [Version Catalogs (libs.versions.toml)](#catalog)
11. [Dependency tree — inspection](#tree)
12. [Multi-project build-lər](#multi)
13. [Tam nümunə — Spring Boot build.gradle.kts](#numune)
14. [Tez-tez istifadə olunan əmrlər](#emrler)
15. [Ümumi Səhvlər](#umumi)
16. [İntervyu Sualları](#intervyu)

---

## 1. Gradle nədir? {#nedir}

Gradle — Java, Kotlin, Android və digər JVM dilləri üçün **modern build tool**. 2007-ci ildən mövcuddur, Google tərəfindən Android-in rəsmi build sistemi kimi seçilib.

### Gradle niyə meydana gəldi?

- Maven — XML sintaksisi verbose idi
- Ant — low-level idi (hər şeyi özün yazırdın)
- Gradle — "imperative + declarative" birləşməsi

### Gradle-ın əsas üstünlükləri

| Xüsusiyyət | Təsir |
|---|---|
| **Incremental build** | Yalnız dəyişən hissəni yenidən kompilyasiya edir |
| **Build cache** | Əvvəlki build nəticələrini cache-də saxlayır |
| **Daemon process** | Arxa fonda işləyən Gradle prosesi — daha sürətli start |
| **Parallel execution** | Multi-module layihələrdə task-lar paralel işləyir |
| **Kotlin DSL** | Type-safe, IDE autocompletion |

### Analogiya

Maven — **reseptdir** (XML ilə qəti göstərişlər). Gradle — **mətbəxin idarə panelidir** (çevik, özü optimallaşdırır, öz script-ini yaza bilərsən).

---

## 2. Gradle vs Maven — qısa müqayisə {#vs-maven}

| Xüsusiyyət | Maven | Gradle |
|---|---|---|
| Config faylı | `pom.xml` | `build.gradle` / `build.gradle.kts` |
| Dil | XML | Groovy / Kotlin DSL |
| Sintaksis | Verbose | Yığcam |
| Performans | Orta | Sürətli (incremental, cache) |
| Öyrənmə əyrisi | Asan | Dik |
| Plugin sistemi | Mature | Daha güclü |
| Multi-project | Yaxşı | Əla |
| Android | Yox | Bəli (rəsmi) |
| Kotlin dəstəyi | Yox | Bəli |

### Sintaksis müqayisə

**Maven (pom.xml):**

```xml
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-web</artifactId>
    <version>3.2.0</version>
</dependency>
```

**Gradle (build.gradle):**

```groovy
implementation 'org.springframework.boot:spring-boot-starter-web:3.2.0'
```

**Gradle (build.gradle.kts):**

```kotlin
implementation("org.springframework.boot:spring-boot-starter-web:3.2.0")
```

---

## 3. Gradle-ın quraşdırılması {#qurasdirma}

### Linux/macOS

```bash
# SDKMAN! vasitəsilə (tövsiyə olunur)
curl -s "https://get.sdkman.io" | bash
source "$HOME/.sdkman/bin/sdkman-init.sh"
sdk install gradle 8.5

# Homebrew (macOS)
brew install gradle

# Yoxla
gradle -v
# Nümunə:
# Gradle 8.5
# Kotlin:       1.9.20
# Groovy:       3.0.17
# JVM:          21
```

### Windows

```powershell
# Chocolatey
choco install gradle

# Scoop
scoop install gradle
```

### JAVA_HOME

Maven kimi, Gradle da `JAVA_HOME`-a ehtiyac duyur:

```bash
export JAVA_HOME=/usr/lib/jvm/java-21-openjdk
export PATH=$JAVA_HOME/bin:$PATH
```

### Wrapper ilə (sistem install olmasın)

```bash
# Layihədə wrapper yarat (bir dəfə)
gradle wrapper --gradle-version 8.5

# İndi artıq sistemdə Gradle olmaya bilər — wrapper istifadə et
./gradlew build       # Linux/macOS
gradlew.bat build     # Windows
```

---

## 4. Groovy DSL vs Kotlin DSL {#dsl}

Gradle iki DSL dəstəkləyir:

### Groovy DSL (`build.gradle`) — klassik

```groovy
plugins {
    id 'java'
    id 'org.springframework.boot' version '3.2.0'
}

group = 'com.example'
version = '1.0.0'

repositories {
    mavenCentral()
}

dependencies {
    implementation 'org.springframework.boot:spring-boot-starter-web'
    testImplementation 'org.springframework.boot:spring-boot-starter-test'
}
```

### Kotlin DSL (`build.gradle.kts`) — modern

```kotlin
plugins {
    java
    id("org.springframework.boot") version "3.2.0"
}

group = "com.example"
version = "1.0.0"

repositories {
    mavenCentral()
}

dependencies {
    implementation("org.springframework.boot:spring-boot-starter-web")
    testImplementation("org.springframework.boot:spring-boot-starter-test")
}
```

### Hansını seçmək?

| Kriter | Groovy | Kotlin |
|---|---|---|
| Yığcamlıq | Yüksək | Orta |
| IDE support | Yaxşı | Əla (type-safe) |
| Error mesajları | Zəif | Yaxşı |
| Yeni layihə | | ✓ tövsiyə olunur |
| Android | | ✓ Google tövsiyə edir |
| Köhnə layihə | ✓ qaldırmaq olar | |

**Tövsiyə:** Yeni layihə üçün **Kotlin DSL**.

---

## 5. build.gradle anatomiyası {#build-gradle}

### Əsas bloklar

```kotlin
// 1. Pluginlər — Gradle-a əlavə funksionallıq verir
plugins {
    java
    id("org.springframework.boot") version "3.2.0"
    id("io.spring.dependency-management") version "1.1.4"
}

// 2. Metadata
group = "com.example"
version = "0.0.1-SNAPSHOT"
description = "Demo Spring Boot Project"

// 3. Java version
java {
    toolchain {
        languageVersion = JavaLanguageVersion.of(21)
    }
}

// 4. Repository-lər
repositories {
    mavenCentral()
    // maven("https://repo.spring.io/snapshot")
}

// 5. Dependencies
dependencies {
    implementation("org.springframework.boot:spring-boot-starter-web")
    runtimeOnly("org.postgresql:postgresql")
    testImplementation("org.springframework.boot:spring-boot-starter-test")
}

// 6. Tasklar üçün konfiqurasiya
tasks.withType<Test> {
    useJUnitPlatform()
}

tasks.withType<JavaCompile> {
    options.compilerArgs.add("-parameters")
    options.encoding = "UTF-8"
}
```

### plugins bloku

```kotlin
plugins {
    // Built-in
    java
    application

    // ID ilə external
    id("org.springframework.boot") version "3.2.0"
    id("io.spring.dependency-management") version "1.1.4"

    // Kotlin plugin (Kotlin layihələr üçün)
    kotlin("jvm") version "1.9.20"
}
```

### Repositories

```kotlin
repositories {
    mavenCentral()                    // əsas repo
    mavenLocal()                      // ~/.m2 (Maven ilə shared)
    gradlePluginPortal()              // Gradle pluginləri
    google()                          // Android üçün
    maven("https://jitpack.io")       // custom repo
    maven {
        url = uri("https://nexus.mycompany.com/repository/maven-public/")
        credentials {
            username = project.findProperty("nexusUser") as String
            password = project.findProperty("nexusPass") as String
        }
    }
}
```

---

## 6. Dependency configurations {#config}

Gradle-ın dependency scope-ları — Maven-dən **daha zəngin**.

### Əsas configuration-lar

| Configuration | Maven ekvivalenti | Nə edir? |
|---|---|---|
| `implementation` | `compile` | Compile + runtime, API-yə exposed deyil |
| `api` | `compile` | Compile + runtime, consumer-lara açıqdır |
| `compileOnly` | `provided` | Yalnız compile — runtime-da yox |
| `runtimeOnly` | `runtime` | Yalnız runtime — compile-da yox |
| `testImplementation` | `test` | Yalnız test |
| `testCompileOnly` | — | Yalnız test compile |
| `testRuntimeOnly` | — | Yalnız test runtime |
| `annotationProcessor` | — | Compile-da annotation processors |

### Nümunələr

```kotlin
dependencies {
    // Əsas — istifadə olunur amma API-də görünmür
    implementation("org.springframework.boot:spring-boot-starter-web")

    // Library yazırsansa və consumer-ın onu görməsini istəyirsənsə
    api("com.google.guava:guava:33.0.0-jre")

    // Lombok — yalnız compile vaxtı
    compileOnly("org.projectlombok:lombok:1.18.30")
    annotationProcessor("org.projectlombok:lombok:1.18.30")

    // DB driver — yalnız runtime
    runtimeOnly("org.postgresql:postgresql:42.7.0")

    // Test
    testImplementation("org.springframework.boot:spring-boot-starter-test")
    testImplementation("org.testcontainers:postgresql:1.19.3")
}
```

### `implementation` vs `api` — vacib fərq

```
Library A:
  - implementation("guava")  → A-nın consumer-ları guava-nı görmür
  - api("guava")             → A-nın consumer-ları guava-nı kompilyasiya time-da görür
```

**Qayda:** Tətbiqlərdə `implementation` istifadə et (daha sürətli build). Yalnız ümumi kitabxana yaradıb onu başqasına verirsənsə, `api` düşünə bilərsən.

---

## 7. Task-lar və lifecycle {#task}

Gradle hər əməliyyatı **task** kimi görür. Task-lar arasında dependency qrafiki qurulur.

### Standart task-lar (java plugin-dən)

```bash
./gradlew tasks

# Əsas tasklar:
# build       - Layihəni build et (compile + test + jar)
# clean       - build/ qovluğunu sil
# test        - Testləri run et
# jar         - JAR yarat
# compileJava - Kompilyasiya et
# classes     - compileJava + processResources
```

### Spring Boot task-ları (plugin ilə)

```bash
./gradlew bootRun          # Spring Boot app run
./gradlew bootJar          # Executable fat JAR
./gradlew bootBuildImage   # OCI (Docker) image yarat
```

### Öz task-ını yazmaq

```kotlin
tasks.register("salam") {
    doLast {
        println("Salam, Gradle!")
    }
}
```

```bash
./gradlew salam
# > Salam, Gradle!
```

### Task dependency

```kotlin
tasks.register("deploy") {
    dependsOn("build")
    doLast {
        println("Deploy edilir...")
    }
}
```

`./gradlew deploy` — əvvəl `build` işlər, sonra deploy.

---

## 8. Gradle Wrapper {#wrapper}

**Gradle Wrapper** — layihənin özü öz Gradle versiyasını idarə edir. Sistemdə Gradle quraşdırılmış olmasın.

### Yaratmaq

```bash
gradle wrapper --gradle-version 8.5
```

Yaradılan fayllar:

```
my-project/
├── gradlew                    # Unix script
├── gradlew.bat                # Windows script
└── gradle/
    └── wrapper/
        ├── gradle-wrapper.jar
        └── gradle-wrapper.properties
```

### gradle-wrapper.properties

```properties
distributionBase=GRADLE_USER_HOME
distributionPath=wrapper/dists
distributionUrl=https\://services.gradle.org/distributions/gradle-8.5-bin.zip
networkTimeout=10000
zipStoreBase=GRADLE_USER_HOME
zipStorePath=wrapper/dists
validateDistributionUrl=true
```

### İstifadə

```bash
./gradlew build        # Linux/macOS
gradlew.bat build      # Windows

# İlk çalışdırmada Gradle avtomatik yüklənir: ~/.gradle/wrapper/dists/
```

### Versiyanı yeniləmək

```bash
./gradlew wrapper --gradle-version 8.5.1
```

### Niyə wrapper vacibdir?

- Hər developer eyni Gradle versiyasını istifadə edir
- CI/CD-də Gradle install tələb olunmur
- Yeni team member 5 dəqiqədə başlaya bilir

**Qayda:** Hər Gradle layihəsində `./gradlew` istifadə et, `gradle` yox.

---

## 9. settings.gradle və plugins bloku {#settings}

### settings.gradle.kts

Layihənin üst konfiqurasiyasıdır — build başlamazdan əvvəl oxunur.

```kotlin
rootProject.name = "my-project"

// Multi-module üçün
include("core", "api", "cli")

// Plugin resolution
pluginManagement {
    repositories {
        gradlePluginPortal()
        mavenCentral()
    }
}

// Version catalogs
dependencyResolutionManagement {
    versionCatalogs {
        create("libs") {
            from(files("gradle/libs.versions.toml"))
        }
    }
}
```

### `plugins` block ordering

```kotlin
// 1. Əvvəl plugins (settings.gradle.kts-də pluginManagement)
plugins {
    id("org.springframework.boot") version "3.2.0"
    id("io.spring.dependency-management") version "1.1.4"
    java
}

// 2. Sonra group, version
group = "com.example"
version = "0.0.1"

// 3. Sonra java/sourceSets
java {
    sourceCompatibility = JavaVersion.VERSION_21
}

// 4. Repositories
repositories {
    mavenCentral()
}

// 5. Dependencies
dependencies {
    implementation("org.springframework.boot:spring-boot-starter-web")
}
```

---

## 10. Version Catalogs (libs.versions.toml) {#catalog}

Gradle 7.4+ — **mərkəzi versiya idarəetməsi**. Bütün dependency versiyalarını bir yerdə saxlamaq.

### `gradle/libs.versions.toml`

```toml
[versions]
spring-boot = "3.2.0"
jackson = "2.15.3"
junit = "5.10.0"
testcontainers = "1.19.3"

[libraries]
spring-boot-starter-web = { module = "org.springframework.boot:spring-boot-starter-web", version.ref = "spring-boot" }
spring-boot-starter-data-jpa = { module = "org.springframework.boot:spring-boot-starter-data-jpa", version.ref = "spring-boot" }
jackson-databind = { module = "com.fasterxml.jackson.core:jackson-databind", version.ref = "jackson" }
junit-jupiter = { module = "org.junit.jupiter:junit-jupiter", version.ref = "junit" }
testcontainers-postgresql = { module = "org.testcontainers:postgresql", version.ref = "testcontainers" }

[bundles]
testing = ["junit-jupiter", "testcontainers-postgresql"]

[plugins]
spring-boot = { id = "org.springframework.boot", version.ref = "spring-boot" }
```

### build.gradle.kts-də istifadə

```kotlin
plugins {
    alias(libs.plugins.spring.boot)
    java
}

dependencies {
    implementation(libs.spring.boot.starter.web)
    implementation(libs.spring.boot.starter.data.jpa)
    implementation(libs.jackson.databind)

    testImplementation(libs.bundles.testing)
}
```

### Niyə catalog?

- **Type-safe** — IDE autocompletion
- **DRY** — versiya bir yerdə
- **Dependency update tool-ları** asan işləyir

---

## 11. Dependency tree — inspection {#tree}

```bash
# Bütün konfiqurasiyalar
./gradlew dependencies

# Yalnız runtime
./gradlew dependencies --configuration runtimeClasspath

# Yalnız test
./gradlew dependencies --configuration testRuntimeClasspath

# Müəyyən dependency-ni axtar
./gradlew dependencyInsight --dependency jackson-databind

# Nümunə çıxış:
# com.fasterxml.jackson.core:jackson-databind:2.15.3
# +--- com.fasterxml.jackson.core:jackson-annotations:2.15.3
# \--- com.fasterxml.jackson.core:jackson-core:2.15.3
```

### Dependency-ni exclude et

```kotlin
dependencies {
    implementation("org.springframework.boot:spring-boot-starter-web") {
        exclude(group = "org.springframework.boot", module = "spring-boot-starter-tomcat")
    }
    implementation("org.springframework.boot:spring-boot-starter-jetty")
}
```

### Versiyanı məcbur et

```kotlin
configurations.all {
    resolutionStrategy {
        force("com.fasterxml.jackson.core:jackson-databind:2.16.0")
    }
}
```

---

## 12. Multi-project build-lər {#multi}

### Struktur

```
my-app/
├── settings.gradle.kts
├── build.gradle.kts              # root build — ümumi konfiqurasiya
├── gradle/
│   └── libs.versions.toml
├── gradlew
├── core/
│   └── build.gradle.kts
├── api/
│   └── build.gradle.kts
└── cli/
    └── build.gradle.kts
```

### settings.gradle.kts

```kotlin
rootProject.name = "my-app"
include("core", "api", "cli")
```

### Root build.gradle.kts

```kotlin
// Bütün subproject-lərə ümumi tətbiq et
subprojects {
    apply(plugin = "java")

    group = "com.example"
    version = "1.0.0"

    java {
        toolchain.languageVersion = JavaLanguageVersion.of(21)
    }

    repositories {
        mavenCentral()
    }

    dependencies {
        // Bütün subproject-lər üçün ümumi
        "testImplementation"("org.junit.jupiter:junit-jupiter:5.10.0")
    }

    tasks.withType<Test> {
        useJUnitPlatform()
    }
}
```

### api/build.gradle.kts

```kotlin
dependencies {
    implementation(project(":core"))     // başqa modul-dan asılılıq
    implementation("org.springframework.boot:spring-boot-starter-web")
}
```

### Build

```bash
# Bütün modullar
./gradlew build

# Yalnız bir modul
./gradlew :api:build

# Paralel build
./gradlew build --parallel
```

---

## 13. Tam nümunə — Spring Boot build.gradle.kts {#numune}

```kotlin
plugins {
    java
    id("org.springframework.boot") version "3.2.0"
    id("io.spring.dependency-management") version "1.1.4"
}

group = "az.example"
version = "0.0.1-SNAPSHOT"
description = "Todo API"

java {
    toolchain {
        languageVersion = JavaLanguageVersion.of(21)
    }
}

configurations {
    compileOnly {
        extendsFrom(configurations.annotationProcessor.get())
    }
}

repositories {
    mavenCentral()
}

dependencies {
    // Spring Boot starters
    implementation("org.springframework.boot:spring-boot-starter-web")
    implementation("org.springframework.boot:spring-boot-starter-data-jpa")
    implementation("org.springframework.boot:spring-boot-starter-validation")
    implementation("org.springframework.boot:spring-boot-starter-actuator")
    implementation("org.springframework.boot:spring-boot-starter-security")

    // Database driverlər
    runtimeOnly("org.postgresql:postgresql")
    runtimeOnly("com.h2database:h2")

    // Lombok
    compileOnly("org.projectlombok:lombok")
    annotationProcessor("org.projectlombok:lombok")

    // Developer Tools (hot reload)
    developmentOnly("org.springframework.boot:spring-boot-devtools")

    // Monitoring
    implementation("io.micrometer:micrometer-registry-prometheus")

    // Test
    testImplementation("org.springframework.boot:spring-boot-starter-test")
    testImplementation("org.springframework.security:spring-security-test")
    testImplementation("org.testcontainers:junit-jupiter:1.19.3")
    testImplementation("org.testcontainers:postgresql:1.19.3")
}

tasks.withType<JavaCompile> {
    options.encoding = "UTF-8"
    options.compilerArgs.add("-parameters")
}

tasks.withType<Test> {
    useJUnitPlatform()
    testLogging {
        events("passed", "skipped", "failed")
    }
}

tasks.bootJar {
    archiveFileName.set("app.jar")
}

// Öz task
tasks.register("printDeps") {
    doLast {
        configurations["runtimeClasspath"].forEach {
            println(it.name)
        }
    }
}
```

---

## 14. Tez-tez istifadə olunan əmrlər {#emrler}

```bash
# Help
./gradlew help
./gradlew tasks             # bütün task-ları göstər
./gradlew tasks --all

# Clean
./gradlew clean

# Build
./gradlew build             # compile + test + jar
./gradlew assemble          # yalnız jar, test yox
./gradlew check             # yalnız test, jar yox

# Test
./gradlew test
./gradlew test --tests "com.example.UserServiceTest"
./gradlew test --tests "*.shouldCreateUser"

# Spring Boot
./gradlew bootRun
./gradlew bootJar
./gradlew bootBuildImage

# Dependency
./gradlew dependencies
./gradlew dependencyInsight --dependency jackson-databind
./gradlew dependencyUpdates      # köhnə dependency-ləri tap

# Daemon
./gradlew --stop             # bütün daemon-ları dayandır
./gradlew --status           # daemon statusu

# Paralelleşdirmə
./gradlew build --parallel
./gradlew build --max-workers=4

# Debug
./gradlew build --info
./gradlew build --debug
./gradlew build --stacktrace

# Offline (internet olmasa)
./gradlew build --offline

# Build cache
./gradlew build --build-cache

# Refresh dependencies
./gradlew build --refresh-dependencies

# Continue (bir task fail etsə belə davam et)
./gradlew build --continue
```

---

## 15. Ümumi Səhvlər {#umumi}

### Səhv 1 — `gradle` vs `./gradlew`

```bash
# YANLIŞ — sistemdə fərqli versiya ola bilər
gradle build

# DOĞRU — wrapper ilə
./gradlew build
```

### Səhv 2 — `implementation` əvəzinə `compile`

```groovy
// KÖHNƏ (deprecated)
compile 'org.springframework:spring-core:6.0.0'

// MODERN
implementation 'org.springframework:spring-core:6.0.0'
```

### Səhv 3 — Kotlin DSL-də tırnaq unudulması

```kotlin
// YANLIŞ (Groovy sintaksisidir)
implementation 'org.springframework.boot:spring-boot-starter-web'

// DOĞRU Kotlin DSL
implementation("org.springframework.boot:spring-boot-starter-web")
```

### Səhv 4 — Daemon memory low

```bash
# gradle.properties
org.gradle.jvmargs=-Xmx4g -XX:MaxMetaspaceSize=1g

# Paralel
org.gradle.parallel=true
org.gradle.caching=true
```

### Səhv 5 — Maven-dən Gradle-a geçəndə repository yaddan çıxır

```kotlin
// Bu olmasa heç bir dependency yüklənmir
repositories {
    mavenCentral()
}
```

### Səhv 6 — `api` ilə `implementation` qarışdırmaq

```kotlin
// Tətbiqlər üçün həmişə implementation
implementation("com.google.guava:guava:33.0.0-jre")

// api yalnız kitabxana yazarkən consumer-a verilməsi lazım gələn tipləri üçün
```

### Səhv 7 — Build vaxtı yavaşdır

```properties
# gradle.properties-də aktivləşdir
org.gradle.parallel=true
org.gradle.caching=true
org.gradle.daemon=true
org.gradle.configureondemand=true
```

---

## İntervyu Sualları {#intervyu}

**S1: Gradle və Maven arasındakı əsas fərqlər nədir?**
> Maven — XML-lə (pom.xml), deklarativ, convention over configuration. Gradle — Groovy/Kotlin DSL, daha çevik, sürətli (incremental build + cache + daemon). Gradle Maven-dən ortalama 2-3 dəfə sürətlidir böyük layihələrdə. Android rəsmi olaraq Gradle istifadə edir. Kiçik layihədə Maven sadədir, böyük layihədə Gradle ən yaxşı seçimdir.

**S2: `implementation`, `api`, `compileOnly`, `runtimeOnly` configuration-ları nə vaxt istifadə olunur?**
> `implementation` — əsas (Maven `compile` ekvivalenti), amma consumer layihələrə API-də görünmür. `api` — həm istifadə olunur, həm də consumer-lara exposed (kitabxana yazarkən). `compileOnly` — yalnız compile vaxtı (Lombok, Servlet API). `runtimeOnly` — yalnız runtime (JDBC driver).

**S3: `implementation` ilə `api` fərqi niyə vacibdir?**
> Build sürəti. `api` ilə əlavə edilən dependency-lər **consumer-ın classpath-ında** görünür — consumer-ın dependency qrafikini böyüdür. `implementation` isə dependency-ni "daxili" saxlayır — consumer-ın build-inə təsir etmir. Nəticədə `implementation` istifadə edən layihələr daha sürətli build olur.

**S4: Gradle Wrapper nədir və niyə vacibdir?**
> Layihəyə əlavə edilən script (`gradlew`) və config (`gradle-wrapper.properties`) — layihənin **öz Gradle versiyasını** idarə edir. Sistemdə Gradle olmasın — ilk `./gradlew` çağırışında avtomatik yüklənir. Bu CI/CD və komanda üçün konsistensiya təmin edir — hər kəs eyni Gradle versiyası işlədir.

**S5: Groovy DSL və Kotlin DSL hansını seçmək?**
> Yeni layihə üçün **Kotlin DSL** — type-safe, IDE autocompletion, daha yaxşı error mesajları. Köhnə layihələrdə Groovy qalır. Google Android üçün Kotlin DSL-i tövsiyə edir. Groovy daha yığcam ola bilər, amma səhvləri runtime-da tapılır.

**S6: Version Catalog (libs.versions.toml) nə verir?**
> Bütün versiyalar bir yerdə saxlanır. Multi-module layihədə hər modul eyni versiyanı referens edir. Dependency update tool-ları asan işləyir. Type-safe access (`libs.spring.boot.starter.web`) — IDE tələffüzü yoxlayır.

**S7: Gradle Daemon nədir?**
> Arxa fonda işləyən Gradle prosesi. Hər `./gradlew` çağırışında JVM yenidən başlamır — daemon artıq isinmiş JVM-də task-ı run edir. Bu start vaxtını kəskin azaldır (saniyələrdən millisaniyələrə). Build cache və incremental build ilə birgə işləyəndə kəskin performans artır.

**S8: `settings.gradle.kts` nə üçün lazımdır?**
> Layihənin root konfiqurasiyasıdır — build başlamazdan əvvəl oxunur. Multi-project build üçün `include()` ilə modulları göstərir, plugin repository-lərini təyin edir, version catalog-ları konfiqurasiya edir. `build.gradle.kts`-dən əvvəl işlənir.

**S9: Multi-project build-də modullar bir-birini necə çağırır?**
> `dependencies { implementation(project(":core")) }` — `:core` modulunu dependency kimi əlavə edir. Root `build.gradle.kts`-də `subprojects { ... }` bloku bütün child-lərə ümumi konfiqurasiya verir.

**S10: Gradle build-i necə sürətləndirmək olar?**
> (1) `org.gradle.parallel=true` — multi-module paralel. (2) `org.gradle.caching=true` — build cache. (3) `org.gradle.daemon=true` — daemon. (4) `implementation` istifadə et (`api` yox). (5) `gradle.properties`-də JVM-ə memory ver. (6) Testləri seçici run et (`--tests "*.shouldCreateUser"`). (7) `buildSrc` əvəzinə composite build istifadə et.

**S11: `bootJar` ilə standart `jar` fərqi nədir?**
> Spring Boot plugin-in yaratdığı `bootJar` — **executable fat JAR**. Bütün dependency-ləri içində saxlayır (`BOOT-INF/lib/`). `java -jar app.jar` ilə birbaşa işə düşür. Standart `jar` — yalnız kompilyasiya edilmiş siniflər (dependency-lər daxil deyil).
