# Secrets Management (Şifrələrin və Həssas Məlumatların İdarəsi)

## Nədir? (What is it?)

Secrets management – API açarları, şifrələr, TLS sertifikatları, token-lər və digər həssas məlumatların təhlükəsiz saxlanması, ötürülməsi və istifadəsi prosesidir. Düzgün secrets management vacibdir: kod tarixçəsinə (Git) secret düşməməlidir, production secrets dev secrets-dən ayrı olmalıdır, encryption at-rest və in-transit təmin edilməlidir. Əsas alətlər: HashiCorp Vault, AWS Secrets Manager, environment variables, .env files.

## Əsas Konseptlər (Key Concepts)

### Environment Variables

```bash
# ENV variables = process-ə ötürülən konfiqurasiya
# 12-Factor App prinsipi: config environment-də

# Linux-da
export DB_PASSWORD="secret123"
echo $DB_PASSWORD

# Process environment görmək
cat /proc/$PID/environ | tr '\0' '\n'

# Dəyişikliyi persistent etmək
# Bash: ~/.bashrc, ~/.profile
# Systemd: /etc/systemd/system/service.service
[Service]
Environment="DB_HOST=localhost"
Environment="DB_PASSWORD=secret"
EnvironmentFile=/etc/myapp/env

# Docker
docker run -e DB_PASSWORD=secret myapp
docker run --env-file .env myapp

# Kubernetes (etcd-də encrypt edilməlidir)
apiVersion: v1
kind: Secret
metadata:
  name: laravel-secrets
type: Opaque
data:
  DB_PASSWORD: c2VjcmV0MTIz    # base64 encoded
stringData:
  APP_KEY: "plain-text"         # K8s base64-ə çevirir

# Mənfiləri:
# - Process list-də görünə bilər (ps auxe)
# - Child process-lərə inherit olur
# - Logging-də görünə bilər
# - Memory dump-da qala bilər
```

### .env Files (dotenv)

```bash
# .env = Key=Value format faylı, development üçün
# Git-ə commit EDİLMƏMƏLİDİR

# .env nümunəsi
APP_NAME="Laravel App"
APP_ENV=production
APP_KEY=base64:YWJjZGVmZ2hpams=
APP_DEBUG=false
APP_URL=https://example.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=root
DB_PASSWORD=supersecret

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# .gitignore
echo ".env" >> .gitignore
echo ".env.local" >> .gitignore
echo ".env.*.local" >> .gitignore

# .env.example (git-ə commit OLMALIDIR)
# Template, real qiymətlər yox
APP_KEY=
DB_PASSWORD=
REDIS_PASSWORD=

# Secret-lərin taranması (yanlışlıqla commit olunsa)
# git-secrets
git secrets --install
git secrets --register-aws
git secrets --scan

# gitleaks
gitleaks detect --source . --verbose
gitleaks protect --staged

# trufflehog
trufflehog git file://. --only-verified
```

### HashiCorp Vault

