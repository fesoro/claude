# Fayl Giriş/Çıxış (File I/O)

## Giriş

Fayl əməliyyatları hər bir proqramlaşdırma dilinin əsas komponentlərindən biridir - konfiqurasiya fayllarını oxumaq, log yazmaq, məlumat ixrac etmək və s. Java fayl I/O üçün iki əsas API təqdim edir: klassik I/O streams və müasir NIO (New I/O). PHP isə sadə prosedural funksiyalardan başlayaraq OOP yanaşmalarına qədər geniş alətlər dəsti təklif edir.

## Java-da istifadəsi

### Klassik I/O - InputStream/OutputStream

Java-nın klassik I/O sistemi stream (axın) konsepsiyası üzərində qurulub:

```java
import java.io.*;

// FileInputStream - bayt-bayt oxuma
try (FileInputStream fis = new FileInputStream("məlumat.bin")) {
    int bayt;
    while ((bayt = fis.read()) != -1) {
        System.out.print((char) bayt);
    }
} catch (IOException e) {
    e.printStackTrace();
}

// FileOutputStream - bayt-bayt yazma
try (FileOutputStream fos = new FileOutputStream("çıxış.bin")) {
    byte[] data = "Salam, dünya!".getBytes();
    fos.write(data);
} catch (IOException e) {
    e.printStackTrace();
}

// Append rejimi
try (FileOutputStream fos = new FileOutputStream("log.txt", true)) {
    fos.write("Yeni sətir\n".getBytes());
}
```

### BufferedReader/BufferedWriter

Buferləmə performansı əhəmiyyətli dərəcədə artırır:

```java
// BufferedReader - sətir-sətir oxuma
try (BufferedReader br = new BufferedReader(new FileReader("mətn.txt"))) {
    String sətir;
    while ((sətir = br.readLine()) != null) {
        System.out.println(sətir);
    }
}

// BufferedWriter - buferli yazma
try (BufferedWriter bw = new BufferedWriter(new FileWriter("çıxış.txt"))) {
    bw.write("Birinci sətir");
    bw.newLine();
    bw.write("İkinci sətir");
    bw.newLine();
    bw.flush(); // buferi boşalt
}

// PrintWriter - daha rahat yazma
try (PrintWriter pw = new PrintWriter(new BufferedWriter(new FileWriter("log.txt")))) {
    pw.println("Log mesajı");
    pw.printf("Tarix: %s, Status: %d%n", "2026-04-11", 200);
}

// InputStreamReader - kodlaşdırma ilə oxuma
try (BufferedReader br = new BufferedReader(
        new InputStreamReader(
            new FileInputStream("utf8.txt"), "UTF-8"))) {
    String sətir = br.readLine();
}
```

### try-with-resources

Java 7-dən etibarən `AutoCloseable` interfeysi həyata keçirən resurslar avtomatik bağlanır:

```java
// Java 7+ try-with-resources
try (
    FileInputStream giriş = new FileInputStream("mənbə.txt");
    FileOutputStream çıxış = new FileOutputStream("hədəf.txt")
) {
    byte[] bufer = new byte[1024];
    int oxunmuş;
    while ((oxunmuş = giriş.read(bufer)) != -1) {
        çıxış.write(bufer, 0, oxunmuş);
    }
} // Hər iki stream avtomatik bağlanır, hətta exception olsa belə

// Java 9+ - əvvəlcədən yaradılmış dəyişənlər
FileInputStream giriş = new FileInputStream("mənbə.txt");
FileOutputStream çıxış = new FileOutputStream("hədəf.txt");
try (giriş; çıxış) {
    // əməliyyatlar
} // yenə avtomatik bağlanır

// try-with-resources olmadan (köhnə yanaşma - istifadə etməyin)
FileInputStream fis = null;
try {
    fis = new FileInputStream("fayl.txt");
    // oxuma əməliyyatları
} catch (IOException e) {
    e.printStackTrace();
} finally {
    if (fis != null) {
        try {
            fis.close();
        } catch (IOException e) {
            e.printStackTrace();
        }
    }
}
```

### NIO - Path və Files (Java 7+)

Müasir Java fayl əməliyyatları üçün NIO API istifadə olunmalıdır:

