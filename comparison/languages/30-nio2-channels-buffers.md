# NIO.2, Channels və Buffers (Java NIO vs PHP Streams)

## Giriş

İ/O (input/output) — disk oxumaq, socket-ə yazmaq, şəbəkədən cavab almaq — hər proqramın əsas işlərindən biridir. Bloklayan I/O sadədir, amma minlərlə əlaqə saxlamaq üçün baha başa gəlir. Bloklanmayan (non-blocking) və asinxron I/O bu problemi həll edir.

**Java**-da üç nəsil I/O var:
- **IO (1.0)** — `InputStream`, `OutputStream` — bloklayan, byte-byte.
- **NIO (Java 1.4, 2002)** — `Channel`, `Buffer`, `Selector` — non-blocking, multiplexed.
- **NIO.2 (Java 7, 2011)** — `Path`, `Files`, `WatchService`, async channels — müasir, daha rahat API.

Netty, Tomcat NIO connector, Micronaut, Vert.x — hamısı NIO üzərində qurulub.

**PHP**-də I/O birləşmiş bir "stream" abstraksiyası ilə idarə olunur: `fopen`, `fread`, `fwrite`, `stream_socket_server`, `stream_select`. Asinxron I/O üçün **ReactPHP**, **Amp**, **Swoole**, **Revolt** kimi kitabxanalar var. PHP-nin öz runtime-ı (PHP-FPM) hər sorğunu bloklayır — async yalnız xüsusi runtime-larda işləyir.

---

## Java-da istifadəsi

### 1) `ByteBuffer` — əsas tikinti bloku

NIO-nun mərkəzində `Buffer`-lər durur. `ByteBuffer` — fix ölçülü byte konteyneridir. `position`, `limit`, `capacity` və `mark` atribut var.

```java
// Heap buffer — JVM heap-dində
ByteBuffer heap = ByteBuffer.allocate(1024);

// Direct buffer — OS-un native yaddaşında, JVM heap-dən kənarda
ByteBuffer direct = ByteBuffer.allocateDirect(1024);

// Fərq:
// - heap buffer: sürətli allocate, amma OS-a köçürmə tələb edir
// - direct buffer: yavaş allocate, amma OS-a birbaşa çata bilir (zero-copy)
// - böyük I/O üçün direct, kiçik və müvəqqəti üçün heap

heap.putInt(42);                 // position = 4
heap.put((byte) 0x0A);           // position = 5
heap.flip();                     // oxumağa hazır: limit=5, position=0

int value = heap.getInt();       // position = 4
byte b = heap.get();             // position = 5

heap.clear();                    // yenidən yazmağa hazır
heap.compact();                  // oxunmamış hissəni başa köçür
```

**Byte order (endianness):**

```java
ByteBuffer buf = ByteBuffer.allocate(8)
    .order(ByteOrder.LITTLE_ENDIAN);    // şəbəkə protokolları üçün
buf.putLong(0x123456789ABCDEFL);
```

### 2) `FileChannel` — fayllar üçün

```java
Path path = Path.of("data.bin");

// Oxu
try (FileChannel ch = FileChannel.open(path, StandardOpenOption.READ)) {
    ByteBuffer buf = ByteBuffer.allocate(4096);
    while (ch.read(buf) != -1) {
        buf.flip();
        while (buf.hasRemaining()) {
            System.out.print((char) buf.get());
        }
        buf.clear();
    }
}

// Zero-copy fayl köçürməsi
try (FileChannel src = FileChannel.open(Path.of("in.bin"),  StandardOpenOption.READ);
     FileChannel dst = FileChannel.open(Path.of("out.bin"), StandardOpenOption.WRITE, StandardOpenOption.CREATE)) {

    long size = src.size();
    long transferred = 0;
    while (transferred < size) {
        transferred += src.transferTo(transferred, size - transferred, dst);
    }
    // transferTo() OS-dan sendfile() syscall-ını çağırır — heç kaçır heç kopirovka
}
```

