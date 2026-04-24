# 019 — Java-da İnkapsulyasiya (Encapsulation)
**Səviyyə:** Başlanğıc


## Mündəricat
1. [İnkapsulyasiya nədir?](#i̇nkapsulyasiya-nədir)
2. [Access modifiers](#access-modifiers)
3. [Getters və Setters](#getters-və-setters)
4. [İnkapsulyasiyanın faydaları](#i̇nkapsulyasiyanın-faydaları)
5. [Information hiding](#information-hiding)
6. [Immutable classes](#immutable-classes)
7. [Builder pattern ilə inkapsulyasiya](#builder-pattern-ilə-i̇nkapsulyasiya)
8. [Praktiki nümunələr](#praktiki-nümunələr)
9. [İntervyu Sualları](#i̇ntervyu-sualları)

---

## İnkapsulyasiya nədir?

**İnkapsulyasiya** — məlumatı (fields) və həmin məlumat üzərindəki əməliyyatları (methods) bir paketdə birləşdirmək və kənar dünyadan gizlətməkdir.

Real dünya analoqu: Avtomobilin mühərriki — sürücü yalnız sükan, qaz, əyləc istifadə edir. Mühərrikin daxili işini görmür, bilavasitə toxuna bilmir. Bu, həm gizliliydir, həm güvənlikdir.

```java
// YANLIŞ: İnkapsulyasiya yoxdur — hər şey açıqdır
public class YanlışBankHesabı {
    public double balans;    // Hər kəs bilavasitə dəyişə bilər!
    public String pin;       // PIN açıq görünür!
    public boolean aktiv;
}

// Xarici kod istənilən dəyər yaza bilər:
YanlışBankHesabı h = new YanlışBankHesabı();
h.balans = -1000000;  // Mənfi balans!
h.pin = "";           // Boş PIN!
h.aktiv = true;

// DOĞRU: İnkapsulyasiya ilə
public class BankHesabı {
    private double balans;       // Xarici koddan gizlidir
    private String pinHash;      // PIN hashed saxlanır
    private boolean aktiv;
    private final String hesabNo;

    public BankHesabı(String hesabNo, String pin) {
        this.hesabNo = hesabNo;
        this.pinHash = pin_hash_et(pin);
        this.balans = 0;
        this.aktiv = true;
    }

    public boolean pul_yatır(double məbləğ) {
        if (!aktiv) return false;
        if (məbləğ <= 0) throw new IllegalArgumentException("Məbləğ müsbət olmalıdır");
        this.balans += məbləğ;
        return true;
    }

    public double getBalans() { return balans; }

    private String pin_hash_et(String pin) {
        return String.valueOf(pin.hashCode()); // sadəlik üçün
    }
}
```

---

## Access modifiers

Java-da dörd access modifier var:

| Modifier | Eyni sinif | Eyni paket | Alt sinif | Hər yerdən |
|---|:---:|:---:|:---:|:---:|
| `private` | ✓ | ✗ | ✗ | ✗ |
| `package-private` (default) | ✓ | ✓ | ✗ | ✗ |
| `protected` | ✓ | ✓ | ✓ | ✗ |
| `public` | ✓ | ✓ | ✓ | ✓ |

```java
package com.misal.bank;

public class Hesab {
    private double balans;           // yalnız Hesab sinifi daxilindən
    double limitSiz;                 // package-private: yalnız com.misal.bank paketindən
    protected String sahibAdı;      // alt siniflərdən və eyni paketdən
    public String hesabNomresi;     // hər yerdən

    private void daxiliEmal() { }   // yalnız bu sinif daxilindən
    void paketMetodu() { }          // paket daxilindən
    protected void mirasMəqsədi() { } // alt siniflərdən
    public void ümumİstifadə() { }  // hər yerdən
}
```

### Qayda: ən məhdud olan seç

```java
public class Məhsul {
    // Sahələr — həmişə private
    private String ad;
    private double qiymət;
    private int stokSayı;

    // Kənar kodun birbaşa daxil olmamalı olduğu köməkçi metodlar — private
    private double endirimliBahasını_hesabla() {
        return qiymət * 0.9;
    }

    // Yalnız paket daxilindəki sınaqlar üçün — package-private
    double getEndirimliBaha() {
        return endirimliBahasını_hesabla();
    }

    // Miras üçün nəzərdə tutulmuş — protected
    protected boolean stokdaVarmı() {
        return stokSayı > 0;
    }

    // API — public
    public String getAd() { return ad; }
    public double getQiymət() { return qiymət; }
}
```

---

## Getters və Setters

Getter/setter-lər sahəyə nəzarətli giriş təmin edir.

```java
public class Tələbə {
    private String ad;
    private String soyad;
    private int yaş;
    private double qiymətOrtalama; // GPA
    private final String tələbəId;

    public Tələbə(String tələbəId, String ad, String soyad, int yaş) {
        this.tələbəId = tələbəId; // final — dəyişdirilə bilməz, setter yoxdur
        setAd(ad);
        setSoyad(soyad);
        setYaş(yaş);
        this.qiymətOrtalama = 0.0;
    }

    // Getter — sadə oxuma
    public String getAd() { return ad; }
    public String getSoyad() { return soyad; }
    public String getTələbəId() { return tələbəId; }

    // Getter — hesablanmış dəyər (field olmaya bilər)
    public String getTamAd() {
        return ad + " " + soyad;
    }

    // Setter — doğrulama ilə
    public void setAd(String ad) {
        if (ad == null || ad.isBlank()) {
            throw new IllegalArgumentException("Ad boş ola bilməz");
        }
        this.ad = ad.trim();
    }

    public void setSoyad(String soyad) {
        if (soyad == null || soyad.isBlank()) {
            throw new IllegalArgumentException("Soyad boş ola bilməz");
        }
        this.soyad = soyad.trim();
    }

    public void setYaş(int yaş) {
        if (yaş < 16 || yaş > 100) {
            throw new IllegalArgumentException("Yaş 16-100 aralığında olmalıdır: " + yaş);
        }
        this.yaş = yaş;
    }

    public void setQiymətOrtalama(double ortalama) {
        if (ortalama < 0.0 || ortalama > 4.0) {
            throw new IllegalArgumentException("GPA 0-4 arasında olmalıdır");
        }
        this.qiymətOrtalama = ortalama;
    }

    // Read-only getter — sadə oxuma
    public int getYaş() { return yaş; }
    public double getQiymətOrtalama() { return qiymətOrtalama; }
}
```

### Defensive copy — mutable obyektlər üçün

```java
public class Kurs {
    private final String ad;
    private final List<String> tələbələr;
    private final Date başlangıcTarixi;

    public Kurs(String ad, List<String> tələbələr, Date başlangıcTarixi) {
        this.ad = ad;
        // YANLIŞ: birbaşa referans saxlamaq
        // this.tələbələr = tələbələr; // Xarici dəyişsə, bu da dəyişər!

        // DOĞRU: defensive copy
        this.tələbələr = new ArrayList<>(tələbələr);
        this.başlangıcTarixi = new Date(başlangıcTarixi.getTime()); // Date mutable-dır
    }

    public List<String> getTələbələr() {
        // YANLIŞ: bilavasitə internal list-i ver
        // return tələbələr; // Kənar kod dəyişdirə bilər!

        // DOĞRU: unmodifiable görünüş ver
        return Collections.unmodifiableList(tələbələr);
    }

    public Date getBaşlangıcTarixi() {
        return new Date(başlangıcTarixi.getTime()); // defensive copy
    }

    public String getAd() { return ad; } // String immutable-dır, OK
}
```

---

## İnkapsulyasiyanın faydaları

```java
// 1. Məlumatın bütövlüyü (Data integrity)
public class Temperatur {
    private double celsius;

    public void setCelsius(double celsius) {
        if (celsius < -273.15) { // Mütləq sıfırdan aşağı olmaz!
            throw new IllegalArgumentException(
                "Temperatur -273.15°C-dən aşağı ola bilməz: " + celsius);
        }
        this.celsius = celsius;
    }

    // Müxtəlif vahildə getter — internal saxlama gizlidir
    public double getCelsius() { return celsius; }
    public double getFahrenheit() { return celsius * 9/5 + 32; }
    public double getKelvin() { return celsius + 273.15; }
}

// 2. İmplementasiya dəyişikliyi — API pozulmaz
public class İstifadəçiAnbarı {
    // Əvvəl: List istifadə edilirdi
    // private List<İstifadəçi> istifadəçilər = new ArrayList<>();

    // İndi: Map istifadə edilir — daha sürətli axtarış
    private Map<Integer, İstifadəçi> istifadəçilər = new HashMap<>();
    private int növbətiId = 1;

    // Public API dəyişmir! Xarici kod heç nə bilmir.
    public void əlavə_et(İstifadəçi u) {
        istifadəçilər.put(növbətiId++, u);
    }

    public Optional<İstifadəçi> tap(int id) {
        return Optional.ofNullable(istifadəçilər.get(id));
    }

    public List<İstifadəçi> hamısı() {
        return List.copyOf(istifadəçilər.values());
    }
}

// 3. Thread safety — synchronized ilə
public class TəhlükəsizSayac {
    private volatile int dəyər = 0; // volatile — görünürlük
    private final Object kilit = new Object();

    public synchronized void artır() { // yalnız bir thread
        dəyər++;
    }

    public synchronized int getDəyər() {
        return dəyər;
    }
}
```

---

## Information hiding

**Information hiding** — implementasiya detallarını gizlətmək. Kənar kod yalnız "nə edir"i bilir, "necə edir"i bilmir.

```java
public class Şifrəleyici {
    // Algorithm — gizli implementasiya detalı
    private static final String ALGORITHM = "AES";
    private static final int KEY_SIZE = 256;
    private final byte[] açar;

    public Şifrəleyici(String açarMətn) {
        // AES açar yaratma — kənar kod bilmir necə edilir
        this.açar = açarYarat(açarMətn);
    }

    // Public API — sadə interfeys
    public String şifrələ(String mətn) {
        return daxiliŞifrələ(mətn, açar);
    }

    public String açıqla(String şifrəliMətn) {
        return daxiliAçıqla(şifrəliMətn, açar);
    }

    // Private — implementasiya detalları gizlidir
    private byte[] açarYarat(String mənbə) {
        // Kompleks kriptoqrafik əməliyyat
        return mənbə.getBytes();
    }

    private String daxiliŞifrələ(String mətn, byte[] açar) {
        // Şifrələmə alqoritmi
        return "ENCRYPTED:" + mətn; // sadəlik üçün
    }

    private String daxiliAçıqla(String şifrəliMətn, byte[] açar) {
        return şifrəliMətn.replace("ENCRYPTED:", ""); // sadəlik üçün
    }
}

// İstifadəçi yalnız şifrələ/açıqla metodlarını görür:
Şifrəleyici ş = new Şifrəleyici("məxfiAçar123");
String şifrəli = ş.şifrələ("Gizli məlumat");
String açıq = ş.açıqla(şifrəli);
```

---

## Immutable classes

**Immutable sinif** — yaradıldıqdan sonra vəziyyəti dəyişdirilə bilməyən sinif. Ən güclü inkapsulyasiya formasıdır.

```java
// Immutable sinif yaratmaq üçün qaydalar:
// 1. Sinfi final et (extend olunmasın)
// 2. Bütün sahələri private final et
// 3. Constructor-da defensive copy al
// 4. Getter-lərdə defensive copy qaytar
// 5. Setter yazma

public final class ParaVahidi {
    private final long məbləğ; // sentlərdə saxla (floating point problem yoxdur)
    private final String valyuta;

    public ParaVahidi(long məbləğ, String valyuta) {
        if (məbləğ < 0) throw new IllegalArgumentException("Məbləğ mənfi ola bilməz");
        if (valyuta == null || valyuta.length() != 3) {
            throw new IllegalArgumentException("Valyuta kodu 3 hərf olmalıdır");
        }
        this.məbləğ = məbləğ;
        this.valyuta = valyuta.toUpperCase();
    }

    // Əməliyyatlar — yeni obyekt qaytarır, mövcudu dəyişmir
    public ParaVahidi əlavə_et(ParaVahidi digər) {
        if (!this.valyuta.equals(digər.valyuta)) {
            throw new IllegalArgumentException("Fərqli valyutaları toplamaq olmaz");
        }
        return new ParaVahidi(this.məbləğ + digər.məbləğ, this.valyuta); // YENİ obyekt
    }

    public ParaVahidi vur(double əmsal) {
        return new ParaVahidi((long)(this.məbləğ * əmsal), this.valyuta);
    }

    public ParaVahidi endirim_tətbiq_et(double faiz) {
        return vur(1.0 - faiz / 100);
    }

    // Getters — String immutable-dır, defensive copy lazım deyil
    public long getMəbləğ() { return məbləğ; }
    public String getValyuta() { return valyuta; }

    // Məbləği manat şəklində göstər
    public String formatla() {
        return String.format("%s %.2f", valyuta, məbləğ / 100.0);
    }

    @Override
    public boolean equals(Object o) {
        if (!(o instanceof ParaVahidi p)) return false;
        return məbləğ == p.məbləğ && valyuta.equals(p.valyuta);
    }

    @Override
    public int hashCode() {
        return Objects.hash(məbləğ, valyuta);
    }

    @Override
    public String toString() {
        return formatla();
    }
}

// İstifadə:
ParaVahidi qiymət = new ParaVahidi(5000, "AZN"); // 50.00 AZN
ParaVahidi vergililə = qiymət.vur(1.18); // ƏDV ilə
ParaVahidi endirimli = qiymət.endirim_tətbiq_et(10); // 10% endirim

// qiymət dəyişmədi! Yalnız yeni obyektlər yarandı:
System.out.println(qiymət);    // AZN 50.00
System.out.println(vergililə); // AZN 59.00
System.out.println(endirimli); // AZN 45.00
```

---

## Builder pattern ilə inkapsulyasiya

Çox sahəli siniflər üçün builder pattern inkapsulyasiyanı saxlayaraq rahat obyekt yaratma imkanı verir.

```java
public final class İstifadəçi {
    // Bütün sahələr private final — immutable!
    private final int id;
    private final String istifadəçiAdı;
    private final String email;
    private final String adı;
    private final String soyadı;
    private final int yaş;
    private final String telefon;
    private final List<String> rollar;
    private final boolean aktiv;
    private final java.time.LocalDateTime qeydiyyatTarixi;

    // Private constructor — yalnız Builder istifadə edə bilər
    private İstifadəçi(Builder builder) {
        this.id = builder.id;
        this.istifadəçiAdı = builder.istifadəçiAdı;
        this.email = builder.email;
        this.adı = builder.adı;
        this.soyadı = builder.soyadı;
        this.yaş = builder.yaş;
        this.telefon = builder.telefon;
        this.rollar = List.copyOf(builder.rollar); // defensive copy
        this.aktiv = builder.aktiv;
        this.qeydiyyatTarixi = java.time.LocalDateTime.now();
    }

    // Yalnız getter-lər
    public int getId() { return id; }
    public String getİstifadəçiAdı() { return istifadəçiAdı; }
    public String getEmail() { return email; }
    public String getAdı() { return adı; }
    public String getSoyadı() { return soyadı; }
    public int getYaş() { return yaş; }
    public String getTelefon() { return telefon; }
    public List<String> getRollar() { return rollar; } // unmodifiable
    public boolean isAktiv() { return aktiv; }
    public java.time.LocalDateTime getQeydiyyatTarixi() { return qeydiyyatTarixi; }

    public String getTamAdı() { return adı + " " + soyadı; }

    @Override
    public String toString() {
        return "İstifadəçi{id=%d, istifadəçiAdı='%s', email='%s', aktiv=%b}"
               .formatted(id, istifadəçiAdı, email, aktiv);
    }

    // Builder sinfi
    public static class Builder {
        // Məcburi sahələr
        private final int id;
        private final String istifadəçiAdı;
        private final String email;

        // Opsional sahələr — default dəyərlərlə
        private String adı = "";
        private String soyadı = "";
        private int yaş = 0;
        private String telefon = null;
        private List<String> rollar = new ArrayList<>();
        private boolean aktiv = true;

        // Constructor — yalnız məcburi sahələr
        public Builder(int id, String istifadəçiAdı, String email) {
            if (id <= 0) throw new IllegalArgumentException("ID müsbət olmalıdır");
            if (istifadəçiAdı == null || istifadəçiAdı.isBlank()) {
                throw new IllegalArgumentException("İstifadəçi adı boş ola bilməz");
            }
            if (email == null || !email.contains("@")) {
                throw new IllegalArgumentException("Email düzgün formatda deyil");
            }
            this.id = id;
            this.istifadəçiAdı = istifadəçiAdı;
            this.email = email;
        }

        public Builder adı(String adı) {
            this.adı = adı;
            return this;
        }

        public Builder soyadı(String soyadı) {
            this.soyadı = soyadı;
            return this;
        }

        public Builder yaş(int yaş) {
            if (yaş < 0 || yaş > 150) throw new IllegalArgumentException("Yaş düzgün deyil");
            this.yaş = yaş;
            return this;
        }

        public Builder telefon(String telefon) {
            this.telefon = telefon;
            return this;
        }

        public Builder rol(String rol) {
            this.rollar.add(rol);
            return this;
        }

        public Builder deaktiv() {
            this.aktiv = false;
            return this;
        }

        public İstifadəçi qur() {
            return new İstifadəçi(this);
        }
    }
}

// İstifadə — rahat, aydın, inkapsulyasiyalı:
İstifadəçi user = new İstifadəçi.Builder(1, "anar_h", "anar@example.com")
    .adı("Anar")
    .soyadı("Hüseynov")
    .yaş(30)
    .telefon("+994501234567")
    .rol("ADMIN")
    .rol("USER")
    .qur();

System.out.println(user);
System.out.println(user.getTamAdı()); // "Anar Hüseynov"
```

---

## Praktiki nümunələr

### ATM Sistemi

```java
public final class ATM {
    private final String atmId;
    private int nağdPulMiqdarı; // 100 AZN-lik əskinas sayı
    private boolean aktiv;
    private final List<String> əməliyyatJurnalı;

    public ATM(String atmId, int ilkinNağd) {
        this.atmId = atmId;
        this.nağdPulMiqdarı = ilkinNağd;
        this.aktiv = true;
        this.əməliyyatJurnalı = new ArrayList<>();
    }

    // Public API — nəzarətli giriş
    public synchronized boolean pul_ver(String kartNo, int məbləğ) {
        if (!aktiv) {
            jurnal_yaz("ATM aktiv deyil");
            return false;
        }
        if (məbləğ <= 0 || məbləğ % 100 != 0) {
            jurnal_yaz("Yanlış məbləğ: " + məbləğ);
            return false;
        }
        int lazımEskinas = məbləğ / 100;
        if (nağdPulMiqdarı < lazımEskinas) {
            jurnal_yaz("Nağd çatışmır. Tələb: " + lazımEskinas + ", Mövcud: " + nağdPulMiqdarı);
            return false;
        }
        nağdPulMiqdarı -= lazımEskinas;
        jurnal_yaz("Kart: " + maskala(kartNo) + ", Verildi: " + məbləğ + " AZN");
        return true;
    }

    public synchronized void nağdDoldur(int əskinas) {
        if (!aktiv) return;
        nağdPulMiqdarı += əskinas;
        jurnal_yaz("Nağd dolduruldu: " + əskinas + " ədəd əskinas");
    }

    // Read-only getter
    public int getNağdPulSəviyyəsi() { return nağdPulMiqdarı * 100; }
    public String getAtmId() { return atmId; }
    public boolean isAktiv() { return aktiv; }

    // Jurnal — kopya ilə qoru
    public List<String> getJurnal() {
        return List.copyOf(əməliyyatJurnalı);
    }

    // Private köməkçi metodlar
    private void jurnal_yaz(String qeyd) {
        əməliyyatJurnalı.add(java.time.LocalDateTime.now() + " [" + atmId + "]: " + qeyd);
    }

    private String maskala(String kartNo) {
        if (kartNo.length() < 4) return "****";
        return "****-****-****-" + kartNo.substring(kartNo.length() - 4);
    }
}
```

---

## İntervyu Sualları

**S1: İnkapsulyasiya ilə information hiding eyni şeydirmi?**
> Yaxın anlayışlardır, amma eyni deyil. **İnkapsulyasiya** məlumatı və metodları bir yerdə birləşdirməkdir. **Information hiding** isə implementasiya detallarını gizlətməkdir. İnkapsulyasiya information hiding-i asanlaşdırır.

**S2: Getter/setter yazmaq həmişə doğrudur?**
> Xeyr. Hər sahə üçün avtomatik getter/setter yazmaq yanlışdır — bu inkapsulyasiyanı zəiflədir. Yalnız kənar kodun görməsi lazım olan sahələr üçün getter, dəyişdirə biləcəyi sahələr üçün setter yazın. Setter-lərdə mütləq doğrulama olsun.

**S3: Immutable sinifin üstünlükləri nədir?**
> 1. Thread-safe-dir (synchronization lazım deyil), 2. Hashing üçün güvənlidir (HashSet/HashMap-də istifadə), 3. Defensive copy lazım deyil, 4. Yan effekt yoxdur, 5. Daha asan debug.

**S4: `final` field ilə immutability eyni şeydirmi?**
> Xeyr. `final` yalnız sahənin **referansının** dəyişməyəcəyini zəmanət edir. Əgər sahə mutable bir obyektə (List, Date) işarə edirsə, o obyektin içi dəyişə bilər. Həqiqi immutability üçün defensive copy + unmodifiable wrapper lazımdır.

**S5: Builder pattern nə zaman istifadə edilir?**
> 4-dən çox parametrli constructor olduqda, bir neçəsi opsional olduqda, parametrlər arasında doğrulama lazım olduqda. Uzun parameterli constructor-ların yerinə istifadə edilir.

**S6: `private` sahəni reflection ilə dəyişmək mümkündürmü?**
> Bəli, texniki cəhətdən mümkündür: `field.setAccessible(true)`. Lakin bu inkapsulyasiya müqaviləsinin pozulmasıdır. Java 9-dan modüllər sistemi bunu daha da məhdudlaşdırdı. Normal proqramlaşdırmada bunu etmək tövsiyə edilmir.

**S7: Record inkapsulyasiya üçün uyğundurmu?**
> Record-lar avtomatik olaraq `private final` sahələr və getter-lər yaradır — inkapsulyasiya üçün idealdır. Amma setter yoxdur (tam immutable-dır). DTO, value object kimi istifadə üçün çox münasibdir.
