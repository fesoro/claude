# 03 — IntelliJ IDEA Quraşdırma və Qısayollar

> **Seviyye:** Junior ⭐


## Mündəricat
1. [IntelliJ IDEA nədir?](#nədir)
2. [Community vs Ultimate](#editions)
3. [Quraşdırma (Toolbox ilə)](#quraşdırma)
4. [İlk Açılış və JDK Konfiqurasiyası](#ilk-açılış)
5. [Yeni Layihə Yaratmaq](#layihə-yaratmaq)
6. [Maven/Gradle Layihəsini İdxal](#idxal)
7. [Run və Debug Konfiqurasiyası](#run-debug)
8. [Ən Faydalı Qısayollar](#qısayollar)
9. [Refaktorinq Qısayolları](#refaktorinq)
10. [Live Templates (`psvm`, `sout`, `fori`)](#templates)
11. [Plugin-lər](#plugins)
12. [Ümumi Səhvlər](#səhvlər)
13. [İntervyu Sualları](#intervyu)

---

## 1. IntelliJ IDEA nədir? {#nədir}

**IntelliJ IDEA** — JetBrains şirkəti tərəfindən inkişaf etdirilən Java IDE-sidir. Java ekosistemində ən populyar IDE hesab olunur. Ağıllı kod tamamlama, güclü refaktorinq alətləri, Spring/Hibernate/Kafka inteqrasiyası və s.

Əsas rəqibləri: **Eclipse**, **VS Code + Java Extension Pack**, **NetBeans**. Böyük Java layihələrində IntelliJ IDEA de-fakto standartdır.

Real dünyada: IntelliJ IDEA bir ağıllı mühərrikli maşına bənzəyir — sürürsən, özü vites dəyişir, lent-keçidi xəbərdarlıq edir, yanacaq azalanda işıq yandırır. Sən sadəcə sükanı idarə edirsən.

---

## 2. Community vs Ultimate {#editions}

JetBrains iki versiya verir:

| Xüsusiyyət | Community (pulsuz) | Ultimate (pullu) |
|---|---|---|
| Qiymət | $0 | ~$170/il fərdi |
| Java, Kotlin, Scala | ✓ | ✓ |
| Maven, Gradle | ✓ | ✓ |
| JUnit, TestNG | ✓ | ✓ |
| Git, Mercurial | ✓ | ✓ |
| **Spring Framework** | ✗ | ✓ |
| **Spring Boot** | ✗ | ✓ |
| **JPA / Hibernate** | ✗ | ✓ |
| **HTTP Client** | ✗ | ✓ |
| **Database tool** | ✗ | ✓ |
| **JavaScript / TypeScript** | ✗ | ✓ |
| **Docker / Kubernetes** | ✗ | ✓ |
| **Profiler** | ✗ | ✓ |

Pulsuz alternativ Ultimate əvəzinə: **Visual Studio Code + Extension Pack for Java + Spring Boot Extension Pack**. Ancaq dərin Spring inteqrasiyası üçün IntelliJ Ultimate daha güclüdür.

JetBrains tələbələrə və açıq-kaynak layihə sahiblərinə pulsuz Ultimate verir: [jetbrains.com/community/education](https://www.jetbrains.com/community/education/).

---

## 3. Quraşdırma (Toolbox ilə) {#quraşdırma}

**JetBrains Toolbox** — IDE-ləri idarə edən rəsmi alətdir. Yeniləmələri avtomatik edir, versiyalar arasında keçid asanlaşdırır.

### Quraşdırma addımları:

**Linux:**
```bash
# Toolbox yüklə
wget https://download.jetbrains.com/toolbox/jetbrains-toolbox-2.2.0.14264.tar.gz
tar -xzf jetbrains-toolbox-*.tar.gz
cd jetbrains-toolbox-*/
./jetbrains-toolbox

# Toolbox açıldı — oradan IntelliJ IDEA Community və ya Ultimate quraşdır
```

**macOS:**
```bash
brew install --cask jetbrains-toolbox
```

**Windows:**
Toolbox-u saytdan yüklə, .exe işə sal, sonra IntelliJ seç.

### Toolbox-suz (birbaşa):

[jetbrains.com/idea/download](https://www.jetbrains.com/idea/download/) — birbaşa yüklə və quraşdır.

### Quraşdırmanı yoxlamaq:

IntelliJ IDEA-nı ilk dəfə işə salanda **Welcome** pəncərəsi açılır. Oradan **New Project**, **Open**, ya da **Get from VCS** seçə bilərsən.

---

## 4. İlk Açılış və JDK Konfiqurasiyası {#ilk-açılış}

IntelliJ kod yazmaq üçün JDK tələb edir. Əgər yoxdursa, özü yükləyə bilər.

### JDK əlavə etmək:

1. **File** → **Project Structure** (`Ctrl+Alt+Shift+S` / `⌘;`)
2. **Platform Settings** → **SDKs**
3. `+` düyməsi → **Download JDK...**
4. Versiya (21 LTS tövsiyə olunur) və distribusiya (Temurin, Corretto, Oracle) seç

### Mövcud JDK-nı əlavə et:

Əgər sistemdə artıq JDK qurulubsa (`/usr/lib/jvm/...` və ya `~/.sdkman/candidates/java/current`):

`+` → **Add JDK...** → qovluq seç.

### Layihə JDK-sını seç:

**Project Structure** → **Project** → **SDK** → yüklənmiş JDK seç.

---

## 5. Yeni Layihə Yaratmaq {#layihə-yaratmaq}

### Sadə Java konsol tətbiqi:

1. **File** → **New** → **Project**
2. **Generators**: **New Project**
3. Ad, yer seç; **Language**: Java; **Build system**: Maven (və ya Gradle)
4. **JDK**: 21
5. **Create**

Layihə strukturu:
```
myapp/
├── src/
│   └── main/java/
│       └── Main.java
├── pom.xml
└── .idea/
```

### Spring Boot layihəsi:

**Ultimate:** **New Project** → **Spring Initializr** (daxili) → dependencies seç.

**Community:** [start.spring.io](https://start.spring.io) saytına get, layihəni yüklə, sonra IntelliJ-də **File** → **Open** et.

---

## 6. Maven/Gradle Layihəsini İdxal {#idxal}

Mövcud layihəni açmaq:

1. **File** → **Open**
2. `pom.xml` və ya `build.gradle` seç (faylı deyil, qovluğu seçirsən, IntelliJ tanıyır)
3. **Open as Project**
4. Sağ aşağı küncdə "Maven projects need to be imported" bildirişi çıxarsa → **Import Changes**

IntelliJ dependencies-i `~/.m2/repository` və ya `~/.gradle/caches`-dən indeksləyir. Bu bir neçə dəqiqə çəkə bilər — **indexing** prosesi.

### Yenidən import:

Əgər `pom.xml` və ya `build.gradle` dəyişdikdən sonra IntelliJ yenilənmirsə:
- **Maven** paneli sağda → 🔄 **Reload All Maven Projects**
- **Gradle** paneli sağda → 🔄 **Reload All Gradle Projects**

Qısayol: `Ctrl+Shift+O` (Linux/Win), `⌘⇧I` (macOS).

---

## 7. Run və Debug Konfiqurasiyası {#run-debug}

### Sürətli Run:

- Main metodun solundakı yaşıl ▶ düyməsinə bas — avtomatik konfiq yaradır
- Qısayol: `Ctrl+Shift+F10` (Linux/Win), `⌃⇧R` (macOS) — kursor altındakı konfiqi işlə sal
- `Shift+F10` — son dəfəki konfiqi işlə sal
- `Shift+F9` — debug ilə son konfiq

### Run Configuration əlavə etmək:

1. Yuxarı sağda **Run Configuration** dropdown → **Edit Configurations...**
2. `+` → **Application** / **JUnit** / **Spring Boot** / **Maven** və s.
3. **Main class**, **VM options**, **Program arguments**, **Environment variables** təyin et

### Spring Boot üçün `application.properties`:

```
# Environment variables daxil
SPRING_PROFILES_ACTIVE=dev
DB_URL=jdbc:postgresql://localhost:5432/mydb

# VM options
-Xmx512m -Xms256m

# Program arguments
--server.port=8081
```

---

## 8. Ən Faydalı Qısayollar {#qısayollar}

İntelliJ-də qısayolları öyrənmək məhsuldarlığı 5x artırır. Aşağıdakılar minimal "must-know" siyahıdır.

### Naviqasiya:

| Əməliyyat | Windows / Linux | macOS |
|---|---|---|
| Search Everywhere | `Shift+Shift` | `⇧⇧` |
| Class axtar | `Ctrl+N` | `⌘O` |
| File axtar | `Ctrl+Shift+N` | `⌘⇧O` |
| Symbol axtar | `Ctrl+Alt+Shift+N` | `⌘⌥O` |
| Action axtar | `Ctrl+Shift+A` | `⌘⇧A` |
| Go to definition | `Ctrl+B` / `Ctrl+Click` | `⌘B` |
| Find usages | `Alt+F7` | `⌥F7` |
| Recent files | `Ctrl+E` | `⌘E` |
| Recent locations | `Ctrl+Shift+E` | `⌘⇧E` |
| File structure | `Ctrl+F12` | `⌘F12` |
| Back/Forward | `Ctrl+Alt+←/→` | `⌘[` / `⌘]` |
| Go to line | `Ctrl+G` | `⌘L` |

### Redaktə:

| Əməliyyat | Windows / Linux | macOS |
|---|---|---|
| Format code | `Ctrl+Alt+L` | `⌘⌥L` |
| Organize imports | `Ctrl+Alt+O` | `⌃⌥O` |
| Quick fix / intention | `Alt+Enter` | `⌥↵` |
| Duplicate line | `Ctrl+D` | `⌘D` |
| Delete line | `Ctrl+Y` | `⌘⌫` |
| Move line up/down | `Alt+Shift+↑/↓` | `⌥⇧↑/↓` |
| Comment line | `Ctrl+/` | `⌘/` |
| Block comment | `Ctrl+Shift+/` | `⌘⇧/` |
| Expand selection | `Ctrl+W` | `⌥↑` |
| Shrink selection | `Ctrl+Shift+W` | `⌥↓` |
| Multi-cursor | `Alt+J` (next occurrence) | `⌃G` |
| Column select | `Alt+Drag` | `⌥Drag` |

### Axtarış/Əvəzlənmə:

| Əməliyyat | Windows / Linux | macOS |
|---|---|---|
| Find in file | `Ctrl+F` | `⌘F` |
| Replace in file | `Ctrl+R` | `⌘R` |
| Find in project | `Ctrl+Shift+F` | `⌘⇧F` |
| Replace in project | `Ctrl+Shift+R` | `⌘⇧R` |
| Next error | `F2` | `F2` |

### Run/Debug:

| Əməliyyat | Windows / Linux | macOS |
|---|---|---|
| Run | `Shift+F10` | `⌃R` |
| Debug | `Shift+F9` | `⌃D` |
| Stop | `Ctrl+F2` | `⌘F2` |
| Rerun | `Ctrl+F5` | `⌃⌘R` |
| Toggle breakpoint | `Ctrl+F8` | `⌘F8` |

### Terminal/Tool pəncərələri:

| Əməliyyat | Windows / Linux | macOS |
|---|---|---|
| Terminal aç | `Alt+F12` | `⌥F12` |
| Project tree | `Alt+1` | `⌘1` |
| Git | `Alt+9` | `⌘9` |
| Close tool window | `Esc` (tool-dan) | `Esc` |

---

## 9. Refaktorinq Qısayolları {#refaktorinq}

Refaktorinq IntelliJ-in ən güclü tərəflərindən biridir. Adları dəyişəndə bütün istifadə yerləri özü yenilənir.

| Refaktorinq | Windows / Linux | macOS |
|---|---|---|
| Rename (istənilən symbol) | `Shift+F6` | `⇧F6` |
| Extract Method | `Ctrl+Alt+M` | `⌘⌥M` |
| Extract Variable | `Ctrl+Alt+V` | `⌘⌥V` |
| Extract Constant | `Ctrl+Alt+C` | `⌘⌥C` |
| Extract Field | `Ctrl+Alt+F` | `⌘⌥F` |
| Extract Parameter | `Ctrl+Alt+P` | `⌘⌥P` |
| Inline | `Ctrl+Alt+N` | `⌘⌥N` |
| Change signature | `Ctrl+F6` | `⌘F6` |
| Move | `F6` | `F6` |
| Safe Delete | `Alt+Delete` | `⌘⌫` |
| Refactor menyusu | `Ctrl+Alt+Shift+T` | `⌃T` |

### Nümunə: "Extract Method"

Kodun bir hissəsini seç → `Ctrl+Alt+M` → ada ver → yeni metod kimi ayrılır. Bütün istifadə yerləri avtomatik yenilənir.

```java
// Əvvəl:
double ümumi = qiymət * miqdar;
double vergi = ümumi * 0.18;
double sonNəticə = ümumi + vergi;

// Ctrl+Alt+M ilə hesablaVergiIlə() metodu çıxardırıq:
double sonNəticə = hesablaVergiIlə(qiymət, miqdar);
```

---

## 10. Live Templates (`psvm`, `sout`, `fori`) {#templates}

Live templates — qısa abbreviaturaları yazıb `Tab` basaraq böyük kod bloklarını generasiya etməyə imkan verir.

### Ən vacib template-lər:

| Abbreviatura | Açılışı |
|---|---|
| `psvm` + Tab | `public static void main(String[] args) { }` |
| `sout` + Tab | `System.out.println();` |
| `souf` + Tab | `System.out.printf("", );` |
| `soutv` + Tab | `System.out.println("dəyişənAdı = " + dəyişənAdı);` |
| `soutm` + Tab | `System.out.println("ClassName.methodName");` |
| `fori` + Tab | `for (int i = 0; i < ; i++) { }` |
| `iter` + Tab | `for (Type item : collection) { }` |
| `itar` + Tab | for + array iteration |
| `ifn` + Tab | `if (x == null) { }` |
| `inn` + Tab | `if (x != null) { }` |
| `psf` + Tab | `public static final` |
| `psfs` + Tab | `public static final String` |
| `psfi` + Tab | `public static final int` |
| `thr` + Tab | `throw new ...();` |
| `try` + Tab | try-catch blok |

### Öz template-ini yarat:

**Settings** → **Editor** → **Live Templates** → `+` → yeni group → yeni template.

Məs., `logi` — Logger info hazırlamaq:
```
log.info("$END$");
```

### Postfix Templates (daha güclü):

Əvvəl ifadəni yaz, sonra `.nəsə` artırıb Tab:

| İfadə | Nəticə |
|---|---|
| `list.for` + Tab | `for (Type item : list) { }` |
| `list.iter` + Tab | for-each loop |
| `x.var` + Tab | `Type x = x;` (dəyişənə mənimsə) |
| `x.nn` + Tab | `if (x != null) { }` |
| `x.null` + Tab | `if (x == null) { }` |
| `x.sout` + Tab | `System.out.println(x);` |
| `x.return` + Tab | `return x;` |
| `x.not` + Tab | `!x` |

Bu üsul "dəyişəndən başla → əməliyyat seç" düşüncə tərzinə uyğundur.

---

## 11. Plugin-lər {#plugins}

**File** → **Settings** → **Plugins** → **Marketplace** tabında axtarış. Top plugin-lər:

### 1. Lombok
Java-da boilerplate azaltmaq üçün `@Data`, `@Getter`, `@Setter`, `@Builder` və s. annotasiyaları görmək üçün IntelliJ-də **Lombok plugin** aktiv olmalıdır.

### 2. SonarLint
Kodda potensial buglar, code smells, security issues real-time-da göstərilir. Komandada uniform qayda.

### 3. GitToolbox
Hər sətirdə Git blame annotasiyası, in-editor commit view, notification-lar.

### 4. Rainbow Brackets
İç-içə mötərizələri rəng kodu ilə ayırır. Deeply nested Stream/Lambda-da xüsusilə faydalı.

### 5. Key Promoter X
Siçan klikləməyi tutur və "bunun qısayolu `Ctrl+Alt+L` idi" bildirişi göstərir. Qısayolları öyrətmək üçün əla.

### 6. .ignore
.gitignore, .dockerignore və s. üçün sintaksis vurğusu və template-lər.

### 7. String Manipulation
Camel case ↔ snake case, encode/decode, sort, filter və s.

### 8. Translation
Koddakı String-ləri seçib birbaşa Google/DeepL ilə tərcümə etmək.

### 9. Material Theme UI / One Dark
Görünüş dəyişdirmək üçün.

### 10. JPA Buddy (Ultimate-ə əlavə)
Entity-lərdən Flyway migrasiyası yaratmaq, repository generasiya etmək.

---

## 12. Ümumi Səhvlər {#səhvlər}

### 1. Yanlış JDK seçimi
Layihə Java 21 tələb edir, amma **Project SDK** Java 8-dir. Kompilyasiya xətaları: "switch expressions not supported in -source 8".
**Həll:** **Project Structure** → **Project** → **SDK** → 21 seç.

### 2. Maven/Gradle dəyişikliklərinin görünməməsi
`pom.xml`-ə yeni dependency əlavə etdin, amma IntelliJ import etmir.
**Həll:** Sağdakı **Maven**/**Gradle** panelində 🔄 düyməsi, ya da `Ctrl+Shift+O`.

### 3. Indexing təkrar-təkrar işləyir
Layihə açılanda hər dəfə uzun müddət indeksləşir.
**Həll:** `build/`, `node_modules/`, `target/` qovluqlarını **Excluded** et — sağ klik → **Mark Directory as** → **Excluded**.

### 4. Lombok işləmir
`@Data` annotasiyası gətirdin, amma `getName()` "undefined" göstərir.
**Həll:** (1) Lombok plugin aktiv olmalı; (2) **Settings** → **Build, Execution, Deployment** → **Compiler** → **Annotation Processors** → "Enable annotation processing" aktiv et.

### 5. "Out of memory" indexing zamanı
Böyük layihələr üçün default heap azlıq edir.
**Həll:** **Help** → **Change Memory Settings** → `Xmx` dəyərini 4GB+ et.

### 6. Qısayollar işləmir
Xüsusilə `Ctrl+Alt+L` (format) Linux-da screen lock edir.
**Həll:** OS-də qısayolu deaktiv et, ya da **Settings** → **Keymap**-dan IntelliJ-də dəyiş.

### 7. Git commit dialoqu açılmır
Commit mesajı pəncərəsi yox, birbaşa commit olur.
**Həll:** **Settings** → **Version Control** → **Commit** → **Use non-modal commit interface** aktiv et.

---

## 13. İntervyu Sualları {#intervyu}

**S1: IntelliJ Community və Ultimate arasındakı əsas fərq nədir?**

Ultimate Spring/JPA/Database/HTTP Client/JS dəstəyi kimi enterprise funksiyaları əhatə edir. Community yalnız core Java, Maven, Gradle, JUnit, Git ilə işləyir. Spring Boot inkişafında Ultimate daha məhsuldardır.

**S2: "Search Everywhere" nə edir və necə çağırılır?**

`Shift+Shift` (Shift-i iki dəfə sürətlə basmaqla). Class, file, action, settings — hər şeyi eyni anda axtarır. IntelliJ-də ən güclü naviqasiya aləti.

**S3: `Alt+Enter`-in rolu nədir?**

Quick fix / intention action menyusunu açır. Kursor altındakı problemə IntelliJ təklif verir: import əlavə et, Optional istifadə et, try-catch əhatə et, null yoxlaması əlavə et, method extract et və s.

**S4: `Shift+F6` nəyə xidmət edir?**

Rename refaktorinqi. Dəyişən, metod, sinif, fayl adını dəyişəndə bütün istifadə yerlərində (referans, JavaDoc, XML, string literal) avtomatik yenilənir.

**S5: Live Template nədir? Nümunə göstərin.**

Abbreviatura + Tab basmaqla böyük kod bloku generasiya edilməsi. Məs., `psvm` + Tab → `public static void main(String[] args) { }`. `sout` → `System.out.println();`.

**S6: Git integration olmadan IntelliJ-də Git-i necə işə salmaq olar?**

IntelliJ daxili VCS dəstəyi var. **VCS** menyusundan commit, push, pull, merge, history. Terminal-a çıxmadan `Alt+9` (Git panel) ilə işləmək olur.

**S7: "Indexing" nədir və niyə uzun çəkir?**

IntelliJ-in bütün layihə fayllarını (və dependencies-i) oxuyub symbol index qurması. Bu index autocomplete, navigation, refactoring-i sürətli edir. Böyük layihədə ilk dəfə açanda 5-30 dəqiqə çəkə bilər, sonra inkremental yenilənir.

**S8: Debug zamanı breakpoint şərtli necə edilir?**

Breakpoint üzərində sağ klik → **Condition** → boolean ifadə (məs., `user.getId() == 42`). Breakpoint yalnız şərt doğru olanda dayanır. Loop-un konkret iterasiyasına çatmaq üçün idealdır.

**S9: `Ctrl+Alt+L` və `Ctrl+Alt+O` fərqi?**

`Ctrl+Alt+L` — **Format Code** — boşluqları, girintiləri, xətt uzunluğunu kod stilinə uyğunlaşdırır. `Ctrl+Alt+O` — **Organize Imports** — istifadə olunmayan import-ları silir, qalanları əlifba sırasına düzür.

**S10: "Run" və "Debug" konfiqurasiyası arasında fərq nədir?**

Eyni konfiqdir, amma "Debug" JVM-ə `-agentlib:jdwp` flag-ı əlavə edir və breakpoint-lərdə JVM-i dayandırır. Performansa kiçik təsir göstərir (adətən debug modunda inlining az olur).

**S11: Çox sayda fayl açıb itirsən — sonuncu fayla necə qayıtmaq olar?**

`Ctrl+E` (Recent Files) — son 50 faylın siyahısı. `Ctrl+Shift+E` (Recent Locations) — son kursor yerləri. Back üçün `Ctrl+Alt+←`.

**S12: Spring Boot tətbiqini IntelliJ-də necə debug etmək olar?**

Main class-ın solundakı debug ikonuna bas, ya da **Shift+F9**. Breakpoint qoyub HTTP sorğu göndər (curl, Postman), IDE sorğu metoduna çatanda dayanacaq. Spring Boot DevTools ilə hot reload və debug birlikdə işləyir.
