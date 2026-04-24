# Tarix və Vaxt (Date and Time)

> **Seviyye:** Intermediate ⭐⭐

## Giriş

Tarix və vaxt emalı proqramlaşdırmanın ən çətin mövzularından biridir - zaman zonaları, yay/qış vaxtı keçidləri, təqvim fərqləri və formatlaşdırma kimi məsələlər hər dildə fərqli həll olunur. Java köhnə `Date`/`Calendar` API-dən müasir `java.time` paketinə keçid edib. PHP isə prosedural `date()` funksiyasından `DateTime` siniflərinə doğru inkişaf edib, üstəlik `Carbon` kimi populyar kitabxanalar mövcuddur.

## Java-da istifadəsi

### Köhnə API - Date və Calendar (Java 8-dən əvvəl)

Köhnə API bir çox problemlərə malik idi, amma onu tanımaq lazımdır çünki hələ də köhnə kodlarda rast gəlinir:

```java
import java.util.Date;
import java.util.Calendar;
import java.text.SimpleDateFormat;

// Date - mutable, thread-unsafe, pis dizayn
Date indi = new Date();
System.out.println(indi); // Sat Apr 11 15:30:00 AZT 2026

// Ay 0-dan başlayır (Yanvar = 0) - çox qarışıq!
Date tarix = new Date(2026 - 1900, 3, 11); // 1900 çıxılmalıdır!

// Calendar
Calendar cal = Calendar.getInstance();
cal.set(2026, Calendar.APRIL, 11); // Ay sabiti istifadə etmək daha aydındır
int il = cal.get(Calendar.YEAR);
int ay = cal.get(Calendar.MONTH); // 0-dan başlayır!
int gün = cal.get(Calendar.DAY_OF_MONTH);

// SimpleDateFormat - thread-unsafe
SimpleDateFormat sdf = new SimpleDateFormat("dd.MM.yyyy HH:mm:ss");
String formatli = sdf.format(indi);
Date parsed = sdf.parse("11.04.2026 15:30:00");
```

### Müasir API - java.time (Java 8+)

Java 8 ilə təqdim olunan `java.time` paketi Joda-Time kitabxanasına əsaslanır və tarix/vaxt emalını kökündən dəyişdirdi:

```java
import java.time.*;
import java.time.format.DateTimeFormatter;
import java.time.temporal.ChronoUnit;

// LocalDate - yalnız tarix (vaxt yoxdur)
LocalDate bu_gün = LocalDate.now();
LocalDate doğum_günü = LocalDate.of(2000, Month.MARCH, 15);
LocalDate doğum_günü2 = LocalDate.of(2000, 3, 15); // ay rəqəmlə

System.out.println(bu_gün);       // 2026-04-11
System.out.println(doğum_günü);   // 2000-03-15

// LocalDate əməliyyatları
LocalDate sabah = bu_gün.plusDays(1);
LocalDate keçən_ay = bu_gün.minusMonths(1);
LocalDate gələn_il = bu_gün.plusYears(1);

// Müqayisə
boolean əvvəldir = doğum_günü.isBefore(bu_gün);    // true
boolean sonradır = doğum_günü.isAfter(bu_gün);      // false
boolean uzun_il = bu_gün.isLeapYear();               // false
int günSayı = bu_gün.getDayOfYear();                  // 101
DayOfWeek haftaGünü = bu_gün.getDayOfWeek();          // SATURDAY
```

### LocalTime və LocalDateTime

```java
// LocalTime - yalnız vaxt (tarix yoxdur)
LocalTime indi = LocalTime.now();
LocalTime görüş = LocalTime.of(14, 30);        // 14:30
LocalTime dəqiq = LocalTime.of(14, 30, 45);    // 14:30:45

System.out.println(indi);    // 15:30:22.123456
System.out.println(görüş);  // 14:30

// LocalDateTime - tarix + vaxt (timezone yoxdur)
LocalDateTime indiFull = LocalDateTime.now();
LocalDateTime xüsusi = LocalDateTime.of(2026, 4, 11, 14, 30, 0);
LocalDateTime birləşmiş = LocalDateTime.of(bu_gün, görüş);

System.out.println(indiFull); // 2026-04-11T15:30:22.123456

// Dəyişdirmə (immutable - yeni obyekt qaytarır)
LocalDateTime ikiSaatSonra = indiFull.plusHours(2);
LocalDateTime dəyişdirilmiş = indiFull
    .withYear(2027)
    .withMonth(6)
    .withDayOfMonth(15)
    .withHour(10);
```