```java
import java.nio.file.*;
import java.nio.charset.StandardCharsets;

// Path yaratma
Path yol = Path.of("sənədlər", "fayl.txt");
Path yol2 = Paths.get("/home/user/fayl.txt"); // köhnə yanaşma, amma hələ işləyir
Path cari = Path.of(".").toAbsolutePath().normalize();

// Path əməliyyatları
System.out.println(yol.getFileName());     // fayl.txt
System.out.println(yol.getParent());       // sənədlər
System.out.println(yol.getRoot());         // null (nisbi yol)
System.out.println(yol.toAbsolutePath());  // tam yol
Path həll = yol.resolve("alt_qovluq");    // sənədlər/fayl.txt/alt_qovluq
Path nisbi = yol.relativize(yol2);         // nisbi yol

// Faylı tamamilə oxumaq
String məzmun = Files.readString(Path.of("fayl.txt")); // Java 11+
String məzmun2 = Files.readString(Path.of("fayl.txt"), StandardCharsets.UTF_8);

// Sətir-sətir oxumaq
List<String> sətirlər = Files.readAllLines(Path.of("fayl.txt"));
for (String sətir : sətirlər) {
    System.out.println(sətir);
}

// Böyük fayllar üçün - lazy stream
try (Stream<String> stream = Files.lines(Path.of("böyük_fayl.txt"))) {
    stream.filter(s -> s.contains("ERROR"))
          .forEach(System.out::println);
}

// Bayt olaraq oxumaq
byte[] baytlar = Files.readAllBytes(Path.of("şəkil.png"));

// Yazmaq
Files.writeString(Path.of("çıxış.txt"), "Məzmun buradadır"); // Java 11+
Files.write(Path.of("çıxış.txt"), "Məzmun".getBytes());
Files.write(Path.of("çıxış.txt"), sətirlər); // List<String> yazmaq

// Append rejimi
Files.writeString(
    Path.of("log.txt"),
    "Yeni sətir\n",
    StandardOpenOption.APPEND,
    StandardOpenOption.CREATE
);
```

### Fayl və qovluq əməliyyatları

```java
// Fayl mövcudluğu yoxlaması
boolean varMı = Files.exists(Path.of("fayl.txt"));
boolean qovluqMu = Files.isDirectory(Path.of("sənədlər"));
boolean oxunaBilir = Files.isReadable(Path.of("fayl.txt"));
boolean yazılaBilir = Files.isWritable(Path.of("fayl.txt"));

// Qovluq yaratma
Files.createDirectory(Path.of("yeni_qovluq"));
Files.createDirectories(Path.of("a/b/c/d")); // bütün valideyn qovluqları ilə

// Fayl kopyalama, köçürmə, silmə
Files.copy(
    Path.of("mənbə.txt"),
    Path.of("hədəf.txt"),
    StandardCopyOption.REPLACE_EXISTING
);
Files.move(
    Path.of("köhnə.txt"),
    Path.of("yeni.txt"),
    StandardCopyOption.ATOMIC_MOVE
);
Files.delete(Path.of("silinəcək.txt"));
Files.deleteIfExists(Path.of("olmaya_bilər.txt"));

// Fayl atributları
long ölçü = Files.size(Path.of("fayl.txt"));
FileTime dəyişdirmə = Files.getLastModifiedTime(Path.of("fayl.txt"));
String tip = Files.probeContentType(Path.of("şəkil.png")); // image/png

// Qovluq məzmununu siyahılamaq
try (DirectoryStream<Path> stream = Files.newDirectoryStream(Path.of("."))) {
    for (Path giriş : stream) {
        System.out.println(giriş.getFileName());
    }
}

// Filtr ilə
try (DirectoryStream<Path> stream = Files.newDirectoryStream(
        Path.of("."), "*.txt")) {
    for (Path fayl : stream) {
        System.out.println(fayl);
    }
}

// Rekursiv gəzmə
try (Stream<Path> yollar = Files.walk(Path.of("."))) {
    yollar.filter(Files::isRegularFile)
          .filter(p -> p.toString().endsWith(".java"))
          .forEach(System.out::println);
}

// Axtarış
try (Stream<Path> nəticələr = Files.find(
        Path.of("."), 10,
        (path, attr) -> attr.isRegularFile() && path.toString().endsWith(".log"))) {
    nəticələr.forEach(System.out::println);
}
```

### Müvəqqəti fayllar

```java
// Müvəqqəti fayl
Path temp = Files.createTempFile("prefix_", ".tmp");
System.out.println(temp); // /tmp/prefix_1234567890.tmp
Files.writeString(temp, "müvəqqəti məlumat");

// Müvəqqəti qovluq
Path tempDir = Files.createTempDirectory("app_");

// Proqram bitdikdə silinəcək
temp.toFile().deleteOnExit();
```

## PHP-də istifadəsi

### Əsas fayl funksiyaları

