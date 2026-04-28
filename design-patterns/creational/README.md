# Creational Design Patterns (Yaradıcı Pattern-lər)

GoF Creational pattern-lər: obyektlərin yaradılması prosesini abstraction-a aparır. Client kodu konkret class-lardan asılı olmadan obyekt alır — yaradılma məntiqi mərkəzləşdirilir, dəyişdirmək asanlaşır.

## Fayllar

| # | Fayl | Level | Qısa izah |
|---|------|-------|-----------|
| 01 | [01-singleton.md](01-singleton.md) | Junior ⭐ | Bütün application boyunca bir instance — Laravel Service Container ilə düzgün istifadə |
| 02 | [02-factory-method.md](02-factory-method.md) | Junior ⭐ | Hansı obyektin yaradılacağını subclass qərar verir — notification, payment, export channel-ları |
| 03 | [03-abstract-factory.md](03-abstract-factory.md) | Middle ⭐⭐ | Bir-biri ilə uyğun obyektlər ailəsini birlikdə yarat — email/SMS/test infrastructure dəstləri |
| 04 | [04-builder.md](04-builder.md) | Middle ⭐⭐ | Mürəkkəb obyekti addım-addım qur — Laravel Query Builder, sipariş, report konfiqurasiyası |
| 05 | [05-prototype.md](05-prototype.md) | Middle ⭐⭐ | Mövcud obyekti kopyala — invoice template, Eloquent `replicate()`, test fixture |
| 06 | [06-object-pool.md](06-object-pool.md) | Middle ⭐⭐ | Bahalı obyektləri pool-da saxla, yenidən istifadə et — DB/Redis connection pool |

## Oxuma Yolu

```
Singleton → Factory Method → Abstract Factory → Builder → Prototype → Object Pool
```

**Junior başlanğıcı:** Singleton → Factory Method
Tək instance idarəsi və yaradılma məntiğini mərkəzləşdirmə — Laravel-in özündə hər ikisi aktivdir.

**Middle dərinləşmə:** Abstract Factory → Builder → Prototype → Object Pool
Mürəkkəb yaradılma ssenariləri: uyğun object ailələri, addım-addım qurma, kopyalama, resurs idarəsi.

## Pattern Seçim Bələdçisi

| Ssenari | Pattern |
|---------|---------|
| Bütün app-da eyni instance lazımdır | Singleton |
| Hansı class yaranacağı runtime-da bilinmir | Factory Method |
| Bir neçə əlaqəli obyekt birlikdə dəyişəcək | Abstract Factory |
| 5+ parametrli mürəkkəb obyekt | Builder |
| Mövcud obyektdən yeni variant lazımdır | Prototype |
| Yaratmaq bahalı, resurs paylaşmaq lazımdır | Object Pool |
