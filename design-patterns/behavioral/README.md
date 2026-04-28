# Behavioral Design Patterns (Davranış Pattern-lər)

GoF Behavioral pattern-lər: obyektlər arasındakı ünsiyyəti və məsuliyyəti müəyyən edir. Bu pattern-lər "kim nə edir" sualını deyil, "kim kiminlə necə danışır" sualını həll edir — algoritm seçimi, state idarəsi, request ötürülməsi, əməliyyat encapsulation-ı.

## Fayllar

| Fayl | Pattern | Səviyyə | Qısa izah |
|------|---------|---------|-----------|
| [01-observer.md](01-observer.md) | Observer | Middle ⭐⭐ | Subject state dəyişəndə subscriber-lərə avtomatik bildiriş; Laravel Events/Listeners |
| [02-strategy.md](02-strategy.md) | Strategy | Middle ⭐⭐ | Eyni işi görən alqoritmlər ayrı class-lara köçürülür, runtime-da dəyişdirilir |
| [03-command.md](03-command.md) | Command | Middle ⭐⭐ | Əməliyyatı object kimi encapsulate et — queue, undo, audit üçün |
| [04-template-method.md](04-template-method.md) | Template Method | Middle ⭐⭐ | Algoritmin skelet base class-da, addımların tamamlanması subclass-larda |
| [05-iterator.md](05-iterator.md) | Iterator | Middle ⭐⭐ | Collection-un daxili strukturunu gizlədərək `foreach` ilə gəzilməsi |
| [06-chain-of-responsibility.md](06-chain-of-responsibility.md) | Chain of Responsibility | Senior ⭐⭐⭐ | Request-i zəncirdə handler-lara ötür; Laravel Middleware, Pipeline |
| [07-state.md](07-state.md) | State | Senior ⭐⭐⭐ | Object-in davranışı daxili state-ə görə dəyişir; order lifecycle, subscription |
| [08-mediator.md](08-mediator.md) | Mediator | Senior ⭐⭐⭐ | Komponentlər arasındakı birbaşa əlaqəni mərkəzi coordinator-a köçür |
| [09-visitor.md](09-visitor.md) | Visitor | Lead ⭐⭐⭐⭐ | Mövcud class-ları dəyişmədən yeni əməliyyat əlavə et; double dispatch |
| [10-memento.md](10-memento.md) | Memento | Middle ⭐⭐ | Object state-ini encapsulation-ı pozmadan snapshot al; undo/redo |
| [11-null-object.md](11-null-object.md) | Null Object | Middle ⭐⭐ | `null` yoxlamalarını aradan qaldıran "heç nə etməyən" default implementasiya |

## Oxuma Yolu

### Əsas ardıcıllıq (tövsiyə olunan)

```
Observer → Strategy → Command → Template Method → Iterator
    → Chain of Responsibility → State → Mediator → Visitor
    → Memento → Null Object
```

### Laravel developer üçün prioritet yol

Əgər Laravel layihəsindəsinizsə, bu sıra daha sürətli dəyər gətirir:

1. **Observer** — Laravel Events/Listeners-i dərinləşdir
2. **Command** — Laravel Jobs, Command Bus, CQRS-ə giriş
3. **Chain of Responsibility** — Middleware, Pipeline-ı anla
4. **State** — Order/Subscription lifecycle üçün state machine
5. **Strategy** — Payment, shipping, export kimi swappable alqoritmlər
6. **Null Object** — null-check zəncirlərinə son; GuestUser pattern

### Mürəkkəblik sırası

**Middle ⭐⭐** — Başla:
- Observer, Strategy, Command, Template Method, Iterator, Memento, Null Object

**Senior ⭐⭐⭐** — Davam et:
- Chain of Responsibility, State, Mediator

**Lead ⭐⭐⭐⭐** — Dərinləş:
- Visitor

## Digər Qovluqlarla Əlaqə

Bu behavioral pattern-lər çox zaman aşağıdakılarla birlikdə istifadə olunur:

- **[../creational/](../creational/)** — Strategy/Command object-lərini yaratmaq üçün Factory
- **[../structural/](../structural/)** — Decorator zənciri CoR kimi işləyir; Composite + Iterator birlikdə güclüdür
- **[../laravel/](../laravel/)** — Event/Listener, Pipeline, Command Bus, State Machine Laravel-spesifik tətbiqlər
- **[../integration/](../integration/)** — Command pattern CQRS-ə, Observer Event Sourcing-ə açılır
- **[../ddd/](../ddd/)** — Domain Events Observer-ın DDD versiyasıdır