```php
// file_get_contents - faylı tamamilə oxumaq (ən sadə yol)
$məzmun = file_get_contents('fayl.txt');
echo $məzmun;

// URL-dən oxumaq da mümkündür
$html = file_get_contents('https://example.com');

// Kontekst ilə HTTP sorğusu
$kontekst = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => 'Content-Type: application/json',
        'content' => json_encode(['ad' => 'Orxan']),
    ]
]);
$cavab = file_get_contents('https://api.example.com/data', false, $kontekst);

// file_put_contents - fayla yazmaq
file_put_contents('çıxış.txt', 'Salam, dünya!');

// Append rejimi
file_put_contents('log.txt', "Yeni sətir\n", FILE_APPEND);

// Massiv yazmaq
file_put_contents('sətirlər.txt', implode("\n", ['sətir1', 'sətir2', 'sətir3']));

// file() - faylı massiv kimi oxumaq (hər sətir bir element)
$sətirlər = file('fayl.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
foreach ($sətirlər as $nömrə => $sətir) {
    echo ($nömrə + 1) . ": $sətir\n";
}
```

### fopen/fclose/fread/fwrite

Daha dəqiq nəzarət lazım olanda:

```php
// Oxuma
$fayl = fopen('mətn.txt', 'r'); // r = read
if ($fayl === false) {
    die('Fayl açıla bilmədi');
}

// Sətir-sətir oxuma
while (($sətir = fgets($fayl)) !== false) {
    echo trim($sətir) . "\n";
}
fclose($fayl);

// Simvol-simvol oxuma
$fayl = fopen('mətn.txt', 'r');
while (($simvol = fgetc($fayl)) !== false) {
    echo $simvol;
}
fclose($fayl);

// Müəyyən bayt sayı oxuma
$fayl = fopen('böyük_fayl.bin', 'rb'); // rb = read binary
$data = fread($fayl, 1024); // 1024 bayt oxu
fclose($fayl);

// Yazma
$fayl = fopen('çıxış.txt', 'w'); // w = write (mövcud məzmunu silir)
fwrite($fayl, "Birinci sətir\n");
fwrite($fayl, "İkinci sətir\n");
fputs($fayl, "Üçüncü sətir\n"); // fwrite ilə eynidir
fclose($fayl);

// Append
$fayl = fopen('log.txt', 'a'); // a = append
fwrite($fayl, date('[Y-m-d H:i:s]') . " Log mesajı\n");
fclose($fayl);

// Oxuma + yazma
$fayl = fopen('data.txt', 'r+'); // r+ = read + write
$məzmun = fread($fayl, filesize('data.txt'));
fseek($fayl, 0); // əvvələ qayıt
fwrite($fayl, strtoupper($məzmun));
fclose($fayl);

// Fayl rejimləri:
// 'r'  - yalnız oxuma, başlanğıcdan
// 'r+' - oxuma + yazma, başlanğıcdan
// 'w'  - yalnız yazma, faylı boşaldır, yoxdursa yaradır
// 'w+' - oxuma + yazma, faylı boşaldır
// 'a'  - yalnız yazma, sona əlavə edir
// 'a+' - oxuma + yazma, sona əlavə edir
// 'x'  - yaratma + yazma, fayl varsa xəta
// 'x+' - yaratma + oxuma + yazma, fayl varsa xəta
```

### Fayl kilidi (File Locking)

```php
$fayl = fopen('paylaşılan.txt', 'a');

// Eksklüziv kilid (yazma üçün)
if (flock($fayl, LOCK_EX)) {
    fwrite($fayl, "Təhlükəsiz yazma\n");
    flock($fayl, LOCK_UN); // kilidi aç
}
fclose($fayl);

// Paylaşılan kilid (oxuma üçün)
$fayl = fopen('paylaşılan.txt', 'r');
if (flock($fayl, LOCK_SH)) {
    $məzmun = fread($fayl, filesize('paylaşılan.txt'));
    flock($fayl, LOCK_UN);
}
fclose($fayl);

// Bloklamayan kilid
if (flock($fayl, LOCK_EX | LOCK_NB)) {
    // Kilid alındı
} else {
    // Fayl başqa proses tərəfindən kildlənib
}
```

### SplFileObject

OOP yanaşma ilə fayl əməliyyatları:

