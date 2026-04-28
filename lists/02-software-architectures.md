## Monolithic / Layered

Monolithic Architecture — tək deployable unit
  USE: small team, early product, unclear domain boundaries
  AVOID: team > 10 devs, domain becomes clear, independent scaling needed
Modular Monolith — daxili modullar ilə sərhəd, tək deploy (Shopify)
  USE: proven domain, medium team; microservices complexity yoxdur, amma bounded contexts var
  AVOID: əgər genuinely hər modul fərqli texnologiyaya ehtiyac duyursa
Majestic Monolith — intentional monolith (Basecamp/DHH)
Layered Architecture (N-Tier) — Presentation/Business/Data layers
  USE: CRUD apps, small teams, clear separation of concerns
  AVOID: complex business logic — anemic domain model problemi yaranır
Classic 3-Tier (Web/App/DB)

## Service-oriented

Service-Oriented Architecture (SOA) — coarse-grained services, ESB
  USE: large enterprise, heterogeneous systems, central governance needed
  AVOID: greenfield apps — ESB bottleneck, heavy coordination overhead
Microservices Architecture — fine-grained, independent deploy
  USE: independent scaling needed, multiple teams, clear domain boundaries, polyglot requirements
  AVOID: small team (< 5 devs), unclear domain, shared DB — distributed monolith risk
Nano-services anti-pattern — həddindən artıq bölünmə
Self-contained Systems (SCS) — vertically-sliced full services
Cell-Based Architecture — shuffle sharding, blast radius isolation
  USE: multi-tenant SaaS, noisy-neighbor problems, regulatory data isolation

## Event-driven

Event-Driven Architecture (EDA) — async event flow
  USE: loose coupling needed, audit trail, fanout to many consumers
  AVOID: request-reply flows (latency unpredictable), simple CRUD apps
Event Sourcing — state = append-only event log
  USE: full audit log required, temporal queries, event replay, complex domain (DDD aggregates)
  AVOID: simple CRUD, reporting-heavy apps (projection complexity explodes), no event versioning strategy
CQRS — oxu/yazı modelləri ayrı
  USE: read/write load asymmetry, complex query requirements, Event Sourcing pairing
  AVOID: simple apps — 2 models = 2× consistency effort
Event Notification vs Event-Carried State Transfer vs Event Sourcing
Choreography vs Orchestration
Saga Pattern (Choreography vs Orchestration) — distributed transactions
  USE: long-running cross-service transactions, no 2PC possible
  AVOID: 2 services only (just use 2PC or redesign boundary)
Outbox Pattern — transactional event publishing
Inbox Pattern — exactly-once consumer side
Transactional Outbox + CDC

## Domain-centric

Domain-Driven Design (DDD) — ubiquitous language, bounded context
  USE: complex domain with rich business logic, large team needing shared language
  AVOID: CRUD-heavy apps, small teams — overhead outweighs benefit
Hexagonal Architecture (Ports & Adapters) — core ↔ adapters
  USE: testability first, multiple adapters (REST + CLI + queue), framework-agnostic core
  AVOID: simple services — port/adapter boilerplate for tiny codebases
Clean Architecture (Uncle Bob) — dependency rule
  USE: long-lived enterprise apps, strict layer dependency control
  AVOID: startups — over-engineering early
Onion Architecture — domain center, outer rings
Vertical Slice Architecture — feature-first, not layer-first
  USE: large teams where horizontal layer ownership causes friction
  AVOID: small teams — feature isolation can duplicate infrastructure code
Screaming Architecture — folders "scream" the domain
Command-Handler / Feature-Slice

## Infrastructure patterns

Serverless Architecture — FaaS (Lambda), managed services
  USE: event-driven workloads, unpredictable traffic, no ops team
  AVOID: long-running processes (> 15 min), cold-start-sensitive flows, high-throughput constant load (EC2 cheaper)