### 3) Memory-mapped fayllar — `MappedByteBuffer`

```java
// Fayl OS yaddaşına map olunur
try (FileChannel ch = FileChannel.open(Path.of("huge.dat"),
        StandardOpenOption.READ, StandardOpenOption.WRITE)) {

    long size = ch.size();
    MappedByteBuffer mmap = ch.map(FileChannel.MapMode.READ_WRITE, 0, size);

    // İndi fayla birbaşa yaddaş kimi çatırıq
    mmap.put(0, (byte) 0xFF);
    mmap.putInt(100, 42);

    mmap.force();    // diskə yaz (fsync)
}

// Java 14+ — Foreign Memory ilə yeni yanaşma mövcuddur (bax 31-ci fayl)
```

Memory-mapped faylların faydası: 100 GB faylı RAM-a sığmadan oxumaq olar — OS səhifələri lazım olduqda yükləyir.

### 4) `SocketChannel` və `ServerSocketChannel`

```java
// Server tərəfi
ServerSocketChannel server = ServerSocketChannel.open();
server.bind(new InetSocketAddress(8080));
server.configureBlocking(false);         // non-blocking rejimi

// Client tərəfi
SocketChannel client = SocketChannel.open();
client.configureBlocking(false);
client.connect(new InetSocketAddress("example.com", 80));

while (!client.finishConnect()) {
    // başqa iş gör
}

ByteBuffer req = ByteBuffer.wrap("GET / HTTP/1.1\r\nHost: example.com\r\n\r\n".getBytes());
while (req.hasRemaining()) {
    client.write(req);
}

ByteBuffer resp = ByteBuffer.allocate(4096);
int read = client.read(resp);
```

### 5) `Selector` — çoxlu kanalı bir thread-də idarə et

NIO-nun super gücü: bir thread minlərlə socket-ə baxa bilər.

```java
Selector selector = Selector.open();

ServerSocketChannel server = ServerSocketChannel.open();
server.bind(new InetSocketAddress(8080));
server.configureBlocking(false);
server.register(selector, SelectionKey.OP_ACCEPT);

while (true) {
    selector.select();                   // bloklayır, hadisə gözləyir
    Iterator<SelectionKey> iter = selector.selectedKeys().iterator();

    while (iter.hasNext()) {
        SelectionKey key = iter.next();
        iter.remove();

        if (key.isAcceptable()) {
            // Yeni client
            SocketChannel client = server.accept();
            client.configureBlocking(false);
            client.register(selector, SelectionKey.OP_READ);

        } else if (key.isReadable()) {
            SocketChannel client = (SocketChannel) key.channel();
            ByteBuffer buf = ByteBuffer.allocate(1024);
            int n = client.read(buf);
            if (n == -1) {
                client.close();
            } else {
                buf.flip();
                // oxunan datanı emal et
                key.interestOps(SelectionKey.OP_WRITE);
            }

        } else if (key.isWritable()) {
            SocketChannel client = (SocketChannel) key.channel();
            ByteBuffer resp = ByteBuffer.wrap("HTTP/1.1 200 OK\r\n\r\nOK".getBytes());
            client.write(resp);
            key.interestOps(SelectionKey.OP_READ);
        }
    }
}
```

**`SelectionKey` flag-ləri:**

- `OP_ACCEPT` — server yeni əlaqə qəbul etməyə hazırdır
- `OP_CONNECT` — client TCP əl görüşü bitirdi
- `OP_READ` — oxumaq üçün data var
- `OP_WRITE` — yazmaq üçün buffer boşdur

### 6) `AsynchronousFileChannel` və `AsynchronousServerSocketChannel`

NIO.2-də callback/Future əsaslı API gəldi:

