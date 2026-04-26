# 58 — Java NIO (Non-blocking I/O)

> **Seviyye:** Senior ⭐⭐⭐


## Mündəricat
1. [NIO vs OIO](#nio-vs-oio)
2. [Channel](#channel)
3. [Buffer](#buffer)
4. [Selector (Multiplexing)](#selector-multiplexing)
5. [Memory-mapped Files](#memory-mapped-files)
6. [AsynchronousFileChannel](#asynchronousfilechannel)
7. [İntervyu Sualları](#i̇ntervyu-sualları)

---

## NIO vs OIO

**OIO (Old I/O / Blocking I/O)** — `java.io` paketi — hər I/O əməliyyatı thread-i blokir.

**NIO (New I/O / Non-blocking I/O)** — `java.nio` paketi (Java 4+) — non-blocking, selector-based.

**NIO.2** — `java.nio.file` paketi (Java 7+) — `Path`, `Files`, async channel-lar.

```
OIO (Blocking):
Thread 1: [oxu → BLOCK → davam] [yazı → BLOCK → davam]
Thread 2: [oxu → BLOCK → davam] [yazı → BLOCK → davam]
Thread 3: [oxu → BLOCK → davam]
→ Hər bağlantı üçün ayrı thread lazımdır!
→ 10,000 bağlantı = 10,000 thread → Out of Memory!

NIO (Non-blocking + Selector):
Thread:   [channel1 hazırdır → oxu] [channel2 hazırdır → yazı] [channel3 hazırdır → oxu]
          → 1 thread 10,000 bağlantını idarə edə bilər!
          → High-performance server-lər üçün ideal (Netty, Undertow bu əsasda qurulub)
```

| Xüsusiyyət | OIO (java.io) | NIO (java.nio) |
|-------------|---------------|----------------|
| Modeli | Blocking, stream | Non-blocking, channel+buffer |
| Thread modeli | Thread per connection | 1 thread, çox connection |
| API | InputStream/OutputStream | Channel/Buffer/Selector |
| İstifadə | Sadə, az bağlantı | Yüksək parallellik |
| Complexity | Sadə | Mürəkkəb |

---

## Channel

**Channel** — NIO-nun əsas I/O komponenti. OIO-nun stream-lərinə bənzər, amma:
- Həm read, həm write edə bilər (stream-lər yalnız biri)
- Non-blocking modda işləyə bilər
- Buffer-larla işləyir (birbaşa deyil)

### FileChannel

```java
import java.io.*;
import java.nio.*;
import java.nio.channels.*;
import java.nio.file.*;

public class FileChannelDemo {

    // FileChannel ilə oxumaq
    public static byte[] readWithChannel(String path) throws IOException {
        try (FileChannel channel = FileChannel.open(
                Path.of(path), StandardOpenOption.READ)) {

            long fileSize = channel.size();
            ByteBuffer buffer = ByteBuffer.allocate((int) fileSize);

            // Buffer dolunana ya da EOF olana kimi oxu
            while (buffer.hasRemaining()) {
                int bytesRead = channel.read(buffer);
                if (bytesRead == -1) break; // EOF
            }

            buffer.flip(); // write mode-dan read mode-a keç
            byte[] data = new byte[buffer.remaining()];
            buffer.get(data);
            return data;
        }
    }

    // FileChannel ilə yazmaq
    public static void writeWithChannel(String path, byte[] data) throws IOException {
        try (FileChannel channel = FileChannel.open(
                Path.of(path),
                StandardOpenOption.WRITE,
                StandardOpenOption.CREATE,
                StandardOpenOption.TRUNCATE_EXISTING)) {

            ByteBuffer buffer = ByteBuffer.wrap(data);
            while (buffer.hasRemaining()) {
                channel.write(buffer);
            }
        }
    }

    // Kanal-dan-kanala sürətli kopyalama (OS-level transfer)
    public static void fastFileCopy(String source, String dest) throws IOException {
        try (FileChannel src = FileChannel.open(Path.of(source), StandardOpenOption.READ);
             FileChannel dst = FileChannel.open(
                 Path.of(dest),
                 StandardOpenOption.WRITE,
                 StandardOpenOption.CREATE)) {

            // OS-in zero-copy mexanizmindən istifadə edir (sendfile syscall)
            // Kernel space-dən user space-ə kopyalama olmadan!
            src.transferTo(0, src.size(), dst);
        }
        System.out.println("Sürətli kopyalama tamamlandı");
    }

    // Faylda müəyyən mövqeyə yazıb-oxumaq
    public static void randomAccess(String path) throws IOException {
        try (FileChannel channel = FileChannel.open(
                Path.of(path),
                StandardOpenOption.READ,
                StandardOpenOption.WRITE,
                StandardOpenOption.CREATE)) {

            // 100-cü byte-a getmək
            channel.position(100);

            ByteBuffer buf = ByteBuffer.allocate(50);
            channel.read(buf);

            // 200-cü byte-a yazmaq
            channel.position(200);
            ByteBuffer writeBuf = ByteBuffer.wrap("HELLO".getBytes());
            channel.write(writeBuf);
        }
    }

    public static void main(String[] args) throws IOException {
        // Test
        writeWithChannel("/tmp/channel_test.txt", "NIO Channel nümunəsi".getBytes());
        byte[] data = readWithChannel("/tmp/channel_test.txt");
        System.out.println(new String(data));

        fastFileCopy("/tmp/channel_test.txt", "/tmp/channel_copy.txt");
    }
}
```

### SocketChannel / ServerSocketChannel

```java
import java.io.*;
import java.net.*;
import java.nio.*;
import java.nio.channels.*;
import java.nio.charset.*;

public class NIONetworkDemo {

    // Non-blocking NIO Server
    public static void startNIOServer(int port) throws IOException {
        ServerSocketChannel serverChannel = ServerSocketChannel.open();
        serverChannel.configureBlocking(false); // Non-blocking!
        serverChannel.bind(new InetSocketAddress(port));

        Selector selector = Selector.open();
        // ACCEPT event-lərini dinlə
        serverChannel.register(selector, SelectionKey.OP_ACCEPT);

        System.out.println("NIO Server port " + port + "-da dinləyir...");

        ByteBuffer buffer = ByteBuffer.allocate(256);

        while (true) {
            // Hazır kanalları gözlə (blocking, amma thread tut)
            selector.select();

            for (SelectionKey key : selector.selectedKeys()) {
                if (key.isAcceptable()) {
                    // Yeni bağlantı qəbul et
                    SocketChannel clientChannel = serverChannel.accept();
                    clientChannel.configureBlocking(false);
                    // READ event-lərini dinlə
                    clientChannel.register(selector, SelectionKey.OP_READ);
                    System.out.println("Yeni bağlantı: " + clientChannel.getRemoteAddress());

                } else if (key.isReadable()) {
                    // Məlumat oxumağa hazır
                    SocketChannel clientChannel = (SocketChannel) key.channel();
                    buffer.clear();
                    int bytesRead = clientChannel.read(buffer);

                    if (bytesRead == -1) {
                        // Bağlantı bağlandı
                        clientChannel.close();
                        key.cancel();
                    } else {
                        buffer.flip();
                        String message = StandardCharsets.UTF_8.decode(buffer).toString();
                        System.out.println("Alındı: " + message.trim());

                        // Echo cavab
                        buffer.rewind();
                        clientChannel.write(buffer);
                    }
                }

                // İşlənmiş key-i sil (vacib!)
                selector.selectedKeys().remove(key);
            }
        }
    }

    // NIO Client
    public static void connectNIOClient(String host, int port) throws IOException {
        try (SocketChannel channel = SocketChannel.open()) {
            channel.connect(new InetSocketAddress(host, port));

            // Sorğu göndər
            ByteBuffer sendBuf = StandardCharsets.UTF_8.encode("Salam, NIO Server!\n");
            channel.write(sendBuf);

            // Cavab al
            ByteBuffer recvBuf = ByteBuffer.allocate(256);
            channel.read(recvBuf);
            recvBuf.flip();
            System.out.println("Cavab: " + StandardCharsets.UTF_8.decode(recvBuf));
        }
    }
}
```

---

## Buffer

**Buffer** — NIO-da məlumat saxlayan konteyner. 3 əsas mövqe ilə:
- **position**: Növbəti oxuma/yazma mövqeyi
- **limit**: Oxuna/yazıla bilən son mövqe
- **capacity**: Buffer-in ümumi tutumu

```
capacity = 8
limit = 8
position = 0

Başlanğıc:
[_, _, _, _, _, _, _, _]
 ↑ position             ↑ limit/capacity

write("HELLO"):
[H, E, L, L, O, _, _, _]
              ↑ position = 5
                         ↑ limit = 8

flip() — write modundan read moduna keç:
[H, E, L, L, O, _, _, _]
 ↑ position = 0    ↑ limit = 5

read() — 3 byte oxu:
[H, E, L, L, O, _, _, _]
          ↑ position = 3 ↑ limit = 5

compact() — oxunmamış məlumatı əvvələ gətir:
[L, O, _, _, _, _, _, _]
     ↑ position = 2
                      ↑ limit = capacity = 8

clear() — sıfırla (məlumat silinmir, amma position=0, limit=capacity):
[L, O, _, _, _, _, _, _]
 ↑ position = 0         ↑ limit = capacity = 8
(Köhnə məlumat üzərinə yazılacaq)
```

```java
import java.nio.*;

public class ByteBufferDemo {

    public static void main(String[] args) {
        // === Buffer yaratmaq ===

        // Heap buffer — JVM heap-də
        ByteBuffer heapBuffer = ByteBuffer.allocate(1024);

        // Direct buffer — native OS memory-də (GC tərəfindən idarə olunmur)
        // I/O əməliyyatları üçün daha sürətli (kernel-dən user space kopyası yox)
        ByteBuffer directBuffer = ByteBuffer.allocateDirect(1024);

        // Mövcud array-dən — wrapper (kopyalama olmadan)
        byte[] array = new byte[]{1, 2, 3, 4, 5};
        ByteBuffer wrappedBuffer = ByteBuffer.wrap(array);

        // === Yazma ===
        ByteBuffer buf = ByteBuffer.allocate(16);
        buf.put((byte) 65);          // 'A' — tək byte
        buf.put("BCD".getBytes());   // byte[] kimi
        buf.putInt(42);              // 4-byte integer
        buf.putDouble(3.14);         // 8-byte double
        System.out.printf("Position: %d, Limit: %d, Capacity: %d%n",
            buf.position(), buf.limit(), buf.capacity());

        // === flip() — write → read ===
        buf.flip();
        System.out.printf("flip() sonra — Position: %d, Limit: %d%n",
            buf.position(), buf.limit());

        // === Oxuma ===
        byte a = buf.get();           // 'A'
        byte[] bcd = new byte[3];
        buf.get(bcd);                 // "BCD"
        int num = buf.getInt();       // 42
        double pi = buf.getDouble();  // 3.14

        System.out.printf("a=%c, bcd=%s, num=%d, pi=%.2f%n", a, new String(bcd), num, pi);

        // === rewind() — position=0, limit dəyişmir ===
        buf.rewind(); // Yenidən başdan oxumaq üçün
        System.out.printf("rewind() — Position: %d, Limit: %d%n",
            buf.position(), buf.limit());

        // === mark() / reset() ===
        buf.get(); // position=1
        buf.mark(); // Cari mövqeyi qeyd et
        buf.get();  // position=2
        buf.get();  // position=3
        buf.reset(); // position marklanmış yerə qayıdır (1)
        System.out.printf("reset() — Position: %d%n", buf.position()); // 1

        // === compact() — oxunmamışları əvvələ gətir ===
        ByteBuffer compactBuf = ByteBuffer.allocate(8);
        compactBuf.put(new byte[]{1, 2, 3, 4, 5});
        compactBuf.flip();
        compactBuf.get(); // 1 oxu
        compactBuf.get(); // 2 oxu
        // Oxunmamışlar: 3, 4, 5
        compactBuf.compact();
        // İndi: [3, 4, 5, _, _, _, _, _], position=3, limit=8
        System.out.printf("compact() — Position: %d, Limit: %d%n",
            compactBuf.position(), compactBuf.limit());

        // === Müxtəlif tip buffer-lər ===
        IntBuffer intBuf = IntBuffer.allocate(5);
        intBuf.put(new int[]{1, 2, 3, 4, 5});
        intBuf.flip();
        while (intBuf.hasRemaining()) {
            System.out.print(intBuf.get() + " ");
        }
        System.out.println();

        // ByteBuffer-dən digər tip görünüş
        ByteBuffer byteBuf = ByteBuffer.allocate(20);
        byteBuf.putInt(10).putInt(20).putInt(30);
        byteBuf.flip();
        IntBuffer asIntBuf = byteBuf.asIntBuffer(); // view
        System.out.println("İlk integer: " + asIntBuf.get()); // 10

        // === Endianness (byte sıralaması) ===
        ByteBuffer endianBuf = ByteBuffer.allocate(4);
        endianBuf.order(ByteOrder.LITTLE_ENDIAN); // x86 default
        endianBuf.putInt(1);
        endianBuf.flip();
        System.out.printf("Little endian: %02X %02X %02X %02X%n",
            endianBuf.get(), endianBuf.get(), endianBuf.get(), endianBuf.get());
        // 01 00 00 00
    }
}
```

---

## Selector (Multiplexing)

**Selector** — tək thread-lə çoxlu channel-ı non-blocking idarə etmək üçün.

```java
import java.io.*;
import java.net.*;
import java.nio.*;
import java.nio.channels.*;
import java.util.*;

public class SelectorDemo {

    // Selector ilə multi-client echo server
    public static void echoServer(int port) throws IOException {
        // Selector yarat
        Selector selector = Selector.open();

        // Server socket channel yarat
        ServerSocketChannel serverChannel = ServerSocketChannel.open();
        serverChannel.configureBlocking(false); // Non-blocking vacibdir!
        serverChannel.bind(new InetSocketAddress(port));

        // Server channel-ı selector-a qeydiyyat et
        serverChannel.register(selector, SelectionKey.OP_ACCEPT);

        System.out.println("Echo Server port " + port + "-da başladı");

        while (!Thread.interrupted()) {
            // Hazır kanallar üçün gözlə
            // 0 qayıdırsa — seçilmiş key yoxdur (timeout ya da wakeup)
            if (selector.select(1000) == 0) continue;

            Set<SelectionKey> selectedKeys = selector.selectedKeys();
            Iterator<SelectionKey> keyIterator = selectedKeys.iterator();

            while (keyIterator.hasNext()) {
                SelectionKey key = keyIterator.next();
                keyIterator.remove(); // KEY-İ SİL (vacib!)

                try {
                    if (key.isAcceptable()) {
                        handleAccept(key, selector);
                    } else if (key.isReadable()) {
                        handleRead(key);
                    } else if (key.isWritable()) {
                        handleWrite(key);
                    }
                } catch (IOException e) {
                    System.err.println("Channel xətası: " + e.getMessage());
                    key.cancel();
                    key.channel().close();
                }
            }
        }

        selector.close();
        serverChannel.close();
    }

    private static void handleAccept(SelectionKey key, Selector selector) throws IOException {
        ServerSocketChannel serverChannel = (ServerSocketChannel) key.channel();
        SocketChannel clientChannel = serverChannel.accept();
        clientChannel.configureBlocking(false);

        // Məlumat attachment (state saxlamaq üçün)
        ByteBuffer buffer = ByteBuffer.allocate(256);

        // READ event-ini dinlə, buffer-i attachment kimi qoy
        clientChannel.register(selector, SelectionKey.OP_READ, buffer);
        System.out.println("Yeni müştəri: " + clientChannel.getRemoteAddress());
    }

    private static void handleRead(SelectionKey key) throws IOException {
        SocketChannel channel = (SocketChannel) key.channel();
        ByteBuffer buffer = (ByteBuffer) key.attachment();

        buffer.clear();
        int bytesRead = channel.read(buffer);

        if (bytesRead == -1) {
            System.out.println("Müştəri bağlandı");
            channel.close();
            key.cancel();
            return;
        }

        buffer.flip();
        // Echo: gələni geri göndər
        // WRITE event-inə keç
        key.interestOps(SelectionKey.OP_WRITE);
    }

    private static void handleWrite(SelectionKey key) throws IOException {
        SocketChannel channel = (SocketChannel) key.channel();
        ByteBuffer buffer = (ByteBuffer) key.attachment();

        channel.write(buffer);

        if (!buffer.hasRemaining()) {
            // Bütün data göndərildi → READ-ə qayıt
            key.interestOps(SelectionKey.OP_READ);
        }
    }

    // SelectionKey event-ləri
    /*
     * OP_ACCEPT  = 16 — ServerSocketChannel yeni bağlantı qəbul etməyə hazır
     * OP_CONNECT = 8  — SocketChannel bağlantı qurmağa hazır (client)
     * OP_READ    = 1  — Channel oxumağa hazır
     * OP_WRITE   = 4  — Channel yazmağa hazır
     *
     * Bir neçəsini birlikdə qeyd etmək:
     * channel.register(selector, SelectionKey.OP_READ | SelectionKey.OP_WRITE);
     */

    public static void main(String[] args) throws IOException {
        // Server-i ayrı thread-də başlat (demo üçün)
        Thread serverThread = new Thread(() -> {
            try {
                echoServer(8080);
            } catch (IOException e) {
                e.printStackTrace();
            }
        });
        serverThread.setDaemon(true);
        serverThread.start();

        System.out.println("Server başladı. Test etmək üçün: telnet localhost 8080");
        try {
            Thread.sleep(10000); // 10 saniyə gözlə
        } catch (InterruptedException e) {
            Thread.currentThread().interrupt();
        }
    }
}
```

---

## Memory-mapped Files

**Memory-mapped files** — faylı virtual yaddaşa map etmək. Çox böyük faylları sürətli emal etmək üçün.

```java
import java.io.*;
import java.nio.*;
import java.nio.channels.*;
import java.nio.file.*;

public class MemoryMappedFileDemo {

    // Faylı memory-map et
    public static void memoryMappedRead(String path) throws IOException {
        try (FileChannel channel = FileChannel.open(Path.of(path), StandardOpenOption.READ)) {

            // Faylı virtual memory-yə map et
            MappedByteBuffer mappedBuffer = channel.map(
                FileChannel.MapMode.READ_ONLY, // Oxuma rejimi
                0,                              // Başlanğıc offset
                channel.size()                 // Map ediləcək ölçü
            );

            // Artıq buffer RAM-da fayl kimi görünür
            // OS paging (demand paging) ilə lazım olan hissəni yükləyir
            System.out.println("Fayl ölçüsü: " + mappedBuffer.capacity() + " bytes");

            // Birinci 100 byte-ı oxu
            byte[] first100 = new byte[Math.min(100, mappedBuffer.capacity())];
            mappedBuffer.get(first100);
            System.out.println("İlk 100 byte: " + new String(first100));
        }
    }

    // Memory-mapped fayl yazma
    public static void memoryMappedWrite(String path, String content) throws IOException {
        byte[] bytes = content.getBytes();

        try (FileChannel channel = FileChannel.open(
                Path.of(path),
                StandardOpenOption.READ,
                StandardOpenOption.WRITE,
                StandardOpenOption.CREATE)) {

            // READ_WRITE mode
            MappedByteBuffer mappedBuffer = channel.map(
                FileChannel.MapMode.READ_WRITE,
                0,
                bytes.length
            );

            mappedBuffer.put(bytes);
            mappedBuffer.force(); // Yaddaşdakı dəyişiklikləri faylqa köçür (flush)
        }
    }

    // Böyük fayllarda axtarış (memory-mapped ilə çox sürətli)
    public static long searchInFile(String path, byte[] pattern) throws IOException {
        try (FileChannel channel = FileChannel.open(Path.of(path), StandardOpenOption.READ)) {

            MappedByteBuffer buffer = channel.map(
                FileChannel.MapMode.READ_ONLY, 0, channel.size());

            // Boyer-Moore ya da sadə axtarış
            outer:
            for (int i = 0; i <= buffer.limit() - pattern.length; i++) {
                for (int j = 0; j < pattern.length; j++) {
                    if (buffer.get(i + j) != pattern[j]) continue outer;
                }
                return i; // Pattern-in mövqeyi
            }
            return -1; // Tapılmadı
        }
    }

    // Shared memory — iki proses arasında (eyni fayl-ı map et)
    public static void sharedMemoryDemo(String path) throws IOException {
        // Birinci proses yazdı, ikinci proses oxudu
        // (Eyni faylın müxtəlif proses tərəfindən map edilməsi)
        // Bu inter-process communication (IPC) üçün istifadə oluna bilər!

        try (FileChannel channel = FileChannel.open(
                Path.of(path),
                StandardOpenOption.READ,
                StandardOpenOption.WRITE,
                StandardOpenOption.CREATE)) {

            MappedByteBuffer buffer = channel.map(
                FileChannel.MapMode.READ_WRITE, 0, 4096);

            // Bir proses yazır
            buffer.putInt(0, 42); // offset 0-da 42 yaz

            // Digər proses oxuyur (eyni faylı map etmişsə)
            int value = buffer.getInt(0); // 42 alır
            System.out.println("Paylaşılan yaddaşdan: " + value);
        }
    }

    public static void main(String[] args) throws IOException {
        // Test faylı yarat
        String testPath = "/tmp/mapped_test.txt";
        memoryMappedWrite(testPath, "NIO Memory Mapped File nümunəsi!");
        memoryMappedRead(testPath);

        long pos = searchInFile(testPath, "Memory".getBytes());
        System.out.println("'Memory' mövqeyi: " + pos);
    }
}
```

---

## AsynchronousFileChannel

**AsynchronousFileChannel** — tam asinxron fayl I/O (Java 7+ NIO.2).

```java
import java.io.*;
import java.nio.*;
import java.nio.channels.*;
import java.nio.file.*;
import java.util.concurrent.*;

public class AsyncFileChannelDemo {

    // Future-based asinxron oxuma
    public static void asyncReadWithFuture(String path) throws Exception {
        try (AsynchronousFileChannel channel = AsynchronousFileChannel.open(
                Path.of(path), StandardOpenOption.READ)) {

            ByteBuffer buffer = ByteBuffer.allocate((int) channel.size());

            // Asinxron oxuma — dərhal qayıdır, Future qaytarır
            Future<Integer> future = channel.read(buffer, 0);

            // Əl işi gör (I/O gözləyərkən)
            System.out.println("I/O gözlənilir... başqa işlər görə bilərəm");

            // Nəticəni gözlə
            int bytesRead = future.get(); // Bloklayır — nəticə hazır olana kimi
            buffer.flip();
            System.out.println("Oxundu (" + bytesRead + " bytes): "
                + new String(buffer.array(), 0, bytesRead));
        }
    }

    // CompletionHandler-based asinxron oxuma (callback style)
    public static void asyncReadWithCallback(String path) throws Exception {
        AsynchronousFileChannel channel = AsynchronousFileChannel.open(
            Path.of(path), StandardOpenOption.READ);

        ByteBuffer buffer = ByteBuffer.allocate(1024);
        CountDownLatch latch = new CountDownLatch(1);

        // CompletionHandler — asinxron callback
        channel.read(buffer, 0, buffer, new CompletionHandler<Integer, ByteBuffer>() {

            @Override
            public void completed(Integer result, ByteBuffer attachment) {
                // Uğurlu oxuma
                attachment.flip();
                byte[] data = new byte[attachment.remaining()];
                attachment.get(data);
                System.out.println("Callback: " + new String(data));

                try { channel.close(); }
                catch (IOException e) { e.printStackTrace(); }
                latch.countDown();
            }

            @Override
            public void failed(Throwable exc, ByteBuffer attachment) {
                // Xəta
                System.err.println("Xəta: " + exc.getMessage());
                latch.countDown();
            }
        });

        System.out.println("Asinxron oxuma başladı, callback gözlənilir...");
        latch.await(); // Callback bitənə kimi gözlə
    }

    // Asinxron yazma
    public static void asyncWrite(String path, String content) throws Exception {
        try (AsynchronousFileChannel channel = AsynchronousFileChannel.open(
                Path.of(path),
                StandardOpenOption.WRITE,
                StandardOpenOption.CREATE)) {

            ByteBuffer buffer = ByteBuffer.wrap(content.getBytes());

            // CompletableFuture ilə daha müasir yanaşma
            CompletableFuture<Integer> cf = new CompletableFuture<>();

            channel.write(buffer, 0, null, new CompletionHandler<Integer, Void>() {
                @Override
                public void completed(Integer result, Void attachment) {
                    cf.complete(result);
                }

                @Override
                public void failed(Throwable exc, Void attachment) {
                    cf.completeExceptionally(exc);
                }
            });

            int written = cf.get();
            System.out.println("Yazıldı: " + written + " bytes");
        }
    }

    // NIO Thread Pool (AsynchronousChannelGroup)
    public static void customThreadPool() throws Exception {
        // Öz thread pool-u ilə async channel group yarat
        ExecutorService executor = Executors.newFixedThreadPool(4);
        AsynchronousChannelGroup group = AsynchronousChannelGroup.withThreadPool(executor);

        // Bu group-u channel-a ver
        try (AsynchronousFileChannel channel = AsynchronousFileChannel.open(
                Path.of("/tmp/async_test.txt"),
                new HashSet<>(java.util.List.of(StandardOpenOption.READ)),
                group)) {

            ByteBuffer buf = ByteBuffer.allocate(100);
            Future<Integer> f = channel.read(buf, 0);
            System.out.println("Oxundu: " + f.get() + " bytes");
        } finally {
            group.shutdown();
        }
    }

    public static void main(String[] args) throws Exception {
        // Test faylı yarat
        Files.writeString(Path.of("/tmp/async_test.txt"),
            "Asinxron NIO test məzmunu");

        asyncReadWithFuture("/tmp/async_test.txt");
        asyncReadWithCallback("/tmp/async_test.txt");
        asyncWrite("/tmp/async_written.txt", "Asinxron yazıldı!");
    }
}
```

---

## İntervyu Sualları

**S: NIO ilə OIO arasındakı əsas fərq nədir?**
C: OIO blocking-dir — `read()` data gələnə kimi thread-i blokir. Hər bağlantı üçün ayrı thread lazımdır. NIO non-blocking — `read()` dərhal qayıdır, data yoxsa 0 qaytarır. Selector sayəsində 1 thread minlərlə bağlantını idarə edə bilər.

**S: ByteBuffer-in `flip()`, `clear()`, `compact()` fərqləri?**
C: `flip()`: yazma rejimindən oxuma rejiminə keçir (limit=position, position=0). `clear()`: buffer-i sıfırlar (position=0, limit=capacity) — köhnə data silinmir, üzərinə yazılır. `compact()`: oxunmamış dataları başa gətirir, yeni yazma üçün yer açır.

**S: Direct buffer nə vaxt istifadə etmək lazımdır?**
C: `ByteBuffer.allocateDirect()` — native OS memory-də ayırır. I/O əməliyyatları üçün daha sürətli (kernel space-dən user space kopyası yox). Amma: GC tərəfindən idarə olunmur, `allocate()` üçündən yavaş. Böyük, uzunömürlü I/O buffer-lər üçün (server tətbiqlər).

**S: Selector necə işləyir?**
C: Bir neçə channel-ı Selector-a register edirsən (OP_READ, OP_WRITE, OP_ACCEPT). `selector.select()` hazır olan channel-ları gözləyir, hazır olanlar `selectedKeys()` set-inə düşür. Tək thread bu set-i iterasiya edib hər channel-ı ayrıca işləyir.

**S: Memory-mapped file nə üstünlük verir?**
C: Fayl virtual adres fəzasına map edilir. OS demand paging ilə yalnız ehtiyac olan hissəni RAM-a yükləyir. 1GB faylda axtarış etmək istəyirsənsə, hamısını yükləmək lazım deyil. Shared memory (IPC) üçün istifadə oluna bilər.

**S: `FileChannel.transferTo()` niyə sürətlidır?**
C: `transferTo()` OS-in `sendfile()` (Linux) ya da `TransmitFile()` (Windows) sistem çağırışından istifadə edir. Bu **zero-copy** texnikasıdır: məlumat kernel-dən user space-ə kopyalanmır, birbaşa kernel space içindəki source-dan destination-a köçürülür. CPU və yaddaş overhead minimuma enir.

**S: AsynchronousFileChannel vs Future vs CompletionHandler?**
C: Hər ikisi `AsynchronousFileChannel`-in read/write metodlarının variantıdır. `Future`: `channel.read(buf, pos)` — geri Future<Integer> qaytarır, `future.get()` ilə nəticəni gözləyirsən (bloklayır). `CompletionHandler`: callback-based — `completed(result, attachment)` asinxron çağırılır, thread-i bloklamır.