```bash
# Vault = centralized secrets management
# Features:
# - Dynamic secrets (short-lived credentials)
# - Encryption as a service
# - PKI certificate management
# - Leasing and renewal
# - Audit logging

# Vault server
vault server -dev          # Dev mode (yalnız test)
export VAULT_ADDR='http://127.0.0.1:8200'
vault operator init        # Production setup

# Secret engine-lər
vault secrets enable -path=kv kv-v2
vault secrets enable -path=database database
vault secrets enable -path=aws aws
vault secrets enable -path=pki pki

# KV secret yazmaq/oxumaq
vault kv put kv/laravel/production \
    db_password=supersecret \
    app_key=base64:xyz

vault kv get kv/laravel/production
vault kv get -field=db_password kv/laravel/production
vault kv get -format=json kv/laravel/production

# Secret versioning (KV v2)
vault kv put kv/laravel/prod db_password=v2secret
vault kv get -version=1 kv/laravel/prod
vault kv rollback -version=1 kv/laravel/prod

# Dynamic database credentials
vault write database/config/mysql-prod \
    plugin_name=mysql-database-plugin \
    connection_url="{{username}}:{{password}}@tcp(127.0.0.1:3306)/" \
    allowed_roles="laravel" \
    username="root" \
    password="rootpass"

vault write database/roles/laravel \
    db_name=mysql-prod \
    creation_statements="CREATE USER '{{name}}'@'%' IDENTIFIED BY '{{password}}'; \
        GRANT SELECT, INSERT, UPDATE, DELETE ON laravel.* TO '{{name}}'@'%';" \
    default_ttl="1h" \
    max_ttl="24h"

# Dynamic credentials al
vault read database/creds/laravel
# Output: username=v-root-laravel-abc, password=xyz123, lease_id=...

# Authentication methods
# Token, Userpass, LDAP, AWS, Kubernetes, GitHub, JWT/OIDC
vault auth enable userpass
vault write auth/userpass/users/admin password=admin policies=admin

# Policies (ACL)
cat > laravel-policy.hcl <<EOF
path "kv/data/laravel/*" {
    capabilities = ["read"]
}
path "database/creds/laravel" {
    capabilities = ["read"]
}
EOF

vault policy write laravel-policy laravel-policy.hcl
```

### AWS Secrets Manager

```bash
# AWS Secrets Manager = managed secrets service
# Features:
# - Auto-rotation (RDS, Redshift üçün built-in)
# - Encryption at-rest (KMS)
# - IAM integration
# - Audit via CloudTrail

# Secret yaratmaq
aws secretsmanager create-secret \
    --name laravel/production/db \
    --description "Laravel prod database" \
    --secret-string '{
        "username": "laravel",
        "password": "supersecret",
        "host": "rds.example.com",
        "dbname": "laravel"
    }'

# Secret oxumaq
aws secretsmanager get-secret-value \
    --secret-id laravel/production/db \
    --query SecretString --output text | jq

# Secret dəyişdirmək
aws secretsmanager update-secret \
    --secret-id laravel/production/db \
    --secret-string '{"password": "newpass"}'

# Auto-rotation (Lambda function ilə)
aws secretsmanager rotate-secret \
    --secret-id laravel/production/db \
    --rotation-lambda-arn arn:aws:lambda:... \
    --rotation-rules AutomaticallyAfterDays=30

# IAM policy
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Action": [
                "secretsmanager:GetSecretValue",
                "secretsmanager:DescribeSecret"
            ],
            "Resource": "arn:aws:secretsmanager:*:*:secret:laravel/*"
        }
    ]
}

# AWS Systems Manager Parameter Store (Alternativ, daha ucuz)
aws ssm put-parameter \
    --name /laravel/prod/db_password \
    --value "supersecret" \
    --type SecureString \
    --key-id alias/aws/ssm

aws ssm get-parameter \
    --name /laravel/prod/db_password \
    --with-decryption
```

### Encryption

```bash
# Symmetric encryption (AES) - eyni açar encrypt/decrypt
openssl enc -aes-256-cbc -salt -in file.txt -out file.enc -k password
openssl enc -d -aes-256-cbc -in file.enc -out file.txt -k password

# Asymmetric encryption (RSA) - public/private key
openssl genrsa -out private.pem 2048
openssl rsa -in private.pem -pubout -out public.pem

openssl rsautl -encrypt -pubin -inkey public.pem -in file.txt -out file.enc
openssl rsautl -decrypt -inkey private.pem -in file.enc -out file.txt

# PGP/GPG
gpg --gen-key
gpg --encrypt --recipient user@example.com file.txt
gpg --decrypt file.txt.gpg

# SOPS (Mozilla, for encrypting config files)
brew install sops
sops --encrypt --pgp ABC123 .env > .env.enc
sops --decrypt .env.enc > .env

# git-crypt (repo-da encrypted fayllar)
git-crypt init
echo ".env filter=git-crypt diff=git-crypt" > .gitattributes
git-crypt add-gpg-user user@example.com
```