```java
Path path = Path.of("large.log");

AsynchronousFileChannel async = AsynchronousFileChannel.open(
    path, StandardOpenOption.READ);

ByteBuffer buf = ByteBuffer.allocate(1024);

// Future ilə
Future<Integer> future = async.read(buf, 0);
int bytesRead = future.get();

// CompletionHandler ilə
async.read(buf, 0, null, new CompletionHandler<Integer, Void>() {
    @Override
    public void completed(Integer result, Void attachment) {
        buf.flip();
        // emal et
    }
    @Override
    public void failed(Throwable exc, Void attachment) {
        exc.printStackTrace();
    }
});
```

Async server:

```java
AsynchronousServerSocketChannel server = AsynchronousServerSocketChannel.open();
server.bind(new InetSocketAddress(8080));

server.accept(null, new CompletionHandler<AsynchronousSocketChannel, Object>() {
    @Override
    public void completed(AsynchronousSocketChannel client, Object att) {
        server.accept(null, this);       // növbətini qəbul et

        ByteBuffer buf = ByteBuffer.allocate(1024);
        client.read(buf, buf, new CompletionHandler<Integer, ByteBuffer>() {
            @Override
            public void completed(Integer n, ByteBuffer b) {
                b.flip();
                client.write(ByteBuffer.wrap("OK".getBytes()));
            }
            @Override
            public void failed(Throwable ex, ByteBuffer b) {}
        });
    }
    @Override
    public void failed(Throwable ex, Object att) {}
});
```

### 7) NIO.2 `Path`, `Files`, `Paths`

```java
// Path — fayl yolunun obyekti
Path p = Path.of("/home/user/data.txt");
Path rel = Path.of("logs", "app.log");
Path abs = p.toAbsolutePath().normalize();

// Files — statik util
boolean exists = Files.exists(p);
long size = Files.size(p);
String content = Files.readString(p, StandardCharsets.UTF_8);
List<String> lines = Files.readAllLines(p);

Files.writeString(p, "salam\n", StandardOpenOption.APPEND);
Files.copy(src, dst, StandardCopyOption.REPLACE_EXISTING);
Files.delete(p);

// Stream API ilə böyük faylı oxu
try (Stream<String> stream = Files.lines(p)) {
    stream.filter(line -> line.contains("ERROR"))
          .forEach(System.out::println);
}

// Qovluğu recursive gəz
try (Stream<Path> walk = Files.walk(Path.of("/var/log"))) {
    walk.filter(Files::isRegularFile)
        .filter(path -> path.toString().endsWith(".log"))
        .forEach(System.out::println);
}
```

### 8) `WatchService` — fayl sistem hadisələri

```java
WatchService ws = FileSystems.getDefault().newWatchService();
Path dir = Path.of("/tmp/uploads");
dir.register(ws,
    StandardWatchEventKinds.ENTRY_CREATE,
    StandardWatchEventKinds.ENTRY_MODIFY,
    StandardWatchEventKinds.ENTRY_DELETE);

while (true) {
    WatchKey key = ws.take();     // bloklayır
    for (WatchEvent<?> event : key.pollEvents()) {
        Path changed = (Path) event.context();
        System.out.println(event.kind() + ": " + changed);
    }
    if (!key.reset()) break;
}
```

Linux-da `inotify`, macOS-da `FSEvents`, Windows-da `ReadDirectoryChangesW` altda işləyir.

### 9) Netty və NIO

**Netty** NIO üzərində qurulmuş yüksək performanslı framework-dur. Real web server-lər (gRPC, HTTP/2, WebSocket) Netty-dən istifadə edir.

```java
EventLoopGroup boss = new NioEventLoopGroup(1);
EventLoopGroup worker = new NioEventLoopGroup();

try {
    ServerBootstrap b = new ServerBootstrap();
    b.group(boss, worker)
     .channel(NioServerSocketChannel.class)
     .childHandler(new ChannelInitializer<SocketChannel>() {
        @Override
        protected void initChannel(SocketChannel ch) {
            ch.pipeline()
              .addLast(new HttpServerCodec())
              .addLast(new HttpObjectAggregator(64 * 1024))
              .addLast(new MyHandler());
        }
     });

    ChannelFuture f = b.bind(8080).sync();
    f.channel().closeFuture().sync();
} finally {
    boss.shutdownGracefully();
    worker.shutdownGracefully();
}
```

