# Dizayn Pattern-ləri (Design Patterns)

## Giriş

Dizayn pattern-ləri proqramlaşdırmada tez-tez rast gəlinən problemlərə sınaqdan keçmiş həllərdir. Java və PHP eyni pattern-ləri fərqli dil xüsusiyyətləri ilə tətbiq edir. Bu fəsildə ən çox istifadə olunan pattern-lərin hər iki dildəki tətbiqlərini müqayisə edəcəyik: Singleton, Factory, Observer, Strategy, Builder və Adapter.

## Java-da istifadəsi

### Singleton Pattern

Singleton yalnız bir nüsxənin mövcud olmasını təmin edir:

```java
// 1. Enum Singleton (ən yaxşı yanaşma - Joshua Bloch tövsiyə edir)
public enum VeriləntabanıBağlantısı {
    INSTANCE;

    private Connection bağlantı;

    VeriləntabanıBağlantısı() {
        // Yalnız bir dəfə çağırılır
        this.bağlantı = DriverManager.getConnection("jdbc:mysql://localhost/db");
    }

    public Connection getBağlantı() {
        return bağlantı;
    }

    public void sorğuİcrəEt(String sql) {
        System.out.println("İcra: " + sql);
    }
}

// İstifadə
VeriləntabanıBağlantısı.INSTANCE.sorğuİcrəEt("SELECT * FROM users");

// Enum singleton-un üstünlükləri:
// - Serialization təhlükəsiz (avtomatik)
// - Reflection ilə yeni nüsxə yaradıla bilməz
// - Thread-safe (JVM təmin edir)
// - Çox sadə kod

// 2. Lazy initialization ilə double-checked locking
public class Konfiqurasiya {
    private static volatile Konfiqurasiya nüsxə;
    private final Properties xüsusiyyətlər;

    private Konfiqurasiya() {
        xüsusiyyətlər = new Properties();
        // fayl oxuma və s.
    }

    public static Konfiqurasiya getInstance() {
        if (nüsxə == null) {
            synchronized (Konfiqurasiya.class) {
                if (nüsxə == null) {
                    nüsxə = new Konfiqurasiya();
                }
            }
        }
        return nüsxə;
    }

    public String get(String açar) {
        return xüsusiyyətlər.getProperty(açar);
    }
}

// 3. Bill Pugh Singleton (static inner class)
public class Logger {
    private Logger() {}

    private static class Holder {
        private static final Logger INSTANCE = new Logger();
    }

    public static Logger getInstance() {
        return Holder.INSTANCE;
    }

    public void log(String mesaj) {
        System.out.println("[LOG] " + mesaj);
    }
}
```

### Factory Pattern

```java
// Factory Method Pattern
public interface Bildiriş {
    void göndər(String mesaj, String alıcı);
}

public class EmailBildirişi implements Bildiriş {
    @Override
    public void göndər(String mesaj, String alıcı) {
        System.out.println("Email göndərildi: " + alıcı + " - " + mesaj);
    }
}

public class SmsBildirişi implements Bildiriş {
    @Override
    public void göndər(String mesaj, String alıcı) {
        System.out.println("SMS göndərildi: " + alıcı + " - " + mesaj);
    }
}

public class PushBildirişi implements Bildiriş {
    @Override
    public void göndər(String mesaj, String alıcı) {
        System.out.println("Push göndərildi: " + alıcı + " - " + mesaj);
    }
}

// Factory Method
public abstract class BildirişFactory {
    public abstract Bildiriş yarat();

    // Template method - factory method-u istifadə edir
    public void bildirişGöndər(String mesaj, String alıcı) {
        Bildiriş bildiriş = yarat();
        bildiriş.göndər(mesaj, alıcı);
    }
}

public class EmailFactory extends BildirişFactory {
    @Override
    public Bildiriş yarat() {
        return new EmailBildirişi();
    }
}

public class SmsFactory extends BildirişFactory {
    @Override
    public Bildiriş yarat() {
        return new SmsBildirişi();
    }
}

// Simple Factory (static method)
public class BildirişYaradıcı {
    public static Bildiriş yarat(String tip) {
        return switch (tip.toLowerCase()) {
            case "email" -> new EmailBildirişi();
            case "sms" -> new SmsBildirişi();
            case "push" -> new PushBildirişi();
            default -> throw new IllegalArgumentException("Bilinməyən tip: " + tip);
        };
    }
}

// İstifadə
Bildiriş b = BildirişYaradıcı.yarat("email");
b.göndər("Salam!", "user@mail.com");

// Abstract Factory Pattern
public interface UIFactory {
    Düymə düyməYarat();
    GirişSahəsi girişSahəsiYarat();
    Dialog dialogYarat();
}

public class WindowsUIFactory implements UIFactory {
    @Override
    public Düymə düyməYarat() { return new WindowsDüymə(); }
    @Override
    public GirişSahəsi girişSahəsiYarat() { return new WindowsGirişSahəsi(); }
    @Override
    public Dialog dialogYarat() { return new WindowsDialog(); }
}

public class MacUIFactory implements UIFactory {
    @Override
    public Düymə düyməYarat() { return new MacDüymə(); }
    @Override
    public GirişSahəsi girişSahəsiYarat() { return new MacGirişSahəsi(); }
    @Override
    public Dialog dialogYarat() { return new MacDialog(); }
}
```