## Praktiki Nümunələr (Practical Examples)

### Laravel + Vault Agent

```bash
# Vault Agent = sidecar process, secret-ləri fayla yazır

cat > /etc/vault-agent/config.hcl <<EOF
auto_auth {
  method "approle" {
    config = {
      role_id_file_path = "/etc/vault/role-id"
      secret_id_file_path = "/etc/vault/secret-id"
    }
  }
  sink "file" {
    config = {
      path = "/tmp/vault-token"
    }
  }
}

template {
  source = "/etc/vault-agent/env.tpl"
  destination = "/var/www/laravel/.env"
  perms = 0600
  command = "systemctl reload php8.2-fpm"
}

vault {
  address = "https://vault.example.com:8200"
}
EOF

cat > /etc/vault-agent/env.tpl <<EOF
APP_KEY={{ with secret "kv/laravel/prod" }}{{ .Data.data.app_key }}{{ end }}
DB_PASSWORD={{ with secret "kv/laravel/prod" }}{{ .Data.data.db_password }}{{ end }}
{{ with secret "database/creds/laravel" }}
DB_USERNAME={{ .Data.username }}
DB_DYNAMIC_PASSWORD={{ .Data.password }}
{{ end }}
EOF

vault agent -config=/etc/vault-agent/config.hcl
```

### Docker secrets

```yaml
# docker-compose.yml
version: '3.8'

services:
  laravel:
    image: laravel:latest
    secrets:
      - db_password
      - app_key
    environment:
      DB_PASSWORD_FILE: /run/secrets/db_password

secrets:
  db_password:
    file: ./secrets/db_password.txt
  app_key:
    external: true
```

```php
// Laravel-də secret file oxumaq
<?php
$dbPassword = trim(file_get_contents('/run/secrets/db_password'));
```

## PHP/Laravel ilə İstifadə

### Laravel .env best practices

```php
// config/database.php - .env-dən istifadə
'connections' => [
    'mysql' => [
        'host' => env('DB_HOST', '127.0.0.1'),
        'password' => env('DB_PASSWORD', ''),
    ],
],

// VACIB: config file-da env() BİRBAŞA istifadə et
// Controller/service-də env() İSTİFADƏ ETMƏ
// Səbəb: php artisan config:cache-dən sonra env() null qaytarır

// Yanlış:
class UserController {
    public function __construct() {
        $key = env('APP_KEY');  // null after config:cache!
    }
}

// Doğru:
class UserController {
    public function __construct() {
        $key = config('app.key');
    }
}

// Config cache
php artisan config:cache     // Production
php artisan config:clear     // Development
```

### Laravel Config caching

```bash
# Config cache strukturu:
# bootstrap/cache/config.php

# Production deploy prosesi:
php artisan optimize       # config:cache + route:cache + view:cache

# Config-də secret istifadəsi
# config/services.php
return [
    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
    ],
];

# Controller-də
$stripeKey = config('services.stripe.key');
```

### Laravel + AWS Secrets Manager

```php
// Laravel AppServiceProvider-də secrets yüklə
namespace App\Providers;

use Aws\SecretsManager\SecretsManagerClient;
use Illuminate\Support\ServiceProvider;

class SecretsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (app()->environment('production')) {
            $this->loadSecretsFromAws();
        }
    }
    
    protected function loadSecretsFromAws(): void
    {
        try {
            $client = new SecretsManagerClient([
                'region' => env('AWS_DEFAULT_REGION'),
                'version' => 'latest',
            ]);
            
            $result = $client->getSecretValue([
                'SecretId' => 'laravel/production/all',
            ]);
            
            $secrets = json_decode($result['SecretString'], true);
            
            foreach ($secrets as $key => $value) {
                config(['database.connections.mysql.password' => $secrets['DB_PASSWORD'] ?? null]);
                config(['services.stripe.secret' => $secrets['STRIPE_SECRET'] ?? null]);
                // ...
            }
        } catch (\Exception $e) {
            Log::critical('Failed to load secrets', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}
```