Netty hər `Channel`-i bir `EventLoop`-a (NIO `Selector`) bağlayır — thread başına 10 000+ connection.

### 10) Virtual Thread + NIO (Java 21+)

Java 21-dən sonra `InputStream.read()` Virtual Thread-də "sehrli" şəkildə non-blocking olur. Ona görə bəzi yeni kod JDK 21+ üçün sadə bloklayan IO-ya qayıtdı:

```java
// JDK 21+: sadə kod, amma milyonlarla virtual thread işləyir
try (var executor = Executors.newVirtualThreadPerTaskExecutor()) {
    while (true) {
        Socket client = serverSocket.accept();
        executor.submit(() -> handleClient(client));
    }
}

void handleClient(Socket s) throws IOException {
    try (var in = s.getInputStream(); var out = s.getOutputStream()) {
        byte[] buf = new byte[1024];
        int n = in.read(buf);         // bloklayır, amma virtual thread
        out.write(buf, 0, n);
    }
}
```

### 11) NIO vs blocking `InputStream`

```java
// Bloklayan (sadə, amma thread-bahalı)
try (InputStream in = socket.getInputStream()) {
    byte[] buf = new byte[1024];
    int n;
    while ((n = in.read(buf)) != -1) {
        process(buf, n);
    }
}

// NIO (mürəkkəb, amma scale edir)
SocketChannel ch = ...;
ch.configureBlocking(false);
ByteBuffer buf = ByteBuffer.allocateDirect(1024);
while (ch.read(buf) > 0) {
    buf.flip();
    process(buf);
    buf.clear();
}
```

---

## PHP-də istifadəsi

### 1) Əsas stream-lər — `fopen`, `fread`, `fwrite`

```php
<?php
$handle = fopen('data.txt', 'r');

while (!feof($handle)) {
    $chunk = fread($handle, 4096);
    echo $chunk;
}

fclose($handle);

// Yaz
$out = fopen('out.txt', 'w');
fwrite($out, "salam\n");
fclose($out);

// file_get_contents — sadə, amma bütün faylı yaddaşa yükləyir
$content = file_get_contents('data.txt');

// SplFileObject — OOP interface
$file = new SplFileObject('data.txt');
foreach ($file as $line) {
    echo $line;
}
```

### 2) Socket stream — TCP server/client

```php
<?php
// Server
$server = stream_socket_server('tcp://0.0.0.0:8080', $errno, $errstr);
if (!$server) {
    throw new RuntimeException("$errstr ($errno)");
}

while ($client = stream_socket_accept($server, -1)) {
    $data = fread($client, 1024);
    fwrite($client, "HTTP/1.1 200 OK\r\n\r\nsalam\n");
    fclose($client);
}
// Bu kod bloklayandır — bir vaxtda bir client

// Client
$sock = stream_socket_client('tcp://example.com:80', $errno, $errstr, 5);
fwrite($sock, "GET / HTTP/1.1\r\nHost: example.com\r\n\r\n");
$response = stream_get_contents($sock);
fclose($sock);
```

### 3) Non-blocking və `stream_select`

```php
<?php
$server = stream_socket_server('tcp://0.0.0.0:8080');
stream_set_blocking($server, false);

$clients = [];

while (true) {
    $read = array_merge([$server], $clients);
    $write = null;
    $except = null;

    // stream_select — Java Selector-un analogudur
    $n = stream_select($read, $write, $except, 1);   // 1 saniyə timeout
    if ($n === false) break;

    foreach ($read as $stream) {
        if ($stream === $server) {
            $client = stream_socket_accept($server, 0);
            stream_set_blocking($client, false);
            $clients[] = $client;
        } else {
            $data = fread($stream, 1024);
            if ($data === '' || $data === false) {
                fclose($stream);
                $clients = array_filter($clients, fn($c) => $c !== $stream);
            } else {
                fwrite($stream, "echo: $data");
            }
        }
    }
}
```

