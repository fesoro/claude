# 89 — Design Patterns — Structural (Struktur)

> **Seviyye:** Senior ⭐⭐⭐


## Mündəricat
1. [Adapter](#adapter)
2. [Decorator](#decorator)
3. [Proxy](#proxy)
4. [Facade](#facade)
5. [Composite](#composite)
6. [Bridge](#bridge)
7. [Flyweight](#flyweight)
8. [İntervyu Sualları](#i̇ntervyu-sualları)

---

## Adapter

**Adapter** — uyğun olmayan interface-lər arasında körpü. Köhnə kodu yeni sistemlə uyğunlaşdırmaq.

### Object Adapter (Komposisiya ilə)

```java
// Köhnə sistem (uyğunsuz interface)
class OldXMLService {
    public String getDataAsXML() {
        return "<data><user>Ali</user><age>25</age></data>";
    }
}

// Yeni sistemin gözlədiyi interface
interface JSONDataProvider {
    String getDataAsJSON();
}

// Object Adapter — OldXMLService-i komposisiya ilə istifadə edir
class XMLtoJSONAdapter implements JSONDataProvider {
    private final OldXMLService xmlService; // Has-a (komposisiya)

    XMLtoJSONAdapter(OldXMLService xmlService) {
        this.xmlService = xmlService;
    }

    @Override
    public String getDataAsJSON() {
        String xml = xmlService.getDataAsXML();
        // Sadə çevrilmə simulyasiyası
        return xml
            .replace("<data>", "{")
            .replace("</data>", "}")
            .replace("<user>", "\"user\":\"")
            .replace("</user>", "\",")
            .replace("<age>", "\"age\":")
            .replace("</age>", "");
    }
}

// Class Adapter (Miras ilə — Java-da az istifadə olunur)
class XMLtoJSONClassAdapter extends OldXMLService implements JSONDataProvider {
    @Override
    public String getDataAsJSON() {
        String xml = getDataAsXML(); // Miras alınan metod
        return "{}"; // Çevrilmə məntiqi
    }
}

// İstifadə:
class AdapterDemo {
    // Yeni sistem yalnız JSONDataProvider bilir
    static void processData(JSONDataProvider provider) {
        String json = provider.getDataAsJSON();
        System.out.println("JSON məlumat: " + json);
    }

    public static void main(String[] args) {
        OldXMLService oldService = new OldXMLService();
        JSONDataProvider adapter = new XMLtoJSONAdapter(oldService);
        processData(adapter); // Köhnə servis yeni sistemlə işləyir!
    }
}
```

### Real Dünya: Java Arrays.asList Adapter

```java
import java.util.*;

public class RealAdapterExamples {

    public static void main(String[] args) {
        // Arrays.asList — Array-ı List interface-inə adapt edir
        String[] array = {"a", "b", "c"};
        List<String> list = Arrays.asList(array); // Adapter!
        // array.get(0) yoxdur, amma list.get(0) var

        // InputStreamReader — InputStream-i Reader-ə adapt edir
        // new InputStreamReader(System.in) — byte stream → char stream

        // Collections.enumeration — Iterator → Enumeration adapter
        List<Integer> numbers = List.of(1, 2, 3);
        Enumeration<Integer> enumeration = Collections.enumeration(numbers);
        while (enumeration.hasMoreElements()) {
            System.out.print(enumeration.nextElement() + " ");
        }
    }
}
```

---

## Decorator

**Decorator** — obyektə dinamik olaraq yeni davranış əlavə etmək. Alt sinif yaratmadan funksionallıq artırır.

### Java I/O Decorator-ların Real Nümunəsi

```java
import java.io.*;

public class DecoratorDemo {

    public static void main(String[] args) throws IOException {
        // Java I/O — Decorator Pattern-ın ən klassik nümunəsi
        // InputStream → Component interface
        // FileInputStream → Concrete Component
        // FilterInputStream → Abstract Decorator
        // BufferedInputStream, GZIPInputStream → Concrete Decorators

        InputStream plain = new FileInputStream("/tmp/data.txt");
        InputStream buffered = new BufferedInputStream(plain, 8192);  // Buffer əlavə et
        // InputStream gzipped = new GZIPInputStream(buffered);       // Decompress əlavə et
        // InputStream counted = new CountingInputStream(buffered);   // Sayma əlavə et

        // Hər layer birinin üstünə qurulur — dinamik davranış!
    }
}
```

### Öz Decorator-larımız

```java
// Component interface
interface Coffee {
    String getDescription();
    double getCost();
}

// Concrete Component
class SimpleCoffee implements Coffee {
    @Override
    public String getDescription() { return "Sadə qəhvə"; }
    @Override
    public double getCost() { return 1.00; }
}

// Abstract Decorator
abstract class CoffeeDecorator implements Coffee {
    protected final Coffee coffee; // Decorated component

    CoffeeDecorator(Coffee coffee) {
        this.coffee = coffee;
    }

    @Override
    public String getDescription() { return coffee.getDescription(); }
    @Override
    public double getCost() { return coffee.getCost(); }
}

// Concrete Decorators
class MilkDecorator extends CoffeeDecorator {
    MilkDecorator(Coffee coffee) { super(coffee); }

    @Override
    public String getDescription() {
        return coffee.getDescription() + ", süd";
    }
    @Override
    public double getCost() { return coffee.getCost() + 0.25; }
}

class SugarDecorator extends CoffeeDecorator {
    SugarDecorator(Coffee coffee) { super(coffee); }

    @Override
    public String getDescription() {
        return coffee.getDescription() + ", şəkər";
    }
    @Override
    public double getCost() { return coffee.getCost() + 0.10; }
}

class VanillaDecorator extends CoffeeDecorator {
    VanillaDecorator(Coffee coffee) { super(coffee); }

    @Override
    public String getDescription() {
        return coffee.getDescription() + ", vanil";
    }
    @Override
    public double getCost() { return coffee.getCost() + 0.50; }
}

class CoffeeShop {
    public static void main(String[] args) {
        // Sadə qəhvə
        Coffee coffee = new SimpleCoffee();
        System.out.printf("%s → %.2f AZN%n", coffee.getDescription(), coffee.getCost());

        // Süd əlavə et
        coffee = new MilkDecorator(coffee);
        System.out.printf("%s → %.2f AZN%n", coffee.getDescription(), coffee.getCost());

        // Şəkər əlavə et
        coffee = new SugarDecorator(coffee);
        System.out.printf("%s → %.2f AZN%n", coffee.getDescription(), coffee.getCost());

        // Vanil əlavə et
        coffee = new VanillaDecorator(new SugarDecorator(new MilkDecorator(
            new SimpleCoffee())));
        System.out.printf("%s → %.2f AZN%n", coffee.getDescription(), coffee.getCost());
    }
}
```

---

## Proxy

**Proxy** — başqa bir obyektin yerinə keçər. Access control, lazy loading, logging, caching üçün.

### Static Proxy

```java
interface ImageLoader {
    void loadImage(String url);
    void display();
}

// Real obyekt
class RealImageLoader implements ImageLoader {
    private final String url;
    private byte[] imageData;

    RealImageLoader(String url) {
        this.url = url;
    }

    @Override
    public void loadImage(String url) {
        System.out.println("Şəkil yüklənir: " + url);
        this.imageData = new byte[1024]; // Simulyasiya
        System.out.println("Yükləndi: " + url);
    }

    @Override
    public void display() {
        System.out.println("Şəkil göstərilir");
    }
}

// Proxy — Lazy Loading
class LazyImageProxy implements ImageLoader {
    private final String url;
    private RealImageLoader realLoader; // Lazy — hələ yaradılmayıb

    LazyImageProxy(String url) {
        this.url = url;
        System.out.println("Proxy yaradıldı (real loader yox hələ): " + url);
    }

    @Override
    public void loadImage(String url) {
        // Lazım olanda yarat
        if (realLoader == null) {
            realLoader = new RealImageLoader(url);
        }
        realLoader.loadImage(url);
    }

    @Override
    public void display() {
        if (realLoader == null) {
            System.out.println("Şəkil hələ yüklənməyib!");
            return;
        }
        realLoader.display();
    }
}

// Protection Proxy — Access Control
class SecureImageProxy implements ImageLoader {
    private final RealImageLoader realLoader;
    private final String userRole;

    SecureImageProxy(String url, String userRole) {
        this.realLoader = new RealImageLoader(url);
        this.userRole = userRole;
    }

    @Override
    public void loadImage(String url) {
        if (!"ADMIN".equals(userRole) && !"USER".equals(userRole)) {
            throw new SecurityException("Şəkil yükləmə icazəsi yoxdur: " + userRole);
        }
        realLoader.loadImage(url);
    }

    @Override
    public void display() {
        realLoader.display();
    }
}
```

### Dynamic Proxy (Reflection ilə)

```java
import java.lang.reflect.*;

interface DataRepository {
    String findById(int id);
    void save(String data);
}

class DataRepositoryImpl implements DataRepository {
    @Override
    public String findById(int id) { return "Data-" + id; }
    @Override
    public void save(String data) { System.out.println("Saxlanıldı: " + data); }
}

class ProxyDemo {
    public static void main(String[] args) {
        DataRepository real = new DataRepositoryImpl();

        // Caching + Logging proxy
        DataRepository proxy = (DataRepository) Proxy.newProxyInstance(
            real.getClass().getClassLoader(),
            new Class[]{DataRepository.class},
            (p, method, methodArgs) -> {
                System.out.println("[Proxy] Metod: " + method.getName());
                long start = System.nanoTime();

                Object result = method.invoke(real, methodArgs);

                System.out.printf("[Proxy] Müddət: %.3f ms%n",
                    (System.nanoTime() - start) / 1_000_000.0);
                return result;
            }
        );

        String data = proxy.findById(42);
        System.out.println("Nəticə: " + data);
        proxy.save("yeni data");
    }
}
```

---

## Facade

**Facade** — mürəkkəb altsistemi sadə interface arxasında gizlədir.

```java
// Mürəkkəb alt sistemlər
class OrderValidator {
    public boolean validate(String orderId) {
        System.out.println("Sifariş doğrulanır: " + orderId);
        return true;
    }
}

class InventorySystem {
    public boolean checkStock(String productId, int quantity) {
        System.out.println("Stok yoxlanılır: " + productId + " x" + quantity);
        return true;
    }
    public void reserveStock(String productId, int quantity) {
        System.out.println("Stok rezerv edildi: " + productId);
    }
}

class PaymentGateway {
    public boolean processPayment(String orderId, double amount) {
        System.out.println("Ödəniş emal olunur: " + orderId + " - " + amount + " AZN");
        return true;
    }
}

class ShippingService {
    public String createShipment(String orderId, String address) {
        System.out.println("Çatdırılma yaradılır: " + orderId + " → " + address);
        return "TRACK-" + orderId;
    }
}

class NotificationSystem {
    public void sendConfirmation(String email, String trackingCode) {
        System.out.println("Email göndərildi: " + email + " (İzləmə: " + trackingCode + ")");
    }
}

// FACADE — bütün mürəkkəbliyi gizlədir
class OrderFacade {
    private final OrderValidator validator = new OrderValidator();
    private final InventorySystem inventory = new InventorySystem();
    private final PaymentGateway payment = new PaymentGateway();
    private final ShippingService shipping = new ShippingService();
    private final NotificationSystem notification = new NotificationSystem();

    // Sadə bir metod — bütün mürəkkəb prosesi koordinasiya edir
    public String placeOrder(String orderId, String productId, int qty,
                             double amount, String address, String email) {
        // 1. Doğrula
        if (!validator.validate(orderId)) throw new RuntimeException("Etibarsız sifariş");

        // 2. Stok yoxla
        if (!inventory.checkStock(productId, qty)) throw new RuntimeException("Stok yoxdur");

        // 3. Ödəniş et
        if (!payment.processPayment(orderId, amount)) throw new RuntimeException("Ödəniş uğursuz");

        // 4. Stok rezerv et
        inventory.reserveStock(productId, qty);

        // 5. Çatdırılma yarat
        String trackingCode = shipping.createShipment(orderId, address);

        // 6. Email göndər
        notification.sendConfirmation(email, trackingCode);

        return trackingCode;
    }
}

// İstifadəçi yalnız Facade-i bilir
class FacadeDemo {
    public static void main(String[] args) {
        OrderFacade facade = new OrderFacade();

        // 1 sadə çağırış — bütün alt sistemlər işləyir
        String tracking = facade.placeOrder(
            "ORD-001", "PROD-42", 2, 59.99,
            "Bakı, Nizami 42", "ali@email.com"
        );

        System.out.println("Sifariş tamamlandı! İzləmə kodu: " + tracking);
    }
}
```

---

## Composite

**Composite** — obyektləri ağac strukturunda yığmaq. Tək obyekt (Leaf) ilə qrup (Composite) eyni interface-dən istifadə edir.

```java
import java.util.*;

// Component interface
interface FileSystemItem {
    String getName();
    long getSize();
    void print(String indent);
}

// Leaf — yaprak nodu (uşaqsız)
class File implements FileSystemItem {
    private final String name;
    private final long size;

    File(String name, long size) {
        this.name = name;
        this.size = size;
    }

    @Override public String getName() { return name; }
    @Override public long getSize() { return size; }

    @Override
    public void print(String indent) {
        System.out.printf("%s📄 %s (%d KB)%n", indent, name, size);
    }
}

// Composite — uşaqları olan nod
class Directory implements FileSystemItem {
    private final String name;
    private final List<FileSystemItem> children = new ArrayList<>();

    Directory(String name) { this.name = name; }

    public void add(FileSystemItem item) { children.add(item); }
    public void remove(FileSystemItem item) { children.remove(item); }

    @Override public String getName() { return name; }

    @Override
    public long getSize() {
        // Rekursiv olaraq bütün uşaqların ölçüsünü cəmlə
        return children.stream().mapToLong(FileSystemItem::getSize).sum();
    }

    @Override
    public void print(String indent) {
        System.out.printf("%s📁 %s/ (%d KB)%n", indent, name, getSize());
        children.forEach(child -> child.print(indent + "  "));
    }
}

class CompositeDemo {
    public static void main(String[] args) {
        // Fayl sistemi ağacı yarat
        Directory root = new Directory("root");

        Directory documents = new Directory("documents");
        documents.add(new File("resume.pdf", 150));
        documents.add(new File("cover_letter.docx", 45));

        Directory projects = new Directory("projects");
        Directory javaProject = new Directory("java-app");
        javaProject.add(new File("Main.java", 12));
        javaProject.add(new File("pom.xml", 5));
        projects.add(javaProject);
        projects.add(new File("notes.txt", 3));

        root.add(documents);
        root.add(projects);
        root.add(new File("README.md", 8));

        // Tək interface ilə hamısını emal et
        root.print("");
        System.out.println("Ümumi ölçü: " + root.getSize() + " KB");
    }
}
```

---

## Bridge

**Bridge** — abstraksiyadan implementasiyanı ayırır. Hər ikisi müstəqil şəkildə inkişaf edə bilər.

```java
// Implementasiya interface
interface MessageSender {
    void send(String recipient, String message);
}

// Concrete Implementations
class EmailSender implements MessageSender {
    @Override
    public void send(String recipient, String message) {
        System.out.println("Email → " + recipient + ": " + message);
    }
}

class SMSSender implements MessageSender {
    @Override
    public void send(String recipient, String message) {
        System.out.println("SMS → " + recipient + ": " + message);
    }
}

// Abstraction (Bridge — sender-ə reference saxlayır)
abstract class Notification {
    protected final MessageSender sender; // Bridge!

    Notification(MessageSender sender) {
        this.sender = sender;
    }

    abstract void notify(String recipient, String message);
}

// Refined Abstractions
class UrgentNotification extends Notification {
    UrgentNotification(MessageSender sender) { super(sender); }

    @Override
    public void notify(String recipient, String message) {
        sender.send(recipient, "[TƏCİLİ] " + message.toUpperCase());
    }
}

class NormalNotification extends Notification {
    NormalNotification(MessageSender sender) { super(sender); }

    @Override
    public void notify(String recipient, String message) {
        sender.send(recipient, message);
    }
}

class BridgeDemo {
    public static void main(String[] args) {
        // Abstraksiya × İmplementasiya kombinasiyaları
        Notification urgentEmail = new UrgentNotification(new EmailSender());
        Notification normalSMS = new NormalNotification(new SMSSender());
        Notification urgentSMS = new UrgentNotification(new SMSSender());

        urgentEmail.notify("ali@email.com", "server çökdü");
        normalSMS.notify("+994501234567", "Sifariş çatdırıldı");
        urgentSMS.notify("+994509876543", "ödəniş uğursuz");
    }
}
```

---

## Flyweight

**Flyweight** — çoxlu eyni tip obyektlər arasında ortaq vəziyyəti paylaşır. Yaddaşı azaldır.

```java
import java.util.*;

// Flyweight — intrinsic state (paylaşılan, dəyişməyən)
record CharacterStyle(String font, int size, String color) {
    // Bu məlumatlar paylaşılır — hər unikal stil üçün bir obyekt
}

// Flyweight Factory — cache ilə
class CharacterStyleFactory {
    private static final Map<String, CharacterStyle> cache = new HashMap<>();

    public static CharacterStyle getStyle(String font, int size, String color) {
        String key = font + "-" + size + "-" + color;
        return cache.computeIfAbsent(key,
            k -> new CharacterStyle(font, size, color));
    }

    public static int getCacheSize() { return cache.size(); }
}

// Context — extrinsic state (hər obyekt üçün unikal)
class Character {
    private final char symbol;       // Extrinsic — unikal
    private final int positionX;     // Extrinsic — unikal
    private final int positionY;     // Extrinsic — unikal
    private final CharacterStyle style; // Intrinsic — paylaşılan Flyweight!

    Character(char symbol, int x, int y, CharacterStyle style) {
        this.symbol = symbol;
        this.positionX = x;
        this.positionY = y;
        this.style = style;
    }

    void render() {
        System.out.printf("'%c' at (%d,%d) [%s, %dpt, %s]%n",
            symbol, positionX, positionY,
            style.font(), style.size(), style.color());
    }
}

class FlyweightDemo {
    public static void main(String[] args) {
        // 100,000 simvol render et — yalnız bir neçə unikal stil
        List<Character> document = new ArrayList<>();
        CharacterStyle normalStyle = CharacterStyleFactory.getStyle("Arial", 12, "black");
        CharacterStyle boldStyle = CharacterStyleFactory.getStyle("Arial-Bold", 14, "black");
        CharacterStyle headingStyle = CharacterStyleFactory.getStyle("Arial-Bold", 20, "blue");

        // Sənəd yaradırıq
        String[] words = {"Salam", "Dünya", "Java"};
        int x = 0;
        for (int y = 0; y < 100; y++) {
            for (char c : words[y % words.length].toCharArray()) {
                CharacterStyle style = (y == 0) ? headingStyle : normalStyle;
                document.add(new Character(c, x++, y, style));
            }
        }

        System.out.println("Simvol sayı: " + document.size());
        System.out.println("Unikal stil sayı: " + CharacterStyleFactory.getCacheSize());
        // 3 unikal stil, 100+ simvol!

        // Yaddaş qənaəti hesablaması:
        // Style olmadan: 100,000 × (font + size + color) = çox yaddaş
        // Flyweight ilə: yalnız 3 style obyekti paylaşılır

        document.stream().limit(5).forEach(Character::render);
    }
}
```

---

## İntervyu Sualları

**S: Adapter vs Facade fərqi nədir?**
C: Adapter uyğunsuz iki interface arasında çevirir (biri → digəri). Facade mürəkkəb altsistemi sadə interface arxasında gizlədir (çoxluları → birləşdirilmiş). Adapter "uyğunlaşdırır", Facade "sadələşdirir".

**S: Decorator vs Inheritance fərqi?**
C: Inheritance compile-time, statik. Decorator runtime, dinamik — istənilən sayda "qat" əlavə etmək olar. Inheritance bütün alt sinif üçün sabit davranış əlavə edir. Decorator hər instance üçün fərqli kombinasiya. Java I/O sistemi Decorator nümunəsidir.

**S: Static Proxy vs Dynamic Proxy fərqi?**
C: Static Proxy — hər interface üçün ayrıca proxy class yazılır. Dynamic Proxy — `Proxy.newProxyInstance()` ilə runtime-da yaradılır, bir `InvocationHandler` bütün metodları tutur. Spring AOP, Hibernate lazy loading dynamic proxy istifadə edir.

**S: Composite Pattern harada istifadə olunur?**
C: File system (fayl/qovluq), GUI widget-lər (Button/Panel), menyu strukturları, XML/HTML DOM, matematikdə expression ağacları. Tək obyekt ilə qrup eyni interface-dən istifadə edir — client fərqinə baxmır.

**S: Flyweight Pattern-in məqsədi nədir?**
C: Çoxlu eyni tip obyektlər yaradılırsa, paylaşıla bilən (intrinsic) state-i bir yerdə saxlayıb yaddaşı azaltmaq. Java String pool, Integer.valueOf() cache (-128 to 127) Flyweight nümunələrdir. Font, renk, texture kimi paylaşıla bilən datalar üçün istifadə olunur.

**S: Bridge Pattern niyə Adapter-dən fərqlidir?**
C: Adapter köhnə kodu yeni interface-ə uyğunlaşdırır — mövcud problem həll edir. Bridge dizayn zamanı abstraksiya ilə implementasiyanı ayırır — gələcək genişlənməni nəzərə alır. Bridge forward-looking, Adapter backward-compatible.

**S: Java String Pool Flyweight pattern-dən necə istifadə edir?**
C: `"salam"` literal-ları pool-da saxlanılır. Eyni literal bir neçə dəfə yazılsa, eyni String obyektinə istinad edir. `"salam" == "salam"` → true (pool). `new String("salam") == new String("salam")` → false (heap). `String.intern()` string-i pool-a əlavə edir.