### Observer Pattern

```java
// Java-nın daxili Observer/Observable sinifləri Java 9-da deprecated olub
// Öz implementasiyamızı yazaq

// Hadisə (Event) sinfi
public record SifarişHadisəsi(String sifarişId, String status, double məbləğ) {}

// Observer interfeysi
@FunctionalInterface
public interface SifarişMüşahidəçisi {
    void yenilə(SifarişHadisəsi hadisə);
}

// Subject (Observable) sinfi
public class SifarişServisi {
    private final List<SifarişMüşahidəçisi> müşahidəçilər = new ArrayList<>();
    private final Map<String, List<SifarişMüşahidəçisi>> hadisəMüşahidəçiləri = new HashMap<>();

    // Bütün hadisələr üçün qeydiyyat
    public void əlavəEt(SifarişMüşahidəçisi müşahidəçi) {
        müşahidəçilər.add(müşahidəçi);
    }

    public void çıxar(SifarişMüşahidəçisi müşahidəçi) {
        müşahidəçilər.remove(müşahidəçi);
    }

    // Xüsusi hadisə üçün qeydiyyat
    public void əlavəEt(String hadisəTipi, SifarişMüşahidəçisi müşahidəçi) {
        hadisəMüşahidəçiləri
            .computeIfAbsent(hadisəTipi, k -> new ArrayList<>())
            .add(müşahidəçi);
    }

    private void bildirimGöndər(SifarişHadisəsi hadisə) {
        müşahidəçilər.forEach(m -> m.yenilə(hadisə));
        hadisəMüşahidəçiləri
            .getOrDefault(hadisə.status(), List.of())
            .forEach(m -> m.yenilə(hadisə));
    }

    public void sifarişYarat(String id, double məbləğ) {
        System.out.println("Sifariş yaradıldı: " + id);
        bildirimGöndər(new SifarişHadisəsi(id, "yaradıldı", məbləğ));
    }

    public void sifarişTəsdiqlə(String id) {
        System.out.println("Sifariş təsdiqləndi: " + id);
        bildirimGöndər(new SifarişHadisəsi(id, "təsdiqləndi", 0));
    }
}

// İstifadə
SifarişServisi servis = new SifarişServisi();

// Lambda ilə (FunctionalInterface sayəsində)
servis.əlavəEt(hadisə ->
    System.out.println("Log: " + hadisə.sifarişId() + " - " + hadisə.status()));

servis.əlavəEt("yaradıldı", hadisə ->
    System.out.println("Email göndərildi: yeni sifariş " + hadisə.məbləğ() + " AZN"));

servis.sifarişYarat("SIF-001", 150.0);
```

### Strategy Pattern