`stream_select()` POSIX `select()` sistem çağırışını istifadə edir — bu, Java `Selector`-un oxşarıdır, amma daha primitivdir.

### 4) ReactPHP — event loop

ReactPHP PHP-yə asinxron event loop gətirən kitabxanadır. Altında `stream_select`, `ext-event`, və ya `ext-uv` istifadə edir.

```php
<?php
use React\EventLoop\Loop;
use React\Socket\SocketServer;
use React\Socket\ConnectionInterface;

$server = new SocketServer('0.0.0.0:8080');

$server->on('connection', function (ConnectionInterface $conn) {
    echo "Yeni connection: " . $conn->getRemoteAddress() . "\n";

    $conn->on('data', function ($data) use ($conn) {
        $conn->write("echo: $data");
    });

    $conn->on('close', function () {
        echo "Qapandı\n";
    });
});

Loop::addPeriodicTimer(5, function () {
    echo "5 saniyə keçdi\n";
});

// Loop::run() ReactPHP-da avtomatik çağırılır
```

ReactPHP ilə HTTP client:

```php
<?php
use React\Http\Browser;

$browser = new Browser();

$browser->get('https://api.example.com/users')
    ->then(
        function (Psr\Http\Message\ResponseInterface $response) {
            echo (string) $response->getBody();
        },
        function (Throwable $e) {
            echo "Xəta: " . $e->getMessage();
        }
    );
```

### 5) Amphp (Revolt EventLoop)

PHP 8.1+ **Fibers**-dən sonra Amphp v3 daha rahat oldu. Revolt EventLoop Amp + ReactPHP üçün ortaq abstraksiyadır.

```php
<?php
use Amp\ByteStream;
use Amp\Socket;

use function Amp\async;
use function Amp\Future\await;

$server = Socket\listen('0.0.0.0:8080');

while ($client = $server->accept()) {
    async(function () use ($client) {
        // Fiber içində, sinxron görünən amma async olan kod
        $data = ByteStream\buffer($client);
        $client->write("echo: $data");
        $client->close();
    });
}
```

### 6) Swoole — C coroutine runtime

Swoole PHP üçün C-də yazılmış extension-dur. Öz event loop-u və coroutine sistemi var.

```php
<?php
use Swoole\Http\Server;

$server = new Server('0.0.0.0', 8080);

$server->set([
    'worker_num' => 4,
    'task_worker_num' => 2,
    'hook_flags' => SWOOLE_HOOK_ALL,    // `fread`, `curl`-u avtomatik async et
]);

$server->on('request', function ($request, $response) {
    // Bu kod bloklayan görünür, amma Swoole onu coroutine-də işlədir
    $data = file_get_contents('https://api.example.com/user');
    $response->end($data);
});

$server->start();
```

Swoole ilə TCP server:

```php
<?php
use Swoole\Coroutine\Server;
use Swoole\Coroutine\Server\Connection;

use function Swoole\Coroutine\run;

run(function () {
    $server = new Server('0.0.0.0', 8080);
    $server->handle(function (Connection $conn) {
        while (true) {
            $data = $conn->recv();
            if ($data === '' || $data === false) {
                break;
            }
            $conn->send("echo: $data");
        }
    });
    $server->start();
});
```

### 7) `fread` chunked, `stream_copy_to_stream`

```php
<?php
// Böyük fayl köçürməsi (zero-copy deyil, amma effektiv)
$in  = fopen('big.bin', 'rb');
$out = fopen('copy.bin', 'wb');

stream_copy_to_stream($in, $out);

fclose($in);
fclose($out);

// Generator ilə memory-efficient oxu
function readLines(string $path): Generator {
    $h = fopen($path, 'r');
    while (!feof($h)) {
        yield fgets($h);
    }
    fclose($h);
}

foreach (readLines('huge.log') as $line) {
    if (str_contains($line, 'ERROR')) {
        echo $line;
    }
}
```

### 8) Stream wrapper və context