### ZonedDateTime və timezone idarəsi

```java
// ZonedDateTime - tarix + vaxt + timezone
ZonedDateTime bakuVaxtı = ZonedDateTime.now(ZoneId.of("Asia/Baku"));
ZonedDateTime londonVaxtı = ZonedDateTime.now(ZoneId.of("Europe/London"));
ZonedDateTime nyVaxtı = ZonedDateTime.now(ZoneId.of("America/New_York"));

System.out.println(bakuVaxtı);   // 2026-04-11T15:30:00+04:00[Asia/Baku]
System.out.println(londonVaxtı); // 2026-04-11T12:30:00+01:00[Europe/London]

// Timezone çevrilməsi
ZonedDateTime bakuda = bakuVaxtı.withZoneSameInstant(ZoneId.of("Asia/Tokyo"));

// Bütün mövcud timezone-lar
Set<String> zonalar = ZoneId.getAvailableZoneIds();

// OffsetDateTime - sabit offset ilə
OffsetDateTime offset = OffsetDateTime.now(ZoneOffset.of("+04:00"));

// Instant - UTC timestamp (epoch-dan millisaniyə)
Instant anLıq = Instant.now();
System.out.println(anLıq); // 2026-04-11T11:30:00.123456Z
long epoch = anLıq.toEpochMilli();

// Instant-ı ZonedDateTime-a çevirmək
ZonedDateTime zoned = anLıq.atZone(ZoneId.of("Asia/Baku"));
```

### Duration və Period

```java
// Duration - vaxt əsaslı müddət (saat, dəqiqə, saniyə)
Duration ikiSaat = Duration.ofHours(2);
Duration beşDəqiqə = Duration.ofMinutes(5);
Duration arası = Duration.between(LocalTime.of(9, 0), LocalTime.of(17, 0));

System.out.println(arası);           // PT8H (8 saat)
System.out.println(arası.toHours()); // 8
System.out.println(arası.toMinutes()); // 480

// Period - tarix əsaslı müddət (il, ay, gün)
Period altıAy = Period.ofMonths(6);
Period birIlÜçAy = Period.of(1, 3, 0);
Period yaş = Period.between(
    LocalDate.of(2000, 3, 15),
    LocalDate.now()
);
System.out.println(yaş.getYears() + " il, " + yaş.getMonths() + " ay");

// ChronoUnit ilə fərq hesablama
long günFərqi = ChronoUnit.DAYS.between(
    LocalDate.of(2026, 1, 1),
    LocalDate.of(2026, 12, 31)
);
System.out.println(günFərqi + " gün"); // 364 gün
```

### DateTimeFormatter

```java
// Hazır formatterlar
LocalDateTime dt = LocalDateTime.now();
System.out.println(dt.format(DateTimeFormatter.ISO_LOCAL_DATE));      // 2026-04-11
System.out.println(dt.format(DateTimeFormatter.ISO_LOCAL_DATE_TIME)); // 2026-04-11T15:30:00

// Xüsusi format
DateTimeFormatter azFormat = DateTimeFormatter.ofPattern("dd.MM.yyyy HH:mm:ss");
System.out.println(dt.format(azFormat)); // 11.04.2026 15:30:00

DateTimeFormatter uzunFormat = DateTimeFormatter.ofPattern(
    "d MMMM yyyy, EEEE", new Locale("az")
);
System.out.println(dt.format(uzunFormat)); // 11 aprel 2026, şənbə

// Parse etmə
LocalDate parsed = LocalDate.parse("11.04.2026", DateTimeFormatter.ofPattern("dd.MM.yyyy"));
LocalDateTime parsedDT = LocalDateTime.parse(
    "11.04.2026 15:30:00",
    azFormat
);

// Thread-safe - SimpleDateFormat-dan fərqli olaraq
// DateTimeFormatter instance-ı paylaşıla bilər
public static final DateTimeFormatter FORMATTER =
    DateTimeFormatter.ofPattern("dd.MM.yyyy");
```

## PHP-də istifadəsi

### Prosedural date() funksiyası