```java
// Strategy interfeysi
public interface ÖdənişStrategiyası {
    boolean ödə(double məbləğ);
    String getAd();
}

// Konkret strategiyalar
public class KreditKartıÖdənişi implements ÖdənişStrategiyası {
    private final String kartNömrəsi;

    public KreditKartıÖdənişi(String kartNömrəsi) {
        this.kartNömrəsi = kartNömrəsi;
    }

    @Override
    public boolean ödə(double məbləğ) {
        System.out.printf("Kredit kartı ilə ödəniş: %.2f AZN (Kart: %s)%n",
            məbləğ, kartNömrəsi.substring(kartNömrəsi.length() - 4));
        return true;
    }

    @Override
    public String getAd() { return "Kredit kartı"; }
}

public class PayPalÖdənişi implements ÖdənişStrategiyası {
    private final String email;

    public PayPalÖdənişi(String email) {
        this.email = email;
    }

    @Override
    public boolean ödə(double məbləğ) {
        System.out.printf("PayPal ilə ödəniş: %.2f AZN (Email: %s)%n", məbləğ, email);
        return true;
    }

    @Override
    public String getAd() { return "PayPal"; }
}

public class KriptovalyutaÖdənişi implements ÖdənişStrategiyası {
    private final String cüzdanÜnvanı;

    public KriptovalyutaÖdənişi(String cüzdanÜnvanı) {
        this.cüzdanÜnvanı = cüzdanÜnvanı;
    }

    @Override
    public boolean ödə(double məbləğ) {
        System.out.printf("Kripto ilə ödəniş: %.2f AZN (Cüzdan: %s)%n",
            məbləğ, cüzdanÜnvanı.substring(0, 8) + "...");
        return true;
    }

    @Override
    public String getAd() { return "Kriptovalyuta"; }
}

// Context sinfi
public class SifarişÖdənişi {
    private ÖdənişStrategiyası strategiya;

    public void setStrategiya(ÖdənişStrategiyası strategiya) {
        this.strategiya = strategiya;
    }

    public boolean ödəniş(double məbləğ) {
        if (strategiya == null) {
            throw new IllegalStateException("Ödəniş strategiyası seçilməyib");
        }
        System.out.println("Ödəniş metodu: " + strategiya.getAd());
        return strategiya.ödə(məbləğ);
    }
}

// İstifadə
SifarişÖdənişi ödəniş = new SifarişÖdənişi();
ödəniş.setStrategiya(new KreditKartıÖdənişi("4111111111111234"));
ödəniş.ödəniş(150.0);

ödəniş.setStrategiya(new PayPalÖdənişi("user@mail.com"));
ödəniş.ödəniş(75.50);
```

### Builder Pattern

```java
// Builder Pattern
public class Sorğu {
    private final String cədvəl;
    private final List<String> sütunlar;
    private final List<String> şərtlər;
    private final String sıralama;
    private final Integer limit;
    private final Integer offset;

    private Sorğu(Builder builder) {
        this.cədvəl = builder.cədvəl;
        this.sütunlar = List.copyOf(builder.sütunlar);
        this.şərtlər = List.copyOf(builder.şərtlər);
        this.sıralama = builder.sıralama;
        this.limit = builder.limit;
        this.offset = builder.offset;
    }

    public String qur() {
        StringBuilder sql = new StringBuilder("SELECT ");
        sql.append(sütunlar.isEmpty() ? "*" : String.join(", ", sütunlar));
        sql.append(" FROM ").append(cədvəl);

        if (!şərtlər.isEmpty()) {
            sql.append(" WHERE ").append(String.join(" AND ", şərtlər));
        }
        if (sıralama != null) {
            sql.append(" ORDER BY ").append(sıralama);
        }
        if (limit != null) {
            sql.append(" LIMIT ").append(limit);
        }
        if (offset != null) {
            sql.append(" OFFSET ").append(offset);
        }
        return sql.toString();
    }

    // Statik daxili Builder sinfi
    public static class Builder {
        private final String cədvəl; // tələb olunan
        private final List<String> sütunlar = new ArrayList<>();
        private final List<String> şərtlər = new ArrayList<>();
        private String sıralama;
        private Integer limit;
        private Integer offset;

        public Builder(String cədvəl) {
            this.cədvəl = cədvəl;
        }

        public Builder sütun(String... sütun) {
            sütunlar.addAll(List.of(sütun));
            return this;
        }

        public Builder harada(String şərt) {
            şərtlər.add(şərt);
            return this;
        }

        public Builder sırala(String sahə) {
            this.sıralama = sahə;
            return this;
        }

        public Builder limit(int limit) {
            this.limit = limit;
            return this;
        }

        public Builder offset(int offset) {
            this.offset = offset;
            return this;
        }

        public Sorğu qur() {
            return new Sorğu(this);
        }
    }
}

// İstifadə
String sql = new Sorğu.Builder("istifadəçilər")
    .sütun("ad", "soyad", "email")
    .harada("yaş > 18")
    .harada("status = 'aktiv'")
    .sırala("ad ASC")
    .limit(10)
    .offset(20)
    .qur()
    .qur();

System.out.println(sql);
// SELECT ad, soyad, email FROM istifadəçilər WHERE yaş > 18 AND status = 'aktiv' ORDER BY ad ASC LIMIT 10 OFFSET 20

// Java 16+ record ilə sadə Builder
public record İstifadəçi(String ad, String soyad, String email, int yaş) {
    public static Builder builder() { return new Builder(); }

    public static class Builder {
        private String ad, soyad, email;
        private int yaş;

        public Builder ad(String ad) { this.ad = ad; return this; }
        public Builder soyad(String soyad) { this.soyad = soyad; return this; }
        public Builder email(String email) { this.email = email; return this; }
        public Builder yaş(int yaş) { this.yaş = yaş; return this; }

        public İstifadəçi qur() {
            return new İstifadəçi(ad, soyad, email, yaş);
        }
    }
}
```

