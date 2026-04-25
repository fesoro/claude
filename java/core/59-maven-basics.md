# 59 — Maven Əsasları

> **Seviyye:** Intermediate ⭐⭐


## Mündəricat
1. [Maven nədir?](#nedir)
2. [Maven-in quraşdırılması](#qurasdirma)
3. [Maven layihə strukturu](#struktur)
4. [pom.xml anatomiyası](#pom)
5. [GAV — Maven coordinates](#gav)
6. [Dependency və scope-lar](#dependency)
7. [Local repo və Maven Central](#repo)
8. [Maven lifecycle və phase-lər](#lifecycle)
9. [Pluginlər](#plugin)
10. [Transitive dependencies](#transitive)
11. [dependencyManagement və BOM](#bom)
12. [Properties](#properties)
13. [Profiles](#profiles)
14. [Tam nümunə — Spring Boot pom.xml](#numune)
15. [Tez-tez istifadə olunan əmrlər](#emrler)
16. [Ümumi Səhvlər](#umumi)
17. [İntervyu Sualları](#intervyu)

---

## 1. Maven nədir? {#nedir}

Maven — Apache tərəfindən hazırlanmış **build** və **dependency management** alətidir. 2004-cü ildən mövcuddur.

### Maven iki əsas problemi həll edir:

**1. Build automation** — mənbə kodu (.java) → bytecode (.class) → .jar faylına çevirmək.

**2. Dependency management** — layihənizin ehtiyac duyduğu kitabxanaları (Spring, Jackson, JUnit və s.) avtomatik yükləmək.

### Maven-siz dünya

```bash
# Əl ilə yüklə
wget https://central.maven.org/spring-core-6.0.0.jar
wget https://central.maven.org/jackson-databind-2.15.jar
# Və daha 20 kitabxana...

# Hər birini classpath-ə əlavə et
javac -cp "spring-core-6.0.0.jar:jackson-databind-2.15.jar:..." Main.java

# Run
java -cp "spring-core-6.0.0.jar:jackson-databind-2.15.jar:...:." Main
```

### Maven ilə

```xml
<dependency>
    <groupId>org.springframework</groupId>
    <artifactId>spring-core</artifactId>
    <version>6.0.0</version>
</dependency>
```

`mvn compile` — hər şey avtomatik.

### Analogiya

Maven — **restoranın mətbəxi** kimidir. Siz sadəcə resept verirsiniz (`pom.xml`), mətbəx isə inqrediyentləri (dependency-ləri) tapır, bişirir, sifarişi hazırlayır. Hər dəfə əl ilə bazara getmirsiz.

---

## 2. Maven-in quraşdırılması {#qurasdirma}

### Linux/macOS

```bash
# Sistemin package manager-i ilə
# Ubuntu/Debian
sudo apt install maven

# macOS (Homebrew)
brew install maven

# Versiyanı yoxla
mvn -version
# Nümunə çıxış:
# Apache Maven 3.9.6
# Java version: 21
```

### Manual install

```bash
# 1. Yüklə
wget https://dlcdn.apache.org/maven/maven-3/3.9.6/binaries/apache-maven-3.9.6-bin.tar.gz

# 2. Aç
tar xzvf apache-maven-3.9.6-bin.tar.gz -C /opt/

# 3. PATH-ə əlavə et (~/.bashrc və ya ~/.zshrc)
export MAVEN_HOME=/opt/apache-maven-3.9.6
export PATH=$MAVEN_HOME/bin:$PATH

# 4. Yeni shell aç, yoxla
mvn -version
```

### JAVA_HOME

Maven `JAVA_HOME` mühit dəyişəninə ehtiyac duyur:

```bash
export JAVA_HOME=/usr/lib/jvm/java-21-openjdk
export PATH=$JAVA_HOME/bin:$PATH
```

### Maven wrapper (alternativ)

Əgər sistemdə Maven quraşdırılmayıbsa — `mvnw` istifadə edin:

```bash
# Maven wrapper əlavə et (bir dəfə)
mvn wrapper:wrapper

# Artıq sistemdə Maven olmasın — lokal wrapper istifadə et
./mvnw compile   # Linux/macOS
mvnw.cmd compile # Windows
```

---

## 3. Maven layihə strukturu {#struktur}

Maven **convention over configuration** prinsipinə əsaslanır — layihə strukturu standart qaydadadır.

```
my-project/
├── pom.xml                              # Maven konfiqurasiya
├── src/
│   ├── main/
│   │   ├── java/                        # Production kod
│   │   │   └── com/example/
│   │   │       └── App.java
│   │   ├── resources/                   # Production resurslar
│   │   │   ├── application.properties
│   │   │   └── logback.xml
│   │   └── webapp/                      # Web proyektlər üçün
│   │       └── WEB-INF/
│   └── test/
│       ├── java/                        # Test kod
│       │   └── com/example/
│       │       └── AppTest.java
│       └── resources/                   # Test resursları
│           └── application-test.yml
├── target/                              # Build çıxışı (gitignore olunmalı)
│   ├── classes/
│   ├── test-classes/
│   └── my-project-1.0.0.jar
└── .mvn/
    └── wrapper/
```

### Vacib qaydalar

- `src/main/java` — istehsalat kodu
- `src/test/java` — yalnız testlər (production-a əlavə olunmur)
- `src/main/resources` — config fayllar (JAR-a daxil edilir)
- `target/` — bütün build artifactləri (həmişə `.gitignore`-a əlavə et)

---

## 4. pom.xml anatomiyası {#pom}

`pom.xml` — Maven layihəsinin **mərkəzi konfiqurasiyasıdır**. POM = Project Object Model.

### Minimal pom.xml

```xml
<?xml version="1.0" encoding="UTF-8"?>
<project xmlns="http://maven.apache.org/POM/4.0.0">
    <modelVersion>4.0.0</modelVersion>

    <!-- Maven coordinates -->
    <groupId>com.example</groupId>
    <artifactId>my-app</artifactId>
    <version>1.0.0</version>
    <packaging>jar</packaging>

    <name>My Application</name>
    <description>İlk Maven layihəm</description>

    <properties>
        <maven.compiler.source>21</maven.compiler.source>
        <maven.compiler.target>21</maven.compiler.target>
        <project.build.sourceEncoding>UTF-8</project.build.sourceEncoding>
    </properties>

    <dependencies>
        <dependency>
            <groupId>org.junit.jupiter</groupId>
            <artifactId>junit-jupiter</artifactId>
            <version>5.10.0</version>
            <scope>test</scope>
        </dependency>
    </dependencies>
</project>
```

### Əsas tag-lər

| Tag | Nə üçün? |
|---|---|
| `modelVersion` | POM faylının versiyası (həmişə `4.0.0`) |
| `groupId` | Təşkilatın unikal ID-si (reverse domain) |
| `artifactId` | Layihənin adı |
| `version` | Layihənin versiyası (semver) |
| `packaging` | Output tipi: `jar`, `war`, `pom`, `ear` |
| `properties` | Dəyişənlər |
| `dependencies` | Kitabxanalar |
| `build` | Plugin-lər və build konfiqurasiyası |

### Packaging növləri

```xml
<packaging>jar</packaging>   <!-- default — executable jar -->
<packaging>war</packaging>   <!-- Servlet/web app -->
<packaging>pom</packaging>   <!-- parent pom (multi-module) -->
<packaging>ear</packaging>   <!-- Enterprise archive (az istifadə) -->
```

---

## 5. GAV — Maven coordinates {#gav}

Hər Maven artefakt **3 şeylə identifikasiya olunur** — GAV:
- **G**roupId
- **A**rtifactId
- **V**ersion

```xml
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-web</artifactId>
    <version>3.2.0</version>
</dependency>
```

### GroupId qaydaları

```xml
<!-- Reverse domain — təşkilatın domain-i tərs -->
<groupId>com.google</groupId>
<groupId>org.springframework</groupId>
<groupId>az.mycompany.projectx</groupId>

<!-- YANLIŞ — bəsit ad -->
<groupId>myorg</groupId>
<groupId>mystuff</groupId>
```

### Version stratejiyaları

```xml
<!-- Semver: MAJOR.MINOR.PATCH -->
<version>1.0.0</version>    <!-- release -->
<version>1.2.3</version>

<!-- SNAPSHOT — development versiyası -->
<version>2.0.0-SNAPSHOT</version>
<!-- Hər yüklənmə zamanı Maven yenisini çəkər -->

<!-- Milestone və RC -->
<version>3.0.0-M1</version>
<version>3.0.0-RC1</version>
```

### Artefakt yerləşməsi local repo-da

```
~/.m2/repository/
└── org/
    └── springframework/
        └── boot/
            └── spring-boot-starter-web/
                └── 3.2.0/
                    ├── spring-boot-starter-web-3.2.0.jar
                    ├── spring-boot-starter-web-3.2.0.pom
                    └── _remote.repositories
```

GroupId `/` ilə əvəz edilib path kimi saxlanır.

---

## 6. Dependency və scope-lar {#dependency}

`<scope>` — dependency-nin hansı compile/runtime fazasında lazım olduğunu göstərir.

### 6 scope növü

| Scope | Nə vaxt əlçatan? | JAR-a daxil? | Nümunə |
|---|---|---|---|
| `compile` (default) | Hər yerdə | Bəli | Çox kitabxana |
| `provided` | Compile + test, runtime-da **yox** | Xeyr | `servlet-api` (Tomcat verir) |
| `runtime` | Runtime + test, compile-da **yox** | Bəli | JDBC driver |
| `test` | Yalnız test | Xeyr | JUnit, Mockito |
| `system` | Lokal fayldan | Xeyr (manual) | Custom JAR |
| `import` | Yalnız `dependencyManagement`-də | — | Spring Boot BOM |

### Nümunələr

```xml
<!-- compile — default, hər yerdə -->
<dependency>
    <groupId>com.fasterxml.jackson.core</groupId>
    <artifactId>jackson-databind</artifactId>
    <version>2.15.0</version>
    <!-- <scope>compile</scope> default -->
</dependency>

<!-- provided — deploy mühitində mövcuddur -->
<dependency>
    <groupId>jakarta.servlet</groupId>
    <artifactId>jakarta.servlet-api</artifactId>
    <version>6.0.0</version>
    <scope>provided</scope>
</dependency>

<!-- runtime — yalnız çalışma vaxtı -->
<dependency>
    <groupId>org.postgresql</groupId>
    <artifactId>postgresql</artifactId>
    <version>42.7.0</version>
    <scope>runtime</scope>
</dependency>

<!-- test — test üçün -->
<dependency>
    <groupId>org.junit.jupiter</groupId>
    <artifactId>junit-jupiter</artifactId>
    <version>5.10.0</version>
    <scope>test</scope>
</dependency>
```

### Nə vaxt provided istifadə etmək?

```
Servlet API → Tomcat-ın öz classpath-indədir
             → JAR-a ikinci dəfə daxil etmək olmaz
             → provided scope istifadə et
```

---

## 7. Local repo və Maven Central {#repo}

Maven dependency-ləri **local cache**-də saxlayır — hər layihə üçün yenidən yükləmir.

### Local repository yeri

```
# Default yer:
~/.m2/repository/            # Linux/macOS
C:\Users\username\.m2\repository\  # Windows
```

### Yeri dəyişdirmək

`~/.m2/settings.xml` faylında:

```xml
<settings>
    <localRepository>/path/to/custom/repo</localRepository>
</settings>
```

### Maven Central

Default olaraq Maven dependency-ləri **Maven Central**-dan yükləyir:
- URL: https://repo.maven.apache.org/maven2/
- UI: https://central.sonatype.com

### Əlavə repository-lər

```xml
<repositories>
    <repository>
        <id>spring-snapshots</id>
        <url>https://repo.spring.io/snapshot</url>
        <snapshots>
            <enabled>true</enabled>
        </snapshots>
    </repository>
    <repository>
        <id>jitpack</id>
        <url>https://jitpack.io</url>
    </repository>
</repositories>
```

### Şirkət daxili Nexus/Artifactory

```xml
<!-- ~/.m2/settings.xml -->
<mirrors>
    <mirror>
        <id>internal-nexus</id>
        <mirrorOf>*</mirrorOf>
        <url>https://nexus.mycompany.com/repository/maven-public/</url>
    </mirror>
</mirrors>
```

---

## 8. Maven lifecycle və phase-lər {#lifecycle}

Maven **3 standart lifecycle**-a sahibdir:

1. **default** — build və deploy
2. **clean** — təmizləmə
3. **site** — sənədlərin yaradılması

### Default lifecycle phase-ləri (ən vaciblər)

| Phase | Nə edir? |
|---|---|
| `validate` | pom.xml düzgündürmü? |
| `compile` | `src/main/java` → `target/classes/` |
| `test-compile` | `src/test/java` → `target/test-classes/` |
| `test` | Unit testləri run et |
| `package` | `.jar` və ya `.war` yarat |
| `verify` | Integration test-ləri run et |
| `install` | Artefakti `~/.m2`-ə qoy |
| `deploy` | Remote repo-ya yüklə |

### Phase-lər kumulyativdir

```bash
mvn install
# Avtomatik çağırar:
# validate → compile → test → package → verify → install
```

### Clean lifecycle

```bash
mvn clean
# target/ qovluğunu silir

mvn clean install
# Əvvəl təmizləyir, sonra build edir
```

### Tez-tez istifadə olunan kombinasiyalar

```bash
mvn clean compile         # təmizlə və kompilyasiya et
mvn clean test            # təmizlə, compile et, test run et
mvn clean package         # JAR yarat
mvn clean install         # lokala qur
mvn clean install -DskipTests  # test-siz install
```

---

## 9. Pluginlər {#plugin}

Maven-in hər işi **plugin**-lər tərəfindən görülür. Plugin-lərin `goal`-ları var.

### Tez-tez istifadə olunan pluginlər

| Plugin | Goal | Nə edir? |
|---|---|---|
| `maven-compiler-plugin` | `compile` | Java kodunu kompilyasiya edir |
| `maven-surefire-plugin` | `test` | Unit testləri icra edir |
| `maven-failsafe-plugin` | `integration-test` | Integration testlər |
| `maven-jar-plugin` | `jar` | JAR yaradır |
| `maven-shade-plugin` | `shade` | Fat JAR (bağımlılıqlarla) |
| `maven-assembly-plugin` | `assembly` | Custom paketləmə |
| `spring-boot-maven-plugin` | `repackage` | Spring Boot executable jar |

### Compiler plugin konfiqurasiyası

```xml
<build>
    <plugins>
        <plugin>
            <groupId>org.apache.maven.plugins</groupId>
            <artifactId>maven-compiler-plugin</artifactId>
            <version>3.12.1</version>
            <configuration>
                <source>21</source>
                <target>21</target>
                <encoding>UTF-8</encoding>
                <compilerArgs>
                    <arg>-parameters</arg>   <!-- Spring reflection üçün -->
                </compilerArgs>
            </configuration>
        </plugin>
    </plugins>
</build>
```

### Shade plugin — fat JAR

```xml
<plugin>
    <groupId>org.apache.maven.plugins</groupId>
    <artifactId>maven-shade-plugin</artifactId>
    <version>3.5.1</version>
    <executions>
        <execution>
            <phase>package</phase>
            <goals>
                <goal>shade</goal>
            </goals>
            <configuration>
                <transformers>
                    <transformer implementation="org.apache.maven.plugins.shade.resource.ManifestResourceTransformer">
                        <mainClass>com.example.Main</mainClass>
                    </transformer>
                </transformers>
            </configuration>
        </execution>
    </executions>
</plugin>
```

Nəticədə `target/my-app-1.0.0.jar` bütün dependency-lərlə — `java -jar` ilə birbaşa çalışır.

---

## 10. Transitive dependencies {#transitive}

Əgər layihəniz A-dan asılıdırsa, A isə B-dən asılıdırsa — **B avtomatik daxil edilir**. Bu **transitive dependency** adlanır.

```
my-app
  └── spring-boot-starter-web
        ├── spring-boot-starter (transitive)
        ├── spring-boot-starter-json
        │     ├── jackson-databind (transitive)
        │     └── jackson-core (transitive)
        ├── tomcat-embed-core (transitive)
        └── spring-webmvc (transitive)
```

### Ağacı göstər

```bash
mvn dependency:tree

# Nümunə çıxış:
# [INFO] com.example:my-app:jar:1.0.0
# [INFO] +- org.springframework.boot:spring-boot-starter-web:jar:3.2.0:compile
# [INFO] |  +- org.springframework.boot:spring-boot-starter:jar:3.2.0:compile
# [INFO] |  |  +- jakarta.annotation:jakarta.annotation-api:jar:2.1.1:compile
# [INFO] |  |  \- org.yaml:snakeyaml:jar:2.2:compile
```

### Exclusion — dependency-ni çıxart

```xml
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-web</artifactId>
    <version>3.2.0</version>
    <exclusions>
        <!-- Default Tomcat-ı çıxart -->
        <exclusion>
            <groupId>org.springframework.boot</groupId>
            <artifactId>spring-boot-starter-tomcat</artifactId>
        </exclusion>
    </exclusions>
</dependency>

<!-- Jetty əlavə et -->
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-jetty</artifactId>
</dependency>
```

### Version conflict — "Nearest wins" qaydası

Əgər iki dependency fərqli versiyada eyni kitabxananı çəkirsə, **ən yaxın yoldakı** qalib gəlir.

```
my-app
  ├── A → jackson 2.15
  └── B → C → jackson 2.10
```

Nəticə: `jackson 2.15` istifadə olunur (daha qısa yol).

---

## 11. dependencyManagement və BOM {#bom}

`<dependencyManagement>` — **dependency versiyalarını mərkəzləşdirmək** üçündür, amma özü dependency əlavə etmir.

### Sadə nümunə

```xml
<dependencyManagement>
    <dependencies>
        <dependency>
            <groupId>com.fasterxml.jackson.core</groupId>
            <artifactId>jackson-databind</artifactId>
            <version>2.15.3</version>
        </dependency>
    </dependencies>
</dependencyManagement>

<dependencies>
    <!-- Versiya tələb olunmur — management-dən götürür -->
    <dependency>
        <groupId>com.fasterxml.jackson.core</groupId>
        <artifactId>jackson-databind</artifactId>
    </dependency>
</dependencies>
```

### BOM (Bill of Materials) — import scope

Spring Boot yüzlərlə dependency-nin uyğun versiyalarını bir yerə yığır. Bunu `import` scope ilə istifadə edirik:

```xml
<dependencyManagement>
    <dependencies>
        <dependency>
            <groupId>org.springframework.boot</groupId>
            <artifactId>spring-boot-dependencies</artifactId>
            <version>3.2.0</version>
            <type>pom</type>
            <scope>import</scope>
        </dependency>
    </dependencies>
</dependencyManagement>

<dependencies>
    <!-- BOM artıq versiyaları bilir — yazmağa ehtiyac yoxdur -->
    <dependency>
        <groupId>org.springframework.boot</groupId>
        <artifactId>spring-boot-starter-web</artifactId>
    </dependency>
    <dependency>
        <groupId>org.springframework.boot</groupId>
        <artifactId>spring-boot-starter-data-jpa</artifactId>
    </dependency>
</dependencies>
```

### Parent pom alternativi

```xml
<parent>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-parent</artifactId>
    <version>3.2.0</version>
</parent>

<!-- Bu halda dependency-ləri versiyasız qeyd edirsən -->
<dependencies>
    <dependency>
        <groupId>org.springframework.boot</groupId>
        <artifactId>spring-boot-starter-web</artifactId>
    </dependency>
</dependencies>
```

---

## 12. Properties {#properties}

`<properties>` — **dəyişənlər** yaratmaq üçündür.

```xml
<properties>
    <!-- Build parametrləri -->
    <maven.compiler.source>21</maven.compiler.source>
    <maven.compiler.target>21</maven.compiler.target>
    <project.build.sourceEncoding>UTF-8</project.build.sourceEncoding>

    <!-- Versiya dəyişənləri -->
    <spring.version>6.1.0</spring.version>
    <jackson.version>2.15.3</jackson.version>
    <junit.version>5.10.0</junit.version>
</properties>

<dependencies>
    <dependency>
        <groupId>org.springframework</groupId>
        <artifactId>spring-core</artifactId>
        <version>${spring.version}</version>
    </dependency>
    <dependency>
        <groupId>com.fasterxml.jackson.core</groupId>
        <artifactId>jackson-databind</artifactId>
        <version>${jackson.version}</version>
    </dependency>
</dependencies>
```

### Built-in properties

```xml
${project.version}           <!-- layihənin versiyası -->
${project.artifactId}        <!-- artifactId -->
${project.basedir}           <!-- layihənin root yolu -->
${java.version}              <!-- system property -->
${env.HOME}                  <!-- mühit dəyişəni -->
```

---

## 13. Profiles {#profiles}

Profile — **fərqli mühit üçün fərqli konfiqurasiya**.

```xml
<profiles>
    <profile>
        <id>dev</id>
        <activation>
            <activeByDefault>true</activeByDefault>
        </activation>
        <properties>
            <env>development</env>
            <db.url>jdbc:h2:mem:testdb</db.url>
        </properties>
    </profile>

    <profile>
        <id>prod</id>
        <properties>
            <env>production</env>
            <db.url>jdbc:postgresql://prod-db:5432/app</db.url>
        </properties>
        <dependencies>
            <dependency>
                <groupId>org.postgresql</groupId>
                <artifactId>postgresql</artifactId>
                <version>42.7.0</version>
            </dependency>
        </dependencies>
    </profile>
</profiles>
```

### Profile aktivləşdirmə

```bash
# Profil seç
mvn clean install -P prod

# Bir neçə profil
mvn clean install -P prod,slow-tests

# Profili deaktiv et
mvn clean install -P !dev
```

---

## 14. Tam nümunə — Spring Boot pom.xml {#numune}

```xml
<?xml version="1.0" encoding="UTF-8"?>
<project xmlns="http://maven.apache.org/POM/4.0.0"
         xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:schemaLocation="http://maven.apache.org/POM/4.0.0
                             https://maven.apache.org/xsd/maven-4.0.0.xsd">
    <modelVersion>4.0.0</modelVersion>

    <!-- Spring Boot parent — BOM və plugin konfiqurasiyası -->
    <parent>
        <groupId>org.springframework.boot</groupId>
        <artifactId>spring-boot-starter-parent</artifactId>
        <version>3.2.0</version>
        <relativePath/>
    </parent>

    <!-- Layihənin kimliyi -->
    <groupId>az.example</groupId>
    <artifactId>todo-api</artifactId>
    <version>0.0.1-SNAPSHOT</version>
    <packaging>jar</packaging>

    <name>Todo API</name>
    <description>Sadə TODO REST API</description>

    <properties>
        <java.version>21</java.version>
        <project.build.sourceEncoding>UTF-8</project.build.sourceEncoding>
        <testcontainers.version>1.19.3</testcontainers.version>
    </properties>

    <dependencies>
        <!-- Web layer -->
        <dependency>
            <groupId>org.springframework.boot</groupId>
            <artifactId>spring-boot-starter-web</artifactId>
        </dependency>

        <!-- Data layer -->
        <dependency>
            <groupId>org.springframework.boot</groupId>
            <artifactId>spring-boot-starter-data-jpa</artifactId>
        </dependency>

        <!-- Validation -->
        <dependency>
            <groupId>org.springframework.boot</groupId>
            <artifactId>spring-boot-starter-validation</artifactId>
        </dependency>

        <!-- Actuator — health checks -->
        <dependency>
            <groupId>org.springframework.boot</groupId>
            <artifactId>spring-boot-starter-actuator</artifactId>
        </dependency>

        <!-- PostgreSQL driver — runtime -->
        <dependency>
            <groupId>org.postgresql</groupId>
            <artifactId>postgresql</artifactId>
            <scope>runtime</scope>
        </dependency>

        <!-- H2 — dev və test üçün -->
        <dependency>
            <groupId>com.h2database</groupId>
            <artifactId>h2</artifactId>
            <scope>runtime</scope>
        </dependency>

        <!-- Lombok — compile time -->
        <dependency>
            <groupId>org.projectlombok</groupId>
            <artifactId>lombok</artifactId>
            <optional>true</optional>
        </dependency>

        <!-- TEST -->
        <dependency>
            <groupId>org.springframework.boot</groupId>
            <artifactId>spring-boot-starter-test</artifactId>
            <scope>test</scope>
        </dependency>

        <dependency>
            <groupId>org.testcontainers</groupId>
            <artifactId>junit-jupiter</artifactId>
            <version>${testcontainers.version}</version>
            <scope>test</scope>
        </dependency>

        <dependency>
            <groupId>org.testcontainers</groupId>
            <artifactId>postgresql</artifactId>
            <version>${testcontainers.version}</version>
            <scope>test</scope>
        </dependency>
    </dependencies>

    <build>
        <plugins>
            <!-- Spring Boot executable jar -->
            <plugin>
                <groupId>org.springframework.boot</groupId>
                <artifactId>spring-boot-maven-plugin</artifactId>
                <configuration>
                    <excludes>
                        <exclude>
                            <groupId>org.projectlombok</groupId>
                            <artifactId>lombok</artifactId>
                        </exclude>
                    </excludes>
                </configuration>
            </plugin>
        </plugins>
    </build>

    <profiles>
        <profile>
            <id>prod</id>
            <properties>
                <spring.profiles.active>prod</spring.profiles.active>
            </properties>
        </profile>
    </profiles>
</project>
```

---

## 15. Tez-tez istifadə olunan əmrlər {#emrler}

```bash
# Clean
mvn clean

# Kompilyasiya
mvn compile

# Test
mvn test

# JAR yarat
mvn package

# Lokala install
mvn install

# Test-siz install
mvn install -DskipTests

# Spring Boot app run
mvn spring-boot:run

# Bir testi run et
mvn test -Dtest=UserServiceTest

# Bir metod
mvn test -Dtest=UserServiceTest#shouldCreateUser

# Dependency ağacı
mvn dependency:tree

# Dependency-i yüklə (download only)
mvn dependency:resolve

# Plugin-lər haqqında info
mvn help:effective-pom
mvn help:effective-settings

# Offline mode (internetə çıxmadan)
mvn package -o

# Parallel build (multi-module)
mvn install -T 4   # 4 thread
mvn install -T 1C  # 1 thread per CPU core

# Verbose çıxış
mvn compile -X

# Quiet
mvn compile -q
```

---

## 16. Ümumi Səhvlər {#umumi}

### Səhv 1 — Java version uyğunsuzluğu

```
[ERROR] Source option 8 is no longer supported. Use 17 or later.
```

Həll:

```xml
<properties>
    <maven.compiler.source>21</maven.compiler.source>
    <maven.compiler.target>21</maven.compiler.target>
</properties>
```

### Səhv 2 — SNAPSHOT versiya köhnə qalır

Maven default olaraq SNAPSHOT-ları gündə bir dəfə yoxlayır. Məcburi yeniləmə:

```bash
mvn clean install -U
```

### Səhv 3 — Dependency conflict

```bash
# Diaqnostika
mvn dependency:tree -Dverbose

# Konflikti görəndə exclude et
```

### Səhv 4 — Test skip edib production-a çıxarmaq

```bash
# Xətalı
mvn deploy -DskipTests

# Düzgün
mvn deploy   # testlər run olunmalıdır
```

### Səhv 5 — `target/` folder-i git-ə əlavə edilir

`.gitignore`-a əlavə et:

```
target/
*.log
.m2/
```

### Səhv 6 — Çox böyük fat JAR

Shade plugin ilə bütün bağımlılıqlar daxil olur — 100MB+ olur. Spring Boot layered JAR istifadə et.

### Səhv 7 — Proxy arxasında Maven işləmir

`~/.m2/settings.xml` içində:

```xml
<proxies>
    <proxy>
        <id>company-proxy</id>
        <active>true</active>
        <protocol>http</protocol>
        <host>proxy.company.com</host>
        <port>8080</port>
    </proxy>
</proxies>
```

---

## İntervyu Sualları {#intervyu}

**S1: Maven və Gradle arasında əsas fərqlər nələrdir?**
> Maven — deklarativ, XML-lə (pom.xml), convention over configuration prinsipinə söykənir, öyrənmək asandır. Gradle — Groovy/Kotlin DSL istifadə edir, daha çevikdir, daha sürətlidir (incremental build, daemon), amma öyrənmə əyrisi dikdir. Böyük layihələrdə Gradle daha performant, kiçik və orta layihələrdə Maven daha sadədir.

**S2: Maven lifecycle və phase nədir?**
> Lifecycle — build prosesinin ardıcıllığıdır. 3 əsas lifecycle var: `clean`, `default`, `site`. Default lifecycle-ın phase-ləri (ardıcıllıqla): `validate`, `compile`, `test`, `package`, `verify`, `install`, `deploy`. `mvn install` çağırılanda, bütün əvvəlki phase-lər avtomatik icra olunur.

**S3: GAV nədir?**
> GroupId + ArtifactId + Version — Maven artefaktlarının unikal identifikatorudur. `com.example:my-lib:1.0.0`. GroupId reverse domain-dir (`org.springframework`), ArtifactId layihə adıdır (`spring-core`), Version semver-ə uyğundur (`6.1.0`, `6.1.0-SNAPSHOT`).

**S4: `compile`, `provided`, `runtime`, `test` scope-larının fərqi?**
> `compile` (default) — hər yerdə əlçatan, JAR-a daxil edilir. `provided` — compile və test-də var, runtime-da deploy mühitinin verdiyi kitabxana sayılır (məs. Servlet API). `runtime` — compile-da lazım deyil (JDBC driver). `test` — yalnız test vaxtı (JUnit, Mockito).

**S5: Transitive dependency nədir və versiya konflikti necə həll olunur?**
> Transitive dependency — bağımlılıqların öz bağımlılıqlarıdır. Məsələn A→B, B→C olduqda, C avtomatik gəlir. Versiya konflikti olduqda Maven "nearest wins" qaydasını tətbiq edir — pom.xml-dən ən qısa yoldakı versiya seçilir. Kontrol üçün `<exclusion>` və ya `<dependencyManagement>` istifadə olunur.

**S6: `dependencyManagement` və `dependencies` arasında fərq?**
> `dependencies` — konkret olaraq dependency əlavə edir. `dependencyManagement` — yalnız versiya və scope-u mərkəzləşdirir, dependency əlavə etmir. Multi-module layihələrdə parent pom `dependencyManagement` istifadə edir, child module-lar versiyasız qeyd edirlər.

**S7: BOM nədir?**
> Bill of Materials — bir sıra bir-biri ilə uyğun versiyaları qeyd edən xüsusi pom. `<scope>import</scope>` ilə `dependencyManagement`-ə import edilir. Spring Boot `spring-boot-dependencies` BOM-u yüzlərlə test olunmuş uyğun versiyanı təqdim edir.

**S8: `mvn clean install` ilə `mvn install` fərqi nədir?**
> `mvn install` — mövcud `target/` fayllarını yenidən istifadə edə bilər (incremental build). `mvn clean install` — əvvəl `target/`-i silir, sonra tam yenidən build edir. CI/CD-də həmişə `clean` istifadə olunur ki, stale artifacts qalmasın.

**S9: Maven profile nə üçündür?**
> Fərqli mühitlər üçün fərqli konfiqurasiya. Məsələn `dev` profile H2 database istifadə edir, `prod` PostgreSQL. Aktivləşdirmə: `mvn install -Pprod`, və ya `<activation>` ilə avtomatik (environment variable-a görə).

**S10: Maven local repository harada saxlanır və necə dəyişdirmək olar?**
> Default: `~/.m2/repository/`. Dəyişmək üçün `~/.m2/settings.xml`-də `<localRepository>/custom/path</localRepository>` əlavə edirik. CI/CD-də çox zaman caching üçün custom path istifadə olunur.

**S11: Spring Boot üçün `spring-boot-maven-plugin` nə edir?**
> Spring Boot-a məxsus executable fat JAR yaradır (layered). `mvn package`-dan sonra `java -jar app.jar` ilə birbaşa çalışdırıla bilir. Həmçinin `mvn spring-boot:run` ilə dev mode run imkanı verir.
