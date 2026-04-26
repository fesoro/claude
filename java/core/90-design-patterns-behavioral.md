# 90 — Design Patterns — Behavioral (Davranış)

> **Seviyye:** Senior ⭐⭐⭐


## Mündəricat
1. [Strategy](#strategy)
2. [Observer](#observer)
3. [Command](#command)
4. [Template Method](#template-method)
5. [Chain of Responsibility](#chain-of-responsibility)
6. [Iterator](#iterator)
7. [State](#state)
8. [Visitor](#visitor)
9. [İntervyu Sualları](#i̇ntervyu-sualları)

---

## Strategy

**Strategy** — alqoritmlərin ailəsini müəyyən edir, hər birini kapsüllər və mübadilə edilə bilən edir. Alqoritmi istifadə edən clientdən müstəqil edir.

```java
import java.util.*;

// Strategy interface
interface SortStrategy {
    void sort(int[] data);
}

// Concrete Strategies
class BubbleSortStrategy implements SortStrategy {
    @Override
    public void sort(int[] data) {
        System.out.println("Bubble sort ilə sıralama...");
        for (int i = 0; i < data.length - 1; i++) {
            for (int j = 0; j < data.length - 1 - i; j++) {
                if (data[j] > data[j + 1]) {
                    int temp = data[j];
                    data[j] = data[j + 1];
                    data[j + 1] = temp;
                }
            }
        }
    }
}

class QuickSortStrategy implements SortStrategy {
    @Override
    public void sort(int[] data) {
        System.out.println("Quick sort ilə sıralama...");
        quickSort(data, 0, data.length - 1);
    }

    private void quickSort(int[] arr, int low, int high) {
        if (low < high) {
            int pivotIndex = partition(arr, low, high);
            quickSort(arr, low, pivotIndex - 1);
            quickSort(arr, pivotIndex + 1, high);
        }
    }

    private int partition(int[] arr, int low, int high) {
        int pivot = arr[high];
        int i = low - 1;
        for (int j = low; j < high; j++) {
            if (arr[j] <= pivot) {
                i++;
                int temp = arr[i]; arr[i] = arr[j]; arr[j] = temp;
            }
        }
        int temp = arr[i + 1]; arr[i + 1] = arr[high]; arr[high] = temp;
        return i + 1;
    }
}

// Context — strategy-dən istifadə edir
class Sorter {
    private SortStrategy strategy; // Dəyişən alqoritm

    Sorter(SortStrategy strategy) {
        this.strategy = strategy;
    }

    // Runtime-da alqoritmi dəyiş
    public void setStrategy(SortStrategy strategy) {
        this.strategy = strategy;
    }

    public void sort(int[] data) {
        strategy.sort(data); // Delegate
    }
}

// Java 8+ Lambda ilə Strategy (funksional yanaşma)
class StrategyWithLambda {
    public static void main(String[] args) {
        int[] data = {5, 2, 8, 1, 9, 3};

        Sorter sorter = new Sorter(new BubbleSortStrategy());
        sorter.sort(data.clone());

        // Alqoritmi dəyiş
        sorter.setStrategy(new QuickSortStrategy());
        sorter.sort(data.clone());

        // Lambda ilə (funksional interface)
        SortStrategy javaSort = arr -> Arrays.sort(arr);
        sorter.setStrategy(javaSort);
        sorter.sort(data.clone());

        // Comparator da Strategy pattern-dir!
        List<String> names = new ArrayList<>(List.of("Əli", "Nigar", "Kamran", "Aytən"));

        // Müxtəlif sıralama strategiyaları
        names.sort(String::compareTo);                 // Əlifba sırası
        names.sort(Comparator.comparingInt(String::length)); // Uzunluğa görə
        names.sort(Comparator.reverseOrder());          // Tərsinə
    }
}
```

---

## Observer

**Observer** — bir obyekt dəyişdikdə asılı bütün obyektlərə avtomatik xəbər verir.

```java
import java.util.*;
import java.util.function.*;

// Observer interface (Listener)
interface StockObserver {
    void onPriceChange(String symbol, double oldPrice, double newPrice);
}

// Subject (Observable)
class StockMarket {
    private final Map<String, Double> prices = new HashMap<>();
    private final List<StockObserver> observers = new ArrayList<>();

    public void addObserver(StockObserver observer) {
        observers.add(observer);
    }

    public void removeObserver(StockObserver observer) {
        observers.remove(observer);
    }

    public void updatePrice(String symbol, double newPrice) {
        double oldPrice = prices.getOrDefault(symbol, 0.0);
        prices.put(symbol, newPrice);

        // Bütün observer-ləri xəbərdar et
        if (oldPrice != newPrice) {
            notifyObservers(symbol, oldPrice, newPrice);
        }
    }

    private void notifyObservers(String symbol, double oldPrice, double newPrice) {
        observers.forEach(obs -> obs.onPriceChange(symbol, oldPrice, newPrice));
    }

    public double getPrice(String symbol) {
        return prices.getOrDefault(symbol, 0.0);
    }
}

// Concrete Observers
class PriceAlertObserver implements StockObserver {
    private final String symbol;
    private final double threshold;

    PriceAlertObserver(String symbol, double threshold) {
        this.symbol = symbol;
        this.threshold = threshold;
    }

    @Override
    public void onPriceChange(String sym, double oldPrice, double newPrice) {
        if (sym.equals(this.symbol) && newPrice > threshold) {
            System.out.printf("[XƏBƏRDARLIQ] %s: %.2f → %.2f (həddi keçdi: %.2f)%n",
                sym, oldPrice, newPrice, threshold);
        }
    }
}

class TradingBotObserver implements StockObserver {
    @Override
    public void onPriceChange(String symbol, double oldPrice, double newPrice) {
        double change = (newPrice - oldPrice) / oldPrice * 100;
        if (change > 5) {
            System.out.printf("[BOT] %s: +%.1f%% artdı → SAT!%n", symbol, change);
        } else if (change < -5) {
            System.out.printf("[BOT] %s: %.1f%% azaldı → AL!%n", symbol, change);
        }
    }
}

// Java standart Observer-lər (köhnə — EventListener pattern)
class ObserverDemo {
    public static void main(String[] args) {
        StockMarket market = new StockMarket();

        // Observer-ləri qeyd et
        market.addObserver(new PriceAlertObserver("AAPL", 150.0));
        market.addObserver(new TradingBotObserver());

        // Lambda ilə observer (Java 8+)
        market.addObserver((symbol, old, newP) ->
            System.out.printf("[LOG] %s dəyişdi: %.2f → %.2f%n", symbol, old, newP));

        // Qiymət dəyişiklikləri
        market.updatePrice("AAPL", 145.0);
        market.updatePrice("AAPL", 155.0); // Xəbərdarlıq + Bot siqnalı
        market.updatePrice("GOOGL", 2800.0);
        market.updatePrice("GOOGL", 2600.0); // Bot siqnalı
    }
}

// Java EventListener Pattern (GUI, Spring Events)
class EventListenerExample {
    // java.util.EventObject
    static class UserLoginEvent extends java.util.EventObject {
        private final String username;

        UserLoginEvent(Object source, String username) {
            super(source);
            this.username = username;
        }

        public String getUsername() { return username; }
    }

    // EventListener interface
    interface UserLoginListener extends java.util.EventListener {
        void onUserLogin(UserLoginEvent event);
    }
}
```

---

## Command

**Command** — sorğuları obyekt kimi kapsüllər. Undo/Redo, quyruq, loglama üçün istifadə olunur.

```java
import java.util.*;

// Command interface
interface Command {
    void execute();
    void undo();
}

// Receiver — real iş görən
class TextEditor {
    private final StringBuilder text = new StringBuilder();

    public void insertText(int position, String str) {
        text.insert(position, str);
    }

    public void deleteText(int position, int length) {
        text.delete(position, position + length);
    }

    public String getText() { return text.toString(); }
}

// Concrete Commands
class InsertCommand implements Command {
    private final TextEditor editor;
    private final int position;
    private final String text;

    InsertCommand(TextEditor editor, int position, String text) {
        this.editor = editor;
        this.position = position;
        this.text = text;
    }

    @Override
    public void execute() {
        editor.insertText(position, text);
        System.out.println("Əlavə edildi: '" + text + "' mövqe " + position);
    }

    @Override
    public void undo() {
        editor.deleteText(position, text.length());
        System.out.println("Geri alındı: əlavə (" + text + ")");
    }
}

class DeleteCommand implements Command {
    private final TextEditor editor;
    private final int position;
    private final int length;
    private String deletedText; // Undo üçün saxla

    DeleteCommand(TextEditor editor, int position, int length) {
        this.editor = editor;
        this.position = position;
        this.length = length;
    }

    @Override
    public void execute() {
        deletedText = editor.getText().substring(position, position + length);
        editor.deleteText(position, length);
        System.out.println("Silindi: '" + deletedText + "'");
    }

    @Override
    public void undo() {
        editor.insertText(position, deletedText);
        System.out.println("Geri alındı: silmə (" + deletedText + ")");
    }
}

// Invoker — history idarə edir
class CommandHistory {
    private final Deque<Command> history = new ArrayDeque<>();
    private final Deque<Command> redoStack = new ArrayDeque<>();

    public void execute(Command command) {
        command.execute();
        history.push(command);
        redoStack.clear(); // Yeni əmr redo stack-i silir
    }

    public boolean undo() {
        if (history.isEmpty()) {
            System.out.println("Geri alınacaq əmr yoxdur");
            return false;
        }
        Command command = history.pop();
        command.undo();
        redoStack.push(command);
        return true;
    }

    public boolean redo() {
        if (redoStack.isEmpty()) {
            System.out.println("Yenidən ediləcək əmr yoxdur");
            return false;
        }
        Command command = redoStack.pop();
        command.execute();
        history.push(command);
        return true;
    }
}

class CommandDemo {
    public static void main(String[] args) {
        TextEditor editor = new TextEditor();
        CommandHistory history = new CommandHistory();

        // Əmrlər icra et
        history.execute(new InsertCommand(editor, 0, "Salam"));
        System.out.println("Mətn: " + editor.getText());

        history.execute(new InsertCommand(editor, 5, ", Dünya"));
        System.out.println("Mətn: " + editor.getText());

        history.execute(new DeleteCommand(editor, 5, 7));
        System.out.println("Mətn: " + editor.getText());

        // Undo
        history.undo();
        System.out.println("Undo sonra: " + editor.getText());

        history.undo();
        System.out.println("Undo sonra: " + editor.getText());

        // Redo
        history.redo();
        System.out.println("Redo sonra: " + editor.getText());
    }
}
```

---

## Template Method

**Template Method** — alqoritmin skeletini abstract class-da müəyyən edir. Alt siniflər bəzi addımları override edir.

```java
// Abstract class — alqoritm skeleti
abstract class DataProcessor {

    // Template Method — bu metod override edilmir!
    public final void process(String dataSource) {
        // Alqoritm addımları — sıra dəyişmir
        String rawData = readData(dataSource);     // 1. Oxu
        String validated = validateData(rawData);  // 2. Doğrula
        String processed = processData(validated); // 3. Emal et
        saveResult(processed);                      // 4. Saxla
        cleanup();                                  // 5. Təmizlə (optional)
    }

    // Abstract addımlar — alt siniflər implement etməlidir
    protected abstract String readData(String source);
    protected abstract String processData(String data);

    // Hook methods — alt siniflər istəsə override edə bilər (default davranış var)
    protected String validateData(String data) {
        if (data == null || data.isBlank()) {
            throw new IllegalArgumentException("Məlumat boş ola bilməz");
        }
        System.out.println("Validasiya keçdi");
        return data;
    }

    protected void saveResult(String result) {
        System.out.println("Nəticə saxlanıldı: " + result);
    }

    protected void cleanup() {
        // Default: heçnə etmə (hook)
        System.out.println("Təmizləndi (default)");
    }
}

// Concrete Implementations
class CSVDataProcessor extends DataProcessor {
    @Override
    protected String readData(String source) {
        System.out.println("CSV fayl oxunur: " + source);
        return "ad,yaş\nƏli,25\nNigar,30"; // Simulyasiya
    }

    @Override
    protected String processData(String data) {
        System.out.println("CSV emal olunur...");
        String[] lines = data.split("\n");
        return "İşlənmiş " + (lines.length - 1) + " sətir";
    }

    @Override
    protected void cleanup() {
        System.out.println("CSV fayl bağlandı");
    }
}

class JSONDataProcessor extends DataProcessor {
    @Override
    protected String readData(String source) {
        System.out.println("JSON API çağırılır: " + source);
        return "{\"users\":[{\"name\":\"Kənan\"}]}"; // Simulyasiya
    }

    @Override
    protected String processData(String data) {
        System.out.println("JSON parse olunur...");
        return "JSON məlumat emal edildi";
    }
    // cleanup() override edilmir — default istifadə olunur
}

// JUnit-ün setUp/tearDown da Template Method nümunəsidir
abstract class BaseTest {
    // setUp → test metod → tearDown — Template Method-a oxşar
    public void runTest(String testName) {
        setUp();
        try {
            System.out.println("Test icra olunur: " + testName);
            doTest();
        } finally {
            tearDown();
        }
    }

    protected void setUp() { System.out.println("setUp"); }
    protected abstract void doTest();
    protected void tearDown() { System.out.println("tearDown"); }
}

class TemplateMethodDemo {
    public static void main(String[] args) {
        System.out.println("=== CSV Prosessor ===");
        new CSVDataProcessor().process("data.csv");

        System.out.println("\n=== JSON Prosessor ===");
        new JSONDataProcessor().process("https://api.example.com/users");
    }
}
```

---

## Chain of Responsibility

**Chain of Responsibility** — sorğunu bir zəncirdən keçirir. Hər handler sorğunu işləyə ya da növbətiyə ötürə bilər.

```java
// Servlet Filter-lər — COR pattern-i nümunəsidir!

// Handler interface
abstract class RequestHandler {
    protected RequestHandler next;

    public RequestHandler setNext(RequestHandler next) {
        this.next = next;
        return next; // Method chaining üçün
    }

    protected void passToNext(HttpRequest request) {
        if (next != null) next.handle(request);
    }

    public abstract void handle(HttpRequest request);
}

// Sadə request temsil
record HttpRequest(String method, String path, String authToken, String body) {}

// Concrete Handlers
class AuthenticationHandler extends RequestHandler {
    @Override
    public void handle(HttpRequest request) {
        System.out.println("[Auth] Token yoxlanılır...");

        if (request.authToken() == null || request.authToken().isEmpty()) {
            System.out.println("[Auth] RƏDD EDİLDİ: Token yoxdur");
            return; // Zənciri kəs
        }

        if (!request.authToken().startsWith("Bearer ")) {
            System.out.println("[Auth] RƏDD EDİLDİ: Yanlış token formatı");
            return;
        }

        System.out.println("[Auth] Token qəbul edildi");
        passToNext(request); // Növbəti handler-ə ötür
    }
}

class RateLimitHandler extends RequestHandler {
    private final Map<String, Integer> requestCounts = new java.util.HashMap<>();
    private static final int MAX_REQUESTS = 10;

    @Override
    public void handle(HttpRequest request) {
        String token = request.authToken();
        int count = requestCounts.merge(token, 1, Integer::sum);

        if (count > MAX_REQUESTS) {
            System.out.println("[RateLimit] RƏDD EDİLDİ: Limit aşıldı (" + count + "/" + MAX_REQUESTS + ")");
            return;
        }

        System.out.println("[RateLimit] OK (" + count + "/" + MAX_REQUESTS + ")");
        passToNext(request);
    }
}

class LoggingHandler extends RequestHandler {
    @Override
    public void handle(HttpRequest request) {
        System.out.printf("[Log] %s %s%n", request.method(), request.path());
        passToNext(request); // Log edib keçir
    }
}

class BusinessLogicHandler extends RequestHandler {
    @Override
    public void handle(HttpRequest request) {
        System.out.println("[Business] Sorğu işlənir: " + request.path());
        System.out.println("[Business] Nəticə qaytarıldı: 200 OK");
    }
}

class CORDemo {
    public static void main(String[] args) {
        // Zənciri qur
        RequestHandler auth = new AuthenticationHandler();
        RequestHandler rateLimit = new RateLimitHandler();
        RequestHandler logging = new LoggingHandler();
        RequestHandler business = new BusinessLogicHandler();

        // Method chaining ilə zənciri bağla
        auth.setNext(rateLimit)
            .setNext(logging)
            .setNext(business);

        System.out.println("=== Etibarlı sorğu ===");
        auth.handle(new HttpRequest("GET", "/api/users", "Bearer token123", null));

        System.out.println("\n=== Token-siz sorğu ===");
        auth.handle(new HttpRequest("POST", "/api/data", null, "{}"));
    }
}
```

---

## Iterator

**Iterator** — kolleksiyadan asılı olmadan elementləri ardıcıl keçmək.

```java
import java.util.*;

// Java öz Iterator interface-ini təqdim edir
// java.util.Iterator<E> { hasNext(), next(), remove() }
// java.lang.Iterable<E> { iterator() } → for-each döngüsü

// Xüsusi tree keçidi üçün Iterator
class BinaryTree<T> implements Iterable<T> {

    static class Node<T> {
        T value;
        Node<T> left, right;

        Node(T value) { this.value = value; }
    }

    private Node<T> root;

    public void insert(T value) {
        // Sadə BST insert (T Comparable olmalıdır — real impl-də)
        root = insertNode(root, value);
    }

    private Node<T> insertNode(Node<T> node, T value) {
        if (node == null) return new Node<>(value);
        // Sadələşdirilmiş (real-da Comparable istifadə et)
        return node; // Placeholder
    }

    // In-order Iterator
    @Override
    public Iterator<T> iterator() {
        return new InOrderIterator();
    }

    class InOrderIterator implements Iterator<T> {
        private final Deque<Node<T>> stack = new ArrayDeque<>();

        InOrderIterator() {
            pushLeft(root);
        }

        private void pushLeft(Node<T> node) {
            while (node != null) {
                stack.push(node);
                node = node.left;
            }
        }

        @Override
        public boolean hasNext() { return !stack.isEmpty(); }

        @Override
        public T next() {
            if (!hasNext()) throw new NoSuchElementException();
            Node<T> node = stack.pop();
            pushLeft(node.right);
            return node.value;
        }
    }
}

// For-each ilə istifadə (Iterable implement etdiyimiz üçün)
class IteratorDemo {
    public static void main(String[] args) {
        // Java-nın Iterator istifadəsi
        List<String> list = new ArrayList<>(List.of("a", "b", "c", "d"));

        // Explicit iterator
        Iterator<String> it = list.iterator();
        while (it.hasNext()) {
            String s = it.next();
            if (s.equals("b")) {
                it.remove(); // ConcurrentModificationException olmadan!
            }
        }
        System.out.println("Listdən 'b' silindi: " + list);

        // For-each (daxildə iterator)
        for (String s : list) {
            System.out.print(s + " ");
        }
    }
}
```

---

## State

**State** — obyektin daxili vəziyyəti dəyişdikdə davranışı dəyişir.

```java
// Traffic Light - vəziyyət maşını
interface TrafficLightState {
    void handle(TrafficLight context);
    String getColor();
}

class RedState implements TrafficLightState {
    @Override
    public void handle(TrafficLight context) {
        System.out.println("QIRMIZI: DAYAN! (30 saniyə)");
        context.setState(new GreenState());
    }
    @Override
    public String getColor() { return "QIRMIZI"; }
}

class GreenState implements TrafficLightState {
    @Override
    public void handle(TrafficLight context) {
        System.out.println("YAŞIL: HƏRƏKƏT ET! (25 saniyə)");
        context.setState(new YellowState());
    }
    @Override
    public String getColor() { return "YAŞIL"; }
}

class YellowState implements TrafficLightState {
    @Override
    public void handle(TrafficLight context) {
        System.out.println("SARI: HAZIRLAŞ! (5 saniyə)");
        context.setState(new RedState());
    }
    @Override
    public String getColor() { return "SARI"; }
}

class TrafficLight {
    private TrafficLightState state;

    TrafficLight() {
        this.state = new RedState(); // Başlanğıc vəziyyəti
    }

    public void setState(TrafficLightState state) {
        System.out.println("Vəziyyət dəyişdi: " + this.state.getColor()
            + " → " + state.getColor());
        this.state = state;
    }

    public void changeLight() {
        state.handle(this);
    }
}

// Order Status State Machine
enum OrderStatus { PENDING, CONFIRMED, SHIPPED, DELIVERED, CANCELLED }

class Order {
    private OrderStatus status = OrderStatus.PENDING;
    private final String id;

    Order(String id) { this.id = id; }

    public void confirm() {
        if (status != OrderStatus.PENDING) {
            throw new IllegalStateException("Yalnız PENDING sifariş təsdiqlənə bilər");
        }
        status = OrderStatus.CONFIRMED;
        System.out.println("[" + id + "] Sifariş təsdiqləndi");
    }

    public void ship() {
        if (status != OrderStatus.CONFIRMED) {
            throw new IllegalStateException("Yalnız CONFIRMED sifariş göndərilə bilər");
        }
        status = OrderStatus.SHIPPED;
        System.out.println("[" + id + "] Sifariş göndərildi");
    }

    public void deliver() {
        if (status != OrderStatus.SHIPPED) {
            throw new IllegalStateException("Yalnız SHIPPED sifariş çatdırıla bilər");
        }
        status = OrderStatus.DELIVERED;
        System.out.println("[" + id + "] Sifariş çatdırıldı");
    }

    public void cancel() {
        if (status == OrderStatus.DELIVERED) {
            throw new IllegalStateException("Çatdırılmış sifariş ləğv edilə bilməz");
        }
        status = OrderStatus.CANCELLED;
        System.out.println("[" + id + "] Sifariş ləğv edildi");
    }

    public OrderStatus getStatus() { return status; }
}

class StateDemo {
    public static void main(String[] args) {
        TrafficLight light = new TrafficLight();
        for (int i = 0; i < 6; i++) {
            light.changeLight();
        }

        System.out.println();
        Order order = new Order("ORD-001");
        order.confirm();
        order.ship();
        order.deliver();

        try {
            order.cancel(); // IllegalStateException!
        } catch (IllegalStateException e) {
            System.out.println("Xəta: " + e.getMessage());
        }
    }
}
```

---

## Visitor

**Visitor** — mövcud sinifləri dəyişmədən yeni əməliyyat əlavə etmək.

```java
// Element interface
interface Shape {
    double area();
    void accept(ShapeVisitor visitor); // Visitor-u qəbul et
}

// Concrete Elements
class Circle implements Shape {
    final double radius;
    Circle(double r) { this.radius = r; }

    @Override
    public double area() { return Math.PI * radius * radius; }

    @Override
    public void accept(ShapeVisitor visitor) {
        visitor.visit(this); // Double dispatch!
    }
}

class Rectangle implements Shape {
    final double width, height;
    Rectangle(double w, double h) { this.width = w; this.height = h; }

    @Override
    public double area() { return width * height; }

    @Override
    public void accept(ShapeVisitor visitor) {
        visitor.visit(this);
    }
}

class Triangle implements Shape {
    final double base, height;
    Triangle(double b, double h) { this.base = b; this.height = h; }

    @Override
    public double area() { return 0.5 * base * height; }

    @Override
    public void accept(ShapeVisitor visitor) {
        visitor.visit(this);
    }
}

// Visitor interface
interface ShapeVisitor {
    void visit(Circle circle);
    void visit(Rectangle rectangle);
    void visit(Triangle triangle);
}

// Concrete Visitors — Shape-ləri dəyişmədən yeni əməliyyatlar əlavə edirik!
class AreaCalculatorVisitor implements ShapeVisitor {
    private double totalArea = 0;

    @Override
    public void visit(Circle c) {
        double area = c.area();
        totalArea += area;
        System.out.printf("Dairə sahəsi: %.2f%n", area);
    }

    @Override
    public void visit(Rectangle r) {
        double area = r.area();
        totalArea += area;
        System.out.printf("Düzbucaqlı sahəsi: %.2f%n", area);
    }

    @Override
    public void visit(Triangle t) {
        double area = t.area();
        totalArea += area;
        System.out.printf("Üçbucaq sahəsi: %.2f%n", area);
    }

    public double getTotalArea() { return totalArea; }
}

class SVGExportVisitor implements ShapeVisitor {
    private final StringBuilder svg = new StringBuilder();

    @Override
    public void visit(Circle c) {
        svg.append(String.format("<circle r=\"%.0f\"/>%n", c.radius));
    }

    @Override
    public void visit(Rectangle r) {
        svg.append(String.format("<rect width=\"%.0f\" height=\"%.0f\"/>%n",
            r.width, r.height));
    }

    @Override
    public void visit(Triangle t) {
        svg.append(String.format("<polygon points=\"0,%.0f %.0f,0 %.0f,%.0f\"/>%n",
            t.height, t.base/2, t.base, t.height));
    }

    public String getSVG() { return "<svg>" + svg + "</svg>"; }
}

class VisitorDemo {
    public static void main(String[] args) {
        List<Shape> shapes = List.of(
            new Circle(5),
            new Rectangle(4, 6),
            new Triangle(3, 8)
        );

        // Visitor 1: Sahə hesabla
        AreaCalculatorVisitor areaCalc = new AreaCalculatorVisitor();
        shapes.forEach(s -> s.accept(areaCalc));
        System.out.printf("Ümumi sahə: %.2f%n", areaCalc.getTotalArea());

        // Visitor 2: SVG export (Shape-ləri dəyişmədik!)
        SVGExportVisitor svgExport = new SVGExportVisitor();
        shapes.forEach(s -> s.accept(svgExport));
        System.out.println("SVG: " + svgExport.getSVG());
    }
}
```

---

## İntervyu Sualları

**S: Strategy Pattern nə zaman istifadə olunur?**
C: Eyni problemi həll edən bir neçə alqoritm olduğunda və runtime-da dəyişdirmək lazım gəldikdə. Şərtlər (if-else/switch) yerinə polimorfizm. Nümunələr: sıralama, ödəniş metodları, şifrələmə alqoritmləri, Comparator.

**S: Observer Pattern nədir?**
C: Publish-Subscribe mexanizmi. Subject (publisher) dəyişdikdə bütün Observer-ləri (subscribers) xəbərdar edir. Java EventListener, Spring ApplicationEvent, JavaFX/Swing hadisə modeli buna əsaslanır.

**S: Command Pattern-in üstünlüyü nədir?**
C: Sorğuları obyekt kimi kapsüllər. Undo/Redo (history stack), əmrləri queue-ya yerləşdirmək, makro (bir neçə əmri birlikdə), loglama. `execute()` + `undo()` metodları tənzimini təmin edir.

**S: Template Method nədir? Hansı prinsipə əsaslanır?**
C: Abstract class-da alqoritm skeleti təyin edilir, alt siniflər bəzi addımları override edir. Hollywood Principle — "Don't call us, we'll call you". JUnit setUp/tearDown, Servlet lifecycle (init/service/destroy), Spring-in JdbcTemplate.

**S: Chain of Responsibility harada istifadə olunur?**
C: Java Servlet Filter-lər, Spring Security filter chain, logging framework-lər (handler zənciri), event bubbling (GUI), exception handling zəncirləri. Hər handler sorğunu işləyə ya da növbətiyə ötürə bilər.

**S: Visitor Pattern double dispatch nədir?**
C: Normal polimorfizm tək dispatch-dir — runtime-da obyektin tipi müəyyən edilir. Visitor-da ikili dispatch: əvvəl `shape.accept(visitor)` — shape-in tipi müəyyən edilir, sonra visitor-un `visit(specificShape)` — visitor-un tipi müəyyən edilir. Bu iki tip seçimi double dispatch-dır.

**S: State vs Strategy fərqi?**
C: Strategy — alqoritm xaricdən seçilir, client özü seçir. State — vəziyyət daxildən dəyişir, context öz state-ini idarə edir. Strategy-də state yoxdur, hər zaman çağırıla bilər. State-də vəziyyətlər arasında keçid var (state machine).
