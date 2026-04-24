# Database Security

> **Seviyye:** Intermediate ⭐⭐

## SQL Injection

En tehlikeli web tehlukesizlik problemi. Istifadecinin daxil etdiyi data SQL query-ye birbaşa daxil olur.

### Necə bas verir?

```php
// TEHLIKELI! SQL Injection mumkundur
$email = $_GET['email']; // Istifadeci: ' OR 1=1 --
$sql = "SELECT * FROM users WHERE email = '$email'";
// Neticede: SELECT * FROM users WHERE email = '' OR 1=1 --'
// Butun user-leri qaytarir!

// Daha tehlikeli:
// Input: '; DROP TABLE users; --
// Neticede: SELECT * FROM users WHERE email = ''; DROP TABLE users; --'
```

### Prepared Statements (Hell yolu #1)

```php
// PDO - Prepared Statement
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
$stmt->execute(['email' => $userInput]);
// Input ne olursa olsun, yalniz string kimi islenir!

// PDO - Positional
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND status = ?");
$stmt->execute([$email, $status]);

// MySQLi
$stmt = $mysqli->prepare("SELECT * FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
```

### Laravel (Hell yolu #2)

```php
// Eloquent (avtomatik safe)
User::where('email', $email)->first();

// Query Builder (avtomatik safe)
DB::table('users')->where('email', $email)->first();

// Raw query-lerde DIQQET!
// TEHLIKELI:
DB::select("SELECT * FROM users WHERE email = '$email'");

// SAFE (binding ile):
DB::select("SELECT * FROM users WHERE email = ?", [$email]);
DB::select("SELECT * FROM users WHERE email = :email", ['email' => $email]);

// whereRaw (diqqetli ol):
// TEHLIKELI:
User::whereRaw("email = '$email'")->first();
// SAFE:
User::whereRaw("email = ?", [$email])->first();
```

### SQL Injection novleri

#### Union-Based

```
Input: ' UNION SELECT username, password FROM admin_users --
Query: SELECT name, email FROM users WHERE id = '' UNION SELECT username, password FROM admin_users --'
```

#### Blind SQL Injection

```
Input: ' AND (SELECT COUNT(*) FROM users WHERE role='admin' AND password LIKE 'a%') > 0 --
-- Cavaba gore (true/false) her-her simvolu tapir
```

#### Second-Order SQL Injection

```php
// Istifadeci qeydiyyatdan kecir:
$name = "admin'--"; // Database-de saxlanilir

// Sonra basqa yerde istifade olunur:
$sql = "SELECT * FROM posts WHERE author = '$name'";
// Tehlikeli!
```

---

## Authentication & Authorization

### Least Privilege Principle

```sql
-- Her application ucun ayri user, minimum icaze
-- YANLIS: root/admin istifade etmek
-- DOGRU:

-- Read-only user (reporting ucun)
CREATE USER 'app_reader'@'%' IDENTIFIED BY 'strong_password';
GRANT SELECT ON myapp.* TO 'app_reader'@'%';

-- Application user (CRUD)
CREATE USER 'app_writer'@'%' IDENTIFIED BY 'strong_password';
GRANT SELECT, INSERT, UPDATE, DELETE ON myapp.* TO 'app_writer'@'%';

-- Migration user (schema changes)
CREATE USER 'app_admin'@'localhost' IDENTIFIED BY 'strong_password';
GRANT ALL PRIVILEGES ON myapp.* TO 'app_admin'@'localhost';

-- FLUSH
FLUSH PRIVILEGES;
```

### Laravel-de Multiple Connections

```php
// config/database.php
'mysql_read' => [
    'username' => env('DB_READ_USERNAME'),  // app_reader
    'password' => env('DB_READ_PASSWORD'),
],
'mysql_write' => [
    'username' => env('DB_WRITE_USERNAME'), // app_writer
    'password' => env('DB_WRITE_PASSWORD'),
],
```

---

## Data Encryption

### At Rest (Disk-de)

```sql
-- MySQL: InnoDB tablespace encryption
ALTER TABLE users ENCRYPTION = 'Y';

-- PostgreSQL: pgcrypto extension
CREATE EXTENSION pgcrypto;
```

### In Transit (Network-de)

```ini
# MySQL: SSL/TLS
[mysqld]
require_secure_transport = ON
ssl-ca = /path/to/ca.pem
ssl-cert = /path/to/server-cert.pem
ssl-key = /path/to/server-key.pem
```

