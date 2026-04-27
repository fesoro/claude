# PHP / Laravel — Interview Hazırlığı

PHP/Laravel backend developer üçün interview sualları — orta səviyyə biliklərdən dərin texniki anlayışa qədər.

**Toplam: 9 fayl** — Middle-dan Lead-ə, ardıcıl öyrənmə yolu ilə.

---

## Səviyyə Göstəriciləri

| Göstərici | Kimə uyğundur |
|-----------|--------------|
| ⭐⭐ **Middle** | 2-4 illik PHP/Laravel developer üçün gözlənilən biliklər |
| ⭐⭐⭐ **Senior** | 4-6+ il, mürəkkəb sistemlər qurur, texniki qərar verir |
| ⭐⭐⭐⭐ **Lead** | Advanced internals, performance engineering, runtime arxitekturası |

---

## ⭐⭐ Middle — Əsas Biliklər (01–03)

| # | Fayl | Nə əhatə edir |
|---|------|--------------|
| 01 | [01-php-core.md](01-php-core.md) | PHP 8.x type system, generators, closures, error handling, magic methods, type juggling |
| 02 | [02-laravel-fundamentals.md](02-laravel-fundamentals.md) | Service Container, Service Provider, Middleware, Request lifecycle, Eloquent, Validation |
| 03 | [03-database-eloquent-advanced.md](03-database-eloquent-advanced.md) | Indexing, Transactions, N+1 problem, Query optimization, Eager loading, Soft deletes |

---

## ⭐⭐⭐ Senior — Texniki Dərinlik (04–07)

| # | Fayl | Nə əhatə edir |
|---|------|--------------|
| 04 | [04-queues-jobs-scheduling.md](04-queues-jobs-scheduling.md) | Queues, Jobs, Chaining, Batching, Horizon, Task Scheduler, Unique Jobs, Failed jobs |
| 05 | [05-advanced-laravel.md](05-advanced-laravel.md) | Pipeline, Notifications, Broadcasting, Octane, Multi-tenancy, Sanctum/Passport, Rate Limiting |
| 06 | [06-php-internals-memory.md](06-php-internals-memory.md) | Zend Engine, OPcache, JIT compiler, Memory management, GC, Streams, SPL, Fibers, FPM |
| 07 | [07-laravel-ecosystem.md](07-laravel-ecosystem.md) | Livewire, Inertia.js, Telescope, Pulse, Reverb, Pennant, Saloon, package development |

---

## ⭐⭐⭐⭐ Lead — Advanced Texniki Mövzular (08–09)

| # | Fayl | Nə əhatə edir |
|---|------|--------------|
| 08 | [08-modern-php-features.md](08-modern-php-features.md) | PHP 8.1–8.4 enums, readonly, named args, match, intersection types, fibers, attributes |
| 09 | [09-async-long-running.md](09-async-long-running.md) | Async PHP, ReactPHP, Swoole, Octane, RoadRunner, FrankenPHP, long-running process patterns |

---

## Mövzu Xəritəsi

```
PHP Core & Types ────────── 01
Laravel Framework ─────────── 02, 05, 07
Database & Eloquent ────────── 03
Queues & Scheduling ────────── 04
PHP Internals & Memory ─────── 06
Modern PHP (8.x) ───────────── 08
Async & Runtime ────────────── 09
```

---

## Hazırlıq Strategiyası

1. **Əvvəlcə** — `01`, `02` (PHP core + Laravel fundamentals)
2. **Sonra** — `03`, `04` (Database + Queues)
3. **Dərinləş** — `05`, `06` (Advanced Laravel + Internals)
4. **Ekosistem** — `07` (Laravel ecosystem tools)
5. **Fərqlən** — `08`, `09` (Modern PHP + Async runtime)
