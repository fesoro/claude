# Lists — Quick Reference Cheat Sheets

Bu folder backend developer üçün **sürətli reference material** toplusudur. Hər fayl bir texnologiyanın əsas konsept, komanda və ya mövzularını qısa formatda əhatə edir.

Tam öyrənmə materialları üçün: `php/`, `java/`, `golang/`, `system-design/`, `sql/`, `docker/` folderlərinə bax.

---

## Məzmun

### Konseptual

| # | Fayl | Məzmun |
|---|------|--------|
| 01 | [design-patterns.md](01-design-patterns.md) | Creational, Structural, Behavioral, Architectural patterns |
| 02 | [software-architectures.md](02-software-architectures.md) | Monolith, Microservices, EDA, DDD, deployment arxitekturaları |
| 03 | [system-design-topics.md](03-system-design-topics.md) | Scaling, caching, messaging, consistency, reliability mövzuları |
| 04 | [api-design.md](04-api-design.md) | REST, GraphQL, gRPC, versioning, security, gateway patterns |
| 05 | [framework-components.md](05-framework-components.md) | Backend framework-lərinin ümumi komponentləri (tüm dillər) |

### PHP / Laravel

| # | Fayl | Məzmun |
|---|------|--------|
| 06 | [php-topics.md](06-php-topics.md) | PHP tipler, array/string funksiyalar, OOP, modern PHP 8.x |
| 07 | [laravel-commands.md](07-laravel-commands.md) | Artisan komandaları (make, migrate, queue, horizon, test) |
| 08 | [composer-commands.md](08-composer-commands.md) | Composer install, update, require, autoload, config |

### Data / Storage

| # | Fayl | Məzmun |
|---|------|--------|
| 09 | [sql-topics.md](09-sql-topics.md) | SQL DQL, DML, DDL, funksiyalar, window functions, CTE |
| 10 | [postgresql-commands.md](10-postgresql-commands.md) | psql, \d meta-commands, pg_dump, pg_restore, config |
| 23 | [mysql-commands.md](23-mysql-commands.md) | mysql client, DDL, replication, mysqldump, EXPLAIN, performance_schema |
| 11 | [redis-topics.md](11-redis-topics.md) | String, Hash, List, Set, ZSet, Pub/Sub, Lua, cluster |
| 26 | [mongodb-commands.md](26-mongodb-commands.md) | mongosh, CRUD, aggregation pipeline, indexes, replica set, sharding |
| 25 | [elasticsearch-commands.md](25-elasticsearch-commands.md) | Index, search DSL, aggregations, mapping, ILM, vector search |

### JVM

| # | Fayl | Məzmun |
|---|------|--------|
| 12 | [java-topics.md](12-java-topics.md) | Java OOP, Collections, Streams, Concurrency, modern Java |
| 13 | [spring-topics.md](13-spring-topics.md) | Spring Core, Boot, Data, Security, WebFlux, Cloud |

### Go

| # | Fayl | Məzmun |
|---|------|--------|
| 14 | [golang-topics.md](14-golang-topics.md) | Go syntax, goroutines, channels, stdlib, tooling |

### Messaging

| # | Fayl | Məzmun |
|---|------|--------|
| 20 | [kafka-commands.md](20-kafka-commands.md) | Topic, producer, consumer, consumer group, config |
| 24 | [rabbitmq-commands.md](24-rabbitmq-commands.md) | rabbitmqctl, exchanges, queues (quorum/stream), DLX, patterns |

### Web / Network

| # | Fayl | Məzmun |
|---|------|--------|
| 19 | [nginx-commands.md](19-nginx-commands.md) | CLI, config, virtual hosts, proxy, SSL, performance |
| 27 | [curl-http-commands.md](27-curl-http-commands.md) | curl & HTTPie reference, auth, mTLS, debugging, HTTP/2-3 |
| 28 | [openssl-commands.md](28-openssl-commands.md) | Cert/key gen, CSR, TLS debug, hashing, encrypt, PEM/DER/p12 |

### Containers / Orchestration

| # | Fayl | Məzmun |
|---|------|--------|
| 18 | [docker-commands.md](18-docker-commands.md) | Image, container, volume, network, compose, Dockerfile |
| 21 | [kubernetes-commands.md](21-kubernetes-commands.md) | kubectl, pods, deployments, services, ingress, RBAC |

### Cloud / IaC

| # | Fayl | Məzmun |
|---|------|--------|
| 33 | [aws-cli-commands.md](33-aws-cli-commands.md) | AWS CLI setup, S3, EC2, IAM, Lambda, SQS, DynamoDB, CloudWatch, SSM |
| 34 | [terraform-commands.md](34-terraform-commands.md) | init/plan/apply/destroy, state, workspaces, HCL, backends, Terragrunt |

### Security / Auth

| # | Fayl | Məzmun |
|---|------|--------|
| 35 | [jwt-oauth-cheatsheet.md](35-jwt-oauth-cheatsheet.md) | JWT structure/algorithms/attacks, OAuth2 grant types, OIDC, JWKS, best practices |

### Dev Tools

| # | Fayl | Məzmun |
|---|------|--------|
| 15 | [git-commands.md](15-git-commands.md) | Git init, branch, merge, rebase, stash, remote, workflow |
| 16 | [linux-commands.md](16-linux-commands.md) | Navigation, process, network, filesystem, permissions |
| 17 | [bash-scripting.md](17-bash-scripting.md) | Variables, conditionals, loops, functions, traps, arrays |
| 22 | [vim-commands.md](22-vim-commands.md) | Modes, navigation, editing, search, splits, macros |
| 29 | [tmux-commands.md](29-tmux-commands.md) | Sessions, windows, panes, copy mode, config, plugins |
| 30 | [regex-cheatsheet.md](30-regex-cheatsheet.md) | PCRE/POSIX, anchors, quantifiers, lookaround, tool fərqləri |
| 32 | [cron-expressions.md](32-cron-expressions.md) | 5/6-field cron, Quartz, Spring, Laravel scheduler, systemd timer |
| 36 | [makefile-commands.md](36-makefile-commands.md) | targets, .PHONY, variables, functions, pattern rules, CI idioms |

### Testing

| # | Fayl | Məzmun |
|---|------|--------|
| 31 | [phpunit-pest-commands.md](31-phpunit-pest-commands.md) | PHPUnit/Pest CLI, assertions, mocks, datasets, Laravel helpers |
