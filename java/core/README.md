# Core Java — 95 Mövzu (01-95)

Java dili, JVM, core API-lər, tooling, design patterns və testing əsasları. Sıfırdan başlayıb Advanced ⭐⭐⭐ səviyyəyə qədər.

**Öyrənmə yolu:** 01 → 95 sıra ilə. Hər fayl müstəqil də oxuna bilər.

---

## Fazalar Xülasəsi

| # | Faza | Aralıq | Səviyyə | Qısa Mövzu |
|---|------|--------|---------|------------|
| 1 | Setup & Basics | 01-04 | Beginner ⭐ | Quraşdırma, hello world, IntelliJ, naming |
| 2 | Core Syntax | 05-13 | Beginner ⭐ | Variables, operators, control flow, loops, arrays, methods, exceptions |
| 3 | OOP | 14-28 | Beginner ⭐ → Intermediate ⭐⭐ | Classes, inheritance, polymorphism, interfaces, records |
| 4 | Essentials | 29-44 | Intermediate ⭐⭐ | Collections, String/DateTime API, modern syntax |
| 5 | Functional & Streams | 45-52 | Intermediate ⭐⭐ | Lambdas, functional interfaces, Optional, Streams |
| 6 | Generics | 53-56 | Intermediate ⭐⭐ | Type parameters, wildcards, erasure |
| 7 | I/O & Tooling | 57-61 | Intermediate ⭐⭐ | I/O, NIO, Maven, Gradle, debugging |
| 8 | Concurrency | 62-71 | Advanced ⭐⭐⭐ | Threads, locks, atomics, CompletableFuture, virtual threads |
| 9 | JVM & Memory | 72-79 | Advanced ⭐⭐⭐ → Expert ⭐⭐⭐⭐ | Architecture, GC, JIT, profiling |
| 10 | Advanced Features | 80-87 | Advanced ⭐⭐⭐ → Expert ⭐⭐⭐⭐ | Reflection, sealed, pattern matching, JPMS |
| 11 | Design Patterns | 88-90 | Advanced ⭐⭐⭐ | GoF patterns |
| 12 | Testing Basics | 91-95 | Intermediate ⭐⭐ → Advanced ⭐⭐⭐ | JUnit5, AssertJ, Mockito |

---

## Səviyyə Legendi

- **Beginner ⭐** — Java ilk dəfə görür
- **Intermediate ⭐⭐** — Əsas syntax biləni, istehsalata hazırdır
- **Advanced ⭐⭐⭐** — Mövzunu dərindən başa düşmək üçün
- **Expert ⭐⭐⭐⭐** — Tuning, internals, performance optimization

---

## Phase 1: Setup & Basics (01-04)

| # | Mövzu | Səv. |
|---|-------|------|
| [01](01-installation-setup.md) | Java Qurulması: JDK/JRE/JVM, JAVA_HOME, SDKMAN, distribusiyalar | Beginner ⭐ |
| [02](02-hello-world-main.md) | İlk proqram, `public static void main`, `javac`/`java`, CLI args | Beginner ⭐ |
| [03](03-intellij-setup-shortcuts.md) | IntelliJ quraşdırma, JDK config, 50+ qısayol, live template | Beginner ⭐ |
| [04](04-naming-conventions-style.md) | PascalCase/camelCase/UPPER_SNAKE, Javadoc, Google Java Style | Beginner ⭐ |

## Phase 2: Core Syntax (05-13)

| # | Mövzu | Səv. |
|---|-------|------|
| [05](05-variables-data-types.md) | 8 primitive, reference tiplər, literal-lər, `var`, default dəyərlər | Beginner ⭐ |
| [06](06-operators.md) | Arithmetic/logical/bitwise/ternary, precedence, `5/2` tələsi | Beginner ⭐ |
| [07](07-control-flow.md) | if/else, classic switch, switch expression, pattern matching | Beginner ⭐ |
| [08](08-loops.md) | for/while/do-while/for-each, labeled break/continue | Beginner ⭐ |
| [09](09-arrays.md) | 1D/2D/jagged, Arrays utility, common interview tasks | Beginner ⭐ |
| [10](10-user-input-scanner.md) | Scanner, BufferedReader, Console, `nextLine()` tələsi | Beginner ⭐ |
| [11](11-methods.md) | Metod anatomiyası, pass-by-value, overloading, varargs, recursion | Beginner ⭐ |
| [12](12-type-casting-instanceof.md) | Widening/narrowing, upcast/downcast, pattern matching `instanceof` | Beginner ⭐ |
| [13](13-exceptions-basics.md) | Exception hierarchy, checked/unchecked, Error | Beginner ⭐ |

