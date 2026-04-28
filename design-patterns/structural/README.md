# Structural Design Patterns (Struktur Pattern-lər)

GoF Structural pattern-lər class-lar və object-lər arasındakı münasibətləri düzgün qurmağı öyrədir. Məqsəd: daha böyük strukturlar yaratmaq, eyni zamanda flexibility və efficiency-ni saxlamaq.

---

## Fayllar

| Fayl | Pattern | Səviyyə | Qısa izah |
|------|---------|---------|-----------|
| [01-facade.md](01-facade.md) | Facade | Middle ⭐⭐ | Mürəkkəb subsistemi sadə interfeys arxasında gizlətmək |
| [02-adapter.md](02-adapter.md) | Adapter | Middle ⭐⭐ | İki uyğunsuz interface arasında körpü qurmaq |
| [03-decorator.md](03-decorator.md) | Decorator | Middle ⭐⭐ | Inheritance olmadan runtime-da davranış qatlamaq |
| [04-proxy.md](04-proxy.md) | Proxy | Senior ⭐⭐⭐ | Real object-in yerinə keçən surrogate — cache, access control, lazy init |
| [05-composite.md](05-composite.md) | Composite | Senior ⭐⭐⭐ | Tree-like hierarchiya — leaf və composite eyni interface |
| [06-flyweight.md](06-flyweight.md) | Flyweight | Lead ⭐⭐⭐⭐ | Paylaşılan intrinsic state ilə yaddaş istehlakını azaltmaq |
| [07-bridge.md](07-bridge.md) | Bridge | Senior ⭐⭐⭐ | Abstraction-ı implementation-dan ayırmaq — ikiqat müstəqil hierarchy |

---

## Oxuma Yolu

### Junior / Middle başlanğıcı (3 pattern)
```
Facade → Adapter → Decorator
```
- **Facade** (01): Laravel Facade mexanizmini dərindən anlamaq; custom Facade yaratmaq; God Facade anti-pattern-dən qaçmaq
- **Adapter** (02): Üçüncü tərəf SDK-larını izolə etmək; payment gateway dəyişmək, vendor lock-in önləmək
- **Decorator** (03): Composition ilə davranış qatlamaq; middleware konsepsiyasını anlamaq; cache + log + retry zəncirləri

### Senior (3 pattern)
```
Proxy → Composite → Bridge
```
- **Proxy** (04): Repository cache qatı; protection proxy (access control); virtual proxy (lazy init); audit trail
- **Composite** (05): Permission tree; navigation menu; recursive category hierarchy; N+1 problem ilə mübarizə
- **Bridge** (07): Subclass explosion-un qarşısını almaq; notification type × channel dizaynı; report type × export format

### Lead / Architect (1 pattern)
```
Flyweight
```
- **Flyweight** (06): Memory-critical ssenarilərdə intrinsic/extrinsic state ayrımı; benchmark əsasında optimallaşdırma

---

## Pattern-lər arası fərqlər

### Struktur bənzər, niyyət fərqli

| Pattern | Eyni interface? | Məqsəd |
|---------|----------------|--------|
| **Adapter** | Xeyr — interface çevirir | Uyğunsuzluğu həll edir |
| **Decorator** | Bəli — əlavə davranış qoşur | Funksionallıq genişləndirir |
| **Proxy** | Bəli — surrogate kimi davranır | Access, cache, lazy init nəzarəti |
| **Facade** | Yeni sadə interface | Mürəkkəbliyi gizlədər |

### Bridge vs Strategy vs Adapter

| Sual | Cavab |
|------|-------|
| Əvvəldən planlanıb, iki müstəqil hierarchy lazımdır? | **Bridge** |
| Algorithm runtime-da dəyişdirilir, bir hierarchy var? | **Strategy** |
| Mövcud uyğunsuz interface-i sonradan düzəltmək lazımdır? | **Adapter** |

---

## Laravel-də Structural Pattern-lər

```
Laravel Facade         → Facade pattern (statik proxy)
Eloquent lazy loading  → Virtual Proxy pattern
Middleware pipeline    → Decorator pattern (HTTP versiyası)
Repository interface   → Adapter pattern (Eloquent → domain)
Gate/Policy            → Protection Proxy pattern
Nested set categories  → Composite pattern
Config singleton-lar   → Flyweight pattern (paylaşılan state)
Notification × Channel → Bridge pattern
```

---

## Digər Kateqoriyalarla Əlaqə

### Creational pattern-lərlə birgə
- **Factory Method / Abstract Factory** → Structural pattern-lərin object-lərini yaradır (Flyweight factory, Composite node factory)
- **Builder** → Mürəkkəb Composite tree-lərini addım-addım qurmaq
- **Singleton** → Facade arxasındakı service-lər, Flyweight factory cache-i
- **Object Pool** → Proxy ilə birgə istifadə: pool-dan alınan real object-ə Proxy arxasından çatmaq

### Behavioral pattern-lərlə birgə
- **Strategy** → Adapter-lər runtime-da Strategy kimi seçilir; Bridge-in implementation tərəfi Strategy kimi dəyişdirilir
- **Observer** → Composite tree-dəki event propagation; Bridge abstraction-ın event fire etməsi
- **Visitor** → Composite tree-nin hər node-unu fərqli davranışla ziyarət etmək; Flyweight node-larına davranış əlavə
- **Iterator** → Composite tree-ni gəzmək üçün
- **Chain of Responsibility** → Decorator-a bənzər zəncirləmə, lakin request-response modeli
- **Template Method** → Bridge abstraction tərəfinin hook metodları

### Architecture ilə əlaqə
- **Hexagonal Architecture** → Port-Adapter cütü = Adapter pattern; Proxy port implementation-ları; Facade port-lardan keçiş nöqtəsi
- **SOLID Principles** → Open/Closed: Decorator ilə mövcud class-ı dəyişmədən genişlənmə; Liskov: Proxy eyni interface-i pozmamalı
- **CQRS** → Read model cache-i Proxy pattern-dir; Decorator-larla query/command separation

---

## Anti-Pattern Xülasəsi

| Pattern | Ən çox görülən anti-pattern |
|---------|---------------------------|
| Facade | God Facade — çox metod, biznes məntiqi içəridə |
| Adapter | Leaky abstraction — Adaptee exception-larının sızması |
| Decorator | Decorator hell — 5+ qat, yanlış sıra effektləri |
| Proxy | Pass-through Proxy — heç bir real dəyər olmadan overhead |
| Composite | Süni uniformluq — əslində uniform olmayan şeyləri eyni interface-ə sıxmaq |
| Flyweight | Mutable intrinsic state — race condition, shared data pozulur |
| Bridge | Strategy kifayət edəndə Bridge qurmaq — premature abstraction |
