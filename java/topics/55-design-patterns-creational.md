# 55. Design Patterns — Creational (Yaradıcı)

## Mündəricat
1. [Singleton](#singleton)
2. [Factory Method](#factory-method)
3. [Abstract Factory](#abstract-factory)
4. [Builder](#builder)
5. [Prototype](#prototype)
6. [İntervyu Sualları](#i̇ntervyu-sualları)

---

## Singleton

**Singleton** — bir sinfin bütün tətbiq boyu yalnız BİR instansının olmasını təmin edir.

İstifadə: Configuration, Logger, Connection Pool, Cache Manager.

### Tənbəl başlatma (Lazy Initialization) — Thread-safe deyil!

```java
// YANLIŞ — thread-safe deyil
public class BadSingleton {
    private static BadSingleton instance;

    private BadSingleton() {}

    // Çoxlu thread eyni anda buraya girə bilər!
    public static BadSingleton getInstance() {
        if (instance == null) {           // Thread 1 null görür
            instance = new BadSingleton(); // Thread 2 də null görür
            // İki instance yarana bilər!
        }
        return instance;
    }
}
```

### Synchronized Singleton — Düzgün amma yavaş

```java
// DOĞRU amma yavaş — hər çağırışda lock
public class SynchronizedSingleton {
    private static SynchronizedSingleton instance;

    private SynchronizedSingleton() {}

    public static synchronized SynchronizedSingleton getInstance() {
        if (instance == null) {
            instance = new SynchronizedSingleton();
        }
        return instance;
    }
}
```

### Double-Checked Locking (DCL) — Tövsiyə olunan

```java
// DOĞRU — thread-safe + performanslı
public class DCLSingleton {
    // volatile — görünürlüğü təmin edir (JVM reordering-dən qoruyur)
    private static volatile DCLSingleton instance;

    private DCLSingleton() {
        // Reflection vasitəsilə ikinci instance-ın yaranmasının qarşısını al
        if (instance != null) {
            throw new RuntimeException("Singleton pozuldu — reflection istifadə etmə!");
        }
    }

    public static DCLSingleton getInstance() {
        if (instance == null) {                     // İlk yoxlama (locking olmadan)
            synchronized (DCLSingleton.class) {
                if (instance == null) {             // İkinci yoxlama (lock içində)
                    instance = new DCLSingleton();
                }
            }
        }
        return instance;
    }
}
```

### Initialization-on-demand Holder — Ən Zərif

```java
// DOĞRU — lazy + thread-safe + heç bir synchronization yoxdur
public class HolderSingleton {

    private HolderSingleton() {}

    // Static inner class — yalnız getInstance() çağırıldıqda yüklənir
    private static class Holder {
        // Class loader-in thread-safety-sini istifadə edir
        private static final HolderSingleton INSTANCE = new HolderSingleton();
    }

    public static HolderSingleton getInstance() {
        return Holder.INSTANCE; // Thread-safe, lazy, no sync overhead
    }
}
```

### Enum Singleton — En Güvənli (Joshua Bloch tövsiyəsi)

```java
// DOĞRU — reflection və serialization-a qarşı qorunmuşdur
public enum EnumSingleton {
    INSTANCE; // Yalnız bir instance

    private final java.util.Map<String, String> config = new java.util.HashMap<>();

    public void setValue(String key, String value) {
        config.put(key, value);
    }

    public String getValue(String key) {
        return config.getOrDefault(key, "");
    }

    public void doWork() {
        System.out.println("Singleton iş görür");
    }
}

// İstifadə:
// EnumSingleton.INSTANCE.doWork();
// EnumSingleton.INSTANCE.setValue("key", "value");
```

### Singleton ilə İstifadə

```java
// Real singleton nümunəsi — Application Config
public class AppConfig {
    private static volatile AppConfig instance;
    private final java.util.Properties properties = new java.util.Properties();

    private AppConfig() {
        try {
            // Konfiqurasiya faylını yüklə
            properties.load(getClass().getResourceAsStream("/app.properties"));
        } catch (Exception e) {
            System.err.println("Config yüklənə bilmədi: " + e.getMessage());
        }
    }

    public static AppConfig getInstance() {
        if (instance == null) {
            synchronized (AppConfig.class) {
                if (instance == null) {
                    instance = new AppConfig();
                }
            }
        }
        return instance;
    }

    public String get(String key) {
        return properties.getProperty(key);
    }

    public String get(String key, String defaultValue) {
        return properties.getProperty(key, defaultValue);
    }
}
```

---

## Factory Method

**Factory Method** — obyekt yaratmaq üçün interface/abstract method təyin edir. Alt siniflər hansı tip yaradacağını qərar verir.

```java
// Product interface
interface Notification {
    void send(String message, String recipient);
}

// Concrete Products
class EmailNotification implements Notification {
    @Override
    public void send(String message, String recipient) {
        System.out.println("Email göndərildi → " + recipient + ": " + message);
    }
}

class SMSNotification implements Notification {
    @Override
    public void send(String message, String recipient) {
        System.out.println("SMS göndərildi → " + recipient + ": " + message);
    }
}

class PushNotification implements Notification {
    @Override
    public void send(String message, String recipient) {
        System.out.println("Push bildiriş → " + recipient + ": " + message);
    }
}

// Creator (Factory Method-u elan edir)
abstract class NotificationFactory {

    // Factory Method — alt siniflər implement edir
    public abstract Notification createNotification();

    // Template Method — factory method-u istifadə edir
    public void sendNotification(String message, String recipient) {
        Notification notification = createNotification(); // Factory Method çağırılır
        notification.send(message, recipient);
    }
}

// Concrete Creators
class EmailNotificationFactory extends NotificationFactory {
    @Override
    public Notification createNotification() {
        return new EmailNotification();
    }
}

class SMSNotificationFactory extends NotificationFactory {
    @Override
    public Notification createNotification() {
        return new SMSNotification();
    }
}

// Static Factory Method Pattern (daha geniş yayılmış)
class NotificationService {
    // Static factory — tip string-ə görə yaradır
    public static Notification create(String type) {
        return switch (type.toLowerCase()) {
            case "email" -> new EmailNotification();
            case "sms"   -> new SMSNotification();
            case "push"  -> new PushNotification();
            default -> throw new IllegalArgumentException("Naməlum tip: " + type);
        };
    }
}

// İstifadə:
class FactoryMethodDemo {
    public static void main(String[] args) {
        // Abstract Factory Method
        NotificationFactory factory = new EmailNotificationFactory();
        factory.sendNotification("Xoş gəlmisiniz!", "ali@example.com");

        // Static Factory Method
        Notification sms = NotificationService.create("sms");
        sms.send("Sifarişiniz təsdiqləndi", "+994501234567");
    }
}
```

---

## Abstract Factory

**Abstract Factory** — əlaqəli obyektlər ailəsi yaratmaq üçün interface. Factory-lər family-sini yaradır.

```java
// Abstract Products
interface Button {
    void render();
    void onClick();
}

interface Checkbox {
    void render();
    boolean isChecked();
}

// Windows ailə
class WindowsButton implements Button {
    @Override
    public void render() { System.out.println("Windows düymə render edildi"); }
    @Override
    public void onClick() { System.out.println("Windows düyməyə click"); }
}

class WindowsCheckbox implements Checkbox {
    private boolean checked = false;
    @Override
    public void render() { System.out.println("Windows checkbox render edildi"); }
    @Override
    public boolean isChecked() { return checked; }
}

// Mac OS ailə
class MacButton implements Button {
    @Override
    public void render() { System.out.println("MacOS düymə render edildi"); }
    @Override
    public void onClick() { System.out.println("MacOS düyməyə click"); }
}

class MacCheckbox implements Checkbox {
    private boolean checked = true;
    @Override
    public void render() { System.out.println("MacOS checkbox render edildi"); }
    @Override
    public boolean isChecked() { return checked; }
}

// Abstract Factory
interface GUIFactory {
    Button createButton();
    Checkbox createCheckbox();
}

// Concrete Factories
class WindowsFactory implements GUIFactory {
    @Override
    public Button createButton() { return new WindowsButton(); }
    @Override
    public Checkbox createCheckbox() { return new WindowsCheckbox(); }
}

class MacFactory implements GUIFactory {
    @Override
    public Button createButton() { return new MacButton(); }
    @Override
    public Checkbox createCheckbox() { return new MacCheckbox(); }
}

// Client — factory-dən istifadə edir
class Application {
    private final Button button;
    private final Checkbox checkbox;

    Application(GUIFactory factory) {
        // Hansı OS olduğunu bilmir — yalnız interface-lərlə işləyir
        this.button = factory.createButton();
        this.checkbox = factory.createCheckbox();
    }

    public void render() {
        button.render();
        checkbox.render();
    }
}

class AbstractFactoryDemo {
    public static void main(String[] args) {
        // OS tipini müəyyən et
        String os = System.getProperty("os.name").toLowerCase();
        GUIFactory factory = os.contains("win") ? new WindowsFactory() : new MacFactory();

        Application app = new Application(factory);
        app.render();
    }
}
```

---

## Builder

**Builder** — mürəkkəb obyekti addım-addım qurmaq üçün. Çoxlu isteğe bağlı parametrlər olduqda ideal.

### Telescoping Constructor Problemi

```java
// YANLIŞ — Telescoping Constructor (məqbul deyil)
public class BadPizza {
    public BadPizza(String size) { }
    public BadPizza(String size, boolean cheese) { }
    public BadPizza(String size, boolean cheese, boolean pepperoni) { }
    public BadPizza(String size, boolean cheese, boolean pepperoni, boolean mushrooms) { }
    // Daha çox parametr əlavə etmək lazım gələrsə?
}
// new BadPizza("large", true, false, true) — hansı parametr nədir?
```

### Inner Static Builder

```java
// DOĞRU — Builder Pattern
public class Pizza {
    // Məcburi sahələr
    private final String size;
    private final String crust;

    // İsteğe bağlı sahələr
    private final boolean cheese;
    private final boolean pepperoni;
    private final boolean mushrooms;
    private final boolean onions;
    private final String sauce;

    // Private constructor — yalnız Builder istifadə edə bilər
    private Pizza(Builder builder) {
        this.size = builder.size;
        this.crust = builder.crust;
        this.cheese = builder.cheese;
        this.pepperoni = builder.pepperoni;
        this.mushrooms = builder.mushrooms;
        this.onions = builder.onions;
        this.sauce = builder.sauce;
    }

    @Override
    public String toString() {
        return String.format("Pizza{size=%s, crust=%s, cheese=%b, pepperoni=%b, " +
            "mushrooms=%b, onions=%b, sauce=%s}",
            size, crust, cheese, pepperoni, mushrooms, onions, sauce);
    }

    // Static inner Builder class
    public static class Builder {
        // Məcburi sahələr — konstruktorda verilir
        private final String size;
        private final String crust;

        // İsteğe bağlı sahələr — default dəyərlərlə
        private boolean cheese = false;
        private boolean pepperoni = false;
        private boolean mushrooms = false;
        private boolean onions = false;
        private String sauce = "tomato";

        public Builder(String size, String crust) {
            if (size == null || size.isBlank())
                throw new IllegalArgumentException("Ölçü məcburidir");
            if (crust == null || crust.isBlank())
                throw new IllegalArgumentException("Qabıq tipi məcburidir");
            this.size = size;
            this.crust = crust;
        }

        // Fluent API — hər metod Builder-i qaytarır
        public Builder cheese(boolean cheese) {
            this.cheese = cheese;
            return this;
        }

        public Builder pepperoni() {
            this.pepperoni = true;
            return this;
        }

        public Builder mushrooms() {
            this.mushrooms = true;
            return this;
        }

        public Builder onions() {
            this.onions = true;
            return this;
        }

        public Builder sauce(String sauce) {
            this.sauce = sauce;
            return this;
        }

        // build() — final validasiya + obyekt yaratma
        public Pizza build() {
            // Biznes qaydaları yoxlaması
            if (pepperoni && !cheese) {
                throw new IllegalStateException("Pepperoni üçün pendir məcburidir");
            }
            return new Pizza(this);
        }
    }

    public static void main(String[] args) {
        // Builder istifadəsi — aydın, oxunaqlı
        Pizza margherita = new Pizza.Builder("large", "thin")
            .cheese(true)
            .sauce("pesto")
            .build();

        Pizza special = new Pizza.Builder("medium", "thick")
            .cheese(true)
            .pepperoni()
            .mushrooms()
            .onions()
            .build();

        System.out.println(margherita);
        System.out.println(special);
    }
}
```

### Lombok @Builder

```java
import lombok.*;

// Lombok @Builder — boilerplate-siz Builder
@Builder
@Getter
@ToString
public class User {
    private final String username;
    private final String email;
    @Builder.Default
    private final String role = "USER"; // Default dəyər
    private final int age;
}

// İstifadə:
// User user = User.builder()
//     .username("ali")
//     .email("ali@example.com")
//     .age(25)
//     .build();
//
// User admin = User.builder()
//     .username("nigar")
//     .email("nigar@example.com")
//     .role("ADMIN")
//     .age(30)
//     .build();
```

### Java Record + Builder

```java
// Java 16+ Record — immutable data class (Builder pattern ilə)
public record Employee(
    String name,
    String department,
    int salary,
    String position
) {
    // Compact canonical constructor — validasiya
    public Employee {
        if (name == null || name.isBlank()) throw new IllegalArgumentException("Ad məcburidir");
        if (salary < 0) throw new IllegalArgumentException("Maaş mənfi ola bilməz");
    }

    // Builder inner class
    public static class Builder {
        private String name;
        private String department = "Ümumi";
        private int salary = 0;
        private String position = "İşçi";

        public Builder name(String name) { this.name = name; return this; }
        public Builder department(String dept) { this.department = dept; return this; }
        public Builder salary(int salary) { this.salary = salary; return this; }
        public Builder position(String pos) { this.position = pos; return this; }

        public Employee build() {
            return new Employee(name, department, salary, position);
        }
    }

    public static Builder builder() { return new Builder(); }
}

// İstifadə:
// Employee emp = Employee.builder()
//     .name("Əli")
//     .department("IT")
//     .salary(3000)
//     .position("Developer")
//     .build();
```

---

## Prototype

**Prototype** — mövcud obyektin klonunu yaratmaq. `clone()` metodundan istifadə edir.

### Shallow Copy vs Deep Copy

```java
import java.util.*;

public class PrototypeDemo {

    // Shallow Copy — reference tipləri paylaşılır
    static class ShallowAddress {
        String city;
        String street;

        ShallowAddress(String city, String street) {
            this.city = city;
            this.street = street;
        }
    }

    static class ShallowEmployee implements Cloneable {
        String name;
        ShallowAddress address; // Reference tip
        List<String> skills;    // Reference tip

        ShallowEmployee(String name, ShallowAddress address, List<String> skills) {
            this.name = name;
            this.address = address;
            this.skills = skills;
        }

        @Override
        public ShallowEmployee clone() {
            try {
                return (ShallowEmployee) super.clone(); // Shallow copy!
                // name (String — immutable) kopyalanır
                // address — eyni reference! (paylaşılır)
                // skills — eyni reference! (paylaşılır)
            } catch (CloneNotSupportedException e) {
                throw new RuntimeException(e);
            }
        }
    }

    // Deep Copy — bütün məzmun kopyalanır
    static class Address implements Cloneable {
        String city;
        String street;

        Address(String city, String street) {
            this.city = city;
            this.street = street;
        }

        @Override
        public Address clone() {
            try {
                return (Address) super.clone(); // String-lər immutable, OK
            } catch (CloneNotSupportedException e) {
                throw new RuntimeException(e);
            }
        }

        @Override
        public String toString() {
            return city + ", " + street;
        }
    }

    static class Employee implements Cloneable {
        String name;
        Address address;
        List<String> skills;

        Employee(String name, Address address, List<String> skills) {
            this.name = name;
            this.address = address;
            this.skills = skills;
        }

        @Override
        public Employee clone() {
            try {
                Employee cloned = (Employee) super.clone();
                // Deep copy — həm address, həm skills klonlanır
                cloned.address = this.address.clone();
                cloned.skills = new ArrayList<>(this.skills); // Yeni list, eyni String-lər
                return cloned;
            } catch (CloneNotSupportedException e) {
                throw new RuntimeException(e);
            }
        }

        @Override
        public String toString() {
            return String.format("Employee{name=%s, address=%s, skills=%s}",
                name, address, skills);
        }
    }

    public static void main(String[] args) {
        // Shallow copy problemi
        ShallowEmployee orig = new ShallowEmployee(
            "Əli",
            new ShallowAddress("Bakı", "İstiqlaliyyət"),
            new ArrayList<>(List.of("Java", "Python"))
        );

        ShallowEmployee clone = orig.clone();
        clone.address.city = "Gəncə"; // Orijinalın address-ini dəyişir!
        clone.skills.add("Go");        // Orijinalın skills-ini dəyişir!

        System.out.println(orig.address.city); // Gəncə! (paylaşılmışdı)
        System.out.println(orig.skills);       // [Java, Python, Go]! (paylaşılmışdı)

        // Deep copy — düzgün
        Employee original = new Employee(
            "Nigar",
            new Address("Bakı", "Nizami"),
            new ArrayList<>(List.of("Java", "Spring"))
        );

        Employee deepClone = original.clone();
        deepClone.address.city = "Sumqayıt"; // Yalnız klonu dəyişir
        deepClone.skills.add("Kubernetes");   // Yalnız klonu dəyişir

        System.out.println(original);   // Bakı, Spring yoxdur
        System.out.println(deepClone);  // Sumqayıt, Kubernetes var
    }
}
```

### Serialization ilə Deep Copy

```java
import java.io.*;

public class SerializationClone {

    // Serialization vasitəsilə dərin klonlama
    @SuppressWarnings("unchecked")
    public static <T extends Serializable> T deepCopy(T object) {
        try {
            // Obyekti byte stream-ə çevir
            ByteArrayOutputStream bos = new ByteArrayOutputStream();
            try (ObjectOutputStream oos = new ObjectOutputStream(bos)) {
                oos.writeObject(object);
            }

            // Byte stream-dən yeni obyekt yarat
            byte[] bytes = bos.toByteArray();
            try (ObjectInputStream ois = new ObjectInputStream(
                    new ByteArrayInputStream(bytes))) {
                return (T) ois.readObject();
            }
        } catch (IOException | ClassNotFoundException e) {
            throw new RuntimeException("Deep copy uğursuz oldu", e);
        }
    }

    public static void main(String[] args) throws Exception {
        java.util.List<String> original = new java.util.ArrayList<>(
            java.util.List.of("a", "b", "c"));

        java.util.List<String> copy = deepCopy(new java.io.Serializable() {
            // Real istifadədə Serializable implementasiya edən sinif istifadə et
        }.getClass().cast(original));

        // Alternativ: Apache Commons Lang
        // T cloned = SerializationUtils.clone(original);
    }
}
```

---

## İntervyu Sualları

**S: Singleton pattern-in thread-safe implementasiyaları hansılardır?**
C: 1) Eager initialization (static field), 2) synchronized getInstance() (yavaş), 3) Double-Checked Locking (volatile + synchronized), 4) Initialization-on-demand Holder (lazy + thread-safe, sync yox), 5) Enum Singleton (ən güvənli — reflection və serialization-a qarşı).

**S: Builder pattern nə zaman istifadə etmək lazımdır?**
C: Çoxlu parametri olan, bir çoxu isteğe bağlı olan obyektlər üçün. Telescoping constructor-dan daha oxunaqlı. İmmutable obyektlər yaratmaq üçün ideal. JavaBeans (setter-lər) ilə müqayisədə type-safe və atomik yaratma imkanı verir.

**S: Factory Method ilə Abstract Factory fərqi?**
C: Factory Method — bir məhsul yaratmaq üçün interface (alt sinif implement edir). Abstract Factory — əlaqəli məhsullar ailesi yaratmaq (Button + Checkbox kimi). Abstract Factory bir neçə Factory Method ehtiva edir.

**S: Shallow copy vs Deep copy fərqi?**
C: Shallow copy — primitiv sahələr kopyalanır, reference tipləri eyni obyekti göstərir (paylaşılmış state). Deep copy — bütün object graph rekursiv kopyalanır (tamamilə müstəqil). `Object.clone()` shallow edir — deep copy üçün override etmək lazımdır.

**S: Enum Singleton niyə üstündür?**
C: 1) JVM tərəfindən thread-safe olmasını təmin edir, 2) Serialization bozulmaz (readResolve() lazım deyil), 3) Reflection-a qarşı qorunmuşdur (constructor-a daxil olmaq olmaz), 4) Kod minimaldır. Joshua Bloch "Effective Java"-da tövsiyə edir.

**S: Prototype pattern-i nə zaman istifadə etmək lazımdır?**
C: Yaratması baha olan (DB sorğusu, hesablamalar) amma klonlaması ucuz olan obyektlər üçün. Obyektin konfigurasyonunu saxlayıb çoxlu variantlar yaratmaq lazım olanda (məsələn, template/prototype obyekt).