### Laravel Encryption

```php
use Illuminate\Support\Facades\Crypt;

// Encrypt
$encrypted = Crypt::encrypt('sensitive data');
$encrypted = Crypt::encryptString('simple string');

// Decrypt
$decrypted = Crypt::decrypt($encrypted);
$decrypted = Crypt::decryptString($encrypted);

// Cast encrypted in model
class User extends Model
{
    protected $casts = [
        'ssn' => 'encrypted',        // Database-də encrypted saxlanılır
        'credit_card' => 'encrypted',
    ];
}

// Hashing (password üçün, one-way)
use Illuminate\Support\Facades\Hash;

$hashed = Hash::make('password123');
Hash::check('password123', $hashed);  // true
Hash::needsRehash($hashed);            // Algorithm yeniləməsi lazımdırmı?
```

### Laravel Sanctum Token

```php
// Personal access token yaratmaq
$token = $user->createToken('api-token', ['read'])->plainTextToken;

// Token təhlükəsizliyi
// - HTTPS only
// - Short-lived (expires_at)
// - Scope based (abilities)
// - Revocable (user->tokens()->delete())

$user->tokens()->where('name', 'old-token')->delete();
```

## Interview Sualları (5-10 Q&A)

**S1: Niyə .env faylını git-ə commit etmək olmaz?**
C: .env faylında production şifrələr, API açarları, database credentials kimi həssas məlumatlar olur. Git tarixçəsinə düşsə, bütün repo kopyalarına yayılır və silmək çətin olur. Repo public olsa, secret-lər hər kəsə açıq olur. Yalnız `.env.example` (template) commit olunur. Yanlışlıqla commit olunsa dərhal secret-ləri rotate etmək lazımdır.

