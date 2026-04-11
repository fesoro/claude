# Zero-Downtime Migrations (Expand/Contract)

## Mündəricat
1. [Problem: Schema Dəyişikliyi + Canlı Traffic](#problem-schema-dəyişikliyi--canlı-traffic)
2. [Expand/Contract Pattern](#expandcontract-pattern)
3. [Sütun Əlavə Etmək](#sütun-əlavə-etmək)
4. [Sütun Adını Dəyişmək](#sütun-adını-dəyişmək)
5. [Sütun Silmək](#sütun-silmək)
6. [Böyük Table Migration](#böyük-table-migration)
7. [PHP İmplementasiyası](#php-implementasiyası)
8. [İntervyu Sualları](#intervyu-sualları)

---

## Problem: Schema Dəyişikliyi + Canlı Traffic

```
Rolling deployment zamanı:
  v1 kod + v2 kod eyni anda işləyir.
  DB schema hər iki versiyaya uyğun olmalıdır!

Tipik problem:
  v2 kod: column adı dəyişdi "user_name" → "username"
  v1 kod: hələ "user_name" oxuyur

  Əgər migration anında edilsə:
  → v1 instanceları çökür! ("user_name column not found")

Həll: Dəyişikliyi bir neçə addıma böl.
  Hər addım geriyə uyğun.
  Addımlar ayrı deployment-larda.

Qızıl qayda:
  Migration həmişə köhnə koddakı vəziyyəti dəstəkləməlidir.
  Yeni schema + köhnə kod = OK
  Yeni schema + yeni kod = OK
  Əvvəl migration, sonra kod dəyişikliyi DEYIL —
  İkisi birlikdə, amma geri uyğun!
```

---

## Expand/Contract Pattern

```
2 fazadan ibarətdir:

EXPAND (Genişlən):
  Köhnə strukturu saxla.
  Yeni strukturu əlavə et.
  Hər iki struktur eyni anda var.
  Köhnə kod hələ işləyir.

CONTRACT (Daralt):
  Köhnə strukturu sil.
  Yalnız yeni struktur qalır.
  Yeni kod tam keçid etdi.

Timeline:
  v1 kod ────────────────────────────────────────────────►
  migration ─────► (expand: yeni əlavə)
  v2 kod ────────────────────────────────────────────────►
  migration ─────────────────────────────► (contract: köhnə sil)

  deploy-1: migration (expand) + v2 kod
  deploy-2: migration (contract)

  İki deployment arası: sistem tam işləyir
```

---

## Sütun Əlavə Etmək

```
Sadə case — NOT NULL default ilə:
  ALTER TABLE users ADD COLUMN age INT;  → OK (nullable)
  ALTER TABLE users ADD COLUMN age INT NOT NULL DEFAULT 0; → OK

  NOT NULL + no default:
  → Mövcud row-lar nə olacaq?
  → Migration lock alır (table rewrite!)
  
  ✅ Əvvəl nullable əlavə et, sonra doldur, sonra NOT NULL et:
  Deploy 1: ALTER TABLE ADD COLUMN age INT; (nullable)
  Deploy 2: UPDATE users SET age = 0 WHERE age IS NULL; (batch!)
  Deploy 3: ALTER TABLE ALTER COLUMN age SET NOT NULL;

Böyük table-da NOT NULL əlavəsi:
  PostgreSQL 11+: NOT NULL + DEFAULT dərhal (metadata-only change)
    ALTER TABLE users ADD COLUMN score INT NOT NULL DEFAULT 0;
    → Mövcud row-lar fiziki olaraq dəyişmir!
    → DEFAULT dəyər yeni row-lara yazılır, köhnələr default görür
  
  PostgreSQL 10-: Full table rewrite → LOCK!
```

---

## Sütun Adını Dəyişmək

```
3 deployment lazımdır:

Status:
  Mövcud: column = "user_name"
  Hədəf:  column = "username"

Deploy 1 — EXPAND:
  ALTER TABLE users ADD COLUMN username VARCHAR(255);
  -- Hər iki sütun var
  -- Sync trigger əlavə et (ya da application level):
  UPDATE users SET username = user_name;  -- ilkin data kopyası

  Application: hər iki sütuna yaz (dual write)
    $user->user_name = $name;   // köhnə kod üçün
    $user->username  = $name;   // yeni kod üçün

Deploy 2 — TRANSITION:
  Application: yeni sütundan oxu (username)
  Hər iki sütuna həmişə yaz.
  Köhnə instance-lar hələ user_name-dən oxuya bilər.

Deploy 3 — CONTRACT:
  Application yalnız username istifadə edir.
  Köhnə kod artıq production-da yoxdur.
  ALTER TABLE users DROP COLUMN user_name;
```

---

## Sütun Silmək

```
2 deployment (əks istiqamət):

Status:
  Mövcud: column = "legacy_field"
  Hədəf:  column silinsin

Deploy 1 — KOD DƏYİŞİKLİYİ:
  Koddan "legacy_field" istifadəsini tamamilə çıxar.
  Migration yoxdur — yalnız kod dəyişikliyi!
  Deploy: köhnə + yeni kod eyni DB üzərindən işləyir.
  Köhnə kod hələ sütunu oxuyur, yeni cod oxumur.

Deploy 2 — MIGRATION:
  ALTER TABLE users DROP COLUMN legacy_field;
  Artıq heç bir kod bu sütunu istifadə etmir.
  → Safe!

Tələ:
  ORM mapping-dəki sütunu sil amma DB-dən silmə → OK
  DB-dən sil amma ORM mapping-də hələ var → CRASH!

  Sequence: Kod dəyişikliyi → Deploy → DB migration
```

---

## Böyük Table Migration

```
100M+ sətirli table-da ALTER TABLE → saatlarla LOCK!

Həlllər:

1. pt-online-schema-change (Percona, MySQL):
   Yeni table structure ilə ghost table yaradır.
   Data tədricən kopyalanır (triggers ilə sync).
   Son addımda table swap (qısa lock).

2. pg_repack (PostgreSQL):
   Table-ı online repack edir.
   ALTER TABLE yerinə.

3. gh-ost (GitHub, MySQL):
   Binlog ilə change tracking.
   Zero-lock migration.

4. Manual batch update:
   NOT NULL əlavəsi üçün:
   -- 1000 row-dan bir güncəlləmə (lock minimal)
   DO $$
   DECLARE
     batch_size INT := 1000;
     updated INT;
   BEGIN
     LOOP
       UPDATE users
       SET age = 0
       WHERE id IN (
         SELECT id FROM users
         WHERE age IS NULL
         LIMIT batch_size
       );
       GET DIAGNOSTICS updated = ROW_COUNT;
       EXIT WHEN updated = 0;
       PERFORM pg_sleep(0.1); -- breather
     END LOOP;
   END $$;

5. Background migration (Laravel):
   php artisan migrate --step
   Job ilə background-da batch update.
```

---

## PHP İmplementasiyası

```php
<?php
// Laravel migration — expand phase (sütun rename, step 1)
return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Yeni sütun əlavə et (nullable — köhnə kod üçün təhlükəsiz)
            $table->string('username')->nullable()->after('user_name');
        });

        // Mövcud dataları kopy et
        DB::statement('UPDATE users SET username = user_name WHERE username IS NULL');
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('username');
        });
    }
};
```

```php
<?php
// Dual-write Observer (transition phase)
class UserObserver
{
    public function saving(User $user): void
    {
        // Köhnə kod yeni sütunu bilmir → köhnə sütuna sync et
        if ($user->isDirty('username')) {
            $user->user_name = $user->username;
        }
        // Yeni kod köhnə sütunu bilmir → yeni sütuna sync et
        if ($user->isDirty('user_name')) {
            $user->username = $user->user_name;
        }
    }
}

// Model-ə əlavə et:
// protected static function booted(): void {
//     static::observe(UserObserver::class);
// }
```

```php
<?php
// Böyük table batch migration job
class BackfillUsernameJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue;

    private const BATCH_SIZE = 1000;

    public function handle(): void
    {
        $lastId = 0;

        do {
            $updated = DB::table('users')
                ->where('id', '>', $lastId)
                ->whereNull('username')
                ->orderBy('id')
                ->limit(self::BATCH_SIZE)
                ->get(['id', 'user_name']);

            if ($updated->isEmpty()) break;

            DB::table('users')
                ->whereIn('id', $updated->pluck('id'))
                ->update(['username' => DB::raw('user_name')]);

            $lastId = $updated->last()->id;

            // CPU/lock breathe
            usleep(50000); // 50ms
        } while ($updated->count() === self::BATCH_SIZE);
    }
}
```

---

## İntervyu Sualları

- Rolling deployment zamanı niyə bir migration iki yerə bölünür?
- "Expand/Contract" pattern-in iki fazası nədir?
- Sütun adını dəyişmək üçün minimum neçə deployment lazımdır?
- NOT NULL sütun əlavə etmək böyük table-da niyə problem yaradır?
- `pt-online-schema-change` necə işləyir?
- "Dual write" pattern niyə lazımdır?
- ORM-də sütun mapping-i silmək DB migration-dan əvvəl niyə edilməlidir?