Function-as-a-Service (FaaS)
Jamstack — static + APIs + CDN
Edge Computing — compute at edge (CDN-level workers)
  USE: latency-sensitive (auth, A/B, personalization at edge), geo-routing
  AVOID: stateful ops, heavy computation, >50ms workloads
Multi-tenant Architecture — row-level / schema-level / pool model
  USE: SaaS product; row-level (most flexible), schema-level (moderate isolation), separate DB (strict isolation/compliance)
  AVOID: mixing models mid-product — migration cost is high
Multi-region Active-Active
  USE: strict RTO/RPO, global user base, regulatory data residency
  AVOID: unless traffic justifies — operational complexity is very high
Multi-region Active-Passive
  USE: DR requirement without global user latency needs
PWA (Progressive Web App)

## Integration patterns

API Gateway Pattern — single entry, cross-cutting concerns
Backend for Frontend (BFF) — per-client backend
Strangler Fig Pattern — gradual replacement
Anti-Corruption Layer (ACL) — DDD integration shield
Enterprise Integration Patterns (EIP) — Hohpe/Woolf
Publish-Subscribe
Point-to-Point Messaging
Request-Reply
Claim Check Pattern — large payload → store, send ref

## Resilience / scalability

Sidecar Pattern — companion container (Envoy, Istio)
Ambassador Pattern — proxy for outbound
Adapter Pattern (infrastructure)
Service Mesh — sidecar-based networking
Circuit Breaker Pattern — fail fast
Bulkhead Pattern — resource isolation
Retry with Exponential Backoff + Jitter
Timeout Pattern
Rate Limiting / Throttling
Load Shedding / Backpressure
Failover / Fallback
Compensating Transaction

## Data patterns

Cache-Aside Pattern — lazy load
Read-through / Write-through / Write-behind Cache
CQRS (data level)
Materialized View
Sharding / Partitioning (horizontal)
Replication (leader-follower, multi-leader, leaderless)
Saga / Two-Phase Commit / Three-Phase Commit
Polyglot Persistence
Database-per-Service (microservices)
Shared Database anti-pattern

## Deployment / release

Blue-Green Deployment
  USE: instant cutover, easy rollback, stateless services
  AVOID: DB schema changes that aren't backwards-compatible; double infra cost
Canary Deployment
  USE: gradual risk, real traffic validation, automated rollback on metrics
  AVOID: no observability in place — you can't detect when to abort
Rolling Deployment
  USE: default for stateless services; resource-efficient (no double infra)
  AVOID: migrations that break backward compat with old version
Shadow Deployment
  USE: validate new version under real load without user impact
  AVOID: services with side effects (double-writes to external systems)
Feature Flags / Feature Toggles
Dark Launch
A/B Testing as architecture
GitOps (ArgoCD, Flux)
  USE: K8s + declarative infra; audit trail via git; easy rollback
  AVOID: secrets in git (use external-secrets-operator)
Immutable Infrastructure
Infrastructure as Code (Terraform, Pulumi)

## Anti-patterns

Big Ball of Mud
Distributed Monolith
God Object / God Service
Spaghetti Architecture
Death by Config
Vendor lock-in (when not intentional)
Premature microservices
Nano-services (too-fine-grained)
Cargo Cult Architecture

## Data / analytical architectures

Data Lake — raw, schema-on-read
Data Warehouse — structured, schema-on-write
Data Lakehouse — Iceberg / Delta / Hudi
Data Mesh — domain-owned data products
Medallion Architecture (Bronze/Silver/Gold)
Lambda Architecture (batch + speed)
Kappa Architecture (stream-only)
Streaming-first Architecture

## AI / ML architectures

RAG (Retrieval-Augmented Generation)
Agent Architecture (ReAct, Plan-and-Execute)
Multi-agent Orchestration
Feature Store Architecture (offline + online)
Model Serving (online inference, batch, streaming)
Feedback-loop / RLHF architecture