```php
<?php
// HTTP context ilə oxu
$context = stream_context_create([
    'http' => [
        'method'  => 'GET',
        'header'  => "User-Agent: MyApp/1.0\r\n",
        'timeout' => 5,
    ],
]);

$content = file_get_contents('https://api.example.com', false, $context);

// Custom stream wrapper (qabaqcıl istifadə)
class Base64StreamWrapper {
    private $position = 0;
    private $buffer = '';

    public function stream_open($path, $mode, $options, &$opened_path) {
        return true;
    }
    public function stream_read($count) { /* ... */ }
    public function stream_eof() { /* ... */ }
}

stream_wrapper_register('base64', Base64StreamWrapper::class);
file_get_contents('base64://encoded_data');
```

### 9) Memory-mapped fayllar?

PHP-də tam `mmap` ekvivalenti yoxdur. Bir neçə yaxın variant:

```php
<?php
// shmop — Shared Memory
$shmid = shmop_open(0xff3, 'c', 0644, 1024);
shmop_write($shmid, 'salam', 0);
$data = shmop_read($shmid, 0, 5);
shmop_close($shmid);

// ext-ffi ilə mmap syscall-ı (qeyri-standart)
$ffi = FFI::cdef("
    void* mmap(void*, size_t, int, int, int, long);
    int munmap(void*, size_t);
", "libc.so.6");
// tam kod çox uzun, amma mümkündür
```

Real istifadədə PHP memory-mapped fayllar əvəzinə OPcache + Redis istifadə edir.

### 10) PHP-FPM və async məhdudiyyəti

```text
PHP-FPM modeli:
1. Nginx → FPM-ə sorğu göndərir
2. FPM free worker tapır
3. Worker PHP scripti bir bloklayan thread-də işlədir
4. Cavab qayıdır, worker free olur

Problem: worker tamamilə bloklanır. `sleep(5)` 5 saniyə bu worker-i öldürür.
Həlli: Swoole/RoadRunner/Octane — long-running worker + coroutine.
```

Laravel Octane (Swoole/FrankenPHP) async imkanları açır:

```php
<?php
// Laravel Octane
use Illuminate\Support\Facades\Concurrency;

$results = Concurrency::run([
    fn() => User::count(),
    fn() => Order::sum('total'),
    fn() => Invoice::where('status', 'pending')->count(),
]);
// Üç query paralel işləyir (FrankenPHP/Swoole-da)
```

### 11) `pcntl_signal` — async signals

```php
<?php
pcntl_async_signals(true);

pcntl_signal(SIGTERM, function ($signo) {
    echo "SIGTERM alındı, graceful shutdown...\n";
    exit(0);
});

pcntl_signal(SIGUSR1, function ($signo) {
    echo "Config reload...\n";
});

// Long-running loop
while (true) {
    doWork();
    sleep(1);
}
```

---

## Əsas fərqlər

| Aspekt | Java NIO/NIO.2 | PHP Streams |
|---|---|---|
| Əsas abstraksiya | `Channel`, `Buffer`, `Selector` | Stream resource (`fopen` ilə) |
| Non-blocking I/O | Dildə built-in (`configureBlocking(false)`) | `stream_set_blocking(false)` |
| I/O multiplexing | `Selector` (epoll/kqueue) | `stream_select` (select) |
| Async file I/O | `AsynchronousFileChannel` | Yoxdur (ReactPHP, Swoole ilə) |
| Zero-copy | `FileChannel.transferTo()` (sendfile) | Yoxdur default, Swoole dəstəkləyir |
| Memory-mapped files | `MappedByteBuffer` | Yoxdur (shmop məhduddur) |
| Direct buffers | `ByteBuffer.allocateDirect()` | Yoxdur |
| File watch | `WatchService` (inotify) | `inotify_*` funksiyaları (PECL) |
| Coroutine inteqrasiyası | Virtual Threads (Java 21+) | Fibers (PHP 8.1+) + Amp/Swoole |
| Runtime model | Tək uzun JVM process | FPM (request-per-process) + async runtimes |
| Framework-lər | Netty, Vert.x, Micronaut | ReactPHP, Amphp, Swoole, RoadRunner |
| `HTTP/2` və WebSocket | Netty, `jdk.httpclient` | Ratchet, ReactPHP, Swoole |