```php
// SplFileObject - OOP fayl idarəsi
$fayl = new SplFileObject('mətn.txt', 'r');

// Sətir-sətir oxuma (Iterator interfeysi)
foreach ($fayl as $nömrə => $sətir) {
    echo ($nömrə + 1) . ": " . trim($sətir) . "\n";
}

// Konkret sətirə keçmə
$fayl->seek(4); // 5-ci sətirə keç (0-dan başlayır)
echo $fayl->current();

// CSV oxuma
$csv = new SplFileObject('data.csv', 'r');
$csv->setFlags(SplFileObject::READ_CSV);
$csv->setCsvControl(',', '"', '\\');

foreach ($csv as $sətir) {
    if ($sətir[0] !== null) { // boş sətirləri keç
        print_r($sətir);
    }
}

// CSV yazma
$csv = new SplFileObject('çıxış.csv', 'w');
$csv->fputcsv(['Ad', 'Soyad', 'Yaş']);
$csv->fputcsv(['Orxan', 'Əliyev', '25']);
$csv->fputcsv(['Əli', 'Həsənov', '30']);

// SplFileInfo - fayl məlumatları
$info = new SplFileInfo('fayl.txt');
echo $info->getSize();          // bayt
echo $info->getExtension();     // txt
echo $info->getRealPath();      // tam yol
echo $info->getMTime();         // son dəyişdirmə vaxtı
echo $info->isReadable();       // true/false
echo $info->isWritable();       // true/false
```

### Qovluq əməliyyatları

```php
// Qovluq yaratma
mkdir('yeni_qovluq');
mkdir('a/b/c/d', 0755, true); // rekursiv, icazə ilə

// Qovluq mövcudluğu
if (is_dir('qovluq')) {
    echo "Qovluq mövcuddur";
}

// Qovluq məzmununu siyahılamaq
$fayllar = scandir('.');
foreach ($fayllar as $fayl) {
    if ($fayl === '.' || $fayl === '..') continue;
    echo $fayl . (is_dir($fayl) ? '/' : '') . "\n";
}

// glob() - pattern ilə axtarış
$txtFayllar = glob('*.txt');
$bütünFayllar = glob('sənədlər/*.{txt,pdf,doc}', GLOB_BRACE);
$rekursiv = glob('**/*.php'); // işləmir, bunun əvəzinə:

// RecursiveDirectoryIterator - rekursiv gəzmə
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator('src'),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iterator as $fayl) {
    if ($fayl->isFile() && $fayl->getExtension() === 'php') {
        echo $fayl->getRealPath() . "\n";
    }
}

// Fayl əməliyyatları
copy('mənbə.txt', 'hədəf.txt');                    // kopyalama
rename('köhnə.txt', 'yeni.txt');                     // köçürmə/adını dəyişmə
unlink('silinəcək.txt');                              // faylı silmə
rmdir('boş_qovluq');                                  // boş qovluğu silmə

// Rekursiv qovluq silmə (daxili funksiya yoxdur)
function qovluğuSil(string $yol): void {
    if (is_dir($yol)) {
        $elementlər = scandir($yol);
        foreach ($elementlər as $element) {
            if ($element === '.' || $element === '..') continue;
            $tamYol = $yol . DIRECTORY_SEPARATOR . $element;
            if (is_dir($tamYol)) {
                qovluğuSil($tamYol);
            } else {
                unlink($tamYol);
            }
        }
        rmdir($yol);
    }
}
```

### Fayl məlumatları və icazələr

```php
// Fayl məlumatları
echo filesize('fayl.txt');              // bayt ölçüsü
echo filetype('fayl.txt');              // file, dir, link, ...
echo filemtime('fayl.txt');             // son dəyişdirmə vaxtı (timestamp)
echo fileatime('fayl.txt');             // son əlçatma vaxtı
echo filectime('fayl.txt');             // yaradılma vaxtı

// Yol funksiyaları
echo pathinfo('sənədlər/hesabat.pdf', PATHINFO_EXTENSION);  // pdf
echo pathinfo('sənədlər/hesabat.pdf', PATHINFO_FILENAME);   // hesabat
echo pathinfo('sənədlər/hesabat.pdf', PATHINFO_DIRNAME);    // sənədlər
echo pathinfo('sənədlər/hesabat.pdf', PATHINFO_BASENAME);   // hesabat.pdf

echo basename('/home/user/fayl.txt');  // fayl.txt
echo dirname('/home/user/fayl.txt');   // /home/user
echo realpath('.');                     // tam yol

// İcazələr
chmod('fayl.txt', 0644);
chown('fayl.txt', 'user');
chgrp('fayl.txt', 'group');
echo decoct(fileperms('fayl.txt') & 0777); // 644

// Mövcudluq yoxlamaları
file_exists('fayl.txt');    // fayl və ya qovluq
is_file('fayl.txt');        // yalnız fayl
is_dir('qovluq');           // yalnız qovluq
is_readable('fayl.txt');    // oxuna bilir?
is_writable('fayl.txt');    // yazıla bilir?
is_link('link.txt');        // simvolik link?

// Müvəqqəti fayl
$temp = tmpfile();
fwrite($temp, 'müvəqqəti data');
$metaData = stream_get_meta_data($temp);
echo $metaData['uri']; // müvəqqəti fayl yolu
fclose($temp); // avtomatik silinir

// tempnam - müvəqqəti fayl adı
$tempFayl = tempnam(sys_get_temp_dir(), 'app_');
file_put_contents($tempFayl, 'data');
```

