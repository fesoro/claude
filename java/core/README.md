# Core Java — 95 Mövzu (000-094)

Java dili, JVM, core API-lər, tooling, design patterns və testing əsasları. Sıfırdan başlayıb İrəli səviyyəyə qədər.

**Öyrənmə yolu:** 000 → 094 sıra ilə. Hər fayl müstəqil də oxuna bilər.

---

## Fazalar Xülasəsi

| # | Faza | Aralıq | Səviyyə | Qısa Mövzu |
|---|------|--------|---------|------------|
| 1 | Setup & Basics | 000-003 | Başlanğıc | Quraşdırma, hello world, IntelliJ, naming |
| 2 | Core Syntax | 004-012 | Başlanğıc | Variables, operators, control flow, loops, arrays, methods, exceptions |
| 3 | OOP | 013-027 | Başlanğıc → Orta | Classes, inheritance, polymorphism, interfaces, records |
| 4 | Essentials | 028-043 | Orta | Collections, String/DateTime API, modern syntax |
| 5 | Functional & Streams | 044-051 | Orta | Lambdas, functional interfaces, Optional, Streams |
| 6 | Generics | 052-055 | Orta | Type parameters, wildcards, erasure |
| 7 | I/O & Tooling | 056-060 | Orta | I/O, NIO, Maven, Gradle, debugging |
| 8 | Concurrency | 061-070 | İrəli | Threads, locks, atomics, CompletableFuture, virtual threads |
| 9 | JVM & Memory | 071-078 | İrəli → Ekspert | Architecture, GC, JIT, profiling |
| 10 | Advanced Features | 079-086 | İrəli → Ekspert | Reflection, sealed, pattern matching, JPMS |
| 11 | Design Patterns | 087-089 | İrəli | GoF patterns |
| 12 | Testing Basics | 090-094 | Orta → İrəli | JUnit5, AssertJ, Mockito |

---

## Səviyyə Legendi

- **Başlanğıc** — Java ilk dəfə görür
- **Orta** — Əsas syntax biləni, istehsalata hazırdır
- **İrəli** — Mövzunu dərindən başa düşmək üçün
- **Ekspert** — Tuning, internals, performance optimization

---

## Phase 1: Setup & Basics (000-003)

| # | Mövzu | Səv. |
|---|-------|------|
| [000](000-installation-setup.md) | Java Qurulması: JDK/JRE/JVM, JAVA_HOME, SDKMAN, distribusiyalar | Başlanğıc |
| [001](001-hello-world-main.md) | İlk proqram, `public static void main`, `javac`/`java`, CLI args | Başlanğıc |
| [002](002-intellij-setup-shortcuts.md) | IntelliJ quraşdırma, JDK config, 50+ qısayol, live template | Başlanğıc |
| [003](003-naming-conventions-style.md) | PascalCase/camelCase/UPPER_SNAKE, Javadoc, Google Java Style | Başlanğıc |

## Phase 2: Core Syntax (004-012)

| # | Mövzu | Səv. |
|---|-------|------|
| [004](004-variables-data-types.md) | 8 primitive, reference tiplər, literal-lər, `var`, default dəyərlər | Başlanğıc |
| [005](005-operators.md) | Arithmetic/logical/bitwise/ternary, precedence, `5/2` tələsi | Başlanğıc |
| [006](006-control-flow.md) | if/else, classic switch, switch expression, pattern matching | Başlanğıc |
| [007](007-loops.md) | for/while/do-while/for-each, labeled break/continue | Başlanğıc |
| [008](008-arrays.md) | 1D/2D/jagged, Arrays utility, common interview tasks | Başlanğıc |
| [009](009-user-input-scanner.md) | Scanner, BufferedReader, Console, `nextLine()` tələsi | Başlanğıc |
| [010](010-methods.md) | Metod anatomiyası, pass-by-value, overloading, varargs, recursion | Başlanğıc |
| [011](011-type-casting-instanceof.md) | Widening/narrowing, upcast/downcast, pattern matching `instanceof` | Başlanğıc |
| [012](012-exceptions-basics.md) | Exception hierarchy, checked/unchecked, Error | Başlanğıc |

## Phase 3: OOP (013-027)