**S2: Laravel-də env() və config() fərqi nədir?**
C: `env()` – .env faylından birbaşa oxuyur, yalnız bootstrap zamanı və config file-larda istifadə olunmalıdır. `config()` – cached config-dən oxuyur. Production-da `php artisan config:cache` sonrası `env()` null qaytarır, çünki .env oxunmur artıq. Qayda: config/*.php-də `env()`, digər yerlərdə `config()`.

**S3: HashiCorp Vault-un əsas üstünlükləri nələrdir?**
C: (1) Dynamic secrets – istifadə zamanı short-lived credentials yaradılır (məs. DB user); (2) Centralized management – bütün secret-lər bir yerdə; (3) Audit logging – kim hansı secret-i oxuyub; (4) Encryption as a service – app-lər özü encrypt etmir; (5) Auto-rotation; (6) Fine-grained access (policies); (7) Multiple auth methods (Kubernetes, AWS, LDAP).

**S4: AWS Secrets Manager və Parameter Store fərqi nədir?**
C: Secrets Manager – auto-rotation dəstəyi (RDS üçün built-in), daha baha ($0.40/secret/ay), versiyalanma built-in, cross-region replication. Parameter Store – ucuz (standard parameter pulsuz), sadə use case üçün, rotation manual, secure string encrypted. Kiçik layihələr üçün Parameter Store kifayət, enterprise üçün Secrets Manager.

**S5: Secret yanlışlıqla Git-ə commit edilsə nə etmək lazımdır?**
C: (1) Dərhal secret-i rotate edin (köhnə qiymət kompromise sayılır); (2) git history-dən silmək: `git filter-repo` və ya `BFG Repo Cleaner`; (3) Force push (əgər private repo, yaxşı koordinasiya ilə); (4) Public repo-da history təmizləmək asan deyil – assume secret kompromise olub. Pre-commit hook və secret scanner (gitleaks) ilə qarşısını alın.

**S6: Dynamic secrets və static secrets fərqi?**
C: Static secret – əvvəlcədən təyin edilir, uzun müddət dəyişməz (məs. API key). Dynamic secret – istifadə zamanı yaradılır, qısa ömürlü (məs. 1 saat), avtomatik revoke. Dynamic daha təhlükəsizdir – kompromis zamanı təsir məhduddur. Vault database dynamic credentials yaxşı nümunədir.

**S7: Docker-də secret necə idarə etmək lazımdır?**
C: Best practices: (1) ENV variables yerinə Docker secrets və ya external secret manager; (2) Image-də HARDCODE etməyin (Dockerfile-da yazmayın); (3) docker-compose `secrets` section istifadə edin – fayla mount olur; (4) Kubernetes-də Secrets resource + ExternalSecrets Operator (Vault/AWS inteqrasiya); (5) Image scanning ilə yoxlayın (image-də secret qalmasın).

**S8: Kubernetes Secret-lər nə qədər təhlükəsizdir?**
C: Default Kubernetes Secrets base64 encoded-dır (encryption deyil). etcd-də encryption at-rest aktivləşdirilməlidir. RBAC ilə Secret-lərə giriş məhdudlaşdırılmalıdır. ExternalSecrets Operator ilə Vault/AWS Secrets Manager-dən dinamik gətirmək daha yaxşıdır. Sealed Secrets ilə repo-da encrypt saxlamaq olar.

**S9: Password və API key rotation niyə vacibdir?**
C: Kompromis riskini azaldır – secret sızdırılsa, ömrü qısa olur. Compliance tələbləri (PCI-DSS, SOC 2) dövri rotation tələb edir. Ayrılan işçilərin access-i avtomatik ləğv olur. Auto-rotation (Vault, AWS SM) ilə app restart olmadan mümkündür. Zero-downtime rotation üçün app-lər iki secret-i paralel dəstəkləməlidir.

**S10: Encryption at-rest və in-transit nədir?**
C: Encryption at-rest – saxlanma yerində şifrələnmə (disk, database, S3). Məlumat oğurlansa oxuna bilmir. AWS-də KMS ilə EBS, RDS, S3 encrypt olunur. Encryption in-transit – şəbəkə üzərindən ötürülmə zamanı (TLS/SSL). HTTPS, TLS database connections. Hər ikisi vacibdir – defence in depth prinsipi.

## Best Practices

1. **Heç vaxt secret-i koda hardcode etmə**: Yalnız environment variables və ya secret manager istifadə et.
2. **.env git-də yox**: `.gitignore`-a əlavə et, `.env.example` template saxla.
3. **Secret scanning**: Pre-commit hook (gitleaks, git-secrets) ilə qarşısını al.
4. **Environment separation**: Dev/staging/prod secrets tamamilə ayrı olsun.
5. **Least privilege**: Hər serviceyə yalnız lazım olan secret-ə icazə ver.
6. **Rotation policy**: Vacib secret-ləri (DB password, API keys) mütəmadi rotate et.
7. **Audit logging**: Hər secret access-i log et (kim, nə vaxt, hansı secret).
8. **Encryption in-transit**: Secret-lər həmişə HTTPS/TLS üzərindən ötürülsün.
9. **Encryption at-rest**: KMS, Vault ilə saxlanma yerində şifrələ.
10. **Short-lived credentials**: Dynamic secrets, JWT token-lər qısa müddətli olsun.
11. **Laravel config:cache**: Production-da optimize üçün config cache istifadə et, env() config-dən kənar istifadə etmə.
12. **Application-level encryption**: Həssas fielod-ları (SSN, kredit kart) Laravel encrypted cast ilə şifrələ.
13. **Secret manager inteqrasiya**: AWS Secrets Manager, Vault ilə Laravel-i inteqrasiya et.
14. **Break-glass procedure**: Emergency access prosedurunu dokumentləşdir.
15. **Regular secret audit**: İstifadə olunmayan secret-ləri silin, expired olanları yenilə.