## Əsas fərqlər

| Xüsusiyyət | Java | PHP |
|---|---|---|
| Əsas API | NIO (`Path`, `Files`) | Prosedural funksiyalar (`file_get_contents` və s.) |
| Fayl oxuma | `Files.readString()`, `Files.readAllLines()` | `file_get_contents()`, `file()` |
| Fayl yazma | `Files.writeString()`, `Files.write()` | `file_put_contents()` |
| Stream oxuma | `BufferedReader`, `InputStream` | `fopen()`/`fgets()`/`fclose()` |
| Resurs idarəsi | `try-with-resources` (avtomatik) | Manual `fclose()`, `SplFileObject` (destructor) |
| Yol API | `Path` sinfi (zəngin API) | `pathinfo()`, `basename()`, `dirname()` |
| Qovluq gəzmə | `Files.walk()`, `DirectoryStream` | `scandir()`, `RecursiveDirectoryIterator` |
| Axtarış | `Files.find()` | `glob()` |
| Böyük fayllar | `Files.lines()` (lazy Stream) | `fgets()` ilə sətir-sətir |
| Binary I/O | `Channel`, `ByteBuffer` | `fread()`/`fwrite()` binary mode |
| OOP yanaşma | Bütün API OOP-dur | `SplFileObject` (alternativ) |

## Niyə belə fərqlər var?

### Java-nın yanaşması

Java fayl I/O-da iki böyük keçid yaşayıb:

1. **Klassik I/O (java.io)**: Stream konsepsiyası üzərində qurulub - `InputStream`/`OutputStream` (bayt), `Reader`/`Writer` (simvol). Decorator pattern ilə funksionallıq əlavə olunur (`BufferedReader(new InputStreamReader(new FileInputStream(...)))`). Bu çox çevik amma verboz-dur.

2. **NIO (java.nio)**: Java 7 ilə `Path` və `Files` sinifləri gəldi. `Files.readString()`, `Files.writeString()` kimi sadə metodlar əlavə olundu. Böyük fayllar üçün `Files.lines()` lazy Stream qaytarır ki, yaddaşda saxlanılmasın.

`try-with-resources` Java-nın resurs idarəsindəki ən mühüm yeniliyidir - fayl handle-ların mütləq bağlanmasını təmin edir, hətta exception olsa belə.

### PHP-nin yanaşması

PHP başlanğıcdan web development üçün nəzərdə tutulub və fayl əməliyyatları mümkün qədər sadə olmalıdır:

1. **Sadəlik**: `file_get_contents()` və `file_put_contents()` bir sətirdə faylı tam oxuyub/yazır. Web tətbiqlərində əksər fayl əməliyyatları bu qədər sadədir.

2. **C miras**: `fopen()`, `fread()`, `fwrite()`, `fclose()` C dilindəki funksiyalara tam uyğundur. PHP C-də yazılıb və bir çox funksiya adı C-dən gəlir.

3. **Avtomatik resurs idarəsi yoxdur**: PHP-də Java-nın `try-with-resources` kimi mexanizm yoxdur. Amma PHP-nin hər sorğudan sonra bütün resursları azad etməsi bu problemi azaldır. `SplFileObject` destructor-da faylı bağlayır.

4. **URL wrappers**: PHP-nin `file_get_contents()` funksiyası həm lokal faylları, həm URL-ləri oxuya bilir. Bu, PHP-nin stream wrapper arxitekturasının nəticəsidir - `php://`, `http://`, `ftp://` kimi sxemləri dəstəkləyir.

### Nəticə

Java fayl I/O-da tip təhlükəsizliyi, resursların mütləq bağlanması və böyük fayllarla effektiv işləməni ön plana çıxarır. PHP isə sadəlik və sürətli inkişafı seçib - əksər web tətbiqləri üçün `file_get_contents()`/`file_put_contents()` kifayətdir. Java-nın NIO API-si daha güclü və çevik olsa da, sadə əməliyyatlar üçün PHP-nin yanaşması daha az kod tələb edir.
