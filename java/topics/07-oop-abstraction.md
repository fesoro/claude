# Java-da Abstraksiya (Abstraction)

## Mündəricat
1. [Abstraksiya nədir?](#abstraksiya-nədir)
2. [Abstraksiya real dünyada](#abstraksiya-real-dünyada)
3. [Interface ilə abstraksiya](#interface-ilə-abstraksiya)
4. [Abstract class ilə abstraksiya](#abstract-class-ilə-abstraksiya)
5. [Abstraksiya qatları (Layers of Abstraction)](#abstraksiya-qatları-layers-of-abstraction)
6. [Leaky Abstraction anti-pattern](#leaky-abstraction-anti-pattern)
7. [Abstraksiya dizayn prinsipləri](#abstraksiya-dizayn-prinsipləri)
8. [Praktiki nümunələr](#praktiki-nümunələr)
9. [İntervyu Sualları](#i̇ntervyu-sualları)

---

## Abstraksiya nədir?

**Abstraksiya** — mürəkkəbliyi gizlədib, yalnız lazımlı məlumatı göstərməkdir. "Nə edir?" sualına cavab verir, "Necə edir?" sualını gizlədir.

İnkapsulyasiya ilə fərqi:
- **İnkapsulyasiya** — məlumatı (data) gizlədir
- **Abstraksiya** — implementasiyanı (behavior) gizlədir

```java
// Abstraksiya olmadan — bütün detallar açıqdır:
public class YanlışEmailGöndərici {
    public void göndər(String alıcı, String mətn) {
        // İstifadəçi bütün texniki detalları bilməli olur:
        Properties props = new Properties();
        props.put("mail.smtp.host", "smtp.gmail.com");
        props.put("mail.smtp.port", "587");
        props.put("mail.smtp.auth", "true");
        props.put("mail.smtp.starttls.enable", "true");
        Session session = Session.getInstance(props, new Authenticator() {
            protected PasswordAuthentication getPasswordAuthentication() {
                return new PasswordAuthentication("user@gmail.com", "pass");
            }
        });
        // ... daha 20+ sətir kod
    }
}

// Abstraksiya ilə — sadə interfeys:
public interface EmailXidməti {
    void göndər(String alıcı, String mövzu, String mətn);
}

// Kənar kod yalnız bu sətri bilir:
emailXidməti.göndər("anar@email.com", "Salam!", "Necəsən?");
// SMTP, SSL, port — hamısı gizlidir
```

---

## Abstraksiya real dünyada

| Real obyekt | Abstraksiya | Gizlənən detallar |
|---|---|---|
| Avtomobil | Sükan, qaz, əyləc | Mühərrik, ötürücü qutu |
| ATM | Kart taxma, PIN, məbləğ | Bank protokolları |
| TV pultu | Düymələr | İnfraqırmızı signal |
| Java List | add(), get(), size() | ArrayList/LinkedList fərqi |

```java
// Java Collections — abstraksiya ustası
List<String> siyahı; // Interface — abstraksiya

// Konkret implementasiya gizlidir
siyahı = new ArrayList<>();   // Dinamik massiv
siyahı = new LinkedList<>();  // İkili əlaqəli siyahı
siyahı = new Vector<>();      // Sinxronizə edilmiş

// İstifadəçi üçün API eynidir!
siyahı.add("element");
siyahı.get(0);
siyahı.size();
// Daxili fərqlər: ArrayList O(1) get, LinkedList O(1) add-remove
```

---

## Interface ilə abstraksiya

Interface ən güclü abstraksiya formasıdır — yalnız "nə" var, "necə" yoxdur.

```java
// Verilənlər bazası abstraksiyası
public interface VərilənlərBazası {
    <T> Optional<T> tap(int id, Class<T> tip);
    <T> List<T> hamısı(Class<T> tip);
    <T> T saxla(T entity);
    <T> boolean sil(int id, Class<T> tip);
    boolean əlaqə_var();
}

// PostgreSQL implementasiyası
public class PostgreSQLVB implements VərilənlərBazası {
    private final String url;
    private Connection bağlantı;

    public PostgreSQLVB(String url, String istifadəçi, String şifrə) {
        this.url = url;
        // JDBC bağlantısı yarat
        try {
            this.bağlantı = DriverManager.getConnection(url, istifadəçi, şifrə);
        } catch (SQLException e) {
            throw new RuntimeException("Bağlantı xətası", e);
        }
    }

    @Override
    public <T> Optional<T> tap(int id, Class<T> tip) {
        // SQL sorğu: SELECT * FROM table WHERE id = ?
        // ResultSet emal et, object-ə çevir
        return Optional.empty(); // sadəlik üçün
    }

    @Override
    public <T> List<T> hamısı(Class<T> tip) {
        // SELECT * FROM table
        return List.of(); // sadəlik üçün
    }

    @Override
    public <T> T saxla(T entity) {
        // INSERT veya UPDATE
        return entity;
    }

    @Override
    public <T> boolean sil(int id, Class<T> tip) {
        // DELETE FROM table WHERE id = ?
        return true;
    }

    @Override
    public boolean əlaqə_var() {
        try {
            return bağlantı != null && !bağlantı.isClosed();
        } catch (SQLException e) {
            return false;
        }
    }
}

// MongoDB implementasiyası
public class MongoDBVB implements VərilənlərBazası {
    // Fərqli implementasiya, eyni interfeys!
    @Override
    public <T> Optional<T> tap(int id, Class<T> tip) {
        // MongoDB document-dən obyekt yarat
        return Optional.empty();
    }

    // ... digər metodlar

    @Override
    public <T> List<T> hamısı(Class<T> tip) { return List.of(); }
    @Override
    public <T> T saxla(T entity) { return entity; }
    @Override
    public <T> boolean sil(int id, Class<T> tip) { return true; }
    @Override
    public boolean əlaqə_var() { return true; }
}

// In-Memory implementasiyası (Test üçün)
public class YaddaşVB implements VərilənlərBazası {
    private final Map<String, Map<Integer, Object>> cədvəllər = new HashMap<>();

    @Override
    @SuppressWarnings("unchecked")
    public <T> Optional<T> tap(int id, Class<T> tip) {
        Map<Integer, Object> cədvəl = cədvəllər.getOrDefault(tip.getName(), Map.of());
        return Optional.ofNullable((T) cədvəl.get(id));
    }

    @Override
    @SuppressWarnings("unchecked")
    public <T> List<T> hamısı(Class<T> tip) {
        return (List<T>) new ArrayList<>(
            cədvəllər.getOrDefault(tip.getName(), Map.of()).values()
        );
    }

    @Override
    public <T> T saxla(T entity) {
        cədvəllər.computeIfAbsent(entity.getClass().getName(), k -> new HashMap<>())
                 .put(System.identityHashCode(entity), entity);
        return entity;
    }

    @Override
    public <T> boolean sil(int id, Class<T> tip) {
        Map<Integer, Object> cədvəl = cədvəllər.get(tip.getName());
        return cədvəl != null && cədvəl.remove(id) != null;
    }

    @Override
    public boolean əlaqə_var() { return true; }
}

// İstifadəçi kodu — hansı VB istifadə edildiyi bilinmir:
public class İstifadəçiXidməti {
    private final VərilənlərBazası vb; // abstraksiya!

    public İstifadəçiXidməti(VərilənlərBazası vb) {
        this.vb = vb; // Dependency Injection
    }

    public Optional<İstifadəçi> tapİstifadəçi(int id) {
        return vb.tap(id, İstifadəçi.class); // PostgreSQL, MongoDB, Yaddaş — bilinmir!
    }
}
```

---

## Abstract class ilə abstraksiya

Abstract class qismən abstraksiya verir — ümumi davranış saxlanır, alt sinifə xüsusi hissəsi buraxılır.

```java
public abstract class VeriMessenger {
    // Ümumi konfiqurasiya
    private final String serverUrl;
    private final int port;
    protected boolean bağlıdır = false;

    protected VeriMessenger(String serverUrl, int port) {
        this.serverUrl = serverUrl;
        this.port = port;
    }

    // Şablon — alqoritm sabit, bəzi addımlar dəyişir
    public final void mesaj_göndər(String alıcı, String mətn) {
        bağlantı_qur();
        String formatlanmışMesaj = mesajı_formatla(alıcı, mətn); // Abstract
        şifrələ(formatlanmışMesaj); // Hook — opsional
        şəbəkəyəGöndər(formatlanmışMesaj); // Abstract
        bağlantı_kəs();
    }

    // Abstract — alt sinif implement etməlidir
    protected abstract String mesajı_formatla(String alıcı, String mətn);
    protected abstract void şəbəkəyəGöndər(String mesaj);

    // Hook — opsional override
    protected void şifrələ(String mesaj) {
        // Default: şifrələmə yoxdur
    }

    // Concrete — dəyişməyən hissə
    private void bağlantı_qur() {
        System.out.printf("TCP bağlantısı: %s:%d%n", serverUrl, port);
        bağlıdır = true;
    }

    private void bağlantı_kəs() {
        bağlıdır = false;
        System.out.println("Bağlantı kəsildi");
    }
}

// JSON mesajlaşma
public class JSONMessenger extends VeriMessenger {
    public JSONMessenger(String serverUrl) {
        super(serverUrl, 8080);
    }

    @Override
    protected String mesajı_formatla(String alıcı, String mətn) {
        return """
               {"to": "%s", "body": "%s", "timestamp": %d}
               """.formatted(alıcı, mətn, System.currentTimeMillis());
    }

    @Override
    protected void şəbəkəyəGöndər(String mesaj) {
        System.out.println("JSON göndərildi: " + mesaj);
    }

    @Override
    protected void şifrələ(String mesaj) {
        System.out.println("JWT ilə imzalandı");
    }
}

// XML mesajlaşma
public class XMLMessenger extends VeriMessenger {
    public XMLMessenger(String serverUrl) {
        super(serverUrl, 8081);
    }

    @Override
    protected String mesajı_formatla(String alıcı, String mətn) {
        return "<message><to>%s</to><body>%s</body></message>"
               .formatted(alıcı, mətn);
    }

    @Override
    protected void şəbəkəyəGöndər(String mesaj) {
        System.out.println("XML göndərildi: " + mesaj);
    }
}
```

---

## Abstraksiya qatları (Layers of Abstraction)

Böyük proqramlarda abstraksiya çoxlu qatlara bölünür. Hər qat yalnız üzündəki qatla danışır.

```
Presentation Layer (UI)         ← İstifadəçi görür
       ↕
Service Layer (Business Logic)  ← İş məntiqi
       ↕
Repository Layer (Data Access)  ← VB müraciəti
       ↕
Database Layer                  ← Əsl VB
```

```java
// === Repository qatı ===
public interface İstifadəçiAnbarı {
    Optional<İstifadəçi> tapId_ilə(int id);
    Optional<İstifadəçi> tapEmail_ilə(String email);
    İstifadəçi saxla(İstifadəçi istifadəçi);
    void sil(int id);
    List<İstifadəçi> hamısı();
}

// === Service qatı — Repository bilmez necə saxlayır ===
public class İstifadəçiXidməti {
    private final İstifadəçiAnbarı anbar;
    private final EmailXidməti email;
    private final ŞifrəHasher hasher;

    public İstifadəçiXidməti(İstifadəçiAnbarı anbar,
                              EmailXidməti email,
                              ŞifrəHasher hasher) {
        this.anbar = anbar;
        this.email = email;
        this.hasher = hasher;
    }

    public İstifadəçi qeydiyyatdan_keçir(String ad, String emailAdres, String şifrə) {
        // Email artıq istifadədədir?
        if (anbar.tapEmail_ilə(emailAdres).isPresent()) {
            throw new IllegalStateException("Email artıq istifadədədir: " + emailAdres);
        }

        // Şifrəni hashla
        String hashliŞifrə = hasher.hash(şifrə);

        // İstifadəçini saxla
        İstifadəçi yeni = new İstifadəçi(0, ad, emailAdres, hashliŞifrə);
        İstifadəçi saxlanmış = anbar.saxla(yeni);

        // Salamlama emaili göndər
        email.göndər(emailAdres, "Xoş gəlmisiniz!", "Qeydiyyatınız tamamlandı!");

        return saxlanmış;
    }

    public İstifadəçi tapİstifadəçi(int id) {
        return anbar.tapId_ilə(id)
                    .orElseThrow(() -> new RuntimeException("İstifadəçi tapılmadı: " + id));
    }
}

// === Controller/Presentation qatı — Service bilmez necə işləyir ===
public class İstifadəçiController {
    private final İstifadəçiXidməti xidmət;

    public İstifadəçiController(İstifadəçiXidməti xidmət) {
        this.xidmət = xidmət;
    }

    public Map<String, Object> qeydiyyat(Map<String, String> sorğu) {
        try {
            String ad = sorğu.get("ad");
            String email = sorğu.get("email");
            String şifrə = sorğu.get("şifrə");

            İstifadəçi yeni = xidmət.qeydiyyatdan_keçir(ad, email, şifrə);

            return Map.of("status", "uğurlu",
                         "id", yeni.getId(),
                         "mesaj", "Qeydiyyat tamamlandı");
        } catch (Exception e) {
            return Map.of("status", "xəta",
                         "mesaj", e.getMessage());
        }
    }
}
```

---

## Leaky Abstraction anti-pattern

**Leaky Abstraction** — abstraksiya implementasiya detallarını "sızdıranda" baş verir. Bu, abstraksiyadan faydalanan kodun gizlənmiş detalları bilməsini tələb edir.

```java
// YANLIŞ: Leaky Abstraction nümunəsi
public interface SürətliXidmət {
    List<İstifadəçi> istifadəçiləriTap(String filtr);
}

public class PostgreSQLXidmət implements SürətliXidmət {
    @Override
    public List<İstifadəçi> istifadəçiləriTap(String filtr) {
        // Leaky: filtr SQL wildcard istifadə edir!
        // "%Anar%" — bu SQL sintaksisidir, abstraksiyadan sızır!
        return vb.sorğu("SELECT * FROM users WHERE name LIKE " + filtr);
    }
}

// İstifadəçi kodu SQL bilməlidir — abstraksiya sızır:
xidmət.istifadəçiləriTap("%Anar%"); // SQL bilmədən istifadə edə bilmir!

// DOĞRU: Sızmayan abstraksiya
public interface İstifadəçiAxtarışı {
    List<İstifadəçi> adaGörəTap(String ad);
    List<İstifadəçi> emailəGörəTap(String email);
    List<İstifadəçi> filtrəGörəTap(AxtarışFiltresi filtr);
}

// Filtr özü abstrakt:
public record AxtarışFiltresi(
    String adSubstringi,
    Integer minYaş,
    Integer maxYaş,
    boolean yalnızAktiv
) {
    public static AxtarışFiltresi ad(String ad) {
        return new AxtarışFiltresi(ad, null, null, false);
    }
}

// İstifadəçi kodu SQL bilmir:
axtarış.adaGörəTap("Anar"); // Sadə!
axtarış.filtrəGörəTap(AxtarışFiltresi.ad("Anar")); // Domain dili!
```

---

## Abstraksiya dizayn prinsipləri

### Program to interface, not implementation

```java
// YANLIŞ: konkret sinifə proqramla
ArrayList<String> siyahı = new ArrayList<>(); // ArrayList-ə bağlıdır
HashMap<String, Integer> xəritə = new HashMap<>(); // HashMap-ə bağlıdır

// DOĞRU: interface-ə proqramla
List<String> siyahı = new ArrayList<>();    // asanlıqla LinkedList-ə keç
Map<String, Integer> xəritə = new HashMap<>(); // asanlıqla TreeMap-ə keç

// Metod imzalarında da:
// YANLIŞ:
public ArrayList<String> siyahıAl() {
    return new ArrayList<>();
}

// DOĞRU:
public List<String> siyahıAl() {
    return new ArrayList<>(); // implementasiya dəyişə bilər
}
```

### Dependency Inversion Principle

```java
// YANLIŞ: High-level modul low-level moduldan asılıdır
public class Sifariş {
    private final MySQLBağlantısı mysql = new MySQLBağlantısı(); // birbaşa asılılıq!

    public void saxla() {
        mysql.daxil_et(this);
    }
}

// DOĞRU: Hər ikisi abstraksiyadan asılıdır
public class Sifariş {
    private final SifarişAnbarı anbar; // interface-dən asılı

    public Sifariş(SifarişAnbarı anbar) { // Dependency Injection
        this.anbar = anbar;
    }

    public void saxla() {
        anbar.saxla(this); // MySQL, PostgreSQL, Memory — bilinmir
    }
}
```

---

## Praktiki nümunələr

### Ödəniş sistemi abstraksiyası

```java
// Abstraksiya qatları:
// 1. PaymentGateway (ən yüksək)
// 2. PaymentProcessor
// 3. NetworkClient (ən aşağı)

@FunctionalInterface
public interface ÖdənişŞəbəkəsi {
    ÖdənişNəticəsi göndər(ÖdənişSorğusu sorğu);
}

public record ÖdənişSorğusu(
    String kartNömrəsi,
    double məbləğ,
    String valyuta,
    String təsvir
) {}

public record ÖdənişNəticəsi(
    boolean uğurlu,
    String əməliyyatId,
    String mesaj
) {}

// Interface — abstraksiya
public interface ÖdənişGates {
    ÖdənişNəticəsi ödəniş_işlə(double məbləğ, String kartNo, String valyuta);
    boolean geri_al(String əməliyyatId);
}

// Stripe implementasiyası
public class StripeGates implements ÖdənişGates {
    private final ÖdənişŞəbəkəsi şəbəkə;
    private final String apiKey;

    public StripeGates(String apiKey, ÖdənişŞəbəkəsi şəbəkə) {
        this.apiKey = apiKey;
        this.şəbəkə = şəbəkə;
    }

    @Override
    public ÖdənişNəticəsi ödəniş_işlə(double məbləğ, String kartNo, String valyuta) {
        // Stripe-a xas formatlaşdırma — kənar koddan gizlidir
        ÖdənişSorğusu sorğu = new ÖdənişSorğusu(
            kartNo, məbləğ, valyuta, "Stripe charge"
        );
        return şəbəkə.göndər(sorğu);
    }

    @Override
    public boolean geri_al(String əməliyyatId) {
        System.out.println("Stripe refund: " + əməliyyatId);
        return true;
    }
}

// PayPal implementasiyası
public class PayPalGates implements ÖdənişGates {
    @Override
    public ÖdənişNəticəsi ödəniş_işlə(double məbləğ, String kartNo, String valyuta) {
        System.out.println("PayPal ilə ödəniş: " + məbləğ + " " + valyuta);
        return new ÖdənişNəticəsi(true, "PP-" + System.currentTimeMillis(), "Uğurlu");
    }

    @Override
    public boolean geri_al(String əməliyyatId) {
        System.out.println("PayPal refund: " + əməliyyatId);
        return true;
    }
}

// Yüksək abstraksiya qatı — ÖdənişXidməti
public class ÖdənişXidməti {
    private final ÖdənişGates gates; // konkret implementasiya gizlidir

    public ÖdənişXidməti(ÖdənişGates gates) {
        this.gates = gates;
    }

    public boolean sifariş_ödə(Sifariş sifariş, String kartNo) {
        System.out.println("Sifariş ödənilir: " + sifariş.getId());
        ÖdənişNəticəsi nəticə = gates.ödəniş_işlə(
            sifariş.getUmumiMəbləğ(),
            kartNo,
            "AZN"
        );

        if (nəticə.uğurlu()) {
            System.out.println("Ödəniş tamamlandı: " + nəticə.əməliyyatId());
            return true;
        } else {
            System.out.println("Ödəniş rədd edildi: " + nəticə.mesaj());
            return false;
        }
    }
}

// Sifariş — sadəlik üçün
record Sifariş(int id, double umumiMəbləğ) {
    public int getId() { return id; }
    public double getUmumiMəbləğ() { return umumiMəbləğ; }
}

// İstifadə — Stripe və ya PayPal — xidmət bilmir:
ÖdənişGates stripe = new StripeGates("sk_test_xxx",
    sorğu -> new ÖdənişNəticəsi(true, "ch_" + System.nanoTime(), "OK"));
ÖdənişGates paypal = new PayPalGates();

// Stripe ilə:
ÖdənişXidməti xidmət = new ÖdənişXidməti(stripe);
xidmət.sifariş_ödə(new Sifariş(1, 150.0), "4111111111111111");

// PayPal ilə (konfiqurasiya dəyişdirildi, kod deyil!):
ÖdənişXidməti xidmət2 = new ÖdənişXidməti(paypal);
xidmət2.sifariş_ödə(new Sifariş(2, 75.0), "4111111111111111");
```

---

## İntervyu Sualları

**S1: Abstraksiya nədir və niyə lazımdır?**
> Abstraksiya — mürəkkəbliyi idarə etmək üsuludur. İmplementasiya detallarını gizlədib, sadə interfeys göstərir. Nəticədə: kod daha az mürəkkəb görünür, daha asan istifadə edilir, implementasiya dəyişsə belə istifadəçi kodu dəyişmir.

**S2: Abstraksiya ilə inkapsulyasiya arasındakı fərq?**
> **İnkapsulyasiya** — məlumatı (state) gizlədir, `private` sahələr və getter/setter-lər. **Abstraksiya** — davranışı (behavior) gizlədir, interface/abstract class. İnkapsulyasiya "nə saxlanır" sualını gizlədir; abstraksiya "necə edilir" sualını gizlədir.

**S3: Leaky Abstraction nədir?**
> Abstraksiya gizlətməli olduğu detalları "sızdıranda" baş verir. İstifadəçi kod abstraksiyadan faydalanmaq üçün, gizlənmiş implementasiya detallarını bilməli olur. Misal: SQL string qəbul edən "ümumi" bir interfeys.

**S4: "Program to interface, not implementation" nə deməkdir?**
> Dəyişənləri, metod parametrlərini, return tiplərini konkret sinif deyil, interface/abstract class tipində müəyyən et. Bu, implementasiyanı asanlıqla dəyişdirməyə imkan verir (Dependency Injection, Strategy pattern).

**S5: Abstraksiya qaçılmaz mürəkkəbliyə qarşı necə işləyir?**
> Abstraksiya mürəkkəbliyi aradan qaldırmır — onu gizlədir. Hər qatı anlayan mühəndis yalnız öz qatının abstraksiyasını bilir. Misal: Web developer HTTP bilir, TCP/IP bilməsi lazım deyil.

**S6: Java-da abstraksiya hansı mexanizmlərlə realizə olunur?**
> 1. **Interface** — tam abstraksiya (yalnız müqavilə), 2. **Abstract class** — qismən abstraksiya (ümumi implementasiya + abstract metodlar), 3. **Access modifiers (private/protected)** — detalları gizlət, 4. **Packages** — paket səviyyəsində gizlillik.

**S7: Dependency Injection abstraksiya ilə necə əlaqəlidir?**
> DI abstraksiyaları bir-birinə bağlamaq üsuludur. Konkret implementasiya (PostgreSQL, Stripe) yüksək qata inject edilir. Yüksək qat yalnız interface-i (VərilənlərBazası, ÖdənişGates) görür — konkret implementasiyadan asılı deyil. Bu DIP (Dependency Inversion Principle)-i realizə edir.