### Adapter Pattern

```java
// Mövcud interfeys (client istifadə edir)
public interface ÖdənişGateway {
    boolean ödəniş(double məbləğ, String valyuta);
    String statusYoxla(String əməliyyatId);
}

// Üçüncü tərəf kitabxanası (fərqli interfeys)
public class EskiÖdənişSistemi {
    public int processPayment(int amountInCents, String curr) {
        System.out.println("Köhnə sistem: " + amountInCents + " sent, " + curr);
        return 1; // uğurlu
    }

    public String getTransactionStatus(int transactionId) {
        return "COMPLETED";
    }
}

// Adapter - köhnə sistemi yeni interfeysə uyğunlaşdırır
public class EskiSistemAdapter implements ÖdənişGateway {
    private final EskiÖdənişSistemi eskiSistem;

    public EskiSistemAdapter(EskiÖdənişSistemi eskiSistem) {
        this.eskiSistem = eskiSistem;
    }

    @Override
    public boolean ödəniş(double məbləğ, String valyuta) {
        int sentMəbləğ = (int) (məbləğ * 100); // manat -> sent
        int nəticə = eskiSistem.processPayment(sentMəbləğ, valyuta);
        return nəticə == 1;
    }

    @Override
    public String statusYoxla(String əməliyyatId) {
        int id = Integer.parseInt(əməliyyatId);
        return eskiSistem.getTransactionStatus(id);
    }
}

// İstifadə - client yalnız ÖdənişGateway-i tanıyır
ÖdənişGateway gateway = new EskiSistemAdapter(new EskiÖdənişSistemi());
gateway.ödəniş(150.0, "AZN");
gateway.statusYoxla("12345");
```

## PHP-də istifadəsi

### Singleton Pattern

```php
// PHP Singleton - static xüsusiyyət və private constructor ilə
class VeriləntabanıBağlantısı {
    private static ?self $nüsxə = null;
    private PDO $bağlantı;

    // Private constructor - xaricdən yaradıla bilməz
    private function __construct() {
        $this->bağlantı = new PDO(
            'mysql:host=localhost;dbname=test',
            'root',
            'şifrə'
        );
    }

    // Clone-u qadağan et
    private function __clone() {}

    // Unserialize-ı qadağan et
    public function __wakeup() {
        throw new \Exception("Singleton unserialize edilə bilməz");
    }

    public static function getInstance(): self {
        if (self::$nüsxə === null) {
            self::$nüsxə = new self();
        }
        return self::$nüsxə;
    }

    public function getBağlantı(): PDO {
        return $this->bağlantı;
    }

    public function sorğu(string $sql): array {
        return $this->bağlantı->query($sql)->fetchAll();
    }
}

// İstifadə
$db = VeriləntabanıBağlantısı::getInstance();
$db->sorğu("SELECT * FROM users");

// Trait ilə təkrar istifadə olunan Singleton
trait SingletonTrait {
    private static ?self $nüsxə = null;

    private function __construct() {}
    private function __clone() {}

    public static function getInstance(): static {
        if (static::$nüsxə === null) {
            static::$nüsxə = new static();
        }
        return static::$nüsxə;
    }
}

class Logger {
    use SingletonTrait;

    private array $loglar = [];

    public function log(string $mesaj): void {
        $this->loglar[] = date('[Y-m-d H:i:s]') . " $mesaj";
    }

    public function getLoglar(): array {
        return $this->loglar;
    }
}

// Müasir PHP-də Singleton əvəzinə DI Container istifadə olunur
// Laravel nümunəsi:
// $this->app->singleton(Logger::class, function () {
//     return new Logger();
// });
```

### Factory Pattern