```php
// date() - formatlanmış tarix string-i qaytarır
echo date('Y-m-d');           // 2026-04-11
echo date('d.m.Y H:i:s');    // 11.04.2026 15:30:00
echo date('l, F j, Y');      // Saturday, April 11, 2026
echo date('D');               // Sat
echo date('N');               // 6 (həftənin günü, 1=Bazar ertəsi)

// time() - Unix timestamp
$indi = time();
echo $indi; // 1776105000

// mktime() - xüsusi tarix üçün timestamp
$timestamp = mktime(14, 30, 0, 4, 11, 2026); // saat, dəqiqə, saniyə, ay, gün, il

// strtotime() - string-dən timestamp
$sabah = strtotime('+1 day');
$gələnHəftə = strtotime('+1 week');
$keçənAy = strtotime('-1 month');
$xüsusi = strtotime('2026-12-31');
$bayram = strtotime('next Monday');
$spesifik = strtotime('first day of January 2027');

echo date('d.m.Y', $sabah);     // 12.04.2026
echo date('d.m.Y', $gələnHəftə); // 18.04.2026

// checkdate() - tarixin düzgünlüyünü yoxlayır
var_dump(checkdate(2, 29, 2024)); // true (uzun il)
var_dump(checkdate(2, 29, 2025)); // false
```

### DateTime sinfi

```php
// DateTime yaratma
$indi = new DateTime();
$xüsusi = new DateTime('2026-04-11 14:30:00');
$string_dən = new DateTime('next Friday');

echo $indi->format('d.m.Y H:i:s'); // 11.04.2026 15:30:00

// Dəyişdirmə - DateTime mutable-dır!
$tarix = new DateTime('2026-04-11');
$tarix->modify('+1 day');
echo $tarix->format('d.m.Y'); // 12.04.2026

// Amma bu problemdir:
function tarixiİşlə(DateTime $tarix): string {
    $tarix->modify('+1 month'); // Orijinalı dəyişdirir!
    return $tarix->format('Y-m-d');
}

$orijinal = new DateTime('2026-04-11');
echo tarixiİşlə($orijinal); // 2026-05-11
echo $orijinal->format('Y-m-d'); // 2026-05-11 - Orijinal da dəyişdi!

// Setter metodları
$tarix = new DateTime();
$tarix->setDate(2026, 12, 31);
$tarix->setTime(23, 59, 59);

// createFromFormat - xüsusi formatdan parse
$tarix = DateTime::createFromFormat('d/m/Y', '11/04/2026');
echo $tarix->format('Y-m-d'); // 2026-04-11

// Timestamp-dən
$tarix = new DateTime('@1776105000');
$tarix->setTimestamp(time());
echo $tarix->getTimestamp();
```

### DateTimeImmutable

```php
// DateTimeImmutable - hər əməliyyat yeni obyekt qaytarır (Java-nın LocalDate-inə bənzər)
$tarix = new DateTimeImmutable('2026-04-11');
$yeniTarix = $tarix->modify('+1 day');

echo $tarix->format('d.m.Y');      // 11.04.2026 - dəyişməyib!
echo $yeniTarix->format('d.m.Y');  // 12.04.2026 - yeni obyekt

// Müasir PHP-də DateTimeImmutable tövsiyə olunur
$indi = new DateTimeImmutable();
$sabah = $indi->modify('+1 day');
$gələnAy = $indi->modify('+1 month');
$gələnİl = $indi->modify('+1 year');

// createFromFormat
$tarix = DateTimeImmutable::createFromFormat('d.m.Y H:i', '11.04.2026 14:30');

// DateTime-dan DateTimeImmutable-a çevirmə
$mutable = new DateTime('2026-04-11');
$immutable = DateTimeImmutable::createFromMutable($mutable);
```

### DateInterval