---

## Niyə belə fərqlər var?

**Java bir process-də minlərlə connection saxlamaq üçün dizayn edilib.** JVM uzun müddət işləyir, yaddaş paylaşır, thread və socket-ləri effektiv idarə edir. NIO 2002-də gəldi çünki Java enterprise serverləri üçün "C10K problem" (10 000 eyni vaxtlı əlaqə) aktual idi. Selector → epoll/kqueue → minlərlə socket-i bir thread idarə edir.

**PHP əvvəldən "bir sorğu bir process" modelində işləyib.** `mod_php` və sonra PHP-FPM hər sorğunu sıfırdan başladır. Bu sadəlik üstünlüyü idi — yaddaş sızıntısı olmur, hər sorğu təmiz başlayır. Amma async I/O bu modeldə lazım deyil — sorğu onsuz da qısadır. Ona görə PHP-nin öz stream API-si çox bəsitdir (select-based, epoll-sız).

**İndi isə PHP də uzun-yaşayan runtime-lara keçir** — Swoole, RoadRunner, FrankenPHP, Octane. Bu runtime-lar async I/O, coroutine, memory paylaşımı gətirir. PHP 8.1 Fibers dil səviyyəsində coroutine dəstəyi verdi. Amma hələ də PHP "default" mühit FPM-dir.

**`libuv`/`libev`** — həm Node.js, həm də ReactPHP altda işlədən C kitabxanalarıdır. PHP-nin `ext-event` və `ext-uv` extension-ları bunları PHP-yə gətirir. Bu, Java NIO-nun JNI ilə OS-dan çağırdığı funksiyaların oxşarıdır.

---

## Hansı dildə var, hansında yoxdur?

**Yalnız Java-da:**

- `ByteBuffer` — position/limit/mark modelli buffer
- Direct buffers (off-heap yaddaş)
- `MappedByteBuffer` — memory-mapped fayllar
- `FileChannel.transferTo()` — OS sendfile
- `Selector` — SelectionKey-lərlə multiplexing
- `AsynchronousFileChannel` — async fayl I/O
- `WatchService` — cross-platform fayl izləmə
- Virtual Thread + bloklayan IO (sehrli kombinasiya)
- Netty ekosistemi (gRPC, HTTP/2, QUIC)

**Yalnız PHP-də (və ya PHP ekosistemində):**

