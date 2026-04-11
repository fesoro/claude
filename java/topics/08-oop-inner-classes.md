# Java-da Daxili Siniflər (Inner Classes)

## Mündəricat
1. [Daxili siniflərin növləri](#daxili-siniflərin-növləri)
2. [Static Nested Class](#static-nested-class)
3. [Non-static Inner Class](#non-static-inner-class)
4. [Local Class](#local-class)
5. [Anonymous Class](#anonymous-class)
6. [Lambda vs Anonymous Class](#lambda-vs-anonymous-class)
7. [Xarici sinif üzvlərinə giriş](#xarici-sinif-üzvlərinə-giriş)
8. [Praktiki nümunələr](#praktiki-nümunələr)
9. [İntervyu Sualları](#i̇ntervyu-sualları)

---

## Daxili siniflərin növləri

Java-da 4 növ daxili sinif var:

```
XariciSinif
├── Static Nested Class     — static açar sözü ilə
├── Non-static Inner Class  — static açar sözü olmadan
├── Local Class             — metod daxilindədir
└── Anonymous Class         — adsız, bir dəfəlik istifadə
```

```java
public class XariciSinif {

    // 1. Static Nested Class
    public static class StatikDaxili {
        public void metod() { System.out.println("Static nested"); }
    }

    // 2. Non-static Inner Class
    public class QeyriStatikDaxili {
        public void metod() { System.out.println("Non-static inner"); }
    }

    public void metoduInner() {
        // 3. Local Class — metod daxilindədir
        class LocalSinif {
            void metod() { System.out.println("Local class"); }
        }
        new LocalSinif().metod();
    }

    public Runnable anonim_yarat() {
        // 4. Anonymous Class — adsız, bilavasitə istifadə
        return new Runnable() {
            @Override
            public void run() { System.out.println("Anonymous class"); }
        };
    }
}
```

---

## Static Nested Class

`static` açar sözü ilə müəyyən edilir. Xarici sinifin obyektindən asılı deyil.

```java
public class Kompüter {
    private String marka;
    private CPU cpu;
    private RAM ram;

    // Static Nested Class — Kompüter-ə aid, amma Kompüter obyekti lazım deyil
    public static class CPU {
        private final String model;
        private final int nüvəSayı;
        private final double sürətGHz;

        public CPU(String model, int nüvəSayı, double sürətGHz) {
            this.model = model;
            this.nüvəSayı = nüvəSayı;
            this.sürətGHz = sürətGHz;
        }

        public String getMəlumat() {
            return model + " (" + nüvəSayı + " nüvə, " + sürətGHz + " GHz)";
        }
    }

    public static class RAM {
        private final int həcmGB;
        private final String növ; // DDR4, DDR5

        public RAM(int həcmGB, String növ) {
            this.həcmGB = həcmGB;
            this.növ = növ;
        }

        public String getMəlumat() {
            return həcmGB + "GB " + növ;
        }
    }

    public Kompüter(String marka, CPU cpu, RAM ram) {
        this.marka = marka;
        this.cpu = cpu;
        this.ram = ram;
    }

    @Override
    public String toString() {
        return marka + " | CPU: " + cpu.getMəlumat() + " | RAM: " + ram.getMəlumat();
    }
}

// İstifadə — Kompüter obyekti olmadan da yaradıla bilər:
Kompüter.CPU cpu = new Kompüter.CPU("Intel Core i9", 16, 3.5); // Static!
Kompüter.RAM ram = new Kompüter.RAM(32, "DDR5");
Kompüter kompüter = new Kompüter("Dell XPS", cpu, ram);
System.out.println(kompüter); // Dell XPS | CPU: Intel Core i9 ...
```

### Builder pattern — Static Nested Class ilə

```java
public final class HTTPSorğu {
    private final String url;
    private final String metod;
    private final Map<String, String> başlıqlar;
    private final String gövdə;
    private final int zaman_aşımı;

    private HTTPSorğu(Builder builder) {
        this.url = builder.url;
        this.metod = builder.metod;
        this.başlıqlar = Map.copyOf(builder.başlıqlar);
        this.gövdə = builder.gövdə;
        this.zaman_aşımı = builder.zaman_aşımı;
    }

    // Static Nested Builder
    public static class Builder {
        private final String url;
        private String metod = "GET";
        private Map<String, String> başlıqlar = new HashMap<>();
        private String gövdə = null;
        private int zaman_aşımı = 30;

        public Builder(String url) {
            this.url = url;
        }

        public Builder metod(String metod) {
            this.metod = metod;
            return this;
        }

        public Builder başlıq(String ad, String dəyər) {
            this.başlıqlar.put(ad, dəyər);
            return this;
        }

        public Builder json_gövdəsi(String json) {
            this.gövdə = json;
            return başlıq("Content-Type", "application/json");
        }

        public Builder zaman_aşımı(int saniyə) {
            this.zaman_aşımı = saniyə;
            return this;
        }

        public HTTPSorğu qur() {
            if (url == null || url.isBlank()) {
                throw new IllegalStateException("URL boş ola bilməz");
            }
            return new HTTPSorğu(this);
        }
    }

    @Override
    public String toString() {
        return metod + " " + url + " (timeout=" + zaman_aşımı + "s)";
    }
}

// İstifadə:
HTTPSorğu sorğu = new HTTPSorğu.Builder("https://api.example.com/users")
    .metod("POST")
    .başlıq("Authorization", "Bearer token123")
    .json_gövdəsi("""
        {"ad": "Anar", "email": "anar@example.com"}
        """)
    .zaman_aşımı(60)
    .qur();
```

---

## Non-static Inner Class

Xarici sinifin obyektinə bağlıdır. Xarici sinifin bütün üzvlərinə (hətta `private`) müraciət edə bilər.

```java
public class BankHesabı {
    private final String hesabNo;
    private double balans;
    private final List<Əməliyyat> tarixçə = new ArrayList<>();

    public BankHesabı(String hesabNo, double ilkinBalans) {
        this.hesabNo = hesabNo;
        this.balans = ilkinBalans;
    }

    // Non-static Inner Class — BankHesabı-nın daxili işini bilir
    public class Əməliyyat {
        private final String növ;
        private final double məbləğ;
        private final java.time.LocalDateTime vaxt;
        private final double əməliyyatdanSonrakıBalans;

        // Inner class xarici sinifin private balansına birbaşa müraciət edir
        private Əməliyyat(String növ, double məbləğ) {
            this.növ = növ;
            this.məbləğ = məbləğ;
            this.vaxt = java.time.LocalDateTime.now();
            // BankHesabı.this.balans — xarici sinifin sahəsi
            this.əməliyyatdanSonrakıBalans = BankHesabı.this.balans;
        }

        public String hesabNomresiAl() {
            return BankHesabı.this.hesabNo; // xarici sinifin sahəsi
        }

        @Override
        public String toString() {
            return "[%s] %s: %+.2f AZN → Balans: %.2f AZN".formatted(
                vaxt.toLocalTime(), növ, məbləğ, əməliyyatdanSonrakıBalans);
        }
    }

    public void pul_yatır(double məbləğ) {
        this.balans += məbləğ;
        tarixçə.add(new Əməliyyat("MƏDAXİL", məbləğ)); // inner class yaradılır
    }

    public boolean pul_çıxar(double məbləğ) {
        if (balans < məbləğ) return false;
        this.balans -= məbləğ;
        tarixçə.add(new Əməliyyat("MƏXARİC", -məbləğ));
        return true;
    }

    public List<Əməliyyat> getTarixçə() {
        return List.copyOf(tarixçə);
    }

    public double getBalans() { return balans; }
}

// İstifadə:
BankHesabı hesab = new BankHesabı("AZ12NABZ000...", 1000.0);
hesab.pul_yatır(500);
hesab.pul_çıxar(200);

// Non-static inner class-ı xaricdən yaratmaq üçün xarici obyekt lazımdır:
BankHesabı.Əməliyyat əm = hesab.new Əməliyyat("TEST", 0); // nadir istifadə

hesab.getTarixçə().forEach(System.out::println);
```

---

## Local Class

Metod daxilində müəyyən edilən sinif. Yalnız həmin metodda istifadə edilə bilər.

```java
public class Hesabat {

    public List<String> xülasə_yarat(List<Map<String, Object>> məlumatlar) {
        // Local class — yalnız bu metod daxilindədir
        class SətirFormatlayıcı {
            private final String ayırıcı;
            private final int genişlik;

            SətirFormatlayıcı(String ayırıcı, int genişlik) {
                this.ayırıcı = ayırıcı;
                this.genişlik = genişlik;
            }

            String formatla(String əsas, Object dəyər) {
                String mətn = "%-" + genişlik + "s %s %s";
                return mətn.formatted(əsas, ayırıcı, dəyər);
            }

            String başlıq(String mətn) {
                return "=".repeat(genişlik + 20) + "\n" +
                       mətn.toUpperCase() + "\n" +
                       "=".repeat(genişlik + 20);
            }
        }

        SətirFormatlayıcı fmt = new SətirFormatlayıcı(":", 20);
        List<String> nəticə = new ArrayList<>();
        nəticə.add(fmt.başlıq("Hesabat Xülasəsi"));

        for (Map<String, Object> sətir : məlumatlar) {
            sətir.forEach((açar, dəyər) ->
                nəticə.add(fmt.formatla(açar, dəyər)));
        }

        return nəticə;
    }

    // Local class-ın xüsusiyyəti: effectively final dəyişənlərə müraciət
    public Runnable çap_işi_yarat(String mesaj) {
        final String prefix = "[JOB]"; // effectively final — inner class görür
        // int dəyişən = 0; // local class görər
        // dəyişən = 1;     // XƏTA: effectively final olur artıq

        class ÇapIşi implements Runnable {
            @Override
            public void run() {
                // prefix-ə müraciət — effectively final olduğu üçün OK
                System.out.println(prefix + " " + mesaj);
            }
        }

        return new ÇapIşi();
    }
}
```

---

## Anonymous Class

Adsız sinif — interface və ya abstract class-ı bilavasitə implementasiya edir. Bir dəfəlik istifadə üçündür.

```java
import java.util.*;

public class AnonymousNümunə {
    public static void main(String[] args) {

        // 1. Runnable — anonim sinif ilə
        Runnable tapşırıq = new Runnable() {
            private int sayac = 0; // anonim sinifdə state ola bilər

            @Override
            public void run() {
                sayac++;
                System.out.println("Tapşırıq icra edildi: " + sayac);
            }
        };
        tapşırıq.run();
        tapşırıq.run();

        // 2. Comparator — anonim sinif ilə
        List<String> adlar = new ArrayList<>(List.of("Zaur", "Anar", "Leyla", "Kamil"));

        Collections.sort(adlar, new Comparator<String>() {
            @Override
            public int compare(String a, String b) {
                // Əlifba sırasına görə, hərfin uzunluğuna görə deyil
                return a.compareToIgnoreCase(b);
            }
        });

        // 3. Abstract class — anonim implementasiya
        abstract class Heyvan {
            abstract String səsVer();
            void özünütanıt() {
                System.out.println("Mən bir heyvanam, səsim: " + səsVer());
            }
        }

        Heyvan bilinməyən = new Heyvan() {
            @Override
            String səsVer() {
                return "Qrr..."; // naməlum heyvan
            }
        };
        bilinməyən.özünütanıt();

        // 4. Interface ilə — anonim sinif
        Comparator<Integer> azalanSıra = new Comparator<Integer>() {
            @Override
            public int compare(Integer a, Integer b) {
                return b - a; // azalan sıra
            }
        };

        List<Integer> rəqəmlər = new ArrayList<>(List.of(5, 2, 8, 1, 9));
        rəqəmlər.sort(azalanSıra);
        System.out.println(rəqəmlər); // [9, 8, 5, 2, 1]
    }
}
```

---

## Lambda vs Anonymous Class

Lambda ifadəsi — functional interface üçün anonim sinifin qısa yazılışıdır.

```java
public class LambdaVsAnonymous {

    @FunctionalInterface
    interface Salam {
        String de(String ad);
    }

    public static void main(String[] args) {

        // === Anonim sinif ===
        Salam a1 = new Salam() {
            @Override
            public String de(String ad) {
                return "Salam, " + ad + "!";
            }
        };

        // === Lambda ===
        Salam l1 = ad -> "Salam, " + ad + "!";

        // Hər ikisi eyni işi görür:
        System.out.println(a1.de("Anar")); // "Salam, Anar!"
        System.out.println(l1.de("Anar")); // "Salam, Anar!"
    }
}
```

### Fərqlər cədvəli

| Xüsusiyyət | Anonim sinif | Lambda |
|---|---|---|
| Funksional interface tələbi | Xeyr (istənilən) | Bəli (yalnız 1 abstract metod) |
| State (sahələr) | Bəli | Xeyr |
| `this` istinadı | Anonim sinifin özünə | Əhatə edən sinifə |
| Birdən çox metod | Bəli | Xeyr |
| Yaddaş | Ayrı sinif faylı yaranır | Invokedynamic (daha səmərəli) |

```java
public class ThisFərqi {
    private String ad = "XariciSinif";

    public void nümunə() {
        // Anonim sinif — öz `this`-i var
        Runnable anonim = new Runnable() {
            private String ad = "AnonimSinif";

            @Override
            public void run() {
                System.out.println(this.ad);          // "AnonimSinif"
                System.out.println(ThisFərqi.this.ad); // "XariciSinif"
            }
        };

        // Lambda — öz `this`-i yoxdur, xarici sinifin `this`-ə istinad edir
        Runnable lambda = () -> {
            System.out.println(this.ad); // "XariciSinif" — xarici sinifin this
        };

        anonim.run();
        lambda.run();
    }
}
```

### Nə zaman hansını istifadə etmək?

```java
// Lambda istifadə et:
// ✓ Yalnız bir metod lazımdır (functional interface)
// ✓ State saxlamaq lazım deyil
// ✓ Qısa, sadə ifadə

List<String> adlar = List.of("Anar", "Leyla", "Kamil");
adlar.stream()
     .filter(a -> a.length() > 4)
     .map(String::toUpperCase)
     .forEach(System.out::println);

// Anonim sinif istifadə et:
// ✓ State lazımdır
// ✓ Birdən çox metod lazımdır
// ✓ Non-functional interface (abstract class)
// ✓ Öz `this` istinadı lazımdır

TimerTask tapşırıq = new TimerTask() {
    private int sayac = 0; // state!

    @Override
    public void run() {
        sayac++;
        System.out.println("Tapşırıq #" + sayac + " icra edildi");
        if (sayac >= 5) {
            cancel(); // TimerTask-ın öz metodu
        }
    }
};
```

---

## Xarici sinif üzvlərinə giriş

```java
public class XariciSinif {
    private int özelDəyər = 10;
    private static int statikDəyər = 20;

    // Static Nested — yalnız STATIC üzvlərə müraciət edə bilər
    public static class StatikDaxili {
        void metod() {
            System.out.println(statikDəyər); // OK — static
            // System.out.println(özelDəyər); // XƏTA! Instance üzv
        }
    }

    // Non-static Inner — HƏR ŞEYə müraciət edə bilər
    public class QeyriStatikDaxili {
        void metod() {
            System.out.println(özelDəyər);   // OK — instance (xarici)
            System.out.println(statikDəyər); // OK — static
        }
    }

    // Metod parametri — effectively final olmalı
    public void metodla(String parametr) {
        class LocalSinif {
            void metod() {
                System.out.println(özelDəyər); // OK — closure
                System.out.println(parametr);  // OK — effectively final
            }
        }
        // parametr = "yeni dəyər"; // Bu olsa, LocalSinif müraciət edə bilməzdi!
    }
}
```

---

## Praktiki nümunələr

### Iterator pattern — Inner Class ilə

```java
public class İstifadəçiSiyahısı implements Iterable<String> {
    private String[] elementlər;
    private int ölçü;

    public İstifadəçiSiyahısı(int tutum) {
        this.elementlər = new String[tutum];
        this.ölçü = 0;
    }

    public void əlavə_et(String element) {
        if (ölçü >= elementlər.length) {
            elementlər = Arrays.copyOf(elementlər, elementlər.length * 2);
        }
        elementlər[ölçü++] = element;
    }

    // Non-static inner class — elementlərə müraciət edir
    private class SiyahıIteratoru implements Iterator<String> {
        private int cariIndeks = 0;

        @Override
        public boolean hasNext() {
            return cariIndeks < ölçü; // xarici sinifin ölçü-sinə müraciət
        }

        @Override
        public String next() {
            if (!hasNext()) throw new NoSuchElementException();
            return elementlər[cariIndeks++]; // xarici sinifin elementlər-inə
        }
    }

    @Override
    public Iterator<String> iterator() {
        return new SiyahıIteratoru(); // inner class qaytarılır
    }
}

// İstifadə:
İstifadəçiSiyahısı siyahı = new İstifadəçiSiyahısı(4);
siyahı.əlavə_et("Anar");
siyahı.əlavə_et("Leyla");
siyahı.əlavə_et("Kamil");

for (String ad : siyahı) { // for-each — iterator() çağırır
    System.out.println(ad);
}
```

### Event Listener — Anonymous Class ilə

```java
public class DüyməNümunəsi {
    // Hadisə interface-i
    @FunctionalInterface
    public interface TıklamaListener {
        void tıklandı(String düyməAdı);
    }

    // Düymə sinfi
    public static class Düymə {
        private final String ad;
        private final List<TıklamaListener> dinləyicilər = new ArrayList<>();

        public Düymə(String ad) {
            this.ad = ad;
        }

        public void dinləyiciƏlavəEt(TıklamaListener dinləyici) {
            dinləyicilər.add(dinləyici);
        }

        public void tıkla() { // Hadisəni tetikle
            dinləyicilər.forEach(d -> d.tıklandı(ad));
        }
    }

    public static void main(String[] args) {
        Düymə saxlaDüyməsi = new Düymə("Saxla");
        Düymə ləğvDüyməsi = new Düymə("Ləğv et");

        // Anonim sinif ilə
        saxlaDüyməsi.dinləyiciƏlavəEt(new TıklamaListener() {
            private int sayac = 0;
            @Override
            public void tıklandı(String düyməAdı) {
                sayac++;
                System.out.println(düyməAdı + " tıklandı (" + sayac + "-ci dəfə)");
            }
        });

        // Lambda ilə (daha qısa, state yoxsa)
        ləğvDüyməsi.dinləyiciƏlavəEt(ad -> System.out.println(ad + " tıklandı — ləğv edildi"));

        // Method reference ilə
        saxlaDüyməsi.dinləyiciƏlavəEt(System.out::println);

        saxlaDüyməsi.tıkla();
        saxlaDüyməsi.tıkla();
        ləğvDüyməsi.tıkla();
    }
}
```

---

## İntervyu Sualları

**S1: Static Nested class ilə Non-static Inner class fərqi nədir?**
> **Static Nested**: `static` açar sözü var, xarici sinifin obyektinə ehtiyac yoxdur, yalnız static üzvlərə müraciət edə bilər. **Non-static Inner**: xarici sinifin obyektinə bağlıdır, xarici sinifin bütün üzvlərinə (hətta `private`) müraciət edə bilər.

**S2: Non-static Inner class-ın obyektini necə yaratmaq olar?**
> Xarici sinifin obyekti olmalıdır: `XariciSinif xarici = new XariciSinif(); XariciSinif.DaxiliSinif daxili = xarici.new DaxiliSinif();`

**S3: Lambda ilə Anonim class arasındakı `this` fərqi nədir?**
> **Anonim class**: `this` anonim sinifin özünə istinad edir. Xarici sinifə `XariciSinif.this` ilə müraciət edilir. **Lambda**: öz `this`-i yoxdur — `this` həmişə əhatə edən sinifə istinad edir.

**S4: Local class niyə effectively final dəyişənlərə müraciət edə bilir?**
> Local class, metod bitdikdən sonra da yaşaya bilir (closure). Lakin metod dəyişənlərinin yaddaşı silinir. Java bunu dəyişənin dəyərini local class-a köçürür. Əgər dəyər dəyişsəydi, kopya ilə əsl dəyər arasında uyğunsuzluq olardı. Ona görə effectively final tələb edilir.

**S5: Anonim sinifin nə vaxt lambda ilə əvəz edilə bilməyəcəyi hallar var?**
> 1. Birdən çox metod lazımdırsa (non-functional), 2. State (instance field) saxlanmalıdırsa, 3. Öz `this` istinadı lazımdırsa, 4. Abstract class-dan yaradılırsa.

**S6: Inner class-ın outer class-ın `private` üzvlərinə müraciəti necə mümkündür?**
> Kompilyator inner class üçün synthetic accessor metodları yaradır. Bu, Java spesifikasiyasının bir hissəsidir — inner class faktiki olaraq outer class-ın bir üzvü sayılır, access control həmin kontekstdə tətbiq olunur.

**S7: Static Nested class Builder pattern-də niyə istifadə edilir?**
> Builder-in `new XariciSinif.Builder()` kimi çağırılması üçün static olması lazımdır. Əgər non-static olsaydı, `new XariciSinif().new Builder()` kimi çağırılmalı olardı ki, bu da mənasız olardı — hənüz obyekt yoxken builder yaratmaq istəyirik.
