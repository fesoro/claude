## Request pipeline

Router / Route Handler
Route Group / Prefix
Middleware (pre/post)
Filter (Spring-stilində)
Interceptor (bütün request-ə toxunur)
Pipe / Transformer (NestJS)
Controller / Handler
Action / Endpoint
Form Request / Request Validator
Route Model Binding
Controller Resolver (DI integration)

## Domain / business

Service (business logic)
Domain Service (DDD)
Use Case / Interactor (Clean Architecture)
Command Handler (CQRS)
Query Handler (CQRS)
Command Bus / Query Bus
Event Bus / Event Emitter
Domain Event Dispatcher
Policy / Authorization Handler
Gate (Laravel)
Specification (business rule obyekt)
Saga / Process Manager

## Persistence

Repository / DAO
ORM (Eloquent, JPA/Hibernate, GORM, Doctrine)
Query Builder
Entity Manager / Session
Migration Tool (Artisan, Flyway, Liquibase, Alembic)
Seeder / Fixtures
Factory (test data)
Connection Pool
Transaction Manager
Unit of Work
Read/Write Connection Splitter
Identity Map (Doctrine)
Lazy Loading Proxy

## Caching

Cache Manager / Store
Cache Driver (Redis, Memcached, File, DB)
Cache Key Namespacing / Tagging
Query Cache
HTTP Cache (ETag/304)
Response Cache
View Cache

## Async / messaging

Scheduler / Cron Job Runner
Job / Task
Queue Worker / Consumer
Queue Producer / Dispatcher
Message Queue Broker Adapter (SQS, RabbitMQ, Kafka)
Pub/Sub Channel
Event Listener / Subscriber
Notification Channel (mail, SMS, push, Slack)
Broadcaster (WebSocket / Pusher)

## API / serialization

REST Controller / API Resource
API Resource / Transformer / Serializer
JSON:API Serializer
Pagination Paginator (offset, cursor)
API Versioning Component
OpenAPI/Swagger Generator
GraphQL Schema / Resolver
Data Loader (N+1 solver)
gRPC Stub / Service
WebSocket Handler / Channel

## Cross-cutting

Logger
Log Handler / Formatter / Processor
Health Check Endpoint
Metrics Collector (Prometheus exporter)
Tracer (OpenTelemetry)
Error / Exception Handler
Exception Reporter (Sentry, Bugsnag)
Request Validator
Serializer / Deserializer
Normalizer / Denormalizer (Symfony)
Mass Assignment Guard
Feature Flag / Toggle Service
Configuration Loader (env, yaml, json)
Secrets Manager Adapter (Vault, AWS SM)
Rate Limiter
Throttler
Session Manager / Session Store
Cookie Manager
CSRF Token Manager
CORS Handler
Translator / Localizer (i18n)

## Security

Authentication Guard / AuthProvider
Password Hasher (bcrypt, argon2)
Token Issuer (JWT, Passport, Sanctum)
OAuth Provider / Consumer
Authorization Policy / Voter (Symfony) / Gate (Laravel)
RBAC / ABAC Engine
Encryption Service
CSP / Security Headers Middleware
Input Sanitizer

## Infrastructure

Dependency Injection Container (Autowiring)
Service Provider / Module
Bean / Service Definition
HTTP Client (Guzzle, OkHttp, RestTemplate/WebClient)
Circuit Breaker Client
Mail Mailer / Transport (SMTP, Mailgun, SES)
Storage / Filesystem Adapter (Flysystem, S3)
File Upload Handler
PDF Generator Service
Image Processor

## View / templating

Template Engine (Blade, Thymeleaf, Twig, Jinja)
View Composer
Layout / Master Template
Component / Slot
Directive (custom tag)
View Helper
Asset Bundler Integration (Vite, Webpack)
Static Asset Manifest

## CLI / ops

CLI Command / Artisan / Spring CLI / Symfony Console
Command Scheduler
Console Kernel
Stub Generator
Code Generator / Scaffolding
Database Console (tinker, sqsh)
Maintenance Mode Handler
Deployer / Release Manager (Deployer, Capistrano)

## Testing

Test Runner / Harness
Fixture / Factory
Mocker / Stubber (Mockery, Mockito)
HTTP Client Mock
Database Transaction Rollback (test cleanup)
Snapshot Tester
Contract / Pact Tester
Feature Flag Override (in tests)
Time Freezer (Carbon::setTestNow)

## Build / bootstrap

Autoloader (Composer PSR-4, javac, go mod)
Kernel / Application
Bootstrap / Service Provider Registration
Environment Loader (.env)
Config Cache / Route Cache / View Cache
Lifecycle Hook (booted, terminating)
Signal Handler (SIGTERM graceful shutdown)

## Real-time

WebSocket Server / Channel
Broadcaster (Pusher, Soketi, Reverb)
Presence Channel
Server-Sent Events Endpoint
Long-poll Handler