```php
// Factory Method Pattern
interface Bildiriş {
    public function göndər(string $mesaj, string $alıcı): void;
}

class EmailBildirişi implements Bildiriş {
    public function göndər(string $mesaj, string $alıcı): void {
        echo "Email göndərildi: $alıcı - $mesaj\n";
    }
}

class SmsBildirişi implements Bildiriş {
    public function göndər(string $mesaj, string $alıcı): void {
        echo "SMS göndərildi: $alıcı - $mesaj\n";
    }
}

class PushBildirişi implements Bildiriş {
    public function göndər(string $mesaj, string $alıcı): void {
        echo "Push göndərildi: $alıcı - $mesaj\n";
    }
}

// Simple Factory
class BildirişFactory {
    public static function yarat(string $tip): Bildiriş {
        return match ($tip) {
            'email' => new EmailBildirişi(),
            'sms' => new SmsBildirişi(),
            'push' => new PushBildirişi(),
            default => throw new InvalidArgumentException("Bilinməyən tip: $tip"),
        };
    }
}

// İstifadə
$bildiriş = BildirişFactory::yarat('email');
$bildiriş->göndər('Salam!', 'user@mail.com');

// Factory Method - abstract class ilə
abstract class BildirişCreator {
    abstract protected function bildirişYarat(): Bildiriş;

    public function göndər(string $mesaj, string $alıcı): void {
        $bildiriş = $this->bildirişYarat();
        $bildiriş->göndər($mesaj, $alıcı);
    }
}

class EmailCreator extends BildirişCreator {
    protected function bildirişYarat(): Bildiriş {
        return new EmailBildirişi();
    }
}

// Abstract Factory
interface UIFactory {
    public function düyməYarat(): Düymə;
    public function girişSahəsiYarat(): GirişSahəsi;
    public function dialogYarat(): Dialog;
}

class WebUIFactory implements UIFactory {
    public function düyməYarat(): Düymə {
        return new HtmlDüymə();
    }
    public function girişSahəsiYarat(): GirişSahəsi {
        return new HtmlGirişSahəsi();
    }
    public function dialogYarat(): Dialog {
        return new HtmlDialog();
    }
}

// PHP 8.1+ Enum ilə Factory
enum BildirişTipi: string {
    case Email = 'email';
    case Sms = 'sms';
    case Push = 'push';

    public function yarat(): Bildiriş {
        return match ($this) {
            self::Email => new EmailBildirişi(),
            self::Sms => new SmsBildirişi(),
            self::Push => new PushBildirişi(),
        };
    }
}

// İstifadə
$bildiriş = BildirişTipi::Email->yarat();
$bildiriş->göndər('Test', 'user@mail.com');
```

### Observer Pattern

```php
// PHP SplObserver/SplSubject interfeysleri (daxili)
// Amma bunlar çox sadədir, öz implementasiyamızı yazaq

// Hadisə sistemi
class HadisəDispetçeri {
    private array $dinləyicilər = [];

    public function dinlə(string $hadisəAdı, callable $callback): void {
        $this->dinləyicilər[$hadisəAdı][] = $callback;
    }

    public function yay(string $hadisəAdı, mixed $data = null): void {
        foreach ($this->dinləyicilər[$hadisəAdı] ?? [] as $callback) {
            $callback($data);
        }
    }

    public function silDinləyiciləri(string $hadisəAdı): void {
        unset($this->dinləyicilər[$hadisəAdı]);
    }
}

// Hadisə sinifləri
class SifarişHadisəsi {
    public function __construct(
        public readonly string $sifarişId,
        public readonly string $status,
        public readonly float $məbləğ,
    ) {}
}

// İstifadə
$dispetçer = new HadisəDispetçeri();

// Dinləyiciləri qeydiyyat et
$dispetçer->dinlə('sifariş.yaradıldı', function (SifarişHadisəsi $h) {
    echo "Log: Yeni sifariş {$h->sifarişId}, məbləğ: {$h->məbləğ} AZN\n";
});

$dispetçer->dinlə('sifariş.yaradıldı', function (SifarişHadisəsi $h) {
    echo "Email: Sifarişiniz {$h->sifarişId} qəbul edildi\n";
});

$dispetçer->dinlə('sifariş.təsdiqləndi', function (SifarişHadisəsi $h) {
    echo "SMS: Sifariş {$h->sifarişId} təsdiqləndi\n";
});

// Hadisə yayımla
$dispetçer->yay('sifariş.yaradıldı', new SifarişHadisəsi('SIF-001', 'yaradıldı', 150.0));
// Log: Yeni sifariş SIF-001, məbləğ: 150 AZN
// Email: Sifarişiniz SIF-001 qəbul edildi

// PSR-14 uyğun hadisə sistemi (Laravel/Symfony tərzi)
interface HadisəDinləyicisi {
    public function idarəEt(object $hadisə): void;
}

class SifarişLogDinləyicisi implements HadisəDinləyicisi {
    public function idarəEt(object $hadisə): void {
        if ($hadisə instanceof SifarişHadisəsi) {
            echo "Log: {$hadisə->sifarişId} - {$hadisə->status}\n";
        }
    }
}

class SifarişEmailDinləyicisi implements HadisəDinləyicisi {
    public function idarəEt(object $hadisə): void {
        if ($hadisə instanceof SifarişHadisəsi) {
            echo "Email göndərildi: sifariş {$hadisə->sifarişId}\n";
        }
    }
}
```

