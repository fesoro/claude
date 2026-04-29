# 09 — Performance (Interview Hazırlığı)

Bu bölmə backend developer müsahibələrinin performance mövzularını əhatə edir. Profiling-dən başlayaraq indexing strategiyasına qədər — hər mövzu real production ssenariolarına əsaslanır.

---

## Mövzular

### Middle ⭐⭐

| # | Fayl | Mövzu |
|---|------|-------|
| 04 | [04-lazy-loading.md](04-lazy-loading.md) | Lazy Loading Strategies |
| 08 | [08-pagination-strategies.md](08-pagination-strategies.md) | Pagination (Offset, Cursor, Keyset) |

### Senior ⭐⭐⭐

| # | Fayl | Mövzu |
|---|------|-------|
| 01 | [01-performance-profiling.md](01-performance-profiling.md) | Performance Profiling Approach |
| 02 | [02-query-optimization.md](02-query-optimization.md) | Database Query Optimization |
| 03 | [03-caching-layers.md](03-caching-layers.md) | Multi-Level Caching |
| 05 | [05-connection-pool-tuning.md](05-connection-pool-tuning.md) | Connection Pooling Optimization |
| 09 | [09-async-batch-processing.md](09-async-batch-processing.md) | Async and Batch Processing |
| 10 | [10-compression-techniques.md](10-compression-techniques.md) | Compression Techniques |
| 11 | [11-apm-tools.md](11-apm-tools.md) | APM Tools and Observability |
| 14 | [14-api-performance.md](14-api-performance.md) | API Performance Optimization |
| 15 | [15-indexing-strategy.md](15-indexing-strategy.md) | Database Indexing Strategy |

### Lead ⭐⭐⭐⭐

| # | Fayl | Mövzu |
|---|------|-------|
| 06 | [06-memory-leak-detection.md](06-memory-leak-detection.md) | Memory Leak Detection |
| 07 | [07-garbage-collection.md](07-garbage-collection.md) | Garbage Collection Concepts |
| 12 | [12-load-testing.md](12-load-testing.md) | Load Testing |
| 13 | [13-flame-graphs.md](13-flame-graphs.md) | Flame Graphs and CPU Profiling |

---

## Reading Paths

### Backend Performance əsasları (Middle → Senior)
```
04 → 08 → 02 → 15 → 03 → 01
```

### Production debugging yolu (Senior → Lead)
```
01 → 11 → 02 → 15 → 06 → 07 → 13
```

### Infrastructure / Capacity Planning (Senior → Lead)
```
05 → 09 → 12 → 11 → 10
```

### Tam hazırlıq (müsahibə üçün)
```
04 → 08 → 02 → 15 → 03 → 14 → 01 → 11 → 05 → 09 → 10 → 06 → 07 → 12 → 13
```

---

## Müsahibədə Ən Çox Soruşulan Suallar

- "Bir endpoint-in yavaş olduğunu bildinizsə, necə debug edərdiniz?" → `01`, `11`, `02`
- "N+1 problem nədir, necə həll olunur?" → `04`, `02`
- "Caching strategiyaları haqqında danışın" → `03`
- "API performance necə optimize edərsiniz?" → `14`, `01`, `11`
- "N+1 problemi API-da necə həll olunur?" → `14`, `02`
- "Pagination növlərini müqayisə edin" → `08`
- "Load test necə aparırsınız?" → `12`
- "Memory leak necə tapılır?" → `06`, `07`
- "Index strategiyasını necə qurursunuz?" → `15`, `02`
