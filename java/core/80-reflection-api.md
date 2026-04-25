# 80 — Reflection API

> **Seviyye:** Advanced ⭐⭐⭐


## Mündəricat
1. [Reflection nədir?](#reflection-nədir)
2. [Class obyekti almaq](#class-obyekti-almaq)
3. [Metodlar, Sahələr, Konstruktorlar](#metodlar-sahələr-konstruktorlar)
4. [setAccessible(true)](#setaccessibletrue)
5. [Dynamic Proxy](#dynamic-proxy)
6. [İstifadə Halları](#i̇stifadə-halları)
7. [Performans Xərci](#performans-xərci)
8. [İntervyu Sualları](#i̇ntervyu-sualları)

---

## Reflection nədir?

**Reflection** — proqramın öz strukturunu (sinflər, metodlar, sahələr) runtime-da analiz etməsi və dəyişdirməsi qabiliyyətidir.

```java
// Normal kod — compile zamanı tip bilinir
String s = new String("Salam");
int len = s.length(); // Birbaşa metod çağırışı

// Reflection — runtime-da dinamik
Class<?> clazz = Class.forName("java.lang.String");
Object obj = clazz.getDeclaredConstructor(String.class).newInstance("Salam");
Method method = clazz.getMethod("length");
int len2 = (int) method.invoke(obj); // Dinamik çağırış
```

Reflection istifadə yerləri:
- Framework-lər (Spring, Hibernate, Jackson)
- Test framework-ləri (JUnit)
- IDE-lər (autocomplete)
- Serialization/Deserialization
- Dependency Injection
- ORM (Object-Relational Mapping)

---

## Class obyekti almaq

```java
public class GettingClassObjects {

    public static void main(String[] args) throws ClassNotFoundException {

        // Üsul 1: .class literal — compile zamanı tip bilinirsə
        Class<String> c1 = String.class;
        Class<Integer> c2 = Integer.class;
        Class<int[]> c3 = int[].class;       // Array-lər üçün
        Class<Void> c4 = Void.class;         // void üçün

        // Üsul 2: getClass() — instance üzərindən
        String str = "Salam";
        Class<?> c5 = str.getClass();
        System.out.println(c5.getName()); // java.lang.String

        // Üsul 3: Class.forName() — sinif adı runtime-da bilinir
        Class<?> c6 = Class.forName("java.util.ArrayList");
        Class<?> c7 = Class.forName("java.lang.String");

        // Massivlər üçün forName
        Class<?> c8 = Class.forName("[Ljava.lang.String;"); // String[]
        Class<?> c9 = Class.forName("[I"); // int[]

        // Üsul 4: ClassLoader vasitəsilə
        Class<?> c10 = ClassLoader.getSystemClassLoader().loadClass("java.util.HashMap");

        // Class-ın məlumatları
        System.out.println("=== String sinfi məlumatları ===");
        System.out.println("Ad: " + c1.getName());                  // java.lang.String
        System.out.println("Sadə ad: " + c1.getSimpleName());       // String
        System.out.println("Package: " + c1.getPackageName());       // java.lang
        System.out.println("Interface?: " + c1.isInterface());       // false
        System.out.println("Abstract?: " + java.lang.reflect.Modifier.isAbstract(c1.getModifiers()));
        System.out.println("Superclass: " + c1.getSuperclass());    // java.lang.Object
        System.out.println("Interfaces: " + java.util.Arrays.toString(c1.getInterfaces()));

        // Primitiv tip-lər
        Class<Integer> intClass = int.class;
        System.out.println("int primitiv?: " + intClass.isPrimitive()); // true
        System.out.println("int vs Integer: " + (int.class == Integer.class)); // false!
        System.out.println("Wrapper: " + Integer.class);

        // Array yoxlaması
        System.out.println("int[] array?: " + int[].class.isArray()); // true
        System.out.println("Component: " + int[].class.getComponentType()); // int
    }
}
```

---

## Metodlar, Sahələr, Konstruktorlar

```java
import java.lang.reflect.*;
import java.util.*;

public class ReflectionInspection {

    // Test sinfi
    static class Person {
        public String name;
        protected int age;
        private String password; // Private sahə

        public Person() {}
        public Person(String name, int age) {
            this.name = name;
            this.age = age;
            this.password = "default123";
        }

        public String getName() { return name; }
        public void setName(String name) { this.name = name; }
        protected void birthday() { age++; }
        private String getPassword() { return password; } // Private metod
    }

    public static void main(String[] args) throws Exception {
        Class<Person> clazz = Person.class;

        // === METODLAR ===
        System.out.println("=== Metodlar ===");

        // getMethods() — public metodlar (miras alınanlar daxil)
        Method[] publicMethods = clazz.getMethods();
        for (Method m : publicMethods) {
            System.out.println("Public: " + m.getName());
        }

        // getDeclaredMethods() — Bu sinifdə elan edilmiş BÜTÜN metodlar (private daxil)
        Method[] allMethods = clazz.getDeclaredMethods();
        for (Method m : allMethods) {
            System.out.printf("  %s %s(%s)%n",
                Modifier.toString(m.getModifiers()),
                m.getReturnType().getSimpleName(),
                Arrays.stream(m.getParameterTypes())
                    .map(Class::getSimpleName)
                    .reduce("", (a, b) -> a.isEmpty() ? b : a + ", " + b));
        }

        // Spesifik metod almaq
        Method getNameMethod = clazz.getMethod("getName"); // Public
        Method getPasswordMethod = clazz.getDeclaredMethod("getPassword"); // Private

        // Metod çağırmaq
        Person person = new Person("Əli", 25);
        String name = (String) getNameMethod.invoke(person);
        System.out.println("getName(): " + name); // Əli

        // === SAHƏLƏr ===
        System.out.println("\n=== Sahələr ===");

        // getFields() — public sahələr (miras daxil)
        Field[] publicFields = clazz.getFields();

        // getDeclaredFields() — Bu sinifdəki bütün sahələr (private daxil)
        Field[] allFields = clazz.getDeclaredFields();
        for (Field f : allFields) {
            System.out.printf("  %s %s %s%n",
                Modifier.toString(f.getModifiers()),
                f.getType().getSimpleName(),
                f.getName());
        }

        // Sahəyə daxil olmaq
        Field nameField = clazz.getField("name"); // public
        nameField.set(person, "Vüsal"); // Dəyər ver
        System.out.println("Yeni ad: " + nameField.get(person));

        // === KONSTRUKTORLAR ===
        System.out.println("\n=== Konstruktorlar ===");

        Constructor<?>[] constructors = clazz.getDeclaredConstructors();
        for (Constructor<?> c : constructors) {
            System.out.printf("  %s(%s)%n",
                c.getName(),
                Arrays.stream(c.getParameterTypes())
                    .map(Class::getSimpleName)
                    .reduce("", (a, b) -> a.isEmpty() ? b : a + ", " + b));
        }

        // Konstruktorla instance yarat
        Constructor<Person> paramConstructor = clazz.getDeclaredConstructor(
            String.class, int.class);
        Person newPerson = paramConstructor.newInstance("Nigar", 30);
        System.out.println("Yeni person: " + newPerson.name + ", " + newPerson.age);

        // Parametrsiz konstruktor
        Constructor<Person> defaultConstructor = clazz.getDeclaredConstructor();
        Person emptyPerson = defaultConstructor.newInstance();
        System.out.println("Boş person: " + emptyPerson.name); // null
    }
}
```

---

## setAccessible(true)

```java
import java.lang.reflect.*;

public class AccessibleDemo {

    static class SecureData {
        private String secret = "SuperGizliMəlumat";
        private static int instanceCount = 0;

        public SecureData() {
            instanceCount++;
        }
    }

    public static void main(String[] args) throws Exception {

        SecureData obj = new SecureData();

        // YANLIŞ — private sahəyə birbaşa daxil olmaq olmaz
        // Field secretField = SecureData.class.getDeclaredField("secret");
        // secretField.get(obj); // IllegalAccessException!

        // DOĞRU — setAccessible(true) ilə
        Field secretField = SecureData.class.getDeclaredField("secret");
        secretField.setAccessible(true); // Access modifikatorunu bypass et
        String secret = (String) secretField.get(obj);
        System.out.println("Gizli məlumat: " + secret);

        // Private sahəyə dəyər vermək
        secretField.set(obj, "YeniGizliMəlumat");
        System.out.println("Dəyişdirildi: " + secretField.get(obj));

        // Private statik sahəyə daxil olmaq
        Field countField = SecureData.class.getDeclaredField("instanceCount");
        countField.setAccessible(true);
        System.out.println("Instance sayı: " + countField.get(null)); // null → statik

        // Private metod çağırmaq
        Method privateMethod = SecureData.class.getDeclaredMethod("toString");
        // privateMethod.setAccessible(true); // public metodsa lazım deyil
        // privateMethod.invoke(obj);

        // Java 9+ Module System ilə məhdudiyyətlər
        // module-info.java-da opens elan edilməsə, setAccessible(true) işləməyə bilər
        // --add-opens java.base/java.lang=ALL-UNNAMED JVM flag ilə bypass edilə bilər

        // Java 17-dən strong encapsulation — bəzi JDK class-larına setAccessible məhdudlaşdı
        try {
            Field modifiersField = Field.class.getDeclaredField("modifiers");
            modifiersField.setAccessible(true); // Java 12+ bunu rədd edə bilər!
        } catch (InaccessibleObjectException e) {
            System.out.println("Modul sistemi məhdudiyyəti: " + e.getMessage());
        }
    }
}
```

---

## Dynamic Proxy

**Dynamic Proxy** — runtime-da interface implementation yaratmaq. `java.lang.reflect.Proxy` sinfi ilə.

```java
import java.lang.reflect.*;

public class DynamicProxyDemo {

    // Interface (proxy yalnız interface-lər üçün işləyir)
    interface UserService {
        String findUser(int id);
        void createUser(String name);
        void deleteUser(int id);
    }

    // Real implementasiya
    static class UserServiceImpl implements UserService {
        @Override
        public String findUser(int id) {
            return "İstifadəçi-" + id;
        }

        @Override
        public void createUser(String name) {
            System.out.println("İstifadəçi yaradıldı: " + name);
        }

        @Override
        public void deleteUser(int id) {
            System.out.println("İstifadəçi silindi: " + id);
        }
    }

    // Logging InvocationHandler
    static class LoggingHandler implements InvocationHandler {
        private final Object target; // Real implementasiya

        LoggingHandler(Object target) {
            this.target = target;
        }

        @Override
        public Object invoke(Object proxy, Method method, Object[] args) throws Throwable {
            // ƏVVƏL (Before advice)
            System.out.println("[LOG] Metod başlandı: " + method.getName()
                + " args=" + java.util.Arrays.toString(args));

            long start = System.nanoTime();
            Object result;

            try {
                // Real metodu çağır
                result = method.invoke(target, args);

                // UĞURDAN SONRA (After returning advice)
                System.out.println("[LOG] Metod tamamlandı: " + method.getName()
                    + " nəticə=" + result);

            } catch (InvocationTargetException e) {
                // XƏTA ZAMANINDA (After throwing advice)
                System.out.println("[LOG] Metod uğursuz: " + method.getName()
                    + " xəta=" + e.getCause().getMessage());
                throw e.getCause(); // Orijinal exception-u yenidən at

            } finally {
                // HƏR HALDA (After advice)
                long elapsed = System.nanoTime() - start;
                System.out.printf("[LOG] Müddət: %.3f ms%n", elapsed / 1_000_000.0);
            }

            return result;
        }
    }

    // Transaction InvocationHandler
    static class TransactionHandler implements InvocationHandler {
        private final Object target;

        TransactionHandler(Object target) {
            this.target = target;
        }

        @Override
        public Object invoke(Object proxy, Method method, Object[] args) throws Throwable {
            System.out.println("[TX] Tranzaksiya başladı");
            try {
                Object result = method.invoke(target, args);
                System.out.println("[TX] Tranzaksiya commit edildi");
                return result;
            } catch (Exception e) {
                System.out.println("[TX] Tranzaksiya rollback edildi");
                throw e;
            }
        }
    }

    // Proxy yaratmaq
    @SuppressWarnings("unchecked")
    public static <T> T createProxy(T target, InvocationHandler handler) {
        return (T) Proxy.newProxyInstance(
            target.getClass().getClassLoader(),  // ClassLoader
            target.getClass().getInterfaces(),   // Hansı interface-lər
            handler                               // Çağırışları tutacaq handler
        );
    }

    public static void main(String[] args) {
        UserService realService = new UserServiceImpl();

        // Logging proxy
        UserService loggingProxy = createProxy(realService,
            new LoggingHandler(realService));

        System.out.println("=== Logging Proxy ===");
        loggingProxy.findUser(1);
        loggingProxy.createUser("Əli");

        // Proxy olduğunu yoxlamaq
        System.out.println("Proxy mu?: " + Proxy.isProxyClass(loggingProxy.getClass()));
        InvocationHandler handler = Proxy.getInvocationHandler(loggingProxy);
        System.out.println("Handler tipi: " + handler.getClass().getSimpleName());

        // Çoxlu proxy zənciri (AOP kimi)
        UserService txProxy = createProxy(loggingProxy,
            new TransactionHandler(loggingProxy));

        System.out.println("\n=== TX + Logging Proxy ===");
        txProxy.createUser("Nigar");
    }
}
```

### Annotation ilə Proxy

```java
import java.lang.annotation.*;
import java.lang.reflect.*;

public class AnnotationBasedProxy {

    // Öz annotasiyamız
    @Retention(RetentionPolicy.RUNTIME)
    @Target(ElementType.METHOD)
    @interface Retry {
        int times() default 3;
        long delayMs() default 100;
    }

    interface DataService {
        @Retry(times = 3, delayMs = 200)
        String fetchData(String id);
    }

    static class DataServiceImpl implements DataService {
        private int callCount = 0;

        @Override
        public String fetchData(String id) {
            callCount++;
            if (callCount < 3) {
                throw new RuntimeException("Müvəqqəti xəta! Cəhd: " + callCount);
            }
            return "Məlumat: " + id;
        }
    }

    static class RetryHandler implements InvocationHandler {
        private final Object target;

        RetryHandler(Object target) { this.target = target; }

        @Override
        public Object invoke(Object proxy, Method method, Object[] args) throws Throwable {
            Retry retry = method.getAnnotation(Retry.class);

            if (retry == null) {
                return method.invoke(target, args); // @Retry yoxdursa birbaşa çağır
            }

            int maxTries = retry.times();
            long delay = retry.delayMs();

            for (int attempt = 1; attempt <= maxTries; attempt++) {
                try {
                    return method.invoke(target, args);
                } catch (InvocationTargetException e) {
                    if (attempt == maxTries) throw e.getCause();
                    System.out.printf("Cəhd %d uğursuz, %dms sonra yenidən...%n",
                        attempt, delay);
                    Thread.sleep(delay);
                }
            }
            return null;
        }
    }

    public static void main(String[] args) {
        DataService service = new DataServiceImpl();
        DataService proxy = (DataService) Proxy.newProxyInstance(
            service.getClass().getClassLoader(),
            new Class[]{DataService.class},
            new RetryHandler(service)
        );

        String result = proxy.fetchData("user-123");
        System.out.println("Nəticə: " + result);
    }
}
```

---

## İstifadə Halları

```java
import java.lang.reflect.*;
import java.util.*;

public class ReflectionUseCases {

    // 1. Sadə Dependency Injection (Spring kimi)
    @java.lang.annotation.Retention(java.lang.annotation.RetentionPolicy.RUNTIME)
    @java.lang.annotation.Target(java.lang.annotation.ElementType.FIELD)
    @interface Inject {}

    static class ServiceA {
        public String serve() { return "ServiceA işləyir"; }
    }

    static class Controller {
        @Inject
        private ServiceA serviceA; // Inject annotasiyası var

        private String name = "Controller"; // Inject yoxdur

        public void handle() {
            System.out.println(serviceA.serve());
        }
    }

    static void inject(Object target) throws Exception {
        Class<?> clazz = target.getClass();
        for (Field field : clazz.getDeclaredFields()) {
            if (field.isAnnotationPresent(Inject.class)) {
                field.setAccessible(true);
                // Sahənin tipinə görə instance yarat
                Object instance = field.getType()
                    .getDeclaredConstructor()
                    .newInstance();
                field.set(target, instance);
                System.out.println("Inject edildi: " + field.getType().getSimpleName()
                    + " → " + field.getName());
            }
        }
    }

    // 2. JSON-a bənzər Serialization
    static String toJson(Object obj) throws Exception {
        StringBuilder sb = new StringBuilder("{");
        Class<?> clazz = obj.getClass();

        Field[] fields = clazz.getDeclaredFields();
        for (int i = 0; i < fields.length; i++) {
            Field field = fields[i];
            field.setAccessible(true);
            sb.append('"').append(field.getName()).append('"')
              .append(':')
              .append('"').append(field.get(obj)).append('"');
            if (i < fields.length - 1) sb.append(',');
        }
        sb.append('}');
        return sb.toString();
    }

    // 3. Generic toString() generator
    static String reflectiveToString(Object obj) {
        Class<?> clazz = obj.getClass();
        StringBuilder sb = new StringBuilder(clazz.getSimpleName()).append("{");
        Field[] fields = clazz.getDeclaredFields();

        for (int i = 0; i < fields.length; i++) {
            fields[i].setAccessible(true);
            try {
                sb.append(fields[i].getName()).append("=");
                sb.append(fields[i].get(obj));
                if (i < fields.length - 1) sb.append(", ");
            } catch (IllegalAccessException e) {
                sb.append("?");
            }
        }
        return sb.append("}").toString();
    }

    static class Product {
        private String name = "Kitab";
        private double price = 15.99;
        private int stock = 100;
    }

    public static void main(String[] args) throws Exception {
        // DI nümunəsi
        Controller controller = new Controller();
        inject(controller);
        controller.handle();

        // Serialization nümunəsi
        Product product = new Product();
        System.out.println(toJson(product));

        // toString nümunəsi
        System.out.println(reflectiveToString(product));
    }
}
```

---

## Performans Xərci

```java
import java.lang.reflect.*;

public class ReflectionPerformance {

    static class Calculator {
        public int add(int a, int b) { return a + b; }
    }

    public static void main(String[] args) throws Exception {
        Calculator calc = new Calculator();
        Method addMethod = Calculator.class.getMethod("add", int.class, int.class);
        addMethod.setAccessible(true);

        int iterations = 10_000_000;

        // Benchmark 1: Birbaşa çağırış
        long start = System.nanoTime();
        for (int i = 0; i < iterations; i++) {
            calc.add(1, 2);
        }
        long directTime = System.nanoTime() - start;

        // Benchmark 2: Reflection ilə
        start = System.nanoTime();
        for (int i = 0; i < iterations; i++) {
            addMethod.invoke(calc, 1, 2);
        }
        long reflectionTime = System.nanoTime() - start;

        System.out.printf("Birbaşa: %.2f ms%n", directTime / 1_000_000.0);
        System.out.printf("Reflection: %.2f ms%n", reflectionTime / 1_000_000.0);
        System.out.printf("Yavaşlama: %.1fx%n", (double) reflectionTime / directTime);
        // Adətən 2-10x yavaş (JIT optimize etdikdən sonra fərq azalır)

        // Reflection sürətləndirmə üsulları:
        // 1. Method/Field obyektini cache-ə al (hər dəfə getDeclaredMethod çağırma)
        // 2. setAccessible(true) bir dəfə çağır
        // 3. Java 8+ MethodHandles.lookup() — daha sürətli
        // 4. Code generation (Byte Buddy, ASM) — reflection-sız dinamik kod

        // MethodHandles nümunəsi (sürətli alternativ)
        java.lang.invoke.MethodHandles.Lookup lookup = java.lang.invoke.MethodHandles.lookup();
        java.lang.invoke.MethodHandle mh = lookup.findVirtual(
            Calculator.class,
            "add",
            java.lang.invoke.MethodType.methodType(int.class, int.class, int.class)
        );

        start = System.nanoTime();
        for (int i = 0; i < iterations; i++) {
            mh.invoke(calc, 1, 2); // MethodHandle — reflection-dan sürətli!
        }
        long mhTime = System.nanoTime() - start;
        System.out.printf("MethodHandle: %.2f ms%n", mhTime / 1_000_000.0);
        // Birbaşa çağırışa çox yaxın!
    }
}
```

---

## İntervyu Sualları

**S: Reflection nədir?**
C: Proqramın öz strukturunu (siniflər, metodlar, sahələr) runtime-da analiz etmə və dəyişdirmə qabiliyyətidir. Framework-lər (Spring, Hibernate), test alətləri (JUnit), IDE-lər reflection istifadə edir.

**S: `Class.forName()` ilə `.class` fərqi?**
C: `.class` compile zamanı — tip bilinir, daha sürətli, sinfi initialize etmir. `Class.forName()` runtime-da — sinif adı string kimi, sinfi yükləyir və initialize edir (static bloklar çalışır). `Class.forName(name, false, loader)` initialize etmədən yükləyir.

**S: `getMethods()` vs `getDeclaredMethods()` fərqi?**
C: `getMethods()` — yalnız public metodlar, miras alınanlar daxil. `getDeclaredMethods()` — bu sinifdəki bütün metodlar (private, protected daxil), amma miras alınanlar daxil deyil. Adətən `getDeclaredMethods()` + `setAccessible(true)` istifadə olunur.

**S: Dynamic Proxy nə üçün istifadə olunur?**
C: Runtime-da interface implementasiyası yaratmaq. Spring AOP bütün `@Transactional`, `@Cacheable`, logging kimi cross-cutting concern-ləri dynamic proxy ilə həyata keçirir. `Proxy.newProxyInstance()` + `InvocationHandler.invoke()` metodları.

**S: `setAccessible(true)` niyə lazımdır?**
C: Private/protected member-lara reflection vasitəsilə daxil olmaq üçün access modifier yoxlamasını bypass edir. Java 9+ module sistemi ilə bəzi hallarda `InaccessibleObjectException` ata bilər (module-info.java `opens` tələb edir).

**S: Reflection-ın performans xərci nədir?**
C: Birbaşa çağırışdan 2-10x yavaş. Səbəblər: type checking, access control, varargs boxing, JIT optimization çətin. Optimallaşdırma: Method/Field-ı cache-ə al, setAccessible(true) bir dəfə çağır, `MethodHandles` istifadə et (birbaşa çağırışa çox yaxın).

**S: JDK Proxy ilə CGLIB fərqi?**
C: JDK Proxy yalnız interface-lər üçün — `Proxy.newProxyInstance()`. CGLIB (Code Generation Library) concrete class-lar üçün — bytecode-u runtime-da generasiya edir, alt sinif yaradır. Spring AOP: interface varsa JDK Proxy, yoxdursa CGLIB istifadə edir.