## Phase 3: OOP (14-28)

| # | Mövzu | Səv. |
|---|-------|------|
| [14](14-oop-classes-objects.md) | Class anatomy, constructors, static vs instance | Beginner ⭐ |
| [15](15-this-super-constructors.md) | Constructor chaining, `this()`/`super()`, inheritance icrası | Beginner ⭐ |
| [16](16-packages-imports-access-modifiers.md) | package/import, `public`/`protected`/default/`private` matrix | Beginner ⭐ |
| [17](17-static-final.md) | static field/method/block, final variable/method/class, sabitlər | Beginner ⭐ |
| [18](18-equals-vs-double-equals.md) | `==` vs `.equals()`, String pool, Integer cache, wrapper-lər | Beginner ⭐ |
| [19](19-string-stringbuilder.md) | String immutability, concat cost, StringBuilder vs StringBuffer | Beginner ⭐ |
| [20](20-oop-encapsulation.md) | access modifiers, getters/setters, information hiding | Beginner ⭐ |
| [21](21-oop-inheritance.md) | extends, super, method overriding, covariant return | Beginner ⭐ |
| [22](22-oop-polymorphism.md) | compile-time vs runtime, dynamic dispatch | Intermediate ⭐⭐ |
| [23](23-oop-abstraction.md) | abstraction layers, leaky abstraction | Intermediate ⭐⭐ |
| [24](24-oop-interfaces.md) | default/static methods, functional interface, marker interface | Intermediate ⭐⭐ |
| [25](25-oop-abstract-classes.md) | abstract vs interface, template method pattern | Intermediate ⭐⭐ |
| [26](26-oop-enums.md) | enum methods, fields, abstract methods, EnumMap/EnumSet | Intermediate ⭐⭐ |
| [27](27-oop-inner-classes.md) | static nested, inner, local, anonymous classes | Intermediate ⭐⭐ |
| [28](28-oop-records.md) | Java 16+ records, compact constructor, limitations | Intermediate ⭐⭐ |

## Phase 4: Essentials — Exceptions, Collections, Modern APIs (29-44)

| # | Mövzu | Səv. |
|---|-------|------|
| [29](29-exceptions-best-practices.md) | try-with-resources, multi-catch, custom exceptions | Intermediate ⭐⭐ |
| [30](30-collections-overview.md) | Collection hierarchy, Iterable, Iterator | Intermediate ⭐⭐ |
| [31](31-collections-arraylist-linkedlist.md) | daxili struktur, Big-O, nə zaman hansı | Intermediate ⭐⭐ |
| [32](32-collections-hashmap.md) | hashing, bucket, load factor, resize, collision | Intermediate ⭐⭐ |
| [33](33-collections-linkedhashmap-treemap.md) | sıra fərqi, NavigableMap, SortedMap | Intermediate ⭐⭐ |
| [34](34-collections-hashset-treeset.md) | Set semantikası, equals/hashCode müqaviləsi | Intermediate ⭐⭐ |
| [35](35-collections-queue-deque.md) | PriorityQueue, ArrayDeque, LinkedList as Deque | Intermediate ⭐⭐ |
| [36](36-collections-comparable-comparator.md) | natural ordering, custom sort | Intermediate ⭐⭐ |
| [37](37-collections-utility.md) | Collections, Arrays utility metodlar | Intermediate ⭐⭐ |
| [38](38-collections-fail-fast-fail-safe.md) | ConcurrentModificationException, iterator davranışı | Intermediate ⭐⭐ |
| [39](39-collections-concurrent.md) | ConcurrentHashMap, CopyOnWriteArrayList, BlockingQueue | Advanced ⭐⭐⭐ |
| [40](40-string-api.md) | String metodları, format, join, split, regex | Intermediate ⭐⭐ |
| [41](41-datetime-api.md) | java.time: LocalDate, Instant, ZonedDateTime, Period | Intermediate ⭐⭐ |
| [42](42-text-blocks.md) | Java 15+ text blocks, `"""` sintaksisi | Intermediate ⭐⭐ |
| [43](43-var-type-inference.md) | `var` local variable inference, nə vaxt istifadə et | Intermediate ⭐⭐ |
| [44](44-switch-expressions.md) | Java 14+ switch expression, arrow syntax, yield | Intermediate ⭐⭐ |

