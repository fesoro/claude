# 74 — JVM ClassLoading

> **Seviyye:** Senior ⭐⭐⭐


## Mündəricat
1. [ClassLoader nədir?](#classloader-nədir)
2. [ClassLoader İerarxiyası](#classloader-i̇erarxiyası)
3. [Parent Delegation Modeli](#parent-delegation-modeli)
4. [Sinif Yükləmə Mərhələləri](#sinif-yükləmə-mərhələləri)
5. [loadClass() Alqoritmi](#loadclass-alqoritmi)
6. [Custom ClassLoader](#custom-classloader)
7. [URLClassLoader](#urlclassloader)
8. [Hot Reload](#hot-reload)
9. [OSGi](#osgi)
10. [İntervyu Sualları](#i̇ntervyu-sualları)

---

## ClassLoader nədir?

**ClassLoader** — `.class` fayllarını JVM-ə yükləyən komponentdir. Java proqramı işə düşdükdə bütün sinifler eyni anda yüklənmir — **tənbəl yükləmə (lazy loading)** tətbiq olunur: sinif ilk dəfə istifadə edildikdə yüklənir.

```java
public class ClassLoadingDemo {
    public static void main(String[] args) {
        // Bu sətirdə String sinfi artıq yüklənib (Bootstrap tərəfindən)
        System.out.println("Başlayırıq...");

        // ClassLoadingDemo sinfi yükləndi
        // Amma MyService sinfi hələ yüklənməyib!

        // MyService ilk dəfə istifadə edildikdə yüklənir
        MyService service = new MyService(); // Burada yüklənir
        service.doWork();
    }
}

class MyService {
    static {
        // Bu blok sinif yükləndikdə bir dəfə icra olunur
        System.out.println("MyService sinfi yükləndi!");
    }

    public void doWork() {
        System.out.println("İş görülür...");
    }
}
```

```java
// Sinfin yüklənib-yüklənmədiyini yoxlamaq
public class CheckLoading {
    public static void main(String[] args) throws ClassNotFoundException {
        // Class.forName() — sinfi yükləyir və Class obyekti qaytarır
        // ikinci parametr false — initialize etmə (static blokları icra etmə)
        Class<?> clazz = Class.forName("java.util.ArrayList", false,
            ClassLoader.getSystemClassLoader());

        System.out.println("Sinif: " + clazz.getName());
        System.out.println("ClassLoader: " + clazz.getClassLoader());
        // null — Bootstrap ClassLoader (ArrayList-ı yükləyir)
    }
}
```

---

## ClassLoader İerarxiyası

```
Bootstrap ClassLoader
│  • JVM-in özü içindədir (C++ kodu)
│  • Java-da null kimi görünür
│  • Yüklədiyi yerlər: $JAVA_HOME/lib/ (rt.jar, java.base modulu)
│  • Siniflər: java.lang.*, java.util.*, java.io.* ...
│
├── Platform ClassLoader (Java 9+) / Extension ClassLoader (Java 8)
│   • Java 8: $JAVA_HOME/lib/ext/ qovluğu
│   • Java 9+: Platform modullları (java.sql, java.xml, ...)
│   • sun.misc.Launcher$ExtClassLoader
│
└── Application ClassLoader (System ClassLoader)
    • Classpath-dəki siniflər (-cp, CLASSPATH env var)
    • İstifadəçinin yazdığı siniflər
    • sun.misc.Launcher$AppClassLoader
    │
    └── Custom ClassLoader (istifadəçi tərəfindən)
        • Application ClassLoader-dən extend edilir
        • Xüsusi yükləmə məntiqi
```

```java
public class ClassLoaderHierarchy {
    public static void main(String[] args) {
        // Bootstrap ClassLoader — null qayıdır
        ClassLoader bootstrapLoader = String.class.getClassLoader();
        System.out.println("String loader: " + bootstrapLoader); // null

        // Platform ClassLoader
        ClassLoader platformLoader = java.sql.Connection.class.getClassLoader();
        System.out.println("Connection loader: " + platformLoader);

        // Application ClassLoader
        ClassLoader appLoader = ClassLoaderHierarchy.class.getClassLoader();
        System.out.println("Bu sinifin loader-i: " + appLoader);

        // İerarxiyani izləmək
        System.out.println("\n=== ClassLoader Zənciri ===");
        ClassLoader current = appLoader;
        while (current != null) {
            System.out.println(current);
            current = current.getParent();
        }
        System.out.println("null (Bootstrap)");
    }
}
```

---

## Parent Delegation Modeli

**Parent Delegation Model** — ClassLoader sinfi yükləməzdən əvvəl öz parent-inə ötürür. Yalnız parent yükləyə bilmədikdə özü cəhd edir.

```
Sinif yüklə tələbi → Application CL
                          ↓ parent-ə ötür
                    Platform CL
                          ↓ parent-ə ötür
                    Bootstrap CL
                          ↓ yükləyə bilir?
                     Bəli → Yüklə
                     Xeyr ↑ Application CL-ə qaytar
                    Platform CL-ə qaytar
                          ↓ yükləyə bilir?
                     Bəli → Yüklə
                     Xeyr ↑ Application CL-ə qaytar
                    Application CL yükləyir
                     ClassNotFoundException atır əgər tapa bilməsə
```

### Niyə bu model?

1. **Təhlükəsizlik**: İstifadəçi `java.lang.String` adlı zərərli sinif yarada bilməz — Bootstrap həmişə əvvəl yoxlayır
2. **Konsistentlik**: Eyni sinif bir dəfə yüklənir, təkrar yüklənmə olmur
3. **Görünürlük**: Alt ClassLoader yuxarı ClassLoader-in siniflerini görür, əksi isə yox

```java
// Parent delegation modelini göstərən nümunə
public class DelegationDemo {
    public static void main(String[] args) throws ClassNotFoundException {
        ClassLoader appClassLoader = ClassLoader.getSystemClassLoader();

        // "java.lang.String"-i yükləməyə çalış
        // Application CL → Platform CL → Bootstrap CL (tapır və yükləyir)
        Class<?> stringClass = appClassLoader.loadClass("java.lang.String");
        System.out.println("String loader: " + stringClass.getClassLoader());
        // null çıxır — Bootstrap yüklədi

        // Öz sinifimizi yükləmək
        Class<?> myClass = appClassLoader.loadClass("DelegationDemo");
        System.out.println("MyClass loader: " + myClass.getClassLoader());
        // AppClassLoader çıxır — Bootstrap və Platform tapa bilmədi
    }
}
```

---

## Sinif Yükləmə Mərhələləri

```
Loading → Linking → Initialization
           ↓
   Verification → Preparation → Resolution
```

### 1. Loading (Yükləmə)
- `.class` faylını tapır (classpath, JAR, network ...)
- Binary formatı oxuyur
- `Class` obyekti yaradır (Metaspace-də)

### 2. Linking (Bağlama)

**Verification (Yoxlama):**
- Bytecode-un düzgün formatda olduğunu yoxlayır
- Tip uyğunluğunu yoxlayır
- Stack overflow olmayacağını yoxlayır
- Zərərli bytecode-u aşkarlar

**Preparation (Hazırlıq):**
- Statik sahələr üçün yaddaş ayrılır
- Default dəyərlər verilir (0, null, false ...)
- Hələ kod icra olunmur

**Resolution (Həll etmə):**
- Simvolik referanslar real referanslara çevrilir
- `java/lang/String` → actual Class pointer

### 3. Initialization (İlkinləşdirmə)
- Static variable initializer-lar icra olunur
- Static bloklar (`static { ... }`) icra olunur
- Yalnız bir dəfə baş verir (thread-safe)

```java
public class InitializationOrder {

    static int a = 10; // Preparation: a = 0, Initialization: a = 10

    static {
        System.out.println("Statik blok 1: a = " + a); // a = 10
        b = 20; // b hənuz elan olunmayıb amma yazıla bilir!
    }

    static int b = 30; // Bu initialization zamanı b = 30 olacaq

    static {
        System.out.println("Statik blok 2: b = " + b); // b = 30
    }

    public static void main(String[] args) {
        System.out.println("main: a=" + a + ", b=" + b);
        // Çıxış:
        // Statik blok 1: a = 10
        // Statik blok 2: b = 30
        // main: a=10, b=30
    }
}
```

---

## loadClass() Alqoritmi

```java
// ClassLoader-in əsas metodu — parent delegation implementasiyası
public class ClassLoader {

    public Class<?> loadClass(String name) throws ClassNotFoundException {
        return loadClass(name, false);
    }

    protected Class<?> loadClass(String name, boolean resolve)
            throws ClassNotFoundException {

        // 1. Artıq yüklənibsə cache-dən qaytar
        Class<?> c = findLoadedClass(name);

        if (c == null) {
            try {
                // 2. Parent-ə ötür (delegation)
                if (parent != null) {
                    c = parent.loadClass(name, false);
                } else {
                    // 3. Parent null-dırsa Bootstrap-ə ötür
                    c = findBootstrapClassOrNull(name);
                }
            } catch (ClassNotFoundException e) {
                // Parent tapa bilmədi — özümüz cəhd edirik
            }

            if (c == null) {
                // 4. Özümüz axtarırıq
                c = findClass(name);
            }
        }

        // 5. Lazım gəldikdə resolve et (nadir hallarda)
        if (resolve) {
            resolveClass(c);
        }

        return c;
    }

    // Alt sinifler bu metodu override etməlidir
    protected Class<?> findClass(String name) throws ClassNotFoundException {
        throw new ClassNotFoundException(name);
    }
}
```

---

## Custom ClassLoader

```java
import java.io.*;
import java.nio.file.*;

public class CustomClassLoader extends ClassLoader {

    private final Path classDirectory;

    public CustomClassLoader(Path classDirectory, ClassLoader parent) {
        super(parent); // Parent delegation üçün
        this.classDirectory = classDirectory;
    }

    @Override
    protected Class<?> findClass(String name) throws ClassNotFoundException {
        // Sinif adını fayl yoluna çevir
        // "com.example.MyClass" → "com/example/MyClass.class"
        String fileName = name.replace('.', '/') + ".class";
        Path classFile = classDirectory.resolve(fileName);

        if (!Files.exists(classFile)) {
            throw new ClassNotFoundException("Sinif tapılmadı: " + name);
        }

        try {
            // .class faylını oxu
            byte[] classBytes = Files.readAllBytes(classFile);

            // Bytecode-u şifrələmə/dəyişdirmə burada edilə bilər
            // Məsələn: AES şifrəsini açmaq

            // JVM-ə yüklə
            return defineClass(name, classBytes, 0, classBytes.length);

        } catch (IOException e) {
            throw new ClassNotFoundException("Sinif oxuna bilmədi: " + name, e);
        }
    }

    // İstifadə nümunəsi
    public static void main(String[] args) throws Exception {
        Path classDir = Path.of("/tmp/classes");

        // Parent olaraq sistem ClassLoader-i istifadə et
        CustomClassLoader loader = new CustomClassLoader(
            classDir,
            ClassLoader.getSystemClassLoader()
        );

        // Sinfi yüklə
        Class<?> clazz = loader.loadClass("com.example.MyPlugin");

        // Instance yarat
        Object instance = clazz.getDeclaredConstructor().newInstance();

        // Metod çağır
        clazz.getMethod("execute").invoke(instance);
    }
}
```

### Şifrəli Sinifləri Yükləmək

```java
import javax.crypto.*;
import javax.crypto.spec.*;
import java.security.*;

public class EncryptedClassLoader extends ClassLoader {

    private final SecretKey secretKey;
    private final Path encryptedDir;

    public EncryptedClassLoader(Path dir, SecretKey key, ClassLoader parent) {
        super(parent);
        this.encryptedDir = dir;
        this.secretKey = key;
    }

    @Override
    protected Class<?> findClass(String name) throws ClassNotFoundException {
        String fileName = name.replace('.', '/') + ".class.enc";
        Path file = encryptedDir.resolve(fileName);

        try {
            byte[] encryptedBytes = java.nio.file.Files.readAllBytes(file);

            // AES şifrəsini aç
            Cipher cipher = Cipher.getInstance("AES");
            cipher.init(Cipher.DECRYPT_MODE, secretKey);
            byte[] classBytes = cipher.doFinal(encryptedBytes);

            return defineClass(name, classBytes, 0, classBytes.length);

        } catch (Exception e) {
            throw new ClassNotFoundException("Şifrəli sinif yüklənə bilmədi: " + name, e);
        }
    }
}
```

---

## URLClassLoader

`URLClassLoader` — URL-lərdən (fayl, JAR, network) sinif yükləyən hazır ClassLoader implementasiyasıdır.

```java
import java.net.*;
import java.nio.file.*;

public class URLClassLoaderDemo {

    public static void main(String[] args) throws Exception {

        // JAR faylından sinif yükləmək
        URL jarUrl = Path.of("/tmp/plugins/myplugin.jar").toUri().toURL();

        try (URLClassLoader loader = new URLClassLoader(
                new URL[]{jarUrl},
                ClassLoader.getSystemClassLoader())) {

            // Plugin interface-ini yüklə
            Class<?> pluginClass = loader.loadClass("com.example.MyPlugin");

            // Plugin interface-inə cast et
            // (PluginInterface eyni ClassLoader tərəfindən yüklənmiş olmalıdır)
            // Object instance = pluginClass.getDeclaredConstructor().newInstance();

            System.out.println("Plugin yükləndi: " + pluginClass.getName());

        } // URLClassLoader.close() — JAR faylını buraxır (try-with-resources)

        // Müxtəlif mənbələrdən yükləmək
        URL[] urls = {
            new URL("file:///tmp/classes/"),  // Qovluqdan
            new URL("jar:file:///tmp/lib.jar!/"),  // JAR-dan
            new URL("http://localhost:8080/classes/"),  // HTTP-dən (nadir)
        };

        URLClassLoader multiLoader = new URLClassLoader(urls);
        System.out.println("Multi-source loader: " + multiLoader);
    }
}
```

---

## Hot Reload

**Hot Reload** — proqram işləyərkən siniflərin yenidən yüklənməsi.

JVM standart olaraq bir sinfi eyni ClassLoader ilə iki dəfə yükləmir. Hot reload üçün **yeni ClassLoader instance-i** lazımdır.

```java
import java.io.*;
import java.net.*;
import java.nio.file.*;

public class HotReloadExample {

    // Plugin interface-i
    interface Plugin {
        String execute();
    }

    // Hot-reload məntiqi
    static class PluginManager {
        private Plugin currentPlugin;
        private Path pluginJar;
        private long lastModified = 0;

        public PluginManager(Path jar) {
            this.pluginJar = jar;
        }

        public Plugin getPlugin() throws Exception {
            long modified = Files.getLastModifiedTime(pluginJar).toMillis();

            // Fayl dəyişibsə yenidən yüklə
            if (modified > lastModified) {
                System.out.println("Plugin yenilənir...");
                reload();
                lastModified = modified;
            }

            return currentPlugin;
        }

        private void reload() throws Exception {
            // YENİ ClassLoader instance-i yarat
            // Köhnə ClassLoader-i atmaq GC-yə buraxır (sinifler Metaspace-dən silinir)
            URLClassLoader loader = new URLClassLoader(
                new URL[]{pluginJar.toUri().toURL()},
                getClass().getClassLoader() // Parent: Application CL
            );

            Class<?> pluginClass = loader.loadClass("com.example.PluginImpl");
            currentPlugin = (Plugin) pluginClass.getDeclaredConstructor().newInstance();
        }
    }

    public static void main(String[] args) throws Exception {
        PluginManager manager = new PluginManager(Path.of("/tmp/plugin.jar"));

        // Hər 5 saniyədə bir yoxla
        while (true) {
            Plugin plugin = manager.getPlugin();
            if (plugin != null) {
                System.out.println("Plugin nəticəsi: " + plugin.execute());
            }
            Thread.sleep(5000);
        }
    }
}
```

### Java Agent ilə Hot Reload

```java
// Java Agent — Instrumentation API ilə sinfi yenidən müəyyən etmək
import java.lang.instrument.*;
import java.security.ProtectionDomain;

public class HotReloadAgent implements ClassFileTransformer {

    @Override
    public byte[] transform(ClassLoader loader, String className,
            Class<?> classBeingRedefined, ProtectionDomain domain,
            byte[] classfileBuffer) {

        // Bytecode-u dəyişdirmək mümkündür
        // Məsələn: logging əlavə etmək, profiling ...
        if (className.contains("MyService")) {
            System.out.println("MyService transformasiya olunur");
            // Dəyişdirilmiş bytecode qaytar
        }

        return classfileBuffer; // Dəyişdirilməmiş halla qaytar
    }

    // premain — agent JVM başlamazdan əvvəl işə düşür
    public static void premain(String agentArgs, Instrumentation inst) {
        inst.addTransformer(new HotReloadAgent(), true);
        System.out.println("Hot reload agent başladı");
    }
}
```

---

## OSGi

**OSGi (Open Services Gateway Initiative)** — dinamik modul sistemi. Hər modul (bundle) öz ClassLoader-inə malikdir.

```
OSGi Framework
├── Bundle A (ClassLoader A)
│   └── com.example.serviceA.*
├── Bundle B (ClassLoader B)
│   └── com.example.serviceB.*
└── Bundle C (ClassLoader C)
    └── com.example.serviceC.*
    └── İstifadə edir: Bundle A-nın export etdiyi paketlər
```

```java
// OSGi Bundle manifest nümunəsi (MANIFEST.MF):
/*
Bundle-SymbolicName: com.example.mybundle
Bundle-Version: 1.0.0
Import-Package: com.example.service;version="[1.0,2.0)"
Export-Package: com.example.mybundle;version="1.0.0"
*/

// OSGi BundleActivator
import org.osgi.framework.*;

public class MyBundleActivator implements BundleActivator {

    @Override
    public void start(BundleContext context) throws Exception {
        System.out.println("Bundle başladı: " + context.getBundle().getSymbolicName());
        // Servisləri qeydiyyata almaq
        context.registerService(MyService.class, new MyServiceImpl(), null);
    }

    @Override
    public void stop(BundleContext context) throws Exception {
        System.out.println("Bundle dayandırıldı");
        // Resursları burax
    }
}
```

---

## ClassLoader-ə Aid Gizli Problemlər

### ClassCastException (fərqli ClassLoader-lər)

```java
// YANLIŞ YANAŞMA — bu ClassCastException verə bilər!
public void badExample() throws Exception {
    URLClassLoader loader1 = new URLClassLoader(new URL[]{new URL("file:///lib.jar")});
    URLClassLoader loader2 = new URLClassLoader(new URL[]{new URL("file:///lib.jar")});

    // Eyni sinif adı, amma fərqli ClassLoader — bunlar FƏRQLI siniflərdir!
    Class<?> class1 = loader1.loadClass("com.example.MyClass");
    Class<?> class2 = loader2.loadClass("com.example.MyClass");

    System.out.println(class1 == class2); // false!
    System.out.println(class1.equals(class2)); // false!

    Object obj1 = class1.getDeclaredConstructor().newInstance();
    // ClassCastException! Eyni ad, amma fərqli Class identifikatorları
    // com.example.MyClass obj = (com.example.MyClass) obj1;
}

// DOĞRU — eyni ClassLoader istifadə et
public void goodExample() throws Exception {
    URLClassLoader sharedLoader = new URLClassLoader(new URL[]{new URL("file:///lib.jar")});

    Class<?> myClass = sharedLoader.loadClass("com.example.MyClass");
    Object obj1 = myClass.getDeclaredConstructor().newInstance();
    Object obj2 = myClass.getDeclaredConstructor().newInstance();

    // Eyni ClassLoader — eyni sinif
    System.out.println(obj1.getClass() == obj2.getClass()); // true
}
```

### Thread Context ClassLoader

```java
// Framework-lərdə tez-tez istifadə olunan pattern
public class ThreadContextClassLoaderDemo {
    public static void main(String[] args) throws Exception {
        Thread thread = Thread.currentThread();

        // Cari context ClassLoader-i al
        ClassLoader contextCL = thread.getContextClassLoader();
        System.out.println("Context CL: " + contextCL);

        // Müvəqqəti dəyişdirmək
        ClassLoader customLoader = new URLClassLoader(
            new URL[]{new URL("file:///tmp/custom.jar")});

        thread.setContextClassLoader(customLoader);
        try {
            // Burada işləyən kod custom ClassLoader-dən istifadə edəcək
            // ServiceLoader, JNDI, JDBC driver-lar context CL istifadə edir
            Class.forName("com.example.MyDriver",
                true, Thread.currentThread().getContextClassLoader());
        } finally {
            // Köhnə loader-i bərpa et
            thread.setContextClassLoader(contextCL);
        }
    }
}
```

---

## İntervyu Sualları

**S: Parent delegation modeli nədir və niyə istifadə olunur?**
C: ClassLoader sinfi yükləməzdən əvvəl parent-inə ötürür. Yalnız parent uğursuz olduqda özü axtarır. Bu: 1) Təhlükəsizliyi təmin edir (java.lang.String-i override etmək olmaz), 2) Sinifin bir dəfə yüklənməsini təmin edir, 3) Görünürlük qaydalarını tənzimləyir.

**S: Sinif yükləmənin 3 mərhələsi hansılardır?**
C: Loading (bytecode oxumaq, Class obyekti yaratmaq), Linking (Verification+Preparation+Resolution), Initialization (static sahələr və blokları icra etmək).

**S: Custom ClassLoader nə üçün lazımdır?**
C: JAR dışı mənbələrdən (network, verilənlər bazası, şifrəli fayllar) sinif yükləmək, hot reload, plugin sistemləri, isolation (OSGi), bytecode instrumentasiyası.

**S: İki obyekt eyni sinif adına malikdir, amma `instanceof` false qaytarır — niyə?**
C: Fərqli ClassLoader-lər tərəfindən yüklənib. JVM-də sinif identifikatorları `ClassLoader + sinif adı` cütlüyüdür. Eyni ad, fərqli ClassLoader = fərqli sinif.

**S: `Class.forName()` ilə `ClassLoader.loadClass()` fərqi?**
C: `Class.forName()` default olaraq sinfi initialize edir (static bloklar icra olunur). `ClassLoader.loadClass()` yalnız yükləyir, initialize etmir. `Class.forName(name, false, loader)` forması initialize etmir.

**S: Metaspace-dəki sinif nə vaxt silinir?**
C: Onu yükləmiş ClassLoader GC tərəfindən toplananda. ClassLoader-ə heç bir istinad qalmadıqda o toplanır, onunla birlikdə yüklənmiş bütün siniflər Metaspace-dən silinir.

**S: Bootstrap ClassLoader Java-da null kimi görünür, niyə?**
C: Bootstrap ClassLoader C++ ilə yazılmışdır, JVM-in özünün bir hissəsidir. Java-da onun üçün ClassLoader obyek mövcud deyil, buna görə `getClassLoader()` null qaytarır.
