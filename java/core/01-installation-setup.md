# 01 — Java Qurulması və JDK/JRE/JVM Fərqi

> **Seviyye:** Junior ⭐


## Mündəricat
1. [Java nədir və niyə istifadə olunur?](#1-java-nədir)
2. [Java-nın qısa tarixi](#2-tarix)
3. [Java versiyaları və LTS nədir?](#3-versiyalar)
4. [JDK vs JRE vs JVM — ən vacib fərq](#4-jdk-jre-jvm)
5. [JDK distribusiyaları — hansını seçmək?](#5-distribusiyalar)
6. [Linux-da Java qurulması](#6-linux)
7. [macOS-da Java qurulması](#7-macos)
8. [Windows-da Java qurulması](#8-windows)
9. [JAVA_HOME təyin etmək](#9-java-home)
10. [Qurmağı yoxlama](#10-yoxlama)
11. [SDKMAN — çoxsaylı Java versiyasını idarə et](#11-sdkman)
12. [İlk command-line kompilyasiyası](#12-ilk-kompilyasiya)
13. [Ümumi Səhvlər](#13-sehvler)
14. [İntervyu Sualları](#14-intervyu)

---

## 1. Java nədir və niyə istifadə olunur? {#1-java-nədir}

**Java** — obyekt-yönümlü, platforma-müstəqil proqramlaşdırma dilidir. 1995-ci ildə Sun Microsystems tərəfindən yaradılmışdır (2010-da Oracle Sun-ı aldı).

**Əsas şüar:** *"Write Once, Run Anywhere"* (Bir dəfə yaz, hər yerdə işə sal).

Real dünyada analogiyası: Elektrik cihazın adapteri kimidir — adapter olduqda eyni cihaz müxtəlif rozetkalarda işləyir. Java da JVM sayəsində eyni kod Windows, Linux və macOS-da işləyir.

### Java-nın əsas xüsusiyyətləri:

| Xüsusiyyət | İzahat |
|---|---|
| Platforma-müstəqil | Bytecode → JVM → hər hansı OS |
| Obyekt-yönümlü | Hər şey obyektlər vasitəsilə həll olunur |
| Statik tipli | Dəyişənin tipi kompilyasiya zamanı yoxlanılır |
| Avtomatik yaddaş idarəetməsi | Garbage Collector (GC) artıq obyektləri silir |
| Çoxşəbəli (multi-threaded) | Eyni anda bir çox iş görə bilər |
| Təhlükəsiz | Sandbox, bytecode yoxlama |

### Java harada istifadə olunur?

- **Enterprise tətbiqlər** — banklar, sığorta, dövlət sistemləri
- **Android tətbiqləri** (Kotlin də işlədilsə belə JVM-də işləyir)
- **Böyük veb tətbiqlər** — Spring Boot, Jakarta EE
- **Big Data** — Hadoop, Spark, Kafka
- **Alət və IDE-lər** — IntelliJ IDEA, Eclipse (özləri Java-da yazılıb)

---

## 2. Java-nın qısa tarixi {#2-tarix}

```
1991 — "Oak" layihəsi başlayır (James Gosling)
1995 — Java 1.0 rəsmi buraxılış
2004 — Java 5 (generics, enum, annotations)
2014 — Java 8 (lambda, Stream API) — ilk böyük LTS
2017 — Java 9 (module system)
2018 — Java 11 (LTS)
2021 — Java 17 (LTS)
2023 — Java 21 (LTS, virtual threads)
2025 — Java 25 (LTS — gözlənilir)
```

---

## 3. Java versiyaları və LTS nədir? {#3-versiyalar}

**LTS (Long-Term Support)** — uzunmüddətli dəstək. Oracle və icma 5-8 il ərzində yamalar (patch) buraxır.

| Versiya | Buraxılış | Tip | Dəstək bitəcək |
|---|---|---|---|
| Java 8 | 2014 | LTS | 2030+ (genişləndirilmiş) |
| Java 11 | 2018 | LTS | 2026 |
| Java 17 | 2021 | LTS | 2029 |
| Java 21 | 2023 | LTS | 2031 |
| Java 25 | 2025 | LTS | 2033 (gözlənilir) |

**Tövsiyə:** Yeni layihələr üçün **Java 21** və ya **Java 25** seçin. Köhnə sistemlərdə Java 8/11/17-ə rast gələ bilərsiniz.

Aralıq versiyalar (9, 10, 12, 13, 14, 15, 16, 18, 19, 20, 22, 23, 24) — "feature release" adlanır və cəmi 6 ay dəstək alır. Produksiyada istifadə tövsiyə edilmir.

---

## 4. JDK vs JRE vs JVM — ən vacib fərq {#4-jdk-jre-jvm}

Bu üç anlayış yeni başlayanları ən çox qarışdıran mövzudur.

### Sadə izah:

- **JVM (Java Virtual Machine)** — bytecode-u icra edən virtual maşındır.
- **JRE (Java Runtime Environment)** — JVM + standart kitabxanalar. Java proqramını **işə salmaq** üçün lazımdır.
- **JDK (Java Development Kit)** — JRE + kompilyator (`javac`) + inkişaf alətləri. Java proqramı **yazmaq** üçün lazımdır.

### ASCII diaqram — bir-birinin içindədir:

```
┌─────────────────────────────────────────────────────┐
│                      JDK                            │
│  (Development Kit — proqram yazmaq üçün)            │
│                                                     │
│   javac (kompilyator)                               │
│   javadoc (dokumentasiya generatoru)                │
│   jar (arxiv alətləri)                              │
│   jdb (debugger)                                    │
│   jlink, jshell, ...                                │
│                                                     │
│   ┌───────────────────────────────────────────┐     │
│   │                  JRE                      │     │
│   │  (Runtime Environment — işə salmaq üçün)  │     │
│   │                                           │     │
│   │   Java standart kitabxanalar (String,     │     │
│   │   ArrayList, File, Thread, ...)           │     │
│   │                                           │     │
│   │   ┌─────────────────────────────────┐     │     │
│   │   │            JVM                  │     │     │
│   │   │  (Virtual Machine)              │     │     │
│   │   │                                 │     │     │
│   │   │   Class Loader                  │     │     │
│   │   │   Bytecode Verifier             │     │     │
│   │   │   Execution Engine (JIT)        │     │     │
│   │   │   Garbage Collector             │     │     │
│   │   │   Runtime Data Areas (heap, ... │     │     │
│   │   └─────────────────────────────────┘     │     │
│   └───────────────────────────────────────────┘     │
└─────────────────────────────────────────────────────┘
```

### Müqayisə cədvəli:

| Xüsusiyyət | JVM | JRE | JDK |
|---|---|---|---|
| Nə edir? | Bytecode-u icra edir | Proqramı işə salır | Proqram yazılıb-işə salınır |
| Tərkibi | Yalnız virtual maşın | JVM + kitabxanalar | JRE + kompilyator + alətlər |
| Kimə lazımdır? | Daxili komponent | Son istifadəçiyə | Developer-ə |
| Həcm | Ən kiçik | Orta | Ən böyük |

### Java 11-dən sonra vacib dəyişiklik:

Java 11-dən başlayaraq **müstəqil JRE paketləri buraxılmır**. Yalnız JDK paylanır. Əgər yalnız işə salmaq lazımdırsa, `jlink` alətilə JDK-dan kiçik runtime yaratmaq olar.

### Kod nümunəsi ilə izahat:

```java
// Bu kodu yazanda — JDK lazımdır (çünki kompilyator lazımdır)
// Fayl adı: Salam.java
public class Salam {
    public static void main(String[] args) {
        System.out.println("Salam, dünya!");
    }
}
```

```bash
# 1. Kompilyasiya (JDK-nın javac-i lazımdır)
javac Salam.java      # Salam.class bytecode faylı yaradır

# 2. İcra (JRE kifayətdir, çünki yalnız JVM işə düşür)
java Salam            # Çıxış: Salam, dünya!
```

---

## 5. JDK distribusiyaları — hansını seçmək? {#5-distribusiyalar}

"Java" açıq mənbədir (OpenJDK), ancaq müxtəlif şirkətlər öz "build"larını buraxır.

| Distribusiya | Kim? | Pulsuzmu? | Tövsiyə |
|---|---|---|---|
| **Oracle JDK** | Oracle | Produksiyada lisenziya lazımdır | Enterprise-da Oracle dəstəyi lazım olsa |
| **OpenJDK** | Oracle/icma | Tam pulsuz | Bütün halllarda |
| **Eclipse Temurin** | Eclipse Foundation | Tam pulsuz | Çoxlu platforma, güclü icma — **ən populyar seçim** |
| **Amazon Corretto** | Amazon | Tam pulsuz | AWS-də işləyirsinizsə |
| **Azul Zulu** | Azul Systems | Tam pulsuz (kommersial versiya da var) | Uzunmüddətli dəstək lazımdırsa |
| **GraalVM** | Oracle | Pulsuz (community) | Native image, çox dilli layihələr |
| **Microsoft Build of OpenJDK** | Microsoft | Pulsuz | Azure-da |
| **IBM Semeru** | IBM | Pulsuz | WebSphere istifadəçiləri |

**Yeni başlayanlara tövsiyə:** **Eclipse Temurin** (köhnə adı AdoptOpenJDK). Səbəb:
- Tam pulsuz
- Güclü icma
- Bütün platformalarda mövcud
- Güvənilir

Yükləmə saytı: https://adoptium.net

---

## 6. Linux-da Java qurulması {#6-linux}

### Ubuntu/Debian (apt):

```bash
# Paket siyahısını yenilə
sudo apt update

# Temurin repo-sunu əlavə et
sudo apt install -y wget apt-transport-https gpg
wget -O - https://packages.adoptium.net/artifactory/api/gpg/key/public | \
  sudo gpg --dearmor -o /usr/share/keyrings/adoptium.gpg
echo "deb [signed-by=/usr/share/keyrings/adoptium.gpg] \
  https://packages.adoptium.net/artifactory/deb $(lsb_release -cs) main" | \
  sudo tee /etc/apt/sources.list.d/adoptium.list

# Java 21 LTS qur
sudo apt update
sudo apt install -y temurin-21-jdk

# Yoxla
java -version
javac -version
```

### Fedora/RHEL/CentOS (dnf):

```bash
# Sistem OpenJDK
sudo dnf install -y java-21-openjdk-devel

# Və ya manual yüklə və aç
wget https://github.com/adoptium/temurin21-binaries/releases/download/.../OpenJDK21U-jdk_x64_linux.tar.gz
tar -xzf OpenJDK21U-jdk_x64_linux.tar.gz -C /opt/
```

### Arch Linux:

```bash
sudo pacman -S jdk21-openjdk
```

---

## 7. macOS-da Java qurulması {#7-macos}

### Homebrew ilə (tövsiyə):

```bash
# Homebrew qurulu deyilsə:
# /bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"

# Temurin 21 qur
brew install --cask temurin@21

# Və ya ən son versiya:
brew install --cask temurin

# Yoxla
java -version
```

### Manual yüklə (Apple Silicon M1/M2/M3):

```bash
# adoptium.net saytından aarch64 (ARM64) .pkg yüklə və quraşdır
# Default yer: /Library/Java/JavaVirtualMachines/temurin-21.jdk/Contents/Home
```

### Çoxlu Java versiyası arasında keçid:

```bash
# Qurulmuş versiyaları göstər
/usr/libexec/java_home -V

# Müəyyən versiyaya keç
export JAVA_HOME=$(/usr/libexec/java_home -v 21)
```

---

## 8. Windows-da Java qurulması {#8-windows}

### Üsul 1 — MSI installer:

1. https://adoptium.net saytına keç.
2. **Windows x64** üçün Java 21 LTS `.msi` yüklə.
3. İnstaller-i işə sal, "Set JAVA_HOME" seçimini yoxla.
4. `Next → Next → Install` bas.

### Üsul 2 — Winget:

```powershell
# PowerShell-də
winget install EclipseAdoptium.Temurin.21.JDK
```

### Üsul 3 — Chocolatey:

```powershell
choco install temurin21
```

### Command Prompt-da yoxla:

```cmd
java -version
javac -version
```

---

## 9. JAVA_HOME təyin etmək {#9-java-home}

`JAVA_HOME` — Java-nın quraşdırıldığı qovluğa işarə edən mühit dəyişənidir. Maven, Gradle, IntelliJ, Spring və bir çox alət bunu istifadə edir.

### Linux / macOS (bash/zsh):

```bash
# ~/.bashrc və ya ~/.zshrc faylına əlavə et:
export JAVA_HOME=/usr/lib/jvm/temurin-21-jdk-amd64   # Linux
# və ya
export JAVA_HOME=/Library/Java/JavaVirtualMachines/temurin-21.jdk/Contents/Home  # macOS

export PATH=$JAVA_HOME/bin:$PATH

# Dəyişiklikləri aktivləşdir
source ~/.bashrc     # və ya source ~/.zshrc
```

### Windows (GUI):

1. **Start → "Environment Variables" axtar**.
2. **System variables → New**:
   - Ad: `JAVA_HOME`
   - Dəyər: `C:\Program Files\Eclipse Adoptium\jdk-21.0.1+12`
3. **Path → Edit → New → `%JAVA_HOME%\bin`**.
4. OK bas, terminal-ı yenidən aç.

### Windows (PowerShell):

```powershell
# Cari sessiya üçün
$env:JAVA_HOME = "C:\Program Files\Eclipse Adoptium\jdk-21.0.1+12"
$env:Path = "$env:JAVA_HOME\bin;$env:Path"

# Daimi (system-wide):
[Environment]::SetEnvironmentVariable("JAVA_HOME",
    "C:\Program Files\Eclipse Adoptium\jdk-21.0.1+12",
    "Machine")
```

---

## 10. Qurmağı yoxlama {#10-yoxlama}

### Əsas yoxlama:

```bash
$ java -version
openjdk version "21.0.1" 2023-10-17 LTS
OpenJDK Runtime Environment Temurin-21.0.1+12 (build 21.0.1+12-LTS)
OpenJDK 64-Bit Server VM Temurin-21.0.1+12 (build 21.0.1+12-LTS, mixed mode)

$ javac -version
javac 21.0.1

$ echo $JAVA_HOME
/usr/lib/jvm/temurin-21-jdk-amd64
```

### Əgər xəta alırsınızsa:

```bash
# "command not found: java"
# Səbəb: PATH-də java yoxdur. JAVA_HOME/bin-i PATH-ə əlavə etməyi unutmusunuz.

# Həll:
which java        # Linux/macOS — Java-nın yerini göstərir
where java        # Windows
```

### İkinci yoxlama — bütün versiyaları göstər:

```bash
# Linux
update-alternatives --list java

# macOS
/usr/libexec/java_home -V

# Windows
dir "C:\Program Files\Eclipse Adoptium"
```

---

## 11. SDKMAN — çoxsaylı Java versiyasını idarə et {#11-sdkman}

Bir neçə layihə müxtəlif Java versiyaları tələb edəndə **SDKMAN** həyatınızı xilas edir.

### SDKMAN qurulması (Linux/macOS):

```bash
curl -s "https://get.sdkman.io" | bash
source ~/.sdkman/bin/sdkman-init.sh
sdk version
```

### Java-nı idarə et:

```bash
# Mövcud Java versiyalarını göstər
sdk list java

# Müəyyən versiyanı qur
sdk install java 21.0.1-tem       # Temurin 21
sdk install java 17.0.9-tem       # Temurin 17
sdk install java 21.0.1-graal     # GraalVM 21

# Cari versiyanı göstər
sdk current java

# Default versiyanı dəyiş
sdk default java 21.0.1-tem

# Cari terminalda müvəqqəti dəyiş
sdk use java 17.0.9-tem

# Sil
sdk uninstall java 17.0.9-tem
```

### Layihə üçün versiya bağla (`.sdkmanrc`):

```bash
# Layihə qovluğunda:
sdk env init        # .sdkmanrc faylı yaradır

# Fayl məzmunu:
# java=21.0.1-tem

# Qovluğa girəndə avtomatik dəyişsin:
sdk env install
```

---

## 12. İlk command-line kompilyasiyası {#12-ilk-kompilyasiya}

### Addım 1 — Fayl yarat:

```bash
mkdir ilk-java-layihesi
cd ilk-java-layihesi
```

### Addım 2 — `Salam.java` yarat:

```java
public class Salam {
    public static void main(String[] args) {
        System.out.println("Salam, Java dünyası!");
        System.out.println("2 + 3 = " + (2 + 3));
    }
}
```

**Vacib:** Fayl adı **mütləq** `Salam.java` olmalıdır (class adı ilə eyni).

### Addım 3 — Kompilyasiya:

```bash
javac Salam.java
# Nəticədə: Salam.class faylı yaranır
```

### Addım 4 — İcra:

```bash
java Salam
# Çıxış:
# Salam, Java dünyası!
# 2 + 3 = 5
```

**Vacib:** `java Salam` yazırsınız — `java Salam.class` deyil. Uzantı yazmayın.

### Java 11+ — tək fayl rejimi:

Java 11-dən başlayaraq kompilyasiyasız işə salmaq olar:

```bash
java Salam.java
# Yalnız öyrənmə və skript üçün. Böyük layihələrdə javac istifadə et.
```

### Paketlər (package) varsa:

```java
// fayl: src/com/example/Salam.java
package com.example;

public class Salam {
    public static void main(String[] args) {
        System.out.println("Paket daxilindən salam!");
    }
}
```

```bash
# src qovluğundan kompilyasiya
cd src
javac com/example/Salam.java       # class/ qovluğuna yazdır
java com.example.Salam             # tam qualified ad ilə işə sal
```

---

## 13. Ümumi Səhvlər {#13-sehvler}

### Səhv 1: `java -version` işləyir, `javac` işləmir

```bash
$ java -version
openjdk version "21.0.1"
$ javac -version
bash: javac: command not found
```

**Səbəb:** JDK əvəzinə yalnız JRE qurulub.
**Həll:** JDK qur (Temurin, Corretto və s.).

### Səhv 2: Fayl adı class adı ilə eyni deyil

```java
// fayl: salam.java (kiçik s)
public class Salam {  // Böyük S
    public static void main(String[] args) { }
}
```

```bash
$ javac salam.java
salam.java:1: error: class Salam is public, should be declared in a file named Salam.java
```

**Həll:** Fayl adını `Salam.java` et (class adı ilə eyni, hətta hərf böyüklüyü də).

### Səhv 3: `.class` uzantısı əlavə etmək

```bash
$ java Salam.class
Error: Could not find or load main class Salam.class
```

**Həll:** Uzantı yazma → `java Salam`.

### Səhv 4: JAVA_HOME `/bin`-ə işarə edir

```bash
# YANLIŞ:
export JAVA_HOME=/usr/lib/jvm/temurin-21-jdk-amd64/bin

# DÜZGÜN:
export JAVA_HOME=/usr/lib/jvm/temurin-21-jdk-amd64
```

Çünki Maven/Gradle `$JAVA_HOME/bin/java` axtarır — iki dəfə `bin/bin/java` alınar.

### Səhv 5: Köhnə versiya istifadə edilir

```bash
$ which java
/usr/bin/java           # sistem köhnə versiya

$ java -version
openjdk version "11.0.20"    # yeni 21 qursan da 11 gəlir
```

**Səbəb:** PATH-də köhnə `java` daha əvvəldir.
**Həll:** `export PATH=$JAVA_HOME/bin:$PATH` (JAVA_HOME-u **ƏN BAŞA** qoy).

---

## 14. İntervyu Sualları {#14-intervyu}

**S1: JDK, JRE, JVM arasındakı fərq nədir?**
> JVM — bytecode-u icra edən virtual maşındır. JRE — JVM + standart kitabxanalar (işə salmaq üçün kifayətdir). JDK — JRE + kompilyator (`javac`) + developer alətləri (proqram yazmaq üçün lazımdır). Əlaqə: **JDK ⊃ JRE ⊃ JVM**.

**S2: "Write Once, Run Anywhere" nə deməkdir?**
> Java kodu bytecode-a (platforma-müstəqil ara formata) kompilyasiya olunur. Bu bytecode hər OS-nin öz JVM-i tərəfindən icra olunur. Nəticədə eyni `.class` faylı Windows, Linux və macOS-da işləyir.

**S3: LTS (Long-Term Support) versiyası nədir və niyə vacibdir?**
> LTS versiyalar 5-8 il təhlükəsizlik və kritik hata yamaları alır. Produksiya sistemləri üçün LTS (Java 8, 11, 17, 21, 25) seçilməlidir. Aralıq versiyalar (9, 10, 12, ...) cəmi 6 ay dəstək alır.

**S4: Hansı JDK distribusiyasını seçmək lazımdır?**
> Əksər hallar üçün **Eclipse Temurin** ən yaxşı pulsuz seçimdir. Amazon-da işləyirsinizsə `Corretto`, native image lazımdırsa `GraalVM`, Oracle dəstəyi tələb olunursa `Oracle JDK`.

**S5: `JAVA_HOME` nə üçün lazımdır?**
> Maven, Gradle, Spring Boot, IDE-lər və bir çox alət Java-nın yerini tapmaq üçün `JAVA_HOME` mühit dəyişəninə baxır. Onu JDK qovluğunun kökünə (bin-ə deyil) təyin etmək lazımdır.

**S6: Java 11-dən sonra JRE niyə ayrıca yüklənmir?**
> Oracle qərar verdi ki, müstəqil JRE paketləri deprecated olsun. Əvəzində `jlink` alətilə proqramınız üçün yalnız lazımi modulları özündə saxlayan kiçik runtime yaratmaq olar.

**S7: `java Salam.java` əmri ilə `javac Salam.java && java Salam` arasında fərq nədir?**
> Java 11+ `java Salam.java` tək faylı birbaşa işə salır — arxa planda yaddaşda kompilyasiya edir, `.class` fayl yaratmır. Yalnız sadə skriptlər üçün istifadə olunur. Böyük layihələr üçün normal `javac` → `java` axını lazımdır.

**S8: Eyni maşında çoxlu Java versiyası necə saxlanılır?**
> **Linux/macOS:** SDKMAN (`sdk use java 21.0.1-tem`). **macOS:** `/usr/libexec/java_home -v 21`. **Windows:** `JAVA_HOME`-u dəyişmək və ya `jEnv`, `jabba` kimi alətlər.

**S9: JVM-in içində nə var?**
> ClassLoader (class yükləmə), Bytecode Verifier (təhlükəsizlik yoxlaması), Execution Engine (interpreter + JIT kompilyator), Garbage Collector, Runtime Data Areas (heap, stack, method area, PC register).

**S10: Niyə Java dilində `.java` faylı kompilyasiya olunanda bytecode yaranır, sistem üçün native kod yox?**
> Bytecode platforma-müstəqildir — hər hansı JVM onu icra edə bilər. Native kod hər OS/CPU üçün ayrı yaradılmalı olardı. JIT (Just-In-Time) kompilyator run-time zamanı isti (çox çağırılan) bytecode-u native koda çevirir — həm daşınan, həm sürətli.