## Phase 5: Functional & Streams (45-52)

| # | Mövzu | Səv. |
|---|-------|------|
| [45](45-lambda-method-references-basics.md) | Lambda sintaksisi, method reference (beginner-focused) | Intermediate ⭐⭐ |
| [46](46-functional-interfaces.md) | Function, Predicate, Consumer, Supplier, BiXxx | Intermediate ⭐⭐ |
| [47](47-optional.md) | Optional yaratma, map/flatMap/filter, anti-patternlər | Intermediate ⭐⭐ |
| [48](48-streams-basics.md) | Stream yaratma, lazy evaluation, pipeline | Intermediate ⭐⭐ |
| [49](49-streams-intermediate-ops.md) | filter, map, flatMap, distinct, sorted, peek | Intermediate ⭐⭐ |
| [50](50-streams-terminal-ops.md) | collect, reduce, count, findFirst, anyMatch | Intermediate ⭐⭐ |
| [51](51-streams-collectors.md) | groupingBy, partitioningBy, joining, toMap | Intermediate ⭐⭐ |
| [52](52-streams-parallel.md) | parallel stream, ForkJoinPool, thread-safety | Advanced ⭐⭐⭐ |

## Phase 6: Generics (53-56)

| # | Mövzu | Səv. |
|---|-------|------|
| [53](53-generics-basics.md) | generic class/method/interface, type parameter | Intermediate ⭐⭐ |
| [54](54-generics-wildcards.md) | `?`, `extends`, `super`, PECS prinsipi | Intermediate ⭐⭐ |
| [55](55-generics-bounded-types.md) | multiple bounds, recursive bounds | Intermediate ⭐⭐ |
| [56](56-generics-type-erasure.md) | compile-time vs runtime, reification | Advanced ⭐⭐⭐ |

## Phase 7: I/O & Build Tooling (57-61)

| # | Mövzu | Səv. |
|---|-------|------|
| [57](57-io-streams.md) | InputStream/OutputStream, Reader/Writer, buffering | Intermediate ⭐⭐ |
| [58](58-io-nio.md) | Channel, Buffer, Selector, non-blocking I/O | Advanced ⭐⭐⭐ |
| [59](59-maven-basics.md) | pom.xml, lifecycle, scopes, BOM, plugins | Intermediate ⭐⭐ |
| [60](60-gradle-basics.md) | build.gradle.kts, configurations, wrapper, version catalog | Intermediate ⭐⭐ |
| [61](61-debugging-basics.md) | Breakpoint növləri, step-over/into/out, JDWP remote debug | Intermediate ⭐⭐ |

## Phase 8: Concurrency (62-71)

| # | Mövzu | Səv. |
|---|-------|------|
| [62](62-concurrency-thread-basics.md) | Thread, Runnable, Callable, lifecycle | Advanced ⭐⭐⭐ |
| [63](63-concurrency-synchronized.md) | synchronized block/method, intrinsic lock, reentrant | Advanced ⭐⭐⭐ |
| [64](64-concurrency-volatile.md) | volatile, happens-before, memory visibility | Advanced ⭐⭐⭐ |
| [65](65-concurrency-executorservice.md) | ThreadPool növləri, Future, submit vs execute | Advanced ⭐⭐⭐ |
| [66](66-concurrency-locks.md) | ReentrantLock, ReadWriteLock, StampedLock | Advanced ⭐⭐⭐ |
| [67](67-concurrency-atomic.md) | AtomicInteger, AtomicReference, CAS operation | Advanced ⭐⭐⭐ |
| [68](68-concurrency-semaphore-latch.md) | Semaphore, CountDownLatch, CyclicBarrier, Phaser | Advanced ⭐⭐⭐ |
| [69](69-concurrency-threadlocal.md) | ThreadLocal, InheritableThreadLocal, memory leak riski | Advanced ⭐⭐⭐ |
| [70](70-concurrency-completablefuture.md) | async chaining, thenApply/thenCompose/handle | Advanced ⭐⭐⭐ |
| [71](71-concurrency-virtual-threads.md) | Java 21 virtual threads, structured concurrency | Advanced ⭐⭐⭐ |

