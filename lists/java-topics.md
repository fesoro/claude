## OOP / fundamentals

OOP (Encapsulation, Inheritance, Polymorphism, Abstraction)
SOLID prinsipləri (SRP, OCP, LSP, ISP, DIP)
Composition over Inheritance
Primitiv tiplər və Wrapper class-lar
Autoboxing / Unboxing
String, StringBuilder, StringBuffer fərqi
String pool, intern()
Equals / hashCode contract
toString override
Mutable vs Immutable
Immutability (final, private fields, defensive copy)
Access modifiers (public, protected, package-private, private)
this, super
Static vs Instance (static fields, methods, blocks)
final (variable, method, class)
Inner Class, Static Nested, Local, Anonymous
Abstract class vs Interface
Interface default methods (Java 8+)
Interface static methods
Interface private methods (Java 9+)
Marker interface (Serializable, Cloneable)

## Collections

Collections Framework ierarxiyası
List (ArrayList, LinkedList, Vector, Stack)
Set (HashSet, LinkedHashSet, TreeSet, EnumSet, CopyOnWriteArraySet)
Map (HashMap, LinkedHashMap, TreeMap, Hashtable, ConcurrentHashMap, EnumMap, WeakHashMap)
Queue, Deque (ArrayDeque, LinkedList, PriorityQueue)
Concurrent collections (ConcurrentHashMap, CopyOnWriteArrayList, BlockingQueue)
Iterator, Iterable, ListIterator
fail-fast vs fail-safe iterator
Comparable (natural ordering) vs Comparator (custom)
Collections.sort, Collections.unmodifiableX
Collectors (toList, toMap, groupingBy, partitioningBy, joining)
List.of, Set.of, Map.of (Java 9+ immutable)

## Generics

Generic class, method
Type parameter (T, E, K, V)
Bounded type (`<T extends Number>`)
Wildcard (? extends T, ? super T)
PECS (Producer Extends, Consumer Super)
Type Erasure
Generics limitations (primitive tiplər olmur)

## Exception handling

Checked vs Unchecked (RuntimeException)
Try-catch-finally, try-with-resources (AutoCloseable)
Multi-catch (catch A | B e)
Custom Exception
Chained exception (cause)
throw vs throws
Exception vs Error
Best practices: specific catch, don't swallow, log properly

## Functional / Streams

Functional Interface (@FunctionalInterface)
Function, BiFunction, Predicate, Consumer, Supplier
Lambda (arrow syntax)
Method References (Class::method)
Stream API (filter, map, reduce, collect, flatMap, sorted, distinct, limit, skip)
Parallel streams
Short-circuit (findFirst, anyMatch, limit)
Terminal vs Intermediate ops
Lazy evaluation
Stream.generate, Stream.iterate
IntStream, LongStream, DoubleStream (primitive streams)
Collectors (groupingBy, partitioningBy, toMap, joining)
Optional<T> (of, ofNullable, map, flatMap, orElse, orElseThrow, ifPresent)

## Concurrency / threading

Thread class, Runnable, Callable
ExecutorService (Executors.newFixedThreadPool, newCachedThreadPool, newSingleThreadExecutor)
Future, FutureTask
CompletableFuture (thenApply, thenCompose, allOf, anyOf)
synchronized keyword (method, block)
volatile — visibility, not atomicity
Atomic classes (AtomicInteger, AtomicReference, LongAdder)
ReentrantLock, ReadWriteLock, StampedLock
Condition (await, signal)
CountDownLatch, CyclicBarrier, Semaphore, Phaser
BlockingQueue (ArrayBlockingQueue, LinkedBlockingQueue)
ConcurrentHashMap (segment-level locking, CAS)
ThreadLocal
ForkJoinPool, RecursiveTask
ScheduledExecutorService
Deadlock, Race Condition, Livelock, Starvation
Happens-before relationship
Double-checked locking (volatile ilə)
Virtual Threads (Project Loom, Java 21+)
Structured Concurrency (Java 21 preview)