- `fopen` ilə stream wrapper-lar (ftp://, http://, data:// və s.)
- `file_get_contents` single-call convenience
- SplFileObject iterator
- Swoole `SWOOLE_HOOK_ALL` — köhnə bloklayan kodu avtomatik async-ə çevirir
- FrankenPHP — Caddy server + PHP + async worker
- Symfony Mercure hub (SSE üçün)

**İkisində də var:**

- Non-blocking sockets
- I/O multiplexing (`Selector` vs `stream_select`)
- TCP/UDP server yazma
- Event loop modeli
- Coroutine dəstəyi (müasir versiyalarda)
- Background timers

**İkisində də primitiv:**

- io_uring (Linux yeni async I/O) — Java hələ tam dəstəkləmir, PHP-də də yoxdur

---

## Best Practices

### Java NIO/NIO.2

1. **Yüksək konkurrensiyada** Virtual Thread + `java.net.Socket` (JDK 21+) seçimini düşün — kod sadədir, performans yaxşıdır.
2. **Çox client-li server** üçün Netty istifadə et — Selector-u özün yazma, səhvlər çox olur.
3. **Direct buffer-ləri reuse et** — `allocateDirect` bahadır, pool yarat.
4. **`transferTo`** böyük fayl köçürməsində — OS sendfile sürətlidir.
5. **`Files.lines()`** böyük fayllar üçün — try-with-resources-lə bağla.
6. **`WatchService`** istifadəsi zamanı `key.reset()` unutma — əks halda izləmə dayanır.
7. **`ByteBuffer.flip()` və `clear()` fərqini** qarışdırma — flip oxumağa hazırlayır, clear yazmağa.
8. **`configureBlocking(false)`** register etməzdən əvvəl — əks halda `IllegalBlockingModeException`.
9. **Thread safety**: `SocketChannel` tək thread-də istifadə et — NIO thread-safe deyil.
10. **Memory-mapped fayllar**: dəyişikliklər üçün `force()` çağır, əks halda OS crash-də data itə bilər.

### PHP Streams

1. **Böyük fayllar üçün `fread` chunked** — `file_get_contents` yaddaşı partladır.
2. **Generator ilə** satır-satır oxu — memory-efficient.
3. **ReactPHP/Amp/Swoole** seçimi: Swoole performansda önündədir, amma extension tələb edir; ReactPHP pure PHP-dir.
4. **Laravel üçün Octane** istifadə et — FrankenPHP və ya Swoole driver-i.
5. **`stream_set_timeout`** socket-lərdə — unutma, əks halda hang ola bilər.
6. **`stream_context_create`** HTTP istəklərində — timeout, user-agent, SSL seçimləri.
7. **`pcntl_async_signals(true)`** long-running script-lərdə — graceful shutdown.
8. **`fclose`** `try/finally` və ya `register_shutdown_function` içində — resource leak-in qarşısı.
9. **Swoole `SWOOLE_HOOK_ALL`** — köhnə kodu bir flaqla async-ə çevirir, amma bəzi kitabxanalar sınır.
10. **Unit testdə** stream mock üçün `vfsStream` (php-vfs) paketi — real fayl sistemi lazım deyil.

---

## Yekun

- **Java NIO** (Java 1.4) — `Channel`, `Buffer`, `Selector`. **NIO.2** (Java 7) — `Path`, `Files`, `AsynchronousFileChannel`, `WatchService`.
- **`ByteBuffer`** — position/limit/capacity modelli fix buffer. Direct buffer OS-un native yaddaşındadır, heap buffer JVM-dədir.
- **`FileChannel.transferTo()`** — zero-copy köçürmə, altda OS `sendfile()` syscall-ı.
- **`Selector`** — bir thread-də minlərlə socket izləyir. Netty, Tomcat NIO, Vert.x bunun üzərindədir.
- **`MappedByteBuffer`** — böyük faylları yaddaş kimi emal etmək (DB sistemlərində istifadə olunur).
- **Virtual Thread** (Java 21+) NIO-nu əvəzləyə bilər — bloklayan kod yazırsan, JVM altda async edir.
- **PHP-də NIO yoxdur** — əvəzinə `stream_*` funksiyaları və `stream_select` var.
- **ReactPHP** pure PHP-də event loop gətirir, altda `stream_select` və ya `ext-event`/`ext-uv`.
- **Swoole** — C extension, öz coroutine sistemi. `SWOOLE_HOOK_ALL` ilə bloklayan kod avtomatik async olur.
- **Amp (Amphp v3)** — PHP 8.1 Fibers + Revolt EventLoop. Sinxron görünən amma async kod.
- **FrankenPHP** — Caddy+PHP ilə Laravel Octane-a yeni alternativ. HTTP/3, worker mode.
- **Memory-mapped fayllar PHP-də yoxdur** — `shmop` məhduddur, `ext-ffi` ilə birbaşa mmap syscall-ı çağırmaq olar.
- **PHP-FPM hər request-i bloklayır** — async yalnız xüsusi runtime-larda (Swoole, RoadRunner, Octane, FrankenPHP).
- **Qərar meyarı:** çox connection + uzun-yaşayan server lazım isə — Java Netty və ya Swoole; sadə HTTP API üçünsə — adi PHP-FPM və ya Spring Boot kifayətdir.