```php
// DateInterval - müddət təmsil edir
$interval = new DateInterval('P1Y2M3DT4H5M6S');
// P = Period, 1Y = 1 il, 2M = 2 ay, 3D = 3 gün
// T = Time, 4H = 4 saat, 5M = 5 dəqiqə, 6S = 6 saniyə

echo $interval->y; // 1
echo $interval->m; // 2
echo $interval->d; // 3
echo $interval->h; // 4
echo $interval->i; // 5
echo $interval->s; // 6

// İki tarix arasındakı fərq
$tarix1 = new DateTimeImmutable('2026-01-01');
$tarix2 = new DateTimeImmutable('2026-04-11');
$fərq = $tarix1->diff($tarix2);

echo $fərq->days;    // 100 (ümumi gün sayı)
echo $fərq->m;       // 3 (ay)
echo $fərq->d;       // 10 (gün)
echo $fərq->format('%m ay və %d gün'); // 3 ay və 10 gün

// Interval ilə əməliyyat
$tarix = new DateTimeImmutable('2026-04-11');
$üçAySonra = $tarix->add(new DateInterval('P3M'));
$ikiHəftəƏvvəl = $tarix->sub(new DateInterval('P2W'));

// DatePeriod - tarix aralığı üzərində iterasiya
$başlanğıc = new DateTimeImmutable('2026-04-01');
$interval = new DateInterval('P1D'); // hər gün
$son = new DateTimeImmutable('2026-04-11');

$period = new DatePeriod($başlanğıc, $interval, $son);
foreach ($period as $tarix) {
    echo $tarix->format('d.m.Y') . "\n";
}
// 01.04.2026, 02.04.2026, ..., 10.04.2026
```

### Timezone idarəsi

```php
// Default timezone
date_default_timezone_set('Asia/Baku');
echo date_default_timezone_get(); // Asia/Baku

// DateTimeZone sinfi
$bakuZone = new DateTimeZone('Asia/Baku');
$londonZone = new DateTimeZone('Europe/London');

$bakuVaxtı = new DateTimeImmutable('now', $bakuZone);
$londonVaxtı = $bakuVaxtı->setTimezone($londonZone);

echo $bakuVaxtı->format('H:i (P)');   // 15:30 (+04:00)
echo $londonVaxtı->format('H:i (P)'); // 12:30 (+01:00)

// Bütün timezone-lar
$zonalar = DateTimeZone::listIdentifiers();
$avropaZonaları = DateTimeZone::listIdentifiers(DateTimeZone::EUROPE);

// Timezone offset
$zone = new DateTimeZone('Asia/Baku');
$offset = $zone->getOffset(new DateTime()); // saniyə ilə
echo $offset / 3600; // 4 (saat)

// Timezone keçidləri (DST)
$keçidlər = $zone->getTransitions(
    strtotime('2026-01-01'),
    strtotime('2026-12-31')
);
```

### Carbon kitabxanası

Carbon PHP-nin ən populyar tarix/vaxt kitabxanasıdır, `DateTimeImmutable` genişləndirir:

```php
use Carbon\Carbon;
use Carbon\CarbonImmutable;

// Yaratma
$indi = Carbon::now();
$bu_gün = Carbon::today();
$sabah = Carbon::tomorrow();
$dünən = Carbon::yesterday();
$xüsusi = Carbon::create(2026, 4, 11, 14, 30, 0);
$parsed = Carbon::parse('2026-04-11');

// İnsan oxuya bilən fərq
echo $indi->diffForHumans(); // "just now"
echo Carbon::parse('2026-04-01')->diffForHumans(); // "10 days ago"
echo Carbon::parse('2026-05-01')->diffForHumans(); // "19 days from now"

// Lokalizasiya
Carbon::setLocale('az');
echo Carbon::parse('2026-04-01')->diffForHumans(); // "10 gün əvvəl"

// Müqayisə
$tarix = Carbon::parse('2026-06-15');
echo $tarix->isWeekend();     // false/true
echo $tarix->isWeekday();     // true/false
echo $tarix->isFuture();      // true
echo $tarix->isPast();        // false
echo $tarix->isToday();       // false
echo $tarix->isBirthday();    // false

// Əməliyyatlar
echo $indi->addDays(5)->format('d.m.Y');
echo $indi->subMonths(2)->format('d.m.Y');
echo $indi->startOfMonth()->format('d.m.Y');
echo $indi->endOfMonth()->format('d.m.Y');
echo $indi->startOfWeek()->format('d.m.Y');

// Aralıq yoxlama
$bayramBaşlanğıc = Carbon::parse('2026-12-20');
$bayramSon = Carbon::parse('2027-01-05');
echo $indi->between($bayramBaşlanğıc, $bayramSon); // false

// CarbonImmutable - daha təhlükəsiz
$tarix = CarbonImmutable::now();
$yeni = $tarix->addDay(); // $tarix dəyişmir
```