## Modern Java features

var (Java 10+) — local variable type inference
Text Blocks """...""" (Java 13+)
Switch expressions (Java 14+)
Records (Java 16+)
Sealed classes, interfaces (Java 17+)
Pattern Matching — instanceof (Java 16), switch (Java 21)
Record patterns (Java 21)
Virtual Threads (Java 21)
Foreign Function & Memory API
Simple Web Server (Java 18+)
Enhanced Random Generators (Java 17+)

## JVM / memory

JVM arxitekturası (ClassLoader, Runtime Data Areas, Execution Engine)
Heap (Young: Eden/S0/S1, Old), Metaspace, Stack, PC Register, Native Method Stack
Garbage Collection: Serial, Parallel, CMS (legacy), G1 (default), ZGC (low-latency), Shenandoah
GC phases: mark, sweep, compact
Minor GC vs Major GC vs Full GC
JVM flags: -Xms, -Xmx, -Xss, -XX:MaxGCPauseMillis
Class Loading (Bootstrap, Platform, System, Custom)
JIT Compiler (C1, C2, tiered compilation, HotSpot)
AOT (GraalVM native-image)
Escape analysis
Reflection API (Class, Method, Field, Constructor)
java.lang.invoke (MethodHandle, VarHandle)
ClassLoader hierarchy

## Annotations / metaprogramming

Annotations və Custom Annotation
@Retention (SOURCE, CLASS, RUNTIME)
@Target (TYPE, METHOD, FIELD, PARAMETER)
@Inherited, @Documented, @Repeatable
Built-in: @Override, @Deprecated, @SuppressWarnings, @FunctionalInterface, @SafeVarargs
Annotation Processor (APT — Lombok, MapStruct)

## I/O / NIO

java.io (InputStream, OutputStream, Reader, Writer)
BufferedReader, FileReader, FileWriter
Serialization / Deserialization (Serializable, transient)
java.nio (Channel, Buffer, Selector)
java.nio.file (Path, Files, WatchService)
Memory-mapped files
Async I/O (AsynchronousFileChannel, AsynchronousSocketChannel)

## Date / time / util

Date/Time API (java.time — LocalDate, LocalTime, LocalDateTime, ZonedDateTime, Instant)
Duration, Period
DateTimeFormatter
java.util.Date, Calendar (legacy)
Locale
BigDecimal / BigInteger
Random, SecureRandom

## Enums / records / sealed

Enum (values, valueOf, ordinal, switch, EnumSet, EnumMap)
Enum constructor
Enum implement interface
Abstract methods in enum
Record (Java 16+) — immutable data carrier
Record canonical constructor
Sealed class (Java 17+) — permits
Sealed interface

## Testing

JUnit 5 (Jupiter) — @Test, @BeforeEach, @BeforeAll, @DisplayName, @ParameterizedTest, @Nested
Assertions (assertEquals, assertThrows, assertAll)
Assumptions (assumeTrue)
Mockito (mock, when, verify, @Mock, @InjectMocks)
MockMvc (Spring MVC test)
WireMock (HTTP stubbing)
Testcontainers (Docker in tests)
AssertJ (fluent assertions)
Hamcrest matchers
JaCoCo — coverage

## Build / tooling

Maven (pom.xml, phases, goals, profiles)
Gradle (build.gradle, Groovy/Kotlin DSL)
javac, java, jar, javadoc
jshell — REPL (Java 9+)
jpackage (Java 14+)
jlink — custom runtime
jmap, jstack, jstat, jcmd — troubleshooting
JFR (Java Flight Recorder) + JMC
VisualVM

## Common libraries

Apache Commons (Lang, Collections, IO)
Guava
Jackson, Gson (JSON)
SLF4J + Logback / Log4j 2
Lombok
MapStruct
Hibernate / JPA
Jakarta EE (əvvəl Java EE)
Netty (async networking)
Reactor, RxJava (reactive)
Retrofit, OkHttp
