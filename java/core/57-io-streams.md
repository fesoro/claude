# 57 — Java I/O Streams

> **Seviyye:** Intermediate ⭐⭐


## Mündəricat
1. [I/O Stream-lərə Giriş](#io-stream-lərə-giriş)
2. [InputStream/OutputStream (Byte Streams)](#inputstreamoutputstream-byte-streams)
3. [Reader/Writer (Character Streams)](#readerwriter-character-streams)
4. [Buffered Stream-lər](#buffered-stream-lər)
5. [InputStreamReader/OutputStreamWriter](#inputstreamreaderoutputstreamwriter)
6. [PrintWriter](#printwriter)
7. [Files Utility (NIO.2)](#files-utility-nio2)
8. [Charset Encoding](#charset-encoding)
9. [İntervyu Sualları](#i̇ntervyu-sualları)

---

## I/O Stream-lərə Giriş

**Stream** — məlumatların ardıcıl axını. Java-da I/O stream-lər iki əsas kateqoriyaya bölünür:

```
Byte Streams (8-bit)          Character Streams (16-bit Unicode)
InputStream                   Reader
  ├── FileInputStream           ├── FileReader
  ├── ByteArrayInputStream      ├── CharArrayReader
  ├── FilterInputStream         ├── InputStreamReader (bridge)
  │    └── BufferedInputStream  └── BufferedReader
  └── ObjectInputStream

OutputStream                  Writer
  ├── FileOutputStream          ├── FileWriter
  ├── ByteArrayOutputStream     ├── CharArrayWriter
  ├── FilterOutputStream        ├── OutputStreamWriter (bridge)
  │    └── BufferedOutputStream ├── BufferedWriter
  └── ObjectOutputStream        └── PrintWriter
```

**Dekorator Pattern** — Java I/O-nun əsasını təşkil edir:
```java
// Dekorator Pattern: stream-lər bir-birini sarır
InputStream raw = new FileInputStream("file.txt");
InputStream buffered = new BufferedInputStream(raw);    // raw-u sararaq buffer əlavə edir
InputStreamReader reader = new InputStreamReader(buffered); // byte → char çevirməsi
BufferedReader br = new BufferedReader(reader);        // char stream-ə buffer əlavə edir
```

---

## InputStream/OutputStream (Byte Streams)

Byte stream-lər bütün növ binary məlumatlar üçün (şəkillər, video, şifrəli məlumatlar).

### FileInputStream / FileOutputStream

```java
import java.io.*;

public class ByteStreamDemo {

    // Fayl kopyalama — byte-by-byte (yavaş, amma aydın)
    public static void copyFileSlow(String source, String dest) throws IOException {
        try (FileInputStream fis = new FileInputStream(source);
             FileOutputStream fos = new FileOutputStream(dest)) {

            int byteValue;
            while ((byteValue = fis.read()) != -1) { // -1 = EOF (End Of File)
                fos.write(byteValue); // Bir byte oxu, bir byte yaz
            }
        }
        System.out.println("Kopyalandı (byte-by-byte)");
    }

    // Fayl kopyalama — buffer ilə (sürətli)
    public static void copyFileBuffered(String source, String dest) throws IOException {
        byte[] buffer = new byte[8192]; // 8KB buffer
        try (FileInputStream fis = new FileInputStream(source);
             FileOutputStream fos = new FileOutputStream(dest)) {

            int bytesRead;
            while ((bytesRead = fis.read(buffer)) != -1) {
                fos.write(buffer, 0, bytesRead); // Yalnız oxunan qədər yaz
            }
        }
        System.out.println("Kopyalandı (buffered)");
    }

    // Java 9+ transferTo()
    public static void copyFileModern(String source, String dest) throws IOException {
        try (FileInputStream fis = new FileInputStream(source);
             FileOutputStream fos = new FileOutputStream(dest)) {
            long bytes = fis.transferTo(fos); // Çox sadə!
            System.out.println(bytes + " byte kopyalandı");
        }
    }

    // FileOutputStream — append mode
    public static void appendToFile(String path, byte[] data) throws IOException {
        // İkinci parametr true = append mode (əvvəlki məzmuna əlavə edir)
        try (FileOutputStream fos = new FileOutputStream(path, true)) {
            fos.write(data);
        }
    }

    // Faylın bütün məzmununu oxumaq
    public static byte[] readAllBytes(String path) throws IOException {
        try (FileInputStream fis = new FileInputStream(path)) {
            return fis.readAllBytes(); // Java 9+
        }
    }

    public static void main(String[] args) throws IOException {
        // Test üçün kiçik fayl yarat
        try (FileOutputStream fos = new FileOutputStream("/tmp/test.bin")) {
            fos.write(new byte[]{72, 101, 108, 108, 111}); // "Hello" ASCII
        }

        byte[] content = readAllBytes("/tmp/test.bin");
        System.out.println("Məzmun: " + new String(content)); // Hello

        copyFileModern("/tmp/test.bin", "/tmp/test_copy.bin");
    }
}
```

### ByteArrayInputStream / ByteArrayOutputStream

```java
import java.io.*;

public class ByteArrayStreamDemo {

    // ByteArrayOutputStream — yaddaşda byte axını
    public static byte[] serializeData(String data) throws IOException {
        ByteArrayOutputStream baos = new ByteArrayOutputStream();

        try (DataOutputStream dos = new DataOutputStream(baos)) {
            dos.writeUTF(data);          // String yaz
            dos.writeInt(data.length()); // Integer yaz
            dos.writeDouble(3.14);       // Double yaz
        }

        return baos.toByteArray(); // Bütün yazılanları byte[] kimi al
    }

    // ByteArrayInputStream — byte[] -dan oxumaq
    public static void deserializeData(byte[] bytes) throws IOException {
        try (DataInputStream dis = new DataInputStream(new ByteArrayInputStream(bytes))) {
            String text = dis.readUTF();    // String oxu
            int len = dis.readInt();        // Integer oxu
            double pi = dis.readDouble();   // Double oxu
            System.out.printf("text=%s, len=%d, pi=%.2f%n", text, len, pi);
        }
    }

    public static void main(String[] args) throws IOException {
        byte[] serialized = serializeData("Salam");
        System.out.println("Serialized: " + serialized.length + " bytes");
        deserializeData(serialized);
    }
}
```

---

## Reader/Writer (Character Streams)

Character stream-lər mətn faylları üçün — Unicode dəstəyi var.

```java
import java.io.*;

public class CharacterStreamDemo {

    // FileReader / FileWriter — mətn faylları
    public static void writeTextFile(String path, String content) throws IOException {
        try (FileWriter fw = new FileWriter(path);
             BufferedWriter bw = new BufferedWriter(fw)) {
            bw.write(content);
            bw.newLine(); // Platform-spesifik yeni sətir (\n ya \r\n)
        }
    }

    public static String readTextFile(String path) throws IOException {
        StringBuilder sb = new StringBuilder();
        try (FileReader fr = new FileReader(path);
             BufferedReader br = new BufferedReader(fr)) {
            String line;
            while ((line = br.readLine()) != null) {
                sb.append(line).append('\n');
            }
        }
        return sb.toString();
    }

    // CharArrayReader / CharArrayWriter — yaddaşda char axını
    public static void charArrayDemo() throws IOException {
        char[] chars = "Azerbaycan dili".toCharArray();

        CharArrayReader car = new CharArrayReader(chars);
        int ch;
        while ((ch = car.read()) != -1) {
            System.out.print((char) ch);
        }
        System.out.println();
    }

    // StringReader / StringWriter — String üzərindən stream
    public static void stringStreamDemo() throws IOException {
        StringWriter sw = new StringWriter();
        sw.write("Mən ");
        sw.write("Java ");
        sw.write("öyrənirəm");

        String result = sw.toString(); // "Mən Java öyrənirəm"
        System.out.println(result);

        StringReader sr = new StringReader(result);
        BufferedReader br = new BufferedReader(sr);
        System.out.println(br.readLine()); // Mən Java öyrənirəm
    }

    public static void main(String[] args) throws IOException {
        writeTextFile("/tmp/text.txt", "Salam, Java IO!\nIkinci sətir.");
        System.out.println(readTextFile("/tmp/text.txt"));
        charArrayDemo();
        stringStreamDemo();
    }
}
```

---

## Buffered Stream-lər

**Buffered stream-lər** — hər əməliyyatda OS-ə müraciət əvəzinə buffer istifadə edərək performansı artırır.

```java
import java.io.*;

public class BufferedStreamDemo {

    // Performans müqayisəsi
    public static long testUnbuffered(String path) throws IOException {
        long start = System.nanoTime();

        try (FileOutputStream fos = new FileOutputStream(path)) {
            for (int i = 0; i < 100_000; i++) {
                fos.write('A'); // Hər dəfə OS system call! Çox yavaş!
            }
        }

        return System.nanoTime() - start;
    }

    public static long testBuffered(String path) throws IOException {
        long start = System.nanoTime();

        try (BufferedOutputStream bos = new BufferedOutputStream(
                new FileOutputStream(path), 8192)) { // 8KB buffer
            for (int i = 0; i < 100_000; i++) {
                bos.write('A'); // Buffer dolunca OS-ə müraciət etmir
            }
            // try bloku bitdikdə flush + close otomatik çağırılır
        }

        return System.nanoTime() - start;
    }

    // BufferedReader-in readLine() metodu
    public static void processLargeFile(String path) throws IOException {
        int lineCount = 0;
        long totalChars = 0;

        try (BufferedReader br = new BufferedReader(
                new FileReader(path), 65536)) { // 64KB buffer

            String line;
            while ((line = br.readLine()) != null) {
                lineCount++;
                totalChars += line.length();
                // Hər sətri emal et
            }
        }

        System.out.printf("Sətir sayı: %d, Cəmi simvol: %d%n", lineCount, totalChars);
    }

    public static void main(String[] args) throws IOException {
        long unbuffered = testUnbuffered("/tmp/unbuf.txt");
        long buffered = testBuffered("/tmp/buf.txt");

        System.out.printf("Buffersiz: %.2f ms%n", unbuffered / 1_000_000.0);
        System.out.printf("Bufferli: %.2f ms%n", buffered / 1_000_000.0);
        System.out.printf("Sürətlənmə: %.1fx%n", (double) unbuffered / buffered);
        // Adətən 10-100x sürətlənmə görünür
    }
}
```

---

## InputStreamReader / OutputStreamWriter

**Bridge stream-lər** — byte stream-ləri character stream-lərə çevirir. Encoding göstərmək mümkündür.

```java
import java.io.*;
import java.nio.charset.*;

public class BridgeStreamDemo {

    // InputStreamReader — byte → char (encoding ilə)
    public static String readWithEncoding(String path, Charset charset) throws IOException {
        StringBuilder sb = new StringBuilder();

        try (InputStream fis = new FileInputStream(path);
             InputStreamReader isr = new InputStreamReader(fis, charset);
             BufferedReader br = new BufferedReader(isr)) {

            String line;
            while ((line = br.readLine()) != null) {
                sb.append(line).append('\n');
            }
        }
        return sb.toString();
    }

    // OutputStreamWriter — char → byte (encoding ilə)
    public static void writeWithEncoding(String path, String content,
            Charset charset) throws IOException {

        try (OutputStream fos = new FileOutputStream(path);
             OutputStreamWriter osw = new OutputStreamWriter(fos, charset);
             BufferedWriter bw = new BufferedWriter(osw)) {
            bw.write(content);
        }
    }

    // Şəbəkə stream-ləri ilə — Server response oxumaq
    public static String readHttpResponse(java.net.Socket socket) throws IOException {
        try (InputStream is = socket.getInputStream();
             InputStreamReader isr = new InputStreamReader(is, StandardCharsets.UTF_8);
             BufferedReader br = new BufferedReader(isr)) {

            StringBuilder sb = new StringBuilder();
            String line;
            while ((line = br.readLine()) != null) {
                sb.append(line).append('\n');
            }
            return sb.toString();
        }
    }

    public static void main(String[] args) throws IOException {
        String content = "Azərbaycan dili: ə, ö, ü, ğ, ş, ç, ı";

        // UTF-8 ilə yaz
        writeWithEncoding("/tmp/utf8.txt", content, StandardCharsets.UTF_8);

        // UTF-8 ilə oxu
        String read = readWithEncoding("/tmp/utf8.txt", StandardCharsets.UTF_8);
        System.out.println(read);

        // Windows-1252 ilə yaz/oxu (köhnə Windows sistemlər)
        Charset win = Charset.forName("windows-1252");
        // writeWithEncoding("/tmp/win1252.txt", content, win); // bəzi simvollar düzgün olmaya bilər
    }
}
```

---

## PrintWriter

**PrintWriter** — formatted text çıxışı üçün.

```java
import java.io.*;
import java.nio.charset.*;

public class PrintWriterDemo {

    public static void main(String[] args) throws IOException {

        // PrintWriter — formatted çıxış
        try (PrintWriter pw = new PrintWriter(
                new FileWriter("/tmp/output.txt"))) {

            pw.println("Salam, Java!"); // Yeni sətir əlavə edir
            pw.printf("Ad: %s, Yaş: %d%n", "Əli", 25); // Formatted
            pw.print("Sonda yeni sətir yoxdur");

            // println auto-flush yoxdur (default) — flush() əl ilə ya close() ilə
        }

        // Auto-flush aktivli PrintWriter
        try (PrintWriter pw = new PrintWriter(
                new OutputStreamWriter(System.out, StandardCharsets.UTF_8), true)) {
            // true = auto-flush (println, printf, format-dan sonra)
            pw.println("Auto-flush aktivdir");
        }

        // Fayla yazma — auto-flush olmadan (sürətli)
        try (PrintWriter pw = new PrintWriter(
                new BufferedWriter(new FileWriter("/tmp/report.txt")))) {
            pw.println("=== Hesabat ===");
            for (int i = 1; i <= 10; i++) {
                pw.printf("Sıra %2d: Dəyər = %6.2f%n", i, i * Math.PI);
            }
            pw.println("=== Son ===");
        }

        // PrintWriter-in checkError() metodu
        PrintWriter pw = new PrintWriter(System.out);
        pw.println("Test");
        if (pw.checkError()) {
            System.err.println("PrintWriter-də xəta baş verdi!");
        }

        // Konsola yazmaq üçün (System.out da PrintStream-dir, PrintWriter deyil)
        PrintStream ps = System.out;
        ps.println("PrintStream: System.out");
        ps.printf("Formatted: %s = %d%n", "cavab", 42);
    }
}
```

---

## Files Utility (NIO.2)

**`java.nio.file.Files`** — Java 7+ fayllar üçün utility class. Çox əməliyyatı sadəcə bir metod çağırışı ilə edir.

```java
import java.io.*;
import java.nio.charset.*;
import java.nio.file.*;
import java.util.*;
import java.util.stream.*;

public class FilesUtilityDemo {

    public static void main(String[] args) throws IOException {
        Path file = Path.of("/tmp/demo.txt");
        Path dir = Path.of("/tmp/demo_dir");

        // === YAZMA ===
        // Bütün məzmunu bir anda yaz (kiçik fayllar üçün)
        Files.writeString(file, "Salam, NIO.2!\nİkinci sətir.", StandardCharsets.UTF_8);

        // Sətirləri yaz
        List<String> lines = List.of("Birinci", "İkinci", "Üçüncü");
        Files.write(file, lines, StandardCharsets.UTF_8, StandardOpenOption.APPEND);

        // Byte[] yaz
        Files.write(Path.of("/tmp/bytes.bin"), new byte[]{1, 2, 3, 4, 5});

        // === OXUMA ===
        // Bütün məzmunu bir sətirdə oxu
        String content = Files.readString(file, StandardCharsets.UTF_8);
        System.out.println("Məzmun:\n" + content);

        // Sətirləri list kimi oxu
        List<String> readLines = Files.readAllLines(file, StandardCharsets.UTF_8);
        readLines.forEach(System.out::println);

        // Stream kimi oxu (böyük fayllar üçün — lazy loading)
        try (Stream<String> lineStream = Files.lines(file, StandardCharsets.UTF_8)) {
            long count = lineStream.filter(l -> l.contains("inci")).count();
            System.out.println("'inci' olan sətir sayı: " + count);
        }

        // Byte[] oxu
        byte[] bytes = Files.readAllBytes(Path.of("/tmp/bytes.bin"));
        System.out.println("Bytes: " + Arrays.toString(bytes));

        // === KÖÇÜRMƏ ===
        // Fayl kopyalama
        Path dest = Path.of("/tmp/demo_copy.txt");
        Files.copy(file, dest, StandardCopyOption.REPLACE_EXISTING);

        // Fayl köçürmə (rename)
        Path moved = Path.of("/tmp/demo_moved.txt");
        Files.move(dest, moved, StandardCopyOption.REPLACE_EXISTING);

        // === QOVLUQ ===
        // Qovluq yarat
        Files.createDirectory(dir);
        Files.createDirectories(Path.of("/tmp/a/b/c")); // Aralıq qovluqları da yarat

        // Qovluğu gəz
        try (DirectoryStream<Path> stream = Files.newDirectoryStream(Path.of("/tmp"), "*.txt")) {
            for (Path entry : stream) {
                System.out.println(entry.getFileName());
            }
        }

        // Rekursiv gəzmək (Files.walk)
        try (Stream<Path> walk = Files.walk(Path.of("/tmp"), 2)) { // max depth = 2
            walk.filter(Files::isRegularFile)
                .filter(p -> p.toString().endsWith(".txt"))
                .forEach(p -> System.out.println("TXT: " + p));
        }

        // === YOXLAMA ===
        System.out.println("Mövcuddur: " + Files.exists(file));
        System.out.println("Qovluq: " + Files.isDirectory(file));
        System.out.println("Fayl: " + Files.isRegularFile(file));
        System.out.println("Oxunabilir: " + Files.isReadable(file));
        System.out.println("Yazıla bilir: " + Files.isWritable(file));
        System.out.println("Ölçü: " + Files.size(file) + " bytes");

        // Metadata
        java.nio.file.attribute.BasicFileAttributes attrs =
            Files.readAttributes(file, java.nio.file.attribute.BasicFileAttributes.class);
        System.out.println("Yaradılma: " + attrs.creationTime());
        System.out.println("Son dəyişiklik: " + attrs.lastModifiedTime());

        // === SİLMƏ ===
        Files.deleteIfExists(moved);
        Files.deleteIfExists(dir);

        // === MÜVƏQQƏTİ FAYL ===
        Path tempFile = Files.createTempFile("myapp_", ".tmp");
        Path tempDir = Files.createTempDirectory("myapp_");
        System.out.println("Müvəqqəti fayl: " + tempFile);
        Files.deleteIfExists(tempFile);
    }
}
```

---

## Charset Encoding

```java
import java.nio.charset.*;
import java.nio.file.*;
import java.util.*;

public class CharsetDemo {

    public static void main(String[] args) throws Exception {

        // Mövcud charset-lər
        System.out.println("Standart charset-lər:");
        System.out.println("UTF-8: " + StandardCharsets.UTF_8);
        System.out.println("UTF-16: " + StandardCharsets.UTF_16);
        System.out.println("US-ASCII: " + StandardCharsets.US_ASCII);
        System.out.println("ISO-8859-1 (Latin-1): " + StandardCharsets.ISO_8859_1);

        // Sistem default charset
        System.out.println("Default: " + Charset.defaultCharset());
        // Java 18+: həmişə UTF-8 (əvvəllər OS-ə görə dəyişirdi)

        // String-i encode/decode etmək
        String azerbaijani = "Azərbaycan: ə, ö, ü, ğ, ş, ç";

        // String → byte[] (encode)
        byte[] utf8Bytes = azerbaijani.getBytes(StandardCharsets.UTF_8);
        byte[] utf16Bytes = azerbaijani.getBytes(StandardCharsets.UTF_16);
        System.out.printf("UTF-8: %d bytes, UTF-16: %d bytes%n",
            utf8Bytes.length, utf16Bytes.length);

        // byte[] → String (decode)
        String decoded = new String(utf8Bytes, StandardCharsets.UTF_8);
        System.out.println("Decoded: " + decoded);

        // Yanlış encoding ilə decode — məlumat itkisi!
        String wrongDecoded = new String(utf8Bytes, StandardCharsets.ISO_8859_1);
        System.out.println("Yanlış decode: " + wrongDecoded); // Hərf başlanğıcında əyri

        // Charset.encode/decode — CharBuffer ilə
        Charset utf8 = StandardCharsets.UTF_8;
        java.nio.ByteBuffer encoded = utf8.encode(azerbaijani);
        java.nio.CharBuffer chars = utf8.decode(encoded);
        System.out.println("Charset encode/decode: " + chars);

        // BOM (Byte Order Mark) — UTF-8 faylların başında bəzən görünür
        // UTF-8 BOM: EF BB BF (Windows Notepad bunu əlavə edir)
        // Java-da oxuyanda BOM-u silmək lazım ola bilər:
        byte[] withBom = new byte[]{(byte)0xEF, (byte)0xBB, (byte)0xBF, 'H', 'i'};
        String bomString = new String(withBom, StandardCharsets.UTF_8);
        System.out.println("BOM ilə: [" + bomString + "]"); // [\uFEFFHi]
        String withoutBom = bomString.startsWith("\uFEFF") ?
            bomString.substring(1) : bomString;
        System.out.println("BOM silindikdən sonra: [" + withoutBom + "]"); // [Hi]

        // Bütün mövcud charset-ləri siyahıla
        SortedMap<String, Charset> allCharsets = Charset.availableCharsets();
        System.out.println("Mövcud charset sayı: " + allCharsets.size());
        // Adətən 150+ charset mövcuddur
    }
}
```

---

## İntervyu Sualları

**S: Byte stream ilə character stream arasındakı fərq nədir?**
C: Byte stream-lər (InputStream/OutputStream) raw binary məlumat işləyir — 8-bit. Character stream-lər (Reader/Writer) Unicode mətn işləyir — 16-bit. Character stream-lər encoding/decoding edir (UTF-8, ISO-8859-1 vs). Mətn üçün Reader/Writer, binary üçün InputStream/OutputStream istifadə et.

**S: Niyə BufferedInputStream/BufferedReader istifadə etmək lazımdır?**
C: Hər `read()` çağırışı olmadan OS system call yerinə buffer (default 8KB) istifadə edir. 100,000 byte oxumaq: buffersiz = 100,000 system call; bufferli = ~13 system call. 10-100x performans artımı verə bilər.

**S: `InputStreamReader` nə üçün lazımdır?**
C: Byte stream-dən character stream-ə "körpü" yaradır. `FileInputStream` byte verir, `BufferedReader.readLine()` isə char tələb edir. `InputStreamReader` bu çevrilməni encoding göstərərək edir: `new InputStreamReader(fis, StandardCharsets.UTF_8)`.

**S: `Files.lines()` niyə `Files.readAllLines()`-dan üstündür?**
C: `Files.readAllLines()` bütün faylı yaddaşa yükləyir — böyük faylda OutOfMemoryError riski. `Files.lines()` Stream qaytarır — lazy loading edir, yalnız tələb olunanda sətri oxuyur. Hər zaman `try-with-resources` ilə istifadə et (stream-i bağlamaq üçün).

**S: try-with-resources olmadan I/O kodunun nəyi səhvdir?**
C: Exception baş verdikdə `close()` çağırılmır → resurs sızması (file descriptor leak). `finally`-də manual `close()` yazmaq da mürəkkəbdir — `close()` özü exception atarsa orijinal exception udulur.

**S: Default charset nə idi, indi nədir?**
C: Java 17 və aşağıda: OS-ə görə dəyişirdi (Windows-da Cp1252, Linux-da UTF-8). Java 18-dən: həmişə UTF-8. Buna görə həmişə eksplisit charset göstərmək (`StandardCharsets.UTF_8`) tövsiyə olunurdu.

**S: `PrintWriter` vs `BufferedWriter` fərqi?**
C: `BufferedWriter` yalnız buffer əlavə edir. `PrintWriter` `println()`, `printf()`, `format()` kimi rahat metodlar təqdim edir, platform-spesifik yeni sətir (`newLine()`) istifadə edir. `PrintWriter` heç vaxt exception atmır (checkError() istifadə edilir). Adətən `PrintWriter`-ı `BufferedWriter`-ın üzərinə qurmaq tövsiyə olunur.