## Əsas fərqlər

| Xüsusiyyət | Java (java.time) | PHP |
|---|---|---|
| Əsas sinif | `LocalDate`, `LocalDateTime`, `ZonedDateTime` | `DateTime`, `DateTimeImmutable` |
| Immutability | Bütün siniflər immutable | `DateTime` mutable, `DateTimeImmutable` immutable |
| Timezone | `ZoneId`, `ZonedDateTime` | `DateTimeZone`, `setTimezone()` |
| Müddət | `Duration` (vaxt), `Period` (tarix) | `DateInterval` (ikisi birlikdə) |
| Fərq hesabı | `ChronoUnit.between()`, `Period.between()` | `diff()` metodu |
| Format | `DateTimeFormatter` (thread-safe) | `format()` metodu, `date()` funksiyası |
| Parse | `parse()` metodu ilə formatter | `createFromFormat()`, `strtotime()` |
| Epox vaxtı | `Instant` | `time()`, `getTimestamp()` |
| Populyar kitabxana | Artıq daxili API kifayətdir | Carbon (çox populyar) |
| String parse | Ciddi format tələb edir | `strtotime()` çox çevik (+1 day, next Monday) |

## Niyə belə fərqlər var?

### Java-nın yanaşması

Java-nın köhnə `Date`/`Calendar` API-si pis dizayn nümunəsi kimi tanınırdı: mutable idi, aylar 0-dan başlayırdı, thread-safe deyildi. Java 8-də tamamilə yeni `java.time` paketi yaradıldı ki, bu problemlər həll olunsun:

1. **İmmutability**: Bütün `java.time` sinifləri immutable-dır. Bu, thread safety təmin edir və yan effektlərin (side effects) qarşısını alır.

2. **Ayrılmış konsepsiyalar**: `LocalDate` (yalnız tarix), `LocalTime` (yalnız vaxt), `LocalDateTime` (ikisi birlikdə), `ZonedDateTime` (timezone ilə), `Instant` (maşın vaxtı) - hər biri xüsusi məqsəd üçün ayrı sinifdir. Bu, "yalnız tarixi" və "tarix+vaxt+timezone" arasındakı fərqi tip sistemində ifadə edir.

3. **Duration vs Period**: Vaxt əsaslı müddət (5 saat, 30 dəqiqə) ilə tarix əsaslı müddət (2 il, 3 ay) konseptual olaraq fərqlidir. Java bunu ayrı siniflərlə ifadə edir.

### PHP-nin yanaşması

PHP daha praktik və çevik yanaşma seçib:

1. **Geriyə uyğunluq**: `date()`, `strtotime()`, `time()` kimi prosedural funksiyalar hələ də işləyir və çox sadədir. Yeni kod üçün `DateTimeImmutable` tövsiyə olunsa da, köhnə yanaşma da mövcuddur.

2. **Tək sinif**: PHP `DateTime` sinfi həm tarixi, həm vaxtı, həm də timezone-u bir yerdə saxlayır. Bu, daha sadə API təmin edir, amma Java-dakı kimi konsepsiyaları ayırmır.

3. **strtotime() çevikliyi**: `strtotime('+1 month')`, `strtotime('next Monday')`, `strtotime('first day of January')` kimi natural dil ifadələrini parse edə bilir. Bu, PHP-nin "sürətli inkişaf" fəlsəfəsinə uyğundur.

4. **Carbon ekosistemi**: PHP-nin daxili API-si kifayət etmədikdə, Carbon kitabxanası `diffForHumans()`, lokalizasiya, müqayisə metodları kimi əlavə funksionallıq təmin edir. Laravel və digər framework-lar Carbon-u daxili olaraq istifadə edir.

### Nəticə

Java tarix/vaxt emalında tip təhlükəsizliyini və aydınlığı ön plana çıxarır - hər konsepsiya üçün ayrı sinif var və hamısı immutable-dır. PHP isə rahatlıq və çevikliyi seçib - bir `DateTimeImmutable` sinfi ilə əksər ehtiyaclar ödənir, `strtotime()` ilə çox rahat string parse etmək mümkündür, Carbon isə əlavə güc verir.