| # | Mövzu | Səv. |
|---|-------|------|
| [013](013-oop-classes-objects.md) | Class anatomy, constructors, static vs instance | Başlanğıc |
| [014](014-this-super-constructors.md) | Constructor chaining, `this()`/`super()`, inheritance icrası | Başlanğıc |
| [015](015-packages-imports-access-modifiers.md) | package/import, `public`/`protected`/default/`private` matrix | Başlanğıc |
| [016](016-static-final.md) | static field/method/block, final variable/method/class, sabitlər | Başlanğıc |
| [017](017-equals-vs-double-equals.md) | `==` vs `.equals()`, String pool, Integer cache, wrapper-lər | Başlanğıc |
| [018](018-string-stringbuilder.md) | String immutability, concat cost, StringBuilder vs StringBuffer | Başlanğıc |
| [019](019-oop-encapsulation.md) | access modifiers, getters/setters, information hiding | Başlanğıc |
| [020](020-oop-inheritance.md) | extends, super, method overriding, covariant return | Başlanğıc |
| [021](021-oop-polymorphism.md) | compile-time vs runtime, dynamic dispatch | Orta |
| [022](022-oop-abstraction.md) | abstraction layers, leaky abstraction | Orta |
| [023](023-oop-interfaces.md) | default/static methods, functional interface, marker interface | Orta |
| [024](024-oop-abstract-classes.md) | abstract vs interface, template method pattern | Orta |
| [025](025-oop-enums.md) | enum methods, fields, abstract methods, EnumMap/EnumSet | Orta |
| [026](026-oop-inner-classes.md) | static nested, inner, local, anonymous classes | Orta |
| [027](027-oop-records.md) | Java 16+ records, compact constructor, limitations | Orta |

## Phase 4: Essentials — Exceptions, Collections, Modern APIs (028-043)

| # | Mövzu | Səv. |
|---|-------|------|
| [028](028-exceptions-best-practices.md) | try-with-resources, multi-catch, custom exceptions | Orta |
| [029](029-collections-overview.md) | Collection hierarchy, Iterable, Iterator | Orta |
| [030](030-collections-arraylist-linkedlist.md) | daxili struktur, Big-O, nə zaman hansı | Orta |
| [031](031-collections-hashmap.md) | hashing, bucket, load factor, resize, collision | Orta |
| [032](032-collections-linkedhashmap-treemap.md) | sıra fərqi, NavigableMap, SortedMap | Orta |
| [033](033-collections-hashset-treeset.md) | Set semantikası, equals/hashCode müqaviləsi | Orta |
| [034](034-collections-queue-deque.md) | PriorityQueue, ArrayDeque, LinkedList as Deque | Orta |
| [035](035-collections-comparable-comparator.md) | natural ordering, custom sort | Orta |
| [036](036-collections-utility.md) | Collections, Arrays utility metodlar | Orta |
| [037](037-collections-fail-fast-fail-safe.md) | ConcurrentModificationException, iterator davranışı | Orta |
| [038](038-collections-concurrent.md) | ConcurrentHashMap, CopyOnWriteArrayList, BlockingQueue | İrəli |
| [039](039-string-api.md) | String metodları, format, join, split, regex | Orta |
| [040](040-datetime-api.md) | java.time: LocalDate, Instant, ZonedDateTime, Period | Orta |
| [041](041-text-blocks.md) | Java 15+ text blocks, `"""` sintaksisi | Orta |
| [042](042-var-type-inference.md) | `var` local variable inference, nə vaxt istifadə et | Orta |
| [043](043-switch-expressions.md) | Java 14+ switch expression, arrow syntax, yield | Orta |

## Phase 5: Functional & Streams (044-051)

| # | Mövzu | Səv. |
|---|-------|------|
| [044](044-lambda-method-references-basics.md) | Lambda sintaksisi, method reference (beginner-focused) | Orta |
| [045](045-functional-interfaces.md) | Function, Predicate, Consumer, Supplier, BiXxx | Orta |
| [046](046-optional.md) | Optional yaratma, map/flatMap/filter, anti-patternlər | Orta |
| [047](047-streams-basics.md) | Stream yaratma, lazy evaluation, pipeline | Orta |
| [048](048-streams-intermediate-ops.md) | filter, map, flatMap, distinct, sorted, peek | Orta |
| [049](049-streams-terminal-ops.md) | collect, reduce, count, findFirst, anyMatch | Orta |
| [050](050-streams-collectors.md) | groupingBy, partitioningBy, joining, toMap | Orta |
| [051](051-streams-parallel.md) | parallel stream, ForkJoinPool, thread-safety | İrəli |

## Phase 6: Generics (052-055)

| # | Mövzu | Səv. |
|---|-------|------|
| [052](052-generics-basics.md) | generic class/method/interface, type parameter | Orta |
| [053](053-generics-wildcards.md) | `?`, `extends`, `super`, PECS prinsipi | Orta |
| [054](054-generics-bounded-types.md) | multiple bounds, recursive bounds | Orta |
| [055](055-generics-type-erasure.md) | compile-time vs runtime, reification | İrəli |

## Phase 7: I/O & Build Tooling (056-060)

| # | Mövzu | Səv. |
|---|-------|------|
| [056](056-io-streams.md) | InputStream/OutputStream, Reader/Writer, buffering | Orta |
| [057](057-io-nio.md) | Channel, Buffer, Selector, non-blocking I/O | İrəli |
| [058](058-maven-basics.md) | pom.xml, lifecycle, scopes, BOM, plugins | Orta |
| [059](059-gradle-basics.md) | build.gradle.kts, configurations, wrapper, version catalog | Orta |
| [060](060-debugging-basics.md) | Breakpoint növləri, step-over/into/out, JDWP remote debug | Orta |