### Strategy Pattern

```php
// Strategy interfeysi
interface ÖdənişStrategiyası {
    public function ödə(float $məbləğ): bool;
    public function getAd(): string;
}

class KreditKartıÖdənişi implements ÖdənişStrategiyası {
    public function __construct(
        private string $kartNömrəsi
    ) {}

    public function ödə(float $məbləğ): bool {
        $son4 = substr($this->kartNömrəsi, -4);
        echo "Kredit kartı ilə ödəniş: {$məbləğ} AZN (Kart: ****{$son4})\n";
        return true;
    }

    public function getAd(): string { return 'Kredit kartı'; }
}

class PayPalÖdənişi implements ÖdənişStrategiyası {
    public function __construct(
        private string $email
    ) {}

    public function ödə(float $məbləğ): bool {
        echo "PayPal ilə ödəniş: {$məbləğ} AZN (Email: {$this->email})\n";
        return true;
    }

    public function getAd(): string { return 'PayPal'; }
}

// Context
class SifarişÖdənişi {
    private ?ÖdənişStrategiyası $strategiya = null;

    public function setStrategiya(ÖdənişStrategiyası $strategiya): void {
        $this->strategiya = $strategiya;
    }

    public function ödə(float $məbləğ): bool {
        if ($this->strategiya === null) {
            throw new RuntimeException('Ödəniş strategiyası seçilməyib');
        }
        echo "Ödəniş metodu: {$this->strategiya->getAd()}\n";
        return $this->strategiya->ödə($məbləğ);
    }
}

// İstifadə
$ödəniş = new SifarişÖdənişi();
$ödəniş->setStrategiya(new KreditKartıÖdənişi('4111111111111234'));
$ödəniş->ödə(150.0);

// PHP-nin birinci sinif funksiyaları ilə daha sadə strategiya
class ÇevikÖdəniş {
    /** @var callable(float): bool */
    private $strategiya;

    public function setStrategiya(callable $strategiya): void {
        $this->strategiya = $strategiya;
    }

    public function ödə(float $məbləğ): bool {
        return ($this->strategiya)($məbləğ);
    }
}

$ödəniş = new ÇevikÖdəniş();
$ödəniş->setStrategiya(function (float $məbləğ): bool {
    echo "Lambda ödəniş: {$məbləğ} AZN\n";
    return true;
});
$ödəniş->ödə(100.0);

// Arrow function ilə
$ödəniş->setStrategiya(fn(float $m) => print("Qısa ödəniş: {$m}\n") && true);
```

### Builder Pattern

