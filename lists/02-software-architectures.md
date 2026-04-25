## Monolithic / Layered

Monolithic Architecture — tək deployable unit
Modular Monolith — daxili modullar ilə sərhəd, tək deploy (Shopify)
Majestic Monolith — intentional monolith (Basecamp/DHH)
Layered Architecture (N-Tier) — Presentation/Business/Data layers
Classic 3-Tier (Web/App/DB)

## Service-oriented

Service-Oriented Architecture (SOA) — coarse-grained services, ESB
Microservices Architecture — fine-grained, independent deploy
Nano-services anti-pattern — həddindən artıq bölünmə
Self-contained Systems (SCS) — vertically-sliced full services
Cell-Based Architecture — shuffle sharding, blast radius isolation

## Event-driven

Event-Driven Architecture (EDA) — async event flow
Event Sourcing — state = append-only event log
CQRS — oxu/yazı modelləri ayrı
Event Notification vs Event-Carried State Transfer vs Event Sourcing
Choreography vs Orchestration
Saga Pattern (Choreography vs Orchestration) — distributed transactions
Outbox Pattern — transactional event publishing
Inbox Pattern — exactly-once consumer side
Transactional Outbox + CDC

## Domain-centric

Domain-Driven Design (DDD) — ubiquitous language, bounded context
Hexagonal Architecture (Ports & Adapters) — core ↔ adapters
Clean Architecture (Uncle Bob) — dependency rule
Onion Architecture — domain center, outer rings
Vertical Slice Architecture — feature-first, not layer-first
Screaming Architecture — folders "scream" the domain
Command-Handler / Feature-Slice

## Infrastructure patterns

Serverless Architecture — FaaS (Lambda), managed services
Function-as-a-Service (FaaS)
Jamstack — static + APIs + CDN
PWA (Progressive Web App)
Edge Computing — compute at edge (CDN-level workers)
Multi-tenant Architecture — row-level / schema-level / pool model
Multi-region Active-Active
Multi-region Active-Passive

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
Canary Deployment
Rolling Deployment
Shadow Deployment
Feature Flags / Feature Toggles
Dark Launch
A/B Testing as architecture
GitOps (ArgoCD, Flux)
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