## Phase 8: Concurrency (061-070)

| # | Mövzu | Səv. |
|---|-------|------|
| [061](061-concurrency-thread-basics.md) | Thread, Runnable, Callable, lifecycle | İrəli |
| [062](062-concurrency-synchronized.md) | synchronized block/method, intrinsic lock, reentrant | İrəli |
| [063](063-concurrency-volatile.md) | volatile, happens-before, memory visibility | İrəli |
| [064](064-concurrency-executorservice.md) | ThreadPool növləri, Future, submit vs execute | İrəli |
| [065](065-concurrency-locks.md) | ReentrantLock, ReadWriteLock, StampedLock | İrəli |
| [066](066-concurrency-atomic.md) | AtomicInteger, AtomicReference, CAS operation | İrəli |
| [067](067-concurrency-semaphore-latch.md) | Semaphore, CountDownLatch, CyclicBarrier, Phaser | İrəli |
| [068](068-concurrency-threadlocal.md) | ThreadLocal, InheritableThreadLocal, memory leak riski | İrəli |
| [069](069-concurrency-completablefuture.md) | async chaining, thenApply/thenCompose/handle | İrəli |
| [070](070-concurrency-virtual-threads.md) | Java 21 virtual threads, structured concurrency | İrəli |

## Phase 9: JVM & Memory (071-078)

| # | Mövzu | Səv. |
|---|-------|------|
| [071](071-jvm-architecture.md) | ClassLoader, Runtime areas, Execution Engine | İrəli |
| [072](072-jvm-memory-areas.md) | Heap, Stack, Metaspace, Code Cache, PC Register | İrəli |
| [073](073-jvm-classloading.md) | Bootstrap/Platform/App CL, delegation model, custom CL | İrəli |
| [074](074-jvm-gc-basics.md) | GC nədir, root references, mark-sweep-compact | İrəli |
| [075](075-jvm-gc-algorithms.md) | G1GC, ZGC, Shenandoah, Serial, Parallel fərqləri | İrəli |
| [076](076-jvm-gc-tuning.md) | heap sizing, GC flags, GC log analizi | Ekspert |
| [077](077-jvm-jit-compiler.md) | JIT, C1/C2, tiered compilation, inlining, escape analysis | Ekspert |
| [078](078-jvm-profiling-tools.md) | JFR, async-profiler, VisualVM, JMC | Ekspert |

## Phase 10: Advanced Features (079-086)

| # | Mövzu | Səv. |
|---|-------|------|
| [079](079-reflection-api.md) | Class, Method, Field, Constructor introspection | İrəli |
| [080](080-annotations-custom.md) | @interface, retention, target, annotation processor | İrəli |
| [081](081-sealed-classes.md) | Java 17 sealed classes, permits, exhaustive patterns | İrəli |
| [082](082-pattern-matching.md) | pattern matching for switch/instanceof, record patterns | İrəli |
| [083](083-sequenced-collections.md) | Java 21 SequencedCollection, first/last, reversed | İrəli |
| [084](084-string-templates.md) | JEP: Template strings (preview) | İrəli |
| [085](085-foreign-memory-api.md) | Java 22 FFM API, native memory access | Ekspert |
| [086](086-modules-jpms.md) | Java 9+ modules, module-info.java, requires/exports | İrəli |

## Phase 11: Design Patterns (087-089)

| # | Mövzu | Səv. |
|---|-------|------|
| [087](087-design-patterns-creational.md) | Singleton, Factory, Builder, Prototype, AbstractFactory | İrəli |
| [088](088-design-patterns-structural.md) | Adapter, Decorator, Proxy, Facade, Composite | İrəli |
| [089](089-design-patterns-behavioral.md) | Strategy, Observer, Command, Template, Chain of Responsibility | İrəli |

## Phase 12: Testing Basics (090-094)

| # | Mövzu | Səv. |
|---|-------|------|
| [090](090-junit5-basics.md) | @Test, @BeforeEach, assertions, lifecycle | Orta |
| [091](091-junit5-advanced.md) | parameterized, dynamic tests, extensions, nested | İrəli |
| [092](092-assertj.md) | fluent assertions, soft, custom, exception | Orta |
| [093](093-mockito-basics.md) | @Mock, when/thenReturn, verify, argument matchers | Orta |
| [094](094-mockito-advanced.md) | ArgumentCaptor, spy, static mock, BDDMockito | İrəli |

---

**Sonrakı qovluq →** [spring/](../spring/) — Spring Framework, Boot, Data, Security (88 mövzu)

*95 fayl | Son yenilənmə: 2026-04-24*