## Phase 9: JVM & Memory (72-79)

| # | Mövzu | Səv. |
|---|-------|------|
| [72](72-jvm-architecture.md) | ClassLoader, Runtime areas, Execution Engine | Advanced ⭐⭐⭐ |
| [73](73-jvm-memory-areas.md) | Heap, Stack, Metaspace, Code Cache, PC Register | Advanced ⭐⭐⭐ |
| [74](74-jvm-classloading.md) | Bootstrap/Platform/App CL, delegation model, custom CL | Advanced ⭐⭐⭐ |
| [75](75-jvm-gc-basics.md) | GC nədir, root references, mark-sweep-compact | Advanced ⭐⭐⭐ |
| [76](76-jvm-gc-algorithms.md) | G1GC, ZGC, Shenandoah, Serial, Parallel fərqləri | Advanced ⭐⭐⭐ |
| [77](77-jvm-gc-tuning.md) | heap sizing, GC flags, GC log analizi | Expert ⭐⭐⭐⭐ |
| [78](78-jvm-jit-compiler.md) | JIT, C1/C2, tiered compilation, inlining, escape analysis | Expert ⭐⭐⭐⭐ |
| [79](79-jvm-profiling-tools.md) | JFR, async-profiler, VisualVM, JMC | Expert ⭐⭐⭐⭐ |

## Phase 10: Advanced Features (80-87)

| # | Mövzu | Səv. |
|---|-------|------|
| [80](80-reflection-api.md) | Class, Method, Field, Constructor introspection | Advanced ⭐⭐⭐ |
| [81](81-annotations-custom.md) | @interface, retention, target, annotation processor | Advanced ⭐⭐⭐ |
| [82](82-sealed-classes.md) | Java 17 sealed classes, permits, exhaustive patterns | Advanced ⭐⭐⭐ |
| [83](83-pattern-matching.md) | pattern matching for switch/instanceof, record patterns | Advanced ⭐⭐⭐ |
| [84](84-sequenced-collections.md) | Java 21 SequencedCollection, first/last, reversed | Advanced ⭐⭐⭐ |
| [85](85-string-templates.md) | JEP: Template strings (preview) | Advanced ⭐⭐⭐ |
| [86](86-foreign-memory-api.md) | Java 22 FFM API, native memory access | Expert ⭐⭐⭐⭐ |
| [87](87-modules-jpms.md) | Java 9+ modules, module-info.java, requires/exports | Advanced ⭐⭐⭐ |

## Phase 11: Design Patterns (88-90)

| # | Mövzu | Səv. |
|---|-------|------|
| [88](88-design-patterns-creational.md) | Singleton, Factory, Builder, Prototype, AbstractFactory | Advanced ⭐⭐⭐ |
| [89](89-design-patterns-structural.md) | Adapter, Decorator, Proxy, Facade, Composite | Advanced ⭐⭐⭐ |
| [90](90-design-patterns-behavioral.md) | Strategy, Observer, Command, Template, Chain of Responsibility | Advanced ⭐⭐⭐ |

## Phase 12: Testing Basics (91-95)

| # | Mövzu | Səv. |
|---|-------|------|
| [91](91-junit5-basics.md) | @Test, @BeforeEach, assertions, lifecycle | Intermediate ⭐⭐ |
| [92](92-junit5-advanced.md) | parameterized, dynamic tests, extensions, nested | Advanced ⭐⭐⭐ |
| [93](93-assertj.md) | fluent assertions, soft, custom, exception | Intermediate ⭐⭐ |
| [94](94-mockito-basics.md) | @Mock, when/thenReturn, verify, argument matchers | Intermediate ⭐⭐ |
| [95](95-mockito-advanced.md) | ArgumentCaptor, spy, static mock, BDDMockito | Advanced ⭐⭐⭐ |

---

**Sonrakı qovluq →** [spring/](../spring/) — Spring Framework, Boot, Data, Security (88 mövzu)

*95 fayl | Son yenilənmə: 2026-04-24*
