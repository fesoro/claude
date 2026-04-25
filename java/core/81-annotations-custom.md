# 81 — Annotations və Custom Annotations

> **Seviyye:** Advanced ⭐⭐⭐


## Mündəricat
1. [Annotation nədir?](#annotation-nədir)
2. [@interface sintaksisi](#interface-sintaksisi)
3. [Element növləri](#element-növləri)
4. [Meta-annotasiyalar](#meta-annotasiyalar)
5. [Runtime-da annotasiyaları oxumaq](#runtime-da-annotasiyaları-oxumaq)
6. [Annotation Processor](#annotation-processor)
7. [İstifadə Nümunələri](#i̇stifadə-nümunələri)
8. [İntervyu Sualları](#i̇ntervyu-sualları)

---

## Annotation nədir?

**Annotation** — koda əlavə metadata. Proqramın icrasına birbaşa təsir etmir, amma compile zamanı, class loading zamanı ya da runtime-da xüsusi işlər üçün istifadə olunur.

```java
// Java-nın standart annotasiyaları
@Override              // Metodun üst sinifdəkini override etdiyini göstərir
@Deprecated            // Köhnəlmiş element — istifadə etmə
@SuppressWarnings("unchecked") // Xəbərdarlıqları sustur
@FunctionalInterface   // Funksional interface olduğunu göstərir
@SafeVarargs           // Varargs ilə tip xəbərdarlıqlarını sustur

// Framework annotasiyaları
@Override
public String toString() { return "..."; }

@Deprecated
public void oldMethod() { } // IDE sarı xətlə vurğulayar

@SuppressWarnings({"unchecked", "rawtypes"})
public void uncheckedMethod() {
    java.util.List list = new java.util.ArrayList(); // Raw type xəbərdarlığı susur
}
```

---

## @interface Sintaksisi

```java
// Ən sadə annotasiya (marker annotation — element yoxdur)
public @interface MyAnnotation {
    // Heç bir element yoxdur
}

// Element-li annotasiya
public @interface Author {
    String name();        // Məcburi element (default yoxdur)
    String date();        // Məcburi element
    int version() default 1; // Default dəyəri olan element — istəğə bağlı
}

// İstifadə:
@Author(name = "Əli Həsənov", date = "2024-01-15")
public class MyClass { }

@Author(name = "Nigar", date = "2024-02-01", version = 2)
public class OtherClass { }

// Tək "value" elementi — adını yazmadan istifadə etmək olar
public @interface Category {
    String value(); // "value" adlı tək element
}

@Category("Backend") // @Category(value = "Backend") ilə eynidir
public class BackendService { }
```

---

## Element Növləri

Annotasiya elementlərinin tipi yalnız bunlardan biri ola bilər:

```java
import java.lang.annotation.*;

public @interface AllTypesAnnotation {

    // 1. Primitiv tiplər
    int intValue() default 0;
    long longValue() default 0L;
    double doubleValue() default 0.0;
    boolean boolValue() default false;
    char charValue() default ' ';

    // 2. String
    String stringValue() default "";

    // 3. Class
    Class<?> classValue() default Object.class;
    Class<? extends Runnable> runnableClass() default Thread.class;

    // 4. Enum
    RetentionPolicy policy() default RetentionPolicy.RUNTIME;
    ElementType target() default ElementType.TYPE;

    // 5. Başqa annotasiya
    Deprecated deprecated() default @Deprecated;

    // 6. Yuxarıdakıların massivləri
    int[] intArray() default {};
    String[] stringArray() default {};
    Class<?>[] classArray() default {};
    ElementType[] targets() default {ElementType.TYPE, ElementType.METHOD};
}

// İstifadə nümunəsi
@AllTypesAnnotation(
    intValue = 42,
    stringValue = "Salam",
    classValue = String.class,
    policy = RetentionPolicy.RUNTIME,
    intArray = {1, 2, 3},
    stringArray = {"a", "b"},
    targets = {ElementType.FIELD, ElementType.METHOD}
)
public class AnnotationDemo { }
```

---

## Meta-annotasiyalar

**Meta-annotasiya** — annotasiyaları annotasiya edən annotasiyalar.

### @Retention

```java
import java.lang.annotation.*;

// SOURCE — yalnız source code-da, compile-dan sonra yox
@Retention(RetentionPolicy.SOURCE)
public @interface SourceOnly {
    // Lombok-un @Getter kimi — compile zamanı kod generasiya edir
    // .class faylında görünmür
}

// CLASS — .class faylında var, amma JVM runtime-da oxumur (DEFAULT)
@Retention(RetentionPolicy.CLASS)
public @interface ClassOnly {
    // Bytecode analysis alətlər üçün (FindBugs, SpotBugs)
    // Reflection ilə oxunmur
}

// RUNTIME — JVM runtime-da mövcuddur, Reflection ilə oxunur
@Retention(RetentionPolicy.RUNTIME)
public @interface RuntimeVisible {
    // Spring @Component, @Autowired, JUnit @Test kimi
    // Reflection.getAnnotation() ilə oxunur
    String value() default "";
}
```

### @Target

```java
import java.lang.annotation.*;

// Hansı elementlərə tətbiq edilə bilər
@Target({
    ElementType.TYPE,             // Sinif, interface, enum, annotation, record
    ElementType.METHOD,           // Metod
    ElementType.FIELD,            // Sahə (enum konstant daxil)
    ElementType.PARAMETER,        // Metod parametri
    ElementType.CONSTRUCTOR,      // Konstruktor
    ElementType.LOCAL_VARIABLE,   // Lokal dəyişən
    ElementType.ANNOTATION_TYPE,  // Başqa annotasiya
    ElementType.PACKAGE,          // Paket (package-info.java)
    ElementType.TYPE_PARAMETER,   // Generic tip parametri (Java 8+)
    ElementType.TYPE_USE,         // İstənilən tip istifadəsi (Java 8+)
    ElementType.MODULE,           // Modul (Java 9+)
    ElementType.RECORD_COMPONENT  // Record komponenti (Java 16+)
})
public @interface UniversalAnnotation { }

// Yalnız metodlar üçün
@Target(ElementType.METHOD)
@Retention(RetentionPolicy.RUNTIME)
public @interface TimeIt {
    String description() default "";
}

// Yalnız sahələr üçün
@Target(ElementType.FIELD)
@Retention(RetentionPolicy.RUNTIME)
public @interface NotNull {
    String message() default "Bu sahə null ola bilməz";
}
```

### @Documented

```java
// @Documented — Javadoc-a daxil et
@Documented
@Retention(RetentionPolicy.RUNTIME)
@Target(ElementType.TYPE)
public @interface PublicAPI {
    String since() default "1.0";
    String author() default "";
}

// @Documented olduqda Javadoc-da görünür:
@PublicAPI(since = "2.0", author = "Əli")
public class MyPublicClass {
    // Javadoc-da @PublicAPI annotasiyası da görünəcək
}
```

### @Inherited

```java
// @Inherited — alt sinflərə miras keçir (yalnız sinif annotasiyaları üçün)
@Inherited
@Retention(RetentionPolicy.RUNTIME)
@Target(ElementType.TYPE)
public @interface Monitored {
    String name() default "";
}

@Monitored(name = "BaseService")
class BaseService {
    // @Monitored annotasiyası var
}

class ChildService extends BaseService {
    // @Inherited olduğu üçün ChildService də @Monitored annotation-ına malikdir!
    // ChildService.class.isAnnotationPresent(Monitored.class) → true
}

// @Inherited yalnız getSuperclass() üçün işləyir
// Interface-lərdə, ya da method annotasiyalarında işləmir
```

### @Repeatable (Java 8+)

```java
// @Repeatable — eyni annotasiyanı bir neçə dəfə tətbiq etmək
@Retention(RetentionPolicy.RUNTIME)
@Target(ElementType.METHOD)
@Repeatable(Schedules.class) // Container annotasiyasını göstər
public @interface Schedule {
    String cron();
}

// Container annotasiyası
@Retention(RetentionPolicy.RUNTIME)
@Target(ElementType.METHOD)
public @interface Schedules {
    Schedule[] value(); // Eyni adlı value() elementi olmalıdır
}

// İstifadə:
public class ScheduledTasks {
    @Schedule(cron = "0 * * * * ?")     // Hər dəqiqə
    @Schedule(cron = "0 0 12 * * ?")    // Hər gün saat 12
    public void scheduledMethod() {
        System.out.println("Planlaşdırılmış iş icra olunur");
    }
}
```

---

## Runtime-da annotasiyaları oxumaq

```java
import java.lang.annotation.*;
import java.lang.reflect.*;

// Test annotasiyaları
@Retention(RetentionPolicy.RUNTIME)
@Target({ElementType.TYPE, ElementType.METHOD, ElementType.FIELD})
@interface Validate {
    int minLength() default 0;
    int maxLength() default Integer.MAX_VALUE;
    boolean required() default true;
}

@Retention(RetentionPolicy.RUNTIME)
@Target(ElementType.FIELD)
@interface Column {
    String name() default "";
    boolean nullable() default true;
    int length() default 255;
}

@Retention(RetentionPolicy.RUNTIME)
@Target(ElementType.TYPE)
@interface Table {
    String name();
}

public class AnnotationReaderDemo {

    @Table(name = "users")
    static class User {
        @Column(name = "user_name", nullable = false, length = 50)
        @Validate(minLength = 3, maxLength = 50, required = true)
        private String username;

        @Column(name = "email_address", nullable = false)
        @Validate(required = true)
        private String email;

        @Column(name = "age")
        private int age;
    }

    // Annotasiyaları oxumaq
    public static void inspectClass(Class<?> clazz) {
        System.out.println("=== " + clazz.getSimpleName() + " ===");

        // Sinif annotasiyaları
        if (clazz.isAnnotationPresent(Table.class)) {
            Table table = clazz.getAnnotation(Table.class);
            System.out.println("Cədvəl adı: " + table.name());
        }

        // Sahə annotasiyaları
        for (Field field : clazz.getDeclaredFields()) {
            System.out.println("\nSahə: " + field.getName());

            if (field.isAnnotationPresent(Column.class)) {
                Column col = field.getAnnotation(Column.class);
                String colName = col.name().isEmpty() ? field.getName() : col.name();
                System.out.printf("  DB sütun: %s (nullable=%b, length=%d)%n",
                    colName, col.nullable(), col.length());
            }

            if (field.isAnnotationPresent(Validate.class)) {
                Validate val = field.getAnnotation(Validate.class);
                System.out.printf("  Validasiya: required=%b, min=%d, max=%d%n",
                    val.required(), val.minLength(), val.maxLength());
            }

            // Bütün annotasiyaları almaq
            Annotation[] annotations = field.getDeclaredAnnotations();
            System.out.println("  Cəmi annotasiya: " + annotations.length);
        }
    }

    // Validasiya framework-u simulyasiyası
    public static java.util.List<String> validate(Object obj) throws IllegalAccessException {
        java.util.List<String> errors = new java.util.ArrayList<>();
        Class<?> clazz = obj.getClass();

        for (Field field : clazz.getDeclaredFields()) {
            if (!field.isAnnotationPresent(Validate.class)) continue;

            field.setAccessible(true);
            Object value = field.get(obj);
            Validate validate = field.getAnnotation(Validate.class);

            // required yoxlaması
            if (validate.required() && (value == null ||
                (value instanceof String s && s.isBlank()))) {
                errors.add(field.getName() + ": məcburidir, boş ola bilməz");
                continue;
            }

            // String uzunluğu yoxlaması
            if (value instanceof String s) {
                if (s.length() < validate.minLength()) {
                    errors.add(field.getName() + ": minimum " + validate.minLength()
                        + " simvol olmalıdır");
                }
                if (s.length() > validate.maxLength()) {
                    errors.add(field.getName() + ": maksimum " + validate.maxLength()
                        + " simvol ola bilər");
                }
            }
        }

        return errors;
    }

    public static void main(String[] args) throws Exception {
        // İnspection
        inspectClass(User.class);

        // Validasiya test-i
        User user = new User();
        user.username = "Ab"; // Çox qısa (min 3)

        java.util.List<String> errors = validate(user);
        if (!errors.isEmpty()) {
            System.out.println("\nValidasiya xətaları:");
            errors.forEach(e -> System.out.println("  - " + e));
        }
    }
}
```

---

## Annotation Processor

**Annotation Processor** — compile zamanı annotasiyaları emal edən komponent (Java 6+, `javac -processor`).

```java
import javax.annotation.processing.*;
import javax.lang.model.*;
import javax.lang.model.element.*;
import javax.tools.*;
import java.util.*;

// Annotasiya elan
@Retention(RetentionPolicy.SOURCE) // SOURCE — yalnız compile zamanı lazım
@Target(ElementType.TYPE)
public @interface AutoLogger {
    // Bu annotasiya hər sinifdə logger field yaradacaq
}

// Processor
@SupportedAnnotationTypes("com.example.AutoLogger")
@SupportedSourceVersion(SourceVersion.RELEASE_17)
public class AutoLoggerProcessor extends AbstractProcessor {

    @Override
    public boolean process(Set<? extends TypeElement> annotations,
                          RoundEnvironment roundEnv) {

        // @AutoLogger annotasiyası olan bütün elementləri tap
        for (Element element : roundEnv.getElementsAnnotatedWith(AutoLogger.class)) {
            if (element.getKind() != ElementKind.CLASS) continue;

            TypeElement classElement = (TypeElement) element;
            String className = classElement.getSimpleName().toString();
            String packageName = processingEnv.getElementUtils()
                .getPackageOf(classElement).getQualifiedName().toString();

            // Mesaj ver (IDE-də görünür)
            processingEnv.getMessager().printMessage(
                Diagnostic.Kind.NOTE,
                "AutoLogger processing: " + className
            );

            // Burada yeni Java fayl generasiya etmək mümkündür
            // (Lombok, MapStruct bunu edir)
            generateLoggerCode(packageName, className);
        }

        return true; // Bu annotasiyaları başqa processor-lara ötürmə
    }

    private void generateLoggerCode(String pkg, String cls) {
        // Yeni .java fayl yaratmaq
        try {
            JavaFileObject file = processingEnv.getFiler()
                .createSourceFile(pkg + "." + cls + "Logger");

            try (java.io.Writer writer = file.openWriter()) {
                writer.write("package " + pkg + ";\n\n");
                writer.write("public class " + cls + "Logger {\n");
                writer.write("  private static final org.slf4j.Logger log =\n");
                writer.write("    org.slf4j.LoggerFactory.getLogger(" + cls + ".class);\n");
                writer.write("}\n");
            }
        } catch (Exception e) {
            processingEnv.getMessager().printMessage(
                Diagnostic.Kind.ERROR, "Kod generasiyası uğursuz: " + e.getMessage());
        }
    }

    @Override
    public SourceVersion getSupportedSourceVersion() {
        return SourceVersion.latestSupported();
    }
}
```

### Annotation Processor Qeydiyyatı

```
# META-INF/services/javax.annotation.processing.Processor
com.example.AutoLoggerProcessor

# Ya da @AutoService (Google) annotasiyası ilə:
@AutoService(Processor.class)
public class AutoLoggerProcessor extends AbstractProcessor { }
```

---

## İstifadə Nümunələri

```java
import java.lang.annotation.*;
import java.lang.reflect.*;
import java.util.*;

public class PracticalAnnotations {

    // 1. @RateLimit — metod çağırış limitini idarə etmək
    @Retention(RetentionPolicy.RUNTIME)
    @Target(ElementType.METHOD)
    @interface RateLimit {
        int callsPerMinute() default 60;
    }

    // 2. @Cache — nəticəni cache-ə al
    @Retention(RetentionPolicy.RUNTIME)
    @Target(ElementType.METHOD)
    @interface Cache {
        int ttlSeconds() default 300; // 5 dəqiqə
        String key() default "";      // Cache açarı
    }

    // 3. @Audit — əməliyyatı qeyd et
    @Retention(RetentionPolicy.RUNTIME)
    @Target(ElementType.METHOD)
    @interface Audit {
        String action();
        boolean logArgs() default false;
        boolean logResult() default false;
    }

    // 4. @Permission — icazə yoxlaması
    @Retention(RetentionPolicy.RUNTIME)
    @Target(ElementType.METHOD)
    @interface RequiresPermission {
        String[] value(); // Tələb olunan icazələr
        boolean all() default true; // true=hamısı lazım, false=biri kifayət
    }

    // İstifadə nümunəsi
    class UserController {
        @Audit(action = "GET_USER", logArgs = true)
        @Cache(ttlSeconds = 60, key = "user-{id}")
        @RequiresPermission({"READ_USER"})
        public String getUser(int id) {
            return "İstifadəçi " + id;
        }

        @Audit(action = "DELETE_USER", logArgs = true, logResult = true)
        @RateLimit(callsPerMinute = 10)
        @RequiresPermission(value = {"ADMIN", "DELETE_USER"}, all = false)
        public void deleteUser(int id) {
            System.out.println("İstifadəçi silindi: " + id);
        }
    }

    // 5. @Config — konfiqurasiya dəyərini inject et
    @Retention(RetentionPolicy.RUNTIME)
    @Target(ElementType.FIELD)
    @interface Config {
        String key();
        String defaultValue() default "";
    }

    // Konfiqurasiya injection
    static class AppService {
        @Config(key = "app.name", defaultValue = "MyApp")
        private String appName;

        @Config(key = "app.port", defaultValue = "8080")
        private String port;

        @Config(key = "db.url")
        private String dbUrl;
    }

    static void injectConfig(Object obj, Properties props) throws Exception {
        for (Field field : obj.getClass().getDeclaredFields()) {
            Config config = field.getAnnotation(Config.class);
            if (config == null) continue;

            field.setAccessible(true);
            String value = props.getProperty(config.key(), config.defaultValue());

            if (value.isEmpty() && config.defaultValue().isEmpty()) {
                throw new IllegalStateException("Məcburi konfiq yoxdur: " + config.key());
            }

            // Tip çevrilməsi
            if (field.getType() == int.class || field.getType() == Integer.class) {
                field.set(obj, Integer.parseInt(value));
            } else if (field.getType() == boolean.class || field.getType() == Boolean.class) {
                field.set(obj, Boolean.parseBoolean(value));
            } else {
                field.set(obj, value);
            }
        }
    }

    public static void main(String[] args) throws Exception {
        Properties props = new Properties();
        props.setProperty("app.name", "MyJavaApp");
        props.setProperty("app.port", "9090");
        props.setProperty("db.url", "jdbc:postgresql://localhost/mydb");

        AppService service = new AppService();
        injectConfig(service, props);

        Field nameField = AppService.class.getDeclaredField("appName");
        nameField.setAccessible(true);
        System.out.println("App adı: " + nameField.get(service));

        Field portField = AppService.class.getDeclaredField("port");
        portField.setAccessible(true);
        System.out.println("Port: " + portField.get(service));
    }
}
```

---

## İntervyu Sualları

**S: Annotation nədir? Necə işləyir?**
C: Annotation — koda əlavə metadata. `@interface` ilə elan edilir. Compile zamanı (SOURCE), class faylında (CLASS) ya da runtime-da (RUNTIME) mövcud ola bilər. Framework-lər runtime annotasiyaları Reflection ilə oxuyub DI, AOP, validation kimi işləri görür.

**S: @Retention növləri hansılardır?**
C: SOURCE — yalnız source code-da (Lombok @Getter, @Override). CLASS — .class faylında, runtime-da yox (default). RUNTIME — JVM-də var, Reflection ilə oxuna bilər (Spring @Component, JUnit @Test).

**S: @Target nə üçün istifadə olunur?**
C: Annotasiyanın hansı elementlərə tətbiq edilə biləcəyini məhdudlaşdırır. ElementType.METHOD — yalnız metodlara, ElementType.FIELD — yalnız sahələrə, ElementType.TYPE — siniflərə/interface-lərə. Yanlış yerdə istifadə kompilasiya xətası verir.

**S: @Inherited necə işləyir?**
C: Sinif annotasiyasını alt siniflərinə miras keçirir. `@Monitored` üst sinifdədir, `@Inherited` olduğuna görə alt sinif də bu annotasiyaya malikdir. Yalnız sinif annotasiyaları üçün işləyir — method və interface-lər üçün işləmir.

**S: Annotation Processor nədir?**
C: `javac` compile zamanı annotasiyaları emal edən `AbstractProcessor` alt sinifidir. Lombok, MapStruct, Dagger bunlardan istifadə edir. Yeni .java fayllar generasiya edə, xəbərdarlıqlar verə, xəta mesajları ata bilər. `META-INF/services/javax.annotation.processing.Processor`-da qeydiyyata alınır.

**S: SOURCE retention niyə istifadə olunur?**
C: Compile zamanı kod generasiyası üçün (Lombok @Getter/@Setter/@Data) — .class faylına getmir, runtime overhead yoxdur. Kod analizi üçün (@Override, @SuppressWarnings) — compiler yoxlayır, runtime lazım deyil.

**S: @Repeatable annotasiya nə üçündür?**
C: Eyni annotasiyanı bir elementə bir neçə dəfə tətbiq etmək. `@Schedule(cron="...")` iki dəfə yazıla bilir. Bunun üçün container annotasiyası da lazımdır (`@Schedules` — `Schedule[]` massivini saxlayır).
