# 61 — Debugging Əsasları

> **Seviyye:** Middle ⭐⭐


## Mündəricat
1. [Debugging nədir?](#nədir)
2. [Print vs Debugger](#print-vs-debug)
3. [Breakpoint — növlər](#breakpoint)
4. [Debug rejimində işə salma](#debug-run)
5. [Step-over, Step-into, Step-out](#step)
6. [Variables və Watches](#variables)
7. [Evaluate Expression](#evaluate)
8. [Call Stack və Frame naviqasiyası](#call-stack)
9. [Şərtli Breakpoint və Hit Count](#şərtli)
10. [Exception Breakpoint-ləri](#exception)
11. [Remote Debugging (JDWP)](#remote)
12. [Spring Boot Debug](#spring-debug)
13. [Multi-thread Debug](#multi-thread)
14. [Thread Dump və Heap Dump](#dump)
15. [Ümumi Səhvlər](#səhvlər)
16. [İntervyu Sualları](#intervyu)

---

## 1. Debugging nədir? {#nədir}

**Debugging** — proqramın çalışması zamanı onu addım-addım izləyərək xətalı davranışın səbəbini tapmaq prosesidir. Debugger kodun icrasını müəyyən nöqtədə dayandırır, dəyişənlərin anlıq dəyərini göstərir, addım-addım irəli aparmağa imkan verir.

Debugging bir detektiv işidir: "proqramdan gözlədiyim ilə aldığım fərqli — harada, nə zaman, hansı dəyərlə?" suallarına cavab tapılır.

JVM-də debugging **JDWP (Java Debug Wire Protocol)** ilə işləyir. IDE (IntelliJ, Eclipse, VS Code) bu protokol üzərindən JVM ilə əlaqə qurur.

---

## 2. Print vs Debugger {#print-vs-debug}

Çox proqramçı `System.out.println` ilə xəta tutur. Bu "printf debugging" adlanır. Bəzən düzgün seçimdir, amma bəzən debugger daha güclüdür.

| Ssenari | Print | Debugger |
|---|---|---|
| Tez bir dəyişən yoxlamaq | ✓ sadə | əl ilə kod dəyişmədən |
| Bir neçə yerdə eyni anda | 10+ sətir əlavə et | breakpoint qoy |
| Dəyişənin tarixçəsi | ✓ | ✗ (anlıq göstərir) |
| Şərtli yoxlamaq | `if (...) sout(...)` | conditional breakpoint |
| Production-da (kod dəyişiklik olmadan) | ✗ | remote debug (mümkünsə) |
| Stack trace | `new Exception().printStackTrace()` | avtomatik |
| Loop-da mini iterasiya | spam yaradır | hit count = 50 |
| Çıxarmağı unutmaq | ❌ "TODO: remove println" | dəyişiklik yoxdur |

Qızıl qayda: bir-iki nöqtə üçün print, mürəkkəb daxili state üçün debugger.

---

## 3. Breakpoint — növlər {#breakpoint}

**Breakpoint** — JVM-in dayandırılması üçün kodda işarələnmiş nöqtə.

### 3.1. Line Breakpoint (xətt breakpoint-i)
Ən sadə. Konkret xəttə qoyulur. Kursor o xəttə çatanda JVM dayanır.

IntelliJ-də: xəttin solundakı bölməyə klik edin, qırmızı nöqtə görünəcək. Ya da `Ctrl+F8`.

```java
public int topla(int a, int b) {
    int sum = a + b;      // ← breakpoint buraya qoyuluf
    return sum;
}
```

### 3.2. Method Breakpoint
Metoda daxil oland və ya çıxanda dayanır. Performans baxımından bir az baha, çünki JVM hər metod çağırışı üçün yoxlayır.

IntelliJ-də: metod imzasının solundakı xəttə breakpoint qoy.

### 3.3. Field Breakpoint (Watchpoint)
Field-ə oxunduqda və ya yazıldıqda dayanır. Xüsusilə hansı kodun fieldi dəyişdiyini axtaranda faydalıdır.

### 3.4. Exception Breakpoint
Müəyyən `Throwable` atıldıqda dayanır. `NullPointerException`-un mənbəyini tapmaq üçün idealdır.

### 3.5. Conditional Breakpoint
Breakpoint yalnız verilmiş şərt doğru olanda dayandırır. Məs., `user.getId() == 42`.

### 3.6. Log Breakpoint
Breakpoint kimi dayandırmır, amma mesaj logger-ə yazır. Production-a deploy etmədən "printf-vari" log qoymaq üçün.

IntelliJ-də: breakpoint üzərində sağ klik → **Suspend** söndür, **Log "..."** yaz.

---

## 4. Debug rejimində işə salma {#debug-run}

IntelliJ-də **Debug** düyməsi (böcək ikonu) ilə tətbiq JDWP flag-ları ilə başlayır:

```
-agentlib:jdwp=transport=dt_socket,server=y,suspend=n,address=*:5005
```

Qısayol: `Shift+F9` (son konfiqi debug-da işə sal).

Normal `Run` ilə fərq JVM-in JDWP port-unu aşacaq, IDE bağlantı quracaq və breakpoint-lərə hörmət edəcək.

---

## 5. Step-over, Step-into, Step-out {#step}

Breakpoint-ə çatandan sonra hərəkət etmək üçün əsas əmrlər:

| Əmr | Qısayol | Təsvir |
|---|---|---|
| **Resume** | `F9` | Növbəti breakpoint-ə qədər davam et |
| **Step Over** | `F8` | Növbəti xəttə keç (metod içinə girməsin) |
| **Step Into** | `F7` | Metod çağırışının içinə gir |
| **Step Into My Code** | `Alt+Shift+F7` | Yalnız sənin kodunda (library-ni atla) |
| **Step Out** | `Shift+F8` | Cari metoddan çıxana qədər davam et |
| **Run to Cursor** | `Alt+F9` | Kursor olduğu yerə qədər işlə (breakpoint kimi) |
| **Drop Frame** | menyudan | Cari metoddan geri qayıt (!) |

### Nümunə:

```java
public void process(List<String> items) {
    for (String item : items) {        // ← breakpoint
        String result = transform(item); // ← F7 burada girər, F8 atlayar
        save(result);
    }
}
```

- `F8` (Step Over) — `transform` və `save` işləsin, amma içinə girmə
- `F7` (Step Into) — `transform` içinə gir
- `Shift+F8` (Step Out) — cari metoddan çıxana qədər işlə
- `F9` (Resume) — növbəti breakpoint-ə qədər davam et

### Drop Frame (güclü funksiya):

Cari metoddan geri qayıtmağa imkan verir, sanki metod heç çağırılmayıb. Metodun əvvəlindən yenidən başlamaq üçün istifadə olunur. Amma diqqət: metod state dəyişdiribsə (DB, fayl, global obyekt), onlar geri qayıtmır!

---

## 6. Variables və Watches {#variables}

Debug zamanı aşağıdakı pəncərə açılır:

```
┌─────────────────── Debug ──────────────────────┐
│ Frames              │ Variables                  │
│ main:42             │ ├─ this = MyService@1a2b   │
│ process:17          │ ├─ items = ArrayList(3)    │
│ transform:25 ←      │ ├─ item = "hello"          │
│                     │ └─ result = null           │
└─────────────────────────────────────────────────┘
```

### Variables:
Cari scope-dakı bütün dəyişənlər (method parameters, local variables, `this.field`). Obyektlər açılıb içi görünür.

### Watches:
Daim izlənməsi lazım gələn ifadələri əlavə edə bilərsən. Məs., `items.size()`, `user.getBalance()`. Hər addımda yenidən hesablanır.

Əlavə: **+** düyməsi ilə `items.stream().filter(x -> x.isBlank()).count()` kimi lambda ilə mürəkkəb ifadə də əlavə edə bilərsən.

### Dəyəri dəyişmək:

Debug zamanı dəyişənə sağ klik → **Set Value** → yeni dəyər. Məs., `user.setAdmin(true)` etmədən test hallarını yoxlamaq olar.

---

## 7. Evaluate Expression {#evaluate}

`Alt+F8` — anlıq ifadə qiymətləndirmə pəncərəsi. İstənilən Java ifadəsini yaza və nəticəni görə bilərsən.

```java
// Debug-da dayanmışsansa, evaluate edə bilərsən:
items.stream().filter(x -> x.startsWith("a")).toList()
user.getOrders().stream().mapToDouble(Order::total).sum()
LocalDateTime.now().plusDays(7)
```

Bu production code-u dəyişmədən hipotezləri test etmək üçün əladır.

---

## 8. Call Stack və Frame naviqasiyası {#call-stack}

**Frames** paneli — cari thread-in metod çağırışları stack-i.

```
Frames:
  parseDate:89       ← cari (ən üstdə)
  processRequest:145
  handleRequest:67
  doGet:32
  Thread.run:834
```

Hər frame-ə klik etsən, həmin metod içindəki local dəyişənləri görə bilərsən. Bu "bu metoda necə çatdım?" sualının cavabıdır.

Hər bir frame ayrı-ayrı variable scope-a sahibdir.

---

## 9. Şərtli Breakpoint və Hit Count {#şərtli}

### Conditional Breakpoint:

Breakpoint üzərində sağ klik → **Condition** → boolean ifadə:

```java
user.getId() == 42
items.size() > 1000
!email.contains("@")
```

Loop içində 1000 iterasiyanın 500-cüsündə problem varsa, bu qırmızı breakpoint olmadan mümkündür:

```java
for (int i = 0; i < items.size(); i++) {
    process(items.get(i));   // breakpoint şərt: i == 500
}
```

### Hit Count:

Breakpoint üzərində sağ klik → **More** → **Pass count**.

| Option | Təsvir |
|---|---|
| Pass count = 10 | Yalnız 10-cu dəfə dayandır |
| Instance filters | Konkret obyekt ID-sində dayan |

Performans üçün: şərtli breakpoint-lər hər dəfə ifadəni hesablayır, ona görə "isti" kodda yavaşlatma hiss oluna bilər.

---

## 10. Exception Breakpoint-ləri {#exception}

**Run** → **View Breakpoints** (`Ctrl+Shift+F8`) → `+` → **Java Exception Breakpoint** → `NullPointerException` (və ya başqa sinif).

İndi hər dəfə NPE atılanda JVM dayanır — `catch`-ə çatmadan əvvəl! Bu səbəbləri və kontekstini görmək üçün əladır.

### Caught vs Uncaught:

- **Caught exception** — blok içində `catch` ilə tutulur
- **Uncaught exception** — yuxarıya atılır, proqram crash olur

Çox vaxt yalnız **Uncaught** aktiv edilir ki, kitabxana-daxili tutulan NPE-lər səni yormasın.

---

## 11. Remote Debugging (JDWP) {#remote}

Tətbiq uzaq serverdə (məs., Docker, Kubernetes, VM) işləyir, sən lokalda debug etmək istəyirsən.

### Serverdə JVM-i JDWP ilə başlat:

```bash
java -agentlib:jdwp=transport=dt_socket,server=y,suspend=n,address=*:5005 -jar myapp.jar
```

| Parametr | Məna |
|---|---|
| `transport=dt_socket` | TCP socket istifadə et |
| `server=y` | JVM debugger-dən gözləsin |
| `suspend=n` | Debugger-siz də başlasın |
| `suspend=y` | Debugger qoşulana qədər dayansın |
| `address=*:5005` | Bütün interfeys-lərdə 5005 port-u |

### IntelliJ-də qoşulma:

1. **Run** → **Edit Configurations...** → `+` → **Remote JVM Debug**
2. Host: `localhost` (və ya server IP)
3. Port: `5005`
4. **Debug** düyməsi — breakpoint qoy və server-də kod işlət

### Docker-də JDWP:

```bash
# Dockerfile və ya command:
docker run -p 8080:8080 -p 5005:5005 \
  -e JAVA_TOOL_OPTIONS="-agentlib:jdwp=transport=dt_socket,server=y,suspend=n,address=*:5005" \
  myapp:latest
```

### Kubernetes-də:

```bash
kubectl port-forward pod/myapp-abc 5005:5005
```

Sonra `localhost:5005`-ə qoşul.

---

## 12. Spring Boot Debug {#spring-debug}

### Məktəb üsulu:

```bash
mvn spring-boot:run -Dspring-boot.run.jvmArguments="-Xdebug -Xrunjdwp:transport=dt_socket,server=y,suspend=n,address=5005"
```

Ya da birbaşa IntelliJ-də Debug düyməsi.

### DevTools ilə Hot Reload:

```xml
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-devtools</artifactId>
    <optional>true</optional>
</dependency>
```

Kod dəyişikliyi saxlananda, DevTools ClassLoader-i yenidən yaradır. Bu debug session-ı qoruyur — breakpoint-lər yerində qalır.

### `application.properties` debug log-u:

```properties
logging.level.org.springframework=DEBUG
logging.level.org.hibernate.SQL=DEBUG
logging.level.org.hibernate.type.descriptor.sql=TRACE
debug=true
```

Auto-configuration report-u da görünür.

---

## 13. Multi-thread Debug {#multi-thread}

Çoxsaplı tətbiqdə breakpoint bütün thread-ləri dayandırır. Bu bəzən arzuolunmazdır.

### Thread suspending policy:

Breakpoint üzərində sağ klik → **Suspend**:
- **All** — bütün thread-ləri dayandır (default)
- **Thread** — yalnız bu thread-i dayandır

Bu concurrency problemlərini modelləşdirmək üçün faydalıdır — bir thread-i "dondurub" digər thread-in reaksiyasını görmək.

### Thread-lər pəncərəsi:

Debug panelində **Threads** tabı — bütün aktiv thread-lərin siyahısı. Hər birinin call stack-ı ayrıca görünür.

Deadlock axtarmaq: thread-lərdə gözləmə ("waiting for monitor") vəziyyətlərini izlə.

---

## 14. Thread Dump və Heap Dump {#dump}

Debugger olmadan prosesin vəziyyətini görmək üçün JDK alətləri.

### Thread Dump (`jstack`):

```bash
# PID tap
jps

# Thread dump çıxar
jstack <pid>
```

Çıxış:
```
"http-nio-8080-exec-1" #21 daemon prio=5 os_prio=0 cpu=45ms
   java.lang.Thread.State: WAITING (on object monitor)
    at java.lang.Object.wait(Native Method)
    at java.lang.Object.wait(Object.java:502)
    at com.example.MyService.process(MyService.java:42)
    ...
```

Deadlock tapmaq üçün idealdır.

### Heap Dump (`jmap`):

```bash
jmap -dump:format=b,file=heap.hprof <pid>
```

`heap.hprof` sonra **Eclipse MAT**, **VisualVM** və ya IntelliJ Ultimate Profiler ilə açıla bilər. Memory leak axtarmaq üçün:
- Hansı sinifdən ən çox obyekt var?
- O obyektlər harada saxlanılır (GC root path)?

### Online JVM izləmə — JVisualVM:

```bash
jvisualvm
```

Real-time heap, CPU, thread, GC qrafikləri. Monitoring + profiling + sampling.

---

## 15. Ümumi Səhvlər {#səhvlər}

### 1. Debug çox yavaşdır
Çox şərtli breakpoint və watchpoint-lər. Çıxarış: istifadə olunmayanları sil.

### 2. Breakpoint boz rəngdədir və işləmir
Kod debug rejimində yığılmayıb ya da ClassLoader bu sinifi yükləməyib.
**Həll:** `mvn clean compile`, sonra yenidən debug başlat.

### 3. Dəyişənin dəyəri "collecting data..."-dır
Metod çox mürəkkəb obyekt qaytarır (JPA Lazy proxy kimi). Dayandırılır.
**Həll:** Sağ klik → **Evaluate lazy value** və ya manually expand.

### 4. `this` ilə remote debugging "source not available"
Server-də yığılmış class-ların source-u IntelliJ-ə uyğun deyil.
**Həll:** Aynı commit-dən build et, classpath-də source JAR-ları verin.

### 5. Production-da debug port açıq qalmaq
`address=*:5005` flag-ı prod-da saxlamaq təhlükəlidir — kod icra etmə imkanı verir.
**Həll:** Prod-da JDWP söndür, yalnız debug lazım olanda aç.

### 6. Step Into library kodunda itmək
Java standart kitabxanasının Java kodunda (`ArrayList.iterator()` kimi) çox addım atırsan.
**Həll:** `Alt+Shift+F7` (Step Into My Code) — yalnız öz paketinə girir. Ya da **Settings** → **Build** → **Debugger** → **Stepping** → "Do not step into classes"-ə `java.*`, `org.springframework.*` əlavə et.

### 7. NPE breakpoint hər yerdə dayanır
Spring və JPA-da daxili NPE-lər var ki, tutulur.
**Həll:** Exception breakpoint-də "caught" söndür, yalnız "uncaught" saxla.

### 8. DevTools restart vaxtı breakpoint itir
Hər restart-dan sonra ClassLoader dəyişir.
**Həll:** DevTools `spring.devtools.restart.enabled=false` edib əl ilə yenidən başlat.

### 9. Remote debug server-ə qoşulmur
Firewall 5005 portu bloklayır ya da `server=y` yoxdur.
**Həll:** Port açıq olmalı, JDWP flag-ları düzgün olmalı.

### 10. Drop Frame DB dəyişikliyini geri qaytarmır
Drop Frame yalnız JVM stack-ını geri qaytarır. DB insert/update, fayl yazımı, HTTP call-lar geri qayıtmır.

---

## 16. İntervyu Sualları {#intervyu}

**S1: Debugger və `System.out.println` arasında fərq nədir?**

Print — kod dəyişilir, hər dəyişən üçün xətt əlavə edilir, unutma riski var. Debugger kod dəyişmədən dayandırır, bütün local state-ə giriş verir, şərtli və dinamik ifadə qiymətləndirməyə icazə verir. Mürəkkəb debug üçün debugger, tez log üçün print.

**S2: JDWP nədir?**

**Java Debug Wire Protocol** — JVM ilə debugger (IDE) arasında əlaqə protokolu. TCP üzərindən işləyir. IDE bu protokol ilə breakpoint yerləşdirir, dəyişənləri oxuyur, step-by-step icrasına nəzarət edir.

**S3: Step Into və Step Over fərqi?**

Step Into (`F7`) metod çağırışının içinə girir. Step Over (`F8`) metodu işlədir amma içinə girməyib növbəti xəttə keçir. Metod detallarını görmək lazım deyilsə — Step Over.

**S4: Conditional Breakpoint necə yerləşdirilir?**

Breakpoint üzərində sağ klik → **Condition** → boolean ifadə. Breakpoint yalnız şərt doğru olanda dayanır. Loop-da konkret iterasiyaya çatmaq və ya nadir şərt tapmaq üçün.

**S5: Remote debug üçün JVM-i necə konfiqurasiya etmək olar?**

JVM-i `-agentlib:jdwp=transport=dt_socket,server=y,suspend=n,address=*:5005` flag-ı ilə başlat. IDE-də "Remote JVM Debug" konfiqurasiya, host + port göstər, Debug düyməsi bas.

**S6: Production-da remote debug təhlükəlidirmi?**

Bəli. Debug portuna qoşulan şəxs istənilən Java ifadəsini icra edə bilər — məlumat oğurlaya, sistem dəyişdirə bilər. Prod-da ya bağlı saxla, ya da məhdud IP/firewall ilə qoru və TLS-li tunnel (SSH) istifadə et.

**S7: Exception Breakpoint nə vaxt lazımdır?**

Bir NPE atılır amma harada və hansı kontekst ilə olduğu aydın deyil. Exception Breakpoint `NullPointerException`-ə qoyulsa, JVM atılma anında dayanır və sən stack və local state-i görürsən — `catch`-ə çatmazdan əvvəl.

**S8: Thread Dump nə üçün götürülür?**

Deadlock, yüksək CPU, asılmış thread-ləri diaqnoz etmək üçün. Hansı thread nə gözləyir, hansı metodda ilişib — hər şey stack trace-də görünür. `jstack <pid>` və ya `kill -3 <pid>` ilə əldə edilir.

**S9: Heap Dump nədir və nə zaman götürülür?**

Heap-in anlıq snapshot-ı. Memory leak axtarmaq üçün — hansı obyektlər yaddaşı tutur, onlar hansı kök referansdan saxlanılır. `jmap -dump:format=b,file=heap.hprof <pid>`, sonra **Eclipse MAT** ilə analiz.

**S10: Drop Frame nə üçün faydalıdır və nə vaxt təhlükəli?**

Metodun icrasını əvvəlinə qaytarır — metod sanki heç çağırılmayıb. Faydalı: "bu metoda necə başlayırdı?" yoxlamaq. Təhlükəli: DB insert, fayl yazımı, HTTP call-ları geri qayıtmır — yalnız stack state.

**S11: "Evaluate Expression" nə üçün istifadə olunur?**

Debug pauzasında anlıq Java ifadəsi yazıb nəticəni görmək. Production code-u dəyişmədən hipotezləri test etmək: `items.stream().filter(...).count()`, `userService.findByEmail(...)`.

**S12: Multi-thread tətbiqdə breakpoint bütün thread-ləri dayandırır — bunu dəyişmək olar?**

Bəli. Breakpoint üzərində sağ klik → **Suspend** → **Thread**. İndi yalnız bu thread dayanır, digərləri davam edir. Race condition modelləşdirmək üçün əla üsuldur.
