## Creational

Singleton — tək instance (global access); anti-pattern sayılır əksər hallarda
Factory Method — subclass hansı obyekti yaradacağını seçir
Abstract Factory — əlaqəli obyekt ailələri yaradır (Kit)
Builder — kompleks obyekti addım-addım qur (fluent API)
Prototype — mövcud obyektin klonu ilə yeni obyekt yarat
Object Pool — bahalı obyektləri reuse et (DB connections)
Dependency Injection — obyekt öz dependency-lərini yaratmır; kənardan alır
Lazy Initialization — first access-də yarat

## Structural

Adapter — incompatible interface-ləri birləşdir (wrapper)
Bridge — abstraction və implementation-u ayır
Composite — tree structure; leaf və composite eyni interface
Decorator — behavior dinamik əlavə et
Facade — kompleks subsystem-ə sadə interface
Proxy — placeholder / controlled access (virtual, remote, protection)
Flyweight — çoxsaylı kiçik obyektləri paylaş (intrinsic state)
Private Class Data — immutability

## Behavioral

Strategy — interchangeable algorithm-lar; runtime-da seçim
Observer — publisher-subscriber, dəyişikliyi bildir
Command — request-i obyekt kimi encapsulate (undo/redo, queue)
Template Method — skeleton parent-də, detallar child-də
Chain of Responsibility — handler zənciri (middleware pattern-ləri)
Iterator — kolleksiyanı traverse (implementation gizlədir)
State — obyektin state-i dəyişəndə behavior-u dəyişir
Mediator — çoxsaylı obyektlərin rabitəsini mərkəzləşdir
Visitor — operation-u structure-dan ayır (double dispatch)
Memento — state snapshot (undo)
Interpreter — grammar-ı obyekt ağacı kimi (DSL)
Null Object — null əvəzinə default no-op obyekt

## Concurrency

Thread Pool — thread-ləri reuse et
Producer-Consumer — queue ilə decoupling
Reader-Writer Lock — çoxsaylı oxu, tək yazı
Double-checked Locking — singleton üçün (pitfall-lı)
Monitor — synchronized method/block
Future / Promise — async result
Actor Model — mesaj keçirmə (Erlang, Akka)
Fork-Join — böyük task-ı alt-task-lara bölür
Active Object — method invocation-u separate thread-də
Thread-Local Storage

## Enterprise (Fowler PoEAA)

Repository — data access abstraction
Unit of Work — dəyişiklikləri qruplaşdır, atomic commit
Service Layer — application-specific operations
Data Mapper — domain obyekti ↔ DB ayrı
Active Record — obyekt özü DB ilə işləyir (Eloquent)
Table Data Gateway / Row Data Gateway
Identity Map — hər obyektin bir instance-ı session-da
Lazy Load — data lazım olanda gətir
Query Object — query-ni obyekt kimi
Specification — business rule-u obyekt kimi
Transaction Script — prosedur-styled method

## Domain-Driven Design

Value Object — identity-siz, immutable (Money, Address)
Entity — identity-li obyekt
Aggregate — consistency boundary
Aggregate Root — xarici aləmdən access nöqtəsi
Domain Event — domain-də baş verən fakt
Domain Service — behavior entity/VO-ya yaraşmadıqda
Application Service — use case orchestration
Bounded Context — model sərhədi
Context Map — context-lər arası əlaqə
Anti-Corruption Layer — kənar sistemdən model-i qoru
Repository (DDD-də) — aggregate üçün kolleksiya illyuziyası
Factory (DDD-də) — kompleks aggregate yaratmaq

## CQRS / Event Sourcing

CQRS — Command/Query Responsibility Segregation
Event Sourcing — state = event log
Event Store
Projection / Read Model
Snapshot — event log-un compaction-u
Replay / Rebuild projection
Saga / Process Manager — uzun-ömürlü business proses

## Integration (EIP)

Outbox Pattern — transactional event publishing
Inbox Pattern — exactly-once consumption
Dead Letter Queue (DLQ)
Message Router / Content-Based Router
Message Translator
Message Channel (point-to-point, pub-sub)
Aggregator — çoxsaylı mesajı birləşdir
Splitter — tək mesajı çoxsaylıya bölür
Claim Check — böyük payload-u store et, ref göndər

## Cloud / distributed

Circuit Breaker — fail fast
Bulkhead — resource isolation
Retry — backoff ilə təkrar
Timeout
Sidecar — companion process (Envoy)
Ambassador — outbound proxy
Anti-Corruption Layer (infra)
Strangler Fig — gradual migration
API Gateway
Backends for Frontends (BFF)
Compensating Transaction
Leader Election
Cache-Aside / Read-through / Write-through / Write-behind
Materialized View
Sharding / Partitioning
Health Endpoint Monitoring
Priority Queue
Queue-Based Load Leveling
Throttling
Federated Identity
Gatekeeper — security proxy

## Functional / reactive

Higher-Order Function
Pure Function
Immutability / Persistent Data Structures
Monad (Maybe/Optional, Either/Result, IO)
Currying, Partial Application
Function Composition
Observable (Rx), Observer vs Iterable duality
Event Stream / Stream Processing

## Frontend / UI

MVC — Model/View/Controller
MVP — Model/View/Presenter
MVVM — Model/View/ViewModel (WPF, Angular)
Flux / Redux — unidirectional data flow
Component Composition
Hooks (React)
HOC (Higher-Order Component)
Render Props
State Machine (XState)
Container vs Presentational Component

## Anti-patterns

God Object / God Class
Spaghetti Code
Lava Flow
Magic Numbers / Strings
Dead Code
Copy-paste programming
Premature Optimization
Golden Hammer — "when you have a hammer, everything is a nail"
Reinventing the wheel
Yo-yo Problem (deep inheritance)
Feature Envy
Shotgun Surgery
Primitive Obsession
Long Parameter List
