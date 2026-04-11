# Java-da Abstract Siniflər (Abstract Classes)

## Mündəricat
1. [Abstract class nədir?](#abstract-class-nədir)
2. [Abstract metodlar](#abstract-metodlar)
3. [Concrete metodlar abstract class-da](#concrete-metodlar-abstract-class-da)
4. [Constructor in abstract class](#constructor-in-abstract-class)
5. [Template Method Pattern](#template-method-pattern)
6. [Abstract class vs Interface](#abstract-class-vs-interface)
7. [Praktiki nümunələr](#praktiki-nümunələr)
8. [İntervyu Sualları](#i̇ntervyu-sualları)

---

## Abstract class nədir?

**Abstract class** — tam olmayan, yarım tamamlanmış bir siniflə benzer. Birbaşa obyekti yaradıla bilməz — yalnız ondan miras alan (extends) konkret sinifin obyekti yaradılır.

Real dünya analoqu: "Forma" (Shape) abstract sinifdir — "Dairə", "Kvadrat", "Üçbucaq" konkret formlardır. "Forma"nın özü çox müəyyənsizdir ki, bilavasitə istifadə edilsin.

```java
// Abstract sinif — abstract açar sözü ilə
public abstract class Forma {
    // Abstract metod — implementasiya yoxdur, yalnız imza var
    public abstract double sahə();
    public abstract double perimetr();

    // Konkret metod — implementasiya var
    public void məlumatı_göstər() {
        System.out.printf("Sahə: %.2f, Perimetr: %.2f%n", sahə(), perimetr());
    }
}

// YANLIŞ: abstract sinifin obyektini yaratmaq
// Forma f = new Forma(); // XƏTA: Cannot instantiate the type Forma

// DOĞRU: konkret alt sinif vasitəsilə
public class Dairə extends Forma {
    private final double radius;

    public Dairə(double radius) {
        this.radius = radius;
    }

    @Override
    public double sahə() {
        return Math.PI * radius * radius;
    }

    @Override
    public double perimetr() {
        return 2 * Math.PI * radius;
    }
}

// İstifadə:
Forma f = new Dairə(5.0); // Polimorfizm — abstract tip ilə istinad
f.məlumatı_göstər(); // Sahə: 78.54, Perimetr: 31.42
```

---

## Abstract metodlar

Abstract metod — bədəni (body) olmayan metod. Yalnız abstract sinifdə müəyyən edilə bilər.

```java
public abstract class Heyvan {
    protected final String ad;
    protected final int yaş;

    public Heyvan(String ad, int yaş) {
        this.ad = ad;
        this.yaş = yaş;
    }

    // Abstract metodlar — alt sinifin mütləq implement etməsi lazım
    public abstract String səsVer();
    public abstract String hərəkətTərzi();

    // Alt siniflər bunu override etməyə bilər
    public String özünüTanıt() {
        return String.format("Mən %s-am, %d yaşındayam. Səsim: %s",
                             ad, yaş, səsVer());
    }
}

public class İt extends Heyvan {
    private final String cins;

    public İt(String ad, int yaş, String cins) {
        super(ad, yaş); // abstract sinifin constructor-u çağırılır
        this.cins = cins;
    }

    @Override
    public String səsVer() {
        return "Hav-hav!";
    }

    @Override
    public String hərəkətTərzi() {
        return "Qaçır";
    }
}

public class Balıq extends Heyvan {
    public Balıq(String ad, int yaş) {
        super(ad, yaş);
    }

    @Override
    public String səsVer() {
        return "..."; // Balıqlar səs vermir
    }

    @Override
    public String hərəkətTərzi() {
        return "Üzür";
    }
}
```

### Abstract metodların qaydalları

```java
// YANLIŞ: abstract sinif olmayan sinifdə abstract metod
public class NormalSinif {
    // public abstract void metod(); // XƏTA!
}

// YANLIŞ: abstract metodun bədəni olması
public abstract class XetaliSinif {
    // public abstract void metod() { } // XƏTA!
}

// YANLIŞ: abstract metodun private olması
public abstract class XetaliSinif2 {
    // private abstract void metod(); // XƏTA! Alt sinif görə bilməz
}

// YANLIŞ: abstract metodun final olması
public abstract class XetaliSinif3 {
    // public abstract final void metod(); // XƏTA! final override oluna bilməz
}

// DOĞRU:
public abstract class DoghuSinif {
    public abstract void publiq_metod();
    protected abstract void protected_metod();
    abstract void paket_metod(); // package-private
}
```

---

## Concrete metodlar abstract class-da

Abstract class həm abstract, həm də implementasiyası olan (concrete) metodlara malik ola bilər.

```java
public abstract class Hesab {
    private final String hesabNomresi;
    private double balans;
    private final List<String> tarixçə = new ArrayList<>();

    // Constructor
    protected Hesab(String hesabNomresi, double ilkinBalans) {
        this.hesabNomresi = hesabNomresi;
        this.balans = ilkinBalans;
        tarixçəyəElavəEt("Hesab açıldı: " + ilkinBalans + " AZN");
    }

    // Abstract metodlar — alt siniflər müəyyən edir
    public abstract boolean pul_çıxarabilirmi(double məbləğ);
    public abstract double komissiya_hesabla(double məbləğ);

    // Concrete metodlar — bütün hesablar üçün eyni məntiq
    public final void pul_yatır(double məbləğ) {
        if (məbləğ <= 0) throw new IllegalArgumentException("Məbləğ müsbət olmalıdır");
        balans += məbləğ;
        tarixçəyəElavəEt("Mədaxil: +" + məbləğ + " AZN");
    }

    public final boolean pul_çıxar(double məbləğ) {
        if (!pul_çıxarabilirmi(məbləğ)) {
            System.out.println("Əməliyyat rədd edildi");
            return false;
        }
        double komissiya = komissiya_hesabla(məbləğ);
        balans -= (məbləğ + komissiya);
        tarixçəyəElavəEt("Məxaric: -" + məbləğ + " AZN (komissiya: " + komissiya + ")");
        return true;
    }

    // Protected — yalnız alt siniflərdən çağırılır
    protected void tarixçəyəElavəEt(String qeyd) {
        tarixçə.add(java.time.LocalDateTime.now() + ": " + qeyd);
    }

    // Getters
    public double getBalans() { return balans; }
    public String getHesabNomresi() { return hesabNomresi; }
    public List<String> getTarixçə() { return List.copyOf(tarixçə); }
}

// Cari hesab — overdraft yoxdur
public class CariHesab extends Hesab {
    public CariHesab(String hesabNomresi, double ilkinBalans) {
        super(hesabNomresi, ilkinBalans);
    }

    @Override
    public boolean pul_çıxarabilirmi(double məbləğ) {
        return getBalans() >= məbləğ; // balans kifayət etməlidir
    }

    @Override
    public double komissiya_hesabla(double məbləğ) {
        return 0.0; // cari hesabda komissiya yoxdur
    }
}

// Kredit hesabı — overdraft var
public class KreditHesabı extends Hesab {
    private final double kredit_limiti;

    public KreditHesabı(String hesabNomresi, double ilkinBalans, double kredit_limiti) {
        super(hesabNomresi, ilkinBalans);
        this.kredit_limiti = kredit_limiti;
    }

    @Override
    public boolean pul_çıxarabilirmi(double məbləğ) {
        return (getBalans() + kredit_limiti) >= məbləğ; // kredit limiti daxilindədir
    }

    @Override
    public double komissiya_hesabla(double məbləğ) {
        return məbləğ * 0.02; // 2% komissiya
    }
}
```

---

## Constructor in abstract class

Abstract sinifin constructor-u var, amma bilavasitə çağırıla bilməz. Alt sinif `super()` ilə onu çağırmalıdır.

```java
public abstract class Nəqliyyat {
    private final String marka;
    private final String model;
    private final int istehsalİli;
    private int cariYanacaq;
    private final int maksTutum;

    // Protected constructor — yalnız alt siniflərdən çağırılır
    protected Nəqliyyat(String marka, String model, int istehsalİli, int maksTutum) {
        this.marka = marka;
        this.model = model;
        this.istehsalİli = istehsalİli;
        this.maksTutum = maksTutum;
        this.cariYanacaq = maksTutum; // tam dolu başla
        System.out.println(marka + " " + model + " hazırlandı");
    }

    // Abstract metodlar
    public abstract double yanacaqSərfi(int km); // hər km üçün litr
    public abstract int maksimumSürət();

    // Konkret metodlar
    public void gedə_bilər_km() {
        double litrPerKm = yanacaqSərfi(1);
        System.out.printf("%s %.1f km gedə bilər%n",
                          modelAdı(), cariYanacaq / litrPerKm);
    }

    public String modelAdı() {
        return marka + " " + model + " (" + istehsalİli + ")";
    }

    // Getters
    public String getMarka() { return marka; }
    public String getModel() { return model; }
}

public class Avtomobil extends Nəqliyyat {
    private final int silindrSayi;

    public Avtomobil(String marka, String model, int istehsalİli, int silindrSayi) {
        super(marka, model, istehsalİli, 60); // 60 litr yanacaq tutumu
        this.silindrSayi = silindrSayi;
    }

    @Override
    public double yanacaqSərfi(int km) {
        return silindrSayi > 4 ? 0.12 : 0.08; // litr/km
    }

    @Override
    public int maksimumSürət() {
        return 220;
    }
}

public class Motosiklet extends Nəqliyyat {
    public Motosiklet(String marka, String model, int istehsalİli) {
        super(marka, model, istehsalİli, 20); // 20 litr yanacaq tutumu
    }

    @Override
    public double yanacaqSərfi(int km) {
        return 0.04; // litr/km — daha qənaətli
    }

    @Override
    public int maksimumSürət() {
        return 180;
    }
}
```

---

## Template Method Pattern

**Template Method** — GOF dizayn pattern-i. Abstract sinif alqoritmin skeletini müəyyən edir, alt siniflər bəzi addımları öz istəklərinə görə dəyişdirir.

```java
/**
 * Template Method Pattern — Məlumat İxracı
 * Alqoritmin skeleti: başla → məlumatı al → emal et → formatla → ixrac et → bitir
 */
public abstract class Məlumatİxracatçı {

    // ŞABLON METOD — final, dəyişdirilə bilməz
    public final void ixrac_et(String mənbə, String hədəf) {
        başlat();                           // 1. Hazırlıq
        List<String> məlumat = məlumat_al(mənbə);  // 2. Məlumat alma — abstract
        List<String> emal = emal_et(məlumat);      // 3. Emal — hook (opsional)
        String nəticə = formatla(emal);             // 4. Formatlama — abstract
        ixrac_et_hədəfə(nəticə, hədəf);           // 5. İxrac — abstract
        bitir();                            // 6. Tamamlama
    }

    // Abstract addımlar — alt sinif mütləq implement etməlidir
    protected abstract List<String> məlumat_al(String mənbə);
    protected abstract String formatla(List<String> məlumat);
    protected abstract void ixrac_et_hədəfə(String məlumat, String hədəf);

    // Hook metodlar — opsional, alt sinif override edə bilər
    protected List<String> emal_et(List<String> məlumat) {
        return məlumat; // default: heç nə dəyişmə
    }

    // Invariant addımlar — dəyişdirilməz
    private void başlat() {
        System.out.println("[" + getClass().getSimpleName() + "] İxrac başladı: " +
                           java.time.LocalTime.now());
    }

    private void bitir() {
        System.out.println("[" + getClass().getSimpleName() + "] İxrac tamamlandı");
    }
}

// CSV ixracatçısı
public class CSVİxracatçı extends Məlumatİxracatçı {
    @Override
    protected List<String> məlumat_al(String mənbə) {
        // Verilənlər bazasından oxu (simulyasiya)
        return List.of("Ad,Soyad,Yaş", "Anar,Hüseynov,30", "Leyla,Əliyeva,25");
    }

    @Override
    protected String formatla(List<String> məlumat) {
        return String.join("\n", məlumat);
    }

    @Override
    protected void ixrac_et_hədəfə(String məlumat, String hədəf) {
        System.out.println("CSV " + hədəf + " faylına yazıldı:\n" + məlumat);
    }
}

// JSON ixracatçısı
public class JSONİxracatçı extends Məlumatİxracatçı {
    @Override
    protected List<String> məlumat_al(String mənbə) {
        return List.of("Anar Hüseynov:30", "Leyla Əliyeva:25");
    }

    @Override
    protected List<String> emal_et(List<String> məlumat) {
        // Məlumatı filtrele — 28 yaşdan böyüklər
        return məlumat.stream()
                      .filter(s -> Integer.parseInt(s.split(":")[1]) > 28)
                      .toList();
    }

    @Override
    protected String formatla(List<String> məlumat) {
        StringBuilder sb = new StringBuilder("[\n");
        for (String s : məlumat) {
            String[] hissələr = s.split(":");
            sb.append(String.format("""
                  {"ad": "%s", "yaş": %s},
                """, hissələr[0], hissələr[1]));
        }
        sb.append("]");
        return sb.toString();
    }

    @Override
    protected void ixrac_et_hədəfə(String məlumat, String hədəf) {
        System.out.println("JSON API-yə göndərildi: " + hədəf + "\n" + məlumat);
    }
}

// İstifadə:
Məlumatİxracatçı csv = new CSVİxracatçı();
csv.ixrac_et("verilənlər_bazası", "hesabat.csv");

Məlumatİxracatçı json = new JSONİxracatçı();
json.ixrac_et("API", "https://api.example.com/upload");
```

### Game Loop — Template Method nümunəsi

```java
public abstract class Oyun {

    // Şablon metod — oyunun axışı
    public final void oyna() {
        başlat();
        while (!oyun_bitmişdir()) {
            addım_at();
        }
        bitir();
    }

    protected abstract void başlat();
    protected abstract boolean oyun_bitmişdir();
    protected abstract void addım_at();
    protected abstract void bitir();
}

public class Satranc extends Oyun {
    private int hərəkətSayı = 0;

    @Override
    protected void başlat() {
        System.out.println("Satranç başladı — ağ hərəkət edir");
    }

    @Override
    protected boolean oyun_bitmişdir() {
        return hərəkətSayı >= 10; // sadəlik üçün
    }

    @Override
    protected void addım_at() {
        hərəkətSayı++;
        System.out.println("Hərəkət " + hərəkətSayı + ": " +
                           (hərəkətSayı % 2 == 0 ? "Qara" : "Ağ") + " hərəkət etdi");
    }

    @Override
    protected void bitir() {
        System.out.println("Oyun bitdi! " + hərəkətSayı + " hərəkət edildi");
    }
}
```

---

## Abstract class vs Interface

```java
// NƏ VAXT ABSTRACT CLASS:
// 1. "is-a" münasibəti — Pişik bir Heyvandır
// 2. Ortaq state (sahə) lazımdır
// 3. Konstruktor məntiqi lazımdır
// 4. protected üzvlər lazımdır
// 5. Non-public metodlar lazımdır

abstract class Heyvan {
    protected String ad; // ortaq state
    protected int enerji = 100;

    protected Heyvan(String ad) { // konstruktor məntiqi
        this.ad = ad;
    }

    protected void enerjiXərclə(int miqdар) { // protected konkret metod
        this.enerji -= miqdар;
    }

    public abstract void səsVer();
}

// NƏ VAXT INTERFACE:
// 1. "can-do" münasibəti — Pişik yata bilər
// 2. Çoxlu miras lazımdır
// 3. Lambda ilə istifadə ediləcək
// 4. API müqaviləsi müəyyən etmək

interface Yuxuluq {
    void yat(int saat);
    default void uzanGözləriniYum() {
        System.out.println("Uzanır...");
        yat(1);
    }
}

interface Ovçu {
    void ov_et(String heyvan);
}

// Pişik həm Heyvan-dır, həm Yuxuluq-dur, həm Ovçu-dur
class Pişik extends Heyvan implements Yuxuluq, Ovçu {
    public Pişik(String ad) { super(ad); }

    @Override public void səsVer() { System.out.println("Miyav!"); }
    @Override public void yat(int saat) { System.out.println(ad + " " + saat + " saat yatır"); }
    @Override public void ov_et(String heyvan) { System.out.println(ad + " " + heyvan + " ovladı"); }
}
```

| Meyar | Abstract Class | Interface |
|---|---|---|
| `extends` / `implements` | `extends` | `implements` |
| Birdən çox ola bilərmi? | Xeyr | Bəli |
| Constructor | Var | Yoxdur |
| Sahələr | İstənilən | Yalnız `public static final` |
| Metodlar | İstənilən access modifier | `public` (default/static/private istisna) |
| `abstract` metodlar | Bəli | Bütün metodlar (default/static xaric) |

---

## Praktiki nümunələr

### HTTP Request Handler

```java
public abstract class HttpHandler {
    // Şablon metod
    public final String handle(String method, String path, String body) {
        if (!icazə_var(path)) {
            return "403 Forbidden";
        }
        return switch (method.toUpperCase()) {
            case "GET"    -> get(path);
            case "POST"   -> post(path, body);
            case "PUT"    -> put(path, body);
            case "DELETE" -> delete(path);
            default       -> "405 Method Not Allowed";
        };
    }

    // Hook — default: hamıya icazə
    protected boolean icazə_var(String path) {
        return true;
    }

    // Abstract — alt sinif implement etməlidir
    protected abstract String get(String path);
    protected abstract String post(String path, String body);
    protected abstract String put(String path, String body);
    protected abstract String delete(String path);
}

public class İstifadəçiHandler extends HttpHandler {
    private final Map<String, String> istifadəçilər = new HashMap<>();

    @Override
    protected String get(String path) {
        String id = path.replace("/users/", "");
        return istifadəçilər.getOrDefault(id, "404 Not Found");
    }

    @Override
    protected String post(String path, String body) {
        String id = String.valueOf(istifadəçilər.size() + 1);
        istifadəçilər.put(id, body);
        return "201 Created: " + id;
    }

    @Override
    protected String put(String path, String body) {
        String id = path.replace("/users/", "");
        if (!istifadəçilər.containsKey(id)) return "404 Not Found";
        istifadəçilər.put(id, body);
        return "200 Updated";
    }

    @Override
    protected String delete(String path) {
        String id = path.replace("/users/", "");
        return istifadəçilər.remove(id) != null ? "200 Deleted" : "404 Not Found";
    }
}
```

---

## İntervyu Sualları

**S1: Abstract sinifin obyektini yaratmaq olarmı?**
> Xeyr, birbaşa olmaz. Ancaq abstract sinif tipli istinad (reference) yaradıb, ona konkret alt sinifin obyektini mənimsətmək olar: `Forma f = new Dairə(5);`

**S2: Abstract sinif özü başqa abstract sinifin alt sinifi ola bilərmi?**
> Bəli. Hətta abstract sinif bütün abstract metodları implement etməyə bilər — qalan abstract metodları öz alt siniflərinə buraxar.

**S3: Abstract metodun bədəni (body) ola bilərmi?**
> Xeyr, abstract metod yalnız imzadan ibarətdir, nöqtəli vergüllə bitir: `public abstract void metod();`

**S4: Abstract olmayan sinif abstract sinifin bütün abstract metodlarını implement etmədisə nə baş verir?**
> Kompilyasiya xətası. Ya bütün abstract metodları implement etməlidir, ya da sinif özü də `abstract` elan edilməlidir.

**S5: Abstract sinif `final` ola bilərmi?**
> Xeyr. `abstract` — "mütləq extend edilsin" deməkdir. `final` — "heç vaxt extend edilməsin" deməkdir. Bunlar bir-birinə ziddir.

**S6: Template Method pattern nədir?**
> GOF behavioral pattern-dir. Abstract sinif alqoritmin skeletini (`final` metod kimi) müəyyən edir. Alt siniflər bəzi addımları override edərək davranışı dəyişdirir, amma ümumi axış dəyişmir.

**S7: Abstract sinifin constructor-u niyə lazımdır?**
> Abstract sinif özünün sahələrini (fields) başlatmaq üçün constructor-a ehtiyac duyur. Alt sinif `super(...)` ilə bu constructor-u çağırmalıdır. Bu, kod təkrarının qarşısını alır — hər alt sinif eyni başlatma kodunu yazmaq məcburiyyətindən qurtulur.

**S8: `protected abstract` metod nə vaxt istifadə edilir?**
> Abstract metodu yalnız paket daxilindəki və ya alt siniflərdəki kodun görməsini istəyirsinizsə. Kənar istifadəçilərdən implementasiya detalını gizlətmək üçün public əvəzinə protected istifadə edilir. Template Method pattern-də abstract addımlar çox vaxt protected-dir.