```php
// Builder Pattern
class Sorğu {
    private function __construct(
        private string $cədvəl,
        private array $sütunlar,
        private array $şərtlər,
        private ?string $sıralama,
        private ?int $limit,
        private ?int $offset,
    ) {}

    public function qur(): string {
        $sql = 'SELECT ';
        $sql .= empty($this->sütunlar) ? '*' : implode(', ', $this->sütunlar);
        $sql .= " FROM {$this->cədvəl}";

        if (!empty($this->şərtlər)) {
            $sql .= ' WHERE ' . implode(' AND ', $this->şərtlər);
        }
        if ($this->sıralama !== null) {
            $sql .= " ORDER BY {$this->sıralama}";
        }
        if ($this->limit !== null) {
            $sql .= " LIMIT {$this->limit}";
        }
        if ($this->offset !== null) {
            $sql .= " OFFSET {$this->offset}";
        }
        return $sql;
    }

    public static function builder(string $cədvəl): SorğuBuilder {
        return new SorğuBuilder($cədvəl);
    }

    // Daxili static factory
    public static function fromBuilder(SorğuBuilder $builder): self {
        return new self(
            $builder->cədvəl,
            $builder->sütunlar,
            $builder->şərtlər,
            $builder->sıralama,
            $builder->limit,
            $builder->offset,
        );
    }
}

class SorğuBuilder {
    public array $sütunlar = [];
    public array $şərtlər = [];
    public ?string $sıralama = null;
    public ?int $limit = null;
    public ?int $offset = null;

    public function __construct(
        public string $cədvəl
    ) {}

    public function sütun(string ...$sütunlar): self {
        $this->sütunlar = array_merge($this->sütunlar, $sütunlar);
        return $this;
    }

    public function harada(string $şərt): self {
        $this->şərtlər[] = $şərt;
        return $this;
    }

    public function sırala(string $sahə): self {
        $this->sıralama = $sahə;
        return $this;
    }

    public function limit(int $limit): self {
        $this->limit = $limit;
        return $this;
    }

    public function offset(int $offset): self {
        $this->offset = $offset;
        return $this;
    }

    public function qur(): Sorğu {
        return Sorğu::fromBuilder($this);
    }
}

// İstifadə
$sql = Sorğu::builder('istifadəçilər')
    ->sütun('ad', 'soyad', 'email')
    ->harada("yaş > 18")
    ->harada("status = 'aktiv'")
    ->sırala('ad ASC')
    ->limit(10)
    ->offset(20)
    ->qur()
    ->qur();

echo $sql;
// SELECT ad, soyad, email FROM istifadəçilər WHERE yaş > 18 AND status = 'aktiv' ORDER BY ad ASC LIMIT 10 OFFSET 20

// PHP 8.0+ Named Arguments ilə Builder-ə alternativ
class İstifadəçi {
    public function __construct(
        public readonly string $ad,
        public readonly string $soyad,
        public readonly string $email = '',
        public readonly int $yaş = 0,
        public readonly string $şəhər = '',
    ) {}
}

// Named arguments Builder pattern-ə ehtiyacı azaldır
$ist = new İstifadəçi(
    ad: 'Orxan',
    soyad: 'Əliyev',
    email: 'orxan@mail.com',
    yaş: 25,
);
```

### Adapter Pattern

```php
// Mövcud interfeys
interface ÖdənişGateway {
    public function ödəniş(float $məbləğ, string $valyuta): bool;
    public function statusYoxla(string $əməliyyatId): string;
}

// Üçüncü tərəf kitabxanası
class EskiÖdənişSistemi {
    public function processPayment(int $amountInCents, string $curr): int {
        echo "Köhnə sistem: {$amountInCents} sent, {$curr}\n";
        return 1;
    }

    public function getTransactionStatus(int $transactionId): string {
        return 'COMPLETED';
    }
}

// Adapter
class EskiSistemAdapter implements ÖdənişGateway {
    public function __construct(
        private EskiÖdənişSistemi $eskiSistem
    ) {}

    public function ödəniş(float $məbləğ, string $valyuta): bool {
        $sent = (int) ($məbləğ * 100);
        $nəticə = $this->eskiSistem->processPayment($sent, $valyuta);
        return $nəticə === 1;
    }

    public function statusYoxla(string $əməliyyatId): string {
        return $this->eskiSistem->getTransactionStatus((int) $əməliyyatId);
    }
}

// İstifadə
$gateway = new EskiSistemAdapter(new EskiÖdənişSistemi());
$gateway->ödəniş(150.0, 'AZN');
echo $gateway->statusYoxla('12345');

// PHP-nin magic metodları ilə dinamik adapter
class DinamikAdapter implements ÖdənişGateway {
    private object $adaptee;
    private array $metodXəritəsi;

    public function __construct(object $adaptee, array $metodXəritəsi) {
        $this->adaptee = $adaptee;
        $this->metodXəritəsi = $metodXəritəsi;
    }

    public function ödəniş(float $məbləğ, string $valyuta): bool {
        $metod = $this->metodXəritəsi['ödəniş'] ?? null;
        if ($metod) {
            return (bool) call_user_func_array(
                [$this->adaptee, $metod['metod']],
                ($metod['transformator'] ?? fn($m, $v) => [$m, $v])($məbləğ, $valyuta)
            );
        }
        throw new \BadMethodCallException('ödəniş metodu xəritələnməyib');
    }

    public function statusYoxla(string $əməliyyatId): string {
        $metod = $this->metodXəritəsi['statusYoxla'] ?? null;
        if ($metod) {
            return call_user_func(
                [$this->adaptee, $metod['metod']],
                ...($metod['transformator'] ?? fn($id) => [$id])($əməliyyatId)
            );
        }
        throw new \BadMethodCallException('statusYoxla metodu xəritələnməyib');
    }
}
```