```php
// Laravel SSL connection
'mysql' => [
    'options' => [
        PDO::MYSQL_ATTR_SSL_CA => '/path/to/ca.pem',
    ],
],
```

### Application-Level Encryption

```php
// Laravel: Encrypted columns
use Illuminate\Database\Eloquent\Casts\Attribute;

class User extends Model
{
    protected $casts = [
        'ssn' => 'encrypted',           // Avtomatik encrypt/decrypt
        'credit_card' => 'encrypted',
    ];
}

// Manual encryption
use Illuminate\Support\Facades\Crypt;

$encrypted = Crypt::encryptString('4111-1111-1111-1111');
$decrypted = Crypt::decryptString($encrypted);
```

**Muhum:** Encrypted sutunlari search/index etmek mumkun deyil! Hash istifade et:

```php
// Searchable encryption pattern
class User extends Model
{
    public function setSsnAttribute($value)
    {
        $this->attributes['ssn_encrypted'] = Crypt::encryptString($value);
        $this->attributes['ssn_hash'] = hash('sha256', $value); // Search ucun
    }
}

// Axtaris:
User::where('ssn_hash', hash('sha256', $searchSsn))->first();
```

---

## Sensitive Data Protection

### Password Hashing

```php
// YANLIS
$password = md5($input);          // Zeif, rainbow table
$password = sha1($input);         // Zeif
$password = sha256($input);       // Salt yoxdur

// DOGRU
$hash = password_hash($input, PASSWORD_BCRYPT);          // bcrypt
$hash = password_hash($input, PASSWORD_ARGON2ID);        // argon2id (en yaxsi)

// Yoxlama
if (password_verify($input, $hash)) {
    // Dogru password
}

// Laravel (avtomatik)
$user->password = Hash::make($input); // bcrypt default
Hash::check($input, $user->password); // Yoxlama
```

### PII (Personal Identifiable Information)

```php
// Logging-de PII olmamalidir!
// YANLIS:
Log::info('User login', ['email' => $user->email, 'ip' => $request->ip()]);

// DOGRU:
Log::info('User login', ['user_id' => $user->id, 'ip' => $request->ip()]);
```

---

## Audit Logging

```sql
-- Audit table
CREATE TABLE audit_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    table_name VARCHAR(100) NOT NULL,
    record_id BIGINT NOT NULL,
    action ENUM('INSERT', 'UPDATE', 'DELETE') NOT NULL,
    old_values JSON,
    new_values JSON,
    user_id BIGINT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_table_record (table_name, record_id),
    INDEX idx_audit_created (created_at)
);
```

```php
// Laravel: Model event ile audit
trait Auditable
{
    protected static function bootAuditable()
    {
        static::updated(function ($model) {
            AuditLog::create([
                'table_name' => $model->getTable(),
                'record_id' => $model->getKey(),
                'action' => 'UPDATE',
                'old_values' => $model->getOriginal(),
                'new_values' => $model->getAttributes(),
                'user_id' => auth()->id(),
                'ip_address' => request()->ip(),
            ]);
        });
    }
}

class User extends Model
{
    use Auditable;
}
```

---

## Backup Security

```bash
# Encrypted backup
mysqldump -u root -p myapp | gzip | openssl enc -aes-256-cbc -salt -out backup.sql.gz.enc

# Decrypt
openssl enc -aes-256-cbc -d -in backup.sql.gz.enc | gunzip | mysql -u root -p myapp
```

---

## Interview suallari

**Q: SQL injection-u nece qarsisin alirsan?**
A: 1) Prepared statements (parametrized queries) - #1 qayda. 2) ORM istifade et (Eloquent). 3) Raw query-lerde binding istifade et. 4) Input validation (whitelist approach). 5) Least privilege DB user. Hec vaxt user input-u birbaşa SQL-e qosma.

**Q: Database password-lari nece saxlayirsan?**
A: bcrypt ve ya argon2id ile hash et. MD5/SHA heç vaxt istifade etmə. password_hash() / password_verify() istifade et. Salt avtomatik elave olunur. Rainbow table attack-dan qoruyur.

**Q: Production DB-ye kim erise bilmeli?**
A: Application: minimum lazimi icaze (CRUD). Developer-ler: read-only access (production-da). DBA: full access (amma audit olunur). CI/CD: yalniz migration ucun. Hec kim production-da manual UPDATE/DELETE etmemelidir (meqbul audit ve approval olmadan).