## Əsas fərqlər

| Pattern | Java | PHP |
|---|---|---|
| **Singleton** | Enum singleton (ən yaxşı), double-checked locking | Static xüsusiyyət + private constructor, Trait ilə |
| **Factory** | Abstract class/interface, `switch` expression | `match` expression, Enum ilə factory metod |
| **Observer** | Öz interfeysi, lambda ilə, `java.beans.PropertyChangeListener` | `SplObserver`/`SplSubject`, callable/Closure |
| **Strategy** | İnterfeys + implementasiyalar | İnterfeys + implementasiyalar, callable/Closure alternativ |
| **Builder** | Static inner class | Ayrı sinif, named arguments alternativ (PHP 8+) |
| **Adapter** | Composition (constructor injection) | Composition, magic metodlar |

| Xüsusiyyət | Java | PHP |
|---|---|---|
| Pattern enforced | Güclü tip sistemi ilə | Daha az ciddi, dinamik alternativlər |
| Lambda istifadəsi | Strategy, Observer üçün | Callable/Closure daha çevik |
| Thread safety | Singleton-da vacib (volatile, synchronized) | Lazım deyil (request-per-process) |
| DI Container | Spring (annotasiya əsaslı) | Laravel/Symfony (attribute + reflection) |
| Named arguments | Yoxdur (Builder lazımdır) | PHP 8.0+ (Builder-ə ehtiyacı azaldır) |

## Niyə belə fərqlər var?

### Singleton fərqləri

Java-da Singleton thread safety tələb edir, çünki JVM çox thread-lidir. Enum singleton ən yaxşı həldir, çünki JVM serialization, reflection və thread safety-ni avtomatik təmin edir. PHP-də isə hər HTTP sorğusu ayrı prosesdə icra olunduğu üçün thread safety problemi yoxdur - sadə static xüsusiyyət kifayətdir. Amma müasir PHP-də Singleton əvəzinə DI Container-in singleton binding-i tövsiyə olunur.

### Factory fərqləri

Java-da Factory pattern güclü tip sistemi ilə birləşir - kompiler düzgün tipin qaytarıldığını yoxlayır. PHP-də `match` expression (PHP 8.0+) factory-ni çox qısa yazmağa imkan verir, PHP 8.1-in enum-ları isə factory metodunu birbaşa enum daxilində təyin etməyə imkan verir.

### Observer fərqləri

Java-da Observer pattern `@FunctionalInterface` və lambda ilə çox təmiz yazılır. PHP-də isə `callable` tipi ilə istənilən funksiya, Closure və ya metod dinləyici olaraq istifadə oluna bilər. PHP-nin `SplObserver`/`SplSubject` interfeysleri çox sadədir və praktikada framework-ların öz hadisə sistemləri istifadə olunur.

### Builder fərqləri

Java-da Builder pattern çox istifadə olunur, çünki Java-da named arguments yoxdur və çox parametrli konstruktorlar oxunmaz olur. PHP 8.0-da gələn named arguments Builder pattern-ə olan ehtiyacı əhəmiyyətli dərəcədə azaltdı - `new İstifadəçi(ad: 'Orxan', yaş: 25)` yazmaq Builder zəncirindən daha sadədir. Amma mürəkkəb quruluşlar üçün Builder hələ də faydalıdır.

### Ümumi fəlsəfə

Java dizayn pattern-lərini interfeys və abstract class-lar vasitəsilə ciddi şəkildə tətbiq edir. PHP isə daha çevik yanaşma təklif edir - eyni pattern həm klassik OOP ilə, həm də callable/Closure kimi dinamik alətlərlə tətbiq oluna bilər. Java-nın yanaşması böyük komandalar və uzunmüddətli layihələr üçün daha strukturlaşdırılmış kod yaradır. PHP-nin yanaşması isə kiçik-orta layihələrdə daha sürətli inkişafa imkan verir.
